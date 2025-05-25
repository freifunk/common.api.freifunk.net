<?php

// Load Composer Autoloader to include Sabre VObject
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use Sabre\VObject\Reader;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;

/**
 * Class IcsMerger - Modern implementation using Sabre VObject directly
 */
class IcsMerger {

	private VCalendar $mergedCalendar;
	private array $defaultHeader = array();
	
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
		
		// Create a new empty calendar with default headers
		$this->mergedCalendar = new VCalendar();
		
		// Set default calendar properties
		foreach ($this->defaultHeader as $key => $value) {
			$this->mergedCalendar->add($key, $value);
		}
	}

	/**
	 * Add text string in ics format to the merger
	 * @param string $text
	 * @param null|array $options
	 */
	public function add($text, $options = null): void {
		try {
			$calendar = Reader::read($text);
			
			// Add all events from this calendar to the merged calendar
			if ($calendar->VEVENT) {
				foreach ($calendar->VEVENT as $event) {
					// Clone the event to avoid issues when moving between calendars
					$clonedEvent = clone $event;
					
					// Add custom options to the event
					if ($options !== null) {
						foreach ($options as $key => $value) {
							$clonedEvent->add($key, $value);
						}
					}
					
					// Add the event to our merged calendar
					$this->mergedCalendar->add($clonedEvent);
				}
			}
		} catch (\Exception $e) {
			error_log("Error parsing ICS content: " . $e->getMessage());
			// Skip invalid calendars
		}
	}

	/**
	 * Return the result as a VCalendar object
	 * @return VCalendar
	 */
	public function getCalendar(): VCalendar {
		return $this->mergedCalendar;
	}

	/**
	 * Return the result after parsing & merging inputs ics (added via IcsMerger::add)
	 * @return array (Legacy compatibility - converts VCalendar back to array format)
	 */
	public function getResult(): array {
		$result = array(
			'VCALENDAR' => array(),
			'VEVENTS' => array()
		);
		
		// Extract calendar properties
		foreach ($this->mergedCalendar->children() as $child) {
			if ($child instanceof VEvent) {
				// This is an event, process it later
				continue;
			} else {
				// This is a calendar property
				$result['VCALENDAR'][$child->name] = (string)$child;
			}
		}
		
		// Extract events
		foreach ($this->mergedCalendar->VEVENT as $event) {
			$eventArray = [];
			
			// Get all properties from the event (exclude components like VALARM)
			foreach ($event->children() as $child) {
				// Skip components (like VALARM, VTIMEZONE), only process properties
				if ($child instanceof \Sabre\VObject\Component) {
					continue;
				}
				
				$name = $child->name;
				
				// Handle special cases for date/time properties
				if (in_array($name, ['DTSTART', 'DTEND'])) {
					$tzidParam = $child->offsetGet('TZID');
					if ($tzidParam) {
						$eventArray[$name] = [
							'value' => (string)$child,
							'params' => ['TZID' => (string)$tzidParam]
						];
					} else {
						$eventArray[$name] = (string)$child;
					}
				} else {
					$eventArray[$name] = (string)$child;
				}
			}
			
			$result['VEVENTS'][] = $eventArray;
		}

		return $result;
	}

	/**
	 * Convert an array returned by IcsMerger::getResult() into valid ics string
	 * @param array $icsMergerResult
	 * @return string
	 */
	public static function getRawText($icsMergerResult): string {
		// If we have a VCalendar object, use it directly
		if ($icsMergerResult instanceof VCalendar) {
			return $icsMergerResult->serialize();
		}
		
		// For legacy array format, create a temporary VCalendar and serialize it
		// This ensures consistent formatting through Sabre VObject
		$calendar = new VCalendar();
		
		// Add calendar properties
		if (isset($icsMergerResult['VCALENDAR'])) {
			foreach ($icsMergerResult['VCALENDAR'] as $key => $value) {
				$calendar->add($key, $value);
			}
		}
		
		// Add events
		if (isset($icsMergerResult['VEVENTS'])) {
			foreach ($icsMergerResult['VEVENTS'] as $eventData) {
				$event = $calendar->createComponent('VEVENT');
				
				foreach ($eventData as $key => $value) {
					if (is_array($value) && isset($value['params']) && isset($value['params']['TZID'])) {
						// Handle timezone parameters
						$property = $event->add($key, $value['value']);
						$property['TZID'] = $value['params']['TZID'];
					} else {
						$event->add($key, $value);
					}
				}
				
				$calendar->add($event);
			}
		}
		
		return $calendar->serialize();
	}
	
	/**
	 * Get the merged calendar as ICS string (modern approach)
	 * @return string
	 */
	public function getIcsString(): string {
		return $this->mergedCalendar->serialize();
	}
}
