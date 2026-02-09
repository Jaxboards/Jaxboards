<?php

declare(strict_types=1);

namespace Jax;

use Jax\Models\File;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function highlight_string;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_string;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function str_contains;
use function str_repeat;
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
        'header' => '/\[h([1-6])\](.*)\[\/h\1\]/Usi',
        'image' => '/\[img(?:=([^\]]+|))?\]((?:http|ftp)\S+)\[\/img\]/Ui',
    ];

    /**
     * @var array<string,string>
     */
    private array $callbackBBCodes = [
        'attachment' => '/\[attachment\](\d+)\[\/attachment\]/',
        'code' => '/\[code(=\w+)?\](.*?)\[\/code\]/is',
        'chess' => '/\[chess\](.*?)\[\/chess\]/is',
        'checkers' => '/\[checkers\](.*?)\[\/checkers\]/is',
        'list' => '/\[(ul|ol)\](.*)\[\/\1\]/Usi',
        'quote' => '/\[quote(?>=([^\]]+))?\](.*?)\[\/quote\]\r?\n?/is',
        'size' => '/\[size=([0-4]?\d)(px|pt|em|)\](.*)\[\/size\]/Usi',
        'table' => '/\[table\](.*)\[\/table\]/Usi',
        'video' => '/\[video\](.*)\[\/video\]/Ui',
    ];

    /**
     * @var array<string,string>
     */
    private array $htmlReplacements = [
        'align' => '<p style="text-align:$1">$2</p>',
        'background' => '<span style="background:$1">$2</span>',
        'bold' => '<strong>$1</strong>',
        'color' => '<span style="color:$1">$2</span>',
        'font' => '<span style="font-family:$1">$2</span>',
        'header' => '<h$1>$2</h$1>',
        'image' => '<img src="$2" title="$1" alt="$1" class="bbcodeimg">',
        'italic' => '<em>$1</em>',
        'spoiler' => '<span class="spoilertext">$1</span>',
        'strikethrough' => '<span style="text-decoration:line-through">$1</span>',
        'underline' => '<span style="text-decoration:underline">$1</span>',
        // Consider adding nofollow if admin approval of new accounts is not enabled
        'url' => '<a href="$1">$1</a>',
        'urlWithLink' => '<a href="$1">$2</a>',
    ];

    /**
     * @var array<string,string>
     */
    private array $markdownReplacements = [
        'align' => '$2',
        'background' => '$2',
        'bold' => '**$1**',
        'color' => '$2',
        'font' => '$2',
        'header' => "# $2\n",
        'image' => '![$1]($2)',
        'italic' => '*$1*',
        'spoiler' => '||$1||',
        'strikethrough' => '~~$1~~',
        'underline' => '__$1__',
        'url' => '[$1]($1)',
        'urlWithLink' => '[$2]($1)',
    ];

    public function __construct(
        private readonly DomainDefinitions $domainDefinitions,
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
        foreach ($this->blockBBCodes as $name => $regex) {
            if (!array_key_exists($name, $this->htmlReplacements)) {
                continue;
            }

            $rules[$regex] = $this->htmlReplacements[$name];
        }

        $text = $this->replaceWithRules($text, $rules);

        // [table]
        $text = $this->replaceWithCallback(
            $text,
            $this->callbackBBCodes['table'],
            $this->bbcodeTableCallback(...),
        );

        // [chess]
        $text = $this->replaceWithCallback(
            $text,
            $this->callbackBBCodes['chess'],
            $this->bbcodeChessCallback(...),
        );


        // [checkers]
        $text = $this->replaceWithCallback(
            $text,
            $this->callbackBBCodes['checkers'],
            $this->bbcodeCheckersCallback(...),
        );

        // [ul] and [ol]
        $text = $this->replaceWithCallback(
            $text,
            $this->callbackBBCodes['list'],
            $this->bbcodeListCallback(...),
        );

        // [size]
        $text = $this->replaceWithCallback(
            $text,
            $this->callbackBBCodes['size'],
            $this->bbcodeSizeCallback(...),
        );

        // [quote]
        $text = $this->replaceWithCallback(
            $text,
            $this->callbackBBCodes['quote'],
            $this->bbcodeQuoteCallback(...),
        );

        // [attachment]
        $text = $this->replaceWithCallback(
            $text,
            $this->callbackBBCodes['attachment'],
            $this->attachmentCallback(...),
        );

        // [video]
        $text = (string) preg_replace_callback(
            $this->callbackBBCodes['video'],
            $this->bbcodeVideoCallback(...),
            $text,
        );

        if ($codeBlocks !== []) {
            return $this->finishCodeTags($text, $codeBlocks);
        }

        return $text;
    }

    public function toMarkdown(string $text): string
    {
        [$text, $codes] = $this->startCodeTags($text);

        $rules = [];

        $rules[$this->callbackBBCodes['attachment']] = '';
        $rules[$this->callbackBBCodes['list']] = '$2';
        $rules[$this->callbackBBCodes['quote']] = '> $2';
        $rules[$this->callbackBBCodes['size']] = '$3';
        $rules[$this->callbackBBCodes['video']] = '$1';

        foreach (
            array_merge(
                $this->blockBBCodes,
                $this->inlineBBCodes,
            ) as $name => $regex
        ) {
            if (!array_key_exists($name, $this->markdownReplacements)) {
                continue;
            }

            $rules[$regex] = $this->markdownReplacements[$name];
        }

        $text = $this->replaceWithRules(
            $text,
            $rules,
        );

        // Code blocks have to come last since they may include bbcode that should be unparsed
        $text = (string) preg_replace_callback(
            $this->callbackBBCodes['code'],
            static fn($match): string => "```{$codes[$match[2]][2]}```",
            $text,
        );

        return $text;
    }

    public function toInlineHTML(string $text): string
    {
        $rules = [];
        foreach ($this->inlineBBCodes as $name => $regex) {
            if (!array_key_exists($name, $this->htmlReplacements)) {
                continue;
            }

            $rules[$regex] = $this->htmlReplacements[$name];
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
            $this->callbackBBCodes['code'],
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
     * @param array<string,string> $rules
     */
    private function replaceWithRules(string $text, array $rules): string
    {
        for ($nestLimit = 0; $nestLimit < 10; ++$nestLimit) {
            $tmp = preg_replace(
                array_keys($rules),
                array_values($rules),
                $text,
            );
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

    /**
     * @param array<string> $match
     */
    private function bbcodeChessCallback(array $match): string
    {
        [, $fen] = $match;

        // If it's empty, start a new game
        $fen = trim(
            $fen,
        ) === '' ? 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR' : $fen;

        // replace numbers with empty squares
        $fen = preg_replace_callback(
            '/[0-8]/',
            static fn($match) => str_repeat(' ', (int) $match[0]),
            (string) $fen,
        );
        $fen = explode('/', (string) $fen);

        $white = [
            // 'R' => '♖',
            // 'N' => '♘',
            // 'B' => '♗',
            // 'Q' => '♕',
            // 'K' => '♔',
            // 'P' => '♙'
            // Decided to use filled (black) unicode pieces instead for visibility
            'R' => '♜',
            'N' => '♞',
            'B' => '♝',
            'Q' => '♛',
            'K' => '♚',
            'P' => '♟',
        ];
        $black = [
            'r' => '♜',
            'n' => '♞',
            'b' => '♝',
            'q' => '♛',
            'k' => '♚',
            'p' => '♟',
        ];

        $characters = [...$white, ...$black];
        $pieces = [];

        for ($row = 0; $row < 8; ++$row) {
            $pieces[$row] = [];

            for ($column = 0; $column < 8; ++$column) {
                $piece = $fen[$row][$column] ?? '';
                $color = array_key_exists($piece, $white)
                    ? 'color:white;-webkit-text-stroke: 1px #222;'
                    : (array_key_exists($piece, $black) ? 'color:black;' : '');
                $character = array_key_exists(
                    $piece,
                    $characters,
                ) ? $characters[$piece] : '';

                $pieces[$row][$column] = ($piece !== '' ? "<div class='piece' data-piece='{$piece}' style='{$color}'>{$character}</div>" : '');
            }
        }

        return $this->renderCheckerBoard($pieces);
    }

    /**
     * @param array<string> $match
     */
    private function bbcodeCheckersCallback(array $match): string
    {
        [, $state] = $match;

        // If it's empty, start a new game
        $state = trim(
            $state,
        ) === '' ? 'bbbb/bbbb/bbbb/4/4/rrrr/rrrr/rrrr' : $state;

        // replace numbers with empty squares
        $state = preg_replace_callback(
            '/[0-8]/',
            static fn($match) => str_repeat(' ', (int) $match[0]),
            (string) $state,
        );

        $state = explode('/', (string) $state);

        $red = [
            'r' => '🔴',
            'R' => '♛',
        ];
        $black = [
            'b' => '⚫️',
            'B' => '♛',
        ];

        $characters = [...$red, ...$black];
        $pieces = [];

        for ($row = 0; $row < 8; ++$row) {
            $pieces[$row] = [];

            for ($column = 0; $column <= 4; ++$column) {
                $piece = $state[$row][$column] ?? '';
                $color = array_key_exists($piece, $red)
                    ? 'color:#ffbebe;'
                    : (array_key_exists($piece, $black) ? 'color:black;' : '');
                $character = array_key_exists(
                    $piece,
                    $characters,
                ) ? $characters[$piece] : '';

                $offset = -$row % 2;
                $pieces[$row][($column * 2 - $offset + 8) % 8] = '';
                $pieces[$row][$column * 2 + $offset + 1] = (trim($piece) !== '' ? "<div class='piece' data-piece='{$piece}' style='{$color}'>{$character}</div>" : '');
            }
        }

        return $this->renderCheckerBoard($pieces, 'checkers');
    }

    /**
     * Renders a checkerboard.
     *
     * @param array<array<string>> $pieces
     */
    private function renderCheckerBoard(array $pieces, string $game = 'chess'): string
    {
        $board = <<<'HTML'
            <tr>
                <th scope="col"></th>
                <th scope="col">A</th>
                <th scope="col">B</th>
                <th scope="col">C</th>
                <th scope="col">D</th>
                <th scope="col">E</th>
                <th scope="col">F</th>
                <th scope="col">G</th>
                <th scope="col">H</th>
            </tr>
            HTML;

        for ($row = 0; $row < 8; ++$row) {
            $cells = '';
            for ($column = 0; $column < 8; ++$column) {
                $cells .= '<td>' . $pieces[$row][$column] . '</td>';
            }

            $board .= "<tr><th scope='row'>" . (8 - $row) . "</th>{$cells}</tr>";
        }

        $table = 'table';

        return "<{$table} class='checkerboard {$game}'>{$board}</table>";
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
