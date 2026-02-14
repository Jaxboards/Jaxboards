<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\ContactDetails;
use Jax\Database\Database;
use Jax\Interfaces\Route;
use Jax\Jax;
use Jax\Lodash;
use Jax\Models\Group;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Template;
use Override;

use function array_filter;
use function array_key_exists;
use function array_map;
use function ceil;
use function implode;

final class Members implements Route
{
    private int $pageNumber = 0;

    private int $perpage = 20;

    public function __construct(
        private readonly ContactDetails $contactDetails,
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Router $router,
        private readonly Template $template,
        private readonly Request $request,
    ) {}

    #[Override]
    public function route($params): void
    {
        $page = (int) $this->request->asString->both('page');
        if ($page > 0) {
            $this->pageNumber = $page - 1;
        }

        if ($this->request->isJSUpdate()) {
            return;
        }

        $this->showMemberList();
    }

    private function showMemberList(): void
    {
        $this->page->setBreadCrumbs([
            $this->router->url('members') => 'Members',
        ]);

        $fields = [
            'displayName' => 'Name',
            'groupID' => 'Group',
            'id' => 'ID',
            'posts' => 'Posts',
            'joinDate' => 'Join Date',
        ];

        $page = '';

        $sorthow = $this->request->asString->both('how') === 'DESC' ? 'DESC' : 'ASC';
        $sortByInput = $this->request->asString->both('sortby');
        $sortby = $sortByInput !== null && array_key_exists($sortByInput, $fields) ? $sortByInput : 'displayName';

        $filter = $this->request->asString->get('filter');

        // fetch all groups
        $groups = Lodash::keyBy(Group::selectMany(), static fn(Group $group): int => $group->id);

        $staffGroupIds = implode(',', array_map(
            static fn(Group $group): int => $group->id,
            array_filter(
                $groups,
                static fn(Group $group): bool => $group->canAccessACP === 1 || $group->canModerate === 1,
            ),
        ));
        $where = $filter === 'staff' ? "WHERE groupID IN ({$staffGroupIds})" : '';

        $pages = '';

        $members = Member::selectMany(
            $where
            . "ORDER BY {$sortby} {$sorthow}
            LIMIT ?, ?",
            $this->pageNumber * $this->perpage,
            $this->perpage,
        );

        $numMemberQuery = $this->database->special(<<<EOT
            SELECT COUNT(m.`id`) AS `num_members`
            FROM %t m
            LEFT JOIN %t g
                ON g.id=m.groupID
            {$where}

            EOT, ['members', 'member_groups']);
        $thisrow = $this->database->arow($numMemberQuery);
        $nummembers = $thisrow['num_members'] ?? 0;

        $pageNumbers = $this->jax->pages(
            (int) ceil($nummembers / $this->perpage),
            $this->pageNumber + 1,
            $this->perpage,
        );
        foreach ($pageNumbers as $pageNumber) {
            $pageURL = $this->router->url('members', [
                'sortby' => $sortby,
                'how' => $sorthow,
                'page' => $pageNumber,
            ]);
            $pages .=
                "<a href='{$pageURL}'"
                . (($pageNumber - 1) === $this->pageNumber ? ' class="active"' : '')
                . ">{$pageNumber}</a> ";
        }

        $links = [];
        foreach ($fields as $field => $fieldLabel) {
            $url = $this->router->url('members', [
                'page' => $this->pageNumber + 1,
                'filter' => $filter,
                'sortby' => $field,
                'how' => $sortby === $field && $sorthow === 'ASC' ? 'DESC' : 'ASC',
            ]);
            $links[] =
                "<a href='{$url}'"
                . ($sortby === $field ? "class='sort" . ($sorthow === 'DESC' ? ' desc' : '') . "'" : '')
                . ">{$fieldLabel}</a>";
        }

        $rows = array_map(fn(Member $member): array => [
            'member' => $member,
            'group' => $groups[$member->groupID],
            'contactDetails' => $this->contactDetails->getContactLinks($member),
        ], $members);

        $page = $this->template->render('members/table', [
            'links' => $links,
            'rows' => $rows,
        ]);
        $page =
            "<div class='pages pages-top'>{$pages}</div><div class='forum-pages-top'>&nbsp;</div>"
            . $this->template->render('global/box', [
                'boxID' => 'memberlist',
                'title' => 'Members',
                'content' => $page,
            ])
            . "<div class='pages pages-bottom'>{$pages}</div>"
            . "<div class='clear'></div>";
        $this->page->command('update', 'page', $page);
        $this->page->append('PAGE', $page);
    }
}
