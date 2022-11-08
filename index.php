<?php
ini_set('display_errors', true);
$code_errors = array();
error_reporting(E_ALL);
function handle_error($errno, $errstr, $errfile, $errline) {
	global $code_errors;
	$code_errors[] = $errstr . ' in ' . substr($errfile, strrpos($errfile, '/')+1, -4) . ' at line ' . $errline;
	return true;
}
set_error_handler('handle_error');
define('IN_ALERT_NOTIFIER', true);

$stylesheets = array();

$path = parse_url('http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ltrim($path, '/');
	$folders = explode('/', $path);
$active_module = str_replace('-', '_', $folders[0]);
if ($active_module == '') {
	$active_module = 'index';
}
if (!is_file("modules/module_{$active_module}.php")) {
	http_response_code(404);
	include('templates/template_404.php');
	exit;
}

require('config.php');

try {
	$db = new PDO(sprintf('mysql:dbname=%s;host=%s;charset=utf8', DB_DATABASE, DB_HOST), DB_USERNAME, DB_PASSWORD);
} catch (PDOException $e) {
	http_response_code(503);
	die('Database connection failed: ' . $e->getMessage());
}
session_start([
	'cookie_lifetime' => 30*24*60*60
]);

// Allow the map_image module to be called without access token
if ($active_module == 'map_image') {
	include('modules/module_map_image.php');
	exit;
}

// Try to verify an access token, if one is provided
if (array_key_exists('access_token', $_GET)) {
	lookup_access_token($_GET['access_token']);
}
if (!array_key_exists('partner', $_SESSION)) {
	require('modules/module_login.php');
} elseif (array_key_exists('partner', $_SESSION)) {
	// Update session data with the database information, just in case
	$stmt = $db->prepare('SELECT id, name, access_token, contact_address, ST_AsText(area) as area FROM `alert-notifier_partners` WHERE id = ?');
	$result = $stmt->execute(array($_SESSION['partner']->id));
	if ($result) {
		$_SESSION['partner'] = parse_partner($stmt->fetchObject());
	}
	$stmt = $db->prepare('SELECT timestamp FROM `alert-notifier_executions` ORDER BY id DESC LIMIT 1');
	$result = $stmt->execute();
	if ($result) {
		$last_timestamp = $stmt->fetchObject()->timestamp;
	}
	$access_token_query_param = 'access_token=' . $_SESSION['partner']->access_token;
	require("modules/module_{$active_module}.php");
}


function lookup_access_token($token) {
	global $db;

	// Check formatting
	if (preg_match('/^[a-zA-Z0-9-_]{20}$/', $token) === 1) {
		// Search for database entry
		$stmt = $db->prepare('SELECT id, name, access_token, contact_address, ST_AsText(area) as area FROM `alert-notifier_partners` WHERE access_token = ?');
		$result = $stmt->execute(array($token));
		if ($result) {
			$_SESSION['partner'] = parse_partner($stmt->fetchObject());
		}
	}
}

function parse_partner($partner) {
	$polygon = array();
	$area_points = explode(',', str_replace(array('POLYGON((', '))'), array('', ''), $partner->area));
	foreach ($area_points as $point) {
		$polygon[] = explode(' ', $point);
	}
	$partner->area = $polygon;
	return $partner;
}