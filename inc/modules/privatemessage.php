<?php

new IM();
class IM
{
    /* Redundant constructor unnecesary in newer PHP versions. */
    /* function IM(){$this->__construct();} */
    public function __construct()
    {
        global $JAX,$DB,$PAGE,$SESS;
        $im = $JAX->p['im_im'];
        $uid = $JAX->p['im_uid'];
        if ($SESS->runonce) {
            $this->filter();
        }
        if ('' !== trim($im) && $uid) {
            $this->message($uid, $im);
        }

        if ($JAX->b['im_menu']) {
            $this->immenu($JAX->b['im_menu']);
        }
    }

    public function filter()
    {
        global $SESS,$USER,$PAGE;
        if (!$USER['enemies']) {
            return;
        }
        $enemies = explode(',', $USER['enemies']);
        //kinda gross I know, unparses then parses then unparses again later on.. o well
        $exploded = explode("\n", $SESS->runonce);
        foreach ($exploded as $k => $v) {
            $v = json_decode($v);
            if ('im' == $v[0]) {
                unset($exploded[$k]);
                if (in_array($v[1], $enemies)) {
                    //this user's blocked, don't do anything
                } else {
                    //send it on up
                    $PAGE->JSRawArray($v);
                }
            }
        }
        $SESS->runonce = implode("\n", $exploded);
    }

    public function message($uid, $im)
    {
        global $DB,$JAX,$PAGE,$SESS,$CFG,$USER,$PERMS;
        $SESS->act();
        $ud = $USER;
        if (false && in_array($uid, explode(',', $USER['enemies']))) {
            return $PAGE->JS('error', "You've blocked this recipient and cannot send messages to them.");
        }
        if (!$ud) {
            return $PAGE->JS('error', 'You must be logged in to instant message!');
        }
        if (!$uid) {
            return $PAGE->JS('error', 'You must have a recipient!');
        }
        if (!$PERMS['can_im']) {
            return $PAGE->JS('error', "You don't have permission to use this feature.");
        }
        $im = $JAX->linkify($im);
        $im = $JAX->theworks($im);
        $cmd = array('im', $uid, $ud['display_name'], $im, $USER['id'], $JAX->smalldate(time(), 1));
        $PAGE->JSRawArray($cmd);
        $cmd[1] = $ud['id'];
        $cmd[4] = 0;
        $onlineusers = $DB->getUsersOnline();
        if (!$onlineusers[$uid] || ($onlineusers[$uid]['last_update'] < (time() - $CFG['timetologout']))) {
            $PAGE->JS('imtoggleoffline', $uid);
        //$fatal=true;
        } elseif ($onlineusers[$uid]['last_update'] < (time() - $CFG['updateinterval'] * 5)) {
            $PAGE->JS('imtoggleoffline', $uid);
        }
        if (!$fatal) {
            if (!$this->sendcmd($cmd, $uid)) {
                $PAGE->JS('imtoggleoffline', $uid);
            }
        }

        return !($e || $fatal);
    }

    public function sendcmd($cmd, $uid)
    {
        global $DB,$CFG;
        if (!is_numeric($uid)) {
            return;
        }
        /* $DB->special("UPDATE %t SET runonce=concat(runonce,%s) WHERE uid=".$uid." AND last_update>".(time()-$CFG['updateinterval']*5),"session",$DB->evalue(json_encode($cmd)."\n")); */
        $result = $DB->safespecial('UPDATE %t SET runonce=concat(runonce,?) WHERE uid=? AND last_update> ?;',
    array('session'),
    $DB->basicvalue(json_encode($cmd)."\n"),
    $uid,
    (time() - $CFG['updateinterval'] * 5));

        return 0 != $DB->affected_rows(1);
    }

    //stuff I'm doin
    public function invite($room, $uid, $otherguy = false)
    {
        global $USER,$CFG,$DB;
        if (!$USER['id']) {
            return;
        }
        if ($otherguy) {
            $room = md5(uniqid(true, rand(0, 1000)));
            //make the window the guy that invited multi
            $PAGE->JS('immakemulti', $otherguy);
            //update other guy
            $this->sendcmd(array('immakemulti', $USER['id']), $otherguy);
        }
        $this->sendcmd(array('iminvite', $room));
    }

    public function immenu($id)
    {
        global $PAGE,$JAX,$USER,$DB;
        if ($JAX->b['im_invitemenu']) {
            $online = $DB->getUsersOnline();
            $result = $DB->safeselect('id,display_name name','members','WHERE id IN ? ORDER BY name ASC',
    explode(',', $USER['friends']));
            $menu = '';
            while ($f = $DB->row($result)) {
                if ($online[$f['id']] && $f['id'] != $id) {
                    $menu .= $f['name'].'<br />';
                }
            }
            if (!$menu) {
                if (!$USER['friends']) {
                    $menu = 'You must add users to your contacts list<br />to use this feature.';
                } else {
                    $menu = 'None of your friends<br />are currently online';
                }
            }
        } else {
            $menu = "<a href='?act=vu${id}'>View Profile</a><br /><a href='?module=privatemessage&im_menu=${id}&im_invitemenu=1'>Add User to Chat</a>";
        }
        $PAGE->JS('update', 'immenu', $menu);
        $PAGE->JS('softurl');
    }
}
