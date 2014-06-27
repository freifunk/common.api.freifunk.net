==What it does==
provides the calendar feeds found in Freifunk API files either as xml sitemap or as structured json output. 

==How it works==
# place calcollect.php on your web server
# adjust $commuities to your aggregated api file
# maybe add some static feeds to the array
# call http://yourserver.tld/yourpath/calcollect.php to get the xml sitemap or use calcollect.php?format=json to get the json output
