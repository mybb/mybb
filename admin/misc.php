<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

require "./global.php";

// Load language packs for this section
global $lang;
$lang->load("misc");

checkadminpermissions("canrunmaint");
logadmin();

if($mybb->input['action'] == "dbmaint")
{
	addacpnav($lang->dbmaint);
}

if($mybb->input['action'] == "do_dbmaint")
{
	$tablelist = "";
	$tables = $db->list_tables($config['database']);
	while(list($tablename) = $db->fetch_array($tables, ""))
	{
		if(!$tablelist)
		{
			$tablelist = $tablename;
		}
		else
		{
			$tablelist .= "," . $tablename;
		}
	}
	$db->query("OPTIMIZE TABLE $tablelist");
	$db->query("ANALYZE TABLE $tablelist");
	cpmessage($lang->dbmaint_done);
}

if($mybb->input['action'] == "dbmaint")
{
	cpheader();
	startform("misc.php", "" , "do_dbmaint");
	starttable();
	tableheader($lang->dbmaint);
	$button = makebuttoncode("dbmaintsubmit", $lang->proceed);
	makelabelcode("<center>$lang->dbmaint_notice<br><br>$button</center>");
	endtable();
	endform();
	cpfooter();
}
?>