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
        
        // After expansion with Sabre VObject, the events are converted to UTC
        // This is expected behavior for the expand functionality
        foreach ($expandedEvents as $event) {
            // Check that the event has proper time information
            $this->assertNotNull($event->DTSTART);
            $this->assertEquals('Recurring Floating Event', (string)$event->SUMMARY);
            
            // The events might be in UTC after expansion, which is acceptable
            $dtstart = (string)$event->DTSTART;
            $this->assertTrue(
                substr($dtstart, -1) === 'Z' || $event->DTSTART->offsetGet('TZID') !== null,
                'Event should have either UTC time (Z suffix) or TZID parameter'
            );
        }
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