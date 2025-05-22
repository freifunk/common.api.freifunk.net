<?php

namespace Tests;

use ICal\EventObject;
use ICal\EventObjectBuilder;
use PHPUnit\Framework\TestCase;

class EventObjectTest extends TestCase
{
    public function testPrintIcsPreservesTimezoneInfo()
    {
        // Verwende den Builder anstatt direkt ein Array zu verwenden
        $event = (new EventObjectBuilder())
            ->summary('Test Event')
            ->dtstart('20240312T200000')
            ->dtend('20240312T220000')
            ->dtstartArray([
                0 => ['TZID' => 'Europe/Berlin'],
                1 => '20240312T200000',
                2 => 1710270000 // Unix-Timestamp
            ])
            ->dtendArray([
                0 => ['TZID' => 'Europe/Berlin'],
                1 => '20240312T220000',
                2 => 1710277200 // Unix-Timestamp
            ])
            ->uid('test-event-123@example.com')
            ->build();
        
        // Konvertiere zu ICS
        $icsOutput = $event->printIcs();
        
        // Überprüfe, ob die TZID-Informationen erhalten bleiben
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20240312T200000', $icsOutput);
        $this->assertStringContainsString('DTEND;TZID=Europe/Berlin:20240312T220000', $icsOutput);
    }
    
    public function testPrintIcsFallsBackToSimpleDateTimeWhenNoTimezone()
    {
        // Verwende den Builder anstatt direkt ein Array zu verwenden
        $event = (new EventObjectBuilder())
            ->summary('Test Event')
            ->dtstart('20240312T200000Z')
            ->dtend('20240312T220000Z')
            ->uid('test-event-123@example.com')
            ->build();
        
        // Konvertiere zu ICS
        $icsOutput = $event->printIcs();
        
        // Überprüfe die Formatierung ohne TZID
        $this->assertStringContainsString('DTSTART:20240312T200000Z', $icsOutput);
        $this->assertStringContainsString('DTEND:20240312T220000Z', $icsOutput);
    }
    
    public function testBuilderCanCreateComplexEvent()
    {
        // Teste einen komplexen Event mit weiteren Eigenschaften
        $event = (new EventObjectBuilder())
            ->summary('Complex Test Event')
            ->description('This is a test event created with the builder')
            ->location('Test Location')
            ->dtstart('20240312T200000')
            ->dtend('20240312T220000')
            ->dtstartTz('Europe/Berlin')
            ->dtendTz('Europe/Berlin')
            ->dtstartArray([
                0 => ['TZID' => 'Europe/Berlin'],
                1 => '20240312T200000',
                2 => 1710270000
            ])
            ->dtendArray([
                0 => ['TZID' => 'Europe/Berlin'],
                1 => '20240312T220000',
                2 => 1710277200
            ])
            ->uid('complex-test-event-456@example.com')
            ->status('CONFIRMED')
            ->created('20240301T120000Z')
            ->dtstamp('20240301T120000Z')
            ->rrule('FREQ=MONTHLY;BYDAY=2TH')
            ->transp('OPAQUE')
            ->sequence('0')
            ->build();
            
        // Validiere einige der Eigenschaften
        $this->assertEquals('Complex Test Event', $event->getSummary());
        $this->assertEquals('This is a test event created with the builder', $event->getDescription());
        $this->assertEquals('Test Location', $event->getLocation());
        $this->assertEquals('complex-test-event-456@example.com', $event->getUid());
        $this->assertEquals('CONFIRMED', $event->getStatus());
        
        // Überprüfe die ICS-Ausgabe
        $icsOutput = $event->printIcs();
        $this->assertStringContainsString('SUMMARY:Complex Test Event', $icsOutput);
        $this->assertStringContainsString('DESCRIPTION:This is a test event created with the builder', $icsOutput);
        $this->assertStringContainsString('LOCATION:Test Location', $icsOutput);
        $this->assertStringContainsString('STATUS:CONFIRMED', $icsOutput);
        $this->assertStringContainsString('RRULE:FREQ=MONTHLY;BYDAY=2TH', $icsOutput);
    }
}
