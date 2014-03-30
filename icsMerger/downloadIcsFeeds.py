#!/usr/bin/python3

import json
import os.path
import re
import urllib
from optparse import OptionParser
from urllib.request import urlopen

ffSummarizedJsonUrl = "http://freifunk.net/map/ffSummarizedDir.json"
icsDir = "./ics/freifunk/"

#log helper function
def log(logLevel, message):
	if logLevel <= options.logLevel:
		print("Message from engine room (level " + str(logLevel) + "): " + message)

#read some command line arguments
parser = OptionParser()
parser.add_option("-l", "--loglevel", dest="logLevel", default=1, type=int, help="define loglevel")
parser.add_option("-p", "--path", dest="icsDir", default="./ics/freifunk/", type="string", help="path where to save ics files")
(options, args) = parser.parse_args()

try:
	ffSummarizedJsonRaw = urlopen(ffSummarizedJsonUrl, None, 10)
except BaseException as e:
	log(0, "error reading directory " + str(e))
	exit(1)

ffSummarizedJson = json.loads(ffSummarizedJsonRaw.readall().decode('utf-8'))

log(5, str(ffSummarizedJson))

for community in ffSummarizedJson:
	log(4, community)
	detail = ffSummarizedJson[community]
	if 'feeds' in detail:
		log(4, str(detail['feeds']))
		feedcounter = 0
		for feed in detail['feeds']:
			if feed['type'] == "ics":
				feedcounter += 1
				log(3, feed['url'])
				try:
					ics = urlopen(feed['url'])
					f = open(icsDir + community + str(feedcounter) + '.ics', "wb")
					try:
						f.write(ics.read())
					finally:
						f.close()
				except IOError:
					log(0, "Error writing file for community " + community)



