<?php

if (0) {
//this file must be required after mysql connecting
preg_match("@(.*)\.jaxboards\.com@i",$_SERVER['SERVER_NAME'],$m);
$prefix=($_SERVER['SERVER_NAME']=="127.0.0.1"||$_SERVER['SERVER_NAME']=="***REMOVED***.afraid.org")?"blueprint":$m[1];
if(!$prefix) {
 if(!$DB) {
  require_once "inc/classes/mysql.php";
  $DB=new MySQL;
  $DB->connect($CFG['sql_host'],$CFG['sql_username'],$CFG['sql_password'],$CFG['sql_db']);
 }
 $result = $DB->safespecial('SELECT prefix FROM jaxboards_service.domains WHERE domain=?', array(),
	$DB->basicvalue($_SERVER['SERVER_NAME']));
 $prefix=$DB->row($result);
 $DB->disposeresult($result);

 if($prefix) $prefix=$prefix['prefix'];
}
if($prefix){
 define("BOARDPATH",(defined("INACP")?"../":"")."boards/".$prefix."/");
 define("STHEMEPATH",(defined("INACP")?"../":"")."Service/Themes/");
 $CFG['prefix']=$prefix;
 if($DB) $DB->prefix($prefix.'_');
 function extendconfig($configfile){
  if(!@include($configfile)) return false;
  foreach($CFG as $k=>$v) $GLOBALS['CFG'][$k]=$v;
  return true;
 }
 if(!extendconfig(BOARDPATH."config.php")) $CFG=Array('noboard'=>1);
} else $CFG=Array('noboard'=>1);

} else {
    function extendconfig($configfile){
        if(!@include($configfile)) return false;
        foreach($CFG as $k=>$v) $GLOBALS['CFG'][$k]=$v;
        return true;
    }
    // define("BOARDPATH",(defined("INACP")?"../":"")."boards/");
    // define("STHEMEPATH",(defined("INACP")?"../":"")."Service/Themes/");
    define("BOARDPATH",(defined("INACP")?"../":"./"));
    define("STHEMEPATH",(defined("INACP")?"../":"")."Service/Themes/");
    date_default_timezone_set("America/Los_Angeles");
    $DB->prefix("jaxboards_");
    if (!extendconfig(BOARDPATH."config.php")) { die("Could not read config file.\n"); }
    // die("USERNAME: ".$CFG['sql_username']."\n");
    // die("USERNAME: ".$CFG['sql_password']."\n");
    // die("USERNAME: ".$CFG['sql_db']."\n");
    if(!$DB) {
        require_once "inc/classes/mysql.php";
        $DB=new MySQL;
        $DB->connect($CFG['sql_host'],$CFG['sql_username'],$CFG['sql_password'],$CFG['sql_db']);
    }
    // $DB->safespecial('SELECT prefix FROM jaxboards_service.domains WHERE domain=?', $DB->basicvalue($_SERVER['SERVER_NAME']));
}

define("FLAGPATH","http://jaxboards.com/flags/");
?>
