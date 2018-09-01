<?php

$meta = array(
    'forum-subforum-row' => <<<'EOT'
<tr id="fid_%1$s" class="%7$s">
    <td class="f_icon" rowspan="2">
        <a id="fid_%1$s_icon" href="?act=vf%1$s&amp;markread=1">
            %8$s
        </a>
    </td>
    <td class="forum" rowspan="2">
        <a href="?act=vf%1$s">
            %2$s
        </a>
        <div class="description">
            %3$s
        </div>
    </td>
    <td class="last_post" colspan="2">
        %4$s
    </td>
</tr>
<tr>
    <td class="item_1">
        Topics: %5$s
    </td>
    <td class="item_2">
        Replies: %6$s
    </td>
</tr>
EOT
    ,
    'forum-subforum-table' => <<<'EOT'
<table class="subindex">
    %1$s
</table>
EOT
    ,
    'forum-subforum-lastpost' => <<<'EOT'
Last Post:
<a href="?act=vt%1$s&amp;getlast=1">
    %2$s
</a>
<br />
By %3$s, %4$s
EOT
    ,
    'forum-table' => <<<'EOT'
<table class="forumindex">
    %1$s
</table>
EOT
    ,
    'forum-row' => <<<'EOT'
<tr id="fr_%1$s" class="%9$s %13$s">
    <td class="f_icon">
        %14$s
    </td>
    <td class="topic">
        <a href="?act=vt%1$s" title="%10$s" onmouseover="JAX.tooltip(this)">
            %2$s
        </a>
        %11$s
        <br />
        %3$s %12$s
    </td>
    <td class="item_1">
        %4$s
    </td>
    <td class="item_2">
        <a href="?act=forum&amp;replies=%1$s">
            Replies: %5$s
        </a>
        <br />
        Views:&nbsp;%6$s
    </td>
    <td class="last_post">
        <a href="?act=vt%1$s&amp;getlast=1">
            %7$s
        </a>
        <br />
        By %8$s
    </td>
</tr>
EOT
    ,
    'forum-topic-pages' => <<<'EOT'
<span class="pages">
    Pages: %s
</span>
EOT
    ,
    'forum-pages-top' => <<<'EOT'
<div class="pages-top pages">
    %s
</div>
EOT
    ,
    'forum-pages-bottom' => <<<'EOT'
<div class="pages-bottom pages">
    %1$s
</div>
EOT
    ,
    'forum-button-newtopic' => 'New Topic',
    'forum-buttons-top' => <<<'EOT'
<div class="forum-buttons-top">
    %s
</div>
EOT
    ,
    'forum-buttons-bottom' => <<<'EOT'
<div class="forum-buttons-top">
    %s
</div>
EOT
    ,
    'forum-pages-part' => <<<'EOT'
<a href="?act=vt%1$s"%2$s>
    %3$s
</a>

EOT
    ,
    'subforum-icon-unread' => '<img src="'.BOARDURL.
    'Service/Themes/Default/icons/unread.png" />',
    'subforum-icon-read' => '<img src="'.BOARDURL.
    'Service/Themes/Default/icons/read.png" />',
);
