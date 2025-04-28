<?php

declare(strict_types=1);

namespace Jax;

use function array_keys;
use function array_values;
use function is_string;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function str_contains;
use function trim;

final class BBCode
{
    /**
     * @var array<string,string>
     */
    private array $bbcodes = [
        '@\[(bg|bgcolor|background)=(#?[\s\w\d]+)\](.*)\[/\1\]@Usi' => '<span style="background:$2">$3</span>',
        '@\[blink\](.*)\[/blink\]@Usi' => '<span style="text-decoration:blink">$1</span>',
        '@\[b\](.*)\[/b\]@Usi' => '<strong>$1</strong>',
        '@\[color=(#?[\s\w\d]+|rgb\([\d, ]+\))\](.*)\[/color\]@Usi' => '<span style="color:$1">$2</span>',
        '@\[font=([\s\w]+)](.*)\[/font\]@Usi' => '<span style="font-family:$1">$2</span>',
        '@\[i\](.*)\[/i\]@Usi' => '<em>$1</em>',
        '@\[spoiler\](.*)\[/spoiler\]@Usi' => '<span class="spoilertext">$1</span>',
        '@\[s\](.*)\[/s\]@Usi' => '<span style="text-decoration:line-through">$1</span>',
        // Consider adding nofollow if admin approval of new accounts is not enabled
        '@\[url=(http|ftp|\?|mailto:)([^\]]+)\](.+?)\[/url\]@i' => '<a href="$1$2">$3</a>',
        '@\[url\](http|ftp|\?)(.*)\[/url\]@Ui' => '<a href="$1$2">$1$2</a>',
        '@\[u\](.*)\[/u\]@Usi' => '<span style="text-decoration:underline">$1</span>',
    ];

    /**
     * @var array<string,string>
     */
    private array $extendedBBCodes = [
        '@\[h([1-5])\](.*)\[/h\1\]@Usi' => '<h$1>$2</h$1>',
        '@\[align=(center|left|right)\](.*)\[/align\]@Usi' => '<p style="text-align:$1">$2</p>',
        '@\[img(?:=([^\]]+|))?\]((?:http|ftp)\S+)\[/img\]@Ui' => '<img src="$2" title="$1" alt="$1" class="bbcodeimg" align="absmiddle" />',
    ];

    public function toHTML(string $text, $minimal = false): ?string
    {
        $text = $this->replaceWithRules($text, $this->bbcodes);

        if ($minimal) {
            return $text;
        }

        $text = $this->replaceWithRules($text, $this->extendedBBCodes);

        // [ul] and [ol]
        $text = $this->replaceWithCallback(
            $text,
            '@\[(ul|ol)\](.*)\[/\1\]@Usi',
            $this->bbcodeLICallback(...),
        );

        // [size]
        $text = $this->replaceWithCallback(
            $text,
            '@\[size=([0-4]?\d)(px|pt|em|)\](.*)\[/size\]@Usi',
            $this->bbcodeSizeCallback(...),
        );

        // [quote]
        $text = $this->replaceWithCallback(
            $text,
            '@\[quote(?>=([^\]]+))?\](.*?)\[/quote\]\r?\n?@is',
            $this->bbcodeQuoteCallback(...),
        );

        return preg_replace_callback(
            '@\[video\](.*)\[/video\]@Ui',
            $this->bbcodeVideoCallback(...),
            $text,
        );
    }

    private function replaceWithRules(string $text, array $rules): string
    {
        for ($nestLimit = 0; $nestLimit < 10; ++$nestLimit) {
            $tmp = preg_replace(array_keys($rules), array_values($rules), $text);
            if ($tmp === $text || !is_string($tmp)) {
                break;
            }
            $text = $tmp;
        }

        return $text;
    }

    private function replaceWithCallback(
        string $text,
        string $pattern,
        callable $callback,
    ): string {
        for ($nestLimit = 0; $nestLimit < 10; ++$nestLimit) {
            $tmp = preg_replace_callback($pattern, $callback, $text);
            if ($tmp === $text || !is_string($tmp)) {
                break;
            }
            $text = $tmp;
        }

        return $text;
    }

    private function bbcodeQuoteCallback(array $match): string
    {
        $quotee = $match[1] !== ''
            ? "<div class='quotee'>{$match[1]}</div>"
            : '';

        return "<div class='quote'>{$quotee}{$match[2]}</div>";
    }

    private function bbcodeSizeCallback(array $match): string
    {
        $fontSize = $match[1] . ($match[2] ?: 'px');

        return "<span style='font-size:{$fontSize}'>{$match[3]}</span>";
    }

    private function bbcodeVideoCallback(array $match): string
    {

        if (str_contains((string) $match[1], 'youtube.com')) {
            preg_match('@v=([\w-]+)@', (string) $match[1], $youtubeMatches);
            $embedUrl = "https://www.youtube.com/embed/{$youtubeMatches[1]}";

            return $this->youtubeEmbedHTML($match[1], $embedUrl);
        }

        if (str_contains((string) $match[1], 'youtu.be')) {
            preg_match('@youtu.be/(?P<params>.+)$@', (string) $match[1], $youtubeMatches);
            $embedUrl = "https://www.youtube.com/embed/{$youtubeMatches['params']}";

            return $this->youtubeEmbedHTML($match[1], $embedUrl);
        }

        return '-Invalid Video Url-';
    }

    private function bbcodeLICallback(array $match): string
    {
        $items = preg_split("@(^|[\r\n])\\*@", (string) $match[2]);

        $html = $match[1] === 'ol' ? '<ol>' : '<ul>';
        foreach ($items as $item) {
            if (trim($item) === '') {
                continue;
            }

            $html .= '<li>' . $item . ' </li>';
        }

        return $html . $match[1] === 'ol' ? '</ol>' : '</ul>';
    }

    // phpcs:disable SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    private function youtubeEmbedHTML(
        string $link,
        string $embedUrl,
    ): string {
        $allow = 'accelerometer; autoplay; clipboard-write; encrypted-media;'
            . ' gyroscope; picture-in-picture; web-share';

        // do NOT replace this with <<<HTML, sonarqube thinks it's an HTML tag and it's bitten me twice
        return <<<DOC
            <div class="media youtube">
                <div class="summary">
                    Watch Youtube Video:
                    <a href="{$link}">
                        {$link}
                    </a>
                </div>
                <div class="open">
                    <a href="{$link}" class="popout">
                        Popout
                    </a>
                    &middot;
                    <a href="{$link}" class="inline">
                        Inline
                    </a>
                </div>
                <div class="movie" style="display:none">
                    <iframe
                        allow="{$allow}"
                        allowfullscreen="allowfullscreen"
                        frameborder="0"
                        height="315"
                        referrerpolicy="strict-origin-when-cross-origin"
                        src="{$embedUrl}"
                        title="YouTube video player"
                        width="560"
                        ></iframe>
                </div>
            </div>
            DOC;
        // phpcs:enable
    }
}
