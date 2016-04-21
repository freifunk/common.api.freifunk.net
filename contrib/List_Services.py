#!/usr/bin/python
# coding: utf-8

import ujson, urllib
from urlgrabber import urlopen
from termcolor import colored

URL="http://api.freifunk.net/data/ffSummarizedDir.json"
file="ffSummarizedDir.json"

try:
	url=urlopen(URL)
	Nation = ujson.loads(url.read())
except:
	print URL , "nicht gefunden - öffne locale Datei..."
	Nation = ujson.loads(open(file,'r').read())


for Ort in Nation:
	if not "evernet" in Ort:
		Fehler=0
		#try:
		#	Inhalt=urllib.urlopen(Staat[Ort])
		#	JSON=ujson.loads(Inhalt.read().replace('\r',''))
		#	Fehler=0
		#except:
		#	print colored("Error in "+Ort+": ","red")
		#	print Inhalt.read()
		#	ujson.dumps(Inhalt.read())
		#	print colored("Error while ujson.loads(Inhalt.read().replace('\r',''))","red")
		#	Fehler=1
		for Service in Nation[Ort]:
			if "services" in Service:
				print "____________________________________________________________________________________"
				if Fehler:
					print colored("Ort: "+str(Ort),"red") #,"\tLink",Nation[Ort]
				else:
					print "Ort: ",Ort#,"\tLink",Nation[Ort]
				print "____________________________________________________________________________________"
				for k in Nation[Ort][Service]:
					print "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
					for l in k:
						print "-----------------------------------------------------------------"
						print l ,  ":\t" , k[l]


