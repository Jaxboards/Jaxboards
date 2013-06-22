<?php
$PAGE->loadmeta("forum");

$IDX=new FORUM;
class FORUM{
 function FORUM(){$this->__construct();}
 function __construct(){
  global $JAX,$PAGE;
  
  $this->numperpage=20;
  $this->page=0;
  if(is_numeric($JAX->b['page'])&&$JAX->b['page']>0) $this->page=$JAX->b['page']-1;

  preg_match("@^([a-zA-Z_]+)(\d+)$@",$JAX->g['act'],$act);
  if($JAX->b['markread']) {
   $this->markread($act[2]);
   return $PAGE->location("?");
  }
  if($PAGE->jsupdate) $this->update();
  else if(is_numeric($JAX->b['replies'])) $this->getreplysummary($JAX->b['replies']);
  else $this->viewforum($act[2]);
 }
 function viewforum($fid){
  global $DB,$PAGE,$JAX,$PERMS,$USER;

  //if no fid supplied, go to the index and halt execution
  if(!$fid) return $PAGE->location("?");

  $page=$rows=$table="";

  $result = $DB->safespecial("SELECT f.*,c.title cat FROM %t f LEFT JOIN %t c ON f.cat_id=c.id WHERE f.id=$fid LIMIT 1", array("forums","categories"));
  $fdata=$DB->row($result);
  $DB->disposeresult($result);
  
  if(!$fdata) {$PAGE->JS("alert",$DB->error());return $PAGE->location("?");}
  
  if($fdata['redirect']){
   $PAGE->JS("softurl");
   $DB->safequery("UPDATE forums SET redirects = redirects + 1 WHERE id=?", $DB->basicvalue($fid));
   return $PAGE->location($fdata['redirect']);
  }
  
  
  $title=&$fdata['title'];

  $fdata['perms']=$JAX->parseperms($fdata['perms'],$USER?$USER['group_id']:3);
  if(!$fdata['perms']['read']) {$PAGE->JS("alert","no permission");return $PAGE->location("?");}

  /*NOW we can actually start building the page*/

  //subforums
  //right now, this loop also fixes the number of pages to show in a forum
  //parent forum - subforum topics = total topics
  //I'm fairly sure this is faster than doing SELECT count(*) FROM topics... but I haven't benchmarked it
  $result = $DB->safespecial("SELECT f.*,m.display_name lp_name,m.group_id lp_gid FROM %t f LEFT JOIN %t m ON f.lp_uid=m.id WHERE path='$fid' OR path LIKE '%% $fid' ORDER BY `order`",
	array("forums","members"));
  $rows="";
  while($f=$DB->row($result)) {
   $fdata['topics']-=$f['topics'];
   if($this->page) continue;
   $rows.=$PAGE->meta('forum-subforum-row',
     $f['id'],
     $f['title'],
     $f['subtitle'],
     $PAGE->meta('forum-subforum-lastpost',
      $f['lp_tid'],
      $JAX->pick($f['lp_topic'],"- - - - -"),
      $f['lp_name']?$PAGE->meta('user-link',$f['lp_uid'],$f['lp_gid'],$f['lp_name']):"None",
      $JAX->pick($JAX->date($f['lp_date']),"- - - - -")
     ),
     $f['topics'],
     $f['posts'],
     ($read=$this->isForumRead($f))?'read':'unread',
     $read?$JAX->pick($PAGE->meta('subforum-icon-read'),$PAGE->meta('icon-read')):$JAX->pick($PAGE->meta('subforum-icon-unread'),$PAGE->meta('icon-unread'))
    );
   if(!$read) $unread=true;
  }
  if($rows) $page.=$PAGE->collapsebox("Subforums",$PAGE->meta('forum-subforum-table',$rows));

  $rows=$table="";

  //generate pages
  $numpages=ceil($fdata['topics']/$this->numperpage);
  if($numpages) foreach($JAX->pages($numpages,$this->page+1,10) as $v) $forumpages.='<a href="?act=vf'.$fid.'&amp;page='.$v.'"'.(($v-1)==$this->page?' class="active"':'').'>'.$v.'</a> ';
  
  //buttons
  $forumbuttons="&nbsp;".($fdata['perms']['start']?'<a href="?act=post&amp;fid='.$fid.'">'.($PAGE->meta($PAGE->metaexists('button-newtopic')?'button-newtopic':'forum-button-newtopic')).'</a>':'');
  $page.=$PAGE->meta('forum-pages-top',$forumpages).$PAGE->meta('forum-buttons-top',$forumbuttons);

  //do order by
  //$orderby="lp_date DESC";
  $orderby="lp_date DESC";
  if($fdata['orderby']) {
   $fdata['orderby']=(int)$fdata['orderby'];
   if($fdata['orderby']&1) {
    $orderby="ASC";$fdata['orderby']--;
   } else $orderby="DESC";
   if($fdata['orderby']==2) $orderby="id ".$orderby;
   else if($fdata['orderby']==4) $orderby="title ".$orderby;
   else $orderby="lp_date ".$orderby;
  }
  
  //topics
  $result = $DB->safespecial("SELECT t.*,m.display_name lp_name,m.group_id lp_gid,m2.group_id auth_gid,m2.display_name auth_name FROM (SELECT * FROM %t WHERE fid=$fid ORDER BY pinned DESC,".$orderby." LIMIT ?,?) t LEFT JOIN %t m ON t.lp_uid = m.id LEFT JOIN %t m2 ON t.auth_id = m2.id",
	array('topics','members','members'),
	$this->page*$this->numperpage,
	$this->numperpage);

  //$DB->special("SELECT t.*,m.display_name lp_name,m.group_id lp_gid,m2.group_id auth_gid,m2.display_name auth_name FROM %t t LEFT JOIN %t m ON t.lp_uid=m.id LEFT JOIN %t m2 ON t.auth_id=m2.id WHERE fid=$fid ORDER BY t.pinned DESC,t.lp_date DESC LIMIT ".,"topics","members","members");
  while($f=$DB->row($result)) {
   $pages="";
   if($f['replies']>9) {
    foreach($JAX->pages(ceil(($f['replies']+1)/10),1,10) as $v) $pages.="<a href='?act=vt".$f['id']."&amp;page=$v'>$v</a> ";
    $pages=$PAGE->meta('forum-topic-pages',$pages);
   }
   $rows.=$PAGE->meta('forum-row',
    $f['id'], //1
    $JAX->wordfilter($f['title']), //2
    $JAX->wordfilter($f['subtitle']), //3
    $PAGE->meta('user-link',$f['auth_id'],$f['auth_gid'],$f['auth_name']), //4
    $f['replies'], //5
    number_format($f['views']), //6
    $JAX->date($f['lp_date']), //7
    $PAGE->meta('user-link',$f['lp_uid'],$f['lp_gid'],$f['lp_name']), //8
    ($f['pinned']?"pinned":"")." ".($f['locked']?"locked":""), //9
    $f['summary']?$f['summary'].(strlen($f['summary'])>45?"...":""):"", //10
    $PERMS['can_moderate']?'<a href="?act=modcontrols&do=modt&tid='.$f['id'].'" class="moderate" onclick="RUN.modcontrols.togbutton(this)"></a>':'', //11
    $pages, //12
    ($read=$this->isTopicRead($f,$fid))?'read':'unread', //13
    $read?$JAX->pick($PAGE->meta('topic-icon-read'),$PAGE->meta('icon-read')):$JAX->pick($PAGE->meta('topic-icon-unread'),$PAGE->meta('icon-read')) //14
   );
   if(!$read) $unread=true;
  }
  //if they're on the first page and no topics were marked as unread, mark the whole forum as read
  //since we don't care about pages past the first one
  if(!$this->page&&!$unread) $this->markread($fid);
  if ($rows) {
   $table=$PAGE->meta('forum-table',$rows);
  } else {
   if($this->page>0) return $PAGE->location('?act=vf'.$fid);
   else if($fdata['perms']['start']) $table=$PAGE->error("This forum is empty! Don't like it? <a href='?act=post&amp;fid=".$fid."'>Create a topic!</a>");
  }
  $page.=$PAGE->meta('box',' id="fid_'.$fid.'_listing"',$title,$table);
  $page.=$PAGE->meta('forum-pages-bottom',$forumpages);
  $page.=$PAGE->meta('forum-buttons-bottom',$forumbuttons);

  //start building the nav path
  $path[$fdata['cat']]="?act=vc".$fdata['cat_id'];
  if($fdata['path']) {
   $pathids=explode(" ",$fdata['path']);
   $forums=Array();
   $result = $DB->safeselect("title,id","forums","WHERE id IN ?", $pathids);
   while($f=$DB->row($result)) $forums[$f['id']]=Array($f['title'],"?act=vf".$f['id']);
   foreach($pathids as $v) $path[$forums[$v][0]]=$forums[$v][1];
  }
  $path[$title]="?act=vf$fid";
  $PAGE->updatepath($path);
  if($PAGE->jsaccess) $PAGE->JS("update","page",$page); else $PAGE->append("PAGE",$page);
 }
 function getreplysummary($tid){
  global $PAGE,$DB;
  $result = $DB->safespecial("SELECT m.display_name name,count(*) replies FROM %t p LEFT JOIN %t m ON p.auth_id=m.id WHERE tid=? GROUP BY p.auth_id ORDER BY replies DESC",
	array("posts","members"),
	$tid);
  $page="";
  while($f=$DB->row($result)) $page.='<tr><td>'.$f['name']."</td><td>".$f['replies'].'</td></tr>';
  $PAGE->JS("softurl");
  $PAGE->JS("window",Array("title"=>"Post Summary","content"=>'<table>'.$page.'</table>'));
 }
 function update(){
  //update the topic listing
 }
 
 function isTopicRead($topic,$fid){
  global $SESS,$USER,$JAX,$PAGE;
  if(!$this->topicsRead) {
   $this->topicsRead=$JAX->parsereadmarkers($SESS->topicsread);
  }
  if(!$this->forumsRead){
   $fr=$JAX->parsereadmarkers($SESS->forumsread);
   $this->forumReadTime=$fr[$fid];
  }
  if($topic['lp_date']>$JAX->pick(max($this->topicsRead[$topic['id']],$this->forumReadTime),$SESS->readtime,$USER['last_visit'])) return false;
  return true;
 }
 
 function isForumRead($forum){
  global $SESS,$USER,$JAX;
  if(!$this->forumsRead) {
   $this->forumsRead=$JAX->parsereadmarkers($SESS->forumsread);
  }
  if($forum['lp_date']>$JAX->pick($this->forumsRead[$forum['id']],$SESS->readtime,$USER['last_visit'])) return false;
  return true;
 }
 
 function markread($id){
  global $SESS;
  $forumsread=JAX::parsereadmarkers($SESS->forumsread);
  $forumsread[$id]=time();
  $SESS->forumsread=JAX::base128encode($forumsread,true);
 }
}
?>
