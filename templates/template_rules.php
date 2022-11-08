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
	bottom: 12px;
	min-width: 350px;
}
#popup:after, #popup:before {
	top: 100%;
	left: 48px;
	border: solid transparent;
	content: " ";
	height: 0;
	width: 0;
	position: absolute;
	pointer-events: none;
}
#popup:after {
	border-top-color: white;
	border-width: 10px;
	margin-left: -10px;
}
#popup:before {
	border-top-color: #cccccc;
	border-width: 11px;
	margin-left: -11px;
}
</style>

<h1>Rules for mail notifications</h1>
<div class="container-fluid">
	<div class="row">
		<div class="col col-md-12 col-lg-6">
<?php
	if (!$rules || count($rules) == 0) {
?>
			<p>Currently no rules are configured on this account</p>
<?php
	}
	echo $restriction_count;
	$alertCategories = array(
		ACCIDENT => 'accident',
		ACCIDENT_MINOR => 'accident',
		ACCIDENT_MAJOR => 'accident',
		HAZARD_ON_ROAD_CONSTRUCTION => 'construction',
		HAZARD_ON_SHOULDER_CAR_STOPPED => 'hazard',
		JAM => 'jam',
		JAM_MODERATE_TRAFFIC => 'jam-moderate',
		JAM_HEAVY_TRAFFIC => 'jam-heavy',
		JAM_STAND_STILL_TRAFFIC => 'jam-heavy',
		ROAD_CLOSED_EVENT => 'road-closed',
		CONSTRUCTION => 'construction'
	);
	foreach ($rules as $rule) {
		if (array_key_exists($rule->alert_type, $alertCategories)) {
			$alertCategory = $alertCategories[$rule->alert_type];
?>
			<img src="/images/icons/<?=$alertCategory?>.png" style="float:right"/>
<?php
		}
?>
			
			<h3><a href="/rules/edit/<?=$rule->id?>?<?=$access_token_query_param?>"><?=htmlspecialchars($rule->name)?></a></h3>
			<p><?=htmlspecialchars($rule->description, ENT_QUOTES)?></p>
			<div style="border-left: 5px solid #ddd; padding-left: 10px">
				<p><strong>Alert type:</strong> <?=htmlspecialchars($rule->alert_type, ENT_QUOTES)?></p>
				<p><strong>Mail address:</strong> <?=htmlspecialchars($rule->mail_address, ENT_QUOTES)?></p>
<?php if (count($rule->restrictions) > 0) { ?>
				<ul>
<?php 	foreach($rule->restrictions as $restriction) { ?>
					<li><?=time_to_week_day($restriction->start)?> - <?=time_to_week_day($restriction->end)?></li>
<?php 	} ?>
				</ul>
<?php } ?>
			</div>
<?php
	}
?>
			<a href="/rules/new?<?=$access_token_query_param?>" class="btn btn-primary btn-lg" style="margin:1em">Create new rule</a>
		</div>
		<div class="col col-md-12 col-lg-6 g-0">
			<div id="map" style="height:80vh; background-color:#999"></div>
		</div>
	</div>
</div>
<script src="/js/ol.js"></script>
<script>
var ruleFeatures = [
<?php
foreach ($rules as $rule) {
	$points = array();
	foreach($rule->area as $point) {
		$points[] = implode(',', $point);
	}
	?>
	new ol.Feature({
		geometry: new ol.geom.Polygon([[[<?=implode('],[', $points)?>]]]).transform('EPSG:4326', 'EPSG:3857'),
		name: "<?=htmlspecialchars($rule->name, ENT_QUOTES)?>"
	}),
<?php } ?>
];
var extent;
ruleFeatures.forEach((feature) => {
	if (!extent) {
		extent = feature.getGeometry().getExtent();
	} else {
		ol.extent.extend(extent, feature.getGeometry().getExtent());
	}
});
var rulesLayer = new ol.layer.Vector({
	source: new ol.source.Vector({
		features: ruleFeatures
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
		rulesLayer
	],
	overlays: [ popup ],
	view: new ol.View({
		center: ol.extent.getCenter(extent),
		zoom: 16
	})
});
map.getView().fit(extent);

var select = new ol.interaction.Select({
	condition: ol.events.condition.pointerMove,
	style: null
});
map.addInteraction(select);
select.on('select', (e) => {
	if (e.selected.length > 0) {
		var feature = e.selected[0];
		var html = '<strong>Name:</strong> ' + feature.get('name');
		content.innerHTML = html;
		container.style.display = 'block';
		popup.setPosition(ol.extent.getCenter(feature.getGeometry().getExtent()));
	} else {
		container.style.display = 'none';
	}
});
</script>

<?php

function time_to_week_day($time) {
	setlocale(LC_TIME, 'en');
	return strftime('%A %R', $time * 60 + strtotime('last Monday'));
}

?>

<?php include('templates/template_footer.php'); ?>
