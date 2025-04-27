<?php

declare(strict_types=1);

namespace ACP;

use DI\Container;
use DI\NotFoundException;
use Jax\Config;
use Jax\Request;
use Jax\Session;
use Jax\User;

use function header;

/**
 * Admin control panel.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
final class App
{
    private array $nav = [
        'dropdowns' => '',
        'links' => '',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
        private readonly Page $page,
        private readonly Request $request,
        private readonly User $user,
        private readonly Session $session,
    ) {}

    public function render(): void
    {
        $adminUserId = $this->session->getPHPSessionValue('auid');
        if ($adminUserId) {
            $this->user->getUser($adminUserId);
        }

        if (!$this->user->getPerm('can_access_acp')) {
            header('Location: ./');

            return;
        }

        $this->page->append('username', (string) $this->user->get('display_name'));
        $this->page->append('title', $this->config->getSetting('boardname') . ' - ACP');
        $this->addNavMenu(
            'Settings',
            '?act=Settings',
            [
                '?act=Settings&do=birthday' => 'Birthdays',
                '?act=Settings&do=global' => 'Global Settings',
                '?act=Settings&do=pages' => 'Custom Pages',
                '?act=Settings&do=shoutbox' => 'Shoutbox',
            ],
        );
        $this->addNavMenu(
            'Members',
            '?act=Members',
            [
                '?act=Members&do=delete' => 'Delete Account',
                '?act=Members&do=edit' => 'Edit',
                '?act=Members&do=ipbans' => 'IP Bans',
                '?act=Members&do=massmessage' => 'Mass Message',
                '?act=Members&do=merge' => 'Account Merge',
                '?act=Members&do=prereg' => 'Pre-Register',
                '?act=Members&do=validation' => 'Validation',
            ],
        );
        $this->addNavMenu(
            'Groups',
            '?act=Groups',
            [
                '?act=Groups&do=create' => 'Create Group',
                '?act=Groups&do=delete' => 'Delete Groups',
                '?act=Groups&do=perms' => 'Edit Permissions',
            ],
        );
        $this->addNavMenu(
            'Themes',
            '?act=Themes',
            [
                '?act=Themes&do=create' => 'Create Skin',
                '?act=Themes' => 'Manage Skin(s)',
            ],
        );
        $this->addNavMenu(
            'Posting',
            '?act=Posting',
            [
                '?act=Posting&do=emoticons' => 'Emoticons',
                '?act=Posting&do=postrating' => 'Post Rating',
                '?act=Posting&do=wordfilter' => 'Word Filter',
            ],
        );
        $this->addNavMenu(
            'Forums',
            '?act=Forums',
            [
                '?act=Forums&do=create' => 'Create Forum',
                '?act=Forums&do=createc' => 'Create Category',
                '?act=Forums&do=order' => 'Manage',
                '?act=Forums&do=recountstats' => 'Recount Statistics',
            ],
        );
        $this->addNavMenu(
            'Tools',
            '?act=Tools',
            [
                '?act=Tools&do=backup' => 'Backup Forum',
                '?act=Tools&do=files' => 'File Manager',
                '?act=Tools&do=errorlog' => 'View Error Log',
            ],
        );
        $this->renderNav();

        $act = $this->request->get('act');

        if (is_string($act) && $act !== '') {
            try {
                $page = $this->container->get('ACP\Page\\' . $act);
                $page->render();
            } catch (NotFoundException) {
                $this->page->addContentBox('Error', "Invalid action: {$act}");
            }
        }



        $this->page->out();
    }

    /**
     * Creates a nav menu in the ACP.
     *
     * @param string $title The name of the button
     * @param string $page  The URL the button links to
     * @param array  $menu  A list of links and associated labels to print
     *                      out as a drop down list
     */
    public function addNavmenu(string $title, string $page, array $menu): void
    {
        $this->nav['links'] .= $this->page->parseTemplate(
            'nav-link.html',
            [
                'class' => mb_strtolower($title),
                'page' => $page,
                'title' => $title,
            ],
        );

        $dropdownLinks = '';
        foreach ($menu as $menuURL => $menuTitle) {
            $dropdownLinks .= $this->page->parseTemplate(
                'nav-dropdown-link.html',
                [
                    'title' => $menuTitle,
                    'url' => $menuURL,
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

    private function renderNav() {
        $this->page->append('nav',
            $this->page->parseTemplate(
                'nav.html',
                [
                    'nav' => $this->nav['links'],
                    'nav_dropdowns' => $this->nav['dropdowns'],
                ],
            )
        );
    }
}
