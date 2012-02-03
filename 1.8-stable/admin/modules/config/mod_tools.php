<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: mod_tools.php 5353 2011-02-15 14:24:00Z Tomm $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->mod_tools, "index.php?module=config-mod_tools");

$plugins->run_hooks("admin_config_mod_tools_begin");

if($mybb->input['action'] == "delete_post_tool")
{
	$plugins->run_hooks("admin_config_mod_tools_delete_post_tool");
	
	$query = $db->simple_select("modtools", "*", "tid='{$mybb->input['tid']}'");
	$tool = $db->fetch_array($query);
	
	// Does the post tool not exist?
	if(!$tool['tid'])
	{
		flash_message($lang->error_invalid_post_tool, 'error');
		admin_redirect("index.php?module=config-mod_tools&action=post_tools");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-mod_tools&action=post_tools");
	}

	if($mybb->request_method == 'post')
	{
		// Delete the type
		$db->delete_query('modtools', "tid='{$tool['tid']}'");
		
		$plugins->run_hooks("admin_config_mod_tools_delete_post_tool_commit");

		// Log admin action
		log_admin_action($tool['tid'], $tool['name']);

		flash_message($lang->success_post_tool_deleted, 'success');
		admin_redirect("index.php?module=config-mod_tools&action=post_tools");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-mod_tools&amp;action=post_tools&amp;tid={$type['tid']}", $lang->confirm_post_tool_deletion);
	}
}

if($mybb->input['action'] == "delete_thread_tool")
{
	$plugins->run_hooks("admin_config_mod_tools_delete_thread_tool");
	
	$query = $db->simple_select("modtools", "*", "tid='{$mybb->input['tid']}'");
	$tool = $db->fetch_array($query);
	
	// Does the post tool not exist?
	if(!$tool['tid'])
	{
		flash_message($lang->error_invalid_thread_tool, 'error');
		admin_redirect("index.php?module=config-mod_tools");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-mod_tools");
	}

	if($mybb->request_method == 'post')
	{
		// Delete the type
		$db->delete_query('modtools', "tid='{$tool['tid']}'");
		
		$plugins->run_hooks("admin_config_mod_tools_delete_thread_tool_commit");

		// Log admin action
		log_admin_action($tool['tid'], $tool['name']);
		$cache->update_forumsdisplay();

		flash_message($lang->success_thread_tool_deleted, 'success');
		admin_redirect("index.php?module=config-mod_tools");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-mod_tools&amp;action=delete_thread_tool&amp;tid={$tool['tid']}", $lang->confirm_thread_tool_deletion);
	}
}


if($mybb->input['action'] == "post_tools")
{
	$plugins->run_hooks("admin_config_mod_tools_post_tools");
	
	$page->add_breadcrumb_item($lang->post_tools);
	$page->output_header($lang->mod_tools." - ".$lang->post_tools);
	
	$sub_tabs['thread_tools'] = array(
		'title' => $lang->thread_tools,
		'link' => "index.php?module=config-mod_tools"
	);
	$sub_tabs['add_thread_tool'] = array(
		'title'=> $lang->add_thread_tool,
		'link' => "index.php?module=config-mod_tools&amp;action=add_thread_tool"
	);
	$sub_tabs['post_tools'] = array(
		'title' => $lang->post_tools,
		'link' => "index.php?module=config-mod_tools&amp;action=post_tools",
		'description' => $lang->post_tools_desc
	);
	$sub_tabs['add_post_tool'] = array(
		'title'=> $lang->add_post_tool,
		'link' => "index.php?module=config-mod_tools&amp;action=add_post_tool"
	);
		
	$page->output_nav_tabs($sub_tabs, 'post_tools');
	
	$table = new Table;
	$table->construct_header($lang->title);
	$table->construct_header($lang->controls, array('class' => "align_center", 'colspan' => 2));
	
	$query = $db->simple_select('modtools', 'tid, name, description, type', "type='p'", array('order_by' => 'name'));
	while($tool = $db->fetch_array($query))
	{
		$table->construct_cell("<a href=\"index.php?module=config-mod_tools&amp;action=edit_post_tool&amp;tid={$tool['tid']}\"><strong>".htmlspecialchars_uni($tool['name'])."</strong></a><br /><small>".htmlspecialchars_uni($tool['description'])."</small>");
		$table->construct_cell("<a href=\"index.php?module=config-mod_tools&amp;action=edit_post_tool&amp;tid={$tool['tid']}\">{$lang->edit}</a>", array('width' => 100, 'class' => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=config-mod_tools&amp;action=delete_post_tool&amp;tid={$tool['tid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_post_tool_deletion}')\">{$lang->delete}</a>", array('width' => 100, 'class' => "align_center"));
		$table->construct_row();
	}
	
	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_post_tools, array('colspan' => 3));
		$table->construct_row();
	}
	
	$table->output($lang->post_tools);
	
	$page->output_footer();
}

if($mybb->input['action'] == "edit_thread_tool")
{
	$plugins->run_hooks("admin_config_mod_tools_edit_thread_tool");
	
	$query = $db->simple_select("modtools", "COUNT(tid) as tools", "tid = '{$mybb->input['tid']}' AND type='t'");
	if($db->fetch_field($query, "tools") < 1)
	{
		flash_message($lang->error_invalid_thread_tool, 'error');
		admin_redirect("index.php?module=config-mod_tools");
	}

	if($mybb->request_method == 'post')
	{
		if(trim($mybb->input['title']) == "")
		{
			$errors[] = $lang->error_missing_title;
		}
		
		if(trim($mybb->input['description']) == "")
		{
			$errors[] = $lang->error_missing_description;
		}
		
		if($mybb->input['forum_type'] == 2)
		{
			$forum_checked[1] = '';
			$forum_checked[2] = "checked=\"checked\"";

			if(count($mybb->input['forum_1_forums']) < 1)
			{
				$errors[] = $lang->error_no_forums_selected;
			}
		}
		else
		{
			$forum_checked[1] = "checked=\"checked\"";
			$forum_checked[2] = '';

			$mybb->input['forum_1_forums'] = '';
		}
		
		
		if($mybb->input['approvethread'] != '' && $mybb->input['approvethread'] != 'approve' && $mybb->input['approvethread'] != 'unapprove' && $mybb->input['approvethread'] != 'toggle')
		{
			$mybb->input['approvethread'] = '';
		}
		
		if($mybb->input['openthread'] != '' && $mybb->input['openthread'] != 'open' && $mybb->input['openthread'] != 'close' && $mybb->input['openthread'] != 'toggle')
		{
			$mybb->input['openthread'] = '';
		}
		
		if($mybb->input['move_type'] == 2)
		{
			$move_checked[1] = '';
			$move_checked[2] = "checked=\"checked\"";

			if(!$mybb->input['move_1_forum'])
			{
				$errors[] = $lang->error_no_move_forum_selected;
			}
			else
			{
				// Check that the destination forum is not a category
				$query = $db->simple_select("forums", "type", "fid = '".intval($mybb->input['move_1_forum'])."'");
				if($db->fetch_field($query, "type") == "c")
				{
					$errors[] = $lang->error_forum_is_category;
				}
			}

			if($mybb->input['move_2_redirect'] != 1 && $mybb->input['move_2_redirect'] != 0)
			{
				$mybb->input['move_2_redirect'] = 0;
			}
			
			if(!isset($mybb->input['move_3_redirecttime']))
			{
				$mybb->input['move_3_redirecttime'] = '';
			}
		}
		else
		{
			$move_checked[1] = "checked=\"checked\"";
			$move_checked[2] = '';

			$mybb->input['move_1_forum'] = '';
			$mybb->input['move_2_redirect'] = 0;
			$mybb->input['move_3_redirecttime'] = '';
		}
		
		if($mybb->input['copy_type'] == 2)
		{
			$copy_checked[1] = '';
			$copy_checked[2] = "checked=\"checked\"";

			if(!$mybb->input['copy_1_forum'])
			{
				$errors[] = $lang->error_no_copy_forum_selected;
			}
			else
			{
				$query = $db->simple_select("forums", "type", "fid = '".intval($mybb->input['copy_1_forum'])."'");
				if($db->fetch_field($query, "type") == "c")
				{
					$errors[] = $lang->error_forum_is_category;
				}
			}
		}
		else
		{
			$copy_checked[1] = "checked=\"checked\"";
			$copy_checked[2] = '';

			$mybb->input['copy_1_forum'] = '';
		}
		
		if(!$errors)
		{
			$thread_options = array(
				'deletethread' => $mybb->input['deletethread'],
				'mergethreads' => $mybb->input['mergethreads'],
				'deletepoll' => $mybb->input['deletepoll'],
				'removeredirects' => $mybb->input['removeredirects'],
				'approvethread' => $mybb->input['approvethread'],
				'openthread' => $mybb->input['openthread'],
				'movethread' => intval($mybb->input['move_1_forum']),
				'movethreadredirect' => $mybb->input['move_2_redirect'],
				'movethreadredirectexpire' => intval($mybb->input['move_3_redirecttime']),
				'copythread' => intval($mybb->input['copy_1_forum']),
				'newsubject' => $mybb->input['newsubject'],
				'addreply' => $mybb->input['newreply'],
				'replysubject' => $mybb->input['newreplysubject'],
				'threadprefix' => intval($mybb->input['threadprefix'])
			);
			
			$update_tool['type'] = 't';
			$update_tool['threadoptions'] = $db->escape_string(serialize($thread_options));
			$update_tool['name'] = $db->escape_string($mybb->input['title']);
			$update_tool['description'] = $db->escape_string($mybb->input['description']);
			$update_tool['forums'] = '';
			
			if(is_array($mybb->input['forum_1_forums']))
			{
				foreach($mybb->input['forum_1_forums'] as $fid)
				{
					$checked[] = intval($fid);
				}
				$update_tool['forums'] = implode(',', $checked);
			}
		
			$db->update_query("modtools", $update_tool, "tid='{$mybb->input['tid']}'");
			
			$plugins->run_hooks("admin_config_mod_tools_edit_thread_tool_commit");

			// Log admin action
			log_admin_action($mybb->input['tid'], $mybb->input['title']);
			$cache->update_forumsdisplay();

			flash_message($lang->success_mod_tool_updated, 'success');
			admin_redirect("index.php?module=config-mod_tools");
		}
	}
	
	$page->add_breadcrumb_item($lang->edit_thread_tool);
	$page->output_header($lang->mod_tools." - ".$lang->edit_thread_tool);

	$sub_tabs['edit_thread_tool'] = array(
		"title" => $lang->edit_thread_tool,
		"description" => $lang->edit_thread_tool_desc,
		"link" => "index.php?module=config-mod_tools"
	);

	$page->output_nav_tabs($sub_tabs, 'edit_thread_tool');

	$form = new Form("index.php?module=config-mod_tools&amp;action=edit_thread_tool", 'post');
	echo $form->generate_hidden_field("tid", $mybb->input['tid']);
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$query = $db->simple_select("modtools", "*", "tid = '{$mybb->input['tid']}'");
		$modtool = $db->fetch_array($query);
		$thread_options = unserialize($modtool['threadoptions']);

		$mybb->input['title'] = $modtool['name'];
		$mybb->input['description'] = $modtool['description'];
		$mybb->input['forum_1_forums'] = explode(",", $modtool['forums']);

		if(!$modtool['forums'] || $modtool['forums'] == -1)
		{
			$forum_checked[1] = "checked=\"checked\"";
			$forum_checked[2] = '';
		}
		else
		{
			$forum_checked[1] = '';
			$forum_checked[2] = "checked=\"checked\"";
		}
		
		$mybb->input['approvethread'] = $thread_options['approvethread'];
		$mybb->input['openthread'] = $thread_options['openthread'];
		$mybb->input['move_1_forum'] = $thread_options['movethread'];
		$mybb->input['move_2_redirect'] = $thread_options['movethreadredirect'];
		$mybb->input['move_3_redirecttime'] = $thread_options['movethreadredirectexpire'];
		
		if(!$thread_options['movethread'])
		{
			$move_checked[1] = "checked=\"checked\"";
			$move_checked[2] = '';
		}
		else
		{
			$move_checked[1] = '';
			$move_checked[2] = "checked=\"checked\"";
		}
		
		if(!$thread_options['copythread'])
		{
			$copy_checked[1] = "checked=\"checked\"";
			$copy_checked[2] = '';
		}
		else
		{
			$copy_checked[1] = '';
			$copy_checked[2] = "checked=\"checked\"";
		}		
		
		$mybb->input['copy_1_forum'] = $thread_options['copythread'];
		$mybb->input['deletethread'] = $thread_options['deletethread'];
		$mybb->input['mergethreads'] = $thread_options['mergethreads'];
		$mybb->input['deletepoll'] = $thread_options['deletepoll'];
		$mybb->input['removeredirects'] = $thread_options['removeredirects'];
		$mybb->input['threadprefix'] = $thread_options['threadprefix'];
		$mybb->input['newsubject'] = $thread_options['newsubject'];
		$mybb->input['newreply'] = $thread_options['addreply'];
		$mybb->input['newreplysubject'] = $thread_options['replysubject'];
	}
	
	$form_container = new FormContainer($lang->general_options);
	$form_container->output_row($lang->name." <em>*</em>", '', $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_description." <em>*</em>", '', $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');


	$actions = "<script type=\"text/javascript\">
    function checkAction(id)
    {
        var checked = '';
        
        $$('.'+id+'s_check').each(function(e)
        {
            if(e.checked == true)
            {
                checked = e.value;
            }
        });
        $$('.'+id+'s').each(function(e)
        {
        	Element.hide(e);
        });
        if($(id+'_'+checked))
        {
            Element.show(id+'_'+checked);
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
	$form_container->output_row($lang->available_in_forums." <em>*</em>", '', $actions);
	$form_container->end();
	
	$approve_unapprove = array(
		'' => $lang->no_change,
		'approve' => $lang->approve,
		'unapprove' => $lang->unapprove,
		'toggle' => $lang->toggle
	);
	
	$open_close = array(
		'' => $lang->no_change,
		'open' => $lang->open,
		'close' => $lang->close,
		'toggle' => $lang->toggle
	);
	
	$form_container = new FormContainer($lang->thread_moderation);
	$form_container->output_row($lang->approve_unapprove." <em>*</em>", '', $form->generate_select_box('approvethread', $approve_unapprove, $mybb->input['approvethread'], array('id' => 'approvethread')), 'approvethread');
	$form_container->output_row($lang->open_close_thread." <em>*</em>", '', $form->generate_select_box('openthread', $open_close, $mybb->input['openthread'], array('id' => 'openthread')), 'openthread');


	$actions = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"move_type\" value=\"1\" {$move_checked[1]} class=\"moves_check\" onclick=\"checkAction('move');\" style=\"vertical-align: middle;\" /> <strong>{$lang->do_not_move_thread}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"move_type\" value=\"2\" {$move_checked[2]} class=\"moves_check\" onclick=\"checkAction('move');\" style=\"vertical-align: middle;\" /> <strong>{$lang->move_thread}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"move_2\" class=\"moves\">
			<table cellpadding=\"4\">
				<tr>
					<td><small>{$lang->forum_to_move_to}</small></td>
					<td>".$form->generate_forum_select('move_1_forum', $mybb->input['move_1_forum'])."</td>
				</tr>
				<tr>
					<td><small>{$lang->leave_redirect}</small></td>
					<td>".$form->generate_yes_no_radio('move_2_redirect', $mybb->input['move_2_redirect'], array('style' => 'width: 2em;'))."</td>
				</tr>
				<tr>
					<td><small>{$lang->delete_redirect_after}</small></td>
					<td>".$form->generate_text_box('move_3_redirecttime', $mybb->input['move_3_redirecttime'], array('style' => 'width: 2em;'))." {$lang->days}</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
	checkAction('move');
	</script>";
	$form_container->output_row($lang->move_thread." <em>*</em>", $lang->move_thread_desc, $actions);
	
	$actions = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"copy_type\" value=\"1\" {$copy_checked[1]} class=\"copys_check\" onclick=\"checkAction('copy');\" style=\"vertical-align: middle;\" /> <strong>{$lang->do_not_copy_thread}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"copy_type\" value=\"2\" {$copy_checked[2]} class=\"copys_check\" onclick=\"checkAction('copy');\" style=\"vertical-align: middle;\" /> <strong>{$lang->copy_thread}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"copy_2\" class=\"copys\">
			<table cellpadding=\"4\">
				<tr>
					<td><small>{$lang->forum_to_copy_to}</small></td>
					<td>".$form->generate_forum_select('copy_1_forum', $mybb->input['copy_1_forum'])."</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
	checkAction('copy');
	</script>";
	$form_container->output_row($lang->copy_thread." <em>*</em>", '', $actions);
	$form_container->output_row($lang->delete_thread." <em>*</em>", '', $form->generate_yes_no_radio('deletethread', $mybb->input['deletethread'], array('style' => 'width: 2em;')));
	$form_container->output_row($lang->merge_thread." <em>*</em>", $lang->merge_thread_desc, $form->generate_yes_no_radio('mergethreads', $mybb->input['mergethreads'], array('style' => 'width: 2em;')));
	$form_container->output_row($lang->delete_poll." <em>*</em>", '', $form->generate_yes_no_radio('deletepoll', $mybb->input['deletepoll'], array('style' => 'width: 2em;')));
	$form_container->output_row($lang->delete_redirects." <em>*</em>", '', $form->generate_yes_no_radio('removeredirects', $mybb->input['removeredirects'], array('style' => 'width: 2em;')));
	
	$query = $db->simple_select('threadprefixes', 'pid, prefix');
	if($db->num_rows($query) > 0)
	{
		$thread_prefixes = array(
			'-1' => $lang->no_change,
			'0' => $lang->no_prefix
		);
		
		while($prefix = $db->fetch_array($query))
		{
			$thread_prefixes[$prefix['pid']] = $prefix['prefix'];
		}
		
		$form_container->output_row($lang->apply_thread_prefix." <em>*</em>", '', $form->generate_select_box('threadprefix', $thread_prefixes, array(intval($mybb->input['threadprefix'])), array('id' => 'threadprefix')), 'threadprefix');
	}
	
	$form_container->output_row($lang->new_subject." <em>*</em>", $lang->new_subject_desc, $form->generate_text_box('newsubject', $mybb->input['newsubject'], array('id' => 'newsubject')));
	$form_container->end();
	
	$form_container = new FormContainer($lang->add_new_reply);
	$form_container->output_row($lang->add_new_reply, $lang->add_new_reply_desc, $form->generate_text_area('newreply', $mybb->input['newreply'], array('id' => 'newreply')), 'newreply');
	$form_container->output_row($lang->reply_subject, $lang->reply_subject_desc, $form->generate_text_box('newreplysubject', $mybb->input['newreplysubject'], array('id' => 'newreplysubject')), 'newreplysubject');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_thread_tool);

	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "add_thread_tool")
{
	$plugins->run_hooks("admin_config_mod_tools_add_thread_tool");
	
	if($mybb->request_method == 'post')
	{
		if(trim($mybb->input['title']) == "")
		{
			$errors[] = $lang->error_missing_title;
		}
		
		if(trim($mybb->input['description']) == "")
		{
			$errors[] = $lang->error_missing_description;
		}
		
		if($mybb->input['forum_type'] == 2)
		{
			$forum_checked[1] = '';
			$forum_checked[2] = "checked=\"checked\"";

			if(count($mybb->input['forum_1_forums']) < 1)
			{
				$errors[] = $lang->error_no_forums_selected;
			}
		}
		else
		{
			$forum_checked[1] = "checked=\"checked\"";
			$forum_checked[2] = '';

			$mybb->input['forum_1_forums'] = '';
		}
		
		
		if($mybb->input['approvethread'] != '' && $mybb->input['approvethread'] != 'approve' && $mybb->input['approvethread'] != 'unapprove' && $mybb->input['approvethread'] != 'toggle')
		{
			$mybb->input['approvethread'] = '';
		}
		
		if($mybb->input['openthread'] != '' && $mybb->input['openthread'] != 'open' && $mybb->input['openthread'] != 'close' && $mybb->input['openthread'] != 'toggle')
		{
			$mybb->input['openthread'] = '';
		}
		
		if(!intval($mybb->input['threadprefix']))
		{
			$mybb->input['threadprefix'] = '';
		}
		
		if($mybb->input['move_type'] == 2)
		{
			$move_checked[1] = '';
			$move_checked[2] = "checked=\"checked\"";

			if(!$mybb->input['move_1_forum'])
			{
				$errors[] = $lang->error_no_move_forum_selected;
			}
			else
			{
				// Check that the destination forum is not a category
				$query = $db->simple_select("forums", "type", "fid = '".intval($mybb->input['move_1_forum'])."'");
				if($db->fetch_field($query, "type") == "c")
				{
					$errors[] = $lang->error_forum_is_category;
				}
			}
		}
		else
		{
			$move_checked[1] = "checked=\"checked\"";
			$move_checked[2] = '';

			$mybb->input['move_1_forum'] = '';
			$mybb->input['move_2_redirect'] = 0;
			$mybb->input['move_3_redirecttime'] = '';
		}
		
		if($mybb->input['copy_type'] == 2)
		{
			$copy_checked[1] = '';
			$copy_checked[2] = "checked=\"checked\"";

			if(!$mybb->input['copy_1_forum'])
			{
				$errors[] = $lang->error_no_copy_forum_selected;
			}
			else
			{
				$query = $db->simple_select("forums", "type", "fid = '".intval($mybb->input['copy_1_forum'])."'");
				if($db->fetch_field($query, "type") == "c")
				{
					$errors[] = $lang->error_forum_is_category;
				}
			}
		}
		else
		{
			$copy_checked[1] = "checked=\"checked\"";
			$copy_checked[2] = '';

			$mybb->input['copy_1_forum'] = '';
		}
		
		if(!$errors)
		{
			$thread_options = array(
				'deletethread' => $mybb->input['deletethread'],
				'mergethreads' => $mybb->input['mergethreads'],
				'deletepoll' => $mybb->input['deletepoll'],
				'removeredirects' => $mybb->input['removeredirects'],
				'approvethread' => $mybb->input['approvethread'],
				'openthread' => $mybb->input['openthread'],
				'movethread' => intval($mybb->input['move_1_forum']),
				'movethreadredirect' => $mybb->input['move_2_redirect'],
				'movethreadredirectexpire' => intval($mybb->input['move_3_redirecttime']),
				'copythread' => intval($mybb->input['copy_1_forum']),
				'newsubject' => $mybb->input['newsubject'],
				'addreply' => $mybb->input['newreply'],
				'replysubject' => $mybb->input['newreplysubject'],
				'threadprefix' => $mybb->input['threadprefix'],
			);
			
			$new_tool['type'] = 't';
			$new_tool['threadoptions'] = $db->escape_string(serialize($thread_options));
			$new_tool['name'] = $db->escape_string($mybb->input['title']);
			$new_tool['description'] = $db->escape_string($mybb->input['description']);
			$new_tool['forums'] = '';
			$new_tool['postoptions'] = '';
			
			if($mybb->input['forum_type'] == 2)
			{
				if(is_array($mybb->input['forum_1_forums']))
				{
					foreach($mybb->input['forum_1_forums'] as $fid)
					{
						$checked[] = intval($fid);
					}
					$new_tool['forums'] = implode(',', $checked);
				}
			}
			else
			{
				$new_tool['forums'] = "-1";
			}

			if(intval($mybb->input['threadprefix']) >= 0)
			{
				$thread_options['threadprefix'] = intval($mybb->input['threadprefix']);
			}

			$tid = $db->insert_query("modtools", $new_tool);
			
			$plugins->run_hooks("admin_config_mod_tools_add_thread_tool_commit");

			// Log admin action
			log_admin_action($tid, $mybb->input['title']);
			$cache->update_forumsdisplay();
			
			flash_message($lang->success_mod_tool_created, 'success');
			admin_redirect("index.php?module=config-mod_tools");
		}
	}
	
	$page->add_breadcrumb_item($lang->add_new_thread_tool);
	$page->output_header($lang->mod_tools." - ".$lang->add_new_thread_tool);
	
	$sub_tabs['thread_tools'] = array(
		'title' => $lang->thread_tools,
		'link' => "index.php?module=config-mod_tools"
	);
	$sub_tabs['add_thread_tool'] = array(
		'title'=> $lang->add_new_thread_tool,
		'link' => "index.php?module=config-mod_tools&amp;action=add_thread_tool",
		'description' => $lang->add_thread_tool_desc
	);
	$sub_tabs['post_tools'] = array(
		'title' => $lang->post_tools,
		'link' => "index.php?module=config-mod_tools&amp;action=post_tools",
	);
	$sub_tabs['add_post_tool'] = array(
		'title'=> $lang->add_new_post_tool,
		'link' => "index.php?module=config-mod_tools&amp;action=add_post_tool"
	);
		
	$page->output_nav_tabs($sub_tabs, 'add_thread_tool');
	
	$form = new Form("index.php?module=config-mod_tools&amp;action=add_thread_tool", 'post');
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['title'] = '';
		$mybb->input['description'] = '';
		$mybb->input['forum_1_forums'] = '';
		$forum_checked[1] = "checked=\"checked\"";
		$forum_checked[2] = '';
		$mybb->input['approvethread'] = '';
		$mybb->input['openthread'] = '';
		$mybb->input['move_1_forum'] = '';
		$mybb->input['move_2_redirect'] = '0';
		$mybb->input['move_3_redirecttime'] = '';
		$move_checked[1] = "checked=\"checked\"";
		$move_checked[2] = '';
		$copy_checked[1] = "checked=\"checked\"";
		$copy_checked[2] = '';
		$mybb->input['copy_1_forum'] = '';
		$mybb->input['deletethread'] = '0';
		$mybb->input['mergethreads'] = '0';
		$mybb->input['deletepoll'] = '0';
		$mybb->input['removeredirects'] = '0';
		$mybb->input['threadprefix'] = '-1';
		$mybb->input['newsubject'] = '{subject}';
		$mybb->input['newreply'] = '';
		$mybb->input['newreplysubject'] = '{subject}';
	}

	$form_container = new FormContainer($lang->general_options);
	$form_container->output_row($lang->name." <em>*</em>", '', $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_description." <em>*</em>", '', $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');


	$actions = "<script type=\"text/javascript\">
    function checkAction(id)
    {
        var checked = '';
        
        $$('.'+id+'s_check').each(function(e)
        {
            if(e.checked == true)
            {
                checked = e.value;
            }
        });
        $$('.'+id+'s').each(function(e)
        {
        	Element.hide(e);
        });
        if($(id+'_'+checked))
        {
            Element.show(id+'_'+checked);
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
	$form_container->output_row($lang->available_in_forums." <em>*</em>", '', $actions);
	$form_container->end();
	
	$approve_unapprove = array(
		'' => $lang->no_change,
		'approve' => $lang->approve,
		'unapprove' => $lang->unapprove,
		'toggle' => $lang->toggle
	);
	
	$open_close = array(
		'' => $lang->no_change,
		'open' => $lang->open,
		'close' => $lang->close,
		'toggle' => $lang->toggle
	);
	
	$form_container = new FormContainer($lang->thread_moderation);
	$form_container->output_row($lang->approve_unapprove." <em>*</em>", '', $form->generate_select_box('approvethread', $approve_unapprove, $mybb->input['approvethread'], array('id' => 'approvethread')), 'approvethread');
	$form_container->output_row($lang->open_close_thread." <em>*</em>", '', $form->generate_select_box('openthread', $open_close, $mybb->input['openthread'], array('id' => 'openthread')), 'openthread');


	$actions = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"move_type\" value=\"1\" {$move_checked[1]} class=\"moves_check\" onclick=\"checkAction('move');\" style=\"vertical-align: middle;\" /> <strong>{$lang->do_not_move_thread}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"move_type\" value=\"2\" {$move_checked[2]} class=\"moves_check\" onclick=\"checkAction('move');\" style=\"vertical-align: middle;\" /> <strong>{$lang->move_thread}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"move_2\" class=\"moves\">
			<table cellpadding=\"4\">
				<tr>
					<td><small>{$lang->forum_to_move_to}</small></td>
					<td>".$form->generate_forum_select('move_1_forum', $mybb->input['move_1_forum'])."</td>
				</tr>
				<tr>
					<td><small>{$lang->leave_redirect}</small></td>
					<td>".$form->generate_yes_no_radio('move_2_redirect', $mybb->input['move_2_redirect'], array('style' => 'width: 2em;'))."</td>
				</tr>
				<tr>
					<td><small>{$lang->delete_redirect_after}</small></td>
					<td>".$form->generate_text_box('move_3_redirecttime', $mybb->input['move_3_redirecttime'], array('style' => 'width: 2em;'))." {$lang->days}</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
	checkAction('move');
	</script>";
	$form_container->output_row($lang->move_thread." <em>*</em>", $lang->move_thread_desc, $actions);
	
	$actions = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"copy_type\" value=\"1\" {$copy_checked[1]} class=\"copys_check\" onclick=\"checkAction('copy');\" style=\"vertical-align: middle;\" /> <strong>{$lang->do_not_copy_thread}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"copy_type\" value=\"2\" {$copy_checked[2]} class=\"copys_check\" onclick=\"checkAction('copy');\" style=\"vertical-align: middle;\" /> <strong>{$lang->copy_thread}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"copy_2\" class=\"copys\">
			<table cellpadding=\"4\">
				<tr>
					<td><small>{$lang->forum_to_copy_to}</small></td>
					<td>".$form->generate_forum_select('copy_1_forum', $mybb->input['copy_1_forum'])."</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
	checkAction('copy');
	</script>";
	$form_container->output_row($lang->copy_thread." <em>*</em>", '', $actions);
	$form_container->output_row($lang->delete_thread." <em>*</em>", '', $form->generate_yes_no_radio('deletethread', $mybb->input['deletethread'], array('style' => 'width: 2em;')));
	$form_container->output_row($lang->merge_thread." <em>*</em>", $lang->merge_thread_desc, $form->generate_yes_no_radio('mergethreads', $mybb->input['mergethreads'], array('style' => 'width: 2em;')));
	$form_container->output_row($lang->delete_poll." <em>*</em>", '', $form->generate_yes_no_radio('deletepoll', $mybb->input['deletepoll'], array('style' => 'width: 2em;')));
	$form_container->output_row($lang->delete_redirects." <em>*</em>", '', $form->generate_yes_no_radio('removeredirects', $mybb->input['removeredirects'], array('style' => 'width: 2em;')));
	
	$query = $db->simple_select('threadprefixes', 'pid, prefix');
	if($db->num_rows($query) > 0)
	{
		$thread_prefixes = array(
			'-1' => $lang->no_change,
			'0' => $lang->no_prefix
		);
	
		while($prefix = $db->fetch_array($query))
		{
			$thread_prefixes[$prefix['pid']] = $prefix['prefix'];
		}
		
		$form_container->output_row($lang->apply_thread_prefix." <em>*</em>", '', $form->generate_select_box('threadprefix', $thread_prefixes, $mybb->input['threadprefix'], array('id' => 'threadprefix')), 'threadprefix');
	}
	
	$form_container->output_row($lang->new_subject." <em>*</em>", $lang->new_subject_desc, $form->generate_text_box('newsubject', $mybb->input['newsubject'], array('id' => 'newsubject')));
	$form_container->end();
	
	$form_container = new FormContainer($lang->add_new_reply);
	$form_container->output_row($lang->add_new_reply, $lang->add_new_reply_desc, $form->generate_text_area('newreply', $mybb->input['newreply'], array('id' => 'newreply')), 'newreply');
	$form_container->output_row($lang->reply_subject, $lang->reply_subject_desc, $form->generate_text_box('newreplysubject', $mybb->input['newreplysubject'], array('id' => 'newreplysubject')), 'newreplysubject');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_thread_tool);

	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "edit_post_tool")
{
	$plugins->run_hooks("admin_config_mod_tools_edit_post_tool");
	
	$query = $db->simple_select("modtools", "COUNT(tid) as tools", "tid = '{$mybb->input['tid']}' AND type='p'");
	if($db->fetch_field($query, "tools") < 1)
	{
		flash_message($lang->error_invalid_post_tool, 'error');
		admin_redirect("index.php?module=config-mod_tools&action=post_tools");
	}
	
	if($mybb->request_method == 'post')
	{
		if(trim($mybb->input['title']) == "")
		{
			$errors[] = $lang->error_missing_title;
		}
		
		if(trim($mybb->input['description']) == "")
		{
			$errors[] = $lang->error_missing_description;
		}
		
		if($mybb->input['forum_type'] == 2)
		{
			if(count($mybb->input['forum_1_forums']) < 1)
			{
				$errors[] = $lang->error_no_forums_selected;
			}
		}
		else
		{
			$mybb->input['forum_1_forums'] = '';
		}		
		
		if($mybb->input['approvethread'] != '' && $mybb->input['approvethread'] != 'approve' && $mybb->input['approvethread'] != 'unapprove' && $mybb->input['approvethread'] != 'toggle')
		{
			$mybb->input['approvethread'] = '';
		}
		
		if($mybb->input['openthread'] != '' && $mybb->input['openthread'] != 'open' && $mybb->input['openthread'] != 'close' && $mybb->input['openthread'] != 'toggle')
		{
			$mybb->input['openthread'] = '';
		}
		
		if($mybb->input['move_type'] == 2)
		{
			if(!$mybb->input['move_1_forum'])
			{
				$errors[] = $lang->error_no_move_forum_selected;
			}
			else
			{
				// Check that the destination forum is not a category
				$query = $db->simple_select("forums", "type", "fid = '".intval($mybb->input['move_1_forum'])."'");
				if($db->fetch_field($query, "type") == "c")
				{
					$errors[] = $lang->error_forum_is_category;
				}
			}
		}
		else
		{
			$mybb->input['move_1_forum'] = '';
			$mybb->input['move_2_redirect'] = 0;
			$mybb->input['move_3_redirecttime'] = '';
		}
		
		if($mybb->input['copy_type'] == 2)
		{
			if(!$mybb->input['copy_1_forum'])
			{
				$errors[] = $lang->error_no_copy_forum_selected;
			}
			else
			{
				$query = $db->simple_select("forums", "type", "fid = '".intval($mybb->input['copy_1_forum'])."'");
				if($db->fetch_field($query, "type") == "c")
				{
					$errors[] = $lang->error_forum_is_category;
				}
			}
		}
		else
		{
			$mybb->input['copy_1_forum'] = '';
		}
		
		if($mybb->input['approveposts'] != '' && $mybb->input['approveposts'] != 'approve' && $mybb->input['approveposts'] != 'unapprove' && $mybb->input['approveposts'] != 'toggle')
		{
			$mybb->input['approveposts'] = '';
		}
		
		if($mybb->input['splitposts'] < -2)
		{
			$mybb->input['splitposts'] = -1;
		}
		
		if($mybb->input['splitpostsclose'] == 1)
		{
			$mybb->input['splitpostsclose'] = 'close';
		}
		else
		{
			$mybb->input['splitpostsclose'] = '';
		}
		
		if($mybb->input['splitpostsstick'] == 1)
		{
			$mybb->input['splitpostsstick'] = 'stick';
		}
		else
		{
			$mybb->input['splitpostsstick'] = '';
		}
		
		if($mybb->input['splitpostsunapprove'] == 1)
		{
			$mybb->input['splitpostsunapprove'] = 'unapprove';
		}
		else
		{
			$mybb->input['splitpostsunapprove'] = '';
		}

		if(!$errors)
		{
			$thread_options = array(
				'deletethread' => $mybb->input['deletethread'],
				'approvethread' => $mybb->input['approvethread'],
				'openthread' => $mybb->input['openthread'],
				'movethread' => intval($mybb->input['move_1_forum']),
				'movethreadredirect' => $mybb->input['move_2_redirect'],
				'movethreadredirectexpire' => intval($mybb->input['move_3_redirecttime']),
				'copythread' => intval($mybb->input['copy_1_forum']),
				'newsubject' => $mybb->input['newsubject'],
				'addreply' => $mybb->input['newreply'],
				'replysubject' => $mybb->input['newreplysubject']
			);
			
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
				'splitpostsreplysubject' => $mybb->input['splitpostsreplysubject']
			);
			
			$update_tool['type'] = 'p';
			$update_tool['threadoptions'] = $db->escape_string(serialize($thread_options));
			$update_tool['postoptions'] = $db->escape_string(serialize($post_options));	
			$update_tool['name'] = $db->escape_string($mybb->input['title']);
			$update_tool['description'] = $db->escape_string($mybb->input['description']);
			$update_tool['forums'] = '';
			
			if(is_array($mybb->input['forum_1_forums']))
			{
				foreach($mybb->input['forum_1_forums'] as $fid)
				{
					$checked[] = intval($fid);
				}
				$update_tool['forums'] = implode(',', $checked);
			}
		
			$db->update_query("modtools", $update_tool, "tid = '{$mybb->input['tid']}'");
			
			$plugins->run_hooks("admin_config_mod_tools_edit_post_tool_commit");

			// Log admin action
			log_admin_action($mybb->input['tid'], $mybb->input['title']);
			
			flash_message($lang->success_mod_tool_updated, 'success');
			admin_redirect("index.php?module=config-mod_tools&action=post_tools");
		}
	}
	
	$page->add_breadcrumb_item($lang->edit_post_tool);
	$page->output_header($lang->mod_tools." - ".$lang->edit_post_tool);

	$sub_tabs['edit_post_tool'] = array(
		"title" => $lang->edit_post_tool,
		"description" => $lang->edit_post_tool_desc,
		"link" => "index.php?module=config-mod_tools"
	);

	$page->output_nav_tabs($sub_tabs, 'edit_post_tool');

	$form = new Form("index.php?module=config-mod_tools&amp;action=edit_post_tool", 'post');
	echo $form->generate_hidden_field("tid", $mybb->input['tid']);
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$query = $db->simple_select("modtools", "*", "tid = '{$mybb->input['tid']}'");
		$modtool = $db->fetch_array($query);
		$thread_options = unserialize($modtool['threadoptions']);
		$post_options = unserialize($modtool['postoptions']);
		
		$mybb->input['title'] = $modtool['name'];
		$mybb->input['description'] = $modtool['description'];
		$mybb->input['forum_1_forums'] = explode(",", $modtool['forums']);
		
		if(!$modtool['forums'] || $modtool['forums'] == -1)
		{
			$forum_checked[1] = "checked=\"checked\"";
			$forum_checked[2] = '';
		}
		else
		{
			$forum_checked[1] = '';
			$forum_checked[2] = "checked=\"checked\"";
		}
		
		$mybb->input['approvethread'] = $thread_options['approvethread'];
		$mybb->input['openthread'] = $thread_options['openthread'];
		$mybb->input['move_1_forum'] = $thread_options['movethread'];
		$mybb->input['move_2_redirect'] = $thread_options['movethreadredirect'];
		$mybb->input['move_3_redirecttime'] = $thread_options['movethreadredirectexpire'];
		
		if(!$thread_options['movethread'])
		{
			$move_checked[1] = "checked=\"checked\"";
			$move_checked[2] = '';
		}
		else
		{
			$move_checked[1] = '';
			$move_checked[2] = "checked=\"checked\"";
		}
		
		if(!$thread_options['copythread'])
		{
			$copy_checked[1] = "checked=\"checked\"";
			$copy_checked[2] = '';
		}
		else
		{
			$copy_checked[1] = '';
			$copy_checked[2] = "checked=\"checked\"";
		}
		
		$mybb->input['copy_1_forum'] = $thread_options['copythread'];
		$mybb->input['deletethread'] = $thread_options['deletethread'];
		$mybb->input['newsubject'] = $thread_options['newsubject'];
		$mybb->input['newreply'] = $thread_options['addreply'];
		$mybb->input['newreplysubject'] = $thread_options['replysubject'];
		
		if($post_options['splitposts'] == '-1')
		{
			$do_not_split_checked = ' selected="selected"';
			$split_same_checked = '';
		}
		else if($post_options['splitposts'] == '-2')
		{
			$do_not_split_checked = '';
			$split_same_checked = ' selected="selected"';
		}
		
		$mybb->input['deleteposts'] = $post_options['deleteposts'];
		$mybb->input['mergeposts'] = $post_options['mergeposts'];
		$mybb->input['approveposts'] = $post_options['approveposts'];
		
		if($post_options['splitpostsclose'] == 'close')
		{
			$mybb->input['splitpostsclose'] = '1';
		}
		else
		{	
			$mybb->input['splitpostsclose'] = '0';
		}
		
		if($post_options['splitpostsstick'] == 'stick')
		{
			$mybb->input['splitpostsstick'] = '1';
		}
		else
		{
			$mybb->input['splitpostsstick'] = '0';
		}
		
		if($post_options['splitpostsunapprove'] == 'unapprove')
		{
			$mybb->input['splitpostsunapprove'] = '1';
		}
		else
		{
			$mybb->input['splitpostsunapprove'] = '0';
		}
		
		$mybb->input['splitposts'] = $post_options['splitposts'];
				
		$mybb->input['splitpostsnewsubject'] = $post_options['splitpostsnewsubject'];
		$mybb->input['splitpostsaddreply'] = $post_options['splitpostsaddreply'];
		$mybb->input['splitpostsreplysubject'] = $post_options['splitpostsreplysubject'];		
	}

	$form_container = new FormContainer($lang->general_options);
	$form_container->output_row($lang->name." <em>*</em>", '', $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_description." <em>*</em>", '', $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');


	$actions = "<script type=\"text/javascript\">
    function checkAction(id)
    {
        var checked = '';
        
        $$('.'+id+'s_check').each(function(e)
        {
            if(e.checked == true)
            {
                checked = e.value;
            }
        });
        $$('.'+id+'s').each(function(e)
        {
        	Element.hide(e);
        });
        if($(id+'_'+checked))
        {
            Element.show(id+'_'+checked);
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
	$form_container->output_row($lang->available_in_forums." <em>*</em>", '', $actions);
	$form_container->end();

	$approve_unapprove = array(
		'' => $lang->no_change,
		'approve' => $lang->approve,
		'unapprove' => $lang->unapprove,
		'toggle' => $lang->toggle
	);

	$form_container = new FormContainer($lang->inline_post_moderation);
	$form_container->output_row($lang->delete_posts." <em>*</em>", '', $form->generate_yes_no_radio('deleteposts', $mybb->input['deleteposts']));
	$form_container->output_row($lang->merge_posts." <em>*</em>", $lang->merge_posts_desc, $form->generate_yes_no_radio('mergeposts', $mybb->input['mergeposts']));
	$form_container->output_row($lang->approve_unapprove_posts." <em>*</em>", '', $form->generate_select_box('approveposts', $approve_unapprove, $mybb->input['approveposts'], array('id' => 'approveposts')), 'approveposts');
	$form_container->end();
	
	$selectoptions = "<option value=\"-1\"{$do_not_split_checked}>{$lang->do_not_split}</option>\n";
	$selectoptions .= "<option value=\"-2\"{$split_same_checked} style=\"border-bottom: 1px solid #000;\">{$lang->split_to_same_forum}</option>\n";
	
	$form_container = new FormContainer($lang->split_posts);
	$form_container->output_row($lang->split_posts2." <em>*</em>", '', $form->generate_forum_select('splitposts', $mybb->input['splitposts']));
	$form_container->output_row($lang->close_split_thread." <em>*</em>", '', $form->generate_yes_no_radio('splitpostsclose', $mybb->input['splitpostsclose']));
	$form_container->output_row($lang->stick_split_thread." <em>*</em>", '', $form->generate_yes_no_radio('splitpostsstick', $mybb->input['splitpostsstick']));
	$form_container->output_row($lang->unapprove_split_thread." <em>*</em>", '', $form->generate_yes_no_radio('splitpostsunapprove', $mybb->input['splitpostsunapprove']));
	$form_container->output_row($lang->split_thread_subject, $lang->split_thread_subject_desc, $form->generate_text_box('splitpostsnewsubject', $mybb->input['splitpostsnewsubject'], array('id' => 'splitpostsnewsubject ')), 'newreplysubject');
	$form_container->output_row($lang->add_new_split_reply, $lang->add_new_split_reply_desc, $form->generate_text_area('splitpostsaddreply', $mybb->input['splitpostsaddreply'], array('id' => 'splitpostsaddreply')), 'splitpostsaddreply');
	$form_container->output_row($lang->split_reply_subject, $lang->split_reply_subject_desc, $form->generate_text_box('splitpostsreplysubject', $mybb->input['splitpostsreplysubject'], array('id' => 'splitpostsreplysubject')), 'splitpostsreplysubject');
	$form_container->end();
	
	$open_close = array(
		'' => $lang->no_change,
		'open' => $lang->open,
		'close' => $lang->close,
		'toggle' => $lang->toggle
	);
	
	$form_container = new FormContainer($lang->thread_moderation);
	$form_container->output_row($lang->approve_unapprove." <em>*</em>", '', $form->generate_select_box('approvethread', $approve_unapprove, $mybb->input['approvethread'], array('id' => 'approvethread')), 'approvethread');
	$form_container->output_row($lang->open_close_thread." <em>*</em>", '', $form->generate_select_box('openthread', $open_close, $mybb->input['openthread'], array('id' => 'openthread')), 'openthread');


	$actions = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"move_type\" value=\"1\" {$move_checked[1]} class=\"moves_check\" onclick=\"checkAction('move');\" style=\"vertical-align: middle;\" /> <strong>{$lang->do_not_move_thread}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"move_type\" value=\"2\" {$move_checked[2]} class=\"moves_check\" onclick=\"checkAction('move');\" style=\"vertical-align: middle;\" /> <strong>{$lang->move_thread}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"move_2\" class=\"moves\">
			<table cellpadding=\"4\">
				<tr>
					<td><small>{$lang->forum_to_move_to}</small></td>
					<td>".$form->generate_forum_select('move_1_forum', $mybb->input['move_1_forum'])."</td>
				</tr>
				<tr>
					<td><small>{$lang->leave_redirect}</small></td>
					<td>".$form->generate_yes_no_radio('move_2_redirect', $mybb->input['move_2_redirect'])."</td>
				</tr>
				<tr>
					<td><small>{$lang->delete_redirect_after}</small></td>
					<td>".$form->generate_text_box('move_3_redirecttime', $mybb->input['move_3_redirecttime'], array('style' => 'width: 2em;'))." {$lang->days}</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
	checkAction('move');
	</script>";
	$form_container->output_row($lang->move_thread." <em>*</em>", $lang->move_thread_desc, $actions);
	
	$actions = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"copy_type\" value=\"1\" {$copy_checked[1]} class=\"copys_check\" onclick=\"checkAction('copy');\" style=\"vertical-align: middle;\" /> <strong>{$lang->do_not_copy_thread}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"copy_type\" value=\"2\" {$copy_checked[2]} class=\"copys_check\" onclick=\"checkAction('copy');\" style=\"vertical-align: middle;\" /> <strong>{$lang->copy_thread}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"copy_2\" class=\"copys\">
			<table cellpadding=\"4\">
				<tr>
					<td><small>{$lang->forum_to_copy_to}</small></td>
					<td>".$form->generate_forum_select('copy_1_forum', $mybb->input['copy_1_forum'])."</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
	checkAction('copy');
	</script>";
	$form_container->output_row($lang->copy_thread." <em>*</em>", '', $actions);
	$form_container->output_row($lang->delete_thread." <em>*</em>", '', $form->generate_yes_no_radio('deletethread', $mybb->input['deletethread']));
	$form_container->output_row($lang->new_subject." <em>*</em>", $lang->new_subject_desc, $form->generate_text_box('newsubject', $mybb->input['newsubject']));
	$form_container->end();
	
	$form_container = new FormContainer($lang->add_new_reply);
	$form_container->output_row($lang->add_new_reply, $lang->add_new_reply_desc, $form->generate_text_area('newreply', $mybb->input['newreply']), 'newreply');
	$form_container->output_row($lang->reply_subject, $lang->reply_subject_desc, $form->generate_text_box('newreplysubject', $mybb->input['newreplysubject'], array('id' => 'newreplysubject')), 'newreplysubject');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_post_tool);

	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "add_post_tool")
{
	$plugins->run_hooks("admin_config_mod_tools_add_post_tool");
	
	if($mybb->request_method == 'post')
	{
		if(trim($mybb->input['title']) == "")
		{
			$errors[] = $lang->error_missing_title;
		}
		
		if(trim($mybb->input['description']) == "")
		{
			$errors[] = $lang->error_missing_description;
		}
		
		if($mybb->input['forum_type'] == 2)
		{
			$forum_checked[1] = '';
			$forum_checked[2] = "checked=\"checked\"";

			if(count($mybb->input['forum_1_forums']) < 1)
			{
				$errors[] = $lang->error_no_forums_selected;
			}
		}
		else
		{
			$forum_checked[1] = "checked=\"checked\"";
			$forum_checked[2] = '';

			$mybb->input['forum_1_forums'] = '';
		}
		
		
		if($mybb->input['approvethread'] != '' && $mybb->input['approvethread'] != 'approve' && $mybb->input['approvethread'] != 'unapprove' && $mybb->input['approvethread'] != 'toggle')
		{
			$mybb->input['approvethread'] = '';
		}
		
		if($mybb->input['openthread'] != '' && $mybb->input['openthread'] != 'open' && $mybb->input['openthread'] != 'close' && $mybb->input['openthread'] != 'toggle')
		{
			$mybb->input['openthread'] = '';
		}
		
		if($mybb->input['move_type'] == 2)
		{
			$move_checked[1] = '';
			$move_checked[2] = "checked=\"checked\"";

			if(!$mybb->input['move_1_forum'])
			{
				$errors[] = $lang->error_no_move_forum_selected;
			}
			else
			{
				// Check that the destination forum is not a category
				$query = $db->simple_select("forums", "type", "fid = '".intval($mybb->input['move_1_forum'])."'");
				if($db->fetch_field($query, "type") == "c")
				{
					$errors[] = $lang->error_forum_is_category;
				}
			}
		}
		else
		{
			$move_checked[1] = "checked=\"checked\"";
			$move_checked[2] = '';

			$mybb->input['move_1_forum'] = '';
			$mybb->input['move_2_redirect'] = 0;
			$mybb->input['move_3_redirecttime'] = '';
		}
		
		if($mybb->input['copy_type'] == 2)
		{
			$copy_checked[1] = '';
			$copy_checked[2] = "checked=\"checked\"";
			
			if(!$mybb->input['copy_1_forum'])
			{
				$errors[] = $lang->error_no_copy_forum_selected;
			}
			else
			{
				$query = $db->simple_select("forums", "type", "fid = '".intval($mybb->input['copy_1_forum'])."'");
				if($db->fetch_field($query, "type") == "c")
				{
					$errors[] = $lang->error_forum_is_category;
				}
			}
		}
		else
		{
			$copy_checked[1] = 'checked=\"checked\"';
			$copy_checked[2] = '';

			$mybb->input['copy_1_forum'] = '';
		}
		
		if($mybb->input['approveposts'] != '' && $mybb->input['approveposts'] != 'approve' && $mybb->input['approveposts'] != 'unapprove' && $mybb->input['approveposts'] != 'toggle')
		{
			$mybb->input['approveposts'] = '';
		}
		
		if($mybb->input['splitposts'] < -2)
		{
			$mybb->input['splitposts'] = -1;
		}
		
		if($mybb->input['splitpostsclose'] == 1)
		{
			$mybb->input['splitpostsclose'] = 'close';
		}
		else
		{
			$mybb->input['splitpostsclose'] = '';
		}
		
		if($mybb->input['splitpostsstick'] == 1)
		{
			$mybb->input['splitpostsstick'] = 'stick';
		}
		else
		{
			$mybb->input['splitpostsstick'] = '';
		}
		
		if($mybb->input['splitpostsunapprove'] == 1)
		{
			$mybb->input['splitpostsunapprove'] = 'unapprove';
		}
		else
		{
			$mybb->input['splitpostsunapprove'] = '';
		}
		
		if(!$errors)
		{
			$thread_options = array(
				'deletethread' => $mybb->input['deletethread'],
				'approvethread' => $mybb->input['approvethread'],
				'openthread' => $mybb->input['openthread'],
				'movethread' => intval($mybb->input['move_1_forum']),
				'movethreadredirect' => $mybb->input['move_2_redirect'],
				'movethreadredirectexpire' => intval($mybb->input['move_3_redirecttime']),
				'copythread' => intval($mybb->input['copy_1_forum']),
				'newsubject' => $mybb->input['newsubject'],
				'addreply' => $mybb->input['newreply'],
				'replysubject' => $mybb->input['newreplysubject']
			);
			
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
				'splitpostsreplysubject' => $mybb->input['splitpostsreplysubject']
			);
			
			$new_tool['type'] = 'p';
			$new_tool['threadoptions'] = $db->escape_string(serialize($thread_options));
			$new_tool['postoptions'] = $db->escape_string(serialize($post_options));	
			$new_tool['name'] = $db->escape_string($mybb->input['title']);
			$new_tool['description'] = $db->escape_string($mybb->input['description']);
			$new_tool['forums'] = '';
			
			if(is_array($mybb->input['forum_1_forums']))
			{
				foreach($mybb->input['forum_1_forums'] as $fid)
				{
					$checked[] = intval($fid);
				}
				$new_tool['forums'] = implode(',', $checked);
			}
		
			$tid = $db->insert_query("modtools", $new_tool);
			
			$plugins->run_hooks("admin_config_mod_tools_add_post_tool_commit");

			// Log admin action
			log_admin_action($tid, $mybb->input['title']);
			
			flash_message($lang->success_mod_tool_created, 'success');
			admin_redirect("index.php?module=config-mod_tools&action=post_tools");
		}
	}
	
	$page->add_breadcrumb_item($lang->add_new_post_tool);
	$page->output_header($lang->mod_tools." - ".$lang->add_new_post_tool);
	
	$sub_tabs['thread_tools'] = array(
		'title' => $lang->thread_tools,
		'link' => "index.php?module=config-mod_tools"
	);
	$sub_tabs['add_thread_tool'] = array(
		'title'=> $lang->add_new_thread_tool,
		'link' => "index.php?module=config-mod_tools&amp;action=add_thread_tool"
	);
	$sub_tabs['post_tools'] = array(
		'title' => $lang->post_tools,
		'link' => "index.php?module=config-mod_tools&amp;action=post_tools",
	);
	$sub_tabs['add_post_tool'] = array(
		'title'=> $lang->add_new_post_tool,
		'link' => "index.php?module=config-mod_tools&amp;action=add_post_tool",
		'description' => $lang->add_post_tool_desc
	);
		
	$page->output_nav_tabs($sub_tabs, 'add_post_tool');
	
	$form = new Form("index.php?module=config-mod_tools&amp;action=add_post_tool", 'post');
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['title'] = '';
		$mybb->input['description'] = '';
		$mybb->input['forum_1_forums'] = '';
		$forum_checked[1] = "checked=\"checked\"";
		$forum_checked[2] = '';
		$mybb->input['approvethread'] = '';
		$mybb->input['openthread'] = '';
		$mybb->input['move_1_forum'] = '';
		$mybb->input['move_2_redirect'] = '0';
		$mybb->input['move_3_redirecttime'] = '';
		$move_checked[1] = "checked=\"checked\"";
		$move_checked[2] = '';
		$copy_checked[1] = "checked=\"checked\"";
		$copy_checked[2] = '';
		$mybb->input['copy_1_forum'] = '';
		$mybb->input['deletethread'] = '0';
		$mybb->input['newsubject'] = '{subject}';
		$mybb->input['newreply'] = '';
		$mybb->input['newreplysubject'] = '{subject}';
		$do_not_split_checked = ' selected="selected"';
		$split_same_checked = '';
		$mybb->input['deleteposts'] = '0';
		$mybb->input['mergeposts'] = '0';
		$mybb->input['approveposts'] = '';
		$mybb->input['splitposts'] = '-1';
		$mybb->input['splitpostsclose'] = '0';
		$mybb->input['splitpostsstick'] = '0';
		$mybb->input['splitpostsunapprove'] = '0';
		$mybb->input['splitpostsnewsubject'] = '{subject}';
		$mybb->input['splitpostsaddreply'] = '';
		$mybb->input['splitpostsreplysubject'] = '{subject}';		
	}

	$form_container = new FormContainer($lang->general_options);
	$form_container->output_row($lang->name." <em>*</em>", '', $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_description." <em>*</em>", '', $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');


	$actions = "<script type=\"text/javascript\">
    function checkAction(id)
    {
        var checked = '';
        
        $$('.'+id+'s_check').each(function(e)
        {
            if(e.checked == true)
            {
                checked = e.value;
            }
        });
        $$('.'+id+'s').each(function(e)
        {
        	Element.hide(e);
        });
        if($(id+'_'+checked))
        {
            Element.show(id+'_'+checked);
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
	$form_container->output_row($lang->available_in_forums." <em>*</em>", '', $actions);
	$form_container->end();

	$approve_unapprove = array(
		'' => $lang->no_change,
		'approve' => $lang->approve,
		'unapprove' => $lang->unapprove,
		'toggle' => $lang->toggle
	);

	$form_container = new FormContainer($lang->inline_post_moderation);
	$form_container->output_row($lang->delete_posts." <em>*</em>", '', $form->generate_yes_no_radio('deleteposts', $mybb->input['deleteposts']));
	$form_container->output_row($lang->merge_posts." <em>*</em>", $lang->merge_posts_desc, $form->generate_yes_no_radio('mergeposts', $mybb->input['mergeposts']));
	$form_container->output_row($lang->approve_unapprove_posts." <em>*</em>", '', $form->generate_select_box('approveposts', $approve_unapprove, $mybb->input['approveposts'], array('id' => 'approveposts')), 'approveposts');
	$form_container->end();
	
	$selectoptions = "<option value=\"-1\"{$do_not_split_checked}>{$lang->do_not_split}</option>\n";
	$selectoptions .= "<option value=\"-2\"{$split_same_checked} style=\"border-bottom: 1px solid #000;\">{$lang->split_to_same_forum}</option>\n";
	
	$form_container = new FormContainer($lang->split_posts);
	$form_container->output_row($lang->split_posts2." <em>*</em>", '', $form->generate_forum_select('splitposts', $mybb->input['splitposts']));
	$form_container->output_row($lang->close_split_thread." <em>*</em>", '', $form->generate_yes_no_radio('splitpostsclose', $mybb->input['splitpostsclose']));
	$form_container->output_row($lang->stick_split_thread." <em>*</em>", '', $form->generate_yes_no_radio('splitpostsstick', $mybb->input['splitpostsstick']));
	$form_container->output_row($lang->unapprove_split_thread." <em>*</em>", '', $form->generate_yes_no_radio('splitpostsunapprove', $mybb->input['splitpostsunapprove']));
	$form_container->output_row($lang->split_thread_subject, $lang->split_thread_subject_desc, $form->generate_text_box('splitpostsnewsubject', $mybb->input['splitpostsnewsubject'], array('id' => 'splitpostsnewsubject ')), 'newreplysubject');
	$form_container->output_row($lang->add_new_split_reply, $lang->add_new_split_reply_desc, $form->generate_text_area('splitpostsaddreply', $mybb->input['splitpostsaddreply'], array('id' => 'splitpostsaddreply')), 'splitpostsaddreply');
	$form_container->output_row($lang->split_reply_subject, $lang->split_reply_subject_desc, $form->generate_text_box('splitpostsreplysubject', $mybb->input['splitpostsreplysubject'], array('id' => 'splitpostsreplysubject')), 'splitpostsreplysubject');
	$form_container->end();
	
	$open_close = array(
		'' => $lang->no_change,
		'open' => $lang->open,
		'close' => $lang->close,
		'toggle' => $lang->toggle
	);
	
	$form_container = new FormContainer($lang->thread_moderation);
	$form_container->output_row($lang->approve_unapprove." <em>*</em>", '', $form->generate_select_box('approvethread', $approve_unapprove, $mybb->input['approvethread'], array('id' => 'approvethread')), 'approvethread');
	$form_container->output_row($lang->open_close_thread." <em>*</em>", '', $form->generate_select_box('openthread', $open_close, $mybb->input['openthread'], array('id' => 'openthread')), 'openthread');


	$actions = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"move_type\" value=\"1\" {$move_checked[1]} class=\"moves_check\" onclick=\"checkAction('move');\" style=\"vertical-align: middle;\" /> <strong>{$lang->do_not_move_thread}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"move_type\" value=\"2\" {$move_checked[2]} class=\"moves_check\" onclick=\"checkAction('move');\" style=\"vertical-align: middle;\" /> <strong>{$lang->move_thread}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"move_2\" class=\"moves\">
			<table cellpadding=\"4\">
				<tr>
					<td><small>{$lang->forum_to_move_to}</small></td>
					<td>".$form->generate_forum_select('move_1_forum', $mybb->input['move_1_forum'])."</td>
				</tr>
				<tr>
					<td><small>{$lang->leave_redirect}</small></td>
					<td>".$form->generate_yes_no_radio('move_2_redirect', $mybb->input['move_2_redirect'])."</td>
				</tr>
				<tr>
					<td><small>{$lang->delete_redirect_after}</small></td>
					<td>".$form->generate_text_box('move_3_redirecttime', $mybb->input['move_3_redirecttime'], array('style' => 'width: 2em;'))." {$lang->days}</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
	checkAction('move');
	</script>";
	$form_container->output_row($lang->move_thread." <em>*</em>", $lang->move_thread_desc, $actions);
	
	$actions = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"copy_type\" value=\"1\" {$copy_checked[1]} class=\"copys_check\" onclick=\"checkAction('copy');\" style=\"vertical-align: middle;\" /> <strong>{$lang->do_not_copy_thread}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"copy_type\" value=\"2\" {$copy_checked[2]} class=\"copys_check\" onclick=\"checkAction('copy');\" style=\"vertical-align: middle;\" /> <strong>{$lang->copy_thread}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"copy_2\" class=\"copys\">
			<table cellpadding=\"4\">
				<tr>
					<td><small>{$lang->forum_to_copy_to}</small></td>
					<td>".$form->generate_forum_select('copy_1_forum', $mybb->input['copy_1_forum'])."</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
	checkAction('copy');
	</script>";
	$form_container->output_row($lang->copy_thread." <em>*</em>", '', $actions);
	$form_container->output_row($lang->delete_thread." <em>*</em>", '', $form->generate_yes_no_radio('deletethread', $mybb->input['deletethread']));
	$form_container->output_row($lang->new_subject." <em>*</em>", $lang->new_subject_desc, $form->generate_text_box('newsubject', $mybb->input['newsubject']));
	$form_container->end();
	
	$form_container = new FormContainer($lang->add_new_reply);
	$form_container->output_row($lang->add_new_reply, $lang->add_new_reply_desc, $form->generate_text_area('newreply', $mybb->input['newreply'], array('id' => 'newreply')), 'newreply');
	$form_container->output_row($lang->reply_subject, $lang->reply_subject_desc, $form->generate_text_box('newreplysubject', $mybb->input['newreplysubject'], array('id' => 'newreplysubject')), 'newreplysubject');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_post_tool);

	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_config_mod_tools_start");
	
	$page->output_header($lang->mod_tools." - ".$lang->thread_tools);
	
	$sub_tabs['thread_tools'] = array(
		'title' => $lang->thread_tools,
		'link' => "index.php?module=config-mod_tools",
		'description' => $lang->thread_tools_desc
	);
	$sub_tabs['add_thread_tool'] = array(
		'title'=> $lang->add_new_thread_tool,
		'link' => "index.php?module=config-mod_tools&amp;action=add_thread_tool"
	);
	$sub_tabs['post_tools'] = array(
		'title' => $lang->post_tools,
		'link' => "index.php?module=config-mod_tools&amp;action=post_tools",
	);
	$sub_tabs['add_post_tool'] = array(
		'title'=> $lang->add_new_post_tool,
		'link' => "index.php?module=config-mod_tools&amp;action=add_post_tool"
	);
		
	$page->output_nav_tabs($sub_tabs, 'thread_tools');
	
	$table = new Table;
	$table->construct_header($lang->title);
	$table->construct_header($lang->controls, array('class' => "align_center", 'colspan' => 2));
	
	$query = $db->simple_select('modtools', 'tid, name, description, type', "type='t'", array('order_by' => 'name'));
	while($tool = $db->fetch_array($query))
	{
		$table->construct_cell("<a href=\"index.php?module=config-mod_tools&amp;action=edit_thread_tool&amp;tid={$tool['tid']}\"><strong>".htmlspecialchars_uni($tool['name'])."</strong></a><br /><small>".htmlspecialchars_uni($tool['description'])."</small>");
		$table->construct_cell("<a href=\"index.php?module=config-mod_tools&amp;action=edit_thread_tool&amp;tid={$tool['tid']}\">{$lang->edit}</a>", array('width' => 100, 'class' => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=config-mod_tools&amp;action=delete_thread_tool&amp;tid={$tool['tid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_thread_tool_deletion}')\">{$lang->delete}</a>", array('width' => 100, 'class' => "align_center"));
		$table->construct_row();
	}
	
	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_thread_tools, array('colspan' => 3));
		$table->construct_row();
	}
	
	$table->output($lang->thread_tools);
	
	$page->output_footer();
}

?>