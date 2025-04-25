<?php

declare(strict_types=1);

namespace Jax\Modules;

use Jax\Config;
use Jax\Database;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Page;
use Jax\Session;
use Jax\TextFormatting;
use Jax\User;

use function array_pop;
use function ceil;
use function gmdate;
use function is_numeric;
use function mb_strlen;
use function mb_substr;
use function trim;

use const PHP_EOL;

final class Shoutbox
{
    public const TAG = true;

    private $shoutlimit;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly IPAddress $ipAddress,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {
        $this->page->loadmeta('shoutbox');
    }

    public function init(): void
    {
        if (
            !$this->config->getSetting('shoutbox')
            || !$this->user->getPerm('can_view_shoutbox')
        ) {
            return;
        }

        $this->shoutlimit = $this->config->getSetting('shoutbox_num');
        if (
            isset($this->jax->b['shoutbox_delete'])
            && is_numeric($this->jax->b['shoutbox_delete'])
        ) {
            $this->deleteshout();
        } elseif (
            isset($this->jax->b['module'])
            && $this->jax->b['module'] === 'shoutbox'
        ) {
            $this->showallshouts();
        }

        if (
            isset($this->jax->p['shoutbox_shout'])
            && trim((string) $this->jax->p['shoutbox_shout']) !== ''
        ) {
            $this->addshout();
        }

        if (!$this->page->jsaccess) {
            $this->displayshoutbox();
        } else {
            $this->updateshoutbox();
        }
    }

    public function canDelete($id, $shoutrow = false): null|int|string|true
    {
        $candelete = $this->user->getPerm('can_delete_shouts');
        if (!$candelete && $this->user->getPerm('can_delete_own_shouts')) {
            if (!$shoutrow) {
                $result = $this->database->safeselect(
                    '`uid`',
                    'shouts',
                    'WHERE `id`=?',
                    $id,
                );
                $shoutrow = $this->database->arow($result);
            }

            if (
                isset($shoutrow['uid'])
                && $shoutrow['uid'] === $this->user->get('id')
            ) {
                $candelete = true;
            }
        }

        return $candelete;
    }

    public function formatshout($row): ?string
    {
        $shout = $this->textFormatting->theworks($row['shout'], ['minimalbb' => true]);
        $user = $row['uid'] ? $this->page->meta(
            'user-link',
            $row['uid'],
            $row['group_id'],
            $row['display_name'],
        ) : 'Guest';
        $avatar = $this->config->getSetting('shoutboxava')
            ? '<img src="' . $this->jax->pick(
                $row['avatar'],
                $this->page->meta('default-avatar'),
            ) . '" class="avatar" alt="avatar" />' : '';
        $deletelink = $this->page->meta('shout-delete', $row['id']);
        if (!$this->canDelete(0, $row)) {
            $deletelink = '';
        }

        if (mb_substr($shout, 0, 4) === '/me ') {
            return $this->page->meta(
                'shout-action',
                $this->jax->smalldate(
                    $row['date'],
                    1,
                ),
                $user,
                mb_substr(
                    $shout,
                    3,
                ),
                $deletelink,
            );
        }

        return $this->page->meta(
            'shout',
            $row['date'],
            $user,
            $shout . PHP_EOL,
            $deletelink,
            $avatar,
        );
    }

    public function displayshoutbox(): void
    {
        $result = $this->database->safespecial(
            <<<'EOT'
                SELECT
                    m.`avatar` AS `avatar`,
                    m.`display_name` AS `display_name`,
                    m.`group_id` AS `group_id`,
                    s.`id` AS `id`,
                    s.`shout` AS `shout`,
                    s.`uid` AS `uid`,
                    UNIX_TIMESTAMP(s.`date`) AS `date`
                FROM %t s
                LEFT JOIN %t m
                    ON s.`uid`=m.`id`
                ORDER BY s.`id` DESC LIMIT ?
                EOT
            ,
            ['shouts', 'members'],
            $this->shoutlimit,
        );
        $shouts = '';
        $first = 0;
        while ($f = $this->database->arow($result)) {
            if (!$first) {
                $first = $f['id'];
            }

            $shouts .= $this->formatshout($f);
        }

        $this->session->addvar('sb_id', $first);
        $this->page->append(
            'shoutbox',
            $this->page->meta(
                'collapsebox',
                " id='shoutbox'",
                $this->page->meta(
                    'shoutbox-title',
                ),
                $this->page->meta(
                    'shoutbox',
                    $shouts,
                ),
            ) . "<script type='text/javascript'>globalsettings.shoutlimit="
            . $this->shoutlimit . ';globalsettings.sound_shout='
            . ($this->user->get('sound_shout') ? 1 : 0)
            . '</script>',
        );
    }

    public function updateshoutbox(): void
    {
        // This is a bit tricky, we're transversing the shouts
        // in reverse order, since they're shifted onto the list, not pushed.
        $last = 0;
        if (
            isset($this->session->vars['sb_id'])
            && $this->session->vars['sb_id']
        ) {
            $result = $this->database->safespecial(
                <<<'EOT'
                    SELECT
                        m.`avatar` AS `avatar`,
                        m.`display_name` AS `display_name`,
                        m.`group_id` AS `group_id`,
                        s.`id` AS `id`,
                        s.`shout` AS `shout`,
                        s.`uid` AS `uid`,
                        UNIX_TIMESTAMP(s.`date`) AS `date`
                    FROM %t s
                    LEFT JOIN %t m
                        ON s.`uid`=m.`id`
                    WHERE s.`id`>?
                    ORDER BY s.`id` ASC LIMIT ?
                    EOT
                ,
                ['shouts', 'members'],
                $this->jax->pick($this->session->vars['sb_id'], 0),
                $this->shoutlimit,
            );
            while ($f = $this->database->arow($result)) {
                $this->page->JS('addshout', $this->formatshout($f));
                if ($this->config->getSetting('shoutboxsounds')) {
                    $sounds = [];
                    if (
                        $this->user->get('sound_shout')
                        && $sounds[$f['shout']]
                    ) {
                        $this->page->JS(
                            'playsound',
                            'sfx',
                            SOUNDSURL . $sounds[$f['shout']] . '.mp3',
                        );
                    }
                }

                $last = $f['id'];
            }
        }

        // Update the sb_id variable if we selected shouts.
        if (!$last) {
            return;
        }

        $this->session->addvar('sb_id', $last);
    }

    public function showallshouts(): void
    {
        $perpage = 100;
        $pagen = 0;
        $pages = '';
        $page = '';
        if (
            isset($this->jax->b['page'])
            && is_numeric($this->jax->b['page'])
            && $this->jax->b['page'] > 1
        ) {
            $pagen = $this->jax->b['page'] - 1;
        }

        $result = $this->database->safeselect(
            'COUNT(`id`)',
            'shouts',
        );
        $thisrow = $this->database->arow($result);
        $numshouts = array_pop($thisrow);
        $this->database->disposeresult($result);
        if ($numshouts > 1000) {
            $numshouts = 1000;
        }

        if ($numshouts > $perpage) {
            $pages .= " &middot; Pages: <span class='pages'>";
            $pageArray = $this->jax->pages(
                ceil($numshouts / $perpage),
                $pagen + 1,
                10,
            );
            foreach ($pageArray as $v) {
                $pages .= '<a href="?module=shoutbox&page='
                    . $v . '"'
                    . ($v + 1 === $pagen ? ' class="active"' : '')
                    . '>' . $v . '</a> ';
            }

            $pages .= '</span>';
        }

        $this->page->path(['Shoutbox History' => '?module=shoutbox']);
        $this->page->updatepath();
        if ($this->page->jsupdate) {
            return;
        }

        $result = $this->database->safespecial(
            <<<'EOT'
                SELECT
                    s.`id` AS `id`,
                    s.`uid` AS `uid`,
                    s.`shout` AS `shout`,
                    UNIX_TIMESTAMP(s.`date`) AS `date`,
                    m.`display_name` AS `display_name`,
                     m.`group_id` AS `group_id`,
                    m.`avatar` AS `avatar`
                FROM %t s
                LEFT JOIN %t m
                ON s.`uid`=m.`id`
                ORDER BY s.`id` DESC LIMIT ?,?
                EOT
            ,
            ['shouts', 'members'],
            $pagen * $perpage,
            $perpage,
        );
        $shouts = '';
        while ($f = $this->database->arow($result)) {
            $shouts .= $this->formatshout($f);
        }

        $page = $this->page->meta(
            'box',
            '',
            'Shoutbox' . $pages,
            '<div class="sbhistory">' . $shouts . '</div>',
        );
        $this->page->JS('update', 'page', $page);
        $this->page->append('PAGE', $page);
    }

    public function deleteshout()
    {
        if ($this->user->isGuest()) {
            return $this->page->location('?');
        }

        $delete = $this->jax->b['shoutbox_delete'] ?? 0;
        $candelete = $this->canDelete($delete);
        if (!$candelete) {
            return $this->page->location('?');
        }

        $this->page->JS('softurl');
        $this->database->safedelete(
            'shouts',
            'WHERE `id`=?',
            $delete,
        );

        return null;
    }

    public function addshout(): void
    {
        $this->session->act();
        $e = '';
        $shout = $this->jax->p['shoutbox_shout'];
        $shout = $this->textFormatting->linkify($shout);

        $perms = $this->user->getPerms();
        if ($this->user->isGuest()) {
            $e = 'You must be logged in to shout!';
        } elseif (!$perms['can_shout']) {
            $e = 'You do not have permission to shout!';
        } elseif (mb_strlen((string) $shout) > 300) {
            $e = 'Shout must be less than 300 characters.';
        }

        if ($e !== '' && $e !== '0') {
            $this->page->JS('error', $e);
            $this->page->append('shoutbox', $this->page->error($e));

            return;
        }

        $this->database->safeinsert(
            'shouts',
            [
                'date' => gmdate('Y-m-d H:i:s'),
                'ip' => $this->ipAddress->asBinary(),
                'shout' => $shout,
                'uid' => $this->user->get('id'),
            ],
        );
    }
}
