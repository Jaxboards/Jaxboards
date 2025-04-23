<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Jax;
use Jax\Page;

use function ceil;
use function is_numeric;
use function sprintf;

final class Members
{
    /**
     * @var float|int
     */
    public $pageNumber = 0;

    public $perpage = 20;

    public function __construct(
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
    ) {
        $this->page->loadmeta('members');
    }

    public function route(): void
    {
        if (
            isset($this->jax->b['page'])
            && is_numeric($this->jax->b['page'])
            && $this->jax->b['page'] > 0
        ) {
            $this->pageNumber = $this->jax->b['page'] - 1;
        }

        if ($this->page->jsupdate) {
            return;
        }

        $this->showmemberlist();
    }

    public function showmemberlist(): void
    {
        $vars = [
            'display_name' => 'Name',
            'g_title' => 'Group',
            'id' => 'ID',
            'posts' => 'Posts',
            'join_date' => 'Join Date',
        ];

        $page = '';

        $sortby = 'm.`display_name`';
        $sorthow = isset($this->jax->b['how']) && $this->jax->b['how'] === 'DESC'
            ? 'DESC' : 'ASC';
        $where = '';
        if (
            isset($this->jax->b['sortby'], $vars[$this->jax->b['sortby']])
            && $vars[$this->jax->b['sortby']]
        ) {
            $sortby = $this->jax->b['sortby'];
        }

        if (
            isset($this->jax->g['filter'])
            && $this->jax->g['filter'] === 'staff'
        ) {
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
        $nummembers = $thisrow['num_members'];

        $pagesArray = $this->jax->pages(
            ceil($nummembers / $this->perpage),
            $this->pageNumber + 1,
            $this->perpage,
        );
        foreach ($pagesArray as $v) {
            $pages .= "<a href='?act=members&amp;sortby="
                . "{$sortby}&amp;how={$sorthow}&amp;page={$v}'"
                . ($v - 1 === $this->pageNumber ? ' class="active"' : '') . ">{$v}</a> ";
        }

        $url = '?act=members'
            . ($this->pageNumber ? '&page=' . ($this->pageNumber + 1) : '')
            . (isset($this->jax->g['filter']) && $this->jax->g['filter']
            ? '&filter=' . $this->jax->g['filter'] : '');

        $links = [];
        foreach ($vars as $k => $v) {
            $links[] = "<a href=\"{$url}&amp;sortby={$k}"
            . ($sortby === $k ? ($sorthow === 'ASC' ? '&amp;how=DESC' : '')
                . '" class="sort' . ($sorthow === 'DESC' ? ' desc' : '') : '')
                . "\">{$v}</a>";
        }

        foreach ($memberarray as $member) {
            $contactdetails = '';
            $contactUrls = [
                'aim' => 'aim:goaim?screenname=%s',
                'bluesky' => 'https://bsky.app/profile/%s.bsky.social',
                'discord' => 'discord:%s',
                'googlechat' => 'gtalk:chat?jid=%s',
                'msn' => 'msnim:chat?contact=%s',
                'skype' => 'skype:%s',
                'steam' => 'https://steamcommunity.com/id/%s',
                'twitter' => 'https://twitter.com/%s',
                'yim' => 'ymsgr:sendim?%s',
                'youtube' => 'https://youtube.com/%s',
            ];
            foreach ($contactUrls as $k => $v) {
                if (!$member['contact_' . $k]) {
                    continue;
                }

                $contactdetails .= '<a class="' . $k . ' contact" href="'
                    . sprintf($v, $this->jax->blockhtml($member['contact_' . $k]))
                    . '" title="' . $k . ' contact">&nbsp;</a>';
            }

            $contactdetails .= '<a title="PM this member" class="pm contact" '
                . 'href="?act=ucp&amp;what=inbox&amp;page=compose&amp;mid='
                . $member['id'] . '"></a>';
            $page .= $this->page->meta(
                'members-row',
                $member['id'],
                $this->jax->pick($member['avatar'], $this->page->meta('default-avatar')),
                $this->page->meta(
                    'user-link',
                    $member['id'],
                    $member['group_id'],
                    $member['display_name'],
                ),
                $member['g_title'],
                $member['id'],
                $member['posts'],
                $this->jax->date($member['join_date']),
                $contactdetails,
            );
        }

        $page = $this->page->meta(
            'members-table',
            $links[0],
            $links[1],
            $links[2],
            $links[3],
            $links[4],
            $page,
        );
        $page = "<div class='pages pages-top'>{$pages}</div>"
            . $this->page->meta(
                'box',
                ' id="memberlist"',
                'Members',
                $page,
            )
            . "<div class='pages pages-bottom'>{$pages}</div>"
            . "<div class='clear'></div>";
        $this->page->JS('update', 'page', $page);
        $this->page->append('PAGE', $page);
    }
}
