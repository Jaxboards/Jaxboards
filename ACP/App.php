<?php

declare(strict_types=1);

namespace ACP;

use DI\Container;
use Exception;
use Jax\Config;
use Jax\DebugLog;
use Jax\IPAddress;
use Jax\Request;
use Jax\Session;
use Jax\User;

use function header;
use function implode;
use function is_string;
use function mb_strtolower;

/**
 * Admin control panel.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
final class App
{
    /**
     * @var array<string,string>
     */
    private array $nav = [
        'dropdowns' => '',
        'links' => '',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
        private readonly DebugLog $debugLog,
        private readonly IPAddress $ipAddress,
        private readonly Page $page,
        private readonly Request $request,
        private readonly User $user,
        private readonly Session $session,
    ) {}

    public function render(): void
    {
        $this->ensureACPAccess();

        $this->page->append('username', (string) $this->user->get('display_name'));
        $this->page->append('title', $this->config->getSetting('boardname') . ' - ACP');

        $this->renderNav();

        $act = $this->request->get('act');

        if (is_string($act) && $act !== '') {
            try {
                $page = $this->container->get('ACP\Page\\' . $act);
                $page->render();
            } catch (Exception) {
                $this->page->addContentBox('Error', "Invalid action: {$act}");
            }
        }

        if ($this->ipAddress->isLocalHost()) {
            $this->page->addContentBox('Debug', implode('<br>', $this->debugLog->getLog()));
        }

        $this->page->out();
    }

    /**
     * Creates a nav menu in the ACP.
     *
     * @param string               $title The name of the button
     * @param array<string,string> $menu  A list of links and associated labels to print
     *                                    out as a drop down list
     */
    public function addNavmenu(string $title, array $menu): void
    {
        $url = "?act={$title}";

        $this->nav['links'] .= $this->page->parseTemplate(
            'nav-link.html',
            [
                'class' => mb_strtolower($title),
                'page' => $url,
                'title' => $title,
            ],
        );

        $dropdownLinks = '';
        foreach ($menu as $do => $menuTitle) {
            $dropdownLinks .= $this->page->parseTemplate(
                'nav-dropdown-link.html',
                [
                    'title' => $menuTitle,
                    'url' => "{$url}&do={$do}",
                ],
            );
        }

        $this->nav['dropdowns'] .= $this->page->parseTemplate(
            'nav-dropdown.html',
            [
                'dropdown_id' => 'menu_' . mb_strtolower($title),
                'dropdown_links' => $dropdownLinks,
            ],
        );
    }

    private function ensureACPAccess(): void
    {
        $adminUserId = $this->session->getPHPSessionValue('auid');
        if ($adminUserId) {
            $this->user->getUser($adminUserId);
        }

        if (!$this->user->getPerm('can_access_acp')) {
            header('Location: ./');

            return;
        }
    }

    private function renderNav(): void
    {
        $this->addNavMenu(
            'Settings',
            [
                'global' => 'Global Settings',
                'pages' => 'Custom Pages',
                'shoutbox' => 'Shoutbox',
            ],
        );
        $this->addNavMenu(
            'Members',
            [
                'delete' => 'Delete Account',
                'edit' => 'Edit',
                'ipbans' => 'IP Bans',
                'massmessage' => 'Mass Message',
                'merge' => 'Account Merge',
                'prereg' => 'Pre-Register',
                'validation' => 'Validation',
            ],
        );
        $this->addNavMenu(
            'Groups',
            [
                'create' => 'Create Group',
                'delete' => 'Delete Groups',
                'perms' => 'Edit Permissions',
            ],
        );
        $this->addNavMenu(
            'Themes',
            [
                'create' => 'Create Skin',
                'manage' => 'Manage Skin(s)',
            ],
        );
        $this->addNavMenu(
            'Posting',
            [
                'emoticons' => 'Emoticons',
                'postrating' => 'Post Rating',
                'wordfilter' => 'Word Filter',
            ],
        );
        $this->addNavMenu(
            'Forums',
            [
                'create' => 'Create Forum',
                'createc' => 'Create Category',
                'order' => 'Manage',
                'recountstats' => 'Recount Statistics',
            ],
        );
        $this->addNavMenu(
            'Tools',
            [
                'backup' => 'Backup Forum',
                'files' => 'File Manager',
                'viewErrorLog' => 'View Error Log',
            ],
        );

        $this->page->append(
            'nav',
            $this->page->parseTemplate('nav.html', $this->nav),
        );
    }
}
