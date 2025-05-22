<?php
/**
 * @category    Parser
 * @package     ics-parser
 */

namespace ICal;

/**
 * Represents an iCalendar event
 */
class EventObject
{
    private ?string $summary = null;
    private ?string $dtstart = null;
    private ?string $dtend = null;
    private ?string $duration = null;
    private ?string $dtstamp = null;
    private ?string $uid = null;
    private ?string $created = null;
    private ?string $lastmodified = null;
    private ?string $description = null;
    private ?string $location = null;
    private ?string $sequence = null;
    private ?string $status = null;
    private ?string $transp = null;
    private ?string $organizer = null;
    private ?string $attendee = null;
    private ?string $url = null;
    private ?string $categories = null;
    private ?string $xWrSource = null;
    private ?string $xWrSourceUrl = null;
    private ?string $rrule = null;
    
    // Array versions of date fields
    private ?array $dtstart_array = null;
    private ?array $dtend_array = null;
    private ?string $dtstart_tz = null;
    private ?string $dtend_tz = null;

    /**
     * EventObject constructor - empty by default as we use the builder pattern
     */
    public function __construct()
    {
        // Empty constructor - use setters for property initialization
    }
    
    /**
     * Normalizes a property key to match class property names
     * 
     * @param string $key The property key to normalize
     * @return string The normalized key
     */
    private function normalizeKey(string $key): string
    {
        // Handle special cases
        if ($key === 'LAST-MODIFIED') {
            return 'lastmodified';
        } elseif ($key === 'X-WR-SOURCE') {
            return 'xWrSource';
        } elseif ($key === 'X-WR-SOURCE-URL') {
            return 'xWrSourceUrl';
        } else {
            return lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', strtolower($key)))));
        }
    }

    /**
     * Magic method for var_export() serialization
     * 
     * Required for ICal class caching functionality, where EventObject instances
     * are serialized to PHP code using var_export() and later reconstructed
     * from cache files.
     * 
     * @param array<string, mixed> $anArray Exported array representation
     * @return self
     */
    public static function __set_state(array $anArray): self
    {
        $eventObject = new self();
        
        foreach ($anArray as $key => $value) {
            $normalizedKey = $eventObject->normalizeKey($key);
            $setter = 'set' . ucfirst($normalizedKey);
            
            if (method_exists($eventObject, $setter)) {
                $eventObject->$setter($value);
            }
        }
        
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
            'SUMMARY'         => $this->getSummary() ?? '',
            'DURATION'        => $this->getDuration() ?? '',
            'DTSTAMP'         => $this->getDtstamp() ?? '',
            'UID'             => $this->getUid() ?? '',
            'CREATED'         => $this->getCreated() ?? '',
            'LAST-MODIFIED'   => $this->getLastmodified() ?? '',
            'DESCRIPTION'     => $this->getDescription() ?? '',
            'LOCATION'        => $this->getLocation() ?? '',
            'SEQUENCE'        => $this->getSequence() ?? '',
            'STATUS'          => $this->getStatus() ?? '',
            'TRANSP'          => $this->getTransp() ?? '',
            'ORGANISER'       => $this->getOrganizer() ?? '',
            'URL'             => $this->getUrl() ?? '',
            'CATEGORIES'      => $this->getCategories() ?? '',
            'X-WR-SOURCE'     => $this->getXWrSource() ?? '',
            'X-WR-SOURCE-URL' => $this->getXWrSourceUrl() ?? '',
            'RRULE'           => $this->getRrule() ?? '',
        ];
        
        // Erst null-Werte filtern, dann trimmen (nur Strings)
        $standardFieldsTrimmed = [];
        foreach ($standardFields as $key => $value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    $standardFieldsTrimmed[$key] = $trimmed;
                }
            }
        }
        $data = array_merge($data, $standardFieldsTrimmed);
        
        // Spezialbehandlung für Felder mit Zeitzone
        $output = "BEGIN:VEVENT".$crlf;
        
        // DTSTART mit TZID
        if ($this->getDtstart() && $this->getDtstartArray() && isset($this->getDtstartArray()[0]['TZID'])) {
            $output .= sprintf("DTSTART;TZID=%s:%s%s", 
                $this->getDtstartArray()[0]['TZID'], 
                $this->getDtstartArray()[1], 
                $crlf);
        } elseif ($this->getDtstart()) {
            $output .= sprintf("DTSTART:%s%s", $this->getDtstart(), $crlf);
        }
        
        // DTEND mit TZID
        if ($this->getDtend() && $this->getDtendArray() && isset($this->getDtendArray()[0]['TZID'])) {
            $output .= sprintf("DTEND;TZID=%s:%s%s", 
                $this->getDtendArray()[0]['TZID'], 
                $this->getDtendArray()[1], 
                $crlf);
        } elseif ($this->getDtend()) {
            $output .= sprintf("DTEND:%s%s", $this->getDtend(), $crlf);
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
            'SUMMARY'       => $this->getSummary() ?? '',
            'DTSTART'       => $this->getDtstart() ?? '',
            'DTEND'         => $this->getDtend() ?? '',
            'DTSTART_TZ'    => $this->getDtstartTz() ?? '',
            'DTEND_TZ'      => $this->getDtendTz() ?? '',
            'DURATION'      => $this->getDuration() ?? '',
            'DTSTAMP'       => $this->getDtstamp() ?? '',
            'UID'           => $this->getUid() ?? '',
            'CREATED'       => $this->getCreated() ?? '',
            'LAST-MODIFIED' => $this->getLastmodified() ?? '',
            'DESCRIPTION'   => $this->getDescription() ?? '',
            'LOCATION'      => $this->getLocation() ?? '',
            'SEQUENCE'      => $this->getSequence() ?? '',
            'STATUS'        => $this->getStatus() ?? '',
            'TRANSP'        => $this->getTransp() ?? '',
            'ORGANISER'     => $this->getOrganizer() ?? '',
            'ATTENDEE(S)'   => $this->getAttendee() ?? '',
        ];

        // Verarbeite nur gültige Strings
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
        // If DTEND and DURATION are missing, set DTEND to DTSTART + 1 hour
        if (!$this->getDtend() && !$this->getDuration()) {
            if ($this->getDtstartArray()) {
                // Copy the DTSTART_array to DTEND_array and increase timestamp by 1 hour
                $dtstartArray = $this->getDtstartArray();
                $dtendArray = $dtstartArray;
                $dtendArray[2] = $dtstartArray[2] + 3600; // +1 hour
                $this->setDtendArray($dtendArray);
                
                // Set DTEND based on DTSTART with +1 hour
                $dtEnd = $this->getDtstart();
                
                // For date formats with T (time)
                if (strpos($dtEnd, 'T') !== false) {
                    $format = strlen($dtEnd) > 13 ? 'Ymd\THis' : 'Ymd\THi'; // With or without seconds
                    $date = \DateTime::createFromFormat($format, $dtEnd);
                    if ($date) {
                        $date->modify('+1 hour');
                        $this->setDtend($date->format($format));
                    } else {
                        // Fallback: String manipulation for other formats
                        // Format: 20240101T120000
                        $dateString = substr($dtEnd, 0, 8); // YYYYMMDD
                        $timeString = substr($dtEnd, 9); // HHMMSS or HHMM
                        
                        if (strlen($timeString) >= 4) {
                            $hours = intval(substr($timeString, 0, 2));
                            $mins = substr($timeString, 2, 2);
                            $secs = strlen($timeString) > 4 ? substr($timeString, 4) : '';
                            
                            $hours = ($hours + 1) % 24;
                            $this->setDtend($dateString . 'T' . sprintf('%02d', $hours) . $mins . $secs);
                        } else {
                            // If time format is not recognized, just add 1 hour as string
                            $this->setDtend($dtEnd);
                        }
                    }
                } else {
                    // If only date without time: +1 day
                    $format = 'Ymd';
                    $date = \DateTime::createFromFormat($format, $dtEnd);
                    if ($date) {
                        $date->modify('+1 day');
                        $this->setDtend($date->format($format));
                    } else {
                        // Fallback: Just use the same value
                        $this->setDtend($dtEnd);
                    }
                }
            }
        }
    }

    /**
     * Helper method for formatting values
     * 
     * @param mixed $value The value to format
     * @return mixed The formatted value
     */
    private function formatValue($value)
    {
        if (is_array($value)) {
            return $value;
        } else {
            return stripslashes(trim(str_replace('\n', "\n", $value ?? '')));
        }
    }

    // Getter und Setter für alle Properties
    
    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary($value): self
    {
        $this->summary = $this->formatValue($value);
        return $this;
    }

    public function getDtstart(): ?string
    {
        return $this->dtstart;
    }

    public function setDtstart($value): self
    {
        $this->dtstart = $this->formatValue($value);
        return $this;
    }

    public function getDtend(): ?string
    {
        return $this->dtend;
    }

    public function setDtend($value): self
    {
        $this->dtend = $this->formatValue($value);
        return $this;
    }

    public function getDuration(): ?string
    {
        return $this->duration;
    }

    public function setDuration($value): self
    {
        $this->duration = $this->formatValue($value);
        return $this;
    }

    public function getDtstamp(): ?string
    {
        return $this->dtstamp;
    }

    public function setDtstamp($value): self
    {
        $this->dtstamp = $this->formatValue($value);
        return $this;
    }

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setUid($value): self
    {
        $this->uid = $this->formatValue($value);
        return $this;
    }

    public function getCreated(): ?string
    {
        return $this->created;
    }

    public function setCreated($value): self
    {
        $this->created = $this->formatValue($value);
        return $this;
    }

    public function getLastmodified(): ?string
    {
        return $this->lastmodified;
    }

    public function setLastmodified($value): self
    {
        $this->lastmodified = $this->formatValue($value);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription($value): self
    {
        $this->description = $this->formatValue($value);
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation($value): self
    {
        $this->location = $this->formatValue($value);
        return $this;
    }

    public function getSequence(): ?string
    {
        return $this->sequence;
    }

    public function setSequence($value): self
    {
        $this->sequence = $this->formatValue($value);
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus($value): self
    {
        $this->status = $this->formatValue($value);
        return $this;
    }

    public function getTransp(): ?string
    {
        return $this->transp;
    }

    public function setTransp($value): self
    {
        $this->transp = $this->formatValue($value);
        return $this;
    }

    public function getOrganizer(): ?string
    {
        return $this->organizer;
    }

    public function setOrganizer($value): self
    {
        $this->organizer = $this->formatValue($value);
        return $this;
    }

    public function getAttendee(): ?string
    {
        return $this->attendee;
    }

    public function setAttendee($value): self
    {
        $this->attendee = $this->formatValue($value);
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl($value): self
    {
        $this->url = $this->formatValue($value);
        return $this;
    }

    public function getCategories(): ?string
    {
        return $this->categories;
    }

    public function setCategories($value): self
    {
        $this->categories = $this->formatValue($value);
        return $this;
    }

    public function getXWrSource(): ?string
    {
        return $this->xWrSource;
    }

    public function setXWrSource($value): self
    {
        $this->xWrSource = $this->formatValue($value);
        return $this;
    }

    public function getXWrSourceUrl(): ?string
    {
        return $this->xWrSourceUrl;
    }

    public function setXWrSourceUrl($value): self
    {
        $this->xWrSourceUrl = $this->formatValue($value);
        return $this;
    }

    public function getRrule(): ?string
    {
        return $this->rrule;
    }

    public function setRrule($value): self
    {
        $this->rrule = $this->formatValue($value);
        return $this;
    }

    public function getDtstartArray(): ?array
    {
        return $this->dtstart_array;
    }

    public function setDtstartArray($value): self
    {
        $this->dtstart_array = $value;
        return $this;
    }

    public function getDtendArray(): ?array
    {
        return $this->dtend_array;
    }

    public function setDtendArray($value): self
    {
        $this->dtend_array = $value;
        return $this;
    }

    public function getDtstartTz(): ?string
    {
        return $this->dtstart_tz;
    }

    public function setDtstartTz($value): self
    {
        $this->dtstart_tz = $this->formatValue($value);
        return $this;
    }

    public function getDtendTz(): ?string
    {
        return $this->dtend_tz;
    }

    public function setDtendTz($value): self
    {
        $this->dtend_tz = $this->formatValue($value);
        return $this;
    }
}
