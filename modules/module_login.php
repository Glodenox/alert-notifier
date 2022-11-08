<?php

if (!defined('IN_ALERT_NOTIFIER')) {
	exit;
}

// If we arrived here with a token, it means we got an invalid login attempt
if (array_key_exists('access_token', $_GET)) {
	// The server will pick up on this HTTP code with fail2ban and will ban these IP addresses for a couple of days
	http_response_code(403);
	require('templates/template_403.php');
} else {
	require('templates/template_login.php');
}

?>