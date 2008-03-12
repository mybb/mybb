<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 * TO BE REMOVED BEFORE 1.4 RELEASE
 */

define("IN_MYBB", 1);

require_once "./global.php";

// Load language packs for this section
global $lang;
$lang->load("moderation");

//checkadminpermissions("caneditmodactions");
logadmin();

addacpnav($lang->nav_moderation, "moderation.php?".SID);
switch($mybb->input['action'])
{
	case "addthreadtool":
	case "addposttool":
		addacpnav($lang->nav_add_action);
		break;
	case "edit":
		addacpnav($lang->nav_edit_action);
		break;
	case "delete":
		addacpnav($lang->nav_delete_action);
		break;
}

$plugins->run_hooks("admin_moderation_start");

if($mybb->input['action'] == "do_delete")
{
	if(isset($mybb->input['deletesubmit']))
	{
		$tid = intval($mybb->input['tid']);
		$plugins->run_hooks("admin_moderation_do_delete");
		$db->delete_query("modtools", "tid='$tid'");

		cpredirect("moderation.php?".SID, $lang->tool_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}

if($mybb->input['action'] == "delete")
{
	$tid = intval($mybb->input['tid']);
	$query = $db->simple_select("modtools", 'name', "tid='$tid'");
	$tool = $db->fetch_array($query);
	$plugins->run_hooks("admin_moderation_delete");
	cpheader();
	startform('moderation.php', '', 'do_delete');
	makehiddencode('tid', $tid);
	starttable();
	$lang->delete_tool_title = $lang->sprintf($lang->delete_tool_title, $tool['name']);
	tableheader($lang->delete_tool_title, '', 1);
	$yes = makebuttoncode('deletesubmit', $lang->yes);
	$no = makebuttoncode(0, $lang->no);
	$lang->delete_tool_confirm = $lang->sprintf($lang->delete_tool_confirm, $tool['name']);
	makelabelcode("<div align=\"center\">$lang->delete_tool_confirm<br /><br />$yes$no</div>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "do_edit")
{
	// Actually edit the tool
	if(trim($mybb->input['name']) == '')
	{
		cperror($lang->no_name);
	}

	$update_tool = array();
	if($mybb->input['type'] == 'p')
	{
		if(stripos($mybb->input['splitpostsnewsubject'], '{subject}') === false)
		{
			$mybb->input['splitpostsnewsubject'] = '{subject}'.$mybb->input['splitpostsnewsubject'];
		}
		$post_options = array(
			'deleteposts' => $mybb->input['deleteposts'],
			'mergeposts' => $mybb->input['mergeposts'],
			'approveposts' => $mybb->input['approveposts'],
			'splitposts' => intval($mybb->input['splitposts']),
			'splitpostsclose' => $mybb->input['splitpostsclose'],
			'splitpostsstick' => $mybb->input['splitpostsstick'],
			'splitpostsunapprove' => $mybb->input['splitpostsunapprove'],
			'splitpostsnewsubject' => $mybb->input['splitpostsnewsubject'],
			'splitpostsaddreply' => $mybb->input['splitpostsaddreply'],
			'splitpostsreplysubject' => $mybb->input['splitpostsreplysubject'],
		);

		$update_tool['postoptions'] = $db->escape_string(serialize($post_options));
	}

	$thread_options = array(
		'deletethread' => $mybb->input['deletethread'],
		'mergethreads' => $mybb->input['mergethreads'],
		'deletepoll' => $mybb->input['deletepoll'],
		'removeredirects' => $mybb->input['removeredirects'],
		'approvethread' => $mybb->input['approvethread'],
		'openthread' => $mybb->input['openthread'],
		'movethread' => intval($mybb->input['movethread']),
		'movethreadredirect' => $mybb->input['movethreadredirect'],
		'movethreadredirectexpire' => intval($mybb->input['movethreadredirectexpire']),
		'copythread' => intval($mybb->input['copythread']),
		'newsubject' => $mybb->input['newsubject'],
		'addreply' => $mybb->input['addreply'],
		'replysubject' => $mybb->input['replysubject'],
		);
	$update_tool['threadoptions'] = $db->escape_string(serialize($thread_options));
	$update_tool['name'] = $db->escape_string($mybb->input['name']);
	$update_tool['description'] = $db->escape_string($mybb->input['description']);
	$update_tool['forums'] = '';
	if(is_array($mybb->input['forums']))
	{
		foreach($mybb->input['forums'] as $fid)
		{
			$checked[] = intval($fid);
		}
		$update_tool['forums'] = implode(',', $checked);
	}

	$plugins->run_hooks("admin_moderation_do_edit");
	
	$db->update_query("modtools", $update_tool, 'tid="'.intval($mybb->input['tid']).'"');

	cpredirect('moderation.php?'.SID, $lang->tool_edited);
}

if($mybb->input['action'] == "edit")
{
	$plugins->run_hooks("admin_moderation_edit");
	
	// Form to edit tool
	if(!$noheader)
	{
		cpheader();
	}

	// Get the tool
	$query = $db->simple_select("modtools", '*', 'tid="'.intval($mybb->input['tid']).'"');
	$tool = $db->fetch_array($query);

	if(!$tool['tid'])
	{
		cperror($lang->invalid_tool);
	}

	if($tool['type'] == 'p')
	{
		$mode = 'p';
		$title = $lang->edit_post_tool_title;
		$submit = $lang->edit_post_action;
	}
	else
	{
		$mode = 't';
		$title = $lang->edit_thread_tool_title;
		$submit = $lang->edit_thread_action;
	}

	startform('moderation.php', '', 'do_edit');
	makehiddencode('tid', $tool['tid']);
	makehiddencode('type', $mode);

	starttable();
	tableheader($title);

	tablesubheader($lang->general_options);
	makeinputcode($lang->name, 'name', $tool['name']);
	maketextareacode($lang->description, 'description', $tool['description']);
	makelabelcode($lang->available_in_forums, forum_checkbox_list('forums', explode(",", $tool['forums']), '0', '', $lang->all_forums));
	
	if($mode == 'p')
	{
		$post_options = unserialize($tool['postoptions']);
		// Add settings for inline post moderation
		tablesubheader($lang->inline_post_moderation);
		makeyesnocode($lang->delete_posts, 'deleteposts', $post_options['deleteposts']);
		makeyesnocode($lang->merge_posts, 'mergeposts', $post_options['mergeposts']);
		$approve_options = array(
			'' => $lang->no_change,
			'approve' => $lang->approve,
			'unapprove' => $lang->unapprove,
			'toggle' => $lang->toggle
		); 
		makeselectcode_array($lang->approve_unapprove_posts, 'approveposts', $approve_options, $post_options['approveposts']);
		$split_thread_extras = "<br /><br /><small>$lang->split_additional_options<br />\n";
		$close_checked = $stick_checked = $unapprove_checked = '';
		if($post_options['splitpostsclose'] == 'close')
		{
			$close_checked = ' checked="checked"';
		}
		if($post_options['splitpostsstick'] == 'stick')
		{
			$stick_checked = ' checked="checked"';
		}
		if($post_options['splitpostsunapprove'] == 'unapprove')
		{
			$unapprove_checked = ' checked="checked"';
		}
		$split_thread_extras .= "<label><input type=\"checkbox\" name=\"splitpostsclose\" value=\"close\"$close_checked /> $lang->close</label> <br />\n";
		$split_thread_extras .= "<label><input type=\"checkbox\" name=\"splitpostsstick\" value=\"stick\"$stick_checked /> $lang->stick</label> <br />\n";
		$split_thread_extras .= "<label><input type=\"checkbox\" name=\"splitpostsunapprove\" value=\"unapprove\"$unapprove_check /> $lang->unapprove</label> </small>";
		makelabelcode($lang->split_posts, forumselect('splitposts', $post_options['splitposts'], '', '', 0, $lang->do_not_split, $lang->split_to_same_forum).$split_thread_extras);
		unset($forumselect);
		makeinputcode($lang->split_new_subject, 'splitpostsnewsubject', $post_options['splitpostsnewsubject']);
		maketextareacode($lang->add_reply_split, 'splitpostsaddreply', $post_options['splitpostsaddreply']);
		makeinputcode($lang->reply_subject, 'splitpostsreplysubject', $post_options['splitpostsreplysubject']);
	}
	// Settings for normal thread moderation
	tablesubheader($lang->thread_moderation);

	$thread_options = unserialize($tool['threadoptions']);

	makeyesnocode($lang->delete_thread, 'deletethread', $thread_options['deletethread']);
	if($mode == 't')
	{
		makeyesnocode($lang->merge_threads, 'mergethreads', $thread_options['mergethreads']);
	}
	makeyesnocode($lang->delete_poll, 'deletepoll', $thread_options['deletepoll']);
	makeyesnocode($lang->remove_redirects, 'removeredirects', $thread_options['removeredirects']);
	$approve_options = array(
		'' => $lang->no_change,
		'approve' => $lang->approve,
		'unapprove' => $lang->unapprove,
		'toggle' => $lang->toggle
	); 
	makeselectcode_array($lang->approve_unapprove_thread, 'approvethread', $approve_options, $thread_options['approvethread']);
	$open_options = array(
		'' => $lang->no_change,
		'open' => $lang->open,
		'close' => $lang->close,
		'toggle' => $lang->toggle
	); 
	makeselectcode_array($lang->open_close_thread, 'openthread', $open_options, $thread_options['openthread']);
	makelabelcode($lang->move_thread, forumselect('movethread', $thread_options['movethread'], '', '', 0, $lang->do_not_move));
	unset($forumselect);
	makeyesnocode($lang->leave_redirect, 'movethreadredirect', $thread_options['movethreadredirect']);
	makeinputcode($lang->redirect_expire, 'movethreadredirectexpire', $thread_options['movethreadredirectexpire']);
	makelabelcode($lang->copy_thread, forumselect('copythread', $thread_options['copythread'], '', '', 0, $lang->do_not_copy, $lang->copy_to_same_forum));
	unset($forumselect);
	makeinputcode($lang->new_subject, 'newsubject', $thread_options['newsubject']);
	maketextareacode($lang->add_reply, 'addreply', $thread_options['addreply']);
	makeinputcode($lang->reply_subject, 'replysubject', $thread_options['replysubject']);
	endtable();
	endform($submit, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "do_addposttool" || $mybb->input['action'] == "do_addthreadtool")
{
	// Actually add the tool
	if(trim($mybb->input['name']) == '')
	{
		cperror($lang->no_name);
	}

	$plugins->run_hooks("admin_moderation_do_add");
	
	$new_tool = array('type' => 't');
	if($mybb->input['action'] == 'do_addposttool')
	{
		$new_tool['type'] = 'p';
		if(stripos($mybb->input['splitpostsnewsubject'], '{subject}') === false)
		{
			$mybb->input['splitpostsnewsubject'] = '{subject}'.$mybb->input['splitpostsnewsubject'];
		}
		$post_options = array(
			'deleteposts' => $mybb->input['deleteposts'],
			'mergeposts' => $mybb->input['mergeposts'],
			'approveposts' => $mybb->input['approveposts'],
			'splitposts' => intval($mybb->input['splitposts']),
			'splitpostsclose' => $mybb->input['splitpostsclose'],
			'splitpostsstick' => $mybb->input['splitpostsstick'],
			'splitpostsunapprove' => $mybb->input['splitpostsunapprove'],
			'splitpostsnewsubject' => $mybb->input['splitpostsnewsubject'],
			'splitpostsaddreply' => $mybb->input['splitpostsaddreply'],
			'splitpostsreplysubject' => $mybb->input['splitpostsreplysubject'],
			);

		$new_tool['postoptions'] = $db->escape_string(serialize($post_options));
	}

	$thread_options = array(
		'deletethread' => $mybb->input['deletethread'],
		'mergethreads' => $mybb->input['mergethreads'],
		'deletepoll' => $mybb->input['deletepoll'],
		'removeredirects' => $mybb->input['removeredirects'],
		'approvethread' => $mybb->input['approvethread'],
		'openthread' => $mybb->input['openthread'],
		'movethread' => intval($mybb->input['movethread']),
		'movethreadredirect' => $mybb->input['movethreadredirect'],
		'movethreadredirectexpire' => intval($mybb->input['movethreadredirectexpire']),
		'copythread' => intval($mybb->input['copythread']),
		'newsubject' => $mybb->input['newsubject'],
		'addreply' => $mybb->input['addreply'],
		'replysubject' => $mybb->input['replysubject'],
	);
	$new_tool['threadoptions'] = $db->escape_string(serialize($thread_options));
	$new_tool['name'] = $db->escape_string($mybb->input['name']);
	$new_tool['description'] = $db->escape_string($mybb->input['description']);
	$new_tool['forums'] = '';
	if(is_array($mybb->input['forums']))
	{
		foreach($mybb->input['forums'] as $fid)
		{
			$checked[] = intval($fid);
		}
		$new_tool['forums'] = implode(',', $checked);
	}

	$db->insert_query("modtools", $new_tool);

	cpredirect('moderation.php?'.SID, $lang->tool_added);
}

if($mybb->input['action'] == "addposttool" || $mybb->input['action'] == "addthreadtool")
{
	$plugins->run_hooks("admin_moderation_add");
	// Form to add tool
	if(!$noheader)
	{
		cpheader();
	}

	if($mybb->input['action'] == 'addposttool')
	{
		$mode = 'p';
		$title = $lang->add_post_tool_title;
		$submit = $lang->add_post_action;
		startform('moderation.php', '', 'do_addposttool');
	}
	else
	{
		$mode = 't';
		$title = $lang->add_thread_tool_title;
		$submit = $lang->add_thread_action;
		startform('moderation.php', '', 'do_addthreadtool');
	}

	starttable();
	tableheader($title);

	tablesubheader($lang->general_options);
	makeinputcode($lang->name, 'name');
	maketextareacode($lang->description, 'description');
	makelabelcode($lang->available_in_forums, forum_checkbox_list('forums', '-1', '0', '', $lang->all_forums));
	
	if($mode == 'p')
	{
		// Add settings for inline post moderation
		tablesubheader($lang->inline_post_moderation);
		makeyesnocode($lang->delete_posts, 'deleteposts', 0);
		makeyesnocode($lang->merge_posts, 'mergeposts', 0);
		$approve_options = array(
			'' => $lang->no_change,
			'approve' => $lang->approve,
			'unapprove' => $lang->unapprove,
			'toggle' => $lang->toggle
		); 
		makeselectcode_array($lang->approve_unapprove_posts, 'approveposts', $approve_options);
		$split_thread_extras = "<br /><br /><small>$lang->split_additional_options<br />\n";
		$split_thread_extras .= "<label><input type=\"checkbox\" name=\"splitpostsclose\" value=\"close\" /> $lang->close</label> <br />\n";
		$split_thread_extras .= "<label><input type=\"checkbox\" name=\"splitpostsstick\" value=\"stick\" /> $lang->stick</label> <br />\n";
		$split_thread_extras .= "<label><input type=\"checkbox\" name=\"splitpostsunapprove\" value=\"unapprove\" /> $lang->unapprove</label> </small>";
		makelabelcode($lang->split_posts, forumselect('splitposts', '', '', '', 0, $lang->do_not_split, $lang->split_to_same_forum).$split_thread_extras);
		unset($forumselect);
		makeinputcode($lang->split_new_subject, 'splitpostsnewsubject', '{subject}');
		maketextareacode($lang->add_reply_split, 'splitpostsaddreply');
		makeinputcode($lang->reply_subject, 'splitpostsreplysubject');

		
	}
	// Settings for normal thread moderation
	tablesubheader($lang->thread_moderation);
	makeyesnocode($lang->delete_thread, 'deletethread', 0);
	if($mode == 't')
	{
		makeyesnocode($lang->merge_threads, 'mergethreads', 0);
	}
	makeyesnocode($lang->delete_poll, 'deletepoll', 0);
	makeyesnocode($lang->remove_redirects, 'removeredirects', 0);
	$approve_options = array(
		'' => $lang->no_change,
		'approve' => $lang->approve,
		'unapprove' => $lang->unapprove,
		'toggle' => $lang->toggle
	); 
	makeselectcode_array($lang->approve_unapprove_thread, 'approvethread', $approve_options);
	$open_options = array(
		'' => $lang->no_change,
		'open' => $lang->open,
		'close' => $lang->close,
		'toggle' => $lang->toggle
	); 
	makeselectcode_array($lang->open_close_thread, 'openthread', $open_options);
	makelabelcode($lang->move_thread, forumselect('movethread', '', '', '', 0, $lang->do_not_move));
	unset($forumselect);
	makeyesnocode($lang->leave_redirect, 'movethreadredirect', 1);
	makeinputcode($lang->redirect_expire, 'movethreadredirectexpire');
	makelabelcode($lang->copy_thread, forumselect('copythread', '', '', '', 0, $lang->do_not_copy, $lang->copy_to_same_forum));
	unset($forumselect);
	makeinputcode($lang->new_subject, 'newsubject', '{subject}');
	maketextareacode($lang->add_reply, 'addreply');
	makeinputcode($lang->reply_subject, 'replysubject');
	endtable();
	endform($submit, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "modify" || $mybb->input['action'] == '')
{
	$plugins->run_hooks("admin_moderation_modify");
	if(!$noheader)
	{
		cpheader();
	}

	$hopto[] = "<input type=\"button\" value=\"$lang->add_thread_action\" onclick=\"hopto('moderation.php?".SID."&amp;action=addthreadtool');\" class=\"hoptobutton\" />";
	makehoptolinks($hopto);

	// Thread tools
	starttable();
	tableheader($lang->thread_tools);
	tablesubheader(array($lang->tool_name, $lang->options));

	$options = array('order_by' => 'name', 'orderdir' => 'ASC');
	$query = $db->simple_select("modtools", 'tid, name, description, type', "type='t'", $options);
	while($tool = $db->fetch_array($query))
	{
		$bgcolor = getaltbg();
		$options = makelinkcode($lang->edit_tool, "moderation.php?".SID."&amp;action=edit&amp;tid={$tool['tid']}");
		$options .= makelinkcode($lang->delete_tool, "moderation.php?".SID."&amp;action=delete&amp;tid={$tool['tid']}");
		$name = htmlspecialchars_uni($tool['name']);
		if(!empty($tool['description']))
		{
			$name .= '<br /><small>'.htmlspecialchars_uni($tool['description']).'</small>';
		}
		echo "<tr>\n";
		echo "<td width=\"50%\" class=\"$bgcolor\">$name</td>\n";
		echo "<td width=\"50%\" class=\"$bgcolor\">$options</td>\n";
		echo "</tr>\n";
	}
	if(!$db->num_rows($query))
	{
		makelabelcode($lang->no_tools, '', 3);
	}
	endtable();


	// Inline Post Tools
	unset($hopto);
	$hopto[] = "<input type=\"button\" value=\"$lang->add_post_action\" onclick=\"hopto('moderation.php?".SID."&amp;action=addposttool');\" class=\"hoptobutton\" />";
	makehoptolinks($hopto);

	starttable();
	tableheader($lang->post_tools);
	tablesubheader(array($lang->tool_name, $lang->options));

	$options = array('order_by' => 'name', 'orderdir' => 'ASC');
	$query = $db->simple_select("modtools", 'tid, name, description, type', "type='p'", $options);
	while($tool = $db->fetch_array($query))
	{
		$bgcolor = getaltbg();
		$options = makelinkcode($lang->edit_tool, "moderation.php?".SID."&amp;action=edit&amp;tid={$tool['tid']}");
		$options .= makelinkcode($lang->delete_tool, "moderation.php?".SID."&amp;action=delete&amp;tid={$tool['tid']}");
		$name = htmlspecialchars_uni($tool['name']);
		if(!empty($tool['description']))
		{
			$name .= '<br /><small>'.htmlspecialchars_uni($tool['description']).'</small>';
		}
		echo "<tr>\n";
		echo "<td width=\"50%\" class=\"$bgcolor\">$name</td>\n";
		echo "<td width=\"50%\" class=\"$bgcolor\">$options</td>\n";
		echo "</tr>\n";
	}
	if(!$db->num_rows($query))
	{
		makelabelcode($lang->no_tools, '', 3);
	}
	endtable();
	cpfooter();
}
?>