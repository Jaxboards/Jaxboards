<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use GeoIp2\Model\City;
use Jax\Config;
use Jax\Database\Database;
use Jax\GeoLocate;
use Jax\Interfaces\Route;
use Jax\IPAddress;
use Jax\Lodash;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Models\Report;
use Jax\Models\Session as ModelsSession;
use Jax\Models\Shout;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Routes\ModControls\ModPosts;
use Jax\Routes\ModControls\ModTopics;
use Jax\Session;
use Jax\Template;
use Jax\User;
use Override;

use function array_filter;
use function array_map;
use function arsort;
use function count;
use function filter_var;
use function gmdate;

use const FILTER_VALIDATE_IP;

final readonly class ModControls implements Route
{
    public function __construct(
        private Config $config,
        private Database $database,
        private GeoLocate $geoLocate,
        private IPAddress $ipAddress,
        private ModTopics $modTopics,
        private ModPosts $modPosts,
        private Page $page,
        private Request $request,
        private Router $router,
        private Session $session,
        private Template $template,
        private User $user,
    ) {}

    #[Override]
    public function route($params): void
    {
        if (!$this->user->isModerator() && !$this->user->get()->mod) {
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
                $this->request->post('submit') === 'showform' || $this->request->both('mid') !== null
                    => $this->editMember(),
                default => $this->selectMemberToEdit(),
            }),
            'iptools' => $this->showModCP($this->ipTools()),
            'onlineSessions' => $this->showModCP($this->showOnlineSessions()),
            'reports' => $this->showModCP($this->showReports()),
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

    private function showModCP(string $cppage = 'Choose an option on the left.'): void
    {
        if (!$this->user->isModerator()) {
            return;
        }

        $page = $this->template->render('modcontrols/index', [
            'content' => $cppage,
        ]);
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

        if ($this->request->post('submit') === 'save') {
            $page .= $this->updateMember();
        }

        // Get the member data.
        if ($memberId !== 0) {
            $member = Member::selectOne($memberId);
        } elseif (!$memberName) {
            return $this->page->error('Member name is a required field.');
        } else {
            $members = Member::selectMany('WHERE `displayName` LIKE ?', $memberName . '%');
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
            || $member->groupID === 2 && ($this->user->get()->id !== 1 && $member->id !== $this->user->get()->id)
        ) {
            return $this->page->error('You do not have permission to edit this profile.');
        }

        return $page
        . $this->template->render('modcontrols/edit-member', [
            'member' => $member,
        ]);
    }

    private function selectMemberToEdit(): string
    {
        return $this->template->render('modcontrols/edit-member');
    }

    private function ipTools(): string
    {
        $ipAddress = $this->request->asString->both('ip') ?? '';
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $ipAddress = '';
        }

        if ($this->request->post('ban') !== null) {
            $this->ipAddress->ban($ipAddress);
        } elseif ($this->request->post('unban') !== null) {
            $this->ipAddress->unBan($ipAddress);
        }

        $posts = [];
        $shoutResults = [];
        $usersWithIP = [];
        $location = 'Unknown';

        if ($ipAddress !== '') {
            $geo = $this->geoLocate->lookup($ipAddress);

            if ($geo !== null) {
                $flag = $this->geoLocate->getFlagEmoji($geo->country->isoCode);
                $location = "{$geo->city->name}, {$geo->country->name} {$flag}";
            }

            $usersWithIP = Member::selectMany('WHERE `ip`=?', $this->ipAddress->asBinary($ipAddress));

            if ($this->config->getSetting('shoutbox')) {
                $shouts = Shout::selectMany('WHERE `ip`=?
                    ORDER BY `id`
                    DESC LIMIT 5', $this->ipAddress->asBinary($ipAddress));

                $members = Member::joinedOn($shouts, static fn(Shout $shout): int => $shout->uid);

                foreach ($shouts as $shout) {
                    $shoutResults[] = [
                        'user' => $members[$shout->uid] ?? null,
                        'shout' => $shout->shout,
                    ];
                }
            }

            $posts = Post::selectMany(
                'WHERE `ip`=? ORDER BY `id` DESC LIMIT 5',
                $this->ipAddress->asBinary($ipAddress),
            );
        }

        return $this->template->render('modcontrols/iptools', [
            'ipAddress' => $ipAddress,
            'isBanned' => $this->ipAddress->isBanned($ipAddress),
            'location' => $location,
            'posts' => $posts,
            'shoutResults' => $shoutResults,
            'torDate' => gmdate('Y-m-d', Carbon::now('UTC')->subDays(2)->getTimestamp()),
            'usersWithIP' => $usersWithIP,
        ]);
    }

    private function showOnlineSessions(): string
    {
        $allSessions = ModelsSession::selectMany('ORDER BY lastUpdate LIMIT 100');

        /** @var array<string,array<ModelsSession>> */
        $groupedSessions = Lodash::groupBy(
            $allSessions,
            static fn(ModelsSession $modelsSession): string => $modelsSession->useragent,
        );
        arsort($groupedSessions);

        $rows = [];
        foreach ($groupedSessions as $userAgent => $sessions) {
            $ips = array_filter(
                array_map(
                    fn(ModelsSession $modelsSession): string => $this->ipAddress->asHumanReadable($modelsSession->ip),
                    $sessions,
                ),
                static fn(string $ip): bool => $ip !== '',
            );
            $ipsWithFlags = array_map(function (string $ip): array {
                $geo = $this->geoLocate->lookup($ip);
                $flag = $geo instanceof City ? $this->geoLocate->getFlagEmoji($geo->country->isoCode) : null;

                return [
                    'ip' => $ip,
                    'flag' => $flag,
                ];
            }, $ips);

            $rows[$userAgent] = [
                'ipsWithFlags' => $ipsWithFlags,
            ];
        }

        return $this->box('Most Active User Agents', $this->template->render('modcontrols/online-sessions', [
            'rows' => $rows,
        ]));
    }

    private function showReport(int $reportId): string
    {
        $report = Report::selectOne($reportId);
        if (!$report instanceof Report) {
            return '';
        }

        $post = Post::selectOne($report->pid);
        if (!$post instanceof Post) {
            return '';
        }

        if ($report->acknowledger === null) {
            $report->acknowledger = $this->user->get()->id;
            $report->acknowledgedDate = $this->database->datetime();
            $report->update();
        }

        $this->router->redirect('topic', ['id' => $post->tid, 'findpost' => $post->id], "#pid_{$post->id}");

        return '';
    }

    private function showReports(): string
    {
        $reportId = (int) $this->request->both('reportId');
        if ($reportId !== 0) {
            $this->showReport($reportId);
        }

        $reports = Report::selectMany('ORDER BY reportDate DESC LIMIT 100');
        $posts = Post::joinedOn($reports, static fn(Report $report): int => $report->pid);
        $reporters = Member::joinedOn($reports, static fn(Report $report): int => $report->reporter);
        $acknowledgers = Member::joinedOn($reports, static fn(Report $report): ?int => $report->acknowledger);

        $rows = array_map(static fn(Report $report): array => [
            'report' => $report,
            'post' => $posts[$report->pid],
            'reporter' => $reporters[$report->reporter],
            'acknowledger' => $report->acknowledger ? $acknowledgers[$report->acknowledger] : null,
        ], $reports);

        return $this->template->render('modcontrols/post-reports', ['rows' => $rows]);
    }

    private function box(string $title, string $content): string
    {
        $content = $content ?: '--No Data--';

        return <<<EOT
            <div class='minibox'>
                <div class='title'>{$title}</div>
                <div class='content'>{$content}</div>
            </div>
            EOT;
    }
}
