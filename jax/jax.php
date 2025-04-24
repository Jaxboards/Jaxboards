<?php

declare(strict_types=1);

namespace Jax;

use function array_merge;
use function count;
use function floor;
use function glob;
use function gmdate;
use function is_array;
use function is_dir;
use function json_decode;
use function mail;
use function mb_substr;
use function preg_match;
use function rmdir;
use function round;
use function setcookie;
use function str_replace;
use function strtotime;
use function time;
use function unlink;
use function unpack;

use const PHP_EOL;

final class Jax
{
    /**
     * @var array<mixed>
     */
    public $c = [];

    /**
     * @var array<mixed>|string
     */
    public $g = [];

    /**
     * @var array<mixed>|string
     */
    public $p = [];

    /**
     * @var array<mixed>
     */
    public $b = [];

    public function __construct(private readonly Config $config)
    {
        $this->c = $_COOKIE;
        $this->g = $_GET;
        $this->p = $_POST;
        $this->b = array_merge($this->p, $this->g);
    }

    public function pick(...$args)
    {
        foreach ($args as $v) {
            if ($v) {
                break;
            }
        }

        return $v;
    }

    public function hiddenFormFields($fields): string
    {
        $html = '';
        foreach ($fields as $key => $value) {
            $html .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
        }

        return $html;
    }

    public function date($date, $autodate = true): false|string
    {
        if (!$date) {
            return false;
        }

        $delta = time() - $date;
        $fmt = '';
        if ($delta < 90) {
            $fmt = 'a minute ago';
        } elseif ($delta < 3600) {
            $fmt = round($delta / 60) . ' minutes ago';
        } elseif (gmdate('m j Y') === gmdate('m j Y', $date)) {
            $fmt = 'Today @ ' . gmdate('g:i a', $date);
        } elseif (gmdate('m j Y', strtotime('yesterday')) === gmdate('m j Y', $date)) {
            $fmt = 'Yesterday @ ' . gmdate('g:i a', $date);
        } else {
            $fmt = gmdate('M jS, Y @ g:i a', $date);
        }

        if (!$autodate) {
            return $fmt;
        }

        return "<span class='autodate' title='{$date}'>{$fmt}</span>";
    }

    public function smalldate(
        $date,
        $seconds = false,
        $autodate = false,
    ): false|string {
        if (!$date) {
            return false;
        }

        return ($autodate
            ? '<span class="autodate smalldate" title="' . $date . '">'
            : '')
            . gmdate('g:i' . ($seconds ? ':s' : '') . 'a, n/j/y', $date)
            . ($autodate ? '</span>' : '');
    }

    public function setCookie(
        $a,
        $b = 'false',
        $c = false,
        $htmlonly = true,
    ): void {
        if (!is_array($a)) {
            $a = [$a => $b];
        } elseif ($b !== 'false') {
            $c = $b;
        }

        foreach ($a as $k => $v) {
            $this->c[$k] = $v;
            setcookie($k, (string) $v, ['expires' => $c, 'path' => null, 'domain' => null, 'secure' => true, 'httponly' => $htmlonly]);
        }
    }

    public function isurl($url): false|int
    {
        return preg_match('@^https?://[\w\.\-%\&\?\=/]+$@', (string) $url);
    }

    public function isemail($email): false|int
    {
        return preg_match('/[\w\+.]+@[\w.]+/', (string) $email);
    }

    public function parseperms($permstoparse, $uid = false): array
    {
        global $PERMS;
        $permstoparse .= '';
        if ($permstoparse === '' || $permstoparse === '0') {
            $permstoparse = '0';
        }

        if ($permstoparse !== '0') {
            if ($uid !== false) {
                $unpack = unpack('n*', $permstoparse);
                $permstoparse = [];
                $counter = count($unpack);
                for ($x = 1; $x < $counter; $x += 2) {
                    $permstoparse[$unpack[$x]] = $unpack[$x + 1];
                }

                $permstoparse = $permstoparse[$uid] ?? null;
            }
        } else {
            $permstoparse = null;
        }

        if ($permstoparse === null) {
            return [
                'poll' => $PERMS['can_poll'],
                'read' => 1,
                'reply' => $PERMS['can_post'],
                'start' => $PERMS['can_post_topics'],
                'upload' => $PERMS['can_attach'],
                'view' => 1,
            ];
        }

        return [
            'poll' => $permstoparse & 32,
            'read' => $permstoparse & 8,
            'reply' => $permstoparse & 2,
            'start' => $permstoparse & 4,
            'upload' => $permstoparse & 1,
            'view' => $permstoparse & 16,
        ];
    }

    public function parsereadmarkers($readmarkers)
    {
        if ($readmarkers) {
            return json_decode((string) $readmarkers, true) ?? [];
        }

        return [];
    }

    public function rmdir($dir): bool
    {
        if (mb_substr((string) $dir, -1) !== '/') {
            $dir .= '/';
        }

        foreach (glob($dir . '*') as $v) {
            if (is_dir($v)) {
                $this->rmdir($v);
            } else {
                unlink($v);
            }
        }

        rmdir($dir);

        return true;
    }

    public function pages($numpages, $active, $tofill)
    {
        $tofill -= 2;
        $pages[] = 1;
        if ($numpages === 1) {
            return $pages;
        }

        $start = $active - floor($tofill / 2);
        if ($numpages - $start < $tofill) {
            $start -= $tofill - ($numpages - $start);
        }

        if ($start <= 1) {
            $start = 2;
        }

        for ($x = 0; $x < $tofill && ($start + $x) < $numpages; ++$x) {
            $pages[] = $x + $start;
        }

        $pages[] = $numpages;

        return $pages;
    }

    public function filesize($bs): string
    {
        $p = 0;
        $sizes = ' KMGT';
        while ($bs > 1024) {
            $bs /= 1024;
            ++$p;
        }

        return round($bs, 2) . ' ' . ($p !== 0 ? $sizes[$p] : '') . 'B';
    }

    public function mail($email, $topic, $message)
    {
        global $_SERVER;

        $boardname = $this->config->getSetting('boardname') ?: 'JaxBoards';
        $boardurl = 'https://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
        $boardlink = "<a href='https://" . $boardurl . "'>" . $boardname . '</a>';

        return @mail(
            (string) $email,
            $boardname . ' - ' . $topic,
            str_replace(
                ['{BOARDNAME}', '{BOARDURL}', '{BOARDLINK}'],
                [$boardname, $boardurl, $boardlink],
                $message,
            ),
            'MIME-Version: 1.0' . PHP_EOL
            . 'Content-type:text/html;charset=iso-8859-1' . PHP_EOL
            . 'From: ' . $this->config->getSetting('mail_from') . PHP_EOL,
        );
    }
}
