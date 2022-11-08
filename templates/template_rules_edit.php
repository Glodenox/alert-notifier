<?php include('templates/template_header.php'); ?>

<?php
setlocale(LC_TIME, 'nl_BE');
?>
<div class="row">
	<form action="/rules/<?=$rule->id == null ? 'new/submit' : 'edit/' . $rule->id?>?<?=$access_token_query_param?>" method="post" id="ruleForm" style="max-height:calc(100vh - 48px); overflow: auto" class="col col-md-12 col-lg-6">
		<input type="hidden" name="ruleArea" id="rule-area" value="<?=$rule->area?>" />
		<div>
			<h1><?=($rule->id == null ? 'New rule' : 'Edit rule')?></h1>
			<div class="mb-3">
				<label for="rule-name" class="form-label">Name</label>
				<input type="text" class="form-control" id="rule-name" name="ruleName" value="<?=htmlspecialchars($rule->name, ENT_QUOTES)?>" />
			</div>
			<div class="mb-3">
				<label for="rule-description" class="form-label">Description</label>
				<textarea class="form-control" id="rule-description" name="ruleDescription" rows="3"><?=htmlspecialchars($rule->description, ENT_QUOTES)?></textarea>
			</div>
			<div class="mb-3">
				<label for="rule-active" class="form-label">Rule status</label>
				<select class="form-select" id="rule-active" name="ruleActive">
					<option value="active"<?=$rule->active ? ' selected' : ''?>>✔ Enabled</option>
					<option value="inactive"<?=$rule->active ? '' : ' selected'?>>❌ Disabled</option>
				</select>
			</div>
			<div class="mb-3">
				<label for="rule-mail" class="form-label">Mail address</label>
				<input type="text" class="form-control" id="rule-mail" name="ruleMail" value="<?=htmlspecialchars($rule->mail_address, ENT_QUOTES)?>" />
			</div>
			<div class="mb-3">
				<label for="rule-alert-type" class="form-label">Alert type</label>
				<select class="form-select" id="rule-alert-type" name="alertType">
<?php	foreach ($alert_types as $alert_type) { ?>
					<option value="<?=$alert_type?>"<?=$alert_type == $rule->alert_type ? ' selected' : ''?>><?=$alert_type?></option>
<?php 	} ?>
				</select>
			</div>
			<div class="mb-3">
				<label for="rule-min-confidence" class="form-label">Minimum confidence needed</label>
				<select class="form-select" id="rule-min-confidence" name="minConfidence">
<?php
		$textual_representation = array(
			0 => 'Lowest',
			1 => 'Low',
			2 => 'Average',
			3 => 'Above average',
			4 => 'High',
			5 => 'Highest'
		);
		foreach ($textual_representation as $i => $text) {
?>
					<option value="<?=$i?>"<?=$rule->min_confidence == $i ? ' selected' : ''?>><?=$text?></option>
<?php	} ?>
				</select>
			</div>
			<div class="mb-3">
				<label for="rule-min-reliability" class="form-label">Minimum reliability needed</label>
				<select class="form-select" id="rule-min-reliability" name="minReliability">
<?php	foreach ($textual_representation as $i => $text) { ?>
					<option value="<?=$i + 5?>"<?=$rule->min_reliability == $i + 5 ? ' selected' : ''?>><?=$text?></option>
<?php	} ?>
				</select>
			</div>
			<div class="mb-3">
				<label for="rule-min-rating" class="form-label">Minimum rating needed</label>
				<select class="form-select" id="rule-min-rating" name="minRating">
<?php	foreach ($textual_representation as $i => $text) { ?>
					<option value="<?=$i?>"<?=$rule->min_rating == $i ? ' selected' : ''?>><?=$text?></option>
<?php	} ?>
				</select>
			</div>
			<div class="mb-3">
				<label class="form-label">Active periods</label>
				<div class="row g-3" id="time-periods">
<?php
		$days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
		foreach ($rule->restrictions as $restriction) { ?>
					<div class="col-md-3">
						<select name="ruleRestrictionStartDay[]" class="form-select">
<?php		foreach ($days as $id => $name) { ?>
							<option value="<?=$id?>"<?=floor($restriction->start / (60*24)) == $id ? ' selected' : ''?>><?=$name?></option>
<?php		} ?>
						</select>
					</div>
					<div class="col-md-2"><input type="time" class="form-control" name="ruleRestrictionStart[]" value="<?=minutes_to_time($restriction->start)?>" /></div>
					<div class="col-md-3">
						<select name="ruleRestrictionEndDay[]" class="form-select">
<?php		foreach ($days as $id => $name) { ?>
							<option value="<?=$id?>"<?=floor($restriction->end / (60*24)) == $id ? ' selected' : ''?>><?=$name?></option>
<?php		} ?>
						</select>
					</div>
					<div class="col-md-2"><input type="time" class="form-control" name="ruleRestrictionEnd[]" value="<?=minutes_to_time($restriction->end)?>" /></div>
					<div class="col-md-2"><button type="button" class="btn btn-danger" name="removePeriod">Delete</button></div>
<?php 	} ?>
				</div>
				<div class="row p-3">
					<button type="button" class="btn btn-secondary" id="add-time-period">Add period</button>
				</div>
			</div>
			<div>
<?php if ($rule->id == null) { ?>
				<button type="submit" class="btn btn-primary mb-3 btn-lg" id="rule-add">Add rule</button>
<?php } else {
		//		<button type="submit" class="btn btn-danger mb-3 btn-lg float-end" id="rule-delete">Regel verwijderen</button>
?>
				<button type="submit" class="btn btn-primary mb-3 btn-lg" id="rule-update">Edit rule</button>
<?php } ?>
			</div>
		</div>
	</form>
	<div id="map" style="background-color:#999; height:calc(100vh - 48px); border-left:2px solid #ced4da" class="col col-md-12 col-lg-6 g-0"></div>
</div>
<div style="display:none" id="time-period-template">
	<div class="col-md-3">
		<select name="ruleRestrictionStartDay[]" class="form-select">
			<option value="0" selected="">Monday</option><option value="1">Tuesday</option><option value="2">Wednesday</option><option value="3">Thursday</option><option value="4">Friday</option><option value="5">Saturday</option><option value="6">Sunday</option>
		</select>
	</div>
	<div class="col-md-2"><input type="time" class="form-control" name="ruleRestrictionStart[]" value="12:00"></div>
	<div class="col-md-3">
		<select name="ruleRestrictionEndDay[]" class="form-select">
			<option value="0" selected="">Monday</option><option value="1">Tuesday</option><option value="2">Wednesday</option><option value="3">Thursday</option><option value="4">Friday</option><option value="5">Saturday</option><option value="6">Sunday</option>
		</select>
	</div>
	<div class="col-md-2"><input type="time" class="form-control" name="ruleRestrictionEnd[]" value="18:00"></div>
	<div class="col-md-2"><button type="button" class="btn btn-danger" name="removePeriod">Delete</button></div>
</div>
<script src="/js/ol.js"></script>
<script>
<?php
$points = array();
foreach($rule->polygon as $point) {
	$points[] = implode(',', $point);
}
?>
var ruleFeature = new ol.Feature({
	geometry: new ol.geom.Polygon([[[<?=implode('],[', $points)?>]]]).transform('EPSG:4326', 'EPSG:3857')
});

var styles = [
	new ol.style.Style({
		stroke: new ol.style.Stroke({
			color: '#ffffff',
			width: 5
		})
	}),
	new ol.style.Style({
		stroke: new ol.style.Stroke({
			color: '#0d6efd',
			width: 3
		}),
		fill: new ol.style.Fill({
			color: 'rgba(13, 110, 253, 0.1)'
		})
	}),
	new ol.style.Style({
		image: new ol.style.Circle({
			radius: 5,
			fill: new ol.style.Fill({
				color: '#0d6efd'
			})
		}),
		geometry: (feature) => new ol.geom.MultiPoint(feature.getGeometry().getCoordinates()[0]) // Retrieve the vertexes as a MultiPoint to style
	})
];
var rulesLayer = new ol.layer.Vector({
	source: new ol.source.Vector({
		features: [ ruleFeature ]
	}),
	style: styles
});

var editInstructions = document.createElement('p');
editInstructions.textContent = 'Delete a point by holding Ctrl, Shift or Alt while clicking';
editInstructions.className = 'ol-unselectable ol-control';
editInstructions.style.top = '0';
editInstructions.style.right = '0';
editInstructions.style.maxWidth = 'calc(100% - 1.3em)';
editInstructions.style.backgroundColor = 'rgba(255,255,255,.8)';
editInstructions.style.borderRadius = '0 4px 0';
var editInstructionsOverlay = new ol.control.Control({
	element: editInstructions
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
	controls: ol.control.defaults().extend([ editInstructionsOverlay ]),
	view: new ol.View({
		center: ol.extent.getCenter(ruleFeature.getGeometry().getExtent()),
		zoom: 16,
		constrainResolution: true
	})
});
map.getView().fit(ruleFeature.getGeometry().getExtent(), {
	padding: [10, 10, 10, 10]
});

var modify = new ol.interaction.Modify({
	features: new ol.Collection([ ruleFeature ]),
	deleteCondition: (ev) => ev.type == 'click' && (ev.originalEvent.altKey || ev.originalEvent.metaKey || ev.originalEvent.ctrlKey || ev.originalEvent.shiftKey)
});
map.addInteraction(modify);

var wktFormat = new ol.format.WKT();
ruleFeature.on('change', () => document.getElementById('rule-area').value = wktFormat.writeFeature(ruleFeature, { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857', decimals: 4 }));
ruleFeature.dispatchEvent('change');

function removeTimePeriod(button) {
	for (var i = 0; i < 4; i++) {
		button.target.parentNode.previousElementSibling.remove();
	}
	button.target.parentNode.remove();
	map.updateSize();
}
document.getElementsByName('removePeriod').forEach((button) => button.addEventListener('click', removeTimePeriod));

document.getElementById('add-time-period').addEventListener('click', () => {
	document.getElementById('time-period-template').childNodes.forEach((templateField) => document.getElementById('time-periods').appendChild(templateField.cloneNode(true)));
	document.getElementById('time-periods').lastElementChild.childNodes[0].addEventListener('click', removeTimePeriod);
	map.updateSize();
});

var ruleForm = document.getElementById('ruleForm');
ruleForm.addEventListener('submit', (e) => {
	e.preventDefault();
	fetch('/rules/<?=($rule->id == null ? "new" : "edit/" . $rule->id) . "?" . $access_token_query_param?>', {
		method: 'post',
		body: new URLSearchParams(new FormData(ruleForm))
	}).then(response => response.json())
	.then(data => {
		console.log('Processed form response', data);
		if (data.ok) {
<?php if ($rule->id == null) { ?>
			window.location = '/rules/edit/' + data.newId + '?<?=$access_token_query_param?>';
<?php } else { ?>
			window.location = '/rules/edit/<?=$rule->id?>?<?=$access_token_query_param?>';
<?php } ?>
		} else {
			alert(data.error);
		}
	});
});
</script>

<?php
function time_to_week_day($time) {
	return strftime('%A %R', $time * 60 + strtotime('last Monday'));
}

function minutes_to_time($minutes) {
	return str_pad(floor($minutes / 60) % 24, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes % 60, 2, '0', STR_PAD_LEFT);
}
?>

<?php include('templates/template_footer.php'); ?>
