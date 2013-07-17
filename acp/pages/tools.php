<?php
if(!defined(INACP)) die();

new tools;
class tools{
 function tools(){$this->__construct();}
 function __construct(){
  global $JAX,$PAGE;
  $sidebar='';
  foreach(Array('?act=tools&do=files'=>'File Manager') as $k=>$v) $sidebar.='<li><a href="'.$k.'">'.$v.'</a></li>';
  $PAGE->sidebar('<ul>'.$sidebar.'</ul>');
  switch($JAX->b['do']){
   case "files":
    $this->filemanager();
   break;
   case "backup":
    $this->backup();
   break;
  }
 }
 function filemanager(){
  global $PAGE,$DB,$JAX,$CFG;
  $page = "";
  if(is_numeric(@$JAX->b['delete'])){
   $result = $DB->safeselect("*","files","WHERE id=?", $DB->basicvalue($JAX->b['delete']));
   $f=$DB->row($result);
   $DB->disposeresult($result);
   if($f){
     $ext=strtolower(array_pop(explode(".",$f['name'])));
     if(in_array($ext,Array("jpg","jpeg","png","gif","bmp"))) $f['hash'].='.'.$ext;
     $page.=@unlink(BOARDPATH.'Uploads/'.$f['hash'])?$PAGE->success('File deleted'):$PAGE->error('Error deleting file, maybe it\'s already been deleted? Removed from DB');
     $DB->safedelete("files","WHERE id=?", $DB->basicvalue($JAX->b['delete']));
   }
  }
  if(is_array(@$JAX->p['dl'])) {
   foreach($JAX->p['dl'] as $k=>$v) if(ctype_digit($v)) $DB->safeupdate("files",Array("downloads"=>$v),"WHERE id=?", $DB->basicvalue($k));
   $page.=$PAGE->success('Changes saved.');
  }
  $result = $DB->safeselect("id,tid,post","posts","WHERE MATCH(post) AGAINST('attachment') AND post LIKE '%[attachment]%'");
  $linkedin=Array();
  while($f=$DB->row($result)){
   preg_match_all('@\[attachment\](\d+)\[/attachment\]@',$f['post'],$m);
   foreach($m[1] as $v) $linkedin[$v][]='<a href="../?act=vt'.$f['tid'].'&findpost='.$f['id'].'">'.$f['id'].'</a>';
  }
  $result = $DB->safespecial("SELECT f.*,m.display_name uname FROM %t f LEFT JOIN %t m ON f.uid=m.id ORDER BY f.size DESC",array("files","members"));
  echo $DB->error(1);
  $table='';
  while($file=$DB->row($result)) {
   $filepieces=explode('.',$file['name']);
   if(count($filepieces)>1) $ext=strtolower(array_pop($filepieces));
   if(in_array($ext,$CFG['images'])) $file['name']='<a href="'.BOARDPATH.'Uploads/'.$file['hash'].'.'.$ext.'">'.$file['name'].'</a>';
   else $file['name']='<a href="../?act=download&id='.$file['id'].'">'.$file['name'].'</a>';
   $table.="<tr><td>".$file['name']."</td><td>".$file['id']."</td><td>".$JAX->filesize($file['size'])."</td><td align='center'><input type='text' style='text-align:center;width:40px' name='dl[".$file['id'].']\' value="'.$file['downloads'].'" /></td><td><a href="../?act=vu'.$file['uid'].'">'.$file['uname']."</a></td><td>".($linkedin[$file['id']]?implode(', ',$linkedin[$file['id']]):'Not linked!')."</td><td align='center'><a onclick='return confirm(\"You sure?\")' href='?act=tools&do=files&delete=".$file['id']."' class='icons delete'></a></td></tr>";
  }
  $page.=$table?"<form method='post'><table id='files'><tr><th>Filename</th><th>ID</th><th>Size</th><th>Downloads</th><th>Uploader</th><th>Linked in</th><th>Delete</th></tr>".$table."<tr><td colspan='3'></td><td><input type='submit' value='Save' /></td><td colspan='3' /></td></table>":$PAGE->error('No files to show.');
  $PAGE->addContentBox('File Manager',$page);
 }
 function backup(){
    global $PAGE,$DB;
    if(@$_POST['dl']) {
        header("Content-type:text/plain");
        header("Content-Disposition:attachment;filename=\"".$DB->prefix.date('n.j.Y').'.sql"');
        function outline($line){
            echo $line."\r\n";
        }
        $tables=$DB->safespecial("SHOW TABLES IN jaxboards_service LIKE 'blueprint_%%' ");
	$allrows = $DB->rows($tables);
        $page="";
        //$o=fopen("backup.sql","w");
        foreach ($allrows as $f) {
            $f[0]=substr(strstr($f[0],"_"),1);
            $page.=$f[0];
            outline("--".$f[0]);
            $createtable=$DB->safespecial("SHOW CREATE TABLE %t",$f[0]);
	    $thisrow = $DB->row($createtable);
            outline(array_pop($thisrow));
	    $DB->disposeresult($createtable);

            $select=$DB->safeselect("*",$f[0]);
            while($row=$DB->arow($select)) {
                $insert=$DB->buildInsert($row);
                outline("INSERT INTO ".$f[0]."(".$insert[0].") VALUES ".$insert[1]);
            }
        }
        die();
    }
    $PAGE->addContentBox("Backup Forum","This tool will allow you to download and save a backup of your forum in case something happens.<br /><br />
    <form method='post' onsubmit='this.submit.disabled=true'>
    <input type='hidden' name='dl' value='1' />
    <input type='submit' name='submit' value='Download Backup' onmouseup='this.value=\"Generating backup...\";' />
    </form>");
 }
}
?>
