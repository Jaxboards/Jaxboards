<?php
$PAGE->loadmeta("idx");
new IDX;
class IDX{
	/* Redundant constructor unnecesary in newer PHP versions. */
	/* function IDX(){
		$this->__construct();
	} */
	function __construct(){
		global $PAGE,$CFG,$JAX,$SESS;
        if($JAX->b['markread']) {$PAGE->JS("softurl");$SESS->forumsread=$SESS->topicsread="";$SESS->readtime=time();}
		if($PAGE->jsupdate) $this->update();
		else $this->viewidx();
	}
	function viewidx(){
		global $DB,$PAGE,$SESS,$JAX,$USER,$CFG;
		$SESS->location_verbose="Viewing board index";
		$result = $DB->safespecial("SELECT f.*,m.display_name lp_name,m.group_id lp_gid FROM %t AS f LEFT JOIN %t AS m ON f.lp_uid=m.id ORDER BY `order`,f.title ASC",
			array("forums","members"));
		$data=$this->subforums=$this->subforumids=$this->mods=Array();

        //this while loop just grabs all of the data, displaying is done below
		while($r=$DB->row($result)) {
            $perms=$JAX->parseperms($r['perms'],$USER?$USER['group_id']:3);
            if($r['perms']&&!$perms['view']) continue;
            //store subforum details for later
			if($r['path']) {
				//if($r['show_sub']==1) {
					preg_match("@\d+$@",$r['path'],$m);
                    $this->subforumids[$m[0]][]=$r['id'];
					$this->subforums[$m[0]].=$PAGE->meta('idx-subforum-link',$r['id'],$r['title'],$JAX->blockhtml($r['subtitle'])).$PAGE->meta('idx-subforum-splitter');
				/*} elseif($r['show_sub']==2) {
					if(is_numeric($r['path'])) $subforums[$r['path']].=$PAGE->meta('idx-subforum-link',$r['id'],$r['title']).$PAGE->meta('idx-subforum-splitter');
				}*/
			} else $data[$r['cat_id']][]=$r;

            //store mod details for later
            if($r['show_ledby']&&$r['mods']) {
             foreach(explode(',',$r['mods']) as $v) if($v) $this->mods[$v]=1;
            }
		}
        $this->mods=array_keys($this->mods);
		$catq=$DB->safeselect("*","categories","ORDER BY `order`,title ASC");
		while($r=$DB->row($catq)) {
			if(!empty($data[$r['id']]))
				$page.=$PAGE->collapsebox($r['title'],$this->buildTable($data[$r['id']]),"cat_".$r['id']);
        }
        $page.=$PAGE->meta('idx-tools');

        $page.=$this->getBoardStats();

		if($PAGE->jsnewlocation) {
			$PAGE->JS("update","page",$page);
			$PAGE->updatepath();
		} else {
			$PAGE->append("PAGE",$page);
		}
	}
    function getsubs($id){
     if(!$this->subforumids[$id]) return Array();
     $r=$this->subforumids[$id];
     foreach($r as $v) {
      if($this->subforumids[$v]) {
       $r=array_merge($r,$this->getsubs($v));
      }
     }
     return $r;
    }
    function getmods($modids){
     global $DB,$PAGE;
     if(!$this->moderatorinfo) {
      $this->moderatorinfo=Array();
      $result = $DB->safeselect("id,display_name,group_id","members","WHERE id IN ?", $this->mods);
      while($f=$DB->row($result)) $this->moderatorinfo[$f['id']]=$PAGE->meta('user-link',$f['id'],$f['group_id'],$f['display_name']);
     }
     foreach(explode(',',$modids) as $v) $r.=$this->moderatorinfo[$v].$PAGE->meta('idx-ledby-splitter');
     return substr($r,0,-strlen($PAGE->meta('idx-ledby-splitter')));
    }
	function buildTable($a){
		global $PAGE,$JAX;
		if(!$a) return;
		$r='';
		foreach($a as $v) {
            $sf="";
            if($v['show_sub']>=1)
             $sf=$this->subforums[$v['id']];
            if($v['show_sub']==2)
             foreach($this->getsubs($v['id']) as $i) $sf.=$this->subforums[$i];
            if($v['redirect']){
            $r.=$PAGE->meta('idx-redirect-row',
                $v['id'],
                $v['title'],
                nl2br($v['subtitle']),
                'Redirects: '.$v['redirects'],
                $JAX->pick($PAGE->meta('icon-redirect'),$PAGE->meta('idx-icon-redirect'))
            );
            } else {
			$r.=$PAGE->meta('idx-row',
				$v['id'],
				$JAX->wordfilter($v['title']),
				nl2br($v['subtitle']),
				$sf?
					$PAGE->meta('idx-subforum-wrapper',
						substr($sf, 0, -1*strlen($PAGE->meta('idx-subforum-splitter')))
					):"",
				$this->formatlastpost($v),
				$PAGE->meta('idx-topics-count',$v['topics']),
				$PAGE->meta('idx-replies-count',$v['posts']),
                ($read=$this->isForumRead($v))?'read':'unread',
                '<a id="fid_'.$v['id'].'_icon"'.(!$read?' href="?act=vf'.$v['id'].'&amp;markread=1"':'').'>'.($read?$JAX->pick($PAGE->meta('icon-read'),$PAGE->meta('idx-icon-read')):$JAX->pick($PAGE->meta('icon-unread'),$PAGE->meta('idx-icon-unread'))).'</a>',
                $v['show_ledby']&&$v['mods']?$PAGE->meta('idx-ledby-wrapper',$this->getmods($v['mods'])):''
			);
            }
		}
		return $PAGE->meta('idx-table',$r);
	}
	function update(){
		$this->updateStats();
		$this->updateLastPosts();
	}
    function getBoardStats(){
        global $DB,$JAX,$PAGE,$PERMS;
        if (!$PERMS['can_view_stats']) return "";
        $result = $DB->safespecial("SELECT s.*,m.group_id,m.display_name FROM %t s LEFT JOIN %t m ON s.last_register=m.id", array("stats","members"));
        $stats=$DB->row($result);
	$DB->disposeresult($result);

        $result = $DB->safespecial("SELECT max(s.last_update) last_update,m.id,m.group_id,m.display_name name,concat(m.dob_month,' ',m.dob_day) birthday,hide,readtime FROM %t s LEFT JOIN %t m ON s.uid=m.id WHERE s.uid GROUP BY s.uid ORDER BY name",
		array('session','members'));
        $nuserstoday=0;
        $today=date('n j');
        while($f=$DB->row($result)) {
            if(!$f['id']) continue;
            $userstoday.='<a href="?act=vu'.$f['id'].'" class="user'.$f['id'].' mgroup'.$f['group_id'].(($f['birthday']==$today&&($CFG['birthdays']&1))?' birthday':'').'" onmouseover="JAX.tooltip(this)" title="Last online: '.$JAX->date(($f['hide']?$f['readtime']:$f['last_update']),false).'">'.$f['name'].'</a>, ';
            $nuserstoday++;
        }
        $userstoday=substr($userstoday,0,-2);
        $usersonline=$this->getusersonlinelist();
        $result = $DB->safeselect("id,title","member_groups","WHERE legend=1 ORDER BY title");
        while($row=$DB->row($result)){
            $legend.='<a href="?" class="mgroup'.$row['id'].'">'.$row['title'].'</a> ';
        }
        $page.=$PAGE->meta('idx-stats',
            $usersonline[1],
            $usersonline[0],
            $usersonline[2],
            $nuserstoday,
            $userstoday,
            number_format($stats['members']),
            number_format($stats['topics']),
            number_format($stats['posts']),
            $PAGE->meta('user-link',
                $stats['last_register'],
                $stats['member_group'],
                $stats['display_name']
                ),
            $legend
            );
        return $page;
    }
	function getusersonlinelist(){
		global $DB,$PAGE,$JAX,$CFG;
		$r='';
        $guests=0;
		foreach($DB->getUsersOnline() as $f)
			if($f['uid']||$f['is_bot']) {
				$title=$JAX->blockhtml($JAX->pick($f['location_verbose'],"Viewing the board."));
				if($f['is_bot']) $r.='<a class="user'.$f['uid'].'" title="'.$title.'" onmouseover="JAX.tooltip(this)">'.$f['name']."</a>";
				else {
                 $nummembers++;
                 $r.=sprintf('<a href="?act=vu%1$s" class="user%1$s mgroup%2$s" title="%4$s" onmouseover="JAX.tooltip(this)">%3$s</a>',
                    $f['uid'],
                    $f['group_id'].($f['status']=="idle"?" idle":($f['birthday']&&($CFG['birthdays']&1)?" birthday":"")),
		    $f['name'],
		    $title
		  );
                }
			} else $guests=$f;
		return Array($r,$nummembers,$guests);
	}
	function updateStats(){
		global $PAGE,$DB,$SESS,$CFG;
		$list=Array();
		if($SESS->users_online_cache) $oldcache=array_flip(explode(",",$SESS->users_online_cache));
		$useronlinecache="";
		foreach($DB->getUsersOnline() as $f) {
			if($f['uid']||$f['is_bot']) {
				if($f['last_action']>=$SESS->last_update  ||  $f['status']=="idle"&&$f['last_action']>($SESS->last_update-$CFG['timetoidle']-30))
					$list[]=Array($f['uid'],$f['group_id'],($f['status']!="active"?$f['status']:($f['birthday']&&($CFG['birthdays']&1)?" birthday":"")),$f['name'],$f['location_verbose']);
				unset($oldcache[$f['uid']]);
				$useronlinecache.=$f['uid'].",";
			}
		}
		if(!empty($oldcache)) $PAGE->JS("setoffline",implode(",",array_flip($oldcache)));
		$SESS->users_online_cache=substr($useronlinecache,0,-1);
		if(!empty($list)) $PAGE->JS("onlinelist",$list);
	}
	function updateLastPosts(){
		global $DB,$SESS,$PAGE,$JAX;
		$result = $DB->safespecial("SELECT f.id,f.lp_tid,f.lp_topic,f.lp_date,f.lp_uid,f.topics,f.posts,m.display_name lp_name,m.group_id lp_gid FROM %t AS f LEFT JOIN %t AS m ON f.lp_uid=m.id WHERE f.lp_date>=?",
			array("forums","members"),
			$JAX->pick($SESS->last_update,time())
		);


		while($f=$DB->row($result)) {
            $PAGE->JS("addclass","#fid_".$f['id'],"unread");
            $PAGE->JS("update","#fid_".$f['id']."_icon",$JAX->pick($PAGE->meta('icon-unread'),$PAGE->meta('idx-icon-unread')));
			$PAGE->JS("update","#fid_".$f['id']."_lastpost",$this->formatlastpost($f),"1");
			$PAGE->JS("update","#fid_".$f['id']."_topics",$PAGE->meta('idx-topics-count',$f['topics']));
			$PAGE->JS("update","#fid_".$f['id']."_replies",$PAGE->meta('idx-replies-count',$f['posts']));
		}
	}

	function formatlastpost($v){
		global $PAGE,$JAX;
		return $PAGE->meta('idx-row-lastpost',
			$v['lp_tid'],
			$JAX->pick($JAX->wordfilter($v['lp_topic']),"- - - - -"),
			$v['lp_uid']?$PAGE->meta("user-link",$v['lp_uid'],$v['lp_gid'],$v['lp_name']):"None",
			$JAX->pick($JAX->date($v['lp_date']),"- - - - -")
		);
	}
    function isForumRead($forum){
     global $SESS,$USER,$JAX;
     if(!$this->forumsRead) {
      $this->forumsRead=$JAX->parsereadmarkers($SESS->forumsread);
     }
     if($forum['lp_date']>$JAX->pick($this->forumsRead[$forum['id']],$SESS->readtime,$USER['last_visit'])) return false;
     return true;
    }
}
?>
