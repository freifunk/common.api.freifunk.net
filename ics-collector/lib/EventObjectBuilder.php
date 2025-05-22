<?php
/**
 * @category    Parser
 * @package     ics-parser
 */

namespace ICal;

/**
 * Builder class for EventObject
 * Provides a simple and type-safe way to create EventObject instances
 */
class EventObjectBuilder
{
    private EventObject $event;

    public function __construct()
    {
        // Create an empty EventObject
        $this->event = new EventObject([]);
    }

    /**
     * Sets the event title
     * 
     * @param string|null $summary Event title
     * @return self
     */
    public function summary(?string $summary): self
    {
        $this->event->setSummary($summary);
        return $this;
    }

    /**
     * Sets the start date
     * 
     * @param string|null $dtstart Start date in format YYYYMMDD[T]HHMMSS[Z]
     * @return self
     */
    public function dtstart(?string $dtstart): self
    {
        $this->event->setDtstart($dtstart);
        return $this;
    }

    /**
     * Sets the end date
     * 
     * @param string|null $dtend End date in format YYYYMMDD[T]HHMMSS[Z]
     * @return self
     */
    public function dtend(?string $dtend): self
    {
        $this->event->setDtend($dtend);
        return $this;
    }

    /**
     * Sets the event duration
     * 
     * @param string|null $duration Duration in format PnDTnHnMnS
     * @return self
     */
    public function duration(?string $duration): self
    {
        $this->event->setDuration($duration);
        return $this;
    }

    /**
     * Sets the event timestamp
     * 
     * @param string|null $dtstamp Timestamp in format YYYYMMDD[T]HHMMSS[Z]
     * @return self
     */
    public function dtstamp(?string $dtstamp): self
    {
        $this->event->setDtstamp($dtstamp);
        return $this;
    }

    /**
     * Sets the event UID
     * 
     * @param string|null $uid Unique identifier
     * @return self
     */
    public function uid(?string $uid): self
    {
        $this->event->setUid($uid);
        return $this;
    }

    /**
     * Sets the creation date
     * 
     * @param string|null $created Creation date in format YYYYMMDD[T]HHMMSS[Z]
     * @return self
     */
    public function created(?string $created): self
    {
        $this->event->setCreated($created);
        return $this;
    }

    /**
     * Sets the last modification date
     * 
     * @param string|null $lastmodified Last modification date
     * @return self
     */
    public function lastmodified(?string $lastmodified): self
    {
        $this->event->setLastmodified($lastmodified);
        return $this;
    }

    /**
     * Sets the event description
     * 
     * @param string|null $description Description
     * @return self
     */
    public function description(?string $description): self
    {
        $this->event->setDescription($description);
        return $this;
    }

    /**
     * Sets the event location
     * 
     * @param string|null $location Location
     * @return self
     */
    public function location(?string $location): self
    {
        $this->event->setLocation($location);
        return $this;
    }

    /**
     * Sets the sequence number
     * 
     * @param string|null $sequence Sequence number
     * @return self
     */
    public function sequence(?string $sequence): self
    {
        $this->event->setSequence($sequence);
        return $this;
    }

    /**
     * Sets the event status
     * 
     * @param string|null $status Status (CONFIRMED, TENTATIVE, CANCELLED)
     * @return self
     */
    public function status(?string $status): self
    {
        $this->event->setStatus($status);
        return $this;
    }

    /**
     * Sets the event transparency
     * 
     * @param string|null $transp Transparency (OPAQUE, TRANSPARENT)
     * @return self
     */
    public function transp(?string $transp): self
    {
        $this->event->setTransp($transp);
        return $this;
    }

    /**
     * Sets the event organizer
     * 
     * @param string|null $organizer Organizer
     * @return self
     */
    public function organizer(?string $organizer): self
    {
        $this->event->setOrganizer($organizer);
        return $this;
    }

    /**
     * Sets the event attendee
     * 
     * @param string|null $attendee Attendee
     * @return self
     */
    public function attendee(?string $attendee): self
    {
        $this->event->setAttendee($attendee);
        return $this;
    }

    /**
     * Sets the event URL
     * 
     * @param string|null $url URL
     * @return self
     */
    public function url(?string $url): self
    {
        $this->event->setUrl($url);
        return $this;
    }

    /**
     * Sets the event categories
     * 
     * @param string|null $categories Categories
     * @return self
     */
    public function categories(?string $categories): self
    {
        $this->event->setCategories($categories);
        return $this;
    }

    /**
     * Sets the event source
     * 
     * @param string|null $xWrSource Source
     * @return self
     */
    public function xWrSource(?string $xWrSource): self
    {
        $this->event->setXWrSource($xWrSource);
        return $this;
    }

    /**
     * Sets the event source URL
     * 
     * @param string|null $xWrSourceUrl Source URL
     * @return self
     */
    public function xWrSourceUrl(?string $xWrSourceUrl): self
    {
        $this->event->setXWrSourceUrl($xWrSourceUrl);
        return $this;
    }

    /**
     * Sets the recurrence rule
     * 
     * @param string|null $rrule Recurrence rule
     * @return self
     */
    public function rrule(?string $rrule): self
    {
        $this->event->setRrule($rrule);
        return $this;
    }

    /**
     * Sets the start date array
     * 
     * @param array|null $dtstartArray Start date array
     * @return self
     */
    public function dtstartArray(?array $dtstartArray): self
    {
        $this->event->setDtstartArray($dtstartArray);
        return $this;
    }

    /**
     * Sets the end date array
     * 
     * @param array|null $dtendArray End date array
     * @return self
     */
    public function dtendArray(?array $dtendArray): self
    {
        $this->event->setDtendArray($dtendArray);
        return $this;
    }

    /**
     * Sets the start date timezone
     * 
     * @param string|null $dtstartTz Start date timezone
     * @return self
     */
    public function dtstartTz(?string $dtstartTz): self
    {
        $this->event->setDtstartTz($dtstartTz);
        return $this;
    }

    /**
     * Sets the end date timezone
     * 
     * @param string|null $dtendTz End date timezone
     * @return self
     */
    public function dtendTz(?string $dtendTz): self
    {
        $this->event->setDtendTz($dtendTz);
        return $this;
    }

    /**
     * Adds a custom attribute
     * 
     * @param string $key Key
     * @param mixed $value Value
     * @return self
     */
    public function withAttribute(string $key, $value): self
    {
        // For custom attributes we need to set the property directly
        // as there are no specific setters for them
        $this->event->$key = $value;
        return $this;
    }

    /**
     * Builds the EventObject from the set data
     * 
     * @return EventObject
     */
    public function build(): EventObject
    {
        return $this->event;
    }
} 