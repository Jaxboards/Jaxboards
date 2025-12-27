<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use GeoIp2\Model\City;
use Jax\Config;
use Jax\GeoLocate;
use Jax\Interfaces\Route;
use Jax\IPAddress;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Models\Session as ModelsSession;
use Jax\Models\Shout;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Routes\ModControls\ModPosts;
use Jax\Routes\ModControls\ModTopics;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function _\groupBy;
use function array_map;
use function arsort;
use function count;
use function filter_var;
use function gmdate;
use function implode;
use function nl2br;

use const FILTER_VALIDATE_IP;

final readonly class ModControls implements Route
{
    public function __construct(
        private Config $config,
        private GeoLocate $geoLocate,
        private IPAddress $ipAddress,
        private ModTopics $modTopics,
        private ModPosts $modPosts,
        private Page $page,
        private Request $request,
        private Router $router,
        private Session $session,
        private TextFormatting $textFormatting,
        private Template $template,
        private User $user,
    ) {
        $this->template->loadMeta('modcp');
    }

    public function route($params): void
    {
        if (
            !$this->user->getGroup()?->canModerate
            && !$this->user->get()->mod
        ) {
            $this->router->redirect('index');

            return;
        }

        if ($this->request->isJSUpdate()) {
            return;
        }

        if ($this->request->both('cancel')) {
            $this->cancel();

            return;
        }

        $dot = $this->request->asString->post('dot');
        if ($dot !== null) {
            $this->modTopics->doTopics($dot);
        }

        $dop = $this->request->asString->post('dop');
        if ($dop !== null) {
            $this->modPosts->doPosts($dop);
        }

        match ($params['do'] ?? $this->request->both('do')) {
            'modp' => $this->modPosts->addPost((int) $this->request->both('pid')),
            'modt' => $this->modTopics->addTopic((int) $this->request->both('tid')),
            'emem' => $this->showModCP(match (true) {
                $this->request->post('submit') === 'showform'
                    || $this->request->both('mid') !== null => $this->editMember(),
                default => $this->selectMemberToEdit(),
            }),
            'iptools' => $this->showModCP($this->ipTools()),
            'onlineSessions' => $this->showModCP($this->showOnlineSessions()),
            default => $dot === null && $dop === null ? $this->showModCP() : null,
        };
    }

    private function cancel(): void
    {
        $this->session->deleteVar('modpids');
        $this->session->deleteVar('modtids');
        $this->sync();
        $this->page->command('modcontrols_clearbox');
    }

    private function sync(): void
    {
        $this->page->command(
            'modcontrols_postsync',
            $this->session->getVar('modpids') ?? '',
            $this->session->getVar('modtids') ?? '',
        );
    }

    private function showModCP(
        string $cppage = 'Choose an option on the left.',
    ): void {
        if (!$this->user->getGroup()?->canModerate) {
            return;
        }

        $page = $this->template->render(
            'modcp/index',
            [
                'content' => $cppage,
            ],
        );
        $page = $this->template->render('global/box', [
            'boxID' => 'modcp',
            'title' => 'Mod CP',
            'content' => $page,
        ]);

        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }

    private function updateMember(): string
    {
        $displayName = $this->request->asString->post('displayName');
        $mid = (int) $this->request->asString->post('mid');

        $member = Member::selectOne($mid);

        if (!$displayName) {
            return $this->page->error('Display name is invalid.');
        }

        if ($member === null) {
            return $this->page->error('That user does not exist');
        }

        $member->about = $this->request->asString->post('about') ?? '';
        $member->avatar = $this->request->asString->post('avatar') ?? '';
        $member->displayName = $displayName;
        $member->full_name = $this->request->asString->post('full_name') ?? '';
        $member->sig = $this->request->asString->post('signature') ?? '';
        $member->update();

        return $this->template->render('success', ['message' => 'Profile information saved.']);
    }

    private function editMember(): string
    {
        $page = '';

        $member = null;

        $memberId = (int) $this->request->asString->both('mid');
        $memberName = $this->request->asString->post('mname');

        if (
            $this->request->post('submit') === 'save'
        ) {
            $page .= $this->updateMember();
        }

        // Get the member data.
        if ($memberId !== 0) {
            $member = Member::selectOne($memberId);
        } elseif (!$memberName) {
            return $this->page->error('Member name is a required field.');
        } else {
            $members = Member::selectMany(
                'WHERE `displayName` LIKE ?',
                $memberName . '%',
            );
            if (count($members) > 1) {
                return $this->page->error('Many users found!');
            }

            $member = $members[0];
        }

        if (!$member) {
            return $this->page->error('No members found that matched the criteria.');
        }

        if (
            $this->user->get()->groupID !== 2
            || $member->groupID === 2
            && ($this->user->get()->id !== 1
                && $member->id !== $this->user->get()->id)
        ) {
            return $this->page->error('You do not have permission to edit this profile.');
        }

        $hiddenFormFields = Template::hiddenFormFields(
            [
                'mid' => (string) $member->id,
                'submit' => 'save',
            ],
        );
        $fieldRows = implode(
            '',
            array_map(
                static function (array $field): string {
                    [$label, $name, $value, $type] = $field;
                    $input = $type === 'textarea'
                        ? <<<HTML
                                <textarea name="{$name}" id="m_{$name}">{$value}</textarea>
                            HTML
                        : <<<HTML
                                <input type="text" id="m_{$name}" name="{$name}" value="{$value}">
                            HTML;

                    return <<<HTML
                        <tr>
                            <td><label for="m_{$name}">{$label}</label></td>
                            <td>{$input}</td>
                        </tr>
                        HTML;
                },
                [
                    ['Display Name', 'displayName', $member->displayName, 'text'],
                    ['Avatar', 'avatar', $member->avatar, 'text'],
                    ['Full Name', 'full_name', $member->full_name, 'text'],
                    [
                        'About',
                        'about',
                        $this->textFormatting->blockhtml($member->about),
                        'textarea',
                    ],
                    [
                        'Signature',
                        'signature',
                        $this->textFormatting->blockhtml($member->sig),
                        'textarea',
                    ],
                ],
            ),
        );

        return $page . <<<HTML
            <form method="post" data-ajax-form="true">
                {$hiddenFormFields}
                <table>
                    {$fieldRows}
                </table>
                <input type="submit" value="Save">
            </form>
            HTML;
    }

    private function selectMemberToEdit(): string
    {
        $hiddenFormFields = Template::hiddenFormFields(
            [
                'submit' => 'showform',
            ],
        );

        return <<<HTML
            <form method="post" data-ajax-form="true">
                {$hiddenFormFields}
                Member name:
                <input type="text" title="Enter member name" name="mname"
                    data-autocomplete-action="searchmembers"
                    data-autocomplete-output="#mid"
                    data-autocomplete-indicator="#validname">
                <span id="validname"></span>
                <input type="hidden" name="mid" id="mid" onchange="this.form.onsubmit();">
                <input type="submit" type="View member details" value="Go">
            </form>
            HTML;
    }

    private function ipTools(): string
    {
        $page = '';

        $ipAddress = $this->request->asString->both('ip') ?? '';
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $ipAddress = '';
        }

        if ($this->request->post('ban') !== null) {
            $this->ipAddress->ban($ipAddress);
        } elseif ($this->request->post('unban') !== null) {
            $this->ipAddress->unBan($ipAddress);
        }

        $form = <<<EOT
            <form method='post' data-ajax-form='true'>
                <label>IP:
                <input type='text' name='ip' title="Enter IP address" value='{$ipAddress}'></label>
                <input type='submit' value='Submit' title="Search for IP">
            </form>
            EOT;
        if ($ipAddress !== '') {
            $page .= "<h3>Data for {$ipAddress}:</h3>";

            $hiddenFields = Template::hiddenFormFields(
                [
                    'ip' => $ipAddress,
                ],
            );
            $banCode = $this->ipAddress->isBanned($ipAddress) ? <<<'HTML'
                <span style="color:#900">
                    banned
                </span>
                <input type="submit" name="unban"
                    onclick="this.form.submitButton=this" value="Unban">
                HTML : <<<'HTML'
                <span style="color:#090">
                    not banned
                </span>
                <input type="submit" name="ban"
                    onclick="this.form.submitButton=this" value="Ban">
                HTML;

            $geo = $this->geoLocate->lookup($ipAddress);

            $location = 'Unknown';
            if ($geo !== null) {
                $flag = $this->geoLocate->getFlagEmoji($geo->country->isoCode);
                $location = "{$geo->city->name}, {$geo->country->name} {$flag}";
            }

            $torDate = gmdate('Y-m-d', Carbon::now('UTC')->subDays(2)->getTimestamp());
            $page .= $this->box(
                'Info',
                <<<EOT
                    <form method='post' data-ajax-form='true'>
                        {$hiddenFields}
                        IP ban status: {$banCode}<br>
                        Location: {$location}
                    </form>
                    IP Lookup Services: <ul>
                        <li><a href="https://whois.domaintools.com/{$ipAddress}">DomainTools Whois</a></li>
                        <li><a href="https://www.ip2location.com/{$ipAddress}">IP2Location Lookup</a></li>
                        <li><a href="https://www.dan.me.uk/torcheck?ip={$ipAddress}">IP2Location Lookup</a></li>
                        <li><a href="https://metrics.torproject.org/exonerator.html?ip={$ipAddress}&timestamp={$torDate}">
                            ExoneraTor Lookup
                        </a></li>
                        <li><a href="https://www.projecthoneypot.org/ip_{$ipAddress}">Project Honeypot Lookup</a></li>
                        <li><a href="https://www.stopforumspam.com/ipcheck/{$ipAddress}">StopForumSpam Lookup</a></li>
                    </ul>
                    EOT,
            );

            $content = [];
            $members = Member::selectMany(
                'WHERE `ip`=?',
                $this->ipAddress->asBinary($ipAddress),
            );
            foreach ($members as $member) {
                $content[] = $this->template->render('user-link', ['user' => $member]);
            }

            $page .= $this->box('Users with this IP:', implode(', ', $content));

            if ($this->config->getSetting('shoutbox')) {
                $content = '';

                $shouts = Shout::selectMany(
                    'WHERE `ip`=?
                    ORDER BY `id`
                    DESC LIMIT 5',
                    $this->ipAddress->asBinary($ipAddress),
                );

                $members = Member::joinedOn(
                    $shouts,
                    static fn(Shout $shout): int => $shout->uid,
                );

                foreach ($shouts as $shout) {
                    $member = $members[$shout->uid] ?? null;
                    $content .= $member ? $this->template->render('user-link', ['user' => $member]) : '';
                    $content .= ': ' . $shout->shout . '<br>';
                }

                $page .= $this->box('Last 5 shouts:', $content);
            }

            $content = '';
            $posts = Post::selectMany(
                'WHERE `ip`=? ORDER BY `id` DESC LIMIT 5',
                $this->ipAddress->asBinary($ipAddress),
            );
            foreach ($posts as $post) {
                $content .= "<div class='post'>"
                    . nl2br($this->textFormatting->blockhtml($this->textFormatting->textOnly($post->post)), false)
                    . '</div>';
            }

            $page .= $this->box('Last 5 posts:', $content);
        }

        return $form . $page;
    }

    private function showOnlineSessions(): string
    {
        $allSessions = ModelsSession::selectMany(
            'ORDER BY lastUpdate LIMIT 100',
        );

        /** @var array<string,ModelsSession[]> */
        $groupedSessions = groupBy($allSessions, static fn(ModelsSession $modelsSession): string => $modelsSession->useragent);
        arsort($groupedSessions);

        $rows = '';
        foreach ($groupedSessions as $userAgent => $sessions) {
            $count = count($sessions);

            $ips = implode('<br>', array_map(function (ModelsSession $modelsSession): string {
                $ip = $this->ipAddress->asHumanReadable($modelsSession->ip);

                if ($ip === '') {
                    return '';
                }

                $geo = $this->geoLocate->lookup($ip);
                $flag = $geo instanceof City
                    ? $this->geoLocate->getFlagEmoji($geo->country->isoCode)
                    : null;

                $ipToolsURL = $this->router->url('modcontrols', [
                    'do' => 'iptools',
                    'ip' => $ip,
                ]);

                return "<a href=\"{$ipToolsURL}\">{$ip} {$flag}</a>";
            }, $sessions));

            $rows .= <<<HTML
                <tr><td>{$userAgent}</td><td>{$count}</td><td>{$ips}</td></tr>
                HTML;
        }

        return $this->box(
            'Most Active User Agents',
            <<<HTML
                <table class="onlinesessions">
                    <tr><th>User Agent</th><th>Count</th><th>IPs</th></tr>
                    {$rows}
                </table>
                HTML,
        );
    }

    private function box(string $title, string $content): string
    {
        $content = ($content ?: '--No Data--');

        return <<<EOT
            <div class='minibox'>
                <div class='title'>{$title}</div>
                <div class='content'>{$content}</div>
            </div>
            EOT;
    }
}
