# Calendar API
Calendar API is a FOSSASIA open-source web service that provides events information of all FOSSASIA community,
 in ics/iCal or json format.

## How it works ?

The API is backed by data retrieved from iCal feeds from FOSSASIA communities. Your community events are not here ? Please follow these simple steps :

1. Get the link to your community iCal feed. [What is iCal](https://en.wikipedia.org/wiki/ICalendar) ?
2. Add it to your community json file, under `feeds` section, category `ics` : 
```json
   "UELT": {
   "feeds": [
            {
                "name": "calendar",
                "url": "http://loco.ubuntu.com/events/ubuntu-eg/ical",
                "category": "ics"
            }
        ]
   }
```
You should see your ics feeds added shortly after our ics updater reruns.

## How to use

An up-to-date instance of Calendar API is deployed here. That's where you should send all data requests :
```
http://api.fossasia.net/ics-collector/CalendarAPI.php?
```

You can host your own instace as well. CalendarAPI.php requires 2 dependencies : the ics parser and the merged ics file. To set up your own instance, please follow this directory structure :

```
  api-folder
   |
   | -- CalendarAPI.php
   | -- data
         |
         | -- ffMerged.ics
   | -- lib
         |
         | -- class.iCalReader.php
```
(or you can just clone the whole repo)

## Parameters

Parameter | Required | Value Formats | Multiple values* | Default Value | Description
--- | --- | --- | --- | --- | ---
source | x |  `all`<br/>a community short name | x| | The community source of event feeds 
format |  | `ics`<br/> `json`||`json`|Result format. Note that some other parameters don't work with `ics` format (`fields` parameter for example)
fields | | `start`, `end`, `location`, `summary`,  `description`, .. |x ||In `json` mode, filter the event field to be returned. If this parameter is not specified, the fields will be returned by default : `start`, `end`, `summary`, `description`, `location`
from | | date* <br/>datetime* <br/>`now` | ||Lower bound of returned events datetime. Based on `start` time.
to | | date<br/>datetime<br/>`now` | || Upper bound of returned events datetime. Based on `start` time.
limit | |An integer | | |   The limit number of return results. If not specified, the API will return as many events as it can.
sort | |`asc-date`<br/>`desc-date` | || Sort result in ascending or descending order of event time. Based on `start` time.


**\*Multiple values** : Support multiple values, separated by commas

**\*date-format** : `YYYY-MM-DD`, for e.g. `1997-04-10`

**\*datetime-format** : `YYYY-MM-DD\TH:m:s`, for e.g. `2015-08-16T10:09:59`
## Examples 

 * Events from all communities, starting now until the end of 2015, ordered from oldest to latest :
```
    ?source=all&start=now&end=2015-12-31T23:59:59&sort=asc-date
```

 * Events from halle community, maximum 6 events, and return only fields `start`, `source` and `url` :
```
   ?source=halle&limit=6&fields=start,source,url
```
 * Simplestquery
```
   ?source=all
```

## Supported method
 Only `HTTP GET` is supported. This is a read-only API, meaning that users can not update events information with this API. It could be updated directly from ics sources.

## Testing
  
 To run API tests : 

```sh
cd common.api.fossasia.net/ics-collector/tests
jasmine-node test_spec.js
```

*Requirement* : jasmine-node. To install jasmine-node globally, run this command :
```sh
npm install jasmine-node -g
```

## Contribute
 We're happy to have reported issues and pull requests. Please clearly specify scenario, API call and API result.
 
