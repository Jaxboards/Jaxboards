<?php

if (!defined(INACP)) {
    die();
}

new settings();
class settings
{
    public function __construct()
    {
        global $JAX;
        $this->leftBar();
        if (!isset($JAX->b['do'])) {
            $JAX->b['do'] = false;
        }
        switch ($JAX->b['do']) {
            case 'emoticons':
                $this->emoticons();
                break;
            case 'wordfilter':
                $this->wordfilter();
                break;
            case 'bbcodes':
                $this->bbcodes();
                break;
            case 'postrating':
                $this->postrating();
                break;
        }
    }

    public function leftBar()
    {
        global $PAGE;
        $sidebar = '';
        $nav = array(
            '?act=posting&do=emoticons' => 'Emoticons',
            '?act=posting&do=wordfilter' => 'Word Filter',
            '?act=posting&do=postrating' => 'Post Rating',
        );
        foreach ($nav as $k => $v) {
            $sidebar .= "<li><a href='${k}'>${v}</a></li>";
        }
        $sidebar = "<ul>${sidebar}</ul>";
        $PAGE->sidebar($sidebar);
    }

    public function showmain()
    {
        global $PAGE;
        $PAGE->addContentBox('Error', 'This page is under construction!');
    }

    public function wordfilter()
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        $wordfilter = array();
        $result = $DB->safeselect(
            '`id`,`type`,`needle`,`replacement`,`enabled`',
            'textrules',
            "WHERE `type`='badword'"
        );
        while ($f = $DB->arow($result)) {
            $wordfilter[$f['needle']] = $f['replacement'];
        }

        //delete
        if (@$JAX->g['d']) {
            $DB->safedelete(
                'textrules',
                "WHERE `type`='badword' AND `needle`=?",
                $DB->basicvalue($JAX->g['d'])
            );
            unset($wordfilter[$JAX->g['d']]);
        }

        //insert
        if (@$JAX->p['submit']) {
            $JAX->p['badword'] = $JAX->blockhtml($JAX->p['badword']);
            if (!$JAX->p['badword'] || !$JAX->p['replacement']) {
                $page .= $PAGE->error('All fields required.');
            } elseif (isset($wordfilter[$JAX->p['badword']])
                && $wordfilter[$JAX->p['badword']]
            ) {
                $page .= $PAGE->error(
                    "'" . $JAX->p['badword'] . "' is already used."
                );
            } else {
                $DB->safeinsert(
                    'textrules',
                    array(
                        'type' => 'badword',
                        'needle' => $JAX->p['badword'],
                        'replacement' => $JAX->p['replacement'],
                    )
                );
                $wordfilter[$JAX->p['badword']] = $JAX->p['replacement'];
            }
        }
        $submit = <<<'EOT'
<tr>
    <td>
        <input type="text" name="badword"/>
    </td>
    <td>
        <input type="text" name="replacement"/>
    </td>
    <td>
        <input type="submit" value="Add" name="submit"/>
    </td>
</tr>
EOT;
        if (empty($wordfilter)) {
            $table = <<<'EOT'
<tr>
    <td colspan="3">
        Not currently filtering words.
    </td>
</tr>
EOT;
            $table .= $submit;
        } else {
            $table = <<<'EOT'
<tr>
    <th>
        Word
    </th>
    <th>
        Replacement
    </th>
    <th>
    </th>
</tr>
EOT;
            $table .= $submit;
            foreach (array_reverse($wordfilter, true) as $filter => $result) {
                $resultCode = $JAX->blockhtml($result);
                $filterUrlEncoded = rawurlencode($filter);
                $table .= <<<EOT
<tr>
    <td>
        ${filter}
    </td>
    <td>
        ${resultCode}
    </td>
    <td class="x">
        <a href="?act=posting&do=wordfilter&d=${filterUrlEncoded}">
            X
        </a>
    </td>
</tr>
EOT;
            }
        }
        $page .= <<<EOT
<form method="post" action="?act=posting&do=wordfilter">
    <table class="badwords">
        ${table}
    </table>
</form>
EOT;

        $PAGE->addContentBox('Word Filter', $page);
    }

    public function emoticons()
    {
        global $PAGE,$JAX,$DB;

        $basesets = array(
            'keshaemotes' => "Kesha's pack",
            'ploadpack' => "Pload's pack",
            '' => 'None',
        );
        $page = '';
        $emoticons = array();
        //delete emoticon
        if (@$JAX->g['d']) {
            $DB->safedelete(
                'textrules',
                "WHERE `type`='emote' AND `needle`=?",
                $DB->basicvalue($_GET['d'])
            );
        }
        //select emoticons
        $result = $DB->safeselect(
            '`id`,`type`,`needle`,`replacement`,`enabled`',
            'textrules',
            "WHERE `type`='emote'"
        );
        while ($f = $DB->arow($result)) {
            $emoticons[$f['needle']] = $f['replacement'];
        }

        //insert emoticon
        if (@$JAX->p['submit']) {
            if (!$JAX->p['emoticon'] || !$JAX->p['image']) {
                $page .= $PAGE->error('All fields required.');
            } elseif (isset($emoticons[$JAX->blockhtml($JAX->p['emoticon'])])) {
                $page .= $PAGE->error('That emoticon is already being used.');
            } else {
                $DB->safeinsert(
                    'textrules',
                    array(
                        'needle' => $JAX->blockhtml($JAX->p['emoticon']),
                        'replacement' => $JAX->p['image'],
                        'enabled' => 1,
                        'type' => 'emote',
                    )
                );
                $emoticons[$JAX->blockhtml($JAX->p['emoticon'])] = $JAX->p['image'];
            }
        }

        if (isset($JAX->p['baseset']) && $basesets[$JAX->p['baseset']]) {
            $PAGE->writeCFG(array('emotepack' => $JAX->p['baseset']));
        }
        $inputs = <<<'EOT'
<tr>
    <td>
        <input type="text" name="emoticon"/>
    </td>
    <td>
        <input type="text" name="image"/>
    </td>
    <td>
        <input type="checkbox" class="switch yn"/>
    </td>
    <td>
        <input type="submit" value="Add" name="submit"/>
    </td>
</tr>
EOT;
        if (empty($emoticons)) {
            $table = <<<'EOT'
<tr>
    <td colspan="3">
        No custom emotes!
    </td>
</tr>
<tr>
    <th>
        Emoticon
    </th>
    <th>
        Image
    </th>
    <th>
        In list?
    </th>
    <th>
    </th>
</tr>
EOT;
            $table .= $inputs;
        } else {
            $emoticons = array_reverse($emoticons, true);
            $table = <<<'EOT'
<tr>
    <th>
        Emoticon
    </th>
    <th>
        Image
    </th>
    <th>
        In list?
    </th>
    <th>
    </th>
</tr>
EOT;
            $table .= $inputs;
            foreach ($emoticons as $smiley => $image) {
                $imageCode = $JAX->blockhtml($image);
                $smileyUrlEncoded = rawurlencode($smiley);
                $table .= <<<EOT
<tr>
    <td>
        ${smiley}
    </td>
    <td>
        <img alt="[Invalid Image]" src="${imageCode}"/>
    </td>
    <td>
        <input type="checkbox" class="switch yn"/>
    </td>
    <td class="x">
        <a href="?act=posting&do=emoticons&d=${smileyUrlEncoded}">
            X
        </a>
    </td>
</tr>
EOT;
            }
        }
        $page .= <<<EOT
<form method="post" action="?act=posting&do=emoticons">
    <table class="badwords">
        ${table}
    </table>
</form>
EOT;

        $PAGE->addContentBox('Custom Emoticons', $page);

        $emoticonpath = $PAGE->getCFGSetting('emotepack');
        $emoticonsetting = $emoticonpath;
        $page = <<<'EOT'
<form method="post">
    Currently using: <select name="baseset" onchange="this.form.submit()">
EOT;
        foreach ($basesets as $packId => $packName) {
            $packCode = $emoticonsetting == $packId ?
                ' selected="selected"' : '';
            $page .= <<<EOT
<option value="${packId}"${packCode}>${packName}</option>
EOT;
        }
        $page .= '</select> &nbsp; &nbsp; ';

        if ($emoticonsetting) {
            if (isset($JAX->b['expanded']) && $JAX->b['expanded']) {
                $page .= <<<'EOT'
    <a href="?act=posting&do=emoticons">
        Collapse
    </a>
</form>
<br />
<br />
<table class="badwords">
    <tr>
        <th>
            Emoticon
        </th>
        <th>
            Image
        </th>
    </tr>
EOT;
                include JAXBOARDS_ROOT . "/emoticons/${emoticonpath}/rules.php";
                foreach ($rules as $k => $v) {
                    $page .= <<<EOT
<tr>
    <td>
        ${k}
    </td>
    <td>
        <img src="/emoticons/${emoticonpath}/${v}"/>
    </td>
</tr>
EOT;
                }
                $page .= '</table>';
            } else {
                $page .= <<<'EOT'
    <a href="?act=posting&do=emoticons&expanded=true">
        Expand
    </a>
</form>
EOT;
            }
        }

        $PAGE->addContentBox('Base Emoticon Set', $page);
    }

    public function bbcodes()
    {
        global $PAGE;
        $table = <<<'EOT'
<tr>
    <th>
        BBCode
    </th>
    <th>
        Rule
    </th>
</tr>
<tr>
    <th>
        <input type="text" name="bbcode"/>
    </th>
    <th>
        <input type="text" name="rule"/>
    </th>
</tr>
EOT;
        $page = <<<EOT
<form method="post">
    <table>
        ${table}
    </table>
</form>
EOT;
        $PAGE->addContentBox('Custom BBCodes', $page);
    }

    public function birthday()
    {
        global $PAGE,$JAX;
        $birthdays = $PAGE->getCFGSetting('birthdays');
        if ($JAX->p['submit']) {
            $PAGE->writeCFG(
                array(
                    'birthdays' => $birthdays = ($JAX->p['bicon'] ? 1 : 0),
                )
            );
        }
        $birthdaysCode = $birthdays & 1 ? " checked='checked'" : '';
        $page = <<<EOT
<form method="post">
    <label>
        Show Birthday Icon
    </label>
    <input type="checkbox" class="switch yn" name="bicon"${birthdaysCode}>
    <br />
    <input type="submit" value="Save" name="submit" />
</form>
EOT;
        $PAGE->addContentBox('Birthdays', $page);
    }

    public function postrating()
    {
        global $PAGE,$JAX,$DB;
        $page = $page2 = '';
        $niblets = array();
        $result = $DB->safeselect(
            '`id`,`img`,`title`',
            'ratingniblets',
            'ORDER BY `id` DESC'
        );
        while ($f = $DB->arow($result)) {
            $niblets[$f['id']] = array('img' => $f['img'], 'title' => $f['title']);
        }

        //delete
        if (@$JAX->g['d']) {
            $DB->safedelete(
                'ratingniblets',
                'WHERE `id`=?',
                $DB->basicvalue($JAX->g['d'])
            );
            unset($niblets[$JAX->g['d']]);
        }

        //insert
        if (@$JAX->p['submit']) {
            if (!$JAX->p['img'] || !$JAX->p['title']) {
                $page .= $PAGE->error('All fields required.');
            } else {
                $DB->safeinsert(
                    'ratingniblets',
                    array(
                        'img' => $JAX->p['img'],
                        'title' => $JAX->p['title'],
                    )
                );
                $niblets[$DB->insert_id(1)] = array(
                    'img' => $JAX->p['img'],
                    'title' => $JAX->p['title'],
                );
            }
        }

        if (@$JAX->p['rsubmit']) {
            $cfg = array(
                'ratings' => ($JAX->p['renabled'] ? 1 : 0) +
                ($JAX->p['ranon'] ? 2 : 0),
            );
            $PAGE->writeCFG($cfg);
            $page2 .= $PAGE->success('Settings saved!');
        }
        $ratingsettings = $PAGE->getCFGSetting('ratings');
        $ratingsEnabledCode = $ratingsettings & 1 ? ' checked="checked"' : '';
        $ratingsAnonymousCode = $ratingsettings & 2 ? ' checked="checked"' : '';

        $page2 .= <<<EOT
<form method="post">
    <label>
        Ratings Enabled:
    </label>
    <input type="checkbox" class="switch yn" name="renabled"${ratingsEnabledCode}/>
    <br />
    <label>
        Anonymous Ratings:
    </label>
    <input type="checkbox" class="switch yn" name="ranon"${ratingsAnonymousCode}/>
    <br />
    <input type="submit" value="Save" name="rsubmit" />
</form>
EOT;
        $table = <<<'EOT'
<tr>
    <th>
        Image
    </th>
    <th>
        Title
    </th>
</tr>
<tr>
    <td>
        <input type="text" name="img"/>
    </td>
    <td>
        <input type="text" name="title"/>
    </td>
    <td>
        <input type="submit" value="Add" name="submit"/>
    </td>
</tr>
EOT;
        if (empty($niblets)) {
            $table .= '<tr><td colspan="3">No rating niblets set!</td></tr>';
        } else {
            krsort($niblets);
            foreach ($niblets as $ratingId => $rating) {
                $ratingName = $JAX->blockhtml($rating['title']);
                $ratingImage = $JAX->blockhtml($rating['img']);
                $table .= <<<EOT
<tr>
    <td>
        <img src="${ratingImage}"/>
    </td>
    <td>
        ${ratingName}
    </td>
    <td class="x">
        <a href="?act=posting&do=postrating&d=${ratingId}">
            X
        </a>
    </td>
</tr>
EOT;
            }
        }
        $page .= <<<EOT
<form method="post" action="?act=posting&do=postrating">
    <table class="badwords">
        ${table}
    </table>
</form>
EOT;
        $PAGE->addContentBox('Post Rating System', $page2);
        $PAGE->addContentBox('Post Rating Niblets', $page);
    }
}
