<?php

declare(strict_types=1);

/*
 * JaxBoards default config file. This is loaded on install, so don't delete
 * this until you've installed Jaxboards!
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards on Github
 */

// phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
return json_decode(
    <<<'JSON'
    {
        "badnamechars": "@[^\\w' ?]@",
        "boardname": "Example Forums",
        "domain": "example.com",
        "mail_from": "Example Forums <no-reply@example.com>",
        "prefix": "jaxboards",
        "service": false,
        "sql_driver": "mysql",
        "sql_db": "jaxboards",
        "sql_host": "127.0.0.1",
        "sql_username": "SQLUSER",
        "sql_password": "SQLPASS",
        "sql_prefix": "jaxboards_",
        "timetologout": 900
    }
    JSON
    ,
    true,
);
// phpcs:enable
