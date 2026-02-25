<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Database\Database;
use Jax\Interfaces\Route;
use Jax\Models\Activity;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\User;
use Jax\UsersOnline;
use Override;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_search;
use function explode;
use function implode;
use function in_array;

final readonly class Contacts implements Route
{
    public function __construct(
        private Page $page,
        private Session $session,
        private Request $request,
        private Template $template,
        private User $user,
        private UsersOnline $usersOnline,
    ) {}

    #[Override]
    public function route($params): void
    {
        $this->page->command('preventNavigation');

        if ($this->user->isGuest()) {
            $this->page->command('error', 'Sorry, you must be logged in to use this feature.');

            return;
        }

        $add = (int) $this->request->asString->both('add');
        $remove = (int) $this->request->asString->both('remove');
        $status = $this->request->asString->both('status');
        $block = (int) $this->request->asString->both('block');
        $unblock = (int) $this->request->asString->both('unblock');

        $displayBuddyList = match (true) {
            $add !== 0 => $this->addContact($add),
            $remove !== 0 => $this->dropContact($remove),
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

        $online = $this->usersOnline->getUsersOnline();

        $friends = [];
        $enemies = [];

        if ($this->user->get()->friends !== '') {
            $friends = array_map(
                static fn(Member $member): array => [
                    'user' => $member,
                    'class' => array_key_exists($member->id, $online) ? 'online' : 'offline',
                ],
                Member::selectMany('WHERE `id` IN ? ORDER BY `name` ASC', explode(',', $this->user->get()->friends)),
            );
        }

        if ($this->user->get()->enemies !== '') {
            $enemies = array_map(
                static fn(Member $member): array => [
                    'user' => $member,
                    'class' => 'blocked',
                ],
                Member::selectMany('WHERE `id` IN ? ORDER BY `name` ASC', explode(',', $this->user->get()->enemies)),
            );
        }

        $this->page->command('window', [
            'content' => $this->template->render('contacts/index', [
                'enemies' => $enemies,
                'friends' => $friends,
                'isInvisible' => $this->session->get()->hide !== 0,
                'user' => $this->user->get(),
            ]),
            'id' => 'contacts',
            'pos' => 'tr 20 20',
            'title' => 'Contacts',
            'resize' => '.content',
        ]);
    }

    private function addContact(int $uid): bool
    {
        $friends = array_filter(explode(',', $this->user->get()->friends), static fn($friend): bool => (bool) $friend);
        $error = null;

        if ($this->user->get()->enemies && in_array((string) $uid, explode(',', $this->user->get()->enemies), true)) {
            $this->unBlock($uid);
        }

        $user = null;
        if ($uid !== 0) {
            $user = Member::selectMany(Database::WHERE_ID_EQUALS, $uid);
        }

        if (!$user) {
            $error = 'This user does not exist, and therefore could not be added to your contacts list.';
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
        $activity->affectedUser = $uid;
        $activity->type = 'buddy_add';
        $activity->uid = $this->user->get()->id;
        $activity->insert();

        return true;
    }

    private function block(int $uid): bool
    {
        $error = null;
        $enemies = $this->user->get()->enemies !== '' ? explode(',', $this->user->get()->enemies) : [];
        $friends = $this->user->get()->friends !== '' ? explode(',', $this->user->get()->friends) : [];

        $isenemy = array_search((string) $uid, $enemies, true);
        $isfriend = array_search((string) $uid, $friends, true);
        if ($isfriend !== false) {
            $this->dropContact($uid);
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
        $enemies = explode(',', $this->user->get()->enemies);
        $id = array_search((string) $uid, $enemies, true);
        if ($id === false) {
            return false;
        }

        unset($enemies[$id]);
        $enemies = implode(',', $enemies);
        $this->user->set('enemies', $enemies);

        return true;
    }

    private function dropContact(int $uid): bool
    {
        $friends = explode(',', $this->user->get()->friends);
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
        if ($this->user->isGuest() || $this->user->get()->usertitle === $status) {
            return false;
        }

        $this->user->set('usertitle', $status);

        return false;
    }
}
