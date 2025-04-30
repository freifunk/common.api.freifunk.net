<?php
/**
 * ICS-Validator 
 * 
 * Funktionen zur Validierung von ICS-Dateien
 */

namespace ICal;

class IcsValidator {
    /**
     * Fehlermeldungen bei der Validierung
     * 
     * @var array<string>
     */
    protected array $errors = [];
    
    /**
     * Warnungen bei der Validierung
     * 
     * @var array<string>
     */
    protected array $warnings = [];
    
    /**
     * Validiert eine ICS-Datei
     * 
     * @param string $filePath Pfad zur ICS-Datei
     * @return bool True wenn die Datei gültig ist, sonst false
     */
    public function validateFile(string $filePath): bool {
        if (!file_exists($filePath)) {
            $this->errors[] = "Datei nicht gefunden: $filePath";
            return false;
        }
        
        $content = file_get_contents($filePath);
        return $this->validateContent($content);
    }
    
    /**
     * Validiert den Inhalt einer ICS-Datei
     * 
     * @param string $content Inhalt der ICS-Datei
     * @return bool True wenn der Inhalt gültig ist, sonst false
     */
    public function validateContent(string $content): bool {
        $this->errors = [];
        $this->warnings = [];
        
        // Grundlegende Struktur-Validierung
        $structureValid = $this->validateStructure($content);
        
        // Event-Validierung
        $eventsValid = $this->validateEvents($content);
        
        return $structureValid && $eventsValid;
    }
    
    /**
     * Validiert die grundlegende Struktur einer ICS-Datei
     * 
     * @param string $content Inhalt der ICS-Datei
     * @return bool True wenn die Struktur gültig ist, sonst false
     */
    protected function validateStructure(string $content): bool {
        $valid = true;
        
        // Prüfe ob die Datei mit BEGIN:VCALENDAR beginnt und mit END:VCALENDAR endet
        if (!preg_match('/^\s*BEGIN:VCALENDAR/i', $content)) {
            $this->errors[] = "Die Datei beginnt nicht mit BEGIN:VCALENDAR";
            $valid = false;
        }
        
        if (!preg_match('/END:VCALENDAR\s*$/i', $content)) {
            $this->errors[] = "Die Datei endet nicht mit END:VCALENDAR";
            $valid = false;
        }
        
        // Prüfe ob VERSION und PRODID vorhanden sind (RFC5545)
        if (!preg_match('/VERSION:/i', $content)) {
            $this->errors[] = "VERSION fehlt (required by RFC5545)";
            $valid = false;
        }
        
        if (!preg_match('/PRODID:/i', $content)) {
            $this->errors[] = "PRODID fehlt (required by RFC5545)";
            $valid = false;
        }
        
        return $valid;
    }
    
    /**
     * Validiert die Events in einer ICS-Datei
     * 
     * @param string $content Inhalt der ICS-Datei
     * @return bool True wenn alle Events gültig sind, sonst false
     */
    protected function validateEvents(string $content): bool {
        $valid = true;
        
        // Extrahiere alle VEVENT-Blöcke
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches);
        
        if (empty($matches[0])) {
            $this->warnings[] = "Keine Events gefunden";
            return true; // Keine Events ist nicht unbedingt ein Fehler
        }
        
        foreach ($matches[1] as $index => $eventContent) {
            $eventNumber = $index + 1;
            
            // Prüfe auf leere required Felder (DTSTART, DTEND/DURATION, UID)
            if (preg_match('/DTSTART;[^:]*:(\s|$)/m', $eventContent) || 
                preg_match('/DTSTART:(\s|$)/m', $eventContent)) {
                $this->errors[] = "Event #$eventNumber: DTSTART hat keinen Wert";
                $valid = false;
            }
            
            // Prüfe auf UID (required by RFC5545)
            if (!preg_match('/UID:/i', $eventContent)) {
                $this->errors[] = "Event #$eventNumber: UID fehlt (required by RFC5545)";
                $valid = false;
            }
            
            // Prüfe ob DTEND oder DURATION vorhanden ist, wenn nicht wird eine Warnung ausgegeben
            if (!preg_match('/DTEND:/i', $eventContent) && !preg_match('/DURATION:/i', $eventContent)) {
                $this->warnings[] = "Event #$eventNumber: Weder DTEND noch DURATION vorhanden";
            } elseif (preg_match('/DTEND;[^:]*:(\s|$)/m', $eventContent) || 
                      preg_match('/DTEND:(\s|$)/m', $eventContent)) {
                $this->errors[] = "Event #$eventNumber: DTEND hat keinen Wert";
                $valid = false;
            }
            
            // Prüfe ob DTSTART und DTEND/DURATION nicht im Konflikt stehen
            if (preg_match('/DTEND:/i', $eventContent) && preg_match('/DURATION:/i', $eventContent)) {
                $this->errors[] = "Event #$eventNumber: DTEND und DURATION dürfen nicht gleichzeitig vorkommen";
                $valid = false;
            }
        }
        
        return $valid;
    }
    
    /**
     * Gibt die während der Validierung aufgetretenen Fehler zurück
     * 
     * @return array<string> Array mit Fehlermeldungen
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Gibt die während der Validierung aufgetretenen Warnungen zurück
     * 
     * @return array<string> Array mit Warnungen
     */
    public function getWarnings(): array {
        return $this->warnings;
    }
    
    /**
     * Korrigiert bekannte Probleme in einer ICS-Datei
     * 
     * @param string $content Inhalt der ICS-Datei
     * @return string Korrigierter Inhalt
     */
    public function fixIcsContent(string $content): string {
        // Entferne Events mit leeren DTSTART/DTEND-Feldern
        $content = preg_replace('/BEGIN:VEVENT.*?DTSTART;[^:]*:(\s|$).*?END:VEVENT\r?\n?/s', '', $content);
        $content = preg_replace('/BEGIN:VEVENT.*?DTSTART:(\s|$).*?END:VEVENT\r?\n?/s', '', $content);
        $content = preg_replace('/BEGIN:VEVENT.*?DTEND;[^:]*:(\s|$).*?END:VEVENT\r?\n?/s', '', $content);
        $content = preg_replace('/BEGIN:VEVENT.*?DTEND:(\s|$).*?END:VEVENT\r?\n?/s', '', $content);
        
        return $content;
    }
} 