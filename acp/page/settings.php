<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\TextFormatting;

use function is_numeric;
use function mb_strlen;
use function preg_replace;
use function trim;

use const PHP_EOL;

final readonly class Settings
{
    public function __construct(
        private Config $config,
        private Database $database,
        private Jax $jax,
        private Page $page,
        private TextFormatting $textFormatting,
    ) {}

    public function route(): void
    {

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

        if (!isset($this->jax->b['do'])) {
            $this->jax->b['do'] = null;
        }

        match ($this->jax->b['do']) {
            'pages' => $this->pages(),
            'shoutbox' => $this->shoutbox(),
            'birthday' => $this->birthday(),
            default => $this->boardname(),
        };
    }

    public function boardname(): void
    {
        $page = '';
        $e = '';
        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            if (trim((string) $this->jax->p['boardname']) === '') {
                $e = 'Board name is required';
            } elseif (
                !isset($this->jax->p['logourl'])
                || (trim($this->jax->p['logourl']) !== ''
                && !$this->jax->isURL($this->jax->p['logourl']))
            ) {
                $e = 'Please enter a valid logo url.';
            }

            if ($e !== '' && $e !== '0') {
                $page .= $this->page->error($e);
            } else {
                $this->config->write([
                    'boardname' => $this->jax->p['boardname'],
                    'logourl' => $this->jax->p['logourl'],
                    'boardoffline' => isset($this->jax->p['boardoffline']) && $this->jax->p['boardoffline'] ? '0' : '1',
                    'offlinetext' => $this->jax->p['offlinetext'],
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

        $this->textFormatting->blockhtml(
            $this->config->getSetting('offlinetext'),
        );
        $page .= $this->page->parseTemplate(
            'settings/boardname-board-offline.html',
            [
                'board_offline_checked' => $this->config->getSetting('boardoffline')
                    ? '' : ' checked="checked"',
                'board_offline_text' => $this->textFormatting->blockhtml(
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
        $page = '';
        if (isset($this->jax->b['delete']) && $this->jax->b['delete']) {
            $this->pages_delete($this->jax->b['delete']);
        }

        if (isset($this->jax->b['page']) && $this->jax->b['page']) {
            $newact = preg_replace(
                '@\W@',
                '<span style="font-weight:bold;color:#F00;">$0</span>',
                (string) $this->jax->b['page'],
            );
            if ($newact !== $this->jax->b['page']) {
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

        $result = $this->database->safeselect(
            ['act', 'page'],
            'pages',
        );
        $table = '';
        while ($f = $this->database->arow($result)) {
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

        $hiddenFields = $this->jax->hiddenFormFields(
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

    public function pages_delete($page): mixed
    {
        return $this->database->safedelete(
            'pages',
            'WHERE `act`=?',
            $this->database->basicvalue($page),
        );
    }

    public function pages_edit($pageurl): void
    {
        $page = '';
        $result = $this->database->safeselect(
            ['act', 'page'],
            'pages',
            'WHERE `act`=?',
            $this->database->basicvalue($pageurl),
        );
        $pageinfo = $this->database->arow($result);
        $this->database->disposeresult($result);
        if (
            isset($this->jax->p['pagecontents'])
            && $this->jax->p['pagecontents']
        ) {
            if ($pageinfo) {
                $this->database->safeupdate(
                    'pages',
                    [
                        'page' => $this->jax->p['pagecontents'],
                    ],
                    'WHERE `act`=?',
                    $this->database->basicvalue($pageurl),
                );
            } else {
                $this->database->safeinsert(
                    'pages',
                    [
                        'act' => $pageurl,
                        'page' => $this->jax->p['pagecontents'],
                    ],
                );
            }

            $pageinfo['page'] = $this->jax->p['pagecontents'];
            $page .= $this->page->success(
                "Page saved. Preview <a href='/?act={$pageurl}'>here</a>",
            );
        }

        $page .= $this->page->parseTemplate(
            'settings/pages-edit.html',
            [
                'content' => $this->textFormatting->blockhtml($pageinfo['page']),
            ],
        );
        $this->page->addContentBox("Editing Page: {$pageurl}", $page);
    }

    /**
     * Shoutbox.
     */
    public function shoutbox(): void
    {
        $page = '';
        $e = '';
        if (isset($this->jax->p['clearall']) && $this->jax->p['clearall']) {
            $result = $this->database->safespecial(
                'TRUNCATE TABLE %t',
                ['shouts'],
            );
            $page .= $this->page->success('Shoutbox cleared!');
        }

        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            if (!isset($this->jax->p['sbe'])) {
                $this->jax->p['sbe'] = false;
            }

            if (!isset($this->jax->p['sbava'])) {
                $this->jax->p['sbava'] = false;
            }

            $write = [
                'shoutbox' => $this->jax->p['sbe'] ? 1 : 0,
                'shoutboxava' => $this->jax->p['sbava'] ? 1 : 0,
            ];
            if (
                is_numeric($this->jax->p['sbnum'])
                && $this->jax->p['sbnum'] <= 10
                && $this->jax->p['sbnum'] > 0
            ) {
                $write['shoutbox_num'] = $this->jax->p['sbnum'];
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
        $birthdays = $this->config->getSetting('birthdays');
        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            if (!isset($this->jax->p['bicon'])) {
                $this->jax->p['bicon'] = false;
            }

            $this->config->write(
                [
                    'birthdays' => $birthdays = ($this->jax->p['bicon'] ? 1 : 0),
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
