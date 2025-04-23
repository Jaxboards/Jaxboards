<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Session;

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
    ) {
        $buddylist = $this->jax->hiddenFormFields(['act' => 'buddylist']);
        $this->page->metadefs['buddylist-contacts'] = <<<EOT
            <div class="contacts">
                <form method="?" data-ajax-form="true">
                    {$buddylist}
                    <a href="?act=logreg5" id="status" class="%s">
                    </a>
                    <input style="width:100%%;padding-left:20px;" type="text" name="status"
                        onblur="this.form.onsubmit()" value="%s"/>
                    %s
            </div>
            EOT;
        $this->page->metadefs['buddylist-contact'] = <<<'EOT'
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
            EOT;
    }

    public function route(): void
    {
        global $USER;

        $this->page->JS('softurl');
        if (!$USER) {
            $this->page->JS(
                'error',
                'Sorry, you must be logged in to use this feature.',
            );

            return;
        }

        if (isset($this->jax->b['add']) && $this->jax->b['add']) {
            $this->addbuddy($this->jax->b['add']);
        } elseif (isset($this->jax->b['remove']) && $this->jax->b['remove']) {
            $this->dropbuddy($this->jax->b['remove']);
        } elseif (isset($this->jax->b['status']) && $this->jax->b['status']) {
            $this->setstatus($this->jax->b['status']);
        } elseif (isset($this->jax->b['block']) && $this->jax->b['block']) {
            $this->block($this->jax->b['block']);
        } elseif (
            isset($this->jax->b['unblock'])
            && $this->jax->b['unblock']
        ) {
            $this->unblock($this->jax->b['unblock']);
        } else {
            $this->displaybuddylist();
        }
    }

    public function displaybuddylist(): void
    {
        global $USER;
        if (!$USER) {
            return;
        }

        $this->page->JS('softurl');
        $contacts = '';
        if ($USER['friends']) {
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
                explode(',', (string) $USER['friends']),
            );
            while ($f = $this->database->arow($result)) {
                $contacts .= $this->page->meta(
                    'buddylist-contact',
                    $f['id'],
                    $f['name'],
                    isset($online[$f['id']]) && $online[$f['id']]
                    ? 'online' : 'offline',
                    $this->jax->pick($f['avatar'], $this->page->meta('default-avatar')),
                    $f['usertitle'],
                );
            }
        }

        if ($USER['enemies']) {
            $result = $this->database->safeselect(
                '`id`,`avatar`,`display_name` AS `name`,`usertitle`',
                'members',
                'WHERE `id` IN ? ORDER BY `name` ASC',
                explode(',', (string) $USER['enemies']),
            );
            while ($f = $this->database->arow($result)) {
                $contacts .= $this->page->meta(
                    'buddylist-contact',
                    $f['id'],
                    $f['name'],
                    'blocked',
                    $this->jax->pick($f['avatar'], $this->page->meta('default-avatar')),
                    $f['usertitle'],
                );
            }
        }

        if ($contacts === '' || $contacts === '0') {
            $contacts = $this->page->meta(
                'error',
                "You don't have any contacts added to your buddy list!",
            );
        }

        $this->page->JS(
            'window',
            [
                'content' => $this->page->meta(
                    'buddylist-contacts',
                    $this->session->hide ? 'invisible' : '',
                    $USER['usertitle'],
                    $contacts,
                ),
                'id' => 'buddylist',
                'pos' => 'tr 20 20',
                'title' => 'Buddies',
            ],
        );
    }

    public function addbuddy($uid): void
    {
        global $USER;
        $friends = $USER['friends'];
        $e = '';

        if (
            $USER['enemies']
            && in_array($uid, explode(',', (string) $USER['enemies']))
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
            $e = 'This user does not exist, and therefore could '
                . 'not be added to your contacts list.';
        } elseif (in_array($uid, explode(',', (string) $friends))) {
            $e = 'This user is already in your contacts list.';
        }

        if ($e !== '' && $e !== '0') {
            $this->page->append('PAGE', $e);
            $this->page->JS('error', $e);
        } else {
            if ($friends) {
                $friends .= ',' . $uid;
            } else {
                $friends = $uid;
            }

            $USER['friends'] = $friends;
            $this->database->safeupdate(
                'members',
                [
                    'friends' => $friends,
                ],
                ' WHERE `id`=?',
                $USER['id'],
            );
            $this->database->safeinsert(
                'activity',
                [
                    'affected_uid' => $uid,
                    'type' => 'buddy_add',
                    'uid' => $USER['id'],
                ],
            );
            $this->displaybuddylist();
        }
    }

    public function block($uid): void
    {
        if (!is_numeric($uid)) {
            return;
        }

        global $USER;
        $e = '';
        $enemies = $USER['enemies']
            ? explode(',', (string) $USER['enemies'])
            : [];
        $friends = $USER['friends']
            ? explode(',', (string) $USER['friends'])
            : [];
        $isenemy = array_search($uid, $enemies, true);
        $isfriend = array_search($uid, $friends, true);
        if ($isfriend !== false) {
            $this->dropbuddy($uid, 1);
        }

        if ($isenemy !== false) {
            $e = 'This user is already blocked.';
        }

        if ($e !== '' && $e !== '0') {
            $this->page->JS('error', $e);
        } else {
            $enemies[] = $uid;
            $enemies = implode(',', $enemies);
            $USER['enemies'] = $enemies;
            $this->database->safeupdate(
                'members',
                [
                    'enemies' => $enemies,
                ],
                ' WHERE `id`=?',
                $USER['id'],
            );
            $this->displaybuddylist();
        }
    }

    public function unblock($uid): void
    {
        global $USER;
        if ($uid && is_numeric($uid)) {
            $enemies = explode(',', (string) $USER['enemies']);
            $id = array_search($uid, $enemies, true);
            if ($id === false) {
                return;
            }

            unset($enemies[$id]);
            $enemies = implode(',', $enemies);
            $USER['enemies'] = $enemies;
            $this->database->safeupdate(
                'members',
                [
                    'enemies' => $enemies,
                ],
                ' WHERE `id`=?',
                $USER['id'],
            );
        }

        $this->displaybuddylist();
    }

    public function dropbuddy($uid, $shh = 0): void
    {
        global $USER;
        if ($uid && is_numeric($uid)) {
            $friends = explode(',', (string) $USER['friends']);
            $id = array_search($uid, $friends, true);
            if ($id === false) {
                return;
            }

            unset($friends[$id]);
            $friends = implode(',', $friends);
            $USER['friends'] = $friends;
            $this->database->safeupdate(
                'members',
                [
                    'friends' => $friends,
                ],
                ' WHERE `id`=?',
                $USER['id'],
            );
        }

        if ($shh) {
            return;
        }

        $this->displaybuddylist();
    }

    public function setstatus($status): void
    {
        global $USER;
        if (!$USER || $USER['usertitle'] === $status) {
            return;
        }

        $this->database->safeupdate(
            'members',
            [
                'usertitle' => $status,
            ],
            'WHERE `id`=?',
            $USER['id'],
        );
    }
}
