<?php include('templates/template_header.php'); ?>

<h1>No Access</h1>
<form action="<?=$_REQUEST['REQUEST_URI']?>" method="get">
	<label for="access-token" class="form-label">Please enter a correct access token:</label>
	<input type="text" required="required" maxlength="20" minlength="20" size="20" name="access_token" id="access_token" value="<?=$_GET['access_token']?>" class="form-control" />
	<button type="submit" class="btn btn-primary">Send</button>
</form>

<?php include('templates/template_footer.php'); ?>