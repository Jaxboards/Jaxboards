<?php

declare(strict_types=1);

namespace Jax;

use Jax\Page\TextRules;

use function array_keys;
use function array_map;
use function array_values;
use function highlight_string;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_array;
use function mb_substr;
use function nl2br;
use function parse_url;
use function pathinfo;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function str_ireplace;
use function str_replace;
use function urlencode;

use const ENT_QUOTES;
use const PATHINFO_EXTENSION;

final class TextFormatting
{
    /**
     * @var array<string,array<string,mixed>>
     */
    private array $attachmentData;

    public function __construct(
        private readonly Config $config,
        private readonly BBCode $bbCode,
        private readonly Database $database,
        private readonly DomainDefinitions $domainDefinitions,
        public readonly TextRules $rules,
        private readonly User $user,
    ) {}

    /**
     * Replaces all URLs with bbcode [url]s so that they become actual links.
     */
    public function linkify(string $text): ?string
    {
        return preg_replace_callback(
            '@(^|\s)(https?://[^\s\)\(<>]+)@',
            $this->linkifyCallback(...),
            $text,
        );
    }

    public function blockhtml(string $text): string
    {
        // Fix for template conditionals.
        return str_replace('{if', '&#123;if', htmlspecialchars($text, ENT_QUOTES));
    }

    public function emotes(string $text): string
    {
        $emoticonLimit = 15;
        $emotes = $this->rules->getEmotes();

        if (!$emotes) {
            return $text;
        }

        $emotesEscaped = array_map(
            static fn(string $emote): string => preg_quote($emote, '@'),
            array_keys($emotes),
        );
        $text = preg_replace_callback(
            '@(\s)(' . implode('|', $emotesEscaped) . ')@',
            $this->emoteCallback(...),
            ' ' . $text,
            $emoticonLimit,
        );

        return mb_substr((string) $text, 1);
    }

    /**
     * Handles badword replacements.
     */
    public function wordfilter(string $text): string
    {
        if ($this->user->get('nowordfilter')) {
            return $text;
        }

        $badwords = $this->rules->getBadwords();

        return str_ireplace(
            array_keys($badwords),
            array_values($badwords),
            $text,
        );
    }

    /**
     * Replaces all code tags with an ID.
     * This essentially pulls all code blocks out of the input text so that code
     * is not treated with badword, emote, and bbcode replacements.
     * finishCodeTags puts the code back into the post.
     *
     * @return array{string,array<array<string>>}
     */
    public function startCodeTags(string $text): array
    {
        preg_match_all('@\[code(=\w+)?\](.*?)\[/code\]@is', $text, $codes);
        foreach ($codes[0] as $key => $fullMatch) {
            $text = str_replace($fullMatch, "[code]{$key}[/code]", $text);
        }

        return [$text, $codes];
    }

    /**
     * Puts code blocks back into the post, and does code highlighting.
     * Currently only php is supported.
     *
     * @param array<array<string>> $codes
     */
    public function finishCodeTags(string $text, array $codes): string
    {
        foreach ($codes[1] as $index => $language) {
            $code = $codes[2][$index];

            $code = $language === '=php' ? highlight_string($code, true) : preg_replace(
                "@([ \r\n]|^) @m",
                '$1&nbsp;',
                $this->blockhtml($code),
            );

            $text = str_replace(
                "[code]{$index}[/code]",
                "<div class=\"bbcode code {$language}\">{$code}</div>",
                $text,
            );
        }

        return $text;
    }

    /**
     * Variant of finishCodeTags that returns bbcode instead of html.
     *
     * @param array<array<string>> $codes
     */
    public function finishCodeTagsBB(
        string $text,
        array $codes,
    ): string {
        foreach ($codes[1] as $index => $language) {
            $code = $codes[2][$index];

            $text = str_replace(
                "[code]{$index}[/code]",
                "[code{$language}]{$code}[/code]",
                $text,
            );
        }

        return $text;
    }

    public function textOnly(string $text): ?string
    {
        while (($cleaned = preg_replace('@\[(\w+)[^\]]*\](.*)\[/\1\]@Us', '$2', (string) $text)) !== $text) {
            $text = $cleaned;
        }

        return $text;
    }

    /**
     * Does pretty much all of the post formatting.
     * BBCodes, badwords, HTML, everything you could want.
     */
    public function theWorks(string $text): string
    {
        [$text, $codes] = $this->startCodeTags($text);

        $text = nl2br($this->blockhtml($text));
        $text = $this->emotes($text);
        $text = $this->bbCode->toHTML($text);
        $text = $this->finishCodeTags($text, $codes);
        $text = $this->attachments($text);

        return $this->wordfilter($text);
    }

    /**
     * Variant of "theWorks" that does not produce block level elements.
     * Only inline (<em>, <strong>, etc).
     */
    public function theWorksInline(string $text): string
    {
        $text = nl2br($this->blockhtml($text));
        $text = $this->emotes($text);
        $text = $this->bbCode->toInlineHTML($text);

        return $this->wordfilter($text);
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    private function linkifyCallback(array $match): string
    {
        [, $before, $stringURL] = $match;

        $parts = parse_url((string) $stringURL);

        $inner = null;

        if ($parts['host'] === $_SERVER['HTTP_HOST']) {
            $inner = match (true) {
                (bool) preg_match('@pid=(\d+)@', $parts['query'], $postMatch) => "Post #{$postMatch[1]}",
                (bool) preg_match('@act=vt(\d+)@', $parts['query'], $topicMatch) => "Topic #{$topicMatch[1]}",
                default => null,
            };


            $stringURL = "?{$parts['query']}";
        }

        $inner ??= $stringURL;

        return "{$before}[url={$stringURL}]{$inner}[/url]";
    }

    private function emoteCallback(array $match): string
    {
        [, $space, $emoteText] = $match;

        return "{$space}<img src='{$this->rules->getEmotes()[$emoteText]}' alt='{$this->blockhtml($emoteText)}' />";
    }

    private function attachments(string $text): null|array|string
    {
        return $text = preg_replace_callback(
            '@\[attachment\](\d+)\[/attachment\]@',
            $this->attachmentCallback(...),
            $text,
            20,
        );
    }

    /**
     * Given an attachment ID, gets the file data associated with it
     * Returns null if file not found.
     *
     * @return null|array<string, mixed>
     */
    private function getAttachmentData(string $fileId): ?array
    {
        if (isset($this->attachmentData[$fileId])) {
            return $this->attachmentData[$fileId];
        }

        $result = $this->database->safeselect(
            [
                'id',
                'name',
                'hash',
                'size',
                'downloads',
            ],
            'files',
            Database::WHERE_ID_EQUALS,
            $fileId,
        );
        $file = $this->database->arow($result);
        $this->database->disposeresult($result);

        return $this->attachmentData[$fileId] = $file;
    }

    private function attachmentCallback(array $match): string
    {
        $file = $this->getAttachmentData($match[1]);

        if (!$file) {
            return "Attachment doesn't exist";
        }

        $ext = (string) pathinfo($file['name'], PATHINFO_EXTENSION);
        $imageExtensions = $this->config->getSetting('images') ?? [];

        if (
            is_array($imageExtensions)
            && !in_array($ext, $imageExtensions, true)
        ) {
            $ext = null;
        }

        if ($ext !== null) {
            $attachmentURL = $this->domainDefinitions->getBoardPathUrl() . '/Uploads/' . $file['hash'] . '.' . $ext;

            return "<a href='{$attachmentURL}'>"
                . "<img src='{$attachmentURL}' alt='attachment' class='bbcodeimg' />"
                . '</a>';
        }

        return '<div class="attachment">'
            . '<a href="index.php?act=download&id='
            . $file['id'] . '&name=' . urlencode((string) $file['name']) . '" class="name">'
            . $file['name'] . '</a> Downloads: ' . $file['downloads'] . '</div>';
    }
}
