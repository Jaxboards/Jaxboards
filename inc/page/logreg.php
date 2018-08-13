<?php
$PAGE->loadmeta('logreg');
$IDX=new LOGREG;
class LOGREG{
 /* Redundant constructor unnecesary in newer PHP versions. */
 /* function LOGREG(){$this->__construct();} */
 function __construct(){
  global $JAX,$PAGE;
  $this->privatekey="6Lcyub0SAAAAAC6ig1rao67cgoPQ0qaouRDox_7G";
  $this->publickey="6Lcyub0SAAAAADHCipWYxUxHNxbPxGjn92TlFeNx";

  switch(substr($JAX->b['act'],6)){
   case 1:$this->register();break;
   case 2:$this->logout();break;
   case 4:$this->loginpopup();break;
   case 3:default:$this->login($JAX->p['user'],$JAX->p['pass']);break;
   case 5:$this->toggleinvisible();break;
   case 6:$this->forgotpassword($JAX->b['uid'],$JAX->b['id']);break;
  }
 }

 function register(){
  $this->registering=true;
  //TODO: Valid email check?

  global $PAGE,$JAX,$DB,$CFG;

  if($JAX->p['username']) $PAGE->location("?");
  $name=trim($JAX->p['name']);
  $dispname=trim($JAX->p['display_name']);
  $pass1=$JAX->p['pass1'];
  $pass2=$JAX->p['pass2'];
  $email=$JAX->p['email'];

  if($JAX->p['register']){
   if(!$name||!$dispname) $e="Name and display name required.";
   elseif($pass1!=$pass2) $e="The passwords do not match.";
   elseif(strlen($dispname)>30||strlen($name)>30) $e="Display name and username must be under 30 characters.";
   elseif(($CFG['badnamechars']&&preg_match($CFG['badnamechars'],$name))||$JAX->blockhtml($name)!=$name) $e="Invalid characters in username!";
   elseif(($CFG['badnamechars']&&preg_match($CFG['badnamechars'],$dispname))) $e="Invalid characters in display name!";
   elseif(!$JAX->isemail($email)) $e="That isn't a valid email!";
   elseif($JAX->ipbanned()) $e="You have been banned from registering on this board.";
   elseif($JAX->forumspammer()) $e="Your IP (".$_SERVER['REMOTE_ADDR'].") has been identified as being a forum spamming address. Please contact the administrator if you believe this to be incorrect";
   elseif($JAX->toruser()) $e="Your IP (".$_SERVER['REMOTE_ADDR'].") has been identified as being a TOR node. This forum does not currently allow registrations from TOR.";
   else {
    $dispname=$JAX->blockhtml($dispname);
    $name=$JAX->blockhtml($name);
	 $result = $DB->safeselect("*","members","WHERE name=? OR display_name=?",
		$DB->basicvalue($name),
		$DB->basicvalue($dispname));
     $f=$DB->row($result);
     $DB->disposeresult($result);

     if($f!=false) {
      if($f['name']==$name) $e="That username is taken!";
      elseif($f['display_name']==$dispname) $e="That display name is already used by another member.";
     }
   }
   if($e) {
    $PAGE->JS("alert",$e);
    $PAGE->append("page",$PAGE->meta('error',$e));
   } else {
    //all clear!
    $DB->safeinsert("members",Array(
     "name"=>$name,
     "display_name"=>$dispname,
     "pass"=>md5($pass1),
     "posts"=>0,
     "email"=>$email,
     "join_date"=>time(),
     "last_visit"=>time(),
     "group_id"=>$CFG['membervalidation']?5:1,
     "ip"=>$JAX->ip2int(),
     "wysiwyg"=>1
    ));
	$DB->safequery("UPDATE ".$DB->ftable(stats)." SET members = members + 1, last_register = ?", $DB->insert_id(1));
    $this->login($name,$pass1);
   }

  } else {
   if($PAGE->jsnewlocation) {
    $PAGE->JS("update","page",$p);
   } $PAGE->append("PAGE",$p);
  }
 }

 function login($u=false,$p=false){
  global $PAGE,$JAX,$SESS,$DB,$CFG;
  if($u&&$p){
   if($SESS->is_bot) return;
   $p=md5($p);
   $result=$DB->safeselect("*","members","WHERE name=? AND pass=?",
	$DB->basicvalue($u),
	$DB->basicvalue($p));

   $f=$DB->row($result);
   $DB->disposeresult($result);

   if($f) {
    if($JAX->p['popup']) $PAGE->JS("closewindow","#loginform");
    $JAX->setCookie(Array("uid"=>$f['id'],"pass"=>$f['pass']),time()+3600*24*30);
    $SESS->clean($f['id']);
    $SESS->user=$u;
    $SESS->uid=$f['id'];
    $SESS->act();
    $perms=$JAX->getPerms($f['group_id']);
    if($this->registering) $PAGE->JS("script","window.location='?'");
    elseif($PAGE->jsaccess) $PAGE->JS("script","window.location.reload()");
    else $PAGE->location("?");
    /*keep this in case you decide to go back to ajax logins
    if($perms['can_moderate']) {
     include "modcontrols.php";
     modcontrols::load();
    }*/
   } else {
    $PAGE->append("page",$PAGE->meta('error',"Incorrect username/password"));
    $PAGE->JS("error","Incorrect username/password");
   }
   $SESS->erase("location");
  }
  $PAGE->append("page",$PAGE->meta('login-form'));
 }
 function logout(){
  global $PAGE,$JAX,$SESS;
  $JAX->setCookie(Array('uid'=>false,'pass'=>false));
  /*if(!$SESS->is_bot) $SESS->user="Guest";
  $SESS->uid=0;
  $SESS->erase("location");*/
  //just make a new session rather than fuss with the old one, to maintain users online
  $SESS->hide=1;
  $SESS->applyChanges();
  $SESS->getSess(false);
  $PAGE->reset("USERBOX",$PAGE->meta('userbox-logged-out'));
  $PAGE->JS("update","userbox",$PAGE->meta('userbox-logged-out'));
  $PAGE->JS("softurl");
  $PAGE->append("page",$PAGE->meta('success',"Logged out successfully"));
  if(!$PAGE->jsaccess) $this->login();
 }

 function loginpopup(){
  global $PAGE;
  $PAGE->JS("softurl");
  $PAGE->JS("window",Array("title"=>"Login","useoverlay"=>1,"id"=>"loginform","content"=>'<form method="post" onsubmit="return RUN.submitForm(this,1)"><input type="hidden" name="act" value="logreg3" /><input type="hidden" name="popup" value="1" /><label for="user">Username:</label><input type="text" name="user" id="user" /><br /><label for="pass">Password (<a href="?act=logreg6" title="Forgot your password?" onmouseover="return JAX.tooltip(this)" onclick="JAX.window.close(this);">?</a>):</label><input type="password" name="pass" id="pass" /><br /><input type="submit" value="Login" /> <a href="?act=logreg1" onclick="JAX.window.close(this)">Register</a></form>'));
 }

 function toggleinvisible(){
  global $PAGE,$SESS;
  if($SESS->hide) $SESS->hide=0;
  else $SESS->hide=1;
  $SESS->applyChanges();
  $PAGE->JS("setstatus",$SESS->hide?"invisible":"online");
  $PAGE->JS("softurl");
 }

 function forgotpassword($uid,$id){
  global $PAGE,$JAX,$DB,$CFG;
  $page="";

  if($PAGE->jsupdate&&empty($JAX->p)) return;

  if(is_numeric($uid)&&$id) {
   $result = $DB->safeselect("id,name,pass","members","WHERE id=?", $DB->basicvalue($uid));
   if(!($udata=$DB->row($result))||md5($udata['pass'])!=$id) $e="This link has expired. Please try again.";
   $DB->disposeresult($result);

   if($e) $page=$PAGE->meta('error',$e);
   else {
    if($JAX->p['pass1']&&$JAX->p['pass2']) {
     if($JAX->p['pass1']!=$JAX->p['pass2']) $page.=$PAGE->meta('error','The passwords did not match, please try again!');
     else {
      $DB->safeupdate("members",Array("pass"=>md5($JAX->p['pass1'])),"WHERE id=?", $DB->basicvalue($udata['id']));
      //just making use of the way registration redirects to the index
      $this->registering=true;
      return $this->login($udata['name'],$JAX->p['pass1']);
     }

    }
    $page.=$PAGE->meta('forgot-password2-form',$JAX->hiddenFormFields(Array('uid'=>$uid,'id'=>$id,'act'=>'logreg6')));
   }
  } else {

      if($JAX->p['user']) {
		$result = $DB->safeselect("id,pass,email","members","WHERE name=?", $DB->basicvalue($JAX->p['user']));
		if(!($udata=$DB->row($result))) $e="There is no user registered as <strong>".$JAX->b['user']."</strong>, sure this is correct?";
		$DB->disposeresult($result);

        if($e) {
         $page.=$PAGE->meta('error',$e);
        } else {
         $link="{BOARDURL}?act=logreg6&uid=".$udata['id']."&id=".md5($udata['pass']);
         if(!$JAX->mail(
          $udata['email'],
          "Recover Your Password!",
          "You have received this email because a password request was received at {BOARDLINK}<br />
          <br />
          If you did not request a password change, simply ignore this email and no actions will be taken. If you would like to change your password, please visit the following page and follow the on-screen instructions: <a href='$link'>$link</a><br />
          <br />
          Thanks!"
          )) $page.=$PAGE->meta('error',"There was a problem sending the email. Please contact the administrator.");
         else $page.=$PAGE->meta('success',"An email has been sent to the email associated with this account. Please check your email and follow the instructions in order to recover your password.");
        }
      }

  }

  $PAGE->append("PAGE",$page);
  $PAGE->JS("update","page",$page);
 }
}
?>
