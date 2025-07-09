<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Builds the "view management" interface allowing administrators to edit their custom designed "views"
 *
 * @param string $base_url The base URL to this instance of the view manager
 * @param string $type The internal type identifier for this view
 * @param array $fields Array of fields this view supports
 * @param array $sort_options Array of possible sort options this view supports if any
 * @param string $conditions_callback Optional callback function which generates list of "conditions" for this view
 */
function view_manager($base_url, $type, $fields, $sort_options=array(), $conditions_callback="")
{
	global $mybb, $db, $page, $lang;

	$sub_tabs['views'] = array(
		'title' => $lang->views,
		'link' => "{$base_url}&amp;action=views",
		'description' => $lang->views_desc
	);

	$sub_tabs['create_view'] = array(
		'title' => $lang->create_new_view,
		'link' => "{$base_url}&amp;action=views&amp;do=add",
		'description' => $lang->create_new_view_desc
	);

	$page->add_breadcrumb_item($lang->view_manager, 'index.php?module=user-users&amp;action=views');

	// Lang strings should be in global lang file

	if($mybb->input['do'] == "set_default")
	{
		$query = $db->simple_select("adminviews", "vid, uid, visibility", "vid='".$mybb->get_input('vid', MyBB::INPUT_INT)."'");
		$admin_view = $db->fetch_array($query);

		if(!$admin_view['vid'] || $admin_view['visibility'] == 1 && $mybb->user['uid'] != $admin_view['uid'])
		{
			flash_message($lang->error_invalid_admin_view, 'error');
			admin_redirect($base_url."&action=views");
		}
		set_default_view($type, $admin_view['vid']);
		flash_message($lang->succuss_view_set_as_default, 'success');
		admin_redirect($base_url."&action=views");
	}

	$errors = array();
	if($mybb->input['do'] == "add")
	{
		if($mybb->request_method == "post")
		{
			if(!trim($mybb->input['title']))
			{
				$errors[] = $lang->error_missing_view_title;
			}
			if($mybb->input['fields_js'])
			{
				$mybb->input['fields'] = explode(",", $mybb->input['fields_js']);
			}
			if(!isset($mybb->input['fields']) || !is_array($mybb->input['fields']) || count($mybb->input['fields']) <= 0)
			{
				$errors[] = $lang->error_no_view_fields;
			}

			if($mybb->get_input('perpage', MyBB::INPUT_INT) <= 0)
			{
				$errors[] = $lang->error_invalid_view_perpage;
			}

			if(!in_array($mybb->input['sortby'], array_keys($sort_options)))
			{
				$errors[] = $lang->error_invalid_view_sortby;
			}

			if($mybb->input['sortorder'] != "asc" && $mybb->input['sortorder'] != "desc")
			{
				$errors[] = $lang->error_invalid_view_sortorder;
			}

			if($mybb->input['visibility'] == 0)
			{
				$mybb->input['visibility'] = 2;
			}

			if(empty($errors))
			{
				$new_view = array(
					"uid" => $mybb->user['uid'],
					"title" => $db->escape_string($mybb->input['title']),
					"type" => $type,
					"visibility" => $mybb->get_input('visibility', MyBB::INPUT_INT),
					"fields" => $db->escape_string(my_serialize($mybb->input['fields'])),
					"conditions" => $db->escape_string(my_serialize($mybb->input['conditions'])),
					"custom_profile_fields" => $db->escape_string(my_serialize($mybb->input['profile_fields'])),
					"sortby" => $db->escape_string($mybb->input['sortby']),
					"sortorder" => $db->escape_string($mybb->input['sortorder']),
					"perpage" => $mybb->get_input('perpage', MyBB::INPUT_INT),
					"view_type" => $db->escape_string($mybb->input['view_type'])
				);

				$vid = $db->insert_query("adminviews", $new_view);

				if($mybb->input['isdefault'])
				{
					set_default_view($type, $vid);
				}
				flash_message($lang->success_view_created, "success");
				admin_redirect($base_url."&vid={$vid}");
			}
		}
		else
		{
			$mybb->input = array_merge($mybb->input, array('perpage' => 20));
		}

		// Write in our JS based field selector
		$page->extra_header .= "<script src=\"jscripts/view_manager.js\" type=\"text/javascript\"></script>\n";

		$page->add_breadcrumb_item($lang->create_new_view);
		$page->output_header($lang->create_new_view);

		$form = new Form($base_url."&amp;action=views&amp;do=add", "post");

		$page->output_nav_tabs($sub_tabs, 'create_view');

		// If we have any error messages, show them
		if(!empty($errors))
		{
			$page->output_inline_error($errors);
		}

		$form_container = new FormContainer($lang->create_new_view);
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $mybb->get_input('title'), array('id' => 'title')), 'title');

		$visibility_public_checked = $mybb->get_input('visibility') == 2;
		$visibility_private_checked = !$visibility_public_checked;

		$visibility_options = array(
			$form->generate_radio_button("visibility", "1", "<strong>{$lang->private}</strong> - {$lang->private_desc}", array("checked" => $visibility_private_checked)),
			$form->generate_radio_button("visibility", "2", "<strong>{$lang->public}</strong> - {$lang->public_desc}", array("checked" => $visibility_public_checked))
		);
		$form_container->output_row($lang->visibility, "", implode("<br />", $visibility_options));

		$form_container->output_row($lang->set_as_default_view, "", $form->generate_yes_no_radio("isdefault", $mybb->get_input('isdefault'), array('yes' => 1, 'no' => 0)));

		if(count($sort_options) > 0)
		{
			$sort_directions = array(
				"asc" => $lang->ascending,
				"desc" => $lang->descending
			);
			$form_container->output_row($lang->sort_results_by, "", $form->generate_select_box('sortby', $sort_options, $mybb->get_input('sortby'), array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('sortorder', $sort_directions, $mybb->get_input('sortorder'), array('id' => 'sortorder')), 'sortby');
		}

		$form_container->output_row($lang->results_per_page, "", $form->generate_numeric_field('perpage', $mybb->get_input('perpage'), array('id' => 'perpage', 'min' => 1)), 'perpage');

		if($type == "user")
		{
			$form_container->output_row($lang->display_results_as, "", $form->generate_radio_button('view_type', 'table', $lang->table, array('checked' => ($mybb->get_input('view_type') != "card" ? true : false)))."<br />".$form->generate_radio_button('view_type', 'card', $lang->business_card, array('checked' => ($mybb->get_input('view_type') == "card" ? true : false))));
		}

		$form_container->end();

		$active = array();

		$field_select = "<div class=\"view_fields\">\n";
		$field_select .= "<div class=\"enabled\"><div class=\"fields_title\">{$lang->enabled}</div><ul id=\"fields_enabled\">\n";
		if(isset($mybb->input['fields']) && is_array($mybb->input['fields']))
		{
			foreach($mybb->input['fields'] as $field)
			{
				if($fields[$field])
				{
					$field_select .= "<li id=\"field-{$field}\">&#149; {$fields[$field]['title']}</li>";
					$active[$field] = 1;
				}
			}
		}
		$field_select .= "</ul></div>\n";
		$field_select .= "<div class=\"disabled\"><div class=\"fields_title\">{$lang->disabled}</div><ul id=\"fields_disabled\">\n";
		foreach($fields as $key => $field)
		{
			if(!empty($active[$key]))
			{
				continue;
			}
			$field_select .= "<li id=\"field-{$key}\">&#149; {$field['title']}</li>";
		}
		$field_select .= "</div></ul>\n";
		$field_select .= $form->generate_hidden_field("fields_js", @implode(",", @array_keys($active)), array('id' => 'fields_js'));
		$field_select = str_replace("'", "\\'", $field_select);
		$field_select = str_replace("\n", "", $field_select);

		$field_select = "<script type=\"text/javascript\">
//<![CDATA[
document.write('".str_replace("/", "\/", $field_select)."');
//]]>
</script>\n";

		foreach($fields as $key => $field)
		{
			$field_options[$key] = $field['title'];
		}

		$field_select .= "<noscript>".$form->generate_select_box('fields[]', $field_options, $mybb->get_input('fields'), array('id' => 'fields', 'multiple' => true))."</noscript>\n";

		$form_container = new FormContainer($lang->fields_to_show);
		$form_container->output_row($lang->fields_to_show_desc, '', $field_select);
		$form_container->end();

		// Build the search conditions
		if(function_exists($conditions_callback))
		{
			$conditions_callback($mybb->input, $form);
		}

		$buttons[] = $form->generate_submit_button($lang->save_view);
		$form->output_submit_wrapper($buttons);

		$form->end();
		$page->output_footer();
	}
	else if($mybb->input['do'] == "edit")
	{
		$query = $db->simple_select("adminviews", "*", "vid='".$mybb->get_input('vid', MyBB::INPUT_INT)."'");
		$admin_view = $db->fetch_array($query);

		// Does the view not exist?
		if(!$admin_view['vid'] || $admin_view['visibility'] == 1 && $mybb->user['uid'] != $admin_view['uid'])
		{
			flash_message($lang->error_invalid_admin_view, 'error');
			admin_redirect($base_url."&action=views");
		}

		if($mybb->request_method == "post")
		{
			if(!trim($mybb->input['title']))
			{
				$errors[] = $lang->error_missing_view_title;
			}
			if($mybb->input['fields_js'])
			{
				$mybb->input['fields'] = explode(",", $mybb->input['fields_js']);
			}

			if(!is_array($mybb->input['fields']) || count($mybb->input['fields']) <= 0)
			{
				$errors[] = $lang->error_no_view_fields;
			}

			if($mybb->get_input('perpage', MyBB::INPUT_INT) <= 0)
			{
				$errors[] = $lang->error_invalid_view_perpage;
			}

			if(!in_array($mybb->input['sortby'], array_keys($sort_options)))
			{
				$errors[] = $lang->error_invalid_view_sortby;
			}

			if($mybb->input['sortorder'] != "asc" && $mybb->input['sortorder'] != "desc")
			{
				$errors[] = $lang->error_invalid_view_sortorder;
			}

			if($mybb->input['visibility'] == 0)
			{
				$mybb->input['visibility'] = 2;
			}

			if(empty($errors))
			{
				$updated_view = array(
					"title" => $db->escape_string($mybb->input['title']),
					"type" => $type,
					"visibility" => $mybb->get_input('visibility', MyBB::INPUT_INT),
					"fields" => $db->escape_string(my_serialize($mybb->input['fields'])),
					"conditions" => $db->escape_string(my_serialize($mybb->input['conditions'])),
					"custom_profile_fields" => $db->escape_string(my_serialize($mybb->input['profile_fields'])),
					"sortby" => $db->escape_string($mybb->input['sortby']),
					"sortorder" => $db->escape_string($mybb->input['sortorder']),
					"perpage" => $mybb->get_input('perpage', MyBB::INPUT_INT),
					"view_type" => $db->escape_string($mybb->input['view_type'])
				);
				$db->update_query("adminviews", $updated_view, "vid='{$admin_view['vid']}'");

				if($mybb->input['isdefault'])
				{
					set_default_view($type, $admin_view['vid']);
				}

				flash_message($lang->success_view_updated, "success");
				admin_redirect($base_url."&vid={$admin_view['vid']}");
			}
		}

		// Write in our JS based field selector
		$page->extra_header .= "<script src=\"jscripts/view_manager.js\" type=\"text/javascript\"></script>\n";

		$page->add_breadcrumb_item($lang->edit_view);
		$page->output_header($lang->edit_view);

		$form = new Form($base_url."&amp;action=views&amp;do=edit&amp;vid={$admin_view['vid']}", "post");

		$sub_tabs = array();
		$sub_tabs['edit_view'] = array(
			'title' => $lang->edit_view,
			'link' => $base_url."&amp;action=views&amp;do=edit&amp;vid={$admin_view['vid']}",
			'description' => $lang->edit_view_desc
		);

		$page->output_nav_tabs($sub_tabs, 'edit_view');

		// If we have any error messages, show them
		if(!empty($errors))
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$admin_view['conditions'] = my_unserialize($admin_view['conditions']);
			$admin_view['fields'] = my_unserialize($admin_view['fields']);
			$admin_view['profile_fields'] = my_unserialize($admin_view['custom_profile_fields']);
			$mybb->input = array_merge($mybb->input, $admin_view);

			$mybb->input['isdefault'] = 0;
			$default_view = fetch_default_view($type);

			if($default_view == $admin_view['vid'])
			{
				$mybb->input['isdefault'] = 1;
			}
		}

		$form_container = new FormContainer($lang->edit_view);
		$form_container->output_row($lang->view." <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');

		$visibility_public_checked = $mybb->input['visibility'] == 2;
		$visibility_private_checked = !$visibility_public_checked;

		$visibility_options = array(
			$form->generate_radio_button("visibility", "1", "<strong>{$lang->private}</strong> - {$lang->private_desc}", array("checked" => $visibility_private_checked)),
			$form->generate_radio_button("visibility", "2", "<strong>{$lang->public}</strong> - {$lang->public_desc}", array("checked" => $visibility_public_checked))
		);
		$form_container->output_row($lang->visibility, "", implode("<br />", $visibility_options));

		$form_container->output_row($lang->set_as_default_view, "", $form->generate_yes_no_radio("isdefault", $mybb->input['isdefault'], array('yes' => 1, 'no' => 0)));

		if(is_array($sort_options) && count($sort_options) > 0)
		{
			$sort_directions = array(
				"asc" => $lang->ascending,
				"desc" => $lang->descending
			);
			$form_container->output_row($lang->sort_results_by, "", $form->generate_select_box('sortby', $sort_options, $mybb->input['sortby'], array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('sortorder', $sort_directions, $mybb->input['sortorder'], array('id' => 'sortorder')), 'sortby');
		}

		$form_container->output_row($lang->results_per_page, "", $form->generate_numeric_field('perpage', $mybb->input['perpage'], array('id' => 'perpage', 'min' => 1)), 'perpage');

		if($type == "user")
		{
			$form_container->output_row($lang->display_results_as, "", $form->generate_radio_button('view_type', 'table', $lang->table, array('checked' => ($mybb->input['view_type'] != "card" ? true : false)))."<br />".$form->generate_radio_button('view_type', 'card', $lang->business_card, array('checked' => ($mybb->input['view_type'] == "card" ? true : false))));
		}

		$form_container->end();

		$field_select = "<div class=\"view_fields\">\n";
		$field_select .= "<div class=\"enabled\"><div class=\"fields_title\">{$lang->enabled}</div><ul id=\"fields_enabled\">\n";
		if(is_array($mybb->input['fields']))
		{
			foreach($mybb->input['fields'] as $field)
			{
				if($fields[$field])
				{
					$field_select .= "<li id=\"field-{$field}\">&#149; {$fields[$field]['title']}</li>";
					$active[$field] = 1;
				}
			}
		}
		$field_select .= "</ul></div>\n";
		$field_select .= "<div class=\"disabled\"><div class=\"fields_title\">{$lang->disabled}</div><ul id=\"fields_disabled\">\n";
		if(is_array($fields))
		{
			foreach($fields as $key => $field)
			{
				if(!empty($active[$key]))
				{
					continue;
				}
				$field_select .= "<li id=\"field-{$key}\">&#149; {$field['title']}</li>";
			}
		}
		$field_select .= "</div></ul>\n";
		$field_select .= $form->generate_hidden_field("fields_js", @implode(",", @array_keys($active)), array('id' => 'fields_js'));
		$field_select = str_replace("'", "\\'", $field_select);
		$field_select = str_replace("\n", "", $field_select);

		$field_select = "<script type=\"text/javascript\">
//<![CDATA[
document.write('".str_replace("/", "\/", $field_select)."');
//]]></script>\n";

		foreach($fields as $key => $field)
		{
			$field_options[$key] = $field['title'];
		}

		$field_select .= "<noscript>".$form->generate_select_box('fields[]', $field_options, $mybb->input['fields'], array('id' => 'fields', 'multiple' => true))."</noscript>\n";

		$form_container = new FormContainer($lang->fields_to_show);
		$form_container->output_row($lang->fields_to_show_desc, '', $field_select);
		$form_container->end();

		// Build the search conditions
		if(function_exists($conditions_callback))
		{
			$conditions_callback($mybb->input, $form);
		}

		$buttons[] = $form->generate_submit_button($lang->save_view);
		$form->output_submit_wrapper($buttons);

		$form->end();
		$page->output_footer();
	}

	else if($mybb->input['do'] == "delete")
	{
		if($mybb->get_input('no'))
		{
			admin_redirect($base_url."&action=views");
		}

		$query = $db->simple_select("adminviews", "COUNT(vid) as views");
		$views = $db->fetch_field($query, "views");

		if($views == 0)
		{
			flash_message($lang->error_cannot_delete_view, 'error');
			admin_redirect($base_url."&action=views");
		}

		$vid = $mybb->get_input('vid', MyBB::INPUT_INT);
		$query = $db->simple_select("adminviews", "vid, uid, visibility", "vid = '{$vid}'");
		$admin_view = $db->fetch_array($query);

		if($vid == 1 || !$admin_view['vid'] || $admin_view['visibility'] == 1 && $mybb->user['uid'] != $admin_view['uid'])
		{
			flash_message($lang->error_invalid_view_delete, 'error');
			admin_redirect($base_url."&action=views");
		}

		if($mybb->request_method == "post")
		{
			$db->delete_query("adminviews", "vid='{$admin_view['vid']}'");
			flash_message($lang->success_view_deleted, 'success');
			admin_redirect($base_url."&action=views");
		}
		else
		{
			$page->output_confirm_action($base_url."&amp;action=views&amp;do=delete&amp;vid={$admin_view['vid']}", $lang->confirm_view_deletion);
		}
	}

	// Export views
	else if($mybb->input['do'] == "export")
	{
		$xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?".">\n";
		$xml = "<adminviews version=\"".$mybb->version_code."\" exported=\"".TIME_NOW."\">\n";

		if($mybb->input['type'])
		{
			$type_where = "type='".$db->escape_string($mybb->input['type'])."'";
		}

		$query = $db->simple_select("adminviews", "*", $type_where);
		while($admin_view = $db->fetch_array($query))
		{
			$fields = my_unserialize($admin_view['fields']);
			$conditions = my_unserialize($admin_view['conditions']);

			$admin_view['title'] = str_replace(']]>', ']]]]><![CDATA[>', $admin_view['title']);
			$admin_view['sortby'] = str_replace(']]>', ']]]]><![CDATA[>', $admin_view['sortby']);
			$admin_view['sortorder'] = str_replace(']]>', ']]]]><![CDATA[>', $admin_view['sortorder']);
			$admin_view['view_type'] = str_replace(']]>', ']]]]><![CDATA[>', $admin_view['view_type']);

			$xml .= "\t<view vid=\"{$admin_view['vid']}\" uid=\"{$admin_view['uid']}\" type=\"{$admin_view['type']}\" visibility=\"{$admin_view['visibility']}\">\n";
			$xml .= "\t\t<title><![CDATA[{$admin_view['title']}]]></title>\n";
			$xml .= "\t\t<fields>\n";
			foreach($fields as $field)
			{
				$xml .= "\t\t\t<field name=\"{$field}\" />\n";
			}
			$xml .= "\t\t</fields>\n";
			$xml .= "\t\t<conditions>\n";
			foreach($conditions as $name => $condition)
			{
				if(!$conditions) continue;
				if(is_array($condition))
				{
					$condition = my_serialize($condition);
					$is_serialized = " is_serialized=\"1\"";
				}
				$condition = str_replace(']]>', ']]]]><![CDATA[>', $condition);
				$xml .= "\t\t\t<condition name=\"{$name}\"{$is_serialized}><![CDATA[{$condition}]]></condition>\n";
			}
			$xml .= "\t\t</conditions>\n";
			$xml .= "\t\t<sortby><![CDATA[{$admin_view['sortby']}]]></sortby>\n";
			$xml .= "\t\t<sortorder><![CDATA[{$admin_view['sortorder']}]]></sortorder>\n";
			$xml .= "\t\t<perpage><![CDATA[{$admin_view['perpage']}]]></perpage>\n";
			$xml .= "\t\t<view_type><![CDATA[{$admin_view['view_type']}]]></view_type>\n";
			$xml .= "\t</view>\n";
		}
		$xml .= "</adminviews>\n";
		$mybb->settings['bbname'] = urlencode($mybb->settings['bbname']);
		header("Content-disposition: filename=".$mybb->settings['bbname']."-views.xml");
		header("Content-Length: ".my_strlen($xml));
		header("Content-type: unknown/unknown");
		header("Pragma: no-cache");
		header("Expires: 0");
		echo $xml;
		exit;
	}

	// Generate a listing of all current views
	else
	{
		$page->output_header($lang->view_manager);

		$page->output_nav_tabs($sub_tabs, 'views');

		$table = new Table;
		$table->construct_header($lang->view);
		$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

		$default_view = fetch_default_view($type);

		$query = $db->simple_select("adminviews", "COUNT(vid) as views");
		$views = $db->fetch_field($query, "views");

		$query = $db->query("
			SELECT v.*, u.username
			FROM ".TABLE_PREFIX."adminviews v
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=v.uid)
			WHERE v.visibility='2' OR (v.visibility='1' AND v.uid='{$mybb->user['uid']}')
			ORDER BY title
		");
		while($view = $db->fetch_array($query))
		{
			$created = "";
			if($view['uid'] == 0)
			{
				$view_type = "default";
				$default_class = "grey";
			}
			else if($view['visibility'] == 2)
			{
				$view_type = "group";
				if($view['username'])
				{
					$username = htmlspecialchars_uni($view['username']);
					$created = "<br /><small>{$lang->created_by} {$username}</small>";
				}
			}
			else
			{
				$view_type = "user";
			}

			$default_add = '';
			if($default_view == $view['vid'])
			{
				$default_add = " ({$lang->default})";
			}

			$title_string = "view_title_{$view['vid']}";

			if(isset($lang->$title_string))
			{
				$view['title'] = $lang->$title_string;
			}

			$table->construct_cell("<div class=\"float_right\"><img src=\"styles/{$page->style}/images/icons/{$view_type}.png\" title=\"".$lang->sprintf($lang->this_is_a_view, $view_type)."\" alt=\"{$view_type}\" /></div><div class=\"{$default_class}\"><strong><a href=\"{$base_url}&amp;action=views&amp;do=edit&amp;vid={$view['vid']}\" >{$view['title']}</a></strong>{$default_add}{$created}</div>");

			$popup = new PopupMenu("view_{$view['vid']}", $lang->options);
			$popup->add_item($lang->edit_view, "{$base_url}&amp;action=views&amp;do=edit&amp;vid={$view['vid']}");
			if($view['vid'] != $default_view)
			{
				$popup->add_item($lang->set_as_default, "{$base_url}&amp;action=views&amp;do=set_default&amp;vid={$view['vid']}");
			}

			if($views > 1 && $view['vid'] != 1)
			{
				$popup->add_item($lang->delete_view, "{$base_url}&amp;action=views&amp;do=delete&amp;vid={$view['vid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_view_deletion}')");
			}
			$controls = $popup->fetch();
			$table->construct_cell($controls, array("class" => "align_center"));
			$table->construct_row();
		}

		$table->output($lang->view);

		echo <<<LEGEND
<br />
<fieldset>
<legend>{$lang->legend}</legend>
<img src="styles/{$page->style}/images/icons/default.png" alt="{$lang->default}" style="vertical-align: middle;" /> {$lang->default_view_desc}<br />
<img src="styles/{$page->style}/images/icons/group.png" alt="{$lang->public}" style="vertical-align: middle;" /> {$lang->public_view_desc}<br />
<img src="styles/{$page->style}/images/icons/user.png" alt="{$lang->private}" style="vertical-align: middle;" /> {$lang->private_view_desc}</fieldset>
LEGEND;
		$page->output_footer();
	}
}

function set_default_view($type, $vid)
{
	global $mybb, $db;

	$query = $db->simple_select("adminoptions", "defaultviews", "uid='{$mybb->user['uid']}'");
	$default_views = my_unserialize($db->fetch_field($query, "defaultviews"));
	$create = !$db->num_rows($query);

	$default_views[$type] = $vid;
	$default_views = my_serialize($default_views);
	$updated_admin = array("defaultviews" => $db->escape_string($default_views));

	if($create == true)
	{
		$updated_admin['uid'] = $mybb->user['uid'];
		$updated_admin['notes'] = '';
		$updated_admin['permissions'] = '';
		$db->insert_query("adminoptions", $updated_admin);
	}
	else
	{
		$db->update_query("adminoptions", $updated_admin, "uid='{$mybb->user['uid']}'");
	}
}

/**
 * @param string $type
 *
 * @return bool|array
 */
function fetch_default_view($type)
{
	global $mybb, $db;
	$query = $db->simple_select("adminoptions", "defaultviews", "uid='{$mybb->user['uid']}'");
	$default_views = my_unserialize($db->fetch_field($query, "defaultviews"));
	if(!is_array($default_views))
	{
		return false;
	}
	return $default_views[$type];
}
