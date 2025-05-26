<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\Request;
use Jax\TextFormatting;

use function filter_var;
use function is_numeric;
use function is_string;
use function mb_strlen;
use function preg_replace;
use function trim;

use const FILTER_VALIDATE_URL;

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
            'global' => 'Global Settings',
            'pages' => 'Custom Pages',
            'shoutbox' => 'Shoutbox',
        ]);

        match ($this->request->both('do')) {
            'pages' => $this->pages(),
            'shoutbox' => $this->shoutbox(),
            default => $this->global(),
        };
    }

    private function global(): void
    {
        $error = null;
        $status = '';
        if ($this->request->post('submit') !== null) {
            $boardName = $this->request->asString->post('boardname');
            $logoUrl = $this->request->asString->post('logourl');
            $error = match (true) {
                !is_string($boardName) || trim($boardName) === '' => 'Board name is required',
                $logoUrl !== '' && !filter_var($logoUrl, FILTER_VALIDATE_URL) => 'Please enter a valid logo url.',
                default => null,
            };

            if ($error === null) {
                $this->config->write([
                    'boardname' => $this->request->post('boardname'),
                    'logourl' => $this->request->post('logourl'),
                    'boardoffline' => $this->request->post('boardoffline') !== null ? '0' : '1',
                    'offlinetext' => $this->request->post('offlinetext'),
                    'birthdays' => ($this->request->post('bicon') !== null ? 1 : 0),
                ]);
            }

            $status = $error !== null
                ? $this->page->error($error)
                : $this->page->success('Settings saved!');
        }

        // This is silly, but we need the whole page to be a form
        $this->page->append('content', '<form method="post">');

        $this->page->addContentBox('Board Name/Logo', $status . $this->page->parseTemplate(
            'settings/boardname.html',
            [
                'board_name' => $this->config->getSetting('boardname'),
                'logo_url' => $this->config->getSetting('logourl'),
            ],
        ));

        $this->page->addContentBox('Board Online/Offline', $this->page->parseTemplate(
            'settings/boardname-board-offline.html',
            [
                'board_offline_checked' => $this->config->getSetting('boardoffline')
                    ? '' : ' checked="checked"',
                'board_offline_text' => $this->textFormatting->blockhtml(
                    $this->config->getSetting('offlinetext') ?? '',
                ),
            ],
        ));

        $this->page->addContentBox('Birthdays', $this->page->parseTemplate(
            'settings/birthday.html',
            [
                'checked' => $this->config->getSetting('birthdays') !== 0 ? ' checked="checked"' : '',
            ],
        ));

        $this->page->append('content', '</form>');
    }

    // Custom pages.
    private function pages(): void
    {
        $page = '';
        $delete = $this->request->asString->both('delete');
        if ($delete !== null) {
            $this->pages_delete($delete);
        }

        $pageAct = $this->request->asString->both('page');
        if ($pageAct !== null) {
            $newact = preg_replace(
                '@\W@',
                '<span style="font-weight:bold;color:#F00;">$0</span>',
                $pageAct,
            );
            if ($newact !== $pageAct) {
                $error = 'The page URL must contain only letters and numbers. '
                    . "Invalid characters: {$newact}";
            } elseif (mb_strlen($newact) > 25) {
                $error = 'The page URL cannot exceed 25 characters.';
            } else {
                $this->pages_edit($pageAct);

                return;
            }

            $page .= $this->page->error($error);
        }

        $result = $this->database->select(
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
            );
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

    private function pages_delete(string $page): void
    {
        $this->database->delete(
            'pages',
            'WHERE `act`=?',
            $this->database->basicvalue($page),
        );
    }

    private function pages_edit(string $pageurl): void
    {
        $page = '';
        $result = $this->database->select(
            ['act', 'page'],
            'pages',
            'WHERE `act`=?',
            $this->database->basicvalue($pageurl),
        );
        $pageinfo = $this->database->arow($result);
        $this->database->disposeresult($result);
        $pagecontents = $this->request->asString->post('pagecontents');
        if ($pagecontents !== null) {
            if ($pageinfo) {
                $pageinfo['page'] = $pagecontents;

                $this->database->update(
                    'pages',
                    [
                        'page' => $pagecontents,
                    ],
                    'WHERE `act`=?',
                    $this->database->basicvalue($pageurl),
                );
            } else {
                $pageinfo = [
                    'act' => $pageurl,
                    'page' => $pagecontents,
                ];
                $this->database->insert(
                    'pages',
                    $pageinfo,
                );
            }

            $pageinfo['page'] = $pagecontents;
            $page .= $this->page->success(
                "Page saved. Preview <a href='/?act={$pageurl}'>here</a>",
            );
        }

        $page .= $this->page->parseTemplate(
            'settings/pages-edit.html',
            [
                'content' => $this->textFormatting->blockhtml($pageinfo ? $pageinfo['page'] : ''),
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
            $result = $this->database->special(
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
}
