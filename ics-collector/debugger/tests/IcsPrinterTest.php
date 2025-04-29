<?php
// test-timezone.php
require_once 'lib/EventObject.php';
require_once 'lib/ICal.php';

use ICal\ICal;

// Lade die Weimarnetz-Datei direkt
$icsFile = 'data/weimarnetz.ics';
$parsedIcs = new ICal($icsFile, 'MO', false, false);

// Hole die Events
$events = $parsedIcs->events();

// Ausgabe überprüfen
header('Content-type: text/calendar; charset=UTF-8');
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//FREIFUNK//Freifunk Calendar//EN\r\n";
echo "X-WR-TIMEZONE:Europe/Berlin\r\n";
echo "X-WR-CALNAME:FFMergedIcs\r\n";
echo "X-WR-CALDESC:A combined ics feed of freifunk communities\r\n";

foreach ($events as $event) {
    echo $event->printIcs();
}

echo "END:VCALENDAR";
