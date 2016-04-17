#!/usr/bin/python
# coding: utf-8

import ujson, urllib
from urlgrabber import urlopen
from termcolor import colored

URL="https://raw.githubusercontent.com/freifunk/directory.api.freifunk.net/master/directory.json"
file="directory.json"

try:
	url=urlopen(URL)
	Staat = ujson.loads(url.read())
except:
	print URL , "nicht gefunden - öffne locale Datei..."
	Staat = ujson.loads(open(file,'r').read())

for Ort in Staat:
	if not "evernet" in Ort:
		try:
			Inhalt=urllib.urlopen(Staat[Ort])
			JSON=ujson.loads(Inhalt.read().replace('\r',''))
			Fehler=0
		except:
			print colored("Error in "+Ort+": ","red")
			print Inhalt.read()
			ujson.dumps(Inhalt.read())
			print colored("Error while ujson.loads(Inhalt.read().replace('\r',''))","red")
			Fehler=1
		for Service in JSON:
			if "services" in Service:
				print "____________________________________________________________________________________"
				if Fehler:
					print colored("Ort: "+str(Ort),"red") #,"\tLink",Staat[Ort]
				else:
					print "Ort: ",Ort#,"\tLink",Staat[Ort]
				print "____________________________________________________________________________________"
				for k in JSON[Service]:
					print "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
					for l in k:
						print "-----------------------------------------------------------------"
						print l ,  ":\t" , k[l]


