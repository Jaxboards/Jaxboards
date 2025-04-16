<?php

declare(strict_types=1);

$PAGE->loadmeta('members');

final class Members
{
    /**
     * @var float|int
     */
    public $page = 0;

    public $perpage = 20;

    public function route(): void
    {
        global $JAX,$PAGE;
        if (
            isset($JAX->b['page'])
            && is_numeric($JAX->b['page'])
            && $JAX->b['page'] > 0
        ) {
            $this->page = $JAX->b['page'] - 1;
        }

        if ($PAGE->jsupdate) {
            return;
        }

        $this->showmemberlist();
    }

    public function showmemberlist(): void
    {
        global $PAGE,$DB,$JAX;
        $vars = [
            'display_name' => 'Name',
            'g_title' => 'Group',
            'id' => 'ID',
            'posts' => 'Posts',
            'join_date' => 'Join Date',
        ];

        $page = '';

        $sortby = 'm.`display_name`';
        $sorthow = isset($JAX->b['how']) && $JAX->b['how'] === 'DESC'
            ? 'DESC' : 'ASC';
        $where = '';
        if (
            isset($JAX->b['sortby'], $vars[$JAX->b['sortby']])
            && $vars[$JAX->b['sortby']]
        ) {
            $sortby = $JAX->b['sortby'];
        }

        if (isset($JAX->g['filter']) && $JAX->g['filter'] === 'staff') {
            $sortby = 'g.`can_access_acp` DESC ,' . $sortby;
            $where = 'WHERE g.`can_access_acp`=1 OR g.`can_moderate`=1';
        }

        $pages = '';

        $memberquery = $DB->safespecial(
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
            $this->page * $this->perpage,
            $this->perpage,
        );

        $memberarray = $DB->arows($memberquery);

        $nummemberquery = $DB->safespecial(
            <<<EOT
                SELECT COUNT(m.`id`) AS `num_members`
                FROM %t m
                LEFT JOIN %t g
                    ON g.id=m.group_id
                {$where}

                EOT,
            ['members', 'member_groups'],
        );
        $thisrow = $DB->arow($nummemberquery);
        $nummembers = $thisrow['num_members'];

        $pagesArray = $JAX->pages(
            ceil($nummembers / $this->perpage),
            $this->page + 1,
            $this->perpage,
        );
        foreach ($pagesArray as $v) {
            $pages .= "<a href='?act=members&amp;sortby="
                . "{$sortby}&amp;how={$sorthow}&amp;page={$v}'"
                . ($v - 1 === $this->page ? ' class="active"' : '') . ">{$v}</a> ";
        }

        $url = '?act=members'
            . ($this->page ? '&page=' . ($this->page + 1) : '')
            . (isset($JAX->g['filter']) && $JAX->g['filter']
            ? '&filter=' . $JAX->g['filter'] : '');

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
                    . sprintf($v, $JAX->blockhtml($member['contact_' . $k]))
                    . '" title="' . $k . ' contact">&nbsp;</a>';
            }

            $contactdetails .= '<a title="PM this member" class="pm contact" '
                . 'href="?act=ucp&amp;what=inbox&amp;page=compose&amp;mid='
                . $member['id'] . '"></a>';
            $page .= $PAGE->meta(
                'members-row',
                $member['id'],
                $JAX->pick($member['avatar'], $PAGE->meta('default-avatar')),
                $PAGE->meta(
                    'user-link',
                    $member['id'],
                    $member['group_id'],
                    $member['display_name'],
                ),
                $member['g_title'],
                $member['id'],
                $member['posts'],
                $JAX->date($member['join_date']),
                $contactdetails,
            );
        }

        $page = $PAGE->meta(
            'members-table',
            $links[0],
            $links[1],
            $links[2],
            $links[3],
            $links[4],
            $page,
        );
        $page = "<div class='pages pages-top'>{$pages}</div>"
            . $PAGE->meta(
                'box',
                ' id="memberlist"',
                'Members',
                $page,
            )
            . "<div class='pages pages-bottom'>{$pages}</div>"
            . "<div class='clear'></div>";
        $PAGE->JS('update', 'page', $page);
        $PAGE->append('PAGE', $page);
    }
}
