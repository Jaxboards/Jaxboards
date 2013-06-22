<?php
$PAGE->loadmeta('ticker');
new ticker;
class ticker{
 function ticker(){$this->__construct();}
 function __construct(){
  global $PAGE;
  $this->maxticks=60;
  if($PAGE->jsnewlocation||!$PAGE->jsaccess) $this->index();
  else $this->update();
 }
 function index(){
  global $PAGE,$DB,$SESS,$JAX,$USER;
  $SESS->location_verbose="Using the ticker!";
  $result = $DB->safespecial(
   "SELECT p.*,f.perms,f.title ftitle,t.title,t.fid,t.replies,t.auth_id auth_id2,m.group_id,m.display_name,m2.group_id group_id2,m2.display_name display_name2 FROM
   (SELECT * FROM %t ORDER BY id DESC LIMIT ?) p
   LEFT JOIN %t t ON t.id=p.tid
   LEFT JOIN %t f ON f.id=t.fid
   LEFT JOIN %t m ON p.auth_id=m.id
   LEFT JOIN %t m2 ON t.auth_id=m2.id
   ",
	array('posts','topics','forums','members','members'), 
	$this->maxticks);
  $ticks="";
  $first=0;
  while($f=$DB->row($result)){
   $p=$JAX->parseperms($f['perms'],$USER?$USER['group_id']:3);
   if(!$p['read']) continue;
   if(!$first) $first=$f['id'];
   $ticks.=$this->ftick($f);
  }
  $SESS->addvar('tickid',$first);
  $page=$PAGE->meta('ticker',$ticks);
  $PAGE->append("PAGE",$page);
  $PAGE->JS("update","page",$page);
 }
 function update(){
  global $PAGE,$DB,$SESS,$USER,$JAX;
  $result = $DB->safespecial(
   "SELECT p.*,f.perms,f.title ftitle,t.title,t.auth_id,t.auth_id auth_id2,t.replies,m.group_id,m.display_name,m2.group_id group_id2,m2.display_name display_name2 FROM
   (SELECT * FROM %t WHERE id>? ORDER BY id LIMIT ?) p
   LEFT JOIN %t t ON t.id=p.tid
   LEFT JOIN %t f ON f.id=t.fid
   LEFT JOIN %t m ON p.auth_id=m.id
   LEFT JOIN %t m2 ON t.auth_id=m2.id
   ",
	array('posts','topics','forums','members','members'),
	JAX::pick($SESS->vars['tickid'],0),
	$this->maxticks);
  $first=false;
  while($f=$DB->row($result)){
   $p=$JAX->parseperms($f['perms'],$USER?$USER['group_id']:3);
   if(!$p['read']) continue;
   if(!$first) $first=$f['id'];
   $PAGE->JS("tick",$this->ftick($f));
  }
  if($first) $SESS->addvar('tickid',$first);
 }
 function ftick($t){
  global $PAGE,$JAX;
  return $PAGE->meta('ticker-tick',
   $JAX->smalldate($t['date'],false,true),
   $PAGE->meta('user-link',$t['auth_id'],$t['group_id'],$t['display_name']),
   $t['tid'],
   $t['id'], //pid
   $t['title'],
   $t['fid'],
   $t['ftitle'],
   $PAGE->meta('user-link',$t['auth_id2'],$t['group_id2'],$t['display_name2']),
   $t['replies']
   );
 }
}
?>
