<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\IPAddress;
use Jax\Jax;
use Jax\ModControls\ModPosts;
use Jax\ModControls\ModTopics;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_shift;
use function count;
use function fclose;
use function file_get_contents;
use function filter_var;
use function fopen;
use function fwrite;
use function gmdate;
use function header;
use function implode;
use function is_numeric;
use function nl2br;
use function strtotime;
use function time;
use function trim;

use const FILTER_VALIDATE_IP;
use const PHP_EOL;

final readonly class ModControls
{
    public function __construct(
        private Config $config,
        private Database $database,
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
            !$this->user->getPerm('can_moderate')
            && !$this->user->get('mod')
        ) {
            $this->page->command('softurl');
            $this->page->command(
                'alert',
                'Your account does not have moderator permissions.',
            );

            return;
        }

        if ($this->request->isJSUpdate()) {
            return;
        }

        if ($this->request->both('cancel')) {
            $this->cancel();

            return;
        }

        $dot = $this->request->post('dot');
        if ($dot !== null) {
            $this->modTopics->doTopics($dot);
        }

        $dop = $this->request->post('dop');
        if ($dop !== null) {
            $this->modPosts->doPosts($dop);
        }

        match ($this->request->both('do')) {
            'modp' => $this->modPosts->addPost((int) $this->request->both('pid')),
            'modt' => $this->modTopics->addTopic((int) $this->request->both('tid')),
            'load' => $this->load(),
            'cp' => $this->showModCP(),
            'emem' => $this->editMembers(),
            'iptools' => $this->ipTools(),
            default => null,
        };
    }

    private function load(): void
    {
        $script = file_get_contents('dist/modcontrols.js');

        if (!$this->request->isJSAccess()) {
            header('Content-Type: application/javascript; charset=utf-8');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2_592_000) . ' GMT');

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

    private function showModCP(string $cppage = ''): void
    {
        if (!$this->user->getPerm('can_moderate')) {
            return;
        }

        $page = $this->template->meta('modcp-index', $cppage);
        $page = $this->template->meta('box', ' id="modcp"', 'Mod CP', $page);

        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }

    private function editMembers(): void
    {
        if (!$this->user->getPerm('can_moderate')) {
            return;
        }

        $error = null;
        $hiddenFormFields = $this->jax->hiddenFormFields(
            [
                'act' => 'modcontrols',
                'do' => 'emem',
                'submit' => 'showform',
            ],
        );
        $page = <<<HTML
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
        if (
            $this->request->post('submit') === 'save'
        ) {
            if (
                trim((string) $this->request->post('display_name')) === ''
                || trim((string) $this->request->post('display_name')) === '0'
            ) {
                $page .= $this->template->meta('error', 'Display name is invalid.');
            } else {
                $this->database->safeupdate(
                    'members',
                    [
                        'about' => $this->request->post('about'),
                        'avatar' => $this->request->post('avatar'),
                        'display_name' => $this->request->post('display_name'),
                        'full_name' => $this->request->post('full_name'),
                        'sig' => $this->request->post('signature'),
                    ],
                    Database::WHERE_ID_EQUALS,
                    $this->database->basicvalue($this->request->post('mid')),
                );

                if ($this->database->error() !== '') {
                    $page .= $this->template->meta(
                        'error',
                        'Error updating profile information.',
                    );
                } else {
                    $page .= $this->template->meta('success', 'Profile information saved.');
                }
            }
        }

        if (
            $this->request->post('submit') === 'showform'
            || $this->request->both('mid') !== null
        ) {
            $memberFields = [
                'group_id',
                'id',
                'display_name',
                'avatar',
                'full_name',
                'about',
                'sig',
            ];

            $member = null;

            // Get the member data.
            if (is_numeric($this->request->both('mid'))) {
                $result = $this->database->safeselect(
                    $memberFields,
                    'members',
                    Database::WHERE_ID_EQUALS,
                    $this->database->basicvalue($this->request->both('mid')),
                );
                $member = $this->database->arow($result);
                $this->database->disposeresult($result);
            } elseif ($this->request->post('mname')) {
                $result = $this->database->safeselect(
                    $memberFields,
                    'members',
                    'WHERE `display_name` LIKE ?',
                    $this->database->basicvalue($this->request->post('mname') . '%'),
                );
                $members = [];
                while ($member = $this->database->arow($result)) {
                    $members[] = $member;
                }

                if (count($members) > 1) {
                    $error = 'Many users found!';
                } else {
                    $member = array_shift($members);
                }
            } else {
                $error = 'Member name is a required field.';
            }

            if (!$member) {
                $error = 'No members found that matched the criteria.';
            }

            if (
                $this->user->get('group_id') !== 2
                || $member['group_id'] === 2
                && ($this->user->get('id') !== 1
                && $member['id'] !== $this->user->get('id'))
            ) {
                $error = 'You do not have permission to edit this profile.';
            }

            if ($error !== null) {
                $page .= $this->template->meta('error', $error);
            } else {
                function field($label, $name, $value, $type = 'input'): string
                {
                    return '<tr><td><label for="m_' . $name . '">' . $label
                        . '</label></td><td>'
                        . ($type === 'textarea' ? '<textarea name="' . $name
                        . '" id="m_' . $name . '">' . $value . '</textarea>'
                        : '<input type="text" id="m_' . $name . '" name="' . $name
                        . '" value="' . $value . '" />') . '</td></tr>';
                }

                $page .= '<form method="post" '
                    . 'data-ajax-form="true"><table>';
                $page .= $this->jax->hiddenFormFields(
                    [
                        'act' => 'modcontrols',
                        'do' => 'emem',
                        'mid' => $member['id'],
                        'submit' => 'save',
                    ],
                );
                $page .= field(
                    'Display Name',
                    'display_name',
                    $member['display_name'],
                )
                    . field('Avatar', 'avatar', $member['avatar'])
                    . field('Full Name', 'full_name', $member['full_name'])
                    . field(
                        'About',
                        'about',
                        $this->textFormatting->blockhtml($member['about']),
                        'textarea',
                    )
                    . field(
                        'Signature',
                        'signature',
                        $this->textFormatting->blockhtml($member['sig']),
                        'textarea',
                    );
                $page .= '</table><input type="submit" value="Save" /></form>';
            }
        }

        $this->showModCP($page);
    }

    private function ipTools(): void
    {
        $page = '';

        $ipAddress = (string) $this->request->both('ip');
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
            $fileHandle = fopen($this->domainDefinitions->getBoardPath() . '/bannedips.txt', 'w');
            fwrite($fileHandle, implode(PHP_EOL, $this->ipAddress->getBannedIps()));
            fclose($fileHandle);
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
        if ($ipAddress) {
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

            $torDate = gmdate('Y-m-d', strtotime('-2 days'));
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
            $result = $this->database->safeselect(
                [
                    'display_name',
                    'group_id',
                    'id',
                ],
                'members',
                'WHERE `ip`=?',
                $this->database->basicvalue($this->ipAddress->asBinary($ipAddress)),
            );
            while ($member = $this->database->arow($result)) {
                $content[] = $this->template->meta(
                    'user-link',
                    $member['id'],
                    $member['group_id'],
                    $member['display_name'],
                );
            }

            $page .= $this->box('Users with this IP:', implode(', ', $content));

            if ($this->config->getSetting('shoutbox')) {
                $content = '';
                $result = $this->database->safespecial(
                    <<<'SQL'
                        SELECT
                            m.`display_name` AS `display_name`,
                            m.`group_id` AS `group_id`,
                            s.`id` AS `id`,
                            s.`shout` AS `shout`,
                            s.`uid` AS `uid`,
                            UNIX_TIMESTAMP(s.`date`) AS `date`
                        FROM %t s
                        LEFT JOIN %t m
                            ON m.`id`=s.`uid`
                        WHERE s.`ip`=?
                        ORDER BY `id`
                        DESC LIMIT 5
                        SQL
                    ,
                    [
                        'shouts',
                        'members',
                    ],
                    $this->database->basicvalue($this->ipAddress->asBinary($ipAddress)),
                );
                while ($shout = $this->database->arow($result)) {
                    $content .= $this->template->meta(
                        'user-link',
                        $shout['uid'],
                        $shout['group_id'],
                        $shout['display_name'],
                    );
                    $content .= ': ' . $shout['shout'] . '<br>';
                }

                $page .= $this->box('Last 5 shouts:', $content);
            }

            $content = '';
            $result = $this->database->safeselect(
                ['post'],
                'posts',
                'WHERE `ip`=? ORDER BY `id` DESC LIMIT 5',
                $this->database->basicvalue($this->ipAddress->asBinary($ipAddress)),
            );
            while ($post = $this->database->arow($result)) {
                $content .= "<div class='post'>"
                    . nl2br($this->textFormatting->blockhtml($this->textFormatting->textOnly($post['post'])))
                    . '</div>';
            }

            $page .= $this->box('Last 5 posts:', $content);
        }

        $this->showModCP($form . $page);
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
