<?php

	$user_id = 1;

	$now = gmdate("D, d M Y H:i:s");
	header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
	header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
	header("Last-Modified: {$now} GMT");

	ob_start();
	$result = array();

	if (!isset($_POST['id'])) {
		print 'Некорректное имя файла';
	}
	else {
		$parent_id = intval($_POST['id']);
		if ($parent_id > 0) {

			require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/mysql/mysql.class.php');
			
			// Ставим файл на паузу
			$sql = 'UPDATE `tbl_files` SET `stopped` = abs(`stopped` - 1) WHERE `id` = ' . $parent_id . ' AND `tbl_files`.`id_user` = ' . $user_id;
			$stmt = $dbh -> prepare($sql);
			$stmt -> execute();

			$result['message'] = 'Статус файла успешно изменен.';
			
		}
	}
	$output = ob_get_contents();
	ob_end_clean();
	$result['error'] = $output;
	print json_encode($result);	
?>