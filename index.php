<?php

function fin($text, $die = true) {
	$fp = @fopen('log.txt', 'a');
	if ($fp !== false) {
		@fwrite($fp, date('r') . " $text\n");
		fclose($fp);
	}
	if ($die)
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

// If a file called 'input' exists, use that as payload (for debugging).
if (file_exists('input'))
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
	$message = explode("\n", $commit['message'], 2);
	$curl = curl_init('http://is.gd/api.php?longurl=' . urlencode($commit['url']));
	if ($curl === false)
		continue;
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$ret = trim(curl_exec($curl));
	$rcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close($curl);
	if ($rcode != 200)
		continue;
	$text = 'Commit: ' . $message[0] . ' ' . $ret;
	$curl = curl_init('https://twitter.com/statuses/update.json');
	if ($curl === false)
		continue;
	fin('Twittering: ' . $text, false);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, 'status=' . urlencode($text));
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_USERPWD, $user . ':' . $pass);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_exec($curl);
	curl_close($curl);
}


?>
