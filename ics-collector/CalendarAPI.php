<?php
require_once 'lib/class.iCalReader.php';
header('Access-Control-Allow-Origin: *');
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
	'DTSTART' => ['start', true],
	'DTEND' => ['end', true],
	'SUMMARY' => [null, true],
	'DESCRIPTION' => [null, true],
	'DTSTAMP' => ['stamp', false],
	'CREATED' => [null, false],
	'LAST_MODIFIED' => [null, false],
	'LOCATION' => [null, true],
	'GEO' => ['geolocation', false],
	'X-WR-SOURCE' => ['source', false],
	'URL' => [null, false],
	'X-WR-SOURCE-URL' => ['sourceurl', false]
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
			"/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]$/", // date format, e.g. 1997-12-31 
			"/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]T[0-2][0-9]:[0-6][0-9]:[0-6][0-9]$/" // datetime format, e.g. 2015-06-10T10:09:59
		],
	'to' => 
		[
			"/^now$/",
			"/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]$/",
			"/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]T[0-2][0-9]:[0-6][0-9]:[0-6][0-9]$/"
		],
	'limit' =>
		[
			"/^[0-9]*$/"
		],
	'sort' =>
		[
			"/^asc-start$/",
			"/^desc-start$/"
		]
);
/**
 * Request validations
 */
// Check HTTP method
header('Content-type: application/json; charset=UTF-8');
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
$parsedIcs = new Ical('data/ffMerged.ics');
foreach ($parsedIcs->cal['VEVENT'] as $key => $value) {
	// this filter is to skip all events that don't match the criteria
    // and shouldn't be added to the output result
	if (!in_array('all', $sources)) {
		if (!array_key_exists('X-WR-SOURCE', $value) || !in_array($value['X-WR-SOURCE'], $sources)) {
			unset($parsedIcs->cal['VEVENT'][$key]);
			continue;
		}
	}
	if (array_key_exists('from', $parameters)) {
		$from = new DateTime($parameters['from']);
		try {
			$eventStart = new DateTime(getICSPropertyValue($value['DTSTART']));
		} catch (Exception $e) {
			throwAPIError('Parse \'DTSTART\' property error : ' . getICSPropertyValue($value['DTSTART']));
		}
		if ($eventStart < $from) {
			unset($parsedIcs->cal['VEVENT'][$key]);
			continue;
		}
	}
	if (array_key_exists('to', $parameters)) {
		$to = new DateTime($parameters['to']);
		try {
			$eventStart = new DateTime(getICSPropertyValue($value['DTSTART']));
		} catch (Exception $e) {
			throwAPIError('Parse \'DTSTART\' property error : ' . getICSPropertyValue($value['DTSTART']));
		}
		if ($eventStart >= $to) {
			unset($parsedIcs->cal['VEVENT'][$key]);
			continue;
		}
	}
}

function sortByStartDate($ev1, $ev2, $ascendant) {
	$startDate1 = getICSPropertyValue($ev1['DTSTART']);
	$startDate2 = getICSPropertyValue($ev2['DTSTART']);
	if ($startDate1 > $startDate2) {
		return $ascendant ? -1 : 1;
	} else if ($startDate1 < $startDate2) {
		return $ascendant ? 1 : -1;
	}
	return 0;
}

$includedEvents = $parsedIcs;
if (array_key_exists('sort', $parameters)) {
	switch ($parameters['sort']) {
		case 'asc-start':
			usort($includedEvents->cal['VEVENT'], function($ev1, $ev2) {
				return sortByStartDate($ev1, $ev2, true);
			});
			break;
		case 'desc-start':
			usort($includedEvents->cal['VEVENT'], function($ev1, $ev2) {
				return sortByStartDate($ev1, $ev2, false);
			});
			break;
		default:
			break;
	}
}
if (array_key_exists('limit', $parameters) && count($includedEvents->cal['VEVENT']) > $parameters['limit']) {
	$includedEvents->cal['VEVENT'] = array_slice($includedEvents->cal['VEVENT'], 0, $parameters['limit'], true);
}

if ($parameters['format'] == 'json') {
	$jsonResult = array();
	foreach ($includedEvents->cal['VEVENT'] as $key => $value) {
		$event = array();
		foreach ($value as $propertyKey => $propertyValue) {
			if (isRequiredField($propertyKey)) {
				$event[$jsonEventFields[$propertyKey][0]] = getICSPropertyValue($propertyValue);
			}
		}
		$jsonResult[$key] = $event;
	}
	if (count($jsonResult) === 0) {
		throwAPIError('No result found');
	}
	echo json_encode($jsonResult);
} else {
	header('Content-type: text/ics; charset=UTF-8');
	echo $includedEvents->toString();
}

function throwAPIError($errorMsg) {
	echo '{ "error": "' . $errorMsg . '"}';
	die;
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
