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

$page->add_breadcrumb_item($lang->thread_prefixes, 'index.php?module=config-thread_prefixes');

$sub_tabs = array(
	"thread_prefixes" => array(
		'title' => $lang->thread_prefixes,
		'link' => 'index.php?module=config-thread_prefixes',
		'description' => $lang->thread_prefixes_desc
	),
	"add_prefix" => array(
		'title'=> $lang->add_new_thread_prefix,
		'link' => 'index.php?module=config-thread_prefixes&amp;action=add_prefix',
		'description' => $lang->add_new_thread_prefix_desc
	)
);

$plugins->run_hooks('admin_config_thread_prefixes_begin');

if($mybb->input['action'] == 'add_prefix')
{
	$plugins->run_hooks('admin_config_thread_prefixes_add_prefix');

	if($mybb->request_method == 'post')
	{
		if(trim($mybb->input['prefix']) == '')
		{
			$errors[] = $lang->error_missing_prefix;
		}

		if(trim($mybb->input['displaystyle']) == '')
		{
			$errors[] = $lang->error_missing_display_style;
		}

		if($mybb->input['forum_type'] == 2)
		{
			if(count($mybb->input['forum_1_forums']) < 1)
			{
				$errors[] = $lang->error_no_forums_selected;
			}

			$forum_checked[2] = "checked=\"checked\"";
		}
		else
		{
			$forum_checked[1] = "checked=\"checked\"";
			$mybb->input['forum_1_forums'] = '';
		}

		if($mybb->input['group_type'] == 2)
		{
			if(count($mybb->input['group_1_groups']) < 1)
			{
				$errors[] = $lang->error_no_groups_selected;
			}

			$group_checked[2] = "checked=\"checked\"";
		}
		else
		{
			$group_checked[1] = "checked=\"checked\"";
			$mybb->input['group_1_forums'] = '';
		}

		if(!$errors)
		{
			$new_prefix = array(
				'prefix'		=> $db->escape_string($mybb->input['prefix']),
				'displaystyle'	=> $db->escape_string($mybb->input['displaystyle'])
			);

			if($mybb->input['forum_type'] == 2)
			{
				if(is_array($mybb->input['forum_1_forums']))
				{
					$checked = array();
					foreach($mybb->input['forum_1_forums'] as $fid)
					{
						$checked[] = (int)$fid;
					}

					$new_prefix['forums'] = implode(',', $checked);
				}
			}
			else
			{
				$new_prefix['forums'] = '-1';
			}

			if($mybb->input['group_type'] == 2)
			{
				if(is_array($mybb->input['group_1_groups']))
				{
					$checked = array();
					foreach($mybb->input['group_1_groups'] as $gid)
					{
						$checked[] = (int)$gid;
					}

					$new_prefix['groups'] = implode(',', $checked);
				}
			}
			else
			{
				$new_prefix['groups'] = '-1';
			}

			$pid = $db->insert_query('threadprefixes', $new_prefix);

			$plugins->run_hooks('admin_config_thread_prefixes_add_prefix_commit');

			// Log admin action
			log_admin_action($pid, htmlspecialchars_uni($mybb->input['prefix']));
			$cache->update_threadprefixes();

			flash_message($lang->success_thread_prefix_created, 'success');
			admin_redirect('index.php?module=config-thread_prefixes');
		}
	}

	$page->add_breadcrumb_item($lang->add_new_thread_prefix);
	$page->output_header($lang->thread_prefixes." - ".$lang->add_new_thread_prefix);
	$page->output_nav_tabs($sub_tabs, 'add_prefix');

	$form = new Form('index.php?module=config-thread_prefixes&amp;action=add_prefix', 'post');

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['prefix'] = '';
		$mybb->input['displaystyle'] = '';
		$mybb->input['forum_1_forums'] = '';
		$forum_checked[1] = "checked=\"checked\"";
		$forum_checked[2] = '';
		$mybb->input['group_1_groups'] = '';
		$group_checked[1] = "checked=\"checked\"";
		$group_checked[2] = '';
	}

	$form_container = new FormContainer($lang->prefix_options);
	$form_container->output_row($lang->prefix.' <em>*</em>', $lang->prefix_desc, $form->generate_text_box('prefix', $mybb->input['prefix'], array('id' => 'prefix')), 'prefix');
	$form_container->output_row($lang->display_style.' <em>*</em>', $lang->display_style_desc, $form->generate_text_box('displaystyle', $mybb->input['displaystyle'], array('id' => 'displaystyle')), 'displaystyle');

	$actions = "<script type=\"text/javascript\">
	function checkAction(id)
	{
		var checked = '';

		$('.'+id+'s_check').each(function(e, val)
		{
			if($(this).prop('checked') == true)
			{
				checked = $(this).val();
			}
		});
		$('.'+id+'s').each(function(e)
		{
			$(this).hide();
		});
		if($('#'+id+'_'+checked))
		{
			$('#'+id+'_'+checked).show();
		}
	}
</script>
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forum_type\" value=\"1\" {$forum_checked[1]} class=\"forums_check\" onclick=\"checkAction('forum');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_forums}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forum_type\" value=\"2\" {$forum_checked[2]} class=\"forums_check\" onclick=\"checkAction('forum');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_forums}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"forum_2\" class=\"forums\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->forums_colon}</small></td>
					<td>".$form->generate_forum_select('forum_1_forums[]', $mybb->input['forum_1_forums'], array('multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
	checkAction('forum');
	</script>";
	$form_container->output_row($lang->available_in_forums.' <em>*</em>', '', $actions);

	$group_select = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"group_type\" value=\"1\" {$group_checked[1]} class=\"groups_check\" onclick=\"checkAction('group');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_groups}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"group_type\" value=\"2\" {$group_checked[2]} class=\"groups_check\" onclick=\"checkAction('group');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_groups}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"group_2\" class=\"groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->groups_colon}</small></td>
					<td>".$form->generate_group_select('group_1_groups[]', $mybb->input['group_1_groups'], array('multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
		checkAction('group');
	</script>";
	$form_container->output_row($lang->available_to_groups." <em>*</em>", '', $group_select);

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_thread_prefix);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == 'edit_prefix')
{
	$prefix = build_prefixes($mybb->input['pid']);
	if(empty($prefix['pid']))
	{
		flash_message($lang->error_invalid_prefix, 'error');
		admin_redirect('index.php?module=config-thread_prefixes');
	}

	$plugins->run_hooks('admin_config_thread_prefixes_edit_prefix_start');

	if($mybb->request_method == 'post')
	{
		if(trim($mybb->input['prefix']) == '')
		{
			$errors[] = $lang->error_missing_prefix;
		}

		if(trim($mybb->input['displaystyle']) == '')
		{
			$errors[] = $lang->error_missing_display_style;
		}

		if($mybb->input['forum_type'] == 2)
		{
			if(count($mybb->input['forum_1_forums']) < 1)
			{
				$errors[] = $lang->error_no_forums_selected;
			}

			$forum_checked[2] = "checked=\"checked\"";
		}
		else
		{
			$forum_checked[1] = "checked=\"checked\"";
			$mybb->input['forum_1_forums'] = '';
		}

		if($mybb->input['group_type'] == 2)
		{
			if(count($mybb->input['group_1_groups']) < 1)
			{
				$errors[] = $lang->error_no_groups_selected;
			}

			$group_checked[2] = "checked=\"checked\"";
		}
		else
		{
			$group_checked[1] = "checked=\"checked\"";
			$mybb->input['group_1_forums'] = '';
		}

		if(!$errors)
		{
			$update_prefix = array(
				'prefix'		=> $db->escape_string($mybb->input['prefix']),
				'displaystyle'	=> $db->escape_string($mybb->input['displaystyle'])
			);

			if($mybb->input['forum_type'] == 2)
			{
				if(is_array($mybb->input['forum_1_forums']))
				{
					$checked = array();
					foreach($mybb->input['forum_1_forums'] as $fid)
					{
						$checked[] = (int)$fid;
					}

					$update_prefix['forums'] = implode(',', $checked);
				}
			}
			else
			{
				$update_prefix['forums'] = '-1';
			}

			if($mybb->input['group_type'] == 2)
			{
				if(is_array($mybb->input['group_1_groups']))
				{
					$checked = array();
					foreach($mybb->input['group_1_groups'] as $gid)
					{
						$checked[] = (int)$gid;
					}

					$update_prefix['groups'] = implode(',', $checked);
				}
			}
			else
			{
				$update_prefix['groups'] = '-1';
			}

			$plugins->run_hooks('admin_config_thread_prefixes_edit_prefix_commit');

			$db->update_query('threadprefixes', $update_prefix, "pid='{$prefix['pid']}'");

			// Log admin action
			log_admin_action($prefix['pid'], htmlspecialchars_uni($mybb->input['prefix']));
			$cache->update_threadprefixes();

			flash_message($lang->success_thread_prefix_updated, 'success');
			admin_redirect('index.php?module=config-thread_prefixes');
		}
	}

	$page->add_breadcrumb_item($lang->edit_thread_prefix);
	$page->output_header($lang->thread_prefixes.' - '.$lang->edit_thread_prefix);

	// Setup the edit prefix tab
	unset($sub_tabs);
	$sub_tabs['edit_prefix'] = array(
		"title" => $lang->edit_prefix,
		"link" => "index.php?module=config-thread_prefixes",
		"description" => $lang->edit_prefix_desc
	);
	$page->output_nav_tabs($sub_tabs, "edit_prefix");

	$form = new Form('index.php?module=config-thread_prefixes&amp;action=edit_prefix', 'post');
	echo $form->generate_hidden_field('pid', $prefix['pid']);

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$query = $db->simple_select('threadprefixes', '*', "pid = '{$prefix['pid']}'");
		$threadprefix = $db->fetch_array($query);

		$mybb->input['prefix'] = $threadprefix['prefix'];
		$mybb->input['displaystyle'] = $threadprefix['displaystyle'];
		$mybb->input['forum_1_forums'] = explode(",", $threadprefix['forums']);

		if(!$threadprefix['forums'] || $threadprefix['forums'] == -1)
		{
			$forum_checked[1] = "checked=\"checked\"";
			$forum_checked[2] = '';
		}
		else
		{
			$forum_checked[1] = '';
			$forum_checked[2] = "checked=\"checked\"";
		}

		$mybb->input['group_1_groups'] = explode(",", $threadprefix['groups']);

		if(!$threadprefix['groups'] || $threadprefix['groups'] == -1)
		{
			$group_checked[1] = "checked=\"checked\"";
			$group_checked[2] = '';
		}
		else
		{
			$group_checked[1] = '';
			$group_checked[2] = "checked=\"checked\"";
		}
	}

	$form_container = new FormContainer($lang->prefix_options);
	$form_container->output_row($lang->prefix.' <em>*</em>', $lang->prefix_desc, $form->generate_text_box('prefix', $mybb->input['prefix'], array('id' => 'prefix')), 'prefix');
	$form_container->output_row($lang->display_style.' <em>*</em>', $lang->display_style_desc, $form->generate_text_box('displaystyle', $mybb->input['displaystyle'], array('id' => 'displaystyle')), 'displaystyle');

	$actions = "<script type=\"text/javascript\">
	function checkAction(id)
	{
		var checked = '';

		$('.'+id+'s_check').each(function(e, val)
		{
			if($(this).prop('checked') == true)
			{
				checked = $(this).val();
			}
		});
		$('.'+id+'s').each(function(e)
		{
			$(this).hide();
		});
		if($('#'+id+'_'+checked))
		{
			$('#'+id+'_'+checked).show();
		}
	}
</script>
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forum_type\" value=\"1\" {$forum_checked[1]} class=\"forums_check\" onclick=\"checkAction('forum');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_forums}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"forum_type\" value=\"2\" {$forum_checked[2]} class=\"forums_check\" onclick=\"checkAction('forum');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_forums}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"forum_2\" class=\"forums\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->forums_colon}</small></td>
					<td>".$form->generate_forum_select('forum_1_forums[]', $mybb->input['forum_1_forums'], array('multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
	checkAction('forum');
	</script>";
	$form_container->output_row($lang->available_in_forums.' <em>*</em>', '', $actions);

	$group_select = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"group_type\" value=\"1\" {$group_checked[1]} class=\"groups_check\" onclick=\"checkAction('group');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_groups}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"group_type\" value=\"2\" {$group_checked[2]} class=\"groups_check\" onclick=\"checkAction('group');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_groups}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"group_2\" class=\"groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->groups_colon}</small></td>
					<td>".$form->generate_group_select('group_1_groups[]', $mybb->input['group_1_groups'], array('multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
		checkAction('group');
	</script>";
	$form_container->output_row($lang->available_to_groups." <em>*</em>", '', $group_select);

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_thread_prefix);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == 'delete_prefix')
{
	$prefix = build_prefixes($mybb->input['pid']);
	if(empty($prefix['pid']))
	{
		flash_message($lang->error_invalid_thread_prefix, 'error');
		admin_redirect('index.php?module=config-thread_prefixes');
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect('index.php?module=config-thread_prefixes');
	}

	$plugins->run_hooks('admin_config_thread_prefixes_delete_prefix');

	if($mybb->request_method == 'post')
	{
		// Remove prefix from existing threads
		$update_threads = array('prefix' => 0);

		// Delete prefix
		$db->delete_query('threadprefixes', "pid='{$prefix['pid']}'");

		$plugins->run_hooks('admin_config_thread_prefixes_delete_thread_prefix_commit');

		$db->update_query('threads', $update_threads, "prefix='{$prefix['pid']}'");

		// Log admin action
		log_admin_action($prefix['pid'], htmlspecialchars_uni($prefix['prefix']));
		$cache->update_threadprefixes();

		flash_message($lang->success_thread_prefix_deleted, 'success');
		admin_redirect('index.php?module=config-thread_prefixes');
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-thread_prefixes&amp;action=delete_prefix&amp;pid={$prefix['pid']}", $lang->confirm_thread_prefix_deletion);
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks('admin_config_thread_prefixes_start');

	$page->output_header($lang->thread_prefixes);
	$page->output_nav_tabs($sub_tabs, 'thread_prefixes');

	$table = new Table;
	$table->construct_header($lang->prefix);
	$table->construct_header($lang->controls, array('class' => 'align_center', 'colspan' => 2));

	$prefixes = build_prefixes();
	if(!empty($prefixes))
	{
		foreach($prefixes as $prefix)
		{
			$table->construct_cell("<a href=\"index.php?module=config-thread_prefixes&amp;action=edit_prefix&amp;pid={$prefix['pid']}\"><strong>".htmlspecialchars_uni($prefix['prefix'])."</strong></a>");
			$table->construct_cell("<a href=\"index.php?module=config-thread_prefixes&amp;action=edit_prefix&amp;pid={$prefix['pid']}\">{$lang->edit}</a>", array('width' => 100, 'class' => "align_center"));
			$table->construct_cell("<a href=\"index.php?module=config-thread_prefixes&amp;action=delete_prefix&amp;pid={$prefix['pid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_thread_prefix_deletion}')\">{$lang->delete}</a>", array('width' => 100, 'class' => 'align_center'));
			$table->construct_row();
		}
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_thread_prefixes, array('colspan' => 3));
		$table->construct_row();
	}

	$table->output($lang->thread_prefixes);

	$page->output_footer();
}
