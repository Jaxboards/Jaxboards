<?php

require __DIR__ . '/../config.php';

require __DIR__ . '/../inc/classes/mysql.php';
$DB = new MySQL();
$DB->connect(
    $CFG['sql_host'],
    $CFG['sql_username'],
    $CFG['sql_password'],
    $CFG['sql_db'],
);

require __DIR__ . '/../domaindefinitions.php';
$list = [[], []];

switch ($_GET['act']) {
    case 'searchmembers':
        $result = $DB->safeselect(
            [
                'id',
                'display_name',
            ],
            'members',
            'WHERE `display_name` LIKE ? ORDER BY `display_name` LIMIT 10',
            $DB->basicvalue(
                htmlspecialchars(
                    str_replace('_', '\_', $_GET['term']),
                    ENT_QUOTES,
                ) . '%',
            ),
        );
        while ($f = $DB->arow($result)) {
            $list[0][] = $f['id'];
            $list[1][] = $f['display_name'];
        }

        break;

    case '':
        break;
}

echo json_encode($list);
