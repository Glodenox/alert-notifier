<?php

if (!defined('IN_ALERT_NOTIFIER')) {
	exit;
}

$partner = $_SESSION['partner'];
$stmt = $db->prepare('SELECT timestamp, result, result_count FROM `alert-notifier_executions` WHERE partner_id = ? ORDER BY timestamp DESC LIMIT 90');
$stmt->execute(array($partner->id));
$executions = $stmt->fetchAll(PDO::FETCH_CLASS);

require('templates/template_status.php');

?>
