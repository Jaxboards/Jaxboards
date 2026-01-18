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
        foreach ($this->fetchActivities($member->id) as $entry) {
            $parsed = $this->parseActivityRSS($entry->activity, $member, $entry->affectedUser);
            $link = $this->domainDefinitions->getBoardUrl()
                . $this->textFormatting->blockhtml($parsed['link']);
            $rssFeed->additem(
                [
                    'description' => $parsed['text'],
                    'guid' => $link,
                    'link' => $link,
                    'pubDate' => $this->date->datetimeAsCarbon($entry->activity->date)?->format('r') ?? '',
                    'title' => $parsed['text'],
                ],
            );
        }

        $this->page->earlyFlush($rssFeed->publish());
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
        return $this->template->render('userprofile/activities', [
            'user' => $member,
            'rows' => $this->fetchActivities($member->id),
        ]);
    }

    /**
     * @return array<object{activity:ModelsActivity,affectedUser:?Member}>
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
            static fn(ModelsActivity $modelsActivity): object => (object) [
                'activity' => $modelsActivity,
                'affectedUser' => $members[$modelsActivity->affectedUser] ?? null,
            ],
            $activities,
        );
    }
}
