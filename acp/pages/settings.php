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
        switch (@$JAX->b['do']) {
            case 'pages':
                $this->pages();
                break;
            case 'shoutbox':
                $this->shoutbox();
                break;
            case 'global':
                $this->boardname();
                break;
            case 'birthday':
                $this->birthday();
                break;
            default:
                $this->showmain();
                break;
        }
    }

    public function leftBar()
    {
        global $PAGE;
        $sidebar = '';
        $nav = array(
            '?act=settings&do=global' => 'Global Settings',
            '?act=settings&do=shoutbox' => 'Shoutbox',
            '?act=settings&do=pages' => 'Custom Pages',
            '?act=settings&do=birthday' => 'Birthdays',
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

    public function boardname()
    {
        global $PAGE,$JAX;
        $page = '';
        $e = '';
        if (@$JAX->p['submit']) {
            if ('' === trim($JAX->p['boardname'])) {
                $e = 'Board name is required';
            } elseif ('' !== trim(@$JAX->p['logourl'])
                && !$JAX->isURL(@$JAX->p['logourl'])
            ) {
                $e = 'Please enter a valid logo url.';
            }
            if ($e) {
                $page .= $PAGE->error($e);
            } else {
                $write = array();
                $write['boardname'] = $JAX->p['boardname'];
                $write['logourl'] = $JAX->p['logourl'];
                $write['boardoffline'] = isset($JAX->p['boardoffline'])
                    && $JAX->p['boardoffline'] ? '0' : '1';
                $write['offlinetext'] = $JAX->p['offlinetext'];
                $PAGE->writeCFG($write);
                $page .= $PAGE->success('Settings saved!');
            }
        }
        $boardName = $PAGE->getCFGSetting('boardname');
        $logoUrl = $PAGE->getCFGSetting('logourl');
        $page .= <<<EOT
<form method="post">
    <label for="boardname">
        Board Name:
    </label>
    <input type="text" name="boardname" value="${boardName}" />
    <br />
    <label for="logourl">
        Logo URL:
    </label>
    <input type="text" name="logourl" value="${logoUrl}" />
    <br />
    <input type="submit" value="Save" name="submit" />
EOT;
        $PAGE->addContentBox('Board Name/Logo', $page);
        $page = '';

        $boardOfflineCode = !$PAGE->getCFGSetting('boardoffline') ?
            ' checked="checked"' : '';
        $boardOfflineText = $JAX->blockhtml(
            $PAGE->getCFGSetting('offlinetext')
        );
        $page .= <<<EOT
<label for="boardoffline">
    Board Online
</label>
<input type="checkbox" name="boardoffline" class="switch yn"${boardOfflineCode}/>
<br />
EOT;

        $page .= <<<EOT
<label for="offlinetext" style="vertical-align:top;">
    Offline Text:
</label>
<textarea name="offlinetext">${boardOfflineText}</textarea>
<br />
<input type="submit" name="submit" value="Save" />
EOT;

        $page = "${page}</form>";
        $PAGE->addContentBox('Board Online/Offline', $page);
    }

    /*
     * Pages
     */

    public function pages()
    {
        global $DB,$PAGE,$JAX;
        $page = '';
        if (@$JAX->b['delete']) {
            $this->pages_delete($JAX->b['delete']);
        }
        if (@$JAX->b['page']) {
            $newact = preg_replace(
                '@\\W@',
                '<span style="font-weight:bold;color:#F00;">$0</span>',
                $JAX->b['page']
            );
            if ($newact != $JAX->b['page']) {
                $e = 'The page URL must contain only letters and numbers. ' .
                    "Invalid characters: ${newact}";
            } elseif (mb_strlen($newact) > 25) {
                $e = 'The page URL cannot exceed 25 characters.';
            } else {
                return $this->pages_edit($newact);
            }
            if ($e) {
                $page .= $PAGE->error($e);
            }
        }
        $result = $DB->safeselect(
            '`act`,`page`',
            'pages'
        );
        $table = '';
        while ($f = $DB->arow($result)) {
            $fAct = $f['act'];
            $table .= <<<EOT
<tr>
    <td>
        ${fAct}
    </td>
    <td>
        <a href="../?act=${fAct}">
            View
        </a>
    </td>
    <td>
        <a href="?act=settings&do=pages&page=${fAct}">
            Edit
        </a>
    </td>
    <td>
        <a onclick="return confirm(\\'You sure?\\')"
            href="?act=settings&do=pages&delete=${fAct}">
            Delete
        </a>
    </td>
</tr>'
EOT;
        }
        if ($table) {
            $page .= <<<EOT
<table class="pages">
    <tr>
        <th>
            Act
        </th>
        <th>
            &nbsp;
        </th>
        <th>
            &nbsp;
        </th>
        <th>
            &nbsp;
        </th>
    </tr>
    ${table}
</table>
EOT;
        }
        $hiddenFields = $JAX->hiddenFormFields(
            array(
                'act' => 'settings',
                'do' => 'pages',
            )
        );
        $page .= <<<EOT
<form method="get">
    ${hiddenFields}
    <br />
    Add a new page at ?act=<input type="text" name="page" />
    <input type="submit" value="Go" />
</form>
EOT;
        $PAGE->addContentBox('Custom Pages', $page);
    }

    public function pages_delete($page)
    {
        global $DB;

        return $DB->safedelete(
            'pages',
            'WHERE `act`=?',
            $DB->basicvalue($page)
        );
    }

    public function pages_edit($pageurl)
    {
        global $PAGE,$DB,$JAX;
        $page = '';
        $result = $DB->safeselect(
            '`act`,`page`',
            'pages',
            'WHERE `act`=?',
            $DB->basicvalue($pageurl)
        );
        $pageinfo = $DB->arow($result);
        $DB->disposeresult($result);
        if (isset($JAX->p['pagecontents']) && $JAX->p['pagecontents']) {
            if ($pageinfo) {
                $DB->safeupdate(
                    'pages',
                    array(
                        'page' => $JAX->p['pagecontents'],
                    ),
                    'WHERE `act`=?',
                    $DB->basicvalue($pageurl)
                );
            } else {
                $DB->safeinsert(
                    'pages',
                    array(
                        'act' => $pageurl,
                        'page' => $JAX->p['pagecontents'],
                    )
                );
            }
            $pageinfo['page'] = $JAX->p['pagecontents'];
            $page .= $PAGE->success(
                "Page saved. Preview <a href='/?act=${pageurl}'>here</a>"
            );
        }
        $pageCode = $JAX->blockhtml($pageinfo['page']);
        $page .= <<<EOT
<form method="post">
    <textarea name="pagecontents" class="editor">${pageCode}</textarea>
    <br />
    <input type="submit" value="Save" />
</form>
EOT;
        $PAGE->addContentBox("Editing Page: ${pageurl}", $page);
    }

    /*
     * Shoutbox
     */

    public function shoutbox()
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        $e = '';
        if (@$JAX->p['clearall']) {
            $result = $DB->safespecial(
                'TRUNCATE TABLE %t',
                array('shouts')
            );
            $page .= $PAGE->success('Shoutbox cleared!');
        }
        if (@$JAX->p['submit']) {
            if (!isset($JAX->p['sbe'])) {
                $JAX->p['sbe'] = false;
            }
            if (!isset($JAX->p['sbava'])) {
                $JAX->p['sbava'] = false;
            }
            $write = array(
                'shoutbox' => $JAX->p['sbe'] ? 1 : 0,
                'shoutboxava' => $JAX->p['sbava'] ? 1 : 0,
            );
            if (is_numeric($JAX->p['sbnum'])
                && $JAX->p['sbnum'] <= 10
                && $JAX->p['sbnum'] > 0
            ) {
                $write['shoutbox_num'] = $JAX->p['sbnum'];
            } else {
                $e = 'Shouts to show must be between 1 and 10';
            }
            $PAGE->writeCFG($write);
            if ($e) {
                $page .= $PAGE->error($e);
            } else {
                $page .= $PAGE->success('Data saved.');
            }
        }
        $shoutboxCode = $PAGE->getCFGSetting('shoutbox') ?
            ' checked="checked"' : '';
        $shoutboxAvatarCode = $PAGE->getCFGSetting('shoutboxava') ?
            ' checked="checked"' : '';
        $shoutboxShouts = $PAGE->getCFGSetting('shoutbox_num');
        $page .= <<<EOT
<form method="post">
    <label for="sbe">
        Shoutbox enabled:
    </label>
    <input id="sbe" type="checkbox" name="sbe" class="switch yn"${shoutboxCode} />
    <br />
    <label for="sbava">
        Shoutbox avatars:
    </label>
    <input type="checkbox" name="sbava" class="switch yn" ${shoutboxAvatarCode} />
    <br />
    <label for="sbnum">
        Shouts to show:
        <br />
        (Max 10)
    </label>
    <input type="text" name="sbnum" class="slider" value="${shoutboxShouts}" />
    <br />
    <br />
    <label for="clear">
        Wipe shoutbox:
    </label>
    <input type="submit" name="clearall" value="Clear all shouts!"
        onclick="return confirm('Are you sure you want to wipe your shoutbox?');">
    <br />
    <br />
    <input type="submit" name="submit" value="Save" />
</form>
EOT;
        $PAGE->addContentBox('Shoutbox', $page);
    }

    public function birthday()
    {
        global $PAGE,$JAX;
        $birthdays = $PAGE->getCFGSetting('birthdays');
        if (@$JAX->p['submit']) {
            if (!isset($JAX->p['bicon'])) {
                $JAX->p['bicon'] = false;
            }
            $PAGE->writeCFG(
                array(
                    'birthdays' => $birthdays = ($JAX->p['bicon'] ? 1 : 0),
                )
            );
        }
        $page = '<form method="post">';
        $birthdaysCode = $birthdays & 1 ? ' checked="checked"' : '';
        $page .= <<<EOT
<label>
    Show Birthday Icon
</label>
<input type="checkbox" class="switch yn" name="bicon"${birthdaysCode}>
<br />
EOT;
        $page .= '<input type="submit" value="Save" name="submit" />';
        $page .= '</form>';
        $PAGE->addContentBox('Birthdays', $page);
    }
}
