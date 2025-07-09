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

$page->add_breadcrumb_item($lang->custom_profile_fields, "index.php?module=config-profile_fields");

$plugins->run_hooks("admin_config_profile_fields_begin");

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_config_profile_fields_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = $lang->error_missing_description;
		}

		if(!trim($mybb->input['fieldtype']))
		{
			$errors[] = $lang->error_missing_fieldtype;
		}

		if(!$errors)
		{
			$type = $mybb->input['fieldtype'];
			$options = preg_replace("#(\r\n|\r|\n)#s", "\n", trim($mybb->input['options']));
			if($type != "text" && $type != "textarea")
			{
				$thing = "$type\n$options";
			}
			else
			{
				$thing = $type;
			}

			foreach(array('viewableby', 'editableby') as $key)
			{
				if($mybb->input[$key] == 'all')
				{
					$mybb->input[$key] = -1;
				}
				elseif($mybb->input[$key] == 'custom')
				{
					if(isset($mybb->input['select'][$key]) && is_array($mybb->input['select'][$key]))
					{
						foreach($mybb->input['select'][$key] as &$val)
						{
							$val = (int)$val;
						}
						unset($val);

						$mybb->input[$key] = implode(',', (array)$mybb->input['select'][$key]);
					}
					else
					{
						$mybb->input[$key] = '';
					}
				}
				else
				{
					$mybb->input[$key] = '';
				}
			}

			$new_profile_field = array(
				"name" => $db->escape_string($mybb->input['name']),
				"description" => $db->escape_string($mybb->input['description']),
				"disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT),
				"type" => $db->escape_string($thing),
				"regex" => $db->escape_string($mybb->input['regex']),
				"length" => $mybb->get_input('length', MyBB::INPUT_INT),
				"maxlength" => $mybb->get_input('maxlength', MyBB::INPUT_INT),
				"required" => $mybb->get_input('required', MyBB::INPUT_INT),
				"registration" => $mybb->get_input('registration', MyBB::INPUT_INT),
				"profile" => $mybb->get_input('profile', MyBB::INPUT_INT),
				"viewableby" => $db->escape_string($mybb->input['viewableby']),
				"editableby" => $db->escape_string($mybb->input['editableby']),
				"postbit" => $mybb->get_input('postbit', MyBB::INPUT_INT),
				"postnum" => $mybb->get_input('postnum', MyBB::INPUT_INT),
				"allowhtml" => $mybb->get_input('allowhtml', MyBB::INPUT_INT),
				"allowmycode" => $mybb->get_input('allowmycode', MyBB::INPUT_INT),
				"allowsmilies" => $mybb->get_input('allowsmilies', MyBB::INPUT_INT),
				"allowimgcode" => $mybb->get_input('allowimgcode', MyBB::INPUT_INT),
				"allowvideocode" => $mybb->get_input('allowvideocode', MyBB::INPUT_INT)
			);

			$fid = $db->insert_query("profilefields", $new_profile_field);

			$db->write_query("ALTER TABLE ".TABLE_PREFIX."userfields ADD fid{$fid} TEXT");

			$plugins->run_hooks("admin_config_profile_fields_add_commit");

			$cache->update_profilefields();

			// Log admin action
			log_admin_action($fid, $mybb->input['name']);

			flash_message($lang->success_profile_field_added, 'success');
			admin_redirect("index.php?module=config-profile_fields");
		}
	}

	$page->add_breadcrumb_item($lang->add_new_profile_field);
	$page->output_header($lang->custom_profile_fields." - ".$lang->add_new_profile_field);

	$sub_tabs['custom_profile_fields'] = array(
		'title' => $lang->custom_profile_fields,
		'link' => "index.php?module=config-profile_fields"
	);

	$sub_tabs['add_profile_field'] = array(
		'title' => $lang->add_new_profile_field,
		'link' => "index.php?module=config-profile_fields&amp;action=add",
		'description' => $lang->add_new_profile_field_desc
	);

	$page->output_nav_tabs($sub_tabs, 'add_profile_field');
	$form = new Form("index.php?module=config-profile_fields&amp;action=add", "post", "add");

	if($errors)
	{
		switch($mybb->input['viewableby'])
		{
			case 'all':
				$mybb->input['viewableby'] = -1;
				break;
			case 'custom':
				$mybb->input['viewableby'] = implode(',', (array)$mybb->input['select']['viewableby']);
				break;
			default:
				$mybb->input['viewableby'] = '';
				break;
		}

		switch($mybb->input['editableby'])
		{
			case 'all':
				$mybb->input['editableby'] = -1;
				break;
			case 'custom':
				$mybb->input['editableby'] = implode(',', (array)$mybb->input['select']['editableby']);
				break;
			default:
				$mybb->input['editableby'] = '';
				break;
		}

		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['fieldtype'] = 'textbox';
		$mybb->input['required'] = 0;
		$mybb->input['registration'] = 0;
		$mybb->input['editable'] = 1;
		$mybb->input['hidden'] = 0;
		$mybb->input['postbit'] = 0;
	}

	if(empty($mybb->input['viewableby']))
	{
		$mybb->input['viewableby'] = '';
	}

	if(empty($mybb->input['editableby']))
	{
		$mybb->input['editableby'] = '';
	}

	$form_container = new FormContainer($lang->add_new_profile_field);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->get_input('name'), array('id' => 'name')), 'name');
	$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->get_input('description'), array('id' => 'description')), 'description');
	$select_list = array(
		"text" => $lang->text,
		"textarea" => $lang->textarea,
		"select" => $lang->select,
		"multiselect" => $lang->multiselect,
		"radio" => $lang->radio,
		"checkbox" => $lang->checkbox
	);
	$form_container->output_row($lang->field_type." <em>*</em>", $lang->field_type_desc, $form->generate_select_box('fieldtype', $select_list, $mybb->get_input('fieldtype'), array('id' => 'fieldtype')), 'fieldtype');
	$form_container->output_row($lang->field_regex, $lang->field_regex_desc, $form->generate_text_box('regex', $mybb->get_input('regex'), array('id' => 'regex')), 'regex', array(), array('id' => 'row_regex'));
	$form_container->output_row($lang->maximum_length, $lang->maximum_length_desc, $form->generate_numeric_field('maxlength', $mybb->get_input('maxlength'), array('id' => 'maxlength', 'min' => 0)), 'maxlength', array(), array('id' => 'row_maxlength'));
	$form_container->output_row($lang->field_length, $lang->field_length_desc, $form->generate_numeric_field('length', $mybb->get_input('length'), array('id' => 'length', 'min' => 0)), 'length', array(), array('id' => 'row_fieldlength'));
	$form_container->output_row($lang->selectable_options, $lang->selectable_options_desc, $form->generate_text_area('options', $mybb->get_input('options'), array('id' => 'options')), 'options', array(), array('id' => 'row_options'));
	$form_container->output_row($lang->min_posts_enabled, $lang->min_posts_enabled_desc, $form->generate_numeric_field('postnum', $mybb->get_input('postnum'), array('id' => 'postnum', 'min' => 0)), 'postnum');
	$form_container->output_row($lang->display_order." <em>*</em>", $lang->display_order_desc, $form->generate_numeric_field('disporder', $mybb->get_input('disporder'), array('id' => 'disporder', 'min' => 0)), 'disporder');
	$form_container->output_row($lang->required." <em>*</em>", $lang->required_desc, $form->generate_yes_no_radio('required', $mybb->get_input('required')));
	$form_container->output_row($lang->show_on_registration." <em>*</em>", $lang->show_on_registration_desc, $form->generate_yes_no_radio('registration', $mybb->get_input('registration')));
	$form_container->output_row($lang->display_on_profile." <em>*</em>", $lang->display_on_profile_desc, $form->generate_yes_no_radio('profile', $mybb->get_input('profile')));
	$form_container->output_row($lang->display_on_postbit." <em>*</em>", $lang->display_on_postbit_desc, $form->generate_yes_no_radio('postbit', $mybb->get_input('postbit')));

	$selected_values = '';
	if($mybb->input['viewableby'] != '' && $mybb->input['viewableby'] != -1)
	{
		$selected_values = explode(',', $mybb->get_input('viewableby'));

		foreach($selected_values as &$value)
		{
			$value = (int)$value;
		}
		unset($value);
	}

	$group_checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['viewableby'] == -1)
	{
		$group_checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['viewableby'] != '')
	{
		$group_checked['custom'] = 'checked="checked"';
	}
	else
	{
		$group_checked['none'] = 'checked="checked"';
	}

	print_selection_javascript();

	$select_code = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"viewableby\" value=\"all\" {$group_checked['all']} class=\"viewableby_forums_groups_check\" onclick=\"checkAction('viewableby');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_groups}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"viewableby\" value=\"custom\" {$group_checked['custom']} class=\"viewableby_forums_groups_check\" onclick=\"checkAction('viewableby');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_groups}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"viewableby_forums_groups_custom\" class=\"viewableby_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->groups_colon}</small></td>
					<td>".$form->generate_group_select('select[viewableby][]', $selected_values, array('id' => 'viewableby', 'multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"viewableby\" value=\"none\" {$group_checked['none']} class=\"viewableby_forums_groups_check\" onclick=\"checkAction('viewableby');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('viewableby');
	</script>";
	$form_container->output_row($lang->viewableby, $lang->viewableby_desc, $select_code, '', array(), array('id' => 'row_viewableby'));

	$selected_values = '';
	if($mybb->input['editableby'] != '' && $mybb->input['editableby'] != -1)
	{
		$selected_values = explode(',', $mybb->get_input('editableby'));

		foreach($selected_values as &$value)
		{
			$value = (int)$value;
		}
		unset($value);
	}

	$group_checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['editableby'] == -1)
	{
		$group_checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['editableby'] != '')
	{
		$group_checked['custom'] = 'checked="checked"';
	}
	else
	{
		$group_checked['none'] = 'checked="checked"';
	}

	print_selection_javascript();

	$select_code = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"editableby\" value=\"all\" {$group_checked['all']} class=\"editableby_forums_groups_check\" onclick=\"checkAction('editableby');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_groups}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"editableby\" value=\"custom\" {$group_checked['custom']} class=\"editableby_forums_groups_check\" onclick=\"checkAction('editableby');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_groups}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"editableby_forums_groups_custom\" class=\"editableby_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->groups_colon}</small></td>
					<td>".$form->generate_group_select('select[editableby][]', $selected_values, array('id' => 'editableby', 'multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"editableby\" value=\"none\" {$group_checked['none']} class=\"editableby_forums_groups_check\" onclick=\"checkAction('editableby');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('editableby');
	</script>";
	$form_container->output_row($lang->editableby, $lang->editableby_desc, $select_code, '', array(), array('id' => 'row_editableby'));

	$parser_options = array(
		$form->generate_check_box('allowhtml', 1, $lang->parse_allowhtml, array('checked' => $mybb->get_input('allowhtml'), 'id' => 'allowhtml')),
		$form->generate_check_box('allowmycode', 1, $lang->parse_allowmycode, array('checked' => $mybb->get_input('allowmycode'), 'id' => 'allowmycode')),
		$form->generate_check_box('allowsmilies', 1, $lang->parse_allowsmilies, array('checked' => $mybb->get_input('allowsmilies'), 'id' => 'allowsmilies')),
		$form->generate_check_box('allowimgcode', 1, $lang->parse_allowimgcode, array('checked' => $mybb->get_input('allowimgcode'), 'id' => 'allowimgcode')),
		$form->generate_check_box('allowvideocode', 1, $lang->parse_allowvideocode, array('checked' => $mybb->get_input('allowvideocode'), 'id' => 'allowvideocode'))
	);
	$form_container->output_row($lang->parser_options, '', implode('<br />', $parser_options), '', array(), array('id' => 'row_parser_options'));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_profile_field);

	$form->output_submit_wrapper($buttons);
	$form->end();

	echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
	<script type="text/javascript">
		$(function() {
				new Peeker($("#fieldtype"), $("#row_maxlength, #row_regex, #row_parser_options"), /text|textarea/, false);
				new Peeker($("#fieldtype"), $("#row_fieldlength"), /select|multiselect/, false);
				new Peeker($("#fieldtype"), $("#row_options"), /select|radio|checkbox/, false);
				// Add a star to the extra row since the "extra" is required if the box is shown
				add_star("row_maxlength");
				add_star("row_fieldlength");
				add_star("row_options");
		});
	</script>';

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("profilefields", "*", "fid = '".$mybb->get_input('fid', MyBB::INPUT_INT)."'");
	$profile_field = $db->fetch_array($query);

	if(!$profile_field['fid'])
	{
		flash_message($lang->error_invalid_fid, 'error');
		admin_redirect("index.php?module=config-profile_fields");
	}

	$plugins->run_hooks("admin_config_profile_fields_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = $lang->error_missing_description;
		}

		if(!trim($mybb->input['fieldtype']))
		{
			$errors[] = $lang->error_missing_fieldtype;
		}

		$type = $mybb->input['fieldtype'];
		$options = preg_replace("#(\r\n|\r|\n)#s", "\n", trim($mybb->input['options']));
		if($type != "text" && $type != "textarea")
		{
			$type = "$type\n$options";
		}

		if(!$errors)
		{
			foreach(array('viewableby', 'editableby') as $key)
			{
				if($mybb->input[$key] == 'all')
				{
					$mybb->input[$key] = -1;
				}
				elseif($mybb->input[$key] == 'custom')
				{
					if(isset($mybb->input['select'][$key]) && is_array($mybb->input['select'][$key]))
					{
						foreach($mybb->input['select'][$key] as &$val)
						{
							$val = (int)$val;
						}
						unset($val);

						$mybb->input[$key] = implode(',', $mybb->input['select'][$key]);
					}
					else
					{
						$mybb->input[$key] = '';
					}
				}
				else
				{
					$mybb->input[$key] = '';
				}
			}

			$updated_profile_field = array(
				"name" => $db->escape_string($mybb->input['name']),
				"description" => $db->escape_string($mybb->input['description']),
				"disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT),
				"type" => $db->escape_string($type),
				"regex" => $db->escape_string($mybb->input['regex']),
				"length" => $mybb->get_input('length', MyBB::INPUT_INT),
				"maxlength" => $mybb->get_input('maxlength', MyBB::INPUT_INT),
				"required" => $mybb->get_input('required', MyBB::INPUT_INT),
				"registration" => $mybb->get_input('registration', MyBB::INPUT_INT),
				"profile" => $mybb->get_input('profile', MyBB::INPUT_INT),
				"viewableby" => $db->escape_string($mybb->input['viewableby']),
				"editableby" => $db->escape_string($mybb->input['editableby']),
				"postbit" => $mybb->get_input('postbit', MyBB::INPUT_INT),
				"postnum" => $mybb->get_input('postnum', MyBB::INPUT_INT),
				"allowhtml" => $mybb->get_input('allowhtml', MyBB::INPUT_INT),
				"allowmycode" => $mybb->get_input('allowmycode', MyBB::INPUT_INT),
				"allowsmilies" => $mybb->get_input('allowsmilies', MyBB::INPUT_INT),
				"allowimgcode" => $mybb->get_input('allowimgcode', MyBB::INPUT_INT),
				"allowvideocode" => $mybb->get_input('allowvideocode', MyBB::INPUT_INT)
			);

			$plugins->run_hooks("admin_config_profile_fields_edit_commit");

			$db->update_query("profilefields", $updated_profile_field, "fid='{$profile_field['fid']}'");

			$cache->update_profilefields();

			// Log admin action
			log_admin_action($profile_field['fid'], $mybb->input['name']);

			flash_message($lang->success_profile_field_saved, 'success');
			admin_redirect("index.php?module=config-profile_fields");
		}
	}

	$page->add_breadcrumb_item($lang->edit_profile_field);
	$page->output_header($lang->custom_profile_fields." - ".$lang->edit_profile_field);

	$sub_tabs['edit_profile_field'] = array(
		'title' => $lang->edit_profile_field,
		'link' => "index.php?module=config-profile_fields&amp;action=edit&amp;fid={$profile_field['fid']}",
		'description' => $lang->edit_profile_field_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_profile_field');
	$form = new Form("index.php?module=config-profile_fields&amp;action=edit", "post", "edit");


	echo $form->generate_hidden_field("fid", $profile_field['fid']);

	if($errors)
	{
		switch($mybb->input['viewableby'])
		{
			case 'all':
				$mybb->input['viewableby'] = -1;
				break;
			case 'custom':
				$mybb->input['viewableby'] = implode(',', (array)$mybb->input['select']['viewableby']);
				break;
			default:
				$mybb->input['viewableby'] = '';
				break;
		}

		switch($mybb->input['editableby'])
		{
			case 'all':
				$mybb->input['editableby'] = -1;
				break;
			case 'custom':
				$mybb->input['editableby'] = implode(',', (array)$mybb->input['select']['editableby']);
				break;
			default:
				$mybb->input['editableby'] = '';
				break;
		}

		$page->output_inline_error($errors);
	}
	else
	{
		$type = explode("\n", $profile_field['type'], "2");

		$mybb->input = $profile_field;
		$mybb->input['fieldtype'] = $type[0];
		$mybb->input['options'] = isset($type[1]) ? $type[1] : null;
	}

	if(empty($mybb->input['viewableby']))
	{
		$mybb->input['viewableby'] = '';
	}

	if(empty($mybb->input['editableby']))
	{
		$mybb->input['editableby'] = '';
	}

	$form_container = new FormContainer($lang->edit_profile_field);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$select_list = array(
		"text" => $lang->text,
		"textarea" => $lang->textarea,
		"select" => $lang->select,
		"multiselect" => $lang->multiselect,
		"radio" => $lang->radio,
		"checkbox" => $lang->checkbox
	);
	$form_container->output_row($lang->field_type." <em>*</em>", $lang->field_type_desc, $form->generate_select_box('fieldtype', $select_list, $mybb->input['fieldtype'], array('id' => 'fieldtype')), 'fieldtype');
	$form_container->output_row($lang->field_regex, $lang->field_regex_desc, $form->generate_text_box('regex', $mybb->input['regex'], array('id' => 'regex')), 'regex', array(), array('id' => 'row_regex'));
	$form_container->output_row($lang->maximum_length, $lang->maximum_length_desc, $form->generate_numeric_field('maxlength', $mybb->input['maxlength'], array('id' => 'maxlength', 'min' => 0)), 'maxlength', array(), array('id' => 'row_maxlength'));
	$form_container->output_row($lang->field_length, $lang->field_length_desc, $form->generate_numeric_field('length', $mybb->input['length'], array('id' => 'length', 'min' => 0)), 'length', array(), array('id' => 'row_fieldlength'));
	$form_container->output_row($lang->selectable_options, $lang->selectable_options_desc, $form->generate_text_area('options', $mybb->input['options'], array('id' => 'options')), 'options', array(), array('id' => 'row_options'));
	$form_container->output_row($lang->min_posts_enabled, $lang->min_posts_enabled_desc, $form->generate_numeric_field('postnum', $mybb->input['postnum'], array('id' => 'postnum', 'min' => 0)), 'postnum');
	$form_container->output_row($lang->display_order." <em>*</em>", $lang->display_order_desc, $form->generate_numeric_field('disporder', $mybb->input['disporder'], array('id' => 'disporder', 'min' => 0)), 'disporder');
	$form_container->output_row($lang->required." <em>*</em>", $lang->required_desc, $form->generate_yes_no_radio('required', $mybb->input['required']));
	$form_container->output_row($lang->show_on_registration." <em>*</em>", $lang->show_on_registration_desc, $form->generate_yes_no_radio('registration', $mybb->input['registration']));
	$form_container->output_row($lang->display_on_profile." <em>*</em>", $lang->display_on_profile_desc, $form->generate_yes_no_radio('profile', $mybb->input['profile']));
	$form_container->output_row($lang->display_on_postbit." <em>*</em>", $lang->display_on_postbit_desc, $form->generate_yes_no_radio('postbit', $mybb->input['postbit']));

	$selected_values = '';
	if($mybb->input['viewableby'] != '' && $mybb->input['viewableby'] != -1)
	{
		$selected_values = explode(',', $mybb->get_input('viewableby'));

		foreach($selected_values as &$value)
		{
			$value = (int)$value;
		}
		unset($value);
	}

	$group_checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['viewableby'] == -1)
	{
		$group_checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['viewableby'] != '')
	{
		$group_checked['custom'] = 'checked="checked"';
	}
	else
	{
		$group_checked['none'] = 'checked="checked"';
	}

	print_selection_javascript();

	$select_code = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"viewableby\" value=\"all\" {$group_checked['all']} class=\"viewableby_forums_groups_check\" onclick=\"checkAction('viewableby');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_groups}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"viewableby\" value=\"custom\" {$group_checked['custom']} class=\"viewableby_forums_groups_check\" onclick=\"checkAction('viewableby');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_groups}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"viewableby_forums_groups_custom\" class=\"viewableby_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->groups_colon}</small></td>
					<td>".$form->generate_group_select('select[viewableby][]', $selected_values, array('id' => 'viewableby', 'multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"viewableby\" value=\"none\" {$group_checked['none']} class=\"viewableby_forums_groups_check\" onclick=\"checkAction('viewableby');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('viewableby');
	</script>";
	$form_container->output_row($lang->viewableby, $lang->viewableby_desc, $select_code, '', array(), array('id' => 'row_viewableby'));

	$selected_values = '';
	if($mybb->input['editableby'] != '' && $mybb->input['editableby'] != -1)
	{
		$selected_values = explode(',', $mybb->get_input('editableby'));

		foreach($selected_values as &$value)
		{
			$value = (int)$value;
		}
		unset($value);
	}

	$group_checked = array('all' => '', 'custom' => '', 'none' => '');
	if($mybb->input['editableby'] == -1)
	{
		$group_checked['all'] = 'checked="checked"';
	}
	elseif($mybb->input['editableby'] != '')
	{
		$group_checked['custom'] = 'checked="checked"';
	}
	else
	{
		$group_checked['none'] = 'checked="checked"';
	}

	print_selection_javascript();

	$select_code = "
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"editableby\" value=\"all\" {$group_checked['all']} class=\"editableby_forums_groups_check\" onclick=\"checkAction('editableby');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_groups}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"editableby\" value=\"custom\" {$group_checked['custom']} class=\"editableby_forums_groups_check\" onclick=\"checkAction('editableby');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_groups}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"editableby_forums_groups_custom\" class=\"editableby_forums_groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->groups_colon}</small></td>
					<td>".$form->generate_group_select('select[editableby][]', $selected_values, array('id' => 'editableby', 'multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"editableby\" value=\"none\" {$group_checked['none']} class=\"editableby_forums_groups_check\" onclick=\"checkAction('editableby');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
		checkAction('editableby');
	</script>";
	$form_container->output_row($lang->editableby, $lang->editableby_desc, $select_code, '', array(), array('id' => 'row_editableby'));

	$parser_options = array(
		$form->generate_check_box('allowhtml', 1, $lang->parse_allowhtml, array('checked' => $mybb->input['allowhtml'], 'id' => 'allowhtml')),
		$form->generate_check_box('allowmycode', 1, $lang->parse_allowmycode, array('checked' => $mybb->input['allowmycode'], 'id' => 'allowmycode')),
		$form->generate_check_box('allowsmilies', 1, $lang->parse_allowsmilies, array('checked' => $mybb->input['allowsmilies'], 'id' => 'allowsmilies')),
		$form->generate_check_box('allowimgcode', 1, $lang->parse_allowimgcode, array('checked' => $mybb->input['allowimgcode'], 'id' => 'allowimgcode')),
		$form->generate_check_box('allowvideocode', 1, $lang->parse_allowvideocode, array('checked' => $mybb->input['allowvideocode'], 'id' => 'allowvideocode'))
	);
	$form_container->output_row($lang->parser_options, '', implode('<br />', $parser_options), '', array(), array('id' => 'row_parser_options'));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_profile_field);

	$form->output_submit_wrapper($buttons);
	$form->end();

	echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
	<script type="text/javascript">
		$(function() {
				new Peeker($("#fieldtype"), $("#row_maxlength, #row_regex, #row_parser_options"), /text|textarea/);
				new Peeker($("#fieldtype"), $("#row_fieldlength"), /select|multiselect/);
				new Peeker($("#fieldtype"), $("#row_options"), /select|radio|checkbox/);
				// Add a star to the extra row since the "extra" is required if the box is shown
				add_star("row_maxlength");
				add_star("row_fieldlength");
				add_star("row_options");
		});
	</script>';

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("profilefields", "*", "fid='".$mybb->get_input('fid', MyBB::INPUT_INT)."'");
	$profile_field = $db->fetch_array($query);

	// Does the profile field not exist?
	if(!$profile_field['fid'])
	{
		flash_message($lang->error_invalid_fid, 'error');
		admin_redirect("index.php?module=config-profile_fields");
	}

	// User clicked no
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=config-profile_fields");
	}

	$plugins->run_hooks("admin_config_profile_fields_delete");

	if($mybb->request_method == "post")
	{
		// Delete the profile field
		$db->delete_query("profilefields", "fid='{$profile_field['fid']}'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."userfields DROP fid{$profile_field['fid']}");

		$plugins->run_hooks("admin_config_profile_fields_delete_commit");

		$cache->update_profilefields();

		// Log admin action
		log_admin_action($profile_field['fid'], $profile_field['name']);

		flash_message($lang->success_profile_field_deleted, 'success');
		admin_redirect("index.php?module=config-profile_fields");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-profile_fields&amp;action=delete&amp;fid={$profile_field['fid']}", $lang->confirm_profile_field_deletion);
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_config_profile_fields_start");

	$page->output_header($lang->custom_profile_fields);

	$sub_tabs['custom_profile_fields'] = array(
		'title' => $lang->custom_profile_fields,
		'link' => "index.php?module=config-profile_fields",
		'description' => $lang->custom_profile_fields_desc
	);

	$sub_tabs['add_profile_field'] = array(
		'title' => $lang->add_new_profile_field,
		'link' => "index.php?module=config-profile_fields&amp;action=add",
	);


	$page->output_nav_tabs($sub_tabs, 'custom_profile_fields');

	$table = new Table;
	$table->construct_header($lang->name);
	$table->construct_header($lang->required, array("class" => "align_center"));
	$table->construct_header($lang->registration, array("class" => "align_center"));
	$table->construct_header($lang->editable, array("class" => "align_center"));
	$table->construct_header($lang->profile, array("class" => "align_center"));
	$table->construct_header($lang->postbit, array("class" => "align_center"));
	$table->construct_header($lang->controls, array("class" => "align_center"));

	$query = $db->simple_select("profilefields", "*", "", array('order_by' => 'disporder'));
	while($field = $db->fetch_array($query))
	{
		if($field['required'])
		{
			$required = $lang->yes;
		}
		else
		{
			$required = $lang->no;
		}

		if($field['registration'])
		{
			$registration = $lang->yes;
		}
		else
		{
			$registration = $lang->no;
		}

		if($field['editableby'] == '')
		{
			$editable = $lang->no;
		}
		else
		{
			$editable = $lang->yes;
		}

		if($field['profile'])
		{
			$profile = $lang->yes;
		}
		else
		{
			$profile = $lang->no;
		}

		if($field['postbit'])
		{
			$postbit = $lang->yes;
		}
		else
		{
			$postbit = $lang->no;
		}

		$table->construct_cell("<strong><a href=\"index.php?module=config-profile_fields&amp;action=edit&amp;fid={$field['fid']}\">".htmlspecialchars_uni($field['name'])."</a></strong><br /><small>".htmlspecialchars_uni($field['description'])."</small>", array('width' => '35%'));
		$table->construct_cell($required, array("class" => "align_center", 'width' => '10%'));
		$table->construct_cell($registration, array("class" => "align_center", 'width' => '10%'));
		$table->construct_cell($editable, array("class" => "align_center", 'width' => '10%'));
		$table->construct_cell($profile, array("class" => "align_center", 'width' => '10%'));
		$table->construct_cell($postbit, array("class" => "align_center", 'width' => '10%')); 

		$popup = new PopupMenu("field_{$field['fid']}", $lang->options);
		$popup->add_item($lang->edit_field, "index.php?module=config-profile_fields&amp;action=edit&amp;fid={$field['fid']}");
		$popup->add_item($lang->delete_field, "index.php?module=config-profile_fields&amp;action=delete&amp;fid={$field['fid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_profile_field_deletion}')");
		$table->construct_cell($popup->fetch(), array("class" => "align_center", 'width' => '20%'));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_profile_fields, array('colspan' => 7));
		$table->construct_row();
	}

	$table->output($lang->custom_profile_fields);

	$page->output_footer();
}
