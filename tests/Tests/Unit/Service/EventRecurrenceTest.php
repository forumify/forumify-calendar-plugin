<?php

declare(strict_types=1);

namespace PluginTests\Tests\Unit\Service;

use DateTime;
use DateTimeZone;
use Forumify\Calendar\Entity\CalendarEvent;
use Forumify\Calendar\Service\EventRecurrence;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EventRecurrenceTest extends TestCase
{
    public function testSingleEventIsOnlyReturnedWhenInRange(): void
    {
        $event = $this->createEvent('2030-06-15 12:00:00');

        self::assertSame(['2030-06-15 12:00'], $this->occurrences($event, '2030-06-15 11:00', '2030-06-15 12:00'));
        // the range excludes its start
        self::assertSame([], $this->occurrences($event, '2030-06-15 12:00', '2030-06-15 13:00'));
        self::assertSame([], $this->occurrences($event, '2030-06-16 00:00', '2030-06-17 00:00'));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function repeatProvider(): iterable
    {
        yield 'daily' => ['daily', ['2030-06-16 12:00', '2030-06-17 12:00', '2030-06-18 12:00']];
        yield 'weekly' => ['weekly', ['2030-06-22 12:00', '2030-06-29 12:00']];
        yield 'monthly' => ['monthly', ['2030-07-15 12:00']];
        yield 'annually' => ['annually', ['2031-06-15 12:00']];
        yield 'yearly' => ['yearly', ['2031-06-15 12:00']];
        yield 'unknown repeat behaves like a single event' => ['fortnightly', []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('repeatProvider')]
    public function testRepeats(string $repeat, array $expected): void
    {
        $event = $this->createEvent('2030-06-15 12:00:00', $repeat);

        $occurrences = $this->occurrences($event, '2030-06-15 12:00', '2031-06-30 00:00');

        self::assertSame($expected, array_slice($occurrences, 0, count($expected)));
    }

    public function testMonthlyRepeatDoesNotDrift(): void
    {
        // adding one month at a time would go Jan 31, Mar 3, Apr 3 and never land on Mar 31
        $event = $this->createEvent('2030-01-31 12:00:00', 'monthly');

        self::assertSame(['2030-03-31 12:00'], $this->occurrences($event, '2030-03-15 00:00', '2030-04-01 00:00'));
    }

    public function testRepeatEndIncludesTheWholeDay(): void
    {
        $event = $this->createEvent('2030-06-15 20:00:00', 'daily', '2030-06-17');

        self::assertSame(
            ['2030-06-15 20:00', '2030-06-16 20:00', '2030-06-17 20:00'],
            $this->occurrences($event, '2030-06-01 00:00', '2030-07-01 00:00'),
        );
    }

    public function testRepeatEndIsInterpretedAsADateInTheGivenTimezone(): void
    {
        $event = $this->createEvent('2030-06-15 23:00:00', 'daily', '2030-06-17');
        $newYork = new DateTimeZone('America/New_York');

        $occurrences = $this->occurrences($event, '2030-06-01 00:00', '2030-07-01 00:00', $newYork);

        // 23:00 UTC is 19:00 in New York, the last occurrence is on June 17th in New York time
        self::assertSame(['2030-06-15 19:00', '2030-06-16 19:00', '2030-06-17 19:00'], $occurrences);
        self::assertSame('2030-06-17 00:00:00', $event->getRepeatEnd()?->format('Y-m-d H:i:s'), 'repeat end is not modified');
    }

    public function testDailyRepeatKeepsTheSameLocalTimeAcrossDaylightSavingTime(): void
    {
        // 18:00 in Brussels, the clocks go forward in the night of March 31st 2030
        $event = $this->createEvent('2030-03-29 17:00:00', 'daily');
        $brussels = new DateTimeZone('Europe/Brussels');

        $occurrences = $this->occurrences($event, '2030-03-29 00:00', '2030-03-31 23:59', $brussels);

        self::assertSame(['2030-03-29 18:00', '2030-03-30 18:00', '2030-03-31 18:00'], $occurrences);
    }

    private function createEvent(string $start, ?string $repeat = null, ?string $repeatEnd = null): CalendarEvent
    {
        $event = new CalendarEvent();
        $event->setStart(new DateTime($start, new DateTimeZone('UTC')));
        $event->setRepeat($repeat);
        $event->setRepeatEnd($repeatEnd === null ? null : new DateTime($repeatEnd, new DateTimeZone('UTC')));

        return $event;
    }

    /**
     * @return list<string> occurrences formatted in the timezone they were calculated in
     */
    private function occurrences(CalendarEvent $event, string $after, string $until, ?DateTimeZone $timezone = null): array
    {
        $utc = new DateTimeZone('UTC');
        $occurrences = new EventRecurrence()->getOccurrences(
            $event,
            new DateTime($after, $utc),
            new DateTime($until, $utc),
            $timezone,
        );

        return array_map(fn (DateTime $occurrence) => $occurrence->format('Y-m-d H:i'), $occurrences);
    }
}
