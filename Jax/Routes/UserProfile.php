<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\ContactDetails;
use Jax\Date;
use Jax\Interfaces\Route;
use Jax\IPAddress;
use Jax\Models\Group;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Routes\UserProfile\ProfileTabs;
use Jax\Session;
use Jax\Template;
use Jax\User;

use function array_map;
use function explode;
use function in_array;

final readonly class UserProfile implements Route
{
    public function __construct(
        private ContactDetails $contactDetails,
        private Date $date,
        private IPAddress $ipAddress,
        private Page $page,
        private ProfileTabs $profileTabs,
        private Request $request,
        private Router $router,
        private Session $session,
        private Template $template,
        private User $user,
    ) {}

    public function route($params): void
    {
        $userId = (int) $params['id'];
        $page = $params['page'] ?? '';

        // Nothing is live updating on the profile page
        if ($this->request->isJSUpdate()) {
            return;
        }

        $profile = $userId !== 0 ? Member::selectOne($userId) : null;

        match (true) {
            !$profile => $this->showProfileError(),
            $this->didComeFromForum() && $page === '' => $this->showContactCard($profile),
            (bool) $this->user->getGroup()?->canViewFullProfile => $this->showFullProfile($page, $profile),
            default => $this->router->redirect('index'),
        };
    }

    private function didComeFromForum(): bool
    {
        return $this->request->isJSNewLocation()
            && !$this->request->isJSDirectLink();
    }

    private function isUserInList(int $userId, string $list): bool
    {
        return !$this->user->isGuest() && in_array(
            $userId,
            array_map(
                static fn($userId): int => (int) $userId,
                explode(',', $list),
            ),
            true,
        );
    }

    private function showContactCard(Member $member): void
    {
        $this->page->command('softurl');
        $this->page->command(
            'window',
            [
                'animate' => false,
                'className' => 'contact-card',
                'content' => $this->template->render(
                    'userprofile/contact-card',
                    [
                        'member' => $member,
                        'contactLinks' => $this->contactDetails->getContactLinks($member),
                        'isGuest' => $this->user->isGuest(),
                        'isFriend' => $this->isUserInList($member->id, $this->user->get()->friends),
                        'isEnemy' => $this->isUserInList($member->id, $this->user->get()->enemies),
                    ],
                ),
                'minimizable' => false,
                'title' => 'Contact Card',
            ],
        );
    }

    private function showFullProfile(string $page, Member $member): void
    {
        $tabHTML = $this->profileTabs->render($page, $member);

        $this->page->setBreadCrumbs(
            [
                $this->router->url('profile', [
                    'id' => $member->id,
                    'page' => 'profile',
                ]) => "{$member->displayName}'s profile",
            ],
        );

        $birthdate = $member->birthdate !== null
            ? $this->date->dateAsCarbon($member->birthdate)
            : null;

        $profile = $this->template->render(
            'userprofile/full-profile',
            [
                'birthdate' => $birthdate,
                'canModerate' => $this->user->isModerator(),
                'contactLinks' => $this->contactDetails->getContactLinks($member),
                'group' => Group::selectOne($member->groupID),
                'ipAddress' => $member->ip !== ''
                    ? $this->ipAddress->asHumanReadable($member->ip)
                    : '',
                'member' => $member,
                'selectedTab' => $page ?: 'activity',
                'tabHTML' => $tabHTML,
                'tabs' => $this->profileTabs->getTabs(),
            ],
        );

        $this->page->command('update', 'page', $profile);
        $this->page->append('PAGE', $profile);

        $this->session->set('locationVerbose', "Viewing {$member->displayName}'s profile");
    }

    private function showProfileError(): void
    {
        $error = $this->template->render('error', ['message' => "Sorry, this user doesn't exist."]);
        $this->page->command('update', 'page', $error);
        $this->page->append('PAGE', $error);
    }
}
