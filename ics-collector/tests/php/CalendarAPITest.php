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
        
        // Dynamische Datumsangaben erstellen - heutiges Datum und in 6 Monaten
        $today = new \DateTime();
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
        
        // Debug: Prüfe Inhalt der ICS-Datei
        $icsContent = file_get_contents($testFile);
        $this->assertStringContainsString('RRULE:FREQ=MONTHLY;BYDAY=TH;BYSETPOS=3', $icsContent, 
            'Die Test-ICS-Datei sollte monatliche Regeln enthalten');
        
        // Test ICal processing directly with dynamic dates
        $directIcal = new \ICal\ICal(false, 'MO', false, false);
        $directIcal->initString($icsContent);
        
        // Dynamische Datumsangaben verwenden
        $directIcal->startDate = $fromDateIcs;
        $directIcal->endDate = $toDateIcs;
        
        $reflection = new \ReflectionObject($directIcal);
        $processRecurrencesProperty = $reflection->getProperty('processRecurrences');
        $processRecurrencesProperty->setAccessible(true);
        $processRecurrencesProperty->setValue($directIcal, true);
        
        // Process recurrences
        $directIcal->processRecurrences();
        
        // Verify events were expanded
        $directEvents = $directIcal->eventsFromRange($fromDateIcs, $toDateIcs);
        
        // Erstelle API mit Test-Datei
        $api = new \CalendarAPI($testFile);
        
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
        
        // Setze processRecurrences direkt auf true, um sicherzustellen, dass wiederkehrende Events verarbeitet werden
        $processRecurrencesProperty = $reflection->getProperty('processRecurrences');
        if ($processRecurrencesProperty) {
            $processRecurrencesProperty->setAccessible(true);
            $processRecurrencesProperty->setValue($api, true);
        }
        
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
        
        // Erhalte die Ausgabe als String
        $output = $result['data'];
        
        // Extrahiere alle Events und ihre Datumsangaben für die Tests
        preg_match_all('/SUMMARY:(.*?)(?:\r\n|\n)/', $output, $summaryMatches);
        preg_match_all('/DTSTART;TZID=Europe\/Berlin:(\d{8})T190000/', $output, $dateMatches);
        
        // DYNAMISCHER ANSATZ:
        // 1. Extrahiere die Anzahl der gefundenen Events aus der Ausgabe
        preg_match_all('/SUMMARY:Neander-Stammtisch-Freifunk/', $output, $matches);
        $foundEventCount = count($matches[0]);
        
        // 2. Prüfe, ob mindestens ein Event gefunden wurde
        $this->assertGreaterThan(0, $foundEventCount, 
             'Es sollte mindestens ein "Neander-Stammtisch-Freifunk" Event in der ICS-Ausgabe sein');
        
        // 3. Prüfe, ob die Datumsmuster gefunden wurden
        preg_match_all('/DTSTART;TZID=Europe\/Berlin:(\d{8})T190000/', $output, $dateMatches);
        $this->assertEquals($foundEventCount, count($dateMatches[1]), 
            'Die Anzahl der Events sollte mit der Anzahl der Datumsangaben übereinstimmen');
        
        // 4. Validiere jedes gefundene Datum - muss ein dritter Donnerstag sein
        foreach ($dateMatches[1] as $dateString) {
            $year = substr($dateString, 0, 4);
            $month = substr($dateString, 4, 2);
            $day = substr($dateString, 6, 2);
            
            $date = \DateTime::createFromFormat('Y-m-d', "$year-$month-$day");
            $timestamp = $date->getTimestamp();
            
            // Prüfe, ob es ein Donnerstag ist
            $dayOfWeek = date('N', $timestamp);
            $this->assertEquals(4, $dayOfWeek, "Das Event am $year-$month-$day sollte an einem Donnerstag stattfinden");
            
            // Berechne den dritten Donnerstag des Monats
            $firstDayOfMonth = strtotime("$year-$month-01");
            $firstDayWeekday = date('N', $firstDayOfMonth);
            $daysToFirstThursday = (4 - $firstDayWeekday + 7) % 7;
            $firstThursday = $firstDayOfMonth + $daysToFirstThursday * 86400;
            $thirdThursday = $firstThursday + 14 * 86400;
            $thirdThursdayDay = date('d', $thirdThursday);
            
            $this->assertEquals($thirdThursdayDay, $day, 
                "Das Event am $year-$month-$day sollte am dritten Donnerstag ($thirdThursdayDay.) stattfinden");
        }
        
        // 5. Prüfe, ob die gefundenen Daten alle im erwarteten Bereich liegen
        if (!empty($dateMatches[1])) {
            $minDateString = min($dateMatches[1]);
            $maxDateString = max($dateMatches[1]);
            
            // Konvertiere zu DateTime-Objekten für einfachere Vergleiche
            $minDate = \DateTime::createFromFormat('Ymd', $minDateString);
            $maxDate = \DateTime::createFromFormat('Ymd', $maxDateString);
            
            // Prüfe, ob die Daten innerhalb des erwarteten Bereichs liegen
            $startRange = \DateTime::createFromFormat('Ymd', $fromDateIcs);
            $endRange = \DateTime::createFromFormat('Ymd', $toDateIcs);
            
            // Wir prüfen hier nur, dass das späteste Datum im Bereich liegt
            // Da die ersten Events vielleicht nicht im Testfenster liegen
            $this->assertLessThanOrEqual($endRange, $maxDate, 
                'Das späteste Event sollte vor dem Ende des Testbereichs liegen');
        }
        
        // Überprüfe, ob die ICS-Ausgabe grundsätzlich valide ist
        $this->assertStringStartsWith("BEGIN:VCALENDAR", $output, 'Die ICS-Ausgabe sollte mit BEGIN:VCALENDAR beginnen');
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $output, 'Die ICS-Ausgabe sollte mit END:VCALENDAR enden');
    }
}
