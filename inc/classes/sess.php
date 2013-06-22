<?php
class SESS{
 var $data=Array();
 var $bots=Array("google"=>"Googlebot","bingbot"=>"Bing","yahoo! slurp"=>"Yahoo","mj12bot"=>"MJ12bot","baidu"=>"Baidu","discobot"=>"DiscoBot");
 var $changedData=Array();
 function SESS($sid){
  $this->__construct($sid);
 }
 function __construct($sid){
  $this->data=$this->getSess($sid);
  $this->data['vars']=unserialize($this->data['vars']);
  if(!$this->data['vars']) $this->data['vars']=Array();
 }
 function getSess($sid){
  global $DB,$JAX;
  $isbot=0;
  foreach($this->bots as $k=>$v) {
   if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']),$k)!==false) {
    $sid=$v;
    $isbot=1;
   }
  }
  if($sid) {
   // $DB->select('*','session','WHERE id='.$DB->evalue($sid).(!$isbot?' AND ip='.$JAX->ip2int():''));
   $result = (!$isbot) ?
	$DB->safeselect('*','session','WHERE id=? AND ip=?;',
		$DB->basicvalue($sid),
		$JAX->ip2int()) :
	$DB->safeselect('*','session','WHERE id=?',
		$DB->basicvalue($sid));
   $r=$DB->arow($result);
   $DB->disposeresult($result);
  }
  if($r) return $r;
  else if(!$isbot) $sid=md5(uniqid(true,rand(0,1000)));
  if(!$isbot) setcookie('sid',$sid);
  $sessData=Array('id'=>$sid,'uid'=>0,'runonce'=>'','ip'=>$JAX->ip2int(),'useragent'=>$_SERVER['HTTP_USER_AGENT'],'is_bot'=>$isbot,'last_action'=>time(),'last_update'=>time());
  $DB->safeinsert("session",$sessData);
  return $sessData;
 }
 function __get($a){ return $this->data[$a]; }
 function __set($a,$b){if($this->data[$a]==$b) return;$this->changedData[$a]=$b;$this->data[$a]=$b; }
 function set($a){
  foreach($a as $k=>$v) $this->__set($k,$v);
 }
 function addvar($a,$b){
  if($this->data['vars'][$a]!=$b){
   $this->data['vars'][$a]=$b;
   $this->changedData['vars']=serialize($this->data['vars']);
  }
 }
 function delvar($a){
  if($this->data['vars'][$a]) {
   unset($this->data['vars'][$a]);
   $this->changedData['vars']=serialize($this->data['vars']);
  }
 }
 function act($a=false){
  global $JAX;
  //$JAX->setCookie("la",time(),time()+60*60*24*30);
  $this->__set('last_action',time());
  if($a) $this->__set('location',$a);
 }
 function erase($a){
  unset($this->changedData[$a]);
 }
 function clean($uid){
  global $DB,$CFG,$PAGE;
  $timeago=time()-$CFG['timetologout'];
  if($uid){
   $result = $DB->safeselect("max(last_action)","session","WHERE uid=? GROUP BY uid",
	$uid);
   $la=$DB->row($result);
   $DB->disposeresult($result);
   if($la) $la=$la[0];
   $DB->safedelete("session","WHERE uid=? AND last_update<?", $DB->basicvalue($uid), $timeago);
   $this->__set("readtime",JAX::pick($la,0));
  }
  $yesterday=mktime(0,0,0);
  $query=$DB->safeselect("uid,max(last_action) last_action","session","WHERE last_update<? GROUP BY uid",
	$yesterday);
  while($f=$DB->row($query)) {
	if($f['uid']) $DB->safeupdate("members",Array("last_visit"=>$f['last_action']),"WHERE id=?", $f['uid']);
  }
  $DB->safespecial("DELETE FROM %t WHERE last_update<? OR (uid=0 AND last_update< ?)",
	array("session"),
	$yesterday,
	$timeago);
  return true;
 }
 function applyChanges(){
  global $DB,$PAGE;
  $sd=$this->changedData;
  $id=$this->data['id'];
  $sd['last_update']=time();
  if($this->data['is_bot']) {
   $sd['forumsread']=$sd['topicsread']=''; //bots tend to read a lot of shit
  }
  if(!$this->data['last_action']) $sd['last_action']=time();
  $DB->safeupdate('session',$sd,"WHERE id=?", $DB->basicvalue($id));
 }
 
 function addSessID($html){
  global $JAX;
  if(!empty($JAX->c)) return $html;
  return preg_replace_callback("@href=['\"]?([^'\"]+)['\"]?@",Array($this,"addSessIDCB"),$html);
 }
 function addSessIDCB($m){
  if($m[1][0]=="?") $m[1].='&amp;sessid='.$this->data['id'];
  return 'href="'.$m[1].'"';
 }
}
?>
