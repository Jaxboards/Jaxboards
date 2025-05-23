<?php

declare(strict_types=1);

namespace Jax\Page\Topic;

use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Template;
use Jax\User;

use function array_filter;
use function array_keys;
use function array_map;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function json_decode;
use function round;

final readonly class Poll
{
    public function __construct(
        private Database $database,
        private Jax $jax,
        private Page $page,
        private Request $request,
        private Template $template,
        private User $user,
    ) {}

    /**
     * @param array<string,mixed> $topicData - The Topic Record
     */
    public function render(array $topicData): string
    {
        return $this->template->meta(
            'box',
            " id='poll'",
            $topicData['poll_q'],
            $this->renderPollHTML($topicData),
        );
    }

    /**
     * @param array<string,mixed> $topicData - The Topic Record
     */
    public function vote(array $topicData): void
    {
        $error = null;
        if ($this->user->isGuest()) {
            $this->page->command('error', 'You must be logged in to vote!');

            return;
        }

        $choice = $this->request->both('choice');
        $choices = json_decode((string) $topicData['poll_choices'], true);
        $numchoices = count($choices);

        $results = $this->parsePollResults((string) $topicData['poll_results']);

        // Results is now an array of arrays, the keys of the parent array
        // correspond to the choices while the arrays within the array
        // correspond to a collection of user IDs that have voted for that
        // choice.
        $voted = false;
        foreach ($results as $result) {
            foreach ($result as $voterId) {
                if ($voterId === $this->user->get('id')) {
                    $voted = true;

                    break;
                }
            }
        }

        if ($voted) {
            $error = 'You have already voted on this poll!';
        }

        if ($topicData['poll_type'] === 'multi') {
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
            !is_numeric($choice)
            || $choice >= $numchoices
            || $choice < 0
        ) {
            $error = 'Invalid choice';
        }

        if ($error !== null) {
            $this->page->command('error', $error);

            return;
        }

        if (is_array($choice)) {
            if ($topicData['poll_type'] === 'multi') {
                foreach ($choice as $c) {
                    $results[$c][] = $this->user->get('id');
                }
            }
        } else {
            $results[$choice][] = $this->user->get('id');
        }

        $presults = [];
        for ($x = 0; $x < $numchoices; ++$x) {
            $presults[$x] = isset($results[$x]) && $results[$x]
                ? implode(',', $results[$x]) : '';
        }

        $presults = implode(';', $presults);

        $this->database->safeupdate(
            'topics',
            [
                'poll_results' => $presults,
            ],
            Database::WHERE_ID_EQUALS,
            $topicData['id'],
        );

        $topicData['poll_results'] = $presults;

        $this->page->command(
            'update',
            '#poll .content',
            $this->renderPollHTML(
                $topicData,
            ),
            '1',
        );
    }

    /**
     * @param array<string,mixed> $topicData - The Topic Record
     */
    private function renderPollHTML(array $topicData): string
    {
        $type = $topicData['poll_type'];
        $choices = json_decode((string) $topicData['poll_choices']);
        $results = $topicData['poll_results'];


        if (!$choices) {
            $choices = [];
        }

        $usersvoted = [];
        $voted = false;

        $totalvotes = 0;

        // Accomplish three things at once:
        // * Determine if the user has voted.
        // * Count up the number of votes.
        // * Parse the result set.
        $numvotes = [];
        foreach ($this->parsePollResults($results) as $optionIndex => $voters) {
            $totalvotes += ($numvotes[$optionIndex] = count($voters));
            if (
                !$this->user->isGuest()
                && in_array($this->user->get('id'), $voters, true)
            ) {
                $voted = true;
            }

            foreach ($voters as $voter) {
                $usersvoted[$voter] = 1;
            }
        }

        $usersvoted = count($usersvoted);

        if ($voted) {
            $resultRows = implode('', array_map(
                static function (int $index, string $choice) use ($numvotes, $totalvotes): string {
                    $percentOfVotes = round($numvotes[$index] / $totalvotes * 100, 2);

                    return <<<HTML
                        <tr>
                            <td>{$choice}</td>
                            <td class='numvotes'>
                                {$numvotes[$index]} votes ({$percentOfVotes}%)
                            </td>
                            <td style='width:200px'>
                                <div class='bar' style='width:{$percentOfVotes}%;'></div>
                            </td>
                        </tr>
                        HTML;
                },
                array_keys($choices),
                $choices,
            ));

            return <<<HTML
                <table>
                    {$resultRows}
                    <tr>
                        <td colspan='3' class='totalvotes'>Total Votes: {$usersvoted}</td>
                    </tr>
                </table>
                HTML;
        }

        $hiddenFields = $this->jax->hiddenFormFields(
            [
                'act' => 'vt' . $topicData['id'],
                'votepoll' => '1',
            ],
        );

        $choicesHTML = '';
        foreach ($choices as $index => $value) {
            $input = $type === 'multi'
                ? "<input type='checkbox' name='choice[]' value='{$index}' id='poll_{$index}' />"
                : "<input type='radio' name='choice' value='{$index}' id='poll_{$index}' /> ";

            $choicesHTML .= <<<HTML
                <div class='choice'>
                    {$input}
                    <label for='poll_{$index}'>{$value}</label>
                </div>
                HTML;
        }

        return <<<HTML
            <form method='post' action='?' data-ajax-form='true'>
                {$hiddenFields}
                {$choicesHTML}
                <div class='buttons'>
                    <input type='submit' value='Vote'>
                </div>
            </form>
            HTML;
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
