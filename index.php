<?php
/*
Jaxboards. THE ULTIMATE 4UMS WOOOOOOO
By Sean John's son (2007 @ 4 AM)
*/

header("Cache-Control: no-cache, must-revalidate");
error_reporting(E_ALL ^ E_NOTICE);

$local=$_SERVER['REMOTE_ADDR']=='127.0.0.1';
$microtime=microtime(true);

/*get the config*/
require("config.php");

/*DB connect!*/
require_once "inc/classes/mysql.php";
$DB=new MySQL;
$DB->connect($CFG['sql_host'],$CFG['sql_username'],$CFG['sql_password'],$CFG['sql_db']);

/*Board Service Stuff, get the board as specified by URL*/
require_once "domaindefinitions.php";

/*Require the classes*/
foreach(Array("page","jax","sess") as $v) require_once "inc/classes/$v.php";

/*Initialize them*/
if($CFG['noboard']) die("board not found");

$PAGE=new PAGE;
$JAX=new JAX;
$SESS=new SESS($JAX->pick($JAX->c['sid'],$JAX->b['sessid']));

if(!$SESS->is_bot&&$JAX->c['uid']) $JAX->getUser($JAX->c['uid'],$JAX->c['pass']);

$USER=&$JAX->userData;
$PERMS=$JAX->getPerms();

/*fix ip if necessary*/
if($USER&&$SESS->ip!=$USER['ip']) $DB->safeupdate('members',Array('ip'=>$SESS->ip),'WHERE id=?', $USER['id']);

/*load the theme*/
$PAGE->loadskin($JAX->pick($SESS->vars['skin_id'],$USER['skin_id']));
$PAGE->loadmeta("global");

/*skin selector*/
if(isset($JAX->b['skin_id'])) {
if(!$JAX->b['skin_id']) {
 $SESS->delvar('skin_id');
 $PAGE->JS("script","document.location='?'");
} else {
$SESS->addvar('skin_id',$JAX->b['skin_id']);
if($PAGE->jsaccess) $PAGE->JS("script","document.location='?'");
}
}
if($SESS->vars['skin_id']) $PAGE->append('NAVIGATION','<div class="success" style="position:fixed;bottom:0;left:0;width:100%;">Skin UCP setting being overriden. <a href="?skin_id=0">Revert</a></div>');

// "Login"
// If they're logged in through cookies, (username & password)
// but the session variable has changed/been removed/not updated for some reason
// this fixes it.

if($JAX->userData&&!$SESS->is_bot){
 if($JAX->userData['id']!=$SESS->uid) {
  $SESS->clean($USER['id']);
  $SESS->uid=$USER['id'];
  $SESS->applychanges();
 }
}

// If the user's navigated to a new page, change their action time (they're alive!)
if($PAGE->jsnewlocation||!$PAGE->jsaccess) {
 $SESS->act($JAX->b['act']);
}


/*Set Navigation*/

$PAGE->path(Array($JAX->pick($CFG['boardname'],"Home")=>"?"));
$PAGE->append('TITLE',$JAX->pick($PAGE->meta('title'),$CFG['boardname'],"JaxBoards"));

if(!$PAGE->jsaccess) {
 foreach(Array("sound_im","wysiwyg") as $v) $variables[]="$v:".($USER?($USER[$v]?1:0):1);
 $variables[]="can_im:".($PERMS['can_im']?1:0);
 $variables[]="groupid:".($JAX->pick($USER['group_id'],3));
 $variables[]="username:'".addslashes($USER['display_name'])."'";
 $variables[]="userid:".$JAX->pick($USER['id'],0);

 $PAGE->append('SCRIPT',' <script type="text/javascript">var globalsettings={'.implode(',',$variables).'}</script>');
 $PAGE->append('SCRIPT',' <script type="text/javascript" src="'.BOARDURL.'Service/jsnew.js?v=1"></script>');
 $PAGE->append('SCRIPT',' <script type="text/javascript" src="'.BOARDURL.'Service/jsrun.js"></script>');
 $PAGE->append('SCRIPT','<!--[if IE]><style> img {behavior: url(Script/fiximgnatural.htc)}</style><![endif]-->');

 if($PERMS['can_moderate']||$USER['mod']) {
  //$PAGE->append("SCRIPT",'<script type="text/javascript" src="?act=modcontrols&do=load"></script>');
 }


 $PAGE->append('CSS','<link rel="stylesheet" type="text/css" href="'.THEMEPATH.'css.css" />');
 if($PAGE->meta('favicon')) $PAGE->append('CSS','<link rel="icon" href="'.$PAGE->meta('favicon').'">');
 $PAGE->append('LOGO',$PAGE->meta("logo",$JAX->pick($CFG['logourl'],BOARDURL.'Service/Themes/Default/img/logo.png')));
 $PAGE->append('NAVIGATION',$PAGE->meta("navigation",$PERMS['can_moderate']?'<li><a href="?act=modcontrols&do=cp">Mod CP</a></li>':'',$PERMS['can_access_acp']?'<li><a href="./acp/" target="_BLANK">ACP</a></li>':'',$CFG['navlinks']?$CFG['navlinks']:''));
 if($USER&&$USER['id']) {
  $result = $DB->safeselect("count(id)","messages","WHERE `read`=0 AND `to`=?", $USER['id']);
  $thisrow = $DB->row($result);
  $nummessages=array_pop($thisrow);
  $DB->disposeresult($result);
 }
$PAGE->addvar('inbox',$nummessages);
if($nummessages) $PAGE->append('FOOTER','<div id="notification" class="newmessage" onclick="RUN.stream.location(\'?act=ucp&what=inbox\');this.style.display=\'none\'">You have '.$nummessages.' new message'.($nummessages==1?'':'s').'</div>');
 if(!$CFG['nocopyright']) $PAGE->append('FOOTER','<div class="footer"><a href="http://jaxboards.com">Jaxboards 1.1.0</a> &copy; 2007-'.date('Y').'</div>');
 $PAGE->addvar('modlink',$PERMS['can_moderate']?$PAGE->meta('modlink'):'');
 $PAGE->addvar('ismod',$PERMS['can_moderate']?1:0);
 $PAGE->addvar('acplink',$PERMS['can_access_acp']?$PAGE->meta('acplink'):'');
 $PAGE->addvar('isadmin',$PERMS['can_access_acp']?1:0);
 $PAGE->addvar('boardname',$CFG['boardname']);
  $PAGE->append('USERBOX',
   ($USER['id']?$PAGE->meta('userbox-logged-in',$PAGE->meta('user-link',$USER['id'],$USER['group_id'],$USER['display_name']),$JAX->smalldate($USER['last_visit']),$nummessages):$PAGE->meta('userbox-logged-out'))
 );
} //end if !jsaccess only
 $PAGE->addvar('groupid',$JAX->pick($USER['group_id'],3));
 $PAGE->addvar('userposts',$USER['posts']);
 $PAGE->addvar('grouptitle',$PERMS['title']);
 $PAGE->addvar('avatar',$JAX->pick($USER['avatar'],$PAGE->meta('default-avatar')));
 $PAGE->addvar('username',$USER['display_name']);
 $PAGE->addvar('userid',$JAX->pick($USER['id'],0));

if($JAX->b['act']!="logreg"&&$JAX->b['act']!="logreg2"&&$JAX->b['act']!="logreg4"&&$JAX->b['act']!="logreg3"){
 if(!$PERMS['can_view_board']||$CFG['boardoffline']&&!$PERMS['can_view_offline_board']) $JAX->b['act']="boardoffline";
}

//include modules :3
foreach(glob("inc/modules/*.php") as $v) {
 if(preg_match("/tag_(\w+)/",$v,$m)) {
  if($JAX->b['module']==$m[1]||$PAGE->templatehas($m[1])) include $v;
 } else if (preg_match("/cookie_(\w+)/",$v,$m)) {
  if($JAX->b['module']==$m[1]||$JAX->c[$m[1]]) include $v;
 } else include($v);
}

//looks like it's straight out of IPB, doesn't it
$actraw=strtolower($JAX->b['act']);
preg_match("@^[a-zA-Z_]+@",$actraw,$act);
$act=array_shift($act);
$actdefs=Array(
""=>"idx",
"vf"=>"forum",
"vt"=>"topic",
"vu"=>"userprofile",
);
if($actdefs[$act]) $act=$actdefs[$act];
if($act=="idx"&&$JAX->b['module']) {
  //do nothing
} elseif($act&&is_file($act="inc/page/".$act.".php")) {
 require $act;
} elseif(!$PAGE->jsaccess||$PAGE->jsnewlocation) {
 $result = $DB->safeselect("page","pages","WHERE act=?", $DB->basicvalue($actraw));
 if($page=$DB->row($result)) {
  $DB->disposeresult($result);
  $page['page']=$JAX->bbcodes($page['page']);
  $PAGE->append("PAGE",$page['page']);
  if($PAGE->jsnewlocation) $PAGE->JS("update","page",$page['page']);
 } else $PAGE->location("?act=idx");
}

// Process temporary commands
if($PAGE->jsaccess&&$SESS->runonce){
 $PAGE->JSRaw($SESS->runonce);
 $SESS->runonce="";
}

// keeps people from leaving their windows open all night
if(($SESS->last_update-$SESS->last_action)>1200) $PAGE->JS("script","window.name=Math.random()");
//any changes to the session variables of the current user throughout the script are finally put into query form here
$SESS->applyChanges();

if(in_array($_SERVER['REMOTE_ADDR'],Array('127.0.0.1','***REMOVED***','***REMOVED***'))) {
 $debug="";
 foreach($DB->queryRuntime as $k=>$v) {$debug.="<b>$v</b> ".$DB->queryList[$k]."<br />";$qtime+=$v;}$debug.=$PAGE->debug()."<br />";
 $PAGE->JS("update","#query .content",$debug);
 $PAGE->append('FOOTER',$PAGE->collapsebox("Debug",$debug,"query")."<div id='debug2'></div><div id='pagegen'></div>");
 $PAGE->JS("update","pagegen",($pagegen="Page Generated in ".round(1000*(microtime(true)-$microtime))." ms"));
}
$PAGE->append('DEBUG',"<div id='pagegen' style='text-align:center'>".$pagegen."</div><div id='debug' style='display:none'></div>");

if($PAGE->jsnewlocation) $PAGE->JS("title",htmlspecialchars_decode($PAGE->get('TITLE'),ENT_QUOTES));
$PAGE->out();
?>
