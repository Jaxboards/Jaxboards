<?php

declare(strict_types=1);

namespace Jax\Page\Topic;

use Jax\Config;
use Jax\Database;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Models\RatingNiblet;
use Jax\Page;
use Jax\Template;
use Jax\User;

use function _\keyBy;
use function array_diff;
use function array_key_exists;
use function array_merge;
use function count;
use function in_array;
use function json_decode;
use function json_encode;

final readonly class Reactions
{
    public function __construct(
        private Config $config,
        private Page $page,
        private Database $database,
        private User $user,
        private Template $template,
    ) {}

    /**
     * Fetches Reactions from "ratingniblets" table.
     *
     * @return array<RatingNiblet> Rating Records by ID
     */
    public function fetchRatingNiblets(): array
    {
        static $ratingNiblets = null;

        if ($ratingNiblets) {
            return $ratingNiblets;
        }

        return $ratingNiblets = keyBy(
            RatingNiblet::selectMany($this->database),
            static fn($niblet) => $niblet->id,
        );
    }

    public function listReactions(int $pid): void
    {
        if ($this->isAnonymousReactionsEnabled()) {
            return;
        }

        $this->page->command('softurl');
        $post = Post::selectOne($this->database, Database::WHERE_ID_EQUALS, $pid);

        $ratings = $post !== null ? json_decode($post->rating, true) : [];

        if ($ratings === []) {
            return;
        }

        $members = array_merge(...$ratings);

        if ($members === []) {
            $this->page->command('alert', 'This post has no ratings yet!');

            return;
        }

        $mdata = keyBy(
            Member::selectMany($this->database, Database::WHERE_ID_IN, $members),
            static fn($member) => $member->id,
        );

        unset($members);
        $niblets = $this->fetchRatingNiblets();
        $page = '';
        foreach ($ratings as $index => $rating) {
            $page .= '<div class="column">';
            $page .= '<img src="' . $niblets[$index]->img . '" /> '
                . $niblets[$index]->title . '<ul>';
            foreach ($rating as $mid) {
                $page .= '<li>' . $this->template->meta(
                    'user-link',
                    $mid,
                    $mdata[$mid]->group_id,
                    $mdata[$mid]->display_name,
                ) . '</li>';
            }

            $page .= '</ul></div>';
        }

        $this->page->command('listrating', $pid, $page);
    }

    public function render(Post $post): string
    {
        // Reactions are turned off
        if (!$this->isReactionsEnabled()) {
            return '';
        }

        $prating = $post->rating !== '' && $post->rating !== '0'
            ? json_decode($post->rating, true)
            : [];
        $postratingbuttons = '';
        $showrating = '';

        foreach ($this->fetchRatingNiblets() as $ratingNiblet) {
            $nibletHTML = $this->template->meta(
                'rating-niblet',
                $ratingNiblet->img,
                $ratingNiblet->title,
            );
            $postratingbuttons .= <<<HTML
                <a href="?act=vt{$post->tid}&amp;ratepost={$post->id}&amp;niblet={$ratingNiblet->id}">
                    {$nibletHTML}
                </a>
                HTML;
            if (!isset($prating[$ratingNiblet->id])) {
                continue;
            }

            if (!$prating[$ratingNiblet->id]) {
                continue;
            }

            $num = 'x' . count($prating[$ratingNiblet->id]);
            $postratingbuttons .= $num;
            $showrating .= $this->template->meta(
                'rating-niblet',
                $ratingNiblet->img,
                $ratingNiblet->title,
            ) . $num;
        }

        return $this->template->meta(
            'rating-wrapper',
            $postratingbuttons,
            $this->isAnonymousReactionsEnabled()
                ? ''
                : "<a href='?act=vt{$post->tid}&amp;listrating={$post->id}'>(List)</a>",
            $showrating,
        );
    }

    public function toggleReaction(int $postid, int $nibletid): void
    {
        $this->page->command('softurl');

        $post = Post::selectOne($this->database, Database::WHERE_ID_EQUALS, $postid);

        $niblets = $this->fetchRatingNiblets();
        $ratings = [];

        if (
            !$post
            || !array_key_exists($nibletid, $niblets)
            || $this->user->isGuest()
        ) {
            $this->page->command('error', 'Invalid parameters');

            return;
        }

        $ratings = json_decode($post->rating, true);
        if (!$ratings) {
            $ratings = [];
        }

        if (!array_key_exists($nibletid, $ratings)) {
            $ratings[$nibletid] = [];
        }

        $unrate = in_array($this->user->get()->id, $ratings[$nibletid], true);
        // Unrate
        if ($unrate) {
            $ratings[$nibletid] = array_diff($ratings[$nibletid], [$this->user->get()->id]);
        } else {
            // Rate
            $ratings[$nibletid][] = $this->user->get()->id;
        }

        $post->rating = json_encode($ratings) ?: $post->rating;
        $post->update($this->database);

        $this->page->command('alert', $unrate ? 'Unrated!' : 'Rated!');
    }

    private function getRatingSetting(): int
    {
        return $this->config->getSetting('ratings') ?? 0;
    }

    private function isReactionsEnabled(): bool
    {
        return ($this->getRatingSetting() & 1) !== 0;
    }

    private function isAnonymousReactionsEnabled(): bool
    {
        return ($this->getRatingSetting() & 2) !== 0;
    }
}
