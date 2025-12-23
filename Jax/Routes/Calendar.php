<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use Jax\Date;
use Jax\Interfaces\Route;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Session;
use Jax\Template;

use function explode;
use function gmdate;
use function implode;

final readonly class Calendar implements Route
{
    public function __construct(
        private Date $date,
        private Page $page,
        private Request $request,
        private Router $router,
        private Session $session,
        private Template $template,
    ) {
        $this->template->loadMeta('calendar');
    }

    public function route($params): void
    {
        $this->page->setBreadCrumbs([
            $this->router->url('calendar') => 'Calendar',
        ]);

        $this->monthView((int) $this->request->both('month'));
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
            Carbon::today('UTC')
                ->addMonths($monthOffset)
                ->format('w t F Y n'),
        );
        $offset = (int) $offset;
        $daysInMonth = (int) $daysInMonth;
        $year = (int) $year;
        $month = (int) $month;

        $this->session->set('locationVerbose', 'Checking out the calendar for ' . $monthName . ' ' . $year);
        $members = Member::selectMany(
            'WHERE MONTH(`birthdate`)=? AND YEAR(`birthdate`)<=?',
            $month,
            $year,
        );
        $birthdays = [];
        foreach ($members as $member) {
            if (!$member->birthdate) {
                continue;
            }

            $birthday = $this->date->dateAsCarbon($member->birthdate);
            $profileURL = $this->router->url('profile', ['id' => $member->id]);
            $birthdays[$birthday?->day][] = <<<HTML
                    <a
                        href="{$profileURL}"
                        class="user{$member->id} mgroup{$member->groupID}"
                        title="{$birthday->age} years old!"
                        data-use-tooltip="true">{$member->displayName}</a>
                HTML;
        }

        $prevMonthURL = $this->router->url('calendar', ['month' => $monthOffset - 1]);
        $nextMonthURL = $this->router->url('calendar', ['month' => $monthOffset + 1]);

        $page .= $this->template->meta(
            'calendar-heading',
            $monthName,
            $year,
            $monthOffset >= -11 ? "<a href='{$prevMonthURL}'>&lt;</a>" : '',
            $monthOffset <= 11 ? "<a href='{$nextMonthURL}'>&gt;</a>" : '',
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
