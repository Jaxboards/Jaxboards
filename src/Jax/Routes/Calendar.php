<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use Carbon\WeekDay;
use Jax\Date;
use Jax\Interfaces\Route;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Session;
use Jax\Template;
use Override;

use function gmdate;

final readonly class Calendar implements Route
{
    public function __construct(
        private Date $date,
        private Page $page,
        private Request $request,
        private Router $router,
        private Session $session,
        private Template $template,
    ) {}

    #[Override]
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

        $today = gmdate('n j Y');
        $firstDayOfMonth = Carbon::today('UTC')->setDay(1)->addMonthsNoOverflow($monthOffset);
        $offset = $firstDayOfMonth->getDaysFromStartOfWeek(WeekDay::Sunday);
        $daysInMonth = $firstDayOfMonth->daysInMonth;
        $year = $firstDayOfMonth->year;
        $month = $firstDayOfMonth->month;
        $monthName = $firstDayOfMonth->monthName;

        $this->session->set('locationVerbose', 'Checking out the calendar for ' . $monthName . ' ' . $year);
        $members = Member::selectMany('WHERE MONTH(`birthdate`)=? AND YEAR(`birthdate`)<=?', $month, $year);
        $birthdays = [];
        foreach ($members as $member) {
            if (!$member->birthdate) {
                continue;
            }

            $birthday = $this->date->dateAsCarbon($member->birthdate);
            if (!$birthday instanceof Carbon) {
                continue;
            }

            $birthdays[$birthday->day][] = [
                'member' => $member,
                'age' => $birthday->age,
            ];
        }

        $weeks = [];
        $days = [];
        if ($offset > 0) {
            $days[] = ['offset' => $offset];
        }

        for ($x = 1; $x <= $daysInMonth; ++$x) {
            $days[] = [
                'class' => $month . ' ' . $x . ' ' . $year === $today ? 'today' : '',
                'day' => $x,
                'birthdays' => $birthdays[$x] ?? [],
            ];

            if (0 !== (($x + $offset) % 7) && $x !== $daysInMonth) {
                continue;
            }

            $weeks[] = $days;
            $days = [];
        }

        $page = $this->template->render('calendar/calendar', [
            'monthName' => $monthName,
            'monthOffset' => $monthOffset,
            'weeks' => $weeks,
            'year' => $year,
        ]);
        $page = $this->template->render('global/box', ['title' => 'Calendar', 'content' => $page]);

        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }
}
