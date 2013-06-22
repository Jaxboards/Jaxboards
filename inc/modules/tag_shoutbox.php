<?php
$PAGE->loadmeta('shoutbox');


new SHOUTBOX;
class SHOUTBOX{
 function __construct(){
  global $PAGE,$JAX,$CFG,$PERMS;
  if(!$CFG['shoutbox']||!$PERMS['can_view_shoutbox']) return;
  $this->shoutlimit=$CFG['shoutbox_num'];
  if(is_numeric($JAX->b['shoutbox_delete'])) $this->deleteshout();
  elseif($JAX->b['module']=="shoutbox") $this->showallshouts();
  if (trim($JAX->p['shoutbox_shout'])!=="") $this->addshout();
  if(!$PAGE->jsaccess) $this->displayshoutbox();
  else {
   $this->updateshoutbox();
  }
 }
 function canDelete($id,$shoutrow=false){
  global $PERMS,$USER,$DB;
  $candelete=$PERMS['can_delete_shouts'];
  if(!$candelete&&$PERMS['can_delete_own_shouts']) {
   if(!$shoutrow) {
     $result = $DB->safeselect("uid","shouts","WHERE id=?", $id);
     $shoutrow=$DB->row($result);
   }
   if($shoutrow['uid']==$USER['id']) $candelete=true; 
  }
  return $candelete;
 }
 function formatshout($row){
  global $PAGE,$JAX,$CFG;
  $shout=$JAX->theworks($row['shout'],Array('minimalbb'=>true));
  $user=$row['uid']?$PAGE->meta('user-link',$row['uid'],$row['group_id'],$row['display_name']):"Guest";
  $avatar=($CFG['shoutboxava']?'<img src="'.$JAX->pick($row['avatar'],$PAGE->meta('default-avatar')).'" class="avatar" alt="avatar" />':'');
  $deletelink=$PAGE->meta('shout-delete',$row['id']);
  if(!$this->canDelete(0,$row)) $deletelink="";
  if(substr($shout,0,4)=="/me ") $shout=$PAGE->meta("shout-action",$JAX->smalldate($row['timestamp'],1),$user,substr($shout,3),$deletelink);
  else $shout=$PAGE->meta("shout",$JAX->smalldate($row['timestamp'],1),$user,$shout."\n",$deletelink,$avatar);
  return $shout;
 }
 function displayshoutbox(){
  global $PAGE,$DB,$SESS,$USER;
  $result = $DB->safespecial("SELECT s.*, m.display_name, m.group_id, m.avatar FROM %t AS s LEFT JOIN %t AS m ON s.uid=m.id ORDER BY s.id DESC LIMIT ?", array("shouts","members"),
	$this->shoutlimit);
  $shouts='';
  $first=0;
  while($f=$DB->arow($result)) {
   if(!$first) $first=$f['id'];
   $shouts.=$this->formatshout($f);
  }
  $SESS->addvar('sb_id',$first);
  $PAGE->append("shoutbox",$PAGE->meta('collapsebox'," id='shoutbox'",$PAGE->meta('shoutbox-title'),$PAGE->meta('shoutbox',$shouts))."<script type='text/javascript'>globalsettings.shoutlimit=".$this->shoutlimit.";globalsettings.sound_shout=".(!$USER||$USER['sound_shout']?1:0)."</script>");
 }
 function updateshoutbox(){
  global $PAGE,$JAX,$DB,$SESS,$USER,$CFG;

  //this is a bit tricky, we're transversing the shouts in reverse order, since they're shifted onto the list, not pushed

  $last=0;
  if($SESS->vars['sb_id']) {
	$DB->safespecial("SELECT s.*,m.display_name,m.group_id,m.avatar FROM %t AS s LEFT JOIN %t AS m ON s.uid=m.id WHERE s.id>? ORDER BY s.id ASC LIMIT ?",
		array("shouts","members"),
		$JAX->pick($SESS->vars['sb_id'],0),
		$this->shoutlimit);
	while($f=$DB->row()) {
	   $PAGE->JS("addshout",$this->formatshout($f));
	   if($CFG['shoutboxsounds']) {
	   $sounds=Array(
	    "diglettdig"=>"diglettdig1",
	    "gidttelgid"=>"gidttelgid",
	    "triotriotrio"=>"triotriotrio",
	    "ruuun"=>"runfuckingrun",
	    "i'm painis cupcake"=>"i_am_painis_cupcake",
	    "diglett"=>"diglett",
	    "i will eat you"=>"i_will_eat_you",
	    "!"=>"alert",
	    "scatman"=>"scatman",
	    "super meat boy"=>"smb",
	    "warpzone"=>"warpzone",
	    "push the buttons"=>"ptb",
	    "so fluffy"=>"so fluffy",
	    "does this count as annoying"=>"does this count as annoying",
	    "its so fluffy im gonna die"=>"its so fluffy im gonna die",
	    "pew pew"=>"pewpew",
		"sounds like someone wants to get ***REMOVED***d again"=>"***REMOVED***dagain",
		"i was frozen today"=>"frozentoday",
		"lol clinton"=>"clinton_denial"
	    );
	   if($USER['sound_shout']&&$sounds[$f['shout']]) $PAGE->JS("playsound","sfx","http://jaxboards.com/Sounds/".$sounds[$f['shout']].".mp3");
	   }
	   $last=$f['id'];
	}
  }

  //update the sb_id variable if we selected shouts
  if($last) $SESS->addvar('sb_id',$last);
 }
 function showallshouts(){
  global $PAGE,$DB,$JAX;
  $perpage=100;
  $pagen=0;
  if(is_numeric($JAX->b['page'])&&$JAX->b['page']>1) $pagen=$JAX->b['page']-1;
  $result = $DB->safeselect("count(*)","shouts");
  $numshouts=array_pop($DB->row($result));
  $DB->disposeresult($result);
  if($numshouts>1000) $numshouts=1000;
  if($numshouts>$perpage) {
  $pages.=" &middot; Pages: <span class='pages'>";
  foreach($JAX->pages(ceil($numshouts/$perpage),$pagen+1,10) as $v) $pages.='<a href="?module=shoutbox&page='.$v.'"'.(($v+1)==$pagen?' class="active"':'').'>'.$v.'</a> ';
  $pages.='</span>';
  }
  $PAGE->path(Array("Shoutbox History"=>"?module=shoutbox"));
  $PAGE->updatepath();
  if($PAGE->jsupdate) return;
  $DB->safespecial("SELECT s.*, m.avatar, m.display_name, m.group_id FROM %t AS s LEFT JOIN %t AS m ON s.uid=m.id ORDER BY s.id DESC LIMIT ?,?",
	array("shouts","members"),
	($pagen*$perpage),
	$perpage
  );
  while($f=$DB->row()) $shouts.=$this->formatshout($f);
  $page=$PAGE->meta('box','','Shoutbox'.$pages,'<div class="sbhistory">'.$shouts.'</div>');
  $PAGE->JS("update","page",$page);
  $PAGE->append("PAGE",$page);
 }
 function deleteshout(){
  global $JAX,$DB,$PAGE,$USER;
  if(!$USER) return $PAGE->location("?");
  $delete=$JAX->b['shoutbox_delete'];
  $candelete=$this->canDelete($delete);
  if(!$candelete) return $PAGE->location("?");
  $PAGE->JS("softurl");
  $DB->safedelete("shouts","WHERE id=?", $delete);
 }
 function addshout(){
  global $JAX,$DB,$PAGE,$SESS;
  $SESS->act();
  $shout=$JAX->p['shoutbox_shout'];
  $shout=$JAX->linkify($shout);
  $perms=$JAX->getPerms();
  if(!$perms['can_shout']) $e="You do not have permission to shout!";
  elseif (strlen($shout)>300) $e="Shout must be less than 300 characters.";
  if($e){
   $PAGE->JS("error",$e);
   $PAGE->prepend("shoutbox",$PAGE->error($e));
   return;
  }
  $DB->safeinsert("shouts",Array("uid"=>$JAX->pick($JAX->userData['id'],0),"shout"=>$shout,"timestamp"=>time(),"ip"=>$JAX->ip2int()));
 }
}
?>
