<?php
/**
 * @category    Parser
 * @package     ics-parser
 */

namespace ICal;

class EventObject
{
    public ?string $summary = null;
    public ?string $dtstart = null;
    public ?string $dtend = null;
    public ?string $duration = null;
    public ?string $dtstamp = null;
    public ?string $uid = null;
    public ?string $created = null;
    public ?string $lastmodified = null;
    public ?string $description = null;
    public ?string $location = null;
    public ?string $sequence = null;
    public ?string $status = null;
    public ?string $transp = null;
    public ?string $organizer = null;
    public ?string $attendee = null;
    public ?string $url = null;
    public ?string $categories = null;
    public ?string $xWrSource = null;
    public ?string $xWrSourceUrl = null;
    public ?string $rrule = null;
    
    // Array-Versionen der Datumsfelder
    public ?array $dtstart_array = null;
    public ?array $dtend_array = null;
    public ?string $dtstart_tz = null;
    public ?string $dtend_tz = null;

    /**
     * EventObject constructor
     * 
     * @param array<string, mixed> $data Event data array
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (!property_exists($this, $key)) {
                    $variable = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', strtolower($key)))));
                } else {
                    $variable = $key;
                }

                if (is_array($value)) {
                     $this->{$variable} = $value;
                } else {
                    $this->{$variable} = stripslashes(trim(str_replace('\n', "\n", $value)));
                }
            }
        }
    }

    /**
     * Magic method for var_export() serialization
     * 
     * @param array<string, mixed> $anArray Exported array representation
     * @return self
     */
    public static function __set_state(array $anArray): self
    {
        $eventObject = new self($anArray);
        return $eventObject;
    }

    /**
     * Return Event data excluding anything blank
     * as ICS format 
     *
     * @return string
     */
    public function printIcs(): string
    {
        $crlf = "\r\n";
        $data = [];
        
        // Standardfelder
        $standardFields = [
            'SUMMARY'         => $this->summary ?? '',
            'DURATION'        => $this->duration ?? '',
            'DTSTAMP'         => $this->dtstamp ?? '',
            'UID'             => $this->uid ?? '',
            'CREATED'         => $this->created ?? '',
            'LAST-MODIFIED'   => $this->lastmodified ?? '',
            'DESCRIPTION'     => $this->description ?? '',
            'LOCATION'        => $this->location ?? '',
            'SEQUENCE'        => $this->sequence ?? '',
            'STATUS'          => $this->status ?? '',
            'TRANSP'          => $this->transp ?? '',
            'ORGANISER'       => $this->organizer ?? '',
            'URL'             => $this->url ?? '',
            'CATEGORIES'      => $this->categories ?? '',
            'X-WR-SOURCE'     => $this->xWrSource ?? '',
            'X-WR-SOURCE-URL' => $this->xWrSourceUrl ?? '',
            'RRULE'           => $this->rrule ?? '',
        ];
        
        // Erst null-Werte durch leere Strings ersetzen, dann trimmen
        $standardFieldsTrimmed = [];
        foreach ($standardFields as $key => $value) {
            if (is_string($value) && trim($value) !== '') {
                $standardFieldsTrimmed[$key] = trim($value);
            }
        }
        $data = array_merge($data, $standardFieldsTrimmed);
        
        // Spezialbehandlung für Felder mit Zeitzone
        $output = "BEGIN:VEVENT".$crlf;
        
        // DTSTART mit TZID
        if (isset($this->dtstart) && isset($this->dtstart_array) && isset($this->dtstart_array[0]['TZID'])) {
            $output .= sprintf("DTSTART;TZID=%s:%s%s", 
                $this->dtstart_array[0]['TZID'], 
                $this->dtstart_array[1], 
                $crlf);
        } elseif (isset($this->dtstart)) {
            $output .= sprintf("DTSTART:%s%s", $this->dtstart, $crlf);
        }
        
        // DTEND mit TZID
        if (isset($this->dtend) && isset($this->dtend_array) && isset($this->dtend_array[0]['TZID'])) {
            $output .= sprintf("DTEND;TZID=%s:%s%s", 
                $this->dtend_array[0]['TZID'], 
                $this->dtend_array[1], 
                $crlf);
        } elseif (isset($this->dtend)) {
            $output .= sprintf("DTEND:%s%s", $this->dtend, $crlf);
        }
        
        // Alle anderen Felder ausgeben
        foreach ($data as $key => $value) {
            $output .= sprintf("%s:%s%s", $key, $value, $crlf);
        }
        
        $output .= "END:VEVENT".$crlf;
        return $output;
    }

    /**
     * Return Event data excluding anything blank
     * within an HTML template
     *
     * @param string $html HTML template to use
     * @return string
     */
    public function printData(string $html = '<p>%s: %s</p>'): string
    {
        $data = [
            'SUMMARY'       => $this->summary ?? '',
            'DTSTART'       => $this->dtstart ?? '',
            'DTEND'         => $this->dtend ?? '',
            'DTSTART_TZ'    => $this->dtstart_tz ?? '',
            'DTEND_TZ'      => $this->dtend_tz ?? '',
            'DURATION'      => $this->duration ?? '',
            'DTSTAMP'       => $this->dtstamp ?? '',
            'UID'           => $this->uid ?? '',
            'CREATED'       => $this->created ?? '',
            'LAST-MODIFIED' => $this->lastmodified ?? '',
            'DESCRIPTION'   => $this->description ?? '',
            'LOCATION'      => $this->location ?? '',
            'SEQUENCE'      => $this->sequence ?? '',
            'STATUS'        => $this->status ?? '',
            'TRANSP'        => $this->transp ?? '',
            'ORGANISER'     => $this->organizer ?? '',
            'ATTENDEE(S)'   => $this->attendee ?? '',
        ];

        // Erst null-Werte durch leere Strings ersetzen, dann trimmen
        $data = array_map(function($value) {
            // Nur trimmen, wenn es ein String ist, sonst unverändert zurückgeben
            if (is_string($value)) {
                return trim($value);
            }
            return $value;
        }, $data);
        
        $data = array_filter($data);  // Remove any blank values
        $output = '';

        foreach ($data as $key => $value) {
            $output .= sprintf($html, $key, $value);
        }

        return $output;
    }

    /**
     * Fixes event data by setting default values for missing properties:
     * - Sets DTEND to 1 hour after DTSTART if missing
     */
    public function fixEventData(): void
    {
        // Wenn DTEND und DURATION fehlen, setze DTEND auf DTSTART + 1 Stunde
        if (!isset($this->dtend) && !isset($this->duration)) {
            if (isset($this->dtstart_array)) {
                // Kopiere das DTSTART_array in das DTEND_array und erhöhe Timestamp um 1 Stunde
                $this->dtend_array = $this->dtstart_array;
                $this->dtend_array[2] = $this->dtstart_array[2] + 3600; // +1 Stunde
                
                // Setze DTEND auf Basis von DTSTART mit +1 Stunde
                $dtEnd = $this->dtstart;
                
                // Bei Datumsformaten mit T (Zeit)
                if (strpos($dtEnd, 'T') !== false) {
                    $format = strlen($dtEnd) > 13 ? 'Ymd\THis' : 'Ymd\THi'; // Mit oder ohne Sekunden
                    $date = \DateTime::createFromFormat($format, $dtEnd);
                    if ($date) {
                        $date->modify('+1 hour');
                        $this->dtend = $date->format($format);
                    } else {
                        // Fallback: String-Manipulation für andere Formate
                        // Format: 20240101T120000
                        $dateString = substr($dtEnd, 0, 8); // YYYYMMDD
                        $timeString = substr($dtEnd, 9); // HHMMSS or HHMM
                        
                        if (strlen($timeString) >= 4) {
                            $hours = intval(substr($timeString, 0, 2));
                            $mins = substr($timeString, 2, 2);
                            $secs = strlen($timeString) > 4 ? substr($timeString, 4) : '';
                            
                            $hours = ($hours + 1) % 24;
                            $this->dtend = $dateString . 'T' . sprintf('%02d', $hours) . $mins . $secs;
                        } else {
                            // Wenn das Zeitformat nicht erkannt wird, füge einfach 1 Stunde als String hinzu
                            $this->dtend = $dtEnd;
                        }
                    }
                } else {
                    // Wenn nur Datum ohne Zeit: +1 Tag
                    $format = 'Ymd';
                    $date = \DateTime::createFromFormat($format, $dtEnd);
                    if ($date) {
                        $date->modify('+1 day');
                        $this->dtend = $date->format($format);
                    } else {
                        // Fallback: Einfach den gleichen Wert verwenden
                        $this->dtend = $dtEnd;
                    }
                }
            }
        }
    }
}
