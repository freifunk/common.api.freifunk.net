<?php
require_once(realpath(dirname(__FILE__)) .  '/../lib/ics-merger.php');

class IcsMergerTest extends PHPUnit_Framework_TestCase {

	private $merger;

	/**
	 * @before
	 */
	public function setupMerger() {
		$this->merger = new IcsMerger();
	}

	public function testWithTwoFile() {
		$this->merger->add(file_get_contents(realpath(dirname(__FILE__)) . '/../data/freifunk_0'));
		$this->merger->add(file_get_contents(realpath(dirname(__FILE__)) . '/../data/freifunk_3'));
		$result = $this->merger->getResult();
		$this->assertEquals(count($result['VEVENTS']), 3);
		/*
		echo '<pre>';
		var_dump($result);
		echo '</pre>';
		echo IcsMerger::getRawText($result);
		*/
	}

	public function testConversionLocalTimeWithTimezone() {
		$this->merger->add
		("BEGIN:VCALENDAR
		  BEGIN:VEVENT
		  DTSTART;TZID=Asia/Saigon:20150527T213000
		  SUMMARY:Event 1
		  END:VEVENT
		  END:VCALENDAR
		");
		$this->merger->add
		("BEGIN:VCALENDAR
		  BEGIN:VEVENT
		  DTSTART;TZID=EST:20150527T213000
		  SUMMARY:Event 2
		  END:VEVENT
		  END:VCALENDAR
		");
		$this->assertEquals($this->merger->getResult()['VEVENTS']['0']['DTSTART']['value'], '20150527T163000');
		$this->assertEquals($this->merger->getResult()['VEVENTS']['1']['DTSTART']['value'], '20150528T033000');
	}

	public function testConversionLocalTimeWithoutTimezone() {
		$this->merger->add
		("BEGIN:VCALENDAR
		  X-WR-TIMEZONE:Asia/Saigon
		  BEGIN:VEVENT
		  DTSTART:20150527T213000
		  SUMMARY:Event 1
		  END:VEVENT
		  END:VCALENDAR
		");
		$this->merger->add
		("BEGIN:VCALENDAR
		  BEGIN:VEVENT
		  DTSTART;TZID=EST:20150527T213000
		  SUMMARY:Event 2
		  END:VEVENT
		  END:VCALENDAR
		");
		$this->assertEquals($this->merger->getResult()['VEVENTS']['0']['DTSTART']['value'], '20150527T163000');
		$this->assertEquals($this->merger->getResult()['VEVENTS']['1']['DTSTART']['value'], '20150528T033000');
	}

	public function testGetRawText() {
		$this->config = parse_ini_file(realpath(dirname(__FILE__)) . '/../lib/' . IcsMerger::CONFIG_FILENAME);
		$defaultTimezone = $this->config['X-WR-TIMEZONE']; 
		$text = "BEGIN:VCALENDAR\nX-WR-TIMEZONE:$defaultTimezone\nPRODID:-//FOSSASIA//FOSSASIA Calendar//EN\nBEGIN:VEVENT\nDTSTART:20150527T213000\nSUMMARY:Event 1\nEND:VEVENT\nEND:VCALENDAR";
		$text1 = "BEGIN:VCALENDAR\nX-WR-TIMEZONE:$defaultTimezone";
		$text2 = "BEGIN:VEVENT\nDTSTART:20150527T213000\nSUMMARY:Event 1\nEND:VEVENT\nEND:VCALENDAR";
		$this->merger->add($text);
		$result = $this->merger->getResult();
		$pos = strpos(IcsMerger::getRawText($result), $text1);
		if ($pos === false) {
			$this->fail("Result should contains $text1");
		}
		$pos = strpos(IcsMerger::getRawText($result), $text2);
		if ($pos === false) {
			$this->fail("Result should contains $text2");
		}
	}

	public function testAddEventWithOption() {
		$this->merger->add
		("BEGIN:VCALENDAR
		  X-WR-TIMEZONE:Asia/Saigon
		  BEGIN:VEVENT
		  DTSTART:20150527T213000
		  SUMMARY:Event with option
		  END:VEVENT
		  END:VCALENDAR
		", array("hi" => "lo"));
		$this->assertEquals($this->merger->getResult()['VEVENTS']['0']['hi'], 'lo');
	}
}