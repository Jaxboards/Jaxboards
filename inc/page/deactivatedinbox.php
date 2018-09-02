<?php

$PAGE->metadefs['inbox-messages-listing'] = <<<'EOT'
<table class="listing">
    <tr>
        <th class="center" width="5%%">
            <input type="checkbox"
                onclick="JAX.checkAll($$('.check'),this.checked)" />
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
</table>
EOT;

$IDX = new INBOX();
class INBOX
{
    public function __construct()
    {
        global $JAX,$PAGE,$USER;
        if (!$USER) {
            return $PAGE->location('?');
        }
        if (is_numeric($JAX->p['messageid'])) {
            switch (mb_strtolower($JAX->p['page'])) {
                case 'delete':
                    $this->delete($JAX->p['messageid']);
                    break;
                case 'forward':
                    $this->compose($JAX->p['messageid'], 'fwd');
                    break;
                case 'reply':
                    $this->compose($JAX->p['messageid']);
                    break;
            }
        } else {
            if (is_numeric($JAX->g['view'])) {
                $this->viewmessage($JAX->g['view']);
            } elseif ('compose' == $JAX->b['page']) {
                $this->compose();
            } elseif ('sent' == $JAX->b['page']) {
                $this->viewmessages('sent');
            } elseif ('flagged' == $JAX->b['page']) {
                $this->viewmessages('flagged');
            } elseif (is_numeric($JAX->b['flag'])) {
                $this->flag($JAX->b['flag']);
            } elseif (!$PAGE->jsupdate) {
                $this->viewmessages();
            }
        }
    }

    public function flag()
    {
        global $PAGE,$DB,$JAX,$USER;
        $PAGE->JS('softurl');
        $DB->safeupdate(
            'messages',
            array(
                'flag' => $JAX->b['tog'] ? 1 : 0,
            ),
            'WHERE `id`=? AND `to`=?',
            $DB->basicvalue($JAX->b['flag']),
            $USER['id']
        );
    }

    public function viewmessage($messageid)
    {
        global $PAGE,$DB,$JAX,$USER;
        if ($PAGE->jsupdate && !$PAGE->jsdirectlink) {
            return;
        }
        $result = $DB->safespecial(
            <<<'EOT'
SELECT a.`id` AS `id`,a.`to` AS `to`,a.`from` AS `from`,a.`title` AS `title`,
	a.`message` AS `message`,a.`read` AS `read`,a.`date` AS `date`,
	a.`del_recipient` AS `del_recipient`,a.`del_sender` AS `del_sender`,
	a.`flag` AS `flag`,m.`group_id` AS `group_id`,m.`display_name` AS `name`
FROM %t a
LEFT JOIN %t m
    ON a.`from`=m.`id`
WHERE a.`id`=?
ORDER BY a.`date` DESC
EOT
            ,
            array(
                'messages',
                'members',
            ),
            $DB->basicvalue($messageid)
        );
        $message = $DB->arow($result);
        $DB->disposeresult($result);
        if ($message['from'] != $USER['id'] && $message['to'] != $USER['id']) {
            $e = "You don't have permission to view this message.";
        }
        if ($e) {
            return $this->showwholething($e);
        }
        if (!$message['read'] && $message['to'] == $USER['id']) {
            $DB->safeupdate(
                'messages',
                array('read' => 1),
                'WHERE `id`=?',
                $message['id']
            );
            $this->updatenummessages();
        }
        $messageId = $message['id'];
        $messageTitle = $message['title'];
        $messageFromCode = $PAGE->meta(
            'user-link',
            $message['from'],
            $message['group_id'],
            $message['name']
        );
        $messageDateCode = $JAX->date($message['date']);
        $messageCode = $JAX->theworks($message['message']);
        $messageFrom = $message['from'];
        $previousPage = $JAX->b['page'];

        $page = <<<EOT
<div class='messageview'>
    <div class='messageinfo'>
        <div class='title'>${messageTitle}</div>
        <div>From: ${messageFromCode}</div>
        <div>Sent: ${messageDateCode}</div>
    </div>
    <div class="message">${messageCode}</div>
    <div class="messagebuttons">
        <form method="post" onsubmit="return RUN.submitForm(this,0,event)">
            <input type="hidden" name="act" value="inbox" />
            <input type="hidden" name="messageid" value="${messageId}" />
            <input type="hidden" name="sender" value="${messageFrom}" />
            <input type="hidden" name="prevpage" value="${previousPage}" />
            <input type="submit" name="page"
                onclick="this.form.submitButton=this;" value="Delete" />
            <input type="submit" name="page"
                onclick="this.form.submitButton=this;" value="Forward" />
            <input type="submit" name="page"
                onclick="this.form.submitButton=this;" value="Reply" />
        </form>
    </div>
</div>
EOT;
        $this->showwholething($page);
    }

    public function showwholething($page, $show = 0)
    {
        global $PAGE;
        if (!$PAGE->jsaccess || $PAGE->jsdirectlink || $show) {
            $page = <<<EOT
<div class="inbox">
    <div class="folders">
        <div class="folder compose">
            <a href="?act=inbox&page=compose">
                Compose
            </a>
        </div>
        <div class="folder inbox">
            <a href="?act=inbox">
                Inbox
            </a>
        </div>
        <div class="folder sent">
            <a href="?act=inbox&page=sent">
                Sent
            </a>
        </div>
        <div class="folder flagged">
            <a href="?act=inbox&page=flagged">
                Flagged
            </a>
        </div>
    </div>
    <div id="inboxpage">${page}</div>
    <div class="clear"></div>
</div>
EOT;
            $page = $PAGE->meta('box', '', 'Inbox', $page);
            $PAGE->JS('update', 'page', $page);
            $PAGE->append('PAGE', $page);
        } else {
            $PAGE->JS('update', 'inboxpage', $page);
        }
    }

    public function updatenummessages()
    {
        global $DB,$PAGE,$USER;
        $result = $DB->safeselect(
            'COUNT(`id`)',
            'messages',
            'WHERE `to`=? AND !`read`',
            $USER['id']
        );
        $unread = $DB->arow($result);
        $DB->disposeresult($result);
        $unread = array_pop($unread);
        $PAGE->JS('update', 'num-messages', $unread);
    }

    public function viewmessages($view = 'inbox')
    {
        global $PAGE,$DB,$JAX,$USER;
        if ($PAGE->jsupdate) {
            return;
        }
        if ('sent' == $view) {
            $result = $DB->safespecial(
                <<<'EOT'
SELECT a.`id` AS `id`,a.`to` AS `to`,a.`from` AS `from`,a.`title` AS `title`,
	a.`message` AS `message`,a.`read` AS `read`,a.`date` AS `date`,
	a.`del_recipient` AS `del_recipient,`a.`del_sender` AS `del_sender`,
	a.`flag` AS `flag`,m.`display_name` AS `display_name`
FROM %t a
LEFT JOIN %t m
    ON a.`to`=m.`id`
WHERE a.`from`=? AND !a.`del_sender`
ORDER BY a.`date` DESC
EOT
                ,
                array('messages', 'members'),
                $USER['id']
            );
        } elseif ('flagged' == $view) {
            $result = $DB->safespecial(
                <<<'EOT'
SELECT a.`id` AS `id`,a.`to` AS `to`,a.`from` AS `from`,a.`title` AS `title`,
	a.`message` AS `message`,a.`read` AS `read`,a.`date` AS `date`,
	a.`del_recipient` AS `del_recipient`,a.`del_sender` AS `del_sender`,
	a.`flag` AS `flag`,m.`display_name` AS `display_name`
FROM %t a
LEFT JOIN %t m
    ON a.`from`=m.`id`
WHERE a.`to`=? AND a.`flag`=1
ORDER BY a.`date` DESC
EOT
                ,
                array('messages', 'members'),
                $USER['id']
            );
        } else {
            $result = $DB->safespecial(
                <<<'EOT'
SELECT a.`id` AS `id`,a.`to` AS `to`,a.`from` AS `from`,a.`title` AS `title`,
	a.`message` AS `message`,a.`read` AS `read`,a.`date` AS `date`,
	a.`del_recipient` AS `del_recipient`,a.`del_sender` AS `del_sender`,
	a.`flag` AS `flag`,m.`display_name` AS `display_name`
FROM %t a
LEFT JOIN %t m
ON a.`from`=m.`id`
WHERE a.`to`=? AND !a.`del_recipient`
ORDER BY a.`date` DESC
EOT
                ,
                array('messages', 'members'),
                $USER['id']
            );
        }
        $unread = 0;
        while ($f = $DB->arow($result)) {
            $hasmessages = 1;
            if (!$f['read']) {
                ++$unread;
            }
            $page .= '<tr ' . (!$f['read'] ? 'class="unread" ' : '') .
                'onclick="if(JAX.event(event).srcElement.tagName.' .
                'toLowerCase()==\'td\') $$(\'input\',this)[0].click()">' .
                '<td class="center"><input class="check" type="checkbox" />' .
                '</td><td class="center"><input type="checkbox" ' .
                ($f['flag'] ? 'checked="checked" ' : '') .
                'class="switch flag" onclick="RUN.stream.location(\'' .
                '?act=inbox&flag=' . $f['id'] . '&tog=\'+(this.checked?1:0))"/>' .
                '</td><td><a href="?act=inbox&view=' . $f['id'] . '">' .
                $f['title'] . '</a></td><td>' . $f['display_name'] .
                '</td><td>' . $JAX->date($f['date']) . '</td></tr>';
        }

        if (!$hasmessages) {
            if ('sent' == $view) {
                $msg = 'No sent messages.';
            } elseif ('flagged' == $view) {
                $msg = 'No flagged messages.';
            } else {
                $msg = 'No messages. You could always try ' .
                '<a href="?act=inbox&page=compose">sending some</a>, though!';
            }
            $page .= '<tr><td colspan="5" class="error">' . $msg . '</td></tr>';
        } else {
            $page .= '<tr><td></td><td colspan="4">' .
                '<button>This button does nothing</button></td></tr>';
        }

        $page = $PAGE->meta(
            'inbox-messages-listing',
            'sent' == $view ? 'Recipient' : 'Sender',
            $page
        );

        if ('inbox' == $view) {
            $PAGE->JS('update', 'num-messages', $unread);
        }
        $this->showwholething($page, 1);
    }

    public function viewsent()
    {
    }

    public function compose($messageid = '', $todo = '')
    {
        global $PAGE,$JAX,$USER,$DB,$CFG;
        $showfull = 0;
        if ($JAX->p['submit']) {
            $mid = $JAX->b['mid'];
            if (!$mid && $JAX->b['to']) {
                $result = $DB->safeselect(
                    '`id`',
                    'members',
                    'WHERE `display_name`=?',
                    $DB->basicvalue($JAX->b['to'])
                );
                $mid = $DB->arow($result);
                $DB->disposeresult($result);

                if ($mid) {
                    $mid = array_pop($mid);
                }
            }
            if (!$mid) {
                $e = 'Invalid user!';
            } elseif (!trim($JAX->b['title'])) {
                $e = 'You must enter a title.';
            }
            if ($e) {
                $PAGE->JS('error', $e);
                $PAGE->append('PAGE', $PAGE->error($e));
            } else {
                $DB->safeinsert(
                    'messages',
                    array(
                        'to' => $mid,
                        'from' => $USER['id'],
                        'title' => $JAX->blockhtml($JAX->p['title']),
                        'message' => $JAX->p['message'],
                        'date' => time(),
                        'del_sender' => 0,
                        'del_recipient' => 0,
                        'read' => 0,
                    )
                );
                $cmd = $JAX->json_encode(
                    array(
                        'newmessage',
                        'You have a new message from ' . $USER['display_name'],
                        $DB->insert_id(1), )
                ) . PHP_EOL;
                $DB->safespecial(
                    <<<'EOT'
UPDATE %t
SET `runonce`=concat(`runonce`,?)
WHERE `uid`=?
EOT
                    ,
                    array('session'),
                    $DB->basicvalue($cmd, 1),
                    $mid
                );
                $this->showwholething(
                    <<<'EOT'
Message successfully delivered.<br />
<br />
<a href='?act=inbox'>Back</a>
EOT
                );

                return;
            }
        }
        if ($PAGE->jsupdate && !$messageid) {
            return;
        }
        $msg = '';
        if ($messageid) {
            $result = $DB->safeselect(
                <<<'EOT'
`id`,`to`,`from`,`title`,`message`,`read`,`date`,`del_recipient`,`del_sender`,
`flag`
EOT
                ,
                'messages',
                'WHERE (`to`=? OR `from`=?) AND `id`=?',
                $USER['id'],
                $USER['id'],
                $DB->basicvalue($messageid)
            );
            $message = $DB->arow($result);
            $DB->disposeresult($result);

            $mid = $message['from'];
            $result = $DB->safeselect(
                '`display_name`',
                'members',
                'WHERE `id`=?',
                $mid
            );
            $thisrow = $DB->arow($result);
            $mname = array_pop($thisrow);
            $DB->disposeresult($result);

            $msg = PHP_EOL . PHP_EOL . PHP_EOL . '[quote=' . $mname . ']' .
                $message['message'] . '[/quote]';
            $mtitle = ('fwd' == $todo ? 'FWD:' : 'RE:') . $message['title'];
            if ('fwd' == $todo) {
                $mid = $mname = '';
            }
        }
        if (is_numeric($JAX->g['mid'])) {
            $showfull = 1;
            $mid = $JAX->b['mid'];
            $result = $DB->safeselect(
                '`display_name`',
                'members',
                'WHERE `id`=?',
                $mid
            );
            $thisrow = $DB->arow($result);
            $mname = array_pop($thisrow);
            $DB->disposeresult($result);

            if (!$mname) {
                $mid = 0;
                $mname = '';
            }
        }
        $toKeyUp = '$(\'validname\').className=\'bad\';' .
            'JAX.autoComplete(\'act=searchmembers&term=\'+' .
            'this.value,this,$(\'mid\'),event);';
        $goodClass = ($mname ? ' class="good"' : '');
        $msgClean = htmlspecialchars($msg);
        $hiddenFields = $JAX->hiddenFormFields(
            array(
                'act' => 'inbox',
                'page' => 'compose',
                'submit' => 1,
            )
        );
        $page = <<<EOT
<div class="composeform">
    <form method="post"
        onsubmit="$('pdedit').editor.submit();return RUN.submitForm(this)">
        ${hiddenFields}
        <div>
            <label for="to">
                To:
            </label>
            <input type="hidden" id="mid" name="mid"
                onchange="$('validname').className='good'"
                value="${mid}" />
            <input type="text" id="to" name="to" value="${mname}"
                onkeydown="if(event.keyCode==13) return false;"
                onkeyup="${toKeyUp}" />
            <span id="validname"${goodClass}></span>
        </div>
        <div>
            <label for="title">
                Title:
            </label>
            <input type="text" id="title" name="title" value="${mtitle}"/>
        </div>
        <div>
            <iframe onload="JAX.editor($('message'),this)"
                style="display:none" id="pdedit"></iframe>
            <textarea id="message" name="message">${msgClean}</textarea>
        </div>
        <input type="submit" value="Send" />
    </form>
</div>
EOT;
        $this->showwholething($page, $showfull);
    }

    public function delete($id)
    {
        global $PAGE,$JAX,$DB,$USER;
        $result = $DB->safeselect(
            <<<'EOT'
`id`,`to`,`from`,`title`,`message`,`read`,`date`,`del_recipient`,`del_sender`,
`flag`
EOT
            ,
            'messages',
            'WHERE `id`=?',
            $DB->basicvalue($id)
        );
        $message = $DB->arow($result);
        $DB->disposeresult($result);

        $is_recipient = $message['to'] == $USER['id'];
        $is_sender = $message['from'] == $USER['id'];
        if ($is_recipient) {
            $DB->safeupdate(
                'messages',
                array(
                    'del_recipient' => 1,
                ),
                'WHERE `id`=?',
                $DB->basicvalue($id)
            );
        }
        if ($is_sender) {
            $DB->safeupdate(
                'messages',
                array(
                    'del_sender' => 1,
                ),
                'WHERE `id`=?',
                $DB->basicvalue($id)
            );
        }
        $PAGE->location(
            '?act=inbox' .
            ($JAX->b['prevpage'] ? '&page=' . $JAX->b['prevpage'] : '')
        );
    }
}
