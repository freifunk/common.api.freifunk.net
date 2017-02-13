<?php
/**
 * @category    Parser
 * @package     ics-parser
 */

namespace ICal;

class EventObject
{
    public $summary;
    public $dtstart;
    public $dtend;
    public $duration;
    public $dtstamp;
    public $uid;
    public $created;
    public $lastmodified;
    public $description;
    public $location;
    public $sequence;
    public $status;
    public $transp;
    public $organizer;
    public $attendee;
    public $url;
    public $categories;
    public $xWrSource;
    public $xWrSourceUrl;

    public function __construct($data = array())
    {
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (!property_exists($this, $key)) {
                    $variable = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', strtolower($key)))));
                } else {
                    $variable = $key;
                }
                $this->{$variable} = $value;

                if (is_array($value)) {
                     $this->{$variable} = $value;
                } else {
                    $this->{$variable} = stripslashes(trim(str_replace('\n', "\n", $value)));
                }
            }
        }
    }

    public static function __set_state($anArray)
    {
        $eventObject = new EventObject($anArray);
        return $eventObject;
    }

    /**
     * Return Event data excluding anything blank
     * as ICS format 
     *
     * @return string
     */
    public function printIcs()
    {
        $crlf = "\r\n";
        $data = array(
            'SUMMARY'         => $this->summary,
            'DTSTART'         => $this->dtstart,
            'DTEND'           => $this->dtend,
            'DURATION'        => $this->duration,
            'DTSTAMP'         => $this->dtstamp,
            'UID'             => $this->uid,
            'CREATED'         => $this->created,
            'LAST-MODIFIED'   => $this->lastmodified,
            'DESCRIPTION'     => $this->description,
            'LOCATION'        => $this->location,
            'SEQUENCE'        => $this->sequence,
            'STATUS'          => $this->status,
            'TRANSP'          => $this->transp,
            'ORGANISER'       => $this->organizer,
            'URL'             => $this->url,
            'CATEGORIES'      => $this->categories,
            'X-WR-SOURCE'     => $this->xWrSource,
            'X-WR-SOURCE-URL' => $this->xWrSourceUrl,
        );

        $data   = array_map('trim', $data); // Trim all values
        $data   = array_filter($data);      // Remove any blank values
        $output = "BEGIN:VEVENT".$crlf;

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
    public function printData($html = '<p>%s: %s</p>')
    {
        $data = array(
            'SUMMARY'       => $this->summary,
            'DTSTART'       => $this->dtstart,
            'DTEND'         => $this->dtend,
            'DTSTART_TZ'    => $this->dtstart_tz,
            'DTEND_TZ'      => $this->dtend_tz,
            'DURATION'      => $this->duration,
            'DTSTAMP'       => $this->dtstamp,
            'UID'           => $this->uid,
            'CREATED'       => $this->created,
            'LAST-MODIFIED' => $this->lastmodified,
            'DESCRIPTION'   => $this->description,
            'LOCATION'      => $this->location,
            'SEQUENCE'      => $this->sequence,
            'STATUS'        => $this->status,
            'TRANSP'        => $this->transp,
            'ORGANISER'     => $this->organizer,
            'ATTENDEE(S)'   => $this->attendee,
        );

        $data   = array_map('trim', $data); // Trim all values
        $data   = array_filter($data);      // Remove any blank values
        $output = '';

        foreach ($data as $key => $value) {
            $output .= sprintf($html, $key, $value);
        }

        return $output;
    }
}
