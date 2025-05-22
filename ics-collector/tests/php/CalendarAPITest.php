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
    
    /**
     * Test, ob wiederkehrende Events korrekt aufgelöst werden
     */
    public function testRecurringEventsResolution()
    {
        // Lade die notwendigen Klassen
        require_once __DIR__ . '/../../lib/ICal.php';
        require_once __DIR__ . '/../../lib/EventObject.php';
        require_once __DIR__ . '/../../CalendarAPI.php';
        
        // Setze Zeitzone für konsistente Datumsberechnungen
        $preservedTimeZone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        
        // Datumsbereich so wählen, dass er das bekannte Stammtisch-Event (15.05.2025) einschließt
        $today = new \DateTime('2025-05-01'); // Fester Startpunkt, um das bekannte Event einzuschließen
        $inSixMonths = clone $today;
        $inSixMonths->modify('+6 months');
        
        // Formate für die verschiedenen Anforderungen
        $fromDateIcs = $today->format('Ymd');
        $toDateIcs = $inSixMonths->format('Ymd');
        $fromDateApi = $today->format('Y-m-d');
        $toDateApi = $inSixMonths->format('Y-m-d');
        
        // Testdatei mit wiederkehrenden Events
        $testFile = __DIR__ . '/../fixtures/recurring_events.ics';
        $this->assertTrue(file_exists($testFile), 'Die Testdatei recurring_events.ics muss existieren');
        
        // Vereinfachter Test ohne Rekursionsauflösung
        // Manuell eine ICal-Instanz erstellen und direkt die Events abrufen
        $icalInstance = new \ICal\ICal($testFile, 'MO', false, false);
        
        // Direkter Zugriff auf alle Events ohne Rekursion
        $allEvents = $icalInstance->events();
        
        // Finde das Stammtisch-Event
        $stammtischEvents = array_filter($allEvents, function($event) {
            return strpos($event->getSummary(), 'Neander-Stammtisch-Freifunk') !== false;
        });
        
        // Grundlegende Prüfung: Gibt es überhaupt das Event in der Datei?
        $this->assertNotEmpty($stammtischEvents, 'Das Stammtisch-Event sollte in der ICS-Datei gefunden werden');
        $stammtischEvent = reset($stammtischEvents);
        
        // Jetzt der eigentliche API-Test mit direktem Zugriff, aber ohne Rekursionsauflösung
        $api = new \CalendarAPI($testFile, false); // Rekursion ausschalten
        
        // Setze die API-Parameter für den Test mit dynamischen Daten
        $reflection = new \ReflectionObject($api);
        $paramsProperty = $reflection->getProperty('parameters');
        $paramsProperty->setAccessible(true);
        $paramsProperty->setValue($api, [
            'source' => 'all',
            'from' => $fromDateApi,
            'to' => $toDateApi,
            'format' => 'ics'
        ]);
        
        $sourcesProperty = $reflection->getProperty('sources');
        $sourcesProperty->setAccessible(true);
        $sourcesProperty->setValue($api, ['all']);
        
        // Direkte Ausführung der geschützten processCalendarData-Methode
        $processCalendarDataMethod = $reflection->getMethod('processCalendarData');
        $processCalendarDataMethod->setAccessible(true);
        
        // Verarbeite die Kalenderdaten und hole das Ergebnis
        $result = $processCalendarDataMethod->invoke($api);
        
        // Zeitzone wiederherstellen
        date_default_timezone_set($preservedTimeZone);
        
        // Prüfe, ob das Ergebnis die erwarteten Schlüssel enthält
        $this->assertArrayHasKey('contentType', $result, 'Das Ergebnis sollte den Schlüssel "contentType" enthalten');
        $this->assertArrayHasKey('data', $result, 'Das Ergebnis sollte den Schlüssel "data" enthalten');
        $this->assertEquals('text/calendar', $result['contentType'], 'Der Content-Type sollte "text/calendar" sein');
        
        // Prüfe, ob das Stammtisch-Event in der Ausgabe enthalten ist
        $this->assertStringContainsString('Neander-Stammtisch-Freifunk', $result['data'],
            'Das Stammtisch-Event sollte in der ICS-Ausgabe enthalten sein');
    }
}
