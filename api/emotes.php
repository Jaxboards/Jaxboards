<?php

declare(strict_types=1);

if (isset($_GET['json'])) {
    chdir('..');

    require_once __DIR__ . '/config.php';

    require_once __DIR__ . '/inc/classes/mysql.php';
    $DB = new MySQL();
    $DB->connect($CFG['sql_host'], $CFG['sql_username'], $CFG['sql_password'], $CFG['sql_db']);

    require_once __DIR__ . '/domaindefinitions.php';

    require_once __DIR__ . '/inc/classes/jax.php';
    $JAX = new JAX();
    $rules = $JAX->getEmoteRules(0);
    foreach ($rules as $k => $v) {
        $rules[$k] = '<img src="' . $v . '" alt="' . $JAX->blockhtml($k) . '" />';
    }

    echo json_encode([array_keys($rules), array_values($rules)]);
}
