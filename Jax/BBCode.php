<?php

declare(strict_types=1);

namespace Jax;

use Jax\BBCode\Games;
use Jax\Models\File;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_unique;
use function highlight_string;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_callable;
use function is_string;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function str_contains;
use function str_replace;
use function trim;

use const ENT_QUOTES;
use const PREG_SET_ORDER;

final class BBCode
{
    /**
     * @var array<int,File>
     */
    private array $attachmentData = [];

    /**
     * @var array<string,string>
     */
    private array $inlineBBCodes = [
        'background' => '/\[bgcolor=(#?[\s\w\d]+)\](.*)\[\/bgcolor\]/Usi',
        'bold' => '/\[b\](.*)\[\/b\]/Usi',
        'color' => '/\[color=(#?[\s\w\d]+|rgb\([\d, ]+\))\](.*)\[\/color\]/Usi',
        'font' => '/\[font=([\s\w]+)](.*)\[\/font\]/Usi',
        'italic' => '/\[i\](.*)\[\/i\]/Usi',
        'spoiler' => '/\[spoiler\](.*)\[\/spoiler\]/Usi',
        'strikethrough' => '/\[s\](.*)\[\/s\]/Usi',
        'underline' => '/\[u\](.*)\[\/u\]/Usi',
        'url' => '/\[url\](?P<url>(?:[?\/]|https?|ftp|mailto:).*)\[\/url\]/Ui',
        'urlWithLink' => '/\[url=(?P<url>(?:[?\/]|https?|ftp|mailto:)[^\]]+)\](.+?)\[\/url\]/i',
    ];

    /**
     * @var array<string,string>
     */
    private array $blockBBCodes = [
        'align' => '/\[align=(center|left|right)\](.*)\[\/align\]/Usi',
        'attachment' => '/\[attachment\](\d+)\[\/attachment\]/',
        'code' => '/\[code(=\w+)?\](.*?)\[\/code\]/is',
        'checkers' => '/\[checkers\](.*?)\[\/checkers\]/is',
        'chess' => '/\[chess\](.*?)\[\/chess\]/is',
        'header' => '/\[h([1-6])\](.*)\[\/h\1\]/Usi',
        'image' => '/\[img(?:=([^\]]+|))?\]((?:http|ftp)\S+)\[\/img\]/Ui',
        'list' => '/\[(ul|ol)\](.*)\[\/\1\]/Usi',
        'othello' => '/\[othello\](.*?)\[\/othello\]/is',
        'quote' => '/\[quote(?>=([^\]]+))?\](.*?)\[\/quote\]\r?\n?/is',
        'size' => '/\[size=([0-4]?\d)(px|pt|em|)\](.*)\[\/size\]/Usi',
        'table' => '/\[table\](.*)\[\/table\]/Usi',
        'video' => '/\[video\](.*)\[\/video\]/Usi',
    ];

    public function __construct(
        private readonly DomainDefinitions $domainDefinitions,
        private readonly Games $games,
        private readonly FileSystem $fileSystem,
        private readonly Template $template,
    ) {}

    /**
     * Returns list of all URLs (extracted from BBCode) in the $text.
     *
     * @return array<string>
     */
    public function getURLs(string $text): array
    {
        $urls = [];
        foreach (
            [
                $this->inlineBBCodes['url'],
                $this->inlineBBCodes['urlWithLink'],
            ] as $regex
        ) {
            preg_match_all($regex, $text, $matches);
            $urls = array_merge($matches['url'], $urls);
        }

        return array_unique($urls);
    }

    /**
     * @param array<array<string>> $codeBlocks
     */
    public function toHTML(string $text, $codeBlocks = []): string
    {
        $text = $this->toInlineHTML($text);

        $rules = [];
        $htmlReplacements = $this->getHTMLReplacements();

        foreach ($this->blockBBCodes as $name => $regex) {
            if (!array_key_exists($name, $htmlReplacements)) {
                continue;
            }

            $rules[$regex] = $htmlReplacements[$name];
        }

        $text = $this->replaceWithRules($text, $rules);

        if ($codeBlocks !== []) {
            return $this->finishCodeTags($text, $codeBlocks);
        }

        return $text;
    }

    public function toMarkdown(string $text): string
    {
        [$text, $codes] = $this->startCodeTags($text);


        $text = $this->replaceWithRules(
            $text,
            $this->getMarkdownRules(),
        );

        // Code blocks have to come last since they may include bbcode that should be unparsed
        $text = (string) preg_replace_callback(
            $this->blockBBCodes['code'],
            static fn($match): string => "```{$codes[$match[2]][2]}```",
            $text,
        );

        return $text;
    }

    public function toInlineHTML(string $text): string
    {
        $rules = [];
        $htmlReplacements = $this->getHTMLReplacements();

        foreach ($this->inlineBBCodes as $name => $regex) {
            if (!array_key_exists($name, $htmlReplacements)) {
                continue;
            }

            $rules[$regex] = $htmlReplacements[$name];
        }

        return $this->replaceWithRules(
            $text,
            $rules,
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
        preg_match_all(
            $this->blockBBCodes['code'],
            $text,
            $codes,
            PREG_SET_ORDER,
        );
        foreach ($codes as $key => $match) {
            $text = str_replace($match[0], "[code]{$key}[/code]", $text);
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
        foreach ($codes as $index => [, $language, $code]) {
            $code = $language === '=php' ? highlight_string(
                $code,
                true,
            ) : preg_replace(
                "@([ \r\n]|^) @m",
                '$1&nbsp;',
                htmlspecialchars($code, ENT_QUOTES),
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
     * @return array<string,callable|string>
     */
    private function getHTMLReplacements(): array
    {
        static $htmlReplacements = null;
        if ($htmlReplacements) {
            return $htmlReplacements;
        }

        $htmlReplacements = [
            'align' => '<p style="text-align:$1">$2</p>',
            'background' => '<span style="background:$1">$2</span>',
            'bold' => '<strong>$1</strong>',
            'color' => '<span style="color:$1">$2</span>',
            'font' => '<span style="font-family:$1">$2</span>',
            'header' => '<h$1>$2</h$1>',
            'image' => '<img src="$2" title="$1" alt="$1" class="bbcodeimg">',
            'italic' => '<em>$1</em>',
            'spoiler' => '<button class="spoilertext as-text">$1</button>',
            'strikethrough' => '<span style="text-decoration:line-through">$1</span>',
            'underline' => '<span style="text-decoration:underline">$1</span>',
            // Consider adding nofollow if admin approval of new accounts is not enabled
            'url' => '<a href="$1">$1</a>',
            'urlWithLink' => '<a href="$1">$2</a>',

            'attachment' => $this->attachmentCallback(...),
            'chess' => $this->games->bbcodeChessCallback(...),
            'checkers' => $this->games->bbcodeCheckersCallback(...),
            'list' => $this->bbcodeListCallback(...),
            'othello' => $this->games->bbcodeOthelloCallback(...),
            'quote' => $this->bbcodeQuoteCallback(...),
            'size' => $this->bbcodeSizeCallback(...),
            'table' => $this->bbcodeTableCallback(...),
            'video' => $this->bbcodeVideoCallback(...),
        ];

        return $htmlReplacements;
    }

    /**
     * @return array<string,callable|string>
     */
    private function getMarkdownRules(): array
    {
        static $markdownReplacements = null;
        if ($markdownReplacements) {
            return $markdownReplacements;
        }

        $markdownReplacements = [
            'align' => '$2',
            'attachment' => '',
            'background' => '$2',
            'bold' => '**$1**',
            'color' => '$2',
            'font' => '$2',
            'header' => "# $2\n",
            'image' => '![$1]($2)',
            'italic' => '*$1*',
            'list' => '$2',
            'quote' => static fn(array $match) => '> ' . str_replace(
                "\n",
                "\n> ",
                $match[2],
            ),
            'size' => '$3',
            'spoiler' => '||$1||',
            'strikethrough' => '~~$1~~',
            'underline' => '__$1__',
            'url' => '[$1]($1)',
            'urlWithLink' => '[$2]($1)',
            'video' => '$1',
        ];

        $rules = [];
        foreach (
            array_merge(
                $this->blockBBCodes,
                $this->inlineBBCodes,
            ) as $name => $regex
        ) {
            if (!array_key_exists($name, $markdownReplacements)) {
                continue;
            }

            $rules[$regex] = $markdownReplacements[$name];
        }

        return $rules;
    }

    /**
     * @param array<string,callable|string> $rules
     */
    private function replaceWithRules(string $text, array $rules): string
    {
        for ($nestLimit = 0; $nestLimit < 10; ++$nestLimit) {
            $tmp = $text;

            foreach ($rules as $pattern => $replacer) {
                if (is_string($replacer)) {
                    $tmp = preg_replace(
                        $pattern,
                        $replacer,
                        $tmp,
                    );
                } elseif (is_callable($replacer)) {
                    $tmp = preg_replace_callback(
                        $pattern,
                        $replacer,
                        $tmp,
                        20,
                    );
                }

                if (!is_string($tmp)) {
                    break;
                }
            }

            if ($tmp === $text) {
                break;
            }

            if (!is_string($tmp)) {
                continue;
            }

            $text = $tmp;
        }

        return $text;
    }

    /**
     * @param array<string> $match
     */
    private function attachmentCallback(array $match): string
    {
        $file = $this->getAttachmentData((int) $match[1]);

        if ($file === null) {
            return "Attachment doesn't exist";
        }

        $ext = $this->fileSystem->getFileInfo($file->name)->getExtension();

        return $this->template->render('bbcode/attachment', [
            'attachmentURL' => $this->domainDefinitions->getBoardPathUrl() . '/Uploads/' . $file->hash . '.' . $ext,
            'file' => $file,
            'isImage' => in_array($ext, Jax::IMAGE_EXTENSIONS, true),
        ]);
    }

    /**
     * Given an attachment ID, gets the file data associated with it
     * Returns null if file not found.
     */
    private function getAttachmentData(int $fileId): ?File
    {
        if (array_key_exists($fileId, $this->attachmentData)) {
            return $this->attachmentData[$fileId];
        }

        $file = File::selectOne($fileId);

        if ($file === null) {
            return null;
        }

        return $this->attachmentData[$file->id] = $file;
    }

    /**
     * @param array<string> $match
     */
    private function bbcodeQuoteCallback(array $match): string
    {
        $quotee = $match[1] !== ''
            ? "<div class='quotee'>{$match[1]}</div>"
            : '';

        return "<div class='quote'>{$quotee}{$match[2]}</div>";
    }

    /**
     * @param array<string> $match
     */
    private function bbcodeSizeCallback(array $match): string
    {
        $fontSize = $match[1] . ($match[2] ?: 'px');

        return "<span style='font-size:{$fontSize}'>{$match[3]}</span>";
    }

    /**
     * For [table]s, we need to apply special rules
     * so that anything between cells and rows is not output (newlines, whitespace, etc).
     *
     * @param array<string> $match
     */
    private function bbcodeTableCallback(array $match): string
    {
        $html = '';

        preg_match_all(
            '/\[tr\](.*)\[\/tr\]/Usi',
            $match[1],
            $rows,
            PREG_SET_ORDER,
        );

        foreach ($rows as $row) {
            $html .= '<tr>';
            preg_match_all(
                '/\[(td|th)\](.*)\[\/\1\]/Usi',
                $row[1],
                $cells,
                PREG_SET_ORDER,
            );
            foreach ($cells as $cell) {
                $html .= "<{$cell[1]}>{$cell[2]}</{$cell[1]}>";
            }

            $html .= '</tr>';
        }

        // Sonar is complaining this HTML table doesn't have headers
        $table = 'table';

        return "<{$table}>{$html}</{$table}>";
    }

    /**
     * @param array<string> $match
     */
    private function bbcodeVideoCallback(array $match): string
    {

        if (str_contains($match[1], 'youtube.com')) {
            preg_match('/v=([\w-]+)/', $match[1], $youtubeMatches);
            $embedUrl = "https://www.youtube.com/embed/{$youtubeMatches[1]}";

            return $this->youtubeEmbedHTML($match[1], $embedUrl);
        }

        if (str_contains($match[1], 'youtu.be')) {
            preg_match('/youtu.be\/(.+)$/', $match[1], $youtubeMatches);
            $embedUrl = "https://www.youtube.com/embed/{$youtubeMatches[1]}";

            return $this->youtubeEmbedHTML($match[1], $embedUrl);
        }

        return '-Invalid Video Url-';
    }

    /**
     * @param array{string,'ol'|'ul',string} $match
     */
    private function bbcodeListCallback(array $match): string
    {
        $tag = $match[1];
        $items = preg_split("@([\r\n]+|^)\\*@", $match[2]) ?: [];

        // This HTML construction could be prettier, but
        // SonarQube requires the LI tags to be surrounded by OL and UL
        $html = $tag === 'ol' ? '<ol>' : '<ul>';
        $html .= implode('', array_map(
            static fn(string $item): string => '<li>' . trim($item) . '</li>',
            array_filter(
                $items,
                static fn(string $line): bool => (bool) trim($line),
            ),
        ));

        return $html . ($tag === 'ol' ? '</ol>' : '</ul>');
    }

    private function youtubeEmbedHTML(
        string $link,
        string $embedURL,
    ): string {
        return $this->template->render('bbcode/youtube-embed', [
            'link' => $link,
            'embedURL' => $embedURL,
        ]);
    }
}
