<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->admin_logs, "index.php?module=tools-adminlog");

$sub_tabs['admin_logs'] = array(
	'title' => $lang->admin_logs,
	'link' => "index.php?module=tools-adminlog",
	'description' => $lang->admin_logs_desc
);
$sub_tabs['prune_admin_logs'] = array(
	'title' => $lang->prune_admin_logs,
	'link' => "index.php?module=tools-adminlog&amp;action=prune",
	'description' => $lang->prune_admin_logs_desc
);

$plugins->run_hooks("admin_tools_adminlog_begin");

if($mybb->input['action'] == 'prune')
{
	if(!is_super_admin($mybb->user['uid']))
	{
		flash_message($lang->cannot_perform_action_super_admin_general, 'error');
		admin_redirect("index.php?module=tools-adminlog");
	}

	$plugins->run_hooks("admin_tools_adminlog_prune");

	if($mybb->request_method == 'post')
	{
		$is_today = false;
		if($mybb->input['older_than'] <= 0)
		{
			$is_today = true;
			$mybb->input['older_than'] = 1;
		}
		$where = 'dateline < '.(TIME_NOW-($mybb->get_input('older_than', MyBB::INPUT_INT)*86400));

		// Searching for entries by a particular user
		if($mybb->input['uid'])
		{
			$where .= " AND uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";
		}

		// Searching for entries in a specific module
		if($mybb->input['filter_module'])
		{
			$where .= " AND module='".$db->escape_string($mybb->input['filter_module'])."'";
		}

		$query = $db->delete_query("adminlog", $where);
		$num_deleted = $db->affected_rows();

		$plugins->run_hooks("admin_tools_adminlog_prune_commit");

		// Log admin action
		log_admin_action($mybb->input['older_than'], $mybb->input['uid'], $mybb->input['filter_module'], $num_deleted);

		$success = $lang->success_pruned_admin_logs;
		if($is_today == true && $num_deleted > 0)
		{
			$success .= ' '.$lang->note_logs_locked;
		}
		elseif($is_today == true && $num_deleted == 0)
		{
			flash_message($lang->note_logs_locked, 'error');
			admin_redirect("index.php?module=tools-adminlog");
		}
		flash_message($success, 'success');
		admin_redirect("index.php?module=tools-adminlog");
	}
	$page->add_breadcrumb_item($lang->prune_admin_logs, "index.php?module=tools-adminlog&amp;action=prune");
	$page->output_header($lang->prune_admin_logs);
	$page->output_nav_tabs($sub_tabs, 'prune_admin_logs');

	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = 'selected="selected"';
	$ordersel[$mybb->input['order']] = 'selected="selected"';

	$user_options[''] = $lang->all_administrators;
	$user_options['0'] = '----------';

	$query = $db->query("
		SELECT DISTINCT l.uid, u.username
		FROM ".TABLE_PREFIX."adminlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$user_options[$user['uid']] = $user['username'];
	}

	$module_options = array();
	$module_options[''] = $lang->all_modules;
	$module_options['0'] = '----------';
	$query = $db->query("
		SELECT DISTINCT l.module
		FROM ".TABLE_PREFIX."adminlog l
		ORDER BY l.module ASC
	");
	while($module = $db->fetch_array($query))
	{
		$module_options[$module['module']] = str_replace(' ', ' -&gt; ', ucwords(str_replace('/', ' ', $module['module'])));
	}

	$form = new Form("index.php?module=tools-adminlog&amp;action=prune", "post");
	$form_container = new FormContainer($lang->prune_administrator_logs);
	$form_container->output_row($lang->module, "", $form->generate_select_box('filter_module', $module_options, $mybb->input['filter_module'], array('id' => 'filter_module')), 'filter_module');
	$form_container->output_row($lang->administrator, "", $form->generate_select_box('uid', $user_options, $mybb->input['uid'], array('id' => 'uid')), 'uid');
	if(!$mybb->input['older_than'])
	{
		$mybb->input['older_than'] = '30';
	}
	$form_container->output_row($lang->date_range, "", $lang->older_than.$form->generate_numeric_field('older_than', $mybb->input['older_than'], array('id' => 'older_than', 'style' => 'width: 50px', 'min' => 0))." {$lang->days}", 'older_than');
	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->prune_administrator_logs);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->admin_logs);
	$page->output_nav_tabs($sub_tabs, 'admin_logs');

	$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if(!$perpage)
	{
		if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
		{
			$mybb->settings['threadsperpage'] = 20;
		}
		
		$perpage = $mybb->settings['threadsperpage'];
	}

	$where = '';

	$plugins->run_hooks("admin_tools_adminlog_start");

	// Searching for entries by a particular user
	if($mybb->input['uid'])
	{
		$where .= " AND l.uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";
	}

	// Searching for entries in a specific module
	if($mybb->input['filter_module'])
	{
		$where .= " AND module='".$db->escape_string($mybb->input['filter_module'])."'";
	}

	// Order?
	switch($mybb->input['sortby'])
	{
		case "username":
			$sortby = "u.username";
			break;
		default:
			$sortby = "l.dateline";
	}
	$order = $mybb->input['order'];
	if($order != 'asc')
	{
		$order = 'desc';
	}

	$query = $db->query("
		SELECT COUNT(l.dateline) AS count
		FROM ".TABLE_PREFIX."adminlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		WHERE 1=1 {$where}
	");
	$rescount = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->input['page'] != "last")
	{
		$pagecnt = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	$postcount = (int)$rescount;
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
	$table->construct_header($lang->date, array('class' => 'align_center', 'width' => '15%'));
	$table->construct_header($lang->information, array('class' => 'align_center', 'width' => '65%'));
	$table->construct_header($lang->ipaddress, array('class' => 'align_center', 'width' => '10%'));

	$query = $db->query("
		SELECT l.*, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."adminlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		WHERE 1=1 {$where}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($logitem = $db->fetch_array($query))
	{
		$information = '';
		$trow = alt_trow();
		$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);

		$logitem['data'] = my_unserialize($logitem['data']);
		$logitem['profilelink'] = build_profile_link($username, $logitem['uid'], "_blank");
		$logitem['dateline'] = my_date('relative', $logitem['dateline']);

		// Get detailed information from meta
		$information = get_admin_log_action($logitem);

		$table->construct_cell($logitem['profilelink']);
		$table->construct_cell($logitem['dateline'], array('class' => 'align_center'));
		$table->construct_cell($information);
		$table->construct_cell(my_inet_ntop($db->unescape_binary($logitem['ipaddress'])), array('class' => 'align_center'));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_adminlogs, array('colspan' => '4'));
		$table->construct_row();
	}

	$table->output($lang->admin_logs);

	// Do we need to construct the pagination?
	if($rescount > $perpage)
	{
		echo draw_admin_pagination($pagecnt, $perpage, $rescount, "index.php?module=tools-adminlog&amp;perpage=$perpage&amp;uid={$mybb->input['uid']}&amp;fid={$mybb->input['fid']}&amp;sortby={$mybb->input['sortby']}&amp;order={$order}&amp;filter_module=".htmlspecialchars_uni($mybb->input['filter_module']))."<br />";
	}

	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = 'selected="selected"';
	$ordersel[$mybb->input['order']] = 'selected="selected"';

	$user_options[''] = $lang->all_administrators;
	$user_options['0'] = '----------';

	$query = $db->query("
		SELECT DISTINCT l.uid, u.username
		FROM ".TABLE_PREFIX."adminlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$user_options[$user['uid']] = $user['username'];
	}

	$module_options = array();
	$module_options[''] = $lang->all_modules;
	$module_options['0'] = '----------';
	$query = $db->query("
		SELECT DISTINCT l.module
		FROM ".TABLE_PREFIX."adminlog l
		ORDER BY l.module ASC
	");
	while($module = $db->fetch_array($query))
	{
		$module_options[$module['module']] = str_replace(' ', ' -&gt; ', ucwords(str_replace('/', ' ', $module['module'])));
	}

	$sort_by = array(
		'dateline' => $lang->date,
		'username' => $lang->username
	);

	$order_array = array(
		'asc' => $lang->asc,
		'desc' => $lang->desc
	);

	$form = new Form("index.php?module=tools-adminlog", "post");
	$form_container = new FormContainer($lang->filter_administrator_logs);
	$form_container->output_row($lang->module, "", $form->generate_select_box('filter_module', $module_options, $mybb->input['filter_module'], array('id' => 'filter_module')), 'filter_module');
	$form_container->output_row($lang->administrator, "", $form->generate_select_box('uid', $user_options, $mybb->input['uid'], array('id' => 'uid')), 'uid');
	$form_container->output_row($lang->sort_by, "", $form->generate_select_box('sortby', $sort_by, $mybb->input['sortby'], array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('order', $order_array, $order, array('id' => 'order'))." {$lang->order}", 'order');
	$form_container->output_row($lang->results_per_page, "", $form->generate_numeric_field('perpage', $perpage, array('id' => 'perpage', 'min' => 1)), 'perpage');

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->filter_administrator_logs);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

/**
 * Returns language-friendly string describing $logitem
 * @param array The log item (one row from mybb_adminlogs)
 * @return string The description
 */
function get_admin_log_action($logitem)
{
	global $lang, $plugins, $mybb;

	$logitem['module'] = str_replace('/', '-', $logitem['module']);
	list($module, $action) = explode('-', $logitem['module']);
	$lang_string = 'admin_log_'.$module.'_'.$action.'_'.$logitem['action'];

	// Specific page overrides
	switch($lang_string)
	{
		// == CONFIG ==
		case 'admin_log_config_banning_add': // Banning IP/Username/Email
		case 'admin_log_config_banning_delete': // Removing banned IP/username/emails
			switch($logitem['data'][2])
			{
				case 1:
					$lang_string = 'admin_log_config_banning_'.$logitem['action'].'_ip';
					break;
				case 2:
					$lang_string = 'admin_log_config_banning_'.$logitem['action'].'_username';
					break;
				case 3:
					$lang_string = 'admin_log_config_banning_'.$logitem['action'].'_email';
					break;
			}
			break;

		case 'admin_log_config_help_documents_add': // Help documents and sections
		case 'admin_log_config_help_documents_edit':
		case 'admin_log_config_help_documents_delete':
			$lang_string .= "_{$logitem['data'][2]}"; // adds _section or _document
			break;

		case 'admin_log_config_languages_edit': // Editing language variables
			$logitem['data'][1] = basename($logitem['data'][1]);
			if($logitem['data'][2] == 1)
			{
				$lang_string = 'admin_log_config_languages_edit_admin';
			}
			break;

		case 'admin_log_config_mycode_toggle_status': // Custom MyCode toggle activation
			if($logitem['data'][2] == 1)
			{
				$lang_string .= '_enabled';
			}
			else
			{
				$lang_string .= '_disabled';
			}
			break;
		case 'admin_log_config_plugins_activate': // Installing plugin
			if($logitem['data'][1])
			{
				$lang_string .= '_install';
			}
			break;
		case 'admin_log_config_plugins_deactivate': // Uninstalling plugin
			if($logitem['data'][1])
			{
				$lang_string .= '_uninstall';
			}
			break;
		// == FORUM ==
		case 'admin_log_forum_attachments_delete': // Deleting attachments
			if($logitem['data'][2])
			{
				$lang_string .= '_post';
			}
			break;
		case 'admin_log_forum_management_copy': // Forum copy
			if($logitem['data'][4])
			{
				$lang_string .= '_with_permissions';
			}
			break;
		case 'admin_log_forum_management_': // add mod, permissions, forum orders
			// first parameter already set with action
			$lang_string .= $logitem['data'][0];
			if($logitem['data'][0] == 'orders' && $logitem['data'][1])
			{
				$lang_string .= '_sub'; // updating forum orders in a subforum
			}
			break;
		case 'admin_log_forum_moderation_queue_': //moderation queue
			// first parameter already set with action
			$lang_string .= $logitem['data'][0];
			break;
		// == HOME ==
		case 'admin_log_home_preferences_': // 2FA
			$lang_string .= $logitem['data'][0]; // either "enabled" or "disabled"
			break;
		// == STYLE ==
		case 'admin_log_style_templates_delete_template': // deleting templates
			// global template set
			if($logitem['data'][2] == -1)
			{
				$lang_string .= '_global';
			}
			break;
		case 'admin_log_style_templates_edit_template': // editing templates
			// global template set
			if($logitem['data'][2] == -1)
			{
				$lang_string .= '_global';
			}
			break;
		// == TOOLS ==
		case 'admin_log_tools_adminlog_prune': // Admin Log Pruning
			if($logitem['data'][1] && !$logitem['data'][2])
			{
				$lang_string = 'admin_log_tools_adminlog_prune_user';
			}
			elseif($logitem['data'][2] && !$logitem['data'][1])
			{
				$lang_string = 'admin_log_tools_adminlog_prune_module';
			}
			elseif($logitem['data'][1] && $logitem['data'][2])
			{
				$lang_string = 'admin_log_tools_adminlog_prune_user_module';
			}
			break;
		case 'admin_log_tools_modlog_prune': // Moderator Log Pruning
			if($logitem['data'][1] && !$logitem['data'][2])
			{
				$lang_string = 'admin_log_tools_modlog_prune_user';
			}
			elseif($logitem['data'][2] && !$logitem['data'][1])
			{
				$lang_string = 'admin_log_tools_modlog_prune_forum';
			}
			elseif($logitem['data'][1] && $logitem['data'][2])
			{
				$lang_string = 'admin_log_tools_modlog_prune_user_forum';
			}
			break;
		case 'admin_log_tools_backupdb_backup': // Create backup
			if($logitem['data'][0] == 'download')
			{
				$lang_string = 'admin_log_tools_backupdb_backup_download';
			}
			$logitem['data'][1] = '...'.substr($logitem['data'][1], -20);
			break;
		case 'admin_log_tools_backupdb_dlbackup': // Download backup
			$logitem['data'][0] = '...'.substr($logitem['data'][0], -20);
			break;
		case 'admin_log_tools_backupdb_delete': // Delete backup
			$logitem['data'][0] = '...'.substr($logitem['data'][0], -20);
			break;
		case 'admin_log_tools_optimizedb_': // Optimize DB
			$logitem['data'][0] = @implode(', ', my_unserialize($logitem['data'][0]));
			break;
		case 'admin_log_tools_recount_rebuild_': // Recount and rebuild
			$detail_lang_string = $lang_string.$logitem['data'][0];
			if(isset($lang->$detail_lang_string))
			{
				$lang_string = $detail_lang_string;
			}
			break;
		// == USERS ==
		case 'admin_log_user_admin_permissions_edit': // editing default/group/user admin permissions
			if($logitem['data'][0] > 0)
			{
				// User
				$lang_string .= '_user';
			}
			elseif($logitem['data'][0] < 0)
			{
				// Group
				$logitem['data'][0] = abs($logitem['data'][0]);
				$lang_string .= '_group';
			}
			break;
		case 'admin_log_user_admin_permissions_delete': // deleting group/user admin permissions
			if($logitem['data'][0] > 0)
			{
				// User
				$lang_string .= '_user';
			}
			elseif($logitem['data'][0] < 0)
			{
				// Group
				$logitem['data'][0] = abs($logitem['data'][0]);
				$lang_string .= '_group';
			}
			break;
		case 'admin_log_user_banning_': // banning
			if($logitem['data'][2] == 0)
			{
				$lang_string = 'admin_log_user_banning_add_permanent';
			}
			else
			{
				$logitem['data'][2] = my_date($mybb->settings['dateformat'], $logitem['data'][2]);
				$lang_string = 'admin_log_user_banning_add_temporary';
			}
			break;
		case 'admin_log_user_groups_join_requests':
			if($logitem['data'][0] == 'approve')
			{
				$lang_string = 'admin_log_user_groups_join_requests_approve';
			}
			else
			{
				$lang_string = 'admin_log_user_groups_join_requests_deny';
			}
			break;
		case 'admin_log_user_users_inline_banned':
			if($logitem['data'][1] == 0)
			{
				$lang_string = 'admin_log_user_users_inline_banned_perm';
			}
			else
			{
				$logitem['data'][1] = my_date($mybb->settings['dateformat'], $logitem['data'][1]);
				$lang_string = 'admin_log_user_users_inline_banned_temp';
			}
			break;
	}

	$plugin_array = array('logitem' => &$logitem, 'lang_string' => &$lang_string);
	$plugins->run_hooks("admin_tools_get_admin_log_action", $plugin_array);

	if(isset($lang->$lang_string))
	{
		array_unshift($logitem['data'], $lang->$lang_string); // First parameter for sprintf is the format string
		$string = call_user_func_array(array($lang, 'sprintf'), $logitem['data']);
		if(!$string)
		{
			$string = $lang->$lang_string; // Fall back to the one in the language pack
		}
	}
	else
	{
		if(isset($logitem['data']['type']) && $logitem['data']['type'] == 'admin_locked_out')
		{
			$string = $lang->sprintf($lang->admin_log_admin_locked_out, (int) $logitem['data']['uid'], htmlspecialchars_uni($logitem['data']['username']));
		}
		else
		{
			// Build a default string
			$string = $logitem['module'].' - '.$logitem['action'];
			if(is_array($logitem['data']) && count($logitem['data']) > 0)
			{
				$string .= '('.implode(', ', $logitem['data']).')';
			}
		}
	}
	return $string;
}


