<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->mod_logs, "index.php?module=tools/modlog");

$plugins->run_hooks("admin_tools_modlog_begin");

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_tools_modlog_start");
	
	$page->output_header($lang->mod_logs);
	
	$sub_tabs['mod_logs'] = array(
		'title' => $lang->mod_logs,
		'link' => "index.php?module=tools/modlog",
		'description' => $lang->mod_logs_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'mod_logs');
	
	$perpage = intval($mybb->input['perpage']);
	if(!$perpage)
	{
		$perpage = $mybb->settings['threadsperpage'];
	}

	$where = '';

	// Searching for entries by a particular user
	if($mybb->input['uid'])
	{
		$where .= " AND l.uid='".intval($mybb->input['uid'])."'";
	}

	// Searching for entries in a specific forum
	if($mybb->input['fid'])
	{
		$where .= " AND l.fid='".intval($mybb->input['fid'])."'";
	}

	// Order?
	switch($mybb->input['sortby'])
	{
		case "username":
			$sortby = "u.username";
			break;
		case "forum":
			$sortby = "f.name";
			break;
		case "thread":
			$sortby = "t.subject";
			break;
		default:
			$sortby = "l.dateline";
	}
	$order = $mybb->input['order'];
	if($order != "asc")
	{
		$order = "desc";
	}

	$query = $db->query("
		SELECT COUNT(l.dateline) AS count
		FROM ".TABLE_PREFIX."moderatorlog l
		WHERE 1=1 {$where}
	");
	$rescount = $db->fetch_field($query, "count");
	
	// Figure out if we need to display multiple pages.
	if($mybb->input['page'] != "last")
	{
		$pagecnt = intval($mybb->input['page']);
	}

	$postcount = intval($rescount);
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->input['page'] == "last")
	{
		$pagecnt = $pages;
	}

	if($pagecnt > $pages)
	{
		$pagecnt = 1;
	}

	if($pagecnt)
	{
		$start = ($pagecnt-1) * $perpage;
	}
	else
	{
		$start = 0;
		$pagecnt = 1;
	}
	
	$table = new Table;
	$table->construct_header($lang->username, array('width' => '10%'));
	$table->construct_header($lang->date, array("class" => "align_center", 'width' => '15%'));
	$table->construct_header($lang->action, array("class" => "align_center", 'width' => '35%'));
	$table->construct_header($lang->information, array("class" => "align_center", 'width' => '30%'));
	$table->construct_header($lang->ipaddress, array("class" => "align_center", 'width' => '10%'));
	
	$query = $db->query("
		SELECT l.*, u.username, u.usergroup, u.displaygroup, t.subject AS tsubject, f.name AS fname, p.subject AS psubject
		FROM ".TABLE_PREFIX."moderatorlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=l.fid)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=l.pid)
		{$where}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($logitem = $db->fetch_array($query))
	{
		$information = '';
		$logitem['dateline'] = date("jS M Y, G:i", $logitem['dateline']);
		$trow = alt_trow();
		$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
		$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);
		if($logitem['tsubject'])
		{
			$information = "<strong>{$lang->thread}</strong> <a href=\"../".get_thread_link($logitem['tid'])."\" target=\"_blank\">".htmlspecialchars_uni($logitem['tsubject'])."</a><br />";
		}
		if($logitem['fname'])
		{
			$information .= "<strong>{$lang->forum}</strong> <a href=\"../".get_forum_link($logitem['fid'])."\" target=\"_blank\">".htmlspecialchars_uni($logitem['fname'])."</a><br />";
		}
		if($logitem['psubject'])
		{
			$information .= "<strong>{$lang->post}</strong> <a href=\"../".get_post_link($logitem['pid'])."#pid{$logitem['pid']}\">".htmlspecialchars_uni($logitem['psubject'])."</a>";
		}
		
		$table->construct_cell($logitem['profilelink']);
		$table->construct_cell($logitem['dateline'], array("class" => "align_center"));
		$table->construct_cell($logitem['action'], array("class" => "align_center"));
		$table->construct_cell($information);
		$table->construct_cell($logitem['ipaddress'], array("class" => "align_center"));
		$table->construct_row();
	}
	
	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_modlogs, array("colspan" => "5"));
		$table->construct_row();
	}
	
	$table->output($lang->mod_logs);
	
	// Do we need to construct the pagination?
	if($rescount > $perpage)
	{
		echo draw_admin_pagination($pagecnt, $perpage, $rescount, "index.php?module=tools/modlog&amp;perpage=$perpage&amp;uid={$mybb->input['uid']}&amp;fid={$mybb->input['fid']}&amp;sortby={$mybb->input['sortby']}&amp;order={$order}")."<br />";
	}
	
	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = "selected=\"selected\"";
	$ordersel[$mybb->input['order']] = "selected=\"selected\"";
	
	$user_options[''] = $lang->all_moderators;
	$user_options['0'] = '----------';
	
	$query = $db->query("
		SELECT DISTINCT l.uid, u.username
		FROM ".TABLE_PREFIX."moderatorlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$selected = '';
		if($mybb->input['uid'] == $user['uid'])
		{
			$selected = "selected=\"selected\"";
		}
		$user_options[$user['uid']] = $user['username'];
	}
	
	$sort_by = array(
		'dateline' => $lang->date,
		'username' => $lang->username,
		'forum' => $lang->forum_name,
		'thread' => $lang->thread_subject
	);
	
	$order_array = array(
		'asc' => $lang->asc,
		'desc' => $lang->desc
	);

	$form = new Form("index.php?module=tools/modlog", "post");
	$form_container = new FormContainer($lang->filter_moderator_logs);
	$form_container->output_row($lang->forum, "", $form->generate_forum_select('fid', $mybb->input['fid'], array('id' => 'fid')), 'fid');	
	$form_container->output_row($lang->forum_moderator, "", $form->generate_select_box('uid', $user_options, $mybb->input['uid'], array('id' => 'uid')), 'uid');	
	$form_container->output_row($lang->sort_by, "", $form->generate_select_box('sortby', $sort_by, $mybb->input['sortby'], array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('order', $order_array, $order, array('id' => 'order'))." {$lang->order}", 'order');	
	$form_container->output_row($lang->results_per_page, "", $form->generate_text_box('perpage', $perpage, array('id' => 'perpage')), 'perpage');	

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->filter_moderator_logs);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}
?>