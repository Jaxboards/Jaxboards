<?php

$buddylist = $JAX->hiddenFormFields(array('module' => 'buddylist'));
$PAGE->metadefs['buddylist-contacts'] = <<<EOT
<div class="contacts">
    <form method="?" data-ajax-form="true">
        ${buddylist}
        <a href="?act=logreg5" id="status" class="%s">
        </a>
        <input style="width:100%%;padding-left:20px;" type="text" name="status"
            onblur="this.form.onsubmit()" value="%s"/>
        %s
</div>
EOT;
$PAGE->metadefs['buddylist-contact'] = <<<'EOT'
<div onclick="new IMWindow(%1$s,'%2$s')"
    class="contact %3$s">
    <a href="?act=vu%1$s">
        <div class="avatar">
            <img src="%4$s" />
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
class buddylist
{
    public function buddylist()
    {
        $this->__construct();
    }

    public function __construct()
    {
        global $PAGE,$JAX,$USER;
        if (!$USER) {
            $PAGE->JS('softurl');
            if ($JAX->c['buddylist']) {
                $JAX->setCookie(
                    array(
                        'buddylist' => false,
                    ),
                    -1,
                    -1,
                    false
                );

                return;
            }

            return $PAGE->JS(
                'error',
                'Sorry, you must be logged in to use this feature.'
            );
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
        } elseif (isset($JAX->b['module']) && 'buddylist' == $JAX->b['module']) {
            $this->displaybuddylist();
        } else {
            $this->update();
        }
    }

    public function displaybuddylist()
    {
        global $JAX,$PAGE,$USER,$DB,$SESS;
        if (!$USER) {
            return;
        }
        $JAX->setCookie(
            array(
                'buddylist' => 1,
            ),
            null,
            null,
            false
        );
        $PAGE->JS('softurl');
        $crap = '';
        if ($USER['friends']) {
            $online = $DB->getUsersOnline();
            $result = $DB->safeselect(
                '`id`,`avatar`,`display_name` AS `name`,`usertitle`',
                'members',
                'WHERE `id` IN ? ORDER BY `name` ASC',
                explode(',', $USER['friends'])
            );
            while ($f = $DB->arow($result)) {
                $crap .= $PAGE->meta(
                    'buddylist-contact',
                    $f['id'],
                    $f['name'],
                    isset($online[$f['id']]) && $online[$f['id']]
                    ? 'online' : 'offline',
                    $JAX->pick($f['avatar'], $PAGE->meta('default-avatar')),
                    $f['usertitle']
                );
            }
        }
        if ($USER['enemies']) {
            $result = $DB->safeselect(
                '`id`,`avatar`,`display_name` AS `name`,`usertitle`',
                'members',
                'WHERE `id` IN ? ORDER BY `name` ASC',
                explode(',', $USER['enemies'])
            );
            while ($f = $DB->arow($result)) {
                $crap .= $PAGE->meta(
                    'buddylist-contact',
                    $f['id'],
                    $f['name'],
                    'blocked',
                    $JAX->pick($f['avatar'], $PAGE->meta('default-avatar')),
                    $f['usertitle']
                );
            }
        }
        if (!$crap) {
            $crap = $PAGE->meta(
                'error',
                "You don't have any contacts added to your buddy list!"
            );
        }
        $PAGE->JS(
            'openbuddylist',
            array(
                'title' => 'Buddies',
                'content' => $PAGE->meta(
                    'buddylist-contacts',
                    $SESS->hide ? 'invisible' : '',
                    $USER['usertitle'],
                    $crap
                ),
            )
        );
    }

    public function update()
    {
        global $PAGE;
    }

    public function addbuddy($uid)
    {
        global $DB,$PAGE,$USER;
        $friends = $USER['friends'];
        $e = '';

        if ($USER['enemies'] && in_array($uid, explode(',', $USER['enemies']))) {
            $this->unblock($uid);
        }

        $user = false;
        if ($uid && is_numeric($uid)) {
            $result = $DB->safeselect(
                <<<'EOT'
`id`,`name`,`pass`,`email`,`sig`,`posts`,`group_id`,`avatar`,`usertitle`,
UNIX_TIMESTAMP(`join_date`) AS `join_date`,
UNIX_TIMESTAMP(`last_visit`) AS `last_visit`,
`contact_skype`,`contact_yim`,`contact_msn`, `contact_gtalk`,`contact_aim`,
`website`,`birthdate`, DAY(`birthdate`) AS `dob_day`,
MONTH(`birthdate`) AS `dob_month`, YEAR(`birthdate`) AS `dob_year`,
`about`,`display_name`,`full_name`,`contact_steam`,`location`,`gender`,
`friends`,`enemies`,`sound_shout`,`sound_im`,`sound_pm`,`sound_postinmytopic`,
`sound_postinsubscribedtopic`,`notify_pm`,`notify_postinmytopic`,
`notify_postinsubscribedtopic`,`ucpnotepad`,`skin_id`,`contact_twitter`,
`email_settings`,`nowordfilter`,INET6_NTOA(`ip`) AS `ip`,`mod`,`wysiwyg`
EOT
                ,
                'members',
                'WHERE `id`=?',
                $uid
            );
            $user = $DB->arow($result);
            $DB->disposeresult($result);
        }

        if (!$user) {
            $e = 'This user does not exist, and therefore could ' .
                'not be added to your contacts list.';
        } elseif (in_array($uid, explode(',', $friends))) {
            $e = 'This user is already in your contacts list.';
        }

        if ($e) {
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
                array(
                    'friends' => $friends,
                ),
                ' WHERE `id`=?',
                $USER['id']
            );
            $DB->safeinsert(
                'activity',
                array(
                    'type' => 'buddy_add',
                    'affected_uid' => $uid,
                    'uid' => $USER['id'],
                )
            );
            $this->displaybuddylist();
        }
    }

    public function block($uid)
    {
        if (!is_numeric($uid)) {
            return;
        }
        global $DB,$PAGE,$USER;
        $e = '';
        $enemies = $USER['enemies'] ? explode(',', $USER['enemies']) : array();
        $friends = $USER['friends'] ? explode(',', $USER['friends']) : array();
        $isenemy = array_search($uid, $enemies);
        $isfriend = array_search($uid, $friends);
        if (false !== $isfriend) {
            $this->dropbuddy($uid, 1);
        }
        if (false !== $isenemy) {
            $e = 'This user is already blocked.';
        }
        if ($e) {
            $PAGE->JS('error', $e);
        } else {
            $enemies[] = $uid;
            $enemies = implode(',', $enemies);
            $USER['enemies'] = $enemies;
            $DB->safeupdate(
                'members',
                array(
                    'enemies' => $enemies,
                ),
                ' WHERE `id`=?',
                $USER['id']
            );
            $this->displaybuddylist();
        }
    }

    public function unblock($uid)
    {
        global $DB,$USER,$PAGE;
        if ($uid && is_numeric($uid)) {
            $enemies = explode(',', $USER['enemies']);
            $id = array_search($uid, $enemies);
            if (false === $id) {
                return;
            }
            unset($enemies[$id]);
            $enemies = implode(',', $enemies);
            $USER['enemies'] = $enemies;
            $DB->safeupdate(
                'members',
                array(
                    'enemies' => $enemies,
                ),
                ' WHERE `id`=?',
                $USER['id']
            );
        }
        $this->displaybuddylist();
    }

    public function dropbuddy($uid, $shh = 0)
    {
        global $DB,$USER,$PAGE;
        if ($uid && is_numeric($uid)) {
            $friends = explode(',', $USER['friends']);
            $id = array_search($uid, $friends);
            if (false === $id) {
                return;
            }
            unset($friends[$id]);
            $friends = implode(',', $friends);
            $USER['friends'] = $friends;
            $DB->safeupdate(
                'members',
                array(
                    'friends' => $friends,
                ),
                ' WHERE `id`=?',
                $USER['id']
            );
        }
        if (!$shh) {
            $this->displaybuddylist();
        }
    }

    public function setstatus($status)
    {
        global $DB,$USER,$PAGE;
        if ($USER && $USER['usertitle'] != $status) {
            $DB->safeupdate(
                'members',
                array(
                    'usertitle' => $status,
                ),
                'WHERE `id`=?',
                $USER['id']
            );
        }
    }
}
