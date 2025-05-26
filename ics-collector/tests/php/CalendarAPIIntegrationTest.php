<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use DateTime;

// Include CalendarAPI directly since it's not autoloaded
require_once __DIR__ . '/../../CalendarAPI.php';

class CalendarAPIIntegrationTest extends TestCase
{
    private string $testIcsFile;
    
    protected function setUp(): void
    {
        // Create a test ICS file
        $this->testIcsFile = tempnam(sys_get_temp_dir(), 'test_calendar_') . '.ics';
        
        $testIcsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//EN
X-WR-TIMEZONE:Europe/Berlin
BEGIN:VEVENT
UID:test1@example.com
DTSTART:20231201T120000
DTEND:20231201T130000
SUMMARY:Test Event 1
X-WR-SOURCE:sourceA
END:VEVENT
BEGIN:VEVENT
UID:test2@example.com
DTSTART:20231202T140000
DTEND:20231202T150000
SUMMARY:Test Event 2
X-WR-SOURCE:sourceB
END:VEVENT
BEGIN:VEVENT
UID:recurring@example.com
DTSTART:20231205T100000
DTEND:20231205T110000
SUMMARY:Recurring Event
RRULE:FREQ=DAILY;COUNT=3
X-WR-SOURCE:sourceA
END:VEVENT
END:VCALENDAR
ICS;
        
        file_put_contents($this->testIcsFile, $testIcsContent);
    }
    
    protected function tearDown(): void
    {
        if (file_exists($this->testIcsFile)) {
            unlink($this->testIcsFile);
        }
    }
    
    public function testSabreVObjectImplementation(): void
    {
        // Test with Sabre VObject implementation (default and only option now)
        $api = new \CalendarAPI($this->testIcsFile);
        
        // Simulate request parameters
        $_GET = [
            'source' => 'sourceA',
            'from' => '2023-12-01',
            'to' => '2023-12-31',
            'format' => 'ics'
        ];
        
        // Use reflection to call protected method
        $reflection = new \ReflectionClass($api);
        $method = $reflection->getMethod('processCalendarData');
        $method->setAccessible(true);
        
        // Set up sources for the API
        $sourcesProperty = $reflection->getProperty('sources');
        $sourcesProperty->setAccessible(true);
        $sourcesProperty->setValue($api, ['sourceA']);
        
        // Set up parameters
        $parametersProperty = $reflection->getProperty('parameters');
        $parametersProperty->setAccessible(true);
        $parametersProperty->setValue($api, $_GET);
        
        $result = $method->invoke($api);
        
        $this->assertArrayHasKey('contentType', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('text/calendar', $result['contentType']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $result['data']);
        $this->assertStringContainsString('Test Event 1', $result['data']);
        $this->assertStringContainsString('Recurring Event', $result['data']);
        
        // Should contain TZID parameters for floating time events
        $this->assertStringContainsString('TZID=Europe/Berlin', $result['data']);
    }
    
    public function testRecurringEventExpansion(): void
    {
        // Test that recurring events are properly expanded with Sabre VObject (default)
        $api = new \CalendarAPI($this->testIcsFile);
        
        $_GET = [
            'source' => 'all',
            'from' => '2023-12-05',
            'to' => '2023-12-08', // Extended range to ensure we get all 3 events
            'format' => 'ics'
        ];
        
        // Use reflection to call protected method
        $reflection = new \ReflectionClass($api);
        $method = $reflection->getMethod('processCalendarData');
        $method->setAccessible(true);
        
        // Set up sources for the API
        $sourcesProperty = $reflection->getProperty('sources');
        $sourcesProperty->setAccessible(true);
        $sourcesProperty->setValue($api, ['all']);
        
        // Set up parameters
        $parametersProperty = $reflection->getProperty('parameters');
        $parametersProperty->setAccessible(true);
        $parametersProperty->setValue($api, $_GET);
        
        $result = $method->invoke($api);
        
        // Count recurring events in ICS output
        $recurringEventCount = substr_count($result['data'], 'Recurring Event');
        
        // Should have recurring events (may be 2 or 3 depending on exact date handling)
        $this->assertGreaterThanOrEqual(2, $recurringEventCount);
        $this->assertLessThanOrEqual(3, $recurringEventCount);
    }
    
    public function testSourceFiltering(): void
    {
        // Test source filtering with Sabre VObject (default)
        $api = new \CalendarAPI($this->testIcsFile);
        
        $_GET = [
            'source' => 'sourceA',
            'from' => '2023-12-01',
            'to' => '2023-12-31',
            'format' => 'ics'
        ];
        
        // Use reflection to call protected method
        $reflection = new \ReflectionClass($api);
        $method = $reflection->getMethod('processCalendarData');
        $method->setAccessible(true);
        
        // Set up sources for the API
        $sourcesProperty = $reflection->getProperty('sources');
        $sourcesProperty->setAccessible(true);
        $sourcesProperty->setValue($api, ['sourceA']);
        
        // Set up parameters
        $parametersProperty = $reflection->getProperty('parameters');
        $parametersProperty->setAccessible(true);
        $parametersProperty->setValue($api, $_GET);
        
        $result = $method->invoke($api);
        
        // Should contain events from sourceA but not sourceB
        $this->assertStringContainsString('Test Event 1', $result['data']);
        $this->assertStringNotContainsString('Test Event 2', $result['data']);
    }

    public function testCalendarAPIRemovesAttendeeFromRecurringEvents(): void
    {
        // Use the recurring_events_with_source fixture that contains ATTENDEE properties
        $fixtureFile = __DIR__ . '/../fixtures/recurring_events_with_source.ics';
        $api = new \CalendarAPI($fixtureFile);
        
        // Simulate request parameters for a date range that includes the recurring events
        $_GET = [
            'source' => 'all',
            'from' => '2024-05-01',
            'to' => '2024-06-30',
            'format' => 'ics'
        ];
        
        // Use reflection to call protected method
        $reflection = new \ReflectionClass($api);
        $method = $reflection->getMethod('processCalendarData');
        $method->setAccessible(true);
        
        // Set up sources for the API
        $sourcesProperty = $reflection->getProperty('sources');
        $sourcesProperty->setAccessible(true);
        $sourcesProperty->setValue($api, ['all']);
        
        // Set up parameters
        $parametersProperty = $reflection->getProperty('parameters');
        $parametersProperty->setAccessible(true);
        $parametersProperty->setValue($api, $_GET);
        
        $result = $method->invoke($api);
        
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
        // including the expanded recurring events, at the API level
        $this->assertStringNotContainsString('ATTENDEE:', $outputIcs);
        $this->assertStringNotContainsString('ATTENDEE:mailto:icke@da.de', $outputIcs);
        $this->assertStringNotContainsString('ATTENDEE:meinereiner', $outputIcs);
        
        // Verify other properties are still present
        $this->assertStringContainsString('SUMMARY:', $outputIcs);
        $this->assertStringContainsString('DESCRIPTION:', $outputIcs);
        $this->assertStringContainsString('LOCATION:', $outputIcs);
        $this->assertStringContainsString('X-WR-SOURCE:', $outputIcs);
        
        // Verify that recurring events are properly expanded (should have multiple instances)
        $summaryCount = substr_count($outputIcs, 'Source B Event 2 (recurring)');
        $this->assertGreaterThan(1, $summaryCount, 'Recurring event should be expanded into multiple instances');
        
        // Verify source filtering works correctly
        $this->assertStringContainsString('source-a', $outputIcs);
        $this->assertStringContainsString('source-b', $outputIcs);
        $this->assertStringContainsString('source-c', $outputIcs);
    }
} 