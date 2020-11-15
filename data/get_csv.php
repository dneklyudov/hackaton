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

			array_walk_recursive($entry, "utf8to1251");
			
			$output = fopen('php://output', 'w') or die('Не удалось открыть поток');
			
			// force download
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			
			// disposition / encoding on response body
			header("Content-Transfer-Encoding: binary");
			header('Content-Type: text/csv');
			header('Content-disposition: attachment; filename=data.csv');
			
			$headers = array('№', 'Исходный адрес', 'Обработанный адрес', 'Комментарии');
			array_walk_recursive($headers, "utf8to1251");
			fputcsv($output, $headers, ';');
			
			$i = 1;
			foreach($entry as $item) {
				fputcsv($output, array_merge(array($i++), $item), ';');
			}
			fclose($output);
		}
	}
	function utf8to1251(&$text) {
		$text = iconv("utf-8", "windows-1251", $text);
	}
?>