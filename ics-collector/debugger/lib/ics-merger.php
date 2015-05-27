<?php
require_once 'class.iCalReader.php';

class IcsMerger {

	private $inputs = array();
	private $config = array();
	const CONFIG_FILENAME = 'ics-merger-config.ini';

	public function __construct() {
		$this->config = parse_ini_file(IcsMerger::CONFIG_FILENAME);
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
			foreach($ical->cal as $key => $value) {
				switch($key) {
				case 'VCALENDAR' :
					$result['VCALENDAR'] = array_merge($result['VCALENDAR'], $value);
					break;
				case 'VEVENT' :
					$result['VEVENTS'] = array_merge($result['VEVENTS'], $value);
					break;
				default : 
					// ignore others
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

	public static function getRawText($icsMergerResult) {

		$callback = function ($v, $k) {
			if (is_array($v)) {
				return '';
			} else {
				return $k . ':' . $v . "\n"; 
			}
		};
		$str = implode('', array_map($callback, $icsMergerResult['VCALENDAR'], array_keys($icsMergerResult['VCALENDAR'])));
		foreach ($icsMergerResult['VEVENTS'] as $event) {
			$str .= 'BEGIN:VEVENT' . "\n"; 
			$str .= implode('', array_map($callback, $event, array_keys($event)));
			$str .= 'END:VEVENT' . "\n";
		}
		return $str;
	}
}
