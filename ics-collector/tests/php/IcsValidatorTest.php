<?php
/**
 * Unit Tests für die IcsValidator-Klasse
 */

namespace Tests;

use ICal\IcsValidator;
use PHPUnit\Framework\TestCase;

class IcsValidatorTest extends TestCase
{
    private string $validIcsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
DTSTART:20240501T100000Z
DTEND:20240501T110000Z
SUMMARY:Test Event
UID:test123@example.com
END:VEVENT
END:VCALENDAR
ICS;

    private string $invalidIcsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
DTSTART:
DTEND:20240501T110000Z
SUMMARY:Invalid Event
UID:invalid123@example.com
END:VEVENT
END:VCALENDAR
ICS;

    private string $noEventsIcsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//EN
CALSCALE:GREGORIAN
END:VCALENDAR
ICS;

    private string $missingUidIcsContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
DTSTART:20240501T100000Z
DTEND:20240501T110000Z
SUMMARY:Missing UID Event
END:VEVENT
END:VCALENDAR
ICS;

    /**
     * Test, dass eine gültige ICS-Datei als gültig erkannt wird
     */
    public function testValidIcsContentIsValid(): void
    {
        $validator = new IcsValidator();
        $result = $validator->validateContent($this->validIcsContent);
        
        $this->assertTrue($result);
        $this->assertEmpty($validator->getErrors());
    }
    
    /**
     * Test, dass eine ICS-Datei ohne Events als gültig erkannt wird, aber eine Warnung erhält
     */
    public function testNoEventsIcsHasWarning(): void
    {
        $validator = new IcsValidator();
        $result = $validator->validateContent($this->noEventsIcsContent);
        
        $this->assertTrue($result);
        $this->assertEmpty($validator->getErrors());
        $this->assertNotEmpty($validator->getWarnings());
        $this->assertStringContainsString('Keine Events gefunden', $validator->getWarnings()[0]);
    }
    
    /**
     * Test, dass eine ICS-Datei mit leerem DTSTART als ungültig erkannt wird
     */
    public function testEmptyDtstartIsInvalid(): void
    {
        $validator = new IcsValidator();
        $result = $validator->validateContent($this->invalidIcsContent);
        
        $this->assertFalse($result);
        $this->assertNotEmpty($validator->getErrors());
        $this->assertStringContainsString('DTSTART hat keinen Wert', $validator->getErrors()[0]);
    }
    
    /**
     * Test, dass eine ICS-Datei ohne UID als ungültig erkannt wird
     */
    public function testMissingUidIsInvalid(): void
    {
        $validator = new IcsValidator();
        $result = $validator->validateContent($this->missingUidIcsContent);
        
        $this->assertFalse($result);
        $this->assertNotEmpty($validator->getErrors());
        $this->assertStringContainsString('UID fehlt', $validator->getErrors()[0]);
    }
    
    /**
     * Test, dass die fixIcsContent-Methode ungültige Events entfernt
     */
    public function testFixIcsContentRemovesInvalidEvents(): void
    {
        $validator = new IcsValidator();
        $fixedContent = $validator->fixIcsContent($this->invalidIcsContent);
        
        // Die fixierte Version sollte nicht mehr das ungültige Event enthalten
        $this->assertNotEquals($this->invalidIcsContent, $fixedContent);
        
        // Die fixierte Version sollte gültig sein
        $isValid = $validator->validateContent($fixedContent);
        $this->assertTrue($isValid);
        $this->assertEmpty($validator->getErrors());
    }
    
    /**
     * Test für mehrere Events in einer ICS-Datei
     */
    public function testMultipleEventsValidation(): void
    {
        $multiEventContent = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
DTSTART:20240501T100000Z
DTEND:20240501T110000Z
SUMMARY:Event 1
UID:event1@example.com
END:VEVENT
BEGIN:VEVENT
DTSTART:20240502T100000Z
DTEND:20240502T110000Z
SUMMARY:Event 2
UID:event2@example.com
END:VEVENT
BEGIN:VEVENT
DTSTART:
DTEND:20240503T110000Z
SUMMARY:Invalid Event
UID:event3@example.com
END:VEVENT
END:VCALENDAR
ICS;

        $validator = new IcsValidator();
        
        // Die Datei sollte ungültig sein wegen dem dritten Event
        $result = $validator->validateContent($multiEventContent);
        $this->assertFalse($result);
        
        // Nach der Reparatur sollte die Datei gültig sein
        $fixedContent = $validator->fixIcsContent($multiEventContent);
        
        // Statt den String direkt zu prüfen, validieren wir ihn
        $isValid = $validator->validateContent($fixedContent);
        $this->assertTrue($isValid, "Fixierte Version sollte valide sein");
        
        // Prüfe, ob gültige Events noch enthalten sind
        $this->assertStringContainsString("Event 1", $fixedContent, "Event 1 sollte noch in der fixierten Version enthalten sein");
        $this->assertStringContainsString("Event 2", $fixedContent, "Event 2 sollte noch in der fixierten Version enthalten sein");
        
        // Prüfe, ob ungültige Events entfernt wurden
        $this->assertStringNotContainsString("DTSTART:\nDTEND", $fixedContent, "Ungültiges Event sollte entfernt worden sein");
    }
    
    /**
     * Test für die Validierung der Grundstruktur
     */
    public function testStructureValidation(): void
    {
        $invalidStructure = "BEGIN:VCALENDAR\nVERSION:2.0\nEND:INVALID";
        
        $validator = new IcsValidator();
        $result = $validator->validateContent($invalidStructure);
        
        $this->assertFalse($result);
        $errors = $validator->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('endet nicht mit END:VCALENDAR', $errors[0]);
    }
    
    /**
     * Test für die Validierung von DTEND und DURATION
     */
    public function testEndTimeValidation(): void
    {
        $bothDtendAndDuration = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//EN
BEGIN:VEVENT
DTSTART:20240501T100000Z
DTEND:20240501T110000Z
DURATION:PT1H
SUMMARY:Conflict Event
UID:conflict@example.com
END:VEVENT
END:VCALENDAR
ICS;

        $validator = new IcsValidator();
        $result = $validator->validateContent($bothDtendAndDuration);
        
        $this->assertFalse($result);
        $errors = $validator->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('DTEND und DURATION dürfen nicht gleichzeitig vorkommen', $errors[0]);
    }
} 