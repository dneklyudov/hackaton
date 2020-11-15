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
			
			// Удаляем сам файл
			$sql = 'SELECT `file` FROM `tbl_files` WHERE `id` = ' . $parent_id . ' AND `id_user` = ' . $user_id;
			$stmt = $dbh -> prepare($sql);
			$stmt -> execute();
			$entry = $stmt -> fetch_assoc();
			if (isset($entry['file']) && $entry['file']) {
				if (file_exists($_SERVER['DOCUMENT_ROOT'] . $entry['file'])) {
					unlink($_SERVER['DOCUMENT_ROOT'] . $entry['file']);
				}

				// Удаляем из таблицы файлов
				$sql = 'DELETE FROM `tbl_files` WHERE `id` = ' . $parent_id;
				$stmt = $dbh -> prepare($sql);
				$stmt -> execute();

				// Удаляем из очереди
				$sql = 'DELETE FROM `tbl_queue` WHERE `parent_id` = ' . $parent_id;
				$stmt = $dbh -> prepare($sql);
				$stmt -> execute();
	
				// Удаляем из таблицы адресов
				$sql = 'DELETE FROM `tbl_addresses` WHERE `parent_id` = ' . $parent_id;
				$stmt = $dbh -> prepare($sql);
				$stmt -> execute();
			
				$result['message'] = 'Файл успешно удален';
			}
		}
	}
	$output = ob_get_contents();
	ob_end_clean();
	$result['error'] = $output;
	print json_encode($result);	
?>