<?php
require '../lib/ics-merger.php';

$merger = new IcsMerger();
$merger->add(file_get_contents('../data/freifunk_0'));
$merger->add(file_get_contents('../data/freifunk_3'));
$result = $merger->getResult();
echo '<pre>';
var_dump($result);
echo '</pre>';

echo IcsMerger::getRawText($result);
