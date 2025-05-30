<?php

declare(strict_types=1);

namespace Jax\Modules;

use Jax\Config;
use Jax\Database;
use Jax\Date;
use Jax\Hooks;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Models\Member;
use Jax\Models\Shout;
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
            || !$this->user->getGroup()?->can_view_shoutbox
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

    public function canDelete(Shout $shout): bool
    {
        $candelete = (bool) $this->user->getGroup()?->can_delete_shouts;

        if ($candelete) {
            return $candelete;
        }

        if (!$this->user->getGroup()?->can_delete_own_shouts) {
            return $candelete;
        }

        if (
            isset($shout->uid)
            && $shout->uid === $this->user->get()->id
        ) {
            return true;
        }

        return $candelete;
    }

    public function formatShout(Shout $shout, ?Member $member): string
    {
        $user = $member ? $this->template->meta(
            'user-link',
            $member->id,
            $member->group_id,
            $member->display_name,
        ) : 'Guest';
        $avatarUrl = $member->avatar ?: $this->template->meta('default-avatar');
        $avatar = $this->config->getSetting('shoutboxava')
            ? "<img src='{$avatarUrl}' class='avatar' alt='avatar' />" : '';
        $deletelink = $this->template->meta('shout-delete', $shout->id);
        if (!$this->canDelete($shout)) {
            $deletelink = '';
        }

        $message = $this->textFormatting->theWorksInline($shout->shout);
        if (mb_substr($message, 0, 4) === '/me ') {
            return $this->template->meta(
                'shout-action',
                $this->date->smallDate(
                    $shout->date,
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
            $this->date->datetimeAsTimestamp($shout->date),
            $user,
            $message,
            $deletelink,
            $avatar,
        );
    }

    public function displayShoutbox(): void
    {
        $shouts = Shout::selectMany(
            $this->database,
            "ORDER BY `id` DESC LIMIT ?",
            $this->shoutlimit,
        );

        $members = Member::joinedOn(
            $this->database,
            $shouts,
            static fn(Shout $shout) => $shout->uid,
        );

        $shoutHTML = '';
        foreach($shouts as $shout) {
            $shoutHTML .= $this->formatShout($shout, $members[$shout->uid]);
        }

        $this->session->addVar('sb_id', $shouts[0]->id ?? 0);
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
                    $shoutHTML,
                ),
            ) . "<script type='text/javascript'>globalsettings.shoutlimit="
                . $this->shoutlimit . ';globalsettings.sound_shout='
                . ($this->user->get()->sound_shout !== 0 ? 1 : 0)
                . '</script>',
        );
    }

    public function updateShoutbox(): void
    {
        // This is a bit tricky, we're transversing the shouts
        // in reverse order, since they're shifted onto the list, not pushed.
        $last = 0;
        $shoutboxId = (int) $this->session->getVar('sb_id') ?: 0;

        if ($shoutboxId === 0) {
            return;
        }

        $shouts = Shout::selectMany(
            $this->database,
            'WHERE `id`>? ORDER BY `id` ASC LIMIT ?',
            $shoutboxId,
            $this->shoutlimit
        );

        $members = Member::joinedOn(
            $this->database,
            $shouts,
            static fn(Shout $shout) => $shout->uid
        );

        foreach ($shouts as $shout) {
            $this->page->command('addshout', $this->formatShout($shout, $members[$shout->uid]));
            $last = (int) $shout->id;
        }

        // Update the sb_id variable if we selected shouts.
        if ($last === 0) {
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

        $numShouts = Shout::count($this->database) ?? 0;

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

        $shouts = Shout::selectMany(
            $this->database,
            'ORDER BY `id` DESC LIMIT ?,?',
            $pagen * $perpage,
            $perpage,
        );

        $membersById = Member::joinedOn(
            $this->database,
            $shouts,
            static fn(Shout $shout) => $shout->uid
        );

        $shoutHTML = '';
        foreach ($shouts as $shout) {
            $shoutHTML .= $this->formatShout($shout, $membersById[$shout->uid]);
        }

        $page = $this->template->meta(
            'box',
            '',
            'Shoutbox' . $pages,
            '<div class="sbhistory">' . $shoutHTML . '</div>',
        );
        $this->page->command('update', 'page', $page);
        $this->page->append('PAGE', $page);
    }

    public function deleteShout(int $delete): void
    {
        $shout = Shout::selectOne($this->database, Database::WHERE_ID_EQUALS, $delete)?->asArray();
        $candelete = !$this->user->isGuest() && $this->canDelete($shout);

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
        $shoutBody = $this->request->asString->post('shoutbox_shout') ?? '';
        $shoutBody = $this->textFormatting->linkify($shoutBody);

        $error = match (true) {
            $this->user->isGuest() => 'You must be logged in to shout!',
            !$this->user->getGroup()?->can_shout => 'You do not have permission to shout!',
            mb_strlen($shoutBody) > 300 => 'Shout must be less than 300 characters.',
            default => null,
        };

        if ($error !== null) {
            $this->page->command('error', $error);
            $this->page->append('SHOUTBOX', $this->page->error($error));

            return;
        }

        $shout = new Shout();
        $shout->date = $this->database->datetime();
        $shout->ip = $this->ipAddress->asBinary() ?? '';
        $shout->shout = $shoutBody;
        $shout->uid = $this->user->get()->id;
        $shout->insert($this->database);

        $this->hooks->dispatch('shout', $shout);
    }
}
