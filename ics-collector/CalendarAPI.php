<?php
require_once 'lib/EventObject.php';
require_once 'lib/ICal.php';
require_once 'lib/ics-validator.php';

use ICal\ICal;
use ICal\EventObject;
use ICal\IcsValidator;

/**
 * CalendarAPI class for handling iCal data and generating responses
 */
class CalendarAPI {
    /**
     * Supported HTTP methods
     * @var array<string>
     */
    protected array $supportedMethods = ['GET'];
    
    /**
     * Mandatory parameters fields
     * @var array<string>
     */
    protected array $mandatory = ['source'];
    
    /**
     * If not specified, the parameters will take these default values
     * @var array<string, string>
     */
    protected array $defaultValues = [
        'format' => 'json'
    ];
    
    /**
     * Ics properties of a VEVENT that will be converted & included in json result (if exist)
     * $icsKey => [$jsonKey, $include]
     * $icsKey : ics property name
     * $jsonKey : json key name (null <=> copy ics property name as json key, lower case)
     * $include : boolean indicating that if the field should be included by default in the result
     * @var array<string, array{0: ?string, 1: bool}>
     */
    protected array $jsonEventFields = [
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
     * @var array<string, array<string>>
     */
    protected array $supportedValues = [
        'format' => ['ics', 'json']
    ];
    
    /**
     * Supported set of values for some parameters that could have multiple values, separated by commas
     * @var array<string, array<string>>
     */
    protected array $supportedMultipleValues = [];
    
    /**
     * Supported formats (in regexp) for some parameters
     * @var array<string, array<string>>
     */
    protected array $supportedFormat = [
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
    
    /**
     * Parameters passed to the API
     * @var array<string, string>
     */
    protected array $parameters = [];
    
    /**
     * Source names from the source parameter
     * @var array<string>
     */
    protected array $sources = [];
    
    /**
     * Whether the fields parameter exists
     */
    protected bool $fieldsParameterExists = false;
    
    /**
     * Fields to include in the output
     * @var array<string>
     */
    protected array $fields = [];
    
    /**
     * ICS-Validator instance
     */
    protected IcsValidator $validator;
    
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
            'fields' => array_map(fn($v) => $v[0], $this->jsonEventFields)
        ];
        
        // Initialize validator
        $this->validator = new IcsValidator();
    }
    
    /**
     * Process the API request
     */
    public function processRequest(): void {
        // Check HTTP method
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        if (!in_array($httpMethod, $this->supportedMethods, true)) {
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
                if (!in_array($this->parameters[$key], $value, true)) {
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
                if (!in_array($field, $this->supportedMultipleValues['fields'], true)) {
                    $this->throwAPIError('Field not supported : ' . $field);
                }
            }
            $this->fieldsParameterExists = true;
        }
        
        // Validate and ensure ICS file integrity before processing
        $this->validateAndRepairIcsFile('data/ffMerged.ics');
        
        $this->processCalendar();
    }
    
    /**
     * Validate and repair an ICS file if needed
     * 
     * @param string $icsFile Path to the ICS file
     * @return bool True if file is valid or was repaired successfully
     */
    protected function validateAndRepairIcsFile(string $icsFile): bool {
        if (!file_exists($icsFile)) {
            error_log("ICS file not found: $icsFile");
            return false;
        }
        
        $isValid = $this->validator->validateFile($icsFile);
        
        // Log validation results
        if (!$isValid) {
            error_log("Invalid ICS file: $icsFile");
            foreach ($this->validator->getErrors() as $error) {
                error_log(" - Error: $error");
            }
        }
        
        if (!empty($this->validator->getWarnings())) {
            foreach ($this->validator->getWarnings() as $warning) {
                error_log(" - Warning: $warning");
            }
        }
        
        // Attempt to repair the file if invalid
        if (!$isValid) {
            error_log("Attempting to repair ICS file: $icsFile");
            
            $content = file_get_contents($icsFile);
            $fixedContent = $this->validator->fixIcsContent($content);
            
            if ($content !== $fixedContent) {
                // Create backup
                $backupFile = $icsFile . '.bak.' . date('YmdHis');
                file_put_contents($backupFile, $content);
                error_log("Created backup: $backupFile");
                
                // Save fixed content
                file_put_contents($icsFile, $fixedContent);
                error_log("Repaired ICS file: $icsFile");
                
                // Validate again
                $isValid = $this->validator->validateFile($icsFile);
                
                if (!$isValid) {
                    error_log("ICS file still invalid after repair: $icsFile");
                    foreach ($this->validator->getErrors() as $error) {
                        error_log(" - Error: $error");
                    }
                }
            } else {
                error_log("No automatic repair possible for: $icsFile");
            }
        }
        
        return $isValid;
    }
    
    /**
     * Process the calendar data and output the response
     */
    protected function processCalendar(): void {
        $parsedIcs = new ICal('data/ffMerged.ics', 'MO', true, true);
        $from = false;
        $to = false;
        
        if (array_key_exists('from', $this->parameters) && strpos($this->parameters['from'], "weeks") !== false) {
            $from = "now " . $this->parameters['from'];
        } elseif (array_key_exists('from', $this->parameters)){
            $from = $this->parameters['from'];
        }
        
        if (array_key_exists('to', $this->parameters) && strpos($this->parameters['to'], "weeks") !== false) {
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
            if (!in_array('all', $this->sources, true)) {
                if ($value->xWrSource === null || !in_array($value->xWrSource, $this->sources, true)) {
                    unset($events[$key]);
                    continue;
                }
            }
        }

        if (array_key_exists('limit', $this->parameters) && count($events) > (int)$this->parameters['limit']) {
            $events = array_slice($events, 0, (int)$this->parameters['limit'], true);
        }

        if ($this->parameters['format'] === 'json') {
            $this->outputJson($events);
        } else {
            $this->outputIcs($parsedIcs, $events);
        }
    }
    
    /**
     * Output JSON response
     * 
     * @param array<EventObject> $events Array of EventObject instances
     */
    protected function outputJson(array $events): void {
        $jsonResult = [];
        foreach ($events as $event) {
            $selectedEvent = [];
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
     * 
     * @param ICal $parsedIcs The parsed ICal object
     * @param array<EventObject> $events Array of EventObject instances
     */
    protected function outputIcs(ICal $parsedIcs, array $events): void {
        header('Access-Control-Allow-Origin: *');
        header('Content-type: text/calendar; charset=UTF-8');
        echo $this->toString($parsedIcs, $events);
    }
    
    /**
     * Convert the ICal object into valid ics string
     * 
     * @param ICal $ical The ICal object
     * @param array<EventObject> $events Array of EventObject instances
     * @return string ICS-formatted string
     */
    protected function toString(ICal $ical, array $events): string {
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
     * 
     * @param string $propertyKey The property key to check
     * @return bool True if the field is required
     */
    protected function isRequiredField(string $propertyKey): bool {
        if ($this->fieldsParameterExists) {
            return array_key_exists($propertyKey, $this->jsonEventFields) && in_array($this->jsonEventFields[$propertyKey][0], $this->fields, true);
        }
        return array_key_exists($propertyKey, $this->jsonEventFields) && $this->isDefaultJSONField($propertyKey);
    }
    
    /**
     * Check if a field is a default JSON field
     * 
     * @param string $icsKey The ICS key to check
     * @return bool True if the field is a default JSON field
     */
    protected function isDefaultJSONField(string $icsKey): bool {
        return $this->jsonEventFields[$icsKey][1];
    }
    
    /**
     * Get property value from ICS
     * 
     * @param mixed $value The property value
     * @return mixed The extracted property value
     */
    protected function getICSPropertyValue(mixed $value): mixed {
        return is_array($value) ? $value['value'] : $value;
    }
    
    /**
     * Get request parameters
     * 
     * @param string $httpMethod The HTTP method
     * @return array<string, string> The request parameters
     */
    protected function getRequestParameters(string $httpMethod): array {
        return $httpMethod === 'GET' ? $_GET : ($httpMethod === 'POST' ? $_POST : []);
    }
    
    /**
     * Throw API error
     * 
     * @param string $errorMsg The error message
     * @throws \Exception
     */
    protected function throwAPIError(string $errorMsg): never {
        throw new \Exception($errorMsg);
    }
}

// Only execute if this file is being run directly (not included through autoloader)
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    $api = new CalendarAPI();
    $api->processRequest();
}
