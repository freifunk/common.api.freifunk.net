<?php
require_once 'lib/EventObject.php';
require_once 'lib/ICal.php';

use ICal\ICal;

/**
 * CalendarAPI class for handling iCal data and generating responses
 */
class CalendarAPI {
    /**
     * Supported HTTP methods
     */
    protected $supportedMethods = ['GET'];
    
    /**
     * Mandatory parameters fields
     */
    protected $mandatory = ['source'];
    
    /**
     * If not specified, the parameters will take these default values
     */
    protected $defaultValues = [
        'format' => 'json'
    ];
    
    /**
     * Ics properties of a VEVENT that will be converted & included in json result (if exist)
     * $icsKey => [$jsonKey, $include]
     * $icsKey : ics property name
     * $jsonKey : json key name (null <=> copy ics property name as json key, lower case)
     * $include : boolean indicating that if the field should be included by default in the result
     */
    protected $jsonEventFields = [
        'dtstart' => ['start', true],
        'dtend' => ['end', true],
        'summary' => [null, true],
        'description' => [null, true],
        'dtstamp' => ['stamp', false],
        'created' => [null, false],
        'lastmodified' => [null, false],
        'location' => [null, true],
        'geo' => ['geolocation', false],
        'xWrSource' => ['source', false],
        'url' => [null, false],
        'xWrSourceUrl' => ['sourceurl', false]
    ];
    
    /**
     * Supported sets of values for some parameters
     */
    protected $supportedValues = [
        'format' => ['ics', 'json']
    ];
    
    /**
     * Supported set of values for some parameters that could have multiple values, separated by commas
     */
    protected $supportedMultipleValues = [];
    
    /**
     * Supported formats (in regexp) for some parameters
     */
    protected $supportedFormat = [
        'from' => [
            "/^now$/",
            "/^\+\d+ weeks$/",
            "/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]$/", // date format, e.g. 1997-12-31
            "/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]T[0-2][0-9]:[0-6][0-9]:[0-6][0-9]$/" // datetime format, e.g. 2015-06-10T10:09:59
        ],
        'to' => [
            "/^now$/",
            "/^\+\d+ weeks$/",
            "/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]$/",
            "/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]T[0-2][0-9]:[0-6][0-9]:[0-6][0-9]$/"
        ],
        'limit' => [
            "/^[0-9]*$/"
        ]
    ];
    
    protected $parameters = [];
    protected $sources = [];
    protected $fieldsParameterExists = false;
    protected $fields = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Prepare JSON event fields
        foreach ($this->jsonEventFields as $key => &$value) {
            if ($value[0] === null) {
                $value[0] = strtolower($key);
            }
        }
        unset($value);
        
        // Initialize supported multiple values
        $this->supportedMultipleValues = [
            'fields' => array_map(function($v) { return $v[0]; }, $this->jsonEventFields)
        ];
    }
    
    /**
     * Process the API request
     */
    public function processRequest() {
        // Check HTTP method
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        if (!in_array($httpMethod, $this->supportedMethods)) {
            $this->throwAPIError('Unsupported HTTP method : ' . $httpMethod);
        }
        
        $this->parameters = $this->getRequestParameters($httpMethod);
        
        // Check required parameters
        foreach ($this->mandatory as $value) {
            if (!array_key_exists($value, $this->parameters)) {
                $this->throwAPIError('Missing required parameter : ' . $value);
            }
        }

        // Check parameters with limited support values
        foreach ($this->supportedValues as $key => $value) {
            if (array_key_exists($key, $this->parameters)) {
                if (!in_array($this->parameters[$key], $value)) {
                    $this->throwAPIError('Value not supported for parameter \'' . $key . '\' : ' . $this->parameters[$key]);
                }
            }
        }
        
        // Check parameter with constrained format
        foreach ($this->supportedFormat as $key => $patterns) {
            if (array_key_exists($key, $this->parameters)) {
                $match = false;
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $this->parameters[$key])) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    $this->throwAPIError('Format not supported for parameter \'' . $key . '\' : ' . $this->parameters[$key]);
                }
            }
        }
        
        // Assign default values for some unspecified parameters
        foreach ($this->defaultValues as $key => $value) {
            if (!array_key_exists($key, $this->parameters)) {
                $this->parameters[$key] = $value;
            }
        }
        
        // source can have multiple values, separated by comma
        $this->sources = explode(',', $this->parameters['source']);

        if (array_key_exists('fields', $this->parameters)) {
            // fields can have multiple values, separated by comma
            $this->fields = explode(',', $this->parameters['fields']);
            foreach ($this->fields as $field) {
                if (!in_array($field, $this->supportedMultipleValues['fields'])) {
                    $this->throwAPIError('Field not supported : ' . $field);
                }
            }
            $this->fieldsParameterExists = true;
        }
        
        $this->processCalendar();
    }
    
    /**
     * Process the calendar data and output the response
     */
    protected function processCalendar() {
        $parsedIcs = new ICal('data/ffMerged.ics', 'MO', $useCache=true, $processRecurrences=true);
        $from = false;
        $to = false;
        
        if (array_key_exists('from', $this->parameters) && strpos($this->parameters['from'], "weeks")) {
            $from = "now " . $this->parameters['from'];
        } elseif (array_key_exists('from', $this->parameters)){
            $from = $this->parameters['from'];
        }
        
        if (array_key_exists('to', $this->parameters) && strpos($this->parameters['to'], "weeks")) {
            $to = "now " . $this->parameters['to'];
        } elseif (array_key_exists('to', $this->parameters)){
            $to = $this->parameters['to'];
        }
        
        if ($from || $to) {
            $events = $parsedIcs->eventsFromRange($from, $to);
        } else {
            $events = $parsedIcs->events();
        }
        
        foreach ($events as $key => $value) {
            // this filter is to skip all events that don't match the criteria
            // and shouldn't be added to the output result
            if (!in_array('all', $this->sources)) {
                if (is_null($value->xWrSource) || !in_array($value->xWrSource, $this->sources)) {
                    unset($events[$key]);
                    continue;
                }
            }
        }

        if (array_key_exists('limit', $this->parameters) && count($events) > $this->parameters['limit']) {
            $events = array_slice($events, 0, $this->parameters['limit'], true);
        }

        if ($this->parameters['format'] == 'json') {
            $this->outputJson($events);
        } else {
            $this->outputIcs($parsedIcs, $events);
        }
    }
    
    /**
     * Output JSON response
     */
    protected function outputJson($events) {
        $jsonResult = array();
        foreach ($events as $event) {
            $selectedEvent = array();
            foreach ($event as $propertyKey => $propertyValue) {
                if ($this->isRequiredField($propertyKey)) {
                    $selectedEvent[$this->jsonEventFields[$propertyKey][0]] = $this->getICSPropertyValue($propertyValue);
                }
            }
            $jsonResult[] = $selectedEvent;
        }
        
        if (count($jsonResult) === 0) {
            $this->throwAPIError('No result found');
        }
        
        header('Content-type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($jsonResult);
    }
    
    /**
     * Output ICS response
     */
    protected function outputIcs($parsedIcs, $events) {
        header('Access-Control-Allow-Origin: *');
        header('Content-type: text/calendar; charset=UTF-8');
        echo $this->toString($parsedIcs, $events);
    }
    
    /**
     * Convert the ICal object into valid ics string
     * @return string
     */
    protected function toString(ICal $ical, $events) {
        $str = 'BEGIN:VCALENDAR' . "\r\n";
        $str .= $ical->printVcalendarDataAsIcs();
        foreach ($events as $event) {
            $str .= $event->printIcs();
        }
        $str .= 'END:VCALENDAR';
        return $str;
    }
    
    /**
     * Check if a field is required
     */
    protected function isRequiredField($propertyKey) {
        if ($this->fieldsParameterExists) {
            return array_key_exists($propertyKey, $this->jsonEventFields) && in_array($this->jsonEventFields[$propertyKey][0], $this->fields);
        }
        return array_key_exists($propertyKey, $this->jsonEventFields) && $this->isDefaultJSONField($propertyKey);
    }
    
    /**
     * Check if a field is a default JSON field
     */
    protected function isDefaultJSONField($icsKey) {
        return $this->jsonEventFields[$icsKey][1];
    }
    
    /**
     * Get property value from ICS
     */
    protected function getICSPropertyValue($value) {
        return is_array($value) ? $value['value'] : $value;
    }
    
    /**
     * Get request parameters
     */
    protected function getRequestParameters($httpMethod) {
        return $httpMethod === 'GET' ? $_GET : ($httpMethod === 'POST' ? $_POST : []);
    }
    
    /**
     * Throw API error
     */
    protected function throwAPIError($errorMsg) {
        throw new \Exception($errorMsg);
    }
}

// Only execute if this file is being run directly (not included through autoloader)
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    $api = new CalendarAPI();
    $api->processRequest();
}
