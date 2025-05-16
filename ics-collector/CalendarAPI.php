<?php
// Include Composer Autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Prevent PHP errors from appearing in output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Ensure no output has occurred yet
if (ob_get_level() == 0) ob_start();

require_once 'lib/EventObject.php';
require_once 'lib/ICal.php';
require_once 'lib/IcsValidator.php';

use ICal\ICal;
use ICal\EventObject;
use ICal\IcsValidator;

/**
 * CalendarAPI class for handling iCal data and generating responses
 */
class CalendarAPI {
        /**
         * Path to the merged ICS file
         */
        protected const ICS_MERGED_FILE = 'data/ffMerged.ics';
        
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
            'format' => 'ics'
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
         * ICS-Validator instance
         */
        protected IcsValidator $validator;
        
        /**
         * Constructor
         */
        public function __construct() {
            // Initialize validator
            $this->validator = new IcsValidator();
        }
        
        /**
         * Memory cleanup function to help with large datasets
         */
        protected function cleanupMemory(): void
        {
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
        
        /**
         * Process the API request
         */
        public function processRequest(): void {
            try {
                // Output-Buffer bereinigen, falls noch Ausgaben vorliegen
                if (ob_get_length() > 0) {
                    ob_clean();
                }
                
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
                
                // Validate and ensure ICS file integrity before processing
                $this->validateAndRepairIcsFile(self::ICS_MERGED_FILE);
                
                // Aufräumen nach der Validierung
                $this->cleanupMemory();
                
                // Hier keine expliziten Output-Buffer-Operationen mehr, 
                // das wird in den Output-Methoden erledigt
                $this->processCalendar();
                
            } catch (\Exception $e) {
                // Stelle sicher, dass der Output-Buffer leer ist
                if (ob_get_length() > 0) {
                    ob_clean();
                }
                
                // HTTP-Fehlercode setzen und Fehler im JSON-Format zurückgeben
                http_response_code(400);
                header('Content-type: application/json; charset=UTF-8');
                header('Access-Control-Allow-Origin: *');
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
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
            // Generate cache key based on request parameters
            $cacheKey = md5(json_encode($this->parameters));
            $cacheFile = sys_get_temp_dir() . '/ff_calendar_' . $cacheKey . '.cache';
            $cacheLifetime = 3600; // 1 hour cache lifetime (in seconds)
            
            // Check if a valid cache exists
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheLifetime)) {
                $cachedData = file_get_contents($cacheFile);
                if ($cachedData) {
                    $cachedResult = unserialize($cachedData);
                    if ($cachedResult) {
                        // Set HTTP headers
                        header('Content-type: ' . $cachedResult['contentType'] . '; charset=UTF-8');
                        header('Access-Control-Allow-Origin: *');
                        header('X-Cache: HIT');
                        
                        // Output from cache
                        echo $cachedResult['data'];
                        exit;
                    }
                }
            }
            
            // Suppress debug output
            $oldErrorReporting = error_reporting();
            error_reporting(E_ERROR | E_PARSE); // Show only severe errors
            
            try {
                // Read and process the ICS file efficiently
                $icsFile = self::ICS_MERGED_FILE;
                $icsContent = file_get_contents($icsFile);
                
                // Clean the content for processing
                $cleanedContent = ICal::cleanIcsContent($icsContent);
                
                // Create ICal object with string input
                $parsedIcs = new ICal(false, 'MO', true, true);
                $parsedIcs->initString($cleanedContent);
                
                // Efficient parameter processing with default values
                $from = false;
                $to = false;
                
                // Extract frequently used parameters only once
                $hasFrom = array_key_exists('from', $this->parameters);
                $hasTo = array_key_exists('to', $this->parameters);
                
                if ($hasFrom) {
                    $fromValue = $this->parameters['from'];
                    if (strpos($fromValue, "weeks") !== false) {
                        $from = "now " . $fromValue;
                    } else {
                        $from = $fromValue;
                    }
                }
                
                if ($hasTo) {
                    $toValue = $this->parameters['to'];
                    if (strpos($toValue, "weeks") !== false) {
                        $to = "now " . $toValue;
                    } else {
                        $to = $toValue;
                    }
                } else {
                    // Default value: 6 months into the future
                    $to = date('Y-m-d', strtotime('+6 months'));
                }
                
                // Set start and end dates for processing recurring events
                $parsedIcs->startDate = $from;
                $parsedIcs->endDate = $to;
                
                if ($from || $to) {
                    $events = $parsedIcs->eventsFromRange($from, $to);
                } else {
                    $events = $parsedIcs->events();
                }
                
                // Optimize event filtering
                $hasAllSource = in_array('all', $this->sources, true);
                $filteredEvents = [];
                
                // Faster source checking through indexing of allowed sources
                $allowedSources = array_flip($this->sources);
                
                foreach ($events as $event) {
                    // Check if the event is in the allowed sources
                    if ($hasAllSource || 
                       (isset($event->xWrSource) && isset($allowedSources[$event->xWrSource]))) {
                        $filteredEvents[] = $event;
                    }
                }
                
                // Replace events with filtered ones
                $events = $filteredEvents;
                
                // Limit the number of events if necessary
                $hasLimit = array_key_exists('limit', $this->parameters);
                if ($hasLimit) {
                    $limit = (int)$this->parameters['limit'];
                    if (count($events) > $limit) {
                        $events = array_slice($events, 0, $limit);
                    }
                }
                
                // Restore original error handling
                error_reporting($oldErrorReporting);
                
                // Prepare result data for cache and output
                $result = [];
                
                // Generate output and write to cache
                ob_start();
                
                // Always output ICS format, regardless of requested format
                $result['contentType'] = 'text/calendar';
                $this->outputIcsForCache($parsedIcs, $events, $result);
                
                $result['data'] = ob_get_contents();
                ob_end_clean();
                
                // Save to cache file
                file_put_contents($cacheFile, serialize($result), LOCK_EX);
                
                // Output result
                header('Content-type: ' . $result['contentType'] . '; charset=UTF-8');
                header('Access-Control-Allow-Origin: *');
                header('X-Cache: MISS');
                
                // Add warning as HTTP header if JSON was requested
                if ($this->parameters['format'] === 'json') {
                    header('X-Format-Warning: JSON format is deprecated and will be removed in future versions. Please use the ICS format.');
                }
                
                echo $result['data'];
                exit;
                
            } catch (\Exception $e) {
                // Ursprüngliche Fehlerbehandlung wiederherstellen
                error_reporting($oldErrorReporting);
                throw $e; // Exception weiterreichen
            }
        }
        
        /**
         * Output ICS response for cache
         * 
         * @param ICal $parsedIcs The parsed ICal object
         * @param array<EventObject> $events Array of EventObject instances
         * @param array &$result Reference to result array for cache
         */
        protected function outputIcsForCache(ICal $parsedIcs, array $events, array &$result): void {
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
            // Begin the iCalendar
            $str = 'BEGIN:VCALENDAR' . "\r\n";
            
            // Add calendar metadata
            $str .= $ical->printVcalendarDataAsIcs();
            
            // Add all events
            foreach ($events as $event) {
                $str .= $event->printIcs();
            }
            
            // End the iCalendar with proper CRLF formatting
            $str .= "END:VCALENDAR\r\n";
            
            return $str;
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
