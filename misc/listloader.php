<?
require("../config.php");
require("../inc/classes/mysql.php");
$DB=new MySQL;
$DB->connect($CFG['sql_host'],$CFG['sql_username'],$CFG['sql_password'],$CFG['sql_db']);

require("../domaindefinitions.php");
$list=Array(Array(),Array());
switch($_GET['act']) {
 case "searchmembers":
  $DB->select("id,display_name name","members","WHERE display_name LIKE ".$DB->evalue(htmlspecialchars(str_replace("_","\_",$_GET['term']),ENT_QUOTES)."%")." ORDER BY display_name LIMIT 10");
  while($f=$DB->row()) {$list[0][]=$f['id'];$list[1][]=$f['name'];}
 break;
 case "":
  
 break;
}
echo json_encode($list);
?>