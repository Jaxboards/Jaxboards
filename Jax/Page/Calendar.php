<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;

use function explode;
use function gmdate;
use function implode;
use function is_numeric;
use function mktime;
use function sprintf;

final class Calendar
{
    private ?int $month = null;

    public function __construct(
        private readonly Database $database,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly Template $template,
    ) {
        $this->template->loadMeta('calendar');
    }

    public function render(): void
    {
        $this->month = is_numeric($this->request->both('month'))
            ? (int) $this->request->both('month')
            : (int) gmdate('n');

        $this->monthview();
    }

    private function monthview(): void
    {
        $monthoffset = $this->month;
        if ($this->request->isJSUpdate()) {
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
        $offset = (int) $offset;

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
        while ($member = $this->database->arow($result)) {
            $birthdays[$member['dob_day']][] = sprintf(
                '<a href="?act=vu%1$s" class="user%1$s mgroup%2$s" '
                . 'title="%4$s years old!" data-use-tooltip="true">'
                . '%3$s</a>',
                $member['id'],
                $member['group_id'],
                $member['name'],
                $year - $member['dob_year'],
            );
        }

        $page .= $this->template->meta(
            'calendar-heading',
            $monthname,
            $year,
            $monthoffset - 1,
            $monthoffset + 1,
        );
        $page .= $this->template->meta('calendar-daynames');
        $week = '';
        for ($x = 1; $x <= $daysinmonth; ++$x) {
            if ($x === 1 && $offset) {
                $week .= $this->template->meta(
                    'calendar-padding',
                    $offset,
                );
            }

            $week .= $this->template->meta(
                'calendar-day',
                $month . ' ' . $x . ' ' . $year === $today ? 'today' : '',
                $x,
                empty($birthdays[$x]) ? '' : $this->template->meta(
                    'calendar-birthdays',
                    implode(',', $birthdays[$x]),
                ),
            );
            if (0 !== ($x + $offset) % 7 && !($x === $daysinmonth && $week)) {
                continue;
            }

            $page .= $this->template->meta('calendar-week', $week);
            $week = '';
        }

        $page = $this->template->meta('calendar', $page);
        $page = $this->template->meta('box', '', 'Calendar', $page);

        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }
}
