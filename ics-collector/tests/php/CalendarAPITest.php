<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class CalendarAPITest extends TestCase
{
    /**
     * Test der modernisierten ICS-Merger-Methode mit Sabre VObject
     */
    public function testModernizedIcsMergerWithSabreVObject(): void
    {
        // Lade die modernisierte ics-merger Klasse
        require_once __DIR__ . '/../../lib/ics-merger.php';
        
        $icsFile = __DIR__ . '/../fixtures/example_with_timezone.ics';
        $this->assertTrue(file_exists($icsFile), 'Die Testdatei example_with_timezone.ics muss existieren');
        
        // Lese die ICS-Datei
        $icsContent = file_get_contents($icsFile);
        
        // Erstelle einen modernisierten IcsMerger
        $merger = new \IcsMerger([
            'VERSION' => '2.0',
            'PRODID' => '-//FREIFUNK//Freifunk Calendar//EN',
            'X-WR-TIMEZONE' => 'Europe/Berlin',
            'X-WR-CALNAME' => 'FFMergedIcs',
            'X-WR-CALDESC' => 'A combined ics feed of freifunk communities'
        ]);
        
        $merger->add($icsContent, ['X-WR-SOURCE' => 'test-source']);
        
        // Hole das Ergebnis
        $result = $merger->getResult();
        $merged = \IcsMerger::getRawText($result);
        
        // Überprüfe grundlegende ICS-Struktur
        $this->assertStringContainsString('BEGIN:VCALENDAR', $merged, 
            'Die Ausgabe sollte ein gültiges VCALENDAR enthalten');
        $this->assertStringContainsString('END:VCALENDAR', $merged,
            'Die Ausgabe sollte ein vollständiges VCALENDAR enthalten');
        $this->assertStringContainsString('BEGIN:VEVENT', $merged,
            'Die Ausgabe sollte mindestens ein VEVENT enthalten');
        
        // Überprüfe Header-Eigenschaften
        $this->assertStringContainsString('PRODID:-//FREIFUNK//Freifunk Calendar//EN', $merged,
            'Die PRODID sollte korrekt gesetzt sein');
        $this->assertStringContainsString('X-WR-TIMEZONE:Europe/Berlin', $merged,
            'Die Zeitzone sollte korrekt gesetzt sein');
            
        // Überprüfe, dass die X-WR-SOURCE hinzugefügt wurde
        $this->assertStringContainsString('X-WR-SOURCE:test-source', $merged,
            'Die X-WR-SOURCE sollte zu den Events hinzugefügt werden');
    }
    
    public function testPlaceholder(): void
    {
        // Placeholder test to keep the test class valid
        $this->assertTrue(true);
    }
}
