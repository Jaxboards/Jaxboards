<?php

$result = $DB->safequery('SELECT t.title,t.id,p.post FROM jaxboards_topics t LEFT JOIN jaxboards_posts p ON t.op=p.id WHERE fid=7 ORDER BY t.id DESC LIMIT 1');

echo $DB->error(1);
$newsdata=$DB->row($result);
$DB->disposeresult($result);

$news=$JAX->theworks($newsdata['post'],Array('noemotes'=>1))."<br /><br /><a href='?act=vt".$newsdata['id']."'>Comment on this post</a>"; // was http://support.jaxboards.com/?act=vt
$todo=<<<HEREDOC
A live document of upcoming features to jaxboards can be found <a href="https://docs.google.com/document/d/1Hi3pIDlpP3ORPftdP_L0PhgWEGlXVUc2araHkZmy4uM/edit?hl=en_US">here</a>.

<p>Just keep in mind that jaxboards will never be finished, not because I'm not working on it, but because there is always features to add/improve.</p>
HEREDOC;
$PAGE->addContentBox('Latest News - '.$newsdata['title'],$news);
$PAGE->addContentBox('Running TODO list',$todo);
?>
