<?php
/**
 * Validierungs- und Reparatur-Skript für ICS-Dateien
 * 
 * Dieses Skript kann verwendet werden, um:
 * 1. Alle ICS-Dateien im data-Verzeichnis zu validieren und zu reparieren
 * 2. Eine einzelne ICS-Datei zu validieren und zu reparieren
 * 
 * Verwendung:
 * - Alle Dateien prüfen:   php validate_all_ics.php
 * - Bestimmte Datei:       php validate_all_ics.php --file=data/filename.ics
 * - Nur validieren:        php validate_all_ics.php --validate-only
 * - Hilfe anzeigen:        php validate_all_ics.php --help
 * 
 * Für Cron-Job: 0 1-5 * * * php /pfad/zu/validate_all_ics.php
 */

require_once 'lib/IcsValidator.php';

use ICal\IcsValidator;

// Konfiguration
$dataDirectory = 'data';
$logFile = 'logs/ics_validation.log';
$skipBackups = true;  // Überspringe .bak-Dateien
$validateOnly = false; // Standardmäßig auch reparieren
$specificFile = null;  // Standardmäßig alle Dateien prüfen
$verbose = true;       // Ausführliche Ausgabe

// Kommandozeilen-Argumente verarbeiten
parseCommandLineArguments();

// Stellt sicher, dass das Log-Verzeichnis existiert
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Hauptlogik basierend auf Parametern ausführen
if ($specificFile !== null) {
    // Einzelne Datei validieren
    logMessage("Validiere einzelne Datei: " . basename($specificFile), $logFile);
    $result = validateAndRepairIcsFile($specificFile, $logFile, $validateOnly);
    exit($result ? 0 : 1);
} else {
    // Alle Dateien validieren
    validateAllIcsFiles();
}

/**
 * Verarbeitet Kommandozeilen-Argumente
 */
function parseCommandLineArguments(): void {
    global $validateOnly, $specificFile, $verbose;
    
    $options = getopt('', ['file:', 'validate-only', 'help', 'quiet']);
    
    if (isset($options['help'])) {
        showHelp();
        exit(0);
    }
    
    if (isset($options['validate-only'])) {
        $validateOnly = true;
    }
    
    if (isset($options['file'])) {
        $specificFile = $options['file'];
        if (!file_exists($specificFile)) {
            echo "Fehler: Datei nicht gefunden: $specificFile\n";
            exit(1);
        }
    }
    
    if (isset($options['quiet'])) {
        $verbose = false;
    }
}

/**
 * Zeigt Hilfe-Text an
 */
function showHelp(): void {
    echo "ICS Validation and Repair Tool\n";
    echo "=============================\n\n";
    echo "Usage:\n";
    echo "  php validate_all_ics.php [options]\n\n";
    echo "Options:\n";
    echo "  --file=PATH           Validate and repair a specific ICS file\n";
    echo "  --validate-only       Only validate, don't attempt to repair files\n";
    echo "  --quiet               Suppress output to screen (still logs to file)\n";
    echo "  --help                Show this help message\n\n";
    echo "Examples:\n";
    echo "  php validate_all_ics.php                    # Validate and repair all ICS files\n";
    echo "  php validate_all_ics.php --file=data/wiesbaden.ics   # Validate specific file\n";
    echo "  php validate_all_ics.php --validate-only    # Only validate files\n";
}

/**
 * Validiert alle ICS-Dateien im Datenverzeichnis
 */
function validateAllIcsFiles(): void {
    global $dataDirectory, $skipBackups, $logFile, $validateOnly;
    
    logMessage("Start ICS-Validierung" . ($validateOnly ? "" : " und Reparatur"), $logFile);
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
        $result = validateAndRepairIcsFile($file, $logFile, $validateOnly);
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
    logMessage("Ungültige Dateien" . ($validateOnly ? "" : " (nach Reparaturversuch)") . ": " . count($invalidFiles), $logFile);

    if (!empty($invalidFiles)) {
        logMessage("\nFolgende Dateien sind ungültig:", $logFile);
        foreach ($invalidFiles as $file) {
            logMessage(" - " . basename($file), $logFile);
        }
    }

    logMessage("\nValidierung abgeschlossen.", $logFile);
}

/**
 * Funktion zum Scannen des Datenverzeichnisses nach ICS-Dateien
 * 
 * @param string $directory Verzeichnis, das durchsucht werden soll
 * @param bool $skipBackups Backup-Dateien (.bak) überspringen?
 * @return array<string> Array mit Dateipfaden
 */
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

/**
 * Funktion zum Validieren und Reparieren einer ICS-Datei
 * 
 * @param string $file Zu validierende Datei
 * @param string $logFile Pfad zur Log-Datei
 * @param bool $validateOnly Nur validieren, nicht reparieren
 * @return bool True wenn die Datei gültig ist (oder erfolgreich repariert wurde)
 */
function validateAndRepairIcsFile(string $file, string $logFile, bool $validateOnly = false): bool {
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
    
    // Attempt to repair the file if invalid and repair mode is enabled
    if (!$isValid && !$validateOnly) {
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
            } else {
                logMessage("Datei wurde erfolgreich repariert: " . basename($file), $logFile);
            }
        } else {
            logMessage("Keine automatische Reparatur möglich für: " . basename($file), $logFile);
        }
    }
    
    return $isValid;
}

/**
 * Logging-Funktion, die sowohl in Datei als auch auf Bildschirm ausgibt
 * 
 * @param string $message Nachricht, die geloggt werden soll
 * @param string $logFile Pfad zur Log-Datei
 */
function logMessage(string $message, string $logFile): void {
    global $verbose;
    
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp] $message" . PHP_EOL;
    
    // Log in Datei
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    
    // Log auf Bildschirm, wenn nicht im quiet-Modus
    if ($verbose) {
        echo $formattedMessage;
    }
} 