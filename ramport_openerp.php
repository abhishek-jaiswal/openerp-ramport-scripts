<?php
/**
 * Object to manage integration with RamPort software
 *
 * @author Mindfire
 * @version 2.0
 * @package pts
 * @subpackage custom
 */
class Ramport {
	const TBL_CUSTOMERS			= 'CUSTOMER.TXT';
	const TBL_VENDORS			= 'VENDOR.TXT';
	const TBL_FILES				= 'FILEFOLD.TXT';
	const TBL_FDADATA			= 'FDADATA.TXT';
	const TBL_ITNOFILE			= 'ITNOFILE.TXT';
	const TBL_ENTRY				= 'ENTRY.TXT|2';
	const TBL_SCAC				= 'TABLE.TXT| 1';
	const TBL_PORTS_LADING		= 'TABLE.TXT| 5';
	const TBL_PORTS_UNLADING	= 'TABLE.TXT| 6';
	const TBL_VESSELS			= 'TABL2.TXT| 8';
	const TBL_AIRPORTS			= 'TABLE.TXT|82';
	const TBL_CUSTOMS_ST		= 'TABLE.TXT|33';
	const TBL_ENTRY_TYPES		= 'TABLE.TXT|10';
	const TBL_NOTES				= 'NOTES.TXT';
	const TBL_LOGS				= 'REFERENC.TXT';
	const TBL_POS				= 'REFPONUM.TXT';
	const TBL_CONTAINERS		= 'CONTAINR.TXT';
	const TBL_FIRMS				= 'FIRMS.TXT';
	const TBL_MIDS				= 'MIDS.TXT';
	const TBL_SECURITY			= 'SECURITY.TXT';
	
	public $link = false;
	public $insert_count = 0;
	public $update_count = 0;
	private $aColumns = array();
	private $current_table = '';

	function __construct() {
		#$this->link = pg_Connect("host=localhost port=5432 dbname=erp user=postgres password=mindfire");
		$this->link = pg_Connect("host=203.129.204.130 port=5432 dbname=erp user=openerp password=mindfire");
	}


	/**
	 * Log something. For now, output text
	 *
	 * @access private
	 */
	private function log($text='', $verbose=1) {
		$file = fopen("import_log.log","a+");
		echo fwrite($file, $text."\r\n");
		fclose($file);
	}
	
	/**
	 * Converts amount in cents to dolars
	 * Eg: 100c = 1.00$
	 *
	 * @param string
	 * @return string
	 */
	private function cents_to_dollars($value) {
		return number_format(intval($value) / 100, 2, '.', '');
	}
	
	/**
	 * Import files into cache table ramport_cache
	 *
	 * @access public
	 * @param string
	 * @param boolean
	 * @return boolean
	 */
	public function load_to_cache($table, $folder) {
		global $oTime;
		$this->current_table = $table;
		$r = true;
		$this->log();
		$start_ts = date('H:i:s');
		
		// For multiple tables inside one file
		$aTMP = explode('|', $table);
		$file	= $aTMP[0];
		$filter	= (isset($aTMP[1])) ? $aTMP[1] : '';
		
		$this->log('Loading: '.$file.' - '.date('Y-m-d').' '.$start_ts);
		
		if ($filter)
		$this->log('Filter: '.$filter);
		
		// If file is not there return false
		if (! file_exists($folder.$file))
		return $this->log('File not found!') && false;
		
		$this->set_columns($table);
		$max = 100;
		// Open file
		$handle = @fopen($folder.$file, "r");
		if ($handle) {
			$total = $this->read_lines($handle, $filter);
			fclose($handle);
			$this->log('total: '.$total);
		} else {
			$r = false;
			$this->log('Couldn\'t open file!');
		}
		$end_ts = date('H:i:s');
		echo "insert_count = ".$this->insert_count.'/n';
		echo "update_count = ".$this->update_count.'/n';
		$this->log('End load: '.$file.' - '.$end_ts);
		
		return $r;
	}
	
	/**
	 * Read lines from file to DB cache
	 *
	 * @access private
	 * @param file
	 * @param string
	 * @return integer
	 */
	private function read_lines($handle, $filter) {
		$count = 0;
		while (! feof($handle)) {
			set_time_limit(10);
			$line = fgets($handle);
			if ($filter) {
				if ($this->current_table == self::TBL_ENTRY) {
					if (substr($line, 13, strlen($filter)) == $filter)
					$this->update_cache($line);
				} else {
					if (substr($line, 0, strlen($filter)) == $filter)
					$this->update_cache(substr($line, strlen($filter)));
				}
			} else {
				$this->update_cache($line);
			}
			$count++;
		}
		return $count;
	}

	
	/**
	 * Convert date from american format for database format
	 *
	 * @access public
	 * @param string
	 * @return string
	 */
	public function convert_date($date, $format='mdy') {
		if ($format=='ymd') {
			$century = (intval(substr($date, 0, 2)) > 70) ? '19' : '20';
			return $century.substr($date, 0, 2).'-'.substr($date, 3, 2).'-'.substr($date, 6, 2);
		} else if ($format=='mdy') {
			$century = (intval(substr($date, 6, 2)) > 70) ? '19' : '20';
			return $century.substr($date, 6, 2).'-'.substr($date, 0, 2).'-'.substr($date, 3, 2);
		}
	}


	/**
	 * Split lines into columns
	 *
	 * @access private
	 * @param string
	 */
	private function update_cache($line) {
		$dup_result = '';
		$result = '';
		$rid = '';
		$status = '';
		$date_time = '';
		$old_line = '';
		$aResult = array();
		
		//Connect to PostgreSql data base fo openERP
		
		if ($this->link) {
			if ($this->current_table==Ramport::TBL_NOTES) {
				$id = trim(substr($line, 0, 7));
				//Check if duplicte record doesn't exist then go for insert
				$qry = "SELECT DISTINCT * FROM shipment_abi_results WHERE file_number='".$id."';";
				$dup_result = pg_exec($this->link, $qry);
				$status = 'imported';
				$date_time = $date_time = $this->convert_date(substr($line, 7, 8)).' '.substr($line, 15, 8);
				
				if (pg_numrows($dup_result) == 0) {
					$sql = "INSERT INTO shipment_abi_results(file_number, line, date_time, status) VALUES('".pg_escape_string($id)."', '".pg_escape_string($line)."', '".pg_escape_string($date_time)."', '".pg_escape_string($status)."');";
					$result = pg_exec($this->link, $sql);
					$this->insert_count = $this->insert_count +1;
					//echo "insert_count = ".$this->insert_count;
					if (!$result) {
						$this->log('Failed to insert notes'. $id);
					}
				} else {
					$aResult = pg_fetch_assoc($dup_result);
					$old_line = $aResult['line'];
					if ($old_line != $line) {
						$sql = "UPDATE shipment_abi_results SET file_number = '".pg_escape_string($id)."', line = '".pg_escape_string($line)."', date_time = '".pg_escape_string($date_time)."', status = '".pg_escape_string($status)."';";
						$result = pg_exec($this->link, $sql);
						$this->update_count = $this->update_count +1;
						//echo "update_count = ".$this->update_count;
						if (!$result) {
							$this->log('Failed to update notes'. $id);
						}
					}
				}
			} else {
				$id = trim(substr($line, 0, $this->aColumns['ramport_key']));
				
				//Check if duplicte record doesn't exist then go for insert
				$qry = "SELECT DISTINCT * FROM shipment_ramport WHERE rid='".$id."';";
				$dup_result = pg_exec($this->link, $qry);
				$status = 'imported';
				
				if (pg_numrows($dup_result) == 0) {
					$sql = "INSERT INTO shipment_ramport(table_name, line, rid, status) VALUES('".$this->current_table."', '".pg_escape_string($line)."', '".pg_escape_string($id)."', '".pg_escape_string($status)."');";
					$result = pg_exec($this->link, $sql);
					
					$this->insert_count = $this->insert_count +1;
					//echo "insert_count = ".$this->insert_count;
					
					if (!$result) {
						$this->log('Failed to insert cache '. $id);
						$this->log('query -  '. $sql);
						$this->log('\n');
						
					}
					
				} else {
					$aResult = pg_fetch_assoc($dup_result);
					$old_line = $aResult['line'];
					if ($old_line != $line) {
						$sql = "UPDATE shipment_ramport SET table_name = '".pg_escape_string($this->current_table)."', line = '".pg_escape_string($line)."', rid = '".pg_escape_string($id)."', status = '".pg_escape_string($status)."';";
						$result = pg_exec($this->link, $sql);
						if($result > 0) {
							// success
						} else {
							// error
							echo $sql . "----------\n";
						}
						$this->update_count = $this->update_count +1;
						//echo "update_count = ".$this->update_count;
						if (!$result) {
							$this->log('Failed to update cache'. $id);
						}
					}
				}
			}
		} else {
			$this->log('Unable to connect to PGSQL @ '.date('H:i:s'));
		}
	}
	
	
	/**
	 * Set name and position of each column in table
	 *
	 * @access private
	 */
	private function set_columns($table) {
		$this->aColumns = array();
		
		if ($table==self::TBL_CUSTOMERS) {
			$this->aColumns = array('ramport_key'=>13, 'name'=>32, 'address'=>32, 'address_2'=>32, 'city'=>21, 'state'=>2, 'postal_code'=>9);
			$this->aColumns += array('tel'=>10, 'fax'=>10, 'contact'=>22, 'type'=>1, 'country'=>2, 'unk1'=>13, 'unk2'=>13);
			$this->aColumns += array('unk3'=>13, 'unk4'=>13, 'last_activity'=>8, 'ALPHA_SORT_SEQUENCE'=>32, 'unk_bool_1'=>3, 'bond_effective_date'=>8, 'unk_b_1'=>1);
			$this->aColumns += array('unk_op_1'=>1, 'unk_b_2'=>1, 'unk_b_3'=>1, 'unk5'=>3, 'su'=>3, 'bill_irs_number'=>13, 'ach_unit_no'=>6, 'unk_multi_1'=>8);
			$this->aColumns += array('fish_wildlife_license_number'=>6, 'date_established'=>8, 'unk_b_4'=>1, 'unk_b_5'=>1, 'billing_contact_name'=>22, 'foreign_address'=>32);
			$this->aColumns += array('unk_multi_2'=>11, 'unk_float_5'=>11, 'unk_float_6'=>11, 'unk_float_7'=>11, 'unk_float_8'=>11, 'unk_date_3'=>8);
			$this->aColumns += array('bond_expiration_date'=>8, 'poa_expiration_date'=>8, 'alternate_import_of_record_irs_number'=>13, 'unk_float_9'=>6);
			$this->aColumns += array('unk_float_10'=>6, 'unk_6'=>6, 'alternate_destination_state'=>2, 'unk_multi_3'=>3, 'bond_producer_number'=>10, 'unk_float_11'=>10);
			$this->aColumns += array('user'=>3, 'email'=>62, 'unk_multi_4'=>6, 'sales_user'=>3, 'op_user'=>3, 'unk_multi_5'=>4, 'trucker_number'=>13);
			$this->aColumns += array('aging_cat_1'=>3, 'aging_cat_2'=>3, 'aging_cat_3'=>3, 'unk_multi_6'=>2, 'fda_consignee'=>12);
		}
		
		if ($table==self::TBL_LOGS)
		$this->aColumns = array('ramport_key'=>7, 'consignee_number'=>13, 'carrier_code'=>4, 'voyage_number'=>5, 'vessel_name'=>22);
		
		if ($table==self::TBL_POS)
		$this->aColumns = array('ramport_key'=>7, 'po_number'=>15);
		
		if ($table==self::TBL_FILES) {
			$this->aColumns = array('ramport_key'=>7, 'unk1'=>7, 'est_vessel_arr_date'=>8, 'elected_entry_date'=>8, 'entry_type_code'=>2, 'entry_number'=>13);
			$this->aColumns += array('live_entry'=>1, 'cst_number'=>3, 'consignee_number'=>13, 'importer_number'=>13, 'carrier_code'=>4, 'voyage_number'=>5);
			$this->aColumns += array('location_of_goods'=>4, 'unk2'=>1, 'location_description'=>25, 'vessel_code'=>5, 'vessel_name'=>22, 'port_unlading_id'=>4);
			$this->aColumns += array('go_number'=>20, 'value'=>16, 'description'=>74, 'port_of_clearance'=>4, 'unk3'=>6, 'unk4'=>2, 'central_exam_site'=>15);
			$this->aColumns += array('messager_service'=>19, 'unk5'=>2, 'weight'=>12, 'date_3461'=>8, 'box_number'=>4, 'box_number2'=>4, 'country_of_origin'=>2);
			$this->aColumns += array('date_of_exportation'=>8, 'unk6'=>9, 'entry_date'=>8, 'unk7'=>4, 'transmission_priority'=>3, 'no_of_packages'=>6, 'est_vessel_arr_time'=>5);
			$this->aColumns += array('unk_date1'=>8, 'unk_time1'=>5, 'estimated_duty'=>11, 'unk_float2'=>11, 'estimated_fees'=>11, 'unk_float4'=>11, 'unk_float5'=>7, 'unk_float6'=>7);
			$this->aColumns += array('unk_date2'=>8, 'i_t_carrier'=>15, 'unk8'=>3, 'unk9'=>12, 'mode_of_transportation'=>2);
		}
		
		if ($table==self::TBL_SECURITY)
		$this->aColumns = array('ramport_key'=>7, 'unk1'=>8, 'importer_number'=>13);
		
		if ($table==self::TBL_ITNOFILE) {
			$this->aColumns = array('ramport_key'=>10, 'it_number'=>12, 'scac'=>4, 'bill_of_lading_number'=>12, 'house_bill_number'=>12);
			$this->aColumns += array('sub_house_bill_number'=>12, 'it_date'=>8, 'number_of_packages'=>8, 'units'=>5, 'issuer_of_house_bill'=>4);
		}
		
		if ($table==self::TBL_ENTRY){
			$this->aColumns = array('ramport_key'=>13, 'sub1'=>1, 'sub2'=>2, 'uk1'=>12, 'gross_weight'=>13, 'uk2'=>19, 'value'=>13, 'uk5'=>30, 'hts'=>10, 'uk3'=>17, 'uk4'=>13, 'net_quantity'=>21, 'uk6'=>13, 'uk7'=>13, 'unit'=>5);
			$this->aColumns +=  array('uk8'=>68, 'rate'=>13, 'uk9'=>13, 'text_rate'=>10, 'uk10'=>48, 'duty'=>13);
			$this->aColumns +=  array('uk11'=>11, 'uk12'=>11, 'uk13'=>11, 'uk14'=>1, 'uk_date_1'=>8, 'mid'=>15);
		}
		
		if ($table==self::TBL_FDADATA){
			$this->aColumns = array('ramport_key'=>18, 'unk1'=>3, 'description'=>72, 'unk2'=>1, 'fda_code'=>7, 'storage'=>1, 'country'=>2, 'mid_manufacturer'=>15);
			$this->aColumns +=  array('mid_shipper'=>15, 'value'=>10, 'unk4'=>12, 'unk5'=>38, 'unk6'=>4, 'unk7'=>4, 'unk8'=>4, 'unk9'=>20, 'unk10'=>13);
			$this->aColumns +=  array('quantity_1'=>11, 'quantity_2'=>11, 'quantity_3'=>11, 'quantity_4'=>11, 'quantity_5'=>11, 'quantity_6'=>11);
			$this->aColumns +=  array('unit_1'=>4, 'unit_2'=>4, 'unit_3'=>4, 'unit_4'=>4, 'unit_5'=>4, 'unit_6'=>4, 'rest'=>3000);
		}
		
		if ($table==self::TBL_VENDORS) {
			$this->aColumns = array('ramport_key'=>13, 'name'=>30, 'address'=>30, 'address_2'=>30, 'city'=>21, 'state'=>2, 'postal_code'=>9);
			$this->aColumns += array('tel'=>10, 'contact'=>20, 'last_purchase_date'=>8, 'billing_code'=>2, 'alpha_sort'=>30, 'check_it_reference'=>13, 'unk1'=>2, 'email'=>62, 'payment_terms'=>2);
		}
		
		if (in_array($table, array(self::TBL_PORTS_LADING, self::TBL_VESSELS)))
		$this->aColumns = array('ramport_key'=>28, 'name'=>25);
		
		if ($table==self::TBL_SCAC)
		$this->aColumns = array('ramport_key'=>28, 'name'=>35);
		
		if ($table==self::TBL_PORTS_UNLADING)
		$this->aColumns = array('ramport_key'=>28, 'name'=>25, 'type'=>1, 'use_in_3461_7501'=>1 );
		
		//Import PO Ref. No.s separately
		
		//Firm Codes import
		if ($table==self::TBL_FIRMS)
		$this->aColumns = array('ramport_key' => 5, 'name' => 35, 'address' => 35, 'city' => 34, 'postal_code' => 30);
		
		//MIDS import
		if ($table==self::TBL_MIDS)
		$this->aColumns = array('ramport_key' => 15, 'name' => 80, 'address' => 90, 'city' => 50);
		
		if ($table==self::TBL_AIRPORTS)
		$this->aColumns = array('ramport_key'=>28, 'name'=>20, 'country'=>2, 'port_code'=>5);
		
		if ($table==self::TBL_CONTAINERS)
		$this->aColumns = array('ramport_key'=>19, 'unk1'=>13);
		
		if ($table==self::TBL_CUSTOMS_ST)
		$this->aColumns = array('ramport_key'=>33, 'variable'=>90);
		
		if ($table==self::TBL_NOTES)
		$this->aColumns = array('shipment_id'=>7, 'date'=>8, 'time'=>8, 'line_1'=>52, 'line_2'=>76, 'show_to_client'=>1, 'initials'=>5);
	}
	
	
	/**
	 * Import values into ERP models
	 *
	 * @access public
	 * @param string
	 * @param boolean
	 * @return boolean
	 */
	public function import($table, $retry_errors = false) {
		echo "import Function \n";
		global $oTime, $oDb;
		$qry='';
		$this->current_table = $table;

		$r = true;

		$start_ts = date('H:i:s');

		$this->log('Importing: '.$this->current_table.' - '.date('Y-m-d').' '.$start_ts);

		$this->set_columns($this->current_table);

		//if ($retry_errors) {
		//	$where_status = "(( status =".$oDb->sql_value('not_imported').') OR ( status ='.$oDb->sql_value('error').'))';
		//} else {
		//	$where_status = " status =".$oDb->sql_value('not_imported');
		//}
		$total = 0;
		
		if ($table==self::TBL_NOTES) {
			$qry = "SELECT DISTINCT  id ,  line  FROM  shipment_abi_results  ORDER BY  id ;";
		} else {
			$qry = "SELECT DISTINCT  rid ,  line  FROM  shipment_ramport  WHERE  table_name = '".pg_escape_string($this->current_table)."' ORDER BY  rid ;";
		}
		//echo $qry;
		$result = pg_exec($this->link, $qry);
		if (pg_numrows($result)  != 0) {
			while($aLine = pg_fetch_assoc($result)) {
				$this->aRecord = $this->split_line($aLine['line']);
				
				$this->log('ID: '.$aLine['rid'], 2);
				if (($table==self::TBL_FILES) || ($table==self::TBL_SECURITY) || ($table==self::TBL_POS))
				$r = $this->update_shipments($aLine['rid']) && $r;
				
				if ($table==self::TBL_LOGS)
				$r = $this->update_shipments($aLine['rid']) && $r;  // Don't update, only create
				$total++;
			}
		}
		$this->log('total: '.$total);
		$end_ts = date('H:i:s');
		$this->log('End import: '.$this->current_table.' - '.$end_ts);
		return $r;
	}
	
	
	/**
	 * Update fields from Shipments
	 *
	 * @access public
	 * @return boolean
	 */
	private function update_shipments($id = '') {
		echo "Update function\n";
		$dup_result = '';
		$result = '';
		$imp_num = (isset($this->aRecord['importer_number']) ) ? 
			"'".pg_escape_string($this->aRecord['importer_number'])."'" : "''";
		
		$ves_name = (isset($this->aRecord['vessel_name']) ) ? 
			"'".pg_escape_string($this->aRecord['vessel_name'])."'" : "''";
			
		$ves_code = (isset($this->aRecord['vessel_code']) ) ? 
			"'".pg_escape_string($this->aRecord['vessel_code'])."'" : "''";
			
		$voyage = (isset($this->aRecord['voyage_number']) ) ? 
			"'".pg_escape_string($this->aRecord['voyage_number'])."'" : "''";
			
		$scac = (isset($this->aRecord['carrier_code']) ) ? 
			"'".pg_escape_string($this->aRecord['carrier_code'])."'" : "''";
		
		$desc = (isset($this->aRecord['description']) ) ? 
			"'".pg_escape_string($this->aRecord['description'])."'" : "''";
		
		$pkgs = (isset($this->aRecord['no_of_packages']) && $this->aRecord['no_of_packages'] != '') ? 
			"'".pg_escape_string($this->aRecord['no_of_packages'])."'" : "'0'";
		
		$po_num = (isset($this->aRecord['po_number']) ) ? 
			"'".pg_escape_string($this->aRecord['po_number'])."'" : "''";
			
		$entry_num = (isset($this->aRecord['entry_number']) ) ? 
			"'".pg_escape_string($this->aRecord['entry_number'])."'" : "''";
		
		$entry_date = (isset($this->aRecord['entry_date']) && $this->aRecord['entry_date'] != '') ? 
			"'".pg_escape_string($this->aRecord['entry_date'])."'" : "NULL";
			
		$date_3461 = (isset($this->aRecord['date_3461']) && $this->aRecord['date_3461'] != '') ? 
			"'".pg_escape_string($this->aRecord['date_3461'])."'" : "NULL";
			
		$exp_date = (isset($this->aRecord['date_of_exportation']) && $this->aRecord['entry_date'] != '') ? 
			"'".pg_escape_string($this->aRecord['date_of_exportation'])."'" : "NULL";
			
		$est_ves_date = (isset($this->aRecord['est_vessel_arr_date']) && $this->aRecord['entry_date'] != '') ? 
			"'".pg_escape_string($this->aRecord['est_vessel_arr_date'])."'" : "NULL";
		
		$country = (isset($this->aRecord['country_of_origin']) ) ? 
			"'".pg_escape_string($this->aRecord['country_of_origin'])."'" : "''";
		
		$cst = (isset($this->aRecord['cst_number']) ) ? 
			"'".pg_escape_string($this->aRecord['cst_number'])."'" : "0";
		
		//Check if duplicte record doesn't exist then go for insert
		$qry = "SELECT DISTINCT file_number FROM shipment WHERE file_number='".$id."';";
		$dup_result = pg_exec($this->link, $qry);
			
		if (pg_numrows($dup_result) == 0) {
			$sql = " INSERT INTO shipment(  file_number,importer_number,vessel_name,vessel_code,voyage,scac_code,description,packages,reference_num,
											entry_number,entry_date,date_3461,date_of_exportation,est_vessel_arrival_date,old_country_code,cst)
											VALUES( $id,$imp_num,$ves_name,$ves_code,$voyage,$scac,$desc,$pkgs,$po_num,$entry_num,
											$entry_date,$date_3461,$exp_date,$est_ves_date,$country,$cst)";
									
			//echo $sql. "--------------- here \n";		
			$result = pg_exec($this->link, $sql);
			
			if (!$result) {
				$this->log('Failed to insert shipment '. $id);
			}
		} else {
			$sql = " UPDATE shipment SET importer_number = $imp_num,
									    vessel_name =  $ves_name ,
									    vessel_code =  $ves_code ,
										voyage =  $voyage ,
										scac_code =  $scac ,
										description =  $desc ,
										packages =  $pkgs ,
										reference_num =  $po_num ,
										entry_number =  $entry_num ,
										entry_date =  $entry_date ,
										date_3461 =  $date_3461 ,
										date_of_exportation =  $exp_date ,
										est_vessel_arrival_date =  $est_ves_date ,
										old_country_code =  $country ,
										cst =   $cst WHERE file_number =   '$id' ";
			//echo $sql;
			$result = pg_exec($this->link, $sql);
			if (!$result) {
				$this->log('Failed to update shipment '. $id);
			}
		}
	}
	
	
	/**
	* Split line into fields
	*
	* @access private
	* @param string
	* @return array
	*/
	private function split_line($line) {
		echo "split_line function\n";
		$pos = 0;
		$aFields = array();
		foreach ($this->aColumns as $field=>$size) {
		$aFields[$field] = trim(substr($line,$pos,$size));
		$pos += $size;
		}
		
		// Special case: Containers
		if ($this->current_table == Ramport::TBL_CONTAINERS) {
		$aFields['shipment_id'] = substr($line, 0, 7);
		$aFields['code'] = str_replace(' ', '', substr($line, 7, 12));
		}
		
		// Special case: Multiple Purchase Orders for any file
		// if ($this->current_table == Ramport::TBL_POS) {
		// $aFields['ramport_key'] = substr($line, 0, 7);
		// $aFields['po_number'] = str_replace(' ', '', substr($line, 7, 15));
		// }
		return $aFields;
	}
	
	
	/**
	 * Import customs statement
	 *
	 * @access public
	 * @param string
	 * @return object
	 */
	public function import_customs_statement() {
		$qry = '';
		$result = '';
		$aStatement = array();
		$aWhere = array();
		$aStatements = array();
		$search_string = "___________                0_____%";
		$aWhere[] = " rid LIKE '" . pg_escape_string($search_string)."'";
		$aWhere[] = " table_name = '".Ramport::TBL_CUSTOMS_ST."'";
		$qry = "SELECT DISTINCT LEFT(rid, 10) as statement FROM shipment_ramport WHERE (". implode(') AND (', $aWhere) . ") ORDER BY statement;";
		
		$result = pg_exec($this->link, $qry);
		
		if (pg_numrows($result) > 0) {
			while($aLine = pg_fetch_assoc($result)) {
				if (substr($aLine['statement'], 4, 1) != 'P') // ignore monthly statements like "2711P10O66" or "2711P10022", still checking what they are about
				$aStatements[] = $aLine['statement'];	
			}
		} else {
			die('No statement number found');
		}

		foreach($aStatements as $number) {
			$aShipments = array();
			
			// Try to get first the Final statement
			$qry = "SELECT DISTINCT rid, line FROM shipment_ramport WHERE table_name ='".Ramport::TBL_CUSTOMS_ST."' AND rid LIKE '" . pg_escape_string($number . 'F %') . "' ORDER BY rid;";
			$result = pg_exec($this->link, $qry);
			
			// If no result, try the Preliminary statement
			if (pg_numrows($result) == 0) {
				$qry = "SELECT DISTINCT rid, line FROM shipment_ramport WHERE table_name ='".Ramport::TBL_CUSTOMS_ST."' AND rid LIKE '" . pg_escape_string($number . 'P %') . "' ORDER BY rid;";
				$result = pg_exec($this->link, $qry);
			}
			
			$oStatement = new Options();
			while($aList = pg_fetch_assoc($result)) {
				$line = $aList['line'];
				$type = substr($line, 33, 1);
				$statement = substr($line, 0, 10);
				$statement_status = substr($line, 10, 1);
				
				if (! $oStatement->statement) {
					$oStatement->statement = $statement;
					$oStatement->statement_status = ($statement_status=='F') ? 'Final' : 'Preliminary';
					$oStatement->type = $statement_status;
				}
				
				if ($type == 'A')
				$oStatement->process_date = '20' . substr($line, 51, 2) . '-' . substr($line, 47, 2) . '-' . substr($line, 49, 2);
				//$oStatement->payment_due_date = '20' . substr($line, 51, 2) . '-' . substr($line, 47, 2) . '-' . substr($line, 49, 2);
				
				if ($type == 'B') {
					$oStatement->payment_due_date	= '20' . substr($line, 60, 2) . '-' . substr($line, 56, 2) . '-' . substr($line, 58, 2);
					$oStatement->port			= trim(substr($line, 36, 4));
					$oStatement->broker			= trim(substr($line, 40, 3));
					$oStatement->importer		= trim(substr($line, 63, 10));
				}
				
				if ($type == 'Q') {
					$subtype = trim(substr($line, 34, 1));
					
					if (in_array($subtype, array(1, 2, 'A'))) {
						$shipment_id = intval(substr($line, 43, 7));
						if (! isset($aShipments[$shipment_id]))
						$aShipments[$shipment_id] = new Options();
						
						$aShipments[$shipment_id]->entry_number = substr($line, 39, 3) . '-' . substr($line, 43, 7) . '-' . substr($line, 50, 1);
					}
					
					if ($subtype == 1) {
						$aShipments[$shipment_id]->estimate_duty = $this->cents_to_dollars(substr($line, 51, 11));
						$aShipments[$shipment_id]->estimate_tax = $this->cents_to_dollars(substr($line, 62, 11));
						$aShipments[$shipment_id]->estimate_ada = $this->cents_to_dollars(substr($line, 88, 8)); // Not sure if its only 8 digits, might be 11 but shouldn't mather, end position is correct.
						$aShipments[$shipment_id]->ref_number = substr($line, 96, 7); // Must be same as the shipment_id
						$aShipments[$shipment_id]->entry_type = substr($line, 111, 2);
					}
					
					if ($subtype == 2) {
						$aShipments[$shipment_id]->unk4_fee = $this->cents_to_dollars(substr($line, 98, 8)); // Statement 2711025J16
						$aShipments[$shipment_id]->status = trim(substr($line, 106, 1));
						if ($aShipments[$shipment_id]->status == 'P') $aShipments[$shipment_id]->status = 'PA'; // Display like Ramport does
									 else {
							$aShipments[$shipment_id]->status = trim(substr($line, 107, 1));
							if ($aShipments[$shipment_id]->status == 1) $aShipments[$shipment_id]->status = 'CE'; // Display Census Error like Ramport does
						}
							}
						
					if ($subtype == 'A') {
						$aShipments[$shipment_id]->merchandise_fee = $this->cents_to_dollars(substr($line, 67, 8));
						$aShipments[$shipment_id]->merc_surcharge_fee = $this->cents_to_dollars(substr($line, 75, 8));
						$aShipments[$shipment_id]->waterways_fee = $this->cents_to_dollars(substr($line, 83, 8));
						$aShipments[$shipment_id]->cotton_fee = $this->cents_to_dollars(substr($line, 103, 8)); // Must find out what is this (statement 0409233165)
					}
						
					if ($subtype == 'B') {
						$aShipments[$shipment_id]->interest = $this->cents_to_dollars(substr($line, 51, 8));
						$aShipments[$shipment_id]->potato_fee = $this->cents_to_dollars(substr($line, 67, 8));
					}
					
					if ($subtype == 'C')
					$aShipments[$shipment_id]->sorghum_fee = $this->cents_to_dollars(substr($line, 75, 8));
	
					// Totals:
	
					if ($subtype == 3) {
						$oStatement->estimate_duty 	= $this->cents_to_dollars(substr($line, 58, 11));
						$oStatement->estimate_tax 	= $this->cents_to_dollars(substr($line, 69, 11));
						$oStatement->estimate_unk1 	= $this->cents_to_dollars(substr($line, 80, 11));
						$oStatement->estimate_unk2 	= $this->cents_to_dollars(substr($line, 91, 11));
						$oStatement->estimate_ada 	= $this->cents_to_dollars(substr($line, 102, 11));
					}
					
					if ($subtype == 4) {
						$oStatement->cotton_fee		= $this->cents_to_dollars(substr($line, 42, 11));
						$oStatement->unk1_fee 		= $this->cents_to_dollars(substr($line, 53, 11));
						$oStatement->unk2_fee 		= $this->cents_to_dollars(substr($line, 64, 11));
						$oStatement->unk3_fee 		= $this->cents_to_dollars(substr($line, 75, 11));
						$oStatement->total 			= $this->cents_to_dollars(substr($line, 86, 11));
						$oStatement->revenue_producing_entries = intval(substr($line, 97, 5));
					}
					
					//if ($subtype == '5')
					if ($subtype == '6')
					$oStatement->interest = $this->cents_to_dollars(substr($line, 56, 8));
					
					if ($subtype == 'E') {
						$oStatement->unk4_fee = $this->cents_to_dollars(substr($line, 43, 8));
						$oStatement->merchandise_fee = $this->cents_to_dollars(substr($line, 67, 11));
						$oStatement->merc_surcharge_fee = $this->cents_to_dollars(substr($line, 78, 9));
						$oStatement->waterways_fee = $this->cents_to_dollars(substr($line, 87, 9));
					}
					
					if ($subtype == 'F')
					$oStatement->potato_fee = $this->cents_to_dollars(substr($line, 52, 8));
					
					
					if ($subtype == 'G')
					$oStatement->sorghum_fee = $this->cents_to_dollars(substr($line, 61, 8));
					//if ($subtype == 'J')
					//if ($subtype == 'K')
				}
				//if ($type == 'Y')
				//if ($type == 'Z')
			}
			
			if (isset($aShipments[0])) unset($aShipments[0]);
			$oStatement->shipments = $aShipments;
	
			foreach($oStatement->shipments as $oDuty) {
				$oDuty->other_fees = number_format($oDuty->unk4_fee + $oDuty->cotton_fee + $oDuty->potato_fee + $oDuty->sorghum_fee, 2, '.', '');
				$oDuty->fees = number_format($oDuty->merchandise_fee + $oDuty->merc_surcharge_fee + $oDuty->waterways_fee + $oDuty->interest + $oDuty->other_fees, 2, '.', '');
				$oDuty->total = number_format($oDuty->estimate_duty + $oDuty->estimate_tax + $oDuty->estimate_ada + $oDuty->fees, 2, '.', '');
			}
			
			// Set total Fees
			$oStatement->other_fees = number_format($oStatement->unk4_fee + $oStatement->cotton_fee + $oStatement->potato_fee + $oStatement->sorghum_fee, 2, '.', '');
			$oStatement->fees = number_format($oStatement->merchandise_fee + $oStatement->merc_surcharge_fee + $oStatement->waterways_fee + $oStatement->interest + $oStatement->other_fees, 2, '.', '');
			// Do check sum to validade amounts (maybe we missed something)
			$oStatement->check_sum = ($oStatement->revenue_producing_entries <= count($oStatement->shipments));
			if ($oStatement->check_sum) {
				$aFields = array('estimate_duty'=>0, 'estimate_tax'=>0, 'estimate_ada'=>0, 'merchandise_fee'=>0, 'merc_surcharge_fee'=>0, 'waterways_fee'=>0, 'fees'=>0, 'total'=>0);
				foreach ($oStatement->shipments as $oDuty)
				foreach($aFields as $var=>$value)
				$aFields[$var] += $oDuty->$var;
				foreach($aFields as $var=>$value)
				if ((string) $oStatement->$var != (string) $value) {
					$oStatement->check_sum = false;
					$name = 'check_sum_' . $var;
					$oStatement->$name = $value;
				}
			}
			//if (count($oStatement->shipments) > 1) {
			//	echo "<pre>"; print_r($oStatement); exit;
			//}
			$this->save_statement($oStatement);
		}
	}
	
	
	/**
	 * Save customs statement
	 *
	 * @access public
	 * @param object
	 * @return str
	 */
	public function save_statement ($oStatement) {
		//Insert entry id into entries table for relation
		//Avoid duplicacy
		//Extract statement info from the object
		$name = ($oStatement->statement.trim($oStatement->type) != '') ? "'".pg_escape_string($oStatement->statement.trim($oStatement->type))."'" : "NULL";
		$invoice_number = ($oStatement->statement != '') ?  "'".pg_escape_string($oStatement->statement)."'" : "NULL";
		$type = ($oStatement->statement_status != '') ?  "'".pg_escape_string($oStatement->statement_status)."'" : "NULL";
		$date = ($oStatement->process_date != '') ?  "'".pg_escape_string($oStatement->process_date)."'" : "NULL";
		$est_dty = ($oStatement->estimate_duty != '') ? "'".pg_escape_string($oStatement->estimate_duty)."'"  : 0.00;
		$merc_fee = ($oStatement->merchandise_fee != '') ? "'".pg_escape_string($oStatement->merchandise_fee)."'" : 0.00;
		$merc_surc_fee = ($oStatement->merc_surcharge_fee != '') ? "'".pg_escape_string($oStatement->merc_surcharge_fee)."'" : 0.00;
		$ww_fee = ($oStatement->waterways_fee != '') ? "'".pg_escape_string($oStatement->waterways_fee)."'" : 0.00;
		$tot_amt_due = ($oStatement->total != '') ? "'".pg_escape_string($oStatement->total)."'" : 0.00;
		
		//Insert/Update statement info
		$insert_sql = "INSERT INTO shipment_statements(name, inv_num, type, date, tot_est_duty, merc_fee, merc_sur_fee, ww_fee, tot_amt_due)
						VALUES($name, $invoice_number, $type, $date, $est_dty, $merc_fee, $merc_surc_fee, $ww_fee, $tot_amt_due)
						RETURNING id;";
								
		//echo $insert_sql; exit;
		$insert_result = pg_exec($this->link, $insert_sql);
		$entry_id  = 0;

		if (pg_numrows($insert_result) > 0) {
			$aTmp = pg_fetch_assoc($insert_result);
			$entry_id = $aTmp['id'];
		}
		
		if(!$insert_result) {
			die('Error Inserting Statement Record'); exit;
		} else {
			echo $name.' inserted'."</br>";
		}

		//Extract line items for the statements
		foreach ($oStatement->shipments as $key => $oShipment) {
			$entry = ($oShipment->entry_number != '') ?  "'".pg_escape_string($oShipment->entry_number)."'" : "NULL";
			$TP = ($oShipment->entry_type != '') ?  "'".pg_escape_string($oShipment->entry_type)."'" : "NULL";
			$ST = ($oShipment->status != '') ?  "'".pg_escape_string($oShipment->status)."'" : "NULL";
			$estd_dty = ($oShipment->estimate_duty != '') ? $oShipment->estimate_duty : 0.00;
			$est_tax = ($oShipment->estimate_tax != '') ? $oShipment->estimate_tax: 0.00;
			$est_cvd = ($oShipment->estimate_cvd != '') ? "'".pg_escape_string($oShipment->estimate_cvd)."'" : 0.00;
			$est_ada = ($oShipment->estimate_ada != '') ? $oShipment->estimate_ada : 0.00;
			$usr_fee = ($oShipment->fees != '') ? $oShipment->fees : 0.00;
			$tot_amt = ($oShipment->total != '') ? $oShipment->total : 0.00;
			$dty_status = ($oShipment->duty_status != '') ? $oShipment->duty_status : 0.00;
			
			$insert_ent_sql = "INSERT INTO shipment_statements_entries(entry_num, tp, st, est_duty, est_tax, est_cvd, est_ada, usr_fee, tot_amt, dty_st, entry_id)
							   VALUES($entry, $TP, $ST, $estd_dty, $est_tax, $est_cvd, $est_ada, $usr_fee, $tot_amt, $dty_status, $entry_id);";
			
			//echo $insert_ent_sql; exit;
			$insert_ent_result = pg_exec($this->link, $insert_ent_sql);
			
			if(!$insert_ent_result) {
				die('Error Inserting Statement Entry Record'); exit;
			} else {
				echo $name.' entry inserted'."</br>";
			}
		}
	}
	
}


/**
 * Generic class to store options
 *
 * @author Mindfire
 * @version 1.0
 * @package pts
 * @subpackage core
 */
class Options {
	private $aParam = array();

	function __construct($aParam = array()) {
		$this->aParam = $aParam;
	}

	/**
	 * Magical method called when accessing inexistent property
	 *
	 *
	 * @access public
	 * @param string
	 */
	public function __set($name, $value) {
		$this->aParam[$name] = $value; // Ex: $o->name = 'John Doe';
	}

	/**
	 * Magical method called when accessing inexistent property
	 *
	 *
	 * @access public
	 * @param string
	 */
	public function __get($name) {
		return isset($this->aParam[$name]) ? $this->aParam[$name] : ''; // Ex: echo $o->name;
	}
}
