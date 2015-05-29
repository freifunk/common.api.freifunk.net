<?php 
require_once '../lib/class.iCalReader.php';
require_once '../lib/ics-merger.php';

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

if (!isAjaxRequest()) {
	echo 'Ajax requests only';
	die;
}


switch ($_POST['dest']) {
  case 'parser':
    if (!array_key_exists('text', $_POST)) {
      echo 'Missing parameter : text';
      die;
    }

    $icsText =  $_POST['text'];
    $ics = new ICal(explode("\n", $icsText));
    $json = array();
    $events = $ics->events();
    $date = $events[0]['DTSTART']['value'];
    $json['metainfo'] = array(
      'PRODID' => $ics->cal['VCALENDAR']['PRODID'],
      'First event date' => $date,
      'Unix timestamp' => ICal::iCalDateToUnixTimestamp($date),
      'Number of events' => $ics->event_count,
      'Number of recurrent events' => $ics->recurrent_event_count,
      'Number of todos' => $ics->todo_count 
      );
    $json['fullCalendar'] = array();
    $json['calHeatmap'] = array();
    foreach ($events as $event) {
      $cell = array('title' => $event['SUMMARY'], 'description' => $event['DESCRIPTION']);
      if (array_key_exists('DTSTART', $event)) {
            $cell['start'] = convertTimeToFullCalendar($event['DTSTART']['value']);
        }
         if (array_key_exists('DTEND', $event)) {
            $cell['end'] = convertTimeToFullCalendar($event['DTEND']['value']);
        }
        array_push($json['fullCalendar'], $cell);

        $unixTimestamp = ICal::iCalDateToUnixTimestamp($event['DTSTART']['value']);
        if (array_key_exists($unixTimestamp, $json['calHeatmap'])) {
          $json['calHeatmap'][$unixTimestamp] += 1;
        } else {
          $json['calHeatmap'][$unixTimestamp] = 1;
        }
    }

    echo json_encode($json);
    break;
  case 'merger':
    if (!array_key_exists('text0', $_POST) || !array_key_exists('text1', $_POST)) {
      echo 'Missing parameter : text0 and/or text1';
      die;
    }

    $merger = new IcsMerger();
    $merger->add($_POST['text0']);
    $merger->add($_POST['text1']);
    $ics = $merger->getResult();
    $json = array();
    $events = $ics['VEVENTS'];
    $date = $events[0]['DTSTART']['value'];
    $json['metainfo'] = array(
      'PRODID' => $ics['VCALENDAR']['PRODID'],
      'First event date' => $date,
      'Unix timestamp' => ICal::iCalDateToUnixTimestamp($date),
      'Number of events' => count($ics['VEVENTS']),
      /* not available 'Number of recurrent events' => $ics->recurrent_event_count, */
      );
    $json['rawText'] = IcsMerger::getRawText($ics);
    $json['fullCalendar'] = array();
    $json['calHeatmap'] = array();
    foreach ($events as $event) {
      $cell = array('title' => $event['SUMMARY'], 'description' => $event['DESCRIPTION']);
      if (array_key_exists('DTSTART', $event)) {
            $cell['start'] = convertTimeToFullCalendar($event['DTSTART']['value']);
        }
         if (array_key_exists('DTEND', $event)) {
            $cell['end'] = convertTimeToFullCalendar($event['DTEND']['value']);
        }
        array_push($json['fullCalendar'], $cell);

        $unixTimestamp = ICal::iCalDateToUnixTimestamp($event['DTSTART']['value']);
        if (array_key_exists($unixTimestamp, $json['calHeatmap'])) {
          $json['calHeatmap'][$unixTimestamp] += 1;
        } else {
          $json['calHeatmap'][$unixTimestamp] = 1;
        }
    }
    echo json_encode($json);
    break;
  default:
    break;
}

function convertTimeToFullCalendar($time) {
    return substr($time, 0, 4) . '-' . substr($time, 4, 2) . '-' . substr($time, 6, 5) . ':' . substr($time, 11, 2) . ':' . substr($time, 13, 2); 
}
