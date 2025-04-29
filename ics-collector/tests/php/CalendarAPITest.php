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
        // Da CalendarAPI direkt über HTTP Anfragen bearbeitet,
        // simulieren wir einen direkten Aufruf und testen die Ausgabe

        // Speichere den aktuellen Ausgabe-Buffer
        $originalBuffer = ob_get_contents();
        if ($originalBuffer !== false) {
            ob_end_clean();
        }

        // Starte einen neuen Ausgabe-Buffer
        ob_start();

        // Simuliere eine HTTP-Anfrage mit den richtigen Parametern
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['source'] = 'weimarnetz';
        $_GET['format'] = 'ics';

        // Direkter Zugriff auf toString, um die Ausgabe zu prüfen
        require_once __DIR__ . '/../../lib/EventObject.php';
        require_once __DIR__ . '/../../lib/ICal.php';
        
        // Lade direkt die weimarnetz.ics-Datei
        $icsFile = __DIR__ . '/../../data/weimarnetz.ics';
        $this->assertTrue(file_exists($icsFile), 'Die Testdatei weimarnetz.ics muss existieren');
        
        // Parse die ICS-Datei
        $parsedIcs = new \ICal\ICal($icsFile, 'MO', false, false);
        $events = $parsedIcs->events();
        
        // Überprüfe, ob Events vorhanden sind
        $this->assertGreaterThan(0, count($events), 'Es sollten Events in weimarnetz.ics vorhanden sein');
        
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
        
        // Stelle den ursprünglichen Ausgabe-Buffer wieder her
        ob_end_clean();
        if ($originalBuffer !== false) {
            ob_start();
            echo $originalBuffer;
        }
    }
    
    /**
     * Test der ICS-Merger-Methode, ob Zeitzoneninformationen erhalten bleiben
     */
    public function testTimezonePreservationInIcsMerger()
    {
        // Lade eine einzelne ICS-Datei
        require_once __DIR__ . '/../../lib/ics-merger.php';
        
        $icsFile = __DIR__ . '/../../data/weimarnetz.ics';
        $this->assertTrue(file_exists($icsFile), 'Die Testdatei weimarnetz.ics muss existieren');
        
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
        
        $merger->add($icsContent, ['X-WR-SOURCE' => 'weimarnetz']);
        
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
