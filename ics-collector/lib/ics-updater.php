<?php

require (realpath(dirname(__FILE__))  . '/ics-merger.php');

$configs = parse_ini_file(realpath(dirname(__FILE__))  .  '/../api-config.ini', true);
$mergedIcsHeader = $configs['MERGED_ICS_HEADER'];

$summary = file_get_contents($configs['COMPONENT_URL']['ICS_COLLECTOR_URL'] . '?format=json');
$summary = json_decode($summary, true);
$merger = new IcsMerger($configs['MERGED_ICS_HEADER']);
foreach($summary as $key => $value) {
	echo 'Retrieving ics from ' . $key . '..' . PHP_EOL;
	$ics = file_get_contents($value['url']);
	$fp = fopen(realpath(dirname(__FILE__)) . '/../data/' . $key . '.ics' , 'w+');
	fwrite($fp, $ics);
	fclose($fp);
	$merger->add($ics, array($configs['CUSTOM_PROPERTY_NAME']['SOURCE_PROPERTY'] => $key));
}

echo 'Merge all ics files..' . PHP_EOL;
$fp = fopen((realpath(dirname(__FILE__))  . '/../data/ffMerged.ics'), 'w+');
fwrite($fp, IcsMerger::getRawText($merger->getResult()));
fclose($fp);
