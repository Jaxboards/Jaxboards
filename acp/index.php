<?php

declare(strict_types=1);

use ACP\Page;
use Jax\Config;
use Jax\Jax;
use Jax\MySQL;

/*
 * Admin login.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
session_start();

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}

// Load composer dependencies.
require_once JAXBOARDS_ROOT . '/jax/autoload.php';
$container = new DI\Container();

require_once JAXBOARDS_ROOT . '/domaindefinitions.php';

$CFG = $container->get('\Jax\Config')->get();

$DB = $container->get('\Jax\MySQL');
$DB->connect(
    $CFG['sql_host'],
    $CFG['sql_username'],
    $CFG['sql_password'],
    $CFG['sql_db'],
    $CFG['sql_prefix'],
);


$JAX = $container->get('\Jax\Jax');
$PAGE = $container->get('\ACP\Page');

$themeElements = [
    'board_name' => $CFG['boardname'],
    'board_url' => BOARDURL,
    'content' => '',
    'css_url' => BOARDURL . 'acp/css/login.css',
    'favicon_url' => BOARDURL . 'favicon.ico',
];

if (isset($JAX->p['submit'])) {
    $user = $JAX->p['user'];
    $password = $JAX->p['pass'];

    $result = $DB->safespecial(
        <<<'EOT'
            SELECT
                m.`id` as `id`,
                g.`can_access_acp` as `can_access_acp`
            FROM %t m
                LEFT JOIN %t g ON m.`group_id` = g.`id`
                WHERE m.`name`=?;
            EOT
        ,
        ['members', 'member_groups'],
        $DB->basicvalue($user),
    );
    $uinfo = $DB->arow($result);
    $DB->disposeresult($result);

    if (!$uinfo) {
        $themeElements['content'] = $PAGE->error(
            'The username/password supplied was incorrect',
        );
    } elseif (is_array($uinfo)) {
        // Check password.
        $verified_password = (bool) $DB->getUser($uinfo['id'], $password);

        if ($uinfo['can_access_acp'] && $verified_password) {
            $_SESSION['auid'] = $uinfo['id'];
            // Successful login, redirect
            header('Location: admin.php');
        } else {
            $themeElements['content'] = $PAGE->error(
                'You are not authorized to log in to the ACP',
            );
        }
    }
}

echo $PAGE->parseTemplate(
    'login.html',
    $themeElements,
);
