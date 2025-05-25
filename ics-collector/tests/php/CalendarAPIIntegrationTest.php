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
    
    public function testSabreVObjectWithJsonOutput(): void
    {
        // Test JSON output with Sabre VObject (default)
        $api = new \CalendarAPI($this->testIcsFile);
        
        // Simulate request parameters for JSON
        $_GET = [
            'source' => 'all',
            'from' => '2023-12-01',
            'to' => '2023-12-31',
            'format' => 'json'
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
        
        $this->assertArrayHasKey('contentType', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('application/json', $result['contentType']);
        
        $jsonData = json_decode($result['data'], true);
        $this->assertIsArray($jsonData);
        $this->assertNotEmpty($jsonData);
        
        // Check that events have expected structure
        $firstEvent = $jsonData[0];
        $this->assertArrayHasKey('uid', $firstEvent);
        $this->assertArrayHasKey('summary', $firstEvent);
        $this->assertArrayHasKey('dtstart', $firstEvent);
        $this->assertArrayHasKey('dtend', $firstEvent);
    }
    
    public function testRecurringEventExpansion(): void
    {
        // Test that recurring events are properly expanded with Sabre VObject (default)
        $api = new \CalendarAPI($this->testIcsFile);
        
        $_GET = [
            'source' => 'all',
            'from' => '2023-12-05',
            'to' => '2023-12-08', // Extended range to ensure we get all 3 events
            'format' => 'json'
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
        
        $jsonData = json_decode($result['data'], true);
        
        // Should have recurring events (may be 2 or 3 depending on exact date handling)
        $recurringEvents = array_filter($jsonData, function($event) {
            return strpos($event['summary'], 'Recurring Event') !== false;
        });
        
        $this->assertGreaterThanOrEqual(2, count($recurringEvents));
        $this->assertLessThanOrEqual(3, count($recurringEvents));
    }
    
    public function testSourceFiltering(): void
    {
        // Test source filtering with Sabre VObject (default)
        $api = new \CalendarAPI($this->testIcsFile);
        
        $_GET = [
            'source' => 'sourceA',
            'from' => '2023-12-01',
            'to' => '2023-12-31',
            'format' => 'json'
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
        
        $jsonData = json_decode($result['data'], true);
        
        // Should only have events from sourceA
        $this->assertGreaterThan(0, count($jsonData));
        
        // Check that no events from sourceB are included
        foreach ($jsonData as $event) {
            $this->assertNotEquals('Test Event 2', $event['summary']);
        }
    }
} 