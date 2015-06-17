FOSSASIA API Common Toolbox
===========

## API Data Collector

[collector/collectCommunities.py](https://github.com/fossasia/common.api.fossasia.net/blob/master/collector/collectCommunities.py)

----------------------

Generate some aggregated data from registered communities :

* `ffSummarizedDir.json`
* `ffGeoJson.json`
* `ffHtmlTable.html`

**Requires** : community jsons list `directory.json`


## API File Updater
[contrib/ffapi-update-nodes.py](https://github.com/fossasia/common.api.fossasia.net/blob/master/contrib/ffapi-update-nodes.py)

----------------------

Eine Möglichkeit, die Anzahl der Knoten und die von OLSR angekündigten Services zu erneuern bietet das Python-Script unter https://github.com/fossasia/common.api.fossasia.net/blob/master/contrib/ffapi-update-nodes.py.

Es kann beispielsweise per Cronjob regelmäßig ausgeführt werden und muss auf dem Server laufen, auf dem auch die API-Datei liegt. Außerdem benötigt das Script Zugriff auf eine Instanz des jsoninfo-Plugins von OLSR. Um auch die von OLSR angekündigten Services darstellen zu können, muss die Datei mit den Informationen lokal vorliegen. Idealerweise läuft ein OLSR-Client mit Verbindung zum restlichen Netz auf dem Server.

Alle notwendigen Einstellungen können im Kopf des Scripts konfiguriert werden.

## Community counter
[contrib/counter.php](https://github.com/fossasia/common.api.fossasia.net/blob/master/contrib/counter.php)

----------------------

**Usage** : call `counter.php?count=nodes` or `counter.php?count=communities` or `counter.php` (counts communities)

## ICS tools
Set of tools to work with ics feeds

----------------------

* [ics-collector/CalendarAPI.php](https://github.com/fossasia/common.api.fossasia.net/blob/master/ics-collector/CalendarAPI.php)

Calendar API endpoint for all communities events [api.fossasia.net/ics-collector/CalendarAPI.php/](http://api.fossasia.net/ics-collector/CalendarAPI.php/)

* [ics-collector/ics-collector.php](https://github.com/fossasia/common.api.fossasia.net/blob/master/ics-collector/ics-collector.php)

Complete list of available ics feeds provided by registered communities

**Requires** : `ffSummarizedDir.json`

* [ics-collector/lib/ics-merger.php](https://github.com/fossasia/common.api.fossasia.net/blob/master/ics-collector/lib/ics-merger.php)

An ics merger library that could serve multiple purposes

* [ics-collector/debugger](https://github.com/fossasia/common.api.fossasia.net/tree/master/ics-collector/debugger)

A simple webapp to debug ics tools. 

Link : [api.fossasia.net/ics-collector/debugger/](http://api.fossasia.net/ics-collector/debugger/)



History
=======

Our goal is to collect information about Open Source Communities and Hackspaces all over Asia. This information will be used to aggregate contact data, locations, news feeds and events.

The FOSSASIA Api is based on the Freifunk Api and the Hackerspaces API (http://hackerspaces.nl/spaceapi/). Each community provides its data in a well defined format, hosted on their places (web space, wiki, web servers) and contributes a link to the directory. This directory only consists of the name and an url per community. First services supported by our freifunk API are the global community map and a community feed aggregator.

The FOSSASIA API is designed to collect metadata of communities in a decentral way and make it available to other users.


