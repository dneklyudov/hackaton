<?php

	$user_id = 1;

	$now = gmdate("D, d M Y H:i:s");
	header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
	header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
	header("Last-Modified: {$now} GMT");
	
	require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/mysql/mysql.class.php');
	
	$sql = 'SELECT `id`, `file` as `name`, `date`, `isready` as `processed`, `stopped` as `pause` FROM `tbl_files` WHERE `isready` = 0 AND `id_user`= ' . $user_id . ' ORDER BY `id` DESC';   
	$stmt = $dbh -> prepare($sql);
	$stmt -> execute();
	$entry = $stmt -> fetch_all_assoc();
	
	foreach ($entry as &$item) {
		$fn = $item['name'];
		$ext = getExtension($fn);
		$ar1 = explode('/', $fn);
		$ar2 = explode('-', $ar1[2]);
		unset($ar2[count($ar2) - 1]);
		unset($ar2[count($ar2) - 1]);
		unset($ar2[count($ar2) - 1]);
		unset($ar2[count($ar2) - 1]);
		$item['link'] = $item['name'];
		$item['name'] = implode('-', $ar2) . '.' . $ext;
		$item['date'] = getHumanDate($item['date']); 
		$item['pause'] = intval($item['pause']);
	}
	unset($item);
	
	$json = json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	
	header('Content-Type: application/json');
	echo $json;
	
	function getExtension($filename) {
		return substr(strrchr($filename, '.'), 1);
	}
	
	function getName($filename) {
		return substr($filename, 0, strrpos($filename, '.'));
	}
	
	function getHumanDate($tmpl_date) {
		$time = substr($tmpl_date, 11);
		if (strlen($time) == 7) {
			$time = '0' . $time;
		}
		return substr($tmpl_date, 8, 2) . '.' . substr($tmpl_date, 5, 2) . '.' . substr($tmpl_date, 0, 4) . ' г. ' . $time;
	}
?>