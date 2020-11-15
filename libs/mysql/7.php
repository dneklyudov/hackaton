<?php 
class MysqlException extends Exception {
	public $error;
	public function __construct($dbh, $message=false, $code=false) {
		if (!$message) {
			$this->message = mysqli_error($dbh);
		}
		if (!$code) {
			$this->code = mysqli_errno($dbh);
		}
		print '<p>Ошибка при работе с базой данных: ' . $this->message . ' ' . $this->code . '</p>';
	}
	public function get_error() {
		return $this->error;
	}
}

class DB_Mysql {
	protected $user;
	protected $pass;
	protected $dbhost;
	protected $dbname;
	public    $dbh;
	protected $temp;
	

	public function __construct($user, $pass, $dbhost, $dbname) {
		$this->user   = $user;
		$this->pass   = $pass;
		$this->dbhost = $dbhost;
		$this->dbname = $dbname;
	}
	protected function connect() {
		$this->dbh = mysqli_connect($this->dbhost, $this->user, $this->pass, $this->dbname);
		if (!$this->dbh) {
			$this->dbh = mysqli_connect($this->dbhost, $this->user, $this->pass, '');
			mysqli_set_charset ($this->dbh, 'utf8');
			$smtp = $this->prepare('CREATE DATABASE ' . $this->dbname . ' DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci');
			$smtp -> execute();
			$this->dbh = mysqli_connect($this->dbhost, $this->user, $this->pass, $this->dbname);
			if (!$this->dbh) {
				print mysqli_connect_errno() . ' ' . mysqli_connect_error()  . '<br>';
				throw new MysqlException($this->dbh);
			}
		}
		else {
			mysqli_set_charset ($this->dbh, 'utf8');
		}
	}

	public function prepare($query) {
		if (!$this->dbh) {
			$this->connect();
		}
		return new DB_MysqlStatement($this->dbh, $query);
	}
}
class DB_MysqlStatement {
	private $result;
	private $binds;
	private $query; 
	private $last_query;
	private $dbh;
	public function __construct($dbh, $query) {
		$this->query = $query;
		$this->dbh   = $dbh;
	}
	public function execute() {
		$binds = func_get_args();
		$cnt = count($binds);
		if ($cnt>0) {
			$binds = $binds[0];
		}
		foreach($binds as $index=>$name) {
		 	$this->binds[$index + 1] = $name;
		}
		$cnt = count($binds);
		$query = $this->query;
		$except = array('NOW()', 'NULL');
		if ($cnt>0) {
			foreach ($this->binds as $ph=>$pv) {
				if (in_array($pv, $except))  {
					$query = str_replace("#$ph#", $pv, $query);
				}
				else {
					$query = str_replace("#$ph#", "'" . mysqli_real_escape_string($this->dbh, $pv) . "'", $query);
				}
			}
		}
		$this->last_query = $query;
		static $numb = 0;
		$numb++;
		$this->result = mysqli_query($this->dbh, $query);
		// print $this->last_query;
		// file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/' . uniqid() . '.txt', $this->last_query);
		if(!$this->result) {
			// print $this->last_query;
			throw new MysqlException($this->dbh);
		}
	}

	public function fetch_row() {
		if(!$this->result) {
			throw new MysqlException($this->dbh);
		}
		return mysqli_fetch_row($this->result);
	}
	public function fetch_assoc(){
		return mysqli_fetch_assoc($this->result);
	}
	public function fetch_all_assoc() {
		$retval = array();
		while($row = $this->fetch_assoc()) {
			$retval[] = $row;
		}
		return $retval;
	}
	public function get_result() {
		return $this->result;
	}
	public function get_query() {
		return $this->last_query;
	}
	public function get_insert_id(){
		return mysqli_insert_id($this->dbh);
	}
}
?>