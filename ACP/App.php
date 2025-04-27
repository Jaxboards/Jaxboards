<?php

declare(strict_types=1);

namespace ACP;

use DI\Container;
use Exception;
use Jax\Config;
use Jax\Request;
use Jax\Session;
use Jax\User;

use function header;
use function ini_set;
use function session_start;

/*
 * Admin control panel.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
final readonly class App
{
    public function __construct(
        private Config $config,
        private Container $container,
        private Page $page,
        private Request $request,
        private User $user,
        private Session $session,
    ) {}

    public function render(): void
    {
        $adminUserId = $this->session->getPHPSessionValue('auid');
        if ($adminUserId) {
            $this->user->getUser($adminUserId);
        }

        if (!$this->user->getPerm('can_access_acp')) {
            header('Location: ./');

            exit;
        }

        $this->page->append('username', $this->user->get('display_name'));
        $this->page->title($this->config->getSetting('boardname') . ' - ACP');
        $this->page->addNavMenu(
            'Settings',
            '?act=Settings',
            [
                '?act=Settings&do=birthday' => 'Birthdays',
                '?act=Settings&do=global' => 'Global Settings',
                '?act=Settings&do=pages' => 'Custom Pages',
                '?act=Settings&do=shoutbox' => 'Shoutbox',
            ],
        );
        $this->page->addNavMenu(
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
        $this->page->addNavMenu(
            'Groups',
            '?act=Groups',
            [
                '?act=Groups&do=create' => 'Create Group',
                '?act=Groups&do=delete' => 'Delete Groups',
                '?act=Groups&do=perms' => 'Edit Permissions',
            ],
        );
        $this->page->addNavMenu(
            'Themes',
            '?act=Themes',
            [
                '?act=Themes&do=create' => 'Create Skin',
                '?act=Themes' => 'Manage Skin(s)',
            ],
        );
        $this->page->addNavMenu(
            'Posting',
            '?act=Posting',
            [
                '?act=Posting&do=emoticons' => 'Emoticons',
                '?act=Posting&do=postrating' => 'Post Rating',
                '?act=Posting&do=wordfilter' => 'Word Filter',
            ],
        );
        $this->page->addNavMenu(
            'Forums',
            '?act=Forums',
            [
                '?act=Forums&do=create' => 'Create Forum',
                '?act=Forums&do=createc' => 'Create Category',
                '?act=Forums&do=order' => 'Manage',
                '?act=Forums&do=recountstats' => 'Recount Statistics',
            ],
        );
        $this->page->addNavMenu(
            'Tools',
            '?act=Tools',
            [
                '?act=Tools&do=backup' => 'Backup Forum',
                '?act=Tools&do=files' => 'File Manager',
                '?act=Tools&do=errorlog' => 'View Error Log',
            ],
        );

        $act = $this->request->get('act');

        if ($act) {
            try {
                $page = $this->container->get('ACP\Page\\' . $act);
                $page->render();
            } catch(\DI\NotFoundException) {
                $this->page->addContentBox('Error', "Invalid action: {$act}");
            }
        }



        $this->page->out();
    }

    private function startSession(): void
    {
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_cookies', 1);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
}
