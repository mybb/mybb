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

$page->add_breadcrumb_item($lang->mycode, "index.php?module=config-mycode");

$plugins->run_hooks("admin_config_mycode_begin");

if($mybb->input['action'] == "toggle_status")
{
	if(!verify_post_check($mybb->input['my_post_key']))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=config-mycode");
	}

	$query = $db->simple_select("mycode", "*", "cid='".$mybb->get_input('cid', MyBB::INPUT_INT)."'");
	$mycode = $db->fetch_array($query);

	if(!$mycode['cid'])
	{
		flash_message($lang->error_invalid_mycode, 'error');
		admin_redirect("index.php?module=config-mycode");
	}

	$plugins->run_hooks("admin_config_mycode_toggle_status");

	if($mycode['active'] == 1)
	{
		$new_status = 0;
		$phrase = $lang->success_deactivated_mycode;
	}
	else
	{
		$new_status = 1;
		$phrase = $lang->success_activated_mycode;
	}
	$mycode_update = array(
		'active' => $new_status,
	);

	$plugins->run_hooks("admin_config_mycode_toggle_status_commit");

	$db->update_query("mycode", $mycode_update, "cid='".$mybb->get_input('cid', MyBB::INPUT_INT)."'");

	$cache->update_mycode();

	// Log admin action
	log_admin_action($mycode['cid'], $mycode['title'], $new_status);

	flash_message($phrase, 'success');
	admin_redirect('index.php?module=config-mycode');
}

if($mybb->input['action'] == "xmlhttp_test_mycode" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_config_mycode_xmlhttp_test_mycode_start");

	// Send no cache headers
	header("Expires: Sat, 1 Jan 2000 01:00:00 GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
	header("Content-type: text/html");

	$sandbox = test_regex($mybb->input['regex'], $mybb->input['replacement'], $mybb->input['test_value']);

	$plugins->run_hooks("admin_config_mycode_xmlhttp_test_mycode_end");

	echo $sandbox['actual'];
	exit;
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_config_mycode_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		if(!trim($mybb->input['regex']))
		{
			$errors[] = $lang->error_missing_regex;
		}

		if(!trim($mybb->input['replacement']))
		{
			$errors[] = $lang->error_missing_replacement;
		}

		if($mybb->input['test'])
		{
			$errors[] = $lang->changes_not_saved;
			$sandbox = test_regex($mybb->input['regex'], $mybb->input['replacement'], $mybb->input['test_value']);
		}

		if(!$errors)
		{
			$new_mycode = array(
				'title'	=> $db->escape_string($mybb->input['title']),
				'description' => $db->escape_string($mybb->input['description']),
				'regex' => $db->escape_string(str_replace("\x0", "", $mybb->input['regex'])),
				'replacement' => $db->escape_string($mybb->input['replacement']),
				'active' => $db->escape_string($mybb->input['active']),
				'parseorder' => $mybb->get_input('parseorder', MyBB::INPUT_INT)
			);

			$cid = $db->insert_query("mycode", $new_mycode);

			$plugins->run_hooks("admin_config_mycode_add_commit");

			$cache->update_mycode();

			// Log admin action
			log_admin_action($cid, htmlspecialchars_uni($mybb->input['title']));

			flash_message($lang->success_added_mycode, 'success');
			admin_redirect('index.php?module=config-mycode');
		}
	}

	$sub_tabs['mycode'] = array(
		'title'	=> $lang->mycode,
		'link' => "index.php?module=config-mycode",
		'description' => $lang->mycode_desc
	);

	$sub_tabs['add_new_mycode'] = array(
		'title'	=> $lang->add_new_mycode,
		'link' => "index.php?module=config-mycode&amp;action=add",
		'description' => $lang->add_new_mycode_desc
	);

	$page->extra_header .= "
	<script type=\"text/javascript\">
	var my_post_key = '".$mybb->post_code."';
	lang.mycode_sandbox_test_error = \"{$lang->mycode_sandbox_test_error}\";
	</script>";

	$page->add_breadcrumb_item($lang->add_new_mycode);
	$page->output_header($lang->custom_mycode." - ".$lang->add_new_mycode);
	$page->output_nav_tabs($sub_tabs, 'add_new_mycode');

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['active'] = 1;
	}

	$form = new Form("index.php?module=config-mycode&amp;action=add", "post", "add");
	$form_container = new FormContainer($lang->add_mycode);
	$form_container->output_row($lang->title." <em>*</em>", '', $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_description, '', $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->regular_expression." <em>*</em>", $lang->regular_expression_desc.'<br /><strong>'.$lang->example.'</strong> \[b\](.*?)\[/b\]', $form->generate_text_area('regex', $mybb->input['regex'], array('id' => 'regex')), 'regex');
	$form_container->output_row($lang->replacement." <em>*</em>", $lang->replacement_desc.'<br /><strong>'.$lang->example.'</strong> &lt;strong&gt;$1&lt;/strong&gt;', $form->generate_text_area('replacement', $mybb->input['replacement'], array('id' => 'replacement')), 'replacement');
	$form_container->output_row($lang->enabled." <em>*</em>", '', $form->generate_yes_no_radio('active', $mybb->input['active']));
	$form_container->output_row($lang->parse_order, $lang->parse_order_desc, $form->generate_numeric_field('parseorder', $mybb->input['parseorder'], array('id' => 'parseorder', 'min' => 0)), 'parseorder');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_mycode);
	$form->output_submit_wrapper($buttons);

	// Sandbox
	echo "<br />\n";
	$form_container = new FormContainer($lang->sandbox);
	$form_container->output_row($lang->sandbox_desc);
	$form_container->output_row($lang->test_value, $lang->test_value_desc, $form->generate_text_area('test_value', $mybb->input['test_value'], array('id' => 'test_value'))."<br />".$form->generate_submit_button($lang->test, array('id' => 'test', 'name' => 'test')), 'test_value');
	$form_container->output_row($lang->result_html, $lang->result_html_desc, $form->generate_text_area('result_html', $sandbox['html'], array('id' => 'result_html', 'disabled' => 1)), 'result_html');
	$form_container->output_row($lang->result_actual, $lang->result_actual_desc, "<div id=\"result_actual\">{$sandbox['actual']}</div>");
	$form_container->end();
	echo '<script type="text/javascript" src="./jscripts/mycode_sandbox.js"></script>';
	echo '<script type="text/javascript">
//<![CDATA[
$(function(){
    new MyCodeSandbox("./index.php?module=config-mycode&action=xmlhttp_test_mycode", $("#test"), $("#regex"), $("#replacement"), $("#test_value"), $("#result_html"), $("#result_actual"));
});
//]]>
</script>';

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("mycode", "*", "cid='".$mybb->get_input('cid', MyBB::INPUT_INT)."'");
	$mycode = $db->fetch_array($query);

	if(!$mycode['cid'])
	{
		flash_message($lang->error_invalid_mycode, 'error');
		admin_redirect("index.php?module=config-mycode");
	}

	$plugins->run_hooks("admin_config_mycode_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		if(!trim($mybb->input['regex']))
		{
			$errors[] = $lang->error_missing_regex;
		}

		if(!trim($mybb->input['replacement']))
		{
			$errors[] = $lang->error_missing_replacement;
		}

		if($mybb->input['test'])
		{
			$errors[] = $lang->changes_not_saved;
			$sandbox = test_regex($mybb->input['regex'], $mybb->input['replacement'], $mybb->input['test_value']);
		}

		if(!$errors)
		{
			$updated_mycode = array(
				'title'	=> $db->escape_string($mybb->input['title']),
				'description' => $db->escape_string($mybb->input['description']),
				'regex' => $db->escape_string(str_replace("\x0", "", $mybb->input['regex'])),
				'replacement' => $db->escape_string($mybb->input['replacement']),
				'active' => $db->escape_string($mybb->input['active']),
				'parseorder' => $mybb->get_input('parseorder', MyBB::INPUT_INT)
			);

			$plugins->run_hooks("admin_config_mycode_edit_commit");

			$db->update_query("mycode", $updated_mycode, "cid='".$mybb->get_input('cid', MyBB::INPUT_INT)."'");

			$cache->update_mycode();

			// Log admin action
			log_admin_action($mycode['cid'], htmlspecialchars_uni($mybb->input['title']));

			flash_message($lang->success_updated_mycode, 'success');
			admin_redirect('index.php?module=config-mycode');
		}
	}

	$sub_tabs['edit_mycode'] = array(
		'title'	=> $lang->edit_mycode,
		'link' => "index.php?module=config-mycode&amp;action=edit",
		'description' => $lang->edit_mycode_desc
	);

	$page->extra_header .= "
	<script type=\"text/javascript\">
	var my_post_key = '".$mybb->post_code."';
	lang.mycode_sandbox_test_error = \"{$lang->mycode_sandbox_test_error}\";
	</script>";

	$page->add_breadcrumb_item($lang->edit_mycode);
	$page->output_header($lang->custom_mycode." - ".$lang->edit_mycode);
	$page->output_nav_tabs($sub_tabs, 'edit_mycode');

	$form = new Form("index.php?module=config-mycode&amp;action=edit", "post", "edit");
	echo $form->generate_hidden_field('cid', $mycode['cid']);

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array_merge($mybb->input, $mycode);
	}

	$form_container = new FormContainer($lang->edit_mycode);
	$form_container->output_row($lang->title." <em>*</em>", '', $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->short_description, '', $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->regular_expression." <em>*</em>", $lang->regular_expression_desc.'<br /><strong>'.$lang->example.'</strong> \[b\](.*?)\[/b\]', $form->generate_text_area('regex', $mybb->input['regex'], array('id' => 'regex')), 'regex');
	$form_container->output_row($lang->replacement." <em>*</em>", $lang->replacement_desc.'<br /><strong>'.$lang->example.'</strong> &lt;strong&gt;$1&lt;/strong&gt;', $form->generate_text_area('replacement', $mybb->input['replacement'], array('id' => 'replacement')), 'replacement');
	$form_container->output_row($lang->enabled." <em>*</em>", '', $form->generate_yes_no_radio('active', $mybb->input['active']));
	$form_container->output_row($lang->parse_order, $lang->parse_order_desc, $form->generate_numeric_field('parseorder', $mybb->input['parseorder'], array('id' => 'parseorder', 'min' => 0)), 'parseorder');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_mycode);

	$form->output_submit_wrapper($buttons);

	// Sandbox
	echo "<br />\n";
	$form_container = new FormContainer($lang->sandbox);
	$form_container->output_row($lang->sandbox_desc);
	$form_container->output_row($lang->test_value, $lang->test_value_desc, $form->generate_text_area('test_value', $mybb->input['test_value'], array('id' => 'test_value'))."<br />".$form->generate_submit_button($lang->test, array('id' => 'test', 'name' => 'test')), 'test_value');
	$form_container->output_row($lang->result_html, $lang->result_html_desc, $form->generate_text_area('result_html', $sandbox['html'], array('id' => 'result_html', 'disabled' => 1)), 'result_html');
	$form_container->output_row($lang->result_actual, $lang->result_actual_desc, "<div id=\"result_actual\">{$sandbox['actual']}</div>");
	$form_container->end();
	echo '<script type="text/javascript" src="./jscripts/mycode_sandbox.js"></script>';
	echo '<script type="text/javascript">

$(function(){
//<![CDATA[
    new MyCodeSandbox("./index.php?module=config-mycode&action=xmlhttp_test_mycode", $("#test"), $("#regex"), $("#replacement"), $("#test_value"), $("#result_html"), $("#result_actual"));
});
//]]>
</script>';

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("mycode", "*", "cid='".$mybb->get_input('cid', MyBB::INPUT_INT)."'");
	$mycode = $db->fetch_array($query);

	if(!$mycode['cid'])
	{
		flash_message($lang->error_invalid_mycode, 'error');
		admin_redirect("index.php?module=config-mycode");
	}

	$plugins->run_hooks("admin_config_mycode_delete");

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-mycode");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query("mycode", "cid='{$mycode['cid']}'");

		$plugins->run_hooks("admin_config_mycode_delete_commit");

		$cache->update_mycode();

		// Log admin action
		log_admin_action($mycode['cid'], htmlspecialchars_uni($mycode['title']));

		flash_message($lang->success_deleted_mycode, 'success');
		admin_redirect("index.php?module=config-mycode");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-mycode&amp;action=delete&amp;cid={$mycode['cid']}", $lang->confirm_mycode_deletion);
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_config_mycode_start");

	$page->output_header($lang->custom_mycode);

	$sub_tabs['mycode'] = array(
		'title'	=> $lang->mycode,
		'link' => "index.php?module=config-mycode",
		'description' => $lang->mycode_desc
	);

	$sub_tabs['add_new_mycode'] = array(
		'title'	=> $lang->add_new_mycode,
		'link' => "index.php?module=config-mycode&amp;action=add"
	);

	$page->output_nav_tabs($sub_tabs, 'mycode');

	$table = new Table;
	$table->construct_header($lang->title);
	$table->construct_header($lang->controls, array('class' => 'align_center', 'width' => 150));

	$query = $db->simple_select("mycode", "*", "", array('order_by' => 'parseorder'));
	while($mycode = $db->fetch_array($query))
	{
		if($mycode['active'] == 1)
		{
			$phrase = $lang->deactivate_mycode;
			$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_on.png\" alt=\"({$lang->alt_enabled})\" title=\"{$lang->alt_enabled}\"  style=\"vertical-align: middle;\" /> ";
		}
		else
		{
			$phrase = $lang->activate_mycode;
			$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_off.png\" alt=\"({$lang->alt_disabled})\" title=\"{$lang->alt_disabled}\"  style=\"vertical-align: middle;\" /> ";
		}

		if($mycode['description'])
		{
			$mycode['description'] = "<small>".htmlspecialchars_uni($mycode['description'])."</small>";
		}

		$table->construct_cell("<div>{$icon}<strong><a href=\"index.php?module=config-mycode&amp;action=edit&amp;cid={$mycode['cid']}\">".htmlspecialchars_uni($mycode['title'])."</a></strong><br />{$mycode['description']}</div>");

		$popup = new PopupMenu("mycode_{$mycode['cid']}", $lang->options);
		$popup->add_item($lang->edit_mycode, "index.php?module=config-mycode&amp;action=edit&amp;cid={$mycode['cid']}");
		$popup->add_item($phrase, "index.php?module=config-mycode&amp;action=toggle_status&amp;cid={$mycode['cid']}&amp;my_post_key={$mybb->post_code}");
		$popup->add_item($lang->delete_mycode, "index.php?module=config-mycode&amp;action=delete&amp;cid={$mycode['cid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_mycode_deletion}')");
		$table->construct_cell($popup->fetch(), array('class' => 'align_center'));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_mycode, array('colspan' => 2));
		$table->construct_row();
	}

	$table->output($lang->custom_mycode);

	$page->output_footer();
}

function test_regex($regex, $replacement, $test)
{
	$array = array();
	$array['actual'] = @preg_replace("#".str_replace("\x0", "", $regex)."#si", $replacement, $test);
	$array['html'] = htmlspecialchars_uni($array['actual']);
	return $array;
}
