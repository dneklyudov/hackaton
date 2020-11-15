<?php

	$user_id = 1;

	$now = gmdate("D, d M Y H:i:s");
	header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
	header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
	header("Last-Modified: {$now} GMT");

	ob_start();
	$result = array();

	require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/mysql/mysql.class.php');
			
	$sql = 'SELECT SUM(`q_addresses`) as `n` FROM `tbl_users_addresses` WHERE `id_user` = ' . $user_id . ' AND YEAR(`date`) = ' . date('Y') . '  AND MONTH(`date`) = ' . date('n');
	$stmt = $dbh -> prepare($sql);
	$stmt -> execute();
	$entry = $stmt -> fetch_assoc();
	if (isset($entry['n']) && $entry['n']) {
		$result['message'] = $entry['n'];
	}
	else {
		$result['message'] = '0';
	}

	$output = ob_get_contents();
	ob_end_clean();
	$result['error'] = $output;
	print json_encode($result);	

?>