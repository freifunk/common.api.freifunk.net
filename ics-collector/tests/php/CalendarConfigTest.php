<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use ICal\CalendarConfig;

class CalendarConfigTest extends TestCase
{
    public function testGetDefaultTimezone(): void
    {
        $timezone = CalendarConfig::getDefaultTimezone();
        $this->assertEquals('Europe/Berlin', $timezone);
    }
    
    public function testGetDefaultCalendarHeader(): void
    {
        $header = CalendarConfig::getDefaultCalendarHeader();
        
        $this->assertIsArray($header);
        $this->assertArrayHasKey('VERSION', $header);
        $this->assertArrayHasKey('PRODID', $header);
        $this->assertArrayHasKey('CALSCALE', $header);
        $this->assertArrayHasKey('METHOD', $header);
        
        $this->assertEquals('2.0', $header['VERSION']);
        $this->assertEquals('-//Freifunk//ICS Collector//EN', $header['PRODID']);
        $this->assertEquals('GREGORIAN', $header['CALSCALE']);
        $this->assertEquals('PUBLISH', $header['METHOD']);
    }
    
    public function testGetDefaultExcludedProperties(): void
    {
        $properties = CalendarConfig::getDefaultExcludedProperties();
        
        $this->assertIsArray($properties);
        $this->assertContains('ATTENDEE', $properties);
        $this->assertContains('ORGANIZER', $properties);
    }
    
    public function testGetDefaultExcludedComponents(): void
    {
        $components = CalendarConfig::getDefaultExcludedComponents();
        
        $this->assertIsArray($components);
        $this->assertContains('VALARM', $components);
    }
    
    public function testGetDefaultApiParameters(): void
    {
        $params = CalendarConfig::getDefaultApiParameters();
        
        $this->assertIsArray($params);
        $this->assertArrayHasKey('format', $params);
        $this->assertArrayHasKey('from', $params);
        $this->assertArrayHasKey('to', $params);
        
        $this->assertEquals('ics', $params['format']);
        $this->assertEquals('now', $params['from']);
        $this->assertEquals('+6 months', $params['to']);
    }
    
    public function testGetSupportedHttpMethods(): void
    {
        $methods = CalendarConfig::getSupportedHttpMethods();
        
        $this->assertIsArray($methods);
        $this->assertContains('GET', $methods);
    }
    
    public function testGetMandatoryParameters(): void
    {
        $mandatory = CalendarConfig::getMandatoryParameters();
        
        $this->assertIsArray($mandatory);
        $this->assertContains('source', $mandatory);
    }
    
    public function testGetSupportedParameterValues(): void
    {
        $values = CalendarConfig::getSupportedParameterValues();
        
        $this->assertIsArray($values);
        $this->assertArrayHasKey('format', $values);
        $this->assertContains('ics', $values['format']);
    }
    
    public function testGetSupportedParameterFormats(): void
    {
        $formats = CalendarConfig::getSupportedParameterFormats();
        
        $this->assertIsArray($formats);
        $this->assertArrayHasKey('from', $formats);
        $this->assertArrayHasKey('to', $formats);
        $this->assertArrayHasKey('limit', $formats);
        
        // Test that each format array contains regex patterns
        foreach ($formats['from'] as $pattern) {
            $this->assertIsString($pattern);
            $this->assertStringStartsWith('/', $pattern);
            $this->assertStringEndsWith('/', $pattern);
        }
    }
    
    public function testGetCacheLifetime(): void
    {
        $lifetime = CalendarConfig::getCacheLifetime();
        
        $this->assertIsInt($lifetime);
        $this->assertEquals(3600, $lifetime); // 1 hour
    }
    
    public function testMergeWithDefaults(): void
    {
        $defaults = ['a' => 1, 'b' => 2, 'c' => 3];
        $userConfig = ['b' => 20, 'd' => 4];
        
        $merged = CalendarConfig::mergeWithDefaults($userConfig, $defaults);
        
        $this->assertEquals(['a' => 1, 'b' => 20, 'c' => 3, 'd' => 4], $merged);
    }
    
    public function testIsValidParameterValue(): void
    {
        // Test valid format value
        $this->assertTrue(CalendarConfig::isValidParameterValue('format', 'ics'));
        
        // Test invalid format value
        $this->assertFalse(CalendarConfig::isValidParameterValue('format', 'json'));
        
        // Test parameter without restrictions
        $this->assertTrue(CalendarConfig::isValidParameterValue('nonexistent', 'anything'));
    }
    
    public function testIsValidParameterFormat(): void
    {
        // Test valid 'from' formats
        $this->assertTrue(CalendarConfig::isValidParameterFormat('from', 'now'));
        $this->assertTrue(CalendarConfig::isValidParameterFormat('from', '+2 weeks'));
        $this->assertTrue(CalendarConfig::isValidParameterFormat('from', '2024-12-31'));
        $this->assertTrue(CalendarConfig::isValidParameterFormat('from', '2024-12-31T23:59:59'));
        
        // Test invalid 'from' formats
        $this->assertFalse(CalendarConfig::isValidParameterFormat('from', 'invalid'));
        $this->assertFalse(CalendarConfig::isValidParameterFormat('from', '24-12-31')); // Wrong year format
        
        // Test valid 'limit' formats
        $this->assertTrue(CalendarConfig::isValidParameterFormat('limit', '10'));
        $this->assertTrue(CalendarConfig::isValidParameterFormat('limit', '0'));
        $this->assertTrue(CalendarConfig::isValidParameterFormat('limit', ''));
        
        // Test invalid 'limit' formats
        $this->assertFalse(CalendarConfig::isValidParameterFormat('limit', 'abc'));
        $this->assertFalse(CalendarConfig::isValidParameterFormat('limit', '-5'));
        
        // Test parameter without format restrictions
        $this->assertTrue(CalendarConfig::isValidParameterFormat('nonexistent', 'anything'));
    }
    
    public function testConstantsAreAccessible(): void
    {
        // Test that all constants are accessible
        $this->assertEquals('Europe/Berlin', CalendarConfig::DEFAULT_TIMEZONE);
        $this->assertEquals('now', CalendarConfig::DEFAULT_FROM_DATE);
        $this->assertEquals('+6 months', CalendarConfig::DEFAULT_TO_DATE);
        $this->assertEquals(3600, CalendarConfig::CACHE_LIFETIME);
        
        $this->assertIsArray(CalendarConfig::DEFAULT_CALENDAR_HEADER);
        $this->assertIsArray(CalendarConfig::DEFAULT_EXCLUDED_PROPERTIES);
        $this->assertIsArray(CalendarConfig::DEFAULT_EXCLUDED_COMPONENTS);
        $this->assertIsArray(CalendarConfig::DEFAULT_API_PARAMETERS);
        $this->assertIsArray(CalendarConfig::SUPPORTED_HTTP_METHODS);
        $this->assertIsArray(CalendarConfig::MANDATORY_PARAMETERS);
        $this->assertIsArray(CalendarConfig::SUPPORTED_PARAMETER_VALUES);
        $this->assertIsArray(CalendarConfig::SUPPORTED_PARAMETER_FORMATS);
    }
} 