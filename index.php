<?php

function fin($text) {
	$fp = @fopen('log.txt', 'a');
	if ($fp !== false) {
		@fwrite($fp, date('r') . " $text\n");
		fclose($fp);
	}
	die($text);
}

file_put_contents('payload', $_POST['payload']);

// Die if the JSON library is not available.
if (!function_exists('json_decode'))
	fin('JSON library not installed.');

// Die if cURL is not available.
if (!function_exists('curl_init'))
	fin('cURL library not installed.');

$json = @$_POST['payload'];

// FIXME: Remove this.
// For debugging purposes, get the data from a file.
$json = file_get_contents('input');

// Die if there's no payload.
if ($json == null)
	fin('No payload.');

// Remove escapes. This is really spooky.
$data = str_replace(array("\\\"", "\\'", "\\\\/", "\\\\"), array('"', "'", '/', "\\"), $json);

// Decode JSON to associative array.
$data = json_decode($data, true);

// Get project name.
$proj = preg_replace('#^https?://(?:www\.)?github\.com/([^/]+)/([^/]+)/?$#', '$1/$2', $data['repository']['url']);

// Die if it's not recognized.
if (($proj === null) || ($proj == $data['repository']['url']))
	fin('Project name could not be read.');

// Load configuration.
require_once('config.php');

// Die if there's no such project configured.
if (!array_key_exists($proj, $CONF))
	fin('No such project configured.');

// Check if Twitter is configured for this project.
$user = $CONF[$proj]['twitter']['user'];
$pass = $CONF[$proj]['twitter']['pass'];

// Die if Twitter configuration is not complete.
if (($user == null) || ($pass == null))
	fin('Twitter configuration missing.');

foreach ($data['commits'] as $commit) {
	$text = 'Commit: ' . $commit['message'] . ' ' . $commit['url'];
	$curl = curl_init('https://twitter.com/statuses/update.json');
	if ($curl === false)
		continue;
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, 'status=' . urlencode($text));
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_close($curl);
}


?>
