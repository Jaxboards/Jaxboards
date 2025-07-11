<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\ContactDetails;
use Jax\Database;
use Jax\IPAddress;
use Jax\Models\Group;
use Jax\Models\Member;
use Jax\Page;
use Jax\Page\UserProfile\ProfileTabs;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\User;

use function array_keys;
use function array_map;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function ucfirst;

final readonly class UserProfile
{
    public function __construct(
        private ContactDetails $contactDetails,
        private Database $database,
        private IPAddress $ipAddress,
        private Page $page,
        private ProfileTabs $profileTabs,
        private Request $request,
        private Session $session,
        private Template $template,
        private User $user,
    ) {
        $this->template->loadMeta('userprofile');
    }

    public function render(): void
    {
        preg_match('@\d+@', (string) $this->request->asString->both('act'), $match);
        $userId = $match !== [] ? (int) $match[0] : 0;


        // Nothing is live updating on the profile page
        if ($this->request->isJSUpdate()) {
            return;
        }

        $profile = $userId !== 0 ? $this->fetchUser($userId) : null;


        match (true) {
            !$profile => $this->showProfileError(),
            $this->didComeFromForum() => $this->showContactCard($profile),
            (bool) $this->user->getGroup()?->canViewFullProfile => $this->showFullProfile($profile),
            default => $this->page->location('?'),
        };
    }

    private function didComeFromForum(): bool
    {
        return $this->request->isJSNewLocation()
            && !$this->request->isJSDirectLink()
            && !$this->request->both('page');
    }

    private function fetchGroupTitle(int $groupId): ?string
    {
        $group = Group::selectOne($this->database, Database::WHERE_ID_EQUALS, $groupId);

        return $group?->title;
    }

    private function fetchUser(int $userId): ?Member
    {
        return Member::selectOne($this->database, Database::WHERE_ID_EQUALS, $userId);
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

    private function renderContactDetails(Member $member): string
    {
        $contactDetails = '';

        $links = $this->contactDetails->getContactLinks($member);
        $contactDetails = implode('', array_map(
            static function ($service) use ($links): string {
                [$href, $value] = $links[$service];

                return <<<HTML
                    <div class="contact {$service}"><a href="{$href}">{$value}</a></div>
                    HTML;
            },
            array_keys($links),
        ));

        $contactDetails .= <<<"HTML"
            <div class="contact im">
                <a href="javascript:void(0)"
                    onclick="new IMWindow({$member->id},'{$member->displayName}')"
                    >IM</a>
            </div>
            <div class="contact pm">
                <a href="?act=ucp&what=inbox&view=compose&mid={$member->id}">PM</a>
            </div>
            HTML;

        if ($this->user->getGroup()?->canModerate) {
            $ipReadable = $member->ip !== ''
                ? $this->ipAddress->asHumanReadable($member->ip)
                : '';
            $contactDetails .= <<<HTML
                    <div>IP: <a href="?act=modcontrols&do=iptools&ip={$ipReadable}">{$ipReadable}</a></div>
                HTML;
        }

        return $contactDetails;
    }

    private function showContactCard(Member $member): void
    {
        $contactdetails = '';

        foreach ($this->contactDetails->getContactLinks($member) as $type => [$href]) {
            $contactdetails .= <<<"HTML"
                <a class="{$type} contact" title="{$type} contact" href="{$href}">&nbsp;</a>
                HTML;
        }

        $addContactLink = $this->isUserInList($member->id, $this->user->get()->friends)
            ? "<a href='?act=buddylist&remove={$member->id}'>Remove Contact</a>"
            : "<a href='?act=buddylist&add={$member->id}'>Add Contact</a>";

        $blockLink = $this->isUserInList($member->id, $this->user->get()->enemies)
            ? "<a href='?act=buddylist&unblock={$member->id}'>Unblock Contact</a>"
            : "<a href='?act=buddylist&block={$member->id}'>Block Contact</a>";

        $this->page->command('softurl');
        $this->page->command(
            'window',
            [
                'animate' => false,
                'className' => 'contact-card',
                'content' => $this->template->meta(
                    'userprofile-contact-card',
                    $member->displayName,
                    $member->avatar ?: $this->template->meta('default-avatar'),
                    $member->usertitle,
                    $member->id,
                    $contactdetails,
                    $addContactLink,
                    $blockLink,
                ),
                'minimizable' => false,
                'title' => 'Contact Card',
            ],
        );
    }

    private function showFullProfile(Member $member): void
    {
        [$tabs, $tabHTML] = $this->profileTabs->render($member);

        $this->page->setBreadCrumbs(
            [
                "?act=vu{$member->id}&page=profile" => "{$member->displayName}'s profile",
            ],
        );

        $contactdetails = $this->renderContactDetails($member);

        $birthday = $member->birthdate ?: 'N/A';
        $page = $this->template->meta(
            'userprofile-full-profile',
            $member->displayName,
            $member->avatar ?: $this->template->meta('default-avatar'),
            $member->usertitle,
            $contactdetails,
            $member->full_name ?: 'N/A',
            ucfirst($member->gender) ?: 'N/A',
            $member->location,
            $birthday,
            $member->website !== '' ? "<a href='{$member->website}'>{$member->website}</a>" : 'N/A',
            $member->joinDate,
            $member->lastVisit,
            $member->id,
            $member->posts,
            $this->fetchGroupTitle($member->groupID),
            implode('', $tabs),
            $tabHTML,
            $this->user->getGroup()?->canModerate
                ? "<a class='moderate' href='?act=modcontrols&do=emem&mid={$member->id}'>Edit</a>" : '',
        );
        $this->page->command('update', 'page', $page);
        $this->page->append('PAGE', $page);

        $this->session->set('locationVerbose', "Viewing {$member->displayName}'s profile");
    }

    private function showProfileError(): void
    {
        $error = $this->template->meta('error', "Sorry, this user doesn't exist.");
        $this->page->command('update', 'page', $error);
        $this->page->append('PAGE', $error);
    }
}
