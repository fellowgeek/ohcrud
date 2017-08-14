<!DOCTYPE html>
<html>
	<head>
		<title><?php print($this->title); ?></title>
		<meta charset='utf-8'>
		<link rel="stylesheet" href="/assets/css/bootstrap.min.css" />
		<link rel="stylesheet" href="/assets/css/navbar-fixed-side.css" />
		<link rel="stylesheet" href="/assets/css/styles.css" media="all" />
		<link rel="icon" type="image/png" href="/assets/images/favicon.png" />
	</head>
	<body>
		<div class="container-fluid">
			<?php print($this->body); ?>
		</div>

		<script src="/assets/js/jquery.min.js"></script>
		<script src="/assets/js/bootstrap.min.js"></script>
		<script src="/assets/js/application.js"></script>
	</body>
</html>