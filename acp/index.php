<?php
/**
 * Admin login.
 *
 * PHP Version 5.3.7
 *
 * @category Jaxboards
 * @package  Jaxboards
 *
 * @author  Sean Johnson <seanjohnson08@gmail.com>
 * @author  World's Tallest Ladder <wtl420@users.noreply.github.com>
 * @license MIT <https://opensource.org/licenses/MIT>
 *
 * @link https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
session_start();
ob_start();

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}

// This is the best place to load the password compatibility library,
// so do it here:
if (!function_exists('password_hash')) {
    include_once JAXBOARDS_ROOT . '/inc/lib/password.php';
}

define('INACP', 'true');

require JAXBOARDS_ROOT . '/config.php';
require JAXBOARDS_ROOT . '/inc/classes/jax.php';
require JAXBOARDS_ROOT . '/inc/classes/mysql.php';

$DB = new MySQL();
$DB->connect(
    $CFG['sql_host'],
    $CFG['sql_username'],
    $CFG['sql_password'],
    $CFG['sql_db'],
    $CFG['sql_prefix']
);

require_once JAXBOARDS_ROOT . '/domaindefinitions.php';

$JAX = new JAX();
$submitted = false;
if (isset($JAX->p['submit']) && $JAX->p['submit']) {
    $submitted = true;
    // start with least permissions, not admin, no password
    $notadmin = true;

    $u = $JAX->p['user'];
    $p = $JAX->p['pass'];
    $result = $DB->safespecial(
        <<<'EOT'
SELECT m.`id` as `id`, g.`can_access_acp` as `can_access_acp`
    FROM %t m
    LEFT JOIN %t g
        ON m.`group_id` = g.`id`
    WHERE m.`name`=?;
EOT
        ,
        array('members', 'member_groups'),
        $DB->basicvalue($u)
    );
    $uinfo = $DB->arow($result);
    $DB->disposeresult($result);

    // Check password
    if (is_array($uinfo)) {
        if ($uinfo['can_access_acp']) {
            $notadmin = false;
        }
        $verified_password = (bool) $JAX->getUser($uinfo['id'], $p);
        if (!$notadmin && $verified_password) {
            $_SESSION['auid'] = $uinfo['id'];
            header('Location: admin.php');
        }
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "https://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="https://www.w3.org/1999/xhtml/" xml:lang="en" lang="en">
 <head>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
  <link rel="stylesheet" type="text/css"
    href="<?php echo BOARDURL; ?>acp/css/index.css"/>
 </head>
 <body>
  <div id="container">
  <div id="login">
   <div id="logo"></div>
   <div id="loginform">
<?php
if ($submitted) {
    if ((isset($uinfo) && false === $uinfo) || !$verified_password) {
        echo '<div class="error">' .
            'The username/password supplied was incorrect.</div>';
    } elseif (isset($uinfo) && $notadmin) {
        echo '<div class="error">You are not authorized to login to the ACP</div>';
    }
}

?>
    <form method="post">
     <label for="user">Username:</label>
    <input type="text" name="user" id="user" /><br />
     <label for="pass">Password:</label>
    <input type="password" name="pass" id="pass" /><br />
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
<?php ob_end_flush(); ?>
