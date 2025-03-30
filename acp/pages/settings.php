<?php

declare(strict_types=1);

if (!defined(INACP)) {
    exit;
}

new settings();
final class settings
{
    public function __construct()
    {
        global $JAX, $PAGE;

        $links = [
            'birthday' => 'Birthdays',
            'global' => 'Global Settings',
            'pages' => 'Custom Pages',
            'shoutbox' => 'Shoutbox',
        ];
        $sidebarLinks = '';
        foreach ($links as $do => $title) {
            $sidebarLinks .= $PAGE->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => '?act=settings&do=' . $do,
                ],
            ) . PHP_EOL;
        }

        $PAGE->sidebar(
            $PAGE->parseTemplate(
                'sidebar-list.html',
                [
                    'content' => $sidebarLinks,
                ],
            ),
        );

        if (!isset($JAX->b['do'])) {
            $JAX->b['do'] = null;
        }

        match ($JAX->b['do']) {
            'pages' => $this->pages(),
            'shoutbox' => $this->shoutbox(),
            'birthday' => $this->birthday(),
            default => $this->boardname(),
        };
    }

    public function boardname(): void
    {
        global $PAGE,$JAX;
        $page = '';
        $e = '';
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (trim((string) $JAX->p['boardname']) === '') {
                $e = 'Board name is required';
            } elseif (
                !isset($JAX->p['logourl'])
                || (trim($JAX->p['logourl']) !== ''
                && !$JAX->isURL($JAX->p['logourl']))
            ) {
                $e = 'Please enter a valid logo url.';
            }

            if ($e !== '' && $e !== '0') {
                $page .= $PAGE->error($e);
            } else {
                $write = [];
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
            'settings/boardname.html',
            [
                'board_name' => $PAGE->getCFGSetting('boardname'),
                'logo_url' => $PAGE->getCFGSetting('logourl'),
            ],
        );
        $PAGE->addContentBox('Board Name/Logo', $page);

        $page = '';
        if (!$PAGE->getCFGSetting('boardoffline')) {
        }

        $JAX->blockhtml(
            $PAGE->getCFGSetting('offlinetext'),
        );
        $page .= $PAGE->parseTemplate(
            'settings/boardname-board-offline.html',
            [
                'board_offline_checked' => $PAGE->getCFGSetting('boardoffline')
                    ? '' : ' checked="checked"',
                'board_offline_text' => $JAX->blockhtml(
                    $PAGE->getCFGSetting('offlinetext'),
                ),
                'content' => $page,
            ],
        );
        $PAGE->addContentBox('Board Online/Offline', $page);
    }

    /**
     * Custom pages.
     */
    public function pages(): void
    {
        global $DB,$PAGE,$JAX;
        $page = '';
        if (isset($JAX->b['delete']) && $JAX->b['delete']) {
            $this->pages_delete($JAX->b['delete']);
        }

        if (isset($JAX->b['page']) && $JAX->b['page']) {
            $newact = preg_replace(
                '@\W@',
                '<span style="font-weight:bold;color:#F00;">$0</span>',
                (string) $JAX->b['page'],
            );
            if ($newact !== $JAX->b['page']) {
                $e = 'The page URL must contain only letters and numbers. '
                    . "Invalid characters: {$newact}";
            } elseif (mb_strlen((string) $newact) > 25) {
                $e = 'The page URL cannot exceed 25 characters.';
            } else {
                $this->pages_edit($newact);

                return;
            }

            $page .= $PAGE->error($e);
        }

        $result = $DB->safeselect(
            ['act', 'page'],
            'pages',
        );
        $table = '';
        while ($f = $DB->arow($result)) {
            $table .= $PAGE->parseTemplate(
                'settings/pages-row.html',
                [
                    'act' => $f['act'],
                ],
            ) . PHP_EOL;
        }

        if ($table !== '' && $table !== '0') {
            $page .= $PAGE->parseTemplate(
                'settings/pages.html',
                [
                    'content' => $table,
                ],
            );
        }

        $hiddenFields = $JAX->hiddenFormFields(
            [
                'act' => 'settings',
                'do' => 'pages',
            ],
        );
        $page .= $PAGE->parseTemplate(
            'settings/pages-new.html',
            [
                'hidden_fields' => $hiddenFields,
            ],
        );
        $PAGE->addContentBox('Custom Pages', $page);
    }

    public function pages_delete($page)
    {
        global $DB;

        return $DB->safedelete(
            'pages',
            'WHERE `act`=?',
            $DB->basicvalue($page),
        );
    }

    public function pages_edit($pageurl): void
    {
        global $PAGE,$DB,$JAX;
        $page = '';
        $result = $DB->safeselect(
            ['act', 'page'],
            'pages',
            'WHERE `act`=?',
            $DB->basicvalue($pageurl),
        );
        $pageinfo = $DB->arow($result);
        $DB->disposeresult($result);
        if (isset($JAX->p['pagecontents']) && $JAX->p['pagecontents']) {
            if ($pageinfo) {
                $DB->safeupdate(
                    'pages',
                    [
                        'page' => $JAX->p['pagecontents'],
                    ],
                    'WHERE `act`=?',
                    $DB->basicvalue($pageurl),
                );
            } else {
                $DB->safeinsert(
                    'pages',
                    [
                        'act' => $pageurl,
                        'page' => $JAX->p['pagecontents'],
                    ],
                );
            }

            $pageinfo['page'] = $JAX->p['pagecontents'];
            $page .= $PAGE->success(
                "Page saved. Preview <a href='/?act={$pageurl}'>here</a>",
            );
        }

        $page .= $PAGE->parseTemplate(
            'settings/pages-edit.html',
            [
                'content' => $JAX->blockhtml($pageinfo['page']),
            ],
        );
        $PAGE->addContentBox("Editing Page: {$pageurl}", $page);
    }

    /**
     * Shoutbox.
     */
    public function shoutbox(): void
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        $e = '';
        if (isset($JAX->p['clearall']) && $JAX->p['clearall']) {
            $result = $DB->safespecial(
                'TRUNCATE TABLE %t',
                ['shouts'],
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

            $write = [
                'shoutbox' => $JAX->p['sbe'] ? 1 : 0,
                'shoutboxava' => $JAX->p['sbava'] ? 1 : 0,
            ];
            if (
                is_numeric($JAX->p['sbnum'])
                && $JAX->p['sbnum'] <= 10
                && $JAX->p['sbnum'] > 0
            ) {
                $write['shoutbox_num'] = $JAX->p['sbnum'];
            } else {
                $e = 'Shouts to show must be between 1 and 10';
            }

            $PAGE->writeCFG($write);
            if ($e !== '' && $e !== '0') {
                $page .= $PAGE->error($e);
            } else {
                $page .= $PAGE->success('Data saved.');
            }
        }

        $page .= $PAGE->parseTemplate(
            'settings/shoutbox.html',
            [
                'shoutbox_avatar_checked' => $PAGE->getCFGSetting('shoutboxava')
                ? ' checked="checked"' : '',
                'shoutbox_checked' => $PAGE->getCFGSetting('shoutbox')
                    ? ' checked="checked"' : '',
                'show_shouts' => $PAGE->getCFGSetting('shoutbox_num'),
            ],
        );
        $PAGE->addContentBox('Shoutbox', $page);
    }

    public function birthday(): void
    {
        global $PAGE,$JAX;
        $birthdays = $PAGE->getCFGSetting('birthdays');
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (!isset($JAX->p['bicon'])) {
                $JAX->p['bicon'] = false;
            }

            $PAGE->writeCFG(
                [
                    'birthdays' => $birthdays = ($JAX->p['bicon'] ? 1 : 0),
                ],
            );
        }

        $page = $PAGE->parseTemplate(
            'settings/birthday.html',
            [
                'checked' => ($birthdays & 1) !== 0 ? ' checked="checked"' : '',
            ],
        );

        $PAGE->addContentBox('Birthdays', $page);
    }
}
