<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Database\Database;
use Jax\Interfaces\Route;
use Jax\Models\Post;
use Jax\Models\Report as ModelsReport;
use Jax\Page;
use Jax\Request;
use Jax\Template;
use Jax\User;

use function array_key_exists;
use function mb_substr;

final readonly class Report implements Route
{
    public const REPORT_REASONS = [
        'spam' => 'Spam',
        'trolling' => 'Flaming/Trolling',
        'inappropriate' => 'Inappropriate Content',
        'needs_to_move' => 'Topic needs to be moved',
        'needs_to_pin' => 'Topic needs to be pinned',
    ];

    public function __construct(
        private Database $database,
        private Request $request,
        private Page $page,
        private Template $template,
        private User $user,
    ) {}

    public function route(array $params): void
    {
        $pid = (int) $this->request->both('pid');
        $reason = $this->request->asString->post('reason');

        if ($this->user->isGuest()) {
            $this->page->command(
                'error',
                'You must be logged in to report posts',
            );

            return;
        }

        match (true) {
            array_key_exists(
                $reason,
                self::REPORT_REASONS,
            ) => $this->reportPost(
                $pid,
                $reason,
            ),
            default => $this->reportPostForm($pid),
        };
    }

    private function reportPost(int $pid, string $reason): void
    {
        $report = new ModelsReport();
        $report->pid = $pid;
        $report->reporter = $this->user->get()->id;
        $report->reason = $reason;
        $report->note = mb_substr(
            (string) $this->request->asString->post('note'),
            0,
            100,
        );
        $report->reportDate = $this->database->datetime();
        $report->insert();

        $this->page->command('closewindow', "#report{$pid}");

        if ($report->id === 0) {
            $this->page->command(
                'error',
                'There was an error submitting your report',
            );

            return;
        }

        $this->page->command('success', 'Thank you for your report');
    }

    private function reportPostForm(int $pid): void
    {
        $post = Post::selectOne($pid);

        if ($post === null) {
            return;
        }

        $this->page->command('softurl');
        $this->page->command('window', [
            'title' => "What's wrong with this post?",
            'id' => "report{$post->id}",
            'content' => $this->template->render('report/report-form', [
                'post' => $post,
                'reasons' => self::REPORT_REASONS,
            ]),
        ]);
    }
}
