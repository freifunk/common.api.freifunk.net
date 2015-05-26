<?php
require_once 'class.iCalReader.php';

class IcsMerger {

	private $inputs = array();

	public function add($text) {
		array_push($this->inputs, $text);
	}

	public function getResult() {
		$result = array(
			"VCALENDAR" => array(),
			"VEVENTS" => array()
		); 
		foreach ($this->inputs as $icsText) {
			$ical = new ICal(explode("\n", $icsText), true);
			foreach($ical->cal as $key => $value) {
				switch($key) {
				case "VCALENDAR" :
					$result["VCALENDAR"] = array_merge($result["VCALENDAR"], $value);
					break;
				case "VEVENT" :
					$result["VEVENTS"] = array_merge($result["VEVENTS"], $value);
					break;
				default : 
					// ignore others
					break;
				}
			}
		}

		$callback = function($value) {
			return $value;
		};
		// flatten array
		$result["VEVENTS"] = array_map($callback, $result["VEVENTS"]);
		return $result;
	}
	
}