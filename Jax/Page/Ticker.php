<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Date;
use Jax\Models\Forum;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Models\Topic;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

final class Ticker
{
    private int $maxticks = 60;

    public function __construct(
        private readonly Date $date,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly Template $template,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {
        $this->template->loadMeta('ticker');
    }

    public function render(): void
    {
        if (
            $this->request->isJSNewLocation()
            || !$this->request->isJSAccess()
        ) {
            $this->index();

            return;
        }

        $this->update();
    }

    private function index(): void
    {
        $this->session->set('locationVerbose', 'Using the ticker!');


        $ticksHTML = '';
        $first = 0;

        foreach ($this->fetchTicks() as $tick) {
            if (!$first) {
                $first = $tick[0]->id;
            }

            $ticksHTML .= $this->renderTick($tick);
        }

        $this->session->addVar('tickid', $first);
        $page = $this->template->meta('ticker', $ticksHTML);
        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }

    /**
     * @return list<array{Post,Member,Topic}>
     */
    private function fetchTicks(int $lastTickId = 0): array
    {
        $posts = Post::selectMany(
            'WHERE `id` > ?
            ORDER BY `id` DESC
            LIMIT ?',
            $lastTickId,
            $this->maxticks,
        );

        $topics = Topic::joinedOn(
            $posts,
            static fn(Post $post): int => $post->tid,
        );

        $forums = Forum::joinedOn(
            $topics,
            static fn(Topic $topic): ?int => $topic->fid,
        );

        $members = Member::joinedOn(
            $posts,
            static fn(Post $post): int => $post->author,
        );

        $ticks = [];

        foreach ($posts as $post) {
            $topic = $topics[$post->tid];
            $forum = $forums[$topic->fid];

            if (!$this->user->getForumPerms($forum->perms)['read']) {
                continue;
            }

            $ticks[] = [
                $post,
                $members[$post->author],
                $topic,
            ];
        }

        return $ticks;
    }

    private function update(): void
    {
        $ticks = $this->fetchTicks($this->session->getVar('tickid'));

        if ($ticks === []) {
            return;
        }

        foreach ($ticks as $tick) {
            $this->page->command('tick', $this->renderTick($tick));
        }

        $this->session->addVar('tickid', $ticks[0][0]->id);
    }

    /**
     * @param array{Post,Member,Topic} $tick
     */
    private function renderTick(array $tick): string
    {
        [$post, $postAuthor, $topic] = $tick;

        return $this->template->meta(
            'ticker-tick',
            $post->date ? $this->date->smallDate($post->date, ['autodate' => true]) : '',
            $this->template->meta(
                'user-link',
                $postAuthor->id,
                $postAuthor->groupID,
                $postAuthor->displayName,
            ),
            $topic->id,
            $post->id,
            $this->textFormatting->wordfilter($topic->title),
        );
    }
}
