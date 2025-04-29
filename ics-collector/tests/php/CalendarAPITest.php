<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class CalendarAPITest extends TestCase
{
    /**
     * Test, ob die Zeitzoneninformationen in der ICS-Ausgabe erhalten bleiben
     */
    public function testTimezonePreservationInIcsOutput()
    {
        // Direkter Zugriff auf toString, um die Ausgabe zu prüfen
        require_once __DIR__ . '/../../lib/EventObject.php';
        require_once __DIR__ . '/../../lib/ICal.php';
        
        // Lade die Test-ICS-Datei
        $icsFile = __DIR__ . '/../fixtures/example_with_timezone.ics';
        $this->assertTrue(file_exists($icsFile), 'Die Testdatei example_with_timezone.ics muss existieren');
        
        // Parse die ICS-Datei
        $parsedIcs = new \ICal\ICal($icsFile, 'MO', false, false);
        $events = $parsedIcs->events();
        
        // Überprüfe, ob Events vorhanden sind
        $this->assertGreaterThan(0, count($events), 'Es sollten Events in der Testdatei vorhanden sein');
        
        // Generiere ICS-Ausgabe
        $icsOutput = "BEGIN:VCALENDAR\r\n";
        $icsOutput .= "VERSION:2.0\r\n";
        $icsOutput .= "PRODID:-//FREIFUNK//Freifunk Calendar//EN\r\n";
        $icsOutput .= "X-WR-TIMEZONE:Europe/Berlin\r\n";
        
        foreach ($events as $event) {
            $icsOutput .= $event->printIcs();
        }
        
        $icsOutput .= "END:VCALENDAR";
        
        // Überprüfe, ob die Zeitzonendaten in der Ausgabe vorhanden sind
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:', $icsOutput, 
            'Die Ausgabe sollte TZID-Informationen für DTSTART enthalten');
        $this->assertStringContainsString('DTEND;TZID=Europe/Berlin:', $icsOutput,
            'Die Ausgabe sollte TZID-Informationen für DTEND enthalten');
        
        // Überprüfe, dass die Zeitzone korrekt formatiert ist
        $this->assertMatchesRegularExpression('/DTSTART;TZID=Europe\/Berlin:\d{8}T\d{6}/', $icsOutput,
            'Die DTSTART;TZID sollte das korrekte Format haben');
    }
    
    /**
     * Test der ICS-Merger-Methode, ob Zeitzoneninformationen erhalten bleiben
     */
    public function testTimezonePreservationInIcsMerger()
    {
        // Lade eine einzelne ICS-Datei
        require_once __DIR__ . '/../../lib/ics-merger.php';
        
        $icsFile = __DIR__ . '/../fixtures/example_with_timezone.ics';
        $this->assertTrue(file_exists($icsFile), 'Die Testdatei example_with_timezone.ics muss existieren');
        
        // Lese die ICS-Datei
        $icsContent = file_get_contents($icsFile);
        
        // Erstelle einen IcsMerger und füge die Datei hinzu
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
        
        // Überprüfe, ob die Zeitzonendaten in der Ausgabe vorhanden sind
        $this->assertStringContainsString('TZID=Europe/Berlin', $merged, 
            'Die Ausgabe sollte TZID-Informationen enthalten');
        
        // Prüfe, ob mindestens ein DTSTART und DTEND mit TZID vorhanden ist
        $this->assertMatchesRegularExpression('/DTSTART;TZID=Europe\/Berlin:\d{8}T\d{6}/', $merged,
            'Die DTSTART;TZID sollte das korrekte Format haben');
        $this->assertMatchesRegularExpression('/DTEND;TZID=Europe\/Berlin:\d{8}T\d{6}/', $merged,
            'Die DTEND;TZID sollte das korrekte Format haben');
    }
}
