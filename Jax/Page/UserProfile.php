<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Date;
use Jax\IPAddress;
use Jax\Page;
use Jax\Page\UserProfile\ProfileTabs;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_filter;
use function array_keys;
use function array_map;
use function array_reduce;
use function explode;
use function in_array;
use function mb_substr;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function ucfirst;

final class UserProfile
{
    private const CONTACT_URLS = [
        'aim' => 'aim:goaim?screenname=%s',
        'bluesky' => 'https://bsky.app/profile/%s.bsky.social',
        'discord' => 'discord:%s',
        'googlechat' => 'gchat:chat?jid=%s',
        'msn' => 'msnim:chat?contact=%s',
        'skype' => 'skype:%s',
        'steam' => 'https://steamcommunity.com/id/%s',
        'twitter' => 'https://twitter.com/%s',
        'yim' => 'ymsgr:sendim?%s',
        'youtube' => 'https://youtube.com/%s',
    ];

    /**
     * @var array<string,null|float|int|string> the profile we are currently viewing
     */
    private ?array $profile = null;

    public function __construct(
        private readonly Database $database,
        private readonly Date $date,
        private readonly IPAddress $ipAddress,
        private readonly Page $page,
        private readonly ProfileTabs $profileTabs,
        private readonly Request $request,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly Template $template,
        private readonly User $user,
    ) {
        $this->template->loadMeta('userprofile');
    }

    public function render(): void
    {
        preg_match('@\d+@', (string) $this->request->both('act'), $match);
        $userId = (int) $match[0];


        // Nothing is live updating on the profile page
        if ($this->request->isJSUpdate() && !$this->request->hasPostData()) {
            return;
        }

        $this->profile = $userId ? $this->fetchUser($userId) : null;

        match (true) {
            !$this->profile => $this->showProfileError(),
            $this->didComeFromForum() => $this->showContactCard(),
            (bool) $this->user->getPerm('can_view_fullprofile') => $this->showFullProfile(),
            default => $this->page->location('?'),
        };
    }

    private function didComeFromForum(): bool
    {
        return $this->request->isJSNewLocation()
            && !$this->request->isJSDirectLink()
            && !$this->request->both('view');
    }

    private function fetchGroupTitle(int $groupId): ?string
    {
        $result = $this->database->safeselect(
            ['title'],
            'member_groups',
            Database::WHERE_ID_EQUALS,
            $groupId,
        );
        $group = $this->database->arow($result);
        $this->database->disposeresult($result);

        return $group['title'] ?? null;
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchUser(int $userId): ?array
    {
        $result = $this->database->safeselect(
            [
                'ip',
                'about',
                'avatar',
                'birthdate',
                'contact_aim',
                'contact_bluesky',
                'contact_discord',
                'contact_gtalk AS contact_googlechat',
                'contact_msn',
                'contact_skype',
                'contact_steam',
                'contact_twitter',
                'contact_yim',
                'contact_youtube',
                'display_name',
                'email',
                'enemies',
                'friends',
                'full_name',
                'gender',
                'group_id',
                'id',
                'location',
                '`mod`',
                'name',
                'posts',
                'sig',
                'skin_id',
                'usertitle',
                'website',
                'UNIX_TIMESTAMP(`join_date`) AS `join_date`',
                'UNIX_TIMESTAMP(`last_visit`) AS `last_visit`',
                'DAY(`birthdate`) AS `dob_day`',
                'MONTH(`birthdate`) AS `dob_month`',
                'YEAR(`birthdate`) AS `dob_year`',
            ],
            'members',
            Database::WHERE_ID_EQUALS,
            $userId,
        );
        $user = $this->database->arow($result);
        $this->database->disposeresult($result);

        return $user;
    }

    private function isUserInList(int $userId, string $listName): bool
    {
        return !$this->user->isGuest() && in_array(
            $userId,
            array_map(
                static fn($userId) => (int) $userId,
                explode(',', (string) $this->user->get($listName)),
            ),
            true,
        );
    }

    private function renderContactDetails(): string
    {
        $profile = $this->profile;
        $contactDetails = '';
        $contactFields = array_filter(array_keys($profile), static fn($field) => str_starts_with($field, 'contact'));

        $contactDetails = array_reduce($contactFields, static function ($html, $field) use ($profile) {
            $type = mb_substr($field, 8);
            $href = sprintf(self::CONTACT_URLS[$type], $profile[$field]);
            $html .= <<<HTML
                <div class="contact {$type}"><a href="{$href}">{$field}</a></div>
                HTML;

            return $html;
        }, '');

        $contactDetails .= <<<HTML
            <div class="contact im">
                <a href="javascript:void(0)"
                    onclick="new IMWindow('{$profile['id']}','{$profile['display_name']}')"
                    >IM</a>
            </div>
            <div class="contact pm">
                <a href="?act=ucp&what=inbox&page=compose&mid={$profile['id']}">PM</a>
            </div>
            HTML;

        if ($this->user->getPerm('can_moderate')) {
            $ipReadable = $this->ipAddress->asHumanReadable($profile['ip']);
            $contactDetails .= <<<HTML
                    <div>IP: <a href="?act=modcontrols&do=iptools&ip={$ipReadable}">{$ipReadable}</a></div>
                HTML;
        }

        return $contactDetails;
    }

    private function showContactCard(): void
    {
        $contactdetails = '';
        $profile = $this->profile;

        foreach (self::CONTACT_URLS as $field => $url) {
            if (!$profile['contact_' . $field]) {
                continue;
            }

            $href = sprintf(
                $url,
                $this->textFormatting->blockhtml(
                    $profile["contact_{$field}"],
                ),
            );
            $contactdetails .= <<<"HTML"
                <a class="{$field} contact" title="{$field} contact" href="{$href}">&nbsp;</a>
                HTML;
        }

        $addContactLink = $this->isUserInList($profile['id'], 'friends')
            ? "<a href='?act=buddylist&remove={$profile['id']}'>Remove Contact</a>"
            : "<a href='?act=buddylist&add={$profile['id']}'>Add Contact</a>";

        $blockLink = $this->isUserInList($profile['id'], 'enemies')
            ? "<a href='?act=buddylist&unblock={$profile['id']}'>Unblock Contact</a>"
            : "<a href='?act=buddylist&block={$profile['id']}'>Block Contact</a>";

        $this->page->command('softurl');
        $this->page->command(
            'window',
            [
                'animate' => false,
                'className' => 'contact-card',
                'content' => $this->template->meta(
                    'userprofile-contact-card',
                    $profile['display_name'],
                    $profile['avatar'] ?: $this->template->meta('default-avatar'),
                    $profile['usertitle'],
                    $profile['id'],
                    $contactdetails,
                    $addContactLink,
                    $blockLink,
                ),
                'minimizable' => false,
                'title' => 'Contact Card',
                'useoverlay' => 1,
            ],
        );
    }

    private function showFullProfile(): void
    {
        [$tabs, $tabHTML] = $this->profileTabs->render($this->profile);
        $profile = $this->profile;

        $this->page->setBreadCrumbs(
            [
                "{$profile['display_name']}'s profile" => "?act=vu{$profile['id']}&view=profile",
            ],
        );

        $contactdetails = $this->renderContactDetails();

        $page = $this->template->meta(
            'userprofile-full-profile',
            $profile['display_name'],
            $profile['avatar'] ?: $this->template->meta('default-avatar'),
            $profile['usertitle'],
            $contactdetails,
            $profile['full_name'] ?: 'N/A',
            ucfirst((string) $profile['gender']) ?: 'N/A',
            $profile['location'],
            $profile['dob_year'] ? "{$profile['dob_month']}/{$profile['dob_day']}/{$profile['dob_year']}" : 'N/A',
            $profile['website'] ? "<a href='{$profile['website']}'>{$profile['website']}</a>" : 'N/A',
            $this->date->autoDate($profile['join_date']),
            $this->date->autoDate($profile['last_visit']),
            $profile['id'],
            $profile['posts'],
            $this->fetchGroupTitle($profile['group_id']),
            $tabs[0],
            $tabs[1],
            $tabs[2],
            $tabs[3],
            $tabs[4],
            $tabs[5],
            $tabHTML,
            $this->user->getPerm('can_moderate')
                ? "<a class='moderate' href='?act=modcontrols&do=emem&mid={$profile['id']}'>Edit</a>" : '',
        );
        $this->page->command('update', 'page', $page);
        $this->page->append('PAGE', $page);

        $this->session->set('location_verbose', "Viewing {$profile['display_name']}'s profile");
    }

    private function showProfileError(): void
    {
        $error = $this->template->meta('error', "Sorry, this user doesn't exist.");
        $this->page->command('update', 'page', $error);
        $this->page->append('PAGE', $error);
    }
}
