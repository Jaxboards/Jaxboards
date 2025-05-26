<?php

declare(strict_types=1);

namespace Jax\Modules;

use Jax\Config;
use Jax\Database;
use Jax\Date;
use Jax\Hooks;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function ceil;
use function mb_strlen;
use function mb_substr;
use function trim;

final class Shoutbox
{
    public const TAG = true;

    private int $shoutlimit;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Date $date,
        private readonly Hooks $hooks,
        private readonly IPAddress $ipAddress,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly Template $template,
        private readonly User $user,
    ) {
        $this->template->loadMeta('shoutbox');
    }

    public function init(): void
    {
        if (
            !$this->config->getSetting('shoutbox')
            || !$this->user->getPerm('can_view_shoutbox')
        ) {
            return;
        }

        $this->shoutlimit = (int) $this->config->getSetting('shoutbox_num');
        $shoutboxDelete = (int) $this->request->both('shoutbox_delete');
        if ($shoutboxDelete !== 0) {
            $this->deleteShout($shoutboxDelete);
        } elseif (
            $this->request->both('module') === 'shoutbox'
        ) {
            $this->showAllShouts();
        }

        if (
            trim($this->request->asString->post('shoutbox_shout') ?? '') !== ''
        ) {
            $this->addShout();
        }

        if (!$this->request->isJSAccess()) {
            $this->displayShoutbox();
        } else {
            $this->updateShoutbox();
        }
    }

    /**
     * @param ?array<string,mixed> $shout
     */
    public function canDelete(int $id, ?array $shout = null): bool
    {
        $candelete = (bool) $this->user->getPerm('can_delete_shouts');
        if (!$candelete && $this->user->getPerm('can_delete_own_shouts')) {
            if (!$shout) {
                $result = $this->database->select(
                    '`uid`',
                    'shouts',
                    Database::WHERE_ID_EQUALS,
                    $id,
                );
                $shout = $this->database->arow($result);
            }

            if (
                isset($shout['uid'])
                && $shout['uid'] === $this->user->get('id')
            ) {
                return true;
            }
        }

        return $candelete;
    }

    /**
     * @param array<string,mixed> $shout
     */
    public function formatShout(array $shout): string
    {
        $user = $shout['uid'] ? $this->template->meta(
            'user-link',
            $shout['uid'],
            $shout['group_id'],
            $shout['display_name'],
        ) : 'Guest';
        $avatarUrl = $shout['avatar'] ?: $this->template->meta('default-avatar');
        $avatar = $this->config->getSetting('shoutboxava')
            ? "<img src='{$avatarUrl}' class='avatar' alt='avatar' />" : '';
        $deletelink = $this->template->meta('shout-delete', $shout['id']);
        if (!$this->canDelete(0, $shout)) {
            $deletelink = '';
        }

        $message = $this->textFormatting->theWorksInline($shout['shout']);
        if (mb_substr($message, 0, 4) === '/me ') {
            return $this->template->meta(
                'shout-action',
                $this->date->smallDate(
                    $shout['date'],
                    ['seconds' => true],
                ),
                $user,
                mb_substr(
                    $message,
                    3,
                ),
                $deletelink,
            );
        }

        return $this->template->meta(
            'shout',
            $shout['date'],
            $user,
            $message,
            $deletelink,
            $avatar,
        );
    }

    public function displayShoutbox(): void
    {
        $result = $this->database->special(
            <<<'SQL'
                SELECT
                    m.`avatar` AS `avatar`,
                    m.`display_name` AS `display_name`,
                    m.`group_id` AS `group_id`,
                    s.`id` AS `id`,
                    s.`shout` AS `shout`,
                    s.`uid` AS `uid`,
                    UNIX_TIMESTAMP(s.`date`) AS `date`
                FROM %t s
                LEFT JOIN %t m
                    ON s.`uid`=m.`id`
                ORDER BY s.`id` DESC LIMIT ?
                SQL,
            ['shouts', 'members'],
            $this->shoutlimit,
        );
        $shouts = '';
        $first = 0;
        while ($shout = $this->database->arow($result)) {
            if (!$first) {
                $first = $shout['id'];
            }

            $shouts .= $this->formatShout($shout);
        }

        $this->session->addVar('sb_id', $first);
        $this->page->append(
            'SHOUTBOX',
            $this->template->meta(
                'collapsebox',
                " id='shoutbox'",
                $this->template->meta(
                    'shoutbox-title',
                ),
                $this->template->meta(
                    'shoutbox',
                    $shouts,
                ),
            ) . "<script type='text/javascript'>globalsettings.shoutlimit="
                . $this->shoutlimit . ';globalsettings.sound_shout='
                . ($this->user->get('sound_shout') ? 1 : 0)
                . '</script>',
        );
    }

    public function updateShoutbox(): void
    {
        // This is a bit tricky, we're transversing the shouts
        // in reverse order, since they're shifted onto the list, not pushed.
        $last = 0;
        $shoutboxId = $this->session->getVar('sb_id') ?: 0;
        if ($shoutboxId) {
            $result = $this->database->special(
                <<<'SQL'
                    SELECT
                        m.`avatar` AS `avatar`,
                        m.`display_name` AS `display_name`,
                        m.`group_id` AS `group_id`,
                        s.`id` AS `id`,
                        s.`shout` AS `shout`,
                        s.`uid` AS `uid`,
                        UNIX_TIMESTAMP(s.`date`) AS `date`
                    FROM %t s
                    LEFT JOIN %t m
                        ON s.`uid`=m.`id`
                    WHERE s.`id`>?
                    ORDER BY s.`id` ASC LIMIT ?
                    SQL,
                ['shouts', 'members'],
                $shoutboxId,
                $this->shoutlimit,
            );
            foreach ($this->database->arows($result) as $shout) {
                $this->page->command('addShout', $this->formatShout($shout));
                $last = (int) $shout['id'];
            }
        }

        // Update the sb_id variable if we selected shouts.
        if ($last !== 0) {
            return;
        }

        $this->session->addVar('sb_id', $last);
    }

    public function showAllShouts(): void
    {
        $perpage = 100;
        $pagen = (int) $this->request->asString->both('page');
        $pages = '';
        $page = '';
        if ($pagen > 0) {
            --$pagen;
        }

        $result = $this->database->select(
            'COUNT(`id`) as `shoutcount`',
            'shouts',
        );
        $shoutCount = $this->database->arow($result);
        $numShouts = $shoutCount ? (int) $shoutCount['shoutcount'] : 0;
        $this->database->disposeresult($result);
        if ($numShouts > 1000) {
            $numShouts = 1000;
        }

        if ($numShouts > $perpage) {
            $pages .= " &middot; Pages: <span class='pages'>";
            $pageArray = $this->jax->pages(
                (int) ceil($numShouts / $perpage),
                $pagen + 1,
                10,
            );
            foreach ($pageArray as $v) {
                $pages .= '<a href="?module=shoutbox&page='
                    . $v . '"'
                    . ($v + 1 === $pagen ? ' class="active"' : '')
                    . '>' . $v . '</a> ';
            }

            $pages .= '</span>';
        }

        $this->page->setBreadCrumbs(['?module=shoutbox' => 'Shoutbox History']);
        if ($this->request->isJSUpdate()) {
            return;
        }

        $result = $this->database->special(
            <<<'SQL'
                SELECT
                    s.`id` AS `id`,
                    s.`uid` AS `uid`,
                    s.`shout` AS `shout`,
                    UNIX_TIMESTAMP(s.`date`) AS `date`,
                    m.`display_name` AS `display_name`,
                    m.`group_id` AS `group_id`,
                    m.`avatar` AS `avatar`
                FROM %t s
                LEFT JOIN %t m
                ON s.`uid`=m.`id`
                ORDER BY s.`id` DESC LIMIT ?,?
                SQL,
            ['shouts', 'members'],
            $pagen * $perpage,
            $perpage,
        );
        $shouts = '';
        while ($shout = $this->database->arow($result)) {
            $shouts .= $this->formatShout($shout);
        }

        $page = $this->template->meta(
            'box',
            '',
            'Shoutbox' . $pages,
            '<div class="sbhistory">' . $shouts . '</div>',
        );
        $this->page->command('update', 'page', $page);
        $this->page->append('PAGE', $page);
    }

    public function deleteShout(int $delete): void
    {
        $candelete = !$this->user->isGuest() && $this->canDelete($delete);

        if (!$candelete) {
            $this->page->location('?');

            return;
        }

        $this->page->command('softurl');
        $this->database->delete(
            'shouts',
            Database::WHERE_ID_EQUALS,
            $delete,
        );
    }

    public function addShout(): void
    {
        $this->session->act();
        $shout = $this->request->asString->post('shoutbox_shout') ?? '';
        $shout = $this->textFormatting->linkify($shout);

        $perms = $this->user->getPerms();

        $error = match (true) {
            $this->user->isGuest() => 'You must be logged in to shout!',
            $perms && !$perms['can_shout'] => 'You do not have permission to shout!',
            mb_strlen($shout) > 300 => 'Shout must be less than 300 characters.',
            default => null,
        };

        if ($error !== null) {
            $this->page->command('error', $error);
            $this->page->append('SHOUTBOX', $this->page->error($error));

            return;
        }

        $shoutData = [
            'date' => $this->database->datetime(),
            'ip' => $this->ipAddress->asBinary(),
            'shout' => $shout,
            'uid' => $this->user->get('id'),
        ];
        $this->database->insert(
            'shouts',
            $shoutData,
        );
        $shoutData['id'] = $this->database->insertId();

        $this->hooks->dispatch('shout', $shoutData);
    }
}
