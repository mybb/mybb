<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */
 
// Board Name: XMB

class Convert_xmb extends Converter {
	var $bbname = "XMB 1.8";
	var $modules = array("0" => array("name" => "Source Database Configuration",
									  "function" => "db_configuration",
									  "dependencies" => ""),
						 "1" => array("name" => "Import XMB Users",
									  "function" => "import_users",
									  "dependencies" => "0")
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