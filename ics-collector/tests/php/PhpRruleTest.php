<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use RRule\RRule;
use RRule\RSet;

class PhpRruleTest extends TestCase
{
    public function testRecurringEventsWithPhpRrule()
    {
        // Teste das monatliche Event am 3. Donnerstag eines Monats
        // Wichtig: Wir setzen den Starttermin bewusst auf den dritten Donnerstag im Mai 2025 (15.05.2025)
        $rule = new RRule([
            'FREQ' => 'MONTHLY',
            'BYSETPOS' => 3,
            'BYDAY' => 'TH',
            'DTSTART' => '2025-05-15T19:00:00',
            'UNTIL' => '2026-05-10T22:00:00Z'
        ]);

        // Hole alle Vorkommnisse
        $occurrences = $rule->getOccurrences();
        
        // Prüfe die Anzahl der Occurrences
        $this->assertGreaterThan(0, count($occurrences), 'Es sollten wiederkehrende Events gefunden werden');
        
        // Gruppiere Events nach Monat
        $eventsByMonth = [];
        foreach ($occurrences as $occurrence) {
            $month = $occurrence->format('Y-m');
            
            if (!isset($eventsByMonth[$month])) {
                $eventsByMonth[$month] = [];
            }
            
            $eventsByMonth[$month][] = $occurrence;
        }
        
        // Prüfe, dass es pro Monat nur ein Event gibt
        foreach ($eventsByMonth as $month => $monthEvents) {
            $this->assertEquals(1, count($monthEvents), "Für Monat $month sollte es nur ein Event geben");
        }
        
        // Prüfe, dass das Event an einem Donnerstag stattfindet und der dritte Donnerstag des Monats ist
        foreach ($occurrences as $occurrence) {
            $dayOfWeek = (int)$occurrence->format('N'); // 1 (Montag) bis 7 (Sonntag)
            $dayOfMonth = (int)$occurrence->format('j');
            $month = $occurrence->format('F Y');
            
            // Prüfe, dass es ein Donnerstag ist (Tag 4 in ISO-8601)
            $this->assertEquals(4, $dayOfWeek, "Das Event am {$dayOfMonth}. im {$month} sollte an einem Donnerstag stattfinden");
            
            // Berechne die Donnerstage des Monats korrekt
            $firstDay = new \DateTime('first day of ' . $month);
            $daysToAdd = (11 - (int)$firstDay->format('N')) % 7; // Tage bis zum ersten Donnerstag
            $firstThursday = (int)$firstDay->format('j') + $daysToAdd;
            $thirdThursday = $firstThursday + 14; // +14 Tage = 3. Donnerstag
            
            // Korrektur für den Fall, dass der Monat mit einem Donnerstag beginnt
            if ($firstDay->format('N') == 4) { // Donnerstag = 4
                $thirdThursday = 1 + 14; // 1. + 14 Tage
            }
            
            $this->assertEquals($thirdThursday, $dayOfMonth, 
                "Das Event am {$dayOfMonth}. im {$month} sollte am dritten Donnerstag ({$thirdThursday}.) stattfinden");
            
            // Ausgabe der Event-Details für die Diagnose
            echo "Event: " . $occurrence->format('Y-m-d (D)') . " - 3. Donnerstag: {$thirdThursday}\n";
        }
    }
    
    public function testRecurringEventsFromIcsFile()
    {
        // Verwende die feste Fixture-Datei
        $testFile = __DIR__ . '/../fixtures/recurring_events.ics';
        $this->assertTrue(file_exists($testFile), 'Die Testdatei recurring_events.ics muss existieren');
        
        // Parse die ICS-Datei manuell
        $icsContent = file_get_contents($testFile);
        $events = $this->parseIcsEvents($icsContent);
        
        // Debug-Ausgabe aller Events
        echo "Gefundene Events in der ICS-Datei:" . PHP_EOL;
        foreach ($events as $key => $event) {
            echo "Event #{$key}:" . PHP_EOL;
            if (isset($event['SUMMARY'])) {
                echo "  SUMMARY: " . $event['SUMMARY'] . PHP_EOL;
            }
            if (isset($event['UID'])) {
                echo "  UID: " . $event['UID'] . PHP_EOL;
            }
            if (isset($event['RRULE'])) {
                echo "  RRULE: " . $event['RRULE'] . PHP_EOL;
            }
        }
        
        // Wir wissen, dass das dritte Event das Monatliche Stammtisch-Event ist
        $stammtischEvent = null;
        if (count($events) >= 3) {
            $stammtischEvent = $events[2]; // Das dritte Event in der ICS-Datei
            echo "Verwende Event #2 als Stammtisch-Event" . PHP_EOL;
            
            if (isset($stammtischEvent['SUMMARY'])) {
                echo "Event-Summary: " . $stammtischEvent['SUMMARY'] . PHP_EOL;
            }
            if (isset($stammtischEvent['RRULE'])) {
                echo "Event-RRULE: " . $stammtischEvent['RRULE'] . PHP_EOL;
            }
        }
        
        $this->assertNotNull($stammtischEvent, 'Es sollten mindestens 3 Events in der ICS-Datei vorhanden sein');
        
        // Wenn wir immer noch kein Stammtisch-Event haben, suchen wir nach einem Event mit MONTHLY in der RRULE
        if ($stammtischEvent === null || !isset($stammtischEvent['RRULE'])) {
            foreach ($events as $event) {
                if (isset($event['RRULE']) && strpos($event['RRULE'], 'MONTHLY') !== false) {
                    $stammtischEvent = $event;
                    echo "Verwende alternatives monatliches Event: " . ($event['SUMMARY'] ?? 'Unbekannt') . PHP_EOL;
                    break;
                }
            }
        }
        
        // Wenn wir überhaupt kein passendes Event finden, können wir den Test nicht fortsetzen
        if ($stammtischEvent === null) {
            $this->markTestSkipped('Kein passendes Event in der ICS-Datei gefunden');
            return;
        }
        
        $this->assertArrayHasKey('RRULE', $stammtischEvent, 'Das Event sollte eine RRULE haben');
        $this->assertStringContainsString('FREQ=MONTHLY', $stammtischEvent['RRULE'], 'Die RRULE sollte MONTHLY als Frequenz haben');
        $this->assertStringContainsString('BYDAY=TH', $stammtischEvent['RRULE'], 'Die RRULE sollte BYDAY=TH enthalten');
        $this->assertStringContainsString('BYSETPOS=3', $stammtischEvent['RRULE'], 'Die RRULE sollte BYSETPOS=3 enthalten');
        
        // Erstelle ein RRule-Objekt aus dem Event
        if (isset($stammtischEvent['DTSTART;TZID=Europe/Berlin'])) {
            $startDateStr = $stammtischEvent['DTSTART;TZID=Europe/Berlin'];
            // Konvertiere das DTSTART-Format von YYYYMMDDTHHMMSS zu YYYY-MM-DD HH:MM:SS
            $startDateFormatted = preg_replace('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})$/', '$1-$2-$3 $4:$5:$6', $startDateStr);
            $startDate = new \DateTime($startDateFormatted);
            echo "Verwende DTSTART mit TZID: " . $startDate->format('Y-m-d H:i:s') . PHP_EOL;
        } elseif (isset($stammtischEvent['DTSTART'])) {
            $startDate = new \DateTime($stammtischEvent['DTSTART']);
            echo "Verwende DTSTART ohne TZID: " . $startDate->format('Y-m-d H:i:s') . PHP_EOL;
        } else {
            $this->markTestSkipped('Event hat kein DTSTART');
            return;
        }
        
        $rrule = $stammtischEvent['RRULE'];
        
        $ruleOptions = $this->parseRrule($rrule);
        $ruleOptions['DTSTART'] = $startDate->format('Y-m-d\TH:i:s');
        
        echo "Parsed RRULE Options: " . json_encode($ruleOptions) . PHP_EOL;
        
        $rule = new RRule($ruleOptions);
        
        // Setze einen Testzeitraum für 6 Monate ab dem Startdatum
        $after = clone $startDate;
        $before = clone $startDate;
        $before->modify('+6 months');
        
        // Hole alle Occurrences innerhalb des Zeitraums
        $occurrences = $rule->getOccurrencesBetween($after, $before);
        
        // Debug-Ausgabe der Occurrences
        echo "Gefundene Occurrences:" . PHP_EOL;
        foreach ($occurrences as $occurrence) {
            echo $occurrence->format('Y-m-d (D)') . PHP_EOL;
        }
        
        // Prüfe, dass Events gefunden wurden
        $this->assertGreaterThan(0, count($occurrences), 'Es sollten wiederkehrende Events gefunden werden');
        
        // Gruppiere Events nach Monat
        $eventsByMonth = [];
        foreach ($occurrences as $occurrence) {
            $month = $occurrence->format('Y-m');
            
            if (!isset($eventsByMonth[$month])) {
                $eventsByMonth[$month] = [];
            }
            
            $eventsByMonth[$month][] = $occurrence;
        }
        
        // Prüfe, dass es pro Monat höchstens ein Event gibt
        foreach ($eventsByMonth as $month => $monthEvents) {
            $this->assertLessThanOrEqual(1, count($monthEvents), "Für Monat $month sollte es höchstens ein Event geben");
        }
    }
    
    /**
     * Parst eine RRULE-Zeichenkette in ein assoziatives Array
     */
    private function parseRrule($rruleStr)
    {
        $parts = explode(';', $rruleStr);
        $rrule = [];
        
        foreach ($parts as $part) {
            $keyValue = explode('=', $part);
            if (count($keyValue) == 2) {
                $key = $keyValue[0];
                $value = $keyValue[1];
                
                // Behandle spezielle Werte wie BYDAY, die Komma-getrennt sein können
                if (in_array($key, ['BYDAY', 'BYMONTHDAY', 'BYMONTH', 'BYSETPOS']) && strpos($value, ',') !== false) {
                    $rrule[$key] = explode(',', $value);
                } else {
                    $rrule[$key] = $value;
                }
            }
        }
        
        return $rrule;
    }
    
    /**
     * Einfacher ICS-Parser, der die Events aus einer ICS-Datei extrahiert
     */
    private function parseIcsEvents($icsContent)
    {
        $events = [];
        $lines = explode("\n", $icsContent);
        $currentEvent = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === 'BEGIN:VEVENT') {
                $currentEvent = [];
            } else if ($line === 'END:VEVENT') {
                if ($currentEvent !== null) {
                    $events[] = $currentEvent;
                    $currentEvent = null;
                }
            } else if ($currentEvent !== null) {
                // Einige Zeilen können mit Leerzeichen beginnen (Fortsetzungszeilen)
                if (strlen($line) > 0 && ($line[0] === ' ' || $line[0] === "\t")) {
                    // Umgang mit gefalteten Zeilen gemäß RFC5545
                    if (isset($lastKey) && !empty($lastKey)) {
                        $currentEvent[$lastKey] .= substr($line, 1);
                    }
                    continue;
                }
                
                // Parse Event-Eigenschaften
                $colonPos = strpos($line, ':');
                $semiPos = strpos($line, ';');
                
                if ($colonPos !== false) {
                    if ($semiPos !== false && $semiPos < $colonPos) {
                        // Property mit Parameter wie DTSTART;TZID=Europe/Berlin
                        $key = $line;
                        $value = '';
                        
                        if (preg_match('/([^;]+)(;[^:]+)?:(.+)$/', $line, $matches)) {
                            $key = $matches[1]; // Hauptschlüssel
                            $params = isset($matches[2]) ? $matches[2] : ''; // Parameter
                            $value = $matches[3]; // Wert
                            
                            // Füge den Schlüssel mit Parametern hinzu
                            $currentEvent[$key . $params] = $value;
                            // Speichere auch ohne Parameter für einfacheren Zugriff
                            $currentEvent[$key] = $value;
                            
                            // Für Debug-Zwecke
                            echo "Parsed property: {$key}{$params} = {$value}\n";
                        } else {
                            // Fallback für den Fall, dass der Regex nicht passt
                            $key = substr($line, 0, $colonPos);
                            $value = substr($line, $colonPos + 1);
                            $currentEvent[$key] = $value;
                        }
                    } else {
                        $key = substr($line, 0, $colonPos);
                        $value = substr($line, $colonPos + 1);
                        $currentEvent[$key] = $value;
                    }
                    
                    $lastKey = $key;
                }
            }
        }
        
        return $events;
    }
} 