<head>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
    <style>
    .hidden {
        display : none;
    }
    </style>
    <script>
        $(document).ready(function() {
            $('.toggleButton').click(function(e) {
                $(e.target).next().toggleClass("hidden");
            });
        });
    </script>
</head>
<?php
/**
 * This example demonstrates how the Ics-Parser should be used.
 *
 * PHP Version 5
 *
 * @category Example
 * @package  Ics-parser
 * @author   Martin Thoma <info@martin-thoma.de>
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT License
 * @version  SVN: <svn_id>
 * @link     http://code.google.com/p/ics-parser/
 * @example  $ical = new ical('MyCal.ics');
 *           print_r( $ical->get_event_array() );
 */
require 'lib/class.iCalReader.php';


$datafiles = scandir('../data');
$json = array();

foreach ($datafiles as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }
    $ical   = new ICal('../data/' . $file, true);
    $events = $ical->events();
    echo '<h3>File : ' . $file . '</h3><br/>';
    $date = $events[0]['DTSTART'];
    echo 'The ical date: ';
    echo $date;
    echo '<br/>';

    echo 'The Unix timestamp: ';
    echo $ical->iCalDateToUnixTimestamp($date);
    echo '<br/>';

    echo 'The number of events: ';
    echo $ical->event_count;
    echo '<br/>';

     echo 'The number of recurrent events: ';
    echo $ical->recurrent_event_count;
    echo '<br/>';

    echo 'The number of todos: ';
    echo $ical->todo_count;
    echo '<br/>';

    echo '<hr/>';
	 echo '<button type="button" class="toggleButton">Show content</button>';
	 echo '<div class="hidden">';
    foreach ($events as $event) {
        echo 'SUMMARY: ' . $event['SUMMARY'] . '<br/>';
        echo 'DTSTART: ' . $event['DTSTART'] . ' - UNIX-Time: ' . $ical->iCalDateToUnixTimestamp($event['DTSTART']) . '<br/>';
        echo 'DTEND: ' . $event['DTEND'] . '<br/>';
        echo 'DTSTAMP: ' . $event['DTSTAMP'] . '<br/>';
        echo 'UID: ' . $event['UID'] . '<br/>';
        echo 'CREATED: ' . $event['CREATED'] . '<br/>';
        echo 'DESCRIPTION: ' . $event['DESCRIPTION'] . '<br/>';
        echo 'LAST-MODIFIED: ' . $event['LAST-MODIFIED'] . '<br/>';
        echo 'LOCATION: ' . $event['LOCATION'] . '<br/>';
        echo 'SEQUENCE: ' . $event['SEQUENCE'] . '<br/>';
        echo 'STATUS: ' . $event['STATUS'] . '<br/>';
        echo 'TRANSP: ' . $event['TRANSP'] . '<br/>';
        echo '<hr/>';
    }
	echo "</div>";
	echo '<hr/>';

}
?>
