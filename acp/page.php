<?php

class PAGE{
  var $CFG=null;
  var $parts=Array("sidebar"=>"","content"=>"");
  var $partparts=Array( "nav"=>"", "navdropdowns"=>"");

 /*add_nav_menu(
    @title string
    @page string
    @menu array
   )

  Title represents the name of the button
  Page is the link the button links to
  Menu represents a list of links and associated labels to print out as a drop down list
 */

 function addNavmenu($title,$page,$menu){
  $this->partparts['nav'].='<a href="'.$page.'" class="'.strtolower($title).'">'.$title.'</a>';
  $this->partparts['navdropdowns'].='<div class="dd_menu" id="menu_'.strtolower($title).'">';
  foreach($menu as $k=>$v) $this->partparts['navdropdowns'].='<a href="'.$k.'">'.$v.'</a>';
  $this->partparts['navdropdowns'].='</div>';
 }

 function append($a,$b){
  $this->parts[$a]=$b;
 }

 function sidebar($sidebar){
  if ($sidebar) $this->parts['sidebar']="<div class='sidebar'><a href='?' class='icons home'>ACP Home</a>".$sidebar."</div>";
  else $this->parts['sidebar']="";
 }

 function title($title){
  $this->parts['title']=$title;
 }

 function addContentBox($title,$content){
  $this->parts['content'].='<div class="box"><div class="header">'.$title.'</div><div class="content">'.$content.'</div></div>';
 }

 function out(){
  $template='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "https://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="https://www.w3.org/1999/xhtml/" xml:lang="en" lang="en">
 <head>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
  <link rel="stylesheet" type="text/css" href="'.BOARDURL.'Service/acp/Theme/css.css" />
  <link rel="stylesheet" type="text/css" href="'.BOARDURL.'Service/Themes/Default/bbcode.css" />
  <script type="text/javascript" src="'.BOARDURL.'Service/jsnew.js"></script>
  <script type="text/javascript" src="'.BOARDURL.'Service/acp/Script/admin.js"></script>
  <title><% TITLE %></title>
 </head>
 <body>
  <a id="header" href="admin.php"></a>
  <div id="userbox">Logged in as: <% USERNAME %> <a href="../">Back to Board</a></div>
  <% NAV %>
  <div id="page">
    <% SIDEBAR %>
   <div class="right">
    <% CONTENT %>
   </div>
  </div>
 </body>
</html>';
  $this->parts['nav']='<div id="nav" onmouseover="dd_menu(event)">'.$this->partparts['nav'].'</div>'.$this->partparts['navdropdowns'];
  foreach($this->parts as $k=>$v){
   $template=str_replace("<% ".strtoupper($k)." %>",$v,$template);
  }
  echo $template;
 }

 function back(){
  return "<a href='javascript:history.back()'>Back</a>";
 }
 function error($a){
  return "<div class='error'>$a</div>";
 }
 function success($a){
  return "<div class='success'>$a</div>";
 }

 function location($a){
  header("Location: $a");
 }

 function writeData($page,$name,$data,$mode="w"){
  $data_string = json_encode($data, JSON_PRETTY_PRINT);
  $write = <<<EOT
<?php
/**
 * JaxBoards config file. It's just JSON embedded in PHP- wow!
 *
 * PHP Version 5.3.0
 *
 * @category Jaxboards
 * @package  Jaxboards
 * @author   Sean Johnson <seanjohnson08@gmail.com>
 * @author   World's Tallest Ladder <wtl420@users.noreply.github.com>
 * @license  MIT <https://opensource.org/licenses/MIT>
 * @link     https://github.com/Jaxboards/Jaxboards Jaxboards on Github
 */

$$name = json_decode(
<<<'EOD'
{$data_string}
EOD
    ,
    true
);

EOT;
  $file = fopen($page, $mode);
  fwrite($file, $write.PHP_EOL);
  fclose($file);

  return $write;
 }

 function writeCFG($data){
  include BOARDPATH."config.php";
  foreach($data as $k=>$v) $CFG[$k]=$v;
  $this->CFG=$CFG;
  return $this->writeData(BOARDPATH."config.php","CFG",$CFG);
 }

 function getCFGSetting($setting){
  if(!$this->CFG) {
   include BOARDPATH."config.php";
   $this->CFG=$CFG;
  }
  return @$this->CFG[$setting];
 }
}

?>
