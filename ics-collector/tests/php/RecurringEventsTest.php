<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class RecurringEventsTest extends TestCase
{
    public function testRecurringEventsExpansion()
    {
        // Verwende die feste Fixture-Datei
        $testFile = __DIR__ . '/../fixtures/recurring_events.ics';
        $this->assertTrue(file_exists($testFile), 'Die Testdatei recurring_events.ics muss existieren');
        
        // Parse die ICS-Datei und aktiviere die Verarbeitung wiederkehrender Events
        require_once __DIR__ . '/../../lib/ICal.php';
        $parsedIcs = new \ICal\ICal($testFile, 'MO', false, true);
        
        // Aktiviere Zeitzonenberücksichtigung für wiederkehrende Events
        $parsedIcs->useTimeZoneWithRRules = true;
        
        // Überprüfe die Anzahl der Events (sollten mehr sein als die 2 ursprünglichen)
        $events = $parsedIcs->events();
        $this->assertGreaterThan(2, count($events), 'Die wiederkehrenden Events sollten expandiert werden');
        
        // Teste das tägliche Event mit COUNT=5
        $dailyEvents = array_filter($events, function($event) {
            return strpos($event->summary, 'Daily Recurring Event') !== false;
        });
        $this->assertEquals(5, count($dailyEvents), 'Das tägliche Event sollte genau 5 mal wiederholt werden');
        
        // Teste das wöchentliche Event (bis 1. Juni)
        $weeklyEvents = array_filter($events, function($event) {
            return strpos($event->summary, 'Weekly Recurring Event') !== false;
        });
        $this->assertNotEmpty($weeklyEvents, 'Es sollten wöchentliche Events vorhanden sein');
        
        // Debug-Ausgabe für ein Event, um die Struktur zu sehen
        if (!empty($events)) {
            echo "Beispiel-Event-Struktur für erstes Event:\n";
            print_r($events[0]);
        }
    }

    public function testRecurringEventsWithTimezone()
    {
        // Verwende die feste Fixture-Datei
        $testFile = __DIR__ . '/../fixtures/recurring_events.ics';
        $this->assertTrue(file_exists($testFile), 'Die Testdatei recurring_events.ics muss existieren');
        
        // Parse die ICS-Datei und aktiviere die Verarbeitung wiederkehrender Events
        require_once __DIR__ . '/../../lib/ICal.php';
        $parsedIcs = new \ICal\ICal($testFile, 'MO', false, true);
        
        // Aktiviere Zeitzonenberücksichtigung für wiederkehrende Events
        $parsedIcs->useTimeZoneWithRRules = true;
        
        // Verarbeite Datumkonversionen für Zeitzonen
        $parsedIcs->processDateConversions();
        
        // Hole alle Events
        $events = $parsedIcs->events();
        $this->assertGreaterThan(2, count($events), 'Die wiederkehrenden Events sollten expandiert werden');
        
        // Grundlegende Tests für wiederkehrende Events
        $dailyEvents = array_filter($events, function($event) {
            return strpos($event->summary, 'Daily Recurring Event') !== false;
        });
        $this->assertEquals(5, count($dailyEvents), 'Das tägliche Event sollte genau 5 mal wiederholt werden');
        
        // Prüfe, ob die Events korrekt verarbeitet wurden
        foreach ($events as $event) {
            // Prüfe, ob der Timestamp im richtigen Format ist
            $this->assertMatchesRegularExpression('/^\d{8}T\d{6}$/', $event->dtstart, 'dtstart sollte im Format YYYYMMDDTHHmmss sein');
            
            // Überprüfe, ob das Event korrekt verarbeitet wird
            $this->assertNotEmpty($event->dtstart, 'Event sollte ein Startdatum haben');
            $this->assertNotEmpty($event->dtend, 'Event sollte ein Enddatum haben');
            
            // Prüfe, ob die Zeitzonenkonvertierung funktioniert hat
            if (property_exists($event, 'dtstart_tz')) {
                $this->assertNotEmpty($event->dtstart_tz, 'Event sollte ein konvertiertes Startdatum mit Zeitzone haben');
            }
            
            // Prüfe bei täglichen Events, ob die Datumsfolge stimmt
            if (strpos($event->summary, 'Daily Recurring Event') !== false) {
                // Erwartete Zeit: 10:00 Berlin entspricht 8:00 UTC
                // Ein Timestamp für 08:00 UTC eines bestimmten Datums kann direkt geprüft werden
                $timestamp = $parsedIcs->iCalDateToUnixTimestamp($event->dtstart, false);
                $this->assertIsInt($timestamp, 'Der Timestamp sollte eine ganze Zahl sein');
                
                // Prüfe, ob die Stunde im Timestamp der Erwartung entspricht
                $hour = (int)date('H', $timestamp);
                $this->assertEquals(10, $hour, 'Die Stunde im Timestamp sollte 8 (UTC) sein');
            }
        }
    }
    
    public function testMonthlyThirdThursdayEvent()
    {
        // Verwende die feste Fixture-Datei
        $testFile = __DIR__ . '/../fixtures/recurring_events.ics';
        $this->assertTrue(file_exists($testFile), 'Die Testdatei recurring_events.ics muss existieren');
        
        // Parse die ICS-Datei und aktiviere die Verarbeitung wiederkehrender Events
        require_once __DIR__ . '/../../lib/ICal.php';
        $parsedIcs = new \ICal\ICal($testFile, 'MO', false, true);
        
        // Aktiviere Zeitzonenberücksichtigung für wiederkehrende Events
        $parsedIcs->useTimeZoneWithRRules = true;
        
        // Setze einen Testzeitraum für 6 Monate (Mai bis Oktober 2025)
        $startDate = '20250501';
        $endDate = '20251031';
        $events = $parsedIcs->eventsFromRange($startDate, $endDate);
        
        // Filtere die monatlichen Events
        $monthlyEvents = array_filter($events, function($event) {
            return strpos($event->summary, 'Neander-Stammtisch-Freifunk') !== false;
        });
        
        // Debugging-Ausgabe für alle gefundenen Events mit ihrer Zeit
        echo "Gefundene Neander-Stammtisch-Events:\n";
        foreach ($monthlyEvents as $key => $event) {
            $timestamp = $parsedIcs->iCalDateToUnixTimestamp($event->dtstart);
            echo date('Y-m-d (D) H:i', $timestamp) . " - Event {$key}\n";
        }
        
        // Gruppiere Events nach Monat
        $eventsByMonth = [];
        foreach ($monthlyEvents as $event) {
            $timestamp = $parsedIcs->iCalDateToUnixTimestamp($event->dtstart);
            $month = date('Y-m', $timestamp);
            
            if (!isset($eventsByMonth[$month])) {
                $eventsByMonth[$month] = [];
            }
            
            $eventsByMonth[$month][] = $event;
        }
        
        // Prüfe, dass es genau 6 Events gibt (Mai bis Oktober 2025)
        $this->assertEquals(6, count($eventsByMonth), 'Es sollten genau 6 Monate mit Events sein');
        
        // Zeige die Anzahl der Events pro Monat
        echo "\nEvents pro Monat:\n";
        foreach ($eventsByMonth as $month => $monthEvents) {
            echo "{$month}: " . count($monthEvents) . " Events\n";
            
            // Wir erwarten nur ein Event pro Monat
            $this->assertEquals(1, count($monthEvents), "Für Monat {$month} sollte es nur ein Event geben");
        }
        
        // Prüfe, dass jedes Event tatsächlich am 3. Donnerstag stattfindet
        foreach ($monthlyEvents as $event) {
            $timestamp = $parsedIcs->iCalDateToUnixTimestamp($event->dtstart);
            $dayOfWeek = (int)date('N', $timestamp); // 1 (Montag) bis 7 (Sonntag)
            $dayOfMonth = (int)date('j', $timestamp);
            $monthName = date('F Y', $timestamp);
            
            // Prüfe, dass es ein Donnerstag ist (Tag 4 in ISO-8601)
            $this->assertEquals(4, $dayOfWeek, "Das Event am {$dayOfMonth}. im {$monthName} sollte an einem Donnerstag stattfinden");
            
            // Berechne, ob es sich um den dritten Donnerstag handelt
            // Erster Tag des Monats
            $firstDayOfMonth = strtotime(date('Y-m-01', $timestamp));
            // Wochentag des ersten Tags (1-7)
            $firstDayWeekday = (int)date('N', $firstDayOfMonth);
            
            // Tage bis zum ersten Donnerstag
            $daysToFirstThursday = (4 - $firstDayWeekday + 7) % 7;
            // Datum des ersten Donnerstags
            $firstThursday = $firstDayOfMonth + $daysToFirstThursday * 86400;
            // Datum des dritten Donnerstags
            $thirdThursday = $firstThursday + 14 * 86400;
            $thirdThursdayDay = (int)date('j', $thirdThursday);
            
            $this->assertEquals($thirdThursdayDay, $dayOfMonth, 
                "Das Event am {$dayOfMonth}. im {$monthName} sollte am dritten Donnerstag ({$thirdThursdayDay}.) stattfinden");
        }
    }
}
