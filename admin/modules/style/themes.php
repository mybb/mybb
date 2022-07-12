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

require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

$page->extra_header .= "
<script type=\"text/javascript\">
//<![CDATA[
var save_changes_lang_string = '{$lang->save_changes_js}';
var delete_lang_string = '{$lang->delete}';
var file_lang_string = '{$lang->file}';
var globally_lang_string = '{$lang->globally}';
var specific_actions_lang_string = '{$lang->specific_actions}';
var specific_actions_desc_lang_string = '{$lang->specific_actions_desc}';
var delete_confirm_lang_string = '{$lang->delete_confirm_js}';

lang.theme_info_fetch_error = \"{$lang->theme_info_fetch_error}\";
lang.theme_info_save_error = \"{$lang->theme_info_save_error}\";
//]]>
</script>";

// Be extra careful about dodgy codenames or stylesheet filenames which try to use directory
// separators and '..' to access private filesystem files.
if (!empty($mybb->input) && (strpos($mybb->input['codename'], '../') !== false || strpos($mybb->input['codename'], '..\\') !== false)) {
	flash_message($lang->error_codename_with_directory_separator, 'error');
	admin_redirect('index.php?module=style-themes');
}
if (!empty($mybb->input) && (strpos($mybb->input['file'], '../') !== false || strpos($mybb->input['file'], '..\\') !== false)) {
	flash_message($lang->error_css_filename_with_directory_separator, 'error');
	admin_redirect('index.php?module=style-themes');
}

if($mybb->input['action'] == "xmlhttp_stylesheet" && $mybb->request_method == "post")
{
	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'");
	$theme = $db->fetch_array($query);

	if(!$theme['tid'] || $theme['tid'] == 1)
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	$parent_list = make_parent_theme_list($theme['tid']);
	$parent_list = implode(',', $parent_list);
	if(!$parent_list)
	{
		$parent_list = 1;
	}

	$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", array('order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1));
	$stylesheet = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$stylesheet['sid'])
	{
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	$css_array = css_to_array($stylesheet['stylesheet']);
	$selector_list = get_selectors_as_options($css_array, $mybb->input['selector']);
	$editable_selector = $css_array[$mybb->input['selector']];
	$properties = parse_css_properties($editable_selector['values']);

	foreach(array('background', 'color', 'width', 'font-family', 'font-size', 'font-style', 'font-weight', 'text-decoration') as $_p)
	{
		if(!isset($properties[$_p]))
		{
			$properties[$_p] = '';
		}
	}

	$form = new Form("index.php?module=style-themes&amp;action=stylesheet_properties", "post", "selector_form", 0, "", true);
	echo $form->generate_hidden_field("tid", $mybb->input['tid'], array('id' => "tid"))."\n";
	echo $form->generate_hidden_field("file", htmlspecialchars_uni($mybb->input['file']), array('id' => "file"))."\n";
	echo $form->generate_hidden_field("selector", htmlspecialchars_uni($mybb->input['selector']), array('id' => 'hidden_selector'))."\n";

	$table = new Table;
	if($lang->settings['rtl'] === true)
	{
		$div_align = "left";
	}
	else
	{
		$div_align = "right";
	}

	$table->construct_cell("<div style=\"float: {$div_align};\">".$form->generate_text_box('css_bits[background]', $properties['background'], array('id' => 'css_bits[background]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->background}</strong></div>", array('style' => 'width: 20%;'));
	$table->construct_cell("<strong>{$lang->extra_css_atribs}</strong><br /><div style=\"align: center;\">".$form->generate_text_area('css_bits[extra]', $properties['extra'], array('id' => 'css_bits[extra]', 'style' => 'width: 98%;', 'rows' => '19'))."</div>", array('rowspan' => 8));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: {$div_align};\">".$form->generate_text_box('css_bits[color]', $properties['color'], array('id' => 'css_bits[color]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->color}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: {$div_align};\">".$form->generate_text_box('css_bits[width]', $properties['width'], array('id' => 'css_bits[width]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->width}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: {$div_align};\">".$form->generate_text_box('css_bits[font_family]', $properties['font-family'], array('id' => 'css_bits[font_family]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_family}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: {$div_align};\">".$form->generate_text_box('css_bits[font_size]', $properties['font-size'], array('id' => 'css_bits[font_size]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_size}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: {$div_align};\">".$form->generate_text_box('css_bits[font_style]', $properties['font-style'], array('id' => 'css_bits[font_style]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_style}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: {$div_align};\">".$form->generate_text_box('css_bits[font_weight]', $properties['font-weight'], array('id' => 'css_bits[font_weight]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_weight}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: {$div_align};\">".$form->generate_text_box('css_bits[text_decoration]', $properties['text-decoration'], array('id' => 'css_bits[text_decoration]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->text_decoration}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();

	$table->output(htmlspecialchars_uni($editable_selector['class_name'])."<span id=\"saved\" style=\"color: #FEE0C6;\"></span>");
	exit;
}

$page->add_breadcrumb_item($lang->themes, "index.php?module=style-themes");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "import" || $mybb->input['action'] == "browse" || !$mybb->input['action'])
{
	$sub_tabs['themes'] = array(
		'title' => $lang->themes,
		'link' => "index.php?module=style-themes",
		'description' => $lang->themes_desc
	);

	$sub_tabs['create_theme'] = array(
		'title' => $lang->create_new_theme,
		'link' => "index.php?module=style-themes&amp;action=add",
		'description' => $lang->create_new_theme_desc
	);

	$sub_tabs['import_theme'] = array(
		'title' => $lang->import_a_theme,
		'link' => "index.php?module=style-themes&amp;action=import",
		'description' => $lang->import_a_theme_desc
	);

	$sub_tabs['browse_themes'] = array(
		'title' => $lang->browse_themes,
		'link' => "index.php?module=style-themes&amp;action=browse",
		'description' => $lang->browse_themes_desc
	);
}

$plugins->run_hooks("admin_style_themes_begin");

if($mybb->input['action'] == "browse")
{
	$plugins->run_hooks("admin_style_themes_browse");

	$page->add_breadcrumb_item($lang->browse_themes);

	$page->output_header($lang->browse_themes);

	$page->output_nav_tabs($sub_tabs, 'browse_themes');

	// Process search requests
	$keywords = "";
	if(!empty($mybb->input['keywords']))
	{
		$keywords = "&keywords=".urlencode($mybb->input['keywords']);
	}

	if(!empty($mybb->input['page']))
	{
		$url_page = "&page=".$mybb->get_input('page', MyBB::INPUT_INT);
	}
	else
	{
		$mybb->input['page'] = 1;
		$url_page = "";
	}

	// Gets the major version code. i.e. 1410 -> 1400 or 121 -> 1200
	$major_version_code = round($mybb->version_code/100, 0)*100;
	// Convert to mods site version codes
	$search_version = ($major_version_code/100).'x';

	$contents = fetch_remote_file("https://community.mybb.com/xmlbrowse.php?api=2&type=themes&version={$search_version}{$keywords}{$url_page}");

	if(!$contents)
	{
		$page->output_inline_error($lang->error_communication_problem);
		$page->output_footer();
		exit;
	}

	$table = new Table;
	$table->construct_header($lang->themes, array('colspan' => 2));
	$table->construct_header($lang->controls, array("class" => "align_center", 'width' => 125));

	$parser = create_xml_parser($contents);
	$tree = $parser->get_tree();

	if(!is_array($tree) || !isset($tree['results']))
	{
		$page->output_inline_error($lang->error_communication_problem);
		$page->output_footer();
		exit;
	}

	if(!empty($tree['results']['result']))
	{
		if(array_key_exists("tag", $tree['results']['result']))
		{
			$only_theme = $tree['results']['result'];
			unset($tree['results']['result']);
			$tree['results']['result'][0] = $only_theme;
		}

		require_once MYBB_ROOT . '/inc/class_parser.php';
		$post_parser = new postParser();

		foreach($tree['results']['result'] as $result)
		{
			$result['thumbnail']['value'] = htmlspecialchars_uni($result['thumbnail']['value']);
			$result['name']['value'] = htmlspecialchars_uni($result['name']['value']);
			$result['description']['value'] = htmlspecialchars_uni($result['description']['value']);
			$result['author']['url']['value'] = htmlspecialchars_uni($result['author']['url']['value']);
			$result['author']['name']['value'] = htmlspecialchars_uni($result['author']['name']['value']);
			$result['download_url']['value'] = htmlspecialchars_uni(html_entity_decode($result['download_url']['value']));

			$table->construct_cell("<img src=\"https://community.mybb.com/{$result['thumbnail']['value']}\" alt=\"{$lang->theme_thumbnail}\" title=\"{$lang->theme_thumbnail}\"/>", array("class" => "align_center", "width" => 100));
			$table->construct_cell("<strong>{$result['name']['value']}</strong><br /><small>{$result['description']['value']}</small><br /><i><small>{$lang->created_by} <a href=\"{$result['author']['url']['value']}\" target=\"_blank\" rel=\"noopener\">{$result['author']['name']['value']}</a></small></i>");
			$table->construct_cell("<strong><a href=\"https://community.mybb.com/{$result['download_url']['value']}\" target=\"_blank\" rel=\"noopener\">{$lang->download}</a></strong>", array("class" => "align_center"));
			$table->construct_row();
		}
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->error_no_results_found, array("colspan" => 3));
		$table->construct_row();
	}

	$search = new Form("index.php?module=style-themes&amp;action=browse", 'post', 'search_form');
	echo "<div style=\"padding-bottom: 3px; margin-top: -9px; text-align: right;\">";
	if(!empty($mybb->input['keywords']))
	{
		$default_class = '';
		$value = htmlspecialchars_uni($mybb->input['keywords']);
	}
	else
	{
		$default_class = "search_default";
		$value = $lang->search_for_themes;
	}
	echo $search->generate_text_box('keywords', $value, array('id' => 'search_keywords', 'class' => "{$default_class} field150 field_small"))."\n";
	echo "<input type=\"submit\" class=\"search_button\" value=\"{$lang->search}\" />\n";
	echo "<script type=\"text/javascript\">
		var form = $(\"#search_form\");
		form.on('submit', function()
		{
			var search = $('#search_keywords');
			if(search.val() == '' || search.val() == '{$lang->search_for_themes}')
			{
				search.trigger('focus');
				return false;
			}
		});

		var search = $('#search_keywords');
		search.on('focus', function()
		{
			var search_focus = $(this);
			if(search_focus.val() == '{$lang->search_for_themes}')
			{
				search_focus.removeClass('search_default');
				search_focus.val('');
			}
		}).on('blur', function()
		{
			var search_blur = $(this);
			if(search_blur.val() == '')
			{
				search_blur.addClass('search_default');
				search_blur.val('{$lang->search_for_themes}');
			}
		});

		// fix the styling used if we have a different default value
		if(search.val() != '{$lang->search_for_themes}')
		{
			search.removeClass('search_default');
		}
		</script>\n";
	echo "</div>\n";
	echo $search->end();

	// Recommended themes = Default; Otherwise search results & pagination
	if($mybb->request_method == "post")
	{
		$table->output("<span style=\"float: right;\"><small><a href=\"https://community.mybb.com/mods.php?action=browse&category=themes\" target=\"_blank\" rel=\"noopener\">{$lang->browse_all_themes}</a></small></span>".$lang->sprintf($lang->browse_results_for_mybb, $mybb->version));
	}
	else
	{
		$table->output("<span style=\"float: right;\"><small><a href=\"https://community.mybb.com/mods.php?action=browse&category=themes\" target=\"_blank\" rel=\"noopener\">{$lang->browse_all_themes}</a></small></span>".$lang->sprintf($lang->recommended_themes_for_mybb, $mybb->version));
	}

	if(!empty($tree['results']['attributes']['total']))
	{
		echo "<br />".draw_admin_pagination($mybb->input['page'], 15, $tree['results']['attributes']['total'], "index.php?module=style-themes&amp;action=browse{$keywords}&amp;page={page}");
	}

	$page->output_footer();
}

if($mybb->input['action'] == "import")
{
	$plugins->run_hooks("admin_style_themes_import");

	if($mybb->request_method == "post")
	{
		if(!$_FILES['local_file'] && !$mybb->input['url'])
		{
			$errors[] = $lang->error_missing_url;
		}

		if(!$errors)
		{
			// Find out if there was an uploaded file
			if($_FILES['local_file']['error'] != 4)
			{
				// Find out if there was an error with the uploaded file
				if($_FILES['local_file']['error'] != 0)
				{
					$errors[] = $lang->error_uploadfailed.$lang->error_uploadfailed_detail;
					switch($_FILES['local_file']['error'])
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
						case 6: // UPLOAD_ERR_NO_TMP_DIR
							$errors[] = $lang->error_uploadfailed_php6;
							break;
						case 7: // UPLOAD_ERR_CANT_WRITE
							$errors[] = $lang->error_uploadfailed_php7;
							break;
						default:
							$errors[] = $lang->sprintf($lang->error_uploadfailed_phpx, $_FILES['local_file']['error']);
							break;
					}
				}

				if(!$errors)
				{
					// Was the temporary file found?
					if(!is_uploaded_file($_FILES['local_file']['tmp_name']))
					{
						$errors[] = $lang->error_uploadfailed_lost;
					}
					// Get the contents
					$contents = @file_get_contents($_FILES['local_file']['tmp_name']);
					// Delete the temporary file if possible
					@unlink($_FILES['local_file']['tmp_name']);
					// Are there contents?
					if(!trim($contents))
					{
						$errors[] = $lang->error_uploadfailed_nocontents;
					}
				}
			}
			else if(!empty($mybb->input['url']))
			{
				// Get the contents
				$contents = @fetch_remote_file($mybb->input['url']);
				if(!$contents)
				{
					$errors[] = $lang->error_local_file;
				}
			}
			else
			{
				// UPLOAD_ERR_NO_FILE
				$errors[] = $lang->error_uploadfailed_php4;
			}

			if(!$errors)
			{
				$options = array(
					'no_stylesheets' => ($mybb->input['import_stylesheets'] ? 0 : 1),
					'no_templates' => ($mybb->input['import_templates'] ? 0 : 1),
					'version_compat' => $mybb->get_input('version_compat', MyBB::INPUT_INT),
					'parent' => $mybb->get_input('tid', MyBB::INPUT_INT),
					'force_name_check' => true,
				);
				$theme_id = import_theme_xml($contents, $options);

				if($theme_id > -1)
				{
					$plugins->run_hooks("admin_style_themes_import_commit");

					// Log admin action
					log_admin_action($theme_id);

					flash_message($lang->success_imported_theme, 'success');
					admin_redirect("index.php?module=style-themes&action=edit&tid=".$theme_id);
				}
				else
				{
					switch($theme_id)
					{
						case -1:
							$errors[] = $lang->error_uploadfailed_nocontents;
							break;
						case -2:
							$errors[] = $lang->error_invalid_version;
							break;
						case -3:
							$errors[] = $lang->error_theme_already_exists;
							break;
						case -4:
							$errors[] = $lang->error_theme_security_problem;
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

	$page->add_breadcrumb_item($lang->import_a_theme, "index.php?module=style-themes&amp;action=import");

	$page->output_header("{$lang->themes} - {$lang->import_a_theme}");

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

	$form = new Form("index.php?module=style-themes&amp;action=import", "post", "", 1);

	$actions = '<script type="text/javascript">
	function checkAction(id)
	{
		var checked = \'\';

		$(\'.\'+id+\'s_check\').each(function(e, val)
		{
			if($(this).prop(\'checked\') == true)
			{
				checked = $(this).val();
			}
		});
		$(\'.\'+id+\'s\').each(function(e)
		{
			$(this).hide();
		});
		if($(\'#\'+id+\'_\'+checked))
		{
			$(\'#\'+id+\'_\'+checked).show();
		}
	}
</script>
	<dl style="margin-top: 0; margin-bottom: 0; width: 35%;">
	<dt><label style="display: block;"><input type="radio" name="import" value="0" '.$import_checked[1].' class="imports_check" onclick="checkAction(\'import\');" style="vertical-align: middle;" /> '.$lang->local_file.'</label></dt>
		<dd style="margin-top: 0; margin-bottom: 0; width: 100%;" id="import_0" class="imports">
	<table cellpadding="4">
				<tr>
					<td>'.$form->generate_file_upload_box("local_file", array('style' => 'width: 230px;')).'</td>
				</tr>
		</table>
		</dd>
		<dt><label style="display: block;"><input type="radio" name="import" value="1" '.$import_checked[2].' class="imports_check" onclick="checkAction(\'import\');" style="vertical-align: middle;" /> '.$lang->url.'</label></dt>
		<dd style="margin-top: 0; margin-bottom: 0; width: 100%;" id="import_1" class="imports">
		<table cellpadding="4">
				<tr>
					<td>'.$form->generate_text_box("url", $mybb->get_input('file')).'</td>
				</tr>
		</table></dd>
	</dl>
	<script type="text/javascript">
	checkAction(\'import\');
	</script>';

	$form_container = new FormContainer($lang->import_a_theme);
	$form_container->output_row($lang->import_from, $lang->import_from_desc, $actions, 'file');
	$form_container->output_row($lang->parent_theme, $lang->parent_theme_desc, $form->generate_select_box('tid', $themes, $mybb->get_input('tid'), array('id' => 'tid')), 'tid');
	$form_container->output_row($lang->new_name, $lang->new_name_desc, $form->generate_text_box('name', $mybb->get_input('name'), array('id' => 'name')), 'name');
	$form_container->output_row($lang->advanced_options, "", $form->generate_check_box('version_compat', '1', $lang->ignore_version_compatibility, array('checked' => $mybb->get_input('version_compat'), 'id' => 'version_compat'))."<br /><small>{$lang->ignore_version_compat_desc}</small><br />".$form->generate_check_box('import_stylesheets', '1', $lang->import_stylesheets, array('checked' => $mybb->get_input('import_stylesheets'), 'id' => 'import_stylesheets'))."<br /><small>{$lang->import_stylesheets_desc}</small><br />".$form->generate_check_box('import_templates', '1', $lang->import_templates, array('checked' => $mybb->get_input('import_templates'), 'id' => 'import_templates'))."<br /><small>{$lang->import_templates_desc}</small>");

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->import_theme);

	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "export")
{
	require_once MYBB_ROOT.'inc/functions_themes.php';
	$themelet_hierarchy = get_themelet_hierarchy();
	$mode = $mybb->settings['themelet_dev_mode'] ? 'devdist' : 'current';
	$themes = $themelet_hierarchy[$mode]['themes'];

	// Does the theme not exist?
	if (!isset($themes[$mybb->input['codename']])) {
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	$theme = $themes[$mybb->input['codename']]['properties'];

	$errors = [];

	$plugins->run_hooks("admin_style_themes_export");

	if($mybb->request_method == "post")
	{
		if (!class_exists('ZipArchive')) {
			flash_message($lang->error_no_ziparchive_for_theme, 'error');
			admin_redirect('index.php?module=style-themes');
		}

		if (!empty($themelet_hierarchy['devdist']['themes'][$mybb->input['codename']])
		    &&
		    !empty($themelet_hierarchy['current']['themes'][$mybb->input['codename']])
		) {
			$mode = $mybb->input['export_devdist'] ? 'devdist' : 'current';
			$theme = $themelet_hierarchy[$mode]['themes'][$mybb->input['codename']]['properties'];
		}

		$tmp_root = create_temp_dir();
		if (!$tmp_root) {
			$errors[] = $lang->error_failed_to_create_tmpdir;
		}

		$dest = $tmp_root.'/'.$theme['codename'];

		if (empty($mybb->input['custom_theme'])) {
			foreach (array_reverse($themes[$theme['codename']]['ancestors']) as $anc_code) {
				$source = MYBB_ROOT."inc/themes/{$anc_code}/{$mode}/";
				if (!cp_or_mv_recursively($source, $dest, false, $err_msg)) {
					flash_message($err_msg, 'error');
					admin_redirect('index.php?module=style-themes');
				}
			}
		}

		$source = MYBB_ROOT."inc/themes/{$theme['codename']}/{$mode}/";

		if (!cp_or_mv_recursively($source, $dest, false, $err_msg)) {
			flash_message($err_msg, 'error');
			admin_redirect('index.php?module=style-themes');
		}

		$zip_filepath = $tmp_root.'/'.$theme['codename'].'.zip';

		if (!zip_directory($dest, $zip_filepath, $err_msg)) {
			rmdir_recursive($tmp_root);
			flash_message($err_msg, 'error');
			admin_redirect('index.php?module=style-themes');
		}

		$plugins->run_hooks('admin_style_themes_export_commit');

		// Log admin action
		log_admin_action($theme['tid'], $theme['name']);

		$data = file_get_contents($zip_filepath);
		header("Content-disposition: attachment; filename={$theme['codename']}.zip");
		header("Content-type: application/zip");
		header("Content-Length: ".strlen($data));
		header("Pragma: no-cache");
		header("Expires: 0");
		echo $data;
		exit;
	}

	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style-themes&amp;action=edit&amp;tid={$mybb->input['tid']}");

	$page->add_breadcrumb_item($lang->export_theme, "index.php?module=style-themes&amp;action=export");

	$page->output_header("{$lang->themes} - {$lang->export_theme}");

	$sub_tabs['edit_stylesheets'] = array(
		'title' => $lang->edit_stylesheets,
		'link' => "index.php?module=style-themes&amp;action=edit&amp;tid={$mybb->input['tid']}",
	);

	$sub_tabs['add_stylesheet'] = array(
		'title' => $lang->add_stylesheet,
		'link' => "index.php?module=style-themes&amp;action=add_stylesheet&amp;tid={$mybb->input['tid']}",
	);

	$sub_tabs['export_theme'] = array(
		'title' => $lang->export_theme,
		'link' => "index.php?module=style-themes&amp;action=export&amp;tid={$mybb->input['tid']}",
		'description' => $lang->export_theme_desc
	);

	$sub_tabs['duplicate_theme'] = array(
		'title' => $lang->duplicate_theme,
		'link' => "index.php?module=style-themes&amp;action=duplicate&amp;tid={$mybb->input['tid']}",
		'description' => $lang->duplicate_theme_desc
	);

	$page->output_nav_tabs($sub_tabs, 'export_theme');

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=style-themes&amp;action=export", "post");
	echo $form->generate_hidden_field('codename', $theme['codename']);

	$form_container = new FormContainer($lang->export_theme.": ".htmlspecialchars_uni($theme['name']));
	$form_container->output_row($lang->include_custom_only, $lang->include_custom_only_desc, $form->generate_yes_no_radio('custom_theme', $mybb->get_input('custom_theme')), 'custom_theme');

	if (!empty($themelet_hierarchy['devdist']['themes'][$mybb->input['codename']])
	    &&
	    !empty($themelet_hierarchy['current']['themes'][$mybb->input['codename']])
	) {
		$form_container->output_row($lang->export_devdist, $lang->export_devdist_desc, $form->generate_yes_no_radio('export_devdist', $mybb->settings['themelet_dev_mode'] ? '1' : '0', 'export_devdist'));
	}

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->export_theme);

	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "duplicate")
{
	$query = $db->simple_select("themes", "*", "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$theme['tid'])
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	$plugins->run_hooks("admin_style_themes_duplicate");

	if($mybb->request_method == "post")
	{
		if($mybb->input['name'] == "")
		{
			$errors[] = $lang->error_missing_name;
		}
		else
		{
			$query = $db->simple_select("themes", "COUNT(tid) as numthemes", "name = '".$db->escape_string($mybb->get_input('name'))."'");
			$numthemes = $db->fetch_field($query, 'numthemes');

			if($numthemes)
			{
				$errors[] = $lang->error_theme_already_exists;
			}
		}

		if(!$errors)
		{
			$properties = my_unserialize($theme['properties']);
			$sid = (int)$properties['templateset'];
			$nprops = null;
			if($mybb->get_input('duplicate_templates'))
			{
				$nsid = $db->insert_query("templatesets", array('title' => $db->escape_string($mybb->get_input('name'))." Templates"));

				// Copy all old Templates to our new templateset
				$query = $db->simple_select("templates", "*", "sid='{$sid}'");
				while($template = $db->fetch_array($query))
				{
					$insert = array(
						"title" => $db->escape_string($template['title']),
						"template" => $db->escape_string($template['template']),
						"sid" => $nsid,
						"version" => $db->escape_string($template['version']),
						"dateline" => TIME_NOW
					);

					if($db->engine == "pgsql")
					{
						echo " ";
						flush();
					}

					$db->insert_query("templates", $insert);
				}

				// We need to change the templateset so we need to work out the others properties too
				foreach($properties as $property => $value)
				{
					if($property == "inherited")
					{
						continue;
					}

					$nprops[$property] = $value;
					if(!empty($properties['inherited'][$property]))
					{
						$nprops['inherited'][$property] = $properties['inherited'][$property];
					}
					else
					{
						$nprops['inherited'][$property] = $theme['tid'];
					}
				}
				$nprops['templateset'] = $nsid;
			}
			$tid = build_new_theme($mybb->get_input('name'), $nprops, $theme['tid']);

			update_theme_stylesheet_list($tid);

			$plugins->run_hooks("admin_style_themes_duplicate_commit");

			// Log admin action
			log_admin_action($tid, $theme['tid']);

			flash_message($lang->success_duplicated_theme, 'success');
			admin_redirect("index.php?module=style-themes&action=edit&tid=".$tid);
		}
	}

	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style-themes&amp;action=edit&amp;tid={$mybb->get_input('tid')}");

	$page->add_breadcrumb_item($lang->duplicate_theme, "index.php?module=style-themes&amp;action=duplicate&amp;tid={$theme['tid']}");

	$page->output_header("{$lang->themes} - {$lang->duplicate_theme}");

	$sub_tabs['edit_stylesheets'] = array(
		'title' => $lang->edit_stylesheets,
		'link' => "index.php?module=style-themes&amp;action=edit&amp;tid={$mybb->get_input('tid')}",
	);

	$sub_tabs['add_stylesheet'] = array(
		'title' => $lang->add_stylesheet,
		'link' => "index.php?module=style-themes&amp;action=add_stylesheet&amp;tid={$mybb->get_input('tid')}",
	);

	$sub_tabs['export_theme'] = array(
		'title' => $lang->export_theme,
		'link' => "index.php?module=style-themes&amp;action=export&amp;tid={$mybb->get_input('tid')}",
		'description' => $lang->export_theme_desc
	);

	$sub_tabs['duplicate_theme'] = array(
		'title' => $lang->duplicate_theme,
		'link' => "index.php?module=style-themes&amp;action=duplicate&amp;tid={$mybb->get_input('tid')}",
		'description' => $lang->duplicate_theme_desc
	);

	$page->output_nav_tabs($sub_tabs, 'duplicate_theme');

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['duplicate_templates'] = true;
	}

	$form = new Form("index.php?module=style-themes&amp;action=duplicate&amp;tid={$theme['tid']}", "post");

	$form_container = new FormContainer($lang->duplicate_theme);
	$form_container->output_row($lang->new_name, $lang->new_name_duplicate_desc, $form->generate_text_box('name', $mybb->get_input('name'), array('id' => 'name')), 'name');
	$form_container->output_row($lang->advanced_options, "", $form->generate_check_box('duplicate_templates', '1', $lang->duplicate_templates, array('checked' => $mybb->get_input('duplicate_templates'), 'id' => 'duplicate_templates'))."<br /><small>{$lang->duplicate_templates_desc}</small>");

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->duplicate_theme);

	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_style_themes_add");

	$query = $db->simple_select("themes", "tid, name");
	while($theme = $db->fetch_array($query))
	{
		$themes[$theme['tid']] = $theme['name'];
	}

	if($mybb->request_method == "post")
	{
		if(!$mybb->input['name'])
		{
			$errors[] = $lang->error_missing_name;
		}
		else if(in_array($mybb->input['name'], $themes))
		{
			$errors[] = $lang->error_theme_already_exists;
		}

		if(!$errors)
		{
			$tid = build_new_theme($mybb->input['name'], null, $mybb->input['tid']);

			$plugins->run_hooks("admin_style_themes_add_commit");

			// Log admin action
			log_admin_action($mybb->input['name'], $tid);

			flash_message($lang->success_theme_created, 'success');
			admin_redirect("index.php?module=style-themes&action=edit&tid=".$tid);
		}
	}

	$page->add_breadcrumb_item($lang->create_new_theme, "index.php?module=style-themes&amp;action=add");

	$page->output_header("{$lang->themes} - {$lang->create_new_theme}");

	$page->output_nav_tabs($sub_tabs, 'create_theme');

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=style-themes&amp;action=add", "post");

	$form_container = new FormContainer($lang->create_a_theme);
	$form_container->output_row($lang->name, $lang->name_desc, $form->generate_text_box('name', $mybb->get_input('name'), array('id' => 'name')), 'name');
	$form_container->output_row($lang->parent_theme, $lang->parent_theme_desc, $form->generate_select_box('tid', $themes, $mybb->get_input('tid'), array('id' => 'tid')), 'tid');

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->create_new_theme);

	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("themes", "*", "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'");
	$theme = $db->fetch_array($query);

	// Does the theme not exist? or are we trying to delete the master?
	if(!$theme['tid'] || $theme['tid'] == 1)
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	// User clicked no
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=style-themes");
	}

	$plugins->run_hooks("admin_style_themes_delete");

	if($mybb->request_method == "post")
	{
		$inherited_theme_cache = array();

		$query = $db->simple_select("themes", "tid,stylesheets", "tid != '{$theme['tid']}'", array('order_by' => "pid, name"));
		while($theme2 = $db->fetch_array($query))
		{
			$theme2['stylesheets'] = my_unserialize($theme2['stylesheets']);

			if(empty($theme2['stylesheets']['inherited']))
			{
				continue;
			}

			$inherited_theme_cache[$theme2['tid']] = $theme2['stylesheets']['inherited'];
		}

		$inherited_stylesheets = false;

		// Are any other themes relying on stylesheets from this theme? Get a list and show an error
		foreach($inherited_theme_cache as $tid => $inherited)
		{
			foreach($inherited as $file => $value)
			{
				foreach($value as $filepath => $val)
				{
					if(strpos($filepath, "cache/themes/theme{$theme['tid']}") !== false)
					{
						$inherited_stylesheets = true;
					}
				}
			}
		}

		if($inherited_stylesheets == true)
		{
			flash_message($lang->error_inheriting_stylesheets, 'error');
			admin_redirect("index.php?module=style-themes");
		}

		$query = $db->simple_select("themestylesheets", "cachefile", "tid='{$theme['tid']}'");
		while($cachefile = $db->fetch_array($query))
		{
			@unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/{$cachefile['cachefile']}");

			$filename_min = str_replace('.css', '.min.css', $cachefile['cachefile']);
			@unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/{$filename_min}");
		}

		$path = MYBB_ROOT."cache/themes/theme{$theme['tid']}/index.html";
		if(file_exists($path))
		{
			@unlink($path);
		}

		$db->delete_query("themestylesheets", "tid='{$theme['tid']}'");

		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid'], $theme, true);

		$db->update_query("users", array('style' => 0), "style='{$theme['tid']}'");

		$path = MYBB_ROOT."cache/themes/theme{$theme['tid']}/";
		if(file_exists($path))
		{
			@rmdir($path);
		}

		$children = (array)make_child_theme_list($theme['tid']);
		$child_tids = array();

		foreach($children as $child_tid)
		{
			if($child_tid != 0)
			{
				$child_tids[] = $child_tid;
			}
		}

		if(!empty($child_tids))
		{
			$db->update_query("themes", array('pid' => $theme['pid']), "tid IN (".implode(',', $child_tids).")");
		}

		$db->delete_query("themes", "tid='{$theme['tid']}'", 1);

		$plugins->run_hooks("admin_style_themes_delete_commit");

		// Log admin action
		log_admin_action($theme['tid'], $theme['name']);

		flash_message($lang->success_theme_deleted, 'success');
		admin_redirect("index.php?module=style-themes");
	}
	else
	{
		$page->output_confirm_action("index.php?module=style-themes&amp;action=delete&amp;tid={$theme['tid']}", $lang->confirm_theme_deletion);
	}
}

if ($mybb->input['action'] == 'edit') {
	require_once MYBB_ROOT.'inc/functions_themes.php';
	$themelet_hierarchy = get_themelet_hierarchy();
	$mode = $mybb->settings['themelet_dev_mode'] ? 'devdist' : 'current';
	$themes = $themelet_hierarchy[$mode]['themes'];

	// Does the theme not exist?
	if(empty($themes[$mybb->input['codename']]))
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	$theme = $themes[$mybb->input['codename']]['properties'];

	$plugins->run_hooks("admin_style_themes_edit");

	if ($mybb->request_method == 'post' && !$mybb->input['do']) {
		$new_properties = array(
			'name' => $mybb->input['name'],
			'description' => $mybb->input['description'],
			'parent' => $mybb->input['parent'],
			'editortheme' => $mybb->get_input('editortheme'),
			'logo' => $mybb->get_input('logo'),
			'tablespace' => $mybb->get_input('tablespace', MyBB::INPUT_INT),
			'borderwidth' => $mybb->get_input('borderwidth', MyBB::INPUT_INT),
			'color' => $mybb->get_input('color')
		);

		if($new_properties['color'] == 'none')
		{
			unset($new_properties['color']);
		}

		if($mybb->input['colors'])
		{
			$colors = explode("\n", $mybb->input['colors']);

			foreach($colors as $color)
			{
				$color = trim($color);
				if(preg_match('(^((\p{L}|\p{Nd}|_)+)={1}((\p{L}|\p{Nd}|_)+)$)u', $color))
				{
					$color = explode("=", $color);
					$new_properties['colors'][$color[0]] = $color[1];
				}
				else
				{
					$errors[] = $lang->sprintf($lang->error_invalid_color, $color);
				}
			}
		}

		$allowedgroups = array();
		if(is_array($mybb->input['allowedgroups']))
		{
			foreach($mybb->input['allowedgroups'] as $gid)
			{
				if($gid == "all")
				{
					$allowedgroups = "all";
					break;
				}
				$gid = (int)$gid;
				$allowedgroups[$gid] = $gid;
			}
		}
		if(is_array($allowedgroups))
		{
			$allowedgroups = implode(",", $allowedgroups);
		}

		$new_properties['allowedgroups'] = $allowedgroups;
		$new_properties = array_merge($theme, $new_properties);

		// Perform validation
		if(!$new_properties['name'])
		{
			$errors[] = $lang->error_missing_name;
		}
		else
		{
			$unused_name = true;
			foreach ($themelet_hierarchy['themes'] as $t_code => $a) {
				if ($a['properties']['name'] == $new_properties['name'] && $mybb->input['codename'] != $t_code) {
					$unused_name = false;
					break;
				}
			}
			if (!$unused_name) {
				$errors[] = $lang->error_theme_already_exists;
			}
		}

		if(!$new_properties['editortheme'] || !file_exists(MYBB_ROOT."jscripts/sceditor/themes/".$new_properties['editortheme']) || is_dir(MYBB_ROOT."jscripts/sceditor/themes/".$new_properties['editortheme']))
		{
			$errors[] = $lang->error_invalid_editortheme;
		}

		if(empty($errors))
		{
			$plugins->run_hooks("admin_style_fs_themes_edit_commit");

			if (!write_json_file(
				MYBB_ROOT."inc/themes/{$mybb->input['codename']}/{$mode}/theme.json",
				$new_properties
			)) {
				$errors[] = $lang->error_failed_to_save_theme;
				$theme = $new_properties;
			} else {
				if (!$cache->read('default_theme')) {
					$cache->update_default_theme();
				}
				$def_theme = $cache->read('default_theme');

				if ($mybb->input['codename'] == $def_theme['codename'])
				{
					$cache->update_default_theme($new_properties);
				}

				// Log admin action
				log_admin_action($theme['codename'], $theme['name']);

				flash_message($lang->success_theme_properties_updated, 'success');
				admin_redirect("index.php?module=style-themes&action=edit&codename={$theme['codename']}");
			}
		} else {
			$theme = $new_properties;
		}
	}

	list($stylesheets_a, $disporders) = get_theme_stylesheets($mybb->input['codename'], $mybb->settings['themelet_dev_mode']);

	// Save any stylesheet orders
	if ($mybb->request_method == 'post' && $mybb->input['do'] == 'save_orders') {
		$disporders = [];
		$prefix1 = 'disporder_';
		$plen1   = strlen($prefix1);
		$prefix2 = 'ext.';
		$plen2   = strlen($prefix2);
		foreach ($mybb->input as $key => $val) {
			$key = str_replace('~', '.', $key);
			if (substr($key, 0, $plen1) === $prefix1) {
				if (substr($key, $plen1, $plen2) === $prefix2) {
					$pl_ss = explode('.', substr($key, $plen1+$plen2), 2);
					$ss_name = array_pop($pl_ss);
					$plugin_code = array_pop($pl_ss);
					if (empty($plugin_code)) {
						$plugin_code = '';
					}
				} else {
					$plugin_code = '';
					$ss_name = substr($key, $plen1);
				}
				while (isset($disporders[$val])) {
					$val++;
				}
				$disporders[$val] = [$plugin_code, $ss_name];
			}
		}
		ksort($disporders);
		$mode = $mybb->settings['themelet_dev_mode'] ? 'devdist' : 'current';
		$resource_file = MYBB_ROOT."inc/themes/{$mybb->input['codename']}/{$mode}/resources.json";
		$resources = read_json_file($resource_file, $err_msg, false);
		if ($resources) {
			$new_ss_list = [];
			foreach ($disporders as $order_num => $pl_ss) {
				$key = $pl_ss[0] == '' ? $pl_ss[1] : "ext.{$pl_ss[0]}.{$pl_ss[1]}";
				if ($resources['stylesheets'][$key]) {
					$new_ss_list[$key] = $resources['stylesheets'][$key];
					unset($resources['stylesheets'][$key]);
				} else	$new_ss_list[$key] = [];
			}
			$new_ss_list = array_merge($new_ss_list, $resources['stylesheets']);
			$resources['stylesheets'] = $new_ss_list;
			if (!write_json_file($resource_file, $resources)) {
				flash_message($lang->error_stylesheet_order_update, 'error');
				admin_redirect("index.php?module=style-themes&action=edit&codename={$theme['codename']}");
			} else {
				flash_message($lang->success_stylesheet_order_updated, 'success');
				admin_redirect("index.php?module=style-themes&action=edit&codename={$theme['codename']}");
			}
		} else {
			flash_message($err_msg, 'error');
			admin_redirect("index.php?module=style-themes&action=edit&codename={$theme['codename']}");
		}
	}

	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style-themes&amp;action=edit&amp;codename={$mybb->input['codename']}");

	$page->output_header("{$lang->themes} - {$lang->stylesheets}");

	$sub_tabs['edit_stylesheets'] = array(
		'title' => $lang->edit_stylesheets,
		'link' => "index.php?module=style-themes&amp;action=edit&amp;codename={$mybb->input['codename']}",
		'description' => $lang->edit_stylesheets_desc
	);

	$sub_tabs['add_stylesheet'] = array(
		'title' => $lang->add_stylesheet,
		'link' => "index.php?module=style-themes&amp;action=add_stylesheet&amp;codename={$mybb->input['codename']}",
	);

	$sub_tabs['export_theme'] = array(
		'title' => $lang->export_theme,
		'link' => "index.php?module=style-themes&amp;action=export&amp;codename={$mybb->input['codename']}"
	);

	$sub_tabs['duplicate_theme'] = array(
		'title' => $lang->duplicate_theme,
		'link' => "index.php?module=style-themes&amp;action=duplicate&amp;codename={$mybb->input['codename']}",
		'description' => $lang->duplicate_theme_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_stylesheets');

	$table = new Table;
	$table->construct_header($lang->stylesheets);
	$table->construct_header($lang->display_order, array("class" => "align_center", "width" => 50));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	// Display Order form
	$form = new Form("index.php?module=style-themes&amp;action=edit", "post", "edit");
	echo $form->generate_hidden_field("codename", $theme['codename']);
	echo $form->generate_hidden_field("do", 'save_orders');

	$ordered_stylesheets = [];
	ksort($disporders);
	foreach ($disporders as $order_num => $arr) {
		if (empty($arr[0])) {
			$key = $arr[1];
		} else {
			$key = "ext.{$arr[0]}.{$arr[1]}";
		}
		$ordered_stylesheets[$key] = $stylesheets_a[$arr[0]][$arr[1]];
	}

	$order_num = 1;
	foreach ($ordered_stylesheets as $ss_name => $ss_arr) {
		$prefix = 'ext.';
		$plen   = strlen($prefix);
		if (substr($ss_name, 0, $plen) === $prefix) {
			list($ext, $plugin_code, $filename) = explode('.', $ss_name, 3);
		} else {
			$filename = $ss_name;
		}

		$inherited = '';

		$sep = ' ';
		$inheritance_data = $ss_arr[-1];
		unset($ss_arr[-1]);
		$inheritance_chain = (array)$inheritance_data['inheritance_chain'];
		array_shift($inheritance_chain); // Remove the themelet itself
		if ($inheritance_data['orig_plugin']) {
			$inherited .= ' <small>'.$lang->sprintf($lang->associated_with_plugin, get_plugin_name($inheritance_data['orig_plugin'])).'</small>';
		}
		if ($inheritance_chain) {
			$inherited .= " <small>({$lang->inherited_from}";

			foreach ($inheritance_chain as $count => $id_arr) {
				if ($count > 0 && $count == count($inheritance_chain) - 1) {
					$sep = " {$lang->and} ";
				}

				if (!$id_arr['is_plugin']) {
					$title = get_theme_name($id_arr['codename']);
				} else	$title = get_plugin_name($id_arr['codename']);
				$inherited .= $sep.$title.($id_arr['is_plugin'] ? ' '.$lang->the_plugin_sq_br : '');
				$sep = $lang->comma;
			}
			$inherited .= ")</small>";
		}

		if($ss_arr == [0 => ['script' => 'global', 'actions' => [0 => 'global']]]) {
			$attached_to = "<small>{$lang->attached_to_all_pages}</small>";
		} else {
			$attached_to = '';

			$applied_to_count = count($ss_arr);
			$count = 0;
			$sep = " ";
			$name = "";

			$colors = array();

			if (!isset($theme['colors']) || !is_array($theme['colors'])) {
				$theme['colors'] = array();
			}

			foreach ($ss_arr as $script_actions) {
				$name    = $script_actions['script'];
				$actions = $script_actions['actions'];

				if (!$name) {
					continue;
				}

				if (array_key_exists($name, $theme['colors'])) {
					$colors[] = $theme['colors'][$name];
				}

				if (count($colors)) {
					// Colors override files and are handled below.
					continue;
				}

				// It's a file:
				++$count;

				$name = htmlspecialchars_uni($name);

				if ($actions[0] != 'global') {
					$actions = array_map('htmlspecialchars_uni', $actions);

					$name = "{$name} ({$lang->actions}: ".implode(',', $actions).")";
				}

				if ($count == $applied_to_count && $count > 1) {
					$sep = " {$lang->and} ";
				}
				$attached_to .= $sep.$name;

				$sep = $lang->comma;
			}

			if ($attached_to) {
				$attached_to = "<small>{$lang->attached_to} {$attached_to}</small>";
			}

			if (count($colors)) {
				// Attached to color instead of files.
				$count = 1;
				$color_list = $sep = '';

				foreach ($colors as $color) {
					if ($count == count($colors) && $count > 1) {
						$sep = " {$lang->and} ";
					}

					$color_list .= $sep.trim($color);
					++$count;

					$sep = ', ';
				}

				$attached_to = "<small>{$lang->attached_to} ".$lang->sprintf($lang->colors_attached_to)." {$color_list}</small>";
			}

			if ($attached_to == '') {
				// Orphaned! :(
				$attached_to = "<small>{$lang->attached_to_nothing}</small>";
			}
		}

		$popup = new PopupMenu("style_popup_{$order_num}", $lang->options);

		$popup->add_item($lang->edit_style, "index.php?module=style-themes&amp;action=edit_stylesheet&amp;file=".htmlspecialchars_uni($ss_name)."&amp;codename={$theme['codename']}");
		$popup->add_item($lang->properties, "index.php?module=style-themes&amp;action=stylesheet_properties&amp;file=".htmlspecialchars_uni($ss_name)."&amp;codename={$theme['codename']}");

		if (empty($inheritance_chain)) {
			$popup->add_item($lang->delete_revert, "index.php?module=style-themes&amp;action=delete_stylesheet&amp;file=".htmlspecialchars_uni($ss_name)."&amp;codename={$theme['codename']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_stylesheet_deletion}')");
		}

		$table->construct_cell("<strong><a href=\"index.php?module=style-themes&amp;action=edit_stylesheet&amp;file=".htmlspecialchars_uni($ss_name)."&amp;codename={$theme['codename']}\">{$filename}</a></strong>{$inherited}<br />{$attached_to}");
		$disporder_fldnm = 'disporder_'.($inheritance_data['orig_plugin'] ? 'ext.'.$inheritance_data['orig_plugin'].'.' : '').$filename;
		$disporder_fldnm = str_replace('.', '~', $disporder_fldnm); // Encode dots with tildes due to PHP limitation (dots converted to underscores)
		$table->construct_cell($form->generate_numeric_field($disporder_fldnm, $order_num++, array('style' => 'width: 80%; text-align: center;', 'min' => 0)), array("class" => "align_center"));
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
	}

	$table->output("{$lang->stylesheets_in} ".htmlspecialchars_uni($theme['name']));

	$buttons = array($form->generate_submit_button($lang->save_stylesheet_order));
	$form->output_submit_wrapper($buttons);
	$form->end();

	echo '<br />';

	// Theme Properties table
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=style-themes&amp;action=edit", "post", "edit");
	echo $form->generate_hidden_field("codename", $theme['codename']);
	$form_container = new FormContainer($lang->edit_theme_properties);
	$form_container->output_row($lang->name." <em>*</em>", $lang->name_desc_edit, $form->generate_text_box('name', $theme['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->description." <em>*</em>", $lang->description_desc_edit, $form->generate_text_box('description', $theme['description'], array('id' => 'description')), 'description');

	$options = build_fs_theme_select($name, /*$selected = */'', /*$usergroup_override = */true, /*$footer = */false, /*$count_override = */false, /*$return_opts_only = */true, /*$ignoredtheme = */$mybb->input['codename']);
	$form_container->output_row($lang->parent_theme." <em>*</em>", $lang->parent_theme_desc, $form->generate_select_box('parent', $options, $theme['parent'], array('id' => 'parent')), 'parent');

	$options = array();
	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
	$options['all'] = $lang->all_user_groups;
	while ($usergroup = $db->fetch_array($query)) {
		$options[(int)$usergroup['gid']] = $usergroup['title'];
	}
	$form_container->output_row($lang->allowed_user_groups, $lang->allowed_user_groups_desc, $form->generate_select_box('allowedgroups[]', $options, explode(",", $theme['allowedgroups']), array('id' => 'allowedgroups', 'multiple' => true, 'size' => 5)), 'allowedgroups');

	$options = array();
	$editor_theme_root = MYBB_ROOT."jscripts/sceditor/themes/";
	if ($dh = @opendir($editor_theme_root)) {
		while($dir = readdir($dh)) {
			if($dir == ".svn" || $dir == "." || $dir == ".." || is_dir($editor_theme_root.$dir) || get_extension($editor_theme_root.$dir) != 'css') {
				continue;
			}
			$options[$dir] = ucfirst(str_replace(array('_', '.css'), array(' ', ''), $dir));
		}
	}

	$form_container->output_row($lang->editor_theme." <em>*</em>", $lang->editor_theme_desc, $form->generate_select_box('editortheme', $options, $theme['editortheme'], array('id' => 'editortheme')), 'editortheme');

	$form_container->output_row($lang->logo, $lang->logo_desc, $form->generate_text_box('logo', $theme['logo'], array('id' => 'boardlogo')), 'logo');
	$form_container->output_row($lang->table_spacing, $lang->table_spacing_desc, $form->generate_numeric_field('tablespace', $theme['tablespace'], array('id' => 'tablespace', 'min' => 0)), 'tablespace');
	$form_container->output_row($lang->inner_border, $lang->inner_border_desc, $form->generate_numeric_field('borderwidth', $theme['borderwidth'], array('id' => 'borderwidth', 'min' => 0)), 'borderwidth');

	$form_container->end();

	$form_container = new FormContainer($lang->colors_manage);

	if (empty($theme['colors']) || !is_array($theme['colors'])) {
		$color_setting = $lang->colors_no_color_setting;
	} else {
		$colors = array('none' => $lang->colors_please_select);
		$colors = array_merge($colors, $theme['colors']);

		if (!isset($theme['color']))
		{
			$theme['color'] = 'none';
		}
		$color_setting = $form->generate_select_box('color', $colors, $theme['color'], array('class' => "select\" style=\"width: 200px;"));

		$mybb->input['colors'] = '';
		foreach ($theme['colors'] as $key => $color) {
			if ($mybb->input['colors']) {
				$mybb->input['colors'] .= "\n";
			}

			$mybb->input['colors'] .= "{$key}={$color}";
		}
	}

	$form_container->output_row($lang->colors_setting, $lang->colors_setting_desc, $color_setting, 'color');
	$form_container->output_row($lang->colors_add, $lang->colors_add_desc, $form->generate_text_area('colors', $mybb->get_input('colors'), array('style' => 'width: 200px;', 'rows' => '5')));

	$form_container->end();

	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->save_theme_properties);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "stylesheet_properties")
{
	// Fetch the theme we want to edit this stylesheet in
	require_once MYBB_ROOT.'inc/functions_themes.php';
	$themelet_hierarchy = get_themelet_hierarchy();
	$mode = $mybb->settings['themelet_dev_mode'] ? 'devdist' : 'current';
	$themes = $themelet_hierarchy[$mode]['themes'];

	// Does the theme not exist?
	if (!isset($themes[$mybb->input['codename']])) {
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect('index.php?module=style-themes');
	}

	$theme = $themes[$mybb->input['codename']]['properties'];

	$plugins->run_hooks('admin_style_themes_stylesheet_properties');

	// Fetch list of all of the stylesheets for this theme
	list($stylesheets_a, $disporders) = get_theme_stylesheets($mybb->input['codename'], $mybb->settings['themelet_dev_mode']);

	$stylesheet_props = false;
	// First check for the stylesheet in the theme itself.
	foreach ($stylesheets_a as $plugin_code => $ss_arr) {
		if (isset($ss_arr[$mybb->input['file']])) {
			$ss_filename = $mybb->input['file'];
			$stylesheet_props = $ss_arr[$ss_filename];
			break;
		}
	}
	// If not there, then check whether its filename indicates that it originates in a plugin,
	// and check for it in the plugin.
	if (empty($stylesheet_props)) {
		$prefix = 'ext.';
		$plen   = strlen($prefix);
		if (substr($mybb->input['file'], 0, $plen) === $prefix) {
			list($ext, $plugin_code, $ss_filename) = explode('.', $mybb->input['file'], 3);
			if (isset($stylesheets_a[$plugin_code][$ss_filename])) {
				$stylesheet_props = $stylesheets_a[$plugin_code][$ss_filename];
			}
		}
	}
	// The stylesheet does not exist.
	if (empty($stylesheet_props)) {
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect('index.php?module=style-themes');
	}

	if($mybb->request_method == "post")
	{
		if(!$errors)
		{
			$attached = array();
			if ($mybb->input['attach'] == 0) {
				$attached = [0 => ['script' => 'global', 'actions' => ['global']]];
			} else if ($mybb->input['attach'] == 1) {
				// Our stylesheet is attached to custom pages in MyBB
				foreach ($mybb->input as $id => $value) {
					if (strpos($id, 'attached_') !== false) {
						$att = ['script' => $value];

						// We have a custom attached file
						$attached_id = (int)str_replace('attached_', '', $id);

						if($mybb->input['action_'.$attached_id] == 1)
						{
							// We have custom actions for attached files
							$att['actions'] = explode(',', $mybb->input['action_list_'.$attached_id]);
						} else {
							$att['actions'] = ['global'];
						}

						$attached[] = $att;
					}
				}
			}
			else if($mybb->input['attach'] == 2)
			{
				if(!is_array($mybb->input['color']))
				{
					$errors[] = $lang->error_no_color_picked;
				}
				else
				{
					$attached = [0 => ['script' => $mybb->input['color'], 'actions' => []]];
				}
			}

			// Update the stylesheet

			$resource_file = MYBB_ROOT."inc/themes/{$mybb->input['codename']}/{$mode}/resources.json";
			$resources = read_json_file($resource_file, $err_msg, false);
			if ($err_msg) {
				flash_message($err_msg, 'error');
				admin_redirect("index.php?module=style-themes&action=stylesheet_properties&codename={$mybb->input['codename']}&file={$mybb->input['file']}");

			}

			if ($plugin_code) {
				// We are overriding a plugin's stylesheet.
				$ss_key = "ext.{$plugin_code}.{$ss_filename}";
			} else {
				$ss_key = $ss_filename;
			}
			$resources['stylesheets'][$ss_key] = $attached;

			if (!write_json_file($resource_file, $resources)) {
				flash_message($lang->error_failed_to_save_stylesheet_props, 'error');
				admin_redirect("index.php?module=style-themes&action=stylesheet_properties&codename={$mybb->input['codename']}&file={$mybb->input['file']}");

			}

			$plugins->run_hooks("admin_style_themes_stylesheet_properties_commit");

			// Log admin action
			log_admin_action($mybb->input['file'], $theme['codename'], $theme['name']);

			flash_message($lang->success_stylesheet_properties_updated, 'success');
			admin_redirect("index.php?module=style-themes&action=edit&codename={$theme['codename']}");
		}
	}

	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style-themes&amp;action=edit&amp;codename={$mybb->input['codename']}");
	$page->add_breadcrumb_item(htmlspecialchars_uni($stylesheet['name'])." {$lang->properties}", "index.php?module=style-themes&amp;action=edit_properties&amp;codename={$mybb->input['codename']}");

	$page->output_header("{$lang->themes} - {$lang->stylesheet_properties}");

	if($errors)
	{
		$page->output_inline_error($errors);

		foreach($mybb->input as $name => $value)
		{
			if(strpos($name, "attached") !== false)
			{
				list(, $id) = explode('_', $name);
				$id = (int)$id;

				$applied_to[$value] = array(0 => 'global');

				if($mybb->input['action_'.$id] == 1)
				{
					$applied_to[$value] = explode(',', $mybb->input['action_list_'.$id]);
				}
			}
		}
	}

	$global_checked[1] = "checked=\"checked\"";
	$global_checked[2] = "";
	$global_checked[3] = "";

	$form = new Form("index.php?module=style-themes&amp;action=stylesheet_properties", "post");

	$specific_files = "<div id=\"attach_1\" class=\"attachs\">";
	$count = 0;

	unset($stylesheet_props[-1]);

	if(is_array($stylesheet_props) && ($stylesheet_props[0]['script'] != 'global' || $stylesheet_props[0]['actions'][0] != 'global'))
	{
		$check_actions = "";
		$stylesheet['colors'] = array();

		if(!is_array($theme['colors']))
		{
			$theme['colors'] = array();
		}

		foreach($stylesheet_props as $sp_arr) {
			$name = $sp_arr['script'];
			$actions = $sp_arr['actions'];

			// Verify this is a color for this theme
			if (is_array($name) && !array_diff($name, array_keys($theme['colors']))) {
				$stylesheet['colors'] = $name;
			}

			if(count($stylesheet['colors']))
			{
				// Colors override files and are handled below.
				continue;
			}

			// It's a file:
			$action_list = "";
			if($actions[0] != "global")
			{
				$action_list = implode(',', $actions);
			}

			if($actions[0] == "global")
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
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_{$count}\" value=\"0\" {$global_action_checked[1]} class=\"action_{$count}s_check\" onclick=\"checkAction('action_{$count}');\" style=\"vertical-align: middle;\" /> {$lang->globally}</label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_{$count}\" value=\"1\" {$global_action_checked[2]} class=\"action_{$count}s_check\" onclick=\"checkAction('action_{$count}');\" style=\"vertical-align: middle;\" /> {$lang->specific_actions}</label></dt>
			<dd style=\"margin-top: 4px;\" id=\"action_{$count}_1\" class=\"action_{$count}s\">
			<small class=\"description\">{$lang->specific_actions_desc}</small>
			<table cellpadding=\"4\">
				<tr>
					<td>".$form->generate_text_box('action_list_'.$count, $action_list, array('id' => 'action_list_'.$count, 'style' => 'width: 190px;'))."</td>
				</tr>
			</table>
		</dd>
		</dl>";

			$form_container = new FormContainer();
			$form_container->output_row("", "", "<span style=\"float: right;\"><a href=\"\" id=\"delete_img_{$count}\"><img src=\"styles/{$page->style}/images/icons/cross.png\" alt=\"{$lang->delete}\" title=\"{$lang->delete}\" /></a></span>{$lang->file} &nbsp;".$form->generate_text_box("attached_{$count}", $name, array('id' => "attached_{$count}", 'style' => 'width: 200px;')), "attached_{$count}");

			$form_container->output_row("", "", $specific_file);

			$specific_files .= "<div id=\"attached_form_{$count}\">".$form_container->end(true)."</div><div id=\"attach_box_".($count+1)."\"></div>";

			$check_actions .= "\n\tcheckAction('action_{$count}');";

			++$count;
		}

		if($check_actions)
		{
			$global_checked[3] = "";
			$global_checked[2] = "checked=\"checked\"";
			$global_checked[1] = "";
		}

		if(!empty($stylesheet['colors']))
		{
			$global_checked[3] = "checked=\"checked\"";
			$global_checked[2] = "";
			$global_checked[1] = "";
		}
	}

	$specific_files .= "</div>";

	// Colors
	$specific_colors = $specific_colors_option = '';

	if(is_array($theme['colors']))
	{
		$specific_colors = "<div id=\"attach_2\" class=\"attachs\">";
		$specific_colors_option = '<dt><label style="display: block;"><input type="radio" name="attach" value="2" '.$global_checked[3].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> '.$lang->colors_specific_color.'</label></dt><br />';

		$specific_color = "
			<small>{$lang->colors_add_edit_desc}</small>
			<br /><br />
			".$form->generate_select_box('color[]', $theme['colors'], $stylesheet['colors'], array('multiple' => true, 'size' => "5\" style=\"width: 200px;"))."
		";

		$form_container = new FormContainer();
		$form_container->output_row("", "", $specific_color);
		$specific_colors .= $form_container->end(true)."</div>";
	}

	$actions = '<script type="text/javascript">
	function checkAction(id)
	{
		var checked = \'\';

		$(\'.\'+id+\'s_check\').each(function(e, val)
		{
			if($(this).prop(\'checked\') == true)
			{
				checked = $(this).val();
			}
		});
		$(\'.\'+id+\'s\').each(function(e)
		{
			$(this).hide();
		});
		if($(\'#\'+id+\'_\'+checked))
		{
			$(\'#\'+id+\'_\'+checked).show();
		}
	}
</script>
	<dl style="margin-top: 0; margin-bottom: 0; width: 40%;">
		<dt><label style="display: block;"><input type="radio" name="attach" value="0" '.$global_checked[1].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> '.$lang->globally.'</label></dt><br />
		<dt><label style="display: block;"><input type="radio" name="attach" value="1" '.$global_checked[2].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> '.$lang->specific_files.' (<a id="new_specific_file">'.$lang->add_another.'</a>)</label></dt><br />
		'.$specific_files.'
		'.$specific_colors_option.'
		'.$specific_colors.'
	</dl>
	<script type="text/javascript">
	checkAction(\'attach\');'.$check_actions.'
	</script>';

	echo $form->generate_hidden_field("file", htmlspecialchars_uni($mybb->input['file']))."<br />\n";
	echo $form->generate_hidden_field("codename", $theme['codename'])."<br />\n";

	$form_container = new FormContainer("{$lang->edit_stylesheet_properties_for} ".htmlspecialchars_uni($mybb->input['file']));

	$form_container->output_row($lang->attached_to, $lang->attached_to_desc, $actions);

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_stylesheet_properties);

	$form->output_submit_wrapper($buttons);

	echo <<<EOF

	<script type="text/javascript" src="./jscripts/theme_properties.js?ver=1821"></script>
	<script type="text/javascript">
	<!---
	themeProperties.setup('{$count}');
	// -->
	</script>
EOF;

	$form->end();

	$page->output_footer();
}

// Shows the page where you can actually edit a particular selector or the whole stylesheet
if($mybb->input['action'] == "edit_stylesheet" && (!isset($mybb->input['mode']) || $mybb->input['mode'] == "simple"))
{
	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'");
	$theme = $db->fetch_array($query);

	if(empty($theme['tid']) || $theme['tid'] == 1)
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	$plugins->run_hooks("admin_style_themes_edit_stylesheet_simple");

	$parent_list = make_parent_theme_list($theme['tid']);
	$parent_list = implode(',', $parent_list);
	if(!$parent_list)
	{
		$parent_list = 1;
	}

	$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", array('order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1));
	$stylesheet = $db->fetch_array($query);

	// Does the theme not exist?
	if(!$stylesheet['sid'])
	{
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	if($mybb->request_method == "post")
	{
		$sid = $stylesheet['sid'];

		// Theme & stylesheet theme ID do not match, editing inherited - we copy to local theme
		if($theme['tid'] != $stylesheet['tid'])
		{
			$sid = copy_stylesheet_to_theme($stylesheet, $theme['tid']);
		}

		// Insert the modified CSS
		$new_stylesheet = $stylesheet['stylesheet'];

		if($mybb->input['serialized'] == 1)
		{
			$mybb->input['css_bits'] = my_unserialize($mybb->input['css_bits']);
		}

		$css_to_insert = '';
		foreach($mybb->input['css_bits'] as $field => $value)
		{
			if(!trim($value) || !trim($field))
			{
				continue;
			}

			if($field == "extra")
			{
				$css_to_insert .= $value."\n";
			}
			else
			{
				$field = str_replace("_", "-", $field);
				$css_to_insert .= "{$field}: {$value};\n";
			}
		}

		$new_stylesheet = insert_into_css($css_to_insert, $mybb->input['selector'], $new_stylesheet);

		// Now we have the new stylesheet, save it
		$updated_stylesheet = array(
			"cachefile" => $db->escape_string($stylesheet['name']),
			"stylesheet" => $db->escape_string($new_stylesheet),
			"lastmodified" => TIME_NOW
		);
		$db->update_query("themestylesheets", $updated_stylesheet, "sid='{$sid}'");

		// Cache the stylesheet to the file
		if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $new_stylesheet))
		{
			$db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
		}

		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid']);

		$plugins->run_hooks("admin_style_themes_edit_stylesheet_simple_commit");

		// Log admin action
		log_admin_action(htmlspecialchars_uni($theme['name']), $stylesheet['name']);

		if(!$mybb->input['ajax'])
		{
			flash_message($lang->success_stylesheet_updated, 'success');

			if($mybb->input['save_close'])
			{
				admin_redirect("index.php?module=style-themes&action=edit&tid={$theme['tid']}");
			}
			else
			{
				admin_redirect("index.php?module=style-themes&action=edit_stylesheet&tid={$theme['tid']}&file={$stylesheet['name']}");
			}
		}
		else
		{
			echo "1";
			exit;
		}
	}

	// Has the file on the file system been modified?
	if(resync_stylesheet($stylesheet))
	{
		// Need to refetch new stylesheet as it was modified
		$query = $db->simple_select("themestylesheets", "stylesheet", "sid='{$stylesheet['sid']}'");
		$stylesheet['stylesheet'] = $db->fetch_field($query, 'stylesheet');
	}

	$css_array = css_to_array($stylesheet['stylesheet']);
	$selector_list = get_selectors_as_options($css_array, $mybb->get_input('selector'));

	// Do we not have any selectors? Send em to the full edit page
	if(!$selector_list)
	{
		flash_message($lang->error_cannot_parse, 'error');
		admin_redirect("index.php?module=style-themes&action=edit_stylesheet&tid={$theme['tid']}&file=".htmlspecialchars_uni($stylesheet['name'])."&mode=advanced");
		exit;
	}

	// Fetch list of all of the stylesheets for this theme
	$stylesheets = fetch_theme_stylesheets($theme);
	$this_stylesheet = $stylesheets[$stylesheet['name']];
	unset($stylesheets);

	$page->extra_header .= "
	<script type=\"text/javascript\">
	var my_post_key = '".$mybb->post_code."';
	</script>";

	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style-themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
	$page->add_breadcrumb_item("{$lang->editing} ".htmlspecialchars_uni($stylesheet['name']), "index.php?module=style-themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;file=".htmlspecialchars_uni($mybb->input['file'])."&amp;mode=simple");

	$page->output_header("{$lang->themes} - {$lang->edit_stylesheets}");

	// If the stylesheet and theme do not match, we must be editing something that is inherited
	if(!empty($this_stylesheet['inherited'][$stylesheet['name']]))
	{
		$query = $db->simple_select("themes", "name", "tid='{$stylesheet['tid']}'");
		$stylesheet_parent = htmlspecialchars_uni($db->fetch_field($query, 'name'));

		// Show inherited warning
		if($stylesheet['tid'] == 1)
		{
			$page->output_alert($lang->sprintf($lang->stylesheet_inherited_default, $stylesheet_parent), "ajax_alert");
		}
		else
		{
			$page->output_alert($lang->sprintf($lang->stylesheet_inherited, $stylesheet_parent), "ajax_alert");
		}
	}

	$sub_tabs['edit_stylesheet'] = array(
		'title' => $lang->edit_stylesheet_simple_mode,
		'link' => "index.php?module=style-themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;file=".htmlspecialchars_uni($mybb->input['file'])."&amp;mode=simple",
		'description' => $lang->edit_stylesheet_simple_mode_desc
	);

	$sub_tabs['edit_stylesheet_advanced'] = array(
		'title' => $lang->edit_stylesheet_advanced_mode,
		'link' => "index.php?module=style-themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;file=".htmlspecialchars_uni($mybb->input['file'])."&amp;mode=advanced",
	);

	$page->output_nav_tabs($sub_tabs, 'edit_stylesheet');

	// Output the selection box
	$form = new Form("index.php", "get", "selector_form");
	echo $form->generate_hidden_field("module", "style/themes")."\n";
	echo $form->generate_hidden_field("action", "edit_stylesheet")."\n";
	echo $form->generate_hidden_field("tid", $mybb->input['tid'])."\n";
	echo $form->generate_hidden_field("file", htmlspecialchars_uni($mybb->input['file']))."\n";

	echo "{$lang->selector}: <select id=\"selector\" name=\"selector\">\n{$selector_list}</select> <span id=\"mini_spinner\">".$form->generate_submit_button($lang->go)."</span><br /><br />\n";

	$form->end();

	// Haven't chosen a selector to edit, show the first one from the stylesheet
	if(!$mybb->get_input('selector'))
	{
		reset($css_array);
		uasort($css_array, "css_selectors_sort_cmp");
		$selector = key($css_array);
		$editable_selector = $css_array[$selector];
	}
	// Show a specific selector
	else
	{
		$editable_selector = $css_array[$mybb->input['selector']];
		$selector = $mybb->input['selector'];
	}

	// Get the properties from this item
	$properties = parse_css_properties($editable_selector['values']);

	foreach(array('background', 'color', 'width', 'font-family', 'font-size', 'font-style', 'font-weight', 'text-decoration') as $_p)
	{
		if(!isset($properties[$_p]))
		{
			$properties[$_p] = '';
		}
	}

	$form = new Form("index.php?module=style-themes&amp;action=edit_stylesheet", "post");
	echo $form->generate_hidden_field("tid", $mybb->input['tid'], array('id' => "tid"))."\n";
	echo $form->generate_hidden_field("file", htmlspecialchars_uni($mybb->input['file']), array('id' => "file"))."\n";
	echo $form->generate_hidden_field("selector", htmlspecialchars_uni($selector), array('id' => 'hidden_selector'))."\n";

	echo "<div id=\"stylesheet\">";
	$table = new Table;
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[background]', $properties['background'], array('id' => 'css_bits[background]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->background}</strong></div>", array('style' => 'width: 20%;'));
	$table->construct_cell("<strong>{$lang->extra_css_atribs}</strong><br /><div style=\"align: center;\">".$form->generate_text_area('css_bits[extra]', $properties['extra'], array('id' => 'css_bits[extra]', 'style' => 'width: 98%;', 'rows' => '19'))."</div>", array('rowspan' => 8));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[color]', $properties['color'], array('id' => 'css_bits[color]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->color}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[width]', $properties['width'], array('id' => 'css_bits[width]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->width}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[font_family]', $properties['font-family'], array('id' => 'css_bits[font_family]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_family}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[font_size]', $properties['font-size'], array('id' => 'css_bits[font_size]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_size}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[font_style]', $properties['font-style'], array('id' => 'css_bits[font_style]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_style}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[font_weight]', $properties['font-weight'], array('id' => 'css_bits[font_weight]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->font_weight}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();
	$table->construct_cell("<div style=\"float: right;\">".$form->generate_text_box('css_bits[text_decoration]', $properties['text-decoration'], array('id' => 'css_bits[text_decoration]', 'style' => 'width: 260px;'))."</div><div><strong>{$lang->text_decoration}</strong></div>", array('style' => 'width: 40%;'));
	$table->construct_row();

	$table->output(htmlspecialchars_uni($editable_selector['class_name'])."<span id=\"saved\" style=\"color: #FEE0C6;\"></span>");

	echo "</div>";

	$buttons[] = $form->generate_reset_button($lang->reset);
	$buttons[] = $form->generate_submit_button($lang->save_changes, array('id' => 'save', 'name' => 'save'));
	$buttons[] = $form->generate_submit_button($lang->save_changes_and_close, array('id' => 'save_close', 'name' => 'save_close'));

	$form->output_submit_wrapper($buttons);

	echo '<script type="text/javascript" src="./jscripts/themes.js?ver=1808"></script>';
	echo '<script type="text/javascript">

$(function() {
//<![CDATA[
	ThemeSelector.init("./index.php?module=style-themes&action=xmlhttp_stylesheet", "./index.php?module=style-themes&action=edit_stylesheet", $("#selector"), $("#stylesheet"), "'.htmlspecialchars_uni($mybb->input['file']).'", $("#selector_form"), "'.$mybb->input['tid'].'");
	lang.saving = "'.$lang->saving.'";
});
//]]>
</script>';

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit_stylesheet" && $mybb->input['mode'] == "advanced")
{
	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'");
	$theme = $db->fetch_array($query);

	if(empty($theme['tid']) || $theme['tid'] == 1)
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	$plugins->run_hooks("admin_style_themes_edit_stylesheet_advanced");

	$parent_list = make_parent_theme_list($theme['tid']);
	$parent_list = implode(',', $parent_list);
	if(!$parent_list)
	{
		$parent_list = 1;
	}

	$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", array('order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1));
	$stylesheet = $db->fetch_array($query);

	// Does the theme not exist?
	if($db->num_rows($query) == 0)
	{
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	if($mybb->request_method == "post")
	{
		$sid = $stylesheet['sid'];

		// Theme & stylesheet theme ID do not match, editing inherited - we copy to local theme
		if($theme['tid'] != $stylesheet['tid'])
		{
			$sid = copy_stylesheet_to_theme($stylesheet, $theme['tid']);
		}

		// Now we have the new stylesheet, save it
		$updated_stylesheet = array(
			"cachefile" => $db->escape_string($stylesheet['name']),
			"stylesheet" => $db->escape_string($mybb->input['stylesheet']),
			"lastmodified" => TIME_NOW
		);
		$db->update_query("themestylesheets", $updated_stylesheet, "sid='{$sid}'");

		// Cache the stylesheet to the file
		if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $mybb->input['stylesheet']))
		{
			$db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
		}

		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid']);

		$plugins->run_hooks("admin_style_themes_edit_stylesheet_advanced_commit");

		// Log admin action
		log_admin_action(htmlspecialchars_uni($theme['name']), $stylesheet['name']);

		flash_message($lang->success_stylesheet_updated, 'success');

		if(!$mybb->get_input('save_close'))
		{
			admin_redirect("index.php?module=style-themes&action=edit_stylesheet&file=".htmlspecialchars_uni($stylesheet['name'])."&tid={$theme['tid']}&mode=advanced");
		}
		else
		{
			admin_redirect("index.php?module=style-themes&action=edit&tid={$theme['tid']}");
		}
	}

	// Fetch list of all of the stylesheets for this theme
	$stylesheets = fetch_theme_stylesheets($theme);
	$this_stylesheet = $stylesheets[$stylesheet['name']];
	unset($stylesheets);

	if($admin_options['codepress'] != 0)
	{
		$page->extra_header .= '
<link href="./jscripts/codemirror/lib/codemirror.css?ver=1813" rel="stylesheet">
<link href="./jscripts/codemirror/theme/mybb.css?ver=1813" rel="stylesheet">
<link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css?ver=1813" rel="stylesheet">
<script src="./jscripts/codemirror/lib/codemirror.js?ver=1813"></script>
<script src="./jscripts/codemirror/mode/css/css.js?ver=1813"></script>
<script src="./jscripts/codemirror/addon/dialog/dialog.js?ver=1813"></script>
<script src="./jscripts/codemirror/addon/search/searchcursor.js?ver=1813"></script>
<script src="./jscripts/codemirror/addon/search/search.js?ver=1821"></script>
';
	}

	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style-themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
	$page->add_breadcrumb_item("{$lang->editing} ".htmlspecialchars_uni($stylesheet['name']), "index.php?module=style-themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;file=".htmlspecialchars_uni($mybb->input['file'])."&amp;mode=advanced");

	$page->output_header("{$lang->themes} - {$lang->edit_stylesheet_advanced_mode}");

	// If the stylesheet and theme do not match, we must be editing something that is inherited
	if(!empty($this_stylesheet['inherited']) && $this_stylesheet['inherited'][$stylesheet['name']])
	{
		$query = $db->simple_select("themes", "name", "tid='{$stylesheet['tid']}'");
		$stylesheet_parent = htmlspecialchars_uni($db->fetch_field($query, 'name'));

		// Show inherited warning
		if($stylesheet['tid'] == 1)
		{
			$page->output_alert($lang->sprintf($lang->stylesheet_inherited_default, $stylesheet_parent));
		}
		else
		{
			$page->output_alert($lang->sprintf($lang->stylesheet_inherited, $stylesheet_parent));
		}
	}

	$sub_tabs['edit_stylesheet'] = array(
		'title' => $lang->edit_stylesheet_simple_mode,
		'link' => "index.php?module=style-themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;file=".htmlspecialchars_uni($mybb->input['file'])."&amp;mode=simple"
	);

	$sub_tabs['edit_stylesheet_advanced'] = array(
		'title' => $lang->edit_stylesheet_advanced_mode,
		'link' => "index.php?module=style-themes&amp;action=edit_stylesheet&amp;tid={$mybb->input['tid']}&amp;file=".htmlspecialchars_uni($mybb->input['file'])."&amp;mode=advanced",
		'description' => $lang->edit_stylesheet_advanced_mode_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_stylesheet_advanced');

	// Has the file on the file system been modified?
	if(resync_stylesheet($stylesheet))
	{
		// Need to refetch new stylesheet as it was modified
		$query = $db->simple_select("themestylesheets", "stylesheet", "sid='{$stylesheet['sid']}'");
		$stylesheet['stylesheet'] = $db->fetch_field($query, 'stylesheet');
	}

	$form = new Form("index.php?module=style-themes&amp;action=edit_stylesheet&amp;mode=advanced", "post", "edit_stylesheet");
	echo $form->generate_hidden_field("tid", $mybb->input['tid'])."\n";
	echo $form->generate_hidden_field("file", htmlspecialchars_uni($mybb->input['file']))."\n";

	$table = new Table;
	$table->construct_cell($form->generate_text_area('stylesheet', $stylesheet['stylesheet'], array('id' => 'stylesheet', 'style' => 'width: 99%;', 'class' => '', 'rows' => '30')));
	$table->construct_row();
	$table->output($lang->full_stylesheet_for.' '.htmlspecialchars_uni($stylesheet['name']), 1, 'tfixed');

	$buttons[] = $form->generate_submit_button($lang->save_changes, array('id' => 'save', 'name' => 'save'));
	$buttons[] = $form->generate_submit_button($lang->save_changes_and_close, array('id' => 'save_close', 'name' => 'save_close'));

	$form->output_submit_wrapper($buttons);

	$form->end();

	if($admin_options['codepress'] != 0)
	{
		echo '<script type="text/javascript">
			var editor = CodeMirror.fromTextArea(document.getElementById("stylesheet"), {
				lineNumbers: true,
				lineWrapping: true,
				viewportMargin: Infinity,
				indentWithTabs: true,
				indentUnit: 4,
				mode: "text/css",
				theme: "mybb"
			});</script>';
	}

	$page->output_footer();
}

if($mybb->input['action'] == "delete_stylesheet")
{
	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'");
	$theme = $db->fetch_array($query);

	if(!$theme['tid'] || $theme['tid'] == 1)
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	$plugins->run_hooks("admin_style_themes_delete_stylesheet");

	$parent_list = make_parent_theme_list($theme['tid']);
	$parent_list = implode(',', $parent_list);
	if(!$parent_list)
	{
		$parent_list = 1;
	}

	$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", array('order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1));
	$stylesheet = $db->fetch_array($query);

	// Does the theme not exist? or are we trying to delete the master?
	if(!$stylesheet['sid'] || $stylesheet['tid'] == 1)
	{
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	// User clicked no
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=style-themes");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query("themestylesheets", "sid='{$stylesheet['sid']}'", 1);
		@unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/{$stylesheet['cachefile']}");

		$filename_min = str_replace('.css', '.min.css', $stylesheet['cachefile']);
		@unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/{$filename_min}");

		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid'], $theme, true);

		$plugins->run_hooks("admin_style_themes_delete_stylesheet_commit");

		// Log admin action
		log_admin_action($stylesheet['sid'], $stylesheet['name'], $theme['tid'], $theme['name']);

		flash_message($lang->success_stylesheet_deleted, 'success');
		admin_redirect("index.php?module=style-themes&action=edit&tid={$theme['tid']}");
	}
	else
	{
		$page->output_confirm_action("index.php?module=style-themes&amp;action=force&amp;tid={$theme['tid']}", $lang->confirm_stylesheet_deletion);
	}
}

if($mybb->input['action'] == "add_stylesheet")
{
	// Fetch the theme we want to edit this stylesheet in
	$query = $db->simple_select("themes", "*", "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'");
	$theme = $db->fetch_array($query);

	if(empty($theme['tid']) || $theme['tid'] == 1)
	{
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	$plugins->run_hooks("admin_style_themes_add_stylesheet");

	// Fetch list of all of the stylesheets for this theme
	$stylesheets = fetch_theme_stylesheets($theme);

	if($mybb->request_method == "post")
	{
		// Remove special characters
		$mybb->input['name'] = preg_replace('#([^a-z0-9-_\.]+)#i', '', $mybb->input['name']);
		if(!$mybb->input['name'] || $mybb->input['name'] == ".css")
		{
			$errors[] = $lang->error_missing_stylesheet_name;
		}

		// Get 30 chars only because we don't want more than that
		$mybb->input['name'] = my_substr($mybb->input['name'], 0, 30);
		if(get_extension($mybb->input['name']) != "css")
		{
			// Does not end with '.css'
			$errors[] = $lang->sprintf($lang->error_missing_stylesheet_extension, $mybb->input['name']);
		}

		if(!$errors)
		{
			if($mybb->input['add_type'] == 1)
			{
				// Import from a current stylesheet
				$parent_list = make_parent_theme_list($theme['tid']);
				$parent_list = implode(',', $parent_list);

				$query = $db->simple_select("themestylesheets", "stylesheet", "name='".$db->escape_string($mybb->input['import'])."' AND tid IN ({$parent_list})", array('limit' => 1, 'order_by' => 'tid', 'order_dir' => 'desc'));
				$stylesheet = $db->fetch_field($query, "stylesheet");
			}
			else
			{
				// Custom stylesheet
				$stylesheet = $mybb->input['stylesheet'];
			}

			$attached = array();

			if($mybb->input['attach'] == 1)
			{
				// Our stylesheet is attached to custom pages in MyBB
				foreach($mybb->input as $id => $value)
				{
					$actions_list = "";
					$attached_to = "";

					if(strpos($id, 'attached_') !== false)
					{
						// We have a custom attached file
						$attached_id = (int)str_replace('attached_', '', $id);
						$attached_to = $value;

						if($mybb->input['action_'.$attached_id] == 1)
						{
							// We have custom actions for attached files
							$actions_list = $mybb->input['action_list_'.$attached_id];
						}

						if($actions_list)
						{
							$attached_to = $attached_to."?".$actions_list;
						}

						$attached[] = $attached_to;
					}
				}
			}
			else if($mybb->input['attach'] == 2)
			{
				if(!is_array($mybb->input['color']))
				{
					$errors[] = $lang->error_no_color_picked;
				}
				else
				{
					$attached = $mybb->input['color'];
				}
			}

			// Add Stylesheet
			$insert_array = array(
				'name' => $db->escape_string($mybb->input['name']),
				'tid' => $mybb->get_input('tid', MyBB::INPUT_INT),
				'attachedto' => implode('|', array_map(array($db, "escape_string"), $attached)),
				'stylesheet' => $db->escape_string($stylesheet),
				'cachefile' => $db->escape_string(str_replace('/', '', $mybb->input['name'])),
				'lastmodified' => TIME_NOW
			);

			$sid = $db->insert_query("themestylesheets", $insert_array);

			if(!cache_stylesheet($theme['tid'], str_replace('/', '', $mybb->input['name']), $stylesheet))
			{
				$db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
			}

			// Update the CSS file list for this theme
			update_theme_stylesheet_list($theme['tid'], $theme, true);

			$plugins->run_hooks("admin_style_themes_add_stylesheet_commit");

			// Log admin action
			log_admin_action($sid, $mybb->input['name'], $theme['tid'], $theme['name']);

			flash_message($lang->success_stylesheet_added, 'success');
			admin_redirect("index.php?module=style-themes&action=edit_stylesheet&tid={$mybb->input['tid']}&sid={$sid}&file=".urlencode($mybb->input['name']));
		}
	}

	if($admin_options['codepress'] != 0)
	{
		$page->extra_header .= '
<link href="./jscripts/codemirror/lib/codemirror.css?ver=1813" rel="stylesheet">
<link href="./jscripts/codemirror/theme/mybb.css?ver=1813" rel="stylesheet">
<link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css?ver=1813" rel="stylesheet">
<script src="./jscripts/codemirror/lib/codemirror.js?ver=1813"></script>
<script src="./jscripts/codemirror/mode/css/css.js?ver=1813"></script>
<script src="./jscripts/codemirror/addon/dialog/dialog.js?ver=1813"></script>
<script src="./jscripts/codemirror/addon/search/searchcursor.js?ver=1813"></script>
<script src="./jscripts/codemirror/addon/search/search.js?ver=1821"></script>
';
	}

	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), "index.php?module=style-themes&amp;action=edit&amp;tid={$mybb->input['tid']}");
	$page->add_breadcrumb_item($lang->add_stylesheet);
	$properties = my_unserialize($theme['properties']);

	$page->output_header("{$lang->themes} - {$lang->add_stylesheet}");

	$sub_tabs['edit_stylesheets'] = array(
		'title' => $lang->edit_stylesheets,
		'link' => "index.php?module=style-themes&amp;action=edit&amp;tid={$mybb->input['tid']}"
	);

	$sub_tabs['add_stylesheet'] = array(
		'title' => $lang->add_stylesheet,
		'link' => "index.php?module=style-themes&amp;action=add_stylesheet&amp;tid={$mybb->input['tid']}",
		'description' => $lang->add_stylesheet_desc
	);

	$sub_tabs['export_theme'] = array(
		'title' => $lang->export_theme,
		'link' => "index.php?module=style-themes&amp;action=export&amp;tid={$mybb->input['tid']}"
	);

	$sub_tabs['duplicate_theme'] = array(
		'title' => $lang->duplicate_theme,
		'link' => "index.php?module=style-themes&amp;action=duplicate&amp;tid={$mybb->input['tid']}",
		'description' => $lang->duplicate_theme_desc
	);

	$page->output_nav_tabs($sub_tabs, 'add_stylesheet');

	$add_checked = array();

	if($errors)
	{
		$page->output_inline_error($errors);

		foreach($mybb->input as $name => $value)
		{
			if(strpos($name, "attached") !== false)
			{
				list(, $id) = explode('_', $name);
				$id = (int)$id;

				$mybb->input['applied_to'][$value] = array(0 => 'global');

				if($mybb->input['action_'.$id] == 1)
				{
					$mybb->input['applied_to'][$value] = explode(',', $mybb->input['action_list_'.$id]);
				}
			}
		}

		if($mybb->input['add_type'] == 1)
		{
			$add_checked[1] = "checked=\"checked\"";
			$add_checked[2] = "";
		}
		else
		{
			$add_checked[2] = "checked=\"checked\"";
			$add_checked[1] = "";
		}
	}
	else
	{
		$stylesheet = $mybb->get_input('stylesheet', MyBB::INPUT_ARRAY);
		if(!isset($stylesheet['sid']))
		{
			$stylesheet['sid'] = '';
		}
		if(isset($stylesheet['name']))
		{
			$mybb->input['name'] = $stylesheet['name'];
		}

		$add_checked[1] = "";
		$add_checked[2] = "";
	}

	$global_checked[1] = "checked=\"checked\"";
	$global_checked[2] = "";
	$global_checked[3] = "";

	$form = new Form("index.php?module=style-themes&amp;action=add_stylesheet", "post", "add_stylesheet");

	echo $form->generate_hidden_field("tid", $mybb->input['tid'])."\n";

	$specific_files = "<div id=\"attach_1\" class=\"attachs\">";
	$count = 0;
	$check_actions = "";
	$mybb->input['attach'] = $mybb->get_input('attach', MyBB::INPUT_INT);
	$stylesheet['colors'] = array();
	$stylesheet['sid'] = null;

	if($mybb->input['attach'] == 1 && is_array($mybb->input['applied_to']) && (!isset($mybb->input['applied_to']['global']) || $mybb->input['applied_to']['global'][0] != "global"))
	{
		foreach($mybb->input['applied_to'] as $name => $actions)
		{
			$action_list = "";
			if($actions[0] != "global")
			{
				$action_list = implode(',', $actions);
			}

			if($actions[0] == "global")
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
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_{$count}\" value=\"0\" {$global_action_checked[1]} class=\"action_{$count}s_check\" onclick=\"checkAction('action_{$count}');\" style=\"vertical-align: middle;\" /> {$lang->globally}</label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"action_{$count}\" value=\"1\" {$global_action_checked[2]} class=\"action_{$count}s_check\" onclick=\"checkAction('action_{$count}');\" style=\"vertical-align: middle;\" /> {$lang->specific_actions}</label></dt>
			<dd style=\"margin-top: 4px;\" id=\"action_{$count}_1\" class=\"action_{$count}s\">
				<small class=\"description\">{$lang->specific_actions_desc}</small>
				<table cellpadding=\"4\">
					<tr>
						<td>".$form->generate_text_box('action_list_'.$count, $action_list, array('id' => 'action_list_'.$count, 'style' => 'width: 190px;'))."</td>
					</tr>
				</table>
			</dd>
		</dl>";

			$form_container = new FormContainer();
			$form_container->output_row("", "", "<span style=\"float: right;\"><a href=\"\" id=\"delete_img_{$count}\"><img src=\"styles/{$page->style}/images/icons/cross.png\" alt=\"{$lang->delete}\" title=\"{$lang->delete}\" /></a></span>{$lang->file} &nbsp;".$form->generate_text_box("attached_{$count}", $name, array('id' => "attached_{$count}", 'style' => 'width: 200px;')), "attached_{$count}");

			$form_container->output_row("", "", $specific_file);

			$specific_files .= "<div id=\"attached_form_{$count}\">".$form_container->end(true)."</div><div id=\"attach_box_{$count}\"></div>";

			$check_actions .= "\n\tcheckAction('action_{$count}');";

			++$count;
		}

		if($check_actions)
		{
			$global_checked[3] = "";
			$global_checked[2] = "checked=\"checked\"";
			$global_checked[1] = "";
		}
	}
	else if($mybb->input['attach'] == 2)
	{
		// Colors
		if(is_array($properties['colors']))
		{
			// We might have colors here...
			foreach($mybb->input['color'] as $color)
			{
				// Verify this is a color for this theme
				if(array_key_exists($color, $properties['colors']))
				{
					$stylesheet['colors'][] = $color;
				}
			}

			if(!empty($stylesheet['colors']))
			{
				$global_checked[3] = "checked=\"checked\"";
				$global_checked[2] = "";
				$global_checked[1] = "";
			}
		}
	}

	$specific_files .= "</div>";

	// Colors
	$specific_colors = $specific_colors_option = '';

	if(isset($properties['colors']) && is_array($properties['colors']))
	{
		$specific_colors = "<br /><div id=\"attach_2\" class=\"attachs\">";
		$specific_colors_option = '<dt><label style="display: block;"><input type="radio" name="attach" value="2" '.$global_checked[3].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> '.$lang->colors_specific_color.'</label></dt>';

		$specific_color = "
			<small>{$lang->colors_add_edit_desc}</small>
			<br /><br />
			".$form->generate_select_box('color[]', $properties['colors'], $stylesheet['colors'], array('multiple' => true, 'size' => "5\" style=\"width: 200px;"))."
		";

		$form_container = new FormContainer();
		$form_container->output_row("", "", $specific_color);
		$specific_colors .= $form_container->end(true)."</div>";
	}

	$actions = '<script type="text/javascript">
	function checkAction(id)
	{
		var checked = \'\';

		$(\'.\'+id+\'s_check\').each(function(e, val)
		{
			if($(this).prop(\'checked\') == true)
			{
				checked = $(this).val();
			}
		});
		$(\'.\'+id+\'s\').each(function(e)
		{
			$(this).hide();
		});
		if($(\'#\'+id+\'_\'+checked))
		{
			$(\'#\'+id+\'_\'+checked).show();
		}
	}
</script>
	<dl style="margin-top: 0; margin-bottom: 0; width: 40%;">
		<dt><label style="display: block;"><input type="radio" name="attach" value="0" '.$global_checked[1].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> '.$lang->globally.'</label></dt><br />
		<dt><label style="display: block;"><input type="radio" name="attach" value="1" '.$global_checked[2].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> '.$lang->specific_files.' (<a id="new_specific_file">'.$lang->add_another.'</a>)</label></dt><br />
		'.$specific_files.'
		'.$specific_colors_option.'
		'.$specific_colors.'
	</dl>
	<script type="text/javascript">
	checkAction(\'attach\');'.$check_actions.'
	</script>';

	echo $form->generate_hidden_field("sid", $stylesheet['sid'])."<br />\n";

	$form_container = new FormContainer($lang->add_stylesheet_to.' '.htmlspecialchars_uni($theme['name']), 'tfixed');
	$form_container->output_row($lang->file_name, $lang->file_name_desc, $form->generate_text_box('name', $mybb->get_input('name'), array('id' => 'name', 'style' => 'width: 200px;')), 'name');

	$form_container->output_row($lang->attached_to, $lang->attached_to_desc, $actions);

	$sheetnames = array();
	foreach($stylesheets as $filename => $style)
	{
		$sheetnames[basename($filename)] = basename($filename);
	}

	$actions = "<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"add_type\" value=\"1\" {$add_checked[1]} class=\"adds_check\" onclick=\"checkAction('add');\" style=\"vertical-align: middle;\" /> <strong>{$lang->import_stylesheet_from}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"add_1\" class=\"adds\">
			<table cellpadding=\"4\">
				<tr>
					<td>".$form->generate_select_box('import', $sheetnames, $mybb->get_input('import'), array('id' => 'import'))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"add_type\" value=\"2\" {$add_checked[2]} class=\"adds_check\" onclick=\"checkAction('add');\" style=\"vertical-align: middle;\" /> <strong>{$lang->write_own}</strong></label></dt>
		<span id=\"add_2\" class=\"adds\"><br />".$form->generate_text_area('stylesheet', $mybb->get_input('stylesheet'), array('id' => 'stylesheet', 'style' => 'width: 99%;', 'class' => '', 'rows' => '30'))."</span>
	</dl>";

	$form_container->output_row("", "", $actions);

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_stylesheet);

	$form->output_submit_wrapper($buttons);

	if($admin_options['codepress'] != 0)
	{
		echo '<script type="text/javascript">
			var editor = CodeMirror.fromTextArea(document.getElementById("stylesheet"), {
				lineNumbers: true,
				lineWrapping: true,
				viewportMargin: Infinity,
				indentWithTabs: true,
				indentUnit: 4,
				mode: "text/css",
				theme: "mybb"
			});</script>';
	}

	echo '<script type="text/javascript" src="./jscripts/themes.js?ver=1808"></script>';
	echo '<script type="text/javascript" src="./jscripts/theme_properties.js?ver=1821"></script>';
	echo '<script type="text/javascript">
$(function() {
//<![CDATA[
	checkAction(\'add\');
	lang.saving = "'.$lang->saving.'";
});
//]]>
</script>';

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "set_default")
{
	if(!verify_post_check($mybb->get_input('my_post_key')))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	require_once MYBB_ROOT.'inc/functions_themes.php';
	$themelet_hierarchy = get_themelet_hierarchy();
	$mode = $mybb->settings['themelet_dev_mode'] ? 'devdist' : 'current';
	$themes = $themelet_hierarchy[$mode]['themes'];

	// Does the theme not exist?
	if(empty($themes[$mybb->input['codename']])) {
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	$plugins->run_hooks("admin_style_themes_set_default");

	$theme = $themes[$mybb->input['codename']]['properties'];
	$cache->update('default_theme', $theme);

	$plugins->run_hooks("admin_style_themes_set_default_commit");

	// Log admin action
	log_admin_action($mybb->input['codename'], $theme['name']);

	flash_message($lang->success_theme_set_default, 'success');
	admin_redirect("index.php?module=style-themes");
}

if($mybb->input['action'] == "force")
{
	require_once MYBB_ROOT.'inc/functions_themes.php';
	$themelet_hierarchy = get_themelet_hierarchy();
	$mode = 'current';
	$themes = $themelet_hierarchy[$mode]['themes'];

	// Does the theme not exist?
	if (!isset($themes[$mybb->input['codename']])) {
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	$theme = $themes[$mybb->input['codename']]['properties'];

	$plugins->run_hooks("admin_style_themes_force");

	// User clicked no
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=style-themes");
	}

	if($mybb->request_method == "post")
	{
		$updated_users = array(
			"style" => $theme['codename']
		);

		$plugins->run_hooks("admin_style_themes_force_commit");

		$db->update_query("users", $updated_users);

		// The theme has to be accessible to all usergroups in order to force on all users
		if($theme['allowedgroups'] !== "all")
		{
			$theme['allowedgroups'] = 'all';
			write_json_file(
				MYBB_ROOT."inc/themes/{$theme['codename']}/{$mode}/theme.json",
				$theme
			);
		}

		// Log admin action
		log_admin_action($theme['codename'], $theme['name']);

		flash_message($lang->success_theme_forced, 'success');
		admin_redirect("index.php?module=style-themes");
	}
	else
	{
		$page->output_confirm_action("index.php?module=style-themes&amp;action=force&amp;codename={$theme['codename']}", $lang->confirm_theme_forced);
	}
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->themes);

	$plugins->run_hooks("admin_style_themes_start");

	$page->output_nav_tabs($sub_tabs, 'themes');

	$table = new Table;
	$table->construct_header($lang->theme);
	$table->construct_header($lang->num_users, array("class" => "align_center", "width" => 100));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	build_theme_list();

	$table->output($lang->themes);

	$page->output_footer();
}
