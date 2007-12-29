<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: index.php 2992 2007-04-05 14:43:48Z chris $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->template_sets, "index.php?".SID."&amp;module=style/templates");

if($mybb->input['action'] == "add_set" || $mybb->input['action'] == "add_template" || $mybb->input['action'] == "search_replace" || $mybb->input['action'] == "find_updated" || (!$mybb->input['action'] && !$mybb->input['sid']))
{
	$sub_tabs['templates'] = array(
		'title' => $lang->manage_template_sets,
		'link' => "index.php?".SID."&amp;module=style/templates",
		'description' => $lang->manage_template_sets_desc
	);

	$sub_tabs['add_set'] = array(
		'title' => $lang->add_set,
		'link' => "index.php?".SID."&amp;module=style/templates&amp;action=add_set"
	);

	$sub_tabs['add_template'] = array(
		'title' => $lang->add_template,
		'link' => "index.php?".SID."&amp;module=style/templates&amp;action=add_template"
	);
	
	$sub_tabs['search_replace'] = array(
		'title' => $lang->search_replace,
		'link' => "index.php?".SID."&amp;module=style/templates&amp;action=search_replace"
	);
	
	$sub_tabs['find_updated'] = array(
		'title' => $lang->find_updated,
		'link' => "index.php?".SID."&amp;module=style/templates&amp;action=find_updated"
	);
}
else if(($mybb->input['sid'] && !$mybb->input['action']) || $mybb->input['action'] == "edit_set" || $mybb->input['action'] == "edit_template")
{
	$sub_tabs['manage_templates'] = array(
		'title' => $lang->manage_templates,
		'link' => "index.php?".SID."&amp;module=style/templates&amp;sid=".intval($mybb->input['sid']),
		'description' => $lang->manage_templates_desc
	);

	$sub_tabs['edit_set'] = array(
		'title' => $lang->edit_set,
		'link' => "index.php?".SID."&amp;module=style/templates&amp;action=edit_set&amp;sid=".intval($mybb->input['sid']),
		'description' => $lang->edit_set_desc
	);

	$sub_tabs['add_template'] = array(
		'title' => $lang->add_template,
		'link' => "index.php?".SID."&amp;module=style/templates&amp;action=add_template&amp;sid=".intval($mybb->input['sid']),
		'description' => $lang->add_template_desc
	);
}

if($mybb->input['action'] == "add_set")
{
	if($mybb->request_method == "post")
	{
	}
	
	$page->output_header($lang->add_set);
	
	$page->output_nav_tabs($sub_tabs, 'add_set');

	$page->output_footer();
}

if($mybb->input['action'] == "add_template")
{	
	if($mybb->request_method == "post")
	{
		if(empty($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}
		else
		{	
			$query = $db->simple_select("templates", "COUNT(tid) as count", "title='".$db->escape_string($mybb->input['title'])."' AND (sid = '-2' OR sid = '".intval($mybb->input['sid'])."')");
			if($db->fetch_field($query, "count") > 0)
			{
				$errors[] = $lang->error_already_exists;
			}
		}
		
		if(!$errors)
		{
			$template_array = array(
				'title' => $db->escape_string($mybb->input['title']),
				'sid' => intval($mybb->input['sid']),
				'template' => $db->escape_string($mybb->input['template']),
			);
						
			$tid = $db->insert_query("templates", $template_array);
			
			flash_message($lang->success_template_saved, 'success');
			
			//if()
			//{
				//admin_redirect("index.php?".SID."&module=style/templates&action=edit_template&tid={$tid}&sid=".intval($mybb->input['sid']);
			//}
			//else
			{
				admin_redirect("index.php?".SID."&module=style/templates&sid=".intval($mybb->input['sid']));
			}
		}
	}
	
	$template_sets[-1] = $lang->global_templates;

	$query = $db->simple_select("templatesets", "*", "", array('order_by' => 'title', 'order_dir' => 'ASC'));
	while($template_set = $db->fetch_array($query))
	{
		$template_sets[$template_set['sid']] = $template_set['title'];
	}
	
	$sid = intval($mybb->input['sid']);
	
	if($sid)
	{
		$page->add_breadcrumb_item($template_sets[$sid], "index.php?".SID."&amp;module=style/templates&amp;sid={$sid}");
	}
	
	if($errors)
	{
		$page->output_inline_error($errors);
		$template = $mybb->input;
	}
	else
	{
		if(!$sid)
		{
			$sid = -1;
		}
		
		$template['title'] = "";
		$template['template'] = "";
		$template['sid'] = $sid;
	}
	
	$page->extra_header .= '
	<link type="text/css" href="./jscripts/codepress/languages/codepress-php.css" rel="stylesheet" id="cp-lang-style" />
	<script type="text/javascript" src="./jscripts/codepress/codepress.js"></script>
	<script type="text/javascript">
		CodePress.language = \'php\';
	</script>';
	
	$page->add_breadcrumb_item($lang->add_template);
	
	$page->output_header($lang->edit_template);
	
	$sub_tabs = array();
	$sub_tabs['add_template'] = array(
		'title' => $lang->add_template,
		'link' => "index.php?".SID."&amp;module=style/templates&amp;action=add_template&amp;sid=".$template['sid'],
		'description' => $lang->add_template_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'add_template');
	
	$form = new Form("index.php?".SID."&amp;module=style/templates&amp;action=add_template", "post", "add_template");
	
	$form_container = new FormContainer($lang->add_template);
	$form_container->output_row($lang->template_name, $lang->template_name_desc, $form->generate_text_box('title', $template['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->template_set, $lang->template_set_desc, $form->generate_select_box('sid', $template_sets, $sid), 'sid');
	$form_container->output_row("", "", $form->generate_text_area('template', $template['template'], array('id' => 'template', 'class' => 'codepress php', 'style' => 'width: 100%; height: 500px;')), 'template');
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->save_continue);
	$buttons[] = $form->generate_submit_button($lang->save_close);

	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	echo "<script language=\"Javascript\" type=\"text/javascript\">
	Event.observe('add_template', 'submit', function()
	{
		if($('template_cp')) {
			var area = $('template_cp');
			area.id = 'template';
			area.value = template.getCode();
			area.disabled = false;
		}
	});
</script>";

	$page->output_footer();
}

if($mybb->input['action'] == "edit_set")
{
	if($mybb->request_method == "post")
	{
	}
	
	$page->output_header($lang->add_set);
	
	$page->output_nav_tabs($sub_tabs, 'add_set');

	$page->output_footer();
}

if($mybb->input['action'] == "edit_template")
{
	if(!$mybb->input['tid'] || !$mybb->input['sid'])
	{
		flash_message($lang->error_missing_input, 'error');
		admin_redirect("index.php?".SID."&module=style/templates");
	}
	
	if($mybb->request_method == "post")
	{
		if(empty($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}
		
		if(!$errors)
		{
			$template_array = array(
				'title' => $db->escape_string($mybb->input['title']),
				'sid' => intval($mybb->input['sid']),
				'template' => $db->escape_string($mybb->input['template']),
				'tid' => intval($mybb->input['tid'])
			);
				
			if($mybb->input['sid'] > 0)
			{
				$query = $db->simple_select("templates", "COUNT(tid) as count", "title='".$db->escape_string($mybb->input['title'])."' AND (sid = '-2' OR sid = '".intval($mybb->input['sid'])."')");
				if($db->fetch_field($query, "count") == 1)
				{
					unset($template_array['tid']);
					$db->insert_query("templates", $template_array);
				}
			}
			else
			{
				$db->replace_query("templates", $template_array);
			}
			
			flash_message($lang->success_template_saved, 'success');
			
			//if()
			//{
				//admin_redirect("index.php?".SID."&module=style/templates&action=edit_template&tid={$tid}&sid=".intval($mybb->input['sid']);
			//}
			//else
			{
				admin_redirect("index.php?".SID."&module=style/templates&sid=".intval($mybb->input['sid']));
			}
		}
	}
	
	$template_sets[-1] = $lang->global_templates;

	$query = $db->simple_select("templatesets", "*", "", array('order_by' => 'title', 'order_dir' => 'ASC'));
	while($template_set = $db->fetch_array($query))
	{
		$template_sets[$template_set['sid']] = $template_set['title'];
	}
	
	if($errors)
	{
		$page->output_inline_error($errors);
		$template = $mybb->input;
	}
	else
	{
		$query = $db->simple_select("templates", "*", "tid='".intval($mybb->input['tid'])."' AND (sid='-2' OR sid='".intval($mybb->input['sid'])."')", array('order_by' => 'sid', 'order_dir' => 'DESC'));
		$template = $db->fetch_array($query);
	}
	
	$sid = intval($mybb->input['sid']);
	
	$page->extra_header .= '
	<link type="text/css" href="./jscripts/codepress/languages/codepress-php.css" rel="stylesheet" id="cp-lang-style" />
	<script type="text/javascript" src="./jscripts/codepress/codepress.js"></script>
	<script type="text/javascript">
		CodePress.language = \'php\';
	</script>';
	
	$page->add_breadcrumb_item($template_sets[$sid], "index.php?".SID."&amp;module=style/templates&amp;sid={$sid}");
	
	$page->add_breadcrumb_item($lang->edit_template_breadcrumb.$template['title'], "index.php?".SID."&amp;module=style/templates&amp;sid={$sid}");
	
	$page->output_header($lang->edit_template);
	
	$sub_tabs = array();
	$sub_tabs['edit_template'] = array(
		'title' => $lang->edit_template,
		'link' => "index.php?".SID."&amp;module=style/templates&amp;action=edit_template&amp;tid=".$mybb->input['tid'],
		'description' => $lang->edit_template_desc
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_template');
	
	$form = new Form("index.php?".SID."&amp;module=style/templates&amp;action=edit_template", "post", "edit_template");
	echo $form->generate_hidden_field('tid', $template['tid']);
	
	$form_container = new FormContainer($lang->edit_template_breadcrumb.$template['title']);
	$form_container->output_row($lang->template_name, $lang->template_name_desc, $form->generate_text_box('title', $template['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->template_set, $lang->template_set_desc, $form->generate_select_box('sid', $template_sets, $sid), 'sid');
	$form_container->output_row("", "", $form->generate_text_area('template', $template['template'], array('id' => 'template', 'class' => 'codepress php', 'style' => 'width: 100%; height: 500px;')), 'template');
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button($lang->save_continue);
	$buttons[] = $form->generate_submit_button($lang->save_close);

	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	echo "<script language=\"Javascript\" type=\"text/javascript\">
	Event.observe('edit_template', 'submit', function()
	{
		if($('template_cp')) {
			var area = $('template_cp');
			area.id = 'template';
			area.value = template.getCode();
			area.disabled = false;
		}
	});
</script>";

	$page->output_footer();
}

if($mybb->input['action'] == "search_replace")
{
	if($mybb->request_method == "post")
	{
	}
	
	$page->output_header($lang->search_replace);
	
	$page->output_nav_tabs($sub_tabs, 'search_replace');

	$page->output_footer();
}

if($mybb->input['action'] == "find_updated")
{
	if($mybb->request_method == "post")
	{
	}
	
	$page->output_header($lang->find_updated);
	
	$page->output_nav_tabs($sub_tabs, 'find_updated');

	$page->output_footer();
}

if($mybb->input['action'] == "delete_set")
{
}

if($mybb->input['action'] == "delete_template")
{
}

if($mybb->input['action'] == "diff_report")
{
}

if($mybb->input['action'] == "revert")
{
}

if($mybb->input['sid'] && !$mybb->input['action'])
{	
	$template_sets[-1] = $lang->global_templates;

	$query = $db->simple_select("templatesets", "*", "", array('order_by' => 'title', 'order_dir' => 'ASC'));
	while($template_set = $db->fetch_array($query))
	{
		$template_sets[$template_set['sid']] = $template_set['title'];
	}
	
	$sid = intval($mybb->input['sid']);

	$page->add_breadcrumb_item($template_sets[$sid], "index.php?".SID."&amp;module=style/templates&amp;sid={$sid}");

	$page->output_header($lang->template_sets);
	
	$page->output_nav_tabs($sub_tabs, 'manage_templates');
	
	$table = new Table;
	$table->construct_header($lang->template_set);
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));
	
	// Global Templates
	if($mybb->input['sid'] == -1)
	{
		$query = $db->simple_select("templates", "tid,title", "sid='-1'", array('order_by' => 'title', 'order_dir' => 'ASC'));
		while($template = $db->fetch_array($query))
		{
			$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
			$popup->add_item($lang->inline_edit, "javascript:;");
			$popup->add_item($lang->full_edit, "index.php?".SID."&amp;module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid=-1");
			$popup->add_item($lang->delete_template, "index.php?".SID."&amp;module=style/templates&amp;delete_template&amp;sid=-1", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_deletion}')");
				
			$table->construct_cell("<a href=\"index.php?".SID."&amp;module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid=-1\" onclick=\"return false;\">{$template['title']}</a>");
			$table->construct_cell($popup->fetch(), array("class" => "align_center"));
			
			$table->construct_row();
		}
		
		if($table->num_rows() == 0)
		{
			$table->construct_cell($lang->no_global_templates, array('colspan' => 2));
			$table->construct_row();
		}
		
		$table->output($template_sets[$sid]);
	
		$page->output_footer();
	}
	
	// Fetch Groups
	$query = $db->simple_select("templategroups", "*", "", array('order_by' => 'title', 'order_dir' => 'ASC'));
	while($templategroup = $db->fetch_array($query))
	{
		$template_groups[$templategroup['prefix']] = $templategroup;
	}
	
	$templates_list = array();
	$unordered_list = array();
	$master_list = array();
	$true_custom = array();
	$custom_list = array();
	$done = array();
	
	// Fetch Templates
	$query = $db->simple_select("templates", "*", "sid='".intval($mybb->input['sid'])."' OR sid='-2'", array('order_by' => 'sid DESC, title', 'order_dir' => 'ASC'));
	while($template = $db->fetch_array($query))
	{
		if($template['sid'] == -2)
		{
			$master_list[$template['title']] = $template['template'];
		}
	
		if($template['sid'] == -2 && array_key_exists($template['title'], $done))
		{
			continue;
		}
		
		$exploded = explode("_", $template['title'], 2);
		
		if(array_key_exists($exploded[0], $template_groups))
		{
			$templates_list[$exploded[0]][] = $template;
		}
		else
		{
			$unordered_list[] = $template;
		}
		
		$done[$template['title']] = array('tid' => $template['tid'], 'template' => $template['template']);
	}
	
	// Find truely custom templates
	foreach($done as $title => $template)
	{
		if(!$master_list[$title])
		{
			$true_custom[$template['tid']] = 1;
		}
		else if(!array_search($template['template'], $master_list))
		{
			$custom_list[$template['tid']] = 1;
		}
	}
	
	$expand_str = "";
	$expand_array = array();
	if($mybb->input['expand'])
	{
		if(strstr($mybb->input['expand'], "|"))
		{
			$expand_array = explode("|", $mybb->input['expand']);
			array_map("intval", $expand_array);
		}
		else
		{
			$expand_array = array(intval($mybb->input['expand']));
		}
	}
	
	foreach($template_groups as $prefix => $group)
	{	
		$tmp_expand = "";
		if(in_array($group['gid'], $expand_array))
		{
			$expand = $lang->collapse;
			$expanded = true;
			
			$tmp_expand = $expand_array;
			$unsetgid = array_search($group['gid'], $tmp_expand);
			unset($tmp_expand[$unsetgid]);
			$group['expand_str'] = implode("|", $tmp_expand);
		}
		else
		{
			$expand = $lang->expand;
			$expanded = false;
			
			$group['expand_str'] = implode("|", $expand_array);
			if($group['expand_str'])
			{
				$group['expand_str'] .= "|";
			}
			$group['expand_str'] .= $group['gid'];
		}
		
		$groupname = $lang->parse($group['title'])." ".$lang->templates;
				
		$table->construct_cell("<strong><a href=\"index.php?".SID."&amp;module=style/templates&amp;sid={$mybb->input['sid']}&amp;expand={$group['expand_str']}\" onclick=\"\">{$groupname}</a></strong>");
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=style/templates&amp;sid={$mybb->input['sid']}&amp;expand={$group['expand_str']}\" onclick=\"\">{$expand}</a>", array("class" => "align_center"));
		$table->construct_row(array("class" => "alt_row"));
		
		if(!empty($templates_list[$prefix]) && $expanded == true)
		{
			foreach($templates_list[$prefix] as $key => $template)
			{
				$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
				$popup->add_item($lang->inline_edit, "javascript:;");
				$popup->add_item($lang->full_edit, "index.php?".SID."&amp;module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid=".intval($mybb->input['sid']));
				
				if($mybb->input['sid'] > 0 && $custom_list[$template['tid']] == 1)
				{			
					$popup->add_item($lang->diff_report, "index.php?".SID."&amp;module=style/templates&amp;delete_set&amp;sid={$set['sid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_set_deletion}')");
					
					$popup->add_item($lang->revert_to_orig, "index.php?".SID."&amp;module=style/templates&amp;revert&amp;tid={$template['tid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_revertion}')");
				}
				
				if($true_custom[$template['tid']] == 1)
				{
					$template['title'] = "<span style=\"color: green;\"><strong>{$template['title']}</strong></span>";
				}
				else if($custom_list[$template['tid']] == 1)
				{
					$template['title'] = "<span style=\"color: green;\">{$template['title']}</span>";
				}
				
				if($true_custom[$template['tid']])
				{
					$popup->add_item($lang->delete_template, "index.php?".SID."&amp;module=style/templates&amp;delete_template&amp;sid={$set['sid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_deletion}')");
				}
					
				$table->construct_cell("<span style=\"padding: 20px;\"><a href=\"index.php?".SID."&amp;module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid=".intval($mybb->input['sid'])."\" onclick=\"return false;\">{$template['title']}</a></span>");
				$table->construct_cell($popup->fetch(), array("class" => "align_center"));
				
				$table->construct_row();
			}
		}
	}
	
	if(!empty($unordered_list))
	{
		foreach($unordered_list as $key => $template)
		{
			$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
			$popup->add_item($lang->inline_edit, "javascript:;");
			$popup->add_item($lang->full_edit, "index.php?".SID."&amp;module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid=".intval($mybb->input['sid']));
			
			if($mybb->input['sid'] > 0 && $custom_list[$template['tid']] == 1)
			{			
				$popup->add_item($lang->diff_report, "index.php?".SID."&amp;module=style/templates&amp;delete_set&amp;sid={$set['sid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_set_deletion}')");
				
				$popup->add_item($lang->revert_to_orig, "index.php?".SID."&amp;module=style/templates&amp;revert&amp;tid={$template['tid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_revertion}')");
			}
			
			if($true_custom[$template['tid']] == 1)
			{
				$template['title'] = "<span style=\"color: green;\"><strong>{$template['title']}</strong></span>";
			}
			else if($custom_list[$template['tid']] == 1)
			{
				$template['title'] = "<span style=\"color: green;\">{$template['title']}</span>";
			}
			
			if($true_custom[$template['tid']])
			{
				$popup->add_item($lang->delete_template, "index.php?".SID."&amp;module=style/templates&amp;delete_template&amp;sid={$set['sid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_deletion}')");
			}
				
			$table->construct_cell("<a href=\"index.php?".SID."&amp;module=style/templates&amp;action=edit_template&amp;tid={$template['tid']}&amp;sid=".intval($mybb->input['sid'])."\" onclick=\"return false;\">{$template['title']}</a>");
			$table->construct_cell($popup->fetch(), array("class" => "align_center"));
			
			$table->construct_row();
		}
	}
	
	$table->output($template_sets[$sid]);
	
	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->template_sets);
	
	$page->output_nav_tabs($sub_tabs, 'templates');
	
	$themes = array();
	$query = $db->simple_select("themes", "tid,name");
	while($theme = $db->fetch_array($query))
	{
		$themes[$theme['tid']][] = $theme['name'];
	}
	
	$template_sets[-1]['title'] = $lang->global_templates;
	$template_sets[-1]['sid'] = -1;

	$query = $db->simple_select("templatesets", "*", "", array('order_by' => 'title', 'order_dir' => 'ASC'));
	while($template_set = $db->fetch_array($query))
	{
		$template_sets[$template_set['sid']] = $template_set;
	}
	
	$table = new Table;
	$table->construct_header($lang->template_set);
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));
	
	foreach($template_sets as $set)
	{
		if($set['sid'] == -1)
		{
			$table->construct_cell("<strong><a href=\"index.php?".SID."&amp;module=style/templates&amp;sid=-1\">{$lang->global_templates}</a></strong><br /><small>{$lang->used_by_all_themes}</small>");
			$table->construct_cell("<a href=\"index.php?".SID."&amp;module=style/templates&amp;sid=-1\">{$lang->expand_templates}</a>", array("class" => "align_center"));
			$table->construct_row();
			continue;
		}
		
		if($themes[$set['sid']])
		{
			$used_by_note = $lang->used_by;
			$comma = "";
			foreach($themes[$set['sid']] as $theme_name)
			{
				$used_by_note .= $comma.$theme_name;
				$comma = ", ";
			}
			$inuse = true;
		}
		else
		{
			$used_by_note = $lang->not_used_by_any_themes;
			$inuse = false;
		}

		$popup = new PopupMenu("templateset_{$set['sid']}", $lang->options);
		$popup->add_item($lang->expand_templates, "index.php?".SID."&amp;module=style/templates&amp;sid={$set['sid']}");
		$popup->add_item($lang->edit_template_set, "index.php?".SID."&amp;module=style/templates&amp;action=edit_set&amp;tid={$set['tid']}");
		
		if($inuse == false)
		{
			$popup->add_item($lang->delete_template_set, "index.php?".SID."&amp;module=style/templates&amp;delete_set&amp;sid={$set['sid']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_set_deletion}')");
		}
		
		$table->construct_cell("<strong><a href=\"index.php?".SID."&amp;module=style/templates&amp;sid={$set['sid']}\">{$set['title']}</a></strong><br /><small>{$used_by_note}</small>");
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
	}
	
	$table->output($lang->template_sets);

	$page->output_footer();
}

?>