<?php

require (realpath(dirname(__FILE__))  . '/ics-merger.php');
require_once (realpath(dirname(__FILE__))  . '/ics-validator.php');

use ICal\IcsValidator;

const MERGED_FILE_NAME = "/../data/ffMerged.ics";
const LOG_FILE = "/../logs/ics_updater.log";

$configs = parse_ini_file(realpath(dirname(__FILE__))  .  '/../api-config.ini', true);
$mergedIcsHeader = $configs['MERGED_ICS_HEADER'];

// Stelle sicher, dass das Log-Verzeichnis existiert
$logDir = dirname(realpath(dirname(__FILE__)) . LOG_FILE);
if (!is_dir($logDir)) {
	mkdir($logDir, 0755, true);
}

// Log-Funktion
function logMessage($message, $logToConsole = true) {
	$timestamp = date('Y-m-d H:i:s');
	$formattedMessage = "[$timestamp] $message" . PHP_EOL;
	
	// In Datei loggen
	file_put_contents(realpath(dirname(__FILE__)) . LOG_FILE, $formattedMessage, FILE_APPEND);
	
	// Wenn gewünscht auch auf Konsole ausgeben
	if ($logToConsole) {
		echo $formattedMessage;
	}
}

// Initialisiere den Validator
$validator = new IcsValidator();

$summary = file_get_contents($configs['COMPONENT_URL']['ICS_COLLECTOR_URL'] . '?format=json');
$summary = json_decode($summary, true);
$merger = new IcsMerger($configs['MERGED_ICS_HEADER']);

$validationStats = [
	'total' => 0,
	'valid' => 0,
	'fixed' => 0,
	'invalid' => 0,
	'skipped' => 0
];

foreach($summary as $key => $value) {
	logMessage('Retrieving ics from ' . $key . '..');
	$validationStats['total']++;
	
	try {
		$ics = file_get_contents($value['url']);
		
		// Speichere die Originaldatei
		$filePath = realpath(dirname(__FILE__)) . '/../data/' . $key . '.ics';
		$fp = fopen($filePath, 'w+');
		fwrite($fp, $ics);
		fclose($fp);
		
		// Überprüfe ob der Inhalt leer ist oder nicht mit BEGIN:VCALENDAR beginnt
		if (empty($ics) || strpos($ics, 'BEGIN:VCALENDAR') === false) {
			logMessage("  FEHLER: Keine gültige ICS-Datei für " . $key);
			$validationStats['skipped']++;
			unset($summary[$key]);
			continue;
		}
		
		// Validiere die Datei
		$isValid = $validator->validateFile($filePath);
		
		if (!$isValid) {
			logMessage("  WARNUNG: Ungültige ICS-Datei für " . $key);
			
			// Logge Fehler
			foreach ($validator->getErrors() as $error) {
				logMessage("    - Error: $error", false);
			}
			
			// Versuche, die Datei zu reparieren
			logMessage("  Versuche Reparatur für " . $key);
			
			$content = file_get_contents($filePath);
			$fixedContent = $validator->fixIcsContent($content);
			
			if ($content !== $fixedContent) {
				// Erstelle Backup
				$backupFile = $filePath . '.bak.' . date('YmdHis');
				file_put_contents($backupFile, $content);
				logMessage("  Backup erstellt: " . basename($backupFile));
				
				// Speichere reparierte Datei
				file_put_contents($filePath, $fixedContent);
				logMessage("  Datei repariert: " . basename($filePath));
				
				// Validiere erneut
				$isValid = $validator->validateFile($filePath);
				
				if ($isValid) {
					logMessage("  Datei wurde erfolgreich repariert");
					$validationStats['fixed']++;
					$ics = $fixedContent; // Verwende die reparierte Version für den Merger
				} else {
					logMessage("  Datei konnte nicht repariert werden, überspringe");
					$validationStats['invalid']++;
					unset($summary[$key]);
					continue;
				}
			} else {
				logMessage("  Keine automatische Reparatur möglich, überspringe");
				$validationStats['invalid']++;
				unset($summary[$key]);
				continue;
			}
		} else {
			$validationStats['valid']++;
			logMessage("  Datei ist gültig");
		}
		
		// Wenn wir bis hier kommen, ist die Datei (möglicherweise nach Reparatur) gültig
		$customParams = array($configs['CUSTOM_PROPERTY_NAME']['SOURCE_PROPERTY'] => $key);
		if (array_key_exists('communityurl', $value))
			$customParams[$configs['CUSTOM_PROPERTY_NAME']['SOURCE_URL_PROPERTY']] = removeProtocolFromURL($value['communityurl']);
		$merger->add($ics, $customParams);
		
	} catch (Exception $e) {
		logMessage("  FEHLER bei " . $key . ": " . $e->getMessage());
		$validationStats['skipped']++;
		unset($summary[$key]);
	}
}

logMessage('Merge all ics files..');
$mergedFilePath = realpath(dirname(__FILE__)) . MERGED_FILE_NAME;
$fp = fopen($mergedFilePath, 'w+');
fwrite($fp, IcsMerger::getRawText($merger->getResult()));
fclose($fp);

// Validiere die zusammengeführte Datei als zusätzliche Sicherheit
$isValid = $validator->validateFile($mergedFilePath);
if (!$isValid) {
	logMessage("WARNUNG: Die zusammengeführte Datei ist nicht gültig!");
	foreach ($validator->getErrors() as $error) {
		logMessage("  - Error: $error");
	}
} else {
	logMessage("Die zusammengeführte Datei ist gültig.");
}

$merger->warmupCache(realpath(dirname(__FILE__)) . MERGED_FILE_NAME);

// Zusammenfassung der Validierungsstatistik
logMessage("\nZusammenfassung der ICS-Validierung:");
logMessage("  Gesamt:     " . $validationStats['total']);
logMessage("  Gültig:     " . $validationStats['valid']);
logMessage("  Repariert:  " . $validationStats['fixed']);
logMessage("  Ungültig:   " . $validationStats['invalid']);
logMessage("  Übersprungen: " . $validationStats['skipped']);

function removeProtocolFromURL($value)
{
	return str_replace('http://', '', str_replace('https://', '', $value));
}
