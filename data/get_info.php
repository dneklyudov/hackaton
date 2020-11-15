<?php

	$user_id = 1;

	$now = gmdate("D, d M Y H:i:s");
	header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
	header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
	header("Last-Modified: {$now} GMT");
 
	if (!isset($_POST['id'])) {
		print 'Ошибка';
	}
	else {
		$parent_id = intval($_POST['id']);
		if ($parent_id > 0) {
		
			require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/mysql/mysql.class.php');
			
			$sql = 'SELECT `address` as `addressOld`, `outaddr` as `addressCorrect`, `state` as `comment` FROM `tbl_addresses`, `tbl_files` WHERE `parent_id` = ' . $parent_id . ' AND `tbl_files`.`id` = `tbl_addresses`.`parent_id` AND `tbl_files`.`id_user` = ' . $user_id . ' ORDER BY `tbl_addresses`.`id`';			
			$stmt = $dbh -> prepare($sql);
			$stmt -> execute();
			$entry = $stmt -> fetch_all_assoc();
			
			$json = json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			
			header('Content-Type: application/json');
			echo $json;
		
		}
	}
?>