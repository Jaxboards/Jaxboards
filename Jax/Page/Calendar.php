<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Date;
use Jax\Models\Member;
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

final readonly class Calendar
{
    public function __construct(
        private Date $date,
        private Page $page,
        private Request $request,
        private Session $session,
        private Template $template,
    ) {
        $this->template->loadMeta('calendar');
    }

    public function render(): void
    {
        $month = is_numeric($this->request->both('month'))
            ? (int) $this->request->both('month')
            : (int) gmdate('n');

        $this->monthView($month);
    }

    private function monthView(int $monthOffset): void
    {
        if ($this->request->isJSUpdate()) {
            return;
        }

        $page = '';
        $today = gmdate('n j Y');
        [
            $offset,
            $daysInMonth,
            $monthName,
            $year,
            $month,
        ] = explode(
            ' ',
            gmdate('w t F Y n', mktime(0, 0, 0, $monthOffset, 1) ?: 0),
        );
        $offset = (int) $offset;
        $daysInMonth = (int) $daysInMonth;
        $year = (int) $year;

        $this->session->set('locationVerbose', 'Checking out the calendar for ' . $monthName . ' ' . $year);
        $members = Member::selectMany(
            'WHERE MONTH(`birthdate`)=? AND YEAR(`birthdate`)<?',
            $month,
            $year,
        );
        $birthdays = [];
        foreach ($members as $member) {
            if (!$member->birthdate) {
                continue;
            }

            $birthday = $this->date->dateAsCarbon($member->birthdate);
            $birthdays[$birthday?->day][] = sprintf(
                '<a href="?act=vu%1$s" class="user%1$s mgroup%2$s" '
                . 'title="%4$s years old!" data-use-tooltip="true">'
                . '%3$s</a>',
                $member->id,
                $member->groupID,
                $member->name,
                $year - ($birthday->year ?? 0),
            );
        }

        $page .= $this->template->meta(
            'calendar-heading',
            $monthName,
            $year,
            $monthOffset - 1,
            $monthOffset + 1,
        );
        $page .= $this->template->meta('calendar-daynames');
        $week = '';
        for ($x = 1; $x <= $daysInMonth; ++$x) {
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
            if (0 !== ($x + $offset) % 7 && !($x === $daysInMonth && $week)) {
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
