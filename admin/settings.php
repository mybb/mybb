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
$lang->load("settings");

addacpnav($lang->nav_settings, "settings.php?".SID);

switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add);
		break;
	case "delete":
		addacpnav($lang->nav_delete);
		break;
	case "edit":
		addacpnav($lang->nav_edit);
		break;
	case "change":
		if($mybb->input['gid'] && $mybb->input['gid'] != -1)
		{
			$query = $db->query("SELECT g.*, COUNT(s.sid) AS settingcount FROM ".TABLE_PREFIX."settinggroups g LEFT JOIN ".TABLE_PREFIX."settings s ON (s.gid=g.gid) WHERE g.gid='".intval($mybb->input['gid'])."' GROUP BY s.gid");
			$groupinfo = $db->fetch_array($query);
			
			$title_lang = "setting_group_".$groupinfo['name'];
			if($lang->$title_lang)
			{
				$groupinfo['title'] = $lang->$title_lang;
			}
			
			addacpnav($groupinfo['title']);
		}
		break;
	case "modify":
		addacpnav($lang->nav_modify);
		break;
}

checkadminpermissions("caneditsettings");
logadmin();

$plugins->run_hooks("admin_settings_start");

if($mybb->input['action'] == "do_change")
{
	$plugins->run_hooks("admin_settings_do_change");
	if(is_array($mybb->input['upsetting']))
	{
		foreach($mybb->input['upsetting'] as $key => $val)
		{
			$val = $db->escape_string($val);
			$key = intval($key);
			$db->query("UPDATE ".TABLE_PREFIX."settings SET value='$val' WHERE sid='$key'");
		}
	}
	rebuildsettings();
	// Check if we need to create our fulltext index after changing the search mode
	if($mybb->settings['searchtype'] == "fulltext")
	{
		if(!$db->is_fulltext(TABLE_PREFIX."posts") && $db->supports_fulltext_boolean(TABLE_PREFIX."posts"))
		{
			$db->create_fulltext_index(TABLE_PREFIX."posts", "message");
		}
		if(!$db->is_fulltext(TABLE_PREFIX."posts") && $db->supports_fulltext(TABLE_PREFIX."threads"))
		{
			$db->create_fulltext_index(TABLE_PREFIX."threads", "subject");
		}
	}
	cpredirect("settings.php?".SID, $lang->settings_updated);
}

if($mybb->input['action'] == "do_add")
{
	if($mybb->input['add'] == "setting")
	{
		if($mybb->input['type'] == "custom")
		{
			$mybb->input['type'] = $db->escape_string($mybb->input['code']);
		}
		$settingarray = array(
			"name" => $db->escape_string($mybb->input['name']),
			"title" => $db->escape_string($mybb->input['title']),
			"description" => $db->escape_string($mybb->input['description']),
			"optionscode" => $mybb->input['type'],
			"value" => $db->escape_string($mybb->input['value']),
			"disporder" => intval($mybb->input['disporder']),
			"gid" => intval($mybb->input['gid'])
			);
		$plugins->run_hooks("admin_settings_do_add_setting");
		$db->insert_query(TABLE_PREFIX."settings", $settingarray);
		rebuildsettings();
		cpredirect("settings.php?".SID, $lang->setting_added);
	}
	else if($mybb->input['add'] == "group")
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settinggroups WHERE name='".$db->escape_string($mybb->input['name'])."'");
		$g = $db->fetch_array($query);
		if($g['name'])
		{
			cperror($lang->group_exists);
		}
		$settinggrouparray = array(
			"name" => $db->escape_string($mybb->input['name']),
			"title" => $db->escape_string($mybb->input['title']),
			"description" => $db->escape_string($mybb->input['description']),
			"disporder" => intval($mybb->input['disporder'])
			);
		if(md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7")
		{
			$settinggrouparray['isdefault'] = $mybb->input['isdefault'];
		}
		$plugins->run_hooks("admin_settings_do_add_group");
		$db->insert_query(TABLE_PREFIX."settinggroups", $settinggrouparray);
		rebuildsettings();
		cpredirect("settings.php?".SID, $lang->group_added);
	}
}

if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{	
		if($mybb->input['sid'])
		{
			$plugins->run_hooks("admin_settings_do_delete_setting");
			$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE sid='".intval($mybb->input['sid'])."'");
			rebuildsettings();
			cpredirect("settings.php?".SID, $lang->setting_deleted);
		}
		else if($mybb->input['gid'])
		{
			$plugins->run_hooks("admin_settings_do_delete_group");
			$db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE gid='".intval($mybb->input['gid'])."'");
			$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE gid='".intval($mybb->input['gid'])."'");
			rebuildsettings();
			cpredirect("settings.php?".SID, $lang->group_deleted);
		}
	}
	else
	{
		header("Location: settings.php?".SID);
	}
}
if($mybb->input['action'] == "export")
{
	$gidwhere = "";
	if($mybb->input['gid'])
	{
		$gidwhere = "WHERE gid='".intval($mybb->input['gid'])."'";
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settings $gidwhere ORDER BY disporder");
	while($setting = $db->fetch_array($query))
	{
		$settinglist[$setting['gid']][] = $setting;
	}
	$plugins->run_hooks("admin_settings_export");
	$xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\n";
	$xml = "<settings version=\"".$mybboard['vercode']."\" exported=\"".time()."\">\n";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settinggroups $gidwhere ORDER BY name ASC");
	while($settinggroup = $db->fetch_array($query))
	{
		$xml .= "\t<settinggroup name=\"".$settinggroup['name']."\" title=\"".$settinggroup['title']."\" description=\"".$settinggroup['description']."\" disporder=\"".$settinggroup['disporder']."\" isdefault=\"".$settinggroup['isdefault']."\">\n";
		if(is_array($settinglist[$settinggroup['gid']]))
		{
			foreach($settinglist[$settinggroup['gid']] as $setting)
			{
				$xml .= "\t\t<setting name=\"".$setting['name']."\">\n";
				$xml .= "\t\t\t<title>".$setting['title']."</title>\n";
				$xml .= "\t\t\t<description><![CDATA[".$setting['description']."]]></description>\n";
				$xml .= "\t\t\t<disporder>".$setting['disporder']."</disporder>\n";
				$xml .= "\t\t\t<optionscode><![CDATA[".$setting['optionscode']."]]></optionscode>\n";
				$xml .= "\t\t\t<settingvalue><![CDATA[".$setting['value']."]]></settingvalue>\n";
				$xml .= "\t\t\t<helpkey>".$setting['helpkey']."</helpkey>\n";
				$xml .= "\t\t</setting>\n";
			}
		}
		$xml .= "\t</settinggroup>\n";
	}
	$xml .= "</settings>";
	$settings['bbname'] = urlencode($settings['bbname']);
	header("Content-disposition: filename=".$settings['bbname']."-settings.xml");
	header("Content-Length: ".strlen($xml));
	header("Content-type: unknown/unknown");
	header("Pragma: no-cache");
	header("Expires: 0");
	echo $xml;
	exit;	
}
if($mybb->input['action'] == "do_edit")
{
	cpheader();
	if($mybb->input['sid'])
	{
		$settingarray = array(
			"name" => $db->escape_string($mybb->input['name']),
			"title" => $db->escape_string($mybb->input['title']),
			"description" => $db->escape_string($mybb->input['description']),
			"optionscode" => $db->escape_string($mybb->input['type']),
			"value" => $db->escape_string($mybb->input['value']),
			"disporder" => intval($mybb->input['disporder']),
			"gid" => intval($mybb->input['gid'])
			);
		$plugins->run_hooks("admin_settings_do_edit_setting");
		$db->update_query(TABLE_PREFIX."settings", $settingarray, "sid='".intval($mybb->input['sid'])."'");
		rebuildsettings();
		cpredirect("settings.php?".SID, $lang->setting_edited);
	}
	else if($mybb->input['gid'])
	{
		$settinggrouparray = array(
			"name" => $db->escape_string($mybb->input['name']),
			"title" => $db->escape_string($mybb->input['title']),
			"description" => $db->escape_string($mybb->input['description']),
			"disporder" => intval($mybb->input['disporder'])
			);
		if(md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7")
		{
			$settinggrouparray['isdefault'] = $mybb->input['isdefault'];
		}
		$plugins->run_hooks("admin_setings_do_edit_group");
		$db->update_query(TABLE_PREFIX."settinggroups", $settinggrouparray, "gid='".intval($mybb->input['gid'])."'");
		rebuildsettings();
		cpredirect("settings.php?".SID, $lang->group_edited);
	}
}

if($mybb->input['action'] == "edit")
{
	cpheader();
	if($mybb->input['sid'])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settings WHERE sid='".intval($mybb->input['sid'])."'");
		$setting = $db->fetch_array($query);
		$plugins->run_hooks("admin_settings_edit_seting");
		$type[$setting['type']] = "selected";
		startform("settings.php", "", "do_edit");
		makehiddencode("sid", $mybb->input['sid']);
		starttable();
		tableheader($lang->modify_setting);
		makeinputcode($lang->setting_title, "title", $setting[title]);
		maketextareacode($lang->description, "description", $setting[description]);
		makeinputcode($lang->setting_name, "name", $setting[name]);
		maketextareacode($lang->setting_type, "type", $setting[optionscode], 6, 50);
		makeinputcode($lang->value, "value", $setting[value]);
		makeinputcode($lang->disp_order, "disporder", $setting['disporder'], 4);
		makeselectcode($lang->group, "gid", "settinggroups", "gid", "name", $setting['gid']);
		endtable();
		endform($lang->modify_setting, $lang->reset_button);
	}
	else if($mybb->input['gid'])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settinggroups WHERE gid='".intval($mybb->input['gid'])."'");
		$group = $db->fetch_array($query);
		$plugins->run_hooks("admin_settings_edit_group");
		startform("settings.php", "", "do_edit");
		makehiddencode("gid", $mybb->input['gid']);
		starttable();
		tableheader($lang->modify_group);
		makeinputcode($lang->group_name, "name", $group['name']);
		makeinputcode($lang->group_title, "title", $group['title']);
		maketextareacode($lang->description, "description", $group['description']);
		makeinputcode($lang->disp_order, "disporder", $group['disporder'], 4);
		if(md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7")
		{
			makeyesnocode($lang->is_default, "isdefault", $group['isdefault']);
		}

		endtable();
		endform($lang->update_group, $lang->reset_button);
	}
	cpfooter();
}

if($mybb->input['action'] == "delete")
{
	cpheader();
	if($mybb->input['sid'])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settings WHERE sid='".intval($mybb->input['sid'])."'");
		$setting = $db->fetch_array($query);
		$plugins->run_hooks("admin_settings_delete_setting");
		startform("settings.php", "", "do_delete");
		makehiddencode("sid", $mybb->input['sid']);
		starttable();
		tableheader($lang->delete_setting, "", 1);
		$yes = makebuttoncode("deletesubmit", $lang->yes);
		$no = makebuttoncode("no", $lang->no);
		makelabelcode("<center>$lang->delete_setting_confirm<br><br>$yes$no</center>", "");
		endtable();
		endform();
	}
	else if($mybb->input['gid'])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settinggroups WHERE gid='".intval($mybb->input['gid'])."'");
		$group = $db->fetch_array($query);
		$plugins->run_hooks("admin_settings_delete_group");
		startform("settings.php", "", "do_delete");
		makehiddencode("gid", $mybb->input['gid']);
		starttable();
		tableheader($lang->delete_group, "", 1);
		$yes = makebuttoncode("deletesubmit", $lang->yes);
		$no = makebuttoncode("no", $lang->no);
		makelabelcode("<center>$lang->delete_group_confirm<br><br>$yes$no</center>", "");
		endtable();
		endform();
	}
	cpfooter();
}

if($mybb->input['action'] == "add")
{
	cpheader();
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settinggroups ORDER BY disporder");
	while($group = $db->fetch_array($query))
	{
		$settinggroups[$group['gid']] = $group;
	}
	reset($settinggroups);
	unset($group);
	$plugins->run_hooks("admin_settings_add");

	startform("settings.php", "", "do_add");
	makehiddencode("add", "group");
	starttable();
	tableheader($lang->add_group);
	makeinputcode($lang->group_name, "name");
	makeinputcode($lang->group_title, "title");
	makeinputcode($lang->disp_order, "disporder", "", 4);
	endtable();
	endform($lang->add_group, $lang->reset_button);

	startform("settings.php", "", "do_add");
	makehiddencode("add", "setting");
	starttable();
	tableheader($lang->add_setting);
	makeinputcode($lang->setting_title, "title");
	maketextareacode($lang->description, "description");
	makeinputcode($lang->setting_name, "name");
	maketextareacode($lang->setting_type, "type", "", 6, 50);
	makeinputcode($lang->value, "value");
	makeinputcode($lang->disp_order, "disporder", "", 4);
	makeselectcode($lang->group, "gid", "settinggroups", "gid", "title");
	endtable();
	endform($lang->add_setting, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "do_modify")
{
	cpheader();
	$plugins->run_hooks("admin_settings_do_modify");
	foreach($mybb->input['disporder'] as $sid => $order)
	{
		$db->query("UPDATE ".TABLE_PREFIX."settings SET disporder='".intval($order)."' WHERE sid='".intval($sid)."'");
	}
	foreach($mybb->input['dispordercats'] as $gid => $order)
	{
		$db->query("UPDATE ".TABLE_PREFIX."settinggroups SET disporder='".intval($order)."' WHERE gid='".intval($gid)."'");
	}
	starttable();
	tableheader($lang->cp_message_header);
	makelabelcode($lang->setting_group_orders_updated);
	endtable();
	$noheader = 1;
	$mybb->input['action'] = "modify";
}
if($mybb->input['action'] == "modify")
{
	$plugins->run_hooks("admin_settings_modify");
	if(!$noheader)
	{
		cpheader();
	}
	startform("settings.php", "", "do_modify");
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settinggroups ORDER BY disporder");
	while($group = $db->fetch_array($query))
	{
		$settinglist .= "<li><strong>$group[title]</strong> ($lang->disp_order_list <input type=\"text\" name=\"dispordercats[$group[gid]]\" size=\"4\" value=\"$group[disporder]\"> ".
			makelinkcode($lang->edit, "settings.php?".SID."&action=edit&gid=$group[gid]").
			makelinkcode($lang->delete, "settings.php?".SID."&action=delete&gid=$group[gid]").
			"</li>\n<ul>\n";
		$query2 = $db->query("SELECT * FROM ".TABLE_PREFIX."settings WHERE gid='$group[gid]' ORDER BY disporder");
		while($setting = $db->fetch_array($query2))
		{
			$settinglist .= "<li>$setting[title] ($lang->disp_order <input type=\"text\" name=\"disporder[$setting[sid]]\" size=\"4\" value=\"$setting[disporder]\">)".
				makelinkcode($lang->edit, "settings.php?".SID."&action=edit&sid=$setting[sid]").
				makelinkcode($lang->delete, "settings.php?".SID."&action=delete&sid=$setting[sid]").
				"</li>\n";
		}
		$settinglist .= "</ul>\n";
	}
	starttable();
	tableheader($lang->settings_management);
	tablesubheader($lang->select_edit_delete);
	makelabelcode("<ul>\n$settinglist</ul>");
	endtable();
	endform($lang->update_orders, $lang->reset_button);
	cpfooter();

}

if($mybb->input['action'] == "change" || $mybb->input['action'] == "")
{
	$plugins->run_hooks("admin_settings_change");
	if(!$noheader)
	{
		cpheader();
	}
	if($mybb->input['gid'])
	{
		$setting_groups = '';
		if($mybb->input['gid'] != -1)
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settinggroups WHERE gid='".intval($mybb->input['gid'])."'");
		}
		else
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settinggroups ORDER BY disporder");
		}
		while($group = $db->fetch_array($query))
		{
			$setting_groups[$group['gid']] = $group;
		}
		$group_ids = implode(",", array_keys($setting_groups));
		
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settings WHERE gid IN ($group_ids) ORDER BY disporder");
		while($setting = $db->fetch_array($query))
		{
			$setting_list[$setting['gid']][$setting['sid']] = $setting;
		}
	
		startform("settings.php", "", "do_change");
		
		foreach($setting_groups as $groupinfo)
		{
			starttable();
			$title_lang = "setting_group_".$groupinfo['name'];
			if($lang->$title_lang)
			{
				$groupinfo['title'] = $lang->$title_lang;
			}
			tableheader($groupinfo['title'], "", 2);
			
			foreach($setting_list[$groupinfo['gid']] as $setting)
			{
				$options = "";
				$type = explode("\n", $setting['optionscode']);
				$type[0] = trim($type[0]);
				if($type[0] == "text" || $type[0] == "")
				{
					$settingcode = "<input type=\"text\" name=\"upsetting[$setting[sid]]\" value=\"$setting[value]\" size=\"25\">";
				}
				else if($type[0] == "textarea")
				{
					$settingcode = "<textarea name=\"upsetting[$setting[sid]]\" rows=\"6\" cols=\"50\">$setting[value]</textarea>";
				}
				else if($type[0] == "yesno")
				{
					if($setting['value'] == "yes")
					{
						$yeschecked = "checked";
						$nochecked = "";
					}
					else
					{
						$nochecked = "checked";
						$yeschecked = "";
					}
					$settingcode = "<input type=\"radio\" name=\"upsetting[$setting[sid]]\" value=\"yes\" $yeschecked> $lang->yes <input type=\"radio\" name=\"upsetting[$setting[sid]]\" value=\"no\" $nochecked> $lang->no";
				}
				else if($type[0] == "onoff")
				{
					if($setting['value'] == "on")
					{
						$onchecked = "checked";
						$offchecked = "";
					}
					else
					{
						$offchecked = "checked";
						$onchecked = "";
					}
					$settingcode = "<input type=\"radio\" name=\"upsetting[$setting[sid]]\" value=\"on\" $onchecked> $lang->on <input type=\"radio\" name=\"upsetting[$setting[sid]]\" value=\"off\" $offchecked> $lang->off";
				}
				elseif($type[0] == "cpstyle")
				{
					$dir = @opendir(MYBB_ADMIN_DIR."/styles");
					while($folder = readdir($dir))
					{
						if($file != "." && $file != ".." && @file_exists(MYBB_ADMIN_DIR."/styles/$folder/stylesheet.css"))
						{
							$folders[$folder] = $folder;
						}
					}
					closedir($dir);
					ksort($folders);
					while(list($key, $val) = each($folders))
					{
						if($val == $setting['value'])
						{
							$sel = "selected";
						}
						else
						{
							$sel = "";
						}
						$options .= "<option value=\"$val\" $sel>$val</option>";
					}
					$settingcode = "<select name=\"upsetting[$setting[sid]]\" size=\"4\">$options</select>";
				}
				elseif($type[0] == "language")
				{
					$languages = $lang->get_languages();
					foreach($languages as $lname => $language)
					{
						if($setting['value'] == $lname)
						{
							$sel = "selected";
						} else {
							$sel = "";
						}
						$options .= "<option value=\"$lname\" $sel>$language</option>";
					}
					$settingcode = "<select name=\"upsetting[$setting[sid]]\" size=\"4\">$options</select>";
				}
				elseif($type[0] == "adminlanguage")
				{
					$languages = $lang->get_languages(1);
					foreach($languages as $lname => $language)
					{
						if($setting['value'] == $lname)
						{
							$sel = "selected";
						} else {
							$sel = "";
						}
						$options .= "<option value=\"$lname\" $sel>$language</option>";
					}
					$settingcode = "<select name=\"upsetting[$setting[sid]]\" size=\"4\">$options</select>";
				}
				elseif($type[0] == "php")
				{
					$setting['optionscode'] = substr($setting['optionscode'], 3);
					eval("\$settingcode = \"".$setting['optionscode']."\";");
				}
				else
				{
					for($i=0;$i<count($type);$i++)
					{
						$optionsexp = explode("=", $type[$i]);
						$lang_string =  "setting_".$setting['name']."_".$optionsexp[0];
						if($lang->$lang_string)
						{
							$lang_string = $lang->$lang_string;
						}
						else
						{
							$lang_string = $optionsexp[1];
						}
						if(!$optionsexp[1])
						{
							continue;
						}
						if($type[0] == "select")
						{
							if($setting[value] == $optionsexp[0])
							{
								$sel = "selected";
							}
							else
							{
								$sel = "";
							}
							$options .= "<option value=\"$optionsexp[0]\" $sel>{$lang_string}</option>";
						}
						else if($type[0] == "radio")
						{
							if($setting[value] == $optionsexp[0])
							{
								$sel = "checked";
							}
							else
							{
								$sel = "";
							}
							$options .= "<input type=\"radio\" name=\"upsetting[$setting[sid]]\" value=\"$optionsexp[0]\" $sel>&nbsp;{$lang_string}<br />";
						}
						else if($type[0] == "checkbox")
						{
							if($setting[value] == $optionsexp[0])
							{
								$sel = "checked";
							}
							else
							{
								$sel = "";
							}
							$options .= "<input type=\"checkbox\" name=\"upsetting[$setting[sid]]\" value=\"$optionsexp[0]\" $sel>&nbsp;{$lang_string}<br />";
						}
					}
					if($type[0] == "select")
					{
						$settingcode = "<select name=\"upsetting[$setting[sid]]\">$options</select>";
					}
					else
					{
						$settingcode = "$options";
					}
				}
				// Check if a custom language string exists for this setting title and description
				$title_lang = "setting_".$setting['name'];
				$desc_lang = $title_lang."_desc";
				if($lang->$title_lang)
				{
					$setting['title'] = $lang->$title_lang;
				}
				if($lang->$desc_lang)
				{
					$setting['description'] = $lang->$desc_lang;
				}
				tablesubheader($setting[title], "", 2, "left");
				makelabelcode("<small>$setting[description]</small>", $settingcode);
				$settingcode = "";
			}
			endtable();
		}
		endform($lang->submit_changes, $lang->reset_button);
	}
	else
	{ // Generate a listing of all of the setting groups
		$hopto[] = "<input type=\"button\" value=\"$lang->add_new_setting\" onclick=\"hopto('settings.php?".SID."&action=add');\" class=\"hoptobutton\">";
		$hopto[] = "<input type=\"button\" value=\"$lang->manage_settings\" onclick=\"hopto('settings.php?".SID."&action=modify');\" class=\"hoptobutton\">";
		$hopto[] = "<input type=\"button\" value=\"$lang->show_all_settings\" onclick=\"hopto('settings.php?".SID."&action=change&gid=-1');\" class=\"hoptobutton\">";
		makehoptolinks($hopto);
		starttable();
		tableheader($lang->board_settings, "", "2");
		echo "<tr>\n";
		echo "<td class=\"subheader\">$lang->sections</td>\n";
		echo "<td class=\"subheader\" align=\"center\">$lang->options</td>\n";
		echo "</tr>\n";
		$query = $db->query("SELECT g.*, COUNT(s.sid) AS settingcount FROM ".TABLE_PREFIX."settinggroups g LEFT JOIN ".TABLE_PREFIX."settings s ON (s.gid=g.gid) WHERE g.disporder>0 GROUP BY s.gid ORDER BY g.disporder");
		while($group = $db->fetch_array($query))
		{
			if($group['settingcount'] != 1)
			{
				$settings_count = sprintf($lang->settings_count, $group['settingcount']);
			}
			else
			{
				$settings_count = $lang->setting_count;
			}
			// Check if a custom language string exists for this setting group name and description
			$title_lang = "setting_group_".$group['name'];
			$desc_lang = $title_lang."_desc";
			if($lang->$title_lang)
			{
				$group['title'] = $lang->$title_lang;
			}
			if($lang->$desc_lang)
			{
				$group['description'] = $lang->$desc_lang;
			}
			
			$bgcolor = getaltbg();
			startform("settings.php");
			makehiddencode("gid", $group['gid']);
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\" width=\"88%\"><strong><a href=\"settings.php?".SID."&action=change&gid=".$group['gid']."\">".$group['title']."</a></strong> (".$settings_count.")<br /><small>".$group['description']."</small>";
			if(md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7" || $group['isdefault'] != "yes")
			{
				$options['change'] = $lang->modify_settings;
				$options['edit'] = $lang->edit_setting_group;
				$options['add'] = $lang->add_setting;
				$options['delete'] = $lang->delete_setting_group;
			}
			else
			{
				$options['change'] = $lang->modify_settings;
			}
			echo "<td class=\"$bgcolor\" align=\"right\" nowrap=\"nowrap\">".makehopper("action", $options)."</td>\n";
			unset($options);
			echo "</tr>\n";
			endform();
		}
		endtable();
	}
	cpfooter();
}

?>