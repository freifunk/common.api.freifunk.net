<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use ICal\SabreVObjectCalendarHandler;
use Sabre\VObject\Component\VEvent;
use DateTime;

class SabreVObjectCalendarHandlerTest extends TestCase
{
    private SabreVObjectCalendarHandler $handler;
    
    protected function setUp(): void
    {
        $this->handler = new SabreVObjectCalendarHandler();
    }
    
    public function testCanInstantiate(): void
    {
        $this->assertInstanceOf(SabreVObjectCalendarHandler::class, $this->handler);
    }
    
    public function testCanParseSimpleIcsString(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:test@example.com
DTSTART:20231201T120000Z
DTEND:20231201T130000Z
SUMMARY:Test Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        
        $this->assertCount(1, $events);
        $this->assertInstanceOf(VEvent::class, $events[0]);
        $this->assertEquals('Test Event', (string)$events[0]->SUMMARY);
    }
    
    public function testCanFilterEventsByDateRange(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:event1@example.com
DTSTART:20231201T120000Z
DTEND:20231201T130000Z
SUMMARY:December Event
END:VEVENT
BEGIN:VEVENT
UID:event2@example.com
DTSTART:20240115T120000Z
DTEND:20240115T130000Z
SUMMARY:January Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $allEvents = $this->handler->parseIcsString($icsContent);
        $filteredEvents = $this->handler->filterEventsByDateRange(
            $allEvents,
            new DateTime('2023-12-01'),
            new DateTime('2023-12-31')
        );
        
        $this->assertCount(1, $filteredEvents);
        $this->assertEquals('December Event', (string)$filteredEvents[0]->SUMMARY);
    }
    
    public function testCanExpandRecurringEvents(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:recurring@example.com
DTSTART:20231201T120000Z
DTEND:20231201T130000Z
SUMMARY:Weekly Meeting
RRULE:FREQ=WEEKLY;COUNT=3
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $expandedEvents = $this->handler->expandRecurringEvents(
            $events,
            new DateTime('2023-12-01'),
            new DateTime('2023-12-31')
        );
        
        $this->assertCount(3, $expandedEvents);
        foreach ($expandedEvents as $event) {
            $this->assertEquals('Weekly Meeting', (string)$event->SUMMARY);
        }
    }
    
    public function testCanMergeMultipleCalendars(): void
    {
        $ics1 = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test1//Test1//EN
BEGIN:VEVENT
UID:event1@example.com
DTSTART:20231201T120000Z
DTEND:20231201T130000Z
SUMMARY:Event 1
END:VEVENT
END:VCALENDAR
ICS;
        
        $ics2 = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test2//Test2//EN
BEGIN:VEVENT
UID:event2@example.com
DTSTART:20231202T120000Z
DTEND:20231202T130000Z
SUMMARY:Event 2
END:VEVENT
END:VCALENDAR
ICS;
        
        $events1 = $this->handler->parseIcsString($ics1);
        $events2 = $this->handler->parseIcsString($ics2);
        
        $mergedEvents = $this->handler->mergeEvents([$events1, $events2]);
        
        $this->assertCount(2, $mergedEvents);
        $summaries = array_map(fn($event) => (string)$event->SUMMARY, $mergedEvents);
        $this->assertContains('Event 1', $summaries);
        $this->assertContains('Event 2', $summaries);
    }
    
    public function testCanConvertEventsToIcsString(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:test@example.com
DTSTART:20231201T120000Z
DTEND:20231201T130000Z
SUMMARY:Test Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $outputIcs = $this->handler->eventsToIcsString($events);
        
        $this->assertStringContainsString('BEGIN:VCALENDAR', $outputIcs);
        $this->assertStringContainsString('BEGIN:VEVENT', $outputIcs);
        $this->assertStringContainsString('SUMMARY:Test Event', $outputIcs);
        $this->assertStringContainsString('END:VEVENT', $outputIcs);
        $this->assertStringContainsString('END:VCALENDAR', $outputIcs);
    }
    
    public function testExcludesUnwantedProperties(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:test@example.com
DTSTART:20231201T120000Z
DTEND:20231201T130000Z
SUMMARY:Test Event
ATTENDEE:mailto:attendee@example.com
ORGANIZER:mailto:organizer@example.com
DESCRIPTION:Test Description
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $outputIcs = $this->handler->eventsToIcsString($events);
        
        // Should contain the event but not ATTENDEE or ORGANIZER
        $this->assertStringContainsString('SUMMARY:Test Event', $outputIcs);
        $this->assertStringContainsString('DESCRIPTION:Test Description', $outputIcs);
        $this->assertStringNotContainsString('ATTENDEE:', $outputIcs);
        $this->assertStringNotContainsString('ORGANIZER:', $outputIcs);
    }
    
    public function testCanConfigureExcludedProperties(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:test@example.com
DTSTART:20231201T120000Z
DTEND:20231201T130000Z
SUMMARY:Test Event
ATTENDEE:mailto:attendee@example.com
DESCRIPTION:Test Description
LOCATION:Test Location
END:VEVENT
END:VCALENDAR
ICS;
        
        // Add LOCATION to excluded properties
        $this->handler->addExcludedProperty('LOCATION');
        
        $events = $this->handler->parseIcsString($icsContent);
        $outputIcs = $this->handler->eventsToIcsString($events);
        
        // Should exclude ATTENDEE (default) and LOCATION (added)
        $this->assertStringContainsString('SUMMARY:Test Event', $outputIcs);
        $this->assertStringContainsString('DESCRIPTION:Test Description', $outputIcs);
        $this->assertStringNotContainsString('ATTENDEE:', $outputIcs);
        $this->assertStringNotContainsString('LOCATION:', $outputIcs);
        
        // Remove ATTENDEE from exclusion list
        $this->handler->removeExcludedProperty('ATTENDEE');
        
        $outputIcs2 = $this->handler->eventsToIcsString($events);
        
        // Now ATTENDEE should be included, but LOCATION still excluded
        $this->assertStringContainsString('ATTENDEE:', $outputIcs2);
        $this->assertStringNotContainsString('LOCATION:', $outputIcs2);
    }

    public function testProcessCalendarRequestRemovesAttendeeProperties(): void
    {
        // Use the example_with_timezone fixture that contains ATTENDEE properties
        $fixtureFile = __DIR__ . '/../fixtures/example_with_timezone.ics';
        
        // Verify the fixture file exists and contains ATTENDEE
        $this->assertFileExists($fixtureFile);
        $originalContent = file_get_contents($fixtureFile);
        $this->assertStringContainsString('ATTENDEE:', $originalContent);
        $this->assertStringContainsString('ATTENDEE:mailto:icke@da.de', $originalContent);
        $this->assertStringContainsString('ATTENDEE:meinereiner', $originalContent);
        
        // Process the calendar request
        $result = $this->handler->processCalendarRequest($fixtureFile, [
            'from' => '2024-04-01',
            'to' => '2024-05-31',
            'format' => 'ics'
        ]);
        
        // Verify the result structure
        $this->assertArrayHasKey('contentType', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('text/calendar', $result['contentType']);
        
        // Verify the output contains the events but no ATTENDEE properties
        $outputIcs = $result['data'];
        $this->assertStringContainsString('BEGIN:VCALENDAR', $outputIcs);
        $this->assertStringContainsString('BEGIN:VEVENT', $outputIcs);
        $this->assertStringContainsString('Test Event with Timezone', $outputIcs);
        $this->assertStringContainsString('Another Test Event', $outputIcs);
        
        // Most importantly: verify that ATTENDEE properties are removed
        $this->assertStringNotContainsString('ATTENDEE:', $outputIcs);
        $this->assertStringNotContainsString('ATTENDEE:mailto:icke@da.de', $outputIcs);
        $this->assertStringNotContainsString('ATTENDEE:meinereiner', $outputIcs);
        
        // Verify other properties are still present
        $this->assertStringContainsString('SUMMARY:', $outputIcs);
        $this->assertStringContainsString('DESCRIPTION:', $outputIcs);
        $this->assertStringContainsString('LOCATION:', $outputIcs);
    }

    public function testProcessCalendarRequestRemovesAttendeeFromRecurringEvents(): void
    {
        // Use the recurring_events_with_source fixture that contains ATTENDEE properties in recurring events
        $fixtureFile = __DIR__ . '/../fixtures/recurring_events_with_source.ics';
        
        // Verify the fixture file exists and contains ATTENDEE in recurring events
        $this->assertFileExists($fixtureFile);
        $originalContent = file_get_contents($fixtureFile);
        $this->assertStringContainsString('ATTENDEE:', $originalContent);
        $this->assertStringContainsString('ATTENDEE:mailto:icke@da.de', $originalContent);
        $this->assertStringContainsString('ATTENDEE:meinereiner', $originalContent);
        $this->assertStringContainsString('RRULE:', $originalContent); // Verify it has recurring events
        
        // Process the calendar request with a date range that includes the recurring events
        $result = $this->handler->processCalendarRequest($fixtureFile, [
            'from' => '2024-05-01',
            'to' => '2024-06-30',
            'format' => 'ics'
        ]);
        
        // Verify the result structure
        $this->assertArrayHasKey('contentType', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('text/calendar', $result['contentType']);
        
        // Verify the output contains the events including expanded recurring events
        $outputIcs = $result['data'];
        $this->assertStringContainsString('BEGIN:VCALENDAR', $outputIcs);
        $this->assertStringContainsString('BEGIN:VEVENT', $outputIcs);
        $this->assertStringContainsString('Source A Event 1', $outputIcs);
        $this->assertStringContainsString('Source B Event 2 (recurring)', $outputIcs);
        
        // Most importantly: verify that ATTENDEE properties are removed from all events,
        // including the expanded recurring events
        $this->assertStringNotContainsString('ATTENDEE:', $outputIcs);
        $this->assertStringNotContainsString('ATTENDEE:mailto:icke@da.de', $outputIcs);
        $this->assertStringNotContainsString('ATTENDEE:meinereiner', $outputIcs);
        
        // Verify other properties are still present
        $this->assertStringContainsString('SUMMARY:', $outputIcs);
        $this->assertStringContainsString('DESCRIPTION:', $outputIcs);
        $this->assertStringContainsString('LOCATION:', $outputIcs);
        $this->assertStringContainsString('X-WR-SOURCE:', $outputIcs);
        
        // Verify that recurring events are properly expanded (should have multiple instances)
        // Count occurrences of the recurring event summary
        $summaryCount = substr_count($outputIcs, 'Source B Event 2 (recurring)');
        $this->assertGreaterThan(1, $summaryCount, 'Recurring event should be expanded into multiple instances');
    }
} 