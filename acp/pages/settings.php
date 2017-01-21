<?php
if(!defined(INACP)) die();

new settings;
class settings{

 function __construct(){$this->settings();}
 
 function settings(){
  global $JAX;
  $this->leftBar();
  switch(@$JAX->b['do']){
   case 'pages':
    $this->pages();
   break;
   case 'shoutbox':
    $this->shoutbox();
   break;
   case 'domains':
    $this->domainmanager();
   break;
   case 'global':
    $this->boardname();
   break;
   case 'birthday':
    $this->birthday();
   break;
   default:
    $this->showmain();
   break;
  }
 }
 
 
 function leftBar(){
  global $PAGE;
  $sidebar = "";
  foreach(
   Array(
    "?act=settings&do=global"=>"Global Settings",
    "?act=settings&do=shoutbox"=>"Shoutbox",
    "?act=settings&do=pages"=>"Custom Pages",
    "?act=settings&do=birthday"=>"Birthdays",
    "?act=settings&do=domains"=>"Domain Setup",
   ) as $k=>$v) {
   $sidebar.="<li><a href='$k'>$v</a></li>";
   }
   $sidebar="<ul>$sidebar</ul>";
  $PAGE->sidebar($sidebar);
 }
 function showmain(){
  global $PAGE;
  $PAGE->addContentBox("Error","This page is under construction!");
 }
 
 function boardname(){
  global $PAGE,$JAX;
  $page = "";
  if(@$JAX->p['submit']){
   if(trim($JAX->p['boardname'])==="") $e="Board name is required";
   else if(trim(@$JAX->p['logourl'])!==""&&!$JAX->isURL(@$JAX->p['logourl'])) $e="Please enter a valid logo url.";
   if($e) $page.=$PAGE->error($e);
   else {
    $write=Array();
    $write['boardname']=$JAX->p['boardname'];
    $write['logourl']=$JAX->p['logourl'];
	$write['boardoffline']=$JAX->p['boardoffline']?'0':'1';
    $write['offlinetext']=$JAX->p['offlinetext'];
    $PAGE->writeCFG($write);
    $page.=$PAGE->success("Settings saved!");
   }
  }
  $page.='<form method="post"><label>Board Name:</label><input type="text" name="boardname" value="'.$PAGE->getCFGSetting('boardname').'" /><br />
  <label>Logo URL:</label><input type="text" name="logourl" value="'.$PAGE->getCFGSetting('logourl').'" /><br />
  <input type="submit" value="Save" name="submit" />';
  $PAGE->addContentBox('Board Name/Logo',$page);$page="";
  
  $page.="<label>Board Online</label><input type='checkbox' name='boardoffline' class='switch yn'".(!$PAGE->getCFGSetting('boardoffline')?' checked="checked"':'')."'/><br />";
  $page.="<label style='vertical-align:top'>Offline Text:</label><textarea name='offlinetext'>".$JAX->blockhtml($PAGE->getCFGSetting('offlinetext'))."</textarea><br /><input type='submit' name='submit' value='Save' />";
  $page="$page</form>";
  $PAGE->addContentBox("Board Online/Offline",$page);
 }
 
 /*
  Pages
 */
 function pages(){
  global $DB,$PAGE,$JAX;
  $page = "";
  if(@$JAX->b['delete']) $this->pages_delete($JAX->b['delete']);
  if(@$JAX->b['page']){
   if(($newact=preg_replace('@\W@','<span style="font-weight:bold;color:#F00;">$0</span>',$JAX->b['page']))!=$JAX->b['page']) $e="The page URL must contain only letters and numbers. Invalid characters: $newact";
   elseif(strlen($newact)>25) $e="The page URL cannot exceed 25 characters.";
   else{
    return $this->pages_edit($newact);
   }
   if($e) $page.=$PAGE->error($e);
  }
  $result = $DB->safeselect("*","pages");
  $table="";
  while($f=$DB->row($result)) $table.='<tr><td>'.$f['act'].'</td><td><a href="../?act='.$f['act'].'">View</a></td><td><a href="?act=settings&do=pages&page='.$f['act'].'">Edit</a></td><td><a onclick="return confirm(\'You sure?\')" href="?act=settings&do=pages&delete='.$f['act'].'">Delete</a></td></tr>';
  if($table) $page.="<table class='pages'><tr><th>Act</th><th></th><th></th><th></th></tr>$table</table>";
  $page.="<form method='get'>".
  $JAX->hiddenFormFields(Array("act"=>"settings","do"=>"pages")).
  "<br />Add a new page at ?act=<input type='text' name='page' /><input type='submit' value='Go' /></form>";
  $PAGE->addContentBox('Custom Pages',$page);
 }
 function pages_delete($page){
  global $DB;
  return $DB->safedelete("pages","WHERE act=?", $DB->basicvalue($page));
 }
 function pages_edit($pageurl){
  global $PAGE,$DB,$JAX;
  $result = $DB->safeselect("*","pages","WHERE act=?", $DB->basicvalue($pageurl));
  $pageinfo=$DB->row($result);
  $DB->disposeresult($result);
  if($JAX->p['pagecontents']){
   if($pageinfo){
    $DB->safeupdate("pages",Array("page"=>$JAX->p['pagecontents']),"WHERE `act`=?", $DB->basicvalue($pageurl));
   } else {
    $DB->safeinsert("pages",Array("act"=>$pageurl,"page"=>$JAX->p['pagecontents']));
   }
   $pageinfo['page']=$JAX->p['pagecontents'];
   $page.=$PAGE->success("Page saved. Preview <a href='/?act=$pageurl'>here</a>");
  }
  $page.="<form method='post'>
  <textarea name='pagecontents' class='editor'>".$JAX->blockhtml($pageinfo['page'])."</textarea><br />
  <input type='submit' value='Save' />
  </form>";
  $PAGE->addContentBox("Editing Page: $pageurl",$page);
  
 }
 
 /*
  Shoutbox
 */
 function shoutbox(){
  global $PAGE,$JAX,$DB;
  $page = "";
  if(@$JAX->p['clearall']){
   $result = $DB->safespecial("TRUNCATE TABLE %t",array("shouts"));
   $page.=$PAGE->success("Shoutbox cleared!");
  }
  if(@$JAX->p['submit']){
   $write=Array('shoutbox'=>$JAX->p['sbe']?1:0,'shoutboxava'=>$JAX->p['sbava']?1:0);
   if(is_numeric($JAX->p['sbnum'])&&$JAX->p['sbnum']<=10&&$JAX->p['sbnum']>1) $write['shoutbox_num']=$JAX->p['sbnum'];
   else $e="Shouts to show must be between 1 and 10";
   $PAGE->writeCFG($write);
   if($e) $page.=$PAGE->error($e);
   else   $page.=$PAGE->success("Data saved.");
  }
  $page.='<form method="post">
  <label for="sbe">Shoutbox enabled:</label><input id="sbe" type="checkbox" name="sbe" class="switch yn"'.($PAGE->getCFGSetting('shoutbox')?' checked="checked"':'').' /><br />
  <label for="sbava">Shoutbox avatars:</label><input type="checkbox" name="sbava" class="switch yn" '.($PAGE->getCFGSetting('shoutboxava')?' checked="checked"':'').' /><br />
  <label for="sbnum">Shouts to show:<br />(Max 10)</label><input type="text" name="sbnum" class="slider" value="'.$PAGE->getCFGSetting('shoutbox_num').'" /><br />
  <br /><label for="clear">Wipe shoutbox:</label><input type="submit" name="clearall" value="Clear all shouts!" onclick="return confirm(\'Are you sure you want to wipe your shoutbox?\');"><br /><br />
  <input type="submit" name="submit" value="Save" /></form>';
  $PAGE->addContentBox('Shoutbox',$page);
 }
 
 function domainmanager(){
  global $DB,$CFG,$PAGE,$JAX;
  $page=$table="";
  if(@$JAX->p['submit']){
   if(strlen($JAX->p['domain'])>100) $e="Domain must be less than 100 characters";
   elseif(preg_match('@[^\w.]@',$JAX->p['domain'])) $e="Please enter a valid domain.";
   
   $result = $DB->safequery("SELECT * FROM jaxboards_service.domains WHERE domain=?", $DB->basicvalue($JAX->p['domain']));
   if($DB->row($result)) $e="That domain has already been claimed";
   $DB->disposeresult($result);
   if($e) $page.=$PAGE->error($e);
   else {
    $result = $DB->safequery("INSERT INTO jaxboards_service.domains(`domain`,`prefix`) VALUES( ?, ?);", $DB->basicvalue($JAX->p['domain']), $DB->basicvalue($CFG['prefix']));
    $page.=$PAGE->success("Domain added! Test <a href='http://".$JAX->blockhtml($JAX->p['domain'])."'>here.</a>");
   }
  } else if(@$JAX->b['delete']) {
   $result = $DB->safequery('DELETE FROM jaxboards_service.domains WHERE domain=? AND prefix=?;', $DB->basicvalue($JAX->b['delete']), $DB->basicvalue($CFG['prefix']));
   if($DB->affected_rows(1)) $page.=$PAGE->success("Domain deleted");
   else $page.=$PAGE->error("Error deleting domain, maybe it doesn't belong to you?");
  }
  $result = $DB->safequery("SELECT * FROM jaxboards_service.domains WHERE prefix=?;", $DB->basicvalue($CFG['prefix']));
  $domains=Array();
  while($f=$DB->row($result)) $domains[]=$f['domain'];
  if(empty($domains)){
   $table="<tr><td>No domains to show!</td></tr>";
  } else {
   foreach($domains as $v) $table.='<tr><td><a href="http://'.$v.'">'.$v.'</a></td><td><a class="icons delete" href="?act=settings&do=domains&delete='.urlencode($v).'"></a></td></tr>';
  }
  $page.="When connecting your A address, use the IP <a href='http://{$_SERVER['SERVER_ADDR']}'>".$_SERVER['SERVER_ADDR'].'</a>';
  $page.="<table>".$table."</table>";
  $page.='<form method="post">Add a domain: <input type="text" name="domain"  /><input type="submit" value="Add" name="submit"/></form>';
  $PAGE->addContentBox("Domain Manager",$page);
 }
 
 function birthday(){
  global $PAGE,$JAX;
  $birthdays=$PAGE->getCFGSetting('birthdays');
  if(@$JAX->p['submit']) {
   $PAGE->writeCFG(Array('birthdays'=>$birthdays=($JAX->p['bicon']?1:0)));
  }
  $page='<form method="post">';
  $page.="<label>Show Birthday Icon</label><input type='checkbox' class='switch yn' name='bicon'".($birthdays&1?" checked='checked'":'')."><br />";
  $page.='<input type="submit" value="Save" name="submit" />';
  $page.='</form>';
  $PAGE->addContentBox("Birthdays",$page);
 }
}
?>
