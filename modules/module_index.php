<?php

if (!defined('IN_ALERT_NOTIFIER')) {
	exit;
}

$stylesheets[] = '/css/ol.css';

$partner = $_SESSION['partner'];
$stmt = $db->prepare('
SELECT LOWER(HEX(alerts.uuid)) as uuid, ST_X(alerts.location) as x, ST_Y(alerts.location) as y, alerts.max_confidence as max_confidence, alerts.max_reliability as max_reliability, alerts.max_rating as max_rating, type as alert_type, alerts.notification_timestamp as notification_timestamp, start_execution.timestamp as start_time
  FROM `alert-notifier_alerts` alerts, `alert-notifier_partners` partner, `alert-notifier_executions` start_execution
  WHERE alerts.start_execution = start_execution.id AND alerts.end_execution IS NULL AND partner.id = ? AND ST_Contains(partner.area, alerts.location)');
$stmt->execute(array($partner->id));
$active_alerts = $stmt->fetchAll(PDO::FETCH_CLASS);

$point_x_sum = $point_y_sum= 0;
foreach ($partner->area as $point) {
	$point_x_sum += $point[0];
	$point_y_sum += $point[1];
}
$average_point = array($point_x_sum / count($partner->area), $point_y_sum / count($partner->area));

$stmt = $db->prepare('
SELECT start_execution.timestamp as start_timestamp, end_execution.timestamp as end_timestamp, alert.max_confidence as max_confidence, alert.max_rating as max_rating, alert.max_reliability as max_reliability
  FROM `alert-notifier_alerts` alert
  LEFT JOIN `alert-notifier_executions` start_execution ON alert.start_execution = start_execution.id
  LEFT JOIN `alert-notifier_executions` end_execution ON alert.end_execution = end_execution.id
  WHERE alert.notification_timestamp IS NOT NULL AND start_execution.partner_id = 1
  ORDER BY 1 DESC LIMIT 5');
$stmt->execute(array($partner->id));
$recent_notifications = $stmt->fetchAll(PDO::FETCH_CLASS);

require('templates/template_index.php');

?>
