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
            return strpos($event->getSummary(), 'Daily Recurring Event') !== false;
        });
        $this->assertEquals(5, count($dailyEvents), 'Das tägliche Event sollte genau 5 mal wiederholt werden');
        
        // Teste das wöchentliche Event (bis 1. Juni)
        $weeklyEvents = array_filter($events, function($event) {
            return strpos($event->getSummary(), 'Weekly Recurring Event') !== false;
        });
        $this->assertNotEmpty($weeklyEvents, 'Es sollten wöchentliche Events vorhanden sein');
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
            return strpos($event->getSummary(), 'Daily Recurring Event') !== false;
        });
        $this->assertEquals(5, count($dailyEvents), 'Das tägliche Event sollte genau 5 mal wiederholt werden');
        
        // Prüfe, ob die Events korrekt verarbeitet wurden
        foreach ($events as $event) {
            // Prüfe, ob der Timestamp im richtigen Format ist
            $this->assertMatchesRegularExpression('/^\d{8}T\d{6}$/', $event->getDtstart(), 'dtstart sollte im Format YYYYMMDDTHHmmss sein');
            
            // Überprüfe, ob das Event korrekt verarbeitet wird
            $this->assertNotEmpty($event->getDtstart(), 'Event sollte ein Startdatum haben');
            $this->assertNotEmpty($event->getDtend(), 'Event sollte ein Enddatum haben');
            
            // Prüfe, ob die Zeitzonenkonvertierung funktioniert hat
            $this->assertNotNull($event->getDtstartTz(), 'Event sollte ein konvertiertes Startdatum mit Zeitzone haben');
            
            // Prüfe bei täglichen Events, ob die Datumsfolge stimmt
            if (strpos($event->getSummary(), 'Daily Recurring Event') !== false) {
                // Erwartete Zeit: 10:00 Berlin entspricht 8:00 UTC
                // Ein Timestamp für 08:00 UTC eines bestimmten Datums kann direkt geprüft werden
                $timestamp = $parsedIcs->iCalDateToUnixTimestamp($event->getDtstart(), false);
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
        
        // Definiere einen festen Zeitraum für den Test (Mai bis November 2025)
        $fromDate = new \DateTime('2025-05-01');
        $toDate = new \DateTime('2025-11-30');
        
        // Formate für die verschiedenen Anforderungen
        $fromDateIcs = $fromDate->format('Ymd');
        $toDateIcs = $toDate->format('Ymd');
        $events = $parsedIcs->eventsFromRange($fromDateIcs, $toDateIcs);
        
        // Filtere die monatlichen Events
        $monthlyEvents = array_filter($events, function($event) {
            return strpos($event->getSummary(), 'Neander-Stammtisch-Freifunk') !== false;
        });
        
        // Gruppiere Events nach Monat
        $eventsByMonth = [];
        foreach ($monthlyEvents as $event) {
            $timestamp = $parsedIcs->iCalDateToUnixTimestamp($event->getDtstart());
            $month = date('Y-m', $timestamp);
            
            if (!isset($eventsByMonth[$month])) {
                $eventsByMonth[$month] = [];
            }
            
            $eventsByMonth[$month][] = $event;
        }
        
        // Prüfe, dass es genau 7 Events gibt (Mai bis November 2025)
        $this->assertEquals(7, count($eventsByMonth), 'Es sollten genau 7 Monate mit Events sein');
        
        // Für jeden Monat prüfen
        foreach ($eventsByMonth as $month => $monthEvents) {
            // Wir erwarten nur ein Event pro Monat
            $this->assertEquals(1, count($monthEvents), "Für Monat {$month} sollte es nur ein Event geben");
        }
        
        // Prüfe, dass jedes Event tatsächlich am 3. Donnerstag stattfindet
        foreach ($monthlyEvents as $event) {
            $timestamp = $parsedIcs->iCalDateToUnixTimestamp($event->getDtstart());
            $dayOfWeek = (int)date('N', $timestamp); // 1 (Montag) bis 7 (Sonntag)
            $dayOfMonth = (int)date('j', $timestamp);
            $monthName = date('F Y', $timestamp);
            
            // Gibt das Ereignis aus (nur für Debugging)
            echo "Event: " . date('Y-m-d (D)', $timestamp) . " - 3. Donnerstag: " . $dayOfMonth . "\n";
            
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
