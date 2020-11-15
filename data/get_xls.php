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

			include_once $_SERVER['DOCUMENT_ROOT'] . '/libs/PHPExcel/Classes/PHPExcel.php';

			$objPHPExcel = new PHPExcel();

			$i = 65;
			$headers = array('№', 'Исходный адрес', 'Обработанный адрес', 'Комментарии');
			foreach ($headers as $item) {
				if ($item) {
					$objPHPExcel->setActiveSheetIndex(0)->setCellValue(chr($i) . '1', $item);
					$i++;
				}
			}
		
			$cnt = 1;
			foreach ($entry as $item) {
				$cnt++;
				$i = 66;
				$objPHPExcel->setActiveSheetIndex(0)->setCellValue(chr(65) . $cnt, $cnt-1);
				foreach ($item as $item1) {
					$objPHPExcel->setActiveSheetIndex(0)->setCellValue(chr($i) . $cnt, $item1);
					$i++;
				}
			}

			$objPHPExcel->getActiveSheet()->getStyle(chr(65) . '1:' . chr($i) . '1')->getFont()->setBold(true);
			$objPHPExcel->getActiveSheet()->getStyle(chr(65) . '1:' . chr($i) . $cnt)->getFont()->setSize(10);

			// Set active sheet index to the first sheet, so Excel opens this as the first sheet
			$objPHPExcel->setActiveSheetIndex(0);

			// Redirect output to a client’s web browser (Excel2007) 
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="data.xlsx"');
			header('Cache-Control: max-age=0');
			// If you're serving to IE 9, then the following may be needed
			header('Cache-Control: max-age=1');
	
			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');	
		}
	}
?>