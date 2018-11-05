<!DOCTYPE html>
<html>
	<head>
		<title><?php print($this->content->title); ?></title>
		<meta charset='utf-8'>
		<link rel="stylesheet" href="/assets/css/styles.css" media="all" />
		<link rel="icon" type="image/png" href="/assets/images/favicon.png" />
	</head>
	<body>
		<div class="container relative">

			<?php print($this->content->html); ?>

		</div>

		<script src="/assets/js/application.js"></script>
	</body>
</html>