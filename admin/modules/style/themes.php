<?php
/**
 * MyBB 1.4
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

if($mybb->input['action'] == "xmlhttp_stylesheet" && $mybb->request_method == "post")
{
	$query = $db->simple_select("themestylesheets", "*", "sid='".intval($mybb->input['sid'])."'");
	$stylesheet = $db->fetch_array($query);
	
	if(!$stylesheet['sid'])
	{
		flash_message("You have selected an invalid stylesheet.", 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	$css_array = css_to_array($stylesheet['stylesheet']);
	$selector_list = get_selectors_as_options($css_array, $mybb->input['selector']);
	$editable_selector = $css_array[$mybb->input['selector']];
	$properties = parse_css_properties($editable_selector['values']);
	
	$form = new Form("index.php?module=style/themes&amp;action=stylesheet_properties", "post", "selector_form", 0, "", true);
	
	$table = new Table;	
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('background', $properties['background'], array('id' => 'background', 'style' => 'width: 260px;'))."</div><div>Background</div>", array('style' => 'width: 20%;'));
	$table->construct_cell("Extra CSS Attributes<br /><div style=\"align: center;\">".$form->generate_text_area('extra', $properties['extra'], array('id' => 'extra', 'style' => 'width: 98%;', 'rows' => '19'))."</div>", array('rowspan' => 8));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('color', $properties['color'], array('id' => 'color', 'style' => 'width: 260px;'))."</div><div>Color</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('width', $properties['width'], array('id' => 'width', 'style' => 'width: 260px;'))."</div><div>Width</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('font', $properties['font'], array('id' => 'font', 'style' => 'width: 260px;'))."</div><div>Font Color</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('font_family', $properties['font-family'], array('id' => 'font_family', 'style' => 'width: 260px;'))."</div><div>Font Family</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('font_size', $properties['font-size'], array('id' => 'font_size', 'style' => 'width: 260px;'))."</div><div>Font Size</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('font_style', $properties['font-style'], array('id' => 'font_style', 'style' => 'width: 260px;'))."</div><div>Font Style</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('font_weight', $properties['font-weight'], array('id' => 'font_weight', 'style' => 'width: 260px;'))."</div><div>Font Weight</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	
	$table->output("<span id=\"mini_spinner\"></span>".htmlspecialchars_uni($editable_selector['class_name'])."<span id=\"saved\" style=\"color: #FEE0C6;\"></span>");
	exit;
}

$page->add_breadcrumb_item("Themes", "index.php?module=style/themes");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "import" || !$mybb->input['action'])
{
	$sub_tabs['themes'] = array(
		'title' => "Themes",
		'link' => "index.php?module=style/themes",
		'description' => "Here you can manage your themes."
	);

	$sub_tabs['create_theme'] = array(
		'title' => "Create New Theme",
		'link' => "index.php?module=style/themes&amp;action=add",
		'description' => "Here you can create a new theme based on the default. <strong>Template sets, stylesheets, and other settings are inherited from the parent theme.</strong>"
	);

	$sub_tabs['import_theme'] = array(
		'title' => "Import a Theme",
		'link' => "index.php?module=style/themes&amp;action=import",
		'description' => "Here you can import new themes."
	);
}

if($mybb->input['action'] == "import")
{
	if($mybb->request_method == "post")
	{
		if(!$mybb->input['local_file'] && !$mybb->input['url'])
		{
			$errors[] = "Please enter a theme a import.";
		}
		
		if(!$errors)
		{
			// Find out if there was an uploaded file
			if($_FILES['compfile']['error'] != 4)
			{
				// Find out if there was an error with the uploaded file
				if($_FILES['compfile']['error'] != 0)
				{
					$errors[] = $lang->error_uploadfailed.$lang->error_uploadfailed_detail;
					switch($_FILES['compfile']['error'])
					{
						case 1: // UPLOAD_ERR_INI_SIZE
							$errors[] = $lang->error_uploadfailed_php1;
							break;
						case 2: // UPLOAD_ERR_FORM_SIZE
							$errors[] = $lang->error_uploadfailed_php2;
							break;
						case 3: // UPLOAD_ERR_PARTIAL
							$errors[] = $lang->error_uploadfailed_php3;
							break;
						case 4: // UPLOAD_ERR_NO_FILE
							$errors[] = $lang->error_uploadfailed_php4;
							break;
						case 6: // UPLOAD_ERR_NO_TMP_DIR
							$errors[] = $lang->error_uploadfailed_php6;
							break;
						case 7: // UPLOAD_ERR_CANT_WRITE
							$errors[] = $lang->error_uploadfailed_php7;
							break;
						default:
							$errors[] = $lang->sprintf($lang->error_uploadfailed_phpx, $_FILES['compfile']['error']);
							break;
					}
				}
				
				if(!$errors)
				{
					// Was the temporary file found?
					if(!is_uploaded_file($_FILES['compfile']['tmp_name']))
					{
						$errors[] = $lang->error_uploadfailed_lost;
					}
					// Get the contents
					$contents = @file_get_contents($_FILES['compfile']['tmp_name']);
					// Delete the temporary file if possible
					@unlink($_FILES['compfile']['tmp_name']);
					// Are there contents?
					if(!trim($contents))
					{
						$errors[] = $lang->error_uploadfailed_nocontents;
					}
				}
			}
			elseif(!empty($mybb->input['localfile']))
			{
				// Get the contents
				$contents = @fetch_remote_file($mybb->input['localfile']);
				if(!$contents)
				{
					$errors[] = $lang->error_local_file;
				}
			}
			
			if(!$errors)
			{
				$options = array(
					'no_stylesheets' => intval($mybb->input['import_stylesheets']),
					'no_templates' => intval($mybb->input['import_templates']),
					'version_compat' => intval($mybb->input['version_compat']),
					'tid' => intval($mybb->input['tid']),
				);
				$theme_id = import_theme_xml($contents, $options);
				
				if($theme_id > -1)
				{
					// Log admin action
					log_admin_action($theme_id);
			
					flash_message("Successfully imported the theme.", 'success');
					admin_redirect("index.php?module=style/themes&action=edit&tid=".$theme_id);
				}
				else
				{
					switch($theme_id)
					{
						case -1:
							$errors[] = "MyBB could not find the theme with the file you uploaded. Please check the file is the correct and is not corrupt.";
							break;
						case -2:
							$errors[] = "This theme has been written for another version of MyBB. Please check the \"Ignore Version Compatibility\" to ignore this error.";
							break;
					}
				}
			}
		}
	}
	
	$query = $db->simple_select("themes", "tid, name");
	while($theme = $db->fetch_array($query))
	{
		$themes[$theme['tid']] = $theme['name'];
	}
	
	$page->add_breadcrumb_item("Import Theme", "index.php?module=style/themes&amp;action=add");
	
	$page->output_header("Themes - Import Theme");
	
	$page->output_nav_tabs($sub_tabs, 'import_theme');
	
	if($errors)
	{
		$page->output_inline_error($errors);
		
		if($mybb->input['import'] == 1)
		{
			$import_checked[1] = "";
			$import_checked[2] = "checked=\"checked\"";
		}
		else
		{
			$import_checked[1] = "checked=\"checked\"";
			$import_checked[2] = "";
		}
	}
	else
	{
		$import_checked[1] = "checked=\"checked\"";
		$import_checked[2] = "";
		
		$mybb->input['import_stylesheets'] = true;
		$mybb->input['import_templates'] = true;
	}
	
	$form = new Form("index.php?module=style/themes&amp;action=add", "post");
	
	$actions = '<script type="text/javascript">
    function checkAction(id)
    {
        var checked = \'\';
		
        $$(\'.\'+id+\'s_check\').each(function(e)
        {
            if(e.checked == true)
            {
                checked = e.value;
            }
        });
        $$(\'.\'+id+\'s\').each(function(e)
        {
        	Element.hide(e);
        });
        if($(id+\'_\'+checked))
        {
            Element.show(id+\'_\'+checked);
        }
    }    
</script>
	<dl style="margin-top: 0; margin-bottom: 0; width: 35%;">
	<dt><label style="display: block;"><input type="radio" name="import" value="0" '.$import_checked[1].' class="imports_check" onclick="checkAction(\'import\');" style="vertical-align: middle;" /> Local File</label></dt>
		<div id="import_0" class="imports">
		<dl style="margin-top: 0; margin-bottom: 0; width: 100%;">
	<dt><label style="display: block;"><table cellpadding="4">
				<tr>
					<td>'.$form->generate_file_upload_box("local_file", array('style' => 'width: 100%;')).'</td>
				</tr>
		</table></label></dt></dl>
		</div>
		<dt><label style="display: block;"><input type="radio" name="import" value="1" '.$import_checked[2].' class="imports_check" onclick="checkAction(\'import\');" style="vertical-align: middle;" /> URL</label></dt>
		<div id="import_1" class="imports">
		<dl style="margin-top: 0; margin-bottom: 0; width: 100%;">
	<dt><label style="display: block;"><table cellpadding="4">
				<tr>
					<td>'.$form->generate_text_box("url", $mybb->input['file']).'</td>
				</tr>
		</table></label></dt></dl>
		</div>
	</dl>
	<script type="text/javascript">
	checkAction(\'import\');
	</script>';
	
	$form_container = new FormContainer("Create a Theme");
	$form_container->output_row("Import from", "Select a file to import. You can either import the theme file from your computer or from a URL.", $actions, 'file');
	$form_container->output_row("Parent Theme", "Select the theme this theme should be a child of.", $form->generate_select_box('tid', $themes, $mybb->input['tid'], array('id' => 'tid')), 'tid');
	$form_container->output_row("New Name", "A new name for the imported theme. If left blank, the name specified in the theme file will be used.", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row("Advanced Options", "A new name for the imported theme. If left blank, the name specified in the theme file will be used.", $form->generate_check_box('version_compat', '1', "Ignore Version Compatibility", array('checked' => $mybb->input['version_compat'], 'id' => 'version_compat'))."<br /><small>Should this theme be installed regardless of the version of MyBB it was created for?</small><br />".$form->generate_check_box('import_stylesheets', '1', "Import Stylesheets", array('checked' => $mybb->input['import_stylesheets'], 'id' => 'import_stylesheets'))."<br /><small>If this theme contains custom stylesheets should they be imported?</small><br />".$form->generate_check_box('import_templates', '1', "Import Templates", array('checked' => $mybb->input['import_templates'], 'id' => 'import_templates'))."<br /><small>If this theme contains custom templates should they be imported?</small>");
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button("Create New Theme");

	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "export")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?module=style/themes");
	}

	if($mybb->request_method == "post")
	{
	}
}

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!$mybb->input['name'])
		{
			$errors[] = "Please enter a name for this theme.";
		}
		
		if(!$errors)
		{
			$tid = build_new_theme($mybb->input['name'], null, $mybb->input['tid']);
			
			// Log admin action
			log_admin_action($mybb->input['name'], $sid);
			
			flash_message("Successfully created the theme.", 'success');
			admin_redirect("index.php?module=style/themes&action=edit&tid=".$tid);
		}
	}
	
	$query = $db->simple_select("themes", "tid, name");
	while($theme = $db->fetch_array($query))
	{
		$themes[$theme['tid']] = $theme['name'];
	}
	
	$page->add_breadcrumb_item("Create New Theme", "index.php?module=style/themes&amp;action=add");
	
	$page->output_header("Themes - Create New Theme");
	
	$page->output_nav_tabs($sub_tabs, 'create_theme');
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	
	$form = new Form("index.php?module=style/themes&amp;action=add", "post");
	
	$form_container = new FormContainer("Create a Theme");
	$form_container->output_row("Name", "Specify a name for the new theme.", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row("Parent Theme", "Select the theme this theme should be a child of.", $form->generate_select_box('tid', $themes, $mybb->input['tid'], array('id' => 'tid')), 'tid');
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button("Create New Theme");

	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?module=style/themes");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=styles/themes");
	}

	if($mybb->request_method == "post")
	{
		// Do delete

		// Delete from themes

		// Delete from stylesheets
		
		// Delete from users

		// Delete any references to inherited stylesheets
		
		flash_message("Successfully deleted the specified theme.", 'success');
		admin_redirect("index.php?module=style/themes");
	}
	else
	{		
		$page->output_confirm_action("index.php?module=style/themes&amp;action=delete&amp;tid={$theme['tid']}", "Are you sure you want to delete this theme?");
	}

	// Are any other themes relying on stylesheets from this theme? Get a list and show a warning
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	
	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	if($mybb->request_method == "post")
	{
		
	}
		
	// Fetch list of all of the stylesheets for this theme
	$file_stylesheets = unserialize($theme['stylesheets']);
	
	$stylesheets = array();
	$inherited_load = array();
	
	// Now we loop through the list of stylesheets for each file
	foreach($file_stylesheets as $file => $action_stylesheet)
	{
		if($file == 'inherited')
		{
			continue;
		}
		
		foreach($action_stylesheet as $action => $style)
		{
			foreach($style as $stylesheet)
			{
				$stylesheets[$stylesheet]['applied_to'][$file][] = $action;
				if(is_array($file_stylesheets['inherited'][$file."_".$action]) && in_array($stylesheet, array_keys($file_stylesheets['inherited'][$file."_".$action])))
				{
					$stylesheets[$stylesheet]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];
					foreach($file_stylesheets['inherited'][$file."_".$action] as $value)
					{
						$inherited_load[] = $value;
					}
				}
			}
		}
	}
		
	$inherited_load = array_unique($inherited_load);
	
	$inherited_themes = array();
	if(count($inherited_load) > 0)
	{
		$query = $db->simple_select("themes", "tid, name", "tid IN (".implode(",", $inherited_load).")");
		while($inherited_theme = $db->fetch_array($query))
		{
			$inherited_themes[$inherited_theme['tid']] = $inherited_theme['name'];
		}
	}
	
	$theme_stylesheets = array();
	
	$query = $db->simple_select("themestylesheets", "*", "tid IN (".implode(",", $inherited_load).")");
	while($theme_stylesheet = $db->fetch_array($query))
	{
		$theme_stylesheets[$theme_stylesheet['cachefile']] = $theme_stylesheet;
	}
	
	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
	
	$page->output_header("Themes - Stylesheets");
	
	$sub_tabs['edit_stylesheets'] = array(
		'title' => "Edit Stylesheets",
		'link' => "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}",
		'description' => "Here you can easily manage this theme's stylesheets."
	);

	$sub_tabs['add_stylesheet'] = array(
		'title' => "Add Stylesheet",
		'link' => "index.php?module=style/themes&amp;action=add_stylesheet&amp;tid={$mybb->input['tid']}",
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_stylesheets');
	
	$table = new Table;
	$table->construct_header("Stylesheets");
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));
	
	foreach($stylesheets as $filename => $style)
	{
		$style['sid'] = $theme_stylesheets[basename($filename)]['sid'];
		
		// Has the file on the file system been modified?
		resync_stylesheet($theme_stylesheets[basename($filename)]);
		
		$inherited = "";
		$inherited_ary = array();
		if(is_array($style['inherited']))
		{
			foreach($style['inherited'] as $tid)
			{
				if($inherited_themes[$tid])
				{
					$inherited_ary[$tid] = $inherited_themes[$tid];
				}
			}
		}
		
		if(!empty($inherited_ary))
		{
			$inherited = " <small>(Inherited from";
			$sep = " ";
			$inherited_count = count($inherited_ary);
			$count = 0;
			
			foreach($inherited_ary as $tid => $file)
			{
				if($count == $applied_to_count && $count != 0)
				{
					$sep = ", and ";
				}
				
				$inherited .= $sep.$file;
				$sep = ", ";
				
				++$count;
			}
			$inherited .= ")</small>";
		}
		
		if(is_array($style['applied_to']) && $style['applied_to']['global'][0] != "global")
		{
			$attached_to = "<small>Attached to";
			
			$applied_to_count = count($style['applied_to']);
			$count = 0;
			$sep = " ";
			foreach($style['applied_to'] as $name => $actions)
			{
				if(!$name)
				{
					continue;
				}
				
				if($actions[0] != "global")
				{
					$name = "actions ".implode(',', $actions)." of ".$name;
				}
				
				if($count == $applied_to_count && $count != 0)
				{
					$sep = ", and ";
				}
				$attached_to .= $sep.$name;
				
				$sep = ", ";
				
				++$count;
			}
			
			$attached_to .= "</small>";
		}
		else
		{
			$attached_to = "<small>Attached to all pages</small>";
		}
		
		$popup = new PopupMenu("style_{$style['sid']}", $lang->options);
		
		$popup->add_item("Properties", "index.php?module=style/themes&amp;action=stylesheet_properties&amp;sid={$style['sid']}&amp;tid={$theme['tid']}");
		$popup->add_item("Edit Style", "index.php?module=style/themes&amp;action=edit_stylesheet&amp;sid={$style['sid']}&amp;tid={$theme['tid']}");
		$popup->add_item("Delete", "index.php?module=style/themes&amp;action=delete_stylesheet&amp;sid={$style['sid']}&amp;tid={$theme['tid']}", "return AdminCP.deleteConfirmation(this, 'Are you sure you want to delete this stylesheet?')");
		
		$table->construct_cell("<strong>".basename($filename)."</strong>{$inherited}<br />{$attached_to}");
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
	}
	
	$table->output("Stylesheets in ".htmlspecialchars_uni($theme['name']));
	
	$page->output_footer();
} 

if($mybb->input['action'] == "stylesheet_properties")
{
	$query = $db->simple_select("themestylesheets", "*", "sid='".intval($mybb->input['sid'])."'");
	$stylesheet = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$stylesheet['sid'])
	{
		flash_message("You have selected an invalid stylesheet.", 'error');
		admin_redirect("index.php?module=style/themes");
	}

	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	// Fetch list of all of the stylesheets for this theme
	$file_stylesheets = unserialize($theme['stylesheets']);
	
	$stylesheets = array();
	$inherited_load = array();
	
	// Now we loop through the list of stylesheets for each file
	foreach($file_stylesheets as $file => $action_stylesheet)
	{
		if($file == 'inherited')
		{
			continue;
		}
		
		foreach($action_stylesheet as $action => $style)
		{
			foreach($style as $stylesheet2)
			{
				$stylesheets[$stylesheet2]['applied_to'][$file][] = $action;
				if(is_array($file_stylesheets['inherited'][$file."_".$action]) && in_array($stylesheet2, array_keys($file_stylesheets['inherited'][$file."_".$action])))
				{
					$stylesheets[$stylesheet2]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];
					foreach($file_stylesheets['inherited'][$file."_".$action] as $value)
					{
						$inherited_load[] = $value;
					}
				}
			}
		}
	}
	
	foreach($stylesheets as $file => $stylesheet2)
	{		
		foreach($stylesheet2['inherited'] as $inherited_file => $tid)
		{
			$stylesheet2['inherited'][basename($inherited_file)] = $tid;
			unset($stylesheet2['inherited'][$inherited_file]);
		}
		
		$stylesheets[basename($file)] = $stylesheet2;
		unset($stylesheets[$file]);
	}
	
	$this_stylesheet = $stylesheets[$stylesheet['name']];	
	unset($stylesheets);
	
	if($mybb->request_method == "post")
	{
		if(!$mybb->input['name'])
		{
			$errors[] = "Please enter a name for this stylesheet.";
		}
		
		if(!$errors)
		{
			// Inheriting?

			// Break inherit relationship
			// Copy stylesheet to this theme
			// Remove inherited stylesheet
		
			flash_message("Successfully updated the specified stylesheet's proporties.", 'success');
			admin_redirect("index.php?module=style/themes");
		}
	}
	
	
	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
	$page->add_breadcrumb_item(htmlspecialchars_uni($stylesheet['name'])." Properties", "index.php?module=style/themes&amp;action=edit_properties&amp;tid={$mybb->input['tid']}");
	
	$page->output_header("Themes - Stylesheet Properties");
	
	//$page->output_nav_tabs($sub_tabs, 'themes');

	// If the stylesheet and theme do not match, we must be editing something that is inherited
	if($this_stylesheet['inherited'][$stylesheet['name']])
	{
		$query = $db->simple_select("themes", "name", "tid='{$stylesheet['tid']}'");
		$stylesheet_parent = htmlspecialchars_uni($db->fetch_field($query, 'name'));
		
		// Show inherited warning
		if($stylesheet['tid'] == 1)
		{
			$page->output_alert("This stylesheet is currently being inherited from {$stylesheet_parent}. Any changes you make will break the inheritance, and the stylesheet will be copied to this theme.");
		}
		else
		{
			$page->output_alert("This stylesheet is currently being inherited from {$stylesheet_parent}. Any changes you make will break the inheritance, and the stylesheet will be copied to this theme. Edit this stylesheet in {$stylesheet_parent} to keep the inheritance.");
		}
	}
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	
	$form = new Form("index.php?module=style/themes&amp;action=stylesheet_properties", "post");
	
	
	$applied_to = $this_stylesheet['applied_to'];
	unset($this_stylesheet);
	
	if(is_array($applied_to) && $applied_to['global'][0] != "global")
	{
		$specific_files = "<div id=\"attach_1\" class=\"attachs\">";
		$check_actions = "";
		
		$global_checked[2] = "checked=\"checked\"";
		$global_checked[1] = "";
		
		$count = 0;
		foreach($applied_to as $name => $actions)
		{
			$short_name = substr($name, 0, -4);
			
			if($errors)
			{
				$action_list = $mybb->input['action_list_'.$short_name];
			}
			else
			{
				$action_list = "";
				if($actions[0] != "global")
				{
					$action_list = implode(',', $actions);
				}
			}
			
			if(!$action_list)
			{
				$global_action_checked[1] = "checked=\"checked\"";
				$global_action_checked[2] = "";
			}
			else
			{
				$global_action_checked[2] = "checked=\"checked\"";
				$global_action_checked[1] = "";
			}
			
			$specific_file = "<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_{$count}\" value=\"0\" {$global_action_checked[1]} class=\"action_{$count}s_check\" onclick=\"checkAction('action_{$count}');\" style=\"vertical-align: middle;\" /> Globally</label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_{$count}\" value=\"1\" {$global_action_checked[2]} class=\"action_{$count}s_check\" onclick=\"checkAction('action_{$count}');\" style=\"vertical-align: middle;\" /> Specific actions</label></dt>
			<dd style=\"margin-top: 4px;\" id=\"action_{$count}_1\" class=\"action_{$count}s\">
			<table cellpadding=\"4\">
				<tr>
					<td>".$form->generate_text_box('action_list_'.$count, $action_list, array('id' => 'action_list_'.$count, 'style' => 'width: 190px;'))."</td>
				</tr>
			</table>
		</dd>
		</dl>";
			
			$form_container = new FormContainer();
			$form_container->output_row("", "", "<span style=\"float: right;\"><a href=\"\" id=\"delete_img_{$count}\"><img src=\"styles/{$page->style}/images/icons/cross.gif\" alt=\"Delete\" title=\"Delete\" /></a></span>File &nbsp;".$form->generate_text_box("attached_{$count}", $name, array('id' => "attached_{$count}", 'style' => 'width: 200px;')), "attached_{$count}");
	
			$form_container->output_row("", "", $specific_file);
	
			$specific_files .= "<div id=\"attached_form_{$count}\">".$form_container->end(true)."</div>";
			
			$check_actions .= "\n\tcheckAction('action_{$count}');";
			
			++$count;
		}
		
		$specific_files .= "</div>";
	}
	else
	{
		$global_checked[1] = "checked=\"checked\"";
		$global_checked[2] = "";
	}
	
	$actions = '<script type="text/javascript">
    function checkAction(id)
    {
        var checked = \'\';
		
        $$(\'.\'+id+\'s_check\').each(function(e)
        {
            if(e.checked == true)
            {
                checked = e.value;
            }
        });
        $$(\'.\'+id+\'s\').each(function(e)
        {
        	Element.hide(e);
        });
        if($(id+\'_\'+checked))
        {
            Element.show(id+\'_\'+checked);
        }
    }    
</script>
	<small>Attached to</small>
	<dl style="margin-top: 0; margin-bottom: 0; width: 40%;">
	<dt><label style="display: block;"><input type="radio" name="attach" value="0" '.$global_checked[1].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> Globally</label></dt>
		<dt><label style="display: block;"><input type="radio" name="attach" value="1" '.$global_checked[2].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> Specific files (<a id="new_specific_file">Add another</a>)</label></dt><br />
		'.$specific_files.'
	</dl>
	<script type="text/javascript">
	checkAction(\'attach\');'.$check_actions.'
	</script>';
	
	echo $form->generate_hidden_field("sid", $stylesheet['sid'])."<br />\n";

	$form_container = new FormContainer("Edit Stylesheet Properties for ".htmlspecialchars_uni($stylesheet['name']));
	$form_container->output_row("File Name", "", $form->generate_text_box('name', $stylesheet['name'], array('id' => 'name', 'style' => 'width: 200px;')), 'name');
	
	$form_container->output_row("", "", $actions);
	
	$form_container->end();
	
	$buttons[] = $form->generate_submit_button("Save Stylesheet Properties");

	$form->output_submit_wrapper($buttons);
	
	echo '<script type="text/javascript" src="./jscripts/themes.js"></script>';
	echo '<script type="text/javascript">

Event.observe(window, "load", function() {
//<![CDATA[
    new ThemeSelector(\''.$count.'\');
});
//]]>
</script>';
	
	$form->end();
	
	$page->output_footer();
}

// Shows the page where you can actually edit a particular selector or the whole stylesheet
if($mybb->input['action'] == "edit_stylesheet" && (!$mybb->input['mode'] || $mybb->input['mode'] == "simple"))
{
	$query = $db->simple_select("themestylesheets", "*", "sid='".intval($mybb->input['sid'])."'");
	$stylesheet = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$stylesheet['sid'])
	{
		flash_message("You have selected an invalid stylesheet.", 'error');
		admin_redirect("index.php?module=style/themes");
	}

	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?module=style/themes");
	}

	if($mybb->request_method == "post")
	{
		$sid = $theme['sid'];
		
		/*
		// Theme & stylesheet theme ID do not match, editing inherited - we copy to local theme
		if($theme['tid'] != $stylesheet['tid'])
		{
			$sid = copy_stylesheet_to_theme($stylesheet, $theme['id']);
		}

		// Insert the modified CSS
		$new_stylesheet = $stylesheet['stylesheet'];

		$css_to_insert = '';
		foreach($mybb->input['css_bits'] as $field => $value)
		{
			if($field == "extra")
			{
				$css_to_insert .= $value."\n";
			}
			else
			{
				$css_to_insert .= "{$field}: {$value}\n";
			}
		}
		$new_stylesheet = insert_into_css($css_to_insert, $class_id, $new_stylesheet, $mybb->input['selector']);

		// Now we have the new stylesheet, save it
		$updated_stylesheet = array(
			"stylesheet" => $db->escape_string($new_stylesheet),
			"lastmodified" => TIME_NOW
		);
		$db->update_query("themestylesheets", $updated_stylesheet, "sid='{$sid}'");

		// Cache the stylesheet to the file
		cache_stylesheet($theme['tid'], $stylesheet['name'], $new_stylesheet);

		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid']);*/

		// Log admin action
		log_admin_action($theme['name'], $stylesheet['name']);

		if(!$mybb->input['ajax'])
		{			
			flash_message("The stylesheet has successfully been updated.", 'success');
				
			if($mybb->input['save_close'])
			{
				admin_redirect("index.php?module=style/themes&action=edit&tid={$theme['tid']}");
			}
			else
			{
				admin_redirect("index.php?module=style/themes&action=edit_stylesheet&tid={$theme['tid']}&sid={$mybb->input['sid']}");
			}
		}
		else
		{
			echo " (Saved @ ".my_date($mybb->settings['timeformat'], TIME_NOW).")";
			exit;
		}
	}
	
	// Fetch list of all of the stylesheets for this theme
	$file_stylesheets = unserialize($theme['stylesheets']);
	
	$stylesheets = array();
	$inherited_load = array();
	
	// Now we loop through the list of stylesheets for each file
	foreach($file_stylesheets as $file => $action_stylesheet)
	{
		if($file == 'inherited')
		{
			continue;
		}
		
		foreach($action_stylesheet as $action => $style)
		{
			foreach($style as $stylesheet2)
			{
				$stylesheets[$stylesheet2]['applied_to'][$file][] = $action;
				if(is_array($file_stylesheets['inherited'][$file."_".$action]) && in_array($stylesheet2, array_keys($file_stylesheets['inherited'][$file."_".$action])))
				{
					$stylesheets[$stylesheet2]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];
					foreach($file_stylesheets['inherited'][$file."_".$action] as $value)
					{
						$inherited_load[] = $value;
					}
				}
			}
		}
	}
	
	foreach($stylesheets as $file => $stylesheet2)
	{		
		foreach($stylesheet2['inherited'] as $inherited_file => $tid)
		{
			$stylesheet2['inherited'][basename($inherited_file)] = $tid;
			unset($stylesheet2['inherited'][$inherited_file]);
		}
		
		$stylesheets[basename($file)] = $stylesheet2;
		unset($stylesheets[$file]);
	}
	
	$this_stylesheet = $stylesheets[$stylesheet['name']];	
	unset($stylesheets);
	
	$page->extra_header .= "
	<script type=\"text/javascript\">
	var my_post_key = '".$mybb->post_code."';
	</script>";
	
	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
	$page->add_breadcrumb_item("Editing ".htmlspecialchars_uni($stylesheet['name']), "index.php?module=style/themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;sid={$mybb->input['sid']}&amp;mode=simple");
	
	$page->output_header("Themes - Edit Stylesheet");

	// If the stylesheet and theme do not match, we must be editing something that is inherited
	if($this_stylesheet['inherited'][$stylesheet['name']])
	{
		$query = $db->simple_select("themes", "name", "tid='{$stylesheet['tid']}'");
		$stylesheet_parent = htmlspecialchars_uni($db->fetch_field($query, 'name'));
		
		// Show inherited warning
		if($stylesheet['tid'] == 1)
		{
			$page->output_alert("This stylesheet is currently being inherited from {$stylesheet_parent}. Any changes you make will break the inheritance, and the stylesheet will be copied to this theme.");
		}
		else
		{
			$page->output_alert("This stylesheet is currently being inherited from {$stylesheet_parent}. Any changes you make will break the inheritance, and the stylesheet will be copied to this theme. Edit this stylesheet in {$stylesheet_parent} to keep the inheritance.");
		}
	}
	
	$sub_tabs['edit_stylesheet'] = array(
		'title' => "Edit Stylesheet: Simple Mode",
		'link' => "index.php?module=style/themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;sid={$mybb->input['sid']}&amp;mode=simple",
		'description' => "Here you can easily edit your theme's stylesheet."
	);

	$sub_tabs['edit_stylesheet_advanced'] = array(
		'title' => "Edit Stylesheet: Advanced Mode",
		'link' => "index.php?module=style/themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;sid={$mybb->input['sid']}&amp;mode=advanced",
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_stylesheet');

	// Has the file on the file system been modified?
	if(resync_stylesheet($stylesheet))
	{
		// Need to refetch new stylesheet as it was modified
		$query = $db->simple_select("themestylesheets", "stylesheet", "sid='{$stylesheet['sid']}'");
		$stylesheet['stylesheet'] = $db->fetch_field($query, 'stylesheet');
	}

	$css_array = css_to_array($stylesheet['stylesheet']);
	$selector_list = get_selectors_as_options($css_array, $mybb->input['selector']);
	
	// Output the selection box
	$form = new Form("index.php?module=style/themes&amp;action=edit_stylesheet", "post", "selector_form");
	echo $form->generate_hidden_field("tid", $mybb->input['tid'])."\n";
	echo $form->generate_hidden_field("sid", $mybb->input['sid'])."\n";	
	
	echo "Selector: <select id=\"selector\">{$selector_list}</select> ".$form->generate_submit_button("Go")."<br /><br />";

	$form->end();

	// Haven't chosen a selector to edit, show the first one from the stylesheet
	if(!$mybb->input['selector'])
	{
		reset($css_array);
		$key = key($css_array);
		$editable_selector = $css_array[$key];
	}
	// Show a specific selector
	else
	{
		$editable_selector = $css_array[$mybb->input['selector']];
	}
	
	// Get the properties from this item
	$properties = parse_css_properties($editable_selector['values']);
	
	$form = new Form("index.php?module=style/themes&amp;action=edit_stylesheet", "post");
	echo $form->generate_hidden_field("tid", $mybb->input['tid'], array('id' => "tid"))."\n";
	echo $form->generate_hidden_field("sid", $mybb->input['sid'], array('id' => "sid"))."\n";
	
	echo "<div id=\"stylesheet\">";
	
	$table = new Table;	
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('background', $properties['background'], array('id' => 'background', 'style' => 'width: 260px;'))."</div><div>Background</div>", array('style' => 'width: 20%;'));
	$table->construct_cell("Extra CSS Attributes<br /><div style=\"align: center;\">".$form->generate_text_area('extra', $properties['extra'], array('id' => 'extra', 'style' => 'width: 98%;', 'rows' => '19'))."</div>", array('rowspan' => 8));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('color', $properties['color'], array('id' => 'color', 'style' => 'width: 260px;'))."</div><div>Color</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('width', $properties['width'], array('id' => 'width', 'style' => 'width: 260px;'))."</div><div>Width</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('font', $properties['font'], array('id' => 'font', 'style' => 'width: 260px;'))."</div><div>Font Color</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('font_family', $properties['font-family'], array('id' => 'font_family', 'style' => 'width: 260px;'))."</div><div>Font Family</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('font_size', $properties['font-size'], array('id' => 'font_size', 'style' => 'width: 260px;'))."</div><div>Font Size</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('font_style', $properties['font-style'], array('id' => 'font_style', 'style' => 'width: 260px;'))."</div><div>Font Style</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('font_weight', $properties['font-weight'], array('id' => 'font_weight', 'style' => 'width: 260px;'))."</div><div>Font Weight</div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	
	$table->output("<span id=\"mini_spinner\"></span>".htmlspecialchars_uni($editable_selector['class_name'])."<span id=\"saved\" style=\"color: #FEE0C6;\"></span>");
	
	echo "</div>";
	
	$buttons[] = $form->generate_reset_button("Reset");
	$buttons[] = $form->generate_submit_button("Save Changes", array('id' => 'save', 'name' => 'save'));
	$buttons[] = $form->generate_submit_button("Save Changes & Close", array('id' => 'save_close', 'name' => 'save_close'));

	$form->output_submit_wrapper($buttons);
	
	echo '<script type="text/javascript" src="./jscripts/themes.js"></script>';
	echo '<script type="text/javascript">

Event.observe(window, "load", function() {
//<![CDATA[
    new ThemeSelector("./index.php?module=style/themes&action=xmlhttp_stylesheet", "./index.php?module=style/themes&action=edit_stylesheet", $("selector"), $("stylesheet"), "'.$mybb->input['sid'].'", $("selector_form"), "'.$mybb->input['tid'].'");
});
//]]>
</script>';

	$form->end();
	
	$page->output_footer();
}

if($mybb->input['action'] == "edit_stylesheet" && $mybb->input['mode'] == "advanced")
{
	$query = $db->simple_select("themestylesheets", "*", "sid='".intval($mybb->input['sid'])."'");
	$stylesheet = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$stylesheet['sid'])
	{
		flash_message("You have selected an invalid stylesheet.", 'error');
		admin_redirect("index.php?module=style/themes");
	}

	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);
	
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?module=style/themes");
	}

	if($mybb->request_method == "post")
	{
		$sid = $theme['sid'];

		/*
		// Theme & stylesheet theme ID do not match, editing inherited - we copy to local theme
		if($theme['tid'] != $stylesheet['tid'])
		{
			$sid = copy_stylesheet_to_theme($stylesheet, $theme['id']);
		}

		// Now we have the new stylesheet, save it
		$updated_stylesheet = array(
			"stylesheet" => $db->escape_string($mybb->input['stylesheet']),
			"lastmodified" => TIME_NOW
		);
		$db->update_query("themestylesheets", $updated_stylesheet, "sid='{$sid}'");

		// Cache the stylesheet to the file
		cache_stylesheet($theme['tid'], $stylesheet['name'], $new_stylesheet);

		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid']);
		*/

		// Log admin action
		log_admin_action($theme['name'], $stylesheet['name']);

		flash_message("The stylesheet has successfully been updated.", 'success');
		
		if($mybb->input['save_code'])
		{
			admin_redirect("index.php?module=style/themes?action=edit_stylesheet&tid={$theme['tid']}&mode=advanced");
		}
		else
		{
			admin_redirect("index.php?module=style/themes?action=edit&tid={$theme['tid']}");
		}
	}
	
	// Fetch list of all of the stylesheets for this theme
	$file_stylesheets = unserialize($theme['stylesheets']);
	
	$stylesheets = array();
	$inherited_load = array();
	
	// Now we loop through the list of stylesheets for each file
	foreach($file_stylesheets as $file => $action_stylesheet)
	{
		if($file == 'inherited')
		{
			continue;
		}
		
		foreach($action_stylesheet as $action => $style)
		{
			foreach($style as $stylesheet2)
			{
				$stylesheets[$stylesheet2]['applied_to'][$file][] = $action;
				if(is_array($file_stylesheets['inherited'][$file."_".$action]) && in_array($stylesheet2, array_keys($file_stylesheets['inherited'][$file."_".$action])))
				{
					$stylesheets[$stylesheet2]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];
					foreach($file_stylesheets['inherited'][$file."_".$action] as $value)
					{
						$inherited_load[] = $value;
					}
				}
			}
		}
	}
	
	foreach($stylesheets as $file => $stylesheet2)
	{		
		foreach($stylesheet2['inherited'] as $inherited_file => $tid)
		{
			$stylesheet2['inherited'][basename($inherited_file)] = $tid;
			unset($stylesheet2['inherited'][$inherited_file]);
		}
		
		$stylesheets[basename($file)] = $stylesheet2;
		unset($stylesheets[$file]);
	}
	
	$this_stylesheet = $stylesheets[$stylesheet['name']];	
	unset($stylesheets);
	
	$page->extra_header .= '
	<link type="text/css" href="./jscripts/codepress/languages/codepress-css.css" rel="stylesheet" id="cp-lang-style" />
	<script type="text/javascript" src="./jscripts/codepress/codepress.js"></script>
	<script type="text/javascript">
		CodePress.language = \'css\';
	</script>';
	
	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style/themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
	$page->add_breadcrumb_item("Editing ".htmlspecialchars_uni($stylesheet['name']), "index.php?module=style/themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;sid={$mybb->input['sid']}&amp;mode=advanced");
	
	$page->output_header("Themes - Edit Stylesheet: Advanced Mode");

	// If the stylesheet and theme do not match, we must be editing something that is inherited
	if($this_stylesheet['inherited'][$stylesheet['name']])
	{
		$query = $db->simple_select("themes", "name", "tid='{$stylesheet['tid']}'");
		$stylesheet_parent = htmlspecialchars_uni($db->fetch_field($query, 'name'));
		
		// Show inherited warning
		if($stylesheet['tid'] == 1)
		{
			$page->output_alert("This stylesheet is currently being inherited from {$stylesheet_parent}. Any changes you make will break the inheritance, and the stylesheet will be copied to this theme.");
		}
		else
		{
			$page->output_alert("This stylesheet is currently being inherited from {$stylesheet_parent}. Any changes you make will break the inheritance, and the stylesheet will be copied to this theme. Edit this stylesheet in {$stylesheet_parent} to keep the inheritance.");
		}
	}
	
	$sub_tabs['edit_stylesheet'] = array(
		'title' => "Edit Stylesheet: Simple Mode",
		'link' => "index.php?module=style/themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;sid={$mybb->input['sid']}&amp;mode=simple"
	);

	$sub_tabs['edit_stylesheet_advanced'] = array(
		'title' => "Edit Stylesheet: Advanced Mode",
		'link' => "index.php?module=style/themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;sid={$mybb->input['sid']}&amp;mode=advanced",
		'description' => "Here you can edit this stylesheet like a flat file."
	);
	
	$page->output_nav_tabs($sub_tabs, 'edit_stylesheet_advanced');

	// Has the file on the file system been modified?
	if(resync_stylesheet($stylesheet))
	{
		// Need to refetch new stylesheet as it was modified
		$query = $db->simple_select("themestylesheets", "stylesheet", "sid='{$stylesheet['sid']}'");
		$stylesheet['stylesheet'] = $db->fetch_field($query, 'stylesheet');
	}
	
	$form = new Form("index.php?module=style/themes&amp;action=edit_stylesheet&amp;mode=advanced", "post", "edit_stylesheet");
	echo $form->generate_hidden_field("tid", $mybb->input['tid'])."\n";
	echo $form->generate_hidden_field("sid", $mybb->input['sid'])."\n";
	
	$table = new Table;	
	$table->construct_cell($form->generate_text_area('stylesheet', $stylesheet['stylesheet'], array('id' => 'stylesheet', 'style' => 'width: 99%;', 'class' => 'codepress css', 'rows' => '30')));
	$table->construct_row();
	$table->output("Full Stylesheet for ".htmlspecialchars_uni($stylesheet['name']));
	
	$buttons[] = $form->generate_reset_button("Reset");
	$buttons[] = $form->generate_submit_button("Save Changes", array('id' => 'save', 'name' => 'save'));
	$buttons[] = $form->generate_submit_button("Save Changes & Close", array('id' => 'save_close', 'name' => 'save_close'));

	$form->output_submit_wrapper($buttons);

	$form->end();
	
	echo "<script language=\"Javascript\" type=\"text/javascript\">
	Event.observe('edit_stylesheet', 'submit', function()
	{
		if($('stylesheet_cp')) {
			var area = $('stylesheet_cp');
			area.id = 'stylesheet';
			area.value = stylesheet.getCode();
			area.disabled = false;
		}
	});
</script>";
	
	$page->output_footer();
}

if($mybb->input['action'] == "delete_stylesheet")
{
	$query = $db->simple_select("themestylesheets", "*", "sid='".intval($mybb->input['sid'])."'");
	$stylesheet = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$stylesheet['sid'])
	{
		flash_message("You have selected an invalid stylesheet.", 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=style/templates");
	}

	if($mybb->request_method == "post")
	{
		// Log admin action
		log_admin_action($stylesheet['tid'], $stylesheet['name'], $theme['tid'], $theme['name']);

		flash_message("Successfully deleted the selected stylesheet.", 'success');
		admin_redirect("index.php?module=style/themes");
	}
	else
	{		
		$page->output_confirm_action("index.php?module=style/themes&amp;action=force&amp;tid={$theme['tid']}", "Are you sure you want to force this theme on all users?");
	}
}

if($mybb->input['action'] == "add_stylesheet")
{
	if($mybb->request_method == "post")
	{
	}
}

if($mybb->input['action'] == "set_default")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	$db->update_query("themes", array('def' => 0));
	$db->update_query("themes", array('def' => 1), "tid='".intval($mybb->input['tid'])."'");

	flash_message("The selected theme has now been marked as the default.", 'success');
	admin_redirect("index.php?module=style/themes");
}

if($mybb->input['action'] == "force")
{
	$query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message("You have selected an invalid theme.", 'error');
		admin_redirect("index.php?module=style/themes");
	}
	
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=style/templates");
	}

	if($mybb->request_method == "post")
	{
		$updated_users = array(
			"style" => $theme['tid']
		);
		
		$db->update_query("users", $updated_users);

		// Log admin action
		log_admin_action($theme['tid'], $theme['name']);

		flash_message("The selected theme has now been forced as the default to all users.", 'success');
	admin_redirect("index.php?module=style/themes");
	}
	else
	{		
		$page->output_confirm_action("index.php?module=style/themes&amp;action=force&amp;tid={$theme['tid']}", "Are you sure you want to force this theme on all users?");
	}
}

if(!$mybb->input['action'])
{
	$page->output_header("Themes");
	
	$page->output_nav_tabs($sub_tabs, 'themes');

	$table = new Table;
	$table->construct_header("Theme");
	$table->construct_header("# Users", array("class" => "align_center", "width" => 100));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	build_theme_list();

	$table->output("Themes");
	
	$page->output_footer();
}

function build_theme_list($parent=0, $depth=0)
{
	global $mybb, $db, $table, $lang; // Global $table is bad, but it will have to do for now
	static $theme_cache;

	$padding = $depth*20; // Padding

	if(!is_array($theme_cache))
	{
		$themes = cache_themes();
		$query = $db->query("
			SELECT style, COUNT(uid) AS users
			FROM ".TABLE_PREFIX."users
			GROUP BY style
		");
		while($user_themes = $db->fetch_array($query))
		{
			$themes[$user_themes['style']]['users'] = intval($user_themes['users']);
		}

		// Restrucure the theme array to something we can "loop-de-loop" with
		foreach($themes as $theme)
		{
			$theme_cache[$theme['pid']][$theme['tid']] = $theme;
		}
		unset($theme);
	}

	if(!is_array($theme_cache[$parent]))
	{
		return;
	}

	foreach($theme_cache[$parent] as $theme)
	{
		$popup = new PopupMenu("theme_{$theme['tid']}", $lang->options);
		if($theme['tid'] > 1)
		{
			$popup->add_item("Edit Theme", "index.php?module=style/themes&amp;action=edit&amp;tid={$theme['tid']}");
			$popup->add_item("Delete Theme", "index.php?module=style/themes&amp;action=delete&amp;tid={$theme['tid']}", "return AdminCP.deleteConfirmation(this, 'Are you sure you want to delete this theme?')");
			if($theme['def'] != 1)
			{
				$popup->add_item("Set as Default", "index.php?module=style/themes&amp;action=set_as_default&amp;tid={$theme['tid']}");
				$set_default = "<a href=\"index.php?module=style/themes&amp;action=set_as_default&amp;tid={$theme['tid']}\"><img src=\"\" title=\"Set as Default\" /></a>";
			}
			else
			{
				$set_default = "<img src=\"\" title=\"Default Theme\" />";
			}
			$popup->add_item("Force on Users", "index.php?module=style/themes&amp;action=force&amp;tid={$theme['tid']}", "return AdminCP.deleteConfirmation(this, 'Are you sure you want to force this theme on all users?')");
		}
		$popup->add_item("Export Theme", "index.php?module=style/themes&amp;action=export&amp;tid={$theme['tid']}");
		$table->construct_cell("<div class=\"float_right;\">{$set_default}</div><div style=\"margin-left: {$padding}px\"><strong>{$theme['name']}</strong></div>");
		$table->construct_cell(my_number_format($theme['users']), array("class" => "align_center"));
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();

		// Fetch & build any child themes
		build_theme_list($theme['tid'], ++$depth);
	}
}

function cache_themes()
{
	global $db, $theme_cache;

	$query = $db->simple_select("themes", "*", "", array('order_by' => "pid, name"));
	while($theme = $db->fetch_array($query))
	{
		$theme['themebits'] = unserialize($theme['themebits']);
		$theme['cssbits'] = unserialize($theme['cssbits']);
		$theme_cache[$theme['tid']] = $theme;
	}
	
	return $theme_cache;
}
?>