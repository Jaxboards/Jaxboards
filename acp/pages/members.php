<?php
if(!defined(INACP)) die();
new members;
class members{
 function __construct(){$this->members();}
 function members(){
  global $JAX,$PAGE;
  switch(@$JAX->b['do']){
   case 'merge':
    $this->merge();
   break;
   case 'edit':
    $this->editmem();
   break;
   case 'delete':
    $this->deletemem();
   break;
   case 'prereg':
    $this->preregister();
   break;
   case 'massmessage':
    $this->massmessage();
   break;
   case 'ipbans':
    $this->ipbans();
   break;
   case 'validation':
    $this->validation();
   break;
   default:
    $this->showmain();
   break;
  }
  $sidebar="";
  foreach(Array(
   "?act=members&do=edit"=>"Edit Members",
   "?act=members&do=prereg"=>"Pre-Register",
   "?act=members&do=merge"=>"Account Merge",
   "?act=members&do=delete"=>"Delete Account",
   "?act=members&do=massmessage"=>"Mass Message",
   "?act=members&do=ipbans"=>"IP Bans"
   ) as $k=>$v) $sidebar.="<li><a href='$k'>$v</a></li>";
  $PAGE->sidebar("<ul>$sidebar</ul>");
 }
 
 function showmain(){
  global $PAGE,$DB,$JAX;
  $result = $DB->safespecial("SELECT m.id,m.avatar,m.display_name,m.group_id,g.title group_title FROM %t m LEFT JOIN %t g ON m.group_id=g.id ORDER BY m.display_name ASC",
	array("members","member_groups"));
  $page="<table><tr><th></th><th>Name</th><th>ID</th></tr>";
  while($f=$DB->row($result)) {
   $page.="<tr><td><img src='".$JAX->pick($f['avatar'],AVAPATH.'default.gif')."' width='50' height='50' /></td><td><a href='?act=members&do=edit&mid=".$f['id']."'>".$f['display_name']."</a><br />".$f['group_title']."</td><td>".$f['id']."</td></tr>";
  }
  $page.="</table>";
  $PAGE->addContentBox("Member List",$page);

 }
 function editmem(){
  global $PAGE,$JAX,$DB;
  if(@$JAX->b['mid']||@$JAX->p['submit']){
   if(@$JAX->b['mid']&&is_numeric(@$JAX->b['mid'])) {
    $result = $DB->safeselect("*","members","WHERE id=?", $DB->basicvalue($JAX->b['mid']));
    $data=$DB->arow($result);
    $DB->disposeresult($result);
    if(@$JAX->p['savedata']){
      if($data['group_id']!=2||$JAX->userData['id']==1){
       $write=Array();
       if($JAX->p['password']) $write['pass']=md5($JAX->p['password']);
       foreach(Array(
        'display_name',
        'name',
        'full_name',
        'usertitle',
        'location',
        'avatar',
        'about',
        'sig',
        'email',
        'ucpnotepad',
        'contact_aim',
        'contact_gtalk',
        'contact_msn',
        'contact_skype',
        'contact_steam',
        'contact_twitter',
        'contact_yim',
        'website',
        'posts',
        'group_id') as $v) {
         $write[$v]=$JAX->p[$v];
        }
        //make it so root admins can't get out of admin
        if($JAX->b['mid']==1) $write['group_id']=2;
        $DB->safeupdate("members",$write,"WHERE id=?", $DB->basicvalue($JAX->b['mid']));
        $page=$PAGE->success("Profile data saved");
      } else $page=$PAGE->error("You do not have permission to edit this profile.".$PAGE->back());
    }
    $result = $DB->safeselect("*","members","WHERE id=?", $DB->basicvalue($JAX->b['mid']));
   } else $result = $DB->safeselect("*","members","WHERE display_name LIKE ?;", $DB->basicvalue($JAX->p['name']."%"));
   $data=Array();
   while($f=$DB->arow($result)) $data[]=$f;
   $nummembers=count($data);
   if($nummembers>1) {
    foreach($data as $v) $page.='<div><img width="100px" height="100px" align="middle" src="'.$JAX->pick($v['avatar'],AVAPATH.'default.gif').'" /> <a href="?act=members&do=edit&mid='.$v['id'].'">'.$v['display_name'].'</a></div>';
    return $PAGE->addContentBox("Select Member to Edit",$page);
   } elseif(!$nummembers) {
    return $PAGE->addContentBox("Error",$PAGE->error("This member does not exist. ".$PAGE->back()));
   }
   $data=array_pop($data);
   if($data['group_id']==2&&$JAX->userData['id']!=1) {
    $page=$PAGE->error("You do not have permission to edit this profile. ".$PAGE->back());
   } else {
    function formfield($label,$name,$value,$which='text'){
     switch($which){
      case 'text':    return "<label>$label</label><input type='text' name='$name' value='$value' /><br />";break;
      case 'textarea':return "<label style='vertical-align:top'>$label</label><textarea name='$name'>$value</textarea><br />";break;
     }
    }
    function h1($a){
     return "<h2>$a</h2>";
    }
    $page.=$JAX->hiddenFormFields(Array('mid'=>$data['id']));
    $page.=formfield("Display Name:","display_name",$data['display_name']);
    $page.=formfield("Username:","name",$data['name']);
    $page.=formfield("Real Name:","full_name",$data['full_name']);
    $page.=formfield("Password:","password","");
    $page.=$this->getGroups($data['group_id']);
    $page.=h1("Profile Fields");
    $page.=formfield("User Title:","usertitle",$data['usertitle']);
    $page.=formfield("Location:","location",$data['location']);
    $page.=formfield("Website:","website",$data['website']);
    $page.=formfield("Avatar:","avatar",$data['avatar']);
    $page.=formfield("About:","about",$data['about'],"textarea");
    $page.=formfield("Signature:","sig",$data['sig'],"textarea");
    $page.=formfield("Email:","email",$data['email']);
    $page.=formfield("UCP Notepad:","ucpnotepad",$data['ucpnotepad'],"textarea");
    $page.=h1("Contact Details");
    $page.=formfield("AIM:","contact_aim",$data['contact_aim']);
    $page.=formfield("MSN:","contact_msn",$data['contact_msn']);
    $page.=formfield("GTalk:","contact_gtalk",$data['contact_gtalk']);
    $page.=formfield("Skype:","contact_skype",$data['contact_skype']);
    $page.=formfield("Steam:","contact_steam",$data['contact_steam']);
    $page.=formfield("Twitter:","contact_twitter",$data['contact_twitter']);
    $page.=formfield("YIM:","contact_yim",$data['contact_yim']);
    $page.=h1("System-Generated Variables");
    $page.=formfield("Post Count:","posts",$data['posts']);
    //$page.=print_r($data,1);
    $page="<form method='post'>$page<input type='submit' name='savedata' value='Save' /></form>";
   }
  } else {
  $page="<form method='post'>
          Member Name: <input type='text' name='name' onkeyup=\"$('validname').className='bad';JAX.autoComplete('act=searchmembers&term='+this.value,this,$('mid'),event);\" />
          <input type='hidden' id='mid' name='mid' onchange=\"$('validname').className='good'\"/><span id='validname'></span>
          <input type='submit' name='submit' value='Go' />
         </form>";
  }
  $PAGE->addContentBox((@$data['name'])?"Editing ".$data['name']."'s details":'Edit Member',$page);
 }
 
 function preregister(){
  global $PAGE,$JAX,$DB;
  $page="";
  if (@$JAX->p['submit']) {
   if (!$JAX->p['username']||!$JAX->p['displayname']||!$JAX->p['pass']) {
	$e="All fields required.";
   } else if (strlen($JAX->p['username'])>30||$JAX->p['displayname']>30) {
	$e="Display name and username must be under 30 characters.";
   } else {
	$result = $DB->safeselect("name,display_name","members","WHERE name=? OR display_name=?", 
		$DB->basicvalue($JAX->p['username']),
		$DB->basicvalue($JAX->p['displayname']));
	if($f=$DB->row($result))
            $e="That ".($f['name']==$JAX->p['username']?"username":"display name")." is already taken";

        $DB->disposeresult($result);
   }

   if($e) $page.=$PAGE->error($e);
   else {
    if($DB->safeinsert("members",Array("name"=>$JAX->p['username'],"display_name"=>$JAX->p['displayname'],"pass"=>md5($JAX->p['pass']),"last_visit"=>time(),"group_id"=>1,"posts"=>0))) $page.=$PAGE->success("Member registered.");
    else $page.=$PAGE->error("An error occurred while processing your request.");
    echo $DB->error();
   }
  }
  $page.='<form method="post"><label>Username:</label><input type="text" name="username" /><br /><label>Display name:</label><input type="text" name="displayname" /><br /><label>Password:</label><input type="password" name="pass" /><br /><input type="submit" name="submit" value="Register" /></form>';
  $PAGE->addContentBox('Pre-Register',$page);
 }
 
 function getGroups($group_id=0){
  global $DB;
  $result = $DB->safeselect("id,title","member_groups","ORDER BY `title` DESC");
  while($f=$DB->row($result)) $page.="<option value='".$f['id']."'".($group_id==$f['id']?" selected='selected'":"").">".$f['title']."</option>";
  return "<label>Group:</label><select name='group_id'>$page</select>";
 }
 
 function merge(){
  global $PAGE,$JAX,$DB;
  if($JAX->p['submit']) {
   if(!$JAX->p['mid1']||!$JAX->p['mid2']) $e="All fields are required";
   elseif(!is_numeric($JAX->p['mid1'])||!is_numeric($JAX->p['mid2'])) $e="An error occurred in processing your request";
   elseif($JAX->p['mid1']==$JAX->p['mid2']) $e="Can't merge a member with her/himself";
   if($e) $page.=$PAGE->error($e);
   else {
    $mid1=$DB->basicvalue($JAX->p['mid1']);
    $mid1int=$JAX->p['mid1'];
    $mid2=$JAX->p['mid2'];
    
    //$DB->debug_mode();
    //files
    $DB->safeupdate('files',   Array('uid'=>    $mid2),'WHERE uid=?',     $mid1);
    //PMs
    $DB->safeupdate('messages',Array('to'=>     $mid2),'WHERE to=?',      $mid1);
    $DB->safeupdate('messages',Array('from'=>   $mid2),'WHERE from=?',    $mid1);
     //posts
    $DB->safeupdate("posts",   Array('auth_id'=>$mid2),"WHERE auth_id=?", $mid1);
    //profile comments
    $DB->safeupdate('profile_comments',Array('to'=>     $mid2),'WHERE to=?',    $mid1);
    $DB->safeupdate('profile_comments',Array('from'=>   $mid2),'WHERE from=?',  $mid1);
    //topics
    $DB->safeupdate("topics",  Array('auth_id'=>$mid2),"WHERE auth_id=?", $mid1);
    $DB->safeupdate("topics",  Array('lp_uid'=>$mid2),"WHERE lp_uid=?", $mid1);
    
    //forums
    $DB->safeupdate("forums",Array('lp_uid'=>$mid2),'WHERE lp_uid=?', $mid1);
    
    //shouts
    $DB->safeupdate("shouts",  Array('uid'=>    $mid2),"WHERE uid=?",    $mid1);
    
    //session
    $DB->safeupdate("session",Array('uid'=>$mid2),"WHERE uid=?", $mid1);
    
    //arcade
    $DB->safeupdate("arcade_scores",Array('uid'=>$mid2),"WHERE uid=?", $mid1);
    $DB->safeupdate("arcade_games",Array('leader'=>$mid2),"WHERE leader=?", $mid1);
    
    //sum post count on account being merged into
    $result = $DB->safeselect("posts,id","members","WHERE id=?", $mid1);
    $posts=$DB->row($result);
    $DB->disposeresult($result);

    if(!$posts) $posts=0; else $posts=$posts[0];
    // $DB->update("members",Array('posts'=>Array('posts+'.$posts)),'WHERE id='.$mid2); /* @@@ YIKES @@@ */
    $DB->safequery("UPDATE members SET posts = posts + ? WHERE id=?", $posts, $mid2);
    
    //delete the account
    $DB->safedelete("members","WHERE id=?", $mid1);
    
    //update stats
    // $DB->update("stats",Array('members'=>Array('members-1'),'last_register'=>Array('(SELECT max(id) FROM '.$DB->prefix.'members)'))); /* @@@ YIKES @@@ */
    $DB->safequery("UPDATE stats SET members = members - 1, last_register = (SELECT max(id) FROM ".$DB->prefix."members)");
    $page.=$PAGE->success("Successfully merged the two accounts.");
   }
  }
  $page.='<form method="post">
          <p>This tool is used for merging duplicate accounts. Merge the duplicate account with the original account.</p>
          <label>Merge:</label><input type="text" name="name1" onkeyup="$(\'validname\').className=\'bad\';JAX.autoComplete(\'act=searchmembers&term=\'+this.value,this,$(\'mid1\'),event);" />
          <input type="hidden" id="mid1" name="mid1" onchange="$(\'validname\').className=\'good\'"/><span id="validname"></span><br />
          <label>With:</label><input type="text" name="name2" onkeyup="$(\'validname2\').className=\'bad\';JAX.autoComplete(\'act=searchmembers&term=\'+this.value,this,$(\'mid2\'),event);" />
          <input type="hidden" id="mid2" name="mid2" onchange="$(\'validname2\').className=\'good\'"/><span id="validname2"></span><br />
          <input type="submit" name="submit" value="Merge Accounts" />
         </form>';
  $PAGE->addContentBox("Account Merge",$page);
 }
 
 function deletemem(){
  global $PAGE,$JAX,$DB;
  $page = "";
  if(@$JAX->p['submit']) {
   if(!$JAX->p['mid']) $e="All fields are required";
   elseif(!is_numeric($JAX->p['mid'])) $e="An error occurred in processing your request";
   if($e) $page.=$PAGE->error($e);
   else {
    $mid=$DB->basicvalue($JAX->p['mid']);
    
    //PMs
    $DB->safedelete('messages','WHERE to=?', $mid);
    $DB->safedelete('messages','WHERE from=?', $mid);
    //posts
    $DB->safedelete("posts","WHERE auth_id=?", $mid);
    //profile comments
    $DB->safedelete('profile_comments','WHERE to=?', $mid);
    $DB->safedelete('profile_comments','WHERE from=?', $mid);
    //topics
    $DB->safedelete("topics","WHERE auth_id=?", $mid);
    
    //forums
    $DB->safeupdate("forums",Array("lp_uid"=>0,"lp_date"=>0,"lp_tid"=>0,"lp_topic"=>0),'WHERE lp_uid=?', $mid);
    
    //shouts
    $DB->safedelete("shouts","WHERE uid=?", $mid);
    
    //session
    $DB->safedelete("session","WHERE uid=?", $mid);
    
    //arcade
    $DB->safedelete("arcade_scores","WHERE uid=?", $mid);
    //TODO: Fix arcade game leader of deleted member if they have high scores
    
    //delete the account
    $DB->safedelete("members","WHERE id=?", $mid);
    
    $DB->fixAllForumLastPosts();
    
    //update stats
    // $DB->update("stats",Array('members'=>Array('members-1'),'last_register'=>Array('(SELECT max(id) FROM '.$DB->prefix.'members)'))); /* @@@ YIKES @@@ */
    $DB->safequery("UPDATE stats SET members = members - 1, last_register = (SELECT max(id) FROM ".$DB->prefix."members)");
    $page.=$PAGE->success("Successfully deleted the member account. <a href='?act=stats'>Board Stat Recount</a> suggested.");
   }
  }
  $page.='<form method="post">
          <p>This tool is used for deleting member accounts. All traces of the member ever even existing will vanish away!</p>
          <label>Member Name:</label><input type="text" name="name" onkeyup="$(\'validname\').className=\'bad\';JAX.autoComplete(\'act=searchmembers&term=\'+this.value,this,$(\'mid\'),event);" autocomplete="off" />
          <input type="hidden" id="mid" name="mid" onchange="$(\'validname\').className=\'good\'"/><span id="validname"></span><br />
          <input type="submit" name="submit" value="Delete Account" />
         </form>';
  $PAGE->addContentBox("Account Merge",$page);
 }
 
 function ipbans(){
  global $PAGE,$JAX;
  $page = "";
  if(isset($JAX->p['ipbans'])) {
   $data=explode("\n",str_replace("\r","",$JAX->p['ipbans']));
   foreach($data as $k=>$v){
    $iscomment=false;
    //check to see if each line is an ip, if it isn't, add a comment
    if($v[0]=="#") $iscomment=true;
    else {
        $d=explode(".",$v);
        if(!trim($v)) continue;
        if(count($d)>4) $iscomment=true;
        else if(count($d)<4&&substr($v,-1)!=".") $iscomment=true;
        else foreach($d as $v2) if($v2&&(is_numeric($v2)&&$v2>255)) $iscomment=true;
    }
    if($iscomment) $data[$k]='#'.$v;
   }
   $data=implode("\n",$data);
   $o=fopen(BOARDPATH."bannedips.txt","w");
   fwrite($o,$data);
   fclose($o);
  } else {
   if(file_exists(BOARDPATH."bannedips.txt")) $data=file_get_contents(BOARDPATH."bannedips.txt");
   else $data = "";
  }
  $page.='<form method="post">
    <p>List one IP per line.<br />IP Ranges should end in period (ex. 127.0. will ban everything starting with those two octets)<br />Comments should be prepended with hash (#comment).</p>
    <textarea name="ipbans" class="editor">';
    $page.=htmlspecialchars($data);
    $page.='</textarea><br />
    <input type="submit" value="Save" />
    </form>';
  $PAGE->addContentBox("IP Bans",$page);
 }
 
 function massmessage(){
  global $PAGE,$JAX,$DB;
  $page = "";
  if(@$JAX->p['submit']){
   if(!trim($JAX->p['title'])||!trim($JAX->p['message'])) $page.=$PAGE->error("All fields required!");
   else {
    $q=$DB->safeselect("id","members","WHERE (?-last_visit)<?", time(), (60*60*24*31*6));
    $num=0;
    while($f=$DB->row($q)) {
     $DB->safeinsert("messages",Array("to"=>$f['id'],"from"=>$JAX->userData['id'],"message"=>$JAX->p['message'],"title"=>$JAX->p['title'],"del_recipient"=>0,"del_sender"=>0,"read"=>0,"flag"=>0,"date"=>time()));
     $num++;
    }
    $page.=$PAGE->success("Successfully delivered $num messages");
   }
  }
  $page.="<form method='post'>Select Groups to message: (all users that have visited in the past 6 months for now, just hacking this in for tj)<br /><label>Title:</label><input type='text' name='title' /><br /><label style='vertical-align:top'>Message:</label><textarea name='message' cols='40' rows='10'></textarea><br /><input type='submit' name='submit' value='Mass Message' /></form>";
  $PAGE->addContentBox("Mass Message",$page);
 }
 
 function validation(){
    global $PAGE,$DB;
    if(isset($_POST['submit1'])) {
        $PAGE->writeCFG(Array('membervalidation'=>$_POST['v_enable']?1:0));
    }
    $page='<form method="post">
        <label style="width:140px">Require Validation:</label> <input name="v_enable" type="checkbox" class="switch yn" '.($PAGE->getCFGSetting('membervalidation')?'checked="checked"':'').' /><br />
        <input type="submit" name="submit1" value="Save" />
    </form>';
    $PAGE->addContentBox("Enable Member Validation",$page);
    
    if(isset($_POST['mid'])) {
        if($_POST['action']=="Allow") {
            $DB->safeupdate('members',Array('group_id'=>1),'WHERE id=?', $DB->basicvalue($_POST['mid']));
        }
    }
    $page='';
    $result = $DB->safeselect('id,display_name,inet_ntoa(ip) ip,email,join_date','members','WHERE group_id=5');
    while($f=$DB->row($result)) {
        $page.='<tr><td>'.$f['display_name'].'</td><td>'.$f['ip'].'</td><td>'.$f['email'].'</td><td>'.date("M jS, Y @ g:i A",$f['join_date']).'</td><td><form method="post"><input type="hidden" name="mid" value="'.$f['id'].'" /><input name="action" type="submit" value="Allow" /></form></td></tr>';
    }
    $page=$page?'<table class="wrappers">'.
    '<tr><th>Name</th><th>IP</th><th>Email</th><th>Registration Date</th><th></th></tr>'.
    $page.
    '</table>'
    :'There are currently no members awaiting validation.';
    $PAGE->addContentBox("Members Awaiting Validation",$page);

 }
}
?>
