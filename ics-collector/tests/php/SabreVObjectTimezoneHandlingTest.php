<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use ICal\SabreVObjectCalendarHandler;
use DateTime;
use DateTimeZone;

class SabreVObjectTimezoneHandlingTest extends TestCase
{
    private SabreVObjectCalendarHandler $handler;
    
    protected function setUp(): void
    {
        $this->handler = new SabreVObjectCalendarHandler('Europe/Berlin');
    }
    
    public function testFloatingTimeEventsGetDefaultTimezone(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:floating@example.com
DTSTART:20231201T120000
DTEND:20231201T130000
SUMMARY:Floating Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        
        $this->assertCount(1, $events);
        $event = $events[0];
        
        // Check that TZID parameter is now set
        $tzidParam = $event->DTSTART->offsetGet('TZID');
        $this->assertNotNull($tzidParam);
        $this->assertEquals('Europe/Berlin', $tzidParam);
        
        // Check DTEND as well
        $tzidParam = $event->DTEND->offsetGet('TZID');
        $this->assertNotNull($tzidParam);
        $this->assertEquals('Europe/Berlin', $tzidParam);
    }
    
    public function testCalendarLevelTimezoneIsUsed(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
X-WR-TIMEZONE:America/New_York
BEGIN:VEVENT
UID:calendar-tz@example.com
DTSTART:20231201T120000
DTEND:20231201T130000
SUMMARY:Calendar TZ Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        
        $this->assertCount(1, $events);
        $event = $events[0];
        
        // Check that calendar-level timezone is used
        $tzidParam = $event->DTSTART->offsetGet('TZID');
        $this->assertNotNull($tzidParam);
        $this->assertEquals('America/New_York', $tzidParam);
    }
    
    public function testExistingTzidIsPreserved(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
X-WR-TIMEZONE:Europe/Berlin
BEGIN:VEVENT
UID:existing-tz@example.com
DTSTART;TZID=Europe/Paris:20231201T120000
DTEND;TZID=Europe/Paris:20231201T130000
SUMMARY:Existing TZ Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        
        $this->assertCount(1, $events);
        $event = $events[0];
        
        // Existing TZID should be preserved
        $tzidParam = $event->DTSTART->offsetGet('TZID');
        $this->assertNotNull($tzidParam);
        $this->assertEquals('Europe/Paris', $tzidParam);
    }
    
    public function testUtcEventsAreNotModified(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:utc@example.com
DTSTART:20231201T120000Z
DTEND:20231201T130000Z
SUMMARY:UTC Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        
        $this->assertCount(1, $events);
        $event = $events[0];
        
        // UTC events should not get TZID parameter
        $tzidParam = $event->DTSTART->offsetGet('TZID');
        $this->assertNull($tzidParam);
        
        // Check the value still ends with Z
        $this->assertStringEndsWith('Z', (string)$event->DTSTART);
    }
    
    public function testSerializedOutputContainsTzid(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:serialization@example.com
DTSTART:20231201T120000
DTEND:20231201T130000
SUMMARY:Serialization Test
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $outputIcs = $this->handler->eventsToIcsString($events);
        
        // Check that the output contains TZID parameters
        $this->assertStringContainsString('TZID=Europe/Berlin', $outputIcs);
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20231201T120000', $outputIcs);
        $this->assertStringContainsString('DTEND;TZID=Europe/Berlin:20231201T130000', $outputIcs);
    }
    
    public function testRecurringEventExpansionWithFloatingTime(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
X-WR-TIMEZONE:Europe/Berlin
BEGIN:VEVENT
UID:recurring-floating@example.com
DTSTART:20231201T120000
DTEND:20231201T130000
SUMMARY:Recurring Floating Event
RRULE:FREQ=DAILY;COUNT=3
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
        
        // After expansion, Sabre converts to UTC -- verify that
        foreach ($expandedEvents as $event) {
            $this->assertNotNull($event->DTSTART);
            $this->assertEquals('Recurring Floating Event', (string)$event->SUMMARY);
            $dtstart = (string)$event->DTSTART;
            $this->assertTrue(
                substr($dtstart, -1) === 'Z' || $event->DTSTART->offsetGet('TZID') !== null,
                'Event should have either UTC time (Z suffix) or TZID parameter'
            );
        }
    }
    
    public function testConvertEventsToTimezoneConvertsUtcToBerlin(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
X-WR-TIMEZONE:Europe/Berlin
BEGIN:VEVENT
UID:recurring-convert@example.com
DTSTART;TZID=Europe/Berlin:20231201T120000
DTEND;TZID=Europe/Berlin:20231201T130000
SUMMARY:Recurring Berlin Event
RRULE:FREQ=DAILY;COUNT=3
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $expandedEvents = $this->handler->expandRecurringEvents(
            $events,
            new DateTime('2023-12-01'),
            new DateTime('2023-12-31')
        );
        $convertedEvents = $this->handler->convertEventsToTimezone($expandedEvents);
        
        $this->assertCount(3, $convertedEvents);
        
        foreach ($convertedEvents as $event) {
            $tzid = $event->DTSTART->offsetGet('TZID');
            $this->assertNotNull($tzid, 'DTSTART should have TZID after conversion');
            $this->assertEquals('Europe/Berlin', (string)$tzid);
            
            $value = (string)$event->DTSTART;
            $this->assertStringNotContainsString('Z', $value,
                'DTSTART should not end with Z after conversion to Europe/Berlin');
        }
        
        // First event should be 12:00 Berlin time
        $this->assertStringContainsString('20231201T120000', (string)$convertedEvents[0]->DTSTART);
    }
    
    public function testConvertEventsToTimezonePreservesExistingBerlinTzid(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:already-berlin@example.com
DTSTART;TZID=Europe/Berlin:20231201T200000
DTEND;TZID=Europe/Berlin:20231201T230000
SUMMARY:Already Berlin Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $convertedEvents = $this->handler->convertEventsToTimezone($events);
        
        $this->assertCount(1, $convertedEvents);
        $event = $convertedEvents[0];
        
        $this->assertEquals('Europe/Berlin', (string)$event->DTSTART->offsetGet('TZID'));
        $this->assertStringContainsString('20231201T200000', (string)$event->DTSTART);
    }
    
    public function testConvertEventsToTimezoneConvertsUtcNonRecurring(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:utc-convert@example.com
DTSTART:20231201T110000Z
DTEND:20231201T120000Z
SUMMARY:UTC Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $convertedEvents = $this->handler->convertEventsToTimezone($events);
        
        $event = $convertedEvents[0];
        $this->assertEquals('Europe/Berlin', (string)$event->DTSTART->offsetGet('TZID'));
        // 11:00 UTC = 12:00 CET (December = winter time)
        $this->assertStringContainsString('20231201T120000', (string)$event->DTSTART);
    }
    
    public function testConvertEventsToTimezoneHandlesSummerTime(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:summer-utc@example.com
DTSTART:20230715T160000Z
DTEND:20230715T170000Z
SUMMARY:Summer UTC Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $convertedEvents = $this->handler->convertEventsToTimezone($events);
        
        $event = $convertedEvents[0];
        $this->assertEquals('Europe/Berlin', (string)$event->DTSTART->offsetGet('TZID'));
        // 16:00 UTC = 18:00 CEST (July = summer time, UTC+2)
        $this->assertStringContainsString('20230715T180000', (string)$event->DTSTART);
    }
    
    public function testConvertEventsToTimezoneSkipsAllDayEvents(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:allday-convert@example.com
DTSTART;VALUE=DATE:20231201
DTEND;VALUE=DATE:20231202
SUMMARY:All Day Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $convertedEvents = $this->handler->convertEventsToTimezone($events);
        
        $event = $convertedEvents[0];
        $tzid = $event->DTSTART->offsetGet('TZID');
        $this->assertNull($tzid, 'All-day events should not get TZID');
    }
    
    public function testConvertEventsToTimezoneConvertsOtherTimezone(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:newyork@example.com
DTSTART;TZID=America/New_York:20231201T120000
DTEND;TZID=America/New_York:20231201T130000
SUMMARY:New York Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $convertedEvents = $this->handler->convertEventsToTimezone($events);
        
        $event = $convertedEvents[0];
        $this->assertEquals('Europe/Berlin', (string)$event->DTSTART->offsetGet('TZID'));
        // 12:00 EST = 18:00 CET (December, NY=UTC-5, Berlin=UTC+1 -> +6h)
        $this->assertStringContainsString('20231201T180000', (string)$event->DTSTART);
    }
    
    public function testExpandedRecurringEventsHaveBerlinTzidInSerializedOutput(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
X-WR-TIMEZONE:Europe/Berlin
BEGIN:VEVENT
UID:expand-serialize@example.com
DTSTART;TZID=Europe/Berlin:20231201T190000
DTEND;TZID=Europe/Berlin:20231201T200000
SUMMARY:Expand and Serialize
RRULE:FREQ=DAILY;COUNT=2
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $expandedEvents = $this->handler->expandRecurringEvents(
            $events,
            new DateTime('2023-12-01'),
            new DateTime('2023-12-31')
        );
        $convertedEvents = $this->handler->convertEventsToTimezone($expandedEvents);
        $outputIcs = $this->handler->eventsToIcsString($convertedEvents);
        
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20231201T190000', $outputIcs);
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20231202T190000', $outputIcs);
        $this->assertStringNotContainsString('DTSTART:2023120', $outputIcs);
    }
    
    public function testAllDayEventsAreNotModified(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:allday@example.com
DTSTART;VALUE=DATE:20231201
DTEND;VALUE=DATE:20231202
SUMMARY:All Day Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        
        $this->assertCount(1, $events);
        $event = $events[0];
        
        // All-day events should not get TZID parameters
        $this->assertFalse($event->DTSTART->hasTime());
        $tzidParam = $event->DTSTART->offsetGet('TZID');
        $this->assertNull($tzidParam);
    }
} 