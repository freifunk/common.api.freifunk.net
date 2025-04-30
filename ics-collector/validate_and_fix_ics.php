<?php
/**
 * ICS-Validierungs- und Reparatur-Skript
 */

require_once 'lib/ics-validator.php';

use ICal\IcsValidator;

// Funktion zum Scannen des Datenverzeichnisses nach ICS-Dateien
function scanForIcsFiles(string $directory): array {
    $icsFiles = [];
    if (!is_dir($directory)) {
        echo "Verzeichnis nicht gefunden: $directory\n";
        return $icsFiles;
    }
    
    $files = scandir($directory);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'ics') {
            $icsFiles[] = $directory . '/' . $file;
        }
    }
    
    return $icsFiles;
}

// Funktion zum Validieren einer ICS-Datei
function validateIcsFile(string $file): bool {
    $validator = new IcsValidator();
    $isValid = $validator->validateFile($file);
    
    echo "Validierung von " . basename($file) . ": " . ($isValid ? "GÜLTIG" : "UNGÜLTIG") . "\n";
    
    if (!$isValid || !empty($validator->getWarnings())) {
        if (!$isValid) {
            echo "Fehler:\n";
            foreach ($validator->getErrors() as $error) {
                echo " - $error\n";
            }
        }
        
        if (!empty($validator->getWarnings())) {
            echo "Warnungen:\n";
            foreach ($validator->getWarnings() as $warning) {
                echo " - $warning\n";
            }
        }
    }
    
    return $isValid;
}

// Funktion zum Reparieren einer ICS-Datei
function fixIcsFile(string $file): void {
    $validator = new IcsValidator();
    $content = file_get_contents($file);
    
    $fixedContent = $validator->fixIcsContent($content);
    
    if ($content !== $fixedContent) {
        // Backup der Original-Datei erstellen
        $backupFile = $file . '.bak';
        file_put_contents($backupFile, $content);
        echo "Backup erstellt: " . basename($backupFile) . "\n";
        
        // Korrigierte Datei speichern
        file_put_contents($file, $fixedContent);
        echo "Datei wurde repariert: " . basename($file) . "\n";
        
        // Nochmal validieren
        validateIcsFile($file);
    } else {
        echo "Keine Reparatur notwendig oder nicht automatisch reparierbar.\n";
    }
}

// Hauptteil des Skripts
echo "ICS-Validierungs- und Reparatur-Tool\n";
echo "===================================\n\n";

$directory = 'data';
echo "Suche nach ICS-Dateien in $directory...\n";
$icsFiles = scanForIcsFiles($directory);

if (empty($icsFiles)) {
    echo "Keine ICS-Dateien gefunden.\n";
    exit(0);
}

echo "Gefundene ICS-Dateien: " . count($icsFiles) . "\n\n";

$invalidFiles = [];

// Validiere alle gefundenen Dateien
foreach ($icsFiles as $file) {
    if (!validateIcsFile($file)) {
        $invalidFiles[] = $file;
    }
    echo "\n";
}

// Repariere ungültige Dateien
if (!empty($invalidFiles)) {
    echo "Repariere ungültige Dateien...\n";
    foreach ($invalidFiles as $file) {
        echo "\nRepariere " . basename($file) . "...\n";
        fixIcsFile($file);
    }
} else {
    echo "Alle Dateien sind gültig.\n";
}

echo "\nDone.\n"; 