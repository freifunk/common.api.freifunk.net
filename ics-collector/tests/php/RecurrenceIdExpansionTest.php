<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use ICal\SabreVObjectCalendarHandler;
use DateTime;

class RecurrenceIdExpansionTest extends TestCase
{
    private SabreVObjectCalendarHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new SabreVObjectCalendarHandler('Europe/Berlin');
    }

    public function testExpandedEventsHaveNoRecurrenceId(): void
    {
        $fixtureFile = __DIR__ . '/../fixtures/recurring_events_with_source.ics';
        $events = $this->handler->parseIcsFile($fixtureFile);

        $expanded = $this->handler->expandRecurringEvents(
            $events,
            new DateTime('2024-05-01'),
            new DateTime('2024-06-30')
        );

        foreach ($expanded as $event) {
            $this->assertNull(
                $event->{'RECURRENCE-ID'} ?? null,
                sprintf('Event "%s" should not have RECURRENCE-ID after expansion', (string)$event->SUMMARY)
            );
        }
    }

    public function testExpandedEventsHaveUniqueUids(): void
    {
        $fixtureFile = __DIR__ . '/../fixtures/recurring_events_with_source.ics';
        $events = $this->handler->parseIcsFile($fixtureFile);

        $expanded = $this->handler->expandRecurringEvents(
            $events,
            new DateTime('2024-05-01'),
            new DateTime('2024-06-30')
        );

        $uids = [];
        foreach ($expanded as $event) {
            $uid = (string)$event->UID;
            $this->assertNotContains($uid, $uids, sprintf('Duplicate UID found: %s', $uid));
            $uids[] = $uid;
        }
    }

    public function testSourceFilterWorksAfterExpansion(): void
    {
        $fixtureFile = __DIR__ . '/../fixtures/recurring_events_with_source.ics';

        $resultB = $this->handler->processCalendarRequest($fixtureFile, [
            'from' => '2024-05-01',
            'to' => '2024-06-30',
            'source' => 'source-b',
            'format' => 'ics'
        ]);

        $ics = $resultB['data'];
        $this->assertGreaterThan(0, substr_count($ics, 'BEGIN:VEVENT'), 'source=source-b should return events');
        $this->assertStringNotContainsString('RECURRENCE-ID', $ics);
        $this->assertStringContainsString('X-WR-SOURCE:source-b', $ics);
    }

    public function testOutputIcsHasNoRecurrenceIdAnywhere(): void
    {
        $fixtureFile = __DIR__ . '/../fixtures/recurring_events_with_source.ics';

        $result = $this->handler->processCalendarRequest($fixtureFile, [
            'from' => '2024-05-01',
            'to' => '2024-06-30',
            'source' => 'all',
            'format' => 'ics'
        ]);

        $this->assertStringNotContainsString('RECURRENCE-ID', $result['data']);
    }

    public function testPreExpandedEventsWithRecurrenceIdAreCleaned(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:pre-expanded@example.com
DTSTAMP:20240501T100000Z
SUMMARY:Pre-expanded Instance 1
DTSTART;TZID=Europe/Berlin:20240505T190000
DTEND;TZID=Europe/Berlin:20240505T203000
X-WR-SOURCE:aachen
RECURRENCE-ID;TZID=Europe/Berlin:20240505T190000
END:VEVENT
BEGIN:VEVENT
UID:pre-expanded@example.com
DTSTAMP:20240501T100000Z
SUMMARY:Pre-expanded Instance 2
DTSTART;TZID=Europe/Berlin:20240512T190000
DTEND;TZID=Europe/Berlin:20240512T203000
X-WR-SOURCE:aachen
RECURRENCE-ID;TZID=Europe/Berlin:20240512T190000
END:VEVENT
BEGIN:VEVENT
UID:normal-event@example.com
DTSTAMP:20240501T100000Z
SUMMARY:Normal Event
DTSTART;TZID=Europe/Berlin:20240510T100000
DTEND;TZID=Europe/Berlin:20240510T110000
X-WR-SOURCE:aachen
END:VEVENT
END:VCALENDAR
ICS;

        $events = $this->handler->parseIcsString($icsContent);

        $expanded = $this->handler->expandRecurringEvents(
            $events,
            new DateTime('2024-05-01'),
            new DateTime('2024-06-30')
        );

        // All RECURRENCE-IDs must be removed
        foreach ($expanded as $event) {
            $this->assertNull(
                $event->{'RECURRENCE-ID'} ?? null,
                sprintf('Event "%s" should not have RECURRENCE-ID', (string)$event->SUMMARY)
            );
        }

        // All UIDs must be unique
        $uids = array_map(fn($e) => (string)$e->UID, $expanded);
        $this->assertCount(count($uids), array_unique($uids), 'All UIDs must be unique');

        // Normal event UID should be unchanged
        $normalUids = array_filter($uids, fn($uid) => str_starts_with($uid, 'normal-event@'));
        $this->assertContains('normal-event@example.com', $normalUids);
    }

    public function testProcessCalendarRequestWithPreExpandedEvents(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:pre-expanded@example.com
DTSTAMP:20240501T100000Z
SUMMARY:Pre-expanded Instance 1
DTSTART;TZID=Europe/Berlin:20240505T190000
DTEND;TZID=Europe/Berlin:20240505T203000
X-WR-SOURCE:aachen
RECURRENCE-ID;TZID=Europe/Berlin:20240505T190000
END:VEVENT
BEGIN:VEVENT
UID:pre-expanded@example.com
DTSTAMP:20240501T100000Z
SUMMARY:Pre-expanded Instance 2
DTSTART;TZID=Europe/Berlin:20240512T190000
DTEND;TZID=Europe/Berlin:20240512T203000
X-WR-SOURCE:aachen
RECURRENCE-ID;TZID=Europe/Berlin:20240512T190000
END:VEVENT
END:VCALENDAR
ICS;

        // Write temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'ics_test_');
        file_put_contents($tmpFile, $icsContent);

        try {
            $result = $this->handler->processCalendarRequest($tmpFile, [
                'from' => '2024-05-01',
                'to' => '2024-06-30',
                'source' => 'aachen',
                'format' => 'ics'
            ]);

            $ics = $result['data'];
            $this->assertGreaterThan(0, substr_count($ics, 'BEGIN:VEVENT'), 'Should have events');
            $this->assertStringNotContainsString('RECURRENCE-ID', $ics, 'No RECURRENCE-ID in output');
            $this->assertStringContainsString('X-WR-SOURCE:aachen', $ics);
        } finally {
            unlink($tmpFile);
        }
    }
}
