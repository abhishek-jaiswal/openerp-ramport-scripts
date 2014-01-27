<?php

class MysqlDb {
	public $link = false;
	public $HOST = 'localhost';
	public $USER = 'root';
	public $PASS = '';
	public $DBNAME = 'soohoo_dev';
	public $insert_count = 0;
	public $update_count = 0;
	function __construct() {
		try {
			$this->link = mysql_connect($this->HOST, $this->USER, $this->PASS)or die("Could not connect: " . mysql_error());
			mysql_select_db($this->DBNAME, $this->link);
		} catch (Exception $e) {
			print_r($e->getMessage());
		}
	}
	
	function sql_query($sql) {
		if($sql=='') {
			echo 'empty sql\n';
			return false;
		}
		if(empty($this->link)) {
			echo 'not Connected\n';
			return false;
		}

		$results = mysql_query($sql,$this->link) or die("Query Failed..\n" . mysql_error());
		//while ($row = mysql_fetch_assoc($results)) {
			//$aResult[] = $row;
		//}
		return $results;
	
	}
 }
 
 
 class PgsqlDb {
	public $link = false;
	public $insert_count = 0;
	public $update_count = 0;
	
	function __construct() {
		try {
			$this->link = pg_Connect("host=203.129.204.130 port=5432 dbname=erp user=openerp password=mindfire");
			
		} catch (Exception $e) {
			print_r($e->getMessage());
		}
		
	}
	
	function sql_query($sql) {
		if($sql=='') {
			echo 'empty sql\n';
			return false;
		}
		if(empty($this->link)) {
			echo 'not Connected\n';
			return false;
		}
		$aResult=[];
		//$results = pg_exec($this->link, $sql) or die("Query Failed..\n" . $sql);
		$results = pg_exec($this->link, $sql);
		if (!$results) {
			echo "failed : \n";
			echo $sql.'\n\n\n';
		}
		//while ($row = pg_fetch_assoc($results)) {
		//	$aResult[] = $row;
		//}
		return $results;
	}
	
}
 
$PgsqlConn = new PgsqlDb();
 
$MysqlConn = new MysqlDb();
$results = $MysqlConn->sql_query('select * from hts_codes');
	while ($row = mysql_fetch_assoc($results)) {
		//$aResult[] = $row;
		//echo $row['id'];
		$hts_heading_id = ($row['hts_heading_id']) ? $row['hts_heading_id'] : 'NULL';
		//echo $hts_heading_id;
		$sql = "Insert INTO shipment_hts (id,create_uid,write_uid,section,chapter,heading,old_heading_id,suffix,hts,"
		."u1,\"order\",description,unit,general,special,other) VALUES (".$row['id']
										.",1"
										.",1"
										.",'".pg_escape_string($row['section'])."'"
										.",'".pg_escape_string($row['chapter'])."'"
										.",'".pg_escape_string($row['heading'])."'"
										.",".$hts_heading_id
										.",'".pg_escape_string($row['sufix'])."'"
										.",'".pg_escape_string($row['hts'])."'"
										.",'".pg_escape_string($row['u1'])."'"
										.",'".pg_escape_string($row['order'])."'"
										.",'".pg_escape_string($row['description'])."'"
										.",'".pg_escape_string($row['unit'])."'"
										.",'".pg_escape_string($row['general'])."'"
										.",'".pg_escape_string($row['special'])."'"
										.",'".pg_escape_string($row['other'])."'"
										.");";
	//echo $sql.'\n';
	$aRes = $PgsqlConn->sql_query($sql);
	//print_r($aRes);
	//echo "\n";
	}
 
 

 