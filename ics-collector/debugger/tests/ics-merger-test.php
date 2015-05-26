<?php
require '../lib/ics-merger.php';

$merger = new IcsMerger();
$merger->add(file_get_contents('../data/freifunk_0'));
$merger->add(file_get_contents('../data/freifunk_3'));
echo '<pre>';
var_dump($merger->getResult());
echo '</pre>';