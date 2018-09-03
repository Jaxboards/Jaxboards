<?php

if (!defined(INACP)) {
    die();
}

new stats();
class stats
{
    public function __construct()
    {
        global $PAGE,$JAX;
        switch (@$JAX->g['do']) {
            case 'recount':
                $this->recount_statistics();
                break;
            default:
                $this->showstats();
                break;
        }
    }

    public function showstats()
    {
        global $PAGE;
        $PAGE->addContentBox(
            'Board Statistics',
            "<a href='?act=stats&do=recount'>Recount Statistics</a>"
        );
    }

    public function recount_statistics()
    {
        global $DB,$PAGE;
        $result = $DB->safeselect(
            '`id`,`nocount`',
            'forums'
        );
        while ($f = $DB->arow($result)) {
            $pc[$f['id']] = $f['nocount'];
        }
        $result = $DB->safespecial(
            <<<'EOT'
SELECT p.`id` AS `id`,
    p.`auth_id` AS `auth_id`,p.`tid` AS `tid`,t.`fid` AS `fid`
FROM %t p
LEFT JOIN %t t
    ON p.`tid`=t.`id`
EOT
            ,
            array('posts', 'topics')
        );
        $stat = array(
            'forum_topics' => array(),
            'topic_posts' => array(),
            'member_posts' => array(),
            'cat_topics' => array(),
            'cat_posts' => array(),
            'forum_posts' => array(),
            'posts' => 0,
            'topics' => 0,
        );
        while ($f = $DB->arow($result)) {
            if (!isset($stat['topic_posts'][$f['tid']])) {
                if (!isset($stat['forum_topics'][$f['fid']])) {
                    $stat['forum_topics'][$f['fid']] = 0;
                }
                ++$stat['forum_topics'][$f['fid']];
                if (!isset($stat['forum_posts'][$f['fid']])) {
                    $stat['forum_posts'][$f['fid']] = 0;
                }
                if (!isset($stat['topics'])) {
                    $stat['topics'] = 0;
                }
                ++$stat['topics'];
                $stat['topic_posts'][$f['tid']] = 0;
            } else {
                if (!isset($stat['topic_posts'][$f['tid']])) {
                    $stat['topic_posts'][$f['tid']] = 0;
                }
                ++$stat['topic_posts'][$f['tid']];
                if (!isset($stat['forum_posts'][$f['fid']])) {
                    $stat['forum_posts'][$f['fid']] = 0;
                }
                ++$stat['forum_posts'][$f['fid']];
            }
            if (!$pc[$f['fid']]) {
                if (!isset($stat['member_posts'][$f['auth_id']])) {
                    $stat['member_posts'][$f['auth_id']] = 0;
                }
                ++$stat['member_posts'][$f['auth_id']];
            } elseif (!$stat['member_posts'][$f['auth_id']]) {
                $stat['member_posts'][$f['auth_id']] = 0;
            }
            if (!isset($stat['posts'])) {
                $stat['posts'] = 0;
            }
            ++$stat['posts'];
        }

        // Go through and sum up category posts as well
        // as forums with subforums.
        $result = $DB->safeselect(
            '`id`,`path`,`cat_id`',
            'forums'
        );
        while ($f = $DB->arow($result)) {
            // I realize I don't use cat stats yet, but I may.
            if (!isset($stat['cat_posts'][$f['cat_id']])) {
                $stat['cat_posts'][$f['cat_id']] = 0;
            }
            if (!isset($stat['cat_topics'][$f['cat_id']])) {
                $stat['cat_topics'][$f['cat_id']] = 0;
            }
            $stat['cat_posts'][$f['cat_id']] += $stat['forum_posts'][$f['id']];
            $stat['cat_topics'][$f['cat_id']] += $stat['forum_topics'][$f['id']];

            if ($f['path']) {
                foreach (explode(' ', $f['path']) as $v) {
                    $stat['forum_topics'][$v] += $stat['forum_topics'][$f['id']];
                    $stat['forum_posts'][$v] += $stat['forum_posts'][$f['id']];
                }
            }
        }

        // YEAH, this is bad. A bajillion update statements
        // however, I have been unable to find a better way to do this.
        // I have to do a seperate update query for every user,
        // topic, category, and forum. pretty sick.
        // Update Topic Replies.
        foreach ($stat['topic_posts'] as $k => $v) {
            $DB->safeupdate(
                'topics',
                array(
                    'replies' => $v,
                ),
                'WHERE `id`=?',
                $k
            );
        }

        // Update member posts.
        foreach ($stat['member_posts'] as $k => $v) {
            $DB->safeupdate(
                'members',
                array(
                    'posts' => $v,
                ),
                'WHERE `id`=?',
                $k
            );
        }

        // Update forum posts.
        foreach ($stat['forum_posts'] as $k => $v) {
            $DB->safeupdate(
                'forums',
                array(
                    'posts' => $v,
                    'topics' => $stat['forum_topics'][$k],
                ),
                'WHERE `id`=?',
                $k
            );
        }

        // Get # of members.
        $result = $DB->safeselect(
            'COUNT(`id`)',
            'members'
        );
        $thisrow = $DB->arow($result);
        $stat['members'] = array_pop($thisrow);
        $DB->disposeresult($result);

        // Update global board stats.
        $DB->safeupdate(
            'stats',
            array(
                'posts' => $stat['posts'],
                'topics' => $stat['topics'],
                'members' => $stat['members'],
            )
        );

        $PAGE->addContentBox(
            'Board Statistics',
            'Board statistics recounted successfully.<br />' .
            "<br /><br /><br /><a href='?act=stats'>Board Statistics</a>"
        );
    }
}
