<!DOCTYPE html>
<html>
	<head>
		<title><?= $this->content->title ?></title>
		<meta charset='utf-8'>
		<?php print($this->content->stylesheet); ?>
		<link rel="stylesheet" href="/assets/css/styles.css" media="all" />

		<link rel="icon" type="image/png" href="/assets/images/favicon.png" />
	</head>
	<body>
		<div class="container relative page">

			<?php if (isset($_SESSION['User']) == true) { ?>
				<?php if (isset($this->request['action']) == true && $this->request['action'] == 'edit') { ?>

					<form id="OhCRUD_File_Upload" enctype="multipart/form-data">
						<input type="file" id="OhCRUD_File" style="display: none;" />
						<input type="submit" id="OhCRUD_File_Submit" style="display: none;" />
					</form>
					<div class="alert hidden"></div>
					<form id="OhCRUD_Page_Editor">
						<input type="hidden" id="OhCRUD_Page_URL" name="OhCRUD_Page_URL" value="<?= $path ?>" />
						<input type="text" id="OhCRUD_Page_Title" name="OhCRUD_Page_Title" value="<?= $this->content->title ?>" />
						<textarea id="OhCRUD_Page_Text" name="OhCRUD_Page_Text"><?= $this->content->text ?></textarea>
						<button type="button" id="OhCRUD_Page_Button_Save">Save</button>
					</form>

				<?php } else { ?>

					<?php if ($this->content->type == 'DB') { ?>
						<a href="<?= $path ?>?action=edit"><img src="/assets/images/ICON_EDIT.png" id="OhCRUD_Page_Button_Action_Edit" /></a>
					<?php } ?>
					<?= $this->content->html ?>

				<?php } ?>

			<?php } else { ?>

				<?php print($this->content->html); ?>

			<?php } ?>

		</div>
		<footer class="footer">
			<p>Oh CRUD! by <a href="https://erfan.me">ERFAN REED</a> - Copyright &copy; <?= date('Y') ?> - All rights reserved. Page generated in <?= microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]; ?> second(s).</p>
		</footer>

		<?php print($this->content->javascript); ?>
		<script src="/assets/js/application.js"></script>

	</body>
</html>