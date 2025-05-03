<?php

declare(strict_types=1);

namespace Jax;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function str_contains;
use function trim;
use function urlencode;

use const PATHINFO_EXTENSION;

final class BBCode
{
    /**
     * @var array<string,array<string,mixed>>
     */
    private array $attachmentData;

    /**
     * @var array<string,string>
     */
    // phpcs:ignore
    private array $inlineBBCodes = [
        '@\[(bg|bgcolor|background)=(#?[\s\w\d]+)\](.*)\[/\1\]@Usi' => '<span style="background:$2">$3</span>',
        '@\[b\](.*)\[/b\]@Usi' => '<strong>$1</strong>',
        '@\[color=(#?[\s\w\d]+|rgb\([\d, ]+\))\](.*)\[/color\]@Usi' => '<span style="color:$1">$2</span>',
        '@\[font=([\s\w]+)](.*)\[/font\]@Usi' => '<span style="font-family:$1">$2</span>',
        '@\[i\](.*)\[/i\]@Usi' => '<em>$1</em>',
        '@\[s\](.*)\[/s\]@Usi' => '<span style="text-decoration:line-through">$1</span>',
        '@\[spoiler\](.*)\[/spoiler\]@Usi' => '<span class="spoilertext">$1</span>',
        '@\[u\](.*)\[/u\]@Usi' => '<span style="text-decoration:underline">$1</span>',
        // Consider adding nofollow if admin approval of new accounts is not enabled
        '@\[url\](http|ftp|\?)(.*)\[/url\]@Ui' => '<a href="$1$2">$1$2</a>',
        '@\[url=(http|ftp|\?|mailto:)([^\]]+)\](.+?)\[/url\]@i' => '<a href="$1$2">$3</a>',
    ];

    /**
     * @var array<string,string>
     */
    private array $blockBBCodes = [
        '@\[align=(center|left|right)\](.*)\[/align\]@Usi' => '<p style="text-align:$1">$2</p>',
        '@\[h([1-5])\](.*)\[/h\1\]@Usi' => '<h$1>$2</h$1>',
        '@\[img(?:=([^\]]+|))?\]((?:http|ftp)\S+)\[/img\]@Ui' => <<<'HTML'
            <img src="$2" title="$1" alt="$1" class="bbcodeimg" />
            HTML,
    ];

    public function __construct(
        private readonly Database $database,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly Config $config,
    ) {}

    public function toHTML(string $text): ?string
    {
        $text = $this->toInlineHTML($text);

        $text = $this->replaceWithRules($text, $this->blockBBCodes);

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

        // [attachment]
        $text = $this->replaceWithCallback(
            $text,
            '@\[attachment\](\d+)\[/attachment\]@',
            $this->attachmentCallback(...),
        );

        return preg_replace_callback(
            '@\[video\](.*)\[/video\]@Ui',
            $this->bbcodeVideoCallback(...),
            $text,
        );
    }

    public function toInlineHTML(string $text): ?string
    {
        return $this->replaceWithRules($text, $this->inlineBBCodes);
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
            $tmp = preg_replace_callback($pattern, $callback, $text, 20);
            if ($tmp === $text || !is_string($tmp)) {
                break;
            }
            $text = $tmp;
        }

        return $text;
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

    /**
     * @param array{string,'ol'|'ul',string} $match
     */
    private function bbcodeLICallback(array $match): string
    {
        $tag = $match[1];
        $items = $match[2];
        $items = preg_split("@([\r\n]+|^)\\*@", (string) $match[2]);

        // This HTML construction could be prettier, but
        // SonarQube requires the <li>s to be surrounded by <ol> and <ul>
        $html = $tag === 'ol' ? '<ol>' : '<ul>';
        $html .= implode('', array_map(
            static fn($item) => "<li>{$item}</li>",
            array_filter($items, static fn($line) => (bool) trim($line)),
        ));
        $html .= $tag === 'ol' ? '</ol>' : '</ul>';

        return $html;
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
