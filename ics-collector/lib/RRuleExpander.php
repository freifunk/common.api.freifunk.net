<?php

namespace ICal;

use RRule\RRule;
use RRule\RSet;
use DateTime;
use DateTimeZone;
use DateInterval;

/**
 * RRuleExpander - Implementierung des RecurrenceExpanderInterface mit rlanvin/php-rrule
 */
class RRuleExpander implements RecurrenceExpanderInterface
{
    /**
     * Prüft, ob ein Event eine gültige Recurring Rule enthält
     *
     * @param array $event Das zu prüfende Event
     * @return boolean True, wenn das Event eine gültige RRULE hat
     */
    public function isRecurringEvent(array $event): bool
    {
        return isset($event['RRULE']) && !empty($event['RRULE']);
    }

    /**
     * Erweitert ein wiederkehrendes Event in mehrere Einzel-Events basierend auf der Recurrence Rule
     *
     * @param array $event Das ursprüngliche Event mit RRULE
     * @param string|null $rangeStart Optionaler Startzeitpunkt im Format YYYYMMDD
     * @param string|null $rangeEnd Optionaler Endzeitpunkt im Format YYYYMMDD
     * @param string $defaultTimezone Die Standard-Zeitzone, falls im Event keine definiert ist
     * 
     * @return array Eine Liste von erweiterten Events (inklusive des Originals, ohne RRULE)
     */
    public function expandRecurringEvent(array $event, ?string $rangeStart = null, ?string $rangeEnd = null, string $defaultTimezone = 'UTC'): array
    {
        if (!$this->isRecurringEvent($event)) {
            return [$event]; // Nicht wiederkehrendes Event wird unverändert zurückgegeben
        }

        // Hole die Zeitzone aus dem Event oder verwende den Default
        $timeZone = $defaultTimezone;
        if (isset($event['DTSTART_array'][0]['TZID'])) {
            $timeZone = $event['DTSTART_array'][0]['TZID'];
        }

        // Parse die RRULE aus dem Event
        $rruleOptions = $this->parseRruleString($event['RRULE']);
        
        // Hole das Startdatum für das wiederkehrende Event
        $dtstart = $event['DTSTART_array'][1];
        $startDateTime = $this->parseDateTimeString($dtstart, $timeZone);
        
        // Setze DTSTART als Option für RRule
        $rruleOptions['DTSTART'] = $startDateTime->format('Y-m-d\TH:i:s');

        // Bereite die Zeitspanne vor (von-bis)
        $from = null;
        $to = null;
        
        if ($rangeStart) {
            $from = $this->parseDateTimeString($rangeStart, $timeZone);
        }
        
        if ($rangeEnd) {
            $to = $this->parseDateTimeString($rangeEnd, $timeZone);
        }

        try {
            // Erstelle RRule-Objekt
            $rule = new RRule($rruleOptions);
            
            // Hole alle Vorkommen im angegebenen Zeitbereich
            $occurrences = [];
            if ($from !== null && $to !== null) {
                $occurrences = $rule->getOccurrencesBetween($from, $to);
            } else {
                $occurrences = $rule->getOccurrences();
            }
            
            // Original-Event (ohne RRULE) zum Ergebnis hinzufügen
            $expandedEvents = [];
            $originalEvent = $event;
            unset($originalEvent['RRULE']);
            
            // Eventdauer berechnen
            $duration = $this->calculateEventDuration($event);
            
            // Prüfe, ob wir ein Original-Event haben und es innerhalb des Bereichs liegt
            $originalIncluded = false;
            $originalDateTime = clone $startDateTime;
            
            // Für jedes Vorkommen ein neues Event erstellen
            foreach ($occurrences as $occurrence) {
                // Prüfe, ob dieses Vorkommen der Originalzeit entspricht
                $isSameAsOriginal = $this->isSameDateTime($occurrence, $originalDateTime);
                
                if ($isSameAsOriginal) {
                    // Wenn es das Original ist, füge das Original-Event hinzu (aber nur einmal)
                    if (!$originalIncluded) {
                        $expandedEvents[] = $originalEvent;
                        $originalIncluded = true;
                    }
                } else {
                    // Füge ein neues Event für diese Wiederholung hinzu
                    $newEvent = $this->createEventOccurrence($event, $occurrence, $duration);
                    $expandedEvents[] = $newEvent;
                }
            }
            
            // Wenn kein Original hinzugefügt wurde (weil es außerhalb des Bereichs liegt),
            // aber es Wiederholungen gibt, füge das Original hinzu
            if (!$originalIncluded && empty($expandedEvents) && 
                ($from === null || $startDateTime >= $from) && 
                ($to === null || $startDateTime <= $to)) {
                $expandedEvents[] = $originalEvent;
            }
            
            return $expandedEvents;
        } catch (\Exception $e) {
            // Bei Fehlern nur das Original-Event zurückgeben
            return [$event];
        }
    }
    
    /**
     * Parst einen RRULE-String in ein Array von Optionen für RRule
     *
     * @param string $rruleStr Die RRULE als String (z.B. "FREQ=WEEKLY;BYDAY=MO,TU")
     * @return array Assoziatives Array mit RRULE-Optionen
     */
    private function parseRruleString(string $rruleStr): array
    {
        $parts = explode(';', $rruleStr);
        $rrule = [];
        
        foreach ($parts as $part) {
            if (empty($part)) continue;
            
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
     * Konvertiert einen Datums-String in ein DateTime-Objekt
     *
     * @param string $dateStr Datums-String im Format YYYYMMDD oder YYYYMMDDTHHMMSS
     * @param string $timezone Zeitzone für das DateTime-Objekt
     * @return DateTime
     */
    private function parseDateTimeString(string $dateStr, string $timezone): DateTime
    {
        // Extrahiere Datum und Zeit aus dem String
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T?(\d{0,2})(\d{0,2})(\d{0,2})Z?$/', $dateStr, $matches)) {
            // Wenn Zeit vorhanden, diese verwenden
            if (!empty($matches[4]) || !empty($matches[5]) || !empty($matches[6])) {
                $formattedDate = sprintf('%s-%s-%s %s:%s:%s',
                    $matches[1], $matches[2], $matches[3],
                    $matches[4] ?: '00', $matches[5] ?: '00', $matches[6] ?: '00'
                );
            } else {
                // Nur Datum, Zeit auf 00:00:00 setzen
                $formattedDate = sprintf('%s-%s-%s 00:00:00',
                    $matches[1], $matches[2], $matches[3]
                );
            }
            
            // Erstelle DateTime mit der angegebenen Zeitzone
            $dateTime = new DateTime($formattedDate, new DateTimeZone($timezone));
            return $dateTime;
        }
        
        // Fallback, wenn das Format nicht erkannt wird
        return new DateTime($dateStr, new DateTimeZone($timezone));
    }
    
    /**
     * Berechnet die Dauer eines Events in Sekunden
     *
     * @param array $event Das Event, dessen Dauer berechnet werden soll
     * @return int Dauer in Sekunden
     */
    private function calculateEventDuration(array $event): int
    {
        // Standard: 1 Stunde
        $duration = 3600;
        
        if (isset($event['DTEND_array'][2]) && isset($event['DTSTART_array'][2])) {
            // Berechne Dauer aus Start- und Endzeitstempel
            $duration = $event['DTEND_array'][2] - $event['DTSTART_array'][2];
        } else if (isset($event['DURATION'])) {
            // Verwende DURATION-Property, falls vorhanden
            try {
                $interval = new DateInterval($event['DURATION']);
                $startDate = new DateTime();
                $endDate = clone $startDate;
                $endDate->add($interval);
                $duration = $endDate->getTimestamp() - $startDate->getTimestamp();
            } catch (\Exception $e) {
                // Fallback auf Standarddauer
            }
        }
        
        return $duration;
    }
    
    /**
     * Prüft, ob zwei DateTime-Objekte den gleichen Zeitpunkt repräsentieren
     *
     * @param DateTime $dt1 Erstes DateTime-Objekt
     * @param DateTime $dt2 Zweites DateTime-Objekt
     * @return bool True, wenn beide den gleichen Zeitpunkt darstellen
     */
    private function isSameDateTime(DateTime $dt1, DateTime $dt2): bool
    {
        // Format both dates to the same format and compare
        $format = 'Y-m-d H:i';
        return $dt1->format($format) === $dt2->format($format);
    }
    
    /**
     * Erstellt ein neues Event-Objekt für ein Vorkommen eines wiederkehrenden Events
     *
     * @param array $originalEvent Das ursprüngliche Event
     * @param DateTime $occurrence Das Datum des Vorkommens
     * @param int $duration Die Dauer des Events in Sekunden
     * @return array Das neue Event-Objekt
     */
    private function createEventOccurrence(array $originalEvent, DateTime $occurrence, int $duration): array
    {
        // Kopiere das ursprüngliche Event
        $newEvent = $originalEvent;
        
        // Entferne RRULE
        unset($newEvent['RRULE']);
        
        // Setze neue Start- und Endzeit
        $occurrenceTime = $occurrence->format('Ymd\THis');
        $occurrenceTimestamp = $occurrence->getTimestamp();
        
        // Aktualisiere DTSTART
        $newEvent['DTSTART'] = $occurrenceTime;
        $newEvent['DTSTART_array'][1] = $occurrenceTime;
        $newEvent['DTSTART_array'][2] = $occurrenceTimestamp;
        
        // Aktualisiere DTEND, falls vorhanden
        if (isset($newEvent['DTEND'])) {
            $endTimestamp = $occurrenceTimestamp + $duration;
            $endTime = date('Ymd\THis', $endTimestamp);
            
            $newEvent['DTEND'] = $endTime;
            $newEvent['DTEND_array'][1] = $endTime;
            $newEvent['DTEND_array'][2] = $endTimestamp;
        }
        
        // Erstelle eine eindeutige UID für diese Instanz
        $newEvent['UID'] = $originalEvent['UID'] . '-' . $occurrenceTime;
        
        return $newEvent;
    }
} 