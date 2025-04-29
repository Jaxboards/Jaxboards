<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\User;

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

        if ($this->request->both('add') !== null) {
            $this->addbuddy($this->request->both('add'));
        } elseif ($this->request->both('remove') !== null) {
            $this->dropbuddy($this->request->both('remove'));
        } elseif ($this->request->both('status') !== null) {
            $this->setstatus($this->request->both('status'));
        } elseif ($this->request->both('block') !== null) {
            $this->block($this->request->both('block'));
        } elseif ($this->request->both('unblock') !== null) {
            $this->unblock($this->request->both('unblock'));
        } else {
            $this->displaybuddylist();
        }
    }

    private function displaybuddylist(): void
    {
        if ($this->user->isGuest()) {
            return;
        }

        $this->page->command('softurl');
        $contacts = '';
        if ($this->user->get('friends')) {
            $online = $this->database->getUsersOnline();
            $result = $this->database->safeselect(
                [
                    'id',
                    'avatar',
                    '`display_name` AS `name`',
                    'usertitle',
                ],
                'members',
                'WHERE `id` IN ? ORDER BY `name` ASC',
                explode(',', (string) $this->user->get('friends')),
            );
            while ($contact = $this->database->arow($result)) {
                $contacts .= $this->template->meta(
                    'buddylist-contact',
                    $contact['id'],
                    $contact['name'],
                    isset($online[$contact['id']]) && $online[$contact['id']]
                    ? 'online' : 'offline',
                    $contact['avatar'] ?: $this->template->meta('default-avatar'),
                    $contact['usertitle'],
                );
            }
        }

        if ($this->user->get('enemies')) {
            $result = $this->database->safeselect(
                '`id`,`avatar`,`display_name` AS `name`,`usertitle`',
                'members',
                'WHERE `id` IN ? ORDER BY `name` ASC',
                explode(',', (string) $this->user->get('enemies')),
            );
            while ($contact = $this->database->arow($result)) {
                $contacts .= $this->template->meta(
                    'buddylist-contact',
                    $contact['id'],
                    $contact['name'],
                    'blocked',
                    $contact['avatar'] ?: $this->template->meta('default-avatar'),
                    $contact['usertitle'],
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

    private function addbuddy(array|string $uid): void
    {
        $friends = $this->user->get('friends');
        $error = null;

        if (
            $this->user->get('enemies')
            && in_array($uid, explode(',', (string) $this->user->get('enemies')))
        ) {
            $this->unblock($uid);
        }

        $user = false;
        if ($uid && is_numeric($uid)) {
            $result = $this->database->safeselect(
                ['id'],
                'members',
                'WHERE `id`=?',
                $uid,
            );
            $user = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        if (!$user) {
            $error = 'This user does not exist, and therefore could '
                . 'not be added to your contacts list.';
        } elseif (in_array($uid, explode(',', (string) $friends))) {
            $error = 'This user is already in your contacts list.';
        }

        if ($error !== null) {
            $this->page->append('PAGE', $error);
            $this->page->command('error', $error);
        } else {
            if ($friends) {
                $friends .= ',' . $uid;
            } else {
                $friends = $uid;
            }

            $this->user->set('friends', $friends);
            $this->database->safeinsert(
                'activity',
                [
                    'affected_uid' => $uid,
                    'type' => 'buddy_add',
                    'uid' => $this->user->get('id'),
                ],
            );
            $this->displaybuddylist();
        }
    }

    private function block(array|string $uid): void
    {
        if (!is_numeric($uid)) {
            return;
        }

        $error = null;
        $enemies = $this->user->get('enemies')
            ? explode(',', (string) $this->user->get('enemies'))
            : [];
        $friends = $this->user->get('friends')
            ? explode(',', (string) $this->user->get('friends'))
            : [];

        $isenemy = array_search($uid, $enemies, true);
        $isfriend = array_search($uid, $friends, true);
        if ($isfriend !== false) {
            $this->dropbuddy($uid, 1);
        }

        if ($isenemy !== false) {
            $error = 'This user is already blocked.';
        }

        if ($error !== null) {
            $this->page->command('error', $error);
        } else {
            $enemies[] = $uid;
            $enemies = implode(',', $enemies);
            $this->user->set('enemies', $enemies);
            $this->displaybuddylist();
        }
    }

    private function unblock(array|string $uid): void
    {
        if ($uid && is_numeric($uid)) {
            $enemies = explode(',', (string) $this->user->get('enemies'));
            $id = array_search($uid, $enemies, true);
            if ($id === false) {
                return;
            }

            unset($enemies[$id]);
            $enemies = implode(',', $enemies);
            $this->user->set('enemies', $enemies);
        }

        $this->displaybuddylist();
    }

    private function dropbuddy(array|string $uid, int $shh = 0): void
    {
        if ($uid && is_numeric($uid)) {
            $friends = explode(',', (string) $this->user->get('friends'));
            $id = array_search($uid, $friends, true);
            if ($id === false) {
                return;
            }

            unset($friends[$id]);
            $friends = implode(',', $friends);
            $this->user->set('friends', $friends);
        }

        if ($shh !== 0) {
            return;
        }

        $this->displaybuddylist();
    }

    private function setstatus(array|string $status): void
    {
        if (
            $this->user->isGuest()
            || $this->user->get('usertitle') === $status
        ) {
            return;
        }

        $this->user->set('usertitle', $status);
    }
}
