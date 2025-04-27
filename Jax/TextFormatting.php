<?php

declare(strict_types=1);

namespace Jax;

use function array_keys;
use function array_map;
use function array_pop;
use function array_values;
use function count;
use function explode;
use function file_exists;
use function highlight_string;
use function htmlspecialchars;
use function implode;
use function in_array;
use function mb_strtolower;
use function mb_substr;
use function nl2br;
use function parse_url;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function str_contains;
use function str_ireplace;
use function str_replace;
use function trim;
use function urlencode;

use const ENT_QUOTES;

final class TextFormatting
{
    /**
     * @var array<string, array>
     */
    private $attachmentData;

    /**
     * @var array<string, string>
     */
    private $badwords = [];

    /**
     * Merged emote pack rules with custom emote rules.
     *
     * @var array<string, string>
     */
    private $emotes = [];

    private ?string $emotePack = null;

    /**
     * Emotes from the emote pack.
     *
     * @var array<string, string>
     */
    private $emotePackRules = [];

    public function __construct(
        private readonly Config $config,
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
            <<<'SQL'
                `id`,`type`,`needle`,`replacement`,`enabled`
                SQL
            ,
            'textrules',
            '',
        );
        while ($rule = $this->database->arow($result)) {
            switch ($rule['type']) {
                case 'emote':
                    $this->emotes[$rule['needle']] = $rule['replacement'];

                    break;

                case 'badword':
                    $this->badwords[$rule['needle']] = $rule['replacement'];

                    break;
            }
        }
    }

    public function getEmoteRules()
    {
        return $this->emotes;
    }

    public function getEmotePackRules(?string $emotePack = null)
    {
        $emotePack = $emotePack ?: $this->config->getSetting('emotepack');

        if ($this->emotePack === $emotePack && $this->emotePackRules) {
            return $this->emotePackRules;
        }

        // Load emoticon pack.
        $emotes = [];
        if ($emotePack !== null) {
            $this->emotePack = $emotePack;
            $rulesPath = JAXBOARDS_ROOT . '/emoticons/' . $emotePack . '/rules.php';

            if (file_exists($rulesPath)) {
                require_once $rulesPath;

                if (!$rules) {
                    exit('Emoticon ruleset corrupted!');
                }

                $this->emotePackRules = $rules;

                foreach ($rules as $emote => $path) {
                    $emotes[$emote] = 'emoticons/' . $emotePack . '/' . $path;
                }
            }
        }

        $this->emotes = $emotes;

        return $this->emotePackRules = $emotes;
    }

    public function linkify(string $text): ?string
    {
        return preg_replace_callback(
            '@(^|\s)(https?://[^\s\)\(<>]+)@',
            $this->linkify_callback(...),
            $text,
        );
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function linkify_callback(array $match): string
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
            $this->emotecallback(...),
            ' ' . $text,
            $emoticonLimit,
        );

        return mb_substr((string) $text, 1);
    }

    public function emotecallback(array $match): string
    {
        [, $space, $emoteText] = $match;

        return $space . '<img src="' . $this->emotes[$emoteText] . '" alt="' . $this->blockhtml($emoteText) . '"/>';
    }

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

    public function startcodetags(string &$text)
    {
        preg_match_all('@\[code(=\w+)?\](.*?)\[/code\]@is', $text, $codes);
        foreach ($codes[0] as $key => $fullMatch) {
            $text = str_replace($fullMatch, '[code]' . $key . '[/code]', $text);
        }

        return $codes;
    }

    public function finishcodetags(
        string $text,
        array $codes,
        bool $returnbb = false,
    ): array|string {
        foreach ($codes[0] as $key => $value) {
            if (!$returnbb) {
                $codes[2][$key] = $codes[1][$key] === '=php' ? highlight_string($codes[2][$key], true) : preg_replace(
                    "@([ \r\n]|^) @m",
                    '$1&nbsp;',
                    $this->blockhtml($codes[2][$key]),
                );
            }

            $text = str_replace(
                '[code]' . $key . '[/code]',
                $returnbb
                    ? '[code' . $codes[1][$key] . ']' . $codes[2][$key] . '[/code]'
                    : '<div class="bbcode code'
                . ($codes[1][$key] ? ' ' . $codes[1][$key] : '') . '">'
                . $codes[2][$key] . '</div>',
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

    public function bbcodes(string $text, $minimal = false): ?string
    {
        $bbcodes = [
            '@\[(bg|bgcolor|background)=(#?[\s\w\d]+)\](.*)\[/\1\]@Usi' => '<span style="background:$2">$3</span>',
            '@\[blink\](.*)\[/blink\]@Usi' => '<span style="text-decoration:blink">$1</span>',
            // I recommend keeping nofollow if admin approval of new accounts is not enabled
            '@\[b\](.*)\[/b\]@Usi' => '<strong>$1</strong>',
            '@\[color=(#?[\s\w\d]+|rgb\([\d, ]+\))\](.*)\[/color\]@Usi' => '<span style="color:$1">$2</span>',
            '@\[font=([\s\w]+)](.*)\[/font\]@Usi' => '<span style="font-family:$1">$2</span>',
            '@\[i\](.*)\[/i\]@Usi' => '<em>$1</em>',
            '@\[spoiler\](.*)\[/spoiler\]@Usi' => '<span class="spoilertext">$1</span>',
            // Consider adding nofollow if admin approval is not enabled
            '@\[s\](.*)\[/s\]@Usi' => '<span style="text-decoration:line-through">$1</span>',
            '@\[url=(http|ftp|\?|mailto:)([^\]]+)\](.+?)\[/url\]@i' => '<a href="$1$2">$3</a>',
            '@\[url\](http|ftp|\?)(.*)\[/url\]@Ui' => '<a href="$1$2">$1$2</a>',
            '@\[u\](.*)\[/u\]@Usi' => '<span style="text-decoration:underline">$1</span>',
        ];

        if (!$minimal) {
            $bbcodes['@\[h([1-5])\](.*)\[/h\1\]@Usi'] = '<h$1>$2</h$1>';
            $bbcodes['@\[align=(center|left|right)\](.*)\[/align\]@Usi']
                = '<p style="text-align:$1">$2</p>';
            $bbcodes['@\[img(?:=([^\]]+|))?\]((?:http|ftp)\S+)\[/img\]@Ui']
                = '<img src="$2" title="$1" alt="$1" class="bbcodeimg" '
                . 'align="absmiddle" />';
        }

        while (($tmp = preg_replace(array_keys($bbcodes), array_values($bbcodes), (string) $text)) !== $text) {
            $text = $tmp;
        }

        if ($minimal) {
            return $text;
        }

        // UL/LI tags.
        while ($text !== ($tmp = preg_replace_callback('@\[(ul|ol)\](.*)\[/\1\]@Usi', $this->bbcode_licallback(...), (string) $text))) {
            $text = $tmp;
        }

        // Size code (actually needs a callback simply because of
        // the variability of the arguments).
        while ($text !== ($tmp = preg_replace_callback('@\[size=([0-4]?\d)(px|pt|em|)\](.*)\[/size\]@Usi', $this->bbcode_sizecallback(...), (string) $text))) {
            $text = $tmp;
        }

        // Do quote tags.
        for (
            $nestLimit = 0; $nestLimit < 10 && preg_match(
                '@\[quote(?>=([^\]]+))?\](.*?)\[/quote\]\r?\n?@is',
                (string) $text,
                $match,
            ); ++$nestLimit
        ) {
            $text = str_replace(
                $match[0],
                '<div class="quote">'
                . ($match[1] !== '' && $match[1] !== '0' ? '<div class="quotee">' . $match[1] . '</div>' : '')
                . $match[2] . '</div>',
                $text,
            );
        }

        return preg_replace_callback(
            '@\[video\](.*)\[/video\]@Ui',
            $this->bbcode_videocallback(...),
            (string) $text,
        );
    }

    public function bbcode_sizecallback(array $match): string
    {
        return '<span style="font-size:'
            . $match[1] . ($match[2] ?: 'px') . '">' . $match[3] . '</span>';
    }

    public function bbcode_videocallback(array $match): string
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

    public function bbcode_licallback(array $match): string
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

    public function attachments(string $text): null|array|string
    {
        return $text = preg_replace_callback(
            '@\[attachment\](\d+)\[/attachment\]@',
            $this->attachment_callback(...),
            $text,
            20,
        );
    }

    public function attachment_callback(array $match): string
    {
        $attachment = $match[1];
        if (isset($this->attachmentData[$attachment])) {
            $data = $this->attachmentData[$attachment];
        } else {
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
                $attachment,
            );
            $file = $this->database->arow($result);
            $this->database->disposeresult($result);
            if (!$file) {
                return "Attachment doesn't exist";
            }

            $this->attachmentData[$attachment] = $file;
        }

        $ext = explode('.', (string) $file['name']);
        $ext = count($ext) === 1 ? '' : mb_strtolower(array_pop($ext));

        if (!in_array($ext, $this->config->getSetting('images') ?? [])) {
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

    public function theworks(string $text, array $cfg = []): string
    {
        if (@!$cfg['nobb'] && @!$cfg['minimalbb']) {
            $codes = $this->startcodetags($text);
        }

        $text = $this->blockhtml($text);
        $text = nl2br($text);

        if (@!$cfg['noemotes']) {
            $text = $this->emotes($text);
        }

        if (@!$cfg['nobb']) {
            $text = $this->bbcodes($text, @$cfg['minimalbb']);
        }

        if (@!$cfg['nobb'] && @!$cfg['minimalbb']) {
            $text = $this->finishcodetags($text, $codes);
        }

        if (@!$cfg['nobb'] && @!$cfg['minimalbb']) {
            $text = $this->attachments($text);
        }

        return $this->wordfilter($text);
    }

    // phpcs:disable SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    private function youtubeEmbedHTML(
        string $link,
        string $embedUrl,
    ): string {
        // phpcs:disable Generic.Files.LineLength.TooLong
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
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
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
