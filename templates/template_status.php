<?php include('templates/template_header.php'); ?>

<h1>Status overview</h1>
<p>Below you can find an overview of the most recent data retrievals.</p>

<!--<h2 class="border-bottom">XX:00</h2>-->
<div style="display:flex; flex-direction:revert">
<?php
foreach($executions as $idx => $execution) {
	if ($idx != 0 && $idx % 30 == 0) {
?>
</div>
<!--<h2 class="border-bottom">XX:00</h2>-->
<div style="display:flex; flex-direction:revert">
<?php
	}
	switch($execution->result) {
		case 'DATA_FOUND':
			$color = 'green';
			break;
		case 'NO_DATA':
			$color = 'gray';
			break;
		case 'NOTIFICATION_SENT':
			$color = 'blue';
			break;
		default:
			$color = 'red';
}
?>
	<div style="height: 2vw; width: 2vw; margin: 0.2vw; background-color: <?=$color?>" title="<?=strftime('%H:%M', $execution->timestamp)?>: <?=$execution->result?>"></div>
<?php } ?>
</div>

<?php include('templates/template_footer.php'); ?>
