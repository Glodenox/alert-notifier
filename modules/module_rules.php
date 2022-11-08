<?php

if (!defined('IN_ALERT_NOTIFIER')) {
	exit;
}

$partner = $_SESSION['partner'];
$stylesheets[] = '/css/ol.css';

if (count($folders) > 1) {
	$id = count($folders) >= 3 ? (int)$folders[2] : null;
	$action = $folders[1];

	if ($action == '') { // Added "/" at the end of the URL
		header('Location: /rules', 301);
		exit;
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		// Check values
		$required_fields_ok = array_reduce(array('ruleName', 'ruleDescription', 'ruleMail', 'alertType', 'ruleArea'), function($carry, $field) {
			return $carry && trim($_POST[$field]) != '';
		}, true);
		if (!$required_fields_ok) {
			json_fail('Een of meer verplichte velden is niet ingevuld');
		}
		if (preg_match('/^POLYGON\(\((?>\d+\.\d+ \d+\.\d+,)+(?>\d+\.\d+ \d+\.\d+)\)\)$/', $_POST['ruleArea'] !== 1)) {
			json_fail('Ongeldige polygoon ontvangen voor het gebied van deze regel');
		}
		if (!in_array($_POST['ruleActive'], array('active', 'inactive'))) {
			json_fail('Ongeldige waarde ontvangen voor de status van de regel');
		}
		foreach(array('minConfidence', 'minReliability', 'minRating') as $field) {
			if (preg_match('/^\d+$/', $_POST[$field]) !== 1) {
				json_fail('Ongeldige waarde ontvangen voor de voorwaarden in de regel');
			}
		}
		$periods_found = array_key_exists('ruleRestrictionStartDay', $_POST);
		$periods_count_ok = array_reduce(array('ruleRestrictionEndDay', 'ruleRestrictionStart', 'ruleRestrictionEnd'), function($carry, $field) use ($periods_found) {
			return $carry && array_key_exists($field, $_POST) == $periods_found;
		}, true);
		if (!$periods_count_ok) {
			json_fail('Geen actieve periodes ingesteld');
		}
		if ($periods_found) {
			$periods_count = count($_POST['ruleRestrictionStartDay']);
			if ($periods_count != count($_POST['ruleRestrictionEndDay']) ||
				$periods_count != count($_POST['ruleRestrictionStart']) ||
				$periods_count != count($_POST['ruleRestrictionEnd'])) {
				json_fail('Fout in het aantal opgegeven actieve periodes');
			}
			foreach ($_POST['ruleRestrictionStartDay'] as $field) {
				if (preg_match('/^\d+$/', $field) !== 1) {
					json_fail('Ongeldige dag ontvangen in een van de periodes');
				}
			}
			foreach ($_POST['ruleRestrictionEndDay'] as $field) {
				if (preg_match('/^\d+$/', $field) !== 1) {
					json_fail('Ongeldige dag ontvangen in een van de periodes');
				}
			}
			foreach ($_POST['ruleRestrictionStart'] as $field) {
				if (preg_match('/^\d{2}:\d{2}$/', $field) !== 1) {
					json_fail('Ongeldige tijd ontvangen in een van de periodes');
				}
			}
			foreach ($_POST['ruleRestrictionEnd'] as $field) {
				if (preg_match('/^\d{2}:\d{2}$/', $field) !== 1) {
					json_fail('Ongeldige tijd ontvangen in een van de periodes');
				}
			}
		}
		// Store values
		$rule_values = array(
			'partner_id' => $partner->id,
			'name' => $_POST['ruleName'],
			'description' => $_POST['ruleDescription'],
			'active' => intval($_POST['ruleActive'] == 'active'),
			'area' => $_POST['ruleArea'],
			'alert_type' => $_POST['alertType'],
			'mail_address' => $_POST['ruleMail'],
			'min_confidence' => $_POST['minConfidence'],
			'min_reliability' => $_POST['minReliability'],
			'min_rating' => $_POST['minRating']
		);
		$restrictions = array();
		foreach($_POST['ruleRestrictionStartDay'] as $idx => $restrictionStartDay) {
			$restrictions[] = array(
				'start' => $restrictionStartDay * 60*24 + time_to_minutes($_POST['ruleRestrictionStart'][$idx]),
				'end' => $_POST['ruleRestrictionEndDay'][$idx] * 60*24 + time_to_minutes($_POST['ruleRestrictionEnd'][$idx])
			);
		}
		$insert_restriction_stmt = $db->prepare('INSERT INTO `alert-notifier_rule_restrictions` (rule_id, start, end) VALUES (:id, :start, :end)');
		if ($id == null) {
			$insert_stmt = $db->prepare('INSERT INTO `alert-notifier_rules` (partner_id, name, description, active, area, alert_type, mail_address, min_confidence, min_reliability, min_rating) VALUES (:partner_id, :name, :description, :active, ST_GeomFromText(:area), :alert_type, :mail_address, :min_confidence, :min_reliability, :min_rating)');
			$result = $insert_stmt->execute($rule_values);
			if ($result) {
				if ($insert_stmt->rowCount() != 1) {
					json_fail('Geen data opslagen in databank om onduidelijke reden');
				}
				$new_rule_id = $db->lastInsertId();
				foreach($restrictions as $restriction) {
					$restriction['id'] = $new_rule_id;
					$insert_restriction_stmt->execute($restriction);
				}
				json_send(array(
					'newId' => $new_rule_id
				));
			} else {
				json_fail('Kon data niet opslaan in database: ' . (strlen($insert_stmt->errorInfo()[2]) > 0 ? $insert_stmt->errorInfo()[2] : $insert_stmt->errorInfo()[1]));
			}
		} else {
			$rule_values['id'] = $id;
			unset($rule_values['partner_id']);
			$update_stmt = $db->prepare('UPDATE `alert-notifier_rules` SET name = :name, description = :description, active = :active, area = ST_GeomFromText(:area), alert_type = :alert_type, mail_address = :mail_address, min_confidence = :min_confidence, min_reliability = :min_reliability, min_rating = :min_rating WHERE id = :id');
			$result = $update_stmt->execute($rule_values);
			if ($result) {
				// Delete existing rules (no matter whether something changed or not)
				$delete_restrictions_stmt = $db->prepare('DELETE FROM `alert-notifier_rule_restrictions` WHERE rule_id = ?');
				$delete_restrictions_stmt->execute(array($id));
				// Replace with new rules
				foreach($restrictions as $restriction) {
					$restriction['id'] = $id;
					$insert_restriction_stmt->execute($restriction);
				}
				json_send();
			} else {
				json_fail('Kon data niet opslaan in database: ' . (strlen($update_stmt->errorInfo()[2]) > 0 ? $update_stmt->errorInfo()[2] : $update_stmt->errorInfo()[1]));
			}
		}
	}

	$known_actions = array('new', 'edit');
	if (!in_array($action, $known_actions)) {
		$error_msg = 'Ongekende actie gevraagd';
		require('templates/template_500.php');
		exit;
	}

	$stmt = $db->prepare('SELECT DISTINCT type FROM `alert-notifier_alerts` ORDER BY 1 ASC');
	$stmt->execute(array($id));
	$alert_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

	if ($action == 'new') {
		$rule = new stdClass();
		$rule->id = null;
		$rule->name = '';
		$rule->description = '';
		$rule->active = true;
		$rule->polygon = $_SESSION['partner']->area;
		$rule->alert_type = '';
		$rule->mail_address = $_SESSION['partner']->contact_address;
		$rule->restrictions = array();
		// TODO: add some default time restrictions?
	} elseif ($action == 'edit') {
		$stmt = $db->prepare('SELECT id, name, description, active, ST_AsText(area) as area, alert_type, mail_address, min_confidence, min_reliability, min_rating FROM `alert-notifier_rules` WHERE partner_id = ? AND id = ?');
		$stmt->execute(array($partner->id, $id));
		$rule = $stmt->fetchObject();
		$rule->polygon = array();
		$area_points = explode(',', str_replace(array('POLYGON((', '))'), array('', ''), $rule->area));
		foreach ($area_points as $point) {
			$rule->polygon[] = explode(' ', $point);
		}

		$stmt = $db->prepare('SELECT rule_id, start, end FROM `alert-notifier_rule_restrictions` WHERE rule_id = ? ORDER BY start ASC');
		$stmt->execute(array($id));
		$rule->restrictions = $stmt->fetchAll(PDO::FETCH_OBJ);
	}

	require('templates/template_rules_edit.php');
} else {
	$stmt = $db->prepare('SELECT id, name, description, active, ST_AsText(area) as area, alert_type, mail_address FROM `alert-notifier_rules` WHERE partner_id = ?');
	$stmt->execute(array($partner->id));
	$rules = $stmt->fetchAll(PDO::FETCH_CLASS);

	$rule_by_id = array();
	foreach ($rules as $rule) {
		$polygon = array();
		$area_points = explode(',', str_replace(array('POLYGON((', '))'), array('', ''), $rule->area));
		foreach ($area_points as $point) {
			$polygon[] = explode(' ', $point);
		}
		$rule->area = $polygon;
		$rule->restrictions = array();
		$rule_by_id[$rule->id] = $rule;
	}

	// TODO: if we ever get more than one partner, consider limiting the restrictions by filtering on the partner in the query
	$stmt = $db->prepare('SELECT rule_id, start, end FROM `alert-notifier_rule_restrictions` ORDER BY start ASC');
	$stmt->execute();
	while ($restriction = $stmt->fetch(PDO::FETCH_OBJ)) {
		if (array_key_exists($restriction->rule_id, $rule_by_id)) {
			$rule_by_id[$restriction->rule_id]->restrictions[] = $restriction;
		}
	}

	$point_x_sum = $point_y_sum= 0;
	foreach ($partner->area as $point) {
		$point_x_sum += $point[0];
		$point_y_sum += $point[1];
	}
	$average_point = array($point_x_sum / count($partner->area), $point_y_sum / count($partner->area));

	require('templates/template_rules.php');
}

function json_encode_safe($value, $options = 0, $depth = 512) {
	// Deal with PHP bug surrounding precision of floats in json_encode: https://stackoverflow.com/questions/42981409/php7-1-json-encode-float-issue
	// Also limit the precision to 6 while we're at it
	return preg_replace('/(\.[0-9]{6})[0-9]+/', '\1', json_encode($value, $options, $depth));
}

function time_to_minutes($time) {
	$timeparts = explode(':', $time);
	return intval($timeparts[0]) * 60 + intval($timeparts[1]);
}

function json_fail($error_message) {
	header($_SERVER["SERVER_PROTOCOL"] . ' 400 Invalid Request', true, 400); 
	echo json_encode_safe(array(
		'ok' => false,
		'error' => $error_message
	), JSON_NUMERIC_CHECK);
	exit;
}

function json_send($obj = null) {
	global $code_errors;
	if (count($code_errors) > 0) {
		json_fail(count($code_errors) == 1 ? $code_errors[0] : $code_errors);
	} else if ($obj === null) {
		echo json_encode_safe(array(
			'ok' => true
		), JSON_NUMERIC_CHECK);
	} else {
		echo json_encode_safe(array_merge(array('ok' => true), $obj), JSON_NUMERIC_CHECK);
	}
	exit;
}

?>