<?php

// Composer Autoloader
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/RecurrenceExpanderInterface.php';
require_once __DIR__ . '/../lib/RRuleExpander.php';

use ICal\RecurrenceExpanderInterface;
use ICal\RRuleExpander;

// Beispiel: Event am 3. Donnerstag eines Monats (ähnlich wie das Neander-Stammtisch-Event)
$rruleStr = 'FREQ=MONTHLY;BYDAY=TH;BYSETPOS=1,-1;UNTIL=20250510T220000Z';
$dtstart = '20240515T190000'; // 15. Mai 2024, 19:00 Uhr
$timeZone = 'Europe/Berlin';

// Zeitraum für die Abfrage setzen (optional)
$from = '20240501'; // 1. Mai 2024
$to = '20241031';   // 31. Oktober 2024

// Create an event array in the format expected by RRuleExpander
$event = [
    'RRULE' => $rruleStr,
    'DTSTART_array' => [
        ['TZID' => $timeZone],
        $dtstart
    ]
];

// Instantiate the expander 
$expander = new RRuleExpander();

// Expandiere das wiederkehrende Event zu individuellen Terminen
$expandedEvents = $expander->expandRecurringEvent($event, $from, $to, $timeZone);

// Konvertiere die expandierten Events in DateTime Objekte für die Ausgabe
$occurrences = [];
foreach ($expandedEvents as $expandedEvent) {
    $timestamp = $expandedEvent['DTSTART_array'][2];
    $occurrences[] = new DateTime('@' . $timestamp);
}

// Ausgabe der Vorkommnisse
echo "Vorkommnisse für das Event 'Monatliches Treffen am 3. Donnerstag':\n";
echo "----------------------------------------------------------------\n";
foreach ($occurrences as $index => $occurrence) {
    echo ($index + 1) . ". " . $occurrence->format('d.m.Y (D)') . " - " . $occurrence->format('H:i') . " Uhr\n";
}
echo "\n";

// Beispiel mit mehreren BYSETPOS Werten (1. und 3. Donnerstag im Monat)
$rruleMultiple = 'FREQ=MONTHLY;BYDAY=TH;BYSETPOS=1,3;UNTIL=20250510T220000Z';
$eventMultiple = [
    'RRULE' => $rruleMultiple,
    'DTSTART_array' => [
        ['TZID' => $timeZone],
        $dtstart
    ]
];

$expandedEventsMultiple = $expander->expandRecurringEvent($eventMultiple, $from, $to, $timeZone);

// Konvertiere die expandierten Events in DateTime Objekte für die Ausgabe
$occurrencesMultiple = [];
foreach ($expandedEventsMultiple as $expandedEvent) {
    $timestamp = $expandedEvent['DTSTART_array'][2];
    $occurrencesMultiple[] = new DateTime('@' . $timestamp);
}

// Ausgabe der Vorkommnisse mit mehreren BYSETPOS-Werten
echo "Vorkommnisse für das Event '1. und 3. Donnerstag im Monat':\n";
echo "--------------------------------------------------------\n";
foreach ($occurrencesMultiple as $index => $occurrence) {
    echo ($index + 1) . ". " . $occurrence->format('d.m.Y (D)') . " - " . $occurrence->format('H:i') . " Uhr\n";
}
echo "\n";

// Integration in die bestehende ICal-Klasse demonstrieren
// Wir erstellen ein Template-Event und verwenden die expandierten Events direkt
$templateEvent = [
    'UID' => 'example-event@freifunk.net',
    'SUMMARY' => 'Monatliches Treffen',
    'DESCRIPTION' => 'Regelmäßiges monatliches Treffen am 3. Donnerstag',
    'DTSTART_array' => [
        ['TZID' => $timeZone],
        $dtstart
    ],
    'DTEND_array' => [
        ['TZID' => $timeZone],
        '20240515T220000'  // 15. Mai 2024, 22:00 Uhr
    ],
    'LOCATION' => 'Freifunk-Treffpunkt',
    'RRULE' => $rruleStr
];

$expandedEvents = $expander->expandRecurringEvent($templateEvent, $from, $to, $timeZone);

// Ausgabe der expandierten Events im ICal-Format
echo "Expandierte Events im ICal-Format:\n";
echo "---------------------------------\n";
foreach ($expandedEvents as $index => $event) {
    echo "Event #" . ($index + 1) . ":\n";
    echo "  UID: " . ($event['UID'] ?? 'N/A') . "\n";
    echo "  Summary: " . ($event['SUMMARY'] ?? 'N/A') . "\n";
    echo "  Start: " . ($event['DTSTART_array'][1] ?? 'N/A') . "\n";
    echo "  Ende: " . ($event['DTEND_array'][1] ?? 'N/A') . "\n";
    echo "  Ort: " . ($event['LOCATION'] ?? 'N/A') . "\n";
    echo "\n";
}

// Beispiel für Integration in vorhandene Anwendung:
/*
// 1. In der ICal-Klasse die bestehende processRecurrences-Methode anpassen:

public function processRecurrences() {
    // Initialisiere den RRuleExpander
    $expander = new RRuleExpander();
    
    foreach ($this->cal['VEVENT'] as $event) {
        if (isset($event['RRULE'])) {
            // Expandiere Event mit RRuleExpander
            $expandedEvents = $expander->expandRecurringEvent(
                $event,
                $this->startDate,
                $this->endDate,
                $this->calendarTimeZone()
            );
            
            // Füge expandierte Events hinzu (ohne das ursprüngliche Event nochmal)
            $this->cal['VEVENT'] = array_merge($this->cal['VEVENT'], array_slice($expandedEvents, 1));
        }
    }
}
*/ 