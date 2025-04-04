<?php

declare(strict_types=1);

final class JAX
{
    public static function json_encode($a, $forceaa = false)
    {
        if ($forceaa) {
            return json_encode(json_decode(json_encode($a), true));
        }

        return json_encode($a);
    }

    public static function json_decode($a, $aa = true): mixed
    {
        return json_decode((string) $a, $aa);
    }

    public static function utf8_encode($a): array|string
    {
        if (is_array($a)) {
            foreach ($a as $k => $v) {
                $a[$k] = self::utf8_encode($v);
            }
        } else {
            $a = mb_convert_encoding((string) $a, 'UTF-8', 'ISO-8859-1');
        }

        return $a;
    }

    public static function is_numerical_array($a): bool
    {
        return range(0, count($a) - 1) === array_keys($a);
    }

    public static function pick(...$args)
    {
        foreach ($args as $v) {
            if ($v) {
                break;
            }
        }

        return $v;
    }

    public $attachmentdata;

    public $userPerms = '';

    public $c = [];

    /**
     * @var array<mixed>|string
     */
    public $g = [];

    /**
     * @var array<mixed>|string
     */
    public $p = [];

    public $s = [];

    /**
     * @var array<mixed>
     */
    public $b = [];

    public $textRules;

    public $userData;

    public $ipbancache;

    public $emoteRules;

    public function __construct()
    {
        $this->c = $this->filterInput($_COOKIE);
        $this->g = $this->filterInput($_GET);
        $this->p = $this->filterInput($_POST);
        $this->b = array_merge($this->p, $this->g);
    }

    public function between($a, $b, $c): bool
    {
        return $a >= $b && $a <= $c;
    }

    public function date($date, $autodate = true): false|string
    {
        if (!$date) {
            return false;
        }

        $delta = time() - $date;
        $fmt = '';
        if ($delta < 90) {
            $fmt = 'a minute ago';
        } elseif ($delta < 3600) {
            $fmt = round($delta / 60) . ' minutes ago';
        } elseif (gmdate('m j Y') === gmdate('m j Y', $date)) {
            $fmt = 'Today @ ' . gmdate('g:i a', $date);
        } elseif (gmdate('m j Y', strtotime('yesterday')) === gmdate('m j Y', $date)) {
            $fmt = 'Yesterday @ ' . gmdate('g:i a', $date);
        } else {
            $fmt = gmdate('M jS, Y @ g:i a', $date);
        }

        if (!$autodate) {
            return $fmt;
        }

        return "<span class='autodate' title='{$date}'>{$fmt}</span>";
    }

    public function smalldate(
        $date,
        $seconds = false,
        $autodate = false,
    ): false|string {
        if (!$date) {
            return false;
        }

        return ($autodate
            ? '<span class="autodate smalldate" title="' . $date . '">'
            : '')
            . gmdate('g:i' . ($seconds ? ':s' : '') . 'a, n/j/y', $date)
            . ($autodate ? '</span>' : '');
    }

    public function setCookie(
        $a,
        $b = 'false',
        $c = false,
        $htmlonly = true,
    ): void {
        if (!is_array($a)) {
            $a = [$a => $b];
        } elseif ($b !== 'false') {
            $c = $b;
        }

        foreach ($a as $k => $v) {
            $this->c[$k] = $v;
            setcookie($k, (string) $v, ['expires' => $c, 'path' => null, 'domain' => null, 'secure' => true, 'httponly' => $htmlonly]);
        }
    }

    public function linkify($a)
    {
        $a = str_replace('<IP>', $this->getIp(), $a);

        return preg_replace_callback(
            '@(^|\s)(https?://[^\s\)\(<>]+)@',
            $this->linkify_callback(...),
            $a,
        );
    }

    public function linkify_callback($match): string
    {
        global $_SERVER;

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

    public function filterInput($a): array|string
    {
        if (is_array($a)) {
            return array_map($this->filterInput(...), $a);
        }

        return stripslashes((string) $a);
    }

    /*
        The getSess and getUser functions both return the
        session/user data respectively
        if not found in the database, getSess inserts a blank Sess row,
        while getUser returns false.
     */

    public function getUser($uid = false, $pass = false)
    {
        global $DB;
        if (!$DB) {
            return null;
        }

        if (!$uid) {
            return $this->userData = false;
        }

        $result = $DB->safeselect(
            [
                'about',
                'avatar',
                'birthdate',
                'contact_aim',
                'contact_bluesky',
                'contact_discord',
                'contact_gtalk',
                'contact_msn',
                'contact_skype',
                'contact_steam',
                'contact_twitter',
                'contact_yim',
                'contact_youtube',
                'display_name',
                'email_settings',
                'email',
                'enemies',
                'friends',
                'full_name',
                'gender',
                'group_id',
                'id',
                'ip',
                'location',
                '`mod`',
                'name',
                'notify_pm',
                'notify_postinmytopic',
                'notify_postinsubscribedtopic',
                'nowordfilter',
                'pass',
                'posts',
                'sig',
                'skin_id',
                'sound_im',
                'sound_pm',
                'sound_postinmytopic',
                'sound_postinsubscribedtopic',
                'sound_shout',
                'ucpnotepad',
                'usertitle',
                'website',
                'wysiwyg',
                'CONCAT(MONTH(`birthdate`),\' \',DAY(`birthdate`)) as `birthday`',
                'DAY(`birthdate`) AS `dob_day`',
                'MONTH(`birthdate`) AS `dob_month`',
                'UNIX_TIMESTAMP(`join_date`) AS `join_date`',
                'UNIX_TIMESTAMP(`last_visit`) AS `last_visit`',
                'YEAR(`birthdate`) AS `dob_year`',
            ],
            'members',
            'WHERE `id`=?',
            $DB->basicvalue($uid),
        );
        $user = $DB->arow($result);
        $user['ip'] = $this->bin2ip($user['ip']);
        if (!$user || !is_array($user) || $user === []) {
            return $this->userData = false;
        }

        $DB->disposeresult($result);
        $user['birthday'] = (date('n j') === $user['birthday'] ? 1 : 0);

        // Password parsing.
        if ($pass !== false) {
            $verifiedPassword = password_verify((string) $pass, (string) $user['pass']);
            $needsRehash = false;
            if ($verifiedPassword) {
                $needsRehash = password_needs_rehash(
                    $user['pass'],
                    PASSWORD_DEFAULT,
                );
            }

            if ($verifiedPassword && $needsRehash) {
                $new_hash = password_hash((string) $pass, PASSWORD_DEFAULT);
                // Add the new hash.
                $DB->safeupdate(
                    'members',
                    [
                        'pass' => $new_hash,
                    ],
                    'WHERE `id` = ?',
                    $user['id'],
                );
            }

            if (!$verifiedPassword) {
                return $this->userData = false;
            }

            unset($user['pass']);
        }

        return $this->userData = $user;
    }

    public function getPerms($group_id = '')
    {
        global $DB;
        if ($group_id === '' && $this->userPerms) {
            return $this->userPerms;
        }

        if ($group_id === '' && $this->userData) {
            $group_id = $this->userData['group_id'];
        }

        if ($this->ipbanned()) {
            $this->userData['group_id'] = $group_id = 4;
        }

        $result = $DB->safeselect(
            <<<'EOT'
                `can_access_acp`,
                `can_add_comments`,
                `can_attach`,
                `can_delete_comments`,
                `can_delete_own_posts`,
                `can_delete_own_shouts`,
                `can_delete_own_topics`,
                `can_delete_shouts`,
                `can_edit_posts`,
                `can_edit_topics`,
                `can_im`,
                `can_karma`,
                `can_lock_own_topics`,
                `can_moderate`,
                `can_override_locked_topics`,
                `can_pm`,
                `can_poll`,
                `can_post_topics`,
                `can_post`,
                `can_shout`,
                `can_use_sigs`,
                `can_view_board`,
                `can_view_fullprofile`,
                `can_view_offline_board`,
                `can_view_shoutbox`,
                `can_view_stats`,
                `flood_control`,
                `icon`,
                `id`,
                `legend`,
                `title`
                EOT
            ,
            'member_groups',
            'WHERE `id`=?',
            self::pick($group_id, 3),
        );
        $retval = $DB->arow($result);
        $this->userPerms = $retval;
        $DB->disposeresult($result);

        return $retval;
    }

    public function blockhtml($a): string
    {
        // Fix for template conditionals.
        return str_replace('{if', '&#123;if', htmlspecialchars((string) $a, ENT_QUOTES));
    }

    public function getTextRules()
    {
        global $CFG,$DB;
        if ($this->textRules) {
            return $this->textRules;
        }

        $q = $DB->safeselect(
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
        while ($f = $DB->arow($q)) {
            $textRules[$f['type']][$f['needle']] = $f['replacement'];
        }

        // Load emoticon pack.
        $emotepack = $CFG['emotepack'] ?? null;
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

    public function getEmoteRules($escape = 1)
    {
        global $CFG,$DB;
        if ($this->textRules === null) {
            $this->getTextRules();
        }

        return $escape ? $this->emoteRules : $this->textRules['emote'];
    }

    public function emotes($a)
    {
        // Believe it or not, adding a space and then removing it later
        // is 20% faster than doing (^|\s).
        $emoticonlimit = 15;
        $this->getTextRules();
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
        global $CFG,$DB;
        if ($this->textRules === null) {
            $this->getTextRules();
        }

        return $this->textRules['badword'];
    }

    public function wordfilter($a)
    {
        global $USER;
        if ($USER && $USER['nowordfilter']) {
            return $a;
        }

        $this->getTextRules();

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

    public function hiddenFormFields($a): string
    {
        $r = '';
        foreach ($a as $k => $v) {
            $r .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />';
        }

        return $r;
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

    public function bbcode_videocallback($m)
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
        $lis = '';
        $m[2] = preg_split("@(^|[\r\n])\\*@", (string) $m[2]);
        foreach ($m[2] as $v) {
            if (trim($v) === '') {
                continue;
            }

            if (trim($v) === '0') {
                continue;
            }

            $lis .= '<li>' . $v . ' </li>';
        }

        return '<' . $m[1] . '>' . $lis . '</' . $m[1] . '>';
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
        global $DB,$CFG;
        $a = $a[1];
        if ($this->attachmentdata[$a]) {
            $data = $this->attachmentdata[$a];
        } else {
            $result = $DB->safeselect(
                [
                    'id',
                    'ip',
                    'name',
                    'hash',
                    'uid',
                    'size',
                    'downloads',
                ],
                'files',
                'WHERE `id`=?',
                $a,
            );
            $data = $DB->arow($result);
            $DB->disposeresult($result);
            if (!$data) {
                return "Attachment doesn't exist";
            }
            $data['ip'] = $JAX->bin2ip($data['ip']);

            $this->attachmentdata[$a] = $data;
        }

        $ext = explode('.', (string) $data['name']);
        $ext = count($ext) === 1 ? '' : mb_strtolower(array_pop($ext));

        if (!in_array($ext, $CFG['images'])) {
            $ext = '';
        }

        if ($ext !== '' && $ext !== '0') {
            $ext = '.' . $ext;
        }

        if ($ext !== '' && $ext !== '0') {
            return '<a href="' . BOARDPATHURL . '/Uploads/' . $data['hash'] . $ext . '">'
                . '<img src="' . BOARDPATHURL . 'Uploads/' . $data['hash'] . $ext . '" '
                . 'class="bbcodeimg" /></a>';
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

    public function parse_activity($a, $rssversion = false): array|string
    {
        global $PAGE,$USER;
        $user = $PAGE->meta(
            'user-link',
            $a['uid'],
            $a['group_id'],
            $USER && $USER['id'] === $a['uid'] ? 'You' : $a['name'],
        );
        $otherguy = $PAGE->meta(
            'user-link',
            $a['aff_id'],
            $a['aff_group_id'],
            $a['aff_name'],
        );
        $r = '';

        switch ($a['type']) {
            case 'profile_comment':
                $r = $rssversion ? [
                    'link' => '?act=vu' . $a['aff_id'],
                    'text' => $a['name'] . ' commented on '
                    . $a['aff_name'] . "'s profile",
                ] : $user . ' commented on ' . $otherguy . "'s profile";

                break;

            case 'new_post':
                $r = $rssversion ? [
                    'link' => '?act=vt' . $a['tid'] . '&findpost=' . $a['pid'],
                    'text' => $a['name'] . ' posted in topic ' . $a['arg1'],
                ] : $user . ' posted in topic <a href="?act=vt' . $a['tid']
                    . '&findpost=' . $a['pid'] . '">' . $a['arg1'] . '</a>, '
                    . $this->smalldate($a['date']);

                break;

            case 'new_topic':
                $r = $rssversion ? [
                    'link' => '?act=vt' . $a['tid'],
                    'text' => $a['name'] . ' created new topic ' . $a['arg1'],
                ] : $user . ' created new topic <a href="?act=vt' . $a['tid']
                    . '">' . $a['arg1'] . '</a>, ' . $this->smalldate($a['date']);

                break;

            case 'profile_name_change':
                $r = $rssversion ? [
                    'link' => '?act=vu' . $a['uid'],
                    'text' => $a['arg1'] . ' is now known as ' . $a['arg2'],
                ] : $PAGE->meta(
                    'user-link',
                    $a['uid'],
                    $a['group_id'],
                    $a['arg1'],
                ) . ' is now known as ' . $PAGE->meta(
                    'user-link',
                    $a['uid'],
                    $a['group_id'],
                    $a['arg2'],
                ) . ', ' . $this->smalldate($a['date']);

                break;

            case 'buddy_add':
                $r = $rssversion ? [
                    'link' => '?act=vu' . $a['uid'],
                    'text' => $a['name'] . ' made friends with ' . $a['aff_name'],
                ] : $user . ' made friends with ' . $otherguy;

                break;
        }

        if ($rssversion) {
            $r['link'] = $this->blockhtml($r['link']);

            return $r;
        }

        return '<div class="activity ' . $a['type'] . '">' . $r . '</div>';
    }

    public function isurl($url): false|int
    {
        return preg_match('@^https?://[\w\.\-%\&\?\=/]+$@', (string) $url);
    }

    public function isemail($email): false|int
    {
        return preg_match('/[\w\+.]+@[\w.]+/', (string) $email);
    }

    public function ipbanned($ip = false)
    {
        global $PAGE;

        if (!$ip) {
            $ip = $this->getIp();
        }

        if ($this->ipbancache === null) {
            if ($PAGE) {
                $PAGE->debug('loaded ip ban list');
            }

            $this->ipbancache = [];
            if (file_exists(BOARDPATH . '/bannedips.txt')) {
                foreach (file(BOARDPATH . '/bannedips.txt') as $v) {
                    $v = trim($v);
                    if ($v === '') {
                        continue;
                    }

                    if ($v === '0') {
                        continue;
                    }

                    if ($v[0] === '#') {
                        continue;
                    }

                    $this->ipbancache[] = $v;
                }
            }
        }

        foreach ($this->ipbancache as $v) {
            if (
                (mb_substr((string) $v, -1) === ':' || mb_substr((string) $v, -1) === '.')
                && mb_strtolower(mb_substr((string) $ip, 0, mb_strlen((string) $v))) === $v
            ) {
                return $v;
            }

            if ($v === $ip) {
                return $v;
            }
        }

        return false;
    }

    /**
     * Check if an IP is banned from the service.
     * Will use the $this->getIp() ipAddress field is left empty.
     *
     * @param string $ipAddress the IP Address to check
     *
     * @return bool if the IP is banned form the service or not
     */
    public function ipServiceBanned($ipAddress = false): bool
    {
        global $DB,$CFG;

        if (!$CFG['service']) {
            // Can't be service banned if there's no service.
            return false;
        }

        if (!$ipAddress) {
            $ipAddress = $this->getIp();
        }

        $result = $DB->safespecial(
            <<<'EOT'
                SELECT COUNT(`ip`) as `banned`
                    FROM `banlist`
                    WHERE ip = ?
                EOT
            ,
            [],
            $DB->basicvalue($JAX->ip2bin($ipAddress)),
        );
        $row = $DB->arow($result);
        $DB->disposeresult($result);

        return !isset($row['banned']) || $row['banned'] > 0;
    }

    public function getIp()
    {
        global $_SERVER;

        return $_SERVER['REMOTE_ADDR'];
    }

    public function ip2bin($ip = false): false|string
    {
        if (!$ip) {
            $ip = $this->getIp();
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            // Not an IP, so don't need to send anything back.
            return '';
        }

        return inet_pton($ip);
    }

    // This comment suggests MySQL's aton is different from php's pton, so
    // we need to do something for mysql IP addresses:
    // https://secure.php.net/manual/en/function.inet-ntop.php#117398
    // .
    public function bin2ip($ip): string
    {
        $l = mb_strlen($ip);

        return inet_ntop($ip) ?: inet_ntop(pack('A' . $l, $ip));
    }

    public function parseperms($permstoparse, $uid = false): array
    {
        global $PERMS;
        $permstoparse .= '';
        if ($permstoparse === '' || $permstoparse === '0') {
            $permstoparse = '0';
        }

        if ($permstoparse !== '0') {
            if ($uid !== false) {
                $unpack = unpack('n*', $permstoparse);
                $permstoparse = [];
                $counter = count($unpack);
                for ($x = 1; $x < $counter; $x += 2) {
                    $permstoparse[$unpack[$x]] = $unpack[$x + 1];
                }

                $permstoparse = $permstoparse[$uid] ?? null;
            }
        } else {
            $permstoparse = null;
        }

        if ($permstoparse === null) {
            return [
                'poll' => $PERMS['can_poll'],
                'read' => 1,
                'reply' => $PERMS['can_post'],
                'start' => $PERMS['can_post_topics'],
                'upload' => $PERMS['can_attach'],
                'view' => 1,
            ];
        }

        return [
            'poll' => $permstoparse & 32,
            'read' => $permstoparse & 8,
            'reply' => $permstoparse & 2,
            'start' => $permstoparse & 4,
            'upload' => $permstoparse & 1,
            'view' => $permstoparse & 16,
        ];
    }

    public function parsereadmarkers($readmarkers)
    {
        if ($readmarkers) {
            return json_decode((string) $readmarkers, true) ?? [];
        }

        return [];
    }

    public function rmdir($dir): bool
    {
        if (mb_substr((string) $dir, -1) !== '/') {
            $dir .= '/';
        }

        foreach (glob($dir . '*') as $v) {
            if (is_dir($v)) {
                $this->rmdir($v);
            } else {
                unlink($v);
            }
        }

        rmdir($dir);

        return true;
    }

    public function pages($numpages, $active, $tofill)
    {
        $tofill -= 2;
        $pages[] = 1;
        if ($numpages === 1) {
            return $pages;
        }

        $start = $active - floor($tofill / 2);
        if ($numpages - $start < $tofill) {
            $start -= $tofill - ($numpages - $start);
        }

        if ($start <= 1) {
            $start = 2;
        }

        for ($x = 0; $x < $tofill && ($start + $x) < $numpages; ++$x) {
            $pages[] = $x + $start;
        }

        $pages[] = $numpages;

        return $pages;
    }

    public function filesize($bs): string
    {
        $p = 0;
        $sizes = ' KMGT';
        while ($bs > 1024) {
            $bs /= 1024;
            ++$p;
        }

        return round($bs, 2) . ' ' . ($p !== 0 ? $sizes[$p] : '') . 'B';
    }

    public function gethostbyaddr($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ptr = implode(
                '.',
                array_reverse(
                    explode(
                        '.',
                        (string) $ip,
                    ),
                ),
            ) . '.in-addr.arpa';
            $host = dns_get_record($ptr, DNS_PTR);

            return $host ? $host[0]['target'] : $ip;
        }

        return gethostbyaddr($ip);
    }

    public function mail($email, $topic, $message)
    {
        global $CFG, $_SERVER;

        $boardname = $CFG['boardname'] ?: 'JaxBoards';
        $boardurl = 'https://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
        $boardlink = "<a href='https://" . $boardurl . "'>" . $boardname . '</a>';

        return @mail(
            (string) $email,
            $boardname . ' - ' . $topic,
            str_replace(
                ['{BOARDNAME}', '{BOARDURL}', '{BOARDLINK}'],
                [$boardname, $boardurl, $boardlink],
                $message,
            ),
            'MIME-Version: 1.0' . PHP_EOL
            . 'Content-type:text/html;charset=iso-8859-1' . PHP_EOL
            . 'From: ' . $CFG['mail_from'] . PHP_EOL,
        );
    }

    // phpcs:disable SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    private function youtubeEmbedHTML(
        string $link,
        string $embedUrl,
    ): string {
        // phpcs:disable Generic.Files.LineLength.TooLong
        return <<<HTML
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
            HTML;
        // phpcs:enable
    }
}
