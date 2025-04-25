<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Session;

use function explode;
use function gmdate;
use function implode;
use function is_numeric;
use function mktime;
use function sprintf;

/**
 * @psalm-api
 */
final class Calendar
{
    private $month;

    public function __construct(
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Session $session,
    ) {
        $this->page->loadmeta('calendar');
    }

    public function render(): void
    {
        if (isset($this->jax->b['month'])) {
            if (is_numeric($this->jax->b['month'])) {
                $this->month = (int) $this->jax->b['month'];
            }
        } else {
            $this->month = (int) gmdate('n');
        }

        $this->monthview();
    }

    public function monthview(): void
    {
        $monthoffset = $this->month;
        if ($this->page->jsupdate) {
            return;
        }

        $page = '';
        $today = gmdate('n j Y');
        [
            $offset,
            $daysinmonth,
            $monthname,
            $year,
            $month,
        ] = explode(
            ' ',
            gmdate('w t F Y n', mktime(0, 0, 0, $monthoffset, 1)),
        );

        $this->session->set('location_verbose', 'Checking out the calendar for ' . $monthname . ' ' . $year);
        $result = $this->database->safeselect(
            [
                'id',
                '`display_name` AS `name`',
                'group_id',
                'DAY(`birthdate`) AS `dob_day`',
                'MONTH(`birthdate`) AS `dob_month`',
                'YEAR(`birthdate`) AS `dob_year`',
            ],
            'members',
            'WHERE MONTH(`birthdate`)=? AND YEAR(`birthdate`)<?',
            $this->database->basicvalue($month),
            $year,
        );
        $birthdays = [];
        while ($f = $this->database->arow($result)) {
            $birthdays[$f['dob_day']][] = sprintf(
                '<a href="?act=vu%1$s" class="user%1$s mgroup%2$s" '
                . 'title="%4$s years old!" data-use-tooltip="true">'
                . '%3$s</a>',
                $f['id'],
                $f['group_id'],
                $f['name'],
                $year - $f['dob_year'],
            );
        }

        $page .= $this->page->meta(
            'calendar-heading',
            $monthname,
            $year,
            $monthoffset - 1,
            $monthoffset + 1,
        );
        $page .= $this->page->meta('calendar-daynames');
        $week = '';
        for ($x = 1; $x <= $daysinmonth; ++$x) {
            if ($x === 1 && $offset) {
                $week .= $this->page->meta(
                    'calendar-padding',
                    $offset,
                );
            }

            $week .= $this->page->meta(
                'calendar-day',
                $month . ' ' . $x . ' ' . $year === $today ? 'today' : '',
                $x,
                empty($birthdays[$x]) ? '' : $this->page->meta(
                    'calendar-birthdays',
                    implode(',', $birthdays[$x]),
                ),
            );
            if (0 !== ($x + $offset) % 7 && !($x === $daysinmonth && $week)) {
                continue;
            }

            $page .= $this->page->meta('calendar-week', $week);
            $week = '';
        }

        $page = $this->page->meta('calendar', $page);
        $page = $this->page->meta('box', '', 'Calendar', $page);

        $this->page->append('PAGE', $page);
        $this->page->JS('update', 'page', $page);
    }
}
