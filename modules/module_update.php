<?php

if (!defined('IN_ALERT_NOTIFIER')) {
	exit;
}

header('Content-Type: text/plain');

$force_processing = array_key_exists('force', $_GET);

// Error handling
$now = new DateTime();
$log_file = 'logs/execution_' . $now->format('Y-m-d\TH-i-s_v') . '.log';
function archive_and_echo_error($errno, $errstr, $errfile, $errline) {
	info($errstr . ' in ' . substr($errfile, strrpos($errfile, '/') + 1, -4) . ' at line ' . $errline);
	return true;
}
function info($message) {
	global $log_file;
	echo $message . PHP_EOL;
	file_put_contents($log_file, date('c') . ': ' . $message . PHP_EOL, FILE_APPEND);
}
set_error_handler('archive_and_echo_error');

// Remove old executions that don't have any connections of still-open alerts
$archive_stmt = $db->prepare('SELECT ex.id as id, ex.log_file as log_file, timestamp FROM `alert-notifier_executions` ex WHERE ex.result != "NOTIFICATION_SENT" AND ex.timestamp < ? AND NOT EXISTS (SELECT "X" FROM `alert-notifier_alerts` a WHERE a.end_execution IS NULL AND a.start_execution = ex.id)');
$result = $archive_stmt->execute(array(time() - 60*60*24*3)); // 3 days
if (!$result) {
	info('Failed to retrieve alerts to archive: ' . implode($archive_stmt->errorInfo(), ' - '));
}
$delete_execution_stmt = $db->prepare('DELETE FROM `alert-notifier_executions` WHERE id = ?');
$remove_log_file_stmt = $db->prepare('UPDATE `alert-notifier_executions` SET log_file = NULL WHERE id = ?');
$executions_to_archive = $archive_stmt->fetchAll(PDO::FETCH_CLASS);
foreach ($executions_to_archive as $execution_to_archive) {
	if ($execution_to_archive->log_file != null && !substr($execution_to_archive->log_file, 0, 4) != 'logs' && strpos($execution_to_archive->log_file, '..') === false && file_exists($execution_to_archive->log_file)) { // sanity check
		unlink($execution_to_archive->log_file);
	}
	// Remove executions older than 30 days, otherwise just update the removal of the log file
	if ($execution_to_archive->timestamp < time() - 60*60*24*30) {
		$delete_execution_stmt->execute(array($execution_to_archive->id));
	} elseif ($execution_to_archive->log_file != null) {
		$remove_log_file_stmt->execute(array($execution_to_archive->id));
	}
}

// Go over all active partners
$stmt = $db->prepare('SELECT id, name, access_token, ST_AsText(area) AS area, map_image_path, map_image_left, map_image_right, map_image_top, map_image_bottom FROM `alert-notifier_partners` WHERE active = 1');
$result = $stmt->execute();
if (!$result) {
	info('No active partners found');
	die();
}
$partners = $stmt->fetchAll(PDO::FETCH_CLASS);

foreach ($partners as $partner) {
	info('Processing partner ' . $partner->name);

	$stmt = $db->prepare('INSERT INTO `alert-notifier_executions` (partner_id, timestamp, log_file) VALUES (?, ?, ?)');
	$result = $stmt->execute(array($partner->id, time(), $log_file));
	if (!$result) {
		info("Couldn't start new execution job: " . implode($stmt->errorInfo(), ' - '));
		continue;
	}
	$execution_id = $db->lastInsertId();

	$stmt = $db->prepare('SELECT id, name, description, alert_type, ST_AsText(area) AS area, mail_address, min_confidence, min_reliability, min_rating, last_email_timestamp FROM `alert-notifier_rules` rule WHERE active = 1 AND partner_id = ? AND EXISTS (SELECT "X" FROM `alert-notifier_rule_restrictions` WHERE rule.id = rule_id and WEEKDAY(NOW()) * 60*24 + HOUR(NOW()) * 60 + MINUTE(NOW()) BETWEEN start AND end)');
	$result = $stmt->execute(array($partner->id));
	if (!$result) {
		info('Could not retrieve rules from the database for ' . $partner->name . ': ' . implode($stmt->errorInfo(), ' - '));
		update_execution_status($execution_id, 'ERROR', 0);
		continue;
	}
	$rules = $stmt->fetchAll(PDO::FETCH_CLASS);
	if (count($rules) === 0 && !$force_processing) {
		info('Currently no active rules present for partner, skipping alert retrieval');
		update_execution_status($execution_id, 'NO_DATA', 0);
		continue;
	}
	// Replace area with a more usable polygon array
	foreach ($rules as $rule) {
		$polygon = array();
		$area_points = explode(',', str_replace(array('POLYGON((', '))'), array('', ''), $rule->area));
		foreach ($area_points as $point) {
			$polygon[] = explode(' ', $point);
		}
		$rule->area = $polygon;
	}

	// Load currently known alerts in this area
	$stmt = $db->prepare('SELECT LOWER(HEX(uuid)) as uuid, ST_X(location) as x, ST_Y(location) as y, end_execution, max_confidence, max_reliability, max_rating, notification_timestamp FROM `alert-notifier_alerts` WHERE ST_Contains(ST_GeomFromText(?), location)');
	$result = $stmt->execute(array($partner->area));
	if (!$result) {
		info('Could not retrieve existing points from area: ' . implode($stmt->errorInfo(), ' - '));
		update_execution_status($execution_id, 'ERROR', 0);
		continue;
	}
	$known_alerts_raw = $stmt->fetchAll(PDO::FETCH_CLASS);
	$known_alerts = array();
	foreach ($known_alerts_raw as $alert) {
		$known_alerts[$alert->uuid] = $alert;
	}
	info(count($known_alerts) . ' existing alerts found to keep up to date');

	// Retrieve the latest information
	$area = str_replace(array('POLYGON((', '))', ',', ' '), array('', '', ';', ','), $partner->area);
	$url = sprintf('https://world-georss.waze.com/rtserver/web/TGeoRSS?tk=' . WFC_TOKEN . '&format=JSON&types=alerts&polygon=%s', $area);
	info('Retrieving alerts...');
	$h = curl_init($url);
	curl_setopt_array($h, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => false
	));
	$response = json_decode(curl_exec($h));
	if ($response === null) {
		info('Invalid JSON received! (' . json_last_error() . ')');
		update_execution_status($execution_id, 'ERROR', 0);
		continue;
	}
	if (!property_exists($response, 'alerts') || count($response->alerts) === 0) {
		info('Empty response received, ignoring for safety reasons');
		update_execution_status($execution_id, 'NO_DATA', 0);
		continue;
	}
	if (count($response->alerts) > 1000) {
		info('More than 1000 alerts found, ignoring as this seems fishy');
		update_execution_status($execution_id, 'ERROR', 0);
		continue;
	}
	foreach ($response->alerts as $alert) {
		// Normalize UUID
		$alert->uuid = strtolower(str_replace('-', '', $alert->uuid));
		// Normalize alert type
		$type = $alert->type;
		if ($alert->subtype != '' && $alert->subtype != NULL && $alert->subtype != 'NO_SUBTYPE') {
			$type = $alert->subtype;
		}
		$alert->type = $type;
		unset($alert->subtype);
	}
	info(count($response->alerts) . ' alerts retrieved.');

	// Calculate diffs (added, updated, deleted)
	$new_alerts = array();
	$updated_alerts = array();
	$matched_uuids = array();
	$close_alert_uuids = array();

	foreach ($response->alerts as $new_alert) {
		if (array_key_exists($new_alert->uuid, $known_alerts)) {
			$matched_uuids[] = $new_alert->uuid;
			$matched_alert = $known_alerts[$new_alert->uuid];
			if ($matched_alert->max_confidence < $new_alert->confidence || $matched_alert->max_reliability < $new_alert->reliability || $matched_alert->max_rating < $new_alert->reportRating) {
				$updated_alerts[] = $new_alert;
			}
		} else {
			$new_alerts[$new_alert->uuid] = $new_alert;
		}
	}
	foreach ($known_alerts as $known_alert) {
		if ($known_alert->end_execution == NULL && !in_array($known_alert->uuid, $matched_uuids)) {
			$close_alert_uuids[] = $known_alert->uuid;
		}
	}

	// Process rules on diffs
	$notifications = array();
	foreach ($rules as $rule) {
		foreach($new_alerts as $new_alert) {
			if (notification_matches($new_alert, $rule)) {
				if ($rule->last_email_timestamp != null && $rule->last_email_timestamp > time() - 60*20) {
					info('Found a new alert that would trigger a notification, but ignoring it as the previous mail for this rule was sent less than 20 minutes ago');
				} else {
					info('Found a new alert that triggers a notification:' . PHP_EOL . print_r($new_alert, true));
					if (!array_key_exists($rule->mail_address, $notifications)) {
						$notifications[$rule->mail_address] = array();
					}
					$new_alert->rule = $rule;
					$notifications[$rule->mail_address][] = $new_alert;
				}
			}
		}
		// Go over updated alerts as well in case one of them now matches the treshold
		foreach ($updated_alerts as $updated_alert) {
			if ($known_alerts[$updated_alert->uuid]->notification_timestamp == NULL && notification_matches($updated_alert, $rule)) {
				info('Found a modified alert that triggers a notification:' . PHP_EOL . print_r($updated_alert, true));
				if (!array_key_exists($rule->mail_address, $notifications)) {
					$notifications[$rule->mail_address] = array();
				}
				$updated_alert->rule = $rule;
				$notifications[$rule->mail_address][] = $updated_alert;
			}
		}
	}
	info('Notifications: ' . count($notifications));
	info('New alerts: ' . count($new_alerts));
	info('Updated alerts: ' . count($updated_alerts));
	info('Alerts to close: ' . count($close_alert_uuids));

	// Persist changes in database
	// > New alerts
	$insert_stmt = $db->prepare("INSERT INTO `alert-notifier_alerts` (uuid, location, start_execution, type, max_confidence, max_reliability, max_rating, notification_timestamp) VALUES (UNHEX(?), GeomFromText(?), ?, ?, ?, ?, ?, ?)");
	foreach ($new_alerts as $alert) {
		$result = $insert_stmt->execute(array($alert->uuid, 'POINT(' . $alert->location->x . ' ' . $alert->location->y . ')', $execution_id, $alert->type, $alert->confidence, $alert->reliability, $alert->reportRating, NULL));
		if (!$result) {
			info('Failed to insert alert: ' . implode($insert_stmt->errorInfo(), ' - '));
		}
	}
	// > Updated alerts
	$update_stmt = $db->prepare("UPDATE `alert-notifier_alerts` SET max_confidence = ?, max_reliability = ?, max_rating = ? WHERE uuid = UNHEX(?)");
	foreach ($updated_alerts as $updated_alert) {
		$old_alert = $known_alerts[$updated_alert->uuid];
		$result = $update_stmt->execute(array(max($old_alert->max_confidence, $updated_alert->confidence), max($old_alert->max_reliability, $updated_alert->reliability), max($old_alert->max_rating, $updated_alert->reportRating), $updated_alert->uuid));
		if (!$result) {
			info('Failed to update alert: ' . implode($update_stmt->errorInfo(), ' - '));
		}
	}
	// > Close alerts
	$close_stmt = $db->prepare("UPDATE `alert-notifier_alerts` SET end_execution = ? WHERE uuid = UNHEX(?)");
	foreach ($close_alert_uuids as $close_alert_uuid) {
		$result = $close_stmt->execute(array($execution_id, $close_alert_uuid));
		if (!$result) {
			info('Failed to close alert: ' . implode($close_stmt->errorInfo(), ' - '));
		}
	}
	// > Notified alerts
	$notified_alert_stmt = $db->prepare("UPDATE `alert-notifier_alerts` SET notification_timestamp = ? WHERE uuid = UNHEX(?)");
	$notified_rule_stmt = $db->prepare("UPDATE `alert-notifier_rules` SET last_email_timestamp = ? WHERE id = ?");
	foreach ($notifications as $mail_address => $alerts) {
		if (send_notification($mail_address, $alerts, $partner)) {
			$rule_ids = array();
			foreach ($alerts as $notification_alert) {
				$rule_ids[$notification_alert->rule->id] = 1;
				$result = $notified_alert_stmt->execute(array(time(), $notification_alert->uuid));
				if (!$result) {
					info('Failed to set notification timestamp on alert: ' . implode($notified_alert_stmt->errorInfo(), ' - '));
				}
			}
			foreach (array_keys($rule_ids) as $rule_id) {
				$result = $notified_rule_stmt->execute(array(time(), $rule_id));
				if (!$result) {
					info('Failed to set last notification timestamp on rule ' . $rule_id . ': ' . implode($notified_rule_stmt->errorInfo(), ' - '));
				}
			}
		} else {
			info('Failed to send notification mail: ' . error_get_last()['message']);
		}
	}

	update_execution_status($execution_id, (count($notifications) > 0 ? 'NOTIFICATION_SENT' : 'DATA_FOUND'), count($response->alerts));
}
info('Execution completed');

function update_execution_status($execution_id, $result, $result_count) {
	global $db;
	$stmt = $db->prepare('UPDATE `alert-notifier_executions` SET result = ?, result_count = ? WHERE id = ?');
	$result = $stmt->execute(array($result, $result_count, $execution_id));
}

function notification_matches($alert, $rule) {
	$point = array($alert->location->x, $alert->location->y);
	return $alert->type == $rule->alert_type && $alert->confidence >= $rule->min_confidence && $alert->reliability >= $rule->min_reliability && $alert->reportRating >= $rule->min_rating && contains_point($rule->area, $point);
}

function contains_point($polygon, $point) {
	if ($polygon[0] != $polygon[count($polygon)-1]) {
		$polygon[count($polygon)] = $polygon[0];
	}
	$j = 0;
	$oddNodes = false;
	$x = $point[1];
	$y = $point[0];
	$n = count($polygon);
	for ($i = 0; $i < $n; $i++) {
		$j++;
		if ($j == $n) {
			$j = 0;
		}
		if ((($polygon[$i][0] < $y) && ($polygon[$j][0] >= $y)) || (($polygon[$j][0] < $y) && ($polygon[$i][0] >= $y))) {
			if ($polygon[$i][1] + ($y - $polygon[$i][0]) / ($polygon[$j][0] - $polygon[$i][0]) * ($polygon[$j][1] - $polygon[$i][1]) < $x) {
				$oddNodes = !$oddNodes;
			}
		}
	}
	return $oddNodes;
}

function send_notification($mail_address, $alerts, $partner) {
	define('MAIL_EOL', "\r\n");
	setlocale(LC_TIME, 'nl_BE');
	$boundary = '--part-' . md5(date('r', time()));

	$headers = 'MIME-Version: 1.0' . MAIL_EOL;
	$headers .= 'From: <' . SEND_ADDRESS . '>' . MAIL_EOL;
	$headers .= 'Content-Type: multipart/related; boundary=' . substr($boundary, 2) . MAIL_EOL;

	$subject = 'Waze Map Alert Notifier Service: ' . count($alerts) . ' new alert' . (count($alerts) > 1 ? 's' : '');

	$images = array();

	$message_header = 'Content-Type: text/html; charset=utf-8' . MAIL_EOL;
	$message_header .= 'Content-Transfer-Encoding: quoted-printable' . MAIL_EOL . MAIL_EOL;
	$message_body = '<html><body><h1 style="font-size:16pt">' . count($alerts) . ' new alert' . (count($alerts) > 1 ? 's' : '') . '</h1>';
	$message_body .= '<p>Overview of new alerts:</p>';
	foreach ($alerts as $alert) {
		$message_body .= '<p><strong>' . htmlentities($alert->rule->name) . '</strong></p>';
		$message_body .= '<p>' . htmlentities($alert->rule->description) . '</p>';
		$message_body .= '<p>This alert was published at Waze on '  . strftime('%A %e %B %G om %R', $alert->pubMillis / 1000) . '</p>';
		$message_body .= '<p><a href="https://www.waze.com/en/live-map/directions?latlng=' . $alert->location->y . ',' . $alert->location->x . '&overlay=false">Link to the location<br /><br /><img src="cid:map_' . $alert->uuid . '" /></a></p>';
		$images[$alert->uuid] = chunk_split(base64_encode(get_image($alert->location->x, $alert->location->y, $partner)));
	}
	$message_body .= '</body></html>' . MAIL_EOL . MAIL_EOL;

	$message = $boundary . MAIL_EOL;
	$message .= $message_header;
	$message .= imap_8bit($message_body);
	$message .= $boundary;
	foreach ($images as $uuid => $image_body) {
		$message .= MAIL_EOL . get_image_headers($uuid);
		$message .= $image_body;
		$message .= $boundary;
	}
	$message .= '--' . MAIL_EOL;

	return mail($mail_address, $subject, $message, $headers);
}

function get_image($lon, $lat, $partner) {
	$lon = floatval($lon);
	$lat = floatval($lat);
	if ($lon < $partner->map_image_left || $lon > $partner->map_image_right || $lat < $partner->map_image_bottom || $lat > $partner->map_image_top) {
		return get_error('Out of bounds location requested');
	}

	$map = imagecreatefrompng($partner->map_image_path);
	$map_width = imagesx($map);
	$map_height = imagesy($map);
	$pixel_per_degree_x = $map_width / ($partner->map_image_right - $partner->map_image_left);
	$pixel_per_degree_y = $map_height / ($partner->map_image_top - $partner->map_image_bottom);

	$x_pin = round(abs($lon - $partner->map_image_left) * $pixel_per_degree_x);
	$y_pin = round(abs($lat - $partner->map_image_top) * $pixel_per_degree_y);
	$crop_left = min($map_width - 500, max(0, $x_pin - 250));
	$crop_top = min($map_height - 300, max(0, $y_pin - 150));

	$selection = imagecrop($map, array('x' => $crop_left, 'y' => $crop_top, 'width' => 500, 'height' => 300));
	imagedestroy($map);

	$pin = imagecreatefrompng('images/pin.png');
	$pin_width = imagesx($pin);
	$pin_height = imagesy($pin);
	imagecopy($selection, $pin, $x_pin - $crop_left - ($pin_width / 2), $y_pin - $crop_top - ($pin_height - 9), 0, 0, $pin_width, $pin_height);
	ob_start();
	imagepng($selection);
	$image_data = ob_get_contents();
	ob_end_clean();
	return $image_data;
}

function get_image_headers($uuid) {
	$image_header = 'Content-Type: image/png' . MAIL_EOL;
	$image_header .= 'Content-ID: <map_' . $uuid . '>' . MAIL_EOL;
	$image_header .= 'Content-Transfer-Encoding: base64' . MAIL_EOL;
	$image_header .= 'Content-Disposition: inline; filename="map_' . $uuid . '.png"' . MAIL_EOL . MAIL_EOL;
	return $image_header;
}

function get_error($text) {
	$error_image = imagecreatetruecolor(500, 300);
	$white = imagecolorallocate($error_image, 255, 255, 255);
	imagestring($error_image, 4, 30, 30, $text, $white);
	ob_start();
	imagepng($error_image);
	$error_data = ob_get_contents();
	ob_end_clean();
	return $error_data;
}

?>