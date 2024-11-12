#!/usr/bin/python3

import json
import shutil
import os.path
import re
import copy
import urllib
from optparse import OptionParser
from urllib.request import urlopen, install_opener, build_opener, ProxyHandler
from datetime import tzinfo, timedelta, datetime

#to correct the location
def correctLocation(data):
	#checking the geocodes in location
	if "geoCode" in data["location"].keys():
		data["location"]["lat"] = data["location"]["geoCode"]["lat"]
		data["location"]["lon"] = data["location"]["geoCode"]["lon"]

		if 'additionalLocations' in data['location']:
			for i in data["location"]["additionalLocations"]:
				if "geoCode" in i:
					i["lat"] = i["geoCode"]["lat"]
					i["lon"] = i["geoCode"]["lon"]
	return data;

#log helper function
def log(logLevel, message):
	if logLevel <= options.logLevel:
		print("Message from engine room (level " + str(logLevel) + "): " + message)

#load config
def loadConfig():
	global ffDirUrl, ffGeoJson, ffSummarizedJson, ffHtmlTable, htmlTableCommunityMapCss, htmlTableSorttableJs
	try:
		configFile = open("config.json", "r")
	except BaseException as e:
		log(0, "error opening config file " +str(e))
		exit(1)

	configContent = configFile.read()
	config = json.loads(configContent)
	configFile.close()

	ffDirUrl = config['ffDirUrl'] if 'ffDirUrl' in config else "https://raw.github.com/freifunk/directory.api.freifunk.net/master/directory.json"
	ffGeoJson = config['ffGeoJson'] if 'ffGeoJson' in config else "/var/www/vhosts/freifunk.net/httpdocs/map/ffGeoJson.json"
	ffSummarizedJson = config['ffSummarizedJson'] if 'ffSummarizedJson' in config else "/var/www/vhosts/freifunk.net/httpdocs/map/ffSummarizedDir.json"
	ffHtmlTable = config['ffHtmlTable'] if 'ffHtmlTable' in config else "/var/www/vhosts/freifunk.net/httpdocs/map/ffHtmlTable.html"
	htmlTableCommunityMapCss = config['htmlTableCommunityMapCss'] if 'htmlTableCommunityMapCss' in config else "//www.freifunk.net/map/community_map.css"
	htmlTableSorttableJs = config['htmlTableSorttableJs'] if 'htmlTableSorttableJs' in config else "//www.freifunk.net/map/sorttable.js"

#load directory
def loadDirectory(url):
	try:
		ffDirectoryRaw = urlopen(url, None, 10)
	except BaseException as e:
		log(0, "error reading directory " + str(e))
		exit(1)

	return json.loads(ffDirectoryRaw.read().decode('utf-8'))

#create a summarized json file, works as cache
def summarizedJson(ffDir, path):
	time = datetime.now().isoformat(' ')
	historyTime = datetime.now().strftime('%Y%m%d-%H.%M.%S-')
	summary = dict() 
	#open summarized file first
	try:
		summaryFile = open(path, "r")
		historyPath = os.path.dirname(path) + "/history"
		shutil.copy(path, historyPath + "/" + historyTime + os.path.basename(path))
	except IOError as e:
		if e.errno == 2:
			summaryFile = open(path, "w")
		else:
			log(0, "error opening summary file " +str(e))
			exit(1)
	except shutil.Error as e:
		log(0, "error backupping ffSummarizedDir.json to \"history/" + historyTime + os.path.basename(path))
	except BaseException as e:
		log(0, "error opening summary file " +str(e))
		exit(1)
	
	if summaryFile.mode == "r":
		content = summaryFile.read()
		if content != "":
			log(4, "cache file " +content)
			summary = json.loads(content)
		#close and reopen file
		summaryFile.close()
	if summary is None:
		summary = dict()
	else:
		#cleanup
		copyOfSummary = summary.copy()
		for sCommunity in copyOfSummary:
			if not sCommunity in ffDir:
				del summary[sCommunity]
				log(0, sCommunity + " is not in our directory anymore and is now deleted from summary!")

	for community in ffDir:
		log(3, "working on community: " + ffDir[community])
		error = None
		additionalLocations = []
		try:
			request = urllib.request.Request(
				ffDir[community],
				data=None,
				headers={
        				'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.47 Safari/537.36'
    				}
			)
			ffApi = json.loads(urlopen(request, None, 10).read().decode('utf-8'))
		except UnicodeError as e:
			try:
				ffApi = json.loads(urlopen(ffDir[community]).read().decode('iso8859_2'))
				log(0, "Unicode Error: " + ffDir[community] + ": " + str(e) + ", try iso8859_2 instead")
				pass
			except BaseException as e:
				log(0, "Error reading community api file " + ffDir[community] + ": " + str(e))
				error = e
		except BaseException as e:
			log(0, "Error reading community api file " + ffDir[community] + ": " + str(e))
			error = e

		if (error is None):
			#OLr wie bisher mtime setzten
			ffApi['mtime'] = time
			ffApi = correctLocation(ffApi)
			if 'additionalLocations' in ffApi['location']:
				for additionalLocation in ffApi['location']['additionalLocations']:
					additionalProperties = copy.deepcopy(ffApi)
					additionalProperties['state'].pop('nodes', None)
					additionalProperties.pop('nodeMaps', None)
					additionalProperties.pop('feeds', None)
					additionalProperties.pop('services', None)
					additionalProperties.pop('timeline', None)
					additionalProperties['location'] = additionalLocation
					additionalProperties['name'] = additionalProperties['name'] + ' (' + additionalLocation['city'] + ')'
					additionalLocations.append(additionalProperties)
					additionalProperties = ""
		else:
			#OLr ffApi von summary lesen oder neu erstellen
			try:	#kein has_key verfügbar
				ffApi = summary[community]
			except:
				ffApi = dict()	#init new instance
				ffApi['mtime'] = time
				#nachfolgende Felder werden für tableHtml benötigt
				ffApi['location'] = dict()
				ffApi['location']['city'] = community
				ffApi['name'] = community
				ffApi['state'] = dict()
			#OLr im Fehlerfall etime und error setzten (statt mtime)
			ffApi['etime'] = time
			ffApi['error'] = str(type(error)) + " " + str(error)	#OLr exception type und nachricht

		summary[community] = ffApi
		for index, additionalLocation in enumerate(additionalLocations):
			summary[community + str(index)] = additionalLocation
	log(4, "our summary: " + str(summary))
	summaryResult = json.dumps(summary, indent=4)

	try:
		summaryFile = open(path, "w")
		summaryFile.write(str(summaryResult))
		summaryFile.flush()
	finally:
		summaryFile.close()

#create geojson output
def geoJson(summary, geoJsonPath):

	features=[]
	#prepare GeoJSON format
	ffGeoJson = { "type" : "FeatureCollection", "features" : features }

	for community in summary:
		properties=dict()
		details = summary[community]
		log(3, "working on community: " + str(summary[community]))
	
		#add data according to http://wiki.freifunk.net/Fields_we_should_use
		try: 
			for contacts in details['contact']:
				properties[contacts] = details['contact'][contacts] 
			geometry = { "type" : "Point", "coordinates" : [ details['location']['lon'], details['location']['lat']] }
			properties['name'] = details['name']
			if 'metacommunity' in details:
				properties['metacommunity'] = details['metacommunity']
			if 'socialprojects' in details:
				properties['socialprojects'] = details['socialprojects']
			if 'city' in details['location']:
				properties['city'] = details['location']['city']
			if 'country' in details['location']:
				properties['country'] = details['location']['country']
			if 'address' in details['location']:
				properties['address'] = details['location']['address']
			properties['url'] = details['url']
			if 'metadetails' in details:
				properties['metadetails'] = details['metadetails']
			if 'feeds' in details:
				properties['feeds'] = details['feeds']
			if 'events' in details:
				properties['events'] = details['events']
			if 'nodes' in details['state']:
				properties['nodes'] = details['state']['nodes']
			if 'nodeMaps' in details:
				properties['nodeMaps'] = details['nodeMaps']
		
			properties['mtime'] = details['state']['lastchange']
			if 'logo' in details['state']:
				properties['logo'] = details['state']['logo']
			properties['shortname'] = community
		except BaseException as e:
			log(1, "There's something wrong with the JSON file: " + str(e))
			continue
	

		features.append({ "type" : "Feature", "geometry" : geometry, "properties" : properties })
		properties=""

	result = json.dumps(ffGeoJson, indent=4)
	log(3, "our result: " + result)
	#write summary to bin directory
	try:
		f = open(geoJsonPath, "w")
		try:
			f.write(str(result))
		finally:
			f.close()
	except IOError:
		pass

def sanitizeContactUrls(contacts):
	if 'url' in contacts and not re.match(r'^http([s]?):\/\/.*', contacts['url']):
		contacts['url'] = "http://" + contacts['url']
	if 'email' in contacts and not re.match(r'^mailto:.*', contacts['email']):
		contacts['email'] = "mailto:" + contacts['email']
	if 'twitter' in contacts and not re.match(r'^http([s]?):\/\/.*', contacts['twitter']):
		contacts['twitter'] = "https://twitter.com/" + contacts['twitter']
	if 'irc' in contacts and not re.match(r'^irc:.*', contacts['irc']):
		contacts['irc'] = "irc:" + contacts['irc']
	if 'jabber' in contacts and not re.match(r'^jabber:.*', contacts['jabber']):
		contacts['jabber'] = "jabber:" + contacts['jabber']
	if 'identica' in contacts and not re.match(r'^identica:.*', contacts['identica']):
		contacts['identica'] = "identica:" + contacts['identica']
	if 'phone' in contacts and not re.match(r'^tel:.*', contacts['phone']):
		contacts['phone'] = "tel:" + contacts['phone']
	return contacts

def tableHtml(summary, HtmlTablePath):
	htmlOutputHead = "<link rel=\"stylesheet\" href=\"" + htmlTableCommunityMapCss + "\" />"
	htmlOutputHead += "<script src=\"" + htmlTableSorttableJs + "\"></script>"
	htmlOutputHead += "<table class=\"sortable community-table\"><tr><th>Name</th><th class=\"sorttable_sorted\">Stadt/Region<span id=\"sorttable_sortfwdind\">&nbsp;▾</span></th><th>Firmware</th><th>Routing</th><th>Knoten</th><th>Kontakt</th></tr>"

	htmlOutputFoot = "</table>"
	htmlOutputContent = ""
	for community in sorted(summary.items(), key=lambda k_v: k_v[1]['location']['city']):
		details = community[1]
		htmlOutputContent += "<tr>"
		if 'url' in details:
			if not re.match(r'^http([s]?):\/\/.*', details['url']):
				details['url'] = "http://" + details['url']
			htmlOutputContent += "<td><a href=\"" + details['url'] + "\">" + details['name'] + "</a></td>"
		else:
			htmlOutputContent += "<td>" + details['name'] + "</td>"
		htmlOutputContent += "<td>" + details['location']['city'] + "</td>"
	
		if 'techDetails' in details:
			if 'firmware' in details['techDetails'] and 'name' in details['techDetails']['firmware']:
				htmlOutputContent += "<td>" + details['techDetails']['firmware']['name'] + "</td>"
			else:
				htmlOutputContent += "<td></td>"

			if 'routing' in details['techDetails']:
				routing = ""
				if isinstance(details['techDetails']['routing'], list):
					for r in details['techDetails']['routing']:
						routing = routing + ", " + r
					routing = routing[2:]
				else:
					routing = details['techDetails']['routing']
				htmlOutputContent += "<td>" + routing + "</td>"
			else:
				htmlOutputContent += "<td></td>"
		else:
			htmlOutputContent += "<td></td><td></td>"
 	
		if 'nodes' in details['state']:
			htmlOutputContent += "<td>" + str(details['state']['nodes']) + "</td>"
		else:
			htmlOutputContent += "<td></td>"
		if 'contact' in details:
			details['contact'] = sanitizeContactUrls(details['contact'])
			htmlOutputContent += "<td class=\"community-popup\"><ul class=\"contacts\">"
			for contact in details['contact']:
				if contact == 'ml':
					continue
				htmlOutputContent += "<li class=\"contact\"><a href=\"" + details['contact'][contact] + "\" class=\"button " + contact + "\" target=\"_window\"></a></li>" 
			htmlOutputContent += "</ul></td>"
		else:
			htmlOutputContent += "<td></td>"


		htmlOutputContent += "</tr>"

	
	result = htmlOutputHead + htmlOutputContent + htmlOutputFoot
	log(3, "our result: " + result)
	#write summary to bin directory
	try:
		f = open(HtmlTablePath, "w")
		try:
			f.write(str(result))
		finally:
			f.close()
	except IOError:
		pass


#read some command line arguments
parser = OptionParser()
parser.add_option("-l", "--loglevel", dest="logLevel", default=1, type=int, help="define loglevel")
parser.add_option("-g", "--geojson", dest="geoJSON", default=False, action="store_true", help="Output format: geoJSON")
parser.add_option("-t", "--tableHtml", dest="tableHtml", default=False, action="store_true", help="Output format: HTML table")
(options, args) = parser.parse_args()


proxy_handler = ProxyHandler({})
opener = build_opener(proxy_handler)
install_opener(opener)

#load config
loadConfig()

#first step: load directory
ffDirectory = loadDirectory(ffDirUrl) 
log(4, "our directory: " + str(ffDirectory))

#second step: write all information to a summarized json
summarizedJson(ffDirectory, ffSummarizedJson)

#now create all other formats
#open summary file
try:
	summaryFile = open(ffSummarizedJson)
	summary = json.loads(summaryFile.read())
	summaryFile.close()
except IOError as e:
	log(0, "error working on summary file " +str(e))
	 
if options.geoJSON:
	geoJson(summary, ffGeoJson)

if options.tableHtml:
	tableHtml(summary, ffHtmlTable)
