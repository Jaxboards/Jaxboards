<?php

declare(strict_types=1);

namespace Jax;

use function array_keys;
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
    private $attachmentdata;

    private $textRules;

    private $emoteRules;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
    ) {
        $this->getTextRules();
    }

    public function getTextRules()
    {
        if ($this->textRules) {
            return $this->textRules;
        }

        $q = $this->database->safeselect(
            <<<'EOT'
                `id`,`type`,`needle`,`replacement`,`enabled`
                EOT
            ,
            'textrules',
            '',
        );
        $textRules = [
            'badword' => [],
            'bbcode' => [],
            'emote' => [],
        ];
        while ($f = $this->database->arow($q)) {
            $textRules[$f['type']][$f['needle']] = $f['replacement'];
        }

        // Load emoticon pack.
        $emotepack = $this->config->getSetting('emotepack');
        if ($emotepack) {
            $emotepack = 'emoticons/' . $emotepack;
            if (mb_substr($emotepack, -1) !== '/') {
                $emotepack .= '/';
            }

            $emoteRules = __DIR__ . '/../../' . $emotepack . 'rules.php';

            if (file_exists($emoteRules)) {
                require_once $emoteRules;
                if (!$rules) {
                    exit('Emoticon ruleset corrupted!');
                }

                foreach ($rules as $k => $v) {
                    if (isset($textRules['emote'][$k])) {
                        continue;
                    }

                    $textRules['emote'][$k] = $emotepack . $v;
                }
            }
        }

        $nrules = [];
        foreach ($textRules['emote'] as $k => $v) {
            $nrules[preg_quote($k, '@')]
                = '<img src="' . $v . '" alt="' . $this->blockhtml($k) . '"/>';
        }

        $this->emoteRules = $nrules === [] ? false : $nrules;
        $this->textRules = $textRules;

        return $this->textRules;
    }

    public function linkify($a): ?string
    {
        return preg_replace_callback(
            '@(^|\s)(https?://[^\s\)\(<>]+)@',
            $this->linkify_callback(...),
            (string) $a,
        );
    }

    public function linkify_callback($match): string
    {
        $url = parse_url((string) $match[2]);
        if (!$url['fragment'] && $url['query']) {
            $url['fragment'] = $url['query'];
        }

        if ($url['host'] === $_SERVER['HTTP_HOST'] && $url['fragment']) {
            if (preg_match('@act=vt(\d+)@', $url['fragment'], $m)) {
                $nice = preg_match('@pid=(\d+)@', $url['fragment'], $m2)
                    ? 'Post #' . $m2[1]
                    : 'Topic #' . $m[1];
            }

            $match[2] = '?' . $url['fragment'];
        }

        return $match[1] . '[url=' . $match[2] . ']' . ($nice ?: $match[2]) . '[/url]';
    }

    public function blockhtml($a): string
    {
        // Fix for template conditionals.
        return str_replace('{if', '&#123;if', htmlspecialchars((string) $a, ENT_QUOTES));
    }

    public function getEmoteRules($escape = 1)
    {
        return $escape ? $this->emoteRules : $this->textRules['emote'];
    }

    public function emotes($a)
    {
        // Believe it or not, adding a space and then removing it later
        // is 20% faster than doing (^|\s).
        $emoticonlimit = 15;
        if (!$this->emoteRules) {
            return $a;
        }

        $a = preg_replace_callback(
            '@(\s)(' . implode('|', array_keys($this->emoteRules)) . ')@',
            $this->emotecallback(...),
            ' ' . $a,
            $emoticonlimit,
        );

        return mb_substr((string) $a, 1);
    }

    public function emotecallback($a): string
    {
        return $a[1] . $this->emoteRules[preg_quote((string) $a[2], '@')];
    }

    public function getwordfilter()
    {
        return $this->textRules['badword'];
    }

    public function wordfilter($a)
    {
        global $USER;
        if ($USER && $USER['nowordfilter']) {
            return $a;
        }

        return str_ireplace(
            array_keys($this->textRules['badword']),
            array_values($this->textRules['badword']),
            $a,
        );
    }

    public function startcodetags(&$a)
    {
        preg_match_all('@\[code(=\w+)?\](.*?)\[/code\]@is', (string) $a, $codes);
        foreach ($codes[0] as $k => $v) {
            $a = str_replace($v, '[code]' . $k . '[/code]', $a);
        }

        return $codes;
    }

    public function finishcodetags($a, $codes, $returnbb = false)
    {
        foreach ($codes[0] as $k => $v) {
            if (!$returnbb) {
                $codes[2][$k] = $codes[1][$k] === '=php' ? highlight_string($codes[2][$k], 1) : preg_replace(
                    "@([ \r\n]|^) @m",
                    '$1&nbsp;',
                    $this->blockhtml($codes[2][$k]),
                );
            }

            $a = str_replace(
                '[code]' . $k . '[/code]',
                $returnbb
                    ? '[code' . $codes[1][$k] . ']' . $codes[2][$k] . '[/code]'
                    : '<div class="bbcode code'
                . ($codes[1][$k] ? ' ' . $codes[1][$k] : '') . '">'
                . $codes[2][$k] . '</div>',
                $a,
            );
        }

        return $a;
    }

    public function textonly($a): ?string
    {
        while (($t = preg_replace('@\[(\w+)[^\]]*\]([\w\W]*)\[/\1\]@U', '$2', (string) $a)) !== $a) {
            $a = $t;
        }

        return $a;
    }

    public function bbcodes($a, $minimal = false): ?string
    {
        $x = 0;
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

        $keys = array_keys($bbcodes);
        $values = array_values($bbcodes);
        while (($tmp = preg_replace($keys, $values, (string) $a)) !== $a) {
            $a = $tmp;
        }

        if ($minimal) {
            return $a;
        }

        // UL/LI tags.
        while ($a !== ($tmp = preg_replace_callback('@\[(ul|ol)\](.*)\[/\1\]@Usi', $this->bbcode_licallback(...), (string) $a))) {
            $a = $tmp;
        }

        // Size code (actually needs a callback simply because of
        // the variability of the arguments).
        while ($a !== ($tmp = preg_replace_callback('@\[size=([0-4]?\d)(px|pt|em|)\](.*)\[/size\]@Usi', $this->bbcode_sizecallback(...), (string) $a))) {
            $a = $tmp;
        }

        // Do quote tags.
        while (
            preg_match(
                '@\[quote(?>=([^\]]+))?\](.*?)\[/quote\]\r?\n?@is',
                (string) $a,
                $m,
            ) && $x < 10
        ) {
            ++$x;
            $a = str_replace(
                $m[0],
                '<div class="quote">'
                . ($m[1] !== '' && $m[1] !== '0' ? '<div class="quotee">' . $m[1] . '</div>' : '')
                . $m[2] . '</div>',
                $a,
            );
        }

        return preg_replace_callback(
            '@\[video\](.*)\[/video\]@Ui',
            $this->bbcode_videocallback(...),
            (string) $a,
        );
    }

    public function bbcode_sizecallback($m): string
    {
        return '<span style="font-size:'
            . $m[1] . ($m[2] ?: 'px') . '">' . $m[3] . '</span>';
    }

    public function bbcode_videocallback($m): string
    {

        if (str_contains((string) $m[1], 'youtube.com')) {
            preg_match('@v=([\w-]+)@', (string) $m[1], $youtubeMatches);
            $embedUrl = "https://www.youtube.com/embed/{$youtubeMatches[1]}";

            return $this->youtubeEmbedHTML($m[1], $embedUrl);
        }

        if (str_contains((string) $m[1], 'youtu.be')) {
            preg_match('@youtu.be/(?P<params>.+)$@', (string) $m[1], $youtubeMatches);
            $embedUrl = "https://www.youtube.com/embed/{$youtubeMatches['params']}";

            return $this->youtubeEmbedHTML($m[1], $embedUrl);
        }

        return '-Invalid Video Url-';
    }

    public function bbcode_licallback($m): string
    {
        $items = preg_split("@(^|[\r\n])\\*@", (string) $m[2]);

        $html = $m[1] === 'ol' ? '<ol>' : '<ul>';
        foreach ($items as $item) {
            if (trim($item) === '') {
                continue;
            }

            $html .= '<li>' . $item . ' </li>';
        }

        return $html . $m[1] === 'ol' ? '</ol>' : '</ul>';
    }

    public function attachments($a): null|array|string
    {
        return $a = preg_replace_callback(
            '@\[attachment\](\d+)\[/attachment\]@',
            $this->attachment_callback(...),
            (string) $a,
            20,
        );
    }

    public function attachment_callback($a): string
    {
        $a = $a[1];
        if (isset($this->attachmentdata[$a])) {
            $data = $this->attachmentdata[$a];
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
                $a,
            );
            $data = $this->database->arow($result);
            $this->database->disposeresult($result);
            if (!$data) {
                return "Attachment doesn't exist";
            }

            $this->attachmentdata[$a] = $data;
        }

        $ext = explode('.', (string) $data['name']);
        $ext = count($ext) === 1 ? '' : mb_strtolower(array_pop($ext));

        if (!in_array($ext, $this->config->getSetting('images') ?? [])) {
            $ext = '';
        }

        if ($ext !== '') {
            $attachmentURL = BOARDPATHURL . '/Uploads/' . $data['hash'] . '.' . $ext;

            return "<a href='{$attachmentURL}'>"
                . "<img src='{$attachmentURL}' alt='attachment' class='bbcodeimg' />"
                . '</a>';
        }

        return '<div class="attachment">'
            . '<a href="index.php?act=download&id='
            . $data['id'] . '&name=' . urlencode((string) $data['name']) . '" class="name">'
            . $data['name'] . '</a> Downloads: ' . $data['downloads'] . '</div>';
    }

    public function theworks($a, $cfg = [])
    {
        if (@!$cfg['nobb'] && @!$cfg['minimalbb']) {
            $codes = $this->startcodetags($a);
        }

        $a = $this->blockhtml($a);
        $a = nl2br($a);

        if (@!$cfg['noemotes']) {
            $a = $this->emotes($a);
        }

        if (@!$cfg['nobb']) {
            $a = $this->bbcodes($a, @$cfg['minimalbb']);
        }

        if (@!$cfg['nobb'] && @!$cfg['minimalbb']) {
            $a = $this->finishcodetags($a, $codes);
        }

        if (@!$cfg['nobb'] && @!$cfg['minimalbb']) {
            $a = $this->attachments($a);
        }

        return $this->wordfilter($a);
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
