<?php
// Board Name: XMB

class convert_xmb {
	var $bbname = "XMB 1.8";
	var $modules = array("0" => array("name" => "Source Database Configuration",
									  "function" => "db_configuration",
									  "dependancies" => ""),
						 "1" => array("name" => "Import XMB Users",
									  "function" => "import_users",
									  "dependancies" => "0")
						);

	function db_configuration()
	{
		global $output;
		
		echo "We're in the db config module!";
	}
	
	function import_users()
	{
		echo "We're in the import users module!";
	}
}


?>