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

// Just a little fix here
$db->query("DELETE FROM ".TABLE_PREFIX."templates WHERE title=''");

// Load language packs for this section
global $lang;
$lang->load("templates");

addacpnav($lang->nav_templates, "templates.php");
switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_template);
		break;
	case "edit":
		addacpnav($lang->nav_edit_template);
		break;
	case "delete":
		addacpnav($lang->nav_delete_template);
		break;
	case "addset":
		addacpnav($lang->nav_add_set);
		break;
	case "editset":
		addacpnav($lang->nav_edit_set);
		break;
	case "deleteset":
		addacpnav($lang->nav_delete_set);
		break;
	case "findupdated":
		addacpnav($lang->nav_find_updated);
		break;
	default:
		if($mybb->input['expand'])
		{
			if($mybb->input['expand'] == "-1")
			{
				addacpnav($lang->global_templates);
			}
			else
			{
				$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templatesets WHERE sid='".intval($mybb->input['expand'])."'");
				$set = $db->fetch_array($query);
				addacpnav($set['title']);
			}
		}
		break;
}

$expand = $mybb->input['expand'];
$group = $mybb->input['group'];

checkadminpermissions("canedittemps");
logadmin();

if($mybb->input['action'] == "do_add") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE sid='".intval($mybb->input['setid'])."' AND title='".addslashes($mybb->input['title'])."'");
	$temp = $db->fetch_array($query);
	if($temp[tid]) {
		cperror($lang->name_exists);
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='".addslashes($mybb->input['title'])."' AND sid='-2'");
	$templateinfo = $db->fetch_array($query);
	if($templateinfo['template'] == $mybb->input['template'])
	{
		cperror($lang->template_same_master);
	}
	$newtemplate = array(
		"title" => addslashes($mybb->input['title']),
		"template" => addslashes($mybb->input['template']),
		"sid" => $mybb->input['setid'],
		"version" => $mybboard['vercode'],
		"status" => "",
		"dateline" => time()
		);
	$db->insert_query(TABLE_PREFIX."templates", $newtemplate);
	$tid = $db->insert_id();
	if($mybb->input['group'])
	{
		$opengroup = "&group=".$mybb->input['group']."#".$mybb->input['group'];
	}
	if($mybb->input['continue'] != "yes")
	{
		$editurl = "templates.php?expand=".$mybb->input['setid'].$opengroup;
	}
	else
	{
		$editurl = "templates.php?action=edit&tid=".$tid."&continue=yes&group=".$mybb->input['group'];
	}
	cpredirect($editurl, $lang->template_added);
}
if($mybb->input['action'] == "do_addset") {
	$newset = array(
		"title" => addslashes($mybb->input['title'])
		);
	$db->insert_query(TABLE_PREFIX."templatesets", $newset);
	$setid = $db->insert_id();
	cpredirect("templates.php?expand=$setid", $lang->set_added);
}
	
if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{	
		$db->query("DELETE FROM ".TABLE_PREFIX."templates WHERE tid='".$mybb->input['tid']."'");
		if($mybb->input['group'])
		{
			$opengroup = "&group=".$mybb->input['group']."#".$mybb->input['group'];
		}
		cpredirect("templates.php?expand=".$mybb->input['expand'].$opengroup, $lang->template_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
		$expand = $template[sid];
	}
}
if($mybb->input['action'] == "do_deleteset")
{
	if($mybb->input['deletesubmit'])
	{	
		$db->query("DELETE FROM ".TABLE_PREFIX."templatesets WHERE sid='".$mybb->input['setid']."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."templates WHERE sid='".$mybb->input['setid']."'");
		cpredirect("templates.php?action=modify", $lang->set_deleted);
	}
	else
	{
		cpredirect("templates.php");
	}
}
if($mybb->input['action'] == "do_editset")
{
	$db->query("UPDATE ".TABLE_PREFIX."templatesets SET title='".addslashes($mybb->input['title'])."' WHERE sid='".intval($mybb->input['setid'])."'");
	cpredirect("templates.php", $lang->set_edited);
}

if($mybb->input['action'] == "do_edit")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE tid='".$mybb->input['tid']."'");
	$templateinfo = $db->fetch_array($query);

	if($mybb->input['title'] == "")
	{
		$mybb->input['title'] = $templateinfo['title'];
	}
	$updatedtemplate = array(
		"title" => addslashes($mybb->input['title']),
		"template" => addslashes($mybb->input['template']),
		"sid" => intval($mybb->input['setid']),
		"version" => $mybboard['vercode'],
		"status" => "",
		"dateline" => time()
		);
	$db->update_query(TABLE_PREFIX."templates", $updatedtemplate, "tid='".$mybb->input['tid']."'");
	if($mybb->input['group'])
	{
		$opengroup = "&group=".$mybb->input['group']."#".$mybb->input['group'];
	}
	if($mybb->input['continue'] != "yes")
	{
		$editurl = "templates.php?expand=".$mybb->input['setid'].$opengroup;
	}
	else
	{
		$editurl = "templates.php?action=edit&tid=".$mybb->input['tid']."&continue=yes&group=".$mybb->input['group'];
	}
	cpredirect($editurl, $lang->template_edited);
}
if($mybb->input['action'] == "do_replace") {
	$noheader = 1;
	if(!$mybb->input['find']) {
		cpmessage($lang->search_noneset);
	} else {
		cpheader();
		starttable();
		tableheader($lang->search_results);
		$lang->search_header = sprintf($lang->search_header, $mybb->input['find']);
		tablesubheader($lang->search_header);
		echo "<tr>\n";
		echo "<td class=\"altbg1\">\n";
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE sid>='1'");
		while($template = $db->fetch_array($query)) {
			$newtemplate = str_replace($mybb->input['find'], $mybb->input['replace'], $template['template']);
			if($newtemplate != $template['template']) {
				if($mybb->input['replace'] != "") {
					$updatedtemplate = array(
						"template" => addslashes($newtemplate)
						);
					$db->update_query(TABLE_PREFIX."templates", $updatedtemplate, "tid='".$template['tid']."'");
					echo "$lang->search_updated $template[title]".
						makelinkcode($lang->search_edit, "templates.php?action=edit&tid=".$template[tid]).
						"<br>";
				} else {
					echo "$lang->search_found $template[title]".
						makelinkcode($lang->search_edit, "templates.php?action=edit&tid=".$template[tid]).
						"<br>";
				}
			}
		}
		echo "</td>\n</tr>";
		endtable();
		cpfooter();
	}
}
if($mybb->input['action'] == "edit") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE tid='".$mybb->input['tid']."'");
	$template = $db->fetch_array($query);
	cpheader();
	if($template[sid] != "-2") {
		startform("templates.php", "" , "do_edit");
		makehiddencode("tid", $mybb->input['tid']);
		starttable();
		tableheader($lang->modify_template);
		makeinputcode($lang->title, "title", $template[title]);
	} elseif(md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7" && $template[sid] == -2) {
		startform("templates.php", "" , "do_edit");
		makehiddencode("tid", $mybb->input['tid']);
		starttable();
		tableheader($lang->modify_master_template);
		makeinputcode($lang->title, "title", $template[title]);
	} else {
		starttable();
		tableheader($lang->view_template);
		makelabelcode($lang->title, $template[title]);
	}
	maketextareacode($lang->template, "template", $template[template], "25", "80");
	if($template[sid] != "-2") {
		$query = $db->query("SELECT tid FROM ".TABLE_PREFIX."templates WHERE title='".addslashes($template['title'])."' AND sid='-2';");
		$master = $db->fetch_array($query);
		if($master['tid'])
		{
			makelabelcode($lang->options, "<a href=\"templates.php?action=edit&tid=".$master['tid']."\">".$lang->view_original."</a>");
		}
		makeselectcode($lang->template_set, "setid", "templatesets", "sid", "title", $template[sid], "-1=Global - All Template Sets");
	} else {
		makehiddencode("setid", $template[sid]);
	}
	if($mybb->input['continue'])
	{
		$continue = "yes";
	}
	else
	{
		$continue = "no";
	}
	if(($template[sid] != -2) || (md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7" && $template[sid] == -2)) {
		makeyesnocode($lang->continue_editing, "continue", $continue);
	}
	endtable();
	makehiddencode("group", $mybb->input['group']);
	if(($template[sid] != -2) || (md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7" && $template[sid] == -2)) {
		endform($lang->update_template, $lang->reset_button);
	}
	cpfooter();
}
if($mybb->input['action'] == "editset") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templatesets WHERE sid='".$mybb->input['setid']."'");
	$set = $db->fetch_array($query);
	cpheader();
	startform("templates.php", "" , "do_editset");
	makehiddencode("setid", $mybb->input['setid']);
	starttable();
	tableheader($lang->modify_set);
	makeinputcode($lang->title, "title", $set[title]);
	endtable();
	endform($lang->update_set, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "delete" || $mybb->input['action'] == "revert") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE tid='".$mybb->input['tid']."'");
	$template = $db->fetch_array($query);

	cpheader();
	startform("templates.php", "", "do_delete");
	makehiddencode("tid", $mybb->input['tid']);
	starttable();
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	if($mybb->input['action'] == "revert")
	{
		tableheader($lang->revert_template, "", 1);
		makelabelcode("<center>$lang->revert_template_notice<br><br>$yes$no</center>", "");
	}
	else
	{
		tableheader($lang->delete_template, "", 1);
		makelabelcode("<center>$lang->delete_template_notice<br><br>$yes$no</center>", "");
	}
	makehiddencode("expand", $mybb->input['expand']);
	makehiddencode("group", $mybb->input['group']);
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "deleteset") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templatesets WHERE sid='".$mybb->input['setid']."'");
	$templateset = $db->fetch_array($query);
	cpheader();
	startform("templates.php", "", "do_deleteset");
	makehiddencode("setid", $mybb->input['setid']);
	starttable();
	tableheader($lang->delete_template_set, "", 1);
	$yes = makebuttoncode("deletesubmit", "Yes");
	$no = makebuttoncode("no", "No");
	makelabelcode("<center>$lang->delete_set_notice $templateset[title]?<br><br>$yes$no</center>", "");
	endtable();
	endform();
	cpfooter();
}
if($mybb->input['action'] == "makeoriginals") {
	$query = $db->query("SELECT t1.*, t2.title AS origtitle FROM ".TABLE_PREFIX."templates t1 LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t1.title=t2.title AND t2.sid='-2') WHERE t1.sid='".$mybb->input['setid']."'");
	$query2 = $db->query("SELECT t1.* FROM ".TABLE_PREFIX."templates t1 LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t1.title=t2.title AND t2.sid='-2') WHERE t1.sid='$set[sid]' AND ISNULL(t2.template) ORDER BY t1.title ASC");

	$query = $db->query("SELECT * FROM templates WHERE sid='".$mybb->input['setid']."'");
	while($template = $db->fetch_array($query)) {
		if($template[origtitle]) {
			$updatedtemplate = array(
				"template" => addslashes($template['template'])
				);
			$db->update_query(TABLE_PREFIX."templates", $updatedtemplate, "title='".$template['title']."' AND sid='-2'");
		} else {
			$newtemplate = array(
				"sid" => -2,
				"title" => addslashes($template['title']),
				"template" => addslashes($template['template'])
				);
			$db->insert_query(TABLE_PREFIX."templates", $newtemplate);
		}
	}
	$db->query("DELETE FROM ".TABLE_PREFIX."templates WHERE sid='".$mybb->input['setid']."'");
	cpredirect("templates.php?expand=$setid", $lang->originals_made);
}

if($mybb->input['action'] == "add") {
	if($mybb->input['title']) {
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='".$mybb->input['title']."' AND sid='-2'");
		$template = $db->fetch_array($query);
	}
	cpheader();
	startform("templates.php", "" , "do_add");
	starttable();
	if(md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7") {
		tableheader($lang->add_master_template);
	} else {
		tableheader($lang->add_template);
	}
	makeinputcode($lang->title, "title", $template[title]);
	maketextareacode($lang->template, "template", $template[template], "25", "80");
	if(md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7") {
		makehiddencode("setid", -2);
	} else {
		makeselectcode($lang->template_set, "setid", "templatesets", "sid", "title", $mybb->input['sid'], "-1=".$lang->global_sel);
	}
	makeyesnocode($lang->continue_editing, "continue", "no");
	endtable();
	makehiddencode("group", $mybb->input['group']);
	endform($lang->add_template, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "addset") {
	cpheader();
	startform("templates.php", "" , "do_addset");
	starttable();
	tableheader($lang->add_set);
	makeinputcode($lang->title, "title", "");
	endtable();
	endform($lang->add_set, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "search") {
	if(!$noheader) {
		cpheader();
	}
	startform("templates.php", "", "do_replace");
	starttable();
	tableheader($lang->search_replace);
	makeinputcode($lang->search_for, "find");
	makeinputcode($lang->replace_with, "replace");
	endtable();
	endform($lang->find_replace, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "diff")
{
	// Compares a template of sid1 with that of sid2, if no sid1, it is assumed -2
	if(!$mybb->input['sid1'])
	{
		$mybb->input['sid1'] = -2;
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='".$mybb->input['title']."' AND sid='".$mybb->input['sid1']."'");
	$template1 = $db->fetch_array($query);

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='".$mybb->input['title']."' AND sid='".$mybb->input['sid2']."'");
	$template2 = $db->fetch_array($query);

	if($template1['template'] == $template2['template'])
	{
		cpmessage($lang->templates_the_same);
	}

	$template1['template'] = explode("\n", htmlspecialchars($template1['template']));
	$template2['template'] = explode("\n", htmlspecialchars($template2['template']));

	require "./inc/class_diff.php";

	$diff = &new Text_Diff($template1['template'], $template2['template']);
	$renderer = &new Text_Diff_Renderer_inline();
	cpheader();
	if($mybb->input['sid2'] == -2)
	{
		starttable();
		makelabelcode("<ins>".$lang->master_updated_ins."</ins><br /><del>".$lang->master_updated_del."</del>");
		endtable();
	}
	starttable();
	tableheader($lang->template_diff_analysis, "", 1);
	makelabelcode("<pre>".$renderer->render($diff)."</pre>", "");
	endtable();
	cpfooter();
}

if($mybb->input['action'] == "findupdated")
{
	// Finds templates that are old and have been updated by MyBB
	$compare_version = $mybboard['vercode'];
	$query = $db->query("SELECT COUNT(*) AS updated_count FROM ".TABLE_PREFIX."templates t INNER JOIN ".TABLE_PREFIX."templates m ON (m.title=t.title AND m.sid=-2 AND m.version>t.version) WHERE t.sid>0");
	$count = $db->fetch_array($query);

	if($count['updated_count'] < 1)
	{
		cpmessage($lang->no_updated_templates);
	}
	cpheader();

	$query = $db->query("SELECT* FROM ".TABLE_PREFIX."templatesets ORDER BY title ASC");
	while($templateset = $db->fetch_array($query))
	{
		$templatesets[$templateset['sid']] = $templateset;
	}

	starttable();
	makelabelcode($lang->updated_template_welcome."<ul><li>".$lang->updated_template_welcome1."</li><li>".$lang->updated_template_welcome2."</li><li>".$lang->updated_template_welcome3."</li></ul>");
	endtable();

	starttable();
	tableheader($lang->updated_template_management, "", 3);
	$query = $db->query("SELECT t.tid,t.title, t.sid, t.version FROM ".TABLE_PREFIX."templates t INNER JOIN ".TABLE_PREFIX."templates m ON (m.title=t.title AND m.sid=-2 AND m.version>t.version) WHERE t.sid>0 ORDER BY t.sid ASC, title ASC");
	while($template = $db->fetch_array($query))
	{
		if(!$done_set[$template['sid']])
		{
			tablesubheader($templatesets[$template['sid']]['title'], "", 3);
			$done_set[$template['sid']] = 1;
		}
		$altbg = getaltbg();
		echo "<tr>";
		echo "<td class=\"$altbg\" width=\"10\">&nbsp;</td>\n";
		echo "<td class=\"$altbg\"><a href=\"templates.php?action=edit&tid=".$template['tid']."\">".$template['title']."</a></td>";
		echo "<td class=\"$altbg\" align=\"right\">";
		echo "<input type=\"button\" value=\"$lang->edit\" onclick=\"hopto('templates.php?action=edit&tid=".$template['tid']."');\" class=\"submitbutton\">";
		echo "<input type=\"button\" value=\"$lang->revert\" onclick=\"hopto('templates.php?action=revert&tid=".$template['tid']."');\" class=\"submitbutton\">";
		echo "<input type=\"button\" value=\"$lang->diff\" onclick=\"hopto('templates.php?action=diff&title=".$template['title']."&sid1=".$template['sid']."&sid2=-2');\" class=\"submitbutton\">";
		echo "</td>";
		echo "</tr>";
	}
	endtable();
	cpfooter();
}

if($mybb->input['action'] == "modify" || $mybb->input['action'] == "") {

	if(!$noheader) {
		cpheader();
	}
	// Fetch the listing of themes so we can see which template sets are associated to themes
	$query = $db->query("SELECT name,tid,themebits FROM ".TABLE_PREFIX."themes WHERE tid!='1'");
	while($theme = $db->fetch_array($query))
	{
		$tbits = unserialize($theme['themebits']);
		$themes[$tbits['templateset']][$theme['tid']] = $theme;
	}

	if(!$expand) // Build a listing of all of the template sets
	{
		if(md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7")
		{
			$templatesets[-20]['title'] = $lang->master_templates;
			$templatesets[-20]['sid'] = -2;
		}
		$templatesets[-10]['title'] = $lang->global_templates;
		$templatesets[-10]['sid'] = -1;

		$query = $db->query("SELECT* FROM ".TABLE_PREFIX."templatesets ORDER BY title ASC");
		while($templateset = $db->fetch_array($query))
		{
			$templatesets[$templateset['sid']] = $templateset;
		}
	
		starttable();
		tableheader($lang->template_management, "", 1);
		foreach($templatesets as $templateset)
		{
			echo "<tr>\n";
			echo "<td class=\"subheader\">";
			echo "<div style=\"float: right;\">";
			echo "<input type=\"button\" value=\"$lang->add_template\" onclick=\"hopto('templates.php?action=add&sid=".$templateset['sid']."');\" class=\"submitbutton\">";
			if($templateset['sid'] != "-2" && $templateset['sid'] != "-1")
			{
				echo "<input type=\"button\" value=\"$lang->edit_set\" onclick=\"hopto('templates.php?action=editset&setid=".$templateset['sid']."');\" class=\"submitbutton\">";
				if(!$themes[$templateset['sid']])
				{
					echo "<input type=\"button\" value=\"$lang->delete_set\" onclick=\"hopto('templates.php?action=deleteset&setid=".$templateset['sid']."');\" class=\"submitbutton\">";
				}
			}
			echo "<input type=\"button\" value=\"$lang->expand\" onclick=\"hopto('templates.php?expand=".$templateset['sid']."');\" class=\"submitbutton\">";
			echo "</div><div>".$templateset['title']."</div></td>\n";
			echo "</tr>\n";
			if($themes[$templateset['sid']])
			{
				$note = $lang->template_set_associated_themes;
				$note .= "<ul>";
				foreach($themes[$templateset['sid']] as $theme)
				{
					$note .= "<li>".$theme['name']."</li>";
				}
				$note .= "</ul>";
				$note .= $lang->template_set_associated_themes2;
			}
			elseif($templateset['sid'] == -2)
			{
				$note = $lang->template_set_master_templates;
			}
			elseif($templateset['sid'] == -1)
			{
				$note = $lang->template_set_global_templates;
			}
			else
			{
				$note = $lang->template_set_no_associated_themes;
			}
			makelabelcode($note);
		}
		endtable();
	}
	else // We're showing a specific template set
	{
		if($expand == -2)
		{
			$templateset['title'] = $lang->master_templates;
			$templateset['sid'] = -2;
		}
		elseif($expand == -1)
		{
			$templateset['title'] = $lang->global_templates;
			$templateset['sid'] = -1;
		}
		else
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templatesets WHERE sid='".$expand."'");
			$templateset = $db->fetch_array($query);
			starttable();
			makelabelcode("<span class=\"highlight4\">$lang->template_color1_note</span><br /><span class=\"highlight3\">$lang->template_color2_note</span><br /><span class=\"highlight2\">$lang->template_color3_note</span>");
			endtable();
		}

		starttable();
		tableheader($lang->template_management." (".$templateset['title'].")", "", 3);
		echo "<tr>\n";
		echo "<td class=\"subheader\" colspan=\"3\">";
		echo "<div style=\"float: right;\">";
		echo "<input type=\"button\" value=\"$lang->add_template\" onclick=\"hopto('templates.php?action=add&sid=".$templateset['sid']."');\" class=\"submitbutton\">";
		if($templateset['sid'] != "-2" && $templateset['sid'] != "-1")
		{
			echo "<input type=\"button\" value=\"$lang->edit_set\" onclick=\"hopto('templates.php?action=editset&setid=".$templateset['sid']."');\" class=\"submitbutton\">";
			if(!$themes[$expand])
			{
				echo "<input type=\"button\" value=\"$lang->delete_set\" onclick=\"hopto('templates.php?action=deleteset&setid=".$templateset['sid']."');\" class=\"submitbutton\">";
			}
		}
		echo "<input type=\"button\" value=\"$lang->collapse\" onclick=\"hopto('templates.php?');\" class=\"submitbutton\">";
		echo "</div><div>".$templateset['title']."</div></td>\n";
		echo "</tr>\n";
		if($expand == -2 && md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7")
		{
			// Master templates
			$query = $db->query("SELECT tid,title FROM ".TABLE_PREFIX."templates WHERE sid='-2' ORDER BY title ASC");
			while($template = $db->fetch_array($query))
			{
				$altbg = getaltbg();
				echo "<tr>";
				echo "<td class=\"$altbg\" width=\"10\">&nbsp;</td>\n";
				echo "<td class=\"$altbg\"><a href=\"templates.php?action=edit&tid=".$template['tid']."\">".$template['title']."</a></td>";
				echo "<td class=\"$altbg\" align=\"right\">";
				echo "<input type=\"button\" value=\"$lang->edit\" onclick=\"hopto('templates.php?action=edit&tid=".$template['tid']."');\" class=\"submitbutton\">";
				echo "<input type=\"button\" value=\"$lang->delete\" onclick=\"hopto('templates.php?action=delete&tid=".$template['tid']."');\" class=\"submitbutton\">";
				echo "</td>";
				echo "</tr>";
			}
		}
		elseif($expand == -1)
		{
			// Global Templates
			$query = $db->query("SELECT tid,title FROM ".TABLE_PREFIX."templates WHERE sid='-1' ORDER BY title ASC");
			while($template = $db->fetch_array($query))
			{
				$altbg = getaltbg();
				echo "<tr>";
				echo "<td class=\"$altbg\" width=\"10\">&nbsp;</td>\n";
				echo "<td class=\"$altbg\"><a href=\"templates.php?action=edit&tid=".$template['tid']."\"><span class=\"highlight4\">".$template['title']."</span></a></td>";
				echo "<td class=\"$altbg\" align=\"right\">";
				echo "<input type=\"button\" value=\"$lang->edit\" onclick=\"hopto('templates.php?action=edit&tid=".$template['tid']."');\" class=\"submitbutton\">";
				echo "<input type=\"button\" value=\"$lang->delete\" onclick=\"hopto('templates.php?action=delete&tid=".$template['tid']."');\" class=\"submitbutton\">";
				echo "</td>";
				echo "</tr>";
			}
		}
		else
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templategroups ORDER BY title ASC");
			while($templategroup = $db->fetch_array($query))
			{
				if($mybb->input['group'] == $templategroup['gid'])
				{
					$expand_group = $templategroup['prefix'];
				}
				$templategroups[$templategroup['prefix']] = $templategroup;
			}

			// Query for custom templates
			$query2 = $db->query("SELECT t1.* FROM ".TABLE_PREFIX."templates t1 LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t1.title=t2.title AND t2.sid='-2') WHERE t1.sid='".$set[sid]."' AND ISNULL(t2.template) ORDER BY t1.title ASC");
			while($template = $db->fetch_array($query2))
			{
				$template['customtemplate'] = 1;
				$templatelist[$template['title']] = $template;
			}

			// Query for original templates
			$query3 = $db->query("SELECT t1.title AS originaltitle, t1.tid AS originaltid, t2.tid FROM ".TABLE_PREFIX."templates t1 LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t2.title=t1.title AND t2.sid='".$set[sid]."') WHERE t1.sid='-2' ORDER BY t1.title ASC");
			while($template = $db->fetch_array($query3)) {
				$templatelist[$template['originaltitle']] = $template;
			}
			reset($templatelist);
			ksort($templatelist);
			foreach($templatelist as $template)
			{
				if($template['customtemplate'])
				{
					$checkname = $template['title'];
				}
				else
				{
					$checkname = $template['originaltitle'];
				}
				$exploded = explode("_", $checkname, 2);
				reset($templategroups);
				$grouptype = "";
				$opengroup = "";
				if($templategroups[$exploded[0]])
				{
					$gid = $templategroups[$exploded[0]]['gid'];
					$grouptype = $exploded[0];
					if(!$donegroup[$exploded[0]])
					{
						$groupname = $lang->parse($templategroups[$exploded[0]]['title']);
						$altbg = getaltbg();
						echo "<tr>\n";
						echo "<td class=\"$altbg\" colspan=\"2\"><b><a href=\"templates.php?expand=$expand&group=$gid#$gid\" name=\"$gid\">$groupname $lang->templates</a></b></td>\n";
						echo "<td class=\"$altbg\" align=\"right\"><input type=\"button\" value=\"$lang->expand\" onclick=\"hopto('templates.php?expand=$expand&group=$gid#$gid');\" class=\"submitbutton\"></td>\n";
						echo "</tr>\n";
						$donegroup[$grouptype] = 1;
					}
					if($expand_group != $grouptype && $mybb->input['group'] != "all")
					{
						continue;
					}
					elseif($mybb->input['group'] != "all")
					{
						$opengroup = "&group=".$gid;
					}
				}
				$altbg = getaltbg();
				if($grouptype)
				{
					echo "<tr>\n";
					echo "<td class=\"$altbg\" width=\"10\">&nbsp;</td>\n";
					echo "<td class=\"$altbg\">";
				}
				else
				{
					echo "<tr>\n";
					echo "<td class=\"$altbg\" colspan=\"2\">\n";
				}
				if(!$template['tid'])
				{
					echo "<a href=\"templates.php?action=add&title=".$template['originaltitle']."&sid=".$set['sid'].$opengroup."\"><span class=\"highlight4\">".$template['originaltitle']."</span></a></td>\n";
					echo "<td class=\"$altbg\" align=\"right\">";
					echo "<input type=\"button\" value=\"$lang->change_original\" onclick=\"hopto('templates.php?action=add&title=".$template['originaltitle']."&sid=".$set['sid']."&group=$grouptype');\" class=\"submitbutton\">";
					echo "</td>\n";
					echo "</tr>\n";
				}
				elseif($template['customtemplate'])
				{
						echo "<a href=\"templates.php?action=edit&tid=".$template['tid'].$opengroup."\"><span class=\"highlight2\">".$template['title']."</span></a></td>";
						echo "<td class=\"$altbg\" align=\"right\">";
						echo "<input type=\"button\" value=\"$lang->edit\" onclick=\"hopto('templates.php?action=edit&tid=".$template['tid']."&group=$grouptype');\" class=\"submitbutton\">";
						echo "<input type=\"button\" value=\"$lang->delete\" onclick=\"hopto('templates.php?action=delete&tid=".$template['tid']."&expand=$expand&group=$grouptype');\" class=\"submitbutton\">";
						echo "</td>\n";
						echo "</tr>\n";
				}
				else
				{
					echo "<a href=\"templates.php?action=edit&tid=".$template['tid'].$opengroup."\"><span class=\"highlight3\">".$template['originaltitle']."</span></a></td>";
					echo "<td class=\"$altbg\" align=\"right\">";
					echo "<input type=\"button\" value=\"$lang->edit\" onclick=\"hopto('templates.php?action=edit&tid=".$template['tid']."&group=$grouptype');\" class=\"submitbutton\">";
					echo "<input type=\"button\" value=\"$lang->revert_original\" onclick=\"hopto('templates.php?action=revert&tid=".$template['tid']."&expand=$expand&group=$grouptype');\" class=\"submitbutton\">";
					echo "</td>\n";
					echo "</tr>\n";
				}
				$grouptype = "";
			}
		}
		endtable();
	}
	cpfooter();
}
?>
