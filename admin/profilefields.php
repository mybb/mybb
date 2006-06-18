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
$lang->load("profilefields");

addacpnav($lang->nav_profile_fields, "profilefields.php?".SID);

switch($mybb->input['action'])
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

$plugins->run_hooks("admin_profilefields_start");

if($mybb->input['action'] == "do_add")
{
	$type = $mybb->input['type'];
	if($type != "text" && $type != "textarea")
	{
		$thing = "$type\n$options";
	}
	else
	{
		$thing = $type;
	}
	$sqlarray = array(
		"name" => $db->escape_string($mybb->input['name']),
		"description" => $db->escape_string($mybb->input['description']),
		"disporder" => intval($mybb->input['disporder']),
		"type" => $db->escape_string($thing),
		"length" => intval($mybb->input['length']),
		"maxlength" => intval($mybb->input['maxlength']),
		"required" => $db->escape_string($mybb->input['required']),
		"editable" => $db->escape_string($mybb->input['editable']),
		"hidden" => $db->escape_string($mybb->input['hidden']),
		);
	$plugins->run_hooks("admin_profilefields_do_ad");
	$db->insert_query(TABLE_PREFIX."profilefields", $sqlarray);
	$fid = $db->insert_id();
	$fieldname = "fid$fid";
	$db->query("
		ALTER 
		TABLE ".TABLE_PREFIX."userfields 
		ADD $fieldname TEXT
	");
	$db->optimize_table(TABLE_PREFIX."userfields");
	cpredirect("profilefields.php?".SID, $lang->field_added);
}
if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{	
		$fid = intval($mybb->input['fid']);
		$plugins->run_hooks("admin_profilefields_do_delete");
		$db->delete_query(TABLE_PREFIX."profilefields", "fid='$fid'");
		$fieldname = "fid$fid";
		$db->query("
			ALTER 
			TABLE ".TABLE_PREFIX."userfields 
			DROP $fieldname
		");
		$db->optimize_table(TABLE_PREFIX."userfields");
		cpredirect("profilefields.php?".SID, $lang->field_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "do_edit")
{
	$type = $mybb->input['type'];
	if($type != "text" && $type != "textarea")
	{
		$thing = "$type\n$options";
	}
	else
	{
		$thing = $type;
	}
	$sqlarray = array(
		"name" => $db->escape_string($mybb->input['name']),
		"description" => $db->escape_string($mybb->input['description']),
		"disporder" => intval($mybb->input['disporder']),
		"type" => $db->escape_string($thing),
		"length" => intval($mybb->input['length']),
		"maxlength" => intval($mybb->input['maxlength']),
		"required" => $db->escape_string($mybb->input['required']),
		"editable" => $db->escape_string($mybb->input['editable']),
		"hidden" => $db->escape_string($mybb->input['hidden']),
		);
	$plugins->run_hooks("admin_profilefields_do_edit");
	$db->update_query(TABLE_PREFIX."profilefields", $sqlarray, "fid='".intval($mybb->input['fid'])."'");
	cpredirect("profilefields.php?".SID, $lang->field_updated);
}
if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_profilefields_add");
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
if($mybb->input['action'] == "delete")
{
	$fid = intval($mybb->input['fid']);
	$query = $db->simple_select(TABLE_PREFIX."profilefields", "*", "fid='$fid'");
	$profilefield = $db->fetch_array($query);
	$plugins->run_hooks("admin_profilefields_delete");
	cpheader();
	startform("profilefields.php", "", "do_delete");
	makehiddencode("fid", $fid);
	starttable();
	$lang->delete_field = sprintf($lang->delete_field, $profilefield['name']);
	tableheader($lang->delete_field, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	$lang->delete_confirm = sprintf($lang->delete_confirm, $profilefield['name']);
	makelabelcode("<div align=\"center\">$lang->delete_confirm<br /><br />$yes$no</div>", "");
	endtable();
	endform();
	cpfooter();
}
if($mybb->input['action'] == "edit")
{
	$fid = intval($mybb->input['fid']);
	$query = $db->simple_select(TABLE_PREFIX."profilefields", "*", "fid='$fid'");
	$profilefield = $db->fetch_array($query);

	$profilefield['name'] = stripslashes($profilefield['name']);
	$profilefield['description'] = stripslashes($profilefield['description']);
	$profilefield['type'] = stripslashes($profilefield['type']);

	$type = explode("\n", $profilefield['type'], "2");
	$typesel[$type[0]] = "selected";
	$options = $type[1];

	$plugins->run_hooks("admin_profilefields_edit");
	
	cpheader();
	startform("profilefields.php", "" , "do_edit");
	makehiddencode("fid", $profilefield['fid']);
	starttable();
	$lang->edit_custom_field = sprintf($lang->edit_custom_field, $profilefield['name']);
	tableheader($lang->edit_custom_field);
	makeinputcode($lang->field_name, "name", "$profilefield[name]");
	maketextareacode($lang->field_description, "description", $profilefield['description']);
	makeinputcode($lang->field_max_length, "maxlength", $profilefield['maxlength']);
	makeinputcode($lang->field_length, "length", $profilefield['length']);
	makeinputcode($lang->field_disporder, "disporder", $profilefield['disporder'], 4);
	makelabelcode($lang->field_type, "<select name=\"type\"><option value=\"text\" $typesel[text]>$lang->field_type_textbox</option><option value=\"textarea\" $typesel[textarea]>$lang->field_type_textarea</option><option value=\"select\" $typesel[select]>$lang->field_type_select</option><option value=\"multiselect\" $typesel[multiselect]>$lang->field_type_multiselect</option><option value=\"radio\" $typesel[radio]>$lang->field_type_radio</option><option value=\"checkbox\" $typesel[checkbox]>$lang->field_type_checkbox</option></select>");
	maketextareacode($lang->field_options, "options", $options, 6, 50);
	makeyesnocode($lang->field_required, "required", $profilefield['required']);
	makeyesnocode($lang->field_editable, "editable", $profilefield['editable']);
	makeyesnocode($lang->field_hidden, "hidden", $profilefield['hidden']);
	endtable();
	endform($lang->edit_field, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	$plugins->run_hooks("admin_profilefields_modify");
	if(!$noheader)
	{
		cpheader();
	}
	$hopto[] = "<input type=\"button\" value=\"$lang->create_profilefield\" onclick=\"hopto('profilefields.php?".SID."&action=add');\" class=\"hoptobutton\">";
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
	$options = array(
		"order_by" => "disporder"
	);
	$query = $db->simple_select(TABLE_PREFIX."profilefields", "*", "", $options);
	while($profilefield = $db->fetch_array($query))
	{
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