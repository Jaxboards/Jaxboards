<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\ContactDetails;
use Jax\Database;
use Jax\Date;
use Jax\Jax;
use Jax\Models\Group;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\Template;

use function _\keyBy;
use function array_key_exists;
use function ceil;

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
            'group_id' => 'Group',
            'id' => 'ID',
            'posts' => 'Posts',
            'join_date' => 'Join Date',
        ];

        $page = '';

        $sorthow = $this->request->asString->both('how') === 'DESC'
            ? 'DESC' : 'ASC';
        $where = '';
        $sortByInput = $this->request->asString->both('sortby');
        $sortby = $sortByInput !== null && array_key_exists($sortByInput, $fields)
            ? $sortByInput
            : 'display_name';

        $filter = $this->request->asString->get('filter');
        if ($filter === 'staff') {
            $sortby = 'g.`can_access_acp` DESC ,' . $sortby;
            $where = 'WHERE g.`can_access_acp`=1 OR g.`can_moderate`=1';
        }

        $pages = '';

        $members = Member::selectMany(
            $this->database,
            "{$where}
            ORDER BY {$sortby} {$sorthow}
            LIMIT ?, ?",
            $this->pageNumber * $this->perpage,
            $this->perpage,
        );

        $groups = Group::joinedOn(
            $this->database,
            $members,
            static fn($group) => $group->id
        );

        $nummemberquery = $this->database->special(
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
            . ($this->pageNumber !== 0 ? '&page=' . ($this->pageNumber + 1) : '')
            . ($filter ? '&filter=' . $filter : '');

        $links = [];
        foreach ($fields as $field => $fieldLabel) {
            $links[] = "<a href=\"{$url}&amp;sortby={$field}"
            . ($sortby === $field ? ($sorthow === 'ASC' ? '&amp;how=DESC' : '')
                . '" class="sort' . ($sorthow === 'DESC' ? ' desc' : '') : '')
                . "\">{$fieldLabel}</a>";
        }

        foreach ($members as $member) {
            $contactdetails = '';
            foreach ($this->contactDetails->getContactLinks($member) as $service => [$href]) {
                $contactdetails .= <<<HTML
                    <a class="{$service} contact" href="{$href}" title="{$service} contact">&nbsp;</a>
                    HTML;
            }

            $contactdetails .= '<a title="PM this member" class="pm contact" '
                . 'href="?act=ucp&amp;what=inbox&amp;view=compose&amp;mid='
                . $member->id . '"></a>';
            $page .= $this->template->meta(
                'members-row',
                $member->id,
                $member->avatar ?: $this->template->meta('default-avatar'),
                $this->template->meta(
                    'user-link',
                    $member->id,
                    $member->group_id,
                    $member->display_name,
                ),
                $groups[$member->group_id]->title,
                $member->id,
                $member->posts,
                $this->date->autoDate($member->join_date),
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
        $page = "<div class='pages pages-top'>{$pages}</div><div class='forum-pages-top'>&nbsp;</div>"
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
