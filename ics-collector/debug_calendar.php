<?php
/**
 * Debug-Skript für CalendarAPI
 * 
 * Dieses Skript hilft bei der Identifizierung von Problemen mit der CalendarAPI
 * auf verschiedenen Umgebungen (lokal vs. Server).
 * 
 * Aufruf: /debug_calendar.php?source=weimarnetz&format=ics&from=now
 */

// Fehleranzeige aktivieren
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Debug-Ausgabe-Funktion
function debug($label, $value, $detailed = false) {
    echo "<h3>$label</h3>";
    
    if (is_array($value) || is_object($value)) {
        echo "<pre>";
        if ($detailed) {
            var_dump($value);
        } else {
            print_r($value);
        }
        echo "</pre>";
    } else {
        echo "<pre>" . htmlspecialchars($value ?? "NULL") . "</pre>";
    }
    echo "<hr>";
}

// Grundlegende Systeminformationen
echo "<h1>CalendarAPI Debug</h1>";
echo "<h2>Systeminformation</h2>";
debug("PHP Version", phpversion());
debug("PHP Zeitzone", date_default_timezone_get());
debug("Aktuelles Datum/Zeit", date('Y-m-d H:i:s'));
debug("Server Software", $_SERVER['SERVER_SOFTWARE'] ?? 'Nicht verfügbar');
debug("Dokumentenroot", $_SERVER['DOCUMENT_ROOT'] ?? 'Nicht verfügbar');
debug("Skriptpfad", __FILE__);

// Laden der benötigten Dateien
try {
    echo "<h2>Dateien und Klassen</h2>";
    
    // Versuche, erforderliche Dateien zu laden
    $libPath = __DIR__ . '/lib';
    debug("Lib-Verzeichnis", $libPath . " (Existiert: " . (is_dir($libPath) ? "Ja" : "Nein") . ")");
    
    if (!is_dir($libPath)) {
        $libPath = realpath(__DIR__ . '/../lib');
        debug("Alternativer Lib-Pfad", $libPath . " (Existiert: " . (is_dir($libPath) ? "Ja" : "Nein") . ")");
    }
    
    $icalPath = $libPath . '/ICal.php';
    debug("ICal.php Pfad", $icalPath . " (Existiert: " . (file_exists($icalPath) ? "Ja" : "Nein") . ")");
    
    // CalendarAPI.php
    $apiPath = __DIR__ . '/CalendarAPI.php';
    debug("CalendarAPI.php Pfad", $apiPath . " (Existiert: " . (file_exists($apiPath) ? "Ja" : "Nein") . ")");
    
    if (!file_exists($apiPath)) {
        $apiPath = realpath(__DIR__ . '/../CalendarAPI.php');
        debug("Alternativer API-Pfad", $apiPath . " (Existiert: " . (file_exists($apiPath) ? "Ja" : "Nein") . ")");
    }
    
    // Überprüfe Merged-ICS Datei
    if (file_exists($apiPath)) {
        include_once $apiPath;
        
        $reflectionClass = new ReflectionClass('CalendarAPI');
        $constants = $reflectionClass->getConstants();
        
        $defaultIcsPath = isset($constants['DEFAULT_ICS_MERGED_FILE']) ? 
            $constants['DEFAULT_ICS_MERGED_FILE'] : 'data/ffMerged.ics';
        
        debug("DEFAULT_ICS_MERGED_FILE Konstante", $defaultIcsPath);
        
        $absDefaultIcsPath = realpath(__DIR__ . '/' . $defaultIcsPath);
        if (!$absDefaultIcsPath) {
            $absDefaultIcsPath = realpath(__DIR__ . '/../' . $defaultIcsPath);
        }
        
        debug("Merged ICS Pfad", $absDefaultIcsPath . " (Existiert: " . 
            (file_exists($absDefaultIcsPath) ? "Ja" : "Nein") . ")");
        
        if (file_exists($absDefaultIcsPath)) {
            $icsFileSize = filesize($absDefaultIcsPath);
            $icsLastModified = date('Y-m-d H:i:s', filemtime($absDefaultIcsPath));
            
            debug("Merged ICS Größe", $icsFileSize . " Bytes");
            debug("Merged ICS letzte Änderung", $icsLastModified);
            
            // Überprüfe den Inhalt der Datei
            $icsContent = file_get_contents($absDefaultIcsPath);
            $contentLength = strlen($icsContent);
            
            debug("Anzahl Zeichen im Inhalt", $contentLength);
            debug("Erste 200 Zeichen", substr($icsContent, 0, 200));
            
            // Suche nach einem bestimmten Source in der Datei
            $sourceParam = $_GET['source'] ?? 'weimarnetz';
            $sourcePattern = "X-WR-SOURCE:" . $sourceParam;
            $hasSource = strpos($icsContent, $sourcePattern) !== false;
            
            debug("Source-Parameter '$sourceParam' in der Datei gefunden", $hasSource ? "Ja" : "Nein");
            
            // Überprüfe auf wiederkehrende Events
            $rruleCount = substr_count($icsContent, "RRULE:");
            debug("Anzahl RRULE-Einträge", $rruleCount);
        }
    }
    
    // Überprüfe die Composer-Abhängigkeiten
    $composerPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($composerPath)) {
        $composerPath = realpath(__DIR__ . '/../vendor/autoload.php');
    }
    
    debug("Composer Autoloader Pfad", $composerPath . " (Existiert: " . 
        (file_exists($composerPath) ? "Ja" : "Nein") . ")");
    
    if (file_exists($composerPath)) {
        include_once $composerPath;
        
        // Überprüfe, ob die rlanvin/php-rrule-Klasse vorhanden ist
        $rruleExists = class_exists('\\RRule\\RRule');
        debug("RRule-Klasse existiert", $rruleExists ? "Ja" : "Nein");
    }
    
    // Überprüfe die Request-Parameter
    echo "<h2>Request-Parameter</h2>";
    debug("GET-Parameter", $_GET);
    
    // Teste die Kernfunktionalität
    echo "<h2>Funktionalitätstest</h2>";
    
    if (class_exists('CalendarAPI')) {
        // Lade die API mit dem konkreten Merged-ICS-Pfad
        $api = new CalendarAPI($absDefaultIcsPath, true);
        
        // Setze die Request-Parameter
        $reflection = new ReflectionObject($api);
        
        $paramsProperty = $reflection->getProperty('parameters');
        $paramsProperty->setAccessible(true);
        $paramsProperty->setValue($api, [
            'source' => $_GET['source'] ?? 'weimarnetz',
            'from' => $_GET['from'] ?? 'now',
            'to' => $_GET['to'] ?? null,
            'format' => $_GET['format'] ?? 'ics'
        ]);
        
        $sourcesProperty = $reflection->getProperty('sources');
        $sourcesProperty->setAccessible(true);
        $sourcesProperty->setValue($api, explode(',', $_GET['source'] ?? 'weimarnetz'));
        
        // Direkte Ausführung der processCalendarData-Methode
        try {
            $processMethod = $reflection->getMethod('processCalendarData');
            $processMethod->setAccessible(true);
            
            debug("CalendarAPI Parameter", [
                'source' => $_GET['source'] ?? 'weimarnetz',
                'from' => $_GET['from'] ?? 'now', 
                'to' => $_GET['to'] ?? 'auto-calculated',
                'format' => $_GET['format'] ?? 'ics'
            ]);
            
            echo "<h3>Verarbeite Kalender-Daten...</h3>";
            $result = $processMethod->invoke($api);
            
            debug("CalendarAPI Ergebnis ContentType", $result['contentType'] ?? 'Nicht verfügbar');
            
            $data = $result['data'] ?? '';
            debug("CalendarAPI Ergebnis Länge", strlen($data) . " Zeichen");
            
            if (!empty($data)) {
                // Zähle Events
                $eventCount = substr_count($data, "BEGIN:VEVENT");
                debug("Anzahl Events im Ergebnis", $eventCount);
                
                // Nur die ersten 500 Zeichen anzeigen
                debug("Ergebnis (Anfang)", substr($data, 0, 500) . "...");
                
                if ($eventCount === 0) {
                    debug("Problem", "Keine Events gefunden! Überprüfe die Filterparameter und das Quell-ICS-File.");
                }
            } else {
                debug("Problem", "Leeres Ergebnis von processCalendarData()");
            }
        } catch (Exception $e) {
            debug("Fehler beim Verarbeiten der Kalenderdaten", $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    } else {
        debug("Problem", "Die CalendarAPI-Klasse konnte nicht geladen werden.");
    }
    
    // Cache-Verzeichnis überprüfen
    echo "<h2>Cache-Überprüfung</h2>";
    $tempDir = sys_get_temp_dir();
    debug("Temp-Verzeichnis", $tempDir);
    
    // Suche nach Cache-Dateien für Calendar API
    $cacheDirContent = scandir($tempDir);
    $calendarCacheFiles = array_filter($cacheDirContent, function($file) {
        return strpos($file, 'ff_calendar_') === 0;
    });
    
    debug("Gefundene Calendar-Cache Dateien", count($calendarCacheFiles));
    if (count($calendarCacheFiles) > 0) {
        debug("Cache-Dateien", array_slice($calendarCacheFiles, 0, 10)); // Zeige max. 10 Dateien
    }
    
} catch (Exception $e) {
    debug("Kritischer Fehler", $e->getMessage() . "\n" . $e->getTraceAsString());
}

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    hr { border: 1px solid #eee; }
    h1 { color: #333; }
    h2 { color: #666; margin-top: 30px; }
    h3 { color: #999; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
</style>"; 