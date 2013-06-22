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
 $DB->safequery("CREATE TABLE ? LIKE jaxboards_service.`$v`", str_replace('blueprint',$prefix,$v)),
);
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
<style type='text/css'>
/*#1C3B65*/
body{background:url(http://jaxboards.com/Themes/Default/img/bg.jpg);margin:0;padding:0;font-family:tahoma,verdana,arial;}
#container{width:1024px;margin:auto;border:1px solid #000;margin-top:10px;}
#logo{background:url(http://jaxboards.com/acp/Theme/img/acp-header.png);height:96px;}
#logo a{display:block;width:100%;height:100%;text-decoration:none;}
#logo a:focus{outline:none;}
#bar{margin:0 -10px;background:url(homepagebar.png);height:32px;border-bottom:1px solid #555;}
#bar a{display:inline-block;height:32px;text-indent:-1000px;}
#bar a:hover{background:url(homepagebar.png)}
#bar a:focus{outline:none;}
#bar a.support{width:118px;}
#bar a.support:hover{background-position:left bottom;}
#bar a.test{width:94px;}
#bar a.test:hover{background-position:-118px bottom;}
#bar a.resource{width:94px;}
#bar a.resource:hover{background-position:-212px bottom;}
#content{background:url(http://jaxboards.com/acp/Theme/img/pagebg.png) top repeat-x #152B49;padding-top:10px;}
.box{margin:10px;border:1px solid #CCF;}
.box.mini{width:31%;float:left;}
.box .content{background:#FFF;padding:20px;}
.box.mini .content{height:200px;}
.box .title{background:url(http://jaxboards.com/Themes/Default/img/boxgrad.png);color:#FFF;padding:5px}
.box1 .content{background:url(customizable.png) no-repeat 95% 95% #FFF;}
.box2 .content{background:url(secure.png) no-repeat 95% 95% #FFF;}
.box3 .content{background:url(norefresh.png) no-repeat 95% 95% #FFF;}
#signup{float:right;background:url(http://jaxboards.com/Themes/Default/img/shade.png) bottom repeat-x;border:2px solid #AAC;padding:30px;margin:30px;}
label{display:inline-block;width:100px;}
input[type=text],input[type=password]{width:200px;}
input[type=submit]{margin-top:10px;padding:5px;}
#post{position:absolute;top:-1000px;left:-1000px;}
ul{padding-left:20px;}
.error{border:1px solid #F00;background:#FDD;font-size:smaller;text-align:center;margin-bottom:10px;}
#copyright{text-align:center;color:#FFF;margin:10px;}
.center{text-align:center;}
</style>
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
