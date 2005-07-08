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
$lang->load("profilefields");

addacpnav($lang->nav_profile_fields, "profilefields.php");

switch($action)
{
	case "add":
		addacpnav($lang->nav_add_field);
		break;
	case "delete":
		addacpnav($lang->nav_delete_field);
		break;
	case "edit":
		addacpnav($lang->nav_edit_field);
		break;
}
checkadminpermissions("caneditpfields");
logadmin();

if($action == "do_add") {
	if($type != "text" && $type != "textarea") {
		$thing = "$type\n$options";
	} else {
		$thing = $type;
	}
	$thing = addslashes($thing);
	$name = addslashes($_POST['name']);
	$description = addslashes($_POST['description']);
	$length = intval($_POST['length']);
	$maxlength = intval($_POST['maxlength']);
	$db->query("INSERT INTO ".TABLE_PREFIX."profilefields (fid,name,description,disporder,type,length,maxlength,required,editable,hidden) VALUES (NULL,'$name','$description','$disporder','$thing','$length','$maxlength','$required','$editable','$hidden')");
	$fid = $db->insert_id();
	$fieldname = "fid$fid";
	$db->query("ALTER TABLE ".TABLE_PREFIX."userfields ADD $fieldname TEXT NOT NULL");
	$db->query("OPTIMIZE TABLE ".TABLE_PREFIX."userfields");
	cpredirect("profilefields.php", $lang->field_added);
}
if($action == "do_delete") {
	if($deletesubmit) {	
		$db->query("DELETE FROM ".TABLE_PREFIX."profilefields WHERE fid='$fid'");
		$fieldname = "fid$fid";
		$db->query("ALTER TABLE ".TABLE_PREFIX."userfields DROP $fieldname");
		$db->query("OPTIMIZE TABLE ".TABLE_PREFIX."userfields");
		cpredirect("profilefields.php", $lang->field_deleted);
	} else {
		$action = "modify";
	}
}

if($action == "do_edit") {
	if($type != "text" && $type != "textarea") {
		$thing = "$type\n$options";
	} else {
		$thing = $type;
	}
	$thing = addslashes($thing);
	$name = addslashes($_POST['name']);
	$description = addslashes($_POST['description']);
	$length = intval($_POST['length']);
	$maxlength = intval($_POST['maxlength']);
	$db->query("UPDATE ".TABLE_PREFIX."profilefields SET name='$name', description='$description', disporder='$disporder', type='$thing', length='$length', maxlength='$maxlength', required='$required', editable='$editable', hidden='$hidden' WHERE fid='$fid'");
	cpredirect("profilefields.php", $lang->field_updated);
}
if($action == "add") {
	cpheader();
	startform("profilefields.php", "" , "do_add");
	starttable();
	tableheader($lang->new_custom_field);
	makeinputcode($lang->field_name, "name");
	maketextareacode($lang->field_description, "description");
	makeinputcode($lang->field_max_length, "maxlength");
	makeinputcode($lang->field_length, "length", 20);
	makeinputcode($lang->field_disporder, "disporder", "", 4);
	makelabelcode($lang->field_type, "<select name=\"type\"><option value=\"text\">$lang->field_type_textbox</option><option value=\"textarea\">$lang->field_type_textarea</option><option value=\"select\">$lang->field_type_select</option><option value=\"multiselect\">$lang->field_type_multiselect</option><option value=\"radio\">$lang->field_type_radio</option><option value=\"checkbox\">$lang->field_type_checkbox</option></select>");
	maketextareacode($lang->field_options, "options", "", 6, 50);
	makeyesnocode($lang->field_required, "required", "no");
	makeyesnocode($lang->field_editable, "editable", "yes");
	makeyesnocode($lang->field_hidden, "hidden", "no");
	endtable();
	endform($lang->add_field, $lang->reset_button);
	cpfooter();
}
if($action == "delete") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields WHERE fid='$fid'");
	$profilefield = $db->fetch_array($query);
	cpheader();
	startform("profilefields.php", "", "do_delete");
	makehiddencode("fid", $fid);
	starttable();
	$lang->delete_field = sprintf($lang->delete_field, $profilefield[name]);
	tableheader($lang->delete_field, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	$lang->delete_confirm = sprintf($lang->delete_confirm, $profilefield[name]);
	makelabelcode("<center>$lang->delete_confirm<br><br>$yes$no</center>", "");
	endtable();
	endform();
	cpfooter();
}
if($action == "edit") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields WHERE fid='$fid'");
	$profilefield = $db->fetch_array($query);

	$profilefield['name'] = stripslashes($profilefield[name]);
	$profilefield['description'] = stripslashes($profilefield[description]);
	$profilefield['type'] = stripslashes($profilefield[type]);

	$type = explode("\n", $profilefield[type], "2");
	$typesel[$type[0]] = "selected";
	$options = $type[1];

	cpheader();
	startform("profilefields.php", "" , "do_edit");
	makehiddencode("fid", $profilefield['fid']);
	starttable();
	$lang->edit_custom_field = sprintf($lang->edit_custom_field, $profilefield[name]);
	tableheader($lang->edit_custom_field);
	makeinputcode($lang->field_name, "name", "$profilefield[name]");
	maketextareacode($lang->field_description, "description", $profilefield[description]);
	makeinputcode($lang->field_max_length, "maxlength", $profilefield[maxlength]);
	makeinputcode($lang->field_length, "length", $profilefield[length]);
	makeinputcode($lang->field_disporder, "disporder", $profilefield[disporder], 4);
	makelabelcode($lang->field_type, "<select name=\"type\"><option value=\"text\" $typesel[text]>$lang->field_type_textbox</option><option value=\"textarea\" $typesel[textarea]>$lang->field_type_textarea</option><option value=\"select\" $typesel[select]>$lang->field_type_select</option><option value=\"multiselect\" $typesel[multiselect]>$lang->field_type_multiselect</option><option value=\"radio\" $typesel[radio]>$lang->field_type_radio</option><option value=\"checkbox\" $typesel[checkbox]>$lang->field_type_checkbox</option></select>");
	maketextareacode($lang->field_options, "options", $options, 6, 50);
	makeyesnocode($lang->field_required, "required", $profilefield[required]);
	makeyesnocode($lang->field_editable, "editable", $profilefield[editable]);
	makeyesnocode($lang->field_hidden, "hidden", $profilefield[hidden]);
	endtable();
	endform($lang->edit_field, $lang->reset_button);
	cpfooter();
}
if($action == "modify" || $action == "") {
	if(!$noheader) {
		cpheader();
	}
	$hopto[] = "<input type=\"button\" value=\"$lang->create_profilefield\" onclick=\"hopto('profilefields.php?action=add');\" class=\"hoptobutton\">";
	makehoptolinks($hopto);

	starttable();
	tableheader($lang->profile_fields, "", "6");
	echo "<tr>\n";
	echo "<td class=\"subheader\">$lang->name</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->id</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->required</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->editable</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->hidden</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->controls</td>\n";
	echo "</tr>\n";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields ORDER BY disporder");
	while($profilefield = $db->fetch_array($query)) {
		$bgcolor = getaltbg();
		startform("profilefields.php");
		makehiddencode("fid", $profilefield['fid']);
		$profilefield['required'] = ($profilefield['required'] == "yes") ? $lang->yes : $lang->no;
		$profilefield['editable'] = ($profilefield['editable'] == "yes") ? $lang->yes : $lang->no;
		$profilefield['hidden'] = ($profilefield['hidden'] == "yes") ? $lang->yes : $lang->no;
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\">$profilefield[name]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\">$profilefield[fid]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\">$profilefield[required]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\">$profilefield[editable]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\">$profilefield[hidden]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"right\"><select name=\"action\"><option value=\"edit\">$lang->edit_field</option><option value=\"delete\">$lang->delete_field</option></select>&nbsp;<input type=\"submit\" value=\"$lang->go\"></td>\n";
		echo "</tr>\n";
		endform();
	}
	endtable();
	cpfooter();
}

?>