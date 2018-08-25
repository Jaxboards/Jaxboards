<?php

class JAX
{
    public $userPerms = '';
    public $c = array();
    public $g = array();
    public $p = array();
    public $s = array();
    public $b = array();
    public $textRules;

    public function __construct()
    {
        $this->c = $this->filterInput($_COOKIE);
        $this->g = $this->filterInput($_GET);
        $this->p = $this->filterInput($_POST);
        $this->s = $this->filterInput($_SESSION);
        $this->b = array_merge($this->p, $this->g);
        $this->textRules = null;
    }

    public function between($a, $b, $c)
    {
        return $a >= $b && $a <= $c;
    }

    public function date($date, $autodate = true)
    {
        if (!$date) {
            return false;
        }
        $delta = time() - $date;
        $fmt = '';
        if ($delta < 90) {
            $fmt = 'a minute ago';
        } elseif ($delta < 3600) {
            $fmt = round($delta / 60).' minutes ago';
        } elseif (date('m j Y') == date('m j Y', $date)) {
            $fmt = 'Today @ '.date('g:i a', $date);
        } elseif (date('m j Y', strtotime('yesterday')) == date('m j Y', $date)) {
            $fmt = 'Yesterday @ '.date('g:i a', $date);
        } else {
            $fmt = date('M jS, Y @ g:i a', $date);
        }
        if (!$autodate) {
            return $fmt;
        }

        return "<span class='autodate' title='${date}'>${fmt}</span>";
    }

    public function smalldate($date, $seconds = false, $autodate = false)
    {
        if (!$date) {
            return false;
        }

        return ($autodate ?
            '<span class="autodate smalldate" title="'.$date.'">' :
            '').
            date('g:i'.($seconds ? ':s' : '').'a, n/j/y', $date).
            ($autodate ? '</span>' : '');
    }

    public static function json_encode($a, $forceaa = false)
    {
        if ($forceaa) {
            return json_encode(json_decode(json_encode($a), true));
        }

        return json_encode($a);
    }

    public static function json_decode($a, $aa = true)
    {
        return json_decode($a, $aa);
    }

    public static function utf8_encode($a)
    {
        if (is_array($a)) {
            foreach ($a as $k => $v) {
                $a[$k] = self::utf8_encode($v);
            }
        } else {
            $a = utf8_encode($a);
        }

        return $a;
    }

    public static function is_numerical_array($a)
    {
        return range(0, count($a) - 1) == array_keys($a);
    }

    public function setCookie($a, $b = 'false', $c = false, $htmlonly = true)
    {
        if (!is_array($a)) {
            $a = array($a => $b);
        } elseif ('false' != $b) {
            $c = $b;
        }
        foreach ($a as $k => $v) {
            $this->c[$k] = $v;
            setcookie($k, $v, $c, null, null, true, $htmlonly);
        }
    }

    public function linkify($a)
    {
        $a = str_replace('<IP>', $this->getIp(), $a);

        return preg_replace_callback(
            '@(^|\\s)(https?://[^\\s\\)\\(<>]+)@',
            array($this, 'linkify_callback'),
            $a
        );
    }

    public function linkify_callback($match)
    {
        $url = parse_url($match[2]);
        if (!$url['fragment'] && $url['query']) {
            $url['fragment'] = $url['query'];
        }
        if ($url['host'] == $_SERVER['HTTP_HOST'] && $url['fragment']) {
            if (preg_match('@act=vt(\\d+)@', $url['fragment'], $m)) {
                if (preg_match('@pid=(\\d+)@', $url['fragment'], $m2)) {
                    $nice = 'Post #'.$m2[1];
                } else {
                    $nice = 'Topic #'.$m[1];
                }
            }
            $match[2] = '?'.$url['fragment'];
        }

        return $match[1].'[url='.$match[2].']'.($nice ? $nice : $match[2]).'[/url]';
    }

    public function filterInput($a)
    {
        if (!get_magic_quotes_gpc()) {
            return $a;
        }
        if (is_array($a)) {
            return array_map(array($this, 'filterInput'), $a);
        }

        return stripslashes($a);
    }

    /* the getSess and getUser functions both return the
     * session/user data respectively
     * if not found in the database, getSess inserts a blank Sess row,
     * while getUser returns false
     */

    public function getUser($uid = false, $pass = false)
    {
        global $DB;
        if (!$DB) {
            return;
        }
        if (!$uid) {
            return $this->userData = false;
        }
        $result = $DB->safeselect(
            <<<'EOT'
`id`,`pass`,`group_id`,`sound_im`,`sound_shout`,`last_visit`,`display_name`,
`friends`,`enemies`,`skin_id`,`nowordfilter`,`wysiwyg`,`avatar`,
INET6_NTOA(`ip`) AS `ip`,`usertitle`,
CONCAT(`dob_month`,' ',`dob_day`) as `birthday`,`mod`,`posts`
EOT
            ,
            'members',
            'WHERE `id`=?',
            $DB->basicvalue($uid)
        );
        $user = $DB->arow($result);
        if (!$user || !is_array($user) || empty($user)) {
            return $this->userData = false;
        }
        $DB->disposeresult($result);
        $user['birthday'] = (date('n j') == $user['birthday'] ? 1 : 0);

        // password fun
        if (false !== $pass) {
            $verified_password = password_verify($pass, $user['pass']);
            if (!$verified_password) {
                // check if it's an old md5 hash
                if (md5($pass) === $user['pass']) {
                    $verified_password = true;
                    $needs_rehash = true;
                }
            } else {
                $needs_rehash = password_needs_rehash(
                    $user['pass'],
                    PASSWORD_DEFAULT
                );
            }
            if ($verified_password && $needs_rehash) {
                $new_hash = password_hash($pass, PASSWORD_DEFAULT);
                // add the new hash
                $DB->safeupdate(
                    'members',
                    array(
                        'pass' => $new_hash,
                    ),
                    'WHERE `id` = ?',
                    $user['id']
                );
            }

            if ($verified_password) {
                unset($user['pass']);
            } else {
                return $this->userData = false;
            }
        }

        return $this->userData = $user;
    }

    public function getPerms($group_id = '')
    {
        global $DB;
        if ('' === $group_id && $this->userPerms) {
            return $this->userPerms;
        }

        if ('' === $group_id) {
            $group_id = $this->userData['group_id'];
        }
        if ($this->ipbanned()) {
            $this->userData['group_id'] = $group_id = 4;
        }
        $result = $DB->safeselect(
            <<<'EOT'
`id`,`title`,`can_post`,`can_edit_posts`,`can_post_topics`,`can_edit_topics`,
`can_add_comments`,`can_delete_comments`,`can_view_board`,
`can_view_offline_board`,`flood_control`,`can_override_locked_topics`,
`icon`,`can_shout`,`can_moderate`,`can_delete_shouts`,`can_delete_own_shouts`,
`can_karma`,`can_im`,`can_pm`,`can_lock_own_topics`,`can_delete_own_topics`,
`can_use_sigs`,`can_attach`,`can_delete_own_posts`,`can_poll`,`can_access_acp`,
`can_view_shoutbox`,`can_view_stats`,`legend`,`can_view_fullprofile`
EOT
            ,
            'member_groups',
            'WHERE `id`=?',
            $this->pick($group_id, 3)
        );
        $retval = $this->userPerms = $DB->arow($result);
        $DB->disposeresult($result);

        return $retval;
    }

    public function blockhtml($a)
    {
        return str_replace('{if', '&#123;if', htmlspecialchars($a, ENT_QUOTES)); //fix for template conditionals
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
            ''
        );
        $textRules = array(
            'emote' => array(),
            'bbcode' => array(),
            'badword' => array(),
        );
        while ($f = $DB->arow($q)) {
            $textRules[$f['type']][$f['needle']] = $f['replacement'];
        }
        //load emoticon pack
        $emotepack = isset($CFG['emotepack']) ? $CFG['emotepack'] : null;
        if ($emotepack) {
            $emotepack = 'emoticons/'.$emotepack;
            if ('/' != substr($emotepack, -1)) {
                $emotepack .= '/';
            }
            if (file_exists($emotepack.'rules.php')) {
                require_once $emotepack.'rules.php';
                if (!$rules) {
                    die('Emoticon ruleset corrupted!');
                }
                foreach ($rules as $k => $v) {
                    if (!isset($textRules['emote'][$k])) {
                        $textRules['emote'][$k] = $emotepack.$v;
                    }
                }
            }
        }
        $nrules = array();
        foreach ($textRules['emote'] as $k => $v) {
            $nrules[preg_quote($k, '@')]
                = '<img src="'.$v.'" alt="'.$this->blockhtml($k).'"/>';
        }
        $this->emoteRules = empty($nrules) ? false : $nrules;
        $this->textRules = $textRules;

        return $this->textRules;
    }

    public function getEmoteRules($escape = 1)
    {
        global $CFG,$DB;
        //legacy code to update to new system, remove this after 5/1
        if (file_exists(BOARDPATH.'emoticons.php')) {
            require_once BOARDPATH.'emoticons.php';
            foreach ($emoticons as $k => $v) {
                $DB->safeinsert(
                    'textrules',
                    array(
                        'type' => 'emote',
                        'needle' => $k,
                        'replacement' => $v,
                    )
                );
            }
            unlink(BOARDPATH.'emoticons.php');
            foreach ($emoticons as $k => $v) {
                $nrules[($escape ? preg_quote($k, '@') : $k)]
                    = '<img src="'.$v.'" />';
            }
        }
        $this->getTextRules();

        return $escape ? $this->emoteRules : $this->textRules['emote'];
    }

    public function emotes($a)
    {
        //believe it or not, adding a space and then removing it later
        //is 20% faster than doing (^|\s)
        $emoticonlimit = 15;
        $this->getTextRules();
        if (!$this->emoteRules) {
            return $a;
        }
        $a = preg_replace_callback(
            '@(\\s)('.implode('|', array_keys($this->emoteRules)).')@',
            array(
                $this,
                'emotecallback',
            ),
            ' '.$a,
            $emoticonlimit
        );

        return substr($a, 1);
    }

    public function emotecallback($a)
    {
        return $a[1].$this->emoteRules[preg_quote($a[2], '@')];
    }

    public function getwordfilter()
    {
        global $CFG,$DB;
        if (!isset($this->textRules)) {
            $wordfilter = array();
            //legacy code to update to new system, remove this after 5/1
            if (file_exists(BOARDPATH.'wordfilter.php')) {
                include_once BOARDPATH.'wordfilter.php';
                foreach ($wordfilter as $k => $v) {
                    $DB->safeinsert(
                        'textrules',
                        array(
                            'type' => 'badword',
                            'needle' => $k,
                            'replacement' => $v,
                        )
                    );
                }
                unlink(BOARDPATH.'wordfilter.php');
            }
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
            $a
        );
    }

    public function startcodetags(&$a)
    {
        preg_match_all('@\\[code(=\\w+)?\\](.*?)\\[/code\\]@is', $a, $codes);
        foreach ($codes[0] as $k => $v) {
            $a = str_replace($v, '[code]'.$k.'[/code]', $a);
        }

        return $codes;
    }

    public function finishcodetags($a, $codes, $returnbb = false)
    {
        foreach ($codes[0] as $k => $v) {
            if (!$returnbb) {
                if ('=php' == $codes[1][$k]) {
                    $codes[2][$k] = highlight_string($codes[2][$k], 1);
                } else {
                    $codes[2][$k] = preg_replace(
                        "@([ \r\n]|^) @m",
                        '$1&nbsp;',
                        $this->blockhtml($codes[2][$k])
                    );
                }
            }
            $a = str_replace(
                '[code]'.$k.'[/code]',
                $returnbb ?
                '[code'.$codes[1][$k].']'.$codes[2][$k].'[/code]' :
                '<div onclick="JAX.select(this)" class="code'.
                ($codes[1][$k] ? ' '.$codes[1][$k] : '').'">'.
                $codes[2][$k].'</div>',
                $a
            );
        }

        return $a;
    }

    public function hiddenFormFields($a)
    {
        $r = '';
        foreach ($a as $k => $v) {
            $r .= '<input type="hidden" name="'.$k.'" value="'.$v.'" />';
        }

        return $r;
    }

    public function textonly($a)
    {
        while (($t = preg_replace(
            '@\\[(\\w+)[^\\]]*\\]([\\w\\W]*)\\[/\\1\\]@U',
            '$2',
            $a
        )) != $a) {
            $a = $t;
        }

        return $a;
    }

    public function bbcodes($a, $minimal = false)
    {
        $x = 0;
        $bbcodes = array(
            '@\\[b\\](.*)\\[/b\\]@Usi' => '<strong>$1</strong>',
            '@\\[i\\](.*)\\[/i\\]@Usi' => '<em>$1</em>',
            '@\\[u\\](.*)\\[/u\\]@Usi' => '<span style="text-decoration:underline">$1</span>',
            '@\\[s\\](.*)\\[/s\\]@Usi' => '<span style="text-decoration:line-through">$1</span>',
            '@\\[blink\\](.*)\\[/blink\\]@Usi' => '<span style="text-decoration:blink">$1</span>',
            '@\\[url=(http|ftp|\\?|mailto:)([^\\]]+)\\](.+?)\\[/url\\]@i' => '<a href="$1$2" rel="nofollow">$3</a>',
            '@\\[spoiler\\](.*)\\[/spoiler\\]@Usi' => '<span class="spoilertext">$1</span>',
            '@\\[url\\](http|ftp|\\?)(.*)\\[/url\\]@Ui' => '<a href="$1$2" rel="nofollow">$1$2</a>',
            '@\\[font=([\\s\\w]+)](.*)\\[/font\\]@Usi' => '<span style="font-family:$1">$2</span>',
            '@\\[color=(#?[\\s\\w\\d]+|rgb\\([\\d, ]+\\))\\](.*)\\[/color\\]@Usi' => '<span style="color:$1">$2</span>',
            '@\\[(bg|bgcolor|background)=(#?[\\s\\w\\d]+)\\](.*)\\[/\\1\\]@Usi' => '<span style="background:$2">$3</span>',
        );

        if (!$minimal) {
            $bbcodes['@\\[h([1-5])\\](.*)\\[/h\\1\\]@Usi'] = '<h$1>$2</h$1>';
            $bbcodes['@\\[align=(center|left|right)\\](.*)\\[/align\\]@Usi']
                = '<p style="text-align:$1">$2</p>';
            $bbcodes['@\\[img(?:=([^\\]]+|))?\\]((?:http|ftp)\\S+)\\[/img\\]@i']
                = '<img src="$2" title="$1" alt="$1" class="bbcodeimg" '.
                'align="absmiddle" />';
            $a = preg_replace_callback(
                '@\\[video\\](.*)\\[/video\\]@Ui',
                array($this, 'bbcode_videocallback'),
                $a
            );
        }
        $keys = array_keys($bbcodes);
        $values = array_values($bbcodes);
        while (($tmp = preg_replace($keys, $values, $a)) != $a) {
            $a = $tmp;
        }

        if ($minimal) {
            return $a;
        }

        //ul/li tags
        while ($a != ($tmp = preg_replace_callback(
            '@\\[(ul|ol)\\](.*)\\[/\\1\\]@Usi',
            array($this, 'bbcode_licallback'),
            $a
        ))) {
            $a = $tmp;
        }
        //size code (actually needs a callback simply because of
        //the variability of the arguments)
        while ($a != ($tmp = preg_replace_callback(
            '@\\[size=([0-4]?\\d)(px|pt|em|)\\](.*)\\[/size\\]@Usi',
            array($this, 'bbcode_sizecallback'),
            $a
        ))) {
            $a = $tmp;
        }

        //do quote tags
        while (preg_match(
            '@\\[quote(?>=([^\\]]+))?\\](.*?)\\[/quote\\]\\r?\\n?@is',
            $a,
            $m
        ) && $x < 10) {
            ++$x;
            $a = str_replace(
                $m[0],
                '<div class="quote">'.
                ($m[1] ? '<div class="quotee">'.$m[1].'</div>' : '').
                $m[2].'</div>',
                $a
            );
        }

        return $a;
    }

    public function bbcode_sizecallback($m)
    {
        return '<span style="font-size:'.
            $m[1].($m[2] ? $m[2] : 'px').'">'.$m[3].'</span>';
    }

    public function bbcode_videocallback($m)
    {
        if (false !== strpos($m[1], 'youtube')) {
            preg_match('@t=(\\d+m)?(\\d+s)?@', $m[0], $time);
            preg_match('@v=([\\w-]+)@', $m[1], $m);
            if ($time) {
                $m[2] = (($time[1] ? substr($time[1], 0, -1) * 60 : 0) +
                    substr($time[2], 0, -1));
            }

            $youtubeLink = 'https://www.youtube.com/watch?v='.
                $m[1].($m[2] ? '&t=' : '').$m[2];
            $popoutCode = 'var w=new JAX.window;w.title=this.href;'.
                'w.content=$$(\'.movie\',this.parentNode.parentNode).innerHTML;'.
                'w.create();return false;';
            $inlineCode = '$$(\'.movie\',this.parentNode.parentNode)'.
                '.style.display=\'block\';return false;';
            $youtubeEmbed = 'https://www.youtube.com/embed/'.$m[1].
                '?start='.$m[2];

            return
                <<<EOT
<div class="media youtube">
    <div class="summary">
        Watch Youtube Video:
        <a href="${youtubeLink}">
            ${youtubeLink}
        </a>
    </div>
    <div class="open">
        <a href="${youtubeLink}"
            onclick="${popoutCode}">
            Popout
        </a>
        &middot;
        <a href="${youtubeLink}"
            onclick="${inlineCode}">
            Inline
        </a>
    </div>
    <div class="movie" style="display:none">
        <iframe width="560" height="315" frameborder="0"
            allowfullscreen=""
            src="${youtubeEmbed}">
        </iframe>
    </div>
</div>
EOT;
        }
        if (false !== strpos($m[1], 'vimeo')) {
            preg_match('@(?:vimeo.com|video)/(\\d+)@', $m[1], $id);

            $vimeoLink = 'https://vimeo.com/'.$id[1];
            $popoutCode = 'var w=new JAX.window;w.title=this.href;'.
                'w.content=$$(\'.movie\',this.parentNode.parentNode)'.
                '.innerHTML;w.create();return false;';
            $inlineCode = '$$(\'.movie\',this.parentNode.parentNode)'.
                '.style.display=\'block\';return false;';
            $vimeoEmbed = 'https://player.vimeo.com/video/'.
                $id[1].'?title=0&byline=0&portrait=0';

            return <<<EOT
<div class="media vimeo">
    <div class="summary">
        Watch Vimeo Video:
        <a href="${vimeoLink}">
            ${vimeoLink}
        </a>
    </div>
    <div class="open">
        <a href="${vimeoLink}" onclick="${popoutCode}">
            Popout
        </a>
        &middot;
        <a href="${vimeoLink}" onclick="${inlineCode}">
            Inline
        </a>
    </div>
    <div class="movie" style="display:none">
        <iframe src="${vimeoEmbed}" width="400" height="300" frameborder="0"
            webkitAllowFullScreen allowFullScreen></iframe>
    </div>
</div>
EOT;
        }

        return '-Invalid Video URL-';
    }

    public function bbcode_licallback($m)
    {
        $lis = '';
        $m[2] = preg_split("@(^|[\r\n])\\*@", $m[2]);
        foreach ($m[2] as $v) {
            if (trim($v)) {
                $lis .= '<li>'.$v.' </li>';
            }
        }

        return '<'.$m[1].'>'.$lis.'</'.$m[1].'>';
    }

    public function attachments($a)
    {
        return $a = preg_replace_callback(
            '@\\[attachment\\](\\d+)\\[/attachment\\]@',
            array($this, 'attachment_callback'),
            $a,
            20
        );
    }

    public function attachment_callback($a)
    {
        global $DB,$CFG;
        $a = $a[1];
        if ($this->attachmentdata[$a]) {
            $data = $this->attachmentdata[$a];
        } else {
            $result = $DB->safeselect(
                <<<'EOT'
`id`,`name`,`hash`,`uid`,`size`,`downloads`,INET6_NTOA(`ip`) AS `ip`
EOT
                ,
                'files',
                'WHERE `id`=?',
                $a
            );
            $data = $DB->arow($result);
            $DB->disposeresult($result);
            if (!$data) {
                return "Attachment doesn't exist";
            }
            $this->attachmentdata[$a] = $data;
        }

        $ext = explode('.', $data['name']);
        if (1 == count($ext)) {
            $ext = '';
        } else {
            $ext = strtolower(array_pop($ext));
        }
        if (!in_array($ext, $CFG['images'])) {
            $ext = '';
        }
        if ($ext) {
            $ext = '.'.$ext;
        }

        if ($ext) {
            return '<a href="'.BOARDPATHURL.'/Uploads/'.$data['hash'].$ext.'">'.
                '<img src="'.BOARDPATHURL.'Uploads/'.$data['hash'].$ext.'" '.
                'class="bbcodeimg" /></a>';
        }

        return '<div class="attachment">'.
            '<a href="index.php?act=download&id='.
            $data['id'].'&name='.urlencode($data['name']).'" class="name">'.
            $data['name'].'</a> Downloads: '.$data['downloads'].'</div>';
    }

    public function theworks($a, $cfg = array())
    {
        if (@!$cfg['nobb'] && @!$cfg['minimalbb']) {
            $codes = $this->startcodetags($a);
        }
        $a = $this->blockhtml($a);
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
        $a = $this->wordfilter($a);

        return nl2br($a);
    }

    public function parse_activity($a, $rssversion = false)
    {
        global $PAGE,$USER;
        $user = $PAGE->meta(
            'user-link',
            $a['uid'],
            $a['group_id'],
            $USER['id'] == $a['uid'] ? 'You' : $a['name']
        );
        $otherguy = $PAGE->meta(
            'user-link',
            $a['aff_id'],
            $a['aff_group_id'],
            $a['aff_name']
        );
        $r = '';
        switch ($a['type']) {
        case 'profile_comment':
            if ($rssversion) {
                $r = array(
                    'text' => $a['name'].' commented on '.
                    $a['aff_name']."'s profile",
                    'link' => '?act=vu'.$a['aff_id'],
                );
            } else {
                $r = $user.' commented on '.$otherguy.'\'s profile';
            }
            break;
        case 'new_post':
            if ($rssversion) {
                $r = array(
                    'text' => $a['name'].' posted in topic '.$a['arg1'],
                    'link' => '?act=vt'.$a['tid'].'&findpost='.$a['pid'],
                );
            } else {
                $r = $user.' posted in topic <a href="?act=vt'.$a['tid'].
                    '&findpost='.$a['pid'].'">'.$a['arg1'].'</a>, '.
                    $this->smalldate($a['date']);
            }
            break;
        case 'new_topic':
            if ($rssversion) {
                $r = array(
                    'text' => $a['name'].' created new topic '.$a['arg1'],
                    'link' => '?act=vt'.$a['tid'],
                );
            } else {
                $r = $user.' created new topic <a href="?act=vt'.$a['tid'].
                    '">'.$a['arg1'].'</a>, '.$this->smalldate($a['date']);
            }
            break;
        case 'profile_name_change':
            if ($rssversion) {
                $r = array(
                    'text' => $a['arg1'].' is now known as '.$a['arg2'],
                    'link' => '?act=vu'.$a['uid'],
                );
            } else {
                $r = $PAGE->meta(
                    'user-link',
                    $a['uid'], $a['group_id'], $a['arg1']
                ).' is now known as '.$PAGE->meta(
                    'user-link',
                    $a['uid'],
                    $a['group_id'],
                    $a['arg2']
                ).', '.$this->smalldate($a['date']);
            }
            break;
        case 'buddy_add':
            if ($rssversion) {
                $r = array(
                    'text' => $a['name'].' made friends with '.$a['aff_name'],
                    'link' => '?act=vu'.$a['uid'],
                );
            } else {
                $r = $user.' made friends with '.$otherguy;
            }
            break;
        }
        if ($rssversion) {
            $r['link'] = $this->blockhtml($r['link']);

            return $r;
        }

        return '<div class="activity '.$a['type'].'">'.$r.'</div>';
    }

    public static function pick()
    {
        $args = func_get_args();
        foreach ($args as $v) {
            if ($v) {
                break;
            }
        }

        return $v;
    }

    public function isurl($url)
    {
        return preg_match('@^https?://[\\w\\.\\-%\\&\\?\\=/]+$@', $url);
    }

    public function isemail($email)
    {
        return preg_match('/[\\w\\+.]+@[\\w.]+/', $email);
    }

    public function ipbanned($ip = false)
    {
        if (!$ip) {
            $ip = $this->getIp();
        }
        global $PAGE;
        if (!isset($this->ipbancache)) {
            if ($PAGE) {
                $PAGE->debug('loaded ip ban list');
            }
            $this->ipbancache = array();
            if (file_exists(BOARDPATH.'/bannedips.txt')) {
                foreach (file(BOARDPATH.'/bannedips.txt') as $v) {
                    $v = trim($v);
                    if ($v && '#' != $v[0]) {
                        $this->ipbancache[] = $v;
                    }
                }
            }
        }
        foreach ($this->ipbancache as $v) {
            if ((':' === mb_substr($v, -1) || '.' === mb_substr($v, -1))
                && mb_strtolower(mb_substr($ip, 0, strlen($v))) === $v
            ) {
                return $v;
            }
            if ($v == $ip) {
                return $v;
            }
        }

        return false;
    }

    public function getIp()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    public function ip2bin($ip = false)
    {
        if (!$ip) {
            $ip = $this->getIp();
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            // not an IP, so don't need to send anything back
            return '';
        }

        return inet_pton($ip);
    }

    // https://secure.php.net/manual/en/function.inet-ntop.php#117398
    // this comment suggests MySQL's aton is different from php's pton, so
    // we need to do something for mysql IP addresses
    public function bin2ip($ip = false)
    {
        if (!is_string($ip)) {
            return '';
        }
        $l = strlen($ip);
        if (4 == $l or 16 == $l) {
            return inet_ntop(pack('A'.$l, $ip));
        }

        return '';
    }

    public function parseperms($permstoparse, $uid = false)
    {
        global $PERMS;
        $permstoparse .= '';
        if (!$permstoparse) {
            $permstoparse = '0';
        }
        if ($permstoparse) {
            if (false !== $uid) {
                $unpack = unpack('n*', $permstoparse);
                $permstoparse = array();
                for ($x = 1; $x < count($unpack); $x += 2) {
                    $permstoparse[$unpack[$x]] = $unpack[$x + 1];
                }
                $permstoparse = $permstoparse[$uid];
            }
        } else {
            $permstoparse = null;
        }
        if (null === $permstoparse) {
            return array(
                'upload' => $PERMS['can_attach'],
                'reply' => $PERMS['can_post'],
                'start' => $PERMS['can_post_topics'],
                'read' => 1,
                'view' => 1,
                'poll' => $PERMS['can_poll'],
            );
        }

        return array(
            'upload' => $permstoparse & 1,
            'reply' => $permstoparse & 2,
            'start' => $permstoparse & 4,
            'read' => $permstoparse & 8,
            'view' => $permstoparse & 16,
            'poll' => $permstoparse & 32,
        );
    }

    public function parsereadmarkers($readmarkers)
    {
        $r = array();
        if ($readmarkers) {
            $unpack = JAX::base128decode($readmarkers);
            $l = count($unpack);
            for ($x = 0; $x < $l; $x += 2) {
                $r[$unpack[$x]] = $unpack[$x + 1];
            }
        }

        return $r;
    }

    public function rmdir($dir)
    {
        if ('/' != substr($dir, -1)) {
            $dir .= '/';
        }
        foreach (glob($dir.'*') as $v) {
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
        if (1 == $numpages) {
            return $pages;
        }
        $start = $active - floor($tofill / 2);
        if (($numpages - $start) < $tofill) {
            $start -= ($tofill - ($numpages - $start));
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

    public function filesize($bs)
    {
        $p = 0;
        $sizes = ' KMGT';
        while ($bs > 1024) {
            $bs /= 1024;
            ++$p;
        }

        return round($bs, 2).' '.($p ? $sizes[$p] : '').'B';
    }

    public function gethostbyaddr($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ptr = implode(
                '.',
                array_reverse(
                    explode(
                        '.',
                        $ip
                    )
                )
            ).'.in-addr.arpa';
            $host = dns_get_record($ptr, DNS_PTR);

            return !$host ? $ip : $host[0]['target'];
        }

        return gethostbyaddr($ip);
    }

    public static function base128encodesingle($int)
    {
        $int = (int) $int;
        $w = chr($int & 127);
        while ($int > 127) {
            $int >>= 7;
            $w = chr(($int & 127) | 128).$w;
        }

        return $w;
    }

    public function base128encode($ints, $preservekeys = false)
    {
        $r = '';
        foreach ($ints as $intkey => $int) {
            $r .= ($preservekeys ? JAX::base128encodesingle($intkey) : '').
                JAX::base128encodesingle($int);
        }

        return $r;
    }

    public static function base128decode($data)
    {
        $ints = array();
        $x = 0;
        while (isset($data[$x])) {
            $int = 0;
            do {
                $byte = (int) ord($data[$x]);
                if ($x) {
                    $int <<= 7;
                }
                $int |= ($byte & 127);
                ++$x;
            } while ($byte & 128);
            $ints[] = $int;
        }

        return $ints;
    }

    public function mail($email, $topic, $message)
    {
        global $CFG;
        $boardname = $CFG['boardname'] ? $CFG['boardname'] : 'JaxBoards';
        $boardurl = 'https://'.$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
        $boardlink = "<a href='https://".$boardurl."'>".$boardname.'</a>';

        return @mail(
            $email,
            $boardname.' - '.$topic,
            str_replace(
                array('{BOARDNAME}', '{BOARDURL}', '{BOARDLINK}'),
                array($boardname,   $boardurl,   $boardlink),
                $message
            ),
            'MIME-Version: 1.0'.PHP_EOL.
            'Content-type:text/html;charset=iso-8859-1'.PHP_EOL.
            'From: '.$CFG['mail_from'].PHP_EOL
        );
    }
}
