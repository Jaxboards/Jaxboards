<?php

declare(strict_types=1);

namespace Jax;

use Carbon\Carbon;

use function gmdate;
use function round;

final class Date
{
    /**
     * Returns a span-wrapped date which is automatically converted to the user's timezone
     * on the client.
     */
    public function autoDate(?int $date): string
    {
        // Some old forums have nullable fields that are no longer nullable
        // This needs to stay for data backwards compatibility
        if (!$date) {
            return '';
        }

        $relativeTime = $this->relativeTime($date);

        return "<span class='autodate' title='{$date}'>{$relativeTime}</span>";
    }

    /**
     * Returns a date in the format "12:00pm, 4/29/2025".
     *
     * @param array<string, bool> $options
     *                                     - "autodate" => true automatically wraps in span
     *                                     - "seconds" => true includes seconds in the date representation
     */
    public function smallDate(
        int $timestamp,
        array $options = [],
    ): string {
        $autodate = $options['autodate'] ?? false;
        $seconds = $options['seconds'] ?? false;

        $formattedDate = gmdate('g:i' . ($seconds ? ':s' : '') . 'a, n/j/y', $timestamp);

        return $autodate
            ? "<span class='autodate smalldate' title='{$timestamp}'>{$formattedDate}</span>"
            : $formattedDate;
    }

    public function relativeTime(int $date): string
    {
        $delta = Carbon::now('UTC')->getTimestamp() - $date;
        $hoursMinutes = gmdate('g:i a', $date);

        return match (true) {
            $delta < 90 => 'a minute ago',
            $delta < 3600 => round($delta / 60) . ' minutes ago',
            gmdate('m j Y') === gmdate('m j Y', $date) => "Today @ {$hoursMinutes}",
            gmdate('m j Y', Carbon::parse('yesterday')->getTimestamp()) === gmdate('m j Y', $date) => "Yesterday @ {$hoursMinutes}",
            default => gmdate('M jS, Y @ g:i a', $date),
        };
    }
}
