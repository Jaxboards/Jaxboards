<?php

$meta = array(
    'userprofile-contact-card' => <<<'EOT'
<div class="contact-card">
    <table>
        <tr>
            <td class="left">
                <div class="username">
                    %1$s
                </div>
                <div class="avatar">
                    <img src="%2$s" />
                </div>
                <div class="usertitle">
                    %3$s
                </div>
            </td>
            <td class="right">
                <a href="javascript:void(0)"
                    onclick="
                        IMWindow('%4$s',this.innerHTML.match(/IM (.+)/)[1]);
                        JAX.window.close(this);
                        return false;
                    ">
                    IM %1$s
                </a>
                <br />
                <a href="?act=ucp&what=inbox&page=compose&mid=%4$s"
                    onclick="JAX.window.close(this)">
                    PM %1$s
                </a>
                <br />
                <br />
                %6$s
                <br />
                %7$s
                <br />
                <br />
                <a href="?act=vu%4$s&view=profile" onclick="JAX.window.close(this)">
                    Full Profile
                </a>
                <div class="contact_details">
                    %5$s
                </div>
            </td>
        </tr>
    </table>
</div>
EOT
    ,
    'userprofile-full-profile' => <<<'EOT'
<div class="userprofile">
    <div class="leftbar">
        <div class="username">
            %1$s %22$s
        </div>
        <div class="avatar">
            <img src="%2$s" />
        </div>
        <div class="usertitle">
            %3$s
        </div>
        <div class="infobox contact">
            Contact Details:
            <div class="content">
                %4$s
            </div>
        </div>
        <div class="infobox personal">
            Personal info:
            <div class="content">
                <div>
                    Full name: %5$s
                </div>
            <div>
                Gender: %6$s
            </div>
            <div>
                Location: %7$s
            </div>
            <div>
                DOB: %8$s
            </div>
            <div>
                Site: %9$s
            </div>
        </div>
    </div>
    <div class="infobox stats">
        Stats:
        <div class="content">
            <div>
                Joined: %10$s
            </div>
            <div>
                Last Visit: %11$s
            </div>
            <div>
                Member: #%12$s
            </div>
            <div>
                Posts: %13$s
            </div>
            <div>
                Group: %14$s
            </div>
        </div>
    </div>
</div>
<div class="rightbar pftabs" onclick="JAX.handleTabs(event,this)">
    %15$s%16$s%17$s%18$s%19$s%20$s
</div>
<div class="rightbar" id="pfbox">
    %21$s
</div>
<div class="clear">
</div>
EOT
    ,
    'userprofile-comment-form' => <<<'EOT'
<div class="comment">
    <div class="userdata">
        <div class="username">
            %s
        </div>
        <div class="avatar">
            <img src="%s" />
        </div>
    </div>
    <div class="commenttext">
        <form onsubmit="return RUN.submitForm(this,1)">
            %s
            <textarea name="comment"></textarea>
            <br />
            <input type="submit" value="Comment" />
        </form>
    </div>
</div>
EOT
    ,
    'userprofile-friend' => <<<'EOT'
<div class="contact" onclick="return RUN.stream.location('?act=vu%s')">
    <div class="avatar">
        <img src="%s" />
    </div>
    <div class="name">
        %s
    </div>
</div>
EOT
    ,
    'userprofile-comment' => <<<'EOT'
<div class="comment">
    <div class="userdata">
        <div class="username">
            %s
        </div>
        <div class="avatar">
            <img src="%s" />
        </div>
    </div>
    <div class="date">
        %s
    </div>
    <div class="commenttext">
        %s
    </div>
</div>
EOT
    ,
    'userprofile-post' => <<<'EOT'
<div class="post">
    <a href="?act=vt%1$s">
        %2$s
    </a>
    <a href="?act=vt%1$s&findpost=%3$s">
        (Post)
    </a>
    <div class="postdate">
        Posted on: %4$s
    </div>
    %5$s
</div>
EOT
    ,
    'userprofile-about' => <<<'EOT'
%1$s
<div class="signature">
    %2$s
</div>
EOT
    ,
    'userprofile-topic' => <<<'EOT'
<div class="post">
    <a href="?act=vt%1$s">
        %2$s
    </a>
    <div class="postdate">
        Posted on: %3$s
    </div>
    %4$s
</div>
EOT
    ,
);
