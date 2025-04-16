<?php

declare(strict_types=1);

if (!defined(INACP)) {
    exit;
}

final class RecountStats
{
    public static function showstats(): void
    {
        global $PAGE;
        $PAGE->addContentBox(
            'Board Statistics',
            $PAGE->parseTemplate(
                'stats/show-stats.html',
            ),
        );
    }

    public static function recountStatistics(): void
    {
        global $DB,$PAGE;
        $result = $DB->safeselect(
            ['id', 'nocount'],
            'forums',
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
            ['posts', 'topics'],
        );
        $stat = [
            'forum_posts' => [],
            'forum_topics' => [],
            'member_posts' => [],
            'posts' => 0,
            'topics' => 0,
            'topic_posts' => [],
        ];
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
            } elseif ($stat['member_posts'][$f['auth_id']] === 0) {
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
            ['id', 'path', 'cat_id'],
            'forums',
        );
        while ($f = $DB->arow($result)) {
            if (!$f['path']) {
                continue;
            }

            foreach (explode(' ', (string) $f['path']) as $fid) {
                $stat['forum_topics'][$fid] += $stat['forum_topics'][$f['id']] ?? 0;
                $stat['forum_posts'][$fid] += $stat['forum_posts'][$f['id']] ?? 0;
            }
        }

        // Update Topic Replies.
        foreach ($stat['topic_posts'] as $k => $v) {
            $DB->safeupdate(
                'topics',
                [
                    'replies' => $v,
                ],
                'WHERE `id`=?',
                $k,
            );
        }

        // Update member posts.
        foreach ($stat['member_posts'] as $k => $v) {
            $DB->safeupdate(
                'members',
                [
                    'posts' => $v,
                ],
                'WHERE `id`=?',
                $k,
            );
        }

        // Update forum posts.
        foreach ($stat['forum_posts'] as $k => $v) {
            $DB->safeupdate(
                'forums',
                [
                    'posts' => $v,
                    'topics' => $stat['forum_topics'][$k],
                ],
                'WHERE `id`=?',
                $k,
            );
        }

        // Get # of members.
        $result = $DB->safeselect(
            'COUNT(`id`)',
            'members',
        );
        $thisrow = $DB->arow($result);
        $stat['members'] = array_pop($thisrow);
        $DB->disposeresult($result);

        // Update global board stats.
        $DB->safeupdate(
            'stats',
            [
                'members' => $stat['members'],
                'posts' => $stat['posts'],
                'topics' => $stat['topics'],
            ],
        );

        $PAGE->addContentBox(
            'Board Statistics',
            $PAGE->success('Board statistics recounted successfully.'),
        );
    }
}
