<?php
/**
 * JaxBoards default config file. This is loaded on install, so don't delete
 * this until you've installed Jaxboards!
 *
 * PHP Version 5.3.0
 *
 * @category Jaxboards
 * @package  Jaxboards
 *
 * @author  Sean Johnson <seanjohnson08@gmail.com>
 * @author  World's Tallest Ladder <wtl420@users.noreply.github.com>
 * @license MIT <https://opensource.org/licenses/MIT>
 *
 * @link https://github.com/Jaxboards/Jaxboards Jaxboards on Github
 */
$CFG = json_decode(
<<<'EOD'
{
    "badnamechars": "@[^\\w']@",
    "boardname": "Example Forums",
    "domain": "example.com",
    "dthemepath": "Service\/Themes\/Default\/",
    "installed": false,
    "images": [
        "jpg",
        "jpeg",
        "png",
        "gif",
        "bmp"
    ],
    "mail_from": "Example Forums <no-reply@example.com>",
    "maxfilesize": 5242880,
    "postmaxsize": 50000,
    "prefix": "jaxboards",
	"service": false,
    "sql_db": "jaxboards",
    "sql_host": "127.0.0.1",
    "sql_username": "SQLUSER",
    "sql_password": "SQLPASS",
    "sql_prefix": "jaxboards_",
    "timetologout": 900,
    "updateinterval": 5
}
EOD
    ,
    true
);
