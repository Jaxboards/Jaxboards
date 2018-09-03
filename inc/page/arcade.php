<?php

$PAGE->metadefs['arcade-index-wrapper'] = <<<'EOT'
<table class="boardindex">
    %s
</table>
EOT;
$PAGE->metadefs['arcade-index-row'] = <<<'EOT'
<tr>
    <td class="f_icon" rowspan="2">
        <img src="%s" alt="Icon" />
    </td>
    <td class="forum" rowspan="2">
        <a href="?act=arcade&amp;play=%s">
            %s
        </a>
        <div class="description">
            %s
        </div>
    </td>
    <td class="last_post" colspan="2">
        Top Score: %s -
        <strong>
            %s
        </strong>
        <br />
        <a href="?act=arcade&amp;scores=%2$s">
            View all scores
        </a>
    </td>
</tr>
<tr>
    <td class="item_1">
        Plays: %s
    </td>
    <td class="item_2">
        Your score: %s
    </td>
</tr>
EOT;
new arcade();
class arcade
{
    public function __construct()
    {
        global $JAX,$SESS;
        if ('newscore' == $JAX->b['do']) {
            $this->submitScore();
        } elseif (is_numeric($JAX->b['scores'])) {
            $this->showScores($JAX->b['scores']);
        } elseif (is_numeric($JAX->b['play'])) {
            $this->play($JAX->b['play']);
        } else {
            $this->index();
        }
    }

    public function index()
    {
        global $DB,$PAGE,$USER,$SESS,$JAX;
        if ($PAGE->jsupdate) {
            return;
        }
        $SESS->location_verbose = "Chillin' in the Arcade";
        $page = '';
        $yourscore = array();
        if ($USER) {
            $result = $DB->safeselect(
                '`game_id`,MAX(`score`) AS `maxscore`',
                '`arcade_scores`',
                'WHERE `uid`=? GROUP BY `game_id`',
                $USER['id']
            );
            while ($f = $DB->arow($result)) {
                $yourscore[$f['game_id']] = $f['maxscore'];
            }
        }
        $result = $DB->safespecial(
            <<<'EOT'
SELECT g.`id` AS `id`, g.`icon` AS `icon`, g.`title` AS `title`,
    g.`description` AS `description`,
    g.`swf` AS `swf`,g.`width` AS `width`,g.`height` AS `height`,
    g.`leader` AS `leader,g.`score` AS `score`,
    g.`times_played` AS `times_played`
    m.`group_id` AS `group_id`,m.`display_name` AS `display_name`
FROM %t g
LEFT JOIN %t m
    ON g.`leader`=m.`id`
    ORDER BY g.`title`
EOT
            ,
            array('arcade_games', 'members')
        );
        while ($f = $DB->arow($result)) {
            $page .= $PAGE->meta(
                'arcade-index-row',
                $f['icon'],
                $f['id'],
                $f['title'],
                $f['description'],
                $PAGE->meta(
                    'user-link',
                    $f['leader'],
                    $f['group_id'],
                    $f['display_name']
                ),
                $JAX->pick($f['score'], 'N/A'),
                $f['times_played'],
                $JAX->pick($yourscore[$f['id']], 'N/A')
            );
        }
        $page = $PAGE->meta('arcade-index-wrapper', $page);
        $page = $PAGE->meta('box', '', 'Arcade', $page);
        $PAGE->append('PAGE', $page);
        $PAGE->JS('update', 'page', $page);
    }

    public function play($id)
    {
        global $DB,$PAGE,$JAX,$SESS;
        if ($PAGE->jsupdate) {
            $scores = $this->getScores($id);
            $update = false;
            foreach ($scores as $f) {
                if ($SESS->last_update <= $f['date']) {
                    $update = true;
                    break;
                }
            }
            if ($update) {
                $PAGE->JS(
                    'update',
                    'scores',
                    $this->buildMiniScoresTable($scores),
                    1
                );
            }

            return;
        }
        $result = $DB->safeselect(
            <<<'EOT'
`id`,`icon`,`gname`,`title`,`description`,`swf`,`width`,`height`,`leader`,
`score`,`times_played`
EOT
            ,
            'arcade_games',
            'WHERE `id`=?',
            $id
        );
        $gamedata = $DB->arow($result);
        $DB->disposeresult($result);
        if (!$gamedata) {
            $PAGE->location('?act=arcade');
        }
        $SESS->location_verbose = 'Playing ' . $gamedata['title'] . ' in the arcade';

        $DB->safespecial(
            <<<'EOT'
UPDATE %t
SET `times_played` = `times_played` + 1
WHERE `id`=?
EOT
            ,
            array('arcade_games'),
            $gamedata['id']
        );

        if ($JAX->b['frame']) {
            $title = $gamedata['title'];
            $frameCode = $PAGE->SWF(
                $gamedata['swf'],
                array(
                    'width' => $gamedata['width'] . 'px',
                    'height' => $gamedata['height'] . 'px',
                    'flashvars' => array(
                        'jax_gameid' => $id,
                    ),
                )
            );
            die(
                <<<EOT
<!DOCTYPE html>
<html>
    <head>
    <title>${title}</title>
    </head>
    <body style="margin:0;padding:0">
        ${frameCode}
    </body>
</html>
EOT
            );
        }

        $page .= '<div class="gameinfo">';
        $page .= '<h3>' . $gamedata['title'] .
            '</h3><img src="' . $gamedata['icon'] . '" /><br />' .
            $gamedata['description'];
        $page .= '<div id="scores">' .
            $this->buildMiniScoresTable($this->getScores($id)) . '</div>';
        $page .= '<div style="text-align:center">' .
            '<a href="?act=arcade">Go Back to the Arcade</a><br />' .
            '<a href="?act=arcade&amp;scores=' . $id . '">View All Scores</a>' .
            '</div>';
        $page .= '</div>';
        $page .= '<div style="margin-right:220px;text-align:center;">' .
            '<iframe src="?act=arcade&play=' . $id . '&frame=1" width="' .
            ($gamedata['width']) . '" height="' . ($gamedata['height']) .
            'px" frameborder="0" style="margin:10px;"></iframe></div>';
        $page .= '<div style="clear:both"></div>';

        $page = $PAGE->meta('box', '', $gamedata['title'], $page);
        $PAGE->append('PAGE', $page);
        $PAGE->JS('update', 'page', $page);
    }

    public function showScores($gameid)
    {
        global $PAGE,$JAX,$USER,$DB;
        $gamedata = $this->getGameData($gameid);
        if (!$gamedata) {
            return $PAGE->location('?act=arcade');
        }

        $scores = false;
        if ($JAX->b['del'] && 2 == $USER['group_id']) {
            $DB->safedelete(
                'arcade_scores',
                'WHERE `id`=?',
                $DB->basicvalue($JAX->b['del'])
            );
            $scores = $this->getScores($gameid);
            $DB->safeupdate(
                'arcade_games',
                array(
                    'leader' => $scores[0]['uid'],
                    'score' => $scores[0]['score'],
                ),
                'WHERE `id`=?',
                $gameid
            );
        }

        if (!$scores) {
            $scores = $this->getScores($gameid);
        }
        if ($JAX->p['comment']) {
            foreach ($scores as $f) {
                if ($f['uid'] == $USER['id']) {
                    $DB->safeupdate(
                        'arcade_scores',
                        array(
                            'comment' => $JAX->p['comment'],
                        ),
                        'WHERE `id`=?',
                        $f['id']
                    );
                    break;
                }
            }
        }
        if ($PAGE->jsupdate) {
            return;
        }
        $commentform = $JAX->b['comment'] ?
            '<form method="post" onsubmit="RUN.submitForm(this);' .
            'this.parentNode.innerHTML=this.comment.value;return false;">' .
            $JAX->hiddenFormFields(
                array(
                    'act' => 'arcade',
                    'scores' => $gameid,
                )
            ) . '<input type="text" name="comment" />' .
            '<input type="submit" value="Add" /></form>' : '';
        $tenseconds = time() - 10;
        $page = '<table style="width:100%" id="memberlist">';
        $page .= '<tr><th width="1"></th><th></th><th>Name</th><th>Score</th>' .
            '<th>Comment</th><th>Date</th>' .
            (2 == $USER['group_id'] ? '<th>X</th>' : '') . '</tr>';
        foreach ($scores as $k => $f) {
            $page .= '<tr><td>' . ($k + 1) . '</td><td class="avatar">' .
                '<img src="' . $JAX->pick(
                    $f['avatar'],
                    $PAGE->meta('default-avatar')
                ) . '" /></td><td>' . $PAGE->meta(
                    'user-link',
                    $f['uid'],
                    $f['group_id'],
                    $f['display_name']
                ) . '</td><td>' . $f['score'] . '</td><td>';
            if ($commentform
                && $f['uid'] == $USER['id']
                && $f['date'] > $tenseconds
            ) {
                $page .= $commentform;
                $commentform = '';
            } else {
                $page .= $JAX->theworks($f['comment'], array('minimalbb' => 1));
            }
            $page .= '</td><td>' . $JAX->date($f['date']) . '</td>' .
                (2 == $USER['group_id'] ?
                '<td><a href="?act=arcade&amp;scores=' . $gameid .
                '&del=' . $f['id'] . '">[X]</a></td>' : '') . '</tr>';
        }
        $page .= '</table>';
        $page = $PAGE->meta('box', '', 'Scores - ' . $gamedata['title'], $page);

        $PAGE->append('PAGE', $page);
        $PAGE->JS('update', 'page', $page);
    }

    public function getScores($gameid, $limit = 10)
    {
        global $DB;
        $r = array();
        $result = $DB->safespecial(
            <<<'EOT'
SELECT s.`id` AS `id`, s.`game_id` AS `game_id`, s.`score` AS `score`,
    s.`comment` AS `comment`,
    m.`display_name` AS `display_name`,m.`avatar` AS `avatar`,
    m.`id` AS `uid`,m.`group_id` AS `group_id`
FROM %t s
LEFT JOIN %t m
    ON s.`uid`=m.`id`
WHERE s.`game_id`=?
ORDER BY s.`score` DESC
LIMIT ?
EOT
            ,
            array('arcade_scores', 'members'),
            $gameid,
            $limit
        );
        while ($f = $DB->arow($result)) {
            $r[] = $f;
        }

        return $r;
    }

    public function getGameData($gameid)
    {
        global $DB;
        $result = $DB->safeselect(
            <<<'EOT'
`id`,`icon`,`gname`,`title`,`description`,`swf`,`width`,`height`,`leader`,
`score`,`times_played`
EOT
            ,
            'arcade_games',
            'WHERE `id`=?',
            $DB->basicvalue($gameid)
        );
        $retval = $DB->arow($result);
        $DB->disposeresult($result);

        return $retval;
    }

    public function buildMiniScoresTable($scores)
    {
        global $PAGE;
        $page = '<table style="width:100%">';
        $page .= '<tr><th colspan="2" style="text-align:center">' .
            'Top Scores</th></tr>';
        foreach ($scores as $f) {
            $page .= '<tr><td style="text-align:right;width:50%;">' .
                $PAGE->meta(
                    'user-link',
                    $f['uid'],
                    $f['group_id'],
                    $f['display_name']
                ) . '</td><td style="text-align:left">' . $f['score'] . '</td></tr>';
        }
        if (!$scores[0]) {
            $page .= '<tr><td colspan="2">N/A</td></tr>';
        }

        return $page . '</table>';
    }

    public function submitScore()
    {
        global $DB,$PAGE,$JAX,$USER;
        $score = $JAX->b['gscore'];
        $gameid = $JAX->b['jax_gameid'];
        if (!$gameid && $JAX->b['gname']) {
            $result = $DB->safeselect(
                '`id`',
                'arcade_games',
                'WHERE `gname`=?',
                $DB->basicvalue($JAX->b['gname'])
            );
            $gameid = $DB->arow($result);
            $DB->disposeresult($result);

            if ($gameid) {
                $gameid = array_shift($gameid);
            } else {
                $gameid = 'nope';
            }
        }
        if ($USER && is_numeric($gameid)) {
            $result = $DB->safeselect(
                <<<'EOT'
`id`,`icon`,`gname`,`title`,`description`,`swf`,`width`,`height`,`leader`,
`score`,`times_played`
EOT
                ,
                'arcade_games',
                'WHERE `id`=?',
                $DB->basicvalue($gameid)
            );
            $gamedata = $DB->arow($result);
            $DB->disposeresult($result);

            if ($gamedata) {
                $query = array(
                    'score' => $score,
                    'date' => time(),
                );
                // Get highest score.
                $result = $DB->safeselect(
                    '`score`',
                    'arcade_scores',
                    'WHERE `game_id`=? AND `uid`=?',
                    $DB->basicvalue($gameid),
                    $USER['id']
                );
                $yourscore = $DB->arow($result);
                $DB->disposeresult($result);

                if ($yourscore) {
                    // Don't do anything if they've scored
                    // less than what they had before.
                    if ($yourscore['score'] < $score) {
                        $DB->safeupdate(
                            'arcade_scores',
                            $query,
                            'WHERE `game_id`=? AND `uid`=?',
                            $DB->basicvalue($gameid),
                            $USER['id']
                        );
                    }
                } else {
                    $query['game_id'] = $gameid;
                    $query['uid'] = $USER['id'];
                    $DB->safeinsert(
                        'arcade_scores',
                        $query
                    );
                }

                if ($JAX->b['gscore'] > $gamedata['score']) {
                    $DB->safeupdate(
                        'arcade_games',
                        array(
                            'score' => $score,
                            'leader' => $USER['id'],
                        ),
                        'WHERE `id`=?',
                        $gameid
                    );
                }
            }
        }
        echo '<script type="text/javascript">parent.RUN.stream.location' .
            '("?act=arcade&scores=' . ($gameid) . '&comment=1")</script>';
        die();
    }
}
