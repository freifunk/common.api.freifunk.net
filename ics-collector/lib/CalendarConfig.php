<?php

namespace ICal;

/**
 * Central configuration class for the Calendar API and handlers
 * 
 * This class centralizes all configuration settings to avoid duplication
 * between CalendarAPI and SabreVObjectCalendarHandler.
 */
class CalendarConfig
{
    /**
     * Default timezone for calendar processing
     */
    public const DEFAULT_TIMEZONE = 'Europe/Berlin';
    
    /**
     * Default date range settings
     */
    public const DEFAULT_FROM_DATE = 'now';
    public const DEFAULT_TO_DATE = '+6 months';
    
    /**
     * Default calendar header properties
     */
    public const DEFAULT_CALENDAR_HEADER = [
        'VERSION' => '2.0',
        'PRODID' => '-//Freifunk//ICS Collector//EN',
        'CALSCALE' => 'GREGORIAN',
        'METHOD' => 'PUBLISH'
    ];
    
    /**
     * Properties to exclude from output (for privacy/cleanliness)
     */
    public const DEFAULT_EXCLUDED_PROPERTIES = [
        'ATTENDEE',
        'ORGANIZER'
    ];
    
    /**
     * Components to exclude from output
     */
    public const DEFAULT_EXCLUDED_COMPONENTS = [
        'VALARM'
    ];
    
    /**
     * Default API parameters
     */
    public const DEFAULT_API_PARAMETERS = [
        'format' => 'ics',
        'from' => self::DEFAULT_FROM_DATE,
        'to' => self::DEFAULT_TO_DATE
    ];
    
    /**
     * Supported HTTP methods for the API
     */
    public const SUPPORTED_HTTP_METHODS = ['GET'];
    
    /**
     * Mandatory API parameters
     */
    public const MANDATORY_PARAMETERS = ['source'];
    
    /**
     * Supported values for specific parameters
     */
    public const SUPPORTED_PARAMETER_VALUES = [
        'format' => ['ics']
    ];
    
    /**
     * Supported formats (regex patterns) for parameters
     */
    public const SUPPORTED_PARAMETER_FORMATS = [
        'from' => [
            "/^now$/",
            "/^\+\d+ weeks$/",
            "/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]$/", // date format, e.g. 1997-12-31
            "/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]T[0-2][0-9]:[0-6][0-9]:[0-6][0-9]$/" // datetime format, e.g. 2015-06-10T10:09:59
        ],
        'to' => [
            "/^now$/",
            "/^\+\d+ weeks$/",
            "/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]$/",
            "/^[1-2][0-9]{3}-[0-1][0-9]-[0-3][0-9]T[0-2][0-9]:[0-6][0-9]:[0-6][0-9]$/"
        ],
        'limit' => [
            "/^[0-9]*$/"
        ]
    ];
    
    /**
     * Cache settings
     */
    public const CACHE_LIFETIME = 3600; // 1 hour in seconds
    
    /**
     * Get default timezone
     */
    public static function getDefaultTimezone(): string
    {
        return self::DEFAULT_TIMEZONE;
    }
    
    /**
     * Get default calendar header
     */
    public static function getDefaultCalendarHeader(): array
    {
        return self::DEFAULT_CALENDAR_HEADER;
    }
    
    /**
     * Get default excluded properties
     */
    public static function getDefaultExcludedProperties(): array
    {
        return self::DEFAULT_EXCLUDED_PROPERTIES;
    }
    
    /**
     * Get default excluded components
     */
    public static function getDefaultExcludedComponents(): array
    {
        return self::DEFAULT_EXCLUDED_COMPONENTS;
    }
    
    /**
     * Get default API parameters
     */
    public static function getDefaultApiParameters(): array
    {
        return self::DEFAULT_API_PARAMETERS;
    }
    
    /**
     * Get supported HTTP methods
     */
    public static function getSupportedHttpMethods(): array
    {
        return self::SUPPORTED_HTTP_METHODS;
    }
    
    /**
     * Get mandatory parameters
     */
    public static function getMandatoryParameters(): array
    {
        return self::MANDATORY_PARAMETERS;
    }
    
    /**
     * Get supported parameter values
     */
    public static function getSupportedParameterValues(): array
    {
        return self::SUPPORTED_PARAMETER_VALUES;
    }
    
    /**
     * Get supported parameter formats
     */
    public static function getSupportedParameterFormats(): array
    {
        return self::SUPPORTED_PARAMETER_FORMATS;
    }
    
    /**
     * Get cache lifetime
     */
    public static function getCacheLifetime(): int
    {
        return self::CACHE_LIFETIME;
    }
    
    /**
     * Merge user configuration with defaults
     * 
     * @param array $userConfig User-provided configuration
     * @param array $defaults Default configuration
     * @return array Merged configuration
     */
    public static function mergeWithDefaults(array $userConfig, array $defaults): array
    {
        return array_merge($defaults, $userConfig);
    }
    
    /**
     * Validate parameter value against supported values
     * 
     * @param string $parameter Parameter name
     * @param string $value Parameter value
     * @return bool True if valid
     */
    public static function isValidParameterValue(string $parameter, string $value): bool
    {
        $supportedValues = self::getSupportedParameterValues();
        
        if (!isset($supportedValues[$parameter])) {
            return true; // No restrictions for this parameter
        }
        
        return in_array($value, $supportedValues[$parameter], true);
    }
    
    /**
     * Validate parameter format against supported formats
     * 
     * @param string $parameter Parameter name
     * @param string $value Parameter value
     * @return bool True if valid
     */
    public static function isValidParameterFormat(string $parameter, string $value): bool
    {
        $supportedFormats = self::getSupportedParameterFormats();
        
        if (!isset($supportedFormats[$parameter])) {
            return true; // No format restrictions for this parameter
        }
        
        foreach ($supportedFormats[$parameter] as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
} 