<?php

$meta = array(
    'ucp-index' => <<<'EOT'
 <form method="post"
    onsubmit="document.querySelector('#npedit').editor.submit();return RUN.submitForm(this)">
    %s
    <div class="username">
        %s
    </div>
    <div class="avatar">
        <img src="%s" />
    </div>
    <textarea id="notepad" name="ucpnotepad">%s</textarea>
    <iframe id="npedit" onload="JAX.editor(document.querySelector('#notepad'),this)"
        style="display:none">
    </iframe>
    <input type="submit" value="Save" />
</form>
EOT
    ,
    'ucp-wrapper' => <<<'EOT'
<div class="box" id="ucp">
    <div class="title">
        UCP
    </div>
    <div class="content_top">
    </div>
    <div class="content">
        <div class="leftbar" class="folders">
            <h2>
                Settings
            </h2>
            <a href="?act=ucp">
                Notepad
            </a>
            <a href="?act=ucp&what=pass">
                Change Password
            </a>
            <a href="?act=ucp&what=email">
                Email
            </a>
            <a href="?act=ucp&what=avatar">
                Avatar
            </a>
            <a href="?act=ucp&what=signature">
                Signature
            </a>
            <a href="?act=ucp&what=profile">
                Profile
            </a>
            <a href="?act=ucp&what=sounds">
                Sounds/Notifications
            </a>
            <a href="?act=ucp&what=board">
                Board Customization
            </a>
            <h2>
                Private Messaging
            </h2>
            <a class="icon compose" href="?act=ucp&what=inbox&page=compose">
                Compose
            </a>
            <a class="icon inbox" href="?act=ucp&what=inbox">
                Inbox
            </a>
            <a class="icon sent" href="?act=ucp&what=inbox&page=sent">
                Sent
            </a>
            <a class="icon flagged" href="?act=ucp&what=inbox&page=flagged">
                Flagged
            </a>
        </div>
        <div id="ucppage" class="inbox">
            %s
        </div>
        <div class="clear">
        </div>
    </div>
    <div class="content_bottom">
    </div>
</div>
EOT
    ,
    'ucp-sound-settings' => <<<'EOT'
<h2>
    Sounds
</h2>
<form onsubmit="return RUN.submitForm(this)" method="post">
    %s
    When someone shouts:
    <br />
    &nbsp;%s play a sound
    <br />
    <br />
    When someone IMs me:
    <br />
    &nbsp;%s play a sound
    <br />
    &nbsp;
    <input type="checkbox"
        onclick="if(!window.webkitNotifications) {
            alert('browser not supported');
        } webkitNotifications.requestPermission()"
        id="dtnotify" />
    display a desktop notification
    <br />
    <br />
    When someone PMs me:
    <br />
    &nbsp;%s play a sound
    <br />
    &nbsp;%s display a notification
    <br />
    <br />
    When someone posts in a topic I've created:
    <br />
    &nbsp;%s play a sound
    <br />
    &nbsp;%s display a notification
    <br />
    <br />
    When someone posts in a topic I'm subscribed to:
    <br />
    &nbsp;%s play a sound
    <br />
    &nbsp;%s display a notification
    <br />
    <br />
    <input type="submit" value="Save" />
</form>
EOT
    ,
    'ucp-sig-settings' => <<<'EOT'
<form onsubmit="document.querySelector('#npedit').editor.submit();return RUN.submitForm(this)"
    method="post">
    %s
    Signature preview:
    <br />
    %s
    <br />
    <br />
    <textarea name="changesig" id="changesig">%s</textarea>
    <iframe id="npedit" onload="JAX.editor(document.querySelector('#changesig'),this)"
        style="display:none">
    </iframe>
    <br />
    <input type="submit" value="Change" />
</form>
EOT
    ,
    'ucp-pass-settings' => <<<'EOT'
<form onsubmit="return RUN.submitForm(this)" method="post">
    %s
    <label for="curpass">
        Current Password:
    </label>
    <input type="password" name="curpass" id="curpass" />
    <br />
    <br />
    <label for="newpass1">
        New Password:
    </label>
    <input type="password" name="newpass1" id="newpass1" />
    <input name="showpass" type="checkbox" onclick="
        document.querySelector('#newpass1').type=this.checked?'text':'password';
        document.querySelector('#confirmpass').style.display=this.checked?'none':'';
        " />
    Show
    <br />
    <div id="confirmpass">
        <label for="newpass2">
            Confirm:
        </label>
        <input type="password" name="newpass2" id="newpass2" />
    </div>
    <br />
    <input type="Submit" />
</form>
EOT
    ,
    'ucp-profile-settings' => <<<'EOT'
<form method="post"
    onsubmit="document.querySelector('#abouteditor').editor.submit();return RUN.submitForm(this)">
    %s
    <h2>
        Name:
    </h2>
    <div>
        Your username:
        <strong>
            %s
        </strong>
    </div>
    <div>
        <label for="username">
            Display Name:
        </label>
        <input type="text" name="display_name" id="username" value="%s" />
    </div>
    <div>
        <label for="realname">
            Real name:
        </label>
        <input type="text" name="full_name" id="realname" value="%s"/>
    </div>
    <div>
        <label for="usertitle">
            Title:
        </label>
        <input type="text" name="usertitle" id="usertitle" value="%s" />
    </div>
    <h2>
        About you:
    </h2>
    <div class="description">
        <textarea rows="10" cols="60" name="about" id="about">%s</textarea>
        <iframe id="abouteditor" onload="JAX.editor(document.querySelector('#about'),this)"
            style="display:none">
        </iframe>
        <h2>
            Location
        </h2>
        <input type="text" name="location" value="%s" />
        <h2>
            Gender
        </h2>
        %s
        <h2>
            Date of Birth:
        </h2>
        %s
        <h2>
            Contact Details (Optional)
        </h2>
        <div id="contact_details">
            <div class="skype">
                <label for="con_skype">
                    Skype:
                </label>
                <input type="text" name="con_skype" id="con_skype" value="%s" />
            </div>
            <div class="yim">
                <label for="con_yim">
                    YIM:
                </label>
                <input type="text" name="con_yim" id="con_yim" value="%s" />
            </div>
            <div class="msn">
                <label for="con_msn">
                    MSN:
                </label>
                <input type="text" name="con_msn" id="con_msn" value="%s" />
            </div>
            <div class="gtalk">
                <label for="con_gtalk">
                    Google Talk:
                </label>
                <input type="text" name="con_gtalk" id="con_gtalk" value="%s" />
            </div>
            <div class="aim">
                <label for="con_aim">
                    AIM:
                </label>
                <input type="text" name="con_aim" id="con_aim" value="%s" />
            </div>
            <div class="steam">
                <label for="con_steam">
                    Steam:
                </label>
                <input type="text" name="con_steam" id="con_steam" value="%s" />
            </div>
            <div class="twitter">
                <label for="con_twitter">
                    Twitter:
                </label>
                <input type="text" name="con_twitter" id="con_twitter" value="%s" />
            </div>
        </div>
        <h2>
            Website
        </h2>
        <label for="url">
            URL:
        </label>
        <input type="text" name="website" id="url" value="%s" />
    </div>
    <div>
        <input name="submit" type="submit" value="Save Profile Settings" />
    </div>
</form>
EOT
    ,
    'ucp-board-settings' => <<<'EOT'
<form method="post" onsubmit="return RUN.submitForm(this)">
    %s
    Board Skin: %s
    <br />
    <br />
    Use word filter: %s
    <br />
    <br />
    WYSIWYG Enabled by Default: %s
    <br />
    <br />
    <input type="submit" value="Save" />
</form>
EOT
    ,
    'ucp-email-settings' => <<<'EOT'
<form method="post" onsubmit="return RUN.submitForm(this)">
    %s
    Your current email: %s
    <br />
    <br />
    %s Receive notifications
    <br />
    %s Receive email from administrators
    <br />
    <br />
    <input type="submit" name="submit" value="Save" />
</form>
EOT
    ,
    'inbox-messageview' => <<<'EOT'
<div class="messageview">
    <div class="messageinfo">
        <div class="title">
            %1$s
        </div>
        <div>
            From: %2$s
        </div>
        <div>
            Sent: %3$s
        </div>
    </div>
    <div class="message">
        %4$s
    </div>
    <div class="messagebuttons">
        <form method="post" onsubmit="return RUN.submitForm(this,0,event)">
        %7$s
        <input type="submit" name="page" onclick="this.form.submitButton=this;"
            value="Delete" />
        <input type="submit" onclick="this.form.submitButton=this;" name="page"
            value="Forward" />
        <input type="submit" onclick="this.form.submitButton=this;" name="page"
            value="Reply" />
        </form>
    </div>
</div>
EOT
    ,
    'inbox-composeform' => <<<'EOT'
<div class="composeform">
    <form method="post"
        onsubmit="document.querySelector('#pdedit').editor.submit();return RUN.submitForm(this)">
        %1$s
        <div>
            <label for="to">
                To:
            </label>
            <input type="hidden" id="mid" name="mid" value="%2$s"
                onchange="document.querySelector('#validname').className='good'" />
            <input type="text" id="to" name="to" value="%3$s" autocomplete="off"
                onkeydown="if(event.keyCode==13) return false;"
                onkeyup="
                    document.querySelector('#validname').className='bad';
                    JAX.autoComplete(
                        'act=searchmembers&term='+this.value,this,document.querySelector('#mid'),
                        event
                    );
                " />
            <span id="validname" class="%4$s">
            </span>
        </div>
        <div>
            <label for="title">
                Title:
            </label>
            <input type="text" id="title" name="title" value="%5$s"/>
        </div>
        <div>
            <textarea id="message" name="message">
                %6$s
            </textarea>
            <iframe id="pdedit"
                onload="JAX.editor(document.querySelector('#message'),this)" style="display:none">
            </iframe>
        </div>
        <input type="submit" value="Send" />
    </form>
</div>
EOT
    ,
    'inbox-messages-listing' => <<<'EOT'
<form method="post" onsubmit="return RUN.submitForm(this)">
    %s
    <table class="listing">
        <tr>
            <th class="center" width="5%%">
                <input type="checkbox"
                    onclick="JAX.checkAll(document.querySelectorAll('.check'),this.checked)" />
            </th>
            <th width="5%%">
                Flag
            </th>
            <th width="45%%">
                Title
            </th>
            <th width="20%%">
                %s
            </th>
            <th width="25%%">
                Date Sent
            </th>
        </tr>
        %s
        <tr>
            <td>
            </td>
            <td colspan="4">
                <input type="submit" value="Delete Messages" />
            </td>
        </tr>
    </table>
</form>
EOT
    ,

    'inbox-messages-row' => <<<'EOT'
<tr class="%1$s"
    onclick="if(JAX.event(event).srcElement.tagName=='TD') {
        this.querySelector('input').click();
    }">
    <td class="center">
        %2$s
    </td>
    <td class="center">
        %3$s
    </td>
    <td>
        <a href="?act=ucp&what=inbox&view=%4$s">
            %5$s
        </a>
    </td>
    <td>
        %6$s
    </td>
    <td>
        %7$s
    </td>
</tr>
EOT
    ,
);
