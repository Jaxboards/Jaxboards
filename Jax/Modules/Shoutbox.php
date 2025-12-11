<?php

declare(strict_types=1);

namespace Jax\Modules;

use Jax\Config;
use Jax\Database\Database;
use Jax\Date;
use Jax\Hooks;
use Jax\Interfaces\Module;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Models\Member;
use Jax\Models\Shout;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function ceil;
use function mb_strlen;
use function mb_substr;
use function trim;

final class Shoutbox implements Module
{
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
        private readonly Router $router,
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
            !$this->template->has('shoutbox')
            || !$this->config->getSetting('shoutbox')
            || !$this->user->getGroup()?->canViewShoutbox
        ) {
            return;
        }

        $this->shoutlimit = (int) ($this->config->getSetting('shoutbox_num') ?? 5);
        $shoutboxDelete = (int) $this->request->both('shoutbox_delete');
        $shoutboxShout = trim($this->request->asString->post('shoutbox_shout') ?? '');

        if ($shoutboxShout !== '') {
            $this->addShout($shoutboxShout);
        }

        if ($shoutboxDelete !== 0) {
            $this->deleteShout($shoutboxDelete);
        } elseif (
            $this->request->both('module') === 'shoutbox'
        ) {
            $this->showAllShouts();
        }

        if (!$this->request->isJSAccess()) {
            $this->displayShoutbox();
        } else {
            $this->updateShoutbox();
        }
    }

    public function canDelete(Shout $shout): bool
    {
        $candelete = (bool) $this->user->getGroup()?->canDeleteShouts;

        if ($candelete) {
            return $candelete;
        }

        if (!$this->user->getGroup()?->canDeleteOwnShouts) {
            return $candelete;
        }

        if (
            $shout->uid === $this->user->get()->id
        ) {
            return true;
        }

        return $candelete;
    }

    public function formatShout(Shout $shout, ?Member $member): string
    {
        $user = $member !== null ? $this->template->meta(
            'user-link',
            $member->id,
            $member->groupID,
            $member->displayName,
        ) : 'Guest';
        $avatarUrl = $member?->avatar ?: $this->template->meta('default-avatar');
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
                $shout->date ? $this->date->smallDate(
                    $shout->date,
                    ['seconds' => true],
                ) : '',
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
            'ORDER BY `id` DESC LIMIT ?',
            $this->shoutlimit,
        );

        $members = Member::joinedOn(
            $shouts,
            static fn(Shout $shout): int => $shout->uid,
        );

        $shoutHTML = '';
        foreach ($shouts as $shout) {
            $shoutHTML .= $this->formatShout($shout, $members[$shout->uid]);
        }

        $this->session->addVar('sb_id', $shouts[0]->id ?? 0);

        $soundShout = $this->user->get()->soundShout !== 0 ? 1 : 0;

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
            ) . <<<HTML
                <script type='text/javascript'>
                    Object.assign(globalSettings, {
                        shoutLimit: {$this->shoutlimit},
                        soundShout: {$soundShout},
                    });
                </script>
                HTML,
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
            'WHERE `id`>? ORDER BY `id` ASC LIMIT ?',
            $shoutboxId,
            $this->shoutlimit,
        );

        $members = Member::joinedOn(
            $shouts,
            static fn(Shout $shout): int => $shout->uid,
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
        $pageNumber = (int) $this->request->asString->both('page');
        $pages = '';
        $page = '';
        if ($pageNumber > 0) {
            --$pageNumber;
        }

        $numShouts = Shout::count() ?? 0;

        if ($numShouts > 1000) {
            $numShouts = 1000;
        }

        if ($numShouts > $perpage) {
            $pages .= " &middot; Pages: <span class='pages'>";
            $pageArray = $this->jax->pages(
                (int) ceil($numShouts / $perpage),
                $pageNumber + 1,
                10,
            );
            foreach ($pageArray as $v) {
                $pages .= '<a href="?module=shoutbox&page='
                    . $v . '"'
                    . ($v === ($pageNumber + 1) ? ' class="active"' : '')
                    . '>' . $v . '</a> ';
            }

            $pages .= '</span>';
        }

        $this->page->setBreadCrumbs(['?module=shoutbox' => 'Shoutbox History']);
        if ($this->request->isJSUpdate()) {
            return;
        }

        $shouts = Shout::selectMany(
            'ORDER BY `id` DESC LIMIT ?,?',
            $pageNumber * $perpage,
            $perpage,
        );

        $membersById = Member::joinedOn(
            $shouts,
            static fn(Shout $shout): int => $shout->uid,
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
        $shout = Shout::selectOne($delete);
        $candelete = !$this->user->isGuest() && $shout !== null && $this->canDelete($shout);

        if (!$candelete) {
            $this->router->redirect('?');

            return;
        }

        $this->page->command('softurl');
        $this->database->delete(
            'shouts',
            Database::WHERE_ID_EQUALS,
            $delete,
        );
    }

    public function addShout(string $shoutBody): void
    {
        $this->session->act();
        $shoutBody = $this->textFormatting->linkify($shoutBody);

        $error = match (true) {
            $this->user->isGuest() => 'You must be logged in to shout!',
            !$this->user->getGroup()?->canShout => 'You do not have permission to shout!',
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
        $shout->insert();

        $this->hooks->dispatch('shout', $shout);
    }
}
