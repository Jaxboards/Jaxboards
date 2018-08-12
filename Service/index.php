<?php

if(in_array($_SERVER['REMOTE_ADDR'],Array('***REMOVED***'))) header("Location: http://support.jaxboards.com");

require("../inc/classes/mysql.php");
require("../inc/classes/jax.php");

$JAX=new JAX;
$DB=new MySQL;

$DB->connect('localhost','SQLUSERNAME','SQLPASSWORD','jaxboards_service');
?>
<?php
if($JAX->p['submit']){
 if($JAX->p['post']) header("Location: http://test.jaxboards.com");

 $JAX->p['boardurl']=strtolower($JAX->b['boardurl']);
 if(!$JAX->p['boardurl']||!$JAX->p['username']||!$JAX->p['password']||!$JAX->p['email']) $e="all fields required.";
 elseif(strlen($JAX->p['boardurl'])>30) $e="board url too long";
 elseif($JAX->p['boardurl']=="www") $e="WWW is reserved.";
 elseif(preg_match("@\W@",$JAX->p['boardurl'])) $e="board url needs to consist of letters, numbers, and underscore only";

 $result = $DB->safeselect("*","directory","WHERE registrar_ip=? AND date>?", $JAX->ip2int(),(time()-7*24*60*60));
 if($DB->num_rows(1)>3) $e="You may only register one 3 boards per week.";
 $DB->disposeresult($result);

 if(!$JAX->isemail($JAX->p['email'])) $e="invalid email";

 if(strlen($JAX->p['username'])>50) $e="username too long";
 elseif(preg_match("@\W@",$JAX->p['username'])) $e="username needs to consist of letters, numbers, and underscore only";

 $result = $DB->safeselect("*","directory","WHERE boardname=?", $DB->basicvalue($JAX->p['boardurl']));
 if($DB->row($result)) $e="that board already exists";
 $DB->disposeresult($result);

 //need to do check for valid email

 if(!$e) {
  make_forum($JAX->p['boardurl'],$JAX->p['username'],$JAX->p['password'],$JAX->p['email']);
  header("Location:http://".$JAX->p['boardurl'].".jaxboards.com");
 }
}

function recurse_copy($src,$dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                recurse_copy($src . '/' . $file,$dst . '/' . $file);
            }
            else {
                copy($src . '/' . $file,$dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function make_forum($prefix,$name,$password,$email){
global $DB,$JAX;

$DB->safequery("SHOW TABLES LIKE 'blueprint_%'");

while($f=$DB->row()) $tables[]=$f[0];

$DB->safeinsert('directory',Array('boardname'=>$prefix,'registrar_email'=>$email,'registrar_ip'=>$JAX->ip2int(),'date'=>time(),'referral'=>$JAX->b['r']));

$DB->select_db('jaxboards');

foreach($tables as $v){
 $DB->safequery("CREATE TABLE ? LIKE jaxboards_service.`$v`", str_replace('blueprint',$prefix,$v));
 $shit=$DB->safequery("SELECT * FROM jaxboards_service.`$v`");
 while($f=$DB->arow($shit)) {
  unset($f['id']);
  $DB->safeinsert(str_replace('blueprint',$prefix,$v),$f);
  echo $DB->error();
 }
}

//don't forget to create member
$DB->safeinsert($prefix.'_members',Array(
'name'=>$name,
'display_name'=>$name,
'pass'=>md5($password),
'email'=>$email,
'sig'=>'',
'posts'=>0,
'group_id'=>2,
'join_date'=>time(),
'last_visit'=>time()
));

echo $DB->error();

recurse_copy("blueprint","../boards/".$prefix);
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<link media="all" rel="stylesheet" href="./css/main.css" />
<meta name="description" content="The world's very first instant forum." />
<title>Jaxboards - The free AJAX powered forum host</title>
</head>
<body onload="if(top.location!=self.location) top.location=self.location">
<div id='container'>
 <div id='logo'><a href="http://jaxboards.com">&nbsp;</a></div>
  <div id='bar'><a href="http://support.jaxboards.com" class="support">Support Forum</a><a href="http://test.jaxboards.com" class="test">Test Forum</a><a href="http://support.jaxboards.com/?act=vf10" class="resource">Resources</a></div>
  <div id='content'>
   <div class='box'>
    <div class='content'>
     <form id="signup" method="post">
      <?php if($e) echo "<div class='error'>$e</div>"; ?>
      <input type="text" name="boardurl" id="boardname" />.jaxboards.com<br />
      <label for="username">Username:</label><input type="text" id="username" name="username" /><br />
      <label for="password">Password:</label><input type="password" id="password" name="password" /><br />
      <label for="email">Email:</label><input type="text" name="email" id="email" /><br />
      <input type="text" name="post" id="post" />
      <div class='center'><input type="submit" name="submit" value="Register a Forum!" /></div>
     </form>
     <strong>So, you want a community. You've come to the right place.</strong><br /><br />
      JaxBoards has been built from the ground up: utilizing feedback from members and forum gurus along the way to create the world's first real-time, AJAX-powered forum - the first bulletin board software to utilize modern technology to make each user's experience as easy and as enjoyable as possible.
      <br clear="all" />
     </div>
   </div>
   <div class='box mini box1'><div class='title'>Customizable</div><div class='content'>Jaxboards offers entirely new ways to make your forum look exactly the way you want:<ul>
   <li>Easy CSS</li>
   <li>Template access</li>
   </ul></div>
   </div>
   <div class='box mini box2'><div class='title'>Stable &amp; Secure</div><div class='content'>Jaxboards maintains the highest standards of efficient, optimized software that can handle anything you throw at it, and a support forum that will back you up 100%.</div></div>
   <div class='box mini box3'><div class='title'>Real Time!</div><div class='content'>In an age where communication is becoming ever more terse, we know how valuable you and your members' time is. Everything that is posted, messaged, or shared shows up instantly on the screen.<br /><br />Save your refresh button.</div></div>
    <br clear="all" />
   </div>
</div>
   <div id='copyright'>JaxBoards &copy; 2007-<?php date("Y");?>, All Rights Reserved</div>
</body>
</html>
