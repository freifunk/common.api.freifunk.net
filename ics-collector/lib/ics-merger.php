<?php
require_once 'class.iCalReader.php';

class IcsMerger {

	private $inputs = array();
	private $defaultHeader = array();
	private $defaultTimezone;
	const CONFIG_FILENAME = 'ics-merger-config.ini';

    /**
     * Initialize a new IcsMerger. Default header properties can be specified in the first parameter. 
     * If not specified, the constructor will automatically look for default header in ./ics-merger-config.ini 
     * @param null|array $defaultHeader
     */
	public function __construct($defaultHeader = null) {
		$this->defaultHeader = $defaultHeader;
		if ($this->defaultHeader === null) {
			$configs = parse_ini_file(IcsMerger::CONFIG_FILENAME, true);
			$this->defaultHeader = $configs['ICS_HEADER'];
		}
		
		$this->defaultTimezone = new DateTimeZone($this->defaultHeader['X-WR-TIMEZONE']);
	}

	/**
	 * Add text string in ics format to the merger
	 * @param string $text
	 * @param null|array $options
	 */
	public function add($text, $options = null) {
		$ical = new ICal(explode("\n", $text), true);
		if ($options != null) {
			/*
			$options = IcsMerger::arrayToIcs($options);
			$insertPos = stripos($text, 'VEVENT') + strlen('VEVENT');
			$text = substr_replace($text, "\n" . $options, $insertPos, 1);
			*/
			foreach ($options as $key => $value) {
				if (!array_key_exists('VEVENT',$ical->cal)){
					continue;
				}
				foreach ($ical->cal['VEVENT'] as &$event) {
					$event[$key] = $value;
				}
			}
		}
		array_push($this->inputs, $ical);
	}

	/**
	 * Return the result after parsing & merging inputs ics (added via IcsMerger::add)
	 * @return array
	 */
	public function getResult() {
		$result = array(
			'VCALENDAR' => array(),
			'VEVENTS' => array()
		);
		foreach ($this->inputs as $ical) {
			//var_dump($ical);
			$timezone = null;
			foreach($ical->cal as $key => $value) {
				switch($key) {
				case 'VCALENDAR' :
					$result['VCALENDAR'] = array_merge($result['VCALENDAR'], $this->processCalendarHead($value, $timezone));
					print_r($result['VCALENDAR']);
					break;
				case 'VEVENT' :
					$result['VEVENTS'] = array_merge($result['VEVENTS'], $this->processEvents($value, $timezone));
					break;
				default : 
					break;
				}
			}
		}

		foreach ($result['VCALENDAR'] as $key => $value) {
			if (array_key_exists($key, $this->defaultHeader)) {
				$result['VCALENDAR'][$key] = $this->defaultHeader[$key];
			} else {
				unset($result['VCALENDAR'][$key]);
			}
		}

		$callback = function($value) {
			return $value;
		};
		// flatten array
		$result['VEVENTS'] = array_map($callback, $result['VEVENTS']);
		return $result;
	}

	// traverse calendar header to extract important informations : default timezone, etc.
	private function processCalendarHead($calendarHead, &$timezone) {
		foreach ($calendarHead as $key => $value) {
			switch ($key) {
				// google calendar
				case 'X-WR-TIMEZONE':
					$timezone = $value;
					break;
				case 'TZID':
					$timezone = $value;
					break;
				default:
					break;
			}
		}
		return $calendarHead;
	}


	// traverse calendar events to perform modifications
	// e.g : convert datetime to default timezone
	private function processEvents($events, $timezone = null) {
		foreach($events as &$event) {
			foreach ($event as $key => &$value) {
				if (!array_key_exists('DTSTAMP', $event)) {
					$event['DTSTAMP'] = "19700101T000000Z";
				}
				switch($key) {
				case 'ATTENDEE':
					unset($event['ATTENDEE']);
				// properties of type DATE / DATE-TIME
				case 'CREATED':
					if (preg_match("/^\d{8}T\d{6}$/", $event['CREATED']) ) {
						$event['CREATED'] = $event['CREATED'] . "Z";
					}
				case 'DTSTAMP':
					if (preg_match("/^\d{8}T\d{6}$/", $event['DTSTAMP']) ) {
						$event['DTSTAMP'] = $event['DTSTAMP'] . "Z";
					}
				case 'DTSTART':
					if (!preg_match("/^\d{8}T\d{6}/", $event['DTSTART']) && 
						preg_match("/^\d{8}$/", $event['DTSTART'])) {
						$event['DTSTART'] = $event['DTSTART'] . "T000000Z";
					}
				case 'DTEND' :
					if (!preg_match("/^\d{8}T\d{6}/", $event['DTEND']) && 
						preg_match("/^\d{8}$/", $event['DTEND'])) {
						$event['DTEND'] = $event['DTEND'] . "T000000Z";
					}
				case 'LAST-MODIFIED':
					if (preg_match("/^\d{8}T\d{6}$/", $event['LAST-MODIFIED']) ) {
						$event['LAST-MODIFIED'] = $event['LAST-MODIFIED'] . "Z";
					}
				case 'RDATE' :
					// only local datime needs conversion
					if (array_key_exists('meta', $value) && array_key_exists('type', $value['meta']) && $value['meta']['type'] == 'DATE-TIME' && $value['meta']['format'] == 'LOCAL-TIME') {
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

	/**
	 * Convert an array returned by IcsMerger::getResult() into valid ics string
	 * @param array $icsMergerResult
	 * @return string
	 */
	public static function getRawText($icsMergerResult) {
		
		$str = 'BEGIN:VCALENDAR' . "\r\n";
		$str .= IcsMerger::arrayToIcs($icsMergerResult['VCALENDAR']);
		foreach ($icsMergerResult['VEVENTS'] as $event) {
			$str .= 'BEGIN:VEVENT' . "\r\n"; 
			$str .= IcsMerger::arrayToIcs($event);
			$str .= 'END:VEVENT' . "\r\n";
		}
		$str .= 'END:VCALENDAR';
		return $str;
	}

	// convert an array of property name - property value into valid ics
	private static function arrayToIcs($array) {
		$callback = function ($v, $k) {
			if (is_array($v)) {
				if (array_key_exists('value', $v)) {
					return $k . ':' . $v['value'] . "\r\n";
				} else {
					return '';
				}
			} else {
				return $k . ':' . $v . "\r\n"; 
			}
		};
		return implode('', array_map($callback, $array, array_keys($array)));
	}
}
