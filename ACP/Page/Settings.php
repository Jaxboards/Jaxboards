<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\Request;
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
        private Request $request,
        private TextFormatting $textFormatting,
    ) {}

    public function render(): void
    {
        $this->page->sidebar([
            'birthday' => 'Birthdays',
            'global' => 'Global Settings',
            'pages' => 'Custom Pages',
            'shoutbox' => 'Shoutbox',
        ]);

        match ($this->request->both('do')) {
            'pages' => $this->pages(),
            'shoutbox' => $this->shoutbox(),
            'birthday' => $this->birthday(),
            default => $this->boardname(),
        };
    }

    private function boardname(): void
    {
        $page = '';
        $error = null;
        if ($this->request->post('submit') !== null) {
            if (trim((string) $this->request->post('boardname')) === '') {
                $error = 'Board name is required';
            } elseif (
                trim((string) $this->request->post('logourl')) !== ''
                && !$this->jax->isURL($this->request->post('logourl'))
            ) {
                $error = 'Please enter a valid logo url.';
            }

            if ($error !== null) {
                $page .= $this->page->error($error);
            } else {
                $this->config->write([
                    'boardname' => $this->request->post('boardname'),
                    'logourl' => $this->request->post('logourl'),
                    'boardoffline' => $this->request->post('boardoffline') !== null ? '0' : '1',
                    'offlinetext' => $this->request->post('offlinetext'),
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

    // Custom pages.
    private function pages(): void
    {
        $page = '';
        if ($this->request->both('delete') !== null) {
            $this->pages_delete($this->request->both('delete'));
        }

        if ($this->request->both('page') !== null) {
            $newact = preg_replace(
                '@\W@',
                '<span style="font-weight:bold;color:#F00;">$0</span>',
                (string) $this->request->both('page'),
            );
            if ($newact !== $this->request->both('page')) {
                $error = 'The page URL must contain only letters and numbers. '
                    . "Invalid characters: {$newact}";
            } elseif (mb_strlen((string) $newact) > 25) {
                $error = 'The page URL cannot exceed 25 characters.';
            } else {
                $this->pages_edit($newact);

                return;
            }

            $page .= $this->page->error($error);
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

    private function pages_delete($page): mixed
    {
        return $this->database->safedelete(
            'pages',
            'WHERE `act`=?',
            $this->database->basicvalue($page),
        );
    }

    private function pages_edit(array|string $pageurl): void
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
            $this->request->post('pagecontents') !== null
        ) {
            if ($pageinfo) {
                $this->database->safeupdate(
                    'pages',
                    [
                        'page' => $this->request->post('pagecontents'),
                    ],
                    'WHERE `act`=?',
                    $this->database->basicvalue($pageurl),
                );
            } else {
                $this->database->safeinsert(
                    'pages',
                    [
                        'act' => $pageurl,
                        'page' => $this->request->post('pagecontents'),
                    ],
                );
            }

            $pageinfo['page'] = $this->request->post('pagecontents');
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

    // Shoutbox.
    private function shoutbox(): void
    {
        $page = '';
        $error = null;
        if ($this->request->post('clearall') !== null) {
            $result = $this->database->safespecial(
                'TRUNCATE TABLE %t',
                ['shouts'],
            );
            $page .= $this->page->success('Shoutbox cleared!');
        }

        if ($this->request->post('submit') !== null) {
            $write = [
                'shoutbox' => $this->request->post('sbe') ? 1 : 0,
                'shoutboxava' => $this->request->post('sbava') ? 1 : 0,
            ];
            if (
                is_numeric($this->request->post('sbnum'))
                && $this->request->post('sbnum') <= 10
                && $this->request->post('sbnum') > 0
            ) {
                $write['shoutbox_num'] = $this->request->post('sbnum');
            } else {
                $error = 'Shouts to show must be between 1 and 10';
            }

            $this->config->write($write);
            if ($error !== null) {
                $page .= $this->page->error($error);
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

    private function birthday(): void
    {
        $birthdays = $this->config->getSetting('birthdays');
        if ($this->request->post('submit') !== null) {
            $this->config->write(
                [
                    'birthdays' => $birthdays = ($this->request->post('bicon') !== null ? 1 : 0),
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
