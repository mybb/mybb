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
	foreach($tables as $tablename)
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
	$db->optimize_table($tablelist);
	$db->analyze_table($tablelist);
	cpmessage($lang->dbmaint_done);
}

if($mybb->input['action'] == "dbmaint")
{
	cpheader();
	startform("misc.php", "" , "do_dbmaint");
	starttable();
	tableheader($lang->dbmaint);
	$button = makebuttoncode("dbmaintsubmit", $lang->proceed);
	makelabelcode("<div align=\"center\">$lang->dbmaint_notice<br /><br />$button</div>");
	endtable();
	endform();
	cpfooter();
}
?>