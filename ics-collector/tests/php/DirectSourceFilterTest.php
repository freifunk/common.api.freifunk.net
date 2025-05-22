<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Direkter Test für das Filtern von Events nach X-WR-SOURCE
 */
class DirectSourceFilterTest extends TestCase
{
    /**
     * Test, ob Events direkt mit der ICal-Klasse nach X-WR-SOURCE gefiltert werden können
     */
    public function testDirectSourceFiltering()
    {
        // Lade die notwendigen Klassen
        require_once __DIR__ . '/../../lib/ICal.php';
        
        // Testdatei mit Events von verschiedenen Sources
        $testFile = __DIR__ . '/../fixtures/recurring_events_with_source.ics';
        $this->assertTrue(file_exists($testFile), 'Die Testdatei recurring_events_with_source.ics muss existieren');
        
        // Stellen sicher, dass die Datei direkt aus dem Dateisystem geladen wird
        $fileContent = file_get_contents($testFile);
        $this->assertNotEmpty($fileContent, 'Die Testdatei sollte Inhalt haben');
        $this->assertStringContainsString('X-WR-SOURCE:source-a', $fileContent, 'Die Testdatei sollte source-a enthalten');
        
        // Parse die ICS-Datei direkt mit initString statt über den Constructor
        $parsedIcs = new \ICal\ICal(false, 'MO', false, true);
        $parsedIcs->initString($fileContent);
        
        // Verarbeite Datum-Konversionen für Zeitzonen
        $parsedIcs->processDateConversions();
        
        // Hole alle Events
        $allEvents = $parsedIcs->events();
        $this->assertGreaterThan(0, count($allEvents), 'Die Testdatei sollte Events enthalten');
        
        // Teste das Filtern nach Source A
        $sourceAEvents = array_filter($allEvents, function($event) {
            return $event->getXWrSource() === 'source-a';
        });
        
        $this->assertEquals(2, count($sourceAEvents), 'Es sollten 2 Events von Source A sein');
        
        // Teste das Filtern nach Source B
        $sourceBEvents = array_filter($allEvents, function($event) {
            return $event->getXWrSource() === 'source-b';
        });
        
        $this->assertEquals(2, count($sourceBEvents), 'Es sollten 2 Events von Source B sein');
        
        // Teste das Filtern nach Source C
        $sourceCEvents = array_filter($allEvents, function($event) {
            return $event->getXWrSource() === 'source-c';
        });
        
        $this->assertEquals(1, count($sourceCEvents), 'Es sollte 1 Event von Source C sein');
        
        // Teste dann die Filterung beim Events aus einem Datumsbereich
        $rangeEvents = $parsedIcs->eventsFromRange('20240501', '20240530');
        $this->assertGreaterThan(0, count($rangeEvents), 'Die gefilterten Events sollten nicht leer sein');
        
        // Teste das Filtern nach Source A in den Range-Events
        $sourceARangeEvents = array_filter($rangeEvents, function($event) {
            return $event->getXWrSource() === 'source-a';
        });
        
        // Es sollten immer noch 2 Events von Source A sein
        $this->assertEquals(2, count($sourceARangeEvents), 'Es sollten 2 Events von Source A im angegebenen Datumsbereich sein');
    }
} 