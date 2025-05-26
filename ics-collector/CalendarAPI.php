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

require_once 'lib/IcsValidator.php';
require_once 'lib/SabreVObjectCalendarHandler.php';
require_once 'lib/CalendarConfig.php';

use ICal\IcsValidator;
use ICal\SabreVObjectCalendarHandler;
use ICal\CalendarConfig;

/**
 * CalendarAPI class for handling iCal data and generating responses
 */
class CalendarAPI {
        /**
         * Path to the merged ICS file
         */
        protected const DEFAULT_ICS_MERGED_FILE = 'data/ffMerged.ics';
        
        /**
         * Path to the merged ICS file, kann durch Konstruktor überschrieben werden
         */
        protected string $icsMergedFile;
        
        /**
         * Supported HTTP methods (from central configuration)
         * @var array<string>
         */
        protected array $supportedMethods;
        
        /**
         * Mandatory parameters fields (from central configuration)
         * @var array<string>
         */
        protected array $mandatory;
        
        /**
         * If not specified, the parameters will take these default values (from central configuration)
         * @var array<string, string>
         */
        protected array $defaultValues;
        
        /**
         * Supported sets of values for some parameters (from central configuration)
         * @var array<string, array<string>>
         */
        protected array $supportedValues;
        
        /**
         * Supported formats (in regexp) for some parameters (from central configuration)
         * @var array<string, array<string>>
         */
        protected array $supportedFormat;
        
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
         * Sabre VObject Calendar Handler instance
         */
        protected SabreVObjectCalendarHandler $sabreHandler;
        
        /**
         * Constructor
         * 
         * @param string|null $icsMergedFile Optional Pfad zur Merged-ICS-Datei (für Tests)
         */
        public function __construct(?string $icsMergedFile = null) {
            // Initialize configuration from central config
            $this->supportedMethods = CalendarConfig::getSupportedHttpMethods();
            $this->mandatory = CalendarConfig::getMandatoryParameters();
            $this->defaultValues = CalendarConfig::getDefaultApiParameters();
            $this->supportedValues = CalendarConfig::getSupportedParameterValues();
            $this->supportedFormat = CalendarConfig::getSupportedParameterFormats();
            
            // Initialize validator
            $this->validator = new IcsValidator();
            
            // Set merged ICS file path
            $this->icsMergedFile = $icsMergedFile ?? self::DEFAULT_ICS_MERGED_FILE;
            
            // Initialize Sabre handler with default timezone from central config
            $this->sabreHandler = new SabreVObjectCalendarHandler();
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
                $this->validateAndRepairIcsFile($this->icsMergedFile);
                
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
            $cacheLifetime = CalendarConfig::getCacheLifetime();
            
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
                        return;
                    }
                }
            }
            
            // Process the calendar and get the result
            $result = $this->processCalendarData();
            
            // Save to cache file
            file_put_contents($cacheFile, serialize($result), LOCK_EX);
            
            // Output result
            header('Content-type: ' . $result['contentType'] . '; charset=UTF-8');
            header('Access-Control-Allow-Origin: *');
            header('X-Cache: MISS');
            
            echo $result['data'];
        }
        
        /**
         * Process the calendar data and return the result without outputting
         * 
         * @return array Associative array with contentType and data
         */
        protected function processCalendarData(): array {
            // Process the calendar and get the result
            $result = $this->processCalendarDataWithSabre();
            
            return $result;
        }
        
        /**
         * Process calendar data using the new Sabre VObject handler
         * 
         * @return array Associative array with contentType and data
         */
        protected function processCalendarDataWithSabre(): array {
            try {
                // Prepare parameters for Sabre handler - pass through all parameters
                // The Sabre handler will handle defaults via central configuration
                $parameters = $this->parameters;
                
                // Map 'source' parameter from sources array
                if (!empty($this->sources) && !in_array('all', $this->sources, true)) {
                    $parameters['source'] = implode(',', $this->sources);
                }
                
                // Use Sabre handler to process the request
                $result = $this->sabreHandler->processCalendarRequest($this->icsMergedFile, $parameters);
                
                return $result;
                
            } catch (\Exception $e) {
                throw $e;
            }
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
