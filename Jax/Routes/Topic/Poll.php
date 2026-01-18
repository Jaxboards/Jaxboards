<?php

declare(strict_types=1);

namespace Jax\Routes\Topic;

use Jax\Models\Topic;
use Jax\Page;
use Jax\Request;
use Jax\Template;
use Jax\User;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_sum;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_numeric;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class Poll
{
    public function __construct(
        private Page $page,
        private Request $request,
        private Template $template,
        private User $user,
    ) {}

    public function render(Topic $topic): string
    {
        return $this->template->render('global/box', [
            'boxID' => 'poll',
            'title' => $topic->pollQuestion,
            'content' => $this->renderPollHTML($topic),
        ]);
    }

    public function vote(Topic $topic): void
    {
        $error = null;
        if ($this->user->isGuest()) {
            $error = 'You must be logged in to vote!';
            $this->page->command('error', $error);
            $this->page->append('PAGE', $this->page->error($error));

            return;
        }

        $choice = $this->request->both('choice');
        $choices = json_decode(
            $topic->pollChoices,
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $numchoices = count($choices);

        $results = $this->parsePollResults($topic->pollResults);

        // Results is now an array of arrays, the keys of the parent array
        // correspond to the choices while the arrays within the array
        // correspond to a collection of user IDs that have voted for that
        // choice.
        $voted = false;
        foreach ($results as $result) {
            foreach ($result as $voterId) {
                if ($voterId === $this->user->get()->id) {
                    $voted = true;

                    break;
                }
            }
        }

        if ($voted) {
            $error = 'You have already voted on this poll!';
        }

        if ($topic->pollType === 'multi') {
            if (is_array($choice)) {
                foreach ($choice as $c) {
                    if (is_numeric($c) && $c < $numchoices && $c >= 0) {
                        continue;
                    }

                    $error = 'Invalid choices';
                }
            } else {
                $error = 'Invalid Choice';
            }
        } elseif (
            !is_numeric($choice) ||
            $choice >= $numchoices ||
            $choice < 0
        ) {
            $error = 'Invalid choice';
        }

        if ($error !== null) {
            $this->page->command('error', $error);
            $this->page->append('PAGE', $this->page->error($error));

            return;
        }

        if (is_array($choice)) {
            if ($topic->pollType === 'multi') {
                foreach ($choice as $c) {
                    $results[$c][] = $this->user->get()->id;
                }
            }
        } else {
            $results[$choice][] = $this->user->get()->id;
        }

        $presults = [];
        for ($x = 0; $x < $numchoices; ++$x) {
            $presults[$x] =
                array_key_exists($x, $results) && $results[$x]
                    ? implode(',', $results[$x])
                    : '';
        }

        $topic->pollResults = implode(';', $presults);
        $topic->update();
    }

    private function renderPollHTML(Topic $topic): string
    {
        $type = $topic->pollType;
        $choices = json_decode($topic->pollChoices, flags: JSON_THROW_ON_ERROR);
        if (!is_array($choices)) {
            $choices = [];
        }

        $usersVoted = [];
        $numVotes = [];

        foreach (
            $this->parsePollResults($topic->pollResults)
            as $optionIndex => $voters
        ) {
            $numVotes[$optionIndex] = count($voters);

            foreach ($voters as $voter) {
                $usersVoted[$voter] = 1;
            }
        }

        $voted =
            !$this->user->isGuest() &&
            array_key_exists($this->user->get()->id, $usersVoted);

        return $this->template->render('topic/poll', [
            'choices' => $choices,
            'numVotes' => $numVotes,
            'totalVotes' => array_sum($numVotes),
            'totalVoters' => count($usersVoted),
            'type' => $type,
            'voted' => $voted,
        ]);
    }

    /**
     * Poll results look like this: 1,2,3;4,5;6,7
     * Choices are semicolon separated and user IDs are comma separated.
     *
     * @return array<array<int>>
     */
    private function parsePollResults(string $pollResults): array
    {
        return array_map(
            static fn(string $voters): array => array_filter(
                array_map(
                    static fn($voterId): int => (int) $voterId,
                    explode(',', $voters),
                ),
                static fn($userId): bool => $userId !== 0,
            ),
            explode(';', $pollResults),
        );
    }
}
