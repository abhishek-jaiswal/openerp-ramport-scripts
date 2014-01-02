<?php
require('ramport_openerp.php');

#$ramport_folder = 'sample/';
#$ramport_folder_2 = 'sample/'; // For FDADATA.TXT in the wrong place.
$ramport_folder = '/mnt/nas/customs/FILES/';
$ramport_folder_2 = '/mnt/nas/customs/PROGRAMS/'; // For FDADATA.TXT in the wrong place.
$retry_errors = false;

// Check if at least Clients' file is present:
if (! file_exists($ramport_folder.Ramport::TBL_CUSTOMERS))
echo 'Ramport files not found.';

$Ramport = new Ramport();

$Ramport->load_to_cache(Ramport::TBL_PORTS_LADING, 		$ramport_folder);
$Ramport->load_to_cache(Ramport::TBL_PORTS_UNLADING, 	$ramport_folder);
$Ramport->load_to_cache(Ramport::TBL_CUSTOMERS, 		$ramport_folder);
$Ramport->load_to_cache(Ramport::TBL_SECURITY, 			$ramport_folder);
$Ramport->load_to_cache(Ramport::TBL_FILES, 			$ramport_folder);
$Ramport->load_to_cache(Ramport::TBL_LOGS, 				$ramport_folder); 
$Ramport->load_to_cache(Ramport::TBL_POS, 				$ramport_folder); 
$Ramport->load_to_cache(Ramport::TBL_ITNOFILE, 			$ramport_folder);
$Ramport->load_to_cache(Ramport::TBL_ENTRY, 			$ramport_folder);
$Ramport->load_to_cache(Ramport::TBL_FDADATA, 			$ramport_folder_2);
$Ramport->load_to_cache(Ramport::TBL_CONTAINERS, 		$ramport_folder);
$Ramport->load_to_cache(Ramport::TBL_NOTES, 			$ramport_folder);
$Ramport->load_to_cache(Ramport::TBL_CUSTOMS_ST, 		$ramport_folder);
$Ramport->load_to_cache(Ramport::TBL_FIRMS, 		    $ramport_folder);
$Ramport->load_to_cache(Ramport::TBL_MIDS, 		        $ramport_folder);

$Ramport->import(Ramport::TBL_PORTS_LADING, 	$retry_errors);
$Ramport->import(Ramport::TBL_PORTS_UNLADING, 	$retry_errors);
//$Ramport->import(Ramport::TBL_CUSTOMERS, 		$retry_errors);
//$Ramport->import(Ramport::TBL_VENDORS, 		$retry_errors);
//$Ramport->import(Ramport::TBL_LOGS, 			$retry_errors); // Shipments
$Ramport->import(Ramport::TBL_POS, 				$retry_errors); // Shipments
$Ramport->import(Ramport::TBL_SECURITY, 		$retry_errors); // Shipments
$Ramport->import(Ramport::TBL_FILES, 			$retry_errors); // Shipments
$Ramport->import(Ramport::TBL_ITNOFILE, 		$retry_errors); // BOLs
$Ramport->import(Ramport::TBL_ENTRY, 			$retry_errors); // Products
$Ramport->import(Ramport::TBL_FDADATA, 			$retry_errors);
$Ramport->import(Ramport::TBL_CONTAINERS, 		$retry_errors);
$Ramport->import(Ramport::TBL_NOTES, 			$retry_errors);
$Ramport->import(Ramport::TBL_FIRMS,     		$retry_errors);
$Ramport->import(Ramport::TBL_MIDS,     		$retry_errors);

$t1 = date('H:i:s');
$h1 = substr($time, 0, 2);

$end_ts = date('H:i:s');
echo "End: ". $end_ts ;
