<?php
require_once 'class.iCalReader.php';

class IcsMerger {

	private $inputs = array();
	private $config = array();
	private $defaultTimezone;
	const CONFIG_FILENAME = 'ics-merger-config.ini';

	public function __construct() {
		$this->config = parse_ini_file(IcsMerger::CONFIG_FILENAME);
		$this->defaultTimezone = new DateTimeZone($this->config['DEFAULT_TIMEZONE']);
	}

	public function add($text) {
		array_push($this->inputs, $text);
	}

	public function getResult() {
		$result = array(
			'VCALENDAR' => array(),
			'VEVENTS' => array()
		);
		foreach ($this->inputs as $icsText) {
			$ical = new ICal(explode("\n", $icsText), true);
			//var_dump($ical);
			$timezone = null;
			foreach($ical->cal as $key => $value) {
				switch($key) {
				case 'VCALENDAR' :
					$result['VCALENDAR'] = array_merge($result['VCALENDAR'], $this->processCalendarHead($value, $timezone));
					break;
				case 'VEVENT' :
					$result['VEVENTS'] = array_merge($result['VEVENTS'], $this->processEvents($value, $timezone));
					break;
				default : 
					break;
				}
			}
		}

		$result['VCALENDAR']['PRODID'] = $this->config['DEFAULT_PRODID'];

		$callback = function($value) {
			return $value;
		};
		// flatten array
		$result['VEVENTS'] = array_map($callback, $result['VEVENTS']);
		return $result;
	}

	private function processCalendarHead($calendarHead, &$timezone) {
		foreach ($calendarHead as $key => $value) {
			switch ($key) {
				// google calendar
				case 'X-WR-TIMEZONE':
					$timezone = $value;
					$calendarHead[$key] = $this->config['DEFAULT_TIMEZONE']; 
					break;
				
				default:
					# code...
					break;
			}
		}
		return $calendarHead;
	}
	private function processEvents($events, $timezone = null) {
		foreach($events as &$event) {
			foreach ($event as $key => &$value) {
				switch($key) {
				// properties of type DATE / DATE-TIME
				case 'DTSTART':
				case 'DTEND' :
				case 'RDATE' :
					// only local datime needs conversion
					if ($value['meta']['type'] == 'DATE-TIME' && $value['meta']['format'] == 'LOCAL-TIME') {
						$tz = null;
						if (array_key_exists('tzid', $value['meta'])) {
							$tz = new DateTimeZone($value['meta']['tzid']);
						} else if ($timezone != null) {
							$tz = new DateTimeZone($timezone);
						}
						if ($tz != null) {
							try {
								$time = new DateTime($value['value'], $tz);
							} catch (Exception $e) {
								echo $e->getMessage();
								exit(1);
							}
							$time->setTimezone($this->defaultTimezone);
							$value['value'] =  $time->format('Ymd\THis');
						}
					}
					break;
				default : 
					// ignore others
					break;
				}
			}
		}
		return $events;
	}

	public static function getRawText($icsMergerResult) {
		$callback = function ($v, $k) {
			if (is_array($v)) {
				if (array_key_exists('value', $v)) {
					return $k . ':' . $v['value'] . PHP_EOL;
				} else {
					return '';
				}
			} else {
				return $k . ':' . $v . PHP_EOL; 
			}
		};
		$str = 'BEGIN:VCALENDAR' . PHP_EOL;
		$str .= implode('', array_map($callback, $icsMergerResult['VCALENDAR'], array_keys($icsMergerResult['VCALENDAR'])));
		foreach ($icsMergerResult['VEVENTS'] as $event) {
			$str .= 'BEGIN:VEVENT' . PHP_EOL; 
			$str .= implode('', array_map($callback, $event, array_keys($event)));
			$str .= 'END:VEVENT' . PHP_EOL;
		}
		$str .= 'END:VCALENDAR';
		return $str;
	}
}
