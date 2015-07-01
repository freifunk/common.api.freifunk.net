FOSSASIA API Common Toolbox
===========
Set of utility scripts to process, aggregrate, and extract information from FOSSASIA communities data

## Components

* API data collector & aggregator [collector/collectCommunities.py](https://github.com/fossasia/common.api.fossasia.net/blob/master/collector/collectCommunities.py)

* Set of tools to work with `.ics` format, including an ics collector, parser, merger and debugger ([live demo](http://api.fossasia.net/ics-collector/debugger/)).

* FOSSASIA Calendar API. Check out the details in [API wiki](https://github.com/fossasia/common.api.fossasia.net/blob/master/ics-collector/README.md)

* and more
 
## History

Our goal is to collect information about Open Source Communities and Hackspaces all over Asia. This information will be used to aggregate contact data, locations, news feeds and events.

The FOSSASIA Api is based on the Freifunk Api and the Hackerspaces API (http://hackerspaces.nl/spaceapi/). Each community provides its data in a well defined format, hosted on their places (web space, wiki, web servers) and contributes a link to the directory. This directory only consists of the name and an url per community. First services supported by our freifunk API are the global community map and a community feed aggregator.

The FOSSASIA API is designed to collect metadata of communities in a decentral way and make it available to other users.

## Contribute

Most of the scripts are written in PHP and Python, and could be run from a terminal. Feel free to clone the repo, make changes and send us Pull Requests.
