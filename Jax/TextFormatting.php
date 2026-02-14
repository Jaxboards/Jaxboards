<?php

declare(strict_types=1);

namespace Jax;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function htmlspecialchars;
use function implode;
use function is_array;
use function mb_strtolower;
use function mb_substr;
use function nl2br;
use function parse_url;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function str_contains;
use function str_ireplace;
use function str_replace;
use function trim;

use const ENT_QUOTES;

final readonly class TextFormatting
{
    public function __construct(
        private BBCode $bbCode,
        public TextRules $rules,
        private Template $template,
        private Request $request,
        private User $user,
    ) {}

    /**
     * Replaces all URLs with bbcode [url]s so that they become actual links.
     */
    public function linkify(string $text): string
    {
        return (string) preg_replace_callback('/(^|\s)(https?:\/\/[^\s\)\(<>]+)/u', $this->linkifyCallback(...), $text);
    }

    /**
     * Replace all URLs that link to video services with a [video] bbcode.
     */
    public function videoify(string $text): string
    {
        return (string) preg_replace_callback(
            '/(^|\s)(https?:\/\/[^\s\)\(<>]+)/u',
            $this->videoifyCallback(...),
            $text,
        );
    }

    public function blockhtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES);
    }

    public function emotes(string $text): string
    {
        $emoticonLimit = 15;
        $emotes = $this->rules->getEmotes();

        if ($emotes === []) {
            return $text;
        }

        $emotesEscaped = implode('|', array_map(static fn(string $emote): string => preg_quote(
            $emote,
            '/',
        ), array_keys($emotes)));
        $text = (string) preg_replace_callback(
            "/(\\s)({$emotesEscaped})/",
            $this->emoteCallback(...),
            ' ' . $text,
            $emoticonLimit,
        );

        return mb_substr($text, 1);
    }

    /**
     * Converts text into a URL slug
     * Ex: "Welcome to Jaxboards!" becomes "welcome-to-jaxboards".
     */
    public function slugify(?string $text): string
    {
        $slug = (string) preg_replace('/\W+/', '-', $text ?? '');
        $slug = mb_substr($slug, 0, 50);
        $slug = trim($slug, '-');

        return mb_strtolower($slug);
    }

    /**
     * Handles badword replacements.
     */
    public function wordFilter(?string $text): string
    {
        if ($this->user->get()->nowordfilter !== 0) {
            return (string) $text;
        }

        $badwords = $this->rules->getBadwords();

        return str_ireplace(array_keys($badwords), array_values($badwords), (string) $text);
    }

    /**
     * @see BBCode::startCodeTags
     *
     * @return array{string,array<array<string>>}
     */
    public function startCodeTags(string $text): array
    {
        return $this->bbCode->startCodeTags($text);
    }

    /**
     * Variant of finishCodeTags that returns bbcode instead of html.
     *
     * @param array<array<string>> $codes
     */
    public function finishCodeTagsBB(string $text, array $codes): string
    {
        foreach ($codes as $index => [, $language, $code]) {
            $text = str_replace("[code]{$index}[/code]", "[code{$language}]{$code}[/code]", $text);
        }

        return $text;
    }

    public function textOnly(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        for ($i = 0; $i < 10; ++$i) {
            $text = (string) preg_replace('/\[(\w+)[^\]]*\](.*)\[\/\1\]/Us', '$2', $text, count: $count);
            if ($count === 0) {
                break;
            }
        }

        return $text;
    }

    /**
     * Does pretty much all of the post formatting.
     * BBCodes, badwords, HTML, everything you could want.
     */
    public function theWorks(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        [$text, $codes] = $this->startCodeTags($text);

        $text = $this->blockhtml($text);
        $text = nl2br($text, false);
        $text = $this->emotes($text);
        $text = $this->bbCode->toHTML($text, $codes);

        return $this->wordFilter($text);
    }

    /**
     * Variant of "theWorks" that does not produce block level elements.
     * Only inline (<em>, <strong>, etc).
     */
    public function theWorksInline(string $text): string
    {
        $text = nl2br($this->blockhtml($text), false);
        $text = $this->emotes($text);
        $text = $this->bbCode->toInlineHTML($text);

        return $this->wordFilter($text);
    }

    /**
     * @param array<string> $match
     */
    private function linkifyCallback(array $match): string
    {
        [, $before, $stringURL] = $match;

        $parts = parse_url($stringURL);

        $inner = null;

        if (
            is_array($parts)
            && array_key_exists('host', $parts)
            && $parts['host'] === $this->request->server('HTTP_HOST')
        ) {
            $inner = match (true) {
                (bool) preg_match('/pid=(\d+)/', $parts['query'] ?? '', $postMatch) => "Post #{$postMatch[1]}",
                (bool) preg_match('/^\/topic\/(\d+)/', $parts['path'] ?? '', $topicMatch) => "Topic #{$topicMatch[1]}",
                default => null,
            };

            $stringURL = ($parts['path'] ?? '') . (array_key_exists('query', $parts) ? "?{$parts['query']}" : '');
        }

        $inner ??= $stringURL;

        return "{$before}[url={$stringURL}]{$inner}[/url]";
    }

    /**
     * @param array<string> $match
     */
    private function videoifyCallback(array $match): string
    {
        [, $before, $stringURL] = $match;

        $serviceURLs = [
            'https://www.youtube.com/watch',
            'https://youtu.be/',
        ];
        foreach ($serviceURLs as $serviceURL) {
            if (str_contains($stringURL, $serviceURL)) {
                return "{$before}[video]{$stringURL}[/video]";
            }
        }

        // return unmodified
        return "{$before}{$stringURL}";
    }

    /**
     * @param array<string> $match
     */
    private function emoteCallback(array $match): string
    {
        [, $space, $emoteText] = $match;

        return $space
        . $this->template->render('bbcode/emote', [
            'image' => $this->rules->getEmotes()[$emoteText],
            'text' => $emoteText,
        ]);
    }
}
