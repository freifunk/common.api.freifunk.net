# Calendar API Documentation

## Overview

The Calendar API provides a RESTful interface for accessing and filtering iCalendar (ICS) data from Freifunk communities. It processes merged calendar data and returns filtered, cleaned ICS output based on various parameters.

## Features

- **Source Filtering**: Filter events by specific Freifunk communities
- **Date Range Filtering**: Retrieve events within a specified time period
- **Recurring Event Expansion**: Automatically expand recurring events within the date range
- **Privacy Protection**: Removes sensitive properties like ATTENDEE and ORGANIZER
- **Timezone Normalization**: Handles timezone information consistently
- **Caching**: Built-in caching for improved performance
- **Validation**: Automatic ICS file validation and repair

## Base URL

```
https://api.freifunk.net/ics-collector/CalendarAPI.php
```

## HTTP Methods

- **GET**: Retrieve calendar data (only supported method)

## Parameters

### Required Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `source` | string | Comma-separated list of source identifiers or "all" for all sources |

### Optional Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `format` | string | `ics` | Output format (currently only "ics" is supported) |
| `from` | string | `now` | Start date for filtering events |
| `to` | string | `+6 months` | End date for filtering events |
| `limit` | integer | unlimited | Maximum number of events to return |

### Date Format Options

The `from` and `to` parameters support multiple formats:

- **Relative dates**: `now`, `+2 weeks`, `+1 month`, etc.
- **ISO date**: `2024-12-31`
- **ISO datetime**: `2024-12-31T23:59:59`

### Examples

```
# Get all events from all sources for the next 6 months
GET /CalendarAPI.php?source=all

# Get events from specific communities
GET /CalendarAPI.php?source=berlin,hamburg,munich

# Get events for a specific date range
GET /CalendarAPI.php?source=all&from=2024-01-01&to=2024-12-31

# Get only the next 10 events
GET /CalendarAPI.php?source=all&limit=10

# Get events for the next 2 weeks
GET /CalendarAPI.php?source=all&from=now&to=+2 weeks
```

## Response Format

### Success Response

**Content-Type**: `text/calendar; charset=UTF-8`

The API returns a valid iCalendar (ICS) file containing the filtered events.

```
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Freifunk//ICS Collector//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VEVENT
UID:event1@example.com
DTSTART;TZID=Europe/Berlin:20241201T190000
DTEND;TZID=Europe/Berlin:20241201T210000
SUMMARY:Freifunk Meetup Berlin
DESCRIPTION:Monthly meetup of Freifunk Berlin
LOCATION:c-base, Berlin
X-WR-SOURCE:berlin
END:VEVENT
END:VCALENDAR
```

### Error Response

**Content-Type**: `application/json; charset=UTF-8`  
**HTTP Status**: `400 Bad Request`

```json
{
  "error": "Missing required parameter : source"
}
```

## Event Processing

### Privacy Protection

The API automatically removes the following properties from events to protect privacy:

- `ATTENDEE`: Email addresses and names of event attendees
- `ORGANIZER`: Event organizer information
- `VALARM`: Alarm/reminder components

### Timezone Handling

- Events with floating time (no timezone) are assigned the default timezone (`Europe/Berlin`)
- Timezone information is preserved and normalized
- All datetime values include proper `TZID` parameters

### Recurring Events

- Recurring events are automatically expanded within the specified date range
- Each occurrence becomes a separate event in the output
- Original recurrence rules (`RRULE`) are removed from expanded events

## Caching

The API implements intelligent caching:

- **Cache Duration**: 1 hour (3600 seconds)
- **Cache Key**: Based on request parameters
- **Cache Headers**: 
  - `X-Cache: HIT` - Response served from cache
  - `X-Cache: MISS` - Response generated fresh

## Error Handling

### Common Error Scenarios

| Error | Description | Solution |
|-------|-------------|----------|
| Missing required parameter | `source` parameter not provided | Add `?source=all` or specific sources |
| Invalid parameter format | Date format doesn't match expected patterns | Use ISO format: `2024-12-31` |
| Unsupported parameter value | Invalid value for `format` parameter | Use `format=ics` |
| File not found | Merged ICS file is missing | Contact API administrator |

### Parameter Validation

The API validates all parameters according to these rules:

#### Source Parameter
- Required for all requests
- Can be "all" or comma-separated list of source identifiers
- Example: `source=berlin,hamburg` or `source=all`

#### Date Parameters (from/to)
- Must match one of these patterns:
  - `now`
  - `+N weeks` (e.g., `+2 weeks`)
  - `YYYY-MM-DD` (e.g., `2024-12-31`)
  - `YYYY-MM-DDTHH:MM:SS` (e.g., `2024-12-31T23:59:59`)

#### Limit Parameter
- Must be a non-negative integer
- `0` or empty means unlimited

## Configuration

The API uses centralized configuration through the `CalendarConfig` class:

### Default Values
- **Timezone**: `Europe/Berlin`
- **From Date**: `now`
- **To Date**: `+6 months`
- **Format**: `ics`
- **Cache Lifetime**: 3600 seconds (1 hour)

### Excluded Properties
- `ATTENDEE`
- `ORGANIZER`

### Excluded Components
- `VALARM`

## Integration Examples

### JavaScript/AJAX

```javascript
fetch('https://api.freifunk.net/ics-collector/CalendarAPI.php?source=all&limit=5')
  .then(response => response.text())
  .then(icsData => {
    // Process ICS data
    console.log(icsData);
  })
  .catch(error => console.error('Error:', error));
```

### PHP

```php
$url = 'https://api.freifunk.net/ics-collector/CalendarAPI.php';
$params = [
    'source' => 'berlin,hamburg',
    'from' => '2024-01-01',
    'to' => '2024-12-31'
];

$response = file_get_contents($url . '?' . http_build_query($params));
// Process ICS response
```

### Python

```python
import requests

url = 'https://api.freifunk.net/ics-collector/CalendarAPI.php'
params = {
    'source': 'all',
    'from': 'now',
    'to': '+1 month'
}

response = requests.get(url, params=params)
ics_data = response.text
```

### cURL

```bash
# Get all events for the next month
curl "https://api.freifunk.net/ics-collector/CalendarAPI.php?source=all&to=+1%20month"

# Get events from specific sources
curl "https://api.freifunk.net/ics-collector/CalendarAPI.php?source=berlin,hamburg&limit=10"
```

## Performance Considerations

### Caching Strategy
- Responses are cached for 1 hour
- Cache keys are based on all request parameters
- Use consistent parameter ordering for better cache hit rates

### Optimization Tips
- Use specific source filters instead of "all" when possible
- Limit date ranges to reduce processing time
- Use the `limit` parameter for pagination or preview functionality

### Rate Limiting
- No explicit rate limiting is currently implemented
- Please be respectful with request frequency
- Consider caching responses on your end for frequently accessed data

## Troubleshooting

### Common Issues

1. **Empty Response**
   - Check if the date range contains any events
   - Verify source identifiers are correct
   - Try using `source=all` to see all available events

2. **Invalid Date Format Error**
   - Ensure dates follow ISO format: `YYYY-MM-DD`
   - URL-encode special characters (e.g., `+` becomes `%2B`)

3. **Missing Events**
   - Check if events fall within the specified date range
   - Verify the source filter includes the desired communities
   - Remember that recurring events are expanded within the date range

### Debug Information

Add these parameters for debugging:
- Use a wide date range to see more events
- Try `source=all` to see all available sources
- Check the `X-Cache` header to verify caching behavior

## API Versioning

- **Current Version**: 1.0
- **Stability**: Stable
- **Backward Compatibility**: Maintained for all documented features

## Support

For technical support or feature requests:
- GitHub Issues: [freifunk/common.api.freifunk.net](https://github.com/freifunk/common.api.freifunk.net)
- Mailing List: [freifunk-dev@lists.freifunk.net](mailto:freifunk-dev@lists.freifunk.net)

## Changelog

### Version 1.0
- Initial stable release
- Centralized configuration system
- Privacy protection (ATTENDEE/ORGANIZER removal)
- VALARM component removal
- Improved timezone handling
- Comprehensive test coverage 