<?php
require_once 'lib/EventObject.php';
require_once 'lib/ICal.php';

use ICal\ICal;

/*
 * Supported HTTP methods
 */
$supportedMethods = ['GET'];
/*
 * Mandatory parameters fields
 */
$mandatory = ['source'];
/**
 * If not specified, the parameters will take these default values
 */
$defaultValues = array
(
    'format' => 'json'
);
/**
 * Ics properties of a VEVENT that will be converted & included in json result (if exist)
 * $icsKey => [$jsonKey, $include]
 * $icsKey : ics property name
 * $jsonKey : json key name (null <=> copy ics property name as json key, lower case)
 * $include : boolean indicating that if the field should be included by default in the result
 */
$jsonEventFields = array
(
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
);

foreach ($jsonEventFields as $key => &$value) {
    if ($value[0] === null) {
        $value[0] = strtolower($key);
    }
}
unset($value);
/**
 * Supported sets of values for some parameters
 */
$supportedValues = array
(
    'format' => ['ics', 'json']
);
/**
 * Supported set of values for some parameters that could have multiple values, separated by commas
 */
$supportedMultipleValues = array
(
    'fields' => array_map(function($v) { return $v[0]; }, $jsonEventFields)
);
/**
 * Supported formats (in regexp) for some parameters
 */
$supportedFormat = array
(
    'from' =>
        [
            "/^now$/",
            "/^\+\d+ weeks$/",
            "/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]$/", // date format, e.g. 1997-12-31
            "/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]T[0-2][0-9]:[0-6][0-9]:[0-6][0-9]$/" // datetime format, e.g. 2015-06-10T10:09:59
        ],
    'to' =>
        [
            "/^now$/",
            "/^\+\d+ weeks$/",
            "/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]$/",
            "/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]T[0-2][0-9]:[0-6][0-9]:[0-6][0-9]$/"
        ],
    'limit' =>
        [
            "/^[0-9]*$/"
        ]
);
/**
 * Request validations
 */
// Check HTTP method
$httpMethod = $_SERVER['REQUEST_METHOD'];
if (!in_array($httpMethod, $supportedMethods)) {
    throwAPIError('Unsupported HTTP method : ' . $httpMethod);
}
$parameters = getRequestParameters($httpMethod);
// Check required parameters
foreach ($mandatory as $value) {
    if (!array_key_exists($value, $parameters)) {
        throwAPIError('Missing required parameter : ' . $value);
    }
}

// Check parameters with limited support values
foreach ($supportedValues as $key => $value) {
    if (array_key_exists($key, $parameters)) {
        if (!in_array($parameters[$key], $value)) {
            throwAPIError('Value not supported for parameter \'' . $key . '\' : ' . $parameters[$key]);
        }
    }
}
// Check parameter with constrained format
foreach ($supportedFormat as $key => $patterns) {
    if (array_key_exists($key, $parameters)) {
        $match = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $parameters[$key])) {
                $match = true;
                break;
            }
        }
        if (!$match) {
            throwAPIError('Format not supported for parameter \'' . $key . '\' : ' . $parameters[$key]);
        }
    }
}
/*
 * Prepare/convert parameter options
 */
// Assign default values for some unspecified parameters
foreach ($defaultValues as $key => $value) {
    if (!array_key_exists($key, $parameters)) {
        $parameters[$key] = $value;
    }
}
// source can have multiple values, separated by comma
$sources = explode(',', $parameters['source']);

$fieldsParameterExists = false;
$fields = array();
if (array_key_exists('fields', $parameters)) {
    // fields can have multiple values, separated by comma
    $fields = explode(',', $parameters['fields']);
    foreach ($fields as $field) {
        if (!in_array($field, $supportedMultipleValues['fields'])) {
            throwAPIError('Field not supported : ' . $field);
        }
    }
    $fieldsParameterExists = true;
}
/*
 * Constructing response
 */
$parsedIcs = new ICal('data/ffMerged.ics', 'MO', $useCache=true, $processRecurrences=true);
$from = false;
$to = false;
if (array_key_exists('from', $parameters) && strpos($parameters['from'], "weeks")) {
    $from = "now " . $parameters['from'];
} elseif (array_key_exists('from', $parameters)){
    $from = $parameters['from'];
}
if (array_key_exists('to', $parameters) && strpos($parameters['to'], "weeks")) {
    $to = "now " . $parameters['to'];
} elseif (array_key_exists('to', $parameters)){
    $to = $parameters['to'];
}
if ($from || $to) {
    $events = $parsedIcs->eventsFromRange($from, $to);
} else {
    $events = $parsedIcs->events();
}
foreach ($events as $key => $value) {
    // this filter is to skip all events that don't match the criteria
    // and shouldn't be added to the output result
    if (!in_array('all', $sources)) {
        if (is_null($value->xWrSource) || !in_array($value->xWrSource, $sources)) {
            unset($events[$key]);
            continue;
        }
    }
}

if (array_key_exists('limit', $parameters) && count($events) > $parameters['limit']) {
    $events = array_slice($events, 0, $parameters['limit'], true);
}

// $includedEvents = $parsedIcs;

if ($parameters['format'] == 'json') {
    $jsonResult = array();
    foreach ($events as $event) {
        $selectedEvent = array();
        foreach ($event as $propertyKey => $propertyValue) {
            if (isRequiredField($propertyKey)) {
                $selectedEvent[$jsonEventFields[$propertyKey][0]] = getICSPropertyValue($propertyValue);
            }
        }
        $jsonResult[] = $selectedEvent;
    }
    if (count($jsonResult) === 0) {
        throwAPIError('No result found');
    }
    header('Content-type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($jsonResult);
} else {
    header('Access-Control-Allow-Origin: *');
    header('Content-type: text/ics; charset=UTF-8');
    echo toString($parsedIcs, $events);
}

/**
 * Convert the ICal object into valid ics string
 * @return string
 */
function toString(ICal $ical, $events) {

    $str = 'BEGIN:VCALENDAR' . "\r\n";
    $str .= $ical->printVcalendarDataAsIcs();
    foreach ($events as $event) {
        $str .= $event->printIcs();
    }
    $str .= 'END:VCALENDAR';
    return $str;
}



function throwAPIError($errorMsg) {
    throw new Exception($errorMsg);
}

function getRequestParameters($httpMethod) {
    return $httpMethod === 'GET' ? $_GET :
        ($httpMethod === 'POST' ? $_POST :
            null);
}
function isRequiredField($propertyKey) {
    global $jsonEventFields, $fieldsParameterExists, $fields;
    if ($fieldsParameterExists) {
        return array_key_exists($propertyKey, $jsonEventFields) && in_array($jsonEventFields[$propertyKey][0], $fields);
    }
    return array_key_exists($propertyKey, $jsonEventFields) && isDefaultJSONField($propertyKey, $jsonEventFields);
}

function isDefaultJSONField($icsKey, $jsonEventFields) {
    return $jsonEventFields[$icsKey][1];
}

function getICSPropertyValue($value) {
    return is_array($value) ? $value['value'] : $value;
}
