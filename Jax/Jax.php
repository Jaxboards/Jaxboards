<?php

declare(strict_types=1);

namespace Jax;

use function floor;
use function glob;
use function gmdate;
use function in_array;
use function is_dir;
use function json_decode;
use function mail;
use function mb_substr;
use function preg_match;
use function rmdir;
use function round;
use function str_replace;
use function strtotime;
use function time;
use function unlink;

use const PHP_EOL;

final readonly class Jax
{
    public function __construct(
        private Config $config,
        private DomainDefinitions $domainDefinitions,
    ) {}

    public function pick(...$args)
    {
        foreach ($args as $v) {
            if ($v) {
                break;
            }
        }

        return $v;
    }

    /**
     * @param array<string,string> $fields
     */
    public function hiddenFormFields(array $fields): string
    {
        $html = '';
        foreach ($fields as $key => $value) {
            $html .= "<input type='hidden' name='{$key}' value='{$value}'>";
        }

        return $html;
    }

    /**
     * @param array<string> $options can contain 'autodate' to control format
     */
    public function date(int $date, $options = ['autodate']): string
    {
        $autodate = in_array('autodate', $options);

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

    /**
     * @param array<string> $options can contain 'autodate' or 'seconds' to control format
     */
    public function smalldate(
        int $timestamp,
        array $options = [],
    ): string {
        $autodate = in_array('autodate', $options);
        $seconds = in_array('seconds', $options);

        return ($autodate ? "<span class='autodate smalldate' title='{$timestamp}'>" : '')
            . gmdate('g:i' . ($seconds ? ':s' : '') . 'a, n/j/y', $timestamp)
            . ($autodate ? '</span>' : '');
    }

    public function isurl(string $url): false|int
    {
        return preg_match('@^https?://[\w\.\-%\&\?\=/]+$@', $url);
    }

    public function isemail(string $email): false|int
    {
        return preg_match('/[\w\+.]+@[\w.]+/', $email);
    }

    public function parsereadmarkers(?string $readmarkers)
    {
        if ($readmarkers) {
            return json_decode($readmarkers, true) ?? [];
        }

        return [];
    }

    public function rmdir(string $dir): bool
    {
        if (mb_substr($dir, -1) !== '/') {
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

    public function pages(int $numpages, int $active, int $tofill)
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

    public function filesize(int $bs): string
    {
        $p = 0;
        $sizes = ' KMGT';
        while ($bs > 1024) {
            $bs /= 1024;
            ++$p;
        }

        return round($bs, 2) . ' ' . ($p !== 0 ? $sizes[$p] : '') . 'B';
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     *
     * @param mixed $email
     * @param mixed $topic
     * @param mixed $message
     */
    public function mail(string $email, string $topic, string $message): bool
    {
        $boardname = $this->config->getSetting('boardname') ?: 'JaxBoards';
        $boardurl = $this->domainDefinitions->getBoardURL();
        $boardlink = "<a href='{$boardurl}'>{$boardname}</a>";

        return mail(
            $email,
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
