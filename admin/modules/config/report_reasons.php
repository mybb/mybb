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

$page->add_breadcrumb_item($lang->report_reasons, "index.php?module=config-report_reasons");

$content_types = array('post', 'profile', 'reputation');

$content_types = $plugins->run_hooks("report_content_types", $content_types);

$plugins->run_hooks("admin_config_report_reasons_begin");

if($mybb->input['action'] == "add")
{
	cast_content_inputs();

	$plugins->run_hooks("admin_config_report_reasons_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		if($mybb->input['extra'] != 0 && $mybb->input['extra'] != 1)
		{
			$errors[] = $lang->error_missing_extra;
		}

		if(!$errors)
		{
			if($mybb->input['appliesto'] != 'all')
			{
				$appliesto = array();
				asort($content_types);
				foreach($content_types as $content)
				{
					if($mybb->input["appliesto_{$content}"] == 1)
					{
						$appliesto[] = $content;
					}
				}
				$appliesto = implode(",", $appliesto);
			}
			else
			{
				$appliesto = 'all';
			}

			$new_reason = array(
				"title" => $db->escape_string($mybb->input['title']),
				"appliesto" => $db->escape_string($appliesto),
				"extra" => $mybb->input['extra']
			);
			$rid = $db->insert_query("reportreasons", $new_reason);

			$plugins->run_hooks("admin_config_report_reasons_add_commit");

			$cache->update_reportreasons();

			// Log admin action
			log_admin_action($rid, $mybb->input['title']);

			flash_message($lang->success_reason_created, 'success');
			admin_redirect("index.php?module=config-report_reasons");
		}
	}

	$page->add_breadcrumb_item($lang->add_new_reason);
	$page->output_header($lang->report_reasons." - ".$lang->add_new_reason);

	$sub_tabs['report_reasons'] = array(
		'title' => $lang->report_reasons,
		'link' => "index.php?module=config-report_reasons"
	);

	$sub_tabs['add_new_reason'] = array(
		'title' => $lang->add_new_reason,
		'link' => "index.php?module=config-report_reasons&amp;action=add",
		'description' => $lang->add_new_reason_desc
	);

	$page->output_nav_tabs($sub_tabs, 'add_new_reason');

	$form = new Form("index.php?module=config-report_reasons&amp;action=add", "post", "add");

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['extra'] = 0;
	}

	$form_container = new FormContainer($lang->add_new_reason);
	$form_container->output_row($lang->reason_title." <em>*</em>", $lang->reason_title_desc, $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->applies_to." <em>*</em>", $lang->applies_to_desc, generate_content_select());
	$form_container->output_row($lang->requires_extra." <em>*</em>", $lang->requires_extra_desc, $form->generate_yes_no_radio('extra', $mybb->input['extra']));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_reason);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("reportreasons", "*", "rid='".$mybb->get_input('rid', MyBB::INPUT_INT)."'");
	$reason = $db->fetch_array($query);

	if(!$reason['rid'])
	{
		flash_message($lang->error_invalid_reason, 'error');
		admin_redirect("index.php?module=config-report_reasons");
	}
	elseif($reason['rid'] == 1)
	{
		flash_message($lang->error_cannot_modify_reason, 'error');
		admin_redirect("index.php?module=config-report_reasons");
	}

	cast_content_inputs();

	$plugins->run_hooks("admin_config_report_reasons_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}

		if($mybb->input['extra'] != 0 && $mybb->input['extra'] != 1)
		{
			$errors[] = $lang->error_missing_extra;
		}

		if(!$errors)
		{
			if($mybb->input['appliesto'] != 'all')
			{
				$appliesto = array();
				asort($content_types);
				foreach($content_types as $content)
				{
					if($mybb->input["appliesto_{$content}"] == 1)
					{
						$appliesto[] = $content;
					}
				}
				$appliesto = implode(",", $appliesto);
			}
			else
			{
				$appliesto = 'all';
			}

			$updated_reason = array(
				"title" => $db->escape_string($mybb->input['title']),
				"appliesto" => $db->escape_string($appliesto),
				"extra" => $mybb->input['extra']
			);

			$plugins->run_hooks("admin_config_report_reasons_edit_commit");

			$db->update_query("reportreasons", $updated_reason, "rid='{$reason['rid']}'");

			$cache->update_reportreasons();

			// Log admin action
			log_admin_action($reason['rid'], $mybb->input['title']);

			flash_message($lang->success_reason_updated, 'success');
			admin_redirect("index.php?module=config-report_reasons");
		}
	}

	$page->add_breadcrumb_item($lang->edit_reason);
	$page->output_header($lang->report_reasons." - ".$lang->edit_reason);

	$sub_tabs['edit_reason'] = array(
		'title' => $lang->edit_reason,
		'link' => "index.php?module=config-report_reasons&amp;action=edit&amp;rid={$reason['rid']}",
		'description' => $lang->edit_reason_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_reason');

	$form = new Form("index.php?module=config-report_reasons&amp;action=edit&amp;rid={$reason['rid']}", "post", "add");

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = $reason;
		$appliesto = explode(",", $reason['appliesto']);
		foreach($appliesto as $content)
		{
			$mybb->input["appliesto_{$content}"] = 1;
		}
	}

	$form_container = new FormContainer($lang->add_new_reason);
	$form_container->output_row($lang->reason_title." <em>*</em>", $lang->reason_title_desc, $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->applies_to." <em>*</em>", $lang->applies_to_desc, generate_content_select());
	$form_container->output_row($lang->requires_extra." <em>*</em>", $lang->requires_extra_desc, $form->generate_yes_no_radio('extra', $mybb->input['extra']));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_reason);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=config-report_reasons");
	}

	$query = $db->simple_select("reportreasons", "*", "rid='".$mybb->get_input('rid', MyBB::INPUT_INT)."'");
	$reason = $db->fetch_array($query);

	if(!$reason['rid'])
	{
		flash_message($lang->error_invalid_reason, 'error');
		admin_redirect("index.php?module=config-report_reasons");
	}
	elseif($reason['rid'] == 1)
	{
		flash_message($lang->error_cannot_delete_reason, 'error');
		admin_redirect("index.php?module=config-report_reasons");
	}

	$plugins->run_hooks("admin_config_report_reasons_delete");

	if($mybb->request_method == "post")
	{

		// Change the reason of associated reports to Other and carry over the title
		$updated_report = array(
			'reasonid' => 1,
			'reason' => $db->escape_string($reason['title'])
		);
		$db->update_query("reportedcontent", $updated_report, "reasonid='{$reason['rid']}'");

		$db->delete_query("reportreasons", "rid='{$reason['rid']}'");

		$plugins->run_hooks("admin_config_report_reasons_delete_commit");

		// Log admin action
		log_admin_action($reason['rid'], $reason['title']);

		flash_message($lang->success_reason_deleted, 'success');
		admin_redirect("index.php?module=config-report_reasons");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-report_reasons&amp;action=delete&amp;rid={$reason['rid']}", $lang->confirm_reason_deletion);
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_config_report_reasons_start");

	if($mybb->request_method == "post")
	{
		if(!empty($mybb->input['disporder']))
		{
			foreach($mybb->input['disporder'] as $rid => $order)
			{
				$db->update_query("reportreasons", array('disporder' => (int)$order), "rid='".(int)$rid."'");
			}

			$plugins->run_hooks("admin_config_report_reasons_start_commit");

			//$cache->update_reportreasons();

			flash_message($lang->success_reasons_disporder_updated, 'success');
			admin_redirect("index.php?module=config-report_reasons");
		}
	}

	$page->output_header($lang->report_reasons);

	$sub_tabs['report_reasons'] = array(
		'title' => $lang->report_reasons,
		'link' => "index.php?module=config-report_reasons",
		'description' => $lang->report_reasons_desc
	);
	$sub_tabs['add_new_reason'] = array(
		'title' => $lang->add_new_reason,
		'link' => "index.php?module=config-report_reasons&amp;action=add",
	);

	$page->output_nav_tabs($sub_tabs, 'report_reasons');

	$form = new Form("index.php?module=config-report_reasons", "post", "reasons");

	$form_container = new FormContainer($lang->report_reasons);
	$form_container->output_row_header($lang->reason_title);
	$form_container->output_row_header($lang->applies_to, array("width" => "35%"));
	$form_container->output_row_header($lang->extra_comment, array("width" => "10%", "class" => "align_center"));
	$form_container->output_row_header($lang->order, array("width" => "5%", "class" => "align_center"));
	$form_container->output_row_header($lang->controls, array("class" => "align_center", "width" => 150));

	$query = $db->simple_select("reportreasons", "*", "", array('order_by' => 'disporder'));
	while($reasons = $db->fetch_array($query))
	{
		$reasons['title'] = $lang->parse($reasons['title']);

		$reasons['appliesto'] = explode(",", $reasons['appliesto']);

		$appliesto = array();
		foreach($reasons['appliesto'] as $content)
		{
			$key = "report_content_".$content;
			$appliesto[] = $lang->$key;
		}
		$reasons['appliesto'] = implode(", ", $appliesto);

		if($reasons['extra'] == 1)
		{
			$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_on.png\" alt=\"({$lang->yes})\" title=\"{$lang->yes}\"  style=\"vertical-align: middle;\" /> ";
		}
		else
		{
			$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_off.png\" alt=\"({$lang->no})\" title=\"{$lang->no}\"  style=\"vertical-align: middle;\" /> ";
		}

		$form_container->output_cell(htmlspecialchars_uni($reasons['title']));
		$form_container->output_cell(htmlspecialchars_uni($reasons['appliesto']));
		$form_container->output_cell("<div>{$icon}</div>", array("class" => "align_center"));
		$form_container->output_cell("<input type=\"text\" name=\"disporder[{$reasons['rid']}]\" value=\"{$reasons['disporder']}\" class=\"text_input align_center\" style=\"width: 80%;\" />", array("class" => "align_center"));
		$popup = new PopupMenu("reasons_{$reasons['rid']}", $lang->options);
		$popup->add_item($lang->edit_reason, "index.php?module=config-report_reasons&amp;action=edit&amp;rid={$reasons['rid']}");
		$popup->add_item($lang->delete_reason, "index.php?module=config-report_reasons&amp;action=delete&amp;rid={$reasons['rid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_reason_deletion}')");
		$form_container->output_cell($popup->fetch(), array("class" => "align_center"));
		$form_container->construct_row();
	}

	if($form_container->num_rows() == 0)
	{
		$form_container->construct_cell($lang->no_report_reasons, array('colspan' => 5));
		$form_container->construct_row();
	}

	$form_container->end();

	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->update_reasons_order);
	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

function generate_content_select()
{
	global $mybb, $lang;

	$checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['appliesto'] == 'all')
	{
		$checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['appliesto'] == '')
	{
		$checked['none'] = 'checked="checked"';
	}
	else
	{
		$checked['custom'] = 'checked="checked"';
	}

	print_selection_javascript();

	return "<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
					<dt><label style=\"display: block;\"><input type=\"radio\" name=\"appliesto\" value=\"all\" {$checked['all']} class=\"appliesto_forums_groups_check\" onclick=\"checkAction('appliesto');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_content}</strong></label></dt>
					<dt><label style=\"display: block;\"><input type=\"radio\" name=\"appliesto\" value=\"custom\" {$checked['custom']} class=\"appliesto_forums_groups_check\" onclick=\"checkAction('appliesto');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_content}</strong></label></dt>
					<dd style=\"margin-top: 4px;\" id=\"appliesto_forums_groups_custom\" class=\"appliesto_forums_groups\">
						<table cellpadding=\"4\">
							<tr>
								<td valign=\"top\"><small>{$lang->content_colon}</small></td>
								<td>".implode("<br />", generate_content_choices())."</td>
							</tr>
						</table>
					</dd>
					<dt><label style=\"display: block;\"><input type=\"radio\" name=\"appliesto\" value=\"none\" {$checked['none']} class=\"appliesto_forums_groups_check\" onclick=\"checkAction('appliesto');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
				</dl>
				<script type=\"text/javascript\">
					checkAction('appliesto');
				</script>";
}

function generate_content_choices()
{
	global $mybb, $lang, $form, $content_types;

	asort($content_types);

	$content_choices = array();
	foreach($content_types as $content)
	{
		$key = "report_content_{$content}";
		$content_choices[] = $form->generate_check_box("appliesto_{$content}", 1, $lang->$key, array('id' => "appliesto_{$content}", 'checked' => $mybb->input["appliesto_{$content}"]));
	}

	return $content_choices;
}

function cast_content_inputs()
{
	global $mybb, $content_types;

	asort($content_types);

	foreach($content_types as $content)
	{
		$key = "appliesto_{$content}";
		$mybb->input[$key] = $mybb->get_input($key, MyBB::INPUT_INT);
	}

	$mybb->input['extra'] = $mybb->get_input('extra', MyBB::INPUT_INT);
}