<?
$PAGE->loadmeta('members');
new members;
class members{
 function members(){$this->__construct();}
 function __construct(){
  global $JAX,$PAGE;
  $this->page=0;
  $this->perpage=20;
  if(is_numeric($JAX->b['page'])&&$JAX->b['page']>0) $this->page=$JAX->b['page']-1;
  if(!$PAGE->jsupdate) $this->showmemberlist();
 }
 function showmemberlist(){
  global $PAGE,$DB,$JAX;
  $vars=Array("display_name"=>"Name","g_title"=>"Group","id"=>"ID","posts"=>"Posts");
  
  $sortby="display_name";$sorthow=$JAX->b['how']=="desc"?"desc":"asc";
  if($vars[$JAX->b['sortby']]) $sortby=$JAX->b['sortby'];
  if($JAX->g['filter']=='staff') {
    $sortby='g.can_access_acp DESC ,'.$sortby;
    $where="WHERE g.can_access_acp=1 OR g.can_moderate=1";
  }
  

  $pages="";
  $memberquery=$DB->special("SELECT SQL_CALC_FOUND_ROWS m.*,g.title g_title FROM %t m LEFT JOIN %t g ON g.id=m.group_id $where ORDER BY ".$sortby." ".$sorthow." LIMIT ".($this->page*$this->perpage).','.$this->perpage,"members","member_groups");
  $nummembers=array_pop($DB->row($DB->query("SELECT FOUND_ROWS()")));
  foreach($JAX->pages(ceil($nummembers/$this->perpage),$this->page+1,$this->perpage) as $v) $pages.="<a href='?act=members&amp;sortby=$sortby&amp;how=$sorthow&amp;page=$v'".($v-1==$this->page?' class="active"':'').">$v</a> ";
  $url="?act=members".($this->page?"&page=".($this->page+1):'').($JAX->g['filter']?'&filter='.$JAX->g['filter']:'');
  $links=Array();
  foreach($vars as $k=>$v) {
   $links[]="<a href=\"$url&amp;sortby=$k".($sortby==$k?($sorthow=="asc"?'&amp;how=desc':'').'" class="sort'.($sorthow=="desc"?" desc":""):'')."\">$v</a>";
  }
  while($f=$DB->row($memberquery)) {
   $contactdetails="";
   foreach(Array("skype"=>"skype:%s","msn"=>"msnim:chat?contact=%s","gtalk"=>"gtalk:chat?jid=%s","aim"=>"aim:goaim?screenname=%s","yim"=>"ymsgr:sendim?%s","steam"=>"http://steamcommunity.com/id/%s","twitter"=>"http://twitter.com/%s") as $k=>$v)
   if($f['contact_'.$k]) $contactdetails.='<a class="'.$k.' contact" href="'.sprintf($v,$JAX->blockhtml($f['contact_'.$k])).'">&nbsp;</a>';
   $contactdetails.='<a class="pm contact" href="?act=ucp&amp;what=inbox&amp;page=compose&amp;mid='.$f['id'].'"></a>';
   $page.=$PAGE->meta('members-row',
     $f['id'],
     $JAX->pick($f['avatar'],$PAGE->meta('default-avatar')),
     $PAGE->meta('user-link',$f['id'],$f['group_id'],$f['display_name']),
     $f['g_title'],
     $f['id'],
     $f['posts'],
     $JAX->date($f['join_date']),
     $contactdetails
     );
  }
  $page=$PAGE->meta('members-table',$links[0],$links[1],$links[2],$links[3],$page);
  $page="<div class='pages pages-top'>$pages</div>".$PAGE->meta('box',' id="memberlist"','Members',$page)."<div class='pages pages-bottom'>$pages</div><div class='clear'></div>";
  $PAGE->JS("update","page",$page);
  $PAGE->append("PAGE",$page);
 }
}
?>