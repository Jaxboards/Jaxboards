<?php

if(!defined(INACP)) die();

new stats;
class stats{
 function __construct(){$this->stats();}

 function stats(){
  global $PAGE,$JAX;
  switch(@$JAX->g['do']) {
   case "recount":$this->recount_statistics();break;
   default:$this->showstats();break;
  }
 }

 function showstats(){
  global $PAGE;
  $PAGE->addContentBox("Board Statistics","<a href='?act=stats&do=recount'>Recount Statistics</a>");
 }

 function recount_statistics(){
  global $DB,$PAGE;
  $result = $DB->safeselect("id,nocount","forums");
  while($f=$DB->row($result)) $pc[$f['id']]=$f['nocount'];
  $result = $DB->safespecial("SELECT p.id,p.auth_id,p.tid,t.fid FROM %t p JOIN %t t ON p.tid=t.id",array("posts","topics"));
  $stat=Array("forum_topics"=>Array(),"topic_posts"=>Array(),"member_posts"=>Array(),"cat_topics"=>Array(),"cat_posts"=>Array(),"forum_posts"=>Array(),"posts"=>0,"topics"=>0);
  while($f=$DB->row($result)) {
   if(!isset($stat['topic_posts'][$f['tid']])) {
    $stat['forum_topics'][$f['fid']]     ++;
    if(!isset($stat['forum_posts'][$f['fid']])) $stat['forum_posts'][$f['fid']]=0;
    $stat['topics']                      ++;
    $stat['topic_posts'][$f['tid']]      =0;
   } else {
    $stat['topic_posts'][$f['tid']]      ++;
    $stat['forum_posts'][$f['fid']]      ++;
   }
   if(!$pc[$f['fid']]) $stat['member_posts'] [$f['auth_id']] ++; else if(!$stat['member_posts'][$f['auth_id']]) $stat['member_posts'][$f['auth_id']]=0;
   $stat['posts']                        ++;
  }

  //go through and sum up category posts as well as forums with subforums
  $result = $DB->safeselect("id,path,cat_id","forums");
  while($f=$DB->row($result)){
   //I realize I don't use cat stats yet, but I may.
   $stat['cat_posts'][$f['cat_id']]+=$stat['forums'][$f['id']];
   $stat['cat_topics'][$f['cat_id']]+=$stat['forum_topics'][$f['id']];

   if($f['path']) {
    foreach(explode(" ",$f['path']) AS $v) {
     $stat['forum_topics'][$v]+=$stat['forum_topics'][$f['id']];
     $stat['forums'][$v]+=$stat['forums'][$f['id']];
    }
   }
  }


  //YEAH, this is bad. A bajillion update statements
  //however, I have been unable to find a better way to do this.
  //I have to do a seperate update query for every user, topic, category, and forum. pretty sick.

  //Update Topic Replies
  foreach($stat['topic_posts'] as $k=>$v) $DB->safeupdate("topics",Array("replies"=>$v),"WHERE id=?", $k);

  //Update member posts
  foreach($stat['member_posts'] as $k=>$v) $DB->safeupdate("members",Array("posts"=>$v),"WHERE id=?", $k);

  //Update forum posts
  foreach($stat['forum_posts'] as $k=>$v) $DB->safeupdate("forums",Array("posts"=>$v,"topics"=>$stat['forum_topics'][$k]),"WHERE id=?", $k);

  //get # of members
  $result = $DB->safeselect("count(id)","members");
  $stat['members']=array_pop($DB->row($result));
  $DB->disposeresult($result);

  //Update global board stats
  $DB->safeupdate("stats",Array("posts"=>$stat['posts'],"topics"=>$stat['topics'],"members"=>$stat['members']));

  $PAGE->addContentBox("Board Statistics","Board statistics recounted successfully.<br /><br /><br /><br /><a href='?act=stats'>Board Statistics</a>");

 }
}
?>
