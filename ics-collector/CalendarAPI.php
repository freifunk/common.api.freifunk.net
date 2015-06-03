<?php
require_once 'lib/class.iCalReader.php';
/*
 * Supported HTTP methods 
 */
$supportedMethods = ['GET'];
/*
 * Mandatory parameters fields
 */
$mandatory = ['source'];
/**
 * Supported sets of values for some parameters
 */
$supportedValues = array
(
	'format' => ['ics', 'json']
);
/**
 * If not specified, the parameters will take these default values
 */
$defaultValues = array
(
	'format' => 'json'
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
/*
 * Constructing response
 */
if ($parameters['format'] == 'json') {
	header('Content-type: application/json; charset=UTF-8');
	$parsedIcs = new Ical('data/ffMerged.ics');
	$result = array();
	foreach ($parsedIcs->cal['VEVENT'] as $key => $value) {
		// this filter is to skip all events that don't match the criteria
        // and shouldn't be added to the output result
		if (!in_array('all', $sources)) {
			if (!array_key_exists('X-WR-SOURCE', $value) || !in_array($value['X-WR-SOURCE'], $sources)) {
				continue;
			}
		}
		$event = array();
		if (array_key_exists('SUMMARY', $value)) {
			$event['summary'] = $value['SUMMARY'];
		}
		if (array_key_exists('DESCRIPTION', $value)) {
			$event['description'] = $value['SUMMARY'];
		}
		$event['start'] = $value['DTSTART']['value'];
		$result[$key] = $event;
	}
	if (count(result0) === 0) {
		throwAPIError('Result not found');
	}
	echo json_encode($result);
} else {
	header('Content-type: text/ics; charset=UTF-8');
	echo file_get_contents('data/ffMerged.ics');
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
