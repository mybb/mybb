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
$lang->load("mycode");

//checkadminpermissions("caneditmycode");
//logadmin();

addacpnav($lang->nav_mycode, "mycode.php?action=modify");

switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_mycode);
		break;
	case "edit":
		addacpnav($lang->nav_edit_mycode);
		break;
	case "delete":
		addacpnav($lang->nav_delete_mycode);
		break;
}

/* Present form to add a mycode */
if($mybb->input['action'] == "add")
{
	cpheader();
	startform("mycode.php", "", "do_add");
	makehiddencode("cid", $cid);
	starttable();
	tableheader($lang->add_mycode);
	makeinputcode($lang->mycode_title_label, "title");
	maketextareacode($lang->mycode_description_label, "description");
	maketextareacode($lang->mycode_regex_label, "regex", "", "4", "80");
	maketextareacode($lang->mycode_replacement_label, "replacement", "", "4", "80");
	makeyesnocode($lang->mycode_active_label, "active", "yes");
	endtable();
	endform($lang->insert_mycode);
	cpfooter();
}

/* Add a mycode */
if($mybb->input['action'] == "do_add")
{
	cpheader();
	
	cpfooter();
}

?>