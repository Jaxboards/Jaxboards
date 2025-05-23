<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\ContactDetails;
use Jax\Database;
use Jax\Date;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Template;

use function ceil;
use function is_numeric;

final class Members
{
    private int $pageNumber = 0;

    private int $perpage = 20;

    public function __construct(
        private readonly ContactDetails $contactDetails,
        private readonly Database $database,
        private readonly Date $date,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Template $template,
        private readonly Request $request,
    ) {
        $this->template->loadMeta('members');
    }

    public function render(): void
    {
        $page = (int) $this->request->asString->both('page');
        if ($page > 0) {
            $this->pageNumber = $page - 1;
        }

        if ($this->request->isJSUpdate()) {
            return;
        }

        $this->showmemberlist();
    }

    private function showmemberlist(): void
    {
        $fields = [
            'display_name' => 'Name',
            'g_title' => 'Group',
            'id' => 'ID',
            'posts' => 'Posts',
            'join_date' => 'Join Date',
        ];

        $page = '';

        $sortby = 'm.`display_name`';
        $sorthow = $this->request->asString->both('how') === 'DESC'
            ? 'DESC' : 'ASC';
        $where = '';
        $sortByInput = $this->request->asString->both('sortby');
        if (
            $sortByInput !== null
            && array_key_exists($sortByInput, $fields, true)
        ) {
            $sortby = $sortByInput;
        }

        $filter = $this->request->asString->get('filter');
        if ($filter === 'staff') {
            $sortby = 'g.`can_access_acp` DESC ,' . $sortby;
            $where = 'WHERE g.`can_access_acp`=1 OR g.`can_moderate`=1';
        }

        $pages = '';

        $memberquery = $this->database->safespecial(
            <<<MySQL
                    SELECT
                        g.`title` AS `g_title`,
                        m.`avatar` AS `avatar`,
                        m.`contact_aim` AS `contact_aim`,
                        m.`contact_bluesky` AS `contact_bluesky`,
                        m.`contact_discord` AS `contact_discord`,
                        m.`contact_gtalk` AS `contact_googlechat`,
                        m.`contact_msn` AS `contact_msn`,
                        m.`contact_skype` AS `contact_skype`,
                        m.`contact_steam` AS `contact_steam`,
                        m.`contact_twitter` AS `contact_twitter`,
                        m.`contact_yim` AS `contact_yim`,
                        m.`contact_youtube` AS `contact_youtube`,
                        m.`display_name` AS `display_name`,
                        m.`group_id` AS `group_id`,
                        m.`id` AS `id`,
                        m.`posts` AS `posts`,
                        UNIX_TIMESTAMP(m.`join_date`) AS `join_date`
                    FROM %t m
                    LEFT JOIN %t g
                        ON g.id=m.group_id
                    {$where}
                    ORDER BY {$sortby} {$sorthow}
                    LIMIT ?, ?
                MySQL,
            ['members', 'member_groups'],
            $this->pageNumber * $this->perpage,
            $this->perpage,
        );

        $memberarray = $this->database->arows($memberquery);

        $nummemberquery = $this->database->safespecial(
            <<<EOT
                SELECT COUNT(m.`id`) AS `num_members`
                FROM %t m
                LEFT JOIN %t g
                    ON g.id=m.group_id
                {$where}

                EOT,
            ['members', 'member_groups'],
        );
        $thisrow = $this->database->arow($nummemberquery);
        $nummembers = $thisrow['num_members'] ?? 0;

        $pageNumbers = $this->jax->pages(
            (int) ceil($nummembers / $this->perpage),
            $this->pageNumber + 1,
            $this->perpage,
        );
        foreach ($pageNumbers as $pageNumber) {
            $pages .= "<a href='?act=members&amp;sortby="
                . "{$sortby}&amp;how={$sorthow}&amp;page={$pageNumber}'"
                . ($pageNumber - 1 === $this->pageNumber ? ' class="active"' : '') . ">{$pageNumber}</a> ";
        }

        $url = '?act=members'
            . ($this->pageNumber ? '&page=' . ($this->pageNumber + 1) : '')
            . ($filter ? '&filter=' . $filter : '');

        $links = [];
        foreach ($fields as $field => $fieldLabel) {
            $links[] = "<a href=\"{$url}&amp;sortby={$field}"
            . ($sortby === $field ? ($sorthow === 'ASC' ? '&amp;how=DESC' : '')
                . '" class="sort' . ($sorthow === 'DESC' ? ' desc' : '') : '')
                . "\">{$fieldLabel}</a>";
        }

        foreach ($memberarray as $member) {
            $contactdetails = '';
            foreach ($this->contactDetails->getContactLinks($member) as $service => [$href]) {
                $contactdetails .= <<<HTML
                    <a class="{$service} contact" href="{$href}" title="{$service} contact">&nbsp;</a>
                    HTML;
            }

            $contactdetails .= '<a title="PM this member" class="pm contact" '
                . 'href="?act=ucp&amp;what=inbox&amp;view=compose&amp;mid='
                . $member['id'] . '"></a>';
            $page .= $this->template->meta(
                'members-row',
                $member['id'],
                $member['avatar'] ?: $this->template->meta('default-avatar'),
                $this->template->meta(
                    'user-link',
                    $member['id'],
                    $member['group_id'],
                    $member['display_name'],
                ),
                $member['g_title'],
                $member['id'],
                $member['posts'],
                $this->date->autoDate($member['join_date']),
                $contactdetails,
            );
        }

        $page = $this->template->meta(
            'members-table',
            $links[0],
            $links[1],
            $links[2],
            $links[3],
            $links[4],
            $page,
        );
        $page = "<div class='pages pages-top'>{$pages}</div>"
            . $this->template->meta(
                'box',
                ' id="memberlist"',
                'Members',
                $page,
            )
            . "<div class='pages pages-bottom'>{$pages}</div>"
            . "<div class='clear'></div>";
        $this->page->command('update', 'page', $page);
        $this->page->append('PAGE', $page);
    }
}
