<?php
/**
 * Community ICalendar feeds collector
 *  WHAT IT DOES : 
 *   provides the calendar feeds found in Freifunk API files either as xml sitemap or as structured json output.
 *  HOW IT WORKS :
 *   - place calcollect.php on your web server
 *   - adjust $commuities to your aggregated api file
 *   - maybe add some static feeds to the array
 *   - call http://yourserver.tld/yourpath/calcollect.php to get the xml sitemap or use calcollect.php?format=json to get the json output
 * 
 */
$communities = "./ffSummarizedDir.json";

//load combined api file
$api = file_get_contents($communities);
$json = json_decode($api, true);

// place our feeds in an arrayi
// array structure: [0]: url, [1]: last change date, [2]: community, [3]: city, [4]: community name
$feeds = array(
        array('http://ics.freifunk.net/tags/freifunk-common.ics','2014-12-22', 'gemeinsam', 'Übetall', 'Freifunk.net - Communityübergreifend')
);

$oJson = array();
$oXMLWriter = new XMLWriter();
$oXMLWriter->openMemory();
$oXMLWriter->setIndent(TRUE);
$oXMLWriter->startDocument('1.0', 'UTF-8');
$oXMLWriter->startElement('urlset');
	$oXMLWriter->startAttribute('xmlns');
		$oXMLWriter->text('http://www.sitemaps.org/schemas/sitemap/0.9');
	$oXMLWriter->endAttribute();

	foreach($feeds as $feed)
	{
		$oJson[$feed[2]] = array();
		$oJson[$feed[2]]['url'] = $feed[0];
		$oJson[$feed[2]]['lastchange'] = $feed[1];
		$oJson[$feed[2]]['city'] = $feed[3];
		$oJson[$feed[2]]['communityname'] = $feed[4];
		$oXMLWriter->startElement('url');
			$oXMLWriter->startElement('loc');
				$oXMLWriter->text($feed[0]);
			$oXMLWriter->endElement();
			$oXMLWriter->startElement('lastmod');
				$oXMLWriter->text($feed[1]);
			$oXMLWriter->endElement();
			$oXMLWriter->startElement('changefreq');
				$oXMLWriter->text('daily');
			$oXMLWriter->endElement();
		$oXMLWriter->endElement();
	}	
	foreach($json as $name => $community)
	{
		if ( ! empty($community['feeds']) ) {
			foreach($community['feeds'] as $feed )
			{
				if ($feed['category'] == "ics") {
					$oJson[$name] = array();
					$oJson[$name]['url'] = $feed['url'];
					$oJson[$name]['lastchange'] = $community['mtime'];
					$oJson[$name]['city'] = $community['location']['city'];
					$oJson[$name]['communityname'] = $community['name'];
					$oXMLWriter->startElement('url');
						$oXMLWriter->startElement('loc');
							$oXMLWriter->text($feed['url']);
						$oXMLWriter->endElement();
						$oXMLWriter->startElement('lastmod');
							$oXMLWriter->text(date($community['mtime']));
						$oXMLWriter->endElement();
						$oXMLWriter->startElement('changefreq');
							$oXMLWriter->text('daily');
						$oXMLWriter->endElement();
					$oXMLWriter->endElement();
//					array_push($feeds, array($feed['url'], $community['mtime'], $name, $community['location']['city'], $community['name']));
				}
			}
		}
	}




$oXMLWriter->endElement();

$oXMLWriter->endDocument();
if ( ! empty($_GET["format"] )) {
	$format = $_GET["format"];
}

if ( $format == "json" ) {
	header('Content-type: application/json; charset=UTF-8');
	echo json_encode($oJson);
} else {
	header('Content-type: text/xml; charset=UTF-8');
	echo $oXMLWriter->outputMemory(TRUE);
}
?>
