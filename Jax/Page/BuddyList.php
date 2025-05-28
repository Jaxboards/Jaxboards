<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Jax;
use Jax\Models\Activity;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\User;

use function array_filter;
use function array_search;
use function explode;
use function implode;
use function in_array;
use function is_numeric;

final readonly class BuddyList
{
    public function __construct(
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Session $session,
        private readonly Request $request,
        private readonly Template $template,
        private readonly User $user,
    ) {
        $buddylist = $this->jax->hiddenFormFields(['act' => 'buddylist']);
        $this->template->addMeta(
            'buddylist-contacts',
            <<<HTML
                    <div class="contacts">
                        <form method="?" data-ajax-form="true">
                            {$buddylist}
                            <a href="?act=logreg5" id="status" class="%s">
                            </a>
                            <input style="width:100%%;padding-left:20px;" type="text" name="status"
                                onblur="this.form.onsubmit()" value="%s"/>
                            %s
                    </div>
                HTML,
        );
        $this->template->addMeta(
            'buddylist-contact',
            <<<'HTML'
                    <div
                        class="contact %3$s">
                        <a href="?act=vu%1$s">
                            <div class="avatar">
                                <img src="%4$s" alt="Avatar"/>
                            </div>
                            <div class="name">
                                %2$s
                            </div>
                            <div class="status">
                                %5$s
                            </div>
                    </div>
                HTML,
        );
    }

    public function render(): void
    {
        $this->page->command('softurl');
        if ($this->user->isGuest()) {
            $this->page->command(
                'error',
                'Sorry, you must be logged in to use this feature.',
            );

            return;
        }

        $add = (int) $this->request->asString->both('add');
        $remove = (int) $this->request->asString->both('remove');
        $status = $this->request->asString->both('status');
        $block = (int) $this->request->asString->both('block');
        $unblock = (int) $this->request->asString->both('unblock');

        $displayBuddyList = match (true) {
            $add !== 0 => $this->addBuddy($add),
            $remove !== 0 => $this->dropBuddy($remove),
            $status !== null => $this->setStatus($status),
            $block !== 0 => $this->block($block),
            $unblock !== 0 => $this->unBlock($unblock),
            default => true,
        };

        if (!$displayBuddyList) {
            return;
        }

        $this->displayBuddyList();
    }

    private function displayBuddyList(): void
    {
        if ($this->user->isGuest()) {
            return;
        }

        $this->page->command('softurl');
        $contacts = '';
        if ($this->user->get('friends')) {
            $online = $this->database->getUsersOnline();
            $friends = Member::selectMany(
                $this->database,
                'WHERE `id` IN ? ORDER BY `name` ASC',
                explode(',', (string) $this->user->get('friends')),
            );
            foreach ($friends as $friend) {
                $contacts .= $this->template->meta(
                    'buddylist-contact',
                    $friend->id,
                    $friend->name,
                    isset($online[$friend->id]) && $online[$friend->id]
                    ? 'online' : 'offline',
                    $friend->avatar ?: $this->template->meta('default-avatar'),
                    $friend->usertitle,
                );
            }
        }

        if ($this->user->get('enemies')) {
            $enemies = Member::selectMany(
                $this->database,
                'WHERE `id` IN ? ORDER BY `name` ASC',
                explode(',', (string) $this->user->get('enemies')),
            );
            foreach ($enemies as $enemy) {
                $contacts .= $this->template->meta(
                    'buddylist-contact',
                    $enemy->id,
                    $enemy->name,
                    'blocked',
                    $enemy->avatar ?: $this->template->meta('default-avatar'),
                    $enemy->usertitle,
                );
            }
        }

        if ($contacts === '' || $contacts === '0') {
            $contacts = $this->template->meta(
                'error',
                "You don't have any contacts added to your buddy list!",
            );
        }

        $this->page->command(
            'window',
            [
                'content' => $this->template->meta(
                    'buddylist-contacts',
                    $this->session->get('hide') ? 'invisible' : '',
                    $this->user->get('usertitle'),
                    $contacts,
                ),
                'id' => 'buddylist',
                'pos' => 'tr 20 20',
                'title' => 'Buddies',
            ],
        );
    }

    private function addBuddy(int $uid): bool
    {
        $friends = array_filter(
            explode(',', (string) $this->user->get('friends')),
            static fn($friend): bool => (bool) $friend,
        );
        $error = null;

        if (
            $this->user->get('enemies')
            && in_array((string) $uid, explode(',', (string) $this->user->get('enemies')), true)
        ) {
            $this->unBlock($uid);
        }

        $user = null;
        if ($uid) {
            $user = Member::selectMany($this->database, Database::WHERE_ID_EQUALS, $uid);
        }

        if (!$user) {
            $error = 'This user does not exist, and therefore could '
                . 'not be added to your contacts list.';
        } elseif (in_array((string) $uid, $friends, true)) {
            $error = 'This user is already in your contacts list.';
        }

        if ($error !== null) {
            $this->page->append('PAGE', $error);
            $this->page->command('error', $error);

            return false;
        }

        $friends[] = $uid;

        $this->user->set('friends', implode(',', $friends));
        $activity = new Activity();
        $activity->affected_uid = $uid;
        $activity->type = 'buddy_add';
        $activity->uid = (int) $this->user->get('id');
        $activity->insert($this->database);

        return true;
    }

    private function block(int $uid): bool
    {
        $error = null;
        $enemies = $this->user->get('enemies')
            ? explode(',', (string) $this->user->get('enemies'))
            : [];
        $friends = $this->user->get('friends')
            ? explode(',', (string) $this->user->get('friends'))
            : [];

        $isenemy = array_search((string) $uid, $enemies, true);
        $isfriend = array_search((string) $uid, $friends, true);
        if ($isfriend !== false) {
            $this->dropBuddy($uid);
        }

        if ($isenemy !== false) {
            $error = 'This user is already blocked.';
        }

        if ($error !== null) {
            $this->page->command('error', $error);

            return false;
        }

        $enemies[] = $uid;
        $enemies = implode(',', $enemies);
        $this->user->set('enemies', $enemies);

        return true;
    }

    private function unBlock(int $uid): bool
    {
        $enemies = explode(',', (string) $this->user->get('enemies'));
        $id = array_search((string) $uid, $enemies, true);
        if ($id === false) {
            return false;
        }

        unset($enemies[$id]);
        $enemies = implode(',', $enemies);
        $this->user->set('enemies', $enemies);

        return true;
    }

    private function dropBuddy(int $uid): bool
    {
        $friends = explode(',', (string) $this->user->get('friends'));
        $id = array_search((string) $uid, $friends, true);
        if ($id === false) {
            return false;
        }

        unset($friends[$id]);
        $friends = implode(',', $friends);
        $this->user->set('friends', $friends);

        return true;
    }

    private function setStatus(string $status): bool
    {
        if (
            $this->user->isGuest()
            || $this->user->get('usertitle') === $status
        ) {
            return false;
        }

        $this->user->set('usertitle', $status);

        return false;
    }
}
