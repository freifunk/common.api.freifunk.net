<?php
/**
* usage: call counter.php?count=nodes or counter.php?count=communities or counter.php (counts communities)
*/

$communities = "http://www.weimarnetz.de/ffmap/ffSummarizedDir.json";

//load combined api file
$api = file_get_contents($communities);
$json = json_decode($api, true);

// set the header type
header("Content-type: text/html");

if ($_GET['count'] == "nodes") {
	$nodescounter = 0;
	foreach($json as $community){
		$nodescounter += $community['state']['nodes'];
	}
	echo $nodescounter;
} else {
	echo count($json);
}

?>
