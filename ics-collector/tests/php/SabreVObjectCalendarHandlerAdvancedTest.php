<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use ICal\SabreVObjectCalendarHandler;
use Sabre\VObject\Component\VEvent;
use DateTime;

class SabreVObjectCalendarHandlerAdvancedTest extends TestCase
{
    private SabreVObjectCalendarHandler $handler;
    
    protected function setUp(): void
    {
        $this->handler = new SabreVObjectCalendarHandler();
    }
    
    public function testCanFilterEventsBySource(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:event1@example.com
DTSTART:20231201T120000Z
DTEND:20231201T130000Z
SUMMARY:Source A Event
X-WR-SOURCE:sourceA
END:VEVENT
BEGIN:VEVENT
UID:event2@example.com
DTSTART:20231201T130000Z
DTEND:20231201T140000Z
SUMMARY:Source B Event
X-WR-SOURCE:sourceB
END:VEVENT
BEGIN:VEVENT
UID:event3@example.com
DTSTART:20231201T140000Z
DTEND:20231201T150000Z
SUMMARY:Another Source A Event
X-WR-SOURCE:sourceA
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $filteredEvents = $this->handler->filterEventsBySource($events, ['sourceA']);
        
        $this->assertCount(2, $filteredEvents);
        foreach ($filteredEvents as $event) {
            $this->assertEquals('sourceA', (string)$event->{'X-WR-SOURCE'});
        }
    }
    
    public function testCanApplyLimit(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:event1@example.com
DTSTART:20231201T120000Z
DTEND:20231201T130000Z
SUMMARY:Event 1
END:VEVENT
BEGIN:VEVENT
UID:event2@example.com
DTSTART:20231201T130000Z
DTEND:20231201T140000Z
SUMMARY:Event 2
END:VEVENT
BEGIN:VEVENT
UID:event3@example.com
DTSTART:20231201T140000Z
DTEND:20231201T150000Z
SUMMARY:Event 3
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $limitedEvents = $this->handler->limitEvents($events, 2);
        
        $this->assertCount(2, $limitedEvents);
        $this->assertEquals('Event 1', (string)$limitedEvents[0]->SUMMARY);
        $this->assertEquals('Event 2', (string)$limitedEvents[1]->SUMMARY);
    }
    
    public function testCanParseRelativeDateStrings(): void
    {
        // Test 'now' parsing
        $nowDate = $this->handler->parseDate('now');
        $this->assertInstanceOf(DateTime::class, $nowDate);
        
        // Test '+2 weeks' parsing
        $futureDate = $this->handler->parseDate('+2 weeks');
        $this->assertInstanceOf(DateTime::class, $futureDate);
        
        // Test absolute date parsing
        $absoluteDate = $this->handler->parseDate('2023-12-01');
        $this->assertInstanceOf(DateTime::class, $absoluteDate);
        $this->assertEquals('2023-12-01', $absoluteDate->format('Y-m-d'));
        
        // Test datetime parsing
        $datetime = $this->handler->parseDate('2023-12-01T12:00:00');
        $this->assertInstanceOf(DateTime::class, $datetime);
        $this->assertEquals('2023-12-01 12:00:00', $datetime->format('Y-m-d H:i:s'));
    }
    
    public function testCanHandleEventsWithTimezones(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VTIMEZONE
TZID:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
UID:timezone-event@example.com
DTSTART;TZID=Europe/Berlin:20231201T120000
DTEND;TZID=Europe/Berlin:20231201T130000
SUMMARY:Timezone Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        
        $this->assertCount(1, $events);
        $this->assertEquals('Timezone Event', (string)$events[0]->SUMMARY);
        
        // Test that timezone information is preserved
        $dtstart = $events[0]->DTSTART;
        $this->assertNotNull($dtstart->getDateTime());
        // Check if the event has timezone parameters
        $params = $dtstart->parameters();
        $this->assertTrue(isset($params['TZID']) || $dtstart->getDateTime()->getTimezone()->getName() !== 'UTC');
    }
    
    public function testCanHandleAllDayEvents(): void
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
        $this->assertEquals('All Day Event', (string)$events[0]->SUMMARY);
        
        // Verify it's treated as all-day
        $dtstart = $events[0]->DTSTART;
        $this->assertTrue($dtstart->hasTime() === false);
    }
    
    public function testCanHandleEventWithoutEndDate(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:no-end@example.com
DTSTART:20231201T120000Z
SUMMARY:Event Without End
DURATION:PT1H
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        
        $this->assertCount(1, $events);
        $this->assertEquals('Event Without End', (string)$events[0]->SUMMARY);
        
        // Check if DURATION is handled properly
        $this->assertNotNull($events[0]->DURATION);
    }
    
    public function testCanSortEventsByStartDate(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:event1@example.com
DTSTART:20231203T120000Z
DTEND:20231203T130000Z
SUMMARY:Third Event
END:VEVENT
BEGIN:VEVENT
UID:event2@example.com
DTSTART:20231201T120000Z
DTEND:20231201T130000Z
SUMMARY:First Event
END:VEVENT
BEGIN:VEVENT
UID:event3@example.com
DTSTART:20231202T120000Z
DTEND:20231202T130000Z
SUMMARY:Second Event
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $sortedEvents = $this->handler->sortEventsByStartDate($events);
        
        $this->assertCount(3, $sortedEvents);
        $this->assertEquals('First Event', (string)$sortedEvents[0]->SUMMARY);
        $this->assertEquals('Second Event', (string)$sortedEvents[1]->SUMMARY);
        $this->assertEquals('Third Event', (string)$sortedEvents[2]->SUMMARY);
    }
    
    public function testCanValidateEventData(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
BEGIN:VEVENT
UID:valid@example.com
DTSTART:20231201T120000Z
DTEND:20231201T130000Z
SUMMARY:Valid Event
DTSTAMP:20231201T100000Z
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $isValid = $this->handler->validateEvent($events[0]);
        
        $this->assertTrue($isValid);
    }
    
    public function testCanHandleEmptyCalendar(): void
    {
        $icsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        
        $this->assertCount(0, $events);
    }
} 