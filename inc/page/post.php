<?php

//IMPORTANT TO DO: fix file uploading so that it checks permissions within the forum
//I've already hidden the attach files button, but it's possible for people to still upload

$PAGE->metadefs['post-preview'] = $PAGE->meta('box', '', 'Post Preview', '%s');

new POST();
class POST
{
    /* Redundant constructor unnecesary in newer PHP versions. */
    /* function POST(){
     $this->__construct();
    } */
    public function __construct()
    {
        global $JAX,$PAGE;
        $this->tid = $JAX->b['tid'];
        $this->fid = $JAX->b['fid'];
        $this->pid = $JAX->b['pid'];
        $this->how = $JAX->b['how'];

        $this->postdata = $JAX->p['postdata'] ? $JAX->p['postdata'] : null;
        if ($this->postdata) {
            //linkify stuff before sending it
            $this->postdata = str_replace("\t", '    ', $this->postdata);
            $codes = $JAX->startcodetags($this->postdata);
            $this->postdata = $JAX->linkify($this->postdata);
            $this->postdata = $JAX->finishcodetags($this->postdata, $codes, true);
            //poor forum martyr
            $this->postdata = str_replace('youtube]', 'video]', $this->postdata);
        }
        if ($JAX->b['uploadflash']) {
            die($this->uploadviaflash());
        }
        if ($_FILES['Filedata']['tmp_name']) {
            $JAX->p['postdata'] .= '[attachment]'.$this->upload($_FILES['Filedata']).'[/attachment]';
        }
        if ('Preview' == $JAX->p['submit'] || 'Full Reply' == $JAX->p['submit']) {
            $this->previewpost();
        } elseif (is_numeric($this->pid)) {
            $this->editpost();
        } elseif (isset($this->postdata)) {
            $this->submitpost();
        } elseif (is_numeric($this->fid) || is_numeric($this->tid) && 'edit' == $this->how) {
            $this->showtopicform();
        } elseif (is_numeric($this->tid)) {
            $this->showpostform();
        } else {
            $PAGE->location('?');
        }
    }

    public function uploadviaflash()
    {
        //it's fucking flash 10 already and filereference.upload doesn't send cookies
        //using SESSION to get id rather than $USER because no cookies are sent
        global $DB,$JAX;
        $result = $DB->safeselect('uid', 'session', 'WHERE id=?', $_GET['sessid']); /* This was almost certainly a security hole before. */
        $data = $DB->row($result);
        $DB->disposeresult($result);

        if (!$data['uid']) {
            return 'must be logged in';
        }
        $fileobj = $_FILES['Filedata'];
        //flash gets this wrong, this normalizes
        $fileobj['name'] = $JAX->b['Filename'];

        return $this->upload($fileobj, $data['uid']);
    }

    public function upload($fileobj, $uid = false)
    {
        global $CFG,$DB,$USER,$JAX;
        if (false === $uid) {
            $uid = $USER['id'];
        }
        if (false === $uid && !$USER) {
            return 'must be logged in';
        }
        $size = filesize($fileobj['tmp_name']);
        //if($size>$CFG['maxupload']) return "too big!";
        $hash = hash_file('sha512', $fileobj['tmp_name']);
        $uploadpath = BOARDPATH.'Uploads/';

        $ext = explode('.', $fileobj['name']);
        if (1 == count($ext)) {
            $ext = '';
        } else {
            $ext = strtolower(array_pop($ext));
        }
        if (!in_array($ext, $CFG['images'])) {
            $ext = '';
        }
        if ($ext) {
            $ext = '.'.$ext;
        }

        $file = $uploadpath.$hash.$ext;
        if (!is_file($file)) {
            move_uploaded_file($fileobj['tmp_name'], $file);
            $DB->safeinsert('files', array('hash' => $hash, 'name' => $fileobj['name'], 'uid' => $uid, 'size' => $size, 'ip' => $JAX->ip2int()));
            $id = $DB->insert_id(1);
        } else {
            $result = $DB->safeselect('id', 'files', 'WHERE hash=?', $hash);
            $thisrow = $DB->row($result);
            $id = array_pop($thisrow);
            $DB->disposeresult($result);
        }

        return (string) $id;
    }

    public function previewpost()
    {
        global $JAX,$PAGE;
        $post = $this->postdata;
        if (trim($post)) {
            $post = $JAX->theworks($post);
            $post = $PAGE->meta('post-preview', $post);
            $this->postpreview = $post;
        }
        if (!$PAGE->jsaccess || 'qreply' == $this->how) {
            $this->showpostform();
        } else {
            $PAGE->JS('update', 'post-preview', $post);
        }
    }

    public function showtopicform()
    {
        global $JAX,$PAGE,$DB,$PERMS,$USER;
        if ($PAGE->jsupdate) {
            return;
        }
        $postdata = $this->postdata;
        $page = '<div id="post-preview">'.$this->postpreview.'</div>';
        $fid = $this->fid;
        $fname = $f['title'];

        if ('edit' == $this->how) {
            $result = $DB->safeselect('*', 'topics', 'WHERE id=?', $DB->basicvalue($this->tid));
            $tdata = $DB->row($result);
            $DB->disposeresult($result);

            if (!$tdata) {
                $e = 'The topic you\'re trying to edit does not exist.';
            } else {
                $result = $DB->safeselect('post', 'posts', 'WHERE id=?', $DB->basicvalue($tdata['op']));
                $postdata = $DB->row($result);
                $DB->disposeresult($result);

                if ($postdata) {
                    $postdata = $postdata[0];
                }
            }
            $fid = $tdata['fid'];
        }

        $result = $DB->safeselect('title,perms', 'forums', 'WHERE id=?', $fid);
        $fdata = $DB->row($result);
        $DB->disposeresult($result);

        $fdata['perms'] = $JAX->parsePerms($fdata['perms'], $USER ? $USER['group_id'] : 3);

        if (!$fdata) {
            $e = 'This forum doesn\'t exist. Weird.';
        }

        if ($e) {
            $page .= $PAGE->meta('error', $e);
        } else {
            $form = '<form method="post" onsubmit="$(\'pdedit\').editor.submit();if(this.submitButton.value.match(/post/i)) this.submitButton.disabled=true;return RUN.submitForm(this,0,event);">
 <div class="topicform">
 <input type="hidden" name="act" value="post" />
 <input type="hidden" name="how" value="newtopic" />
 <input type="hidden" name="fid" value="'.$fid.'" />
  <label for="ttitle">Topic title:</label><input type="text" name="ttitle" id="ttitle" value="'.$tdata['title'].'" /><br />
  <label for="tdesc">Description:</label><input type="text" id="tdesc" name="tdesc" value="'.$tdata['subtitle'].'" /><br />
  <textarea name="postdata" id="postdata">'.$JAX->blockhtml($postdata).'</textarea>
  <iframe id="pdedit" onload="JAX.editor($(\'postdata\'),this)" style="display:none"></iframe><br /><div class="postoptions">
  '.($fdata['perms']['poll'] ? '<label class="addpoll" for="addpoll">Add a Poll</label> <select name="poll_type" onchange="$(\'polloptions\').style.display=this.value?\'block\':\'none\'"><option value="">No</option><option value="single">Yes, single-choice</option><option value="multi">Yes, multi-choice</option></select><br />
  <div id="polloptions" style="display:none">
   <label for="pollq">Poll Question:</label><input type="text" name="pollq" /><br />
   <label for="pollc">Poll Choices:</label> (one per line) <textarea id="pollc" name="pollchoices"></textarea></div>' : '').
  ($fdata['perms']['upload'] ? '<div id="attachfiles" class="addfile">Add Files <input type="file" name="Filedata" /></div>' : '').
  '<div class="buttons"><input type="submit" name="submit" value="Post New Topic" onclick="this.form.submitButton=this;" id="submitbutton" /> <input type="submit" name="submit" value="Preview" onclick="this.form.submitButton=this" /></div>
 </div>
</form>';
            $page .= $PAGE->meta('box', '', $fdata['title'].' > New Topic', $form);
        }
        $PAGE->append('page', $page);
        $PAGE->JS('update', 'page', $page);
        if (!$e) {
            if ($fdata['perms']['upload']) {
                $PAGE->JS('attachfiles');
            }
            $PAGE->JS('SCRIPT', "$('pollchoices').style.display='none'");
        }
    }

    public function showpostform()
    {
        global $PAGE,$JAX,$DB,$SESS,$USER;
        $tid = $this->tid;
        if ($PAGE->jsupdate && 'qreply' != $this->how) {
            return;
        }
        if ($USER && 'qreply' == $this->how) {
            $PAGE->JS('closewindow', '#qreply');
        }
        if ($tid) {
            $result = $DB->safespecial('SELECT t.title,f.perms FROM %t t LEFT JOIN %t f ON t.fid=f.id WHERE t.id=?', array('topics', 'forums'), $DB->basicvalue($tid));
            $tdata = $DB->row($result);
            $DB->disposeresult($result);
            if (!$tdata) {
                $page .= $PAGE->meta('error', "The topic you're attempting to reply in no longer exists.");
            }
            $tdata['title'] = $JAX->wordfilter($tdata['title']);
            $tdata['perms'] = $JAX->parseperms($tdata['perms'], $USER ? $USER['group_id'] : 3);
        }
        $page .= '<div id="post-preview">'.$this->postpreview.'</div>';
        $postdata = $JAX->blockhtml($this->postdata);
        $varsarray = array(
            'act' => 'post',
            'how' => 'fullpost',
        );
        if ($this->pid) {
            $varsarray['pid'] = $this->pid;
        } else {
            $varsarray['tid'] = $tid;
        }
        foreach ($varsarray as $k => $v) {
            $vars .= '<input type="hidden" name="'.$k.'" value="'.$v.'" />';
        }

        if ($SESS->vars['multiquote']) {
            $postdata = '';

            // $result = $DB->special("SELECT p.*,m.display_name name FROM %t p LEFT JOIN %t m ON p.auth_id=m.id WHERE p.id IN (".$SESS->vars['multiquote'].")","posts","members");

            $result = $DB->safespecial('SELECT p.*,m.display_name name FROM %t p LEFT JOIN %t m ON p.auth_id=m.id WHERE p.id IN ?',
    array('posts', 'members'),
    $SESS->vars['multiquote']);

            while ($f = $DB->row($result)) {
                $postdata .= '[quote='.$f['name'].']'.$f['post']."[/quote]\n\n";
            }
            $SESS->delvar('multiquote');
        }

        $form = '<div class="postform">
<form method="post" onsubmit="if(this.submitButton.value.match(/post/i)) this.submitButton.disabled=true;$(\'pdedit\').editor.submit();return RUN.submitForm(this,0,event);" enctype="multipart/form-data">
 '.$vars.'
  <textarea name="postdata" id="post">'.$postdata.'</textarea><iframe id="pdedit" onload="JAX.editor($(\'post\'),this)" style="display:none"></iframe><br />'.
  ($tdata['perms']['upload'] ? '<div id="attachfiles">Add Files <input type="file" name="Filedata" /></div>' : '').
  '<div class="buttons"><input type="submit" name="submit" value="Post" onclick="this.form.submitButton=this" id="submitbutton"/><input type="submit" name="submit" value="Preview" onclick="this.form.submitButton=this"/></div>
</form></div>';
        $page .= $PAGE->meta('box', '', $tdata['title'].' &gt; Reply', $form);
        $PAGE->append('page', $page);
        $PAGE->JS('update', 'page', $page);
        if ($tdata['perms']['upload']) {
            $PAGE->JS('attachfiles');
        }
    }

    public function canedit($post)
    {
        global $PERMS,$USER;

        return ($post['auth_id'] && ($post['newtopic'] ? $PERMS['can_edit_topics'] : $PERMS['can_edit_posts']) && $post['auth_id'] == $USER['id']) || $this->canmoderate($post['tid']);
    }

    public function canmoderate($tid)
    {
        global $PAGE,$PERMS,$USER,$DB;
        if ($this->canmod) {
            return $this->canmod;
        }
        $canmod = false;
        if ($PERMS['can_moderate']) {
            $canmod = true;
        }
        if ($USER['mod']) {
            $result = $DB->safespecial('SELECT mods FROM %t WHERE id=(SELECT fid FROM %t WHERE id=?)',
    array('forums', 'topics'),
    $DB->basicvalue($tid));
            $mods = $DB->row($result);
            $DB->disposeresult($result);
            if (in_array($USER['id'], explode(',', $mods['mods']))) {
                $canmod = true;
            }
        }

        return $this->canmod = $canmod;
    }

    public function editpost()
    {
        global $DB,$JAX,$PAGE,$USER,$PERMS;
        $pid = $this->pid;
        $tid = $this->tid;
        if (!$pid || !is_numeric($pid)) {
            $e = 'Stop playing with the variables!';
        }
        if (isset($this->postdata)) {
            if ('' === trim($this->postdata)) {
                $e = "You didn't supply a post!";
            } elseif (strlen($this->postdata) > 50000) {
                $e = 'Post must not exceed 50,000 characters.';
            }
        }
        if (!$e) {
            $result = $DB->safeselect('*', 'posts', 'WHERE id=?', $pid);
            $tmp = $DB->row($result);
            $DB->disposeresult($result);

            if (!$tmp) {
                $e = 'The post you are trying to edit does not exist.';
            } elseif (!$this->canedit($tmp)) {
                $e = "You don't have permission to edit that post!";
            } elseif (!isset($this->postdata)) {
                $editpost = true;
                $this->postdata = $tmp['post'];
            }
        }
        if ($tid && !$e) {
            if (!is_numeric($tid) || !$tid) {
                $e = 'Stop playing with the variables!';
            } else {
                $result = $DB->safeselect('*', 'topics', 'WHERE id=?', $tid);
                $tmp = $DB->row($result);
                $DB->disposeresult($result);

                if (!$tmp) {
                    $e = "The topic you are trying to edit doesn't exist.";
                } elseif ('' === trim($JAX->p['ttitle'])) {
                    $e = 'You must supply a topic title!';
                } else {
                    $DB->safeupdate('topics',
     array(
         'title' => $JAX->blockhtml($JAX->p['ttitle']),
         'subtitle' => $JAX->blockhtml($JAX->p['tdesc']),
         'summary' => substr(
           preg_replace('@\\s+@',' ',
            $JAX->wordfilter(
            $JAX->blockhtml(
             $JAX->textonly(
              $this->postdata
             )
            )
           )), 0, 50),
     ), 'WHERE id=?', $tid);
                }
            }
        }
        if ($e) {
            $PAGE->JS('error', $e);
            $PAGE->append('PAGE', $PAGE->error($e));
        }
        if ($e || $editpost) {
            $this->showpostform();

            return false;
        }
        $DB->safeupdate('posts', array('post' => $this->postdata, 'editdate' => time(), 'editby' => $USER['id']), 'WHERE id=?', $DB->basicvalue($pid));
        $PAGE->JS('update', "#pid_${pid} .post_content", $JAX->theworks($this->postdata));
        $PAGE->JS('softurl');
    }

    public function submitpost()
    {
        global $JAX,$PAGE,$DB,$SESS,$USER,$PERMS;
        $SESS->act();
        $tid = $this->tid;
        $fid = $this->fid;
        $postdata = $this->postdata;
        $fdata = false;
        $newtopic = false;
        $time = time();
        $uid = $USER['id'];
        $uname = $USER['name'];

        if ('' === trim($postdata)) {
            $e = "You didn't supply a post!";
        } elseif (strlen($postdata) > 50000) {
            $e = 'Post must not exceed 50,000 characters.';
        }

        if (!$e && 'newtopic' == $this->how) {
            if (!$fid || !is_numeric($fid)) {
                $e = 'No forum specified exists.';
            } elseif ('' === trim($JAX->p['ttitle'])) {
                $e = "You didn't specify a topic title!";
            } elseif (strlen($JAX->p['ttitle']) > 255) {
                $e = 'Topic title must not exceed 255 characters';
            } elseif (strlen($JAX->p['subtitle']) > 255) {
                $e = 'Subtitle must not exceed 255 characters';
            } elseif ($JAX->p['poll_type']) {
                $pollchoices = array();
                foreach (preg_split("@[\r\n]+@", $JAX->p['pollchoices']) as $k => $v) {
                    if (trim($v)) {
                        $pollchoices[] = $JAX->blockhtml($v);
                    }
                }
                if ('' === trim($JAX->p['pollq'])) {
                    $e = "You didn't specify a poll question!";
                } elseif (count($pollchoices) > 10) {
                    $e = 'Poll choices must not exceed 10.';
                } elseif (empty($pollchoices)) {
                    $e = "You didn't provide any poll choices!";
                }
            }
            //perms
            $result = $DB->safeselect('perms', 'forums', 'WHERE id=?', $fid);
            $fdata = $DB->row($result);
            $DB->disposeresult($result);

            if (!$fdata) {
                $e = "The forum you're trying to post in does not exist.";
            } else {
                $fdata['perms'] = $JAX->parseperms($fdata['perms'], $USER ? $USER['group_id'] : 3);
                if (!$fdata['perms']['start']) {
                    $e = "You don't have permission to post a new topic in that forum.";
                }
                if (($JAX->p['poll_type'] || $JAX->p['pollq']) && !$fdata['perms']['poll']) {
                    $e = "You don't have permission to post a poll in that forum";
                }
            }

            if (!$e) {
                $DB->safeinsert('topics', array(
                    'title' => $JAX->blockhtml($JAX->p['ttitle']),
                    'subtitle' => $JAX->blockhtml($JAX->p['tdesc']),
                    'date' => $time,
                    'lp_uid' => $uid,
                    'lp_date' => $time,
                    'fid' => $fid,
                    'auth_id' => $uid,
                    'replies' => 0,
                    'views' => 0,
                    'poll_type' => $JAX->p['poll_type'],
                    'poll_q' => $JAX->blockhtml($JAX->p['pollq']),
                    'poll_choices' => $pollchoices ? $JAX->json_encode($pollchoices) : '',
                    'summary' => substr(
                        preg_replace('@\\s+@', ' ',
                         $JAX->blockhtml(
                          $JAX->textonly(
                           $this->postdata
                          )
                         )
                        ), 0, 50),
                ));
                $tid = $DB->insert_id(1);
            }
            $newtopic = true;
        }

        if ($e) {
            $PAGE->append('PAGE', $PAGE->error($e));
            $PAGE->JS('error', $e);
            $PAGE->JS('enable', 'submitbutton');
            if ('newtopic' == $this->how) {
                $this->showtopicform();
            } else {
                $this->showpostform();
            }

            return;
        }

        if ($tid && is_numeric($tid)) {
            $result = $DB->safespecial('SELECT t.title topictitle,f.id,f.path,f.perms,f.nocount,t.locked FROM %t AS t LEFT JOIN %t AS f ON t.fid=f.id WHERE t.id=?',
    array('topics', 'forums'), $tid);
            $fdata = $DB->arow($result);
            $DB->disposeresult($result);
        }
        if (!$fdata) {
            $e = "The topic you're trying to reply to does not exist.";
            $PAGE->append('PAGE', $PAGE->error($e));
            $PAGE->JS('error', $e);

            return false;
        }
        $fdata['perms'] = $JAX->parseperms($fdata['perms'], $USER ? $USER['group_id'] : 3);
        if (!$fdata['perms']['reply'] || $fdata['locked'] && !$PERMS['can_override_locked_topics']) {
            $e = "You don't have permission to post here.";
            $PAGE->append('PAGE', $PAGE->error($e));
            $PAGE->JS('error', $e);

            return false;
        }

        //Actually PUT THE POST IN for godsakes
        $DB->safeinsert('posts', array(
            'auth_id' => $uid,
            'post' => $postdata,
            'date' => $time,
            'tid' => $tid,
            'newtopic' => $newtopic ? 1 : 0,
            'ip' => $JAX->ip2int(),
        ));

        $pid = $DB->insert_id(1);
        //set op
        if ($newtopic) {
            $DB->safeupdate('topics', array('op' => $pid), 'WHERE id=?', $tid);
        }

        //update activity history
        $DB->safeinsert('activity', array(
            'uid' => $uid,
            'type' => $newtopic ? 'new_topic' : 'new_post',
            'tid' => $tid,
            'pid' => $pid,
            'arg1' => $fdata['topictitle'],
            'date' => time(),
        ));

        //update last post info
        //for the topic:
        if (!$newtopic) {
            $DB->safequery(
    'UPDATE '.$DB->ftable('topics').' set lp_uid = ?, lp_date = ?, replies = replies + 1 WHERE id=?',
    $uid, $time, $tid);
        }

        //do some magic to update the tree all the way up (for subforums)
        $path = trim($fdata['path']) ? explode(' ', $fdata['path']) : array();
        if (!in_array($fdata['id'], $path)) {
            $path[] = $fdata['id'];
        }

        // $DB->update("forums",Array(
        // 'lp_uid'=>$uid,
        // 'lp_tid'=>$tid,
        // 'lp_topic'=>$fdata['topictitle'],
        // 'lp_date'=>$time,
        // )+($newtopic?Array(
        // 'topics'=>Array('topics+1')
        // ):Array(
        // 'posts'=>Array('posts+1')
        // ))
        // ,"WHERE id IN (".implode(",",$path).")");

        // syslog(LOG_ERR,"UPDATE: PATH IS ".implode(",", $path)."\n");
        // syslog(LOG_ERR,"UPDATE: PATH IS ".print_r($path, true)."\n");

        if ($newtopic) {
            $DB->safequery('UPDATE '.$DB->ftable('forums').' SET lp_uid= ?, lp_tid = ?, lp_topic = ?, lp_date = ?, topics = topics + 1 WHERE id IN ?',
        $uid,
        $tid,
        $fdata['topictitle'],
        $time,
        $path);
        } else {
            $DB->safequery('UPDATE '.$DB->ftable('forums').' SET lp_uid= ?, lp_tid = ?, lp_topic = ?, lp_date = ?, posts = posts + 1 WHERE id IN ?',
        $uid,
        $tid,
        $fdata['topictitle'],
        $time,
        $path);
        }

        //$PAGE->JS("alert",print_r($DB->queryList,1));

        //update statistics
        if (!$fdata['nocount']) {
            $DB->safequery('UPDATE '.$DB->ftable('members').' SET posts = posts + 1 WHERE id=?', $DB->basicvalue($JAX->userData['id']));
        }

        if ($newtopic) {
            $DB->safequery('UPDATE '.$DB->ftable('stats').' SET posts = posts + 1, topics = topics + 1;');
        } else {
            $DB->safequery('UPDATE '.$DB->ftable('stats').' set posts = posts + 1;');
        }

        if ('qreply' != $this->how) {
            $PAGE->location('?act=vt'.$tid.'&getlast=1');
        } else {
            $PAGE->JS('closewindow', '#qreply');
            $PAGE->JS('script', 'RUN.stream.donext(1)');
        }
    }
}
