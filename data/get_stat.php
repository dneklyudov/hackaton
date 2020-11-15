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
			
			// Информация о файле (прежде всего имя)
			$sql = 'SELECT `file` FROM `tbl_files` WHERE `id` = ' . $parent_id . ' AND `id_user` = ' . $user_id;
			$stmt = $dbh -> prepare($sql);
			$stmt -> execute();
			$entry_file = $stmt -> fetch_assoc();

			if (isset($entry_file['file']) && $entry_file['file']) {
				$fn = $entry_file['file'];
				$ext = getExtension($fn);
				$ar1 = explode('/', $fn);
				$ar2 = explode('-', $ar1[2]);
				unset($ar2[count($ar2) - 1]);
				unset($ar2[count($ar2) - 1]);
				unset($ar2[count($ar2) - 1]);
				unset($ar2[count($ar2) - 1]);
				$fn = implode('-', $ar2) . '.' . $ext;
	
				// Всего адресов из этого файла
				$sql = 'SELECT COUNT(`id`) as `n` FROM `tbl_addresses` WHERE `parent_id` = ' . $parent_id;
				$stmt = $dbh -> prepare($sql);
				$stmt -> execute();
				$entry_total = $stmt -> fetch_assoc();
				$total = $entry_total['n'];
				
				// Всего удачных адресов из этого файла
				$sql = 'SELECT COUNT(`id`) as `n` FROM `tbl_addresses` WHERE `parent_id` = ' . $parent_id . ' AND `state` = \'Адрес подтвержден\'';
				$stmt = $dbh -> prepare($sql);
				$stmt -> execute();
				$entry_ok = $stmt -> fetch_assoc();
				$ok = $entry_ok['n'];
	
				// Всего проблемных адресов из этого файла
				$sql = 'SELECT COUNT(`id`) as `n` FROM `tbl_addresses` WHERE `parent_id` = ' . $parent_id . ' AND `state` != \'Адрес подтвержден\'';
				$stmt = $dbh -> prepare($sql);
				$stmt -> execute();
				$entry_bad = $stmt -> fetch_assoc();
				$bad = $entry_bad['n'];
	
				$result['message'] = '<p>Проверено ' . $total . ' адресов в файле ' . $fn .  '.</p><p>При проведении проверки обнаружено:</p><ul><li>' . $ok . ' корректных подтвержденных адресов</li><li>' . $bad . ' вызывающих вопросы адресов</li></ul><p>Для получения подробной информации изучите отчеты.</p>';
			}
		}
	}
	$output = ob_get_contents();
	ob_end_clean();
	$result['error'] = $output;
	print json_encode($result);	
	
	function getExtension($filename) {
		return substr(strrchr($filename, '.'), 1);
	}
?>