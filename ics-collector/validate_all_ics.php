<?php
/**
 * Validierungs- und Reparatur-Skript für alle ICS-Dateien im data-Verzeichnis
 * 
 * Dieses Skript kann als Cron-Job eingerichtet werden, um die Integrität
 * aller ICS-Dateien regelmäßig zu überprüfen und fehlerhafte Dateien zu reparieren.
 * 
 * Empfehlung: Ausführung stündlich in den Nachtstunden, wenn wenig Benutzer aktiv sind.
 * Crontab-Eintrag: 0 1-5 * * * php /pfad/zu/validate_all_ics.php
 */

require_once 'lib/ics-validator.php';

use ICal\IcsValidator;

// Konfiguration
$dataDirectory = 'data';
$logFile = 'logs/ics_validation.log';
$skipBackups = true;  // Überspringe .bak-Dateien

// Stellt sicher, dass das Log-Verzeichnis existiert
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Logging-Funktion
function logMessage(string $message, string $logFile): void {
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    echo $formattedMessage;
}

// Funktion zum Scannen des Datenverzeichnisses nach ICS-Dateien
function scanForIcsFiles(string $directory, bool $skipBackups): array {
    $icsFiles = [];
    if (!is_dir($directory)) {
        return $icsFiles;
    }
    
    $files = scandir($directory);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'ics') {
            // Überspringe .bak-Dateien wenn gewünscht
            if ($skipBackups && strpos($file, '.bak') !== false) {
                continue;
            }
            $icsFiles[] = $directory . '/' . $file;
        }
    }
    
    return $icsFiles;
}

// Funktion zum Validieren und Reparieren einer ICS-Datei
function validateAndRepairIcsFile(string $file, string $logFile): bool {
    $validator = new IcsValidator();
    $isValid = $validator->validateFile($file);
    
    logMessage("Validierung von " . basename($file) . ": " . ($isValid ? "GÜLTIG" : "UNGÜLTIG"), $logFile);
    
    // Log validation results
    if (!$isValid) {
        foreach ($validator->getErrors() as $error) {
            logMessage(" - Error: $error", $logFile);
        }
    }
    
    if (!empty($validator->getWarnings())) {
        foreach ($validator->getWarnings() as $warning) {
            logMessage(" - Warning: $warning", $logFile);
        }
    }
    
    // Attempt to repair the file if invalid
    if (!$isValid) {
        logMessage("Versuche Reparatur: " . basename($file), $logFile);
        
        $content = file_get_contents($file);
        $fixedContent = $validator->fixIcsContent($content);
        
        if ($content !== $fixedContent) {
            // Create backup with timestamp
            $backupFile = $file . '.bak.' . date('YmdHis');
            file_put_contents($backupFile, $content);
            logMessage("Backup erstellt: " . basename($backupFile), $logFile);
            
            // Save fixed content
            file_put_contents($file, $fixedContent);
            logMessage("Datei repariert: " . basename($file), $logFile);
            
            // Validate again
            $isValid = $validator->validateFile($file);
            
            if (!$isValid) {
                logMessage("Datei ist nach Reparatur immer noch ungültig: " . basename($file), $logFile);
                foreach ($validator->getErrors() as $error) {
                    logMessage(" - Error nach Reparatur: $error", $logFile);
                }
            }
        } else {
            logMessage("Keine automatische Reparatur möglich für: " . basename($file), $logFile);
        }
    }
    
    return $isValid;
}

// Hauptteil des Skripts
logMessage("Start ICS-Validierung und Reparatur", $logFile);
logMessage("Suche nach ICS-Dateien in $dataDirectory...", $logFile);
$icsFiles = scanForIcsFiles($dataDirectory, $skipBackups);

if (empty($icsFiles)) {
    logMessage("Keine ICS-Dateien gefunden.", $logFile);
    exit(0);
}

logMessage("Gefundene ICS-Dateien: " . count($icsFiles), $logFile);

$invalidFiles = [];
$validFiles = [];

// Validiere alle gefundenen Dateien
foreach ($icsFiles as $file) {
    $result = validateAndRepairIcsFile($file, $logFile);
    if ($result) {
        $validFiles[] = $file;
    } else {
        $invalidFiles[] = $file;
    }
}

// Zusammenfassung
logMessage("\nZusammenfassung:", $logFile);
logMessage("Geprüfte Dateien: " . count($icsFiles), $logFile);
logMessage("Gültige Dateien: " . count($validFiles), $logFile);
logMessage("Ungültige Dateien (nach Reparaturversuch): " . count($invalidFiles), $logFile);

if (!empty($invalidFiles)) {
    logMessage("\nFolgende Dateien konnten nicht repariert werden:", $logFile);
    foreach ($invalidFiles as $file) {
        logMessage(" - " . basename($file), $logFile);
    }
}

logMessage("\nValidierung abgeschlossen.", $logFile); 