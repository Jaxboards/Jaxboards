<?php

declare(strict_types=1);

namespace Jax\Page\Topic;

use Jax\Config;
use Jax\Database;
use Jax\Page;
use Jax\Template;
use Jax\User;

use function array_diff;
use function array_key_exists;
use function array_merge;
use function count;
use function in_array;
use function json_decode;
use function json_encode;

final class Reactions
{
    public function __construct(
        private readonly Config $config,
        private readonly Page $page,
        private readonly Database $database,
        private readonly User $user,
        private readonly Template $template,
    ) {}

    /**
     * @param array $post - The Post record
     */
    public function render(array $post): string
    {
        // Reactions are turned off
        if (!$this->isReactionsEnabled()) {
            return '';
        }

        $prating = [];
        $postratingbuttons = '';
        $showrating = '';
        if ($post['rating']) {
            $prating = json_decode((string) $post['rating'], true);
        }

        $rniblets = $this->getRatingNiblets();
        foreach ($rniblets as $nibletIndex => $niblet) {
            $nibletHTML = $this->template->meta(
                'rating-niblet',
                $niblet['img'],
                $niblet['title'],
            );
            $postratingbuttons .= <<<HTML
                <a href="?act=vt{$post['tid']}&amp;ratepost={$post['pid']}&amp;niblet={$nibletIndex}">
                    {$nibletHTML}
                </a>
                HTML;

            if (!isset($prating[$nibletIndex])) {
                continue;
            }

            if (!$prating[$nibletIndex]) {
                continue;
            }

            $num = 'x' . count($prating[$nibletIndex]);
            $postratingbuttons .= $num;
            $showrating .= $this->template->meta(
                'rating-niblet',
                $niblet['img'],
                $niblet['title'],
            ) . $num;
        }

        return $this->template->meta(
            'rating-wrapper',
            $postratingbuttons,
            $this->isAnonymousReactionsEnabled()
                ? ''
                : "<a href='?act=vt{$post['tid']}&amp;listrating={$post['pid']}'>(List)</a>",
            $showrating,
        );
    }

    public function toggleReaction(int $postid, int $nibletid): void
    {
        $this->page->command('softurl');

        $result = $this->database->safeselect(
            ['rating'],
            'posts',
            Database::WHERE_ID_EQUALS,
            $this->database->basicvalue($postid),
        );
        $post = $this->database->arow($result);
        $this->database->disposeresult($result);

        $niblets = $this->getRatingNiblets();
        $ratings = [];

        $error = match (true) {
            $this->user->isGuest() => 'You must be logged in to rate posts.',
            !$post => "That post doesn't exist.",
            !$niblets[$nibletid] => 'Invalid rating',
            default => null,
        };

        if ($error !== null) {
            $this->page->command('error', $error);

            return;
        }

        $ratings = json_decode((string) $post['rating'], true);
        if (!$ratings) {
            $ratings = [];
        }

        if (!array_key_exists($nibletid, $ratings)) {
            $ratings[$nibletid] = [];
        }

        $unrate = in_array($this->user->get('id'), $ratings[$nibletid], true);
        // Unrate
        if ($unrate) {
            $ratings[$nibletid] = array_diff($ratings[$nibletid], [$this->user->get('id')]);
        } else {
            // Rate
            $ratings[$nibletid][] = $this->user->get('id');
        }

        $this->database->safeupdate(
            'posts',
            [
                'rating' => json_encode($ratings),
            ],
            Database::WHERE_ID_EQUALS,
            $this->database->basicvalue($postid),
        );
        $this->page->command('alert', $unrate ? 'Unrated!' : 'Rated!');
    }

    public function getRatingNiblets(): array
    {
        static $ratingNiblets = null;

        if ($ratingNiblets) {
            return $ratingNiblets;
        }

        $result = $this->database->safeselect(
            ['id', 'img', 'title'],
            'ratingniblets',
        );
        $ratingNiblets = [];
        while ($niblet = $this->database->arow($result)) {
            $ratingNiblets[$niblet['id']] = ['img' => $niblet['img'], 'title' => $niblet['title']];
        }

        return $ratingNiblets;
    }

    public function listReactions(int $pid): void
    {
        if ($this->isAnonymousReactionsEnabled()) {
            return;
        }

        $this->page->command('softurl');
        $result = $this->database->safeselect(
            ['rating'],
            'posts',
            Database::WHERE_ID_EQUALS,
            $this->database->basicvalue($pid),
        );
        $row = $this->database->arow($result);
        $this->database->disposeresult($result);
        $ratings = $row ? json_decode((string) $row['rating'], true) : [];

        if (empty($ratings)) {
            return;
        }

        $members = [];
        foreach ($ratings as $v) {
            $members = array_merge($members, $v);
        }

        if ($members === []) {
            $this->page->command('alert', 'This post has no ratings yet!');

            return;
        }

        $result = $this->database->safeselect(
            [
                'id',
                'display_name',
                'group_id',
            ],
            'members',
            Database::WHERE_ID_IN,
            $members,
        );
        $mdata = [];
        while ($member = $this->database->arow($result)) {
            $mdata[$member['id']] = [$member['display_name'], $member['group_id']];
        }

        unset($members);
        $niblets = $this->getRatingNiblets();
        $page = '';
        foreach ($ratings as $index => $rating) {
            $page .= '<div class="column">';
            $page .= '<img src="' . $niblets[$index]['img'] . '" /> '
                . $niblets[$index]['title'] . '<ul>';
            foreach ($rating as $mid) {
                $page .= '<li>' . $this->template->meta(
                    'user-link',
                    $mid,
                    $mdata[$mid][1],
                    $mdata[$mid][0],
                ) . '</li>';
            }

            $page .= '</ul></div>';
        }

        $this->page->command('listrating', $pid, $page);
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
