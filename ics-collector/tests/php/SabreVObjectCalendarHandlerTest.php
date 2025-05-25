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
    
    public function testCanConvertEventsToJsonArray(): void
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
DESCRIPTION:Test Description
END:VEVENT
END:VCALENDAR
ICS;
        
        $events = $this->handler->parseIcsString($icsContent);
        $jsonArray = $this->handler->eventsToJsonArray($events);
        
        $this->assertCount(1, $jsonArray);
        $this->assertEquals('test@example.com', $jsonArray[0]['uid']);
        $this->assertEquals('Test Event', $jsonArray[0]['summary']);
        $this->assertEquals('Test Description', $jsonArray[0]['description']);
        $this->assertArrayHasKey('dtstart', $jsonArray[0]);
        $this->assertArrayHasKey('dtend', $jsonArray[0]);
    }
} 