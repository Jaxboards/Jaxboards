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
  $template='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
 <head>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
  <link rel="stylesheet" type="text/css" href="Theme/css.css" />
  <link rel="stylesheet" type="text/css" href="'.STHEMEPATH.'Default/bbcode.css" />
  <script type="text/javascript" src="'.STHEMEPATH.'../jsnew.js"></script>
  <script type="text/javascript" src="Script/admin.php"></script>
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
  $write="<?php\n";
  $write.='$'.$name."=Array(\n";
  foreach($data as $k=>$v) {
   $quote=is_numeric($v)?'':'"';
   $write.='"'.str_replace('"','\"',$k).'"=>'.$quote.str_replace(Array('\\','"'),Array('\\\\','\"'),$v).$quote.','."\n";
  }
  if(!empty($data)) $write=substr($write,0,-2);
  $write.="\n);\n";
  $write.="?>";
  $o=fopen($page,$mode);fwrite($o,$write);fclose($o);
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
