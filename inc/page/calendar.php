<?php

$PAGE->loadmeta('calendar');
new CALENDAR();
final class CALENDAR
{
    public $month;

    public function __construct()
    {
        global $JAX;
        if (isset($JAX->b['month'])) {
            if (is_numeric($JAX->b['month'])) {
                $this->month = $JAX->b['month'];
            }
        } else {
            $this->month = date('n');
        }

        $this->monthview();
    }

    public function monthview(): void
    {
        global $PAGE,$DB,$SESS;
        $monthoffset = $this->month;
        if ($PAGE->jsupdate) {
            return;
        }

        $page = '';
        $today = date('n j Y');
        [
            $offset,
            $daysinmonth,
            $monthname,
            $year,
            $month,
        ] = explode(
            ' ',
            date('w t F Y n', mktime(0, 0, 0, $monthoffset, 1)),
        );

        $SESS->location_verbose
            = 'Checking out the calendar for ' . $monthname . ' ' . $year;
        $result = $DB->safeselect(
            <<<'EOT'
                `id`,`display_name` AS `name`,`group_id`,DAY(`birthdate`) AS `dob_day`,
                MONTH(`birthdate`) AS `dob_month`,YEAR(`birthdate`) AS `dob_year`
                EOT
            ,
            'members',
            'WHERE MONTH(`birthdate`)=? AND YEAR(`birthdate`)<?',
            $DB->basicvalue($month),
            $year,
        );
        $birthdays = [];
        while ($f = $DB->arow($result)) {
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

        $page .= $PAGE->meta(
            'calendar-heading',
            $monthname,
            $year,
            $monthoffset - 1,
            $monthoffset + 1,
        );
        $page .= $PAGE->meta('calendar-daynames');
        $week = '';
        for ($x = 1; $x <= $daysinmonth; ++$x) {
            if ($x === 1 && $offset) {
                $week .= $PAGE->meta(
                    'calendar-padding',
                    $offset,
                );
            }

            $week .= $PAGE->meta(
                'calendar-day',
                $month . ' ' . $x . ' ' . $year === $today ? 'today' : '',
                $x,
                empty($birthdays[$x]) ? '' : $PAGE->meta(
                    'calendar-birthdays',
                    implode(',', $birthdays[$x]),
                ),
            );
            if (0 !== ($x + $offset) % 7 && !($x === $daysinmonth && $week)) {
                continue;
            }

            $page .= $PAGE->meta('calendar-week', $week);
            $week = '';
        }

        $page = $PAGE->meta('calendar', $page);
        $page = $PAGE->meta('box', '', 'Calendar', $page);

        $PAGE->append('PAGE', $page);
        $PAGE->JS('update', 'page', $page);
    }
}
