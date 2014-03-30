#!/bin/sh
     
### VARIABLES ###

#download ics files from api
downloader="./downloadIcsFeeds.py"

# Path to user's ical server directory this is where a folder exists for each calendar.
calpath="./ics"
     
# Where to store combined ics files
# typically this is a a folder in your web server path.
calout="./out"
     
     
### Do not edit below this line ###

$( python3 $downloader -path $calpath/freifunk  )

cals=`ls $calpath | grep -v dropbox | grep -v inbox`
    
TEMP="/tmp/temp.ics"
     
for cal in $cals; do
     
    echo "BEGIN:VCALENDAR
PRODID:-//ccc - common community calendar//freifunk.net//
VERSION:2.0
X-WR-CALNAME:freifunk.net" > $TEMP
     
    awk '/BEGIN:VEVENT/,/END:VEVENT/' $calpath/$cal/*.ics >> $TEMP
     
    echo "END:VCALENDAR" >> $TEMP
     
    tr -d '\r' < $TEMP > $calout/$cal.ics
     
done
     
exit 0
