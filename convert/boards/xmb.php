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
	var $modules = array("db_configuration" => array("name" => "Source Database Configuration",
									  "dependencies" => ""),
						 "import_users" => array("name" => "Import XMB Users",
									  "dependencies" => "")
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