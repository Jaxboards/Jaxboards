<?php
new offlineboard;
class offlineboard{
function __construct(){
 global $PAGE,$JAX,$CFG;
 if($perms['can_view_board']) {
  $PAGE->JS("alert","should redirect");
 }
 if(!$PAGE->jsupdate) {
  $PAGE->append("PAGE",
   $PAGE->meta('box','','Error',
    $PAGE->error("You don't have permission to view the board. If you have an account that has permission, please log in.".
     ($CFG['boardoffline']&&$CFG['offlinetext']?"<br /><br />Note:<br />".nl2br($CFG['offlinetext']):'')
    )));
 }
}
}
?>
