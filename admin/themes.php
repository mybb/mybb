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

define("IN_MYBB", 1);

require "./global.php";
require_once MYBB_ROOT."inc/class_xml.php";


// Get language packs for this section
global $lang;
$lang->load("themes");

addacpnav($lang->nav_themes, "themes.php?".SID);
switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_theme);
		break;
	case "edit":
		addacpnav($lang->nav_edit_theme);
		break;
	case "delete":
		addacpnav($lang->nav_delete_theme);
		break;
	case "download":
		addacpnav($lang->nav_download_theme);
		break;
	case "import":
		addacpnav($lang->nav_import_theme);
		break;
}
checkadminpermissions("caneditthemes");
logadmin();

$plugins->run_hooks("admin_themes_start");

if($mybb->input['action'] == "do_add")
{
	if(!$mybb->input['pid'])
	{
		$mybb->input['pid'] = 1;
	}
	$query = $db->query("SELECT tid FROM ".TABLE_PREFIX."themes WHERE name='".$db->escape_string($mybb->input['name'])."'");
	$existingtheme = $db->fetch_array($query);
	if($existingtheme['tid'])
	{
		cpmessage($lang->theme_exists);
	}
	$themearray = array(
		"name" => $db->escape_string($mybb->input['name']),
		"pid" => $mybb->input['pid'],
		);
	$plugins->run_hooks("admin_themes_do_add");
	$db->insert_query(TABLE_PREFIX."themes", $themearray);
	$tid = $db->insert_id();
	update_theme($tid, $mybb->input['pid'], "", "", 0, 1);
	cpredirect("themes.php?".SID, $lang->theme_added);
}

if($mybb->input['action'] == "do_edit")
{
	if(!$mybb->input['pid'])
	{
		$mybb->input['pid'] = 1;
	}
	if($mybb->input['tid'] == 1)
	{
		$mybb->input['pid'] = "0";
	}
	if($mybb->input['pid'] == $mybb->input['tid'])
	{
		cpmessage($lang->theme_same_parent);
	}
	$themelist = "<ul>";
	$themelist .= update_theme($mybb->input['tid'], $mybb->input['pid'], $mybb->input['themebits'], $mybb->input['css'], 0);
	$themelist .= "</ul>";

	// Figure out usergroup
	if(!is_array($mybb->input['allowedgroups']))
	{
		$allowedgroups = "none";
	}
	elseif(in_array("all", $mybb->input['allowedgroups']))
	{
		$allowedgroups = "all";
	}
	else
	{
		$allowedgroups = array();
		foreach($mybb->input['allowedgroups'] as $gid)
		{
			$allowedgroups[] = intval($gid);
		}
		$allowedgroups = implode(",", $allowedgroups);
	}

	$themearray = array(
		"name" => $db->escape_string($mybb->input['name']),
		"pid" => $mybb->input['pid'],
		"allowedgroups" => $allowedgroups,
		);
	$plugins->run_hooks("admin_themes_do_edit");
	$db->update_query(TABLE_PREFIX."themes", $themearray, "tid='".intval($mybb->input['tid'])."'");

	cpredirect("themes.php?".SID, $lang->theme_updated."<br />$themelist");
}

if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{
		$plugins->run_hooks("admin_themes_do_delete");
		$db->query("UPDATE ".TABLE_PREFIX."users SET style='' WHERE style='".intval($mybb->input['tid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."themes WHERE tid='".intval($mybb->input['tid'])."'");
		cpredirect("themes.php?".SID, $lang->theme_deleted);
		@unlink(MYBB_ROOT.'css/theme_'.intval($mybb->input['tid']).'.css');
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}
if($mybb->input['action'] == "default")
{
	$plugins->run_hooks("admin_themes_default");
	$db->query("UPDATE ".TABLE_PREFIX."themes SET def='0'");
	$db->query("UPDATE ".TABLE_PREFIX."themes SET def='1' WHERE tid='".intval($mybb->input['tid'])."'");
	cpredirect("themes.php?".SID, $lang->default_updated);
}
if($mybb->input['action'] == "do_download")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."themes WHERE tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	$themebits = unserialize($theme['themebits']);
	$themebits['extracss'] = $theme['extracss'];
	$inheritedbits = $themebits['inherited'];
	unset($themebits['inherited']);

	$plugins->run_hooks("admin_themes_do_download");
	if($mybb->input['customonly'] == "no")
	{
		$css = build_css_array($mybb->input['tid'], 0);
	}
	else
	{
		$css = unserialize($theme['cssbits']);
		foreach($themebits as $name => $value)
		{
			if($inheritedbits[$name] && $inheritedbits[$name] != $mybb->input['tid'] && $inheritedbits[$name] != 1 && $mybb->input['tid'] != 1)
			{
				unset($themebits[$name]);
			}
		}
	}
	$xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n";
	$xml .= "<theme name=\"".$theme['name']."\" version=\"".$mybboard['vercode']."\">\r\n";
	function xml_css_bits($css, $depth)
	{
		foreach($css as $name => $value)
		{
			if(is_array($value))
			{
				$subxml = "";
				$subxml = xml_css_bits($value, $depth."\t");
				if($subxml)
				{
					$xml .= "$depth<".$name.">\r\n";
					$xml .= $subxml;
					$xml .= "$depth</".$name.">\r\n";
				}
			}
			else
			{
				if($value)
				{
					$xml .= "$depth<".$name."><![CDATA[".$value."]]></".$name.">\r\n";
				}
			}
		}
		return $xml;
	}
	if(is_array($css))
	{
		$xml .= "\t<cssbits>\r\n";
		$xml .= xml_css_bits($css, "\t\t");
		$xml .= "\t</cssbits>\r\n";
	}
	if(is_array($themebits))
	{
		$xml .= "\t<themebits>\r\n";
		foreach($themebits as $name => $value)
		{
			$xml .= "\t\t<".$name."><![CDATA[".$value."]]></".$name.">\r\n";
		}
		$xml .= "\t</themebits>\r\n";
	}
	if($mybb->input['inctemps'] != "no")
	{
		$xml .= "\t<templates>\r\n";
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE sid='".$themebits['templateset']."'");
		while($template=$db->fetch_array($query))
		{
			$template['template'] = stripslashes($template['template']);
			$template['template'] = str_replace("\n", "\n", $template['template']);
			$xml .= "\t\t<template name=\"".$template['title']."\" version=\"".$template['version']."\"><![CDATA[".$template['template']."]]></template>\r\n";
			$tempsdone[$template['title']] = 1;
		}
		if($mybb->input['customtempsonly'] == "no")
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE sid='-2'");
			while($template=$db->fetch_array($query))
			{
				if(!$tempsdone[$template[title]])
				{
					$template['template'] = stripslashes($template['template']);
					$template['template'] =str_replace("\n", "\n", $template['template']);
					$xml .= "\t\t<template name=\"".$template['title']."\" version=\"".$template['version']."\"><![CDATA[".$template['template']."]]></template>\r\n";
				}
			}
		}
		$xml .= "\t</templates>\r\n";
	}
	$xml .= "</theme>";
	$theme['name'] = rawurlencode($theme['name']);
	header("Content-disposition: attachment; filename=".$theme['name']."-theme.xml");
	header("Content-Length: ".my_strlen($xml));
	header("Content-type: application/octet-stream");
	header("Pragma: no-cache");
	header("Expires: 0");
	echo $xml;
	exit;
}
if($mybb->input['action'] == "do_import")
{
	// Find out if there was an uploaded file
	if($_FILES['compfile']['error'] != 4)
	{
		// Find out if there was an error with the uploaded file
		if($_FILES['compfile']['error'] != 0)
		{
			$error = $lang->error_uploadfailed.$lang->error_uploadfailed_detail;
			switch($_FILES['compfile']['error'])
			{
				case 1: // UPLOAD_ERR_INI_SIZE
					$error .= $lang->error_uploadfailed_php1;
					break;
				case 2: // UPLOAD_ERR_FORM_SIZE
					$error .= $lang->error_uploadfailed_php2;
					break;
				case 3: // UPLOAD_ERR_PARTIAL
					$error .= $lang->error_uploadfailed_php3;
					break;
				case 4: // UPLOAD_ERR_NO_FILE
					$error .= $lang->error_uploadfailed_php4;
					break;
				case 6: // UPLOAD_ERR_NO_TMP_DIR
					$error .= $lang->error_uploadfailed_php6;
					break;
				case 7: // UPLOAD_ERR_CANT_WRITE
					$error .= $lang->error_uploadfailed_php7;
					break;
				default:
					$error .= sprintf($lang->error_uploadfailed_phpx, $_FILES['compfile']['error']);
					break;
			}
			cperror($error);
		}

		// Was the temporary file found?
		if(!is_uploaded_file($_FILES['compfile']['tmp_name']))
		{
			cperror($lang->error_uploadfailed_lost);
		}
		// Get the contents
		$contents = @file_get_contents($_FILES['compfile']['tmp_name']);
		// Delete the temporary file if possible
		@unlink($_FILES['compfile']['tmp_name']);
		// Are there contents?
		if(!trim($contents))
		{
			cperror($lang->error_uploadfailed_nocontents);
		}
	}
	elseif(!empty($mybb->input['localfile']))
	{
		// Get the contents
		$contents = @file_get_contents($mybb->input['localfile']);
		if(!$contents)
		{
			cperror($lang->error_local_file);
		}
	}
	$plugins->run_hooks("admin_themes_do_import");
	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	$theme = $tree['theme'];

	if(!$tree['theme'])
	{
		cperror($lang->failed_finding_theme);
	}
	if(empty($mybb->input['name']))
	{
		$name = $theme['attributes']['name'];
	}
	else
	{
		$name = $mybb->input['name'];
	}
	$version = $theme['attributes']['version'];

	$query = $db->simple_select(TABLE_PREFIX."themes", "tid", "name='".$db->escape_string($name)."'", array("limit" => 1));
	$existingtheme = $db->fetch_array($query);
	if($existingtheme['tid'])
	{
		cpmessage($lang->theme_exists);
	}

	if($mybboard['vercode'] != $version && $mybb->input['ignorecompat'] != "yes")
	{
		$lang->version_warning = sprintf($lang->version_warning, $mybboard['internalver']);
		cperror($lang->version_warning);
	}

	$css = kill_tags($theme['cssbits']);
	$themebits = kill_tags($theme['themebits']);
	$templates = $theme['templates']['template'];

	if($master == "yes")
	{
		$templateset = -2;
		$tid = 1;
		$db->query("DELETE FROM ".TABLE_PREFIX."templates WHERE sid='-2'");
	}
	else
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."templatesets VALUES ('', '$name Templates')");
		$templateset = $db->insert_id();
		$tid = "";
	}

	$themebits['templateset'] = $templateset;

	if($mybb->input['importtemps'] == "yes" && $templates)
	{
		foreach($templates as $template)
		{
			$templatename = $template['attributes']['name'];
			$templatevalue = $db->escape_string($template['value']);
			$templateversion = $template['attributes']['version'];
			$time = time();
			$db->query("INSERT INTO ".TABLE_PREFIX."templates (title,template,sid,version,status,dateline) VALUES ('$templatename','$templatevalue','$templateset','$templateversion','','$time')");
		}
	}

	if(!$mybb->input['pid'])
	{
		$mybb->input['pid'] = 1;
	}
	$themearray = array(
		"name" => $db->escape_string($name),
		"pid" => $mybb->input['pid'],
		);

	$db->insert_query(TABLE_PREFIX."themes", $themearray);
	$tid = $db->insert_id();
	update_theme($tid, $mybb->input['pid'], $themebits, $css, 0);
	$lang->theme_imported = sprintf($lang->theme_imported, $name);
	cpredirect("themes.php?".SID, $lang->theme_imported);
}
if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_themes_add");
	cpheader();
	startform("themes.php", "" , "do_add");
	starttable();
	tableheader($lang->add_theme);
	tablesubheader($lang->general_options);
	makeinputcode($lang->theme_name, "name");
	makelabelcode($lang->theme_parent, make_theme_select("pid", $mybb->input['pid']));
	endtable();
	endform($lang->add_theme, $lang->reset_button);
	cpfooter();

}
if($mybb->input['action'] == "settings")
{
	$plugins->run_hooks("admin_themes_settings");
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."themes WHERE tid='".$mybb->input['tid']."'");
	$theme = $db->fetch_array($query);
	cpheader();
	startform("themes.php", "" , "do_settings");
	starttable();
	tableheader($lang->theme_settings);
	tablesubheader($lang->general_options);
	makeinputcode($lang->theme_name, "name", $theme['name']);
	makelabelcode($lang->theme_parent, make_theme_select("pid", $theme['pid']));
	endtable();
	endform($lang->update_theme, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "edit") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."themes WHERE tid='1'");
	$master = $db->fetch_array($query);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."themes WHERE tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	$themebits = unserialize($theme['themebits']);
	$css = build_css_array($theme['tid']);

	// Do allowed usergroups
	$existing_groups = explode(",", $theme['allowedgroups']);
	$options = array(
		"order_by" => "title",
		"order_dir" => "ASC"
		);
	$query = $db->simple_select(TABLE_PREFIX."usergroups", "gid, title", "", $options);
	$has_check = 0;
	while($usergroup = $db->fetch_array($query))
	{
		$checked = '';
		if(in_array($usergroup['gid'], $existing_groups))
		{
			$checked = "checked=\"checked\"";
			$has_check = 1;
		}
		$usergroups[] = "<input type=\"checkbox\" name=\"allowedgroups[]\" value=\"$usergroup[gid]\"$checked /> $usergroup[title]";
	}
	$checked = '';
	if(!$has_check && !in_array("none", $existing_groups))
	{
		$checked = "checked=\"checked\"";
	}
	$usergroups[] = "<input type=\"checkbox\" name=\"allowedgroups[]\" value=\"all\"$checked /> <strong>$lang->all_groups</strong>";
	$usergroups = implode("<br />", $usergroups);
	$plugins->run_hooks("admin_themes_do_edit");
	$lang->modify_theme = sprintf($lang->modify_theme, $theme['name']);
	cpheader();
	startform("themes.php", "" , "do_edit");
	makehiddencode("tid", $mybb->input['tid']);
	starttable();

	tableheader($lang->modify_theme);

	tablesubheader($lang->general_options);
	makeinputcode($lang->theme_name, "name", $theme['name']);
	makelabelcode($lang->theme_parent, make_theme_select("pid", $theme['pid']));
	makelabelcode($lang->allowed_groups, "<small>$usergroups</small>");

	tablesubheader($lang->theme_options);
	makethemebitedit($lang->template_set, "templateset");
	makethemebitedit($lang->image_dir, "imgdir");
	makethemebitedit($lang->forum_logo, "logo");
	makethemebitedit($lang->table_spacing, "tablespace");
	makethemebitedit($lang->inner_border_width, "borderwidth");
	endtable();

	makecssedit($css['body'], "body", $lang->body, "");
	makecssedit($css['container'], "container", $lang->container, "", 0, 0, 0, 1);
	makecssedit($css['content'], "content", $lang->content);
	makecssedit($css['menu'], "menu", $lang->top_menu, "");
	makecssedit($css['panel'], "panel", $lang->panel, "");
	makecssedit($css['table'], "table", $lang->tables, 1, 1, 0);
	makecssedit($css['tborder'], "tborder", $lang->tborder, "", 1, 1, 0, 0);
	makecssedit($css['thead'], "thead", $lang->thead, "");
	makecssedit($css['tcat'], "tcat", $lang->tcat, "");
	makecssedit($css['trow1'], "trow1", $lang->trow1, "");
	makecssedit($css['trow2'], "trow2", $lang->trow2, "");
	makecssedit($css['trow_shaded'], "trow_shaded", $lang->trow_shaded, "");
	makecssedit($css['trow_sep'], "trow_sep", $lang->trow_sep, "");
	makecssedit($css['tfoot'], "tfoot", $lang->tfoot, "");
	makecssedit($css['bottommenu'], "bottommenu", $lang->bottom_menu, "");

	makecssedit($css['navigation'], "navigation", $lang->navigation, "");
	makecssedit($css['navigation_active'], "navigation_active", $lang->active_navigation, "");
	makecssedit($css['smalltext'], "smalltext", $lang->smalltext, "", 1, 1, 0);
	makecssedit($css['largetext'], "largetext", $lang->largetext, "", 1, 1, 0);
	makecssinputedit($css);
	makecsstoolbaredit($css);
	makecssautocompleteedit($css);
	makecsspopupmenuedit($css);
	makecssreputationedit($css);
	starttable();
	tableheader($lang->additional_css, "", 1);
	tablesubheader($lang->additional_css_note, "", 1);
	makethemebitedit("", "extracss");
	endtable();


	endform($lang->update_theme, $lang->reset_button);

	cpfooter();
}
if($mybb->input['action'] == "delete") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."themes WHERE tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	$plugins->run_hooks("admin_themes_delete");
	$lang->delete_theme = sprintf($lang->delete_theme, $theme['name']);
	$lang->delete_theme_confirm = sprintf($lang->delete_theme_confirm, $theme['name']);
	cpheader();
	startform("themes.php", "", "do_delete");
	makehiddencode("tid", $mybb->input['tid']);
	starttable();
	tableheader($lang->delete_theme, "", 1);
	if($theme[def] != 1)
	{
		$yes = makebuttoncode("deletesubmit", $lang->yes);
		$no = makebuttoncode("no", $lang->no);
		makelabelcode("<div align=\"center\">".$lang->delete_theme_confirm."<br /><br />$yes$no</div>", "");
	}
	else
	{
		makelabelcode($lang->error_delete_default, "");
	}
	endtable();
	endform();
	cpfooter();

}
if($mybb->input['action'] == "import")
{
	$plugins->run_hooks("admin_themes_import");
	cpheader();
	startform("themes.php", "" , "do_import");
	starttable();
	tableheader($lang->import_theme, "");
	makeuploadcode($lang->theme_file, "compfile");
	makeinputcode($lang->local_file_name, "localfile", "../install/resources/mybb_theme.xml");
	tablesubheader($lang->import_options);
	makeinputcode($lang->import_name, "name");
	makelabelcode($lang->theme_parent, make_theme_select("pid", 1));
	makeyesnocode($lang->import_custom_templates, "importtemps");
	makeyesnocode($lang->ignore_version, "ignorecompat", "no");
	endtable();
	endform($lang->import_theme, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "download")
{
	if(!$noheader)
	{
		cpheader();
	}
	$plugins->run_hooks("admin_themes_download");
	startform("themes.php", "" , "do_download");
	starttable();
	tableheader($lang->download_theme, "");
	tablesubheader($lang->select_download, "");
	makelabelcode($lang->theme_parent, make_theme_select("tid", $mybb->input['tid']));
	makeyesnocode($lang->include_custom_only, "customonly", "yes");
	makeyesnocode($lang->include_templates, "inctemps", "yes");
	makeyesnocode($lang->include_custom_temps_only, "customtempsonly", "yes");
	endtable();
	endform($lang->do_download, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	$plugins->run_hooks("admin_themes_modify");
	if(!$noheader)
	{
		cpheader();
	}
	$lang->export_advanced_settings = str_replace("\n", "\\n", $lang->export_advanced_settings);
?>
<script type="text/javascript">
<!--
function theme_hop(tid)
{
	action = eval("document.themes.theme_"+tid+".options[document.themes.theme_"+tid+".selectedIndex].value");
	if(action == "download")
	{
		var confirmReturn = false;
		confirmReturn = confirm('<?php echo $lang->export_advanced_settings; ?>');
		if(confirmReturn == false)
		{
			window.location = "themes.php?<?php echo SID; ?>&action=do_download&tid="+tid+"&customonly=yes&inctemps=yes&customtempsonly=yes";
		}
		else
		{
			window.location = "themes.php?<?php echo SID; ?>&action=download&tid="+tid;
		}
	}
	else if(action != "")
	{
		window.location = "themes.php?<?php echo SID; ?>&action="+action+"&tid="+tid;
	}
}
-->
</script>
<?php
	$hopto[] = "<input type=\"button\" value=\"$lang->new_theme\" onclick=\"hopto('themes.php?".SID."&action=add');\" class=\"hoptobutton\">";
	$hopto[] = "<input type=\"button\" value=\"$lang->import_theme\" onclick=\"hopto('themes.php?".SID."&action=import');\" class=\"hoptobutton\">";
	$hopto[] = "<input type=\"button\" value=\"$lang->download_theme\" onclick=\"hopto('themes.php?".SID."&action=download');\" class=\"hoptobutton\">";
	makehoptolinks($hopto);

	startform("themes.php", "themes" , "do_modify");
	starttable();
	tableheader($lang->theme_management, "", 2);
	echo "<tr>\n";
	echo "<td class=\"subheader\">$lang->theme</td>\n";
	echo "<td class=\"subheader\" align=\"center\" width=\"20%\">$lang->controls</td>\n";
	echo "</tr>\n";
	make_theme_list();
	endtable();
	endform();
	cpfooter();
}
?>