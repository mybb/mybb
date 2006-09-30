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

require_once "./global.php";

// Just a little fix here
$db->query("DELETE FROM ".TABLE_PREFIX."templates WHERE title=''");

// Load language packs for this section
global $lang;
$lang->load("templates");

$mybb->input['tid'] = intval($mybb->input['tid']);
$mybb->input['setid'] = intval($mybb->input['setid']);
$mybb->input['expand'] = intval($mybb->input['expand']);
$mybb->input['sid2'] = intval($mybb->input['sid2']);
$mybb->input['sid'] = intval($mybb->input['sid']);

addacpnav($lang->nav_templates, "templates.php?".SID);
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
	case "diff":
		addacpnav($lang->nav_diff);
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

$plugins->run_hooks("admin_templates_start");

$expand = $mybb->input['expand'];
$group = $mybb->input['group'];

checkadminpermissions("canedittemps");
logadmin();

if($mybb->input['action'] == "do_add")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE sid='".intval($mybb->input['setid'])."' AND title='".$db->escape_string($mybb->input['title'])."'");
	$temp = $db->fetch_array($query);
	if($temp['tid'])
	{
		cperror($lang->name_exists);
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='".$db->escape_string($mybb->input['title'])."' AND sid='-2'");
	$templateinfo = $db->fetch_array($query);
	if($templateinfo['template'] == $mybb->input['template'])
	{
		cperror($lang->template_same_master);
	}
	$newtemplate = array(
		"title" => $db->escape_string($mybb->input['title']),
		"template" => $db->escape_string($mybb->input['template']),
		"sid" => intval($mybb->input['setid']),
		"version" => $mybb->version_code,
		"status" => "",
		"dateline" => time()
	);
	$plugins->run_hooks("admin_templates_do_add");
	$db->insert_query("templates", $newtemplate);
	$tid = $db->insert_id();
	if($mybb->input['group'])
	{
		$opengroup = "&amp;group=".$mybb->input['group']."#".$mybb->input['group'];
	}
	if($mybb->input['continue'] != "yes")
	{
		$editurl = "templates.php?".SID."&expand=".$mybb->input['setid'].$opengroup;
	}
	else
	{
		$editurl = "templates.php?".SID."&action=edit&tid=".$tid."&continue=yes&group=".$mybb->input['group'];
	}
	cpredirect($editurl, $lang->template_added);
}
if($mybb->input['action'] == "do_addset")
{
	$newset = array(
		"title" => $db->escape_string($mybb->input['title'])
		);
	$plugins->run_hooks("admin_templates_do_addset");
	$db->insert_query("templatesets", $newset);
	$setid = $db->insert_id();
	cpredirect("templates.php?".SID."&expand=$setid", $lang->set_added);
}

if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{
		$plugins->run_hooks("admin_templates_do_delete");
		$db->query("DELETE FROM ".TABLE_PREFIX."templates WHERE tid='".$mybb->input['tid']."'");
		if($mybb->input['group'])
		{
			$opengroup = "&amp;group=".$mybb->input['group']."#".$mybb->input['group'];
		}
		cpredirect("templates.php?".SID."&expand=".$mybb->input['expand'].$opengroup, $lang->template_deleted);
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
		$plugins->run_hooks("admin_templates_do_deleteset");
		$db->query("DELETE FROM ".TABLE_PREFIX."templatesets WHERE sid='".$mybb->input['setid']."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."templates WHERE sid='".$mybb->input['setid']."'");
		cpredirect("templates.php?".SID."&action=modify", $lang->set_deleted);
	}
	else
	{
		cpredirect("templates.php?".SID);
	}
}
if($mybb->input['action'] == "do_editset")
{
	$plugins->run_hooks("admin_templates_do_editset");
	$db->query("UPDATE ".TABLE_PREFIX."templatesets SET title='".$db->escape_string($mybb->input['title'])."' WHERE sid='".intval($mybb->input['setid'])."'");
	cpredirect("templates.php?".SID, $lang->set_edited);
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
		"title" => $db->escape_string($mybb->input['title']),
		"template" => $db->escape_string($mybb->input['template']),
		"sid" => intval($mybb->input['setid']),
		"version" => $mybb->version_code,
		"status" => "",
		"dateline" => time()
	);
	$plugins->run_hooks("admin_templates_do_edit");
	$db->update_query("templates", $updatedtemplate, "tid='".$mybb->input['tid']."'");
	if($mybb->input['group'])
	{
		$opengroup = "&amp;group=".$mybb->input['group']."#".$mybb->input['group'];
	}
	if($mybb->input['continue'] != "yes")
	{
		$editurl = "templates.php?".SID."&expand=".$mybb->input['setid'].$opengroup;
	}
	else
	{
		$editurl = "templates.php?".SID."&action=edit&tid=".$mybb->input['tid']."&continue=yes&group=".$mybb->input['group'];
	}
	cpredirect($editurl, $lang->template_edited);
}
if($mybb->input['action'] == "do_replace")
{
	$noheader = 1;
	// Is there something to search for?
	if(!$mybb->input['find'])
	{ // Nope!
		cperror($lang->search_noneset);
	}
	else
	{ // Yup!
		cpheader();
		starttable();
		tableheader($lang->search_results);
		// Get the names of all template sets
		$query = $db->query("SELECT sid, title FROM ".TABLE_PREFIX."templatesets");
		$template_groups[-2] = $lang->default_templates;
		$template_groups[-1] = $lang->global_templates;
		while($tgroup = $db->fetch_array($query))
		{
			$template_groups[$tgroup['sid']] = $tgroup['title'];
		}
		$plugins->run_hooks("admin_templates_do_replace");
		// Select all templates with that search term
		$query = $db->query("SELECT tid, title, template, sid FROM ".TABLE_PREFIX."templates WHERE template LIKE '%".$db->escape_string($mybb->input['find'])."%' ORDER BY sid,title ASC");
		if($db->num_rows($query) == 0)
		{
			makelabelcode(sprintf($lang->search_noresults, $mybb->input['find']));
		}
		else
		{
			while($template = $db->fetch_array($query))
			{
				if($template['sid'] == 1)
				{
					$template_list[-2][$template['title']] = $template;
				}
				else
				{
					$template_list[$template['sid']][$template['title']] = $template;
				}
			}

			// Loop templates we found
			foreach($template_list as $sid => $templates)
			{
				// Show group header
				$search_header = sprintf($lang->search_header, $mybb->input['find'], $template_groups[$sid]);
				tablesubheader($search_header);

				foreach($templates as $title => $template)
				{
					// Do replacement
					$newtemplate = str_replace($mybb->input['find'], $mybb->input['replace'], $template['template']);
					if($newtemplate != $template['template'])
					{
						// If the template is different, that means the search term has been found.
						if($mybb->input['replace'] != "")
						{
							if($template['sid'] == -2)
							{
								// The template is a master template.  We have to make a new custom template.
								$new_template = array(
									"title" => $db->escape_string($title),
									"template" => $db->escape_string($newtemplate),
									"sid" => 1,
									"version" => $mybb->version_code,
									"status" => '',
									"dateline" => time()
								);
								$db->insert_query("templates", $new_template);
								$new_tid = $db->insert_id();
								$label = sprintf($lang->search_created_custom, $template['title']);
								makelabelcode($label, makelinkcode($lang->search_edit, "templates.php?".SID."&amp;action=edit&amp;tid=".$new_tid));
							}
							else
							{
								// The template is a custom template.  Replace as normal.
								// Update the template if there is a replacement term
								$updatedtemplate = array(
									"template" => $db->escape_string($newtemplate)
									);
								$db->update_query("templates", $updatedtemplate, "tid='".$template['tid']."'");
								$label = sprintf($lang->search_updated, $template['title']);
								makelabelcode($label, makelinkcode($lang->search_edit, "templates.php?".SID."&action=edit&tid=".$template['tid']));
							}
						}
						else
						{
							// Just show that the term was found
							if($template['sid'] == -2)
							{
								$label = sprintf($lang->search_found, $template['title']);
								makelabelcode($label, makelinkcode($lang->search_change_original, "templates.php?".SID."&action=add&title=".$template['title']."&sid=1"));
							}
							else
							{
								$label = sprintf($lang->search_found, $template['title']);
								makelabelcode($label, makelinkcode($lang->search_edit, "templates.php?".SID."&action=edit&tid=".$template['tid']));
							}
						}
					}
				}
			}
		}
		endtable();
		cpfooter();
	}
}
if($mybb->input['action'] == "do_search_names")
{
	// Is there something to search for?
	if(!$mybb->input['title'])
	{ // Nope!
		cperror($lang->search_noneset);
	}
	else
	{ // Yup!
		$plugins->run_hooks("admin_templates_do_search_names");
		cpheader();
		starttable();
		tableheader($lang->search_results);
		$lang->search_names_header = sprintf($lang->search_names_header, $mybb->input['title']);
		tablesubheader($lang->search_names_header);
		// Query for templates
		$query = $db->query("
			SELECT t.tid, t.title, t.sid, s.title as settitle, t2.tid as customtid
			FROM ".TABLE_PREFIX."templates t
			LEFT JOIN ".TABLE_PREFIX."templatesets s ON (t.sid=s.sid)
			LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t.title=t2.title AND t2.sid='1')
			WHERE t.title LIKE '%".$db->escape_string($mybb->input['title'])."%'
			ORDER BY t.title ASC
		");
		while($template = $db->fetch_array($query))
		{
			$link = makelinkcode($lang->search_edit, "templates.php?".SID."&amp;action=edit&amp;tid=".$template['tid']);
			if($template['sid'] == -2)
			{
				$template['settitle'] = $lang->master_templates;
				if(!$template['customtid'])
				{
					$link = makelinkcode($lang->search_change_original, "templates.php?".SID."&amp;action=add&amp;title=".$template['title']."&amp;sid=1");
				}
				else
				{
					$link = makelinkcode($lang->search_edit, "templates.php?".SID."&amp;action=edit&amp;tid=".$template['customtid']);
				}
			}
			elseif($template['sid'] == -1)
			{
				$template['settitle'] = $lang->global_templates;
			}
			makelabelcode("<strong>{$template['title']}</strong><br />{$template['settitle']}", $link);
		}
		endtable();
		cpfooter();
	}
}
if($mybb->input['action'] == "edit")
{
	if(isset($mybb->input['title']))
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='".$db->escape_string($mybb->input['title'])."'");
		$template = $db->fetch_array($query);
	}
	else
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE tid='".intval($mybb->input['tid'])."'");
		$template = $db->fetch_array($query);
	}
	$plugins->run_hooks("admin_templates_edit");
	cpheader();
	if($template['sid'] != "-2")
	{
		startform("templates.php", "" , "do_edit");
		makehiddencode("tid", $mybb->input['tid']);
		starttable();
		tableheader($lang->modify_template);
		makeinputcode($lang->title, "title", $template[title]);
	}
	elseif(md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7" && $template['sid'] == -2)
	{
		startform("templates.php", "" , "do_edit");
		makehiddencode("tid", $mybb->input['tid']);
		starttable();
		tableheader($lang->modify_master_template);
		makeinputcode($lang->title, "title", $template['title']);
	}
	else
	{
		starttable();
		tableheader($lang->view_template);
		makelabelcode($lang->title, $template[title]);
	}
	maketextareacode($lang->template, "template", $template['template'], "25", "80");
	if($template['sid'] != "-2")
	{
		$query = $db->query("SELECT tid FROM ".TABLE_PREFIX."templates WHERE title='".$db->escape_string($template['title'])."' AND sid='-2';");
		$master = $db->fetch_array($query);
		if($master['tid'])
		{
			makelabelcode($lang->options, "<a href=\"templates.php?".SID."&amp;action=edit&amp;tid=".$master['tid']."\">".$lang->view_original."</a><br /><a href=\"templates.php?".SID."&amp;action=diff&amp;title=$template[title]&amp;sid2=$template[sid]\">".$lang->diff_with_original."</a>");
		}
		makeselectcode($lang->template_set, "setid", "templatesets", "sid", "title", $template['sid'], "-1=Global - All Template Sets");
	}
	else
	{
		makehiddencode("setid", $template['sid']);
	}
	if($mybb->input['continue'])
	{
		$continue = "yes";
	}
	else
	{
		$continue = "no";
	}
	if(($template['sid'] != -2) || (md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7" && $template['sid'] == -2))
	{
		makeyesnocode($lang->continue_editing, "continue", $continue);
	}
	endtable();
	makehiddencode("group", $mybb->input['group']);
	if(($template['sid'] != -2) || (md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7" && $template['sid'] == -2))
	{
		endform($lang->update_template, $lang->reset_button);
	}
	cpfooter();
}
if($mybb->input['action'] == "editset")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templatesets WHERE sid='".$mybb->input['setid']."'");
	$set = $db->fetch_array($query);
	$plugins->run_hooks("admin_templates_editset");
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

if($mybb->input['action'] == "delete" || $mybb->input['action'] == "revert")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE tid='".$mybb->input['tid']."'");
	$template = $db->fetch_array($query);
	$plugins->run_hooks("admin_templates_delete");
	cpheader();
	startform("templates.php", "", "do_delete");
	makehiddencode("tid", $mybb->input['tid']);
	starttable();
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	if($mybb->input['action'] == "revert")
	{
		tableheader($lang->revert_template, "", 1);
		makelabelcode("<div align=\"center\">$lang->revert_template_notice<br /><br />$yes$no</div>", "");
	}
	else
	{
		tableheader($lang->delete_template, "", 1);
		makelabelcode("<div align=\"center\">$lang->delete_template_notice<br /><br />$yes$no</div>", "");
	}
	makehiddencode("expand", $mybb->input['expand']);
	makehiddencode("group", $mybb->input['group']);
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "deleteset")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templatesets WHERE sid='".$mybb->input['setid']."'");
	$templateset = $db->fetch_array($query);
	$plugins->run_hooks("admin_templates_deleteset");
	cpheader();
	startform("templates.php", "", "do_deleteset");
	makehiddencode("setid", $mybb->input['setid']);
	starttable();
	tableheader($lang->delete_template_set, "", 1);
	$yes = makebuttoncode("deletesubmit", "Yes");
	$no = makebuttoncode("no", "No");
	makelabelcode("<div align=\"center\">$lang->delete_set_notice {$templateset['title']}?<br /><br />$yes$no</div>", "");
	endtable();
	endform();
	cpfooter();
}
if($mybb->input['action'] == "makeoriginals")
{
	$plugins->run_hooks("admin_templates_makeoriginals");
	$query = $db->query("SELECT t1.*, t2.title AS origtitle FROM ".TABLE_PREFIX."templates t1 LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t1.title=t2.title AND t2.sid='-2') WHERE t1.sid='".$mybb->input['setid']."'");
	$query2 = $db->query("SELECT t1.* FROM ".TABLE_PREFIX."templates t1 LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t1.title=t2.title AND t2.sid='-2') WHERE t1.sid='$set[sid]' AND ISNULL(t2.template) ORDER BY t1.title ASC");

	$query = $db->query("SELECT * FROM templates WHERE sid='".$mybb->input['setid']."'");
	while($template = $db->fetch_array($query))
	{
		if($template[origtitle])
    {
			$updatedtemplate = array(
				"template" => $db->escape_string($template['template'])
			);
			$db->update_query("templates", $updatedtemplate, "title='".$template['title']."' AND sid='-2'");
		}
		else
		{
			$newtemplate = array(
				"sid" => -2,
				"title" => $db->escape_string($template['title']),
				"template" => $db->escape_string($template['template'])
			);
			$db->insert_query("templates", $newtemplate);
		}
	}
	$db->query("DELETE FROM ".TABLE_PREFIX."templates WHERE sid='".$mybb->input['setid']."'");
	cpredirect("templates.php?".SID."&expand=$setid", $lang->originals_made);
}

if($mybb->input['action'] == "add")
{
	if($mybb->input['title'])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='".$db->escape_string($mybb->input['title'])."' AND sid='-2'");
		$template = $db->fetch_array($query);
	}
	$plugins->run_hooks("admin_templates_add");
	cpheader();
	startform("templates.php", "" , "do_add");
	starttable();
	if(md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7")
	{
		tableheader($lang->add_master_template);
	}
	else
	{
		tableheader($lang->add_template);
	}
	makeinputcode($lang->title, "title", $template[title]);
	maketextareacode($lang->template, "template", $template[template], "25", "80");
	if(md5($debugmode) == "0100e895f975e14f4193538dac4d0dc7")
	{
		makehiddencode("setid", -2);
	}
	else
	{
		makeselectcode($lang->template_set, "setid", "templatesets", "sid", "title", $mybb->input['sid'], "-1=".$lang->global_sel);
	}
	makeyesnocode($lang->continue_editing, "continue", "no");
	endtable();
	makehiddencode("group", $mybb->input['group']);
	endform($lang->add_template, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "addset")
{
	$plugins->run_hooks("admin_templates_addset");
	cpheader();
	startform("templates.php", "" , "do_addset");
	starttable();
	tableheader($lang->add_set);
	makeinputcode($lang->title, "title", "");
	endtable();
	endform($lang->add_set, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "search")
{
	$plugins->run_hooks("admin_templates_search");
	if(!$noheader)
	{
		cpheader();
	}
	startform("templates.php", "", "do_replace");
	starttable();
	tableheader($lang->search_replace);
	makelabelcode($lang->search_label, "", 2);
	makeinputcode($lang->search_for, "find");
	makeinputcode($lang->replace_with, "replace");
	endtable();
	endform($lang->find_replace, $lang->reset_button);

	startform("templates.php", "", "do_search_names");
	starttable();
	tableheader($lang->search_names);
	makeinputcode($lang->search_for, "title");
	endtable();
	endform($lang->find_names, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "diff")
{
	// Compares a template of sid1 with that of sid2, if no sid1, it is assumed -2
	if(!$mybb->input['sid1'])
	{
		$mybb->input['sid1'] = -2;
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='".$db->escape_string($mybb->input['title'])."' AND sid='".$mybb->input['sid1']."'");
	$template1 = $db->fetch_array($query);

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."templates WHERE title='".$db->escape_string($mybb->input['title'])."' AND sid='".$mybb->input['sid2']."'");
	$template2 = $db->fetch_array($query);

	if($template1['template'] == $template2['template'])
	{
		cpmessage($lang->templates_the_same);
	}

	$template1['template'] = explode("\n", htmlspecialchars($template1['template']));
	$template2['template'] = explode("\n", htmlspecialchars($template2['template']));

	$plugins->run_hooks("admin_templates_diff");
	require_once MYBB_ROOT."inc/class_diff.php";

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
	$compare_version = $mybb->version_code;
	$query = $db->query("SELECT COUNT(*) AS updated_count FROM ".TABLE_PREFIX."templates t INNER JOIN ".TABLE_PREFIX."templates m ON (m.title=t.title AND m.sid=-2 AND m.version>t.version) WHERE t.sid>0");
	$count = $db->fetch_array($query);

	if($count['updated_count'] < 1)
	{
		cpmessage($lang->no_updated_templates);
	}
	$plugins->run_hooks("admin_templates_findupdated");
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
		echo "<td class=\"$altbg\"><a href=\"templates.php?".SID."&amp;action=edit&amp;tid=".$template['tid']."\">".$template['title']."</a></td>";
		echo "<td class=\"$altbg\" align=\"right\">";
		echo "<input type=\"button\" value=\"$lang->edit\" onclick=\"hopto('templates.php?".SID."&amp;action=edit&amp;tid=".$template['tid']."');\" class=\"submitbutton\" />";
		echo "<input type=\"button\" value=\"$lang->revert\" onclick=\"hopto('templates.php?".SID."&amp;action=revert&amp;tid=".$template['tid']."');\" class=\"submitbutton\" />";
		echo "<input type=\"button\" value=\"$lang->diff\" onclick=\"hopto('templates.php?".SID."&amp;action=diff&amp;title=".$template['title']."&amp;sid1=".$template['sid']."&amp;sid2=-2');\" class=\"submitbutton\" />";
		echo "</td>";
		echo "</tr>";
	}
	endtable();
	cpfooter();
}

if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	$plugins->run_hooks("admin_templates_modify");
	if(!$noheader)
	{
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
			echo "<input type=\"button\" value=\"$lang->add_template\" onclick=\"hopto('templates.php?".SID."&amp;action=add&amp;sid=".$templateset['sid']."');\" class=\"submitbutton\" />";
			if($templateset['sid'] != "-2" && $templateset['sid'] != "-1")
			{
				echo "<input type=\"button\" value=\"$lang->edit_set\" onclick=\"hopto('templates.php?".SID."&amp;action=editset&amp;setid=".$templateset['sid']."');\" class=\"submitbutton\" />";
				if(!$themes[$templateset['sid']])
				{
					echo "<input type=\"button\" value=\"$lang->delete_set\" onclick=\"hopto('templates.php?".SID."&amp;action=deleteset&amp;setid=".$templateset['sid']."');\" class=\"submitbutton\" />";
				}
			}
			echo "<input type=\"button\" value=\"$lang->expand\" onclick=\"hopto('templates.php?".SID."&amp;expand=".$templateset['sid']."');\" class=\"submitbutton\" />";
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
		echo "<input type=\"button\" value=\"$lang->add_template\" onclick=\"hopto('templates.php?".SID."&amp;action=add&amp;sid=".$templateset['sid']."');\" class=\"submitbutton\" />";
		if($templateset['sid'] != "-2" && $templateset['sid'] != "-1")
		{
			echo "<input type=\"button\" value=\"$lang->edit_set\" onclick=\"hopto('templates.php?".SID."&amp;action=editset&amp;setid=".$templateset['sid']."');\" class=\"submitbutton\" />";
			if(!$themes[$expand])
			{
				echo "<input type=\"button\" value=\"$lang->delete_set\" onclick=\"hopto('templates.php?".SID."&amp;action=deleteset&amp;setid=".$templateset['sid']."');\" class=\"submitbutton\" />";
			}
		}
		echo "<input type=\"button\" value=\"$lang->collapse\" onclick=\"hopto('templates.php?".SID."');\" class=\"submitbutton\" />";
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
				echo "<td class=\"$altbg\"><a href=\"templates.php?".SID."&amp;action=edit&amp;tid=".$template['tid']."\">".$template['title']."</a></td>";
				echo "<td class=\"$altbg\" align=\"right\">";
				echo "<input type=\"button\" value=\"$lang->edit\" onclick=\"hopto('templates.php?".SID."&amp;action=edit&amp;tid=".$template['tid']."');\" class=\"submitbutton\" />";
				echo "<input type=\"button\" value=\"$lang->delete\" onclick=\"hopto('templates.php?".SID."&amp;action=delete&amp;tid=".$template['tid']."');\" class=\"submitbutton\" />";
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
				echo "<td class=\"$altbg\"><a href=\"templates.php?".SID."&amp;action=edit&amp;tid=".$template['tid']."\"><span class=\"highlight4\">".$template['title']."</span></a></td>";
				echo "<td class=\"$altbg\" align=\"right\">";
				echo "<input type=\"button\" value=\"$lang->edit\" onclick=\"hopto('templates.php?".SID."&amp;action=edit&amp;tid=".$template['tid']."');\" class=\"submitbutton\" />";
				echo "<input type=\"button\" value=\"$lang->delete\" onclick=\"hopto('templates.php?".SID."&amp;action=delete&amp;tid=".$template['tid']."');\" class=\"submitbutton\" />";
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
			$query2 = $db->query("SELECT t1.* FROM ".TABLE_PREFIX."templates t1 LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t1.title=t2.title AND t2.sid='-2') WHERE t1.sid='".$set['sid']."' AND ISNULL(t2.template) ORDER BY t1.title ASC");
			while($template = $db->fetch_array($query2))
			{
				$template['customtemplate'] = 1;
				$templatelist[$template['title']] = $template;
			}

			// Query for original templates
			$query3 = $db->query("SELECT t1.title AS originaltitle, t1.tid AS originaltid, t2.tid FROM ".TABLE_PREFIX."templates t1 LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t2.title=t1.title AND t2.sid='".$set['sid']."') WHERE t1.sid='-2' ORDER BY t1.title ASC");
			while($template = $db->fetch_array($query3))
			{
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
						echo "<td class=\"$altbg\" colspan=\"2\"><b><a href=\"templates.php?".SID."&amp;expand=$expand&amp;group=$gid#$gid\" name=\"$gid\">$groupname $lang->templates</a></b></td>\n";
						echo "<td class=\"$altbg\" align=\"right\"><input type=\"button\" value=\"$lang->expand\" onclick=\"hopto('templates.php?".SID."&amp;expand=$expand&amp;group=$gid#$gid');\" class=\"submitbutton\" /></td>\n";
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
					echo "<a href=\"templates.php?".SID."&amp;action=add&amp;title=".$template['originaltitle']."&amp;sid=".$set['sid'].$opengroup."\"><span class=\"highlight4\">".$template['originaltitle']."</span></a></td>\n";
					echo "<td class=\"$altbg\" align=\"right\">";
					echo "<input type=\"button\" value=\"$lang->change_original\" onclick=\"hopto('templates.php?".SID."&amp;action=add&amp;title=".$template['originaltitle']."&amp;sid=".$set['sid']."&amp;group=$grouptype');\" class=\"submitbutton\" />";
					echo "</td>\n";
					echo "</tr>\n";
				}
				elseif($template['customtemplate'])
				{
						echo "<a href=\"templates.php?".SID."&amp;action=edit&amp;tid=".$template['tid'].$opengroup."\"><span class=\"highlight2\">".$template['title']."</span></a></td>";
						echo "<td class=\"$altbg\" align=\"right\">";
						echo "<input type=\"button\" value=\"$lang->edit\" onclick=\"hopto('templates.php?".SID."&amp;action=edit&amp;tid=".$template['tid']."&amp;group=$grouptype');\" class=\"submitbutton\" />";
						echo "<input type=\"button\" value=\"$lang->delete\" onclick=\"hopto('templates.php?".SID."&amp;action=delete&amp;tid=".$template['tid']."&amp;expand=$expand&amp;group=$grouptype');\" class=\"submitbutton\" />";
						echo "</td>\n";
						echo "</tr>\n";
				}
				else
				{
					echo "<a href=\"templates.php?".SID."&amp;action=edit&amp;tid=".$template['tid'].$opengroup."\"><span class=\"highlight3\">".$template['originaltitle']."</span></a></td>";
					echo "<td class=\"$altbg\" align=\"right\">";
					echo "<input type=\"button\" value=\"$lang->edit\" onclick=\"hopto('templates.php?".SID."&amp;action=edit&amp;tid=".$template['tid']."&amp;group=$grouptype');\" class=\"submitbutton\" />";
					if($expand == 1)
					{
						echo "<input type=\"button\" value=\"$lang->diff\" onclick=\"hopto('templates.php?".SID."&amp;action=diff&amp;title=".$template['originaltitle']."&amp;sid2=$expand');\" class=\"submitbutton\" />";
					}
					echo "<input type=\"button\" value=\"$lang->revert_original\" onclick=\"hopto('templates.php?".SID."&amp;action=revert&amp;tid=".$template['tid']."&amp;expand=$expand&amp;group=$grouptype');\" class=\"submitbutton\" />";
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
