<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\Models\Badge;
use Jax\Models\BadgeAssociation;
use Jax\Models\Member;
use Jax\Models\Page as ModelsPage;
use Jax\Request;
use Jax\TextFormatting;

use function _\keyBy;
use function filter_var;
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
            'badges' => 'Badges',
        ]);

        match ($this->request->both('do')) {
            'pages' => $this->pages(),
            'shoutbox' => $this->shoutbox(),
            'badges' => $this->badges(),
            default => $this->global(),
        };
    }

    private function submitGlobal(): string
    {
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

        return $error !== null
            ? $this->page->error($error)
            : $this->page->success('Settings saved!');
    }

    private function global(): void
    {
        $status = '';
        if ($this->request->post('submit') !== null) {
            $status = $this->submitGlobal();
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
                $this->pagesEdit($pageAct);

                return;
            }

            $page .= $this->page->error($error);
        }

        $pages = ModelsPage::selectMany($this->database);
        $table = '';
        foreach ($pages as $pageRecord) {
            $table .= $this->page->parseTemplate(
                'settings/pages-row.html',
                [
                    'act' => $pageRecord->act,
                ],
            );
        }

        if ($table !== '') {
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
            $page,
        );
    }

    private function pagesEdit(string $pageurl): void
    {
        $page = '';
        $pageRecord = ModelsPage::selectOne($this->database, 'WHERE `act`=?', $pageurl);
        $pageRecord ??= new ModelsPage();

        $pageContents = $this->request->asString->post('pagecontents');
        if ($pageContents !== null) {
            $pageRecord->page = $pageContents;
            $pageRecord->act = $pageurl;
            $pageRecord->upsert($this->database);

            $page .= $this->page->success(
                "Page saved. Preview <a href='/?act={$pageurl}'>here</a>",
            );
        }

        $page .= $this->page->parseTemplate(
            'settings/pages-edit.html',
            [
                'content' => $this->textFormatting->blockhtml($pageRecord->page),
            ],
        );
        $this->page->addContentBox("Editing Page: {$pageurl}", $page);
    }

    private function saveShoutboxSettings(): string
    {
        $shoutboxNum = (int) $this->request->asString->post('sbnum');

        if ($shoutboxNum === 0) {
            return $this->page->error('Shouts to show must be between 1 and 10');
        }

        $this->config->write([
            'shoutbox' => $this->request->post('sbe') ? 1 : 0,
            'shoutboxava' => $this->request->post('sbava') ? 1 : 0,
            'shoutbox_num' => $shoutboxNum,
        ]);

        return $this->page->success('Data saved.');
    }

    private function shoutbox(): void
    {
        $page = '';
        if ($this->request->post('clearall') !== null) {
            $this->database->special(
                'TRUNCATE TABLE %t',
                ['shouts'],
            );
            $page .= $this->page->success('Shoutbox cleared!');
        }

        if ($this->request->post('submit') !== null) {
            $page .= $this->saveShoutboxSettings();
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

    private function saveBadgeSettings(string $submitButton): string
    {
        if ($submitButton === 'Save') {
            $this->config->write([
                'badgesEnabled' => $this->request->post('badgesEnabled') ? 1 : 0,
            ]);
        }

        if ($submitButton === 'Add Badge') {
            $imagePath = $this->request->asString->post('imagePath') ?? '';
            $badgeTitle = $this->request->asString->post('badgeTitle') ?? '';
            $description = $this->request->asString->post('description') ?? '';

            if ($imagePath === '' || $badgeTitle === '') {
                return $this->page->error('Image path and badge title are required');
            }

            if (!filter_var($imagePath, FILTER_VALIDATE_URL)) {
                return $this->page->error('Image path must be a valid URL');
            }


            $badge = new Badge();
            $badge->imagePath = $imagePath;
            $badge->badgeTitle = $badgeTitle;
            $badge->description = $description;
            $badge->insert($this->database);
        }

        if ($submitButton === 'Grant Badge') {
            $mid = (int) $this->request->asString->post('mid');
            $badgeId = (int) $this->request->asString->post('badgeId');
            $reason = $this->request->asString->post('reason') ?? '';
            $count = (int) $this->request->asString->post('count');

            if ($count <= 0) {
                return $this->page->error('Badge count must be >0');
            }
            if ($mid <= 0) {
                return $this->page->error('Must select user to grant to');
            }
            if ($badgeId <= 0) {
                return $this->page->error('Badge not selected');
            }

            $badgeAssociation = new BadgeAssociation();
            $badgeAssociation->user = $mid;
            $badgeAssociation->badge = $badgeId;
            $badgeAssociation->reason = $reason;
            $badgeAssociation->badgeCount = $count;
            $badgeAssociation->awardDate = $this->database->datetime();
            $badgeAssociation->insert($this->database);
        }

        return $this->page->success('Data saved.');
    }

    private function badges(): void
    {
        $saveDataResult = '';
        $submitButton = $this->request->asString->post('submit');
        if ($submitButton) {
            $saveDataResult .= $this->saveBadgeSettings($submitButton);
        }


        $delete = (int) $this->request->asString->both('d');
        if ($this->request->both('d')) {
            $badge = Badge::selectOne($this->database, Database::WHERE_ID_EQUALS, $delete);
            if ($badge !== null) {
                $badge->delete($this->database);
            }
        }

        $badges = keyBy(
            Badge::selectMany($this->database),
            static fn(Badge $badge): int => $badge->id,
        );
        $grantedBadges = BadgeAssociation::selectMany(
            $this->database,
            'ORDER BY id DESC',
        );
        $grantedMembers = Member::joinedOn(
            $this->database,
            $grantedBadges,
            static fn(BadgeAssociation $badgeAssociation): int => $badgeAssociation->user,
        );

        $badgesList = '';
        foreach ($badges as $badge) {
            $badgesList .= $this->page->parseTemplate(
                'settings/badges-row.html',
                [
                    'badgeId' => $badge->id,
                    'imagePath' => $badge->imagePath,
                    'badgeTitle' => $badge->badgeTitle,
                    'description' => $badge->description,
                ],
            );
        }

        $badgeOptions = '';
        foreach ($badges as $badge) {
            $badgeOptions .= "<option data-image='{$badge->imagePath}' value='{$badge->id}'>{$badge->badgeTitle}</option>";
        }

        $grantedBadgesRows = '';
        foreach ($grantedBadges as $grantedBadge) {
            $badge = $badges[$grantedBadge->badge];
            $member = $grantedMembers[$grantedBadge->user];

            $grantedBadgesRows .= '<tr>'
                . "<td><img src='{$badge->imagePath}'></td>"
                . "<td>{$badge->badgeTitle}</td>"
                . "<td>{$grantedBadge->reason}</td>"
                . "<td>{$grantedBadge->badgeCount}</td>"
                . "<td>{$member->displayName}</td>"
                . '</tr>';
        }

        if ($saveDataResult !== '') {
            $this->page->addContentBox('Save Data', $saveDataResult);
        }

        $this->page->addContentBox('Badges', $this->page->parseTemplate(
            'settings/badges-enable.html',
            [
                'badges_enabled' => $this->config->getSetting('badgesEnabled') ? ' checked' : '',
            ],
        ));

        $this->page->addContentBox('Manage Badges', $this->page->parseTemplate(
            'settings/badges-list.html',
            [
                'badges' => $badgesList,
            ],
        ));

        $this->page->addContentBox('Grant Badges', $this->page->parseTemplate(
            'settings/badges-grant.html',
            [
                'options' => $badgeOptions,
                'grantedBadges' => $grantedBadgesRows,
            ],
        ));
    }
}
