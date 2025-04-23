<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Jax;

use function is_numeric;
use function mb_strlen;
use function preg_replace;
use function trim;

use const PHP_EOL;

final readonly class Settings
{
    public function __construct(private Config $config, private Page $page) {}

    public function route(): void
    {
        global $JAX;

        $links = [
            'birthday' => 'Birthdays',
            'global' => 'Global Settings',
            'pages' => 'Custom Pages',
            'shoutbox' => 'Shoutbox',
        ];
        $sidebarLinks = '';
        foreach ($links as $do => $title) {
            $sidebarLinks .= $this->page->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => '?act=settings&do=' . $do,
                ],
            ) . PHP_EOL;
        }

        $this->page->sidebar(
            $this->page->parseTemplate(
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
        global $JAX;
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
                $page .= $this->page->error($e);
            } else {
                $this->config->write([
                    'boardname' => $JAX->p['boardname'],
                    'logourl' => $JAX->p['logourl'],
                    'boardoffline' => isset($JAX->p['boardoffline']) && $JAX->p['boardoffline'] ? '0' : '1',
                    'offlinetext' => $JAX->p['offlinetext'],
                ]);
                $page .= $this->page->success('Settings saved!');
            }
        }

        $page .= $this->page->parseTemplate(
            'settings/boardname.html',
            [
                'board_name' => $this->config->getSetting('boardname'),
                'logo_url' => $this->config->getSetting('logourl'),
            ],
        );
        $this->page->addContentBox('Board Name/Logo', $page);

        $page = '';
        if (!$this->config->getSetting('boardoffline')) {
        }

        $JAX->blockhtml(
            $this->config->getSetting('offlinetext'),
        );
        $page .= $this->page->parseTemplate(
            'settings/boardname-board-offline.html',
            [
                'board_offline_checked' => $this->config->getSetting('boardoffline')
                    ? '' : ' checked="checked"',
                'board_offline_text' => $JAX->blockhtml(
                    $this->config->getSetting('offlinetext'),
                ),
                'content' => $page,
            ],
        );
        $this->page->addContentBox('Board Online/Offline', $page);
    }

    /**
     * Custom pages.
     */
    public function pages(): void
    {
        global $DB,$JAX;
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

            $page .= $this->page->error($e);
        }

        $result = $DB->safeselect(
            ['act', 'page'],
            'pages',
        );
        $table = '';
        while ($f = $DB->arow($result)) {
            $table .= $this->page->parseTemplate(
                'settings/pages-row.html',
                [
                    'act' => $f['act'],
                ],
            ) . PHP_EOL;
        }

        if ($table !== '' && $table !== '0') {
            $page .= $this->page->parseTemplate(
                'settings/pages.html',
                [
                    'content' => $table,
                ],
            );
        }

        $hiddenFields = Jax::hiddenFormFields(
            [
                'act' => 'settings',
                'do' => 'pages',
            ],
        );
        $page .= $this->page->parseTemplate(
            'settings/pages-new.html',
            [
                'hidden_fields' => $hiddenFields,
            ],
        );
        $this->page->addContentBox('Custom Pages', $page);
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
        global $DB,$JAX;
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
            $page .= $this->page->success(
                "Page saved. Preview <a href='/?act={$pageurl}'>here</a>",
            );
        }

        $page .= $this->page->parseTemplate(
            'settings/pages-edit.html',
            [
                'content' => $JAX->blockhtml($pageinfo['page']),
            ],
        );
        $this->page->addContentBox("Editing Page: {$pageurl}", $page);
    }

    /**
     * Shoutbox.
     */
    public function shoutbox(): void
    {
        global $JAX,$DB;
        $page = '';
        $e = '';
        if (isset($JAX->p['clearall']) && $JAX->p['clearall']) {
            $result = $DB->safespecial(
                'TRUNCATE TABLE %t',
                ['shouts'],
            );
            $page .= $this->page->success('Shoutbox cleared!');
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

            $this->config->write($write);
            if ($e !== '' && $e !== '0') {
                $page .= $this->page->error($e);
            } else {
                $page .= $this->page->success('Data saved.');
            }
        }

        $page .= $this->page->parseTemplate(
            'settings/shoutbox.html',
            [
                'shoutbox_avatar_checked' => $this->config->getSetting('shoutboxava')
                ? ' checked="checked"' : '',
                'shoutbox_checked' => $this->config->getSetting('shoutbox')
                    ? ' checked="checked"' : '',
                'show_shouts' => $this->config->getSetting('shoutbox_num'),
            ],
        );
        $this->page->addContentBox('Shoutbox', $page);
    }

    public function birthday(): void
    {
        global $JAX;
        $birthdays = $this->config->getSetting('birthdays');
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (!isset($JAX->p['bicon'])) {
                $JAX->p['bicon'] = false;
            }

            $this->config->write(
                [
                    'birthdays' => $birthdays = ($JAX->p['bicon'] ? 1 : 0),
                ],
            );
        }

        $page = $this->page->parseTemplate(
            'settings/birthday.html',
            [
                'checked' => ($birthdays & 1) !== 0 ? ' checked="checked"' : '',
            ],
        );

        $this->page->addContentBox('Birthdays', $page);
    }
}
