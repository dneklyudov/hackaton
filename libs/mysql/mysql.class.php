<?php
	if (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT']) {
		include_once($_SERVER['DOCUMENT_ROOT'] . '/libs/mysql/7.php');	
	}
	else {
		include_once('/home/d/dneklmis/hackaton.devbs.ru/public_html/libs/mysql/7.php');	
	}

	class DB_Mysql_Hosting extends DB_Mysql {
		public $user   = 'dneklmis_60';
		public $pass   = 'wbv*z3MO';
		public $dbhost = 'localhost';
		public $dbname = 'dneklmis_60';
		public function __construct() { }
	}

	class DB_Mysql_Local extends DB_Mysql {
		public $user   = 'root';
		public $pass   = '';
		public $dbhost = 'localhost';
		public $dbname = 'hackaton';
		public function __construct() { }
	}

	if (isset($_SERVER['HTTP_HOST'])) {
		if (strpos($_SERVER['HTTP_HOST'], '.') === false) {
			$dbh = new DB_Mysql_Local();
		}
		else {
			$dbh = new DB_Mysql_Hosting();
		}
	}
	else {
		$dbh = new DB_Mysql_Hosting();
	}
?>