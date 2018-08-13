<?php
$PAGE->loadmeta("topic");

//Topics module
$IDX=new TOPIC;
class TOPIC{
 /* Redundant constructor unnecesary in newer PHP versions. */
 /* function TOPIC(){ $this->__construct(); } */
 function __construct(){
  global $JAX,$PAGE;

  preg_match('@\d+$@',$JAX->b['act'],$act);

  $this->id=$id=$act[0]?$act[0]:0;

  $this->page=$JAX->b['page'];
  if($this->page<=0||!is_numeric($this->page)) $this->page=1;
  $this->page--;

  $this->numperpage=10;
  if($JAX->b['qreply']&&!$PAGE->jsupdate) {
   if($PAGE->jsaccess&&!$PAGE->jsdirectlink)$this->qreplyform($id);
   else                                    $PAGE->location("?act=post&tid=".$id);
  }
  elseif($JAX->b['ratepost'])              $this->ratepost($JAX->b['ratepost'],$JAX->b['niblet']);
  elseif($JAX->b['votepoll'])              $this->votepoll($id);
  elseif($JAX->b['findpost'])              $this->findpost($JAX->b['findpost']);
  elseif($JAX->b['getlast'])               $this->getlastpost($id);
  elseif($JAX->b['edit'])                  $this->qeditpost($JAX->b['edit']) ;
  elseif($JAX->b['quote'])                 $this->multiquote($id);
  elseif($JAX->b['markread'])              $this->markread($id);
  elseif($JAX->b['listrating'])            $this->listrating($JAX->b['listrating']);
  elseif($PAGE->jsupdate)                  $this->update($id)   ;
  else                                     $this->viewtopic($id);
 }
 function viewtopic($id){
  global $DB,$PAGE,$JAX,$SESS,$USER,$PERMS;
  $page=$this->page;
  if(!$id) return $PAGE->location("?");
  $result = $DB->safespecial("SELECT a.title topic_title,a.locked,a.lp_date,b.title forum_title,b.perms fperms,c.id cat_id,c.title cat_title,a.fid,a.poll_q,a.poll_type,a.poll_choices,a.poll_results,a.subtitle FROM %t AS a LEFT JOIN (%t AS b) ON a.fid=b.id LEFT JOIN (%t AS c) ON b.cat_id=c.id WHERE a.id=$id LIMIT 1",
	array("topics","forums","categories"));
  $topicdata=$DB->arow($result);
  $DB->disposeresult($result);

  if(!$topicdata) {
   return $PAGE->location("?"); //put them back on the index, skip these next few lines
  }
  $topicdata['topic_title']=$JAX->wordfilter($topicdata['topic_title']);
  $topicdata['subtitle']=$JAX->wordfilter($topicdata['subtitle']);
  $topicdata['fperms']=$JAX->parseperms($topicdata['fperms'],$USER?$USER['group_id']:3);
  if($topicdata['lp_date']>$USER['last_visit']) $this->markread($id);
  if(!$topicdata['fperms']['read']) return $PAGE->location("?"); //no business being here yo

  $PAGE->append("TITLE"," -> ".$topicdata['topic_title']);
  $SESS->location_verbose="In topic '".$topicdata['topic_title']."'";

  /*Output RSS instead*/
  if($JAX->b['fmt']=="RSS") {
   require_once("inc/classes/rssfeed.php");
   $link="https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
   $feed=new rssfeed(Array("title"=>$topicdata['topic_title'],"description"=>$topicdata['subtitle'],"link"=>$link."?act=vt".$id));
   $result = $DB->safespecial("SELECT p.id,p.post,p.date,m.id,m.display_name FROM %t p LEFT JOIN %t m ON p.auth_id=m.id WHERE p.tid=?",
	array("posts","members"),
	$DB->basicvalue($id));
   echo $DB->error(1);
   while($f=$DB->row($result)) $feed->additem(Array('title'=>$f['display_name'].':','link'=>$link.'?act=vt'.$id.'&amp;findpost='.$f['id'],'description'=>$JAX->blockhtml($JAX->theworks($f['post'])),'guid'=>$f['id'],'pubDate'=>date('r',$f['date'])));
   $feed->publish();
   die();
  }


  //fix this to work with subforums
  $PAGE->path(Array($topicdata['cat_title']=>"?act=vc".$topicdata['cat_id'],$topicdata['forum_title']=>"?act=vf".$topicdata['fid'],$topicdata['topic_title']=>"?act=vt$id"));

  /*generate pages*/
  $result = $DB->safeselect("count(*)","posts","WHERE tid=?", $id);
  $thisrow = $DB->row($result);
  $posts=array_pop($thisrow);
  $DB->disposeresult($result);

  $totalpages=ceil($posts/$this->numperpage);
  $pagelist='';
  foreach($JAX->pages($totalpages,$this->page+1,10) as $x) $pagelist.=$PAGE->meta("topic-pages-part",$id,$x,($x==($this->page+1)?' class="active"':''),$x);

  //are they on the last page? stores a session variable
  $SESS->addvar('topic_lastpage',($page+1)==$totalpages);

  //if it's a poll, put it in
  if($topicdata['poll_type']) $pollshit=$PAGE->meta("box"," id='poll'",$topicdata['poll_q'],$this->generatepoll($topicdata['poll_q'],$topicdata['poll_type'],$JAX->json_decode($topicdata['poll_choices']),$topicdata['poll_results']));

  //generate post listing
  $page=$PAGE->meta("topic-table",$this->postsintooutput());
  $page=$PAGE->meta("topic-wrapper",$topicdata['topic_title'].($topicdata['subtitle']?', '.$topicdata['subtitle']:""),$page,'<a href="./?act=vt'.$id.'&amp;fmt=RSS" class="social rss">RSS</a>');

  //add buttons
  $buttons=Array(
   $topicdata['fperms']['start']?"<a href='?act=post&fid=".$topicdata['fid']."'>".($PAGE->meta($PAGE->metaexists('button-newtopic')?'button-newtopic':'topic-button-newtopic'))."</a>":'&nbsp;',
   $topicdata['fperms']['reply']&&(!$topicdata['locked']||$PERMS['can_override_locked_topics'])?"<a href='?act=vt$id&qreply=1'>".($PAGE->meta($PAGE->metaexists('button-qreply')?'button-qreply':'topic-button-qreply')):'',
   $topicdata['fperms']['reply']&&(!$topicdata['locked']||$PERMS['can_override_locked_topics'])?"<a href='?act=post&tid=$id'>".($PAGE->meta($PAGE->metaexists('button-reply')?'button-reply':'topic-button-reply'))."</a>":''
  );

  //make the users online list
  foreach($DB->getUsersOnline() as $f)
   if($f['uid']&&$f['location']=="vt$id") {
    $usersonline.=$f['is_bot']?'<a class="user'.$f['uid'].'">'.$f['name'].'</a>':$PAGE->meta('user-link',$f['uid'],$f['group_id'].($f['status']=="idle"?" idle":""),$f['name']);
   }
  $page.=$PAGE->meta('topic-users-online',$usersonline);

  //add in other page elements shiz
  $page=$pollshit.$PAGE->meta('topic-pages-top',$pagelist).$PAGE->meta('topic-buttons-top',$buttons).$page.$PAGE->meta('topic-pages-bottom',$pagelist).$PAGE->meta('topic-buttons-bottom',$buttons);

  //update view count
  $DB->safequery("UPDATE ".$DB->ftable('topics')." SET views = views + 1 WHERE id=?", $id);

  if($PAGE->jsaccess) {
   $PAGE->JS("update","page",$page);
   $PAGE->updatepath();
   if($JAX->b['pid']) $PAGE->JS("scrollToPost",$JAX->b['pid']);
   else if($JAX->b['page']) $PAGE->JS("scrollToPost",$this->firstPostID,1);
  }
  else $PAGE->append("page",$page);
 }

 function update($id){
  global $SESS,$PAGE,$DB,$JAX;

  /*check for new posts and append them*/
  if($SESS->location!="vt$id") $SESS->delvar('topic_lastpid');

  if(is_numeric($SESS->vars['topic_lastpid'])&&$SESS->vars['topic_lastpage']) {
   $crap=$this->postsintooutput($SESS->vars['topic_lastpid']);
   if($crap){$PAGE->JS("appendrows","#intopic",$crap);}
  }

  /*update users online list*/
  $list=Array();
  $oldcache=array_flip(explode(",",$SESS->users_online_cache));
  $newcache="";
  foreach($DB->getUsersOnline() as $f) {
   if($f['uid']&&$f['location']=="vt$id") {
    if(!isset($oldcache[$f['uid']])) {
     $list[]=Array($f['uid'],$f['group_id'],($f['status']!="active"?$f['status']:''),$f['name']);
    } else unset($oldcache[$f['uid']]);
    $newcache.=$f['uid'].",";
   }
  }
  if(!empty($list)) $PAGE->JS("onlinelist",$list);
  $oldcache=implode(",",array_flip($oldcache));
  $newcache=substr($newcache,0,-1);
  if($oldcache) $PAGE->JS("setoffline",$oldcache);
  $SESS->users_online_cache=$newcache;
 }

 function qreplyform($id){
  global $PAGE,$SESS,$DB,$JAX;
  $prefilled="";
  $PAGE->JS("softurl");
  if($SESS->vars['multiquote']) {
   $result = $DB->safespecial("SELECT p.*,m.display_name name FROM %t p LEFT JOIN %t m ON p.auth_id=m.id WHERE p.id IN ?;",
	array("posts","members"),
	explode(",", $SESS->vars['multiquote']));

   while($f=$DB->row($result)) $prefilled.='[quote='.$f['name'].']'.$f['post']."[/quote]\n\n";
   $SESS->delvar('multiquote');
  }
  $result = $DB->safeselect("title","topics","WHERE id=?", $id);
  $tdata=$DB->row($result);
  $DB->disposeresult($result);

  $PAGE->JS("window",Array("id"=>"qreply","title"=>$JAX->wordfilter($tdata['title']),"content"=>$PAGE->meta("topic-reply-form",$id,$JAX->blockhtml($prefilled)),"resize"=>"textarea"));
  $PAGE->JS("updateqreply",'');
 }

 function postsintooutput($lastpid=0){
  global $DB,$PAGE,$JAX,$SESS,$USER,$PERMS,$CFG;
  $usersonline=$DB->getUsersOnline();

  if ($lastpid) {
      $query=$DB->safespecial("SELECT
          m.*,
          p.tid,p.id AS pid,p.ip,p.newtopic,p.post,p.showsig,p.showemotes,p.tid,p.date,p.auth_id,p.rating,
          g.title,g.icon,
          p.editdate,p.editby,e.display_name ename,e.group_id egroup_id
          FROM %t AS p LEFT JOIN %t AS m ON p.auth_id=m.id LEFT JOIN %t AS g ON m.group_id=g.id LEFT JOIN %t AS e ON p.editby=e.id WHERE p.tid=?
          AND p.id>? ORDER BY pid",
	array("posts","members","member_groups","members"),
        $this->id,
        $lastpid);

  } else {

      $query=$DB->safespecial("SELECT
          m.*,
          p.tid,p.id AS pid,p.ip,p.newtopic,p.post,p.showsig,p.showemotes,p.tid,p.date,p.auth_id,p.rating,
          g.title,g.icon,
          p.editdate,p.editby,e.display_name ename,e.group_id egroup_id
          FROM %t AS p LEFT JOIN %t AS m ON p.auth_id=m.id LEFT JOIN %t AS g ON m.group_id=g.id LEFT JOIN %t AS e ON p.editby=e.id WHERE p.tid=?
          ORDER BY newtopic DESC, pid ASC LIMIT ?,?",
	    array("posts","members","member_groups","members"),
	    $this->id,
	    (($topic_post_counter=($this->page)*$this->numperpage)),
	    $this->numperpage);
  }

  $rows='';
  while($post=$DB->arow($query)) {
   if(!$this->firstPostID) $this->firstPostID=$post['pid'];
   $postt=$post['post'];

   $postt=$JAX->theworks($postt);

   //post rating
   if($CFG['ratings']&1) {
       $postrating=$showrating='';
       $prating=Array();
       if($post['rating']) $prating=json_decode($post['rating'],true);
       $rniblets=$DB->getRatingNiblets();
       if($rniblets) {
        foreach($rniblets as $k=>$v) {
         $postrating.='<a href="?act=topic&amp;ratepost='.$post['pid'].'&amp;niblet='.$k.'">'.$PAGE->meta('rating-niblet',$v['img'],$v['title']).'</a>';
         if($prating[$k]) {
          $num='x'.count($prating[$k]);
          $postrating.=$num;
          $showrating.=$PAGE->meta('rating-niblet',$v['img'],$v['title']).$num;
         }
        }
        $postrating=$PAGE->meta('rating-wrapper',
            $postrating,
            (!($CFG['ratings']&2)?'<a href="?act=vt'.$this->id.'&amp;listrating='.$post['pid'].'">(List)</a>':''),
            $showrating
            );
       }
   }

   $rows.=$PAGE->meta("topic-post-row",
     $post['pid'],
     $this->id,
     $post['auth_id']?$PAGE->meta('user-link',$post['auth_id'],$post['group_id'],$post['display_name']):"Guest",
     $JAX->pick($post['avatar'],$PAGE->meta('default-avatar')),
     $post['usertitle'],
     $post['posts'],
     $PAGE->meta('topic-status-'.($usersonline[$post['auth_id']]?"online":"offline")),
     $post['title'],
     $post['auth_id'],
     ($this->canedit($post)?"<a href='?act=vt".$this->id."&amp;edit=".$post['pid']."' class='edit'>".$PAGE->meta('topic-edit-button')."</a>":"")." <a href='?act=vt".$this->id."&amp;quote=".$post['pid']."' onclick='RUN.handleQuoting(this);return false;' class='quotepost'>".$PAGE->meta('topic-quote-button')."</a> ".($this->canmoderate()?"<a href='?act=modcontrols&amp;do=modp&amp;pid=".$post['pid']."' class='modpost' onclick='RUN.modcontrols.togbutton(this)'>".$PAGE->meta('topic-mod-button')."</a>":''),
     $JAX->date($post['date']),
     '<a href="?act=vt'.$this->id.'&amp;findpost='.$post['pid'].'" onclick="prompt(\'Link to this post:\',this.href)">'.$PAGE->meta('topic-perma-button').'</a>',
     $postt,
     $post['sig']?$JAX->theworks($post['sig']):'',
	 $post['auth_id'],
     $post['editdate']?$PAGE->meta('topic-edit-by',$PAGE->meta('user-link',$post['editby'],$post['egroup_id'],$post['ename']),$JAX->date($post['editdate'])):'',
     $PERMS['can_moderate']?'<a href="?act=modcontrols&amp;do=iptools&amp;ip='.$post['ip'].'">'.$PAGE->meta('topic-mod-ipbutton',long2ip($post['ip'])).'</a>':'',
     $post['icon']?$PAGE->meta('topic-icon-wrapper',$post['icon']):'',
     ++$topic_post_counter,
     $post['contact_skype'],
     $post['contact_yim'],
     $post['contact_msn'],
     $post['contact_gtalk'],
     $post['contact_aim'],
     $post['contact_twitter'],
     $post['contact_steam'],
     '',
     '',
     '',
     $postrating
   );
   $lastpid=$post['pid'];
  }
  $this->lastPostID=$lastpid;
  $SESS->addvar('topic_lastpid',$lastpid);
  return $rows;
 }

 function canedit($post){
  global $PERMS,$USER;
  return $this->canmoderate()||($post['auth_id']&&($post['newtopic']?$PERMS['can_edit_topics']:$PERMS['can_edit_posts'])&&$post['auth_id']==$USER['id']);
 }

 function canmoderate(){
  global $PAGE,$PERMS,$USER,$DB;
  if($this->canmod) return $this->canmod;
  $canmod=false;
  if($PERMS['can_moderate']) $canmod=true;
  if($USER['mod']){
   $result = $DB->safespecial('SELECT mods FROM %t WHERE id=(SELECT fid FROM %t WHERE id=?)',
	array('forums','topics'),
	$DB->basicvalue($this->id));
   $mods=$DB->row($result);
   $DB->disposeresult($result);
   if(in_array($USER['id'],explode(',',$mods['mods']))) $canmod=true;
  }
  return $this->canmod=$canmod;
 }

 function generatepoll($q,$type,$choices,$results){
  if(!$choices) $choices=Array();
  global $PAGE,$USER,$JAX;
  if($USER){
   //accomplish three things at once:
   //-determine if the user has voted
   //-count up the number of votes
   //-parse the result set
   $presults=Array();
   $voted=false;
   $totalvotes=0;
   $usersvoted=Array();
   $numvotes=Array();
   foreach(explode(";",$results) as $k=>$v) {
    $presults[$k]=$v?explode(",",$v):Array();
    $totalvotes+=($numvotes[$k]=count($presults[$k]));
    if(in_array($USER['id'],$presults[$k])) $voted=true;
    foreach($presults[$k] as $user) $usersvoted[$user]=1;
   }
  }
  $usersvoted=count($usersvoted);
  if($voted){
   $page.="<table>";
   foreach($choices as $k=>$v) $page.="<tr><td>$v</td><td class='numvotes'>".$numvotes[$k]." votes (".round($numvotes[$k]/$totalvotes*100,2)."%)</td><td style='width:200px'><div class='bar' style='width:".round($numvotes[$k]/$totalvotes*100)."%;'></div></td></tr>";
   $page.="<tr><td colspan='3' class='totalvotes'>Total Votes: ".$usersvoted."</td></tr>";
   $page.="</table>";
  } else {
   $page="<form method='post' action='?' onsubmit='return RUN.submitForm(this)'>".$JAX->hiddenFormFields(Array("act"=>"vt".$this->id,"votepoll"=>1));
   if($type=='multi') {
    foreach($choices as $k=>$v) {$page.="<div class='choice'><input type='checkbox' name='choice[]' value='$k' id='poll_$k' /> <label for='poll_$k'>$v</label></div>";}
   } else {
    foreach($choices as $k=>$v) {$page.="<div class='choice'><input type='radio' name='choice' value='$k' id='poll_$k' /> <label for='poll_$k'>$v</label></div>";}
   }
   $page.="<div class='buttons'><input type='submit' value='Vote'></div></form>";
  }
  return $page;
 }

 function votepoll($tid){
  global $DB,$PAGE,$USER,$JAX;

  if(!$USER) $e='You must be logged in to vote!';
  else {
   $result = $DB->safeselect("poll_q,poll_results,poll_choices,poll_type","topics","WHERE id=?", $this->id);
   $row=$DB->row($result);
   $DB->disposeresult($result);

   $choice=$JAX->b['choice'];
   $choices=$JAX->json_decode($row['poll_choices']);
   $numchoices=count($choices);
   $results=$row['poll_results'];
   if($results) {
    $results=explode(';',$results);
    foreach($results as $k=>$v) $results[$k]=$v?explode(',',$v):Array();
   } else {
    $results=Array();
   }

   //results is now an array of arrays, the keys of the parent array correspond to the choices while the arrays within the array correspond
   //to a collection of user IDs that have voted for that choice
   $voted=false;
   foreach($results as $v) {
       foreach($v as $v2) {
           if($v2==$USER['id']) {
               $voted=true;
               break;
           }
       }
   }

   if($voted) $e="You have already voted on this poll!";

   if($row['poll_type']=="multi") {
    if(is_array($choice)) {foreach($choice as $c) if(!is_numeric($c)||$c>=$numchoices||$c<0) $e="Invalid choices";}
    else $e="Invalid Choice";
   } elseif(!is_numeric($choice)||$c>=$numchoices||$c<0) $e="Invalid choice";
  }

  if($e) return $PAGE->JS("error",$e);

  if($row['poll_type']=="multi") {
   foreach($choice as $c) $results[$c][]=$USER['id'];
  } else {
   $results[$choice][]=$USER['id'];
  }

  $presults=Array();
  for($x=0;$x<$numchoices;$x++) $presults[$x]=($results[$x]?implode(",",$results[$x]):'');
  $presults=implode(";",$presults);

  $PAGE->JS("update","#poll .content",$this->generatePoll($row['poll_q'],$row['poll_type'],$choices,$presults),"1");

  $DB->safeupdate("topics",Array("poll_results"=>$presults),"WHERE id=?", $this->id);
 }

 function ratepost($postid,$nibletid){
  global $DB,$USER,$PAGE;
  $PAGE->JS("softurl");
  if(!is_numeric($postid)||!is_numeric($nibletid)) return false;
  $result = $DB->safeselect("rating","posts","WHERE id=?", $DB->basicvalue($postid));
  $f=$DB->row($result);
  $DB->disposeresult($result);

  $niblets=$DB->getRatingNiblets();
  if(!$USER['id']) $e="You don't have permission to rate posts.";
  elseif(!$f) $e="That post doesn't exist.";
  elseif(!$niblets[$nibletid]) $e="Invalid rating";
  else {
   $ratings=json_decode($f['rating'],true);
   if(!$ratings) $ratings=Array();
   else {
    $found=false;
    foreach($ratings as $k=>$v) {
        if(($pos=array_search($USER['id'],$v))!==false) {
            unset($ratings[$k][$pos]);
            if(empty($ratings[$k])) unset($ratings[$k]);
        }
    }
   }
  }
  if($e) $PAGE->JS("error",$e);
  else {
    $ratings[(int)$nibletid][]=(int)$USER['id'];
    $DB->safeupdate("posts",Array('rating'=>json_encode($ratings)),"WHERE id=?", $DB->basicvalue($postid));
    $PAGE->JS("alert",'Rated!');
  }
 }

 function qeditpost($id){
  global $DB,$JAX,$PAGE,$USER,$PERMS;
  if(!is_numeric($id)) return;
  if(!$PAGE->jsaccess) $PAGE->location("?act=post&pid=".$id);
  $PAGE->JS("softurl");
  $result = $DB->safeselect("*","posts","WHERE id=?", $id);
  $post=$DB->row($result);
  $DB->disposeresult($result);

  $hiddenfields=$JAX->hiddenFormFields(Array("act"=>"post","how"=>"qedit","pid"=>$id));

  if($PAGE->jsnewlocation){
   if(!$post) $PAGE->JS("alert","Post not found!");
   elseif(!$this->canedit($post)) $PAGE->JS("alert","You don't have permission to edit this post.");
   else {
    if($post['newtopic']) {
     $hiddenfields.=$JAX->hiddenFormFields(Array("tid"=>$post['tid']));
     $result = $DB->safeselect("*","topics",'WHERE id=?', $post['tid']);
     $topic=$DB->row($result);
     $DB->disposeresult($result);

     $form=$PAGE->meta('topic-qedit-topic',$hiddenfields,$topic['title'],$topic['subtitle'],$JAX->blockhtml($post['post']));
    } else {
     $form=$PAGE->meta('topic-qedit-post',$hiddenfields,$JAX->blockhtml($post['post']),$id);
    }
    $PAGE->JS("update","#pid_$id .post_content",$form);
   }
  }
 }

 function multiquote($tid){
  global $PAGE,$JAX,$DB,$SESS;
  $pid=$JAX->b['quote'];
  $post=false;
  if($pid&&is_numeric($pid)) {
   $result = $DB->safespecial("SELECT p.post,m.display_name name FROM %t p LEFT JOIN %t m ON p.auth_id=m.id WHERE p.id=?",
	array("posts","members"),
	$pid);
   $post=$DB->row($result);
   $DB->disposeresult($result);
  }
  if(!$post) {
   $e="That post doesn't exist!";
   $PAGE->JS("alert",$e);
   $PAGE->append("PAGE",$PAGE->meta('error',$e));
   return;
  } else {
   if($JAX->b['qreply']) {
    $PAGE->JS("updateqreply",'[quote='.$post['name'].']'.$post['post']."[/quote]\n\n");
   } else {
    if(!in_array($pid,explode(" ",$SESS->vars['multiquote'])))
     $SESS->addvar("multiquote",$SESS->vars['multiquote']?$SESS->vars['multiquote'].','.$pid:$pid);
	 //this line toggles whether or not the qreply window should open on quote
     if($PAGE->jsaccess) $this->qreplyform($tid);
     else header("Location:?act=post&tid=".$tid);
   }
  }
  $PAGE->JS("softurl");
 }

 function getlastpost($tid){
  global $DB,$PAGE;
  $result = $DB->safeselect("max(id) lastpid,count(*) numposts","posts","WHERE tid=?", $tid);
  $f=$DB->row($result);
  $DB->disposeresult($result);

  $PAGE->JS("softurl");
  $PAGE->location("?act=vt$tid&page=".(ceil(($f['numposts']/$this->numperpage)))."&pid=".$f['lastpid']."#pid_".$f['lastpid']);
 }

 function findpost($pid){
  global $PAGE,$DB;
  if(!is_numeric($pid)) $couldntfindit=true;
  else {
   $result = $DB->safespecial("SELECT * FROM %t WHERE tid=(SELECT tid FROM %t WHERE id=?) ORDER BY id ASC",
	array("posts","posts"),
	$pid);
   $num=1;
   while($f=$DB->row($result)) {
    if($f['id']==$pid) {
     $pid=$f['id'];
     $couldntfindit=false;
     break;
    }
    $num++;
   }
  }
  $PAGE->JS("softurl");
  if($couldntfindit) {
   $PAGE->JS("alert","that post doesn't exist");
  } else {
   $PAGE->location("?act=vt".$this->id."&page=".(ceil($num/$this->numperpage))."&pid=".$pid."#pid_".$pid);
  }
 }

 function markread($id){
  global $SESS,$PAGE,$JAX;
  $topicsread=$JAX->parsereadmarkers($SESS->topicsread);
  $topicsread[$id]=time();
  $SESS->topicsread=$JAX->base128encode($topicsread,true);
 }
 function listrating($pid){
  global $DB,$PAGE,$CFG;
  if($CFG['ratings']&2) return;
  $PAGE->JS("softurl");
  $result = $DB->safeselect("rating","posts","WHERE id=?", $DB->basicvalue($pid));
  $row=$DB->row($result);
  $DB->disposeresult($result);

  if($row) $ratings=json_decode($row[0],true);
  else $ratings=Array();
  if(empty($ratings)) return;
  else {
   $members=Array();
   foreach($ratings as $v) $members=array_merge($members,$v);
   $result = $DB->safeselect("id,display_name,group_id","members","WHERE id IN ?", $members);
   $mdata=Array($result);
   while($f=$DB->arow($result)) $mdata[$f['id']]=Array($f['display_name'],$f['group_id']);
   unset($members);
   $niblets=$DB->getRatingNiblets();
   foreach($ratings as $k=>$v) {
    $page.='<div class="column">';
    $page.='<img src="'.$niblets[$k]['img'].'" /> '.$niblets[$k]['title'].'<ul>';
    foreach($v as $mid) $page.='<li>'.$PAGE->meta('user-link',$mid,$mdata[$mid][1],$mdata[$mid][0]).'</li>';
    $page.='</ul></div>';
   }
  }
  $PAGE->JS("listrating",$pid,$page);
 }
}

?>
