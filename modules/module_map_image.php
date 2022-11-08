<?php

if (!defined('IN_ALERT_NOTIFIER')) {
	exit;
}

header('Content-Type: image/png');

$partner_id = intval($_GET['partner']);
if ($partner_id === 0) {
	show_error('No or invalid partner specified');
}

$stmt = $db->prepare('SELECT map_image_path, map_image_left, map_image_right, map_image_top, map_image_bottom FROM `alert-notifier_partners` WHERE id = ?');
$result = $stmt->execute(array($partner_id));
if (!$result) {
	show_error('Could not retrieve specified partner');
}
$partner = $stmt->fetchObject();

$lon = floatval($_GET['lon']);
$lat = floatval($_GET['lat']);
if ($lon < $partner->map_image_left || $lon > $partner->map_image_right || $lat < $partner->map_image_bottom || $lat > $partner->map_image_top) {
	show_error('Out of bounds location requested');
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
imagepng($selection);

function show_error($text) {
	$error_image = imagecreatetruecolor(500, 300);
	$white = imagecolorallocate($error_image, 255, 255, 255);
	imagestring($error_image, 4, 30, 30, $text, $white);
	imagepng($error_image);
	exit;
}

?>