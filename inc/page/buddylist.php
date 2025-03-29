<?php

$buddylist = $JAX->hiddenFormFields(['module' => 'buddylist']);
$PAGE->metadefs['buddylist-contacts'] = <<<EOT
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
$PAGE->metadefs['buddylist-contact'] = <<<'EOT'
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
new buddylist();
final class buddylist
{
    public function __construct()
    {
        global $PAGE,$JAX,$USER;

        $PAGE->JS('softurl');
        if (!$USER) {
            $PAGE->JS(
                'error',
                'Sorry, you must be logged in to use this feature.',
            );

            return;
        }

        if (isset($JAX->b['add']) && $JAX->b['add']) {
            $this->addbuddy($JAX->b['add']);
        } elseif (isset($JAX->b['remove']) && $JAX->b['remove']) {
            $this->dropbuddy($JAX->b['remove']);
        } elseif (isset($JAX->b['status']) && $JAX->b['status']) {
            $this->setstatus($JAX->b['status']);
        } elseif (isset($JAX->b['block']) && $JAX->b['block']) {
            $this->block($JAX->b['block']);
        } elseif (isset($JAX->b['unblock']) && $JAX->b['unblock']) {
            $this->unblock($JAX->b['unblock']);
        } else {
            $this->displaybuddylist();
        }
    }

    public function displaybuddylist(): void
    {
        global $JAX,$PAGE,$USER,$DB,$SESS;
        if (!$USER) {
            return;
        }

        $PAGE->JS('softurl');
        $crap = '';
        if ($USER['friends']) {
            $online = $DB->getUsersOnline();
            $result = $DB->safeselect(
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
            while ($f = $DB->arow($result)) {
                $crap .= $PAGE->meta(
                    'buddylist-contact',
                    $f['id'],
                    $f['name'],
                    isset($online[$f['id']]) && $online[$f['id']]
                    ? 'online' : 'offline',
                    $JAX->pick($f['avatar'], $PAGE->meta('default-avatar')),
                    $f['usertitle'],
                );
            }
        }

        if ($USER['enemies']) {
            $result = $DB->safeselect(
                '`id`,`avatar`,`display_name` AS `name`,`usertitle`',
                'members',
                'WHERE `id` IN ? ORDER BY `name` ASC',
                explode(',', (string) $USER['enemies']),
            );
            while ($f = $DB->arow($result)) {
                $crap .= $PAGE->meta(
                    'buddylist-contact',
                    $f['id'],
                    $f['name'],
                    'blocked',
                    $JAX->pick($f['avatar'], $PAGE->meta('default-avatar')),
                    $f['usertitle'],
                );
            }
        }

        if ($crap === '' || $crap === '0') {
            $crap = $PAGE->meta(
                'error',
                "You don't have any contacts added to your buddy list!",
            );
        }

        $PAGE->JS(
            'window',
            [
                'content' => $PAGE->meta(
                    'buddylist-contacts',
                    $SESS->hide ? 'invisible' : '',
                    $USER['usertitle'],
                    $crap,
                ),
                'id' => 'buddylist',
                'pos' => 'tr 20 20',
                'title' => 'Buddies',
            ],
        );
    }

    public function addbuddy($uid): void
    {
        global $DB,$PAGE,$USER;
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
            $result = $DB->safeselect(
                [
                    'about',
                    'avatar',
                    'birthdate',
                    'contact_aim',
                    'contact_bluesky',
                    'contact_discord',
                    'contact_gtalk',
                    'contact_msn',
                    'contact_skype',
                    'contact_steam',
                    'contact_twitter',
                    'contact_yim',
                    'contact_youtube',
                    'display_name',
                    'email_settings',
                    'email',
                    'enemies',
                    'friends',
                    'full_name',
                    'gender',
                    'group_id',
                    'id',
                    'location',
                    'mod',
                    'name',
                    'notify_pm',
                    'notify_postinmytopic',
                    'notify_postinsubscribedtopic',
                    'nowordfilter',
                    'pass',
                    'posts',
                    'sig',
                    'skin_id',
                    'sound_im',
                    'sound_pm',
                    'sound_postinmytopic',
                    'sound_postinsubscribedtopic',
                    'sound_shout',
                    'ucpnotepad',
                    'usertitle',
                    'website',
                    'wysiwyg',
                    'DAY(`birthdate`) AS `dob_day`',
                    'INET6_NTOA(`ip`) AS `ip`',
                    'MONTH(`birthdate`) AS `dob_month`',
                    'UNIX_TIMESTAMP(`join_date`) AS `join_date`',
                    'UNIX_TIMESTAMP(`last_visit`) AS `last_visit`',
                    'YEAR(`birthdate`) AS `dob_year`',
                ],
                'members',
                'WHERE `id`=?',
                $uid,
            );
            $user = $DB->arow($result);
            $DB->disposeresult($result);
        }

        if (!$user) {
            $e = 'This user does not exist, and therefore could '
                . 'not be added to your contacts list.';
        } elseif (in_array($uid, explode(',', (string) $friends))) {
            $e = 'This user is already in your contacts list.';
        }

        if ($e !== '' && $e !== '0') {
            $PAGE->append('PAGE', $e);
            $PAGE->JS('error', $e);
        } else {
            if ($friends) {
                $friends .= ',' . $uid;
            } else {
                $friends = $uid;
            }

            $USER['friends'] = $friends;
            $DB->safeupdate(
                'members',
                [
                    'friends' => $friends,
                ],
                ' WHERE `id`=?',
                $USER['id'],
            );
            $DB->safeinsert(
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

        global $DB,$PAGE,$USER;
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
            $PAGE->JS('error', $e);
        } else {
            $enemies[] = $uid;
            $enemies = implode(',', $enemies);
            $USER['enemies'] = $enemies;
            $DB->safeupdate(
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
        global $DB,$USER,$PAGE;
        if ($uid && is_numeric($uid)) {
            $enemies = explode(',', (string) $USER['enemies']);
            $id = array_search($uid, $enemies, true);
            if ($id === false) {
                return;
            }

            unset($enemies[$id]);
            $enemies = implode(',', $enemies);
            $USER['enemies'] = $enemies;
            $DB->safeupdate(
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
        global $DB,$USER,$PAGE;
        if ($uid && is_numeric($uid)) {
            $friends = explode(',', (string) $USER['friends']);
            $id = array_search($uid, $friends, true);
            if ($id === false) {
                return;
            }

            unset($friends[$id]);
            $friends = implode(',', $friends);
            $USER['friends'] = $friends;
            $DB->safeupdate(
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
        global $DB,$USER,$PAGE;
        if (!$USER || $USER['usertitle'] === $status) {
            return;
        }

        $DB->safeupdate(
            'members',
            [
                'usertitle' => $status,
            ],
            'WHERE `id`=?',
            $USER['id'],
        );
    }
}
