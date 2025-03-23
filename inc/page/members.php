<?php

$PAGE->loadmeta('members');
new members();
final class members
{
    /**
     * @var float|int
     */
    public $page = 0;

    public $perpage = 20;

    public function __construct()
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
            <<<EOT
                SELECT m.`id` AS `id`,m.`name` AS `name`,m.`email` AS `email`,m.`sig` AS `sig`,
                    m.`posts` AS `posts`,m.`group_id` AS `group_id`,m.`avatar` AS `avatar`,
                    m.`usertitle` AS `usertitle`,UNIX_TIMESTAMP(m.`join_date`) AS `join_date`,
                    UNIX_TIMESTAMP(m.`last_visit`) AS `last_visit`,
                    m.`contact_skype` AS `contact_skype`,m.`contact_yim` AS `contact_yim`,
                    m.`contact_msn` AS `contact_msn`,m.`contact_gtalk` AS `contact_googlechat`,
                    m.`contact_aim` AS `contact_AIM`,m.`website` AS `website`,
                    m.`birthdate` AS `birthdate`,
                    DAY(m.`birthdate`) AS `dob_day`,MONTH(m.`birthdate`) AS `dob_month`,
                    YEAR(m.`birthdate`) AS `dob_year`,m.`about` AS `about`,
                    m.`display_name` AS `display_name`,m.`full_name` AS `full_name`,
                    m.`contact_steam` AS `contact_Steam`,m.`location` AS `location`,
                    m.`gender` AS `gender`,m.`friends` AS `friends`,m.`enemies` AS `enemies`,
                    m.`sound_shout` AS `sound_shout`,m.`sound_im` AS `sound_im`,
                    m.`sound_pm` AS `sound_pm`,m.`sound_postinmytopic` AS `sound_postinmytopic`,
                    m.`sound_postinsubscribedtopic` AS `sound_postinsubscribedtopic`,
                    m.`notify_pm` AS `notify_pm`,
                    m.`notify_postinmytopic` AS `notify_postinmytopic`,
                    m.`notify_postinsubscribedtopic` AS `notify_postinsubscribedtopic`,
                    m.`ucpnotepad` AS `ucpnotepad`,m.`skin_id` AS `skin_id`,
                    m.`contact_twitter` AS `contact_twitter`,
                    m.`contact_discord` AS `contact_discord`,
                    m.`contact_youtube` AS `contact_youTube`,
                    m.`contact_bluesky` AS `contact_bluesky`,
                    m.`email_settings` AS `email_settings`,m.`nowordfilter` AS `nowordfilter`,
                    INET6_NTOA(m.`ip`) AS `ip`,m.`mod` AS `mod`,m.`wysiwyg` AS `wysiwyg`,
                    g.`title` AS `g_title`
                FROM %t m
                LEFT JOIN %t g
                    ON g.id=m.group_id
                {$where}
                ORDER BY {$sortby} {$sorthow}
                LIMIT ?, ?
                EOT,
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

        foreach ($memberarray as $f) {
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
                if (!$f['contact_' . $k]) {
                    continue;
                }

                $contactdetails .= '<a class="' . $k . ' contact" href="'
                    . sprintf($v, $JAX->blockhtml($f['contact_' . $k]))
                    . '" title="' . $k . ' contact">&nbsp;</a>';
            }

            $contactdetails .= '<a title="PM this member" class="pm contact" '
                . 'href="?act=ucp&amp;what=inbox&amp;page=compose&amp;mid='
                . $f['id'] . '"></a>';
            $page .= $PAGE->meta(
                'members-row',
                $f['id'],
                $JAX->pick($f['avatar'], $PAGE->meta('default-avatar')),
                $PAGE->meta(
                    'user-link',
                    $f['id'],
                    $f['group_id'],
                    $f['display_name'],
                ),
                $f['g_title'],
                $f['id'],
                $f['posts'],
                $JAX->date($f['join_date']),
                $contactdetails,
            );
        }

        $page = $PAGE->meta(
            'members-table',
            $links[0],
            $links[1],
            $links[2],
            $links[3],
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
