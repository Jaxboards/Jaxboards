<?php
$PAGE->loadmeta('userprofile');

$IDX=new userprofile;
class userprofile{

 var $num_activity=30;

 var $contacturls=Array("skype"=>"skype:%s","msn"=>"msnim:chat?contact=%s","gtalk"=>"gtalk:chat?jid=%s","aim"=>"aim:goaim?screenname=%s","yim"=>"ymsgr:sendim?%s","steam"=>"http://steamcommunity.com/id/%s","twitter"=>"http://twitter.com/%s");

 /* Redundant constructor unnecesary in newer PHP versions. */
 /* function userprofile(){
  $this->__construct();
 } */
 function __construct(){
  global $JAX,$PAGE;
  preg_match("@\d+@",$JAX->b['act'],$m);
  $id=$m[0];
  if(!$id) $PAGE->location("?");
  elseif($PAGE->jsnewloc&&!$PAGE->jsdirectlink&&!$JAX->b['view']) $this->showcontactcard($id);
  else $this->showfullprofile($id);
 }
 function showcontactcard($id){
  global $PAGE,$DB,$JAX,$SESS,$USER;
  $result = $DB->safespecial("SELECT m.id AS uid,m.display_name AS uname,m.usertitle,g.title,m.avatar,
  m.contact_gtalk,m.contact_aim,m.contact_yim,m.contact_msn,m.contact_skype,m.contact_steam,m.contact_twitter
  FROM %t AS m LEFT JOIN %t AS g ON m.group_id=g.id WHERE m.id=?",
	array("members","member_groups"),
	$id);
  $ud=$DB->arow($result);
  $DB->disposeresult($result);
  if(!$ud) $PAGE->error("This user doesn't exist!");

  foreach($this->contacturls as $k=>$v)
   if($ud['contact_'.$k]) $contactdetails.='<a class="'.$k.' contact" href="'.sprintf($v,$JAX->blockhtml($ud['contact_'.$k])).'">&nbsp;</a>';
  $PAGE->JS("softurl");
  $PAGE->JS("window",Array("useoverlay"=>1,"minimizable"=>false,'animate'=>false,"title"=>"Contact Card","className"=>"contact-card","content"=>
   $PAGE->meta(
    "userprofile-contact-card",
     $ud['uname'],
     $JAX->pick($ud['avatar'],$PAGE->meta('default-avatar')),
     $ud['usertitle'],
     $ud['uid'],
     $contactdetails,
     in_array($ud['uid'],explode(",",$USER['friends']))?'<a href="?module=buddylist&remove='.$ud['uid'].'">Remove Contact</a>':'<a href="?module=buddylist&add='.$ud['uid'].'">Add Contact</a>',
     in_array($ud['uid'],explode(",",$USER['enemies']))?'<a href="?module=buddylist&unblock='.$ud['uid'].'">Unblock Contact</a>':'<a href="?module=buddylist&block='.$ud['uid'].'">Block Contact</>'
    )
   ));
 }
 function showfullprofile($id){
  global $PAGE,$DB,$JAX,$USER,$SESS,$PERMS;
  if($PAGE->jsupdate&&empty($JAX->p)) return false;
  $nouser=false;
  $udata = null;
  if(!$id||!is_numeric($id)) $nouser=true;
  else {
	$result = $DB->safespecial("SELECT m.*,g.title `group` FROM %t m LEFT JOIN %t g ON m.group_id=g.id WHERE m.id=?",
		array("members","member_groups"),
		$id);
	echo $DB->error(1);
	$udata=$DB->row($result);
	$DB->disposeresult($result);
   }
  if(!$udata||$nouser) {
   $e=$PAGE->meta('error',"Sorry, This user doesn't exist.");
   $PAGE->JS("update","page",$e);
   $PAGE->append("page",$e);
   return;
  }
  $pfpageloc=$JAX->b['page'];
  $pfbox='';
  switch($pfpageloc){
   case "activity":
   default:
    $pfpageloc="activity";
    $result = $DB->safespecial("SELECT a.*,a.affected_uid aff_id,m.display_name aff_name,m.group_id aff_group_id FROM %t a LEFT JOIN %t m ON a.affected_uid=m.id WHERE a.uid=? ORDER BY a.id DESC LIMIT ?",
	array("activity","members"),
	$id,
	$this->num_activity);
    if($JAX->b['fmt']=="RSS") {
     require_once("inc/classes/rssfeed.php");
     $feed=new rssfeed(Array('title'=>$udata['display_name']."'s recent activity",'description'=>$udata['usertitle']));
     while($f=$DB->row($result)) {
      $f['name']=$udata['display_name'];
      $f['group_id']=$udata['group_id'];
      $data=$JAX->parse_activity($f,true);
      $feed->additem(Array('title'=>$data['text'],'pubDate'=>date('r',$f['date']),'description'=>$data['text'],'link'=>'http://'.$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'].$data['link'],'guid'=>$f['id']));}
     $feed->publish();
     die();
    } else {    
     while($f=$DB->row($result)) {$f['name']=$udata['display_name'];$f['group_id']=$udata['group_id'];$pfbox.=$JAX->parse_activity($f);}
     if(!$pfbox) $pfbox="This user has yet to do anything note-worthy!";
     else $pfbox="<a href='./?act=vu".$id."&amp;page=activity&amp;fmt=RSS' class='social rss' style='float:right'>RSS</a>".$pfbox;
    }
   break;
   case "posts":
    $result = $DB->safespecial("SELECT p.post,p.id pid,p.tid,t.title,p.date,f.perms FROM %t p LEFT JOIN %t t ON p.tid=t.id LEFT JOIN %t f ON f.id=t.fid WHERE p.auth_id=? ORDER BY p.id DESC LIMIT 10",
	array("posts","topics","forums"),
	$id);
    while($f=$DB->row($result)) {
        $p=$JAX->parseperms($f['perms'],$USER?$USER['group_id']:3);
        if(!$p['read']) continue;
        $pfbox.=$PAGE->meta('userprofile-post',$f['tid'],$f['title'],$f['pid'],$JAX->date($f['date']),$JAX->theworks($f['post']));
    }
   break;
   case "topics":
    $result = $DB->safespecial("SELECT p.post,p.id pid,p.tid,t.title,p.date,f.perms FROM %t p LEFT JOIN %t t ON p.tid=t.id LEFT JOIN %t f ON f.id=t.fid WHERE p.auth_id=? AND p.newtopic=1 ORDER BY p.id DESC LIMIT 10",
	array("posts","topics","forums"),
	$id);
    while($f=$DB->row($result)) {
        $p=$JAX->parseperms($f['perms'],$USER?$USER['group_id']:3);
        if(!$p['read']) continue;
        $pfbox.=$PAGE->meta('userprofile-topic',$f['tid'],$f['title'],$JAX->date($f['date']),$JAX->theworks($f['post']));
    }
    if(!$pfbox) $pfbox="No topics to show.";
   break;
   case "about":
    $pfbox=$PAGE->meta('userprofile-about',$JAX->theworks($udata['about']),$JAX->theworks($udata['sig']));
   break;
   case "friends":
    if($udata['friends']){
     $result = $DB->safespecial("SELECT m.avatar,m.id,m.display_name name,m.usertitle FROM %t m LEFT JOIN %t g ON m.group_id=g.id WHERE m.id IN ? ORDER BY name",
	array("members","member_groups"),
	exlode(",", $udata['friends']));

     while($f=$DB->row($result)) $pfbox.=$PAGE->meta('userprofile-friend',$f['id'],$JAX->pick($f['avatar'],$PAGE->meta('default-avatar')),$PAGE->meta('user-link',$f['id'],$f['group_id'],$f['name']));
    }
    if(!$pfbox) $pfbox="I'm pretty lonely, I have no friends. :(";
    else $pfbox='<div class="contacts">'.$pfbox.'<br clear="all" /></div>';
   break;
   case "comments":
    if(is_numeric($JAX->b['del'])) {
        // if($PERMS['can_delete_comments']||$PERMS['can_moderate'])
            // $DB->delete("profile_comments","WHERE `id`=".$DB->evalue($JAX->b['del']).(!$PERMS['can_moderate']?' AND `from`='.$DB->evalue($USER['id']):''));

	if ($PERMS['can_moderate']) {
            	$DB->safedelete("profile_comments","WHERE `id`=?", $DB->basicvalue($JAX->b['del']));
        } else if ($PERMS['can_delete_comments']) {
            	$DB->safedelete("profile_comments","WHERE `id`=? AND `from`=?", $DB->basicvalue($JAX->b['del']), $DB->basicvalue($USER['id']));
        }
    }
    if(isset($JAX->p['comment'])&&$JAX->p['comment']!=="") {
     if(!$USER||!$PERMS['can_add_comments']) {
      $e="No permission to add comments!";
     } else {
      $DB->safeinsert("activity",Array("type"=>"profile_comment","uid"=>$USER['id'],"date"=>time(),"affected_uid"=>$id));
      $DB->safeinsert("profile_comments",Array("to"=>$id,"from"=>$USER['id'],"comment"=>$JAX->p['comment'],"date"=>time()));
     }
     if($e) {
      $PAGE->JS("error",$e);$pfbox.=$PAGE->meta('error',$e);
     }
    }
    if($USER&&$PERMS['can_add_comments']) {$pfbox=$PAGE->meta('userprofile-comment-form',
     $USER['name'],
     $JAX->pick($USER['avatar'],$PAGE->meta('default-avatar')),
     $JAX->hiddenFormFields(Array('act'=>'vu'.$id,'view'=>'profile','page'=>'comments'))
    );}
    $result = $DB->safespecial("SELECT c.*,m.display_name,m.group_id,m.avatar FROM %t c LEFT JOIN %t m ON c.from=m.id WHERE c.to=$id ORDER BY id DESC LIMIT 10",
	array("profile_comments","members"));
    while($f=$DB->row($result)) {
     $pfbox.=$PAGE->meta('userprofile-comment',$PAGE->meta('user-link',$f['from'],$f['group_id'],$f['display_name']),$JAX->pick($f['avatar'],$PAGE->meta('default-avatar')),$JAX->date($f['date']),$JAX->theworks($f['comment']).
     ($PERMS['can_delete_comments']&&$f['from']==$USER['id']||$PERMS['can_moderate']?' <a href="?act='.$JAX->b['act'].'&view=profile&page=comments&del='.$f['id'].'" class="delete">[X]</a>':'')
     );
     $found=true;
    }
    if(!$found) $pfbox.="No comments to display!";
   break;
  }
  if($JAX->b['page']&&$PAGE->jsaccess&&!$PAGE->jsdirectlink){
   $PAGE->JS("update","pfbox",$pfbox);
  } else {
   $PAGE->path(Array($udata['display_name']."'s profile"=>"?act=vu".$id."&view=profile"));
   $PAGE->updatepath();

   $tabs=Array("about","activity","posts","topics","comments","friends");
   foreach($tabs as $k=>$v) {
    $tabs[$k]='<a href="?act=vu'.$id.'&view=profile&page='.$v.'"'.($v==$pfpageloc?' class="active"':'').'>'.ucwords($v).'</a>';
   }

   $contactdetails='';
   foreach($udata as $k=>$v) {
    if(substr($k,0,8)=="contact_"&&$v)
     $contactdetails.='<div class="contact '.substr($k,8).'"><a href="'.sprintf($this->contacturls[substr($k,8)],$v).'">'.$v.'</a></div>';
   }
   $contactdetails.='<div class="contact im"><a href="javascript:void(0)" onclick="IMWindow(\''.$udata['id'].'\',\''.$udata['display_name'].'\')">IM</a></div>';
   $contactdetails.='<div class="contact pm"><a href="?act=ucp&what=inbox&page=compose&mid='.$udata['id'].'">PM</a></div>';
   if($PERMS['can_moderate']) {
    $contactdetails.='<div>IP: <a href="?act=modcontrols&do=iptools&ip='.$udata['ip'].'">'.long2ip($udata['ip']).'</a></div>';
   }
   
   $page=$PAGE->meta("userprofile-full-profile",
    $udata['display_name'],
    $JAX->pick($udata['avatar'],$PAGE->meta('default-avatar')),
    $udata['usertitle'],
    $contactdetails,
    $JAX->pick($udata['full_name'],"N/A"),
    $JAX->pick(ucfirst($udata['sex']),"N/A"),
    $udata['location'],
    ($udata['dob_year']?$udata['dob_month'].'/'.$udata['dob_day'].'/'.$udata['dob_year']:"N/A"),
    ($udata['website']?'<a href="'.$udata['website'].'">'.$udata['website'].'</a>':'N/A'),
    ($JAX->date($udata['join_date'])),
    $JAX->date($udata['last_visit']),
    $udata['id'],
    $udata['posts'],
    $udata['group'],
    $tabs[0],
    $tabs[1],
    $tabs[2],
    $tabs[3],
    $tabs[4],
    $tabs[5],
    $pfbox,
    ($PERMS['can_moderate']?'<a class="moderate" href="?act=modcontrols&do=emem&mid='.$udata['id'].'">Edit</a>':'')
   );
   $PAGE->JS("update","page",$page);
   $PAGE->append("page",$page);

   $SESS->location_verbose="Viewing ".$udata['display_name']."'s profile";
  }
 }
}
?>
