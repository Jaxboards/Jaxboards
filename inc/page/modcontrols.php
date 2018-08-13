<?php
$PAGE->loadmeta('modcp');

new modcontrols;
 class modcontrols{
  /* Redundant constructor unnecesary in newer PHP versions. */
  /* function modcontrols(){$this->__construct();} */
  function __construct(){
   global $JAX,$PAGE,$USER;

   $this->perms=$JAX->getPerms();
   if(!$this->perms['can_moderate']&&!$USER['mod']) {
    $PAGE->JS("softurl");
    return $PAGE->JS("alert","what the FUCK do you think you're doing, punk?");
   }
   if($JAX->b['cancel']) return $this->cancel();

   if($PAGE->jsupdate&&empty($JAX->p)) return false;


   if($JAX->p['dot']) return $this->dotopics($JAX->p['dot']);
   if($JAX->p['dop']) return $this->doposts($JAX->p['dop']);
   switch($JAX->b['do']){
    case "modp":$this->modpost($JAX->b['pid']);break;
    case "modt":$this->modtopic($JAX->b['tid']);break;
    case "load":$this->load();break;
    case "cp":$this->showmodcp();break;
    case "emem":$this->editmembers();break;
    case "iptools":$this->iptools();break;
   }
  }

  function dotopics($do){
  global $PAGE,$SESS,$JAX,$DB;
   switch($do){
    case 'move':$PAGE->JS('modcontrols_move',0);break;
    case 'moveto':
     $result = $DB->safeselect('*','forums','WHERE id=?', $DB->basicvalue($JAX->p['id']));
     $rowfound = $DB->row($result);
     $DB->disposeresult($result);
     if(!is_numeric($JAX->p['id'])|| !$rowfound) return;

     $result = $DB->safeselect("fid","topics","WHERE id IN ?", explode(",", $SESS->vars['modtids']));
     while($f=$DB->row($result)) $fids[$f['fid']]=1;

     $fids=array_flip($fids);
     $DB->safeupdate("topics",Array("fid"=>$JAX->p['id']),"WHERE id IN ?", explode(",", $SESS->vars['modtids']));
      $this->cancel();
     $fids[]=$JAX->p['id'];
     foreach($fids as $v) $DB->fixForumLastPost($v);
     $PAGE->location("?act=vf".$JAX->p['id']);
    break;
    case 'pin':$DB->safeupdate("topics",Array("pinned"=>1),"WHERE id IN ?", explode(",", $SESS->vars['modtids']));$PAGE->JS("alert","topics pinned!");$this->cancel();break;
    case 'unpin':$DB->safeupdate("topics",Array("pinned"=>0),"WHERE id IN ?", explode(",", $SESS->vars['modtids']));$PAGE->JS("alert","topics unpinned!");$this->cancel();break;
    case 'lock':$DB->safeupdate("topics",Array("locked"=>1),"WHERE id IN ?", explode(",", $SESS->vars['modtids']));$PAGE->JS("alert","topics locked!");$this->cancel();break;
    case 'unlock':$DB->safeupdate("topics",Array("locked"=>0),"WHERE id IN ?", explode(",", $SESS->vars['modtids']));$PAGE->JS("alert","topics unlocked!");$this->cancel();break;
    case 'delete':$this->deletetopics();$this->cancel();break;
    case 'merge':$this->mergetopics();break;
   }
  }

  function doposts($do){
   global $PAGE,$JAX,$SESS,$DB;
   switch($do){
    case 'move':$PAGE->JS('modcontrols_move',1);break;
    case 'moveto':
     $DB->safeupdate("posts",Array("tid"=>$JAX->p['id']),"WHERE id IN ?", explode(",", $SESS->vars['modpids']));
     $this->cancel();
     $PAGE->location("?act=vt".$JAX->p['id']);
    break;
    case 'delete':$this->deleteposts();$this->cancel();break;
   }
  }

  function cancel(){
   global $SESS,$PAGE;
   if($SESS->vars['modpids']) $SESS->delvar("modpids");
   if($SESS->vars['modtids']) $SESS->delvar("modtids");
   $this->sync();
   $PAGE->JS("modcontrols_clearbox");
  }

  function modpost($pid){
   global $PAGE,$SESS,$DB,$USER;
   if(!is_numeric($pid)) return;

   $result = $DB->safeselect("*","posts","WHERE id=?", $DB->basicvalue($pid));
   $postdata=$DB->row($result);
   $DB->disposeresult($result);

   if(!$postdata) return;
   elseif($postdata['newtopic']) return $this->modtopic($postdata['tid']);

   $PAGE->JS("softurl");

   //see if they have permission to manipulate this post
   if(!$this->perms['can_moderate']) {
    $result = $DB->safespecial("SELECT mods FROM %t WHERE id=(SELECT fid FROM %t WHERE id=?)",
	array("forums","topics"),
	$postdata['tid']);

    $mods=$DB->row($result);
    $DB->disposeresult($result);

    if(!$mods) return;
    else $mods=explode(',',$mods['mods']);
    if(!in_array($USER['id'],$mods)) return $PAGE->JS("error","You don't have permission to be moderating in this forum");
   }


   //push the PID onto the list of PIDs they're workin with
   //I feel sorry for the poor bastard that tries to mod my code, everything looks like this

   //LITTLE DID YOU KNOW, PAST SELF, THAT FUTURE SELF WOULD BE WORKING ON IT
   //THANKS ALOT ASSHOLE
   $exploded=explode(',',$SESS->vars['modpids']);
   if(($placement=array_search($pid,$exploded))===false) $SESS->addVar("modpids",$SESS->vars['modpids']?$SESS->vars['modpids'].','.$pid:$pid);
   else {
    unset($exploded[$placement]);
    $SESS->addVar("modpids",implode(',',$exploded));
   }

   $this->sync();
  }
  function modtopic($tid){
   global $PAGE,$SESS,$DB,$USER,$PERMS;
   $PAGE->JS("softurl");
   if(!is_numeric($tid)) return;
   if(!$PERMS['can_moderate']) {
    $result = $DB->safespecial("SELECT mods FROM %t WHERE id=(SELECT fid FROM %t WHERE id=?)",
	array("forums","topics"),
	$DB->basicvalue($tid));
    $mods=$DB->row($result);
    $DB->disposeresult($result);

    if(!$mods) return $PAGE->JS("error",$DB->error());
    else $mods=explode(',',$mods['mods']);
    if(!in_array($USER['id'],$mods)) return $PAGE->JS("error","You don't have permission to be moderating in this forum");
   }
   $exploded=explode(",",$SESS->vars['modtids']);
   if(($placement=array_search($tid,$exploded))===false) $SESS->addVar("modtids",$SESS->vars['modtids']?$SESS->vars['modtids'].','.$tid:$tid);
   else {
    unset($exploded[$placement]);
    $SESS->addVar("modtids",implode(',',$exploded));
   }
   $this->sync();
  }

  function sync(){
   global $SESS,$PAGE;
   $PAGE->JS("modcontrols_postsync",$SESS->vars['modpids'],$SESS->vars['modtids']);
  }

  function deleteposts(){
   global $SESS,$PAGE,$DB,$USER;
   if(!$SESS->vars['modpids']) return $PAGE->JS("error","No posts to delete.");

   //get trashcan
   $result = $DB->safeselect("id","forums","WHERE trashcan=1 LIMIT 1");
   $trashcan=$DB->row($result);
   $DB->disposeresult($result);

   $result = $DB->safeselect("tid","posts","WHERE id IN ?", explode(",", $SESS->vars['modpids']));

   //build list of tids that the posts were in
   $tids=Array();
   $pids=explode(",",$SESS->vars['modpids']);
   while($f=$DB->row($result)) $tids[$f[0]]=1;

   if($trashcan) {
    //get first & last post
    foreach($pids as $v) {
     if(!$op||$v<$op) $op=$v;
     if(!$lp||$v>$lp) $lp=$v;
    }
    $result = $DB->safeselect("posts","WHERE id=?", $DB->basicvalue($lp));
    $lp=$DB->row($result);
    $DB->disposeresult($result);

    //create a new topic
    $DB->safeinsert("topics",Array(
     'title'=>'Posts deleted from: '.implode(',',array_keys($tids)),
     'op'=>$op,
     'auth_id'=>$USER['id'],
     'fid'=>$trashcan[0],
     'lp_date'=>time(),
     'lp_uid'=>$lp['auth_id'],
     'replies'=>0
    ));
    $tid=$DB->insert_id(1);
    $DB->safeupdate("posts",Array("tid"=>$tid,"newtopic"=>0),"WHERE id IN ?", explode(",", $SESS->vars['modpids']));
    $DB->safeupdate("posts",Array("newtopic"=>1),"WHERE id=?", $DB->basicvalue($op));
    $tids[]=$tid;

   } else {
    $DB->safedelete("posts","WHERE id IN ?", explode(",", $SESS->vars['modpids']));
   }
   foreach($tids as $k=>$v){
    //recount replies
    $DB->safespecial("UPDATE %t SET replies=(SELECT count(*) FROM %t WHERE tid=?)-1 WHERE id=?",
	array("topics","posts"), $k, $k);
   }
   //remove them from the page
   foreach($pids as $v) $PAGE->JS("removeel","#pid_".$v);
  }

  function deletetopics(){
   global $SESS,$DB,$PAGE;
   if(!$SESS->vars['modtids']) return $PAGE->JS("error","No topics to delete");
   $data=Array();

   //get trashcan id
   $result = $DB->safeselect("id","forums","WHERE trashcan=1 LIMIT 1");
   $trashcan=$DB->row($result);
   $DB->disposeresult($result);

   $trashcan=$trashcan?$trashcan['id']:false;
   $result = $DB->safeselect("fid,id","topics","WHERE id IN ?", explode(",", $SESS->vars['modtids']));
   $delete=Array();
   while($f=$DB->row($result)){
    $data[$f[0]]++;
    if($trashcan&&$trashcan==$f[0]) $delete[]=$f['id'];
   }
   if($trashcan) {
    $DB->safeupdate("topics",Array("fid"=>$trashcan),"WHERE id IN ?", explode(",", $SESS->vars['modtids']));
    $delete=implode(',',$delete);
    $data[$trashcan]=1;
   } else {
    $delete=$SESS->vars['modtids'];
   }
   if(!empty($delete)){
    $DB->safedelete("posts","WHERE tid IN ?", explode(",", $delete));
    $DB->safedelete("topics","WHERE id IN ?", explode(",", $delete));
   }
   foreach($data as $k=>$v){
    $DB->fixForumLastPost($k);
   }
   $SESS->delvar("modtids");
   $PAGE->JS("modcontrols_clearbox");
   $PAGE->JS("alert","topics deleted!");
  }

  function mergetopics(){
   global $SESS,$DB,$PAGE,$JAX;
   $exploded=explode(",",$SESS->vars['modtids']);
   if(is_numeric($JAX->p['ot'])&&in_array($JAX->p['ot'],$exploded)){
    //move the posts and set all posts to normal (newtopic=0)
    $DB->safeupdate("posts",Array('tid'=>$JAX->p['ot'],'newtopic'=>'0'),"WHERE tid IN ?", explode(",", $SESS->vars['modtids']));

	//make the first post in the topic have newtopic=1
	 //get the op
	 $result = $DB->safeselect("min(id)","posts","WHERE tid=?", $DB->basicvalue($JAX->p['ot']));
	 $thisrow = $DB->row($result);
	 $op=array_pop($thisrow);
         $DB->disposeresult($result);

	 $DB->safeupdate("posts",Array("newtopic"=>1),"WHERE id=?", $op);

	//also fix op
	$DB->safeupdate("topics",Array("op"=>$op),"WHERE tid=?", $DB->basicvalue($JAX->p['ot']));
	unset($exploded[array_search($JAX->p['ot'],$exploded)]);
	$DB->safedelete("topics","WHERE id IN ?", $exploded);
	$PAGE->location("?act=vt".$JAX->p['ot']);
	$this->cancel();
   }
   $page.='<form method="post" onsubmit="return RUN.submitForm(this)" style="padding:10px;">Which topic should the topics be merged into?<br />';
   $page.=$JAX->hiddenFormFields(Array('act'=>'modcontrols','dot'=>'merge'));

   $result = $DB->safeselect("id,title","topics","WHERE id IN ?", explode(",", $SESS->vars['modtids']));
   $titles=Array();
   while($f=$DB->row($result)) $titles[$f['id']]=$f['title'];
   foreach($exploded as $v) {
    $page.='<input type="radio" name="ot" value="'.$v.'" /> '.$titles[$v].'<br />';
   }
   $page.='<input type="submit" value="Merge" /></form>';
   $page=$PAGE->collapsebox('Merging Topics',$page);
   $PAGE->JS("update","page",$page);
   $PAGE->append("page",$page);
  }

  function banposts(){
   global $PAGE;
   $PAGE->JS("alert","under construction");
  }

  public static function load(){
   global $PAGE;
   $script=file_get_contents("Script/modcontrols.js");
   if($PAGE&&$PAGE->jsaccess){
    $PAGE->JS("softurl");
    $PAGE->JS("script",$script);
   } else {
    header("Content-Type: application/javascript; charset=utf-8");
    header("Expires: ".gmdate("D, d M Y H:i:s", time() + 2592000)." GMT");
    die($script);
   }
  }

  function showmodcp($cppage=''){
   global $PAGE,$PERMS;
   if(!$PERMS['can_moderate']) return;
   $page=$PAGE->meta('modcp-index',$cppage);
   $page=$PAGE->meta('box',' id="modcp"','Mod CP',$page);
   $PAGE->append("page",$page);
   $PAGE->JS("update","page",$page);
  }

  function editmembers(){

   global $PAGE,$JAX,$DB,$USER,$PERMS;
   if(!$PERMS['can_moderate']) return;
   $page='<form method="post" onsubmit="return RUN.submitForm(this)">'.
    $JAX->hiddenFormFields(Array('submit'=>"showform",'act'=>'modcontrols','do'=>'emem')).
    'Member name: <input type="text" name="mname" onkeyup="$(\'validname\').className=\'bad\';JAX.autoComplete(\'act=searchmembers&term=\'+this.value,this,$(\'mid\'),event);" />
    <span id="validname"></span>
    <input type="hidden" name="mid" id="mid" onchange="$(\'validname\').className=\'good\';this.form.onsubmit();" />
    <input type="submit" value="Go" />
    </form>';
   if($JAX->p['submit']=="save") {
    if(!trim($JAX->p['display_name'])) $page.=$PAGE->meta('error',"Display name is invalid.");
    else {
     $DB->safeupdate("members",Array("sig"=>$JAX->p['signature'],"display_name"=>$JAX->p['display_name'],'about'=>$JAX->p['about'],'avatar'=>$JAX->p['avatar']),"WHERE id=?", $DB->basicvalue($JAX->p['mid']));
     if($DB->affected_rows(1)<0) $page.=$PAGE->meta('error',"Error updating profile information.");
     else $page.=$PAGE->meta('success',"Profile information saved.");
    }
   }
   if($JAX->p['submit']=="showform"||isset($JAX->b['mid'])){
    //get the member data
    if(is_numeric($JAX->b['mid'])) {
     $result = $DB->safeselect("*","members","WHERE id=?", $DB->basicvalue($JAX->b['mid']));
     $data=$DB->arow($result);
     $DB->disposeresult($result);
    } elseif($JAX->p['mname']) {
     $result = $DB->safeselect("*","members","WHERE display_name LIKE ?", $DB->basicvalue($JAX->p['mname']."%"));
     $data=Array();
     while($f=$DB->arow($result)) $data[]=$f;
     if(count($data)>1) $e="Many users found!";
     else $data=array_shift($data);
    } else $e="Member name is a required field.";

    if(!$data) $e="No members found that matched the criteria.";
    if($data['can_moderate']&&$USER['group_id']!=2||$data['group_id']==2&&($USER['id']!=1&&$data['id']!=$USER['id'])) $e="You do not have permission to edit this profile.";

    if($e) $page.=$PAGE->meta('error',$e);
    else {
     function field($label,$name,$value,$type='input'){
      return '<tr><td><label for="m_'.$name.'">'.$label.'</label></td><td>'.
      ($type=='textarea'?'<textarea name="'.$name.'" id="m_'.$name.'">'.$value.'</textarea>':
      '<input type="text" id="m_'.$name.'" name="'.$name.'" value="'.$value.'" />').'</td></tr>';
     }
      $page.='<form method="post" onsubmit="return RUN.submitForm(this)"><table>';
      $page.=$JAX->hiddenFormFields(Array("act"=>"modcontrols","do"=>"emem","mid"=>$data['id'],"submit"=>"save"));
      $page.=field("Display Name","display_name",$data['display_name']).
	        field("Avatar","avatar",$data['avatar']).
            field("Full Name","full_name",$data['full_name']).
            field("About","about",$JAX->blockhtml($data['about']),'textarea').
            field("Signature","signature",$JAX->blockhtml($data['sig']),'textarea');
      $page.='</table><input type="submit" value="Save" /></form>';
    }
   }

   $this->showmodcp($page);
  }

  function iptools(){
   global $PAGE,$DB,$CFG,$JAX,$USER;
   require_once("inc/classes/geoip.php");

   $ip=$JAX->b['ip'];
   if(strpos($ip,'.')) $ip=$JAX->ip2int($ip);
   if($ip) $dottedip=long2ip($ip);

   if($JAX->p['ban']) {
    if(!$JAX->ipbanned($dottedip)) {
     $changed=true;$JAX->ipbancache[]=$dottedip;
    }
   } else if($JAX->p['unban']) {
    if($entry=$JAX->ipbanned($dottedip)) {
      $changed=true;
      unset($JAX->ipbancache[array_search($entry,$JAX->ipbancache)]);
     }
   }
   if($changed) {$o=fopen(BOARDPATH."/bannedips.txt","w");fwrite($o,implode("\n",$JAX->ipbancache));fclose($o);}

   function box($title,$content){
    return "<div class='minibox'><div class='title'>$title</div><div class='content'>".($content?:"--No Data--")."</div></div>";
   }
   $form="<form method='post' onsubmit='return RUN.submitForm(this)'>".$JAX->hiddenFormFields(Array('act'=>'modcontrols','do'=>'iptools'))."IP: <input type='text' name='ip' value='".$dottedip."' /><input type='submit' value='Submit' /></form>";
   if($ip){
    $page.="<h3>Data for ".$dottedip.":</h3>";

    $g=new GeoIP;
    $cc=$g->country_code($dottedip);
    //$hostname=$JAX->gethostbyaddr($dottedip);
    $page.=box("Info","<form method='post' onsubmit='return RUN.submitForm(this)'>".$JAX->hiddenFormFields(Array('ip'=>$ip,'act'=>'modcontrols','do'=>'iptools'))."Country: ".($cc?'<img src="'.FLAGURL.strtolower($cc).'.gif" /> ':'').$JAX->pick($g->country_name($dottedip),'--None--')."<br />".
    //"Hostname: <a href='https://$hostname'>$hostname</a><br />".
      "IP ban status: ".($JAX->ipbanned($dottedip)?'<span style="color:#900">banned</span> <input type="submit" name="unban" onclick="this.form.submitButton=this" value="Unban" />':'<span style="color:#090">not banned</span> <input type="submit" name="ban" onclick="this.form.submitButton=this" value="Ban" />').'</form>'.
      "StopForumSpam status: ".($JAX->forumspammer($dottedip)?'<span style="color:#900">forum spammer!</span>':'clean.')."<br />".
      "Tor status: ".($JAX->toruser($dottedip)?'<span style="color:#900">Confirmed Tor User</span>':'Not a TOR user')

      );

    $content=Array();
    $result = $DB->safeselect("group_id,display_name,id","members","WHERE ip=?", $DB->basicvalue($ip));
    while($f=$DB->row($result)) $content[]=$PAGE->meta('user-link',$f['id'],$f['group_id'],$f['display_name']);
    $page.=box("Users with this IP:",implode(', ',$content));

    if($CFG['shoutbox']){
     $content="";
     $result = $DB->safespecial("SELECT s.*,m.group_id,m.display_name FROM %t s LEFT JOIN %t m ON m.id=s.uid WHERE s.ip=? ORDER BY id DESC LIMIT 5",
	array("shouts","members"), $DB->basicvalue($ip));
     while($f=$DB->row($result)) $content.=$PAGE->meta('user-link',$f['uid'],$f['group_id'],$f['display_name']).' : '.$f['shout']."<br />";
     $page.=box("Last 5 shouts:",$content);
    }
    $content="";
    $result = $DB->safeselect("post","posts","WHERE ip=? ORDER BY id DESC LIMIT 5",
	$DB->basicvalue($ip));
    while($f=$DB->row($result)) $content.="<div class='post'>".nl2br($JAX->blockhtml($JAX->textonly($f['post'])))."</div>";
    $page.=box("Last 5 posts:",$content);
   }
   $this->showmodcp($form.$page);
  }
 }
?>
