<?
$PAGE->metadefs['inbox-messages-listing']='<table class="listing">
<tr><th class="center" width="5%%"><input type="checkbox" onclick="JAX.checkAll($$(\'.check\'),this.checked)" /></th><th width="5%%">Flag</th><th width="45%%">Title</th><th width="20%%">%s</th><th width="25%%">Date Sent</th></tr>%s</table>';

$IDX=new INBOX;
class INBOX{
 function INBOX(){$this->__construct();}
 function __construct(){
  global $JAX,$PAGE,$USER;
  if(!$USER) return $PAGE->location("?");
  if(is_numeric($JAX->p['messageid'])) {
   switch (strtolower($JAX->p['page'])) {
    case 'delete':$this->delete($JAX->p['messageid']);break;
    case 'forward':$this->compose($JAX->p['messageid'],'fwd');break;
    case 'reply':$this->compose($JAX->p['messageid']);break;
   }
  } else {
    if(is_numeric($JAX->g['view'])) $this->viewmessage($JAX->g['view']);
    else if($JAX->b['page']=="compose") $this->compose();
    else if($JAX->b['page']=="sent") $this->viewmessages('sent');
    else if($JAX->b['page']=="flagged") $this->viewmessages('flagged');
    else if(is_numeric($JAX->b['flag'])) $this->flag($JAX->b['flag']);
    else if(!$PAGE->jsupdate) $this->viewmessages();
  }
 }

 function flag(){
  global $PAGE,$DB,$JAX,$USER;
  $PAGE->JS("softurl");
  $DB->update("messages",Array("flag"=>$JAX->b['tog']?1:0),"WHERE `id`=".$DB->evalue($JAX->b['flag'])." AND `to`=".$USER['id']);
 }

 function viewmessage($messageid){
  global $PAGE,$DB,$JAX,$USER;
  if($PAGE->jsupdate&&!$PAGE->jsdirectlink) return;
  $DB->special("SELECT a.*,m.group_id,m.display_name name FROM %t a LEFT JOIN %t m ON a.from=m.id WHERE a.id=".$DB->evalue($messageid)." ORDER BY date DESC","messages","members");
  $message=$DB->row();
  if($message['from']!=$USER['id']&&$message['to']!=$USER['id']) $e="You don't have permission to view this message.";
  if($e) return $this->showwholething($e);
  if(!$message['read']&&$message['to']==$USER['id']) {
   $DB->update("messages",Array("read"=>1),"WHERE id=".$message['id']);
   $this->updatenummessages();
  }
  $page="<div class='messageview'>
<div class='messageinfo'>
<div class='title'>".$message['title']."</div>
<div>From: ".$PAGE->meta('user-link',$message['from'],$message['group_id'],$message['name'])."</div>
<div>Sent: ".$JAX->date($message['date']).'</div>
</div>
<div class="message">'.$JAX->theworks($message['message']).'</div>
<div class="messagebuttons">
 <form method="post" onsubmit="return RUN.submitForm(this,0,event)">
  <input type="hidden" name="act" value="inbox" />
  <input type="hidden" name="messageid" value="'.$message['id'].'" />
  <input type="hidden" name="sender" value="'.$message['from'].'" />
  <input type="hidden" name="prevpage" value="'.$JAX->b['page'].'" />
  <input type="submit" name="page" onclick="this.form.submitButton=this;" value="Delete" /> <input type="submit" onclick="this.form.submitButton=this;" name="page" value="Forward" /> <input type="submit" onclick="this.form.submitButton=this;" name="page" value="Reply" />
 </form>
</div>
</div>';
  $this->showwholething($page);
 }

 function showwholething($page,$show=0){
  global $PAGE;
  if(!$PAGE->jsaccess||$PAGE->jsdirectlink||$show) {
  $page='<div class="inbox">
<div class="folders">
<div class="folder compose"><a href="?act=inbox&page=compose">Compose</a></div>
<div class="folder inbox"><a href="?act=inbox">Inbox</a></div>
<div class="folder sent"><a href="?act=inbox&page=sent">Sent</a></div>
<div class="folder flagged"><a href="?act=inbox&page=flagged">Flagged</a></div>
</div>
<div id="inboxpage">'.$page.'</div>
<div class="clear"></div>
</div>';
  $page=$PAGE->meta('box','',"Inbox",$page);
  $PAGE->JS("update","page",$page);
  $PAGE->append("PAGE",$page);
 }  else $PAGE->JS("update","inboxpage",$page);

 }
 
 function updatenummessages(){
  global $DB,$PAGE,$USER;
  $DB->select("count(*)","messages","WHERE `to`=".$USER['id']." AND !`read`");
  $unread=$DB->row();
  $unread=array_pop($unread);
  $PAGE->JS("update","num-messages",$unread);
 }

 function viewmessages($view="inbox"){
  global $PAGE,$DB,$JAX,$USER;
  if($PAGE->jsupdate) return;
  if($view=="sent")
   $DB->special("SELECT a.*,m.display_name FROM %t a LEFT JOIN %t m ON a.to=m.id WHERE a.from=".$USER['id']." AND !del_sender ORDER BY a.date DESC","messages","members");
  else if($view=="flagged") {
   $DB->special("SELECT a.*,m.display_name FROM %t a LEFT JOIN %t m ON a.from=m.id WHERE a.to=".$USER['id']." AND flag=1 ORDER BY a.date DESC","messages","members");
  } else {
   $DB->special("SELECT a.*,m.display_name FROM %t a LEFT JOIN %t m ON a.from=m.id WHERE a.to=".$USER['id']." AND !del_recipient ORDER BY a.date DESC","messages","members");
  }
  $unread=0;
  while($f=$DB->row()) {
   $hasmessages=1;
   if(!$f['read'])$unread++;$page.='<tr '.(!$f['read']?'class="unread" ':'').'onclick="if(JAX.event(event).srcElement.tagName.toLowerCase()==\'td\') $$(\'input\',this)[0].click()"><td class="center"><input class="check" type="checkbox" /></td><td class="center"><input type="checkbox" '.($f['flag']?'checked="checked" ':'').'class="switch flag" onclick="RUN.stream.location(\'?act=inbox&flag='.$f['id'].'&tog=\'+(this.checked?1:0))" /></td><td><a href="?act=inbox&view='.$f['id'].'">'.$f['title'].'</a></td><td>'.$f['display_name'].'</td><td>'.$JAX->date($f['date']).'</td></tr>';
  }

  if(!$hasmessages) {
   if($view=="sent") $msg="No sent messages.";
   else if($view=="flagged") $msg="No flagged messages.";
   else $msg='No messages. You could always try <a href="?act=inbox&page=compose">sending some</a>, though!';
   $page.='<tr><td colspan="5" class="error">'.$msg.'</td></tr>';
  } else $page.='<tr><td></td><td colspan="4"><button>This button does nothing</button></td></tr>';

  $page=$PAGE->meta('inbox-messages-listing',$view=="sent"?"Recipient":"Sender",$page);

  if($view=="inbox") $PAGE->JS("update","num-messages",$unread);
  $this->showwholething($page,1);
 }

 function viewsent(){
 }


 function compose($messageid='',$todo=''){
  global $PAGE,$JAX,$USER,$DB,$CFG;
  $showfull=0;
  if($JAX->p['submit']) {
   $mid=$JAX->b['mid'];
   if(!$mid&&$JAX->b['to']) {
    $DB->select("id","members","WHERE display_name=".$DB->evalue($JAX->b['to']));
    $mid=$DB->row();
    if($mid) $mid=array_pop($mid);
   }
   if(!$mid) $e="Invalid user!";
   else if(!trim($JAX->b['title'])) $e="You must enter a title.";
   if($e) {$PAGE->JS("error",$e);$PAGE->append("PAGE",$PAGE->error($e));}
   else {
    $DB->insert("messages",Array("to"=>$mid,"from"=>$USER['id'],"title"=>$JAX->blockhtml($JAX->p['title']),"message"=>$JAX->p['message'],"date"=>time(),"del_sender"=>0,"del_recipient"=>0,"read"=>0));
    $cmd=$JAX->json_encode(Array("newmessage","You have a new message from ".$USER['display_name'],$DB->insert_id()))."\n";
    $DB->special("UPDATE %t SET runonce=concat(runonce,".$DB->evalue($cmd,1).") WHERE uid=".$mid,"session");
    $this->showwholething("Message successfully delivered.<br /><br /><a href='?act=inbox'>Back</a>");
    return;
   }
  }
  if($PAGE->jsupdate&&!$messageid) return;
  $msg='';
  if($messageid) {
   $DB->select("*","messages","WHERE (`to`=".$USER['id']." OR `from`=".$USER['id'].") AND `id`=".$DB->evalue($messageid));
   $message=$DB->row();
   $mid=$message['from'];
   $DB->select("display_name","members","WHERE id=".$mid);
   $mname=array_pop($DB->row());
   $msg="\n\n\n".'[quote='.$mname.']'.$message['message'].'[/quote]';
   $mtitle=($todo=="fwd"?"FWD:":"RE:").$message['title'];
   if($todo=="fwd") {
     $mid=$mname="";
   }
  }
  if(is_numeric($JAX->g['mid'])) {
   $showfull=1;
   $mid=$JAX->b['mid'];
   $DB->select("display_name","members","WHERE id=".$mid);
   $mname=array_pop($DB->row());
   if(!$mname) {$mid=0;$mname='';}
  }
  $page='<div class="composeform">
 <form method="post" onsubmit="$(\'pdedit\').editor.submit();return RUN.submitForm(this)">
 <div><label for="to">To:</label>
      <input type="hidden" id="mid" name="mid" onchange="$(\'validname\').className=\'good\'" value="'.$mid.'" />
      <input type="text" id="to" name="to" value="'.$mname.'" onkeydown="if(event.keyCode==13) return false;" onkeyup="$(\'validname\').className=\'bad\';JAX.autoComplete(\'act=searchmembers&term=\'+this.value,this,$(\'mid\'),event);" />
      <span id="validname"'.($mname?' class="good"':'').'></span>
 </div>
 <div><label for="title">Title:</label>
      <input type="text" id="title" name="title" value="'.$mtitle.'"/></div>
 <div><iframe onload="JAX.editor($(\'message\'),this)" style="display:none" id="pdedit"></iframe><textarea id="message" name="message">'.htmlspecialchars($msg).'</textarea></div>'
.$JAX->hiddenFormFields(Array("act"=>"inbox","page"=>"compose","submit"=>"1")).
'<input type="submit" value="Send" />
</form>
</div>';
  $this->showwholething($page,$showfull);
 }

 function delete($id){
  global $PAGE,$JAX,$DB,$USER;
  $DB->select("*","messages","WHERE `id`=".$DB->evalue($id));
  $message=$DB->row();
  $is_recipient=$message['to']==$USER['id'];
  $is_sender=$message['from']==$USER['id'];
  if($is_recipient) $DB->update("messages",Array("del_recipient"=>1),"WHERE id=".$DB->evalue($id));
  if($is_sender)    $DB->update("messages",Array("del_sender"=>1),"WHERE id=".$DB->evalue($id));
  $PAGE->location("?act=inbox".($JAX->b['prevpage']?"&page=".$JAX->b['prevpage']:''));
 }
}
?>