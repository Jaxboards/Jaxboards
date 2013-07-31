<?php
$PAGE->loadmeta('ucp');

new UCP;
class UCP{
 function UCP(){
  $this->__construct();
 }
 function __construct(){
  global $PAGE,$JAX,$USER,$DB;
  if(!$USER||$USER['group_id']==4) return $PAGE->location("?");
  $result = $DB->safeselect("*","members","WHERE id=?", $DB->basicvalue($USER['id']));
  $GLOBALS['USER']=$DB->arow($result);
  $DB->disposeresult($result);
  
  $PAGE->path(Array("UCP"=>"?act=ucp"));
  $this->what=$JAX->b['what'];
  switch($this->what) {
   case "sounds":
    $this->showsoundsettings();
   break;
   case "signature":
    $this->showsigsettings();
   break;
   case "pass":
    $this->showpasssettings();
   break;
   case "email":
    $this->showemailsettings();
   break;
   case "avatar":
    $this->showavatarsettings();
   break;
   case "profile":
    $this->showprofilesettings();
   break;
   case "board":
    $this->showboardsettings();
   break;
   case "inbox":
   	if(is_array($JAX->p['dmessage'])) {
   		foreach($JAX->p['dmessage'] as $v) $this->delete($v,false);
   	}
    if(is_numeric($JAX->p['messageid'])) {
     switch (strtolower($JAX->p['page'])) {
      case 'delete':$this->delete($JAX->p['messageid']);break;
      case 'forward':$this->compose($JAX->p['messageid'],'fwd');break;
      case 'reply':$this->compose($JAX->p['messageid']);break;
     }
    } else {
     if($JAX->b['page']=="compose") $this->compose();
     else if(is_numeric($JAX->g['view'])) $this->viewmessage($JAX->g['view']);
     else if($JAX->b['page']=="sent") $this->viewmessages('sent');
     else if($JAX->b['page']=="flagged") $this->viewmessages('flagged');
     else if(is_numeric($JAX->b['flag'])) return $this->flag($JAX->b['flag']);
     else $this->viewmessages();
    }
   break;
   default:
    if($PAGE->jsupdate&&empty($JAX->p)) return;
    $this->showmain();
   break;
  }
  if(!$PAGE->jsaccess||$PAGE->jsnewlocation) $this->showucp();
 }
 function getlocationforform(){
  return JAX::hiddenFormFields(Array('act'=>'ucp','what'=>$this->what));
 }
 function showmain(){
  global $PAGE,$JAX,$USER,$DB;
  if($JAX->p['ucpnotepad']){
   if(strlen($JAX->p['ucpnotepad'])>2000) {
    $e="The UCP notepad cannot exceed 2000 characters.";
    $PAGE->JS("error",$e);
   } else {
    $DB->safeupdate("members",Array("ucpnotepad"=>$JAX->p['ucpnotepad']),"WHERE id=?", $USER['id']);
    $USER['ucpnotepad']=$JAX->p['ucpnotepad'];
   }
  }
  $this->ucppage=($e?$PAGE->meta('error',$e):'').$PAGE->meta('ucp-index',
   $JAX->hiddenFormFields(Array('act'=>'ucp')),
   $USER['display_name'],
   $JAX->pick($USER['avatar'],$PAGE->meta('default-avatar')),
   trim($USER['ucpnotepad'])?$JAX->blockhtml($USER['ucpnotepad']):"Personal notes go here."
  );
  $this->showentirething=true;
  $this->showucp();
 }
 function showucp($page=false){
  if($this->shownucp) return;
  global $PAGE;
  if(!$page) $page=$this->ucppage;

   $page=$PAGE->meta('ucp-wrapper',$page);
   //$PAGE->JS("window",Array("id"=>"ucpwin","title"=>"Settings","content"=>$page,"animate"=>false));
   $PAGE->append("PAGE",$page);
   $PAGE->JS("update","page",$page);
   if($this->runscript) $PAGE->JS("script",$this->runscript);
   $PAGE->updatepath();

  $this->shownucp=true;
 }
 function showsoundsettings(){
  global $USER,$PAGE,$JAX,$DB;

  $variables=Array(
   'sound_shout',
   'sound_im',
   'sound_pm',
   'notify_pm',
   'sound_postinmytopic',
   'notify_postinmytopic',
   'sound_postinsubscribedtopic',
   'notify_postinsubscribedtopic'
  );

  if($JAX->p['submit']) {
   $update=Array();
   foreach($variables as $v) $update[$v]=$JAX->p[$v]?1:0;
   $DB->safeupdate("members",$update,"WHERE id=?", $USER['id']);

   foreach($variables as $v) $PAGE->JS("script","window.globalsettings.$v=".($JAX->p[$v]?1:0));

   $PAGE->JS("alert","Settings saved successfully.");

   $PAGE->ucppage="Settings saved successfully.";

  } elseif ($PAGE->jsupdate) {
   return true;
  }

  $checkboxes=Array($this->getlocationforform().$JAX->hiddenFormFields(Array('submit'=>1)));

  foreach($variables as $v) $checkboxes[]='<input type="checkbox" name="'.$v.'" '.($USER[$v]?'checked="checked"':'').'/>';
  
  $this->ucppage=$PAGE->meta('ucp-sound-settings',$checkboxes);
  $this->runscript="if($('dtnotify')&&window.webkitNotifications) $('dtnotify').checked=(webkitNotifications.checkPermission()==0)";

  unset($checkboxes);

 }
 
 
 

 function showsigsettings(){
  global $USER,$JAX,$DB,$PAGE;
  $update=false;
  $sig=$USER['sig'];
  if(isset($JAX->p['changesig'])) {
   $sig=$JAX->linkify($JAX->p['changesig']);
   $DB->safeupdate("members",Array("sig"=>$sig),"WHERE id=?", $USER['id']);
   $update=true;
  }
  $this->ucppage=$PAGE->meta('ucp-sig-settings',$this->getlocationforform(),$sig!==""?$JAX->theworks($sig):"( none )",$JAX->blockhtml($sig));
  if($update) $this->showucp();
 }





 function showpasssettings(){
  global $JAX,$USER,$PAGE,$DB;
  if(isset($JAX->p['passchange'])){
   if(!$JAX->p['showpass']&&$JAX->p['newpass1']!=$JAX->p['newpass2']) $e="Those passwords do not match.";
   if(!$JAX->p['newpass1']||!$JAX->p['showpass']&&!$JAX->p['newpass2']||!$JAX->p['curpass']) $e="All form fields are required.";
   if(md5($JAX->p['curpass'])!=$USER['pass'])                         $e="The password you entered is incorrect.";
   if($e){
    $this->ucppage.=$PAGE->meta('error',$e);
    $PAGE->JS("error",$e);
   } else {
    $hashpass=md5($JAX->p['newpass1']);
    $DB->safeupdate("members",Array("pass"=>$hashpass),"WHERE id=?", $USER['id']);
    $JAX->setCookie('pass',$hashpass);
    $this->ucppage='Password changed.<br /><br /><a href="?act=ucp&what=pass">Back</a>';
    return $this->showucp();
   }
  }
  $this->ucppage.=$PAGE->meta('ucp-pass-settings',$this->getlocationforform().$JAX->hiddenFormFields(Array('passchange'=>1)));
 }
 function showemailsettings(){
  global $USER,$JAX,$PAGE,$DB;
  if($JAX->p['submit']){
   if($JAX->p['email']&&!$JAX->isemail($JAX->p['email'])) $e="Please enter a valid email!";
   if($e) {
    $PAGE->JS('alert',$e);
   } else {
    $DB->safeupdate("members",Array("email"=>$JAX->p['email'],"email_settings"=>($JAX->p['notifications']?2:0)+($JAX->p['adminemails']?1:0)),"WHERE id=?", $USER['id']);
    $this->ucppage='Email settings updated.<br /><br /><a href="?act=ucp&what=email">Back</a>';
   }
   return $this->showucp();
  }
  $this->ucppage.=$PAGE->meta('ucp-email-settings',
$this->getlocationforform().$JAX->hiddenFormFields(Array('submit'=>'true')),
($JAX->b['change']?"<input type='text' name='email' value='".$USER['email']."' />":"<strong>".$JAX->pick($USER['email'],'--none--')."</strong> <a href='?act=ucp&what=email&change=1'>Change</a><input type='hidden' name='email' value='".($USER['email'])."' />"),
'<input type="checkbox" name="notifications"'.($USER['email_settings']&2?" checked='checked'":"").'>',
'<input type="checkbox" name="adminemails"'.($USER['email_settings']&1?' checked="checked"':'').'>'
);
 }
 function showavatarsettings(){
  global $USER,$PAGE,$JAX,$DB;
  if(isset($JAX->p['changedava'])){
   if($JAX->p['changedava']&&!$JAX->isurl($JAX->p['changedava'])) $e="Please enter a valid image URL.";
   else {
    $DB->safeupdate("members",Array("avatar"=>$JAX->p['changedava']),"WHERE id=?", $USER['id']);
    $USER['avatar']=$JAX->p['changedava'];
   }
   $update=true;
  }
  $this->ucppage='Your avatar: <span class="avatar"><img src="'.JAX::pick($USER['avatar'],$PAGE->meta('default-avatar')).'" alt="Unable to load avatar"></span><br /><br />
<form onsubmit="return RUN.submitForm(this)" method="post">'.
$this->getlocationforform()
.($e?$PAGE->error($e):"").'<input type="text" name="changedava" value="'.$JAX->blockhtml($USER['avatar']).'" />
<input type="submit" value="Edit" />
</form>';
  if($update) $this->showucp();
 }
 function showprofilesettings(){
  global $USER,$JAX,$PAGE,$DB,$CFG;
  if($JAX->p['submit']) {
   //insert that jizz into the database'
   $data=Array(
    'display_name'=>trim($JAX->p['display_name']),
    'full_name'=>$JAX->p['full_name'],
    'usertitle'=>$JAX->p['usertitle'],
    'about'=>$JAX->p['about'],
    'location'=>$JAX->p['location'],
    'dob_month'=>$JAX->pick($JAX->p['dob_month'],null),
    'dob_day'=>$JAX->pick($JAX->p['dob_day'],null),
    'dob_year'=>$JAX->pick($JAX->p['dob_year'],null),
    'contact_yim'=>$JAX->p['con_yim'],
    'contact_msn'=>$JAX->p['con_msn'],
    'contact_gtalk'=>$JAX->p['con_gtalk'],
    'contact_skype'=>$JAX->p['con_skype'],
    'contact_aim'=>$JAX->p['con_aim'],
    'contact_steam'=>$JAX->p['con_steam'],
    'contact_twitter'=>$JAX->p['con_twitter'],
    'website'=>$JAX->p['website'],
    'sex'=>in_array($JAX->p['sex'],Array("male","female"))?$JAX->p['sex']:null
   );

   /************************/
   /* BEGIN input checking */
   /************************/

   if(""===$data['display_name']) $data['display_name']=$USER['name'];
   if($CFG['badnamechars']&&preg_match($CFG['badnamechars'],$data['display_name'])) $error="Invalid characters in display name!";
   else {
    $DB->safeselect("*","members","WHERE `display_name` = ? AND id!=?", $DB->basicvalue($data['display_name']), $USER['id']);
    if($DB->row()!==false) $error="That display name is already in use.";
   }
   if($data['dob_month']||$data['dob_year']||$data['dob_day']) {
    if(!is_numeric($data['dob_month'])||!is_numeric($data['dob_day'])&&!is_numeric($data['dob_year'])) $error="That isn't a valid birth date.";
    if(($data['dob_month']%2)&&$data['dob_day']==31||
        $data['dob_month']==2&&(!$data['dob_year']%4&&$data['dob_day']>29||$data['dob_year']%4&&$data['dob_day']>28)
      ) $error="That birth date doesn't exist!";
   }
   foreach(Array(
    "contact_yim"=>"YIM username",
    "contact_msn"=>"MSN username",
    "contact_gtalk"=>"Google Talk username",
    "contact_steam"=>"Steam username",
    "contact_twitter"=>"Twitter ID",
    "contact_aim"=>"AIM username",
    "contact_skype"=>"Skype username",
    "full_name"=>"Full name",
    "display_name"=>"Display name",
    "website"=>"Website URL",
	"usertitle"=>"User Title",
	"location"=>"Location"
   ) as $k=>$v) {
    if(strstr($k,"contact")!==false&&preg_match("/[^\w.@]/",$data[$k])) $error="Invalid characters in $v";

	$data[$k]=$JAX->blockhtml($data[$k]);
    $length=$k=="display_name"?30:($k=='location'?100:50);
    if(strlen($data[$k])>$length) $error="$v must be less than $length characters.";
   }

   /**********************/
   /*Handle errors/insert*/
   /**********************/

   if(!$error){
    if($data['display_name']!=$USER['display_name']) {
     $DB->safeinsert("activity",Array(
      'type'=>'profile_name_change',
      'arg1'=>$USER['display_name'],
      'arg2'=>$data['display_name'],
      'uid'=>$USER['id'],
      'date'=>time()
     ));
    }
    $DB->safeupdate("members",$data,"WHERE id=?", $USER['id']);
    $this->ucppage='Profile successfully updated.<br /><br /><a href="?act=ucp&what=profile">Back</a>';
    $this->showucp();
    return;
   } else {
    $PAGE->ucppage.=$PAGE->meta('error',$error);
    $PAGE->JS('error',$error);
   }
  }
  $data=$USER;
  $sexselect='<select name="sex">';
  foreach(Array("","male","female") as $v) $sexselect.='<option value="'.$v.'"'.($data['sex']==$v?' selected="selected"':'').'>'.$JAX->pick(ucfirst($v),"Not telling").'</option>';
  $sexselect.='</select>';
  
  $dobselect='<select name="dob_month"><option value="">--</option>';
  foreach(Array("January","February","March","April","May","June","July","August","September","October","November","December") as $k=>$v) $dobselect.='<option value="'.($k+1).'"'.(($k+1)==$data['dob_month']?' selected="selected"':'').'>'.$v.'</option>';
  $dobselect.='</select><select name="dob_day"><option value="">--</option>';
  for($x=1;$x<32;$x++) $dobselect.='<option value="'.$x.'"'.($x==$data['dob_day']?' selected="selected"':'').'>'.$x.'</option>';
  $dobselect.='</select><select name="dob_year"><option value="">--</option>';
  $thisyear=(int)date("Y");
  for($x=$thisyear;$x>$thisyear-100;$x--) $dobselect.='<option value="'.$x.'"'.($x==$data['dob_year']?' selected="selected"':'').'>'.$x.'</option>';
  $dobselect.='</select>';
  
  $this->ucppage=$PAGE->meta('ucp-profile-settings',
   $this->getlocationforform().$JAX->hiddenFormFields(Array('submit'=>'1')),
   $USER['name'],
   $data['display_name'],
   $data['full_name'],
   $data['usertitle'],
   $data['about'],
   $data['location'],
   $sexselect,
   $dobselect,
   $data['contact_skype'],
   $data['contact_yim'],
   $data['contact_msn'],
   $data['contact_gtalk'],
   $data['contact_aim'],
   $data['contact_steam'],
   $data['contact_twitter'],
   $data['website']
   );
 }

 function showboardsettings(){
     global $PAGE,$DB,$JAX,$USER;
     if(is_numeric($JAX->b['skin'])) {
         $result = $DB->safeselect("*","skins","WHERE id=?", $JAX->b['skin']);
         if(!$DB->row($result)) $e="The skin chosen no longer exists.";
         else {
	     $DB->disposeresult($result);
             $DB->safeupdate("members",Array(
                "skin_id"=>$JAX->b['skin'],
                "nowordfilter"=>$JAX->p['usewordfilter']?0:1,
                "wysiwyg"=>$JAX->p['wysiwyg']?1:0
                ),"WHERE id=?", $USER['id']);
             $USER['skin_id']=$JAX->b['skin'];
         }
         if(!$e) {
             if($PAGE->jsaccess) return $PAGE->JS("script","document.location.reload()");
             else return header("Location: ?act=ucp&what=board");
         } else $this->ucppage.=$PAGE->meta('error',$e);
         $showthing=true;
     }
     // $DB->select("*","skins",($USER['group_id']!=2?"WHERE hidden!=1 ":"")."ORDER BY `title` ASC");
     $result = ($USER['group_id']!=2) ? $DB->safeselect("*","skins","WHERE hidden!=1 ORDER BY `title` ASC"):
     	$DB->safeselect("*","skins", "ORDER BY `title` ASC");
     $select='';
     while($f=$DB->row($result)) {
        $select.="<option value='".$f['id']."' ".($USER['skin_id']==$f['id']?"selected='selected'":"")."/>".($f['hidden']?"*":"").$f['title']."</option>";$found=true;
     }
     $select='<select name="skin">'.$select.'</select>';
     if(!$found) $select='--No Skins--';
     $this->ucppage.=$PAGE->meta('ucp-board-settings',
         $this->getlocationforform(),
         $select,
         '<input type="checkbox" name="usewordfilter"'.(!$USER['nowordfilter']?' checked="checked"':'').' />',
         '<input type="checkbox" name="wysiwyg"'.($USER['wysiwyg']?' checked="checked"':'').' />'
     );
     if($showthing) $this->showucp();
 }
 
 /* HERE BE PRIVATE MESSAGING
    ARRRRRRRRRRRRRRRRRRRRRRRR */
 
 function flag(){
  global $PAGE,$DB,$JAX,$USER;
  $PAGE->JS("softurl");
  $DB->safeupdate("messages",Array("flag"=>$JAX->b['tog']?1:0),"WHERE `id`=? AND `to`=?", $DB->basicvalue($JAX->b['flag']), $USER['id']);
 }

 function viewmessage($messageid){
  global $PAGE,$DB,$JAX,$USER;
  if($PAGE->jsupdate&&!$PAGE->jsdirectlink) return;
  $result = $DB->safespecial("SELECT a.*,m.group_id,m.display_name name,m.avatar,m.usertitle FROM %t a LEFT JOIN %t m ON a.from=m.id WHERE a.id=? ORDER BY date DESC",
	array("messages","members"),
	$DB->basicvalue($messageid));
  $message=$DB->row($result);
  $DB->disposeresult($row);
  if($message['from']!=$USER['id']&&$message['to']!=$USER['id']) $e="You don't have permission to view this message.";
  if($e) return $this->showucp($e);
  if(!$message['read']&&$message['to']==$USER['id']) {
   $DB->safeupdate("messages",Array("read"=>1),"WHERE id=?", $message['id']);
   $this->updatenummessages();
  }
  
  $page=$PAGE->meta('inbox-messageview',
   $message['title'],
   $PAGE->meta('user-link',$message['from'],$message['group_id'],$message['name']),
   $JAX->date($message['date']),
   $JAX->theworks($message['message']),
   $JAX->pick($message['avatar'],$PAGE->meta('default-avatar')),
   $message['usertitle'],
   $JAX->hiddenFormFields(Array('act'=>'ucp','what'=>'inbox','messageid'=>$message['id'],'sender'=>$message['from']))
  );
  $this->showucp($page);
 }

 
 function updatenummessages(){
  global $DB,$PAGE,$USER;
  $result = $DB->safeselect("count(*)","messages","WHERE `to`=? AND !`read`", $USER['id']);
  $unread=$DB->row($result);
  $DB->disposeresult($result);

  $unread=array_pop($unread);
  $PAGE->JS("update","num-messages",$unread);
 }

 function viewmessages($view="inbox"){
  global $PAGE,$DB,$JAX,$USER;
  
  if($PAGE->jsupdate&&empty($JAX->p)) return;
  $result = null;
  if($view=="sent")
   $result = $DB->safespecial("SELECT a.*,m.display_name FROM %t a LEFT JOIN %t m ON a.to=m.id WHERE a.from=? AND !del_sender ORDER BY a.date DESC",
	array("messages","members"),
	$USER['id']);

  else if($view=="flagged") {
   $result = $DB->safespecial("SELECT a.*,m.display_name FROM %t a LEFT JOIN %t m ON a.from=m.id WHERE a.to=? AND !del_recipient AND flag=1 ORDER BY a.date DESC",
	array("messages","members"),
	$USER['id']);

  } else {
   $result = $DB->safespecial("SELECT a.*,m.display_name FROM %t a LEFT JOIN %t m ON a.from=m.id WHERE a.to=? AND !del_recipient ORDER BY a.date DESC",
	array("messages","members"),
	$USER['id']);

  }
  $unread=0;
  while($f=$DB->row($result)) {
   $hasmessages=1;
   if(!$f['read'])$unread++;
   $page.=$PAGE->meta('inbox-messages-row',
    (!$f['read']?'unread':'read'),
    '<input class="check" type="checkbox" name="dmessage[]" value="'.$f['id'].'" />',
    '<input type="checkbox" '.($f['flag']?'checked="checked" ':'').'class="switch flag" onclick="RUN.stream.location(\'?act=ucp&what=inbox&flag='.$f['id'].'&tog=\'+(this.checked?1:0))" />',
    $f['id'],
    $f['title'],
    $f['display_name'],
    $JAX->date($f['date'])
   );
  }

  if(!$hasmessages) {
   if($view=="sent") $msg="No sent messages.";
   else if($view=="flagged") $msg="No flagged messages.";
   else $msg='No messages. You could always try <a href="?act=ucp&what=inbox&page=compose">sending some</a>, though!';
   $page.='<tr><td colspan="5" class="error">'.$msg.'</td></tr>';
  };

  $page=$PAGE->meta('inbox-messages-listing',$JAX->hiddenFormFields(Array('act'=>'ucp','what'=>'inbox')),$view=="sent"?"Recipient":"Sender",$page);

  if($view=="inbox") $PAGE->JS("update","num-messages",$unread);
  $this->showucp($page);
 }

 function compose($messageid='',$todo=''){
  global $PAGE,$JAX,$USER,$DB,$CFG;
  $showfull=0;
  if($JAX->p['submit']) {
   $mid=$JAX->b['mid'];
   if(!$mid&&$JAX->b['to']) {
    $result = $DB->safeselect("id,email,email_settings","members","WHERE display_name=?", $DB->basicvalue($JAX->b['to']));
    $udata=$DB->row($result);
    $DB->disposeresult($result);
   } else {
    $result = $DB->safeselect("id,email,email_settings","members","WHERE id=?", $DB->basicvalue($mid));
    $udata=$DB->row($result);
    $DB->disposeresult($result);
   }
   if(!$udata) $e="Invalid user!";
   else if(!trim($JAX->b['title'])) $e="You must enter a title.";
   if($e) {$PAGE->JS("error",$e);$PAGE->append("PAGE",$PAGE->error($e));}
   else {
    //put it into the table
    $DB->safeinsert("messages",Array("to"=>$udata['id'],"from"=>$USER['id'],"title"=>$JAX->blockhtml($JAX->p['title']),"message"=>$JAX->p['message'],"date"=>time(),"del_sender"=>0,"del_recipient"=>0,"read"=>0));
    //give them a notification
    $cmd=$JAX->json_encode(Array("newmessage","You have a new message from ".$USER['display_name'],$DB->insert_id(1)))."\n";
    $result = $DB->safespecial("UPDATE %t SET runonce=concat(runonce,?) WHERE uid=?",
	array("session"),
	$DB->basicvalue($cmd,1),
	$udata['id']);
    //send em an email!
    if($udata['email_settings']&2) {
     $JAX->mail(
      $udata['email'],
      "PM From ".$USER['display_name'],
      "You are receiving this email because you've received a message from ".$USER['display_name']." on {BOARDLINK}.<br />
     <br />
     Please go to <a href='{BOARDURL}?act=ucp&what=inbox'>{BOARDURL}?act=ucp&what=inbox</a> to view your message.");
    }
    
    $this->showucp("Message successfully delivered.<br /><br /><a href='?act=ucp&what=inbox'>Back</a>");
    return;
   }
  }
  if($PAGE->jsupdate&&!$messageid) return;
  $msg='';
  if($messageid) {
   $result = $DB->safeselect("*","messages","WHERE (`to`=? OR `from`=?) AND `id`=?",
	$USER['id'],
	$USER['id'],
	$DB->basicvalue($messageid));

   $message=$DB->row($result);
   $DB->disposeresult($result);

   $mid=$message['from'];
   $result = $DB->safeselect("display_name","members","WHERE id=?", $mid);
   $mname=array_pop($DB->row($result));
   $DB->disposeresult($result);

   $msg="\n\n\n".'[quote='.$mname.']'.$message['message'].'[/quote]';
   $mtitle=($todo=="fwd"?"FWD:":"RE:").$message['title'];
   if($todo=="fwd") {
     $mid=$mname="";
   }
  }
  if(is_numeric($JAX->g['mid'])) {
   $showfull=1;
   $mid=$JAX->b['mid'];
   $result = $DB->safeselect("display_name","members","WHERE id=?", $mid);
   $mname=array_pop($DB->row($result));
   $DB->disposeresult($result);

   if(!$mname) {$mid=0;$mname='';}
  }
  
  
  $page=$PAGE->meta('inbox-composeform',
   $JAX->hiddenFormFields(Array("act"=>"ucp","what"=>"inbox","page"=>"compose","submit"=>"1")),
   $mid,
   $mname,
   ($mname?'good':''),
   $mtitle,
   htmlspecialchars($msg)
  );
  $this->showucp($page);
 }

 function delete($id,$relocate=true){
  global $PAGE,$JAX,$DB,$USER;
  $result = $DB->safeselect("*","messages","WHERE `id`=?", $DB->basicvalue($id));
  $message=$DB->row($result);
  $DB->disposeresult($result);

  $is_recipient=$message['to']==$USER['id'];
  $is_sender=$message['from']==$USER['id'];
  if($is_recipient) $DB->safeupdate("messages",Array("del_recipient"=>1),"WHERE id=?", $DB->basicvalue($id));
  if($is_sender)    $DB->safeupdate("messages",Array("del_sender"=>1),"WHERE id=?", $DB->basicvalue($id));
  $result = $DB->safeselect("*","messages","WHERE `id`=?", $DB->basicvalue($id));
  $message=$DB->row($result);
  $DB->disposeresult($result);

  if($message['del_recipient']&&$message['del_sender']) $DB->safedelete("messages","WHERE id=?", $DB->basicvalue($id));
  if($relocate) $PAGE->location("?act=ucp&what=inbox".($JAX->b['prevpage']?"&page=".$JAX->b['prevpage']:''));
 }
}
?>
