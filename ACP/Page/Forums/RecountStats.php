<?php

declare(strict_types=1);

namespace ACP\Page\Forums;

use ACP\Page;
use Jax\Database;
use Jax\Request;

use function array_pop;
use function explode;

final readonly class RecountStats
{
    public function __construct(
        private readonly Page $page,
        private readonly Database $database,
        private readonly Request $request,
    ) {}

    public function render(): void
    {
        match (true) {
            $this->request->both('execute') !== null => $this->recountStatistics(),
            default => $this->showStats(),
        };
    }

    public function showStats(): void
    {
        $this->page->addContentBox(
            'Board Statistics',
            $this->page->parseTemplate(
                'stats/show-stats.html',
            ),
        );
    }

    public function recountStatistics(): void
    {
        $result = $this->database->select(
            ['id', 'nocount'],
            'forums',
        );
        $countPostsInForum = [];
        foreach ($this->database->arows($result) as $forum) {
            $countPostsInForum[$forum['id']] = !$forum['nocount'];
        }

        $result = $this->database->special(
            <<<'SQL'
                SELECT
                    p.`id` AS `id`,
                    p.`auth_id` AS `auth_id`,
                    p.`tid` AS `tid`,
                    t.`fid` AS `fid`
                FROM %t p
                LEFT JOIN %t t ON p.`tid`=t.`id`
                SQL,
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
        foreach ($this->database->arows($result) as $post) {
            if (!isset($stat['topic_posts'][$post['tid']])) {
                if (!isset($stat['forum_topics'][$post['fid']])) {
                    $stat['forum_topics'][$post['fid']] = 0;
                }

                ++$stat['forum_topics'][$post['fid']];
                if (!isset($stat['forum_posts'][$post['fid']])) {
                    $stat['forum_posts'][$post['fid']] = 0;
                }

                ++$stat['topics'];
                $stat['topic_posts'][$post['tid']] = 0;
            } else {
                ++$stat['topic_posts'][$post['tid']];
                if (!isset($stat['forum_posts'][$post['fid']])) {
                    $stat['forum_posts'][$post['fid']] = 0;
                }

                ++$stat['forum_posts'][$post['fid']];
            }

            if ($countPostsInForum[$post['fid']]) {
                if (!isset($stat['member_posts'][$post['auth_id']])) {
                    $stat['member_posts'][$post['auth_id']] = 0;
                }

                ++$stat['member_posts'][$post['auth_id']];
            }

            ++$stat['posts'];
        }

        // Go through and sum up category posts as well
        // as forums with subforums.
        $result = $this->database->select(
            ['id', 'path', 'cat_id'],
            'forums',
        );
        foreach ($this->database->arows($result) as $forum) {
            if (!$forum['path']) {
                continue;
            }

            foreach (explode(' ', (string) $forum['path']) as $fid) {
                $stat['forum_topics'][$fid] += $stat['forum_topics'][$forum['id']] ?? 0;
                $stat['forum_posts'][$fid] += $stat['forum_posts'][$forum['id']] ?? 0;
            }
        }

        // Update Topic Replies.
        foreach ($stat['topic_posts'] as $k => $v) {
            $this->database->update(
                'topics',
                [
                    'replies' => $v,
                ],
                Database::WHERE_ID_EQUALS,
                $k,
            );
        }

        // Update member posts.
        foreach ($stat['member_posts'] as $k => $v) {
            $this->database->update(
                'members',
                [
                    'posts' => $v,
                ],
                Database::WHERE_ID_EQUALS,
                $k,
            );
        }

        // Update forum posts.
        foreach ($stat['forum_posts'] as $k => $v) {
            $this->database->update(
                'forums',
                [
                    'posts' => $v,
                    'topics' => $stat['forum_topics'][$k],
                ],
                Database::WHERE_ID_EQUALS,
                $k,
            );
        }

        // Get # of members.
        $result = $this->database->select(
            'COUNT(`id`)',
            'members',
        );
        $members = $this->database->arow($result);
        $stat['members'] = $members ? array_pop($members) : 0;
        $this->database->disposeresult($result);

        // Update global board stats.
        $this->database->update(
            'stats',
            [
                'members' => $stat['members'],
                'posts' => $stat['posts'],
                'topics' => $stat['topics'],
            ],
        );

        $this->page->addContentBox(
            'Board Statistics',
            $this->page->success('Board statistics recounted successfully.'),
        );
    }
}
