<?php

declare(strict_types=1);

namespace ACP;

final class Nav
{
    /**
     * Map of category titles to menu links
     * @var array<string,array<string,string>>
     */
    private array $categories = [
        'Settings' => [
            'global' => 'Global Settings',
            'pages' => 'Custom Pages',
            'shoutbox' => 'Shoutbox',
            'badges' => 'Badges',
            'webhooks' => 'Webhooks',
        ],
        'Members' => [
            'delete' => 'Delete Account',
            'edit' => 'Edit',
            'ipbans' => 'IP Bans',
            'massmessage' => 'Mass Message',
            'merge' => 'Account Merge',
            'prereg' => 'Pre-Register',
            'validation' => 'Validation',
        ],
        'Groups' => [
            'create' => 'Create Group',
            'delete' => 'Delete Groups',
            'perms' => 'Edit Permissions',
        ],
        'Themes' => [
            'create' => 'Create Skin',
            'manage' => 'Manage Skin(s)',
        ],
        'Posting' => [
            'emoticons' => 'Emoticons',
            'postRating' => 'Post Rating',
            'wordfilter' => 'Word Filter',
        ],
        'Forums' => [
            'create' => 'Create Forum',
            'createc' => 'Create Category',
            'order' => 'Manage',
            'recountstats' => 'Recount Statistics',
        ],
        'Tools' => [
            'backup' => 'Backup Forum',
            'files' => 'File Manager',
            'viewErrorLog' => 'View Error Log',
            'phpinfo' => 'View PHPInfo',
        ],
    ];

    public function __construct(
        private readonly Page $page,
    ) {}

    /**
     * @return array<string,string>
     */
    public function getMenu(string $category): array
    {
        return $this->categories[$category] ?? [];
    }

    public function render(): void
    {
        $this->page->append('nav', $this->page->render('nav.html', [
            'categories' => $this->categories,
        ]));
    }
}
