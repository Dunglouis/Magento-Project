<?php
declare(strict_types=1);

namespace TechNest\Warranty\Model;

/**
 * Warranty cover dates: N months from the start day, the last covered day being the day before
 * the same day N months later (31 Jan + 1 month covers up to 28/29 Feb)
 */
class CoverPeriod
{
    /**
     * Last covered day of a cover of the given length
     *
     * @param string $startsAt Y-m-d
     * @param int $months
     * @return string Y-m-d
     */
    public function lastDay(string $startsAt, int $months): string
    {
        $start = new \DateTimeImmutable(substr($startsAt, 0, 10));
        $day = (int)$start->format('j');
        $target = $start->modify('first day of this month')->modify(sprintf('+%d months', $months));
        $sameDay = $target->setDate(
            (int)$target->format('Y'),
            (int)$target->format('n'),
            min($day, (int)$target->format('t'))
        );
        // Same calendar day N months later when it exists, else the month end is the first uncovered day
        $endExclusive = $day > (int)$target->format('t') ? $sameDay->modify('+1 day') : $sameDay;
        return $endExclusive->modify('-1 day')->format('Y-m-d');
    }

    /**
     * Days left in the cover, 0 when it has ended
     *
     * @param string $expiresAt Y-m-d
     * @param string $today Y-m-d
     * @return int
     */
    public function daysLeft(string $expiresAt, string $today): int
    {
        if ($expiresAt < $today) {
            return 0;
        }
        return (int)(new \DateTimeImmutable($today))->diff(new \DateTimeImmutable($expiresAt))->days + 1;
    }
}
