<?
if(!defined(INACP)) die();

new forums;
class forums{
 function __construct(){
  $this->forums();
 }
 function forums(){
  global $JAX,$PAGE;

  $links=Array("order"=>"Manage","create"=>"Create");
  $sidebar="";
  foreach($links as $k=>$v) $sidebar.='<li><a href="?act=forums&do='.$k.'">'.$v.'</a></li>';
  $PAGE->sidebar("<ul>".$sidebar."</ul>");

  if($JAX->b['delete']){
   if(is_numeric($JAX->b['delete'])) return $this->deleteforum($JAX->b['delete']);
   elseif(preg_match("@c_(\d+)@",$JAX->b['delete'],$m)) return $this->deletecategory($m[1]);
  } else if($JAX->b['edit']) {
   if(is_numeric($JAX->b['edit'])) return $this->createforum($JAX->b['edit']);
   elseif(preg_match("@c_(\d+)@",$JAX->b['edit'],$m)) return $this->createcategory($m[1]);
  }
  
  switch($JAX->g['do']) {
    case "order":$this->orderforums();break;
    case "create":$this->createforum();break;
    case "createc":$this->createcategory();break;
    default:$this->orderforums();break;
  }
 }

 function showindex(){
  global $PAGE;
  $PAGE->addContentBox("Forums","Yadda<br />Yadda<br />Yadda");
 }

 function orderforums($highlight=0){
  global $PAGE,$DB,$JAX;
  $page="";
  if($highlight) $page.="Forum Created. Now, just place it wherever you like!<br />";
  if($JAX->p['tree']) {
   $JAX->p['tree']=json_decode($JAX->p['tree'],true);
   $data=$this->mysqltree($JAX->p['tree']);
   if($JAX->g['do']=="create") return;
   $page.="<div class='success'>Data Saved</div>";
  }
  $forums=Array();
  $DB->select("*","categories","ORDER BY `order`,id ASC");
  while($f=$DB->row()) {$forums['c_'.$f['id']]=Array('title'=>$f['title']);$cats[]=$f['id'];}
  $DB->select("*","forums","ORDER BY `order`,title");
  $tree=Array();
  while($f=$DB->row()) {
   $forums[$f['id']]=Array('title'=>$f['title'],'trashcan'=>$f['trashcan'],"mods"=>$f['mods']);
   $treeparts=explode(" ",$f['path']);
   array_unshift($treeparts,'c_'.$f['cat_id']);
   $intree=&$tree;
   foreach($treeparts as $v){
    if(!trim($v)) continue;
    if(!is_array($intree[$v])) $intree[$v]=Array();
    $intree=&$intree[$v];
   }
   if(!$intree[$f['id']]) $intree[$f['id']]=true;
  }
  foreach($cats as $v) $sortedtree['c_'.$v]=$tree['c_'.$v];
  $page=$page.$this->printtree($sortedtree,$forums,"tree",$highlight)."<form method='post'><input type='hidden' id='ordered' name='tree' /><input type='submit' value='Save' /></form>";
  $page.="<script type='text/javascript'>JAX.sortableTree($$('.tree'),'forum_','ordered')</script>";
  $PAGE->addContentBox("Forums",$page);
 }
 
 //saves the posted tree to mysql
 function mysqltree($tree,$p='',$x=0){
  global $DB;
  $r=Array();
  if(!is_array($tree)) {
   return;
  } else {
   foreach($tree as $k=>$v){
    $k=substr($k,1);
    $x++;
	$p2=$p.$k." ";
	sscanf($p2,"c_%d",$cat);
	//$f=$p;
	$f=trim(strstr($p," "));
    if(is_array($v))
	 self::mysqltree($v,$p2." ",$x);
	if($k[0]=="c") $DB->update("categories",Array('order'=>$x),"WHERE id=$cat");
	else $DB->update("forums",Array('path'=>preg_replace("@\s+@"," ",$f),'order'=>$x,'cat_id'=>$cat),"WHERE id='".$k."'");
   }
  }
 }
 function printtree($t,$data,$class=false,$highlight=0){
  foreach($t as $k=>$v) {
   $classes=Array();
   if($k[0]=="c") $classes[]="parentlock";
   else $classes[]="nofirstlevel";
   if($highlight&&$k==$highlight) $classes[]="highlight";
   $r.="<li id='forum_$k' ".(!empty($classes)?'class="'.implode(" ",$classes).'"':'').">".
   ($data[$k]['trashcan']?'<span class="icons trashcan"></span>':'').
    $data[$k]['title'].
   ($data[$k]['mods']?" - <i>".($nummods=count(explode(',',$data[$k]['mods'])))." moderator".($nummods==1?"":"s")."</i>":"").
   " <a href='?act=forums&delete=$k' class='icons delete' title='Delete'></a> <a href='?act=forums&edit=$k' class='icons edit' title='Edit'></a>".
   (is_array($v)?self::printtree($v,$data,'',$highlight):"").
   "</li>";
  }
  $r="<ul ".($class?"class='$class'":"").">".$r."</ul>";
  return $r;
 }

 //also used to edit forum
 //btw, this function is so super messy right now but I don't care
 //I'm working 2 jobs and have NO TIME to clean this shit up
 //sorry if you have to see this abomination
 function createforum($fid=false){
  global $PAGE,$JAX,$DB;
  if($fid){
   $DB->select("*","forums","WHERE id=".$DB->evalue($fid));
   $fdata=$DB->row();
  }
  if(isset($JAX->p['tree'])){
   if($JAX->p['tree']) $this->orderforums();
   $page.=$PAGE->success("Forum created.");
  }
  if(is_numeric($JAX->b['rmod'])) {
   //remove mod from forum
   if($fdata['mods']) {
    $exploded=explode(",",$fdata['mods']);
    unset($exploded[array_search($JAX->b['rmod'],$exploded)]);
    $fdata['mods']=implode(",",$exploded);
    $DB->update("forums",Array("mods"=>$fdata['mods']),"WHERE id=".$DB->evalue($fid));
    $this->updateperforummodflag();
    $PAGE->location("?act=forums&edit=".$fid);
   }
  }
  
  if($JAX->p['submit']){
   //saves all of the shit
   //really should be its own function, but I don't gaf
   $grouppermsa=Array();$groupperms="";
   $DB->select("id","member_groups");
   while($f=$DB->row()) {$v=$JAX->p['groups'][$f['id']];if(!$v['global']) $grouppermsa[$f['id']]=($v['read']?8:0)+($v['start']?4:0)+($v['reply']?2:0)+($v['upload']?1:0)+($v['view']?16:0)+($v['poll']?32:0);}
   foreach($grouppermsa as $k=>$v) {$groupperms.=pack("n*",$k,$v);}
   $sub=$JAX->p['showsub'];
   if(is_numeric($JAX->p['orderby'])) $orderby=$JAX->p['orderby'];
   $write=Array(
    'title'=>$JAX->p['title'],
    'cat_id'=>$JAX->pick($fdata['cat_id'],array_pop($DB->row($DB->select("id","categories")))),
    'subtitle'=>$JAX->p['description'],
    'perms'=>$groupperms,
    'redirect'=>$JAX->p['redirect'],
    'show_sub'=>$sub==1||$sub==2?$sub:0,
    'nocount'=>$JAX->p['nocount']?0:1,
    'orderby'=>($orderby>0&&$orderby<=5)?$orderby:0,
    'trashcan'=>$JAX->p['trashcan']?1:0,
    'show_ledby'=>$JAX->p['show_ledby']?1:0,
    'mods'=>$fdata['mods'] //handling done below
    );
   //add per-forum moderator
   if(is_numeric($JAX->p['modid'])) {
    $DB->select("*","members","WHERE id=".$DB->evalue($JAX->p['modid']));
    if($DB->row()) {
     if(array_search($JAX->p['modid'],explode(',',$fdata['mods']))===false) {
     $write['mods']=$fdata['mods']?$fdata['mods'].','.$JAX->p['modid']:$JAX->p['modid'];
     }
    } else $e="You tried to add a moderator that doesn't exist!";
   }
   if(!$write['title']) $e="Forum title is required";
   
   if(!$e) {
    //clear trashcan on other forums
    if($write['trashcan']||(!$write['trashcan']&&$fdata['trashcan'])) $DB->update('forums',Array('trashcan'=>0));
    
    if($fdata) {
     $DB->update('forums',$write,'WHERE id='.$fid);
     if($JAX->p['modid']) $this->updateperforummodflag();
     $page.=$PAGE->success("Data saved.");
    } else {
     $DB->insert("forums",$write);
     return $this->orderforums($DB->insert_id());
    }
   }
   $fdata=$write;
  }
  
  //do perms table
  function checkbox($id,$name,$checked){return '<input type="checkbox" class="switch yn" name="groups['.$id.']['.$name.']" '.($checked?'checked="checked" ':'').($name=='global'?' onchange="globaltoggle(this.parentNode.parentNode,this.checked)" ':'').'/>';}
  if($fdata['perms']) {
   $unpack=unpack("n*",$fdata['perms']);
   for($x=1;$x<count($unpack);$x+=2) $perms[$unpack[$x]]=$unpack[$x+1];
  }
  $DB->select("*","member_groups");
  $groupperms="";
  while($f=$DB->row()) {
   $global=!isset($perms[$f['id']]);
   $p=$JAX->parseperms($perms[$f['id']]);
   $groupperms.='<tr><td>'.$f['title'].'</td><td>'.checkbox($f['id'],'global',$global).'</td><td>'.checkbox($f['id'],'view',$global?1:$p['view']).'</td><td>'.checkbox($f['id'],'read',$global?1:$p['read']).'</td><td>'.checkbox($f['id'],'start',$global?$f['can_post_topics']:$p['start']).'</td><td>'.checkbox($f['id'],'reply',$global?$f['can_post']:$p['reply']).'</td><td>'.checkbox($f['id'],'upload',$global?$f['can_attach']:$p['upload']).'</td><td>'.checkbox($f['id'],'poll',$global?$f['can_poll']:$p['poll']).'</td></tr>';
  }
  $page.=($e?$PAGE->error($e):"")."<form method='post'><table class='settings'>
<tr><td>Forum Title:</td><td><input type='text' name='title' value='".$JAX->blockhtml($fdata['title'])."' /></td></tr>
<tr><td>Description:</td><td><textarea name='description'>".$JAX->blockhtml($fdata['subtitle'])."</textarea></td></tr>
<tr><td>Redirect URL:</td><td><input type='text' name='redirect' value='".$JAX->blockhtml($fdata['redirect'])."' /></td></tr>
<tr><td>Show Subforums:</td><td><select name='showsub'>";
foreach(Array('Not at all','One level below','All subforums') as $k=>$v) $page.='<option value="'.$k.'"'.($k==$fdata['show_sub']?' selected="selected"':'').'>'.$v.'</option>';
$page.="</select></td></tr>
<tr><td>Order Topics By:</td><td><select name='orderby'>";
foreach(Array("Last Post, Descending","Last Post, Ascending","Topic Creation Time, Descending","Topic Creation Time, Ascending","Topic Title, Descending","Topic Title, Ascending") as $k=>$v) $page.="<option value='".$k."'".($fdata['orderby']==$k?" selected='selected'":"").">".$v."</option>";
$page.="</select></td></tr>
<tr><td>Posts count towards post count?</td><td><input type='checkbox' class='switch yn' name='nocount'".($fdata['nocount']?'':' checked="checked"')." /></td></tr>
<tr><td>Trashcan?</td><td><input type='checkbox' class='switch yn' name='trashcan'".($fdata['trashcan']?' checked="checked"':'')." /></td></tr>
</table>";

$moderators='<table class="settings">
<tr><td>Moderators:</td><td>';
if($fdata['mods']) {
 $DB->select("display_name,id","members","WHERE id IN (".$fdata['mods'].")");
 while($f=$DB->row()) $mods.=$f['display_name'].' <a href="?act=forums&edit='.$fid.'&rmod='.$f['id'].'">X</a>, ';
 $moderators.=substr($mods,0,-2);
} else $moderators.="No forum-specific moderators added!";
$moderators.='<br /><input type="text" name="name" onkeyup="$(\'validname\').className=\'bad\';JAX.autoComplete(\'act=searchmembers&term=\'+this.value,this,$(\'modid\'),event);" />
          <input type="hidden" id="modid" name="modid" onchange="$(\'validname\').className=\'good\'"/><span id="validname"></span><input type="submit" name="submit" value="Add Moderator" /></td></tr>
<tr><td>Show "Forum Led By":</td><td><input type="checkbox" class="switch yn" name="show_ledby" '.($fdata['show_ledby']?' checked="checked"':'').'/></td></tr>
</table>';

$forumperms.="<table id='perms'>
<tr> <th>Group</th> <th>Use Global?</th> <th>View</th> <th>Read</th> <th>Start</th> <th>Reply</th> <th>Upload</th> <th>Polls</th></tr>".
$groupperms.
"
</table><br /><div class='center'><input type='submit' value='".($fid?'Save':'Next')."' name='submit' /></div>
</form>
<script type='text/javascript'>
function globaltoggle(a,checked){
for(var x=0;x<6;x++) a.cells[x+2].style.visibility=checked?'hidden':'visible'
}
var perms=$('perms')
for(var x=1;x<perms.rows.length;x++){
 globaltoggle(perms.rows[x],perms.rows[x].getElementsByTagName('input')[0].checked)
}
</script>";

  $PAGE->addContentBox(($fid?'Edit':'Create').' Forum'.($fid?' - '.$JAX->blockhtml($fdata['title']):''),$page);
  $PAGE->addContentBox("Moderators",$moderators);
  $PAGE->addContentBox("Forum Permissions",$forumperms);
 }
 
 function deleteforum($id){
  global $JAX,$DB,$PAGE;
  if($JAX->p['submit']=="Cancel"){
   $PAGE->location("?act=forums&do=order");
  } else if($JAX->p['submit']) {
   $DB->delete('forums','WHERE id='.$DB->evalue($id));
   if($JAX->p['moveto']) {
    $DB->update('topics',Array('fid'=>$JAX->p['moveto']),' WHERE fid='.$DB->evalue($id));
    $topics=$DB->affected_rows();
   } else {
    $DB->special("DELETE FROM %t WHERE tid IN (SELECT id FROM %t WHERE fid=".$DB->evalue($id).')',"posts","topics");
    $posts=$DB->affected_rows();
    $DB->delete("topics",'WHERE fid='.$DB->evalue($id));
    $topics=$DB->affected_rows();
   }
   $page.=($JAX->p['moveto']?'Moved':'Deleted')." ".$topics." topics".($posts?" and $posts posts":"");
   return $PAGE->addContentBox("Forum Deletion",$PAGE->success($page."<br /><br /><a href='?act=stats'>Statistics recount</a> suggested.<br /><br /><a href='?act=forums&do=order'>Back</a>"));
  }
  $DB->select("*","forums","WHERE id=".$DB->evalue($id));
  $fdata=$DB->row();
  if(!$fdata) $page="Forum doesn't exist.";
  else {
   $page="<form method='post'><input type='submit' name='submit' value='Delete' /></form>";
  }
  $DB->select('*','forums');
  $forums.='<option value="">Nowhere! (delete)</option>';
  while($f=$DB->row()) $forums.='<option value="'.$f['id'].'">'.$f['title'].'</option>';
  $page="<form method='post'>Move all topics to: <select name='moveto'>".$forums."</select><br /><br /><input name='submit' type='submit' value='Confirm Deletion' /><input name='submit' type='submit' value='Cancel' /></form>";
  $PAGE->addContentBox("Deleting Forum: ".$fdata['title'],$page);
 }
 
 function createcategory($cid=false){
  global $JAX,$DB,$PAGE;
  if($cid) {
   $DB->select("*","categories","WHERE id=".$DB->evalue($cid));
   $cdata=$DB->row();
  }
  if($JAX->p['submit']) {
   if(!trim($JAX->p['cat_name'])) $page.=$PAGE->error("All fields required");
   else{
    $stuff=Array("title"=>$JAX->p['cat_name']);
    if($cdata) $DB->update("categories",$stuff,"WHERE id=".$DB->evalue($cid));
    else {
     $DB->insert("categories",$stuff);
    }
    $cdata=$stuff;
    
    $page.=$PAGE->success("Category ".($cdata?"edit":"creat")."ed.");
   }
  }
  $page.='<form method="post">
  <label>Category Title:</label><input type="text" name="cat_name" value="'.$JAX->blockhtml($cdata['title']).'" /><br />
  <input type="submit" name="submit" value="'.($cdata?"Edit":"Create").'" />
  </form>';
  
  $PAGE->addContentBox(($cdata?'Edit':'Create').' Category',$page);
 }
 function deletecategory($id){
  global $PAGE,$DB,$JAX;
  $page='';
  $DB->select("*","categories");
  $categories=Array();
  $cattitle=false;
  while($f=$DB->arow()) if($f['id']!=$id) $categories[$f['id']]=$f['title']; else $cattitle=$f['title'];
  if($cattitle===false) $e="The category you're trying to delete does not exist.";
  
  if(!$e&&$JAX->p['submit']){
   if(!isset($categories[$JAX->p['moveto']])) $e="Invalid category to move forums to.";
   else {
    $DB->update('forums',Array('cat_id'=>$JAX->p['moveto']),'WHERE cat_id='.$DB->evalue($id));
    $DB->delete('categories','WHERE id='.$DB->evalue($id));
    $page.=$PAGE->success('Category deleted!');
   }
  }
  if(empty($categories)) $e="You cannot delete the only category you have left.";
  if($e) $page.=$PAGE->error($e);
  else {
   $page.='<form method="post"><label>Move all forums to:</label><select name="moveto">';
   foreach($categories as $k=>$v) $page.='<option value="'.$k.'">'.$v.'</option>';
   $page.='</select><br /><input type="submit" value="Delete \''.JAX::blockhtml($cattitle).'\'" name="submit" /></select></form>';
  }
  $PAGE->addContentBox("Category Deletion",$page);
 }
 
 //this function updates all of the user->mod flags that specify whether or not a user is a per-forum mod
 //based on the comma delimited list of mods for each forum
 function updateperforummodflag(){
  global $DB;
  $DB->update("members",Array("mod"=>0));
  $DB->select("mods","forums");
  //build an array of mods
  $mods=Array();
  while($f=$DB->row()) foreach(explode(',',$f['mods']) as $v) if($v) $mods[$v]=1;
  //update
  $DB->update("members",Array("mod"=>1),"WHERE id IN(".implode(',',array_keys($mods)).")");
 }
}
?>