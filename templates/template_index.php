<?php include('templates/template_header.php'); ?>

<style>
#popup {
	position: absolute;
	background-color: white;
	box-shadow: 0 1px 4px rgba(0,0,0,0.2);
	padding: 15px;
	border-radius: 10px;
	border: 1px solid #cccccc;
	left: -50px;
	top: 12px;
	min-width: 350px;
}
#popup:after, #popup:before {
	bottom: 100%;
	left: 48px;
	border: solid transparent;
	content: " ";
	height: 0;
	width: 0;
	position: absolute;
	pointer-events: none;
}
#popup:after {
	border-bottom-color: white;
	border-width: 10px;
	margin-left: -10px;
}
#popup:before {
	border-bottom-color: #cccccc;
	border-width: 11px;
	margin-left: -11px;
}
</style>

<h1>Overview of active alerts in Waze</h1>
<div id="map" class="container-fluid g-0" style="height:50vh; background-color:#999"></div>
<h2>Recent notifications</h2>
<table class="table table-striped">
	<thead>
		<tr>
			<th>Start time</th>
			<th>End time</th>
			<th>Confidence (max)</th>
			<th>Reliability (max)</th>
			<th>Rating (max)</th>
		</tr>
	</thead>
	<tbody>
<?php
$textual_representation = array(
	0 => 'Lowest',
	1 => 'Low',
	2 => 'Average',
	3 => 'Above average',
	4 => 'High',
	5 => 'Highest'
);
foreach($recent_notifications as $recent_notification) { ?>
		<tr>
			<td><?=format_timestamp($recent_notification->start_timestamp)?></td>
			<td><?=format_timestamp($recent_notification->end_timestamp)?></td>
			<td style="background-color: <?=get_color($recent_notification->max_confidence, 0, 5)?>"><?=$textual_representation[$recent_notification->max_confidence]?></td>
			<td style="background-color: <?=get_color($recent_notification->max_reliability, 5, 10)?>"><?=$textual_representation[$recent_notification->max_reliability - 5]?></td>
			<td style="background-color: <?=get_color($recent_notification->max_rating, 0, 5)?>"><?=$textual_representation[$recent_notification->max_rating]?></td>
		</tr>
<?php } ?>
	</tbody>
</table>
<script src="/js/ol.js"></script>
<script>
<?php
$points = array();
foreach($partner->area as $point) {
	$points[] = implode(',', $point);
}
?>
var alerts = [];
<?php foreach ($active_alerts as $alert) { ?>
alerts.push(new ol.Feature({
	geometry: new ol.geom.Point(ol.proj.transform([<?=$alert->x?>, <?=$alert->y?>], 'EPSG:4326', 'EPSG:3857')),
	uuid: '<?=$alert->uuid?>',
	alertType: '<?=$alert->alert_type?>',
	startTime: '<?=$alert->start_time?>',
	notificationTimestamp: <?=($alert->notification_timestamp ? $alert->notification_timestamp : 'null')?>
}));
<?php } ?>

var alertTypeIcons = {
	ACCIDENT: 'accident',
	ACCIDENT_MINOR: 'accident',
	ACCIDENT_MAJOR: 'accident',
	HAZARD_ON_ROAD_CONSTRUCTION: 'construction',
	JAM: 'jam',
	JAM_MODERATE_TRAFFIC: 'jam-moderate',
	JAM_HEAVY_TRAFFIC: 'jam-heavy',
	JAM_STAND_STILL_TRAFFIC: 'jam-heavy',
	ROAD_CLOSED_EVENT: 'road-closed',
	CONSTRUCTION: 'construction'
};
function calculateStyle(feature) {
	var alertIcon = 'hazard';
	if (alertTypeIcons.hasOwnProperty(feature.get('alertType'))) {
		alertIcon = alertTypeIcons[feature.get('alertType')];
	}
	return new ol.style.Style({
		image: new ol.style.Icon({
			anchor: [0.5, 72],
			anchorXUnits: 'fraction',
			anchorYUnits: 'pixels',
			src: '/images/icons/' + alertIcon + '.png'
		})
	});
}

var zonesLayer = new ol.layer.Vector({
	source: new ol.source.Vector({
		features: [
			new ol.Feature({
				geometry: new ol.geom.Polygon([[[<?=implode('],[', $points)?>]]]).transform('EPSG:4326', 'EPSG:3857')
			})
		]
	}),
	style: new ol.style.Style({
		stroke: new ol.style.Stroke({
			color: '#0d6efd',
			width: 3
		}),
		fill: new ol.style.Fill({
			color: 'rgba(13, 110, 253, 0.1)'
		})
	})
});

var alertLayer = new ol.layer.Vector({
	source: new ol.source.Vector({
		features: alerts
	}),
	style: calculateStyle
});

var container = document.createElement('div');
container.id = 'popup';
var content = document.createElement('div');
container.appendChild(content);

var popup = new ol.Overlay({
	element: container
});

var map = new ol.Map({
	target: 'map',
	layers: [
		new ol.layer.Tile({
			source: new ol.source.XYZ({
				attributions: '&copy; 2006-' + (new Date()).getFullYear() + ' <a href="https://www.waze.com/live-map" target="_blank">Waze Mobile Ltd</a>. All Rights Reserved.',
				attributionsCollapsible: false,
				url: 'https://worldtiles{1-4}.waze.com/tiles/{z}/{x}/{y}.png'
			})
		}),
		zonesLayer,
		alertLayer
	],
	overlays: [ popup ],
	view: new ol.View({
		center: ol.proj.fromLonLat([<?=$average_point[0]?>, <?=$average_point[1]?>]),
		zoom: 12,
		constrainResolution: true
	})
});

var select = new ol.interaction.Select({
	condition: ol.events.condition.pointerMove,
	style: null,
	filter: (feature) => feature.getGeometry().getType() == 'Point'
});
map.addInteraction(select);
select.on('select', (e) => {
	if (e.selected.length > 0) {
		var feature = e.selected[0];
		var html = '<strong>Alert Type:</strong> ' + feature.get('alertType') + '<br/>';
		html += '<strong>Start Date:</strong> ' + (new Date(feature.get('startTime') * 1000)).toLocaleString() + '<br/>';
		html += '<strong>Identification:</strong> ' + feature.get('uuid') + '<br/>';
		html += '<strong>Mail sent:</strong> ' + (feature.get('notificationTimestamp') ? (new Date(feature.get('notificationTimestamp') * 1000)).toLocaleString() : 'No');
		content.innerHTML = html;
		container.style.display = 'block';
		popup.setPosition(feature.getGeometry().getCoordinates());
	} else {
		container.style.display = 'none';
	}
});
</script>

<?php include('templates/template_footer.php'); ?>

<?php

function format_timestamp($timestamp) {
	setlocale(LC_TIME, 'en');
	return strftime('%e %B %G, %R', $timestamp);
}

function get_color($value, $min, $max) {
	$classes = array('#f8d7da', '#fbeece', '#fff3cd', '#e8edd5', '#d1e7dd');
	$minmaxed_value = min(max($value, $min), $max);
	$percentage = ($minmaxed_value - $min) / ($max - $min);
	return $classes[min(floor($percentage * count($classes)), count($classes) - 1)];
}

?>
