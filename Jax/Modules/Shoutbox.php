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
use function min;
use function str_starts_with;
use function trim;

final class Shoutbox implements Module
{
    private readonly int $shoutlimit;

    private bool $avatarsEnabled = false;

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
        $this->avatarsEnabled = (bool) $this->config->getSetting('shoutboxava');
        $this->shoutlimit = (int) ($this->config->getSetting('shoutbox_num') ?? 5);
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

        $shoutboxDelete = (int) $this->request->both('shoutbox_delete');
        $shoutboxShout = trim($this->request->asString->post('shoutbox_shout') ?? '');

        if ($shoutboxShout !== '') {
            $this->addShout($shoutboxShout);
        }

        if ($this->request->both('module') === 'shoutbox') {
            $this->showAllShouts();
        }

        match (true) {
            $shoutboxDelete !== 0 => $this->deleteShout($shoutboxDelete),
            $this->request->isJSAccess() => $this->updateShoutbox(),
            default => $this->displayShoutbox(),
        };
    }

    public function canDelete(Shout $shout): bool
    {
        $canDeleteAllShouts = (bool) $this->user->getGroup()?->canDeleteShouts;
        $canDeleteOwnShouts = $this->user->getGroup()?->canDeleteOwnShouts;
        $isOwnShout = $shout->uid === $this->user->get()->id;

        return $canDeleteAllShouts || $canDeleteOwnShouts && $isOwnShout;
    }

    public function formatShout(Shout $shout, ?Member $member): string
    {
        if (str_starts_with($shout->shout, '/me ')) {
            return $this->template->render('shoutbox/action', [
                'avatarsEnabled' => $this->avatarsEnabled,
                'canDelete' => $this->canDelete($shout),
                'shout' => $shout,
                'timestamp' => $this->date->datetimeAsTimestamp($shout->date),
                'user' => $member,
            ]);
        }

        return $this->template->render('shoutbox/shout', [
            'avatarsEnabled' => $this->avatarsEnabled,
            'canDelete' => $this->canDelete($shout),
            'shout' => $shout,
            'timestamp' => $this->date->datetimeAsTimestamp($shout->date),
            'user' => $member,
        ]);
    }

    public function displayShoutbox(): void
    {
        $shouts = Shout::selectMany('ORDER BY `id` DESC LIMIT ?', $this->shoutlimit);

        $members = Member::joinedOn($shouts, static fn(Shout $shout): int => $shout->uid);

        $shoutHTML = '';
        foreach ($shouts as $shout) {
            $shoutHTML .= $this->formatShout($shout, $members[$shout->uid] ?? null);
        }

        $this->session->addVar('sb_id', $shouts[0]->id ?? 0);

        $soundShout = $this->user->get()->soundShout !== 0 ? 1 : 0;

        $this->page->append(
            'SHOUTBOX',
            $this->page->collapseBox(
                $this->template->render('shoutbox/title'),
                $this->template->render('shoutbox/shoutbox', [
                    'shouts' => $shoutHTML,
                ]),
                'shoutbox',
            )
                . <<<HTML
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

        $shouts = Shout::selectMany('WHERE `id`>? ORDER BY `id` ASC LIMIT ?', $shoutboxId, $this->shoutlimit);

        $members = Member::joinedOn($shouts, static fn(Shout $shout): int => $shout->uid);

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

        $numShouts = min(Shout::count(), 1_000);

        if ($numShouts > $perpage) {
            $pages .= " &middot; Pages: <span class='pages'>";
            $pageArray = $this->jax->pages((int) ceil($numShouts / $perpage), $pageNumber + 1, 10);
            foreach ($pageArray as $v) {
                $pages .=
                    '<a href="?module=shoutbox&page='
                    . $v
                    . '"'
                    . ($v === ($pageNumber + 1) ? ' class="active"' : '')
                    . '>'
                    . $v
                    . '</a> ';
            }

            $pages .= '</span>';
        }

        $this->page->setBreadCrumbs([
            $this->router->url('shoutbox') => 'Shoutbox History',
        ]);
        if ($this->request->isJSUpdate()) {
            return;
        }

        $shouts = Shout::selectMany('ORDER BY `id` DESC LIMIT ?,?', $pageNumber * $perpage, $perpage);

        $membersById = Member::joinedOn($shouts, static fn(Shout $shout): int => $shout->uid);

        $shoutHTML = '';
        foreach ($shouts as $shout) {
            $shoutHTML .= $this->formatShout($shout, $membersById[$shout->uid] ?? null);
        }

        $page = $this->template->render('global/box', [
            'title' => 'Shoutbox' . $pages,
            'content' => '<div class="sbhistory">' . $shoutHTML . '</div>',
        ]);
        $this->page->command('update', 'page', $page);
        $this->page->append('PAGE', $page);
    }

    public function deleteShout(int $delete): void
    {
        $shout = Shout::selectOne($delete);
        $candelete = !$this->user->isGuest() && $shout !== null && $this->canDelete($shout);

        if (!$candelete) {
            if ($this->request->isJSAccess()) {
                $this->page->command('error', "You don't have permission to delete that shout");

                return;
            }

            $this->router->redirect('index');

            return;
        }

        $this->page->command('preventNavigation');
        $this->database->delete('shouts', Database::WHERE_ID_EQUALS, $delete);
    }

    public function addShout(string $shoutBody): void
    {
        $shoutBody = $this->textFormatting->linkify($shoutBody);

        $error = match (true) {
            $this->user->isGuest() => 'You must be logged in to shout!',
            !$this->user->getGroup()?->canShout => 'You do not have permission to shout!',
            mb_strlen($shoutBody) > 255 => 'Shout must be less than 255 characters.',
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
