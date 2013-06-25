<?php
$meta=Array(
'path'=>'<ul>%1$s</ul><div class="clear"></div>',
'path-part'=>'<li><a href="%1$s">%2$s</a></li>',
'window'=>'<div class="window"><div class="title" onmousedown="(new JAX.drag).apply(this.parentNode,this)">Window <div class="x">X</div></div><div class="content">Content</div></div>',
'box'=>'<div class="box"%1$s><div class="title">%2$s</div><div class="content_top"></div><div class="content">%3$s</div><div class="content_bottom"></div></div>',
'collapsebox'=>'<div class="box"%1$s><div class="title"><div class="x" onclick="JAX.collapse(this.parentNode.nextSibling)">-/+</div>%2$s</div><div class="collapse_content"><div class="content_top"></div><div class="content">%3$s</div><div class="content_bottom"></div></div></div>',
'error'=>'<div class="error">%1$s</div>',
'success'=>'<div class="success">%1$s</div>',
'logo'=>'<div id="logo"><a href="?"><img src="%1$s" alt="JaxBoards Logo" /></a></div>',
'activity'=>'<div class="activity">%1$s</div>',

'navigation'=>'<div class="navigation" onclick="JAX.handleTabs(event,this,function(e){return e.parentNode;})"><ul><li class="active"><a href="?">Home</a></li><li><a href="?module=buddylist">Contacts</a></li><li><a href="?act=search">Search</a></li><li><a href="?act=members">Members</a></li><li><a href="?act=ticker">Ticker</a></li><li><a href="?act=calendar">Calendar</a></li>%1$s%2$s%3$s</ul></div>',
'modlink'=>'<a href="?act=modcontrols&amp;do=cp">Mod CP</a>',
'acplink'=>'<a href="./acp/">ACP</a>',

//'icon-unread'=>'<img src="'.BOARDPATH.'Service/Themes/Default/icons/unread.png" />',
//'icon-read'=>'<img src="'.BOARDPATH.'Service/Themes/Default/icons/read.png" />',


'default-avatar'=>BOARDPATH."Service/Themes/Default/avatars/default.gif",


'userbox-logged-out'=>'<form onsubmit="return RUN.submitForm(this,1)" action="?" method="post"><div>Username: <input type="text" name="user" tabindex="1" /><br />Password: <a href="?act=logreg6" class="forgot">Forgot?</a><input type="password" name="pass" tabindex="2" /><br /><input type="hidden" name="act" value="logreg" /><input type="submit" value="Login" /> <a href="?act=logreg1">Register</a></div></form>',
'userbox-logged-in'=>'<div class="welcome">Hi, %1$s!  <a href="?act=logreg2">Logout</a></div><div class="lastvisit">Last Visit: %2$s</div><div class="messages">Inbox: <a href="?act=ucp&amp;what=inbox" id="num-messages" class="num-messages">%3$s</a></div><div class="settings"><a href="?act=ucp">Settings</a></div>',

'user-link'=>'<a href="?act=vu%1$s" class="user%1$s mgroup%2$s">%3$s</a>',

'rating-wrapper'=>'<div class="postrating"><div class="form">Rate: %1$s %2$s</div><div class="showrating">%3$s</div></div>',
'rating-niblet'=>'<img src="%1$s" title="%2$s" alt="%2$s" />'
);
?>
