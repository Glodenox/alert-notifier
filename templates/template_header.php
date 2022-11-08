<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>Waze for Cities Alert Notifier</title>
		<link rel="stylesheet" href="/css/bootstrap.min.css" integrity="sha384-giJF6kkoqNQ00vy+HMDP7azOuL0xtbfIcaT9wjKHr8RbDVddVHyTfAAsrekwKmP1" crossorigin="anonymous" />
		<link rel="stylesheet" href="/css/dashboard.css" />
<?php foreach ($stylesheets as $stylesheet) { ?>
		<link rel="stylesheet" href="<?=$stylesheet?>" />
<?php } ?>
	</head>
	<body>
		<header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
			<a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="/?<?=$access_token_query_param?>">Alert Notifier</a>
			<button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>
<?php if (array_key_exists('partner', $_SESSION)) { ?>
			<div class="container-fluid"></div>
			<ul class="navbar-nav px-3">
				<li class="nav-item navbar-text text-nowrap"><?=$_SESSION['partner']->name?></li>
			</ul>
<?php } ?>
		</header>
		<div class="container-fluid">
			<div class="row">
				<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
					<div class="position-sticky pt-3">
						<ul class="nav flex-column">
							<li class="nav-item">
								<a class="nav-link<?=($active_module == 'index' ? ' active' : '')?>" aria-current="page" href="/?<?=$access_token_query_param?>"><div>🏠</div> Overview</a>
							</li>
						</ul>
<?php if (array_key_exists('partner', $_SESSION)) { ?>
						<ul class="nav flex-column">
							<li class="nav-item">
								<a class="nav-link<?=($active_module == 'status' ? ' active' : '')?>" aria-current="page" href="/status?<?=$access_token_query_param?>"><div>🚦</div> Status</a>
							</li>
							<li class="nav-item">
								<a class="nav-link<?=($active_module == 'rules' ? ' active' : '')?>" aria-current="page" href="/rules?<?=$access_token_query_param?>"><div>🚩</div> Rules</a>
							</li>
						</ul>
<?php } ?>
						<ul class="nav flex-column">
							<li class="nav-item">
								<div style="padding:.5rem 1rem">Most recent data:<br /><?=$last_timestamp ? time_elapsed_string(new DateTime('@'.$last_timestamp), new DateTime()) : 'unknown'?></div>
							</li>
						</ul>
					</div>
				</nav>
				<main class="col-md-9 ms-sm-auto col-lg-10">
<?php

// Credit to Glavic at http://stackoverflow.com/questions/1416697/converting-timestamp-to-time-ago-in-php-e-g-1-day-ago-2-days-ago
function time_elapsed_string($from, $to) {
	$diff = $to->diff($from);

	$diff->w = floor($diff->d / 7);
	$diff->d -= $diff->w * 7;

	$units = array(
		'y' => 'year',
		'm' => 'month',
		'w' => 'week',
		'd' => 'day',
		'h' => 'h.',
		'i' => 'min.',
		's' => 'sec.',
	);
	$string = [];
	foreach ($units as $unit => $name) {
		if ($diff->$unit) {
			$string[] = $diff->$unit . ' ' . $name;
		}
	}

	$string = array_slice($string, 0, 2);
	return $string ? implode(', ', $string) . ' ago' : 'just happened';
}

?>