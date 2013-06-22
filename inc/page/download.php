<?php
new downloader;
class downloader{
 function downloader(){$this->__construct();}
 function __construct(){
  global $JAX,$DB;
  $id=$JAX->b['id'];
  if(is_numeric($id)) {
   $result = $DB->safeselect("*","files","WHERE id=?", $id);
   $data=$DB->row($result);
   $DB->disposeresult($result);

  }
  if($data){
   $DB->safequery("UPDATE files SET downloads = downloads + 1 WHERE id=?", $id);
   $ext=explode(".",$data['name']);
   if(count($ext)==1) $ext="";
   else $ext=strtolower(array_pop($ext));
   if(in_array($ext,Array("jpeg","jpg","png","gif","bmp"))) $data['hash'].=".".$ext;
   $file=BOARDPATH."Uploads/".$data['hash'];
   if(file_exists($file)) {
    if(!$data['name']) $data['name']="unknown";
    header("Content-type:application/idk");
    header('Content-disposition:attachment;filename="'.$data['name'].'"');
    readfile($file);
   }
   die();
  }
 }
}
?>
