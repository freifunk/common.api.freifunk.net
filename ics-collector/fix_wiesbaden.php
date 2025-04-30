<?php
/**
 * Reparatur-Skript speziell für wiesbaden.ics
 */

require_once 'lib/ics-validator.php';

use ICal\IcsValidator;

$file = 'data/cottbus.ics';

echo "Validiere " . basename($file) . "...\n";

$validator = new IcsValidator();
$isValid = $validator->validateFile($file);

echo "Validierung: " . ($isValid ? "GÜLTIG" : "UNGÜLTIG") . "\n";

if (!$isValid) {
    echo "\nFehler:\n";
    foreach ($validator->getErrors() as $error) {
        echo " - $error\n";
    }
}

if (!empty($validator->getWarnings())) {
    echo "\nWarnungen:\n";
    foreach ($validator->getWarnings() as $warning) {
        echo " - $warning\n";
    }
}

// Spezielle Reparatur für Wiesbaden
if (!$isValid) {
    echo "\nRepariere " . basename($file) . "...\n";
    
    // Inhalt laden
    $content = file_get_contents($file);
    
    // Spezifisches Problem in wiesbaden.ics: Leere DTSTART/DTEND-Felder
    // Entweder entfernen oder mit gültigen Werten füllen
    
    // Option 1: Entferne das defekte Event komplett
    $pattern = '/BEGIN:VEVENT.*?END:VEVENT/s';
    if (preg_match($pattern, $content, $matches) && 
        (strpos($matches[0], 'DTSTART;TZID=Europe/Berlin:') !== false || 
         strpos($matches[0], 'DTEND;TZID=Europe/Berlin:') !== false)) {
        
        echo "Defektes Event gefunden. Erstelle Backup und entferne das Event...\n";
        
        // Backup erstellen
        file_put_contents($file . '.bak', $content);
        
        // Event entfernen
        $content = preg_replace($pattern, '', $content, 1);
        
        // Speichern
        file_put_contents($file, $content);
        
        echo "Event wurde entfernt.\n";
    } else {
        // Option 2: Manuell reparieren für das spezifische Problem
        $content = str_replace(
            "DTSTART;TZID=Europe/Berlin:\nDTEND;TZID=Europe/Berlin:",
            "DTSTART;TZID=Europe/Berlin:20250601T190000\nDTEND;TZID=Europe/Berlin:20250601T220000",
            $content
        );
        
        // Backup erstellen
        file_put_contents($file . '.bak', file_get_contents($file));
        
        // Speichern
        file_put_contents($file, $content);
        
        echo "Event wurde repariert mit einem Standard-Zeitraum.\n";
    }
    
    // Nochmal validieren
    $isValid = $validator->validateFile($file);
    echo "\nValiederung nach Reparatur: " . ($isValid ? "GÜLTIG" : "UNGÜLTIG") . "\n";
    
    if (!$isValid) {
        echo "\nVerbleibende Fehler:\n";
        foreach ($validator->getErrors() as $error) {
            echo " - $error\n";
        }
    }
}

echo "\nDone.\n"; 