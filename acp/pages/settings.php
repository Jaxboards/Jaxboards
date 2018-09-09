<?php

if (!defined(INACP)) {
    die();
}

new settings();
class settings
{
    public function __construct()
    {
        global $JAX, $PAGE;

        $links = array(
            'global' => 'Global Settings',
            'shoutbox' => 'Shoutbox',
            'pages' => 'Custom Pages',
            'birthday' => 'Birthdays',
        );
        $sidebarLinks = '';
        foreach ($links as $do => $title) {
            $sidebarLinks .= $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/sidebar-list-link.html',
                array(
                    'url' => '?act=settings&do=' . $do,
                    'title' => $title,
                )
            ) . PHP_EOL;
        }

        $PAGE->sidebar(
            $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/sidebar-list.html',
                array(
                    'content' => $sidebarLinks,
                )
            )
        );

        if (!isset($JAX->b['do'])) {
            $JAX->b['do'] = null;
        }
        switch ($JAX->b['do']) {
            case 'pages':
                $this->pages();
                break;
            case 'shoutbox':
                $this->shoutbox();
                break;
            case 'birthday':
                $this->birthday();
                break;
            case 'global':
            default:
                $this->boardname();
        }
    }

    public function boardname()
    {
        global $PAGE,$JAX;
        $page = '';
        $e = '';
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if ('' === trim($JAX->p['boardname'])) {
                $e = 'Board name is required';
            } elseif (!isset($JAX->p['logourl'])
                || ('' !== trim($JAX->p['logourl'])
                && !$JAX->isURL($JAX->p['logourl']))
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
        $page .= $PAGE->parseTemplate(
            JAXBOARDS_ROOT . '/acp/views/settings/boardname.html',
            array(
                'board_name' => $PAGE->getCFGSetting('boardname'),
                'logo_url' => $PAGE->getCFGSetting('logourl'),
            )
        );
        $PAGE->addContentBox('Board Name/Logo', $page);

        $page = '';
        $boardOfflineCode = !$PAGE->getCFGSetting('boardoffline') ?
            'checked="checked"' : '';
        $boardOfflineText = $JAX->blockhtml(
            $PAGE->getCFGSetting('offlinetext')
        );
        $page .= $PAGE->parseTemplate(
            JAXBOARDS_ROOT . '/acp/views/settings/boardname-board-offline.html',
            array(
                'board_offline_checked' => !$PAGE->getCFGSetting('boardoffline') ?
                    ' checked="checked"' : '',
                'board_offline_text' => $JAX->blockhtml(
                    $PAGE->getCFGSetting('offlinetext')
                ),
                'content' => $page,
            )
        );
        $PAGE->addContentBox('Board Online/Offline', $page);
    }

    /**
     * Custom pages
     *
     * @return void
     */
    public function pages()
    {
        global $DB,$PAGE,$JAX;
        $page = '';
        if (isset($JAX->b['delete']) && $JAX->b['delete']) {
            $this->pages_delete($JAX->b['delete']);
        }
        if (isset($JAX->b['page']) && $JAX->b['page']) {
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
            $table .= $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/settings/pages-row.html',
                array(
                    'act' => $f['act'],
                )
            ) . PHP_EOL;
        }
        if ($table) {
            $page .= $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/settings/pages.html',
                array(
                    'content' => $table,
                )
            );
        }
        $hiddenFields = $JAX->hiddenFormFields(
            array(
                'act' => 'settings',
                'do' => 'pages',
            )
        );
        $page .= $PAGE->parseTemplate(
            JAXBOARDS_ROOT . '/acp/views/settings/pages-new.html',
            array(
                'hidden_fields' => $hiddenFields,
            )
        );
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
        $page .= $PAGE->parseTemplate(
            JAXBOARDS_ROOT . '/acp/views/settings/pages-edit.html',
            array(
                'content' => $JAX->blockhtml($pageinfo['page']),
            )
        );
        $PAGE->addContentBox("Editing Page: ${pageurl}", $page);
    }

    /**
     * Shoutbox
     *
     * @return void
     */
    public function shoutbox()
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        $e = '';
        if (isset($JAX->p['clearall']) && $JAX->p['clearall']) {
            $result = $DB->safespecial(
                'TRUNCATE TABLE %t',
                array('shouts')
            );
            $page .= $PAGE->success('Shoutbox cleared!');
        }
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
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
        $page .= $PAGE->parseTemplate(
            JAXBOARDS_ROOT . '/acp/views/settings/shoutbox.html',
            array(
                'shoutbox_checked' => $PAGE->getCFGSetting('shoutbox') ?
                    ' checked="checked"' : '',
                'shoutbox_avatar_checked' => $PAGE->getCFGSetting('shoutboxava') ?
                ' checked="checked"' : '',
                'show_shouts' => $PAGE->getCFGSetting('shoutbox_num'),
            )
        );
        $PAGE->addContentBox('Shoutbox', $page);
    }

    public function birthday()
    {
        global $PAGE,$JAX;
        $birthdays = $PAGE->getCFGSetting('birthdays');
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (!isset($JAX->p['bicon'])) {
                $JAX->p['bicon'] = false;
            }
            $PAGE->writeCFG(
                array(
                    'birthdays' => $birthdays = ($JAX->p['bicon'] ? 1 : 0),
                )
            );
        }
        $page = $PAGE->parseTemplate(
            JAXBOARDS_ROOT . '/acp/views/settings/birthday.html',
            array(
                'checked' => $birthdays & 1 ? ' checked="checked"' : '',
            )
        );

        $PAGE->addContentBox('Birthdays', $page);
    }
}
