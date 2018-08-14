<?php
class JAX{
 /* Redundant constructor unnecesary in newer PHP versions. */
 /* function JAX(){$this->__construct();} */
 function __construct(){
  $this->c=$this->filterInput($_COOKIE);
  $this->g=$this->filterInput($_GET);
  $this->p=$this->filterInput($_POST);
  $this->b=array_merge($this->p,$this->g);
  $this->textRules = NULL;
 }

 function between($a,$b,$c){
  return $a>=$b&&$a<=$c;
 }

 function date($date,$autodate=true){
  if(!$date) return false;
  $delta=time()-$date;
  $fmt="";
  if     ($delta<90)                   $fmt="a minute ago";
  elseif ($delta<3600)                 $fmt=round($delta/60)." minutes ago";
  elseif (date("m j Y")==date("m j Y",$date))  $fmt="Today @ ".date("g:i a",$date);
  elseif (date("m j Y",strtotime("yesterday"))==date("m j Y",$date))               $fmt="Yesterday @ ".date("g:i a",$date);
  else                                 $fmt=date("M jS, Y @ g:i a",$date);
  if(!$autodate) return $fmt;
  return "<span class='autodate' title='$date'>$fmt</span>";
 }

 function smalldate($date,$seconds=false,$autodate=false){
  if(!$date) return false;
  return ($autodate?'<span class="autodate smalldate" title="'.$date.'">':'').date("g:i".($seconds?":s":"")."a, n/j/y",$date).($autodate?'</span>':'');
 }

public static function json_encode($a,$forceaa=false){
 $keys=array_keys($a);
 $r="";
 $replaces=Array("\\"=>"\\\\",'"'=>'\"',"\r\n"=>"\\n","\n"=>"\\n","\r"=>"\\n");
 if($forceaa||array_diff($keys,range(0,count($a)))){
  //associative array
  foreach($a as $k=>$v) {
   $k='"'.$k.'"';
   $r.=$k.':'.(is_array($v)?self::json_encode($v):(!is_numeric($v)?'"':'').str_replace(array_keys($replaces),array_values($replaces),$v).(!is_numeric($v)?'"':'')).',';
  }
  if(substr($r,-1)==",") $r=substr($r,0,-1);
  $r='{'.$r.'}';
 } else {
  foreach($a as $v) $r.=(is_array($v)?self::json_encode($v):'"'.str_replace(array_keys($replaces),array_values($replaces),$v).'"').',';
  if(substr($r,-1)==",") $r=substr($r,0,-1);
  $r='['.$r.']';
 }
 return $r;
 $o=fopen("log.php","a");
 fwrite($o,$r."\n\n\n\n");
 fclose($o);
 return $r;
}

public static function json_decode($a,$aa=true){
 return json_decode($a,$aa);
}

 public static function utf8_encode($a){
  if(is_array($a)) foreach($a as $k=>$v) $a[$k]=self::utf8_encode($v);
  else $a=utf8_encode($a);
  return $a;
 }

 public static function is_numerical_array($a){
  return range(0,count($a)-1)==array_keys($a);
 }

 function setCookie($a,$b='false',$c=false,$htmlonly=true){
  if(!is_array($a)) $a=Array($a=>$b);
  elseif($b!='false') $c=$b;
  foreach($a as $k=>$v){
   $this->c[$k]=$v;
   setcookie($k,$v,$c,null,null,false,$htmlonly);
  }
 }

 function linkify($a){
  $a=str_replace("<IP>",$_SERVER['REMOTE_ADDR'],$a);
  return preg_replace_callback("@(^|\s)(https?://[^\s\)\(<>]+)@",Array($this,"linkify_callback"),$a);
 }
 function linkify_callback($match){
  $url=parse_url($match[2]);
  if(!$url['fragment']&&$url['query']) $url['fragment']=$url['query'];
  if($url['host']==$_SERVER['HTTP_HOST']&&$url['fragment']) {
   if(preg_match("@act=vt(\d+)@",$url['fragment'],$m)) {
    if(preg_match("@pid=(\d+)@",$url['fragment'],$m2)) $nice="Post #".$m2[1];
    else $nice="Topic #".$m[1];
   }
   $match[2]="?".$url['fragment'];
  }
  return $match[1].'[url='.$match[2].']'.($nice?$nice:$match[2]).'[/url]';
 }

 function filterInput($a){
  if(!get_magic_quotes_gpc()) return $a;
  if(is_array($a)) return array_map(array($this,'filterInput'),$a);
  return stripslashes($a);
 }

 //-------------------
 //-----the getSess and getUser functions both return the session/user data respectively
 //-----if not found in the database, getSess inserts a blank Sess row, while getUser returns false
 //-------------------

 function getUser($uid=false,$pass=false){
  global $DB;
  if(!$DB) return;
  if(!$uid) return $this->userData=false;
  $result = $DB->safeselect("id,group_id,sound_im,sound_shout,last_visit,display_name,friends,enemies,skin_id,nowordfilter,wysiwyg,avatar,ip,usertitle,concat(dob_month,' ',dob_day) birthday,`mod`,posts","members","WHERE id=? AND pass=?", $DB->basicvalue($uid), $DB->basicvalue($pass));
  if(!$row=$DB->row($result)) {
   return $this->userData=false;
  } else {
   $DB->disposeresult($result);
   // $row['buddies']=explode(",",$row['buddies']); /* Possible bug. */
   $row['birthday']=(date('n j')==$row['birthday']?1:0);
   return $this->userData=$row;
  }
 }

 function getPerms($group_id=''){
  global $DB;
  if($group_id===''&&$this->userPerms) return $this->userPerms;
  else {
   if($group_id==='') {
    $group_id=$this->userData['group_id'];
   }
   if($this->ipbanned()) {
    $this->userData['group_id']=$group_id=4;
   }
   $result = $DB->safeselect("*","member_groups","WHERE id=?", $this->pick($group_id,3));
   $retval = $this->userPerms=$DB->row($result);
   $DB->disposeresult($result);
   return $retval;
  }
 }

 function blockhtml($a){
  return str_replace("{if","&#123;if",htmlspecialchars($a,ENT_QUOTES)); //fix for template conditionals
 }

 function getTextRules(){
  global $CFG,$DB;
  if($this->textRules) return $this->textRules;
  $q=$DB->safeselect("*","textrules",'');
  $textRules=Array('emote'=>Array(),'bbcode'=>Array(),'badword'=>Array());
  while($f=$DB->row($q)) $textRules[$f['type']][$f['needle']]=$f['replacement'];
  //load emoticon pack
  $emotepack=isset($CFG['emotepack']) ? $CFG['emotepack'] : NULL;
  if($emotepack) {
   $emotepack="emoticons/".$emotepack;
   if(substr($emotepack,-1)!="/") $emotepack.="/";
   if(file_exists($emotepack."rules.php")) {
    require_once($emotepack."rules.php");
    if(!$rules) die("Emoticon ruleset corrupted!");
    else foreach($rules as $k=>$v) if(!isset($textRules['emote'][$k])) $textRules['emote'][$k]=$emotepack.$v;
   }
  }
  $nrules=Array();
  foreach($textRules['emote'] as $k=>$v) $nrules[preg_quote($k,'@')]='<img src="'.$v.'" alt="'.$this->blockhtml($k).'"/>';
  $this->emoteRules=empty($nrules)?false:$nrules;
  $this->textRules=$textRules;
  return $this->textRules;
 }

 function getEmoteRules($escape=1){
  global $CFG,$DB;
  //legacy code to update to new system, remove this after 5/1
  if(file_exists(BOARDPATH."emoticons.php")){
   require_once(BOARDPATH."emoticons.php");
   foreach($emoticons as $k=>$v) $DB->safeinsert("textrules",Array('type'=>'emote','needle'=>$k,'replacement'=>$v));
   unlink(BOARDPATH."emoticons.php");
   foreach($emoticons as $k=>$v) $nrules[($escape?preg_quote($k,'@'):$k)]='<img src="'.$v.'" />';
  }
  $this->getTextRules();
  return $escape?$this->emoteRules:$this->textRules['emote'];
 }

 function emotes($a){
  //believe it or not, adding a space and then removing it later is 20% faster than doing (^|\s)
  $emoticonlimit=15;
  $this->getTextRules();
  if(!$this->emoteRules) return $a;
  $a=preg_replace_callback("@(\s)(".implode("|",array_keys($this->emoteRules)).")@",Array($this,"emotecallback")," ".$a,$emoticonlimit);
  return substr($a,1);
 }

 function emotecallback($a){
  return $a[1].$this->emoteRules[preg_quote($a[2],'@')];
 }

 function getwordfilter(){
  global $CFG,$DB;
  if(!isset($this->textRules)){
   $wordfilter=Array();
   //legacy code to update to new system, remove this after 5/1
   if(file_exists(BOARDPATH."wordfilter.php")) {
    require_once(BOARDPATH."wordfilter.php");
    foreach($wordfilter as $k=>$v) {
     $DB->safeinsert("textrules",Array('type'=>'badword','needle'=>$k,'replacement'=>$v));
    }
    unlink(BOARDPATH."wordfilter.php");
   }
   $this->getTextRules();
  }
  return $this->textRules['badword'];
 }

 function wordfilter($a){
  global $USER;
  if($USER&&$USER['nowordfilter']) return $a;
  $this->getTextRules();
  return str_ireplace(array_keys($this->textRules['badword']),array_values($this->textRules['badword']),$a);
 }

 function startcodetags(&$a){
  preg_match_all('@\[code(=\w+)?\](.*?)\[/code\]@is',$a,$codes);
  foreach($codes[0] as $k=>$v) $a=str_replace($v,'[code]'.$k.'[/code]',$a);
  return $codes;
 }

 function finishcodetags($a,$codes,$returnbb=false){
  foreach($codes[0] as $k=>$v) {
   if(!$returnbb) {
   if($codes[1][$k]=="=php"){
    $codes[2][$k]=highlight_string($codes[2][$k],1);
   } else {
    $codes[2][$k]=preg_replace("@([ \r\n]|^) @m",'$1&nbsp;',$this->blockhtml($codes[2][$k]));
   }
   }
   $a=str_replace('[code]'.$k.'[/code]',$returnbb?'[code'.$codes[1][$k].']'.$codes[2][$k].'[/code]':'<div onclick="JAX.select(this)" class="code'.($codes[1][$k]?' '.$codes[1][$k]:'').'">'.$codes[2][$k].'</div>',$a);
  }
  return $a;
 }

 function hiddenFormFields($a){
  $r='';
  foreach($a as $k=>$v) $r.='<input type="hidden" name="'.$k.'" value="'.$v.'" />';
  return $r;
 }

 function textonly($a){
  while(($t=preg_replace("@\[(\w+)[^\]]*\]([\w\W]*)\[/\\1\]@U",'$2',$a))!=$a) $a=$t;;
  return $a;
 }

 function bbcodes($a,$minimal=false){
  $bbcodes=Array(
    '@\[b\](.*)\[/b\]@Usi'=>'<strong>$1</strong>',
    '@\[i\](.*)\[/i\]@Usi'=>'<em>$1</em>',
    '@\[u\](.*)\[/u\]@Usi'=>'<span style="text-decoration:underline">$1</span>',
    '@\[s\](.*)\[/s\]@Usi'=>'<span style="text-decoration:line-through">$1</span>',
    '@\[blink\](.*)\[/blink\]@Usi'=>'<span style="text-decoration:blink">$1</span>',
    '@\[url=(http|ftp|\?|mailto:)([^\]]+)\](.+?)\[/url\]@i'=>'<a href="$1$2" rel="nofollow">$3</a>',
	'@\[spoiler\](.*)\[/spoiler\]@Usi'=>'<span class="spoilertext">$1</span>',
    '@\[url\](http|ftp|\?)(.*)\[/url\]@Ui'=>'<a href="$1$2" rel="nofollow">$1$2</a>',
    '@\[font=([\s\w]+)](.*)\[/font\]@Usi'=>'<span style="font-family:$1">$2</span>',
    '@\[color=(#?[\s\w\d]+|rgb\([\d, ]+\))\](.*)\[/color\]@Usi'=>'<span style="color:$1">$2</span>',
    '@\[(bg|bgcolor|background)=(#?[\s\w\d]+)\](.*)\[/\\1\]@Usi'=>'<span style="background:$2">$3</span>'
	);

	if(!$minimal){
     $bbcodes['@\[h([1-5])\](.*)\[/h\\1\]@Usi']='<h$1>$2</h$1>';
     $bbcodes['@\[align=(center|left|right)\](.*)\[/align\]@Usi']='<p style="text-align:$1">$2</p>';
     $bbcodes['@\[img(?:=([^\]]+|))?\]((?:http|ftp)\S+)\[/img\]@i']='<img src="$2" title="$1" alt="$1" class="bbcodeimg" align="absmiddle" />';
     $a=preg_replace_callback('@\[video\](.*)\[/video\]@Ui',Array($this,'bbcode_videocallback'),$a);
	}
  $keys=array_keys($bbcodes);$values=array_values($bbcodes);
  while(($tmp=preg_replace($keys,$values,$a))!=$a) $a=$tmp;

  if($minimal) return $a;

  //ul/li tags
  while($a!=($tmp=preg_replace_callback('@\[(ul|ol)\](.*)\[/\\1\]@Usi',Array($this,"bbcode_licallback"),$a))) $a=$tmp;
  //size code (actually needs a callback simply because of the variability of the arguments)
  while($a!=($tmp=preg_replace_callback('@\[size=([0-4]?\d)(px|pt|em|)\](.*)\[/size\]@Usi',Array($this,"bbcode_sizecallback"),$a))) $a=$tmp;;

  //do quote tags
  while(preg_match('@\[quote(?>=([^\]]+))?\](.*?)\[/quote\]\r?\n?@is',$a,$m)&&$x<10) {$x++;$a=str_replace($m[0],'<div class="quote">'.($m[1]?'<div class="quotee">'.$m[1].'</div>':'').$m[2].'</div>',$a);}

  return $a;
 }

 function bbcode_sizecallback($m){
  return '<span style="font-size:'.$m[1].($m[2]?$m[2]:'px').'">'.$m[3].'</span>';
 }
 function bbcode_videocallback($m){
    if(strpos($m[1],"youtube")!==false) {
        preg_match('@t=(\d+m)?(\d+s)?@',$m[0],$time);
        preg_match('@v=([\w-]+)@',$m[1],$m);
        if($time){
            $m[2]=(($time[1]?substr($time[1],0,-1)*60:0)+substr($time[2],0,-1));
        }
        return '<div class="media youtube">
        <div class="summary">Watch Youtube Video: <a href="https://www.youtube.com/watch?v='.$m[1].($m[2]?'&t=':'').$m[2].'">https://youtube.com/watch?v='.$m[1].($m[2]?'&t=':'').$m[2].'</a></div>
        <div class="open"><a href="https://www.youtube.com/watch?v='.$m[1].'" onclick="var w=new JAX.window;w.title=this.href;w.content=$$(\'.movie\',this.parentNode.parentNode).innerHTML;w.create();return false;">Popout</a> &middot; <a href="https://www.youtube.com/watch?v='.$m[1].$m[2].'" onclick="$$(\'.movie\',this.parentNode.parentNode).style.display=\'block\';return false;">Inline</a></div>
        <div class="movie" style="display:none"><iframe width="560" height="315" frameborder="0" allowfullscreen="" src="https://www.youtube.com/embed/'.$m[1].'?start='.$m[2].'"></iframe></div>
        </div>';
    } else if(strpos($m[1],'vimeo')!==false) {
        preg_match('@(?:vimeo.com|video)/(\d+)@',$m[1],$id);
        return '<div class="media vimeo">
        <div class="summary">Watch Vimeo Video: <a href="https://vimeo.com/'.$id[1].'">https://vimeo.com/'.$id[1].'</a></div>
        <div class="open"><a href="https://vimeo.com/'.$id[1].'" onclick="var w=new JAX.window;w.title=this.href;w.content=$$(\'.movie\',this.parentNode.parentNode).innerHTML;w.create();return false;">Popout</a> &middot; <a href="https://vimeo.com/'.$id[1].'" onclick="$$(\'.movie\',this.parentNode.parentNode).style.display=\'block\';return false;">Inline</a></div>
        <div class="movie" style="display:none">
        <iframe src="https://player.vimeo.com/video/'.$id[1].'?title=0&byline=0&portrait=0" width="400" height="300" frameborder="0" webkitAllowFullScreen allowFullScreen></iframe>
        </div>
        </div>';

    } else {
        return '-Invalid Video URL-';
    }
 }

 function bbcode_licallback($m){
  $lis="";
  $m[2]=preg_split("@(^|[\r\n])\*@",$m[2]);
  foreach($m[2] as $v)
   if(trim($v))
    $lis.="<li>".$v." </li>";
  return "<".$m[1].">".$lis."</".$m[1].">";
 }

 function attachments($a){
  return $a=preg_replace_callback('@\[attachment\](\d+)\[/attachment\]@',Array($this,'attachment_callback'),$a,20);
 }
 function attachment_callback($a){
  global $DB,$CFG;
  $a=$a[1];
  if($this->attachmentdata[$a]) {
   $data=$this->attachmentdata[$a];
  } else {
   $result = $DB->safeselect("*","files","WHERE id=?",$a);
   $data=$DB->row($result);
   $DB->disposeresult($result);
   if(!$data) return "Attachment doesn't exist";
   else $this->attachmentdata[$a]=$data;
  }

  $ext=explode(".",$data['name']);
  if(count($ext)==1) $ext="";
  else $ext=strtolower(array_pop($ext));
  if(!in_array($ext,$CFG['images'])) $ext="";
  if($ext) $ext=".".$ext;

  if($ext) {
   return '<a href="'.BOARDPATHURL.'/Uploads/'.$data['hash'].$ext.'"><img src="'.BOARDPATHURL.'Uploads/'.$data['hash'].$ext.'" class="bbcodeimg" /></a>';
  } else {
   return '<div class="attachment"><a href="index.php?act=download&id='.$data['id'].'&name='.urlencode($data['name']).'" class="name">'.$data['name']."</a> Downloads: ".$data['downloads']."</div>";
  }
 }

 function theworks($a,$cfg=Array()){
  if (@!$cfg['nobb'] && @!$cfg['minimalbb']) $codes=$this->startcodetags($a);
  $a=$this->blockhtml($a);
  //$a=$this->wordfilter($a);
  //$a=$this->linkify($a); now linkifies before sendage
  if(@!$cfg['noemotes']) $a=$this->emotes($a);
  if(@!$cfg['nobb']) $a=$this->bbcodes($a,@$cfg['minimalbb']);
  if(@!$cfg['nobb'] && @!$cfg['minimalbb']) $a=$this->finishcodetags($a,$codes);
  if(@!$cfg['nobb'] && @!$cfg['minimalbb']) $a=$this->attachments($a);
  $a=$this->wordfilter($a);
  $a=nl2br($a);
  return $a;
 }

 function parse_activity($a,$rssversion=false){
  global $PAGE,$USER;
  $user=$PAGE->meta('user-link',$a['uid'],$a['group_id'],$USER['id']==$a['uid']?"You":$a['name']);
  $otherguy=$PAGE->meta('user-link',$a['aff_id'],$a['aff_group_id'],$a['aff_name']);
  $r='';
  switch($a['type']) {
   case "profile_comment":
    if($rssversion) $r=Array('text'=>$a['name']." commented on ".$a['aff_name']."'s profile",'link'=>'?act=vu'.$a['aff_id']);
    else $r=$user.' commented on '.$otherguy.'\'s profile';
   break;
   case "new_post":
    if($rssversion) $r=Array('text'=>$a['name']." posted in topic ".$a['arg1'],'link'=>'?act=vt'.$a['tid'].'&findpost='.$a['pid']);
    else $r=$user.' posted in topic <a href="?act=vt'.$a['tid'].'&findpost='.$a['pid'].'">'.$a['arg1'].'</a>, '.$this->smalldate($a['date']);
   break;
   case "new_topic":
    if($rssversion) $r=Array('text'=>$a['name'].' created new topic '.$a['arg1'],'link'=>'?act=vt'.$a['tid']);
    else $r=$user.' created new topic <a href="?act=vt'.$a['tid'].'">'.$a['arg1'].'</a>, '.$this->smalldate($a['date']);
   break;
   case "profile_name_change":
    if($rssversion) $r=Array('text'=>$a['arg1'].' is now known as '.$a['arg2'],'link'=>'?act=vu'.$a['uid']);
    else $r=$PAGE->meta('user-link',$a['uid'],$a['group_id'],$a['arg1'])." is now known as ".$PAGE->meta('user-link',$a['uid'],$a['group_id'],$a['arg2']).', '.$this->smalldate($a['date']);
   break;
   case "buddy_add":
    if($rssversion) $r=Array('text'=>$a['name'].' made friends with '.$a['aff_name'],'link'=>'?act=vu'.$a['uid']);
    else $r=$user.' made friends with '.$otherguy;
   break;
  }
  if($rssversion) {$r['link']=$this->blockhtml($r['link']);return $r;}
  return '<div class="activity '.$a['type'].'">'.$r.'</div>';
 }

 public static function pick(){
  $args=func_get_args();
  foreach($args as $v) {
      if($v) {
          break;
      }
  }
  return $v;
 }

 function isurl($url){
  return preg_match("@^https?://[\w\.\-%\&\?\=/]+$@",$url);
 }
 function isemail($email){
  return preg_match("/[\w\+.]+@[\w.]+/",$email);
 }

 function ipbanned($ip=false){
  if(!$ip) $ip=$_SERVER['REMOTE_ADDR'];
  global $PAGE;
  if(!isset($this->ipbancache)) {
   if($PAGE) $PAGE->debug("loaded ip ban list");
   $this->ipbancache=Array();
   if(file_exists(BOARDPATH."/bannedips.txt")) foreach(file(BOARDPATH."/bannedips.txt") as $v) {
    $v=trim($v);
    if($v&&$v[0]!="#") $this->ipbancache[]=$v;
   }
  }
  foreach($this->ipbancache as $v) {
    if(substr($v,-1)=="."&&substr($ip,0,strlen($v))==$v) return $v;
    else if($v==$ip) return $v;
  }
  return false;
 }

 function forumspammer($ip=false){
    if(!$ip) $ip=$_SERVER['REMOTE_ADDR'];
    $name=implode('.',array_reverse(explode('.',$ip))).'.opm.tornevall.org';
    return gethostbyname($name)!=$name;
 }

 function toruser($ip=false){
    if(!$ip) $ip=$_SERVER['REMOTE_ADDR'];
    $jax=$_SERVER['SERVER_ADDR'];
    $name=implode(".",array_reverse(explode(".",$jax.".80.".$ip))).".ip-port.exitlist.torproject.org";
    return gethostbyname($name)=="127.0.0.2";
 }

 function ip2int($ip=false){
  if(!$ip) $ip=$_SERVER['REMOTE_ADDR'];
  if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      // only IPv4 support exists right now
      return 0;
  }
  $int=0;
  foreach(explode(".",$ip) as $v) {$int*=256;$int+=$v;}
  return $int;
 }

 function parseperms($permstoparse,$uid=false){
  global $PERMS;
  $permstoparse .= '';
  if (!$permstoparse) {
      $permstoparse = '0';
  }
  if ($permstoparse) {
  if($uid!==false) {$unpack=unpack("n*",$permstoparse);$permstoparse=Array();for($x=1;$x<count($unpack);$x+=2) $permstoparse[$unpack[$x]]=$unpack[$x+1];$permstoparse=$permstoparse[$uid];}
  } else {
      $permstoparse = null;
  }
  if($permstoparse===NULL) return Array('upload'=>$PERMS['can_attach'],'reply'=>$PERMS['can_post'],'start'=>$PERMS['can_post_topics'],'read'=>1,'view'=>1,'poll'=>$PERMS['can_poll']);
  return Array('upload'=>$permstoparse&1,'reply'=>$permstoparse&2,'start'=>$permstoparse&4,'read'=>$permstoparse&8,'view'=>$permstoparse&16,'poll'=>$permstoparse&32);
 }

 function parsereadmarkers($readmarkers){
  $r=Array();
  if($readmarkers){
   $unpack=JAX::base128decode($readmarkers);
   $l=count($unpack);
   for($x=0;$x<$l;$x+=2) $r[$unpack[$x]]=$unpack[$x+1];
  }
  return $r;
 }

 function rmdir($dir){
  if(substr($dir,-1)!="/") $dir.="/";
  foreach(glob($dir."*") as $v) {
   if(is_dir($v)) $this->rmdir($v);
   else unlink($v);
  }
  rmdir($dir);
  return true;
 }

 function pages($numpages,$active,$tofill){
  $tofill-=2;
  $pages[]=1;
  if($numpages==1) return $pages;
  $start=$active-floor($tofill/2);
  if(($numpages-$start)<$tofill) $start-=($tofill-($numpages-$start));
  if($start<=1) {$start=2;}
  for($x=0;$x<$tofill&&($start+$x)<$numpages;$x++) $pages[]=$x+$start;

  $pages[]=$numpages;
  return $pages;
 }

 function filesize($bs){
  $p=0;
  $sizes=' KMGT';
  while($bs>1024) {
   $bs/=1024;
   $p++;
  }
  return round($bs,2).' '.($p?$sizes[$p]:'').'B';
 }

 function gethostbyaddr($ip){
  $ptr= implode(".",array_reverse(explode(".",$ip))).".in-addr.arpa";
  $host = dns_get_record($ptr,DNS_PTR);
  return !$host?$ip:$host[0]['target'];
 }

 public static function base128encodesingle($int){
  $int=(int)$int;
  $w=chr($int&127);
  while($int>127) {
   $int>>=7;
   $w=chr(($int&127)|128).$w;
  }
  return $w;
 }

 function base128encode($ints,$preservekeys=false){
  $r='';
  foreach($ints as $intkey=>$int)
   $r.=($preservekeys?JAX::base128encodesingle($intkey):'').JAX::base128encodesingle($int);;
  return $r;
 }

 public static function base128decode($data){
  $ints=Array();$x=0;
  while(isset($data[$x])){
   $int=0;
   do {
    $byte=(int)ord($data[$x]);
    if($x) $int<<=7;
    $int|=($byte&127);
    $x++;
   } while($byte&128);
   $ints[]=$int;
  }
  return $ints;
 }

 function mail($email,$topic,$message){
  global $CFG;
  $boardname=$CFG['boardname']?$CFG['boardname']:"JaxBoards";
  $boardurl="https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
  $boardlink="<a href='https://".$boardurl."'>".$boardname."</a>";
  return @mail(
  $email,
  $boardname.' - '.$topic,
  str_replace(
   Array("{BOARDNAME}","{BOARDURL}","{BOARDLINK}"),
   Array(   $boardname,   $boardurl,   $boardlink),
   $message
  ),
  "MIME-Version: 1.0\r\nContent-type:text/html;charset=iso-8859-1\r\nFrom: ".$CFG["mail_from"]."\r\n"
  );
 }
};?>
