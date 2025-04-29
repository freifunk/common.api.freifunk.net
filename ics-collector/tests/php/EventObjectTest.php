<?php

namespace Tests;

use ICal\EventObject;
use PHPUnit\Framework\TestCase;

class EventObjectTest extends TestCase
{
    public function testPrintIcsPreservesTimezoneInfo()
    {
        // Erstelle ein Event-Objekt mit TZID-Informationen
        $eventData = [
            'summary' => 'Test Event',
            'dtstart' => '20240312T200000',
            'dtend' => '20240312T220000',
            'dtstart_array' => [
                0 => ['TZID' => 'Europe/Berlin'],
                1 => '20240312T200000',
                2 => 1710270000 // Unix-Timestamp
            ],
            'dtend_array' => [
                0 => ['TZID' => 'Europe/Berlin'],
                1 => '20240312T220000',
                2 => 1710277200 // Unix-Timestamp
            ],
            'uid' => 'test-event-123@example.com'
        ];
        
        $event = new EventObject($eventData);
        
        // Konvertiere zu ICS
        $icsOutput = $event->printIcs();
        
        // Überprüfe, ob die TZID-Informationen erhalten bleiben
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20240312T200000', $icsOutput);
        $this->assertStringContainsString('DTEND;TZID=Europe/Berlin:20240312T220000', $icsOutput);
    }
    
    public function testPrintIcsFallsBackToSimpleDateTimeWhenNoTimezone()
    {
        // Erstelle ein Event-Objekt ohne TZID-Informationen
        $eventData = [
            'summary' => 'Test Event',
            'dtstart' => '20240312T200000Z',
            'dtend' => '20240312T220000Z',
            'uid' => 'test-event-123@example.com'
        ];
        
        $event = new EventObject($eventData);
        
        // Konvertiere zu ICS
        $icsOutput = $event->printIcs();
        
        // Überprüfe die Formatierung ohne TZID
        $this->assertStringContainsString('DTSTART:20240312T200000Z', $icsOutput);
        $this->assertStringContainsString('DTEND:20240312T220000Z', $icsOutput);
    }
}
