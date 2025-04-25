<?php

declare(strict_types=1);

namespace ACP;

use DI\Container;
use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\User;

use function file_exists;
use function header;
use function ini_set;
use function session_start;

/**
 * Admin control panel.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
final class App
{
    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly User $user,
    ) {}

    public function render(): void
    {
        $this->startSession();

        $this->connectDB();

        if (isset($_SESSION['auid'])) {
            $userData = $this->user->getUser($_SESSION['auid']);
        }

        if (!$this->user->getPerm('can_access_acp')) {
            header('Location: ./');

            exit;
        }

        $this->page->append('username', $this->user->get('display_name'));
        $this->page->title($this->config->getSetting('boardname') . ' - ACP');
        $this->page->addNavMenu(
            'Settings',
            '?act=settings',
            [
                '?act=settings&do=birthday' => 'Birthdays',
                '?act=settings&do=global' => 'Global Settings',
                '?act=settings&do=pages' => 'Custom Pages',
                '?act=settings&do=shoutbox' => 'Shoutbox',
            ],
        );
        $this->page->addNavMenu(
            'Members',
            '?act=members',
            [
                '?act=members&do=delete' => 'Delete Account',
                '?act=members&do=edit' => 'Edit',
                '?act=members&do=ipbans' => 'IP Bans',
                '?act=members&do=massmessage' => 'Mass Message',
                '?act=members&do=merge' => 'Account Merge',
                '?act=members&do=prereg' => 'Pre-Register',
                '?act=members&do=validation' => 'Validation',
            ],
        );
        $this->page->addNavMenu(
            'Groups',
            '?act=groups',
            [
                '?act=groups&do=create' => 'Create Group',
                '?act=groups&do=delete' => 'Delete Groups',
                '?act=groups&do=perms' => 'Edit Permissions',
            ],
        );
        $this->page->addNavMenu(
            'Themes',
            '?act=themes',
            [
                '?act=themes&do=create' => 'Create Skin',
                '?act=themes' => 'Manage Skin(s)',
            ],
        );
        $this->page->addNavMenu(
            'Posting',
            '?act=posting',
            [
                '?act=posting&do=emoticons' => 'Emoticons',
                '?act=posting&do=postrating' => 'Post Rating',
                '?act=posting&do=wordfilter' => 'Word Filter',
            ],
        );
        $this->page->addNavMenu(
            'Forums',
            '?act=forums',
            [
                '?act=forums&do=create' => 'Create Forum',
                '?act=forums&do=createc' => 'Create Category',
                '?act=forums&do=order' => 'Manage',
                '?act=forums&do=recountstats' => 'Recount Statistics',
            ],
        );
        $this->page->addNavMenu(
            'Tools',
            '?act=tools',
            [
                '?act=tools&do=backup' => 'Backup Forum',
                '?act=tools&do=files' => 'File Manager',
                '?act=tools&do=errorlog' => 'View Error Log',
            ],
        );

        $act = $this->jax->g['act'] ?? null;

        if ($act && file_exists("./page/{$act}.php")) {
            $page = $this->container->get('ACP\Page\\' . $act);
            $page->render();
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

    private function connectDB(): void
    {
        $this->database->connect(
            $this->config->getSetting('sql_host'),
            $this->config->getSetting('sql_username'),
            $this->config->getSetting('sql_password'),
            $this->config->getSetting('sql_db'),
            $this->config->getSetting('sql_prefix'),
        );
    }
}
