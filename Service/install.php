<?php

/**
 * Service install file, for installing a new JaxBoards service.
 *
 * PHP Version 5.3.7
 *
 * @license MIT <https://opensource.org/licenses/MIT>
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
if (! defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}
if (! defined('SERVICE_ROOT')) {
    define('SERVICE_ROOT', __DIR__);
}

if (file_exists(SERVICE_ROOT.'/install.lock')) {
    exit('Install lock file found! Please remove if you wish to install.');
}

require_once JAXBOARDS_ROOT.'/inc/classes/mysql.php';
require_once JAXBOARDS_ROOT.'/inc/classes/jax.php';
require_once JAXBOARDS_ROOT.'/acp/page.php';
// Get default CFG.
require_once JAXBOARDS_ROOT.'/config.default.php';

/**
 * Recursively copies one directory to another.
 *
 * @param string $src The source directory- this must exist already
 * @param string $dst The destination directory- this is assumed to not exist already
 */
function recurseCopy($src, $dst): void
{
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file !== '.') && ($file !== '..')) {
            if (is_dir($src.'/'.$file)) {
                recurseCopy($src.'/'.$file, $dst.'/'.$file);
            } else {
                copy($src.'/'.$file, $dst.'/'.$file);
            }
        }
    }
    closedir($dir);
}

$JAX = new JAX();
$DB = new MySQL();
$PAGE = new PAGE();

$fields = [
    'domain' => [
        'name' => 'Domain',
        'type' => 'text',
        'placeholder' => 'example.com',
    ],
    'sql_host' => [
        'name' => 'MySQL Host',
        'type' => 'text',
        'placeholder' => 'localhost',
        'value' => 'localhost',
    ],
    'sql_db' => [
        'name' => 'MySQL Database',
        'type' => 'text',
        'placeholder' => 'jaxboards',
    ],
    'sql_username' => [
        'name' => 'MySQL Username',
        'type' => 'text',
        'placeholder' => 'jaxboards',
    ],
    'sql_password' => [
        'name' => 'MySQL Password',
        'type' => 'password',
    ],
    'admin_username' => [
        'name' => 'Admin Username',
        'type' => 'text',
        'placeholder' => 'admin',
    ],
    'admin_password' => [
        'name' => 'Admin Password',
        'type' => 'password',
    ],
    'admin_password_2' => [
        'name' => 'Re-Type Admin Password',
        'type' => 'password',
    ],
    'admin_email' => [
        'name' => 'Admin Email Address',
        'type' => 'text',
        'placeholder' => 'admin@example.com',
    ],
];

$errors = [];

if (isset($JAX->p['submit']) && $JAX->p['submit']) {
    // Make sure each field is set.
    foreach ($fields as $field => $attributes) {
        if (! $JAX->p[$field]) {
            $errors[] = $attributes['name'].' must be filled in.';
        }
    }
    if ($JAX->p['domain'] && ! parse_url($JAX->p['domain'], \PHP_URL_HOST)) {
        if (
            preg_match('@[^\w.]@', $JAX->p['domain'])
        ) {
            $errors[] = 'Invalid domain';
        } else {
            // Looks like we have a proper hostname,
            // just remove the leading www. if it exists.
            $JAX->p['domain'] = preg_replace('/^www./', '', $JAX->p['domain']);
        }
    } else {
        // Remove www if it exists, also only grab host if url is entered.
        $JAX->p['domain'] = preg_replace('/^www./', '', parse_url($JAX->p['domain'], \PHP_URL_HOST));
    }
    if ($JAX->p['admin_password'] !== $JAX->p['admin_password_2']) {
        $errors[] = 'Admin passwords do not match';
    }

    if (! $JAX->isemail($JAX->p['admin_email'])) {
        $errors[] = 'invalid email';
    }

    if (mb_strlen($JAX->p['admin_username']) > 50) {
        $errors[] = 'Admin username is too long';
    } elseif (preg_match('@\W@', $JAX->p['admin_username'])) {
        $errors[] = 'Admin username needs to consist of letters,'.
            'numbers, and underscore only';
    }

    // Are we installing this the service way.
    $service = isset($JAX->p['service']) && (bool) $JAX->p['service'];

    $connected = $DB->connect(
        $JAX->p['sql_host'],
        $JAX->p['sql_username'],
        $JAX->p['sql_password'],
        $JAX->p['sql_db']
    );

    if (! $connected) {
        $errors[] = 'There was an error connecting to the MySQL database.';
    }

    if (empty($errors)) {
        // Update with our settings.
        $CFG['boardname'] = 'Jaxboards';
        $CFG['domain'] = $JAX->p['domain'];
        $CFG['mail_from'] = $JAX->p['admin_username'].' <'.
            $JAX->p['admin_email'].'>';
        $CFG['sql_db'] = $JAX->p['sql_db'];
        $CFG['sql_host'] = $JAX->p['sql_host'];
        $CFG['sql_username'] = $JAX->p['sql_username'];
        $CFG['sql_password'] = $JAX->p['sql_password'];
        $CFG['installed'] = true;
        $CFG['service'] = $service;
        $CFG['prefix'] = $service ? '' : 'jaxboards';
        $CFG['sql_prefix'] = $CFG['prefix'] ? $CFG['prefix'].'_' : '';

        $PAGE->writeData(JAXBOARDS_ROOT.'/config.php', 'CFG', $CFG);

        if ($service) {
            // Create directory table.
            $queries = [
                'DROP TABLE IF EXISTS `directory`;',
                <<<'EOT'
                    CREATE TABLE `directory` (
                      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                      `registrar_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                      `registrar_ip` varbinary(16) NOT NULL DEFAULT '',
                      `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                      `boardname` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
                      `referral` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    EOT
                ,
                'TRUNCATE `directory`;',
                'DROP TABLE IF EXISTS `banlist`;',
                <<<'EOT'
                    CREATE TABLE `banlist` (
                      `ip` varbinary(16) NOT NULL,
                      UNIQUE KEY `ip` (`ip`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    EOT
                ,
                'TRUNCATE `banlist`;',
            ];
            foreach ($queries as $query) {
                $result = $DB->safequery($query);
                $DB->disposeresult($result);
            }

            // Create the text and support boards.
            $default_boards = [
                'test' => 'Test forums',
                'support' => 'Support forums',
            ];
        } else {
            // Create the board!
            $default_boards = [
                'jaxboards' => 'Jaxboards',
            ];
        }

        foreach ($default_boards as $board => $boardname) {
            $boardPrefix = $board.'_';
            $DB->prefix($boardPrefix);

            if ($service) {
                $DB->prefix('');
                // Add board to directory.
                $DB->safeinsert(
                    'directory',
                    [
                        'boardname' => $board,
                        'registrar_email' => $JAX->p['admin_email'],
                        'registrar_ip' => $JAX->ip2bin(),
                        'date' => date('Y-m-d H:i:s', time()),
                        'referral' => $JAX->b['r'] ?? '',
                    ]
                );
                $DB->prefix($boardPrefix);
            }

            // Create the directory and blueprint tables
            // Import sql file and run it with php from this:
            // https://stackoverflow.com/a/19752106
            // It's not pretty or perfect but it'll work for our use case...
            $query = '';
            $lines = file(SERVICE_ROOT.'/blueprint.sql');
            foreach ($lines as $line) {
                // Skip comments.
                if (mb_substr($line, 0, 2) === '--' || $line === '') {
                    continue;
                }

                // Replace blueprint_ with board name.
                $line = preg_replace('/blueprint_/', $boardPrefix, $line);

                // Add line to current query.
                $query .= $line;

                // If it has a semicolon at the end, it's the end of the query.
                if (mb_substr(trim($line), -1, 1) === ';') {
                    // Perform the query.
                    $result = $DB->safequery($query);
                    $DB->disposeresult($result);
                    // Reset temp variable to empty.
                    $query = '';
                }
            }

            // Don't forget to create the admin.
            $DB->safeinsert(
                'members',
                [
                    'name' => $JAX->p['admin_username'],
                    'display_name' => $JAX->p['admin_username'],
                    'pass' => password_hash($JAX->p['admin_password'], \PASSWORD_DEFAULT),
                    'email' => $JAX->p['admin_email'],
                    'sig' => '',
                    'posts' => 0,
                    'group_id' => 2,
                    'join_date' => date('Y-m-d H:i:s', time()),
                    'last_visit' => date('Y-m-d H:i:s', time()),
                ]
            );

            echo $DB->error();

            @mkdir(JAXBOARDS_ROOT.'/boards');
            recurseCopy('blueprint', JAXBOARDS_ROOT.'/boards/'.$board);
        }

        // Create lock file.
        // $file = fopen(SERVICE_ROOT . '/install.lock', 'w');
        // fwrite($file, '');
        // fclose($file);
        // Send us to the service page.
        header('Location: '.dirname($_SERVER['REQUEST_URI']));
    }
}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "https://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="https://www.w3.org/1999/xhtml/" xml:lang="en" lang="en">
<head>
<link media="all" rel="stylesheet" href="./css/main.css" />
</style>
<meta name="description" content="The world's very first instant forum." />
<title>Jaxboards - The free AJAX powered forum host</title>
</head>
<body onload="if(top.location!=self.location) top.location=self.location">
<div id='container'>
<div id='logo'>
    <a href="https://<?php echo $_SERVER['REQUEST_URI']; ?>">&nbsp;</a>
</div>
  <div id='content'>
   <div class='box'>
    <div class='content'>
     <form id="signup" method="post">
<?php
foreach ($errors as $error) {
    echo "<div class='error'>{$error}</div>";
}
?>
    <input type="checkbox" name="service" value="1" id="service" checked>
    <label for="service">Service Install</label>
</fieldset>
<br/>
<?php
foreach ($fields as $field => $attributes) {
    echo "    <label for=\"{$field}\">{$attributes['name']}:</label>".
        "<input type=\"{$attributes['type']}\"
            name=\"{$field}\" id=\"{$field}\"
            placeholder=\"".
            ($attributes['placeholder'] ?? '').
            '"
            value="'.
            ($attributes['value'] ?? '').
            '"
        />'.
        '<br />';
}
?>
      <div class='center'>
        <input type="submit" name="submit" value="Start your service!" />
     </div>
     </form>
     <strong>Bring a Jaxboards service of your own to life.</strong><br /><br />
    <p>
This installer sets up everything you need to get your very own JaxBoards
service up and running! Make sure you have your database credentials ready
and your webserver setup to send the root domain to the /Service directory
and a windcard subdomain to the Jaxboards root directory.
    </p>
      <br clear="all" />

     </div>
   </div>
   <div class='box mini box1'>
<div class='title'>Customizable</div>
<div class='content'>
Jaxboards offers entirely new ways to make your forum look exactly the way you want:
<ul>
   <li>Easy CSS</li>
   <li>Template access</li>
   </ul></div>
   </div>
   <div class='box mini box2'>
<div class='title'>Stable &amp; Secure</div>
<div class='content'>
Jaxboards maintains the highest standards of efficient, optimized software
that can handle anything you throw at it, and a support forum that will back
you up 100%.
</div>
</div>
   <div class='box mini box3'>
<div class='title'>Real Time!</div>
<div class='content'>
In an age where communication is becoming ever more terse, we know how
valuable you and your members' time is. Everything that is posted, messaged,
or shared shows up instantly on the screen.
<br /><br />
Save your refresh button.
</div></div>
    <br clear="all" />

</div>
   <div id='copyright'>
JaxBoards &copy; 2007-<?php echo date('Y'); ?>, All Rights Reserved
</div>
</body>
</html>