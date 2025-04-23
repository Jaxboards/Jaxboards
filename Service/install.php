<?php

use ACP\Page;
use DI\Container;
use Jax\Config;
use Jax\Database;
use Jax\IPAddress;
use Jax\Jax;

/*
 * Service install file, for installing a new JaxBoards service.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}
if (!defined('SERVICE_ROOT')) {
    define('SERVICE_ROOT', __DIR__);
}

if (file_exists(SERVICE_ROOT . '/install.lock')) {
    echo 'Install lock file found! Please remove if you wish to install.';

    exit(1);
}

require_once JAXBOARDS_ROOT . '/jax/autoload.php';
$container = new Container();

require_once JAXBOARDS_ROOT . '/acp/page.php';

// Get default CFG.
require_once JAXBOARDS_ROOT . '/config.default.php';

const DB_DATETIME = 'Y-m-d H:i:s';

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
    while (($file = readdir($dir)) !== false) {
        if ($file === '.') {
            continue;
        }
        if ($file === '..') {
            continue;
        }
        if (is_dir($src . '/' . $file)) {
            recurseCopy($src . '/' . $file, $dst . '/' . $file);
        } else {
            copy($src . '/' . $file, $dst . '/' . $file);
        }
    }
    closedir($dir);
}

$JAX = $container->get(Jax::class);
$DB = $container->get(Database::class);
$PAGE = $container->get(Page::class);

$fields = [
    'admin_email' => [
        'name' => 'Admin Email Address',
        'placeholder' => 'admin@example.com',
        'type' => 'text',
    ],
    'admin_password' => [
        'name' => 'Admin Password',
        'type' => 'password',
    ],
    'admin_password_2' => [
        'name' => 'Re-Type Admin Password',
        'type' => 'password',
    ],
    'admin_username' => [
        'name' => 'Admin Username',
        'placeholder' => 'admin',
        'type' => 'text',
    ],
    'domain' => [
        'name' => 'Domain',
        'placeholder' => 'example.com',
        'type' => 'text',
    ],
    'sql_db' => [
        'name' => 'MySQL Database',
        'placeholder' => 'jaxboards',
        'type' => 'text',
    ],
    'sql_host' => [
        'name' => 'MySQL Host',
        'placeholder' => 'localhost',
        'type' => 'text',
        'value' => 'localhost',
    ],
    'sql_password' => [
        'name' => 'MySQL Password',
        'type' => 'password',
    ],
    'sql_username' => [
        'name' => 'MySQL Username',
        'placeholder' => 'jaxboards',
        'type' => 'text',
    ],
];

$errors = [];

if (isset($JAX->p['submit']) && $JAX->p['submit']) {
    // Make sure each field is set.
    foreach ($fields as $field => $attributes) {
        if ($JAX->p[$field]) {
            continue;
        }

        $errors[] = $attributes['name'] . ' must be filled in.';
    }
    if (
        $JAX->p['domain']
        && !parse_url((string) $JAX->p['domain'], PHP_URL_HOST)
    ) {
        if (preg_match('@[^\w\-.]@', (string) $JAX->p['domain'])) {
            $errors[] = 'Invalid domain';
        } else {
            // Looks like we have a proper hostname,
            // just remove the leading www. if it exists.
            $JAX->p['domain'] = preg_replace(
                '/^www./',
                '',
                (string) $JAX->p['domain'],
            );
        }
    } else {
        // Remove www if it exists, also only grab host if url is entered.
        $JAX->p['domain'] = preg_replace(
            '/^www./',
            '',
            parse_url((string) $JAX->p['domain'], PHP_URL_HOST),
        );
    }
    if ($JAX->p['admin_password'] !== $JAX->p['admin_password_2']) {
        $errors[] = 'Admin passwords do not match';
    }

    if (!$JAX->isemail($JAX->p['admin_email'])) {
        $errors[] = 'invalid email';
    }

    if (mb_strlen((string) $JAX->p['admin_username']) > 50) {
        $errors[] = 'Admin username is too long';
    } elseif (preg_match('@\W@', (string) $JAX->p['admin_username'])) {
        $errors[] = 'Admin username needs to consist of letters,'
            . 'numbers, and underscore only';
    }

    // Are we installing this the service way.
    $service = isset($JAX->p['service']) && (bool) $JAX->p['service'];

    $connected = $DB->connect(
        $JAX->p['sql_host'],
        $JAX->p['sql_username'],
        $JAX->p['sql_password'],
        $JAX->p['sql_db'],
    );

    if (!$connected) {
        $errors[] = 'There was an error connecting to the MySQL database.';
    }

    if ($errors === []) {
        // Update with our settings.
        $container->get(Config::class)->writeServiceConfig(
            [
                'boardname' => 'Jaxboards',
                'domain' => $JAX->p['domain'],
                'mail_from' => $JAX->p['admin_username'] . ' <'
                    . $JAX->p['admin_email'] . '>',
                'sql_db' => $JAX->p['sql_db'],
                'sql_host' => $JAX->p['sql_host'],
                'sql_username' => $JAX->p['sql_username'],
                'sql_password' => $JAX->p['sql_password'],
                'installed' => true,
                'service' => $service,
                'prefix' => $service ? '' : 'jaxboards',
                'sql_prefix' => $service ? '' : 'jaxboards_',
            ],
        );

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
                    EOT,
                'TRUNCATE `directory`;',
                'DROP TABLE IF EXISTS `banlist`;',
                <<<'EOT'
                    CREATE TABLE `banlist` (
                      `ip` varbinary(16) NOT NULL,
                      UNIQUE KEY `ip` (`ip`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    EOT,
                'TRUNCATE `banlist`;',
            ];
            foreach ($queries as $query) {
                $result = $DB->safequery($query);
                $DB->disposeresult($result);
            }

            // Create the text and support boards.
            $default_boards = [
                'support' => 'Support forums',
                'test' => 'Test forums',
            ];
        } else {
            // Create the board!
            $default_boards = [
                'jaxboards' => 'Jaxboards',
            ];
        }

        foreach (array_keys($default_boards) as $board) {
            $boardPrefix = $board . '_';
            $DB->prefix($boardPrefix);

            if ($service) {
                $DB->prefix('');
                // Add board to directory.
                $DB->safeinsert(
                    'directory',
                    [
                        'boardname' => $board,
                        'date' => gmdate(DB_DATETIME),
                        'referral' => $JAX->b['r'] ?? '',
                        'registrar_email' => $JAX->p['admin_email'],
                        'registrar_ip' => $container->get(IPAddress::class)->asBinary(),
                    ],
                );
                $DB->prefix($boardPrefix);
            }

            // Create the directory and blueprint tables
            // Import sql file and run it with php from this:
            // https://stackoverflow.com/a/19752106
            // It's not pretty or perfect but it'll work for our use case...
            $query = '';
            $lines = file(SERVICE_ROOT . '/blueprint.sql');
            foreach ($lines as $line) {
                // Skip comments.
                if (mb_substr($line, 0, 2) === '--') {
                    continue;
                }
                if ($line === '') {
                    continue;
                }
                // Replace blueprint_ with board name.
                $line = str_replace('blueprint_', $boardPrefix, $line);

                // Add line to current query.
                $query .= $line;

                // If it has a semicolon at the end, it's the end of the query.
                if (mb_substr(trim((string) $line), -1, 1) !== ';') {
                    continue;
                }

                // Perform the query.
                $result = $DB->safequery($query);
                $DB->disposeresult($result);
                // Reset temp variable to empty.
                $query = '';
            }

            // Don't forget to create the admin.
            $DB->safeinsert(
                'members',
                [
                    'display_name' => $JAX->p['admin_username'],
                    'email' => $JAX->p['admin_email'],
                    'group_id' => 2,
                    'join_date' => gmdate(DB_DATETIME),
                    'last_visit' => gmdate(DB_DATETIME),
                    'name' => $JAX->p['admin_username'],
                    'pass' => password_hash(
                        (string) $JAX->p['admin_password'],
                        PASSWORD_DEFAULT,
                    ),
                    'posts' => 0,
                    'sig' => '',
                ],
            );

            echo $DB->error();

            @mkdir(JAXBOARDS_ROOT . '/boards');
            recurseCopy('blueprint', JAXBOARDS_ROOT . '/boards/' . $board);
        }

        // Create lock file.
        // $file = fopen(SERVICE_ROOT . '/install.lock', 'w');
        // fwrite($file, '');
        // fclose($file);
        // Send us to the service page.
        header('Refresh:0');
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
<div id='logo'/>
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
    echo "    <label for=\"{$field}\">{$attributes['name']}:</label>"
        . "<input type=\"{$attributes['type']}\"
            name=\"{$field}\" id=\"{$field}\"
            placeholder=\""
            . ($attributes['placeholder'] ?? '')
            . '"
            value="'
            . ($attributes['value'] ?? '')
            . '"
        />'
        . '<br />';
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
JaxBoards &copy; 2007-<?php echo gmdate('Y'); ?>, All Rights Reserved
</div>
</body>
</html>
