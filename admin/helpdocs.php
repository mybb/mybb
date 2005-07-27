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
$lang->load("helpdocs");

checkadminpermissions("canedithelp");
logadmin();

addacpnav($lang->nav_helpdocs, "helpdocs.php?action=modify");

switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_helpdoc);
		break;
	case "edit":
		addacpnav($lang->nav_edit_helpdoc);
		break;
	case "delete":
		addacpnav($lang->nav_delete_helpdoc);
		break;
}

if($mybb->input['action'] == "do_add")
{
	if($mybb->input['add'] == "doc")
	{
		$sqlarray = array(
			"sid" => intval($mybb->input['sid']),
			"name" => addslashes($mybb->input['name']),
			"description" => addslashes($mybb->input['description']),
			"document" => addslashes($mybb->input['document']),
			"usetranslation" => addslashes($mybb->input['usetranslation']),
			"enabled" => addslashes($mybb->input['enabled']),
			"disporder" => intval($mybb->input['disporder']),
			);
		$db->insert_query(TABLE_PREFIX."helpdocs", $sqlarray);
		cpredirect("helpdocs.php", $lang->doc_added);
	}
	elseif($mybb->input['add'] == "section")
	{
		$sqlarray = array(
			"name" => addslashes($mybb->input['name']),
			"description" => addslashes($mybb->input['description']),
			"usetranslation" => addslashes($mybb->input['usetranslation']),
			"enabled" => addslashes($mybb->input['enabled']),
			"disporder" => intval($mybb->input['disporder']),
			);
		$db->insert_query(TABLE_PREFIX."helpsections", $sqlarray);
		cpredirect("helpdocs.php", $lang->section_added);
	}
}

if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{	
		if(!empty($mybb->input['hid']))
		{
			$db->query("DELETE FROM ".TABLE_PREFIX."helpdocs WHERE hid='".intval($mybb->input['hid'])."'");
			cpredirect("helpdocs.php", $lang->doc_deleted);
		}
		elseif(!empty($mybb->input['sid'])])
		{
			$sid = intval($mybb->input['sid']);
			$db->query("DELETE FROM ".TABLE_PREFIX."helpsections WHERE sid='".$sid."'");
			$db->query("DELETE FROM ".TABLE_PREFIX."helpdocs WHERE sid='".$sid."' AND hid>'7'");
			// Move back any defaults left without a category
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."helpdocs WHERE sid='".$sid."'");
			while($doc = $db->fetch_array($query))
			{
				if($doc['hid'] <= 4)
				{
					$newsid = "1";
				}
				else
				{
					$newsid = "2";
				}
				$db->query("UPDATE ".TABLE_PREFIX."helpdocs SET sid='$newsid' WHERE hid='$doc[hid]'");
			}
			cpredirect("helpdocs.php", $lang->section_deleted);
		}
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "do_edit")
{
	if($mybb->input['hid'])
	{
		$sqlarray = array(
			"sid" => intval($mybb->input['sid']),
			"name" => addslashes($mybb->input['name']),
			"description" => addslashes($mybb->input['description']),
			"document" => addslashes($mybb->input['document']),
			"usetranslation" => addslashes($mybb->input['usetranslation']),
			"enabled" => addslashes($mybb->input['enabled']),
			"disporder" => intval($mybb->input['disporder']),
			);
		$db->update_query(TABLE_PREFIX."helpdocs", $sqlarray, "hid='".intval($mybb->input['hid']."'");
		cpredirect("helpdocs.php", $lang->doc_updated);
	}
	elseif($mybb->input['sid'])
	{
		$sqlarray = array(
			"name" => addslashes($mybb->input['name']),
			"description" => addslashes($mybb->input['description']),
			"usetranslation" => addslashes($mybb->input['usetranslation']),
			"enabled" => addslashes($mybb->input['enabled']),
			"disporder" => intval($mybb->input['disporder']),
			);
		$db->update_query(TABLE_PREFIX."helpsections", $sqlarray, "sid='".intval($mybb->input['sid']."'");
		cpredirect("helpdocs.php", $lang->section_updated);
	}
}

if($mybb->input['action'] == "edit")
{
	cpheader();
	if($mybb->input['hid'])
	{
		$hid = intval($mybb->input['hid']);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."helpdocs WHERE hid='$hid'");
		$doc = $db->fetch_array($query);
		$doc['description'] = stripslashes($doc['description']);
		$doc['document'] = stripslashes($doc['document']);
		startform("helpdocs.php", "", "do_edit");
		makehiddencode("hid", "$hid");
		starttable();
		$lang->modify_doc = sprintf($lang->modify_doc, $doc['name']);
		tableheader($lang->modify_doc);
		makelabelcode($lang->doc_id, $doc['hid']);
		if($doc['hid'] > 7)
		{
			makeinputcode($lang->doc_title, "name", $doc['name']);
			maketextareacode($lang->description, "description", $doc['description']);
			maketextareacode($lang->document, "document", $doc['document'], "8", "60");
			makeyesnocode($lang->use_translation, "usetranslation", "$doc[usetranslation]");
		}
		else
		{
			makehiddencode("name", $doc['name']);
			makehiddencode("description", $doc['description']);
			makehiddencode("document", $doc['document']);
			makehiddencode("usetranslation", $doc['usetranslation']);
		}
		makeyesnocode($lang->enabled, "enabled", $doc['enabled']);
		makeinputcode($lang->disporder, "disporder", $doc['disporder'], "4");
		makeselectcode($lang->doc_section, "sid", "helpsections", "sid", "name", $doc['sid']);
		endtable();
		endform($lang->update_doc, $lang->reset_button);
	}
	elseif($$mybb->input['sid'])
	{
		$sid = intval($mybb->input['sid']);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."helpsections WHERE sid='$sid'");
		$section = $db->fetch_array($query);
		startform("helpdocs.php", "", "do_edit");
		makehiddencode("sid", "$sid");
		starttable();
		$lang->modify_section = sprintf($lang->modify_section, $section['name']);
		tableheader($lang->modify_section);
		makelabelcode($lang->section_id, $section['sid']);
		if($section['sid'] > 2)
		{
			makeinputcode($lang->section_name, "name", $section['name']);
			maketextareacode($lang->description, "description", $section['description']);
			makeyesnocode($lang->use_translation, "usetranslation", $section['usetranslation']);
		}
		else
		{
			makehiddencode("name", $section['name']);
			makehiddencode("description", $section['description']);
			makehiddencode("usetranslation", $section['usetranslation']);
		}
		makeyesnocode($lang->enabled, "enabled", $section['enabled']);
		makeinputcode($lang->disporder, "disporder", "$section[disporder]", "4");
		endtable();
		endform($lang->update_section, $lang->reset_button);
	}
	cpfooter();
}

if($mybb->input['action'] == "delete")
{
	cpheader();
	if($mybb->input['hid'])
	{
		$hid = intval($mybb->input['hid']);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."helpdocs WHERE hid='$hid'");
		$doc = $db->fetch_array($query);
		if($mybb->input['hid'] > 7)
		{
			$lang->delete_doc = sprintf($lang->delete_doc, $doc[name]);
			$lang->delete_doc_confirm = sprintf($lang->delete_doc_confirm, $doc[name]);
			startform("helpdocs.php", "", "do_delete");
			makehiddencode("hid", $hid);
			starttable();
			tableheader($lang->delete_doc, "", 1);
			$yes = makebuttoncode("deletesubmit", $lang->yes);
			$no = makebuttoncode("no", $lang->no);
			makelabelcode("<center>$lang->delete_doc_confirm<br><br>$yes$no</center>", "");
			endtable();
			endform();
		}
	}
	elseif($mybb->input['sid'])
	{
		$sid = intval($mybb->input['sid']);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."helpsections WHERE sid='$sid'");
		$section = $db->fetch_array($query);
		if($section['sid'] > 2)
		{
			$lang->delete_section = sprintf($lang->delete_section, $section[name]);
			$lang->delete_section_confirm = sprintf($lang->delete_section_confirm, $section[name]);
			startform("helpdocs.php", "", "do_delete");
			makehiddencode("sid", $sid);
			starttable();
			tableheader($lang->delete_section, "", 1);
			$yes = makebuttoncode("deletesubmit", $lang->yes);
			$no = makebuttoncode("no", $lang->no);
			makelabelcode("<center>$lang->delete_section_confirm<br><br>$yes$no</center>", "");
			endtable();
			endform();
		}
	}
	cpfooter();
}

if($mybb->input['action'] == "add")
{
	cpheader();
	startform("helpdocs.php", "", "do_add");
	makehiddencode("add", "section");
	starttable();
	tableheader($lang->add_section);
	makeinputcode($lang->section_name, "name");
	maketextareacode($lang->description, "description");
	makeyesnocode($lang->use_translation, "usetranslation", "no");
	makeyesnocode($lang->enabled, "enabled");
	makeinputcode($lang->disporder, "disporder", "1", "4");
	endtable();
	endform($lang->add_section, $lang->reset_button);
	echo "<br />\n";
	echo "<br />\n";
	startform("helpdocs.php", "", "do_add");
	makehiddencode("add", "doc");
	starttable();
	tableheader($lang->add_doc);
	makeinputcode($lang->doc_title, "name");
	maketextareacode($lang->description, "description");
	maketextareacode($lang->document, "document", "", "8", "60");
	makeyesnocode($lang->use_translation, "usetranslation", "no");
	makeyesnocode($lang->enabled, "enabled");
	makeinputcode($lang->disporder, "disporder", "1", "4");
	makeselectcode($lang->doc_section, "sid", "helpsections", "sid", "name");
	endtable();
	endform($lang->add_doc_section, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	if(!$noheader)
	{
		cpheader();
	}
	$hopto[] = "<input type=\"button\" value=\"$lang->hopto_add\" onclick=\"hopto('helpdocs.php?action=add');\" class=\"hoptobutton\">";
	makehoptolinks($hopto);
	// Get default sections/documents
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."helpsections WHERE sid<='2' ORDER BY disporder");
	while($section = $db->fetch_array($query)) {
		$disablednote = "";
		if($section['enabled'] == "no")
		{
			$disablednote = $lang->disabled_note;
		}
		$defaulthelpsections .= "<li><b>$section[name]</b> $disablednote".
			makelinkcode($lang->edit, "helpdocs.php?action=edit&sid=$section[sid]").
			"</li>\n<ul>\n";
		$query2 = $db->query("SELECT * FROM ".TABLE_PREFIX."helpdocs WHERE sid='$section[sid]' ORDER BY disporder");
		while($doc = $db->fetch_array($query2))
		{
			$disablednote = "";
			if($doc['enabled'] == "no")
			{
				$disablednote = $lang->disabled_note;
			}
			$defaulthelpsections .= "<li>$doc[name] $disablednote".
				makelinkcode($lang->edit, "helpdocs.php?action=edit&hid=$doc[hid]");
				if($doc['hid'] > 7)
				{
					$defaulthelpsections .= makelinkcode($lang->delete, "helpdocs.php?action=delete&hid=$doc[hid]");
				}
			$defaulthelpsections .= "</li>\n";
		}
		$defaulthelpsections .= "</ul>\n<br />\n";
	}
	// Get custom help sections/documents
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."helpsections WHERE sid>'2' ORDER BY disporder");
	while($section = $db->fetch_array($query))
	{
		$disablednote = "";
		if($section['enabled'] == "no")
		{
			$disablednote = $lang->disabled_note;
		}
		$customhelpsections .= "<li><b>$section[name]</b> $disablednote".
			makelinkcode($lang->edit, "helpdocs.php?action=edit&sid=$section[sid]").
			makelinkcode($lang->delete, "helpdocs.php?action=delete&sid=$section[sid]").
			"</li>\n<ul>\n";
		$query2 = $db->query("SELECT * FROM ".TABLE_PREFIX."helpdocs WHERE sid='$section[sid]' ORDER BY disporder");
		while($doc = $db->fetch_array($query2))
		{
			$disablednote = "";
			if($doc['enabled'] == "no")
			{
				$disablednote = $lang->disabled_note;
			}
			$customhelpsections .= "<li>$doc[name] $disablednote".
				makelinkcode($lang->edit, "helpdocs.php?action=edit&hid=$doc[hid]");
				if($doc['hid'] > 7)
				{
					$customhelpsections .= makelinkcode($lang->delete, "helpdocs.php?action=delete&hid=$doc[hid]");
				}
			$customhelpsections .= "</li>\n";
		}
		$customhelpsections .= "</ul>\n<br />\n";
	}
	starttable();
	tableheader($lang->helpdocs);
	tablesubheader($lang->default_sections);
	makelabelcode($lang->select_edit_delete);
	makelabelcode("<ul>\n$defaulthelpsections</ul>");
	tablesubheader($lang->custom_sections);
	makelabelcode($lang->select_edit_delete);
	makelabelcode("<ul>\n$customhelpsections</ul>");
	endtable();
	cpfooter();
}
?>