<? ob_start(); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
 <head>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
  <style type="text/css">
   html{height:100%;}
   body{font-family:arial;margin:0;scroll:none;background:url(http://jaxboards.com/acp/Theme/img/loginglow.png) center no-repeat #274463;height:100%;}
   #login{border:1px solid #000;text-align:center;font-size:20px;background:#FFF;position:absolute;top:50%;left:50%;width:500px;height:300px;margin:-150px 0 0 -250px;}
   #loginform{;padding:40px 0;}
   #logo{background:url(http://jaxboards.com/acp/Theme/img/loginlogo.png) center;height:90px;border:1px solid #2d4669;}
   #container{height:100%;position:relative;}
   input[type="submit"]{margin-top:30px;font-size:15px;}
   label{display:inline-block;width:130px;text-align:left;}
   .error{color:#F00;background:#FDD;border:1px solid #F00;padding:5px;margin:-20px 5px 20px 5px;font-size:15px;}
   input[type=submit]{background:url(http://jaxboards.com/acp/Theme/img/buttonbg.png);color:#FFF;border-radius:9px;border:1px solid #000000;}
  </style>
 </head>
 <body>
  <div id="container">
  <?
  define("INACP","true");
  
   require "../inc/classes/jax.php";
   require "../config.php";
   require "../inc/classes/mysql.php";

   $DB=new MySQL;
   $DB->connect($CFG['sql_host'],$CFG['sql_username'],$CFG['sql_password'],$CFG['sql_db'],$CFG['sql_prefix']);
   
   require_once "../domaindefinitions.php";

   $JAX=new JAX;

   if($JAX->p['submit']){
    $u=$JAX->p['user'];
    $p=md5($JAX->p['pass']);
    $DB->special('SELECT m.id,g.can_access_acp FROM %t m LEFT JOIN %t g ON m.group_id=g.id WHERE name='.$DB->evalue($u).' AND pass='.$DB->evalue($p),'members','member_groups');
    $uinfo=$DB->row();
    if(!$uinfo['can_access_acp']) $notadmin=true;
    else {
     $JAX->setCookie(Array("auid"=>$uinfo['id'],"apass"=>$p));
     header("Location: admin.php");
    }
   }
  ?>
  <div id="login">
   <div id="logo"></div>
   <div id="loginform">
    <?
     if($uinfo===false){
      echo '<div class="error">The username/password supplied was incorrect.</div>';
     } elseif($notadmin) {
      echo '<div class="error">You are not authorized to login to the ACP</div>';
     }

    ?>
    <form method="post">
     <label for="user">Username:</label><input type="text" name="user" id="user" /><br />
     <label for="pass">Password:</label><input type="password" name="pass" id="pass" /><br />
     <input type="submit" value="Login to the ACP" name="submit" />
    </form>
   </div>
  </div>
  </div>
  <script type='text/javascript'>
   document.getElementById('user').focus()
  </script>
 </body>
</html>
<? ob_end_flush(); ?>