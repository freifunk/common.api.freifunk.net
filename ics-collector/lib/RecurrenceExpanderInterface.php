<?php

namespace ICal;

/**
 * Interface für die Erweiterung von wiederkehrenden Ereignissen in iCalendar
 */
interface RecurrenceExpanderInterface
{
    /**
     * Erweitert ein wiederkehrendes Event in mehrere Einzel-Events basierend auf der Recurrence Rule
     *
     * @param array $event Das ursprüngliche Event mit RRULE
     * @param string|null $rangeStart Optionaler Startzeitpunkt im Format YYYYMMDD
     * @param string|null $rangeEnd Optionaler Endzeitpunkt im Format YYYYMMDD
     * @param string $defaultTimezone Die Standard-Zeitzone, falls im Event keine definiert ist
     * 
     * @return array Eine Liste von erweiterten Events (inklusive des Originals, ohne RRULE)
     */
    public function expandRecurringEvent(array $event, ?string $rangeStart = null, ?string $rangeEnd = null, string $defaultTimezone = 'UTC'): array;
    
    /**
     * Prüft, ob ein Event eine gültige Recurring Rule enthält
     *
     * @param array $event Das zu prüfende Event
     * @return boolean True, wenn das Event eine gültige RRULE hat
     */
    public function isRecurringEvent(array $event): bool;
} 