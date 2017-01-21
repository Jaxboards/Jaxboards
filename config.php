<?php
$CFG=Array(
/* "mail_from"=>"jaxboards.com <no-reply@jaxboards.com>", */
"mail_from"=>"Example Forums <no-reply@example.com>",
"boardname"=>"Example Forums",
"prefix"=>"",
"updateinterval"=>5,
"sql_host"=>"127.0.0.1", /* localhost uses local UNIX domain socket */
"sql_username"=>"SQLUSER",
"sql_password"=>"SQLPASS",
"sql_prefix"=>"jaxboards_",
"sql_db"=>"jaxboards",
"dthemepath"=>(defined("INACP")?"../":"")."Service/Themes/Default/",
"postmaxsize"=>50000,
"badnamechars"=>"@[^\w']@",
"maxfilesize"=>5*1024*1024,
"timetologout"=>900,
"images"=>Array("jpg","jpeg","png","gif","bmp")
);
?>
