<?
if(!defined(INACP)) die();

new themes;


class themes{
 function __construct(){
  global $PAGE,$JAX;
  $sidebar='';
  foreach(Array('?act=themes&page=create'=>'Create New Skin','?act=themes'=>'Manage Skins') as $k=>$v) $sidebar.='<li><a href="'.$k.'">'.$v.'</a></li>';
  $PAGE->sidebar('<ul>'.$sidebar.'</ul>View Community Skins');
  if($JAX->g['editcss']) $this->editcss($JAX->g['editcss']);
  elseif($JAX->g['editwrapper']) $this->editwrapper($JAX->g['editwrapper']);
  elseif(is_numeric($JAX->g['deleteskin'])) $this->deleteskin($JAX->g['deleteskin']);
  elseif($JAX->g['page']=="create") $this->createskin();
  else $this->showskinindex();
 }
 function themes(){$this->__construct();}

 function getwrappers(){
  $wrappers=Array();
  $o=opendir(BOARDPATH."Wrappers");
  while($f=readdir($o)) if($f!="."&&$f!="..") $wrappers[]=substr($f,0,-4);
  closedir($o);
  return $wrappers;
 }
 
 function showskinindex(){
  global $PAGE,$DB,$JAX,$CFG;

  if($JAX->g['deletewrapper']){
   if(!preg_match("@[^\w ]@",$JAX->g['deletewrapper'])&&file_exists(BOARDPATH."Wrappers/".$JAX->g['deletewrapper'].".txt")) {
    unlink(BOARDPATH."Wrappers/".$JAX->g['deletewrapper'].".txt");
    $PAGE->location("?act=themes");
   } else $errorwrapper="The wrapper you are trying to delete does not exist.";
  }

  if($JAX->p['newwrapper']){
   if(preg_match("@[^\w ]@",$JAX->p['newwrapper'])) $errorwrapper='Wrapper name must consist of letters, numbers, spaces, and underscore.';
   else if(strlen($JAX->p['newwrapper'])>50) $errorwrapper='Wrapper name must be less than 50 characters.';
   else if(file_exists(BOARDPATH."Wrappers/".$JAX->p['newwrapper'].".txt")) $errorwrapper="That wrapper already exists.";
   else {
    $o=fopen(BOARDPATH."Wrappers/".$JAX->p['newwrapper'].".txt","w");
    fwrite($o,file_get_contents($CFG['dthemepath']."wrappers.txt"));
    fclose($o);
   }
  }

  //make an array of wrappers
  $wrappers=$this->getwrappers();


  if($JAX->p['submit']){

   //update wrappers/hidden status
   if(is_array($JAX->p['wrapper'])) foreach($JAX->p['wrapper'] as $k=>$v) {
    if(!$v||in_array($v,$wrappers))
     $DB->update("skins",Array("wrapper"=>$v,"hidden"=>$JAX->p['hidden'][$k]?1:0),"WHERE id='".$k."'");
   }
   
   if(is_array($JAX->p['renameskin'])) {
    foreach($JAX->p['renameskin'] as $k=>$v) {
        if($k==$v) continue;
        if(preg_match('@[^\w ]@',$k)) continue;
        if(!is_dir(BOARDPATH.'Themes/'.$k)) continue;
        if(preg_match('@[^\w ]@',$v)||strlen($v)>50) $errorskins="Skin name must consist of letters, numbers, spaces, and underscore, and be under 50 characters long.";
        else if(is_dir(BOARDPATH.'Themes/'.$v)) $errorskins="That skin name is already being used.";
        else {
            $DB->update("skins",Array("title"=>$v),"WHERE title=".$DB->evalue($k)." AND custom=1");
            rename(BOARDPATH.'Themes/'.$k,BOARDPATH.'Themes/'.$v);
        }
    }
   }
   
   if(is_array($JAX->p['renamewrapper'])) {
       foreach($JAX->p['renamewrapper'] as $k=>$v) {
        if($k==$v) continue;
        if(preg_match('@[^\w ]@',$k)) continue;
        if(!is_file(BOARDPATH.'Wrappers/'.$k.'.txt')) continue;
        if(preg_match('@[^\w ]@',$v)||strlen($v)>50) $errorwrapper="Wrapper name must consist of letters, numbers, spaces, and underscore, and be under 50 characters long.";
        elseif(is_file(BOARDPATH.'Wrappers/'.$v.'.txt')) $errorwrapper="That wrapper name is already being used.";
        else {
            $DB->update("skins",Array("wrapper"=>$v),"WHERE wrapper=".$DB->evalue($k)." AND custom=1");
            rename(BOARDPATH.'Wrappers/'.$k.'.txt',BOARDPATH.'Wrappers/'.$v.'.txt');
        }
        $wrappers=$this->getwrappers();
    }
   }


   //set default
   $DB->update("skins",Array("default"=>0));
   $DB->update("skins",Array("default"=>1),"WHERE id='".$JAX->p['default']."'");
  }
  $DB->select("*","skins","ORDER BY title ASC");
  $usedwrappers=Array();
  while($f=$DB->row()) {
   $skins.="<tr><td><span>".$f['title']."</span>".($f['custom']?" <a href='#' onclick='return edit(this,\"skin\")' class='icons edit'></a>":"")."</td><td><a href='?act=themes&editcss=".$f['id']."'>".($f['custom']?"Edit":"View")." CSS</a></td><td>";
    $skins.="<select name='wrapper[".$f['id']."]'>".($f['custom']?"":"<option value=''>Skin Default</option>");
    foreach($wrappers as $v) $skins.="<option value='$v' ".($v==$f['wrapper']?"selected='selected' ":'').">$v</option>";
    $skins.="</select></td><td><a href='?act=themes&deleteskin=".$f['id']."' onclick='return confirm(\"Are you sure?\")'>Delete</a></td><td><input type='checkbox' name='hidden[".$f['id']."]' class='switch yn' ".($f['hidden']?'checked="checked"':'')." /></td><td><input type='radio' name='default' value='".$f['id']."' ".($f['default']?"checked='checked'":"")."/>";
   $skins.="</td></tr>";
   $usedwrappers[]=$f['wrapper'];
  }
  $skins=($errorskins?"<div class='error'>".$errorskins."</div>":"")."<form method='post'><input type='hidden' name='submit' value='1' /><table class='skins'><tr><th>Name</th><th></th><th>Wrapper</th><th></th><th>Hidden</th><th>Default</th></tr>$skins</table><input type='submit' value='Save Changes'></form>";
  $skins.='<style type="text/css">.skins td:first-child a{visibility:hidden;} .skins td:first-child:hover a{visibility:visible;}</style>';
  $PAGE->addContentBox("Themes",$skins);

  $wrap="";
  foreach($wrappers as $v) {
    $wrap.="<tr><td><span>$v</span> <a href='#' onclick='return edit(this,\"wrapper\")' class='icons edit'></a></td><td><a href='?act=themes&editwrapper=".$v."'>Edit Wrapper</a></td><td>".(in_array($v,$usedwrappers)?"In use":"<a href='?act=themes&deletewrapper=$v' onclick='return confirm(\"Are you sure?\")'>Delete</a>")."</td></tr>";
  }
  $wrap="<table class='wrappers'><tr><th>Name</th><th></th><th>Delete</th></tr>$wrap</table><br /><form method='post'><input type='text' name='newwrapper' /><input type='submit' value='Create Wrapper' /></form>";
  $wrap.='<style type="text/css">.wrappers td:first-child a{visibility:hidden;} .wrappers td:first-child:hover a{visibility:visible;}</style>';
  
  $wrap.=<<<heredoc
  <script type="text/javascript">
  function edit(link,suffix){
    a=$$('span',link.parentNode)
    link.parentNode.removeChild(link)
    a.innerHTML='<input type="text" name="rename'+suffix+'['+a.innerHTML+']" value="'+a.innerHTML+'" />'
    if(suffix=='wrapper') a.innerHTML='<form method="post">'+a.innerHTML+'<input type="submit" value="Save" name="submit" /></form>'
  }
  </script>
heredoc;
  $PAGE->addContentBox("Wrappers",($errorwrapper?$PAGE->error($errorwrapper):"").$wrap);
 }

 function editcss($id){
  global $PAGE,$DB,$JAX;
  $DB->select("*","skins","WHERE id='$id'");
  $skin=$DB->row();
  if($skin&&$skin['custom']&&$JAX->p['newskindata']){
   $o=fopen(BOARDPATH."Themes/".$skin['title']."/css.css","w");
   fwrite($o,$JAX->p['newskindata']);
   fclose($o);
  } else {
  }
  $PAGE->addContentBox(($skin['custom']?"Editing":"Viewing")." Skin: ".$skin['title'],"
  <form method='post' onsubmit='return submitForm(this)'>
  <textarea name='newskindata' class='editor'>".$JAX->blockhtml(file_get_contents((!$skin['custom']?STHEMEPATH:BOARDPATH."Themes/").$skin['title']."/css.css"))."</textarea>
  <div class='center'>".($skin['custom']?"<input type='submit' value='Save Changes' />":"")."</div>
  </form>");
 }

 function editwrapper($wrapper){
  global $PAGE,$JAX;
  $wrapperf=BOARDPATH."Wrappers/".$wrapper.".txt";
  if(preg_match("@[^ \w]@",$wrapper)&&!is_file($wrapperf)) $PAGE->addContentBox("Error","The theme you're trying to edit does not exist.");
  else {
   if(isset($JAX->p['newwrapper'])){
    if(strpos($JAX->p['newwrapper'],'<!--FOOTER-->')===false) $saved=$PAGE->error('&lt;!--FOOTER--&gt; must not be removed from the wrapper.');
    else {
     $o=fopen($wrapperf,"w");
     fwrite($o,$JAX->p['newwrapper']);
     fclose($o);
     $saved=$PAGE->success("Wrapper Saved Successfully");
    }
   }
   $PAGE->addContentBox("Editing Wrapper: $wrapper","$saved<form method='post'><textarea name='newwrapper' class='editor'>".$JAX->blockhtml(file_get_contents($wrapperf))."</textarea><input type='submit' value='Save Changes' /></form>");
  }
  
 }

 function createskin(){
  global $PAGE,$JAX,$DB,$CFG;
  if($JAX->p['submit']){
   if(!$JAX->p['skinname']) $e="No skin name supplied!";
   else if(preg_match("@[^\w ]@",$JAX->p['skinname'])) $e="Skinname must only consist of letters, numbers, and spaces.";
   else if(strlen($JAX->p['skinname'])>50) $e="Skin name must be less than 50 characters.";
   else if(is_dir(BOARDPATH."Themes/".$JAX->p['skinname'])) $e="A skin with that name already exists.";
   else if(!in_array($JAX->p['wrapper'],$this->getwrappers())) $e="Invalid wrapper.";
   else {
    $DB->insert('skins',Array("title"=>$JAX->p['skinname'],"wrapper"=>$JAX->p['wrapper'],"hidden"=>$JAX->p['hidden']?1:0,"default"=>$JAX->p['default']?1:0,"custom"=>1));
    if($JAX->p['default']){
	 $DB->update('skins',Array('default'=>0),"WHERE id!='".$DB->insert_id()."'");
	}
	mkdir(BOARDPATH."Themes/".$JAX->p['skinname']);
	$o=fopen(BOARDPATH."Themes/".$JAX->p['skinname']."/css.css","w");
    fwrite($o,file_get_contents($CFG['dthemepath'].'css.css'));
    fclose($o);
	$PAGE->location("?act=themes");
   }
   if($e) $page=$PAGE->error($e);
  }
  $page.="<form method='post'>";
  $page.='<label>Skin Name:</label> <input type="text" name="skinname" /><br />';
  $page.='<label>Wrapper:</label> <select name="wrapper">';
  foreach($this->getwrappers() as $v) $page.='<option value="'.$v.'">'.$v.'</option>';
  $page.='</select><br />';
  $page.='<label>Hidden:</label> <input type="checkbox" class="switch yn" name="hidden" /><br />';
  $page.='<label>Default:</label> <input type="checkbox" class="switch yn" name="default" /><br />';
  $page.='<input type="submit" name="submit" value="Create Skin" />';
  $page.="</form>";
  $PAGE->addContentBox("Create New Skin",$page);
 }

 function deleteskin($id){
  global $PAGE,$DB,$JAX;
  $DB->select("*","skins","WHERE id='".$id."'");
  $skin=$DB->row();
  $skindir=BOARDPATH."Themes/".$skin['title'];
  if(is_dir($skindir)){
   foreach(glob($skindir."/*") as $v) unlink($v);
   $JAX->rmdir($skindir);
  }
  $DB->delete('skins',"WHERE id='".$id."'");
  //make a random skin default if it's the default
  if($skin['default']) $DB->update('skins',Array('default'=>1),'LIMIT 1');
  $PAGE->location("?act=themes");
 }
  
}
?>