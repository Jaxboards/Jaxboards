<?php

declare(strict_types=1);

namespace Jax\Routes\UserProfile;

use Jax\Date;
use Jax\DomainDefinitions;
use Jax\Models\Activity as ModelsActivity;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\RSSFeed;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_map;

final readonly class Activity
{
    private const ACTIVITY_LIMIT = 30;

    public function __construct(
        private Date $date,
        private DomainDefinitions $domainDefinitions,
        private Page $page,
        private Request $request,
        private Router $router,
        private TextFormatting $textFormatting,
        private Template $template,
        private User $user,
    ) {}

    public function render(Member $member): string
    {

        if ($this->request->both('fmt') === 'RSS') {
            $this->renderRSSFeed($member);

            return '';
        }

        return $this->renderActivitiesPage($member);
    }

    public function renderRSSFeed(Member $member): void
    {
        $boardURL = $this->domainDefinitions->getBoardURL();
        $rssFeed = new RSSFeed(
            [
                'description' => $member->usertitle,
                'link' => $boardURL . $this->router->url('profile', ['id' => $member->id]),
                'title' => $member->displayName . "'s recent activity",
            ],
        );
        foreach ($this->fetchActivities($member->id) as [$activity, $affectedUser]) {
            $parsed = $this->parseActivityRSS($activity, $member, $affectedUser);
            $rssFeed->additem(
                [
                    'description' => $parsed['text'],
                    'guid' => $activity->id,
                    'link' => $this->domainDefinitions->getBoardUrl()
                        . $this->textFormatting->blockhtml($parsed['link']),
                    'pubDate' => $this->date->datetimeAsCarbon($activity->date)?->format('r') ?? '',
                    'title' => $parsed['text'],
                ],
            );
        }

        $this->page->earlyFlush($rssFeed->publish());
    }

    private function parseActivity(
        ModelsActivity $modelsActivity,
        Member $member,
        ?Member $affectedUser,
    ): string {
        $user = $this->template->render(
            'user-link',
            ['user' => [
                'id' => $modelsActivity->uid,
                'groupID' => $member->groupID,
                'displayName' => $this->user->get()->id === $modelsActivity->uid ? 'You' : $member->displayName
            ]],
        );
        $otherguy = $affectedUser instanceof Member ? $this->template->render('user-link', ['user' => $affectedUser]) : '';

        $date = $modelsActivity->date
            ? $this->date->smallDate($modelsActivity->date)
            : '';

        $urls = [
            'new_post' => $this->router->url('topic', [
                'id' => $modelsActivity->tid,
                'slug' => $this->textFormatting->slugify($modelsActivity->arg1),
                'findpost' => $modelsActivity->pid,
            ]),
            'new_topic' => $this->router->url('topic', [
                'id' => $modelsActivity->tid,
                'slug' => $this->textFormatting->slugify($modelsActivity->arg1),
            ]),
        ];

        $text = match ($modelsActivity->type) {
            'profile_comment' => "{$user}  commented on  {$otherguy}'s profile",
            'new_post' => <<<HTML
                {$user} posted in topic
                <a href="{$urls['new_post']}">{$modelsActivity->arg1}</a>
                {$date}
                HTML,
            'new_topic' => <<<HTML
                {$user} created new topic
                <a href="{$urls['new_topic']}">{$modelsActivity->arg1}</a>
                {$date}
                HTML,
            'profile_name_change' => $this->template->render(
                'user-link',
                [
                    'id' => $modelsActivity->uid,
                    'groupID' => $member->groupID,
                    'displayName' => $modelsActivity->arg1
                ]
            ) . ' is now known as ' . $this->template->render(
                'user-link',
                [
                    'id' => $modelsActivity->uid,
                    'groupID' => $member->groupID,
                    'displayName' => $modelsActivity->arg2
                ]
            ) . ', ' . $date,
            'buddy_add' => $user . ' made friends with ' . $otherguy,
            default => '',
        };

        return "<div class=\"activity {$modelsActivity->type}\">{$text}</div>";
    }

    /**
     * @return array{link:string,text:string}
     */
    private function parseActivityRSS(
        ModelsActivity $modelsActivity,
        Member $user,
        ?Member $affectedUser,
    ): array {
        return match ($modelsActivity->type) {
            'profile_comment' => [
                'link' => $this->router->url('profile', [
                    'id' => $modelsActivity->affectedUser,
                ]),
                'text' => "{$user->displayName} commented on {$affectedUser->displayName}'s profile",
            ],
            'new_post' => [
                'link' => $this->router->url('topic', [
                    'id' => $modelsActivity->tid,
                    'findpost' => $modelsActivity->pid,
                ]),
                'text' => $user->displayName . ' posted in topic ' . $modelsActivity->arg1,
            ],
            'new_topic' => [
                'link' => $this->router->url('topic', [
                    'id' => $modelsActivity->tid,
                ]),
                'text' => $user->displayName . ' created new topic ' . $modelsActivity->arg1,
            ],
            'profile_name_change' => [
                'link' => $this->router->url('profile', [
                    'id' => $modelsActivity->uid,
                ]),
                'text' => $modelsActivity->arg1 . ' is now known as ' . $modelsActivity->arg2,
            ],
            'buddy_add' => [
                'link' => $this->router->url('profile', [
                    'id' => $modelsActivity->uid,
                ]),
                'text' => $user->displayName . ' made friends with ' . $affectedUser->displayName,
            ],
            default => ['link' => '', 'text' => ''],
        };
    }

    private function renderActivitiesPage(Member $member): string
    {
        $tabHTML = '';

        foreach ($this->fetchActivities($member->id) as [$activity, $affectedUser]) {
            $tabHTML .= $this->parseActivity($activity, $member, $affectedUser);
        }

        $rssButtonURL = $this->router->url('profile', ['id' => $member->id, 'page' => 'activity', 'fmt' => 'RSS']);

        return $tabHTML !== ''
            ? <<<HTML
                <a href="{$rssButtonURL}" target="_blank" class="social" style='float:right'
                >RSS</a>{$tabHTML}
                HTML
            : 'This user has yet to do anything noteworthy!';
    }

    /**
     * @return array<array{ModelsActivity,?Member}>
     */
    private function fetchActivities(int $profileId): array
    {
        $activities = ModelsActivity::selectMany(
            <<<'SQL'
                WHERE `uid`= ?
                ORDER BY id DESC
                LIMIT ?
                SQL,
            $profileId,
            self::ACTIVITY_LIMIT,
        );

        $members = Member::joinedOn(
            $activities,
            static fn(ModelsActivity $modelsActivity): ?int => $modelsActivity->affectedUser,
        );

        return array_map(
            static fn(ModelsActivity $modelsActivity): array => [
                $modelsActivity,
                $members[$modelsActivity->affectedUser] ?? null,
            ],
            $activities,
        );
    }
}
