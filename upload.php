<?php

	$id_user = 1;

	ob_start();
	$result = array();

	if ((!isset($_FILES) || (@count($_FILES) == 0)) && (!isset($_POST) || (@count($_POST) == 0))) {
		$output = ob_get_contents();
		ob_end_clean();
		$result['error'] = 'Файл слишком большой';
		print json_encode($result);
		exit;
	}

	clearstatcache();

	if (substr($_SERVER['DOCUMENT_ROOT'], -1) != '/') { $_SERVER['DOCUMENT_ROOT'] .= '/'; }
	// include_once ($_SERVER['DOCUMENT_ROOT'] . 'includes/common/common_functions.php');

	$uploaddir = $_SERVER['DOCUMENT_ROOT'] . 'upload/';
	if (!is_dir($uploaddir)) {
		mkdir($uploaddir, 0775, true);
	}

	$current_field_mime = array(
		'text/csv',
		'application/csv',
		'application/x-msexcel',
		'application/vnd.ms-excel', 
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
	);

	/*
	Не удаляем, потому что ссылки
	// Удаляем старые файлы (старше 24 часов)
	if (is_dir($uploaddir)) {
		if ($dh = opendir($uploaddir)) {
			while (false !== ($file = readdir($dh))) {
		               if ($file != "." && $file != "..") {  
					if (strtotime('-24 hours') > filemtime($uploaddir . '/' . $file)) {
						unlink($uploaddir . '/' . $file);
					}
				}
			}
			closedir($dh);
		}
	}
	*/

	foreach($_FILES as $file) {

		if ($file['error'] == UPLOAD_ERR_OK) {
			if (isset($file['tmp_name'])) {
				if ($file['size'] > 0) {
					if (!in_array($file['type'], $current_field_mime)) {
						print 'Некорректный тип файла «' . basename($file['name']) . '» (' . $file['type'] . ')';
					}
					else {
						$temp_fn = translit(basename(str_replace('_', '', $file['name'])));
						if (getName($temp_fn) == '') {
							$temp_fn = uniqid() . '.' . getExtension($temp_fn);
						}
						$newFileName = $uploaddir . $temp_fn;
						$newFileName = getCorrectFileNamewUpload(substr($newFileName, strlen($_SERVER['DOCUMENT_ROOT']) - 1));
						if ($newFileName) {
							$newPath = $_SERVER['DOCUMENT_ROOT'] . $newFileName;
	
							if (move_uploaded_file($file['tmp_name'], $newPath)) {

								$filesize = round((filesize($newPath)/1024),1) . ' кб';
								$filename = substr($newPath, strlen($_SERVER['DOCUMENT_ROOT']));

								if (getExtension($filename) == 'csv') {

									// Перекодируем из Win1251 в UTF8 
									$str = file_get_contents($newPath);
									$str = iconv("Windows-1251", "UTF-8//IGNORE", $str);
									file_put_contents($newPath, $str);

									require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/mysql/mysql.class.php');

								 	$sql = 'INSERT INTO `tbl_files` (`id`, `file`, `date`, `isready`, `id_user`) VALUES (NULL, \'' . $filename . '\', NOW(), 0, ' . $id_user . ')';
									$stmt = $dbh -> prepare($sql);
									$stmt -> execute();
									$file_id = mysqli_insert_id($dbh->dbh); 

								 	$sql = 'INSERT INTO `tbl_users_files`(`id`, `id_user`, `date`, `file`) VALUES (NULL, ' . $id_user . ', NOW(), \'' . $filename . '\')';
									$stmt = $dbh -> prepare($sql);
									$stmt -> execute();

									$str_v = '';
									$param = array();
									$c_param = 1;

									// Читаем по 100 строчек из файла
									$counter = 0;
									$total_c = 0;
									$arr_csv = array();
									$filelg = FileLineGenerator($newPath);
									foreach($filelg as $str) {
								 		$counter++;
								 		$total_c++;
										$csv_line = str_getcsv($str);							
										$line = array();
										for ($i = 0, $j = count($csv_line); $i < $j; $i++) {
											$line[] .= trim($csv_line[$i]);
										}
										$arr_csv[] = $line;

										// Записываем в базу по 100 записей за раз
								 		if ($counter == 100) {
								 		
										 	foreach($arr_csv as $address) {
										 		if ($address[0]) {
											 		$str_v .= '(NULL, #' . $c_param++ . '#, #' . $c_param++ . '#, 0), ';
											 		$param[] = $file_id;
											 		$param[] = $address[0];
											 	}
										 	}

										 	$str_v = substr($str_v, 0, -2);
										 	$sql = 'INSERT INTO `tbl_queue` (`id`, `parent_id`, `address`, `state`) VALUES ' . $str_v . ';';
											$stmt = $dbh -> prepare($sql);
											$stmt -> execute($param);
			
											$str_v = '';
											$param = array();
											$c_param = 1;
											$counter = 0;
											$arr_csv = array();						 			
								 		}										
									}
									
									// Если чего-то осталось, пишем в базу
									if (count($arr_csv)) {
	
									 	foreach($arr_csv as $address) {
									 		if ($address[0]) {
										 		$str_v .= '(NULL, #' . $c_param++ . '#, #' . $c_param++ . '#, 0), ';
										 		$param[] = $file_id;
										 		$param[] = $address[0];
										 	}
									 	}
	
									 	$str_v = substr($str_v, 0, -2);
									 	$sql = 'INSERT INTO `tbl_queue` (`id`, `parent_id`, `address`, `state`) VALUES ' .  $str_v . ';';
										$stmt = $dbh -> prepare($sql);
										$stmt -> execute($param);
										
										$result['message'] = 'Адреса из файла успешно добавлены в очередь (' . ($total_c) . ' шт.)';	
	
									}
								}
/*
								if (getExtension($filename) == 'xlsx' || getExtension($filename) == 'xls') {

									if ($file['type'] == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
						
										include_once $_SERVER['DOCUMENT_ROOT'] . '/libs/PHPExcel/Classes/PHPExcel/IOFactory.php';
									
										$inputFileType = 'Excel2007';
										// 	$inputFileType = 'Excel5';
										//	$inputFileType = 'Excel2003XML';
										//	$inputFileType = 'OOCalc';
										//	$inputFileType = 'Gnumeric';
									
										$objReader = PHPExcel_IOFactory::createReader($newPath);
										$objPHPExcel = $objReader->load($newPath);	
										$loadedSheetNames = $objPHPExcel->getSheetNames(); // Каждый лист - текстовый блок
						
										$ar = array();
										foreach($loadedSheetNames as $sheetIndex => $loadedSheetName) {
											$ar[] = $loadedSheetName;
										}
						
										print 'Листы: ' . print_r($ar, 1);
									}


	
								}
*/

							}
							else {
								print 'Не удалось скопировать файл «' . basename($file['name']) . '»'; 
							}
						}
						else {
							print 'Не удалось скопировать файл «' . basename($file['name']) . '» с корректным именем'; 
						}
					}
				}
				else {
					print 'Пустой файл «' . basename($file['name']) . '»';
				}
			}
			else {
				print 'При загрузке файла «' . basename($file['name']) . '» произошла ошибка'; 
			}
		}
		elseif ($file['error'] == UPLOAD_ERR_INI_SIZE) {
			print 'Файл «' . basename($file['name']) . '» слишком большой';
		}
		elseif ($file['error'] == UPLOAD_ERR_FORM_SIZE) {
			print 'Файл «' . basename($file['name']) . '» слишком большой';
		}
		else {
			print 'При загрузке файла «' . basename($file['name']) . '» произошла ошибка'; 
		}
	}

	$output = ob_get_contents();
	ob_end_clean();
	$result['error'] = $output;
	print json_encode($result);

	function getCorrectFileNamewUpload($value) {
		if ($value) {
			$arr = explode(",", $value);
			clearstatcache();
			foreach ($arr as &$file) {
				$old = $file;

				$micro_date = microtime();
				$date_array = explode(" ",$micro_date);
				$date = date("ymd-His", $date_array[1]) . '-m' . substr($date_array[0], 2, -2);
				$fn = getName($file) . '-' . $date;

				$ext = getExtension($file);
				$arr_l = explode("-", $fn);
				$s = @count($arr_l);
				if (is_numeric($arr_l[$s - 1])) { // Последнее значение - целое число, мы его выкидываем
					unset($arr_l[$s - 1]);
				}
				$fn = implode("-", $arr_l);
	
				$base_val = $fn;
				$ind = 1;
				do {
					$val = $base_val . '-' . $ind;
					$ind++;						
					if (!file_exists($_SERVER['DOCUMENT_ROOT'] . 'upload/' . basename($val) . '.' . $ext)) {
						break;
					}
				} while(1);
				$file = str_replace('.', '', $val) . '.' . $ext;
			}
			unset($file);
			$value = implode(",", $arr);		
			return $value;
		}
		return '';
	}
	
	function FileLineGenerator($file) {
		if (!$fh = fopen($file, 'r')) {
			return;
		}
		while (false !== ($line = fgets($fh))) {
			yield $line;
		}
		fclose($fh);
	}
	
	function microtime_float() {
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}

	function translit($address) {
		$tr = array(
		        "А"=>"a",
			"Б"=>"b",
			"В"=>"v",
			"Г"=>"g",
		        "Д"=>"d",
			"Е"=>"e",
			"Ё"=>"e",
			"Ж"=>"zh",
			"З"=>"z",
			"И"=>"i",
		        "Й"=>"y",
			"К"=>"k",
			"Л"=>"l",
			"М"=>"m",
			"Н"=>"n",
		        "О"=>"o",
			"П"=>"p",
			"Р"=>"r",
			"С"=>"s",
			"Т"=>"t",
		        "У"=>"u",
			"Ф"=>"f",
			"Х"=>"h",
			"Ц"=>"ts",
			"Ч"=>"ch",
		        "Ш"=>"sh",
			"Щ"=>"sch",
			"Ъ"=>"",
			"Ы"=>"y",
			"Ь"=>"",
		        "Э"=>"e",
			"Ю"=>"yu",
			"Я"=>"ya",
			"а"=>"a",
			"б"=>"b",
		        "в"=>"v",
			"г"=>"g",
			"д"=>"d",
			"е"=>"e",
			"ё"=>"e",
			"ж"=>"zh",
		        "з"=>"z",
			"и"=>"i",
			"й"=>"y",
			"к"=>"k",
			"л"=>"l",
		        "м"=>"m",
			"н"=>"n",
			"о"=>"o",
			"п"=>"p",
			"р"=>"r",
		        "с"=>"s",
			"т"=>"t",
			"у"=>"u",
			"ф"=>"f",
			"х"=>"h",
		        "ц"=>"ts",
			"ч"=>"ch",
			"ш"=>"sh",
			"щ"=>"sch",
			"ъ"=>"",
		        "ы"=>"y",
			"ь"=>"",
			"э"=>"e",
			"ю"=>"yu",
			"я"=>"ya",
			" "=>"-"
		);

		$address = strip_tags($address); // убираем HTML-теги
		$address = str_replace(array("\n", "\r"), " ", $address); // убираем перевод каретки
		$address = preg_replace("/\s+/", ' ', $address); // удаляем повторяющие пробелы
		$address = trim($address); // убираем пробелы в начале и конце строки
		$address = strtr($address, $tr);
		$address = preg_replace("/[^0-9a-z-_. ]/i", "", $address); // очищаем строку от недопустимых символов
		if ($address == '') {
			$address = uniqid();
		}
		return $address;
	}

	function getExtension($filename) {
		return substr(strrchr($filename, '.'), 1);
	}

	function getName($filename) {
		return substr($filename, 0, strrpos($filename, '.'));
	}

	function getCorrectLink($value, $table, $id) {
		global $dbh;

		$val = trim(mb_strtolower(translit($value), 'UTF-8'));
		if ($table == 'tbl_pages') {
			$val = str_replace('.', '', $val);
		}

		$sql = 'SELECT `id` FROM `' . $table . '` WHERE `link` = \'' . $val . '\' LIMIT 1';
		$stmt  = $dbh -> prepare ($sql);
		$stmt  -> execute(array());
		$entry = $stmt->fetch_assoc();

		if ((!isset($entry['id'])) or ((($id) && ($entry['id'] == $id)))) {
		}
		else {
			$arr_l = explode("-", $val);
			$s = @count($arr_l);
			if (is_numeric($arr_l[$s - 1])) { 
				unset($arr_l[$s - 1]);
			}
			$val = implode("-", $arr_l);
			$base_val = $val;
			$ind = 1;
			do {
				$val = $base_val . '-' . $ind;
				$ind++;
				$sql = 'SELECT `id` FROM `' . $table . '` WHERE `link` = \'' . $val . '\' LIMIT 1';
				$stmt  = $dbh -> prepare ($sql);
				$stmt  -> execute(array());
				$entry1 = $stmt->fetch_assoc();
				if ((!isset($entry1['id'])) or ((($id) && ($entry1['id'] == $id)))) {
					break;
				}
			} while(1);
		}
		return $val;
	}	
?>