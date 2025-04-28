<?php

declare(strict_types=1);

namespace Jax;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function dirname;
use function file_exists;
use function highlight_string;
use function htmlspecialchars;
use function implode;
use function in_array;
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

    /**
     * @var array<string,string>
     */
    private array $badwords = [];

    /**
     * Map of emojis to their URL replacements.
     * This is a merge of both emote pack and custom emotes.
     *
     * @var array<string,string>
     */
    private array $emotes = [];

    private ?string $emotePack = null;

    /**
     * Emotes from the emote pack.
     *
     * @var array<string, string>
     */
    private array $emotePackRules = [];

    public function __construct(
        private readonly Config $config,
        private readonly BBCode $bbCode,
        private readonly Database $database,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly User $user,
    ) {
        // Preload custom rules and emojis
        $this->getEmotePackRules();
        $this->getCustomRules();
    }

    public function getCustomRules(): void
    {
        $result = $this->database->safeselect(
            [
                'id',
                'type',
                'needle',
                'replacement',
                'enabled',
            ],
            'textrules',
            '',
        );
        while ($rule = $this->database->arow($result)) {
            if ($rule['type'] === 'emote') {
                $this->emotes[$rule['needle']] = $rule['replacement'];

                break;
            }

            if ($rule['type'] === 'badword') {
                $this->badwords[$rule['needle']] = $rule['replacement'];

                break;
            }
        }
    }

    /**
     * Get emote pack + custom rules.
     *
     * @return array<string,string> map with keys of emojis to their URL replacements
     */
    public function getEmoteRules(): array
    {
        return $this->emotes;
    }

    /**
     * Get emote pack rules.
     *
     * @return array<string,string> map with keys of emojis to their URL replacements
     */
    public function getEmotePackRules(?string $emotePack = null): array
    {
        $emotePack = $emotePack ?: $this->config->getSetting('emotepack');

        if ($this->emotePack === $emotePack && $this->emotePackRules) {
            return $this->emotePackRules;
        }

        // Load emoticon pack.
        $emotes = [];
        if ($emotePack !== null) {
            $this->emotePack = $emotePack;
            $rulesPath = dirname(__DIR__) . '/emoticons/' . $emotePack . '/rules.php';

            if (file_exists($rulesPath)) {
                require_once $rulesPath;

                if (!isset($rules)) {
                    return $emotes;
                }

                $this->emotePackRules = $rules;

                foreach ($rules as $emote => $path) {
                    $emotes[$emote] = "emoticons/{$emotePack}/{$path}";
                }
            }
        }

        $this->emotes = $emotes;

        return $this->emotePackRules = $emotes;
    }

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
        $emotes = $this->emotes;

        if (!$emotes) {
            return $text;
        }

        $text = preg_replace_callback(
            '@(\s)(' . implode('|', array_map(static fn(string $emote): string => preg_quote($emote, '@'), array_keys($emotes))) . ')@',
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

        return str_ireplace(
            array_keys($this->badwords),
            array_values($this->badwords),
            $text,
        );
    }

    /**
     * Replaces all code tags with an ID.
     * This essentially pulls all code blocks out of the input text so that code
     * is not treated with badword, emote, and bbcode replacements.
     * finishCodeTags puts the code back into the post.
     *
     * @return array{string,array{array<string>,array<string>}}
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
     */
    public function finishCodeTags(
        string $text,
        array $codes,
        bool $returnbb = false,
    ): string {
        foreach (array_keys($codes[0]) as $index) {
            $language = $codes[1][$index];
            $code = $codes[2][$index];

            if (!$returnbb) {
                $code = $language === '=php' ? highlight_string($code, true) : preg_replace(
                    "@([ \r\n]|^) @m",
                    '$1&nbsp;',
                    $this->blockhtml($code),
                );
            }

            $text = str_replace(
                "[code]{$index}[/code]",
                $returnbb
                    ? "[code{$language}]{$code}[/code]"
                    : "<div class=\"bbcode code {$language}\">{$code}</div>",
                $text,
            );
        }

        return $text;
    }

    public function textonly(string $text): ?string
    {
        while (($cleaned = preg_replace('@\[(\w+)[^\]]*\]([\w\W]*)\[/\1\]@U', '$2', (string) $text)) !== $text) {
            $text = $cleaned;
        }

        return $text;
    }

    public function theworks(string $text, array $cfg = []): string
    {
        $replaceBBCode = !array_key_exists('nobb', $cfg);
        $minimalBBCode = array_key_exists('minimalbb', $cfg);

        if ($replaceBBCode && !$minimalBBCode) {
            [$text, $codes] = $this->startCodeTags($text);
        }

        $text = nl2br($this->blockhtml($text));

        if (!array_key_exists('noemotes', $cfg)) {
            $text = $this->emotes($text);
        }

        if ($replaceBBCode) {
            $text = $minimalBBCode
                ? $this->bbCode->toInlineHTML($text)
                : $this->bbCode->toHTML($text, $minimalBBCode);
        }

        if ($replaceBBCode && !$minimalBBCode) {
            $text = $this->finishcodetags($text, $codes);
        }

        if ($replaceBBCode && !$minimalBBCode) {
            $text = $this->attachments($text);
        }

        return $this->wordfilter($text);
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    private function linkifyCallback(array $match): string
    {
        $url = parse_url((string) $match[2]);
        if (!$url['fragment'] && $url['query']) {
            $url['fragment'] = $url['query'];
        }

        if ($url['host'] === $_SERVER['HTTP_HOST'] && $url['fragment']) {
            if (preg_match('@act=vt(\d+)@', $url['fragment'], $match)) {
                $nice = preg_match('@pid=(\d+)@', $url['fragment'], $match2)
                    ? 'Post #' . $match2[1]
                    : 'Topic #' . $match[1];
            }

            $match[2] = '?' . $url['fragment'];
        }

        return $match[1] . '[url=' . $match[2] . ']' . ($nice ?: $match[2]) . '[/url]';
    }

    private function emoteCallback(array $match): string
    {
        [, $space, $emoteText] = $match;

        return $space . '<img src="' . $this->emotes[$emoteText] . '" alt="' . $this->blockhtml($emoteText) . '"/>';
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
            'WHERE `id`=?',
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

        if (!in_array($ext, $this->config->getSetting('images') ?? [], true)) {
            $ext = '';
        }

        if ($ext !== '') {
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
