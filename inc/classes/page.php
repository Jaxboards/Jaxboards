<?php
class PAGE{
 var $metadefs=Array();
 /* Redundant constructor unnecesary in newer PHP versions. */
 /* function PAGE(){$this->__construct();} */
 function __construct(){
  $this->JSOutput=Array();
  $this->jsaccess=$_SERVER['HTTP_X_JSACCESS'];
  $this->jsupdate=($this->jsaccess==1);
  $this->jsnewlocation=$this->jsnewloc=($this->jsaccess>=2);
  $this->jsdirectlink=($this->jsaccess==3);
  $this->mobile=stripos($_SERVER["HTTP_USER_AGENT"],"mobile")!==false;
  $this->parts=Array();
  $this->vars=Array();
  $this->metadefs=Array();
  $this->userMetaDefs=Array();
  $this->moreFormatting=Array();
  
 }

 function get($a){
  return $this->parts[$a];
 }

 function append($a,$b){
  if($a=="SCRIPT"&&$this->mobile) return;
  $a=strtoupper($a);
  if(!$this->jsaccess||$a=="TITLE") {
   if(!isset($this->parts[$a])) return $this->reset($a,$b);
   return $this->parts[$a].=$b;
  }
 }
 
 function addvar($a,$b){
  $this->vars['<%'.$a.'%>']=$b;
 }
 function filtervars($a){
  return str_replace(array_keys($this->vars),array_values($this->vars),$a);
 }
 
 function prepend($a,$b){
  if(!$this->jsaccess){
   $a=strtoupper($a);
   if(!isset($this->parts[$a])) return $this->reset($a,$b);
   return $this->parts[$a]=$b.$this->parts[$a];
  }
 }

 function location($a){
  global $PAGE,$SESS,$JAX;
  if(empty($JAX->c)&&$a[0]=="?") $a='?sessid='.$SESS->data['id'].'&'.substr($a,1);
  if($PAGE->jsaccess) $PAGE->JS("location",$a);
  else header("Location: $a");
 }

 function reset($a,$b=''){
  $a=strtoupper($a);
  $this->parts[$a]=$b;
 }

 function JS(){
  $args=func_get_args();
  if($args[0]=="softurl") $GLOBALS['SESS']->erase("location");
  if($this->jsaccess) $this->JSOutput[]=$args;
 }

 function JSRaw($a){
  foreach(explode("\n",$a) as $a22) {
   $a2=json_decode($a22);
   if(!is_array($a2)) continue;
   if(is_array($a2[0])) foreach($a2 as $v) $this->JSOutput[]=$v;
   else $this->JSOutput[]=$a2;
  }
 }
 function JSRawArray($a){
  $this->JSOutput[]=$a;
 }

 function out(){
  global $ads,$SESS;
  if (isset($this->done)) return false;
  $this->done=true;
  $this->parts['path']="<div id='path' class='path'>".$this->buildpath()."</div>";
  //ads
  
  if ($this->jsaccess) {
   header("Content-type:text/plain");
   foreach($this->JSOutput as $k=>$v) $this->JSOutput[$k]=$SESS->addSessID($v);
   echo !empty($this->JSOutput)?JAX::json_encode($this->JSOutput):"";
  }
  else {
   $autobox=Array("PAGE","COPYRIGHT","USERBOX");
   foreach($this->parts as $k=>$v) {
    $k=strtoupper($k);
    if(in_array($k,$autobox)) $v='<div id="'.strtolower($k).'">'.$v.'</div>';
    if($k=="PATH") $this->template=preg_replace("@<!--PATH-->@",$v.$ads,$this->template,1);
    $this->template=str_replace("<!--".$k."-->",$v,$this->template);
   }
   $this->template=$this->filtervars($this->template);
   $this->template=$SESS->addSessId($this->template);
   if($this->checkextended(null,$this->template)) $this->template=$this->metaextended($this->template);
   echo $this->template;
  }
 }

 function collapsebox($a,$b,$c=false){
  return $this->meta('collapsebox',($c?' id="'.$c.'"':''),$a,$b);
 }

 function error($a){
  return $this->meta('error',$a);
 }

 function templatehas($a){
  return preg_match("/<!--$a-->/i",$this->template);
 }

 function loadtemplate($a){
  $this->template=file_get_contents($a);
  $this->template=preg_replace_callback("@<!--INCLUDE:(\w+)-->@",Array($this,"includer"),$this->template);
  $this->template=preg_replace_callback('@<M name=([\'"])([^\'"]+)\\1>(.*?)</M>@s',Array(&$this,"userMetaParse"),$this->template);
 }
 
 function loadskin($id){
  global $DB,$CFG;
  if($id) {
   $result = $DB->safeselect("*","skins","WHERE id=? LIMIT 1", $id);
   $skin=$DB->row($result);
   $DB->disposeresult($result);
  }
  if(!$skin) {
   $result = $DB->safeselect("*","skins","WHERE `default`=1 LIMIT 1");
   $skin=$DB->row($result);
   $DB->disposeresult($result);
  }
  if(!$skin) {$skin=Array("title"=>"Default","custom"=>0);}
  $t=($skin['custom']?BOARDPATH:"")."Themes/".$skin['title']."/";
  if(is_dir($t)) define("THEMEPATH",$t);
  else define("THEMEPATH",$CFG['dthemepath']);
  define("DTHEMEPATH",$CFG['dthemepath']);
  define("IMGPATH",THEMEPATH."img/");
  $this->loadtemplate($skin['wrapper']?BOARDPATH."Wrappers/".$skin['wrapper'].".txt":THEMEPATH."wrappers.txt");
 }

 function userMetaParse($m){
  $this->checkextended($m[2],$m[3]);
  $this->userMetaDefs[$m[2]]=$m[3];
  return "";
 }

 function includer($m){
  global $DB;
  $result = $DB->safeselect("page","pages","WHERE act=?", $DB->basicvalue($m[1]));
  $page=array_shift($DB->row($result));
  $DB->disposeresult($result);
  return $page?$page:'';
 }
 
 function loadmeta($a){
   
   if(is_file(THEMEPATH."meta/".$a.".php")) $file=THEMEPATH."meta/".$a.".php";
   else $file=DTHEMEPATH."meta/".$a.".php";
   $this->metaqueue[]=$file;
   $this->debug("Added $a to queue");

 }
 function processqueue($what){
  while($v=array_pop($this->metaqueue)){ require_once($v);$this->debug("$what triggered $v to load");
  if(is_array($meta)) {
   foreach($meta as $k=>$v) $this->checkextended($k,$v);
   $this->metadefs=$meta+$this->metadefs;}
  }
 }

 function meta(){
  $args=func_get_args();
  $meta=array_shift($args);
  $this->processqueue($meta);
  $r=@vsprintf(str_replace(Array('<%','%>'),Array('<%%','%%>'),$this->userMetaDefs[$meta]?:$this->metadefs[$meta]),is_array($args[0])?$args[0]:$args);
  if($r===false) die($meta.' has too many arguments');
  if($this->moreFormatting[$meta]) return $this->metaextended($r);
  return $r;
 }
 function metaextended($m){
  return preg_replace_callback("@{if ([^}]+)}(.*){/if}@Us",Array($this,'metaextendedifcb'),$this->filtervars($m));
 }
 function metaextendedifcb($m){
  if(strpos($m[1],'||')!==false) $s='||';
  else $s='&&';
  foreach(explode($s,$m[1]) as $piece) {
   preg_match("@(\S+?)\s*([!><]?=|[><])\s*(\S*)@",$piece,$pp);
   switch($pp[2]){
    case "=":$c=$pp[1]==$pp[3];break;
    case "!=":$c=$pp[1]!=$pp[3];break;
    case ">=":$c=$pp[1]>=$pp[3];break;
    case ">":$c=$pp[1]>$pp[3];break;
    case "<=":$c=$pp[1]<=$pp[3];break;
    case "<":$c=$pp[1]<$pp[3];break;
   }
   if($s=='&&'&&!$c) break;
   else if($s=='||'&&$c) break;
  }
  if($c) return $m[2];
  return '';
 }
  
 function checkextended($meta=null,$data){
  if(strpos($data,'{if ')!==false) if($meta) $this->moreFormatting[$meta]=true; else return true;
  return false;
 }
 
 function metaexists($meta){
  return $this->userMetaDefs[$meta]||$this->metadefs[$meta];
 }

 function path($a){
  if(!is_array($this->parts['path'])) $this->parts['path']=Array();
  $empty=empty($this->parts['path']);
  foreach($a as $value=>$link) $this->parts['path'][$link]=$value;
  return true;
 }

 function buildpath(){
  $first=true;
  foreach($this->parts['path'] as $value=>$link) {
   $path.=$this->meta($first&&$this->metaexists('path-home')?"path-home":"path-part",$value,$link);
   $first=false;
  }
  return $this->meta("path",$path);
 }


 function updatepath($a=false){
  if($a) $this->path($a);
  $this->JS("update","path",$this->buildpath());
 }
 
 function debug($data=""){
  if($data) $this->debuginfo.=$data."<br />";
  else return $this->debuginfo;
 }
 
 function SWF($file,$options=Array()){
    $settings=Array('width'=>'100%','height'=>'100%');
    foreach($options as $k=>$v) $settings[$k]=$v;
    $object= '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="'.$settings['width'].'" height="'.$settings['height'].'">';
    $object.='<param name="movie" value="'.$file.'"></param>';
    if($settings['flashvars']) $object.='<param name="flashvars" value="'.http_build_query($settings['flashvars']).'" />';
    $object.='<param name="allowScriptAccess" value="always" />';
    $embed= '<embed style="display:block" type="application/x-shockwave-flash" pluginspage="http://macromedia.com/go/getflashplayer" src="'.$file.'" width="'.$settings['width'].'" height="'.$settings['height'].'" wmode="opaque" flashvars="'.http_build_query($settings['flashvars']).'" allowScriptAccess="always"></embed>';
    return stristr('msie',$_SERVER['HTTP_USER_AGENT'])?$object:$embed;
 }
}
?>
