<?php

namespace ICal;

use Sabre\VObject\Reader;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use DateTime;
use DateTimeInterface;
use DateTimeZone;

/**
 * Calendar handler using Sabre VObject library to replace ICal.php functionality
 */
class SabreVObjectCalendarHandler
{
    /**
     * Default timezone for calendar processing
     */
    private DateTimeZone $defaultTimezone;

    /**
     * Properties to exclude from output (for privacy/cleanliness)
     */
    private array $excludedProperties;

    /**
     * Default calendar header properties
     */
    private array $defaultHeader;

    public function __construct(?string $timezone = null)
    {
        // Use central configuration
        $this->defaultTimezone = new DateTimeZone($timezone ?? CalendarConfig::getDefaultTimezone());
        $this->excludedProperties = CalendarConfig::getDefaultExcludedProperties();
        $this->defaultHeader = CalendarConfig::getDefaultCalendarHeader();
    }

    /**
     * Parse ICS string and return array of VEvent objects
     * 
     * @param string $icsContent ICS file content
     * @return VEvent[]
     */
    public function parseIcsString(string $icsContent): array
    {
        try {
            $calendar = Reader::read($icsContent);
            
            if (!$calendar instanceof VCalendar) {
                throw new \InvalidArgumentException('Invalid iCalendar data');
            }

            // Extract calendar-level timezone if available
            $calendarTimezone = $this->extractCalendarTimezone($calendar);
            if ($calendarTimezone) {
                $this->defaultTimezone = $calendarTimezone;
            }

            $events = [];
            foreach ($calendar->VEVENT as $event) {
                $events[] = $event;
            }

            // Normalize timezone handling
            $events = $this->normalizeEventTimezones($events);

            return $events;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to parse ICS content: ' . $e->getMessage());
        }
    }

    /**
     * Filter events by date range
     * 
     * @param VEvent[] $events
     * @param DateTimeInterface $from
     * @param DateTimeInterface $to
     * @return VEvent[]
     */
    public function filterEventsByDateRange(array $events, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $filteredEvents = [];

        foreach ($events as $event) {
            $eventStart = $event->DTSTART->getDateTime();
            
            if ($eventStart >= $from && $eventStart <= $to) {
                $filteredEvents[] = $event;
            }
        }

        return $filteredEvents;
    }

    /**
     * Expand recurring events within a date range
     * 
     * @param VEvent[] $events
     * @param DateTimeInterface $from
     * @param DateTimeInterface $to
     * @return VEvent[]
     */
    public function expandRecurringEvents(array $events, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $expandedEvents = [];

        foreach ($events as $event) {
            if (isset($event->RRULE)) {
                // Create a temporary calendar for expansion
                $tempCalendar = new VCalendar();
                $tempCalendar->add(clone $event);
                
                // Use our default timezone as reference for floating times
                $expandedCalendar = $tempCalendar->expand($from, $to, $this->defaultTimezone);
                
                foreach ($expandedCalendar->VEVENT as $expandedEvent) {
                    $expandedEvents[] = $expandedEvent;
                }
            } else {
                // Non-recurring event - add it as is (filtering happens later)
                $expandedEvents[] = $event;
            }
        }

        return $expandedEvents;
    }

    /**
     * Merge multiple arrays of events into one
     * 
     * @param array<VEvent[]> $eventArrays
     * @return VEvent[]
     */
    public function mergeEvents(array $eventArrays): array
    {
        $mergedEvents = [];

        foreach ($eventArrays as $events) {
            foreach ($events as $event) {
                $mergedEvents[] = $event;
            }
        }

        return $mergedEvents;
    }

    /**
     * Convert events to ICS string
     * 
     * @param VEvent[] $events
     * @return string
     */
    public function eventsToIcsString(array $events): string
    {
        $calendar = new VCalendar();
        
        // Set default header properties
        foreach ($this->defaultHeader as $property => $value) {
            $calendar->$property = $value;
        }

        foreach ($events as $event) {
            // Clean the event before adding it to the calendar
            $cleanedEvent = $this->cleanEvent($event);
            $calendar->add($cleanedEvent);
        }

        return $calendar->serialize();
    }

    /**
     * Clean an event by removing excluded properties
     * 
     * @param VEvent $event
     * @return VEvent
     */
    private function cleanEvent(VEvent $event): VEvent
    {
        // Clone the event to avoid modifying the original
        $cleanedEvent = clone $event;
        
        // Remove excluded properties
        foreach ($this->excludedProperties as $property) {
            if (isset($cleanedEvent->{$property})) {
                unset($cleanedEvent->{$property});
            }
        }
        $cleanedEvent->remove('VALARM');
        
        return $cleanedEvent;
    }

    /**
     * Parse ICS file and return array of VEvent objects
     * 
     * @param string $filePath Path to ICS file
     * @return VEvent[]
     */
    public function parseIcsFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("ICS file not found: $filePath");
        }

        $icsContent = file_get_contents($filePath);
        if ($icsContent === false) {
            throw new \RuntimeException("Failed to read ICS file: $filePath");
        }

        return $this->parseIcsString($icsContent);
    }

    /**
     * Set default timezone
     */
    public function setDefaultTimezone(string $timezone): void
    {
        $this->defaultTimezone = new DateTimeZone($timezone);
    }

    /**
     * Get default timezone
     */
    public function getDefaultTimezone(): DateTimeZone
    {
        return $this->defaultTimezone;
    }

    /**
     * Set default header properties
     */
    public function setDefaultHeader(array $header): void
    {
        $this->defaultHeader = array_merge($this->defaultHeader, $header);
    }

    /**
     * Get default header properties
     */
    public function getDefaultHeader(): array
    {
        return $this->defaultHeader;
    }

    /**
     * Set properties to exclude from output
     * 
     * @param array $properties Array of property names to exclude
     */
    public function setExcludedProperties(array $properties): void
    {
        $this->excludedProperties = $properties;
    }

    /**
     * Get properties excluded from output
     * 
     * @return array
     */
    public function getExcludedProperties(): array
    {
        return $this->excludedProperties;
    }

    /**
     * Add a property to the exclusion list
     * 
     * @param string $property Property name to exclude
     */
    public function addExcludedProperty(string $property): void
    {
        if (!in_array($property, $this->excludedProperties)) {
            $this->excludedProperties[] = $property;
        }
    }

    /**
     * Remove a property from the exclusion list
     * 
     * @param string $property Property name to include again
     */
    public function removeExcludedProperty(string $property): void
    {
        $this->excludedProperties = array_filter($this->excludedProperties, function($prop) use ($property) {
            return $prop !== $property;
        });
    }

    /**
     * Filter events by source
     * 
     * @param VEvent[] $events
     * @param string[] $allowedSources
     * @return VEvent[]
     */
    public function filterEventsBySource(array $events, array $allowedSources): array
    {
        if (empty($allowedSources) || in_array('all', $allowedSources)) {
            return $events;
        }

        $filteredEvents = [];
        $allowedSourcesFlipped = array_flip($allowedSources);

        foreach ($events as $event) {
            $source = isset($event->{'X-WR-SOURCE'}) ? (string)$event->{'X-WR-SOURCE'} : '';
            if (isset($allowedSourcesFlipped[$source])) {
                $filteredEvents[] = $event;
            }
        }

        return $filteredEvents;
    }

    /**
     * Limit the number of events
     * 
     * @param VEvent[] $events
     * @param int $limit
     * @return VEvent[]
     */
    public function limitEvents(array $events, int $limit): array
    {
        if ($limit <= 0) {
            return $events;
        }

        return array_slice($events, 0, $limit);
    }

    /**
     * Parse date string (supports relative dates like 'now', '+2 weeks', absolute dates)
     * 
     * @param string $dateString
     * @return DateTime
     */
    public function parseDate(string $dateString): DateTime
    {
        try {
            // Handle special cases
            if ($dateString === 'now') {
                return new DateTime();
            }

            // Handle relative dates like '+2 weeks'
            if (strpos($dateString, '+') === 0 || strpos($dateString, '-') === 0) {
                return new DateTime($dateString);
            }

            // Handle absolute dates (ISO format)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
                return new DateTime($dateString);
            }

            // Handle datetime format
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $dateString)) {
                return new DateTime($dateString);
            }

            // Fallback: try to parse as is
            return new DateTime($dateString);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid date format: $dateString");
        }
    }

    /**
     * Sort events by start date
     * 
     * @param VEvent[] $events
     * @return VEvent[]
     */
    public function sortEventsByStartDate(array $events): array
    {
        usort($events, function (VEvent $a, VEvent $b) {
            $dateA = $a->DTSTART->getDateTime();
            $dateB = $b->DTSTART->getDateTime();
            return $dateA <=> $dateB;
        });

        return $events;
    }

    /**
     * Validate event data (check for required fields)
     * 
     * @param VEvent $event
     * @return bool
     */
    public function validateEvent(VEvent $event): bool
    {
        // Check for required fields according to RFC 5545
        if (!isset($event->UID)) {
            return false;
        }

        if (!isset($event->DTSTART)) {
            return false;
        }

        if (!isset($event->DTSTAMP)) {
            return false;
        }

        return true;
    }

    /**
     * Process calendar data similar to CalendarAPI functionality
     * 
     * @param string $icsFilePath Path to ICS file
     * @param array $parameters Request parameters (from, to, source, limit, format)
     * @return array Result array with contentType and data
     */
    public function processCalendarRequest(string $icsFilePath, array $parameters = []): array
    {
        // Merge parameters with defaults from central configuration
        $defaultParams = CalendarConfig::getDefaultApiParameters();
        $parameters = CalendarConfig::mergeWithDefaults($parameters, $defaultParams);
        
        // Parse parameters
        $from = $parameters['from'];
        $to = $parameters['to'];
        $sources = isset($parameters['source']) ? explode(',', $parameters['source']) : ['all'];
        $limit = isset($parameters['limit']) ? (int)$parameters['limit'] : 0;
        $format = $parameters['format'];

        // Parse date range
        $fromDate = $this->parseDate($from);
        $toDate = $this->parseDate($to);

        // Load and parse events
        $events = $this->parseIcsFile($icsFilePath);

        // Apply filters and processing
        // First expand recurring events, then filter by date range
        // This ensures that events with DTSTART in the past can still generate future instances
        $events = $this->expandRecurringEvents($events, $fromDate, $toDate);
        $events = $this->filterEventsByDateRange($events, $fromDate, $toDate);
        $events = $this->filterEventsBySource($events, $sources);
        $events = $this->sortEventsByStartDate($events);

        if ($limit > 0) {
            $events = $this->limitEvents($events, $limit);
        }

        // Generate output
        $result = [];
        $result['contentType'] = 'text/calendar';
        $result['data'] = $this->eventsToIcsString($events);

        return $result;
    }

    /**
     * Normalize events to ensure proper timezone handling
     * This ensures that all events have proper TZID parameters where needed
     * 
     * @param VEvent[] $events
     * @return VEvent[]
     */
    public function normalizeEventTimezones(array $events): array
    {
        foreach ($events as $event) {
            $this->normalizeEventDateTimeProperties($event);
        }

        return $events;
    }

    /**
     * Normalize a single event's datetime properties
     * 
     * @param VEvent $event
     */
    private function normalizeEventDateTimeProperties(VEvent $event): void
    {
        // Process DTSTART
        if (isset($event->DTSTART)) {
            $this->normalizeDateTime($event->DTSTART);
        }

        // Process DTEND
        if (isset($event->DTEND)) {
            $this->normalizeDateTime($event->DTEND);
        }

        // Process other datetime properties
        $dateTimeProperties = ['DTSTAMP', 'CREATED', 'LAST-MODIFIED'];
        foreach ($dateTimeProperties as $property) {
            if (isset($event->{$property})) {
                $this->normalizeDateTime($event->{$property});
            }
        }
    }

    /**
     * Normalize a single datetime property to ensure proper timezone handling
     * 
     * @param \Sabre\VObject\Property $dateTimeProperty
     */
    private function normalizeDateTime(\Sabre\VObject\Property $dateTimeProperty): void
    {
        // Skip DATE-only properties (all-day events)
        $valueParam = $dateTimeProperty->offsetGet('VALUE');
        if ($valueParam && (string)$valueParam === 'DATE') {
            return;
        }
        
        $value = (string)$dateTimeProperty;
        
        // Check if it's floating time (no Z suffix, no TZID parameter)
        $hasUtcSuffix = substr($value, -1) === 'Z';
        $hasTzid = $dateTimeProperty->offsetGet('TZID') !== null;
        
        // If it's floating time, assign default timezone
        if (!$hasUtcSuffix && !$hasTzid) {
            $dateTimeProperty->offsetSet('TZID', $this->defaultTimezone->getName());
        }
    }

    /**
     * Extract timezone from calendar-level X-WR-TIMEZONE property
     * 
     * @param VCalendar $calendar
     * @return DateTimeZone|null
     */
    private function extractCalendarTimezone(VCalendar $calendar): ?DateTimeZone
    {
        if (isset($calendar->{'X-WR-TIMEZONE'})) {
            try {
                return new DateTimeZone((string)$calendar->{'X-WR-TIMEZONE'});
            } catch (\Exception $e) {
                // Ignore invalid timezone
            }
        }

        return null;
    }
} 