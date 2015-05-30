<?php
require_once(realpath(dirname(__FILE__)) .  '/../lib/class.iCalReader.php');

class IcalParserTest extends PHPUnit_Framework_TestCase {

	public function testRecurrentRuleDisabled() {
		$parser = new ICal(explode(PHP_EOL,
		 "BEGIN:VCALENDAR
		  BEGIN:VEVENT
		  DTSTART;TZID=Asia/Saigon:20150527T213000
		  RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
		  SUMMARY:Event 1
		  END:VEVENT
		  END:VCALENDAR"), true);
		$this->assertEquals(count($parser->cal['VEVENT']), 1);
	}

	public function testRecurrentRuleWithCount() {
		$parser = new ICal(explode(PHP_EOL,
		 "BEGIN:VCALENDAR
		  BEGIN:VEVENT
		  DTSTART;TZID=Asia/Saigon:20150101T0000000
		  DTEND;TZID=Asia/Saigon:20150101T000001
		  RRULE:FREQ=DAILY;COUNT=10;INTERVAL=2
		  SUMMARY:Event 1
		  END:VEVENT
		  END:VCALENDAR"));
		$this->assertEquals(count($parser->cal['VEVENT']), 10);
	}

	public function testRecurrentRuleWithUntil() {
		$parser = new ICal(explode(PHP_EOL,
		 "BEGIN:VCALENDAR
		  BEGIN:VEVENT
		  DTSTART;TZID=Asia/Saigon:20150101T0000001
		  DTEND;TZID=Asia/Saigon:20150101T000002
		  RRULE:FREQ=DAILY;UNTIL=20160101T000000Z
		  SUMMARY:Event 1
		  END:VEVENT
		  END:VCALENDAR"));
		$this->assertEquals(count($parser->cal['VEVENT']), 366);
	}

	public function testRecurrentRuleEveryJanuaryIn3Years() {
		$parser = new ICal(explode(PHP_EOL,
		 "BEGIN:VCALENDAR
		  BEGIN:VEVENT
		  DTSTART;TZID=America/New_York:19980101T090000
		  DTEND;TZID=America/New_York:19980101T091000
		  RRULE:FREQ=YEARLY;UNTIL=20000131T140000Z;BYMONTH=1;BYDAY=SU,MO,TU,WE,TH,FR,SA
		  SUMMARY:Event 1
		  END:VEVENT
		  END:VCALENDAR"));
		$this->assertEquals(count($parser->cal['VEVENT']), 3);
	}

}