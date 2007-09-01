<?php
/**
 * MyBB 1.4
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

/**
 * Builds the "view management" interface allowing administrators to edit their custom designed "views"
 *
 * @param string The base URL to this instance of the view manager
 * @param string The internal type identifier for this view
 * @param array Array of fields this view supports
 * @param array Array of possible sort options this view supports if any
 * @param string Optional callback function which generates list of "conditions" for this view
 */
function view_manager($base_url, $type, $fields, $sort_options=array(), $conditions_callback="")
{
	global $mybb, $db, $page, $lang;

	$sub_tabs['views'] = array(
		'title' => "Views",
		'link' => "{$base_url}&amp;action=views",
		'description' => "The view manager allows you to create different kinds of views for this specific area. Different views are useful for generating a variety of reports."
	);

	$sub_tabs['create_view'] = array(
		'title' => "Create New View",
		'link' => "{$base_url}&amp;action=views&amp;do=add",
		'description' => "Here you can define a new view for this area. You can define which fields you want to be shown, any search criteria and sorting options."
	);

	$page->add_breadcrumb_item("View Manager");

	// Lang strings should be in global lang file

	if($mybb->input['do'] == "set_default")
	{
		$query = $db->simple_select("adminviews", "vid", "vid='".intval($mybb->input['vid'])."'");
		$admin_view = $db->fetch_array($query);

		if(!$admin_view['vid'] || $admin_view['visibility'] == 1 && $mybb->user['uid'] != $admin_view['uid'])
		{
			flash_message("You selected an invalid administration view.", 'error');
			admin_redirect($base_url."&action=views");
		}
		set_default_view($type, $admin_view['vid']);
		flash_message("The administration view has successfully been set as your default", 'success');
		admin_redirect($base_url."&action=views");
	}
	
	if($mybb->input['do'] == "add")
	{
		if($mybb->request_method == "post")
		{
			if(!trim($mybb->input['title']))
			{
				$errors[] = "You did not enter a title for this view.";
			}
			if($mybb->input['fields_js'])
			{
				$mybb->input['fields'] = explode(",", $mybb->input['fields_js']);
			}
			if(count($mybb->input['fields']) <= 0)
			{
				$errors[] = "You did not select any fields to display on this view";
			}

			if(intval($mybb->input['perpage']) <= 0)
			{
				$errors[] = "You have entered an invalid number of results to show per page";
			}

			if(!in_array($mybb->input['sortby'], array_keys($sort_options)))
			{
				$errors[] = "You have selected an invalid field to sort results by";
			}

			if($mybb->input['sortorder'] != "asc" && $mybb->input['sortorder'] != "desc")
			{
				$errors[] = "You have selected an invalid sort order";
			}

			if($mybb->input['visibility'] == 0)
			{
				$mybb->input['visibility'] = 2;
			}

			if(!$errors)
			{
				$new_view = array(
					"uid" => $mybb->user['uid'],
					"title" => $db->escape_string($mybb->input['title']),
					"type" => $type,
					"visibility" => intval($mybb->input['visibility']),
					"fields" => $db->escape_string(serialize($mybb->input['fields'])),
					"conditions" => $db->escape_string(serialize($mybb->input['conditions'])),
					"sortby" => $db->escape_string($mybb->input['sortby']),
					"sortorder" => $db->escape_string($mybb->input['sortorder']),
					"perpage" => intval($mybb->input['perpage']),
					"view_type" => $db->escape_string($mybb->input['view_type'])
				);

				$db->insert_query("adminviews", $new_view);
				$vid = $db->insert_id();

				if($mybb->input['isdefault'])
				{
					set_default_view($type, $vid);
				}
				flash_message("The administration view has successfully been created", "success");
				admin_redirect($base_url."&vid={$vid}");
			}
		}
		else
		{
			$mybb->input = array(
				"perpage" => 20
			);
		}

		$page->output_header("Create New View");
			
		$form = new Form($base_url."&action=views&do=add", "post");

		$page->output_nav_tabs($sub_tabs, 'create_view');

		// If we have any error messages, show them
		if($errors)
		{
			$page->output_inline_error($errors);
		}

		$form_container = new FormContainer("Create New View");
		$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');

		if($mybb->input['visibility'] == 2)
		{
			$visibility_public_checked = true;
		}
		else
		{
			$visibility_private_checked = true;
		}

		$visibility_options = array(
			$form->generate_radio_button("visibility", "1", "<strong>Private</strong> - This view is only visible to you", array("checked" => $visibility_private_checked)),
			$form->generate_radio_button("visibility", "2", "<strong>Public</strong> - All other administrators can see this view", array("checked" => $visibility_public_checked))
		);
		$form_container->output_row("Visibility", "", implode("<br />", $visibility_options));

		$form_container->output_row("Set as Default View?", "", $form->generate_yes_no_radio("isdefault", $mybb->input['isdefault'], array('yes' => 1, 'no' => 0)), "isdefault");

		if(count($sort_options) > 0)
		{
			$sort_directions = array(
				"asc" => "Ascending",
				"desc" => "Descending"
			);
			$form_container->output_row("Sort results by", "", $form->generate_select_box('sortby', $sort_options, $mybb->input['sortby'], array('id' => 'sortby'))." in ".$form->generate_select_box('sortorder', $sort_directions, $mybb->input['sortorder'], array('id' => 'sortorder')), 'sortby');
		}

		$form_container->output_row("Results per page", "", $form->generate_text_box('perpage', $mybb->input['perpage'], array('id' => 'perpage')), 'perpage');

		if($type == "user")
		{
			$form_container->output_row("Display results as", "", $form->generate_radio_button('view_type', 'table', 'Table', array('checked' => ($mybb->input['view_type'] != "card" ? true : false)))."<br />".$form->generate_radio_button('view_type', 'card', 'Business cards', array('checked' => ($mybb->input['view_type'] == "card" ? true : false))));
		}

		$form_container->end();

		// Write in our JS based field selector
		echo "<script src=\"../jscripts/scriptaculous.js?load=effects,dragdrop\" type=\"text/javascript\"></script>\n";
		echo "<script src=\"jscripts/view_manager.js\" type=\"text/javascript\"></script>\n";
		$field_select .= "<div class=\"view_fields\">\n";
		$field_select .= "<div class=\"enabled\"><div class=\"fields_title\">Enabled</div><ul id=\"fields_enabled\">\n";
		foreach($mybb->input['fields'] as $field)
		{
			if($fields[$field])
			{
				$field_select .= "<li id=\"field-{$field}\">{$fields[$field]['title']}</li>";
				$active[$field] = 1;
			}
		}
		$field_select .= "</ul></div>\n";
		$field_select .= "<div class=\"disabled\"><div class=\"fields_title\">Disabled</div><ul id=\"fields_disabled\">\n";
		foreach($fields as $key => $field)
		{
			if($active[$key]) continue;
			$field_select .= "<li id=\"field-{$key}\">{$field['title']}</li>";
		}
		$field_select .= "</div></ul>\n";
		$field_select .= $form->generate_hidden_field("fields_js", @implode(",", @array_keys($active)), array('id' => 'fields_js'));
		$field_select = str_replace("'", "\\'", $field_select);
		$field_select = str_replace("\n", "", $field_select);
		
		$field_select = "<script type=\"text/javascript\">document.write('{$field_select}');</script>\n";
		
		foreach($fields as $key => $field)
		{
			$field_options[$key] = $field['title'];
		}
		
		$field_select .= "<noscript>".$form->generate_select_box('fields', $field_options, $mybb->input['fields'], array('id' => 'fields', 'multiple' => true))."</noscript>\n";

		$form_container = new FormContainer("Fields to Show");
		$form_container->output_row("Please select the fields you wish to display", $description, $field_select);
		$form_container->end();

		// Build the search conditions
		if(function_exists($conditions_callback))
		{
			$conditions_callback($mybb->input, $form); 
		}

		$buttons[] = $form->generate_submit_button("Save View");
		$form->output_submit_wrapper($buttons);

		$form->end();
		$page->output_footer();

	}

	else if($mybb->input['do'] == "edit")
	{
		$query = $db->simple_select("adminviews", "*", "vid='".intval($mybb->input['vid'])."'");
		$admin_view = $db->fetch_array($query);

		// Does the view not exist?
		if(!$admin_view['vid'] || $admin_view['visibility'] == 1 && $mybb->user['uid'] != $admin_view['uid'])
		{
			flash_message("You have selected an invalid view", 'error');
			admin_redirect($base_url."&action=views");
		}

		if($mybb->request_method == "post")
		{
			if(!trim($mybb->input['title']))
			{
				$errors[] = "You did not enter a title for this view.";
			}
			if($mybb->input['fields_js'])
			{
				$mybb->input['fields'] = explode(",", $mybb->input['fields_js']);
			}

			if(count($mybb->input['fields']) <= 0)
			{
				$errors[] = "You did not select any fields to display on this view";
			}

			if(intval($mybb->input['perpage']) <= 0)
			{
				$errors[] = "You have entered an invalid number of results to show per page";
			}

			if(!in_array($mybb->input['sortby'], array_keys($sort_options)))
			{
				$errors[] = "You have selected an invalid field to sort results by";
			}

			if($mybb->input['sortorder'] != "asc" && $mybb->input['sortorder'] != "desc")
			{
				$errors[] = "You have selected an invalid sort order";
			}

			if($mybb->input['visibility'] == 0)
			{
				$mybb->input['visibility'] = 2;
			}

			if(!$errors)
			{
				$updated_view = array(
					"uid" => $mybb->user['uid'],
					"title" => $db->escape_string($mybb->input['title']),
					"type" => $type,
					"visibility" => intval($mybb->input['visibility']),
					"fields" => $db->escape_string(serialize($mybb->input['fields'])),
					"conditions" => $db->escape_string(serialize($mybb->input['conditions'])),
					"sortby" => $db->escape_string($mybb->input['sortby']),
					"sortorder" => $db->escape_string($mybb->input['sortorder']),
					"perpage" => intval($mybb->input['perpage']),
					"view_type" => $db->escape_string($mybb->input['view_type'])
				);
				$db->update_query("adminviews", $updated_view, "vid='{$admin_view['vid']}'");

				if($mybb->input['isdefault'])
				{
					set_default_view($type, $view['vid']);
				}

				flash_message("The administration view has successfully been updated", "success");
				admin_redirect($base_url."&vid={$vid}");
			}
		}
		else
		{
			$default_view = fetch_default_view($type);
			if($default_view = $view['vid'])
			{
				$mybb->input['isdefault'] = 1;
			}
		}

		$page->output_header("Edit View");
			
		$form = new Form($base_url."&action=views&do=edit&vid={$admin_view['vid']}", "post");

		$sub_tabs = array();
		$sub_tabs['edit_view'] = array(
			'title' => "Edit View",
			'description' => "Whilst editing a view you can define which fields you want to be shown, any search criteria and sorting options."
		);

		$page->output_nav_tabs($sub_tabs, 'edit_view');

		// If we have any error messages, show them
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$admin_view['conditions'] = unserialize($admin_view['conditions']);
			$admin_view['fields'] = unserialize($admin_view['fields']);
			$mybb->input = $admin_view;
		}

		$form_container = new FormContainer("Edit View");
		$form_container->output_row("Title <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');

		if($mybb->input['visibility'] == 2)
		{
			$visibility_public_checked = true;
		}
		else
		{
			$visibility_private_checked = true;
		}

		$visibility_options = array(
			$form->generate_radio_button("visibility", "1", "<strong>Private</strong> - This view is only visible to you", array("checked" => $visibility_private_checked)),
			$form->generate_radio_button("visibility", "2", "<strong>Public</strong> - All other administrators can see this view", array("checked" => $visibility_public_checked))
		);
		$form_container->output_row("Visibility", "", implode("<br />", $visibility_options));

		$form_container->output_row("Set as Default View?", "", $form->generate_yes_no_radio("isdefault", $mybb->input['isdefault'], array('yes' => 1, 'no' => 0)), "isdefault");

		if(count($sort_options) > 0)
		{
			$sort_directions = array(
				"asc" => "Ascending",
				"desc" => "Descending"
			);
			$form_container->output_row("Sort results by", "", $form->generate_select_box('sortby', $sort_options, $mybb->input['sortby'], array('id' => 'sortby'))." in ".$form->generate_select_box('sortorder', $sort_directions, $mybb->input['sortorder'], array('id' => 'sortorder')), 'sortby');
		}

		$form_container->output_row("Results per page", "", $form->generate_text_box('perpage', $mybb->input['perpage'], array('id' => 'perpage')), 'perpage');

		if($type == "user")
		{
			$form_container->output_row("Display results as", "", $form->generate_radio_button('view_type', 'table', 'Table', array('checked' => ($mybb->input['view_type'] != "card" ? true : false)))."<br />".$form->generate_radio_button('view_type', 'card', 'Business cards', array('checked' => ($mybb->input['view_type'] == "card" ? true : false))));
		}

		$form_container->end();

		// Write in our JS based field selector
		echo "<script src=\"../jscripts/scriptaculous.js?load=effects,dragdrop\" type=\"text/javascript\"></script>\n";
		echo "<script src=\"jscripts/view_manager.js\" type=\"text/javascript\"></script>\n";
		$field_select .= "<div class=\"view_fields\">\n";
		$field_select .= "<div class=\"enabled\"><div class=\"fields_title\">Enabled</div><ul id=\"fields_enabled\">\n";
		foreach($mybb->input['fields'] as $field)
		{
			if($fields[$field])
			{
				$field_select .= "<li id=\"field-{$field}\">{$fields[$field]['title']}</li>";
				$active[$field] = 1;
			}
		}
		$field_select .= "</ul></div>\n";
		$field_select .= "<div class=\"disabled\"><div class=\"fields_title\">Disabled</div><ul id=\"fields_disabled\">\n";
		foreach($fields as $key => $field)
		{
			if($active[$key]) continue;
			$field_select .= "<li id=\"field-{$key}\">{$field['title']}</li>";
		}
		$field_select .= "</div></ul>\n";
		$field_select .= $form->generate_hidden_field("fields_js", @implode(",", @array_keys($active)), array('id' => 'fields_js'));
		$field_select = str_replace("'", "\\'", $field_select);
		$field_select = str_replace("\n", "", $field_select);
		
		$field_select = "<script type=\"text/javascript\">document.write('{$field_select}');</script>\n";
		
		foreach($fields as $key => $field)
		{
			$field_options[$key] = $field['title'];
		}
		
		$field_select .= "<noscript>".$form->generate_select_box('fields', $field_options, $mybb->input['fields'], array('id' => 'fields', 'multiple' => true))."</noscript>\n";

		$form_container = new FormContainer("Fields to Show");
		$form_container->output_row("Please select the fields you wish to display", $description, $field_select);
		$form_container->end();

		// Build the search conditions
		if(function_exists($conditions_callback))
		{
			$conditions_callback($mybb->input, $form);
		}


		$buttons[] = $form->generate_submit_button("Save View");
		$form->output_submit_wrapper($buttons);

		$form->end();
		$page->output_footer();
	}

	else if($mybb->input['do'] == "delete")
	{
		if($mybb->input['no']) 
		{ 
			admin_redirect($base_url."&action=views"); 
		} 
		
		$query = $db->simple_select("adminviews", "vid", "vid='".intval($mybb->input['vid'])."'");
		$admin_view = $db->fetch_array($query);

		if(!$admin_view['vid'] || $admin_view['visibility'] == 1 && $mybb->user['uid'] != $admin_view['uid'])
		{
			flash_message("You selected an invalid administration view to delete", 'error');
			admin_redirect($base_url."&action=views");
		}
		
		if($mybb->request_method == "post")
		{
			$db->delete_query("adminviews", "vid='{$admin_view['vid']}");
			flash_message("The administration view has successfully been deleted", 'success');
			admin_redirect($base_url."&action=views");
		}
		else
		{
			$page->output_confirm_action($base_url."&amp;action=views&amp;do=delete&amp;vid={$admin_view['vid']}", "Are you sure you want to delete the selected view?"); 
		}
	}

	// Export views
	else if($mybb->input['do'] == "export")
	{
		$xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?".">\n";
		$xml = "<adminviews version=\"".$mybb->version_code."\" exported=\"".time()."\">\n";

		if($mybb->input['type'])
		{
			$type_where = "type='".$db->escape_string($mybb->input['type'])."'";
		}

		$query = $db->simple_select("adminviews", "*", $type_where);
		while($admin_view = $db->fetch_array($query))
		{
			$fields = unserialize($admin_view['fields']);
			$conditions = unserialize($admin_view['conditions']);
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
					$condition = serialize($condition);
					$is_serialized = " is_serialized=\"1\"";
				}
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
		$page->output_header("View Manager");
		
		$page->output_nav_tabs($sub_tabs, 'views');

		$table = new Table;
		$table->construct_header("View");
		$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

		$default_view = fetch_default_view($type);
		
		$query = $db->query("
			SELECT v.*, u.username
			FROM ".TABLE_PREFIX."adminviews v
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=v.uid)
			WHERE v.visibility='2' OR (v.visibility='1' AND v.uid='{$mybb->user['uid']}')
			ORDER BY title
		");
		while($view = $db->fetch_array($query))
		{
			if($view['uid'] == 0)
			{
				$view_type = "default";
				$default_class = "grey";
			}
			else if($view['visibility'] == 2)
			{
				$view_type = "public";
				if($view['username'])
				{
					$created = "<br /><small>Created by {$view['username']}</small>";
				}
			}
			else
			{
				$view_type = "private";
			}

			if($default_view == $view['vid'])
			{
				$default_add = " (Default)";
			}

			$table->construct_cell("<div class=\"float_right\"><img src=\"styles/{$page->style}/images/icons/view_{$perm_type}.gif\" title=\"This is a {$view_type} view\" alt=\"{$view_type}\" /></div><div class=\{$default_class}\"><strong><a href=\"{$base_url}&amp;action=views&amp;do=edit&amp;vid={$view['vid']}\" >{$view['title']}</a></strong>{$default_add}{$created}</div>");
			
			$popup = new PopupMenu("view_{$view['vid']}", $lang->options);
			$popup->add_item("Edit View", "{$base_url}&amp;action=views&amp;do=edit&amp;vid={$view['vid']}");
			if($view['vid'] != $default_view)
			{
				$popup->add_item("Set as Default", "{$base_url}&amp;action=views&amp;do=set_default&amp;vid={$view['vid']}");
			}
			$popup->add_item("Delete View", "{$base_url}&amp;action=views&amp;do=delete&amp;vid={$view['vid']}", "return AdminCP.deleteConfirmation(this, 'Delete this view?')");
			$controls = $popup->fetch();
			$table->construct_cell($controls, array("class" => "align_center"));
			$table->construct_row();
		}
			
		$table->output("Views");
		
		echo <<<LEGEND
<br />
<fieldset>
<legend>{$lang->legend}</legend>
<img src="styles/{$page->style}/images/icons/view_default.gif" alt="default" style="vertical-align: middle;" /> Default view created by MyBB. Cannot be edited or removed.<br />
<img src="styles/{$page->style}/images/icons/view_public.gif" alt="public" style="vertical-align: middle;" /> Public view visible to all administrators.<br />
<img src="styles/{$page->style}/images/icons/view_private.gif" alt="private" style="vertical-align: middle;" /> Private view visible only to yourself.</fieldset>
LEGEND;
		$page->output_footer();	
	}
}

function set_default_view($type, $vid)
{
	global $mybb, $db;

	$query = $db->simple_select("adminoptions", "defaultviews", "uid='{$mybb->user['uid']}'");
	$default_views = unserialize($db->fetch_field($query, "defaultviews"));
	if(!$db->num_rows($query))
	{
		$create = true;
	}
	$default_views[$type] = $vid;
	$default_views = serialize($default_views);
	$updated_admin = array("defaultviews" => $db->escape_string($default_views));

	if($create == true)
	{
		$updated_admin['uid'] = $mybb->user['uid'];
		$db->insert_query("adminoptions", $updated_admin);
	}
	else
	{
		$db->update_query("adminoptions", $updated_admin, "uid='{$mybb->user['uid']}'");
	}
}

function fetch_default_view($type)
{
	global $mybb, $db;
	$query = $db->simple_select("adminoptions", "defaultviews", "uid='{$mybb->user['uid']}'");
	$default_views = unserialize($db->fetch_field($query, "defaultviews"));
	if(!is_array($default_views))
	{
		return false;
		$create = true;
	}
	return $default_views[$type];
}
?>