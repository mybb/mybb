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

$page->add_breadcrumb_item($lang->template_sets, "index.php?module=style-templates");

$sid = $mybb->get_input('sid', MyBB::INPUT_INT);

$expand_str = "";
$expand_str2 = "";
$expand_array = array();
if(isset($mybb->input['expand']))
{
	$expand_array = explode("|", $mybb->input['expand']);
	$expand_array = array_map("intval", $expand_array);
	$expand_str = "&amp;expand=".implode("|", $expand_array);
	$expand_str2 = "&expand=".implode("|", $expand_array);
}

if($mybb->input['action'] == "add_set" || $mybb->input['action'] == "add_template" || ($mybb->input['action'] == "add_template_group" && !$sid) || $mybb->input['action'] == "search_replace" || $mybb->input['action'] == "find_updated" || (!$mybb->input['action'] && !$sid))
{
	$sub_tabs['templates'] = array(
		'title' => $lang->manage_template_sets,
		'link' => "index.php?module=style-templates",
		'description' => $lang->manage_template_sets_desc
	);

	$sub_tabs['add_set'] = array(
		'title' => $lang->add_set,
		'link' => "index.php?module=style-templates&amp;action=add_set".$expand_str
	);

	if($mybb->get_input('sid', MyBB::INPUT_INT) != -1)
	{
		$sub_tabs['add_template_group'] = array(
			'title' => $lang->add_template_group,
			'link' => "index.php?module=style-templates&amp;action=add_template_group".$expand_str,
			'description' => $lang->add_template_group_desc
		);
	}

	$sub_tabs['search_replace'] = array(
		'title' => $lang->search_replace,
		'link' => "index.php?module=style-templates&amp;action=search_replace",
		'description' => $lang->search_replace_desc
	);

	$sub_tabs['find_updated'] = array(
		'title' => $lang->find_updated,
		'link' => "index.php?module=style-templates&amp;action=find_updated",
		'description' => $lang->find_updated_desc
	);
}
else if(($sid && !$mybb->input['action']) || $mybb->input['action'] == "edit_set" || $mybb->input['action'] == "check_set" || $mybb->input['action'] == "edit_template" || $mybb->input['action'] == "add_template_group")
{
	$sub_tabs['manage_templates'] = array(
		'title' => $lang->manage_templates,
		'link' => "index.php?module=style-templates&amp;sid=".$sid.$expand_str,
		'description' => $lang->manage_templates_desc
	);

	if($sid > 0)
	{
		$sub_tabs['edit_set'] = array(
			'title' => $lang->edit_set,
			'link' => "index.php?module=style-templates&amp;action=edit_set&amp;sid=".$sid.$expand_str,
			'description' => $lang->edit_set_desc
		);
	}

	$sub_tabs['add_template'] = array(
		'title' => $lang->add_template,
		'link' => "index.php?module=style-templates&amp;action=add_template&amp;sid=".$sid.$expand_str,
		'description' => $lang->add_template_desc
	);

	if($mybb->get_input('sid', MyBB::INPUT_INT) != -1)
	{
		$sub_tabs['add_template_group'] = array(
			'title' => $lang->add_template_group,
			'link' => "index.php?module=style-templates&amp;action=add_template_group&amp;sid=".$sid.$expand_str,
			'description' => $lang->add_template_group_desc
		);
	}
}

$template_sets = array();
$template_sets[-1] = $lang->global_templates;

$query = $db->simple_select("templatesets", "*", "", array('order_by' => 'title', 'order_dir' => 'ASC'));
while($template_set = $db->fetch_array($query))
{
	$template_sets[$template_set['sid']] = $template_set['title'];
}

$plugins->run_hooks("admin_style_templates");

if($mybb->input['action'] == "add_set")
{
	$plugins->run_hooks("admin_style_templates_add_set");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_set_title;
		}

		if(!$errors)
		{
			$sid = $db->insert_query("templatesets", array('title' => $db->escape_string($mybb->input['title'])));

			$plugins->run_hooks("admin_style_templates_add_set_commit");

			// Log admin action
			log_admin_action($sid, $mybb->input['title']);

			flash_message($lang->success_template_set_saved, 'success');
			admin_redirect("index.php?module=style-templates&sid=".$sid);
		}
	}

	$page->add_breadcrumb_item($lang->add_set);

	$page->output_header($lang->add_set);

	$sub_tabs = array();
	$sub_tabs['add_set'] = array(
		'title' => $lang->add_set,
		'link' => "index.php?module=style-templates&amp;action=add_set",
		'description' => $lang->add_set_desc
	);

	$page->output_nav_tabs($sub_tabs, 'add_set');

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['title'] = "";
	}

	$form = new Form("index.php?module=style-templates&amp;action=add_set", "post", "add_set");

	$form_container = new FormContainer($lang->add_set);
	$form_container->output_row($lang->title, "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->end();

	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->save);

	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "add_template")
{
	$plugins->run_hooks("admin_style_templates_add_template");

	if($mybb->request_method == "post")
	{
		if(empty($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_set_title;
		}
		else
		{
			$query = $db->simple_select("templates", "COUNT(tid) as count", "title='".$db->escape_string($mybb->input['title'])."' AND (sid = '-2' OR sid = '{$sid}')");
			if($db->fetch_field($query, "count") > 0)
			{
				$errors[] = $lang->error_already_exists;
			}
		}

		if(!isset($template_sets[$sid]))
		{
			$errors[] = $lang->error_invalid_set;
		}

		// Are we trying to do malicious things in our template?
		if(check_template($mybb->input['template']))
		{
			$errors[] = $lang->error_security_problem;
		}

		if(!$errors)
		{
			$template_array = array(
				'title' => $db->escape_string($mybb->input['title']),
				'sid' => $sid,
				'template' => $db->escape_string(rtrim($mybb->input['template'])),
				'version' => $db->escape_string($mybb->version_code),
				'status' => '',
				'dateline' => TIME_NOW
			);

			$tid = $db->insert_query("templates", $template_array);

			$plugins->run_hooks("admin_style_templates_add_template_commit");

			// Log admin action
			log_admin_action($tid, $mybb->input['title'], $sid, $template_sets[$sid]);

			flash_message($lang->success_template_saved, 'success');

			if($mybb->input['continue'])
			{
				admin_redirect("index.php?module=style-templates&action=edit_template&title=".urlencode($mybb->input['title'])."&sid=".$sid.$expand_str2);
			}
			else
			{
				admin_redirect("index.php?module=style-templates&sid=".$sid.$expand_str2);
			}
		}
	}

	if($errors)
	{
		$template = $mybb->input;
	}
	else
	{
		if(!$sid)
		{
			$sid = -1;
		}

		$template['template'] = "";
		$template['sid'] = $sid;
	}

	if($mybb->input['sid'])
	{
		$page->add_breadcrumb_item($template_sets[$sid], "index.php?module=style-templates&amp;sid={$sid}{$expand_str}");
	}

	if($admin_options['codepress'] != 0)
	{
		$page->extra_header .= '
<link href="./jscripts/codemirror/lib/codemirror.css" rel="stylesheet">
<link href="./jscripts/codemirror/theme/mybb.css?ver=1804" rel="stylesheet">
<script src="./jscripts/codemirror/lib/codemirror.js"></script>
<script src="./jscripts/codemirror/mode/xml/xml.js"></script>
<script src="./jscripts/codemirror/mode/javascript/javascript.js"></script>
<script src="./jscripts/codemirror/mode/css/css.js"></script>
<script src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js"></script>
<link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css" rel="stylesheet">
<script src="./jscripts/codemirror/addon/dialog/dialog.js"></script>
<script src="./jscripts/codemirror/addon/search/searchcursor.js"></script>
<script src="./jscripts/codemirror/addon/search/search.js"></script>
<script src="./jscripts/codemirror/addon/fold/foldcode.js"></script>
<script src="./jscripts/codemirror/addon/fold/xml-fold.js"></script>
<script src="./jscripts/codemirror/addon/fold/foldgutter.js"></script>
<link href="./jscripts/codemirror/addon/fold/foldgutter.css" rel="stylesheet">
';
	}

	$page->add_breadcrumb_item($lang->add_template);

	$page->output_header($lang->add_template);

	$sub_tabs = array();
	$sub_tabs['add_template'] = array(
		'title' => $lang->add_template,
		'link' => "index.php?module=style-templates&amp;action=add_template&amp;sid=".$template['sid'].$expand_str,
		'description' => $lang->add_template_desc
	);

	$page->output_nav_tabs($sub_tabs, 'add_template');

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=style-templates&amp;action=add_template{$expand_str}", "post", "add_template");

	$form_container = new FormContainer($lang->add_template, 'tfixed');
	$form_container->output_row($lang->template_name, $lang->template_name_desc, $form->generate_text_box('title', $template['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->template_set, $lang->template_set_desc, $form->generate_select_box('sid', $template_sets, $sid), 'sid');
	$form_container->output_row("", "", $form->generate_text_area('template', $template['template'], array('id' => 'template', 'class' => '', 'style' => 'width: 100%; height: 500px;')), 'template');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_continue, array('name' => 'continue'));
	$buttons[] = $form->generate_submit_button($lang->save_close, array('name' => 'close'));

	$form->output_submit_wrapper($buttons);

	$form->end();

	if($admin_options['codepress'] != 0)
	{
		echo '<script type="text/javascript">
			var editor = CodeMirror.fromTextArea(document.getElementById("template"), {
				lineNumbers: true,
				lineWrapping: true,
				foldGutter: true,
				gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
				viewportMargin: Infinity,
				indentWithTabs: true,
				indentUnit: 4,
				mode: "text/html",
				theme: "mybb"
			});
		</script>';
	}

	$page->output_footer();
}

if($mybb->input['action'] == "add_template_group")
{
	$plugins->run_hooks("admin_style_templates_add_template_group");

	if($mybb->get_input('sid', MyBB::INPUT_INT) == -1)
	{
		admin_redirect("index.php?module=style-templates&amp;sid={$sid}".$expand_str2);
	}

	$errors = array();
	if($mybb->request_method == "post")
	{
		$prefix = trim($mybb->input['prefix']);
		if(!$prefix)
		{
			$errors[] = $lang->error_missing_group_prefix;
		}

		$title = trim($mybb->input['title']);
		if(!$title)
		{
			$errors[] = $lang->error_missing_group_title;
		}

		if(!$errors)
		{
			$query = $db->simple_select("templategroups", "COUNT(gid) AS gid", "prefix = '".$db->escape_string($mybb->input['prefix'])."'");
			$prefix_count = $db->fetch_field($query, 'gid');

			if($prefix_count >= 1)
			{
				$errors[] = $lang->error_duplicate_group_prefix;
			}
			else
			{
				// Add template group
				$insert_array = array(
					'prefix' => $db->escape_string($prefix),
					'title' => $db->escape_string($title),
					'isdefault' => 0
				);

				$gid = $db->insert_query('templategroups', $insert_array);

				$plugins->run_hooks('admin_style_templates_add_template_group_commit');

				log_admin_action($gid, $title);
				flash_message($lang->success_template_group_saved, 'success');

				if($sid)
				{
					admin_redirect("index.php?module=style-templates&amp;sid={$sid}".$expand_str2);
				}

				admin_redirect('index.php?module=style-templates');
			}
		}
	}

	if($mybb->input['sid'])
	{
		$page->add_breadcrumb_item($template_sets[$sid], "index.php?module=style-templates&amp;sid={$sid}{$expand_str}");
	}

	$page->add_breadcrumb_item($lang->add_template_group);
	$page->output_header($lang->add_template_group);
	$page->output_nav_tabs($sub_tabs, 'add_template_group');

	if($errors)
	{
		$template_group = array(
			'prefix' => $prefix,
			'title' => $title
		);

		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=style-templates&amp;action=add_template_group{$expand_str}", "post", "add_template_group");
	echo $form->generate_hidden_field('sid', $sid);

	$form_container = new FormContainer($lang->add_template_group);
	$form_container->output_row($lang->template_group_prefix, $lang->template_group_prefix_desc, $form->generate_text_box('prefix', $template_group['prefix'], array('id' => 'prefix')), 'prefix');
	$form_container->output_row($lang->template_group_title, $lang->template_group_title_desc, $form->generate_text_box('title', $template_group['title'], array('id' => 'title')), 'title');
	$form_container->end();

	$buttons = array(
		$form->generate_submit_button($lang->add_template_group)
	);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit_set")
{
	$query = $db->simple_select("templatesets", "*", "sid='{$sid}'");
	$set = $db->fetch_array($query);
	if(!$set)
	{
		flash_message($lang->error_invalid_input, 'error');
		admin_redirect("index.php?module=style-templates");
	}

	$plugins->run_hooks("admin_style_templates_edit_set");

	$sid = $set['sid'];

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_set_title;
		}

		if(!$errors)
		{
			$query = $db->update_query("templatesets", array('title' => $db->escape_string($mybb->input['title'])), "sid='{$sid}'");

			$plugins->run_hooks("admin_style_templates_edit_set_commit");

			// Log admin action
			log_admin_action($sid, $set['title']);

			flash_message($lang->success_template_set_saved, 'success');
			admin_redirect("index.php?module=style-templates&sid=".$sid.$expand_str2);
		}
	}

	if($sid)
	{
		$page->add_breadcrumb_item($template_sets[$sid], "index.php?module=style-templates&amp;sid={$sid}{$expand_str}");
	}

	$page->add_breadcrumb_item($lang->edit_set);

	$page->output_header($lang->edit_set);

	$sub_tabs = array();
	$sub_tabs['edit_set'] = array(
		'title' => $lang->edit_set,
		'link' => "index.php?module=style-templates&amp;action=edit_set&amp;sid=".$sid,
		'description' => $lang->edit_set_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_set');

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$query = $db->simple_select("templatesets", "title", "sid='{$sid}'");
		$mybb->input['title'] = $db->fetch_field($query, "title");
	}

	$form = new Form("index.php?module=style-templates&amp;action=edit_set{$expand_str}", "post", "edit_set");
	echo $form->generate_hidden_field("sid", $sid);

	$form_container = new FormContainer($lang->edit_set);
	$form_container->output_row($lang->title, "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->end();

	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->save);

	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit_template")
{
	if(!$mybb->input['title'] || !$sid || !isset($template_sets[$sid]))
	{
		flash_message($lang->error_missing_input, 'error');
		admin_redirect("index.php?module=style-templates");
	}

	$plugins->run_hooks("admin_style_templates_edit_template");

	if($mybb->request_method == "post")
	{
		if(empty($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		// Are we trying to do malicious things in our template?
		if(check_template($mybb->input['template']))
		{
			$errors[] = $lang->error_security_problem;
		}

		if(!$errors)
		{
			$query = $db->simple_select("templates", "*", "tid='{$mybb->input['tid']}'");
			$template = $db->fetch_array($query);

			$template_array = array(
				'title' => $db->escape_string($mybb->input['title']),
				'sid' => $sid,
				'template' => $db->escape_string(rtrim($mybb->input['template'])),
				'version' => $mybb->version_code,
				'status' => '',
				'dateline' => TIME_NOW
			);

			// Make sure we have the correct tid associated with this template. If the user double submits then the tid could originally be the master template tid, but because the form is sumbitted again, the tid doesn't get updated to the new modified template one. This then causes the master template to be overwritten
			$query = $db->simple_select("templates", "tid", "title='".$db->escape_string($template['title'])."' AND (sid = '-2' OR sid = '{$template['sid']}')", array('order_by' => 'sid', 'order_dir' => 'desc', 'limit' => 1));
			$template['tid'] = $db->fetch_field($query, "tid");

			$plugins->run_hooks("admin_style_templates_edit_template_commit_start");

			if($sid > 0)
			{
				// Check to see if it's never been edited before (i.e. master) or if this a new template (i.e. we've renamed it)  or if it's a custom template
				$query = $db->simple_select("templates", "sid", "title='".$db->escape_string($mybb->input['title'])."' AND (sid = '-2' OR sid = '{$sid}' OR sid='{$template['sid']}')", array('order_by' => 'sid', 'order_dir' => 'desc'));
				$existing_sid = $db->fetch_field($query, "sid");
				$existing_rows = $db->num_rows($query);

				if(($existing_sid == -2 && $existing_rows == 1) || $existing_rows == 0)
				{
					$template['tid'] = $db->insert_query("templates", $template_array);
				}
				else
				{
					$db->update_query("templates", $template_array, "tid='{$template['tid']}' AND sid != '-2'");
				}
			}
			else
			{
				// Global template set
				$db->update_query("templates", $template_array, "tid='{$template['tid']}' AND sid != '-2'");
			}

			$plugins->run_hooks("admin_style_templates_edit_template_commit");

			$query = $db->simple_select("templatesets", "title", "sid='{$sid}'");
			$set = $db->fetch_array($query);

			$exploded = explode("_", $template_array['title'], 2);
			$prefix = $exploded[0];

			$query = $db->simple_select("templategroups", "gid", "prefix = '".$db->escape_string($prefix)."'");
			$group = $db->fetch_field($query, "gid");

			if(!$group)
			{
				$group = "-1";
			}

			// Log admin action
			log_admin_action($template['tid'], $mybb->input['title'], $mybb->input['sid'], $set['title']);

			flash_message($lang->success_template_saved, 'success');

			if($mybb->input['continue'])
			{
				if($mybb->input['from'] == "diff_report")
				{
					admin_redirect("index.php?module=style-templates&action=edit_template&title=".urlencode($mybb->input['title'])."&sid=".$mybb->get_input('sid', MyBB::INPUT_INT).$expand_str2."&amp;from=diff_report");
				}
				else
				{
					admin_redirect("index.php?module=style-templates&action=edit_template&title=".urlencode($mybb->input['title'])."&sid=".$mybb->get_input('sid', MyBB::INPUT_INT).$expand_str2);
				}
			}
			else
			{
				if($mybb->input['from'] == "diff_report")
				{
					admin_redirect("index.php?module=style-templates&amp;action=find_updated");
				}
				else
				{
					admin_redirect("index.php?module=style-templates&sid=".$mybb->get_input('sid', MyBB::INPUT_INT).$expand_str2."#group_{$group}");
				}
			}
		}
	}

	if($errors)
	{
		$template = $mybb->input;
	}
	else
	{
		$query = $db->simple_select("templates", "*", "title='".$db->escape_string($mybb->input['title'])."' AND (sid='-2' OR sid='{$sid}')", array('order_by' => 'sid', 'order_dir' => 'DESC', 'limit' => 1));
		$template = $db->fetch_array($query);
	}
	$template['title'] = htmlspecialchars_uni($template['title']);

	if($admin_options['codepress'] != 0)
	{
		$page->extra_header .= '
<link href="./jscripts/codemirror/lib/codemirror.css" rel="stylesheet">
<link href="./jscripts/codemirror/theme/mybb.css?ver=1804" rel="stylesheet">
<script src="./jscripts/codemirror/lib/codemirror.js"></script>
<script src="./jscripts/codemirror/mode/xml/xml.js"></script>
<script src="./jscripts/codemirror/mode/javascript/javascript.js"></script>
<script src="./jscripts/codemirror/mode/css/css.js"></script>
<script src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js"></script>
<link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css" rel="stylesheet">
<script src="./jscripts/codemirror/addon/dialog/dialog.js"></script>
<script src="./jscripts/codemirror/addon/search/searchcursor.js"></script>
<script src="./jscripts/codemirror/addon/search/search.js"></script>
<script src="./jscripts/codemirror/addon/fold/foldcode.js"></script>
<script src="./jscripts/codemirror/addon/fold/xml-fold.js"></script>
<script src="./jscripts/codemirror/addon/fold/foldgutter.js"></script>
<link href="./jscripts/codemirror/addon/fold/foldgutter.css" rel="stylesheet">
';
	}

	$page->add_breadcrumb_item($template_sets[$sid], "index.php?module=style-templates&amp;sid={$sid}{$expand_str}");

	if(!isset($mybb->input['from']))
	{
		$mybb->input['from'] = '';
	}

	if($mybb->input['from'] == "diff_report")
	{
		$page->add_breadcrumb_item($lang->find_updated, "index.php?module=style-templates&amp;action=find_updated");
	}

	$page->add_breadcrumb_item($lang->edit_template_breadcrumb.$template['title'], "index.php?module=style-templates&amp;sid={$sid}");

	$page->output_header($lang->sprintf($lang->editing_template, $template['title']));


	$sub_tabs = array();

	if($mybb->input['from'] == "diff_report")
	{
		$sub_tabs['find_updated'] = array(
			'title' => $lang->find_updated,
			'link' => "index.php?module=style-templates&amp;action=find_updated"
		);

		$sub_tabs['diff_report'] = array(
			'title' => $lang->diff_report,
			'link' => "index.php?module=style-templates&amp;action=diff_report&amp;title=".$db->escape_string($template['title'])."&amp;sid1=".(int)$template['sid']."&amp;sid2=-2",
		);
	}

	$sub_tabs['edit_template'] = array(
		'title' => $lang->edit_template,
		'link' => "index.php?module=style-templates&amp;action=edit_template&amp;title=".htmlspecialchars_uni($template['title']).$expand_str,
		'description' => $lang->edit_template_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_template');

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=style-templates&amp;action=edit_template{$expand_str}", "post", "edit_template");
	echo $form->generate_hidden_field('tid', $template['tid'])."\n";

	if($mybb->input['from'] == "diff_report")
	{
		echo $form->generate_hidden_field('from', "diff_report");
	}

	$form_container = new FormContainer($lang->edit_template_breadcrumb.$template['title'], 'tfixed');
	$form_container->output_row($lang->template_name, $lang->template_name_desc, $form->generate_text_box('title', $template['title'], array('id' => 'title')), 'title');

	// Force users to save the default template to a specific set, rather than the "global" templates - where they can delete it
	if($template['sid'] == "-2")
	{
		unset($template_sets[-1]);
	}

	$form_container->output_row($lang->template_set, $lang->template_set_desc, $form->generate_select_box('sid', $template_sets, $sid));

	$form_container->output_row("", "", $form->generate_text_area('template', $template['template'], array('id' => 'template', 'class' => '', 'style' => 'width: 100%; height: 500px;')));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_continue, array('name' => 'continue'));
	$buttons[] = $form->generate_submit_button($lang->save_close, array('name' => 'close'));

	$form->output_submit_wrapper($buttons);

	$form->end();

	if($admin_options['codepress'] != 0)
	{
		echo '<script type="text/javascript">
			var editor = CodeMirror.fromTextArea(document.getElementById("template"), {
				lineNumbers: true,
				lineWrapping: true,
				foldGutter: true,
				gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
				viewportMargin: Infinity,
				indentWithTabs: true,
				indentUnit: 4,
				mode: "text/html",
				theme: "mybb"
			});
		</script>';
	}

	$page->output_footer();
}

if($mybb->input['action'] == "edit_template_group")
{
	$query = $db->simple_select("templategroups", "*", "gid = '".$mybb->get_input('gid', MyBB::INPUT_INT)."'");

	if(!$db->num_rows($query))
	{
		flash_message($lang->error_missing_template_group, 'error');
		admin_redirect("index.php?module=style-templates&amp;sid={$sid}{$expand_str}");
	}

	$template_group = $db->fetch_array($query);
	if(isset($template_group['isdefault']) && $template_group['isdefault'] == 1)
	{
		flash_message($lang->error_default_template_group, 'error');
		admin_redirect("index.php?module=style-templates&amp;sid={$sid}{$expand_str}");
	}

	$plugins->run_hooks("admin_style_templates_edit_template_group");

	$errors = array();
	if($mybb->request_method == "post")
	{
		$prefix = trim($mybb->input['prefix']);
		if(!$prefix)
		{
			$errors[] = $lang->error_missing_group_prefix;
		}

		$title = trim($mybb->input['title']);
		if(!$title)
		{
			$errors[] = $lang->error_missing_group_title;
		}

		if(!$errors)
		{
			if($prefix != $template_group['prefix'])
			{
				$query = $db->simple_select("templategroups", "COUNT(gid) AS gid", "prefix = '".$db->escape_string($mybb->input['prefix'])."'");
				$prefix_count = $db->fetch_field($query, 'gid');

				if($prefix_count >= 1)
				{
					$errors[] = $lang->error_duplicate_group_prefix;
				}
			}

			if(!$errors)
			{
				// Add template group
				$update_array = array(
					'prefix' => $db->escape_string($prefix),
					'title' => $db->escape_string($title),
					'isdefault' => 0
				);

				$plugins->run_hooks('admin_style_templates_edit_template_group_commit');

				$db->update_query('templategroups', $update_array, "gid = '{$template_group['gid']}'");

				log_admin_action($template_group['gid'], htmlspecialchars_uni($title));
				flash_message($lang->success_template_group_saved, 'success');
				admin_redirect("index.php?module=style-templates&amp;sid={$sid}");
			}
		}
	}

	$lang->editing_template_group = $lang->sprintf($lang->editing_template_group, htmlspecialchars_uni($template_group['title']));

	$page->add_breadcrumb_item($template_sets[$sid], "index.php?module=style-templates&amp;sid={$sid}{$expand_str}");
	$page->add_breadcrumb_item($lang->editing_template_group, "index.php?module=style-templates&amp;sid={$sid}");

	$page->output_header($lang->editing_template_group);

	if($errors)
	{
		$template_group['prefix'] = $prefix;
		$template_group['title'] = $title;

		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=style-templates&amp;action=edit_template_group", "post");
	echo $form->generate_hidden_field('sid', $sid);
	echo $form->generate_hidden_field('gid', $template_group['gid']);

	$form_container = new FormContainer($lang->edit_template_group);
	$form_container->output_row($lang->template_group_prefix, $lang->template_group_prefix_desc, $form->generate_text_box('prefix', $template_group['prefix'], array('id' => 'prefix')), 'prefix');
	$form_container->output_row($lang->template_group_title, $lang->template_group_title_desc, $form->generate_text_box('title', $template_group['title'], array('id' => 'title')), 'title');
	$form_container->end();

	$buttons = array(
		$form->generate_submit_button($lang->save_template_group)
	);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "search_replace")
{
	$plugins->run_hooks("admin_style_templates_search_replace");

	if($mybb->request_method == "post")
	{
		if($mybb->input['type'] == "templates")
		{
			// Search and replace in templates

			if(!$mybb->input['find'])
			{
				flash_message($lang->search_noneset, "error");
				admin_redirect("index.php?module=style-templates&action=search_replace");
			}
			else
			{
				$page->add_breadcrumb_item($lang->search_replace);

				$page->output_header($lang->search_replace);

				$plugins->run_hooks("admin_style_templates_search_replace_find");

				$page->output_nav_tabs($sub_tabs, 'search_replace');

				$templates_list = array();
				$table = new Table;

				$template_sets = array();

				// Get the names of all template sets
				$template_sets[-2] = $lang->master_templates;
				$template_sets[-1] = $lang->global_templates;

				$query = $db->simple_select("templatesets", "sid, title");
				while($set = $db->fetch_array($query))
				{
					$template_sets[$set['sid']] = $set['title'];
				}

				// Select all templates with that search term
				$query = $db->query("
					SELECT t.tid, t.title, t.sid, t.template
					FROM ".TABLE_PREFIX."templates t
					LEFT JOIN ".TABLE_PREFIX."templatesets s ON (t.sid=s.sid)
					LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t.title=t2.title AND t2.sid='1')
					WHERE t.template LIKE '%".$db->escape_string_like($mybb->input['find'])."%' AND NOT (t.sid = -2 AND (t2.tid) IS NOT NULL)
					ORDER BY t.title ASC
				");
				if($db->num_rows($query) == 0)
				{
					$table->construct_cell($lang->sprintf($lang->search_noresults, htmlspecialchars_uni($mybb->input['find'])), array("class" => "align_center"));

					$table->construct_row();

					$table->output($lang->search_results);
				}
				else
				{
					$template_list = array();
					while($template = $db->fetch_array($query))
					{
						$template_list[$template['sid']][$template['title']] = $template;
					}

					$count = 0;

					foreach($template_list as $sid => $templates)
					{
						++$count;

						$search_header = $lang->sprintf($lang->search_header, htmlspecialchars_uni($mybb->input['find']), $template_sets[$sid]);
						$table->construct_header($search_header, array("colspan" => 2));

						foreach($templates as $title => $template)
						{
							// Do replacement
							$newtemplate = str_ireplace($mybb->input['find'], $mybb->input['replace'], $template['template']);
							if($newtemplate != $template['template'] && check_template($newtemplate) === false)
							{
								// If the template is different, that means the search term has been found.
								if(trim($mybb->input['replace']) != "")
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
											"dateline" => TIME_NOW
										);
										$new_tid = $db->insert_query("templates", $new_template);
										$label = $lang->sprintf($lang->search_created_custom, $template['title']);
										$url = "index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid=1";
									}
									else
									{
										// The template is a custom template.  Replace as normal.
										// Update the template if there is a replacement term
										$updatedtemplate = array(
											"template" => $db->escape_string($newtemplate)
										);
										$db->update_query("templates", $updatedtemplate, "tid='".$template['tid']."'");
										$label = $lang->sprintf($lang->search_updated, $template['title']);
										$url = "index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid={$template['sid']}";
									}
								}
								else
								{
									// Just show that the term was found
									if($template['sid'] == -2)
									{
										$label = $lang->sprintf($lang->search_found, $template['title']);
									}
									else
									{
										$label = $lang->sprintf($lang->search_found, $template['title']);
										$url = "index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid={$template['sid']}";
									}
								}
							}
							else
							{
								// Just show that the term was found
								if($template['sid'] == -2)
								{
									$label = $lang->sprintf($lang->search_found, $template['title']);
								}
								else
								{
									$label = $lang->sprintf($lang->search_found, $template['title']);
									$url = "index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid={$template['sid']}";
								}
							}

							$table->construct_cell($label, array("width" => "85%"));

							if($sid == -2)
							{
								$popup = new PopupMenu("template_{$template['tid']}", $lang->options);

								foreach($template_sets as $set_sid => $title)
								{
									if($set_sid > 0)
									{
										$popup->add_item($lang->edit_in." ".htmlspecialchars_uni($title), "index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid={$set_sid}");
									}
								}

								$table->construct_cell($popup->fetch(), array("class" => "align_center"));
							}
							else
							{
								$table->construct_cell("<a href=\"{$url}\">{$lang->edit}</a>", array("class" => "align_center"));
							}

							$table->construct_row();
						}

						if($count == 1)
						{
							$table->output($lang->search_results);
						}
						else
						{
							$table->output();
						}
					}
				}

				if(trim($mybb->input['replace']) != "")
				{
					// Log admin action - only if replace
					log_admin_action($mybb->input['find'], $mybb->input['replace']);
				}

				$page->output_footer();
				exit;
			}
		}
		else
		{
			if(!$mybb->input['title'])
			{
				flash_message($lang->search_noneset, "error");
				admin_redirect("index.php?module=style-templates&action=search_replace");
			}
			else
			{
				// Search Template Titles

				$templatessets = array();

				$templates_sets = array();
				// Get the names of all template sets
				$template_sets[-2] = $lang->master_templates;
				$template_sets[-1] = $lang->global_templates;

				$plugins->run_hooks("admin_style_templates_search_replace_title");

				$query = $db->simple_select("templatesets", "sid, title");
				while($set = $db->fetch_array($query))
				{
					$template_sets[$set['sid']] = $set['title'];
				}

				$table = new Table;

				$query = $db->query("
					SELECT t.tid, t.title, t.sid, s.title as settitle, t2.tid as customtid
					FROM ".TABLE_PREFIX."templates t
					LEFT JOIN ".TABLE_PREFIX."templatesets s ON (t.sid=s.sid)
					LEFT JOIN ".TABLE_PREFIX."templates t2 ON (t.title=t2.title AND t2.sid='1')
					WHERE t.title LIKE '%".$db->escape_string_like($mybb->input['title'])."%'
					ORDER BY t.title ASC
				");
				while($template = $db->fetch_array($query))
				{
					if($template['sid'] == -2)
					{
						if(!$template['customtid'])
						{
							$template['original'] = true;
						}
						else
						{
							$template['modified'] = true;
						}
					}
					else
					{
						$template['original'] = false;
						$template['modified'] = false;
					}
					$templatessets[$template['sid']][$template['title']] = $template;
				}

				$page->add_breadcrumb_item($lang->search_replace);

				$page->output_header($lang->search_replace);

				$page->output_nav_tabs($sub_tabs, 'search_replace');

				if(empty($templatessets))
				{
					$table->construct_cell($lang->sprintf($lang->search_noresults_title, htmlspecialchars_uni($mybb->input['title'])), array("class" => "align_center"));

					$table->construct_row();

					$table->output($lang->search_results);
				}

				$count = 0;

				foreach($templatessets as $sid => $templates)
				{
					++$count;

					$table->construct_header($template_sets[$sid], array("colspan" => 2));

					foreach($templates as $template)
					{
						$template['pretty_title'] = $template['title'];

						$popup = new PopupMenu("template_{$template['tid']}", $lang->options);

						if($sid == -2)
						{
							foreach($template_sets as $set_sid => $title)
							{
								if($set_sid < 0) continue;

								$popup->add_item($lang->edit_in." ".htmlspecialchars_uni($title), "index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid={$set_sid}");
							}
						}
						else
						{
							$popup->add_item($lang->full_edit, "index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid={$sid}");
						}

						if(isset($template['modified']) && $template['modified'] == true)
						{
							if($sid > 0)
							{
								$popup->add_item($lang->diff_report, "index.php?module=style-templates&amp;action=diff_report&amp;title=".urlencode($template['title'])."&amp;sid2={$sid}");

								$popup->add_item($lang->revert_to_orig, "index.php?module=style-templates&amp;action=revert&amp;title=".urlencode($template['title'])."&amp;sid={$sid}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_revertion}')");
							}

							$template['pretty_title'] = "<span style=\"color: green;\">{$template['title']}</span>";
						}
						// This template does not exist in the master list
						else if(!isset($template['original']) || $template['original'] == false)
						{
							$popup->add_item($lang->delete_template, "index.php?module=style-templates&amp;action=delete_template&amp;title=".urlencode($template['title'])."&amp;sid={$sid}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_deletion}')");

							$template['pretty_title'] = "<span style=\"color: blue;\">{$template['title']}</span>";
						}

						$table->construct_cell("<span style=\"padding: 20px;\">{$template['pretty_title']}</span>", array("width" => "85%"));
						$table->construct_cell($popup->fetch(), array("class" => "align_center"));

						$table->construct_row();
					}

					if($count == 1)
					{
						$table->output($lang->sprintf($lang->search_names_header, htmlspecialchars_uni($mybb->input['title'])));
					}
					else if($count > 0)
					{
						$table->output();
					}
				}

				$page->output_footer();
				exit;
			}
		}
	}

	if($admin_options['codepress'] != 0)
	{
		$page->extra_header .= '
<link href="./jscripts/codemirror/lib/codemirror.css" rel="stylesheet">
<link href="./jscripts/codemirror/theme/mybb.css?ver=1804" rel="stylesheet">
<script src="./jscripts/codemirror/lib/codemirror.js"></script>
<script src="./jscripts/codemirror/mode/xml/xml.js"></script>
<script src="./jscripts/codemirror/mode/javascript/javascript.js"></script>
<script src="./jscripts/codemirror/mode/css/css.js"></script>
<script src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js"></script>
<link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css" rel="stylesheet">
<script src="./jscripts/codemirror/addon/dialog/dialog.js"></script>
<script src="./jscripts/codemirror/addon/search/searchcursor.js"></script>
<script src="./jscripts/codemirror/addon/search/search.js"></script>
<script src="./jscripts/codemirror/addon/fold/foldcode.js"></script>
<script src="./jscripts/codemirror/addon/fold/xml-fold.js"></script>
<script src="./jscripts/codemirror/addon/fold/foldgutter.js"></script>
<link href="./jscripts/codemirror/addon/fold/foldgutter.css" rel="stylesheet">
';
	}

	$page->add_breadcrumb_item($lang->search_replace);

	$page->output_header($lang->search_replace);

	$page->output_nav_tabs($sub_tabs, 'search_replace');

	$form = new Form("index.php?module=style-templates&amp;action=search_replace", "post", "do_template");
	echo $form->generate_hidden_field('type', "templates");

	$form_container = new FormContainer($lang->search_replace, 'tfixed');
	$form_container->output_row($lang->search_for, "", $form->generate_text_area('find', $mybb->input['find'], array('id' => 'find', 'class' => '', 'style' => 'width: 100%; height: 200px;')));

	$form_container->output_row($lang->replace_with, "", $form->generate_text_area('replace', $mybb->input['replace'], array('id' => 'replace', 'class' => '', 'style' => 'width: 100%; height: 200px;')));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->find_and_replace);

	$form->output_submit_wrapper($buttons);

	$form->end();

	echo "<br />";


	$form = new Form("index.php?module=style-templates&amp;action=search_replace", "post", "do_title");
	echo $form->generate_hidden_field('type', "titles");

	$form_container = new FormContainer($lang->search_template_names);

	$form_container->output_row($lang->search_for, "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');

	$form_container->end();

	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->find_templates);
	$buttons[] = $form->generate_reset_button($lang->reset);

	$form->output_submit_wrapper($buttons);

	$form->end();

	if($admin_options['codepress'] != 0)
	{
		echo '<script type="text/javascript">
			var editor1 = CodeMirror.fromTextArea(document.getElementById("find"), {
				lineNumbers: true,
				lineWrapping: true,
				foldGutter: true,
				gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
				viewportMargin: Infinity,
				indentWithTabs: true,
				indentUnit: 4,
				mode: "text/html",
				theme: "mybb"
			});

			var editor2 = CodeMirror.fromTextArea(document.getElementById("replace"), {
				lineNumbers: true,
				lineWrapping: true,
				foldGutter: true,
				gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
				viewportMargin: Infinity,
				indentWithTabs: true,
				indentUnit: 4,
				mode: "text/html",
				theme: "mybb"
			});
		</script>';
	}

	$page->output_footer();
}

if($mybb->input['action'] == "find_updated")
{
	// Finds templates that are old and have been updated by MyBB
	$compare_version = $mybb->version_code;
	$query = $db->query("
		SELECT COUNT(*) AS updated_count
		FROM ".TABLE_PREFIX."templates t
		LEFT JOIN ".TABLE_PREFIX."templates m ON (m.title=t.title AND m.sid=-2 AND m.version > t.version)
		WHERE t.sid > 0 AND m.template != t.template
	");
	$count = $db->fetch_array($query);

	if($count['updated_count'] < 1)
	{
		flash_message($lang->no_updated_templates, 'success');
		admin_redirect("index.php?module=style-templates");
	}

	$plugins->run_hooks("admin_style_templates_find_updated");

	$page->add_breadcrumb_item($lang->find_updated, "index.php?module=style-templates&amp;action=find_updated");

	$page->output_header($lang->find_updated);

	$page->output_nav_tabs($sub_tabs, 'find_updated');

	$query = $db->simple_select("templatesets", "*", "", array('order_by' => 'title'));
	while($templateset = $db->fetch_array($query))
	{
		$templatesets[$templateset['sid']] = $templateset;
	}

	echo <<<LEGEND
	<fieldset>
<legend>{$lang->legend}</legend>
<ul>
<li>{$lang->updated_template_welcome1}</li>
<li>{$lang->updated_template_welcome2}</li>
<li>{$lang->updated_template_welcome3}</li>
</ul>
</fieldset>
LEGEND;

	$count = 0;
	$done_set = array();
	$done_output = array();
	$templates = array();
	$table = new Table;

	$query = $db->query("
		SELECT t.tid, t.title, t.sid, t.version
		FROM ".TABLE_PREFIX."templates t
		LEFT JOIN ".TABLE_PREFIX."templates m ON (m.title=t.title AND m.sid=-2 AND m.version > t.version)
		WHERE t.sid > 0 AND m.template != t.template
		ORDER BY t.sid ASC, title ASC
	");
	while($template = $db->fetch_array($query))
	{
		$templates[$template['sid']][] = $template;
	}

	foreach($templates as $sid => $templates)
	{
		if(!$done_set[$sid])
		{
			$table->construct_header($templatesets[$sid]['title'], array("colspan" => 2));

			$done_set[$sid] = 1;
			++$count;
		}

		foreach($templates as $template)
		{
			$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
			$popup->add_item($lang->full_edit, "index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid={$sid}&amp;from=diff_report");
			$popup->add_item($lang->diff_report, "index.php?module=style-templates&amp;action=diff_report&amp;title=".urlencode($template['title'])."&amp;sid1=".$template['sid']."&amp;sid2=-2&amp;from=diff_report");
			$popup->add_item($lang->revert_to_orig, "index.php?module=style-templates&amp;action=revert&amp;title=".urlencode($template['title'])."&amp;sid={$sid}&amp;from=diff_report&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_revertion}')");

			$table->construct_cell("<a href=\"index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid={$sid}&amp;from=diff_report\">{$template['title']}</a>", array('width' => '80%'));
			$table->construct_cell($popup->fetch(), array("class" => "align_center"));

			$table->construct_row();
		}

		if($done_set[$sid] && !$done_output[$sid])
		{
			$done_output[$sid] = 1;
			if($count == 1)
			{
				$table->output($lang->find_updated);
			}
			else
			{
				$table->output();
			}
		}
	}

	$page->output_footer();
}

if($mybb->input['action'] == "delete_template_group")
{
	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
	$query = $db->simple_select("templategroups", "*", "gid='{$gid}'");

	if(!$db->num_rows($query))
	{
		flash_message($lang->error_missing_template_group, 'error');
		admin_redirect("index.php?module=style-templates&amp;sid={$sid}");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=style-templates&amp;sid={$sid}");
	}

	$plugins->run_hooks("admin_style_template_group_delete");

	$template_group = $db->fetch_array($query);

	if($mybb->request_method == "post")
	{
		// Delete the group
		$db->delete_query("templategroups", "gid = '{$template_group['gid']}'");

		$plugins->run_hooks("admin_style_template_group_delete_commit");

		// Log admin action
		log_admin_action($template_group['gid'], htmlspecialchars_uni($template_group['title']));

		flash_message($lang->success_template_group_deleted, 'success');
		admin_redirect("index.php?module=style-templates&amp;sid={$sid}");
	}
	else
	{
		$page->output_confirm_action("index.php?module=style-templates&amp;action=delete_template_group&amp;gid={$template_group['gid']}&amp;sid={$sid}", $lang->confirm_template_group_delete);
	}
}

if($mybb->input['action'] == "delete_set")
{
	$query = $db->simple_select("templatesets", "*", "sid='{$sid}' AND sid > 0");
	$set = $db->fetch_array($query);

	// Does the template not exist?
	if(!$set['sid'])
	{
		flash_message($lang->error_invalid_template_set, 'error');
		admin_redirect("index.php?module=style-templates");
	}

	$plugins->run_hooks("admin_style_templates_delete_set");

	// Is there a theme attached to this set?
	$query = $db->simple_select("themes", "properties");
	while($theme = $db->fetch_array($query))
	{
		$properties = my_unserialize($theme['properties']);
		if($properties['templateset'] == $sid)
		{
			flash_message($lang->error_themes_attached_template_set, 'error');
			admin_redirect("index.php?module=style-templates");
			break;
		}
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=style-templates");
	}

	if($mybb->request_method == "post")
	{
		// Delete the templateset
		$db->delete_query("templatesets", "sid='{$set['sid']}'");
		// Delete all custom templates in this templateset
		$db->delete_query("templates", "sid='{$set['sid']}'");

		$plugins->run_hooks("admin_style_templates_delete_set_commit");

		// Log admin action
		log_admin_action($set['sid'], $set['title']);

		flash_message($lang->success_template_set_deleted, 'success');
		admin_redirect("index.php?module=style-templates");
	}
	else
	{
		$page->output_confirm_action("index.php?module=style-templates&amp;action=delete_set&amp;sid={$set['sid']}", $lang->confirm_template_set_deletion);
	}

}

if($mybb->input['action'] == "delete_template")
{
	$query = $db->query("
		SELECT t.*, s.title as set_title
		FROM ".TABLE_PREFIX."templates t
		LEFT JOIN ".TABLE_PREFIX."templatesets s ON(t.sid=s.sid)
		WHERE t.title='".$db->escape_string($mybb->input['title'])."' AND t.sid > '-2' AND t.sid = '{$sid}'
	");
	$template = $db->fetch_array($query);

	// Does the template not exist?
	if(!$template)
	{
		flash_message($lang->error_invalid_template, 'error');
		admin_redirect("index.php?module=style-templates");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=style-templates&sid={$template['sid']}{$expand_str2}");
	}

	$plugins->run_hooks("admin_style_templates_delete_template");

	if($mybb->request_method == "post")
	{
		// Delete the template
		$db->delete_query("templates", "tid='{$template['tid']}'");

		$plugins->run_hooks("admin_style_templates_delete_template_commit");

		// Log admin action
		log_admin_action($template['tid'], $template['title'], $template['sid'], $template['set_title']);

		flash_message($lang->success_template_deleted, 'success');
		admin_redirect("index.php?module=style-templates&sid={$template['sid']}{$expand_str2}");
	}
	else
	{
		$page->output_confirm_action("index.php?module=style-templates&amp;action=delete_template&amp;sid={$template['sid']}{$expand_str}", $lang->confirm_template_deletion);
	}
}

if($mybb->input['action'] == "diff_report")
{
	// Compares a template of sid1 with that of sid2, if no sid1, it is assumed -2
	if(!$mybb->input['sid1'] || !isset($template_sets[$mybb->input['sid1']]))
	{
		$mybb->input['sid1'] = -2;
	}

	if($mybb->input['sid2'] == -2)
	{
		$sub_tabs['find_updated'] = array(
			'title' => $lang->find_updated,
			'link' => "index.php?module=style-templates&amp;action=find_updated"
		);
	}

	if($mybb->input['sid2'] != -2 && !isset($template_sets[$mybb->input['sid2']]))
	{
		flash_message($lang->error_invalid_input, 'error');
		admin_redirect("index.php?module=style-templates");
	}

	if(!$mybb->input['from'])
	{
		$mybb->input['from'] = 0;
	}

	$sub_tabs['diff_report'] = array(
		'title' => $lang->diff_report,
		'link' => "index.php?module=style-templates&amp;action=diff_report&amp;title=".$db->escape_string($mybb->input['title'])."&amp;from=".htmlspecialchars_uni($mybb->input['from'])."sid1=".$mybb->get_input('sid1', MyBB::INPUT_INT)."&amp;sid2=".$mybb->get_input('sid2', MyBB::INPUT_INT),
		'description' => $lang->diff_report_desc
	);

	$plugins->run_hooks("admin_style_templates_diff_report");

	$query = $db->simple_select("templates", "*", "title='".$db->escape_string($mybb->input['title'])."' AND sid='".$mybb->get_input('sid1', MyBB::INPUT_INT)."'");
	$template1 = $db->fetch_array($query);

	$query = $db->simple_select("templates", "*", "title='".$db->escape_string($mybb->input['title'])."' AND sid='".$mybb->get_input('sid2', MyBB::INPUT_INT)."'");
	$template2 = $db->fetch_array($query);

	if($mybb->input['sid2'] == -2)
	{
		$sub_tabs['full_edit'] = array(
			'title' => $lang->full_edit,
			'link' => "index.php?module=style-templates&action=edit_template&title=".urlencode($template1['title'])."&sid=".$mybb->get_input('sid1', MyBB::INPUT_INT)."&amp;from=diff_report",
		);
	}

	if($template1['template'] == $template2['template'])
	{
		flash_message($lang->templates_the_same, 'error');
		admin_redirect("index.php?module=style-templates&sid=".$mybb->get_input('sid2', MyBB::INPUT_INT).$expand_str);
	}

	$template1['template'] = explode("\n", $template1['template']);
	$template2['template'] = explode("\n", $template2['template']);

	$plugins->run_hooks("admin_style_templates_diff_report_run");

	require_once MYBB_ROOT."inc/3rdparty/diff/Diff.php";
	require_once MYBB_ROOT."inc/3rdparty/diff/Diff/Renderer.php";
	require_once MYBB_ROOT."inc/3rdparty/diff/Diff/Renderer/Inline.php";

	$diff = new Horde_Text_Diff('auto', array($template1['template'], $template2['template']));
	$renderer = new Horde_Text_Diff_Renderer_Inline();

	if($sid)
	{
		$page->add_breadcrumb_item($template_sets[$sid], "index.php?module=style-templates&amp;sid={$sid}{$expand_str}");
	}

	if($mybb->input['sid2'] == -2)
	{
		$page->add_breadcrumb_item($lang->find_updated, "index.php?module=style-templates&amp;action=find_updated");
	}

	$page->add_breadcrumb_item($lang->diff_report.": ".htmlspecialchars_uni($template1['title']), "index.php?module=style-templates&amp;action=diff_report&amp;title=".$db->escape_string($mybb->input['title'])."&amp;from=".htmlspecialchars_uni($mybb->input['from'])."&amp;sid1=".$mybb->get_input('sid1', MyBB::INPUT_INT)."&amp;sid2=".$mybb->get_input('sid2', MyBB::INPUT_INT));

	$page->output_header($lang->template_sets);

	$page->output_nav_tabs($sub_tabs, 'diff_report');

	$table = new Table;

	if($mybb->input['from'])
	{
		$table->construct_header("<ins>".$lang->master_updated_ins."</ins><br /><del>".$lang->master_updated_del."</del>");
	}
	else
	{
		$table->construct_header("<ins>".$lang->master_updated_del."</ins><br /><del>".$lang->master_updated_ins."</del>");
	}

	$table->construct_cell("<pre class=\"differential\">".$renderer->render($diff)."</pre>");
	$table->construct_row();

	$table->output($lang->template_diff_analysis.": ".$template1['title']);

	$page->output_footer();
}

if($mybb->input['action'] == "revert")
{
	$query = $db->query("
		SELECT t.*, s.title as set_title
		FROM ".TABLE_PREFIX."templates t
		LEFT JOIN ".TABLE_PREFIX."templatesets s ON(s.sid=t.sid)
		WHERE t.title='".$db->escape_string($mybb->input['title'])."' AND t.sid > 0 AND t.sid = '".$mybb->get_input('sid', MyBB::INPUT_INT)."'
	");
	$template = $db->fetch_array($query);

	// Does the template not exist?
	if(!$template)
	{
		flash_message($lang->error_invalid_template, 'error');
		admin_redirect("index.php?module=style-templates");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=style-templates&sid={$template['sid']}{$expand_str2}");
	}

	$plugins->run_hooks("admin_style_templates_revert");

	if($mybb->request_method == "post")
	{
		// Revert the template
		$db->delete_query("templates", "tid='{$template['tid']}'");

		$plugins->run_hooks("admin_style_templates_revert_commit");

		// Log admin action
		log_admin_action($template['tid'], $template['title'], $template['sid'], $template['set_title']);

		flash_message($lang->success_template_reverted, 'success');

		if($mybb->input['from'] == "diff_report")
		{
			admin_redirect("index.php?module=style-templates&action=find_updated");
		}
		else
		{
			admin_redirect("index.php?module=style-templates&sid={$template['sid']}{$expand_str2}");
		}
	}
	else
	{
		$page->output_confirm_action("index.php?module=style-templates&amp;sid={$template['sid']}{$expand_str}", $lang->confirm_template_revertion);
	}
}

if($mybb->input['sid'] && !$mybb->input['action'])
{
	if(!isset($template_sets[$mybb->input['sid']]))
	{
		flash_message($lang->error_invalid_input, 'error');
		admin_redirect("index.php?module=style-templates");
	}

	$plugins->run_hooks("admin_style_templates_set");

	$table = new Table;

	$page->add_breadcrumb_item($template_sets[$sid], "index.php?module=style-templates&amp;sid={$sid}");

	$page->output_header($lang->template_sets);

	$page->output_nav_tabs($sub_tabs, 'manage_templates');

	$table->construct_header($lang->template_set);
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	// Global Templates
	if($sid == -1)
	{
		$query = $db->simple_select("templates", "tid,title", "sid='-1'", array('order_by' => 'title', 'order_dir' => 'ASC'));
		while($template = $db->fetch_array($query))
		{
			$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
			$popup->add_item($lang->full_edit, "index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid=-1");
			$popup->add_item($lang->delete_template, "index.php?module=style-templates&amp;action=delete_template&amp;title=".urlencode($template['title'])."&amp;sid=-1&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_deletion}')");

			$table->construct_cell("<a href=\"index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid=-1\">{$template['title']}</a>");
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

	if(!isset($mybb->input['expand']))
	{
		$mybb->input['expand'] = '';
	}
	if($mybb->input['expand'] == 'all')
	{
		// If we're expanding everything, stick in the ungrouped templates in the list as well
		$expand_array = array(-1);
	}
	// Fetch Groups
	$query = $db->simple_select("templategroups", "*");

	$template_groups = array();
	while($templategroup = $db->fetch_array($query))
	{
		$templategroup['title'] = $lang->sprintf($lang->templates, htmlspecialchars_uni($lang->parse($templategroup['title'])));
		if($mybb->input['expand'] == 'all')
		{
			$expand_array[] = $templategroup['gid'];
		}
		if(in_array($templategroup['gid'], $expand_array))
		{
			$templategroup['expanded'] = 1;
		}
		$template_groups[$templategroup['prefix']] = $templategroup;
	}

	/**
	 * @param array $a
	 * @param array $b
	 *
	 * @return int
	 */
	function sort_template_groups($a, $b)
	{
		return strcasecmp($a['title'], $b['title']);
	}
	uasort($template_groups, "sort_template_groups");

	// Add the ungrouped templates group at the bottom
	$template_groups['-1'] = array(
		"prefix" => "",
		"title" => $lang->ungrouped_templates,
		"gid" => -1
	);

	// Set the template group keys to lowercase for case insensitive comparison.
	$template_groups = array_change_key_case($template_groups, CASE_LOWER);

	// Load the list of templates
	$query = $db->simple_select("templates", "*", "sid='".$mybb->get_input('sid', MyBB::INPUT_INT)."' OR sid='-2'", array('order_by' => 'sid DESC, title', 'order_dir' => 'ASC'));
	while($template = $db->fetch_array($query))
	{
		$exploded = explode("_", $template['title'], 2);

		// Set the prefix to lowercase for case insensitive comparison.
		$exploded[0] = strtolower($exploded[0]);

		if(isset($template_groups[$exploded[0]]))
		{
			$group = $exploded[0];
		}
		else
		{
			$group = -1;
		}

		$template['gid'] = -1;
		if(isset($template_groups[$exploded[0]]['gid']))
		{
			$template['gid'] = $template_groups[$exploded[0]]['gid'];
		}

		// If this template is not a master template, we simple add it to the list
		if($template['sid'] != -2)
		{
			$template['original'] = false;
			$template['modified'] = false;
			$template_groups[$group]['templates'][$template['title']] = $template;
		}
		else if(!in_array($template['gid'], $expand_array) && !isset($expand_array[-1]))
		{
			$template['original'] = true;
			$template['modified'] = false;
			$template_groups[$group]['templates'][$template['title']] = $template;

			// Save some memory!
			unset($template_groups[$group]['templates'][$template['title']]['template']);
		}
		// Otherwise, if we are down to master templates we need to do a few extra things
		else
		{
			// Master template that hasn't been customised in the set we have expanded
			if(!isset($template_groups[$group]['templates'][$template['title']]) || $template_groups[$group]['templates'][$template['title']]['template'] == $template['template'])
			{
				$template['original'] = true;
				$template_groups[$group]['templates'][$template['title']] = $template;
			}
			// Template has been modified in the set we have expanded (it doesn't match the master)
			else if($template_groups[$group]['templates'][$template['title']]['template'] != $template['template'] && $template_groups[$group]['templates'][$template['title']]['sid'] != -2)
			{
				$template_groups[$group]['templates'][$template['title']]['modified'] = true;
			}

			// Save some memory!
			unset($template_groups[$group]['templates'][$template['title']]['template']);
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

		if($group['expand_str'])
		{
			$group['expand_str'] = "&amp;expand={$group['expand_str']}";
		}

		$set_popup = '';
		if(isset($group['isdefault']) && !$group['isdefault'])
		{
			$popup = new PopupMenu("template_set_{$group['gid']}", $lang->options);
			$popup->add_item($lang->edit_template_group, "index.php?module=style-templates&amp;sid={$sid}&amp;action=edit_template_group&amp;gid={$group['gid']}{$group['expand_str']}");
			$popup->add_item($lang->delete_template_group, "index.php?module=style-templates&amp;sid={$sid}&amp;action=delete_template_group&amp;gid={$group['gid']}&amp;my_post_key={$mybb->post_code}{$group['expand_str']}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_group_delete}')");

			$set_popup = "<div class=\"float_right\">{$popup->fetch()}</div>";
		}

		if($expanded == true)
		{
			// Show templates in this group
			$table->construct_cell("{$set_popup}<strong><a href=\"index.php?module=style-templates&amp;sid={$sid}{$group['expand_str']}#group_{$group['gid']}\">{$group['title']}</a></strong>");
			$table->construct_cell("<a href=\"index.php?module=style-templates&amp;sid={$sid}{$group['expand_str']}#group_{$group['gid']}\">{$expand}</a>", array("class" => "align_center"));
			$table->construct_row(array("class" => "alt_row", "id" => "group_".$group['gid'], "name" => "group_".$group['gid']));

			if(isset($group['templates']) && count($group['templates']) > 0)
			{
				$templates = $group['templates'];
				ksort($templates);

				foreach($templates as $template)
				{
					$template['pretty_title'] = $template['title'];

					$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
					$popup->add_item($lang->full_edit, "index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid={$sid}{$expand_str}");

					if(isset($template['modified']) && $template['modified'] == true)
					{
						if($sid > 0)
						{
							$popup->add_item($lang->diff_report, "index.php?module=style-templates&amp;action=diff_report&amp;title=".urlencode($template['title'])."&amp;sid2={$sid}");

							$popup->add_item($lang->revert_to_orig, "index.php?module=style-templates&amp;action=revert&amp;title=".urlencode($template['title'])."&amp;sid={$sid}&amp;my_post_key={$mybb->post_code}{$expand_str}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_revertion}')");
						}

						$template['pretty_title'] = "<span style=\"color: green;\">{$template['title']}</span>";
					}
					// This template does not exist in the master list
					else if(isset($template['original']) && $template['original'] == false)
					{
						$popup->add_item($lang->delete_template, "index.php?module=style-templates&amp;action=delete_template&amp;title=".urlencode($template['title'])."&amp;sid={$sid}&amp;my_post_key={$mybb->post_code}{$expand_str}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_deletion}')");

						$template['pretty_title'] = "<span style=\"color: blue;\">{$template['title']}</span>";
					}

					$table->construct_cell("<span style=\"padding: 20px;\"><a href=\"index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid={$sid}{$expand_str}\" >{$template['pretty_title']}</a></span>");
					$table->construct_cell($popup->fetch(), array("class" => "align_center"));

					$table->construct_row();
				}
			}
			else
			{
				// No templates in this group
				$table->construct_cell($lang->empty_template_set, array('colspan' => 2));
				$table->construct_row();
			}
		}
		else
		{
			// Collapse template set
			$table->construct_cell("{$set_popup}<strong><a href=\"index.php?module=style-templates&amp;sid={$sid}{$group['expand_str']}#group_{$group['gid']}\">{$group['title']}</a></strong>");
 			$table->construct_cell("<a href=\"index.php?module=style-templates&amp;sid={$sid}{$group['expand_str']}#group_{$group['gid']}\">{$expand}</a>", array("class" => "align_center"));
 			$table->construct_row(array("class" => "alt_row", "id" => "group_".$group['gid'], "name" => "group_".$group['gid']));
		}
	}

	$table->output($template_sets[$sid]);

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_style_templates_start");

	$page->output_header($lang->template_sets);

	$page->output_nav_tabs($sub_tabs, 'templates');

	$themes = array();
	$query = $db->simple_select("themes", "name,tid,properties", "tid != '1'");
	while($theme = $db->fetch_array($query))
	{
		$tbits = my_unserialize($theme['properties']);
		$themes[$tbits['templateset']][$theme['tid']] = htmlspecialchars_uni($theme['name']);
	}

	$template_sets = array();
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
			$table->construct_cell("<strong><a href=\"index.php?module=style-templates&amp;sid=-1\">{$lang->global_templates}</a></strong><br /><small>{$lang->used_by_all_themes}</small>");
			$table->construct_cell("<a href=\"index.php?module=style-templates&amp;sid=-1\">{$lang->expand_templates}</a>", array("class" => "align_center"));
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
				$comma = $lang->comma;
			}
		}
		else
		{
			$used_by_note = $lang->not_used_by_any_themes;
		}

		if($set['sid'] == 1)
		{
			$actions = "<a href=\"index.php?module=style-templates&amp;sid={$set['sid']}\">{$lang->expand_templates}</a>";
		}
		else
		{
			$popup = new PopupMenu("templateset_{$set['sid']}", $lang->options);
			$popup->add_item($lang->expand_templates, "index.php?module=style-templates&amp;sid={$set['sid']}");

			if($set['sid'] != 1)
			{
				$popup->add_item($lang->edit_template_set, "index.php?module=style-templates&amp;action=edit_set&amp;sid={$set['sid']}");

				if(!$themes[$set['sid']])
				{
					$popup->add_item($lang->delete_template_set, "index.php?module=style-templates&amp;action=delete_set&amp;sid={$set['sid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_set_deletion}')");
				}
			}

			$actions = $popup->fetch();
		}

		$table->construct_cell("<strong><a href=\"index.php?module=style-templates&amp;sid={$set['sid']}\">{$set['title']}</a></strong><br /><small>{$used_by_note}</small>");
		$table->construct_cell($actions, array("class" => "align_center"));
		$table->construct_row();
	}

	$table->output($lang->template_sets);

	$page->output_footer();
}
