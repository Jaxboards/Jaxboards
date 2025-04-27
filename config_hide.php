<?php

declare(strict_types=1);
$CFG = json_decode(
    <<<'EOD'
        {"badnamechars":"@[^\\w']@","boardname":"Jaxboards","domain":"localhost","dthemepath":"Service\/Themes\/Default\/","images":["jpg","jpeg","png","gif","bmp"],"mail_from":"Sean <seanjohnson08@gmail.com>","maxfilesize":5242880,"postmaxsize":50000,"prefix":"jaxboards","service":true,"sql_db":"bibbyteam","sql_host":"localhost","sql_username":"root","sql_password":"bony211","sql_prefix":"jaxboards_","timetologout":900,"updateinterval":5}
        EOD
    ,
    true,
);
