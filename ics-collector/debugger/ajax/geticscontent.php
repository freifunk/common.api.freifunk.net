<?php
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

if (!isAjaxRequest()) {
	echo 'Ajax requests only';
	die;
}

if (!array_key_exists('filename', $_POST)) {
	echo 'Missing parameter : filename';
	die;
}

echo file_get_contents('../data/' . $_POST['filename']);