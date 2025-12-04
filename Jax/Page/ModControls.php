<?php

declare(strict_types=1);

namespace Jax\Page;

use Carbon\Carbon;
use Jax\Config;
use Jax\DomainDefinitions;
use Jax\IPAddress;
use Jax\Jax;
use Jax\ModControls\ModPosts;
use Jax\ModControls\ModTopics;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Models\Shout;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_map;
use function count;
use function file_get_contents;
use function file_put_contents;
use function filter_var;
use function gmdate;
use function header;
use function implode;
use function nl2br;

use const FILTER_VALIDATE_IP;
use const PHP_EOL;

final readonly class ModControls
{
    public function __construct(
        private Config $config,
        private DomainDefinitions $domainDefinitions,
        private IPAddress $ipAddress,
        private Jax $jax,
        private ModTopics $modTopics,
        private ModPosts $modPosts,
        private Page $page,
        private Request $request,
        private Session $session,
        private TextFormatting $textFormatting,
        private Template $template,
        private User $user,
    ) {
        $this->template->loadMeta('modcp');
    }

    public function render(): void
    {
        if (
            !$this->user->getGroup()?->canModerate
            && !$this->user->get()->mod
        ) {
            $this->page->location('?');

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

        match ($this->request->both('do')) {
            'modp' => $this->modPosts->addPost((int) $this->request->both('pid')),
            'modt' => $this->modTopics->addTopic((int) $this->request->both('tid')),
            'load' => $this->load(),
            'cp' => $this->showModCP(),
            'emem' => $this->showModCP(match (true) {
                $this->request->post('submit') === 'showform'
                    || $this->request->both('mid') !== null => $this->editMember(),
                default => $this->selectMemberToEdit(),
            }),
            'iptools' => $this->showModCP($this->ipTools()),
            default => null,
        };
    }

    private function load(): void
    {
        $script = file_get_contents('dist/modcontrols.js');

        if (!$this->request->isJSAccess()) {
            header('Content-Type: application/javascript; charset=utf-8');
            header('Expires: ' . Carbon::now('UTC')->addMonth()->format(Carbon::RFC7231));

            echo $script;

            exit(0);
        }

        $this->page->command('softurl');
        $this->page->command('script', $script);
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

        $page = $this->template->meta('modcp-index', $cppage);
        $page = $this->template->meta('box', ' id="modcp"', 'Mod CP', $page);

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

        return $this->template->meta('success', 'Profile information saved.');
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

        $hiddenFormFields = $this->jax->hiddenFormFields(
            [
                'act' => 'modcontrols',
                'do' => 'emem',
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
                                <input type="text" id="m_{$name}" name="{$name}" value="{$value}" />
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
                <input type="submit" value="Save" />
            </form>
            HTML;
    }

    private function selectMemberToEdit(): string
    {
        $hiddenFormFields = $this->jax->hiddenFormFields(
            [
                'act' => 'modcontrols',
                'do' => 'emem',
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
                    data-autocomplete-indicator="#validname" />
                <span id="validname"></span>
                <input type="hidden" name="mid" id="mid" onchange="this.form.onsubmit();" />
                <input type="submit" type="View member details" value="Go" />
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

        $changed = false;

        if ($this->request->post('ban') !== null) {
            if (!$this->ipAddress->isBanned($ipAddress)) {
                $changed = true;
                $this->ipAddress->ban($ipAddress);
            }
        } elseif ($this->request->post('unban') !== null) {
            if ($this->ipAddress->isBanned($ipAddress)) {
                $changed = true;
                $this->ipAddress->unBan($ipAddress);
            }
        }

        if ($changed) {
            file_put_contents(
                $this->domainDefinitions->getBoardPath() . '/bannedips.txt',
                implode(PHP_EOL, $this->ipAddress->getBannedIps()),
            );
        }

        $hiddenFields = $this->jax->hiddenFormFields(
            [
                'act' => 'modcontrols',
                'do' => 'iptools',
            ],
        );
        $form = <<<EOT
            <form method='post' data-ajax-form='true'>
                {$hiddenFields}
                <label>IP:
                <input type='text' name='ip' title="Enter IP address" value='{$ipAddress}' /></label>
                <input type='submit' value='Submit' title="Search for IP" />
            </form>
            EOT;
        if ($ipAddress !== '') {
            $page .= "<h3>Data for {$ipAddress}:</h3>";

            $hiddenFields = $this->jax->hiddenFormFields(
                [
                    'act' => 'modcontrols',
                    'do' => 'iptools',
                    'ip' => $ipAddress,
                ],
            );
            $banCode = $this->ipAddress->isBanned($ipAddress) ? <<<'HTML'
                <span style="color:#900">
                    banned
                </span>
                <input type="submit" name="unban"
                    onclick="this.form.submitButton=this" value="Unban" />
                HTML : <<<'HTML'
                <span style="color:#090">
                    not banned
                </span>
                <input type="submit" name="ban"
                    onclick="this.form.submitButton=this" value="Ban" />
                HTML;

            $torDate = gmdate('Y-m-d', Carbon::now('UTC')->subDays(2)->getTimestamp());
            $page .= $this->box(
                'Info',
                <<<EOT
                    <form method='post' data-ajax-form='true'>
                        {$hiddenFields}
                        IP ban status: {$banCode}<br>
                    </form>
                    IP Lookup Services: <ul>
                        <li><a href="https://whois.domaintools.com/{$ipAddress}">DomainTools Whois</a></li>
                        <li><a href="https://www.domaintools.com/research/traceroute/?query={$ipAddress}">
                            DomainTools Traceroute
                        </a></li>
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
                $content[] = $this->template->meta(
                    'user-link',
                    $member->id,
                    $member->groupID,
                    $member->displayName,
                );
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
                    $content .= $member ? $this->template->meta(
                        'user-link',
                        $member->id,
                        $member->groupID,
                        $member->displayName,
                    ) : '';
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
                    . nl2br($this->textFormatting->blockhtml($this->textFormatting->textOnly($post->post)))
                    . '</div>';
            }

            $page .= $this->box('Last 5 posts:', $content);
        }

        return $form . $page;
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
