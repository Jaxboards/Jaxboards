<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/../inc/classes/mysql.php';
$DB = new MySQL();
$DB->connect(
    $CFG['sql_host'],
    $CFG['sql_username'],
    $CFG['sql_password'],
    $CFG['sql_db'],
);

require_once __DIR__ . '/../domaindefinitions.php';

require_once __DIR__ . '/../inc/classes/jax.php';

$list = [[], []];

switch ($_GET['act'] ?? '') {
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
        echo json_encode($list);

        break;

    case 'emotes':
        $JAX = new JAX();
        $rules = $JAX->getEmoteRules(0);
        foreach ($rules as $k => $v) {
            $rules[$k] = '<img src="' . $v . '" alt="' . $JAX->blockhtml($k) . '" />';
        }

        echo json_encode([array_keys($rules), array_values($rules)]);

        break;

    default:
}
