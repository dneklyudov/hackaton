<?php

	$id_user = 1;

 	// Обрабатываем очередные Q элементов из очереди
	define('Q', 100); // Сколько элементов из очереди обрабатываем за один раз

	if (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT']) {
		require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/mysql/mysql.class.php');
	}
	else {
		require_once('/home/d/dneklmis/hackaton.devbs.ru/public_html/libs/mysql/mysql.class.php');
	}

	/*
 	$sql = 'SELECT COUNT(`id`) as `n` FROM `tbl_queue`';
	$stmt = $dbh -> prepare($sql);
	$stmt -> execute();
	$entry = $stmt -> fetch_assoc();
	$total = $entry['n'];
	print 'Всего элементов в очереди на момент запуска скрипта: ' . $total . '<br>';
	*/

 	$sql = 'SELECT `tbl_queue`.* FROM `tbl_queue`, `tbl_files` WHERE `tbl_queue`.`parent_id` = `tbl_files`.`id` AND `tbl_files`.`stopped` =0 AND `tbl_files`.`id_user` = ' . $id_user . ' ORDER BY `id` LIMIT 0, ' . Q;
	$stmt = $dbh -> prepare($sql);
	$stmt -> execute();
	$entry = $stmt -> fetch_all_assoc();

	// Для биллинга
	$qaddr = 0;

	$ar_queue_ids = array();
	$str_v = '';
	foreach($entry as $item) {

		$data = array(
			"addr" => array(
				0 => array('val' => $item['address'])
			),
			"version" => 'demo'
		);

		$data_string = json_encode ($data, JSON_UNESCAPED_UNICODE);

		// print '<pre>' . print_r($data_string, 1) . '</pre>';

		$curl = curl_init('https://address.pochta.ru/validate/api/v7_1');
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'AuthCode: 53fb9daa-7f06-481f-aad6-c6a7a58ec0bb')
		);
		$result = curl_exec($curl);
		curl_close($curl);

		$qaddr++;

		$ar = json_decode($result, 1);

		// Если вылетел по timeout, то ничего не делаем, в следующий раз повторим
		if ($ar['state'] != '202') {

			// Ответ отправляется, если сервис не смог подтвердить адрес.
			if ($ar['state'] == '201') {
				$status = 'Адрес не подтвержден полностью';
			}

			// Ответ отправляется, если сервис не смог проверить адрес за определенное время. В текущей реализации установленное максимальное время для // проверки 4.5 сек. Параметр может быть изменен в любое время без нотификации для клиентов.
			elseif ($ar['state'] == '202') {
				$status = 'Адрес отклонен по timeout';
			}

			// Ответ отправляется, если по результатам ручной верификации адрес не подтвержден.
			elseif ($ar['state'] == '203') {
				$status = 'Адрес отклонен филиалом';
			}

			// Ответ отправляется по результатам автоматической проверки, если не требуется ручная верификация:
			// абонентский ящик в указанном ОПС не найден
			elseif ($ar['state'] == '404') {
				$status = 'Ящик в указанном ОПС не найден';
			}

			elseif ($ar['state'] == 'REQ001') {
				$status = 'Ошибка формата: формат не является json или есть лишние символы';
			}

			elseif ($ar['state'] == 'REQ002') {
				$status = 'Не указаны значения обязательных атрибутов (обязательные атрибуты отсутствуют в запросе либо присутствуют, но без значения)';
			}

			elseif ($ar['state'] == 'REQ003') {
				$status = 'Ошибка типа данных';
			}


			// Ответ отправляется по результатам автоматической проверки и ручной верификации:
			// если найден единственный завершенный адрес или
			// адрес восстановлен до полного -- suggest buiding / flat
			// Атрибут "missing" отсутствует.
			elseif ($ar['state'] == '301') {
				$status = 'Адрес подтвержден';
				if (isset($ar['addr']['accuracy'])) {
					$address['accuracy'] = $ar['addr']['accuracy'];

					$ac_i = substr($address['accuracy'], 0, 1);
					if ($ac_i == '0') { $address['accuracy_index'] = 'Индекс определен по дому/квартире'; }
					if ($ac_i == '1') { $address['accuracy_index'] = 'Индекс определен по улице'; }
					if ($ac_i == '2') { $address['accuracy_index'] = 'Индекс определен по населенному пункту (в населенном пункте нет улиц или у улицы нет индекса)'; }
					if ($ac_i == '3') { $address['accuracy_index'] = 'Индекс не определен'; }

					$ac_h = substr($address['accuracy'], 1, 1);
					if ($ac_h == '0') { $address['accuracy_home'] = 'Дом найден в ФИАС'; }
					if ($ac_h == '1') { $address['accuracy_home'] = 'Дом определен из запроса'; }
					if ($ac_h == '2') { $address['accuracy_home'] = 'Дом не определен'; }

					$ac_f = substr($address['accuracy'], -1);
					if ($ac_h == '0') { $address['accuracy_flat'] = 'Квартира найдена в ФИАС'; }
					if ($ac_h == '1') { $address['accuracy_flat'] = 'Квартира определена из запроса'; }
					if ($ac_h == '2') { $address['accuracy_flat'] = 'Квартира не определена'; }

				}
				$address['normal'] = $ar['addr']['outaddr'];
			}

			// Ответ отправляется по результатам автоматической проверки, если не требуется ручная верификация:
			// если найден единственный адрес и он неполный.
			// В массиве element перечисляются только подтвержденные элементы адреса, в атрибуте "missing" перечисляются недостающие элементы адреса.
			elseif ($ar['state'] == '302') {
				$status = 'Адрес подтвержден и он неполный';
				if (isset($ar['addr']['missing'])) {
					$address['missing'] = $ar['addr']['missing'];
				}
				
				if (isset($ar['addr']['accuracy'])) {
					$address['accuracy'] = $ar['addr']['accuracy'];

					$ac_i = substr($address['accuracy'], 0, 1);
					if ($ac_i == '0') { $address['accuracy_index'] = 'Индекс определен по дому/квартире'; }
					if ($ac_i == '1') { $address['accuracy_index'] = 'Индекс определен по улице'; }
					if ($ac_i == '2') { $address['accuracy_index'] = 'Индекс определен по населенному пункту (в населенном пункте нет улиц или у улицы нет индекса)'; }
					if ($ac_i == '3') { $address['accuracy_index'] = 'Индекс не определен'; }

					$ac_h = substr($address['accuracy'], 1, 1);
					if ($ac_h == '0') { $address['accuracy_home'] = 'Дом найден в ФИАС'; }
					if ($ac_h == '1') { $address['accuracy_home'] = 'Дом определен из запроса'; }
					if ($ac_h == '2') { $address['accuracy_home'] = 'Дом не определен'; }

					$ac_f = substr($address['accuracy'], -1);
					if ($ac_h == '0') { $address['accuracy_flat'] = 'Квартира найдена в ФИАС'; }
					if ($ac_h == '1') { $address['accuracy_flat'] = 'Квартира определена из запроса'; }
					if ($ac_h == '2') { $address['accuracy_flat'] = 'Квартира не определена'; }

				}
				$address['normal'] = $ar['addr']['outaddr'];
			}

			// Ответ отправляется по результатам автоматической проверки, если не требуется ручная верификация:
			// выявлено несколько равнозначных адресов.
			elseif ($ar['state'] == '303') {
				$status = 'Адресу сопоставлено несколько вариантов';

				if (isset($ar['addr']['missing'])) {
					$address['missing'] = $ar['addr']['missing'];
				}

				if (isset($ar['addr']['accuracy'])) {
					$address['accuracy'] = $ar['addr']['accuracy'];

					$ac_i = substr($address['accuracy'], 0, 1);
					if ($ac_i == '0') { $address['accuracy_index'] = 'Индекс определен по дому/квартире'; }
					if ($ac_i == '1') { $address['accuracy_index'] = 'Индекс определен по улице'; }
					if ($ac_i == '2') { $address['accuracy_index'] = 'Индекс определен по населенному пункту (в населенном пункте нет улиц или у улицы нет индекса)'; }
					if ($ac_i == '3') { $address['accuracy_index'] = 'Индекс не определен'; }

					$ac_h = substr($address['accuracy'], 1, 1);
					if ($ac_h == '0') { $address['accuracy_home'] = 'Дом найден в ФИАС'; }
					if ($ac_h == '1') { $address['accuracy_home'] = 'Дом определен из запроса'; }
					if ($ac_h == '2') { $address['accuracy_home'] = 'Дом не определен'; }

					$ac_f = substr($address['accuracy'], -1);
					if ($ac_h == '0') { $address['accuracy_flat'] = 'Квартира найдена в ФИАС'; }
					if ($ac_h == '1') { $address['accuracy_flat'] = 'Квартира определена из запроса'; }
					if ($ac_h == '2') { $address['accuracy_flat'] = 'Квартира не определена'; }

				}
			}

			// Для очистки очереди
			$ar_queue_ids[] = $item['id'];

			// Для записи в результаты
			$str_v .= '(
				NULL, 
				' . $item['parent_id'] . ', 
				\'' . $item['address'] . '\', 
				\'' . (isset($ar['addr']['outaddr']) ? $ar['addr']['outaddr'] : '') . '\', 
				\'' . $status . '\'
			), ';
		}
	}

	if ($str_v) {
		// Обновляем таблицу адресов: прописываем нормализованные и сообщения об ошибках
		$str_v = substr($str_v, 0, -2);
		$sql = 'INSERT INTO `tbl_addresses`(`id`, `parent_id`, `address`, `outaddr`, `state`) VALUES ' . $str_v;
		$stmt = $dbh -> prepare($sql);
		$stmt -> execute();

		// Удаляем из очереди успешно обработанные
		$sql = 'DELETE FROM `tbl_queue` WHERE id IN (' . implode(',', $ar_queue_ids) . ')';
		$stmt = $dbh -> prepare($sql);
		$stmt -> execute();

		// Обновляем статус isready в таблице загрузок файлов, если больше нет записей в очереди
		$sql = 'UPDATE `tbl_files` SET `isready`=1 WHERE `isready`=0 AND `tbl_files`.`id` NOT IN (SELECT DISTINCT `tbl_queue`.`parent_id` FROM `tbl_queue`)';
		$stmt = $dbh -> prepare($sql);
		$stmt -> execute();
	}
	
	// Для биллинга
	if ($qaddr) {
		$sql = 'INSERT INTO `tbl_users_addresses`(`id`, `id_user`, `date`, `q_addresses`) VALUES (NULL, ' . $id_user . ', NOW(), ' . $qaddr . ')';
		$stmt = $dbh -> prepare($sql);
		$stmt -> execute();
	}
	
	/*
 	$sql = 'SELECT COUNT(`id`) as `n` FROM `tbl_queue`';
	$stmt = $dbh -> prepare($sql);
	$stmt -> execute();
	$entry = $stmt -> fetch_assoc();
	$total = $entry['n'];
	print 'Всего элементов в очереди на момент окончания работы скрипта: ' . $total . '<br>';
	*/
?>