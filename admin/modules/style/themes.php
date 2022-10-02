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

$errors = $sub_tabs = [];

$action = $mybb->get_input('action');
$codename = $mybb->get_input('codename');

require_once MYBB_ROOT.'inc/functions_themes.php';
$themelet_hierarchy = get_themelet_hierarchy();
$mode = $mybb->settings['themelet_dev_mode'] ? 'devdist' : 'current';
$themes = $themelet_hierarchy[$mode]['themes'];

// Be extra careful about dodgy codenames or staging/stylesheet/template filenames which
// try to use directory separators and '..' to access private filesystem files.
function is_unsafe_path($path) {
	return $path == '..'
	       || strpos($path, '../') !== false
	       || strpos($path, '..\\') !== false
	       || strpos($path, '/..') !== false
	       || strpos($path, '\\..') !== false;
}
if (!empty($codename) && is_unsafe_path($codename)) {
	flash_message($lang->sprintf($lang->error_path_with_double_dot, $lang->theme_codename), 'error');
	admin_redirect('index.php?module=style-themes');
}
if (!empty($mybb->get_input('new_codename')) && is_unsafe_path($mybb->get_input('new_codename'))) {
	flash_message($lang->sprintf($lang->error_path_with_double_dot, $lang->theme_codename), 'error');
	admin_redirect('index.php?module=style-themes');
}
if (!empty($mybb->input['file']) && is_unsafe_path($mybb->input['file'])) {
	flash_message($lang->sprintf($lang->error_path_with_double_dot, $lang->stylesheet_filename), 'error');
	admin_redirect('index.php?module=style-themes');
}
if (!empty($mybb->input['staged_theme']) && is_unsafe_path($mybb->input['staged_theme'])) {
	flash_message($lang->sprintf($lang->error_path_with_double_dot, $lang->staging_filename), 'error');
	admin_redirect('index.php?module=style-themes');
}
if (!empty($mybb->input['template']) && is_unsafe_path($mybb->input['template'])) {
	flash_message($lang->sprintf($lang->error_path_with_double_dot, $lang->template_path), 'error');
	admin_redirect('index.php?module=style-themes');
}

// These actions rely on a correct codename parameter being supplied which maps to a theme
if (in_array($action, ['xmlhttp_stylesheet', 'stylesheets', 'stylesheet_properties', 'edit_stylesheet', 'delete_stylesheet', 'add_stylesheet', 'set_default', 'force', 'export', 'templates', 'edit_template', 'add_template', 'delete_template', 'edit'])) {
	// Validate the supplied theme codename.
	if (!is_valid_theme_code($codename)) {
		flash_message($lang->sprintf($lang->error_invalid_theme_codename, htmlspecialchars_uni($codename)), 'error');
		admin_redirect('index.php?module=style-themes');
	}
	// Does the theme not exist?
	if(empty($themes[$codename])) {
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect('index.php?module=style-themes');
	}
	// Set a global $theme variable
	$theme = $themes[$codename]['properties'];
}

if ($action == 'xmlhttp_stylesheet' && $mybb->request_method == 'post') {
	// Fetch list of all of the stylesheets for this theme
	list($stylesheets_a, $disporders) = get_theme_stylesheets($codename);

	list($plugin_code, $namespace, $component, $filename) = parse_res_spec1($mybb->get_input('file'));

	// Check that the stylesheet supplied for editing exists
	if (empty($stylesheets_a[$plugin_code][$mybb->get_input('file')])) {
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect('index.php?module=style-themes');
	}

	// Fetch the stylesheet from the filesystem
	if ($plugin_code) {
		$specifier = "~p~{$codename}:{$plugin_code}:{$component}:{$filename}";
	} else {
		$specifier = "~t~{$codename}:{$namespace}:{$component}:{$filename}";
	}
	$stylesheet = resolve_themelet_resource($specifier, /*$use_themelet_cache = */false, /*$return_type = */RTR_RETURN_RESOURCE, /*$min_override = */true, /*$scss_override = */true);


	$css_array = css_to_array($stylesheet);
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
	echo $form->generate_hidden_field("codename", $codename, array('id' => "codename"))."\n";
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

if (in_array($action, ['add', 'import', 'browse', ''])) {
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
} else if (in_array($action, ['edit', 'stylesheets', 'add_stylesheet', 'templates', 'export', 'duplicate', 'edit_template', 'add_template'])) {
	if (!empty($themelet_hierarchy[$mode]['themes'][$codename]['properties']['name'])) {
		$name = $themelet_hierarchy[$mode]['themes'][$codename]['properties']['name'];
	} else	$name = '[Unknown]';

	$page->add_breadcrumb_item(htmlspecialchars_uni($name).get_theme_name_apx($codename), 'index.php?module=style-themes&amp;action=edit&amp;codename='.urlencode($codename));

	if ($action != 'edit_template') {
		$sub_tabs['edit'] = array(
			'title' => $lang->edit_theme_properties,
			'link' => 'index.php?module=style-themes&amp;action=edit&amp;codename='.urlencode($codename),
			'description' => $lang->edit_theme_desc
		);

		$sub_tabs['edit_stylesheets'] = array(
			'title' => $lang->edit_stylesheets,
			'link' => 'index.php?module=style-themes&amp;action=stylesheets&amp;codename='.urlencode($codename),
			'description' => $lang->edit_stylesheets_desc
		);

		if (is_mutable_theme($codename, $mode)) {
			$sub_tabs['add_stylesheet'] = array(
				'title' => $lang->add_stylesheet,
				'link' => 'index.php?module=style-themes&amp;action=add_stylesheet&amp;codename='.urlencode($codename),
				'description' => $lang->add_stylesheet_desc
			);
		}

		$sub_tabs['edit_templates'] = array(
			'title' => $lang->edit_templates,
			'link' => 'index.php?module=style-themes&amp;action=templates&amp;codename='.urlencode($codename),
			'description' => $lang->edit_templates_desc
		);

		$sub_tabs['add_template'] = array(
			'title' => $lang->add_template,
			'link' => 'index.php?module=style-themes&amp;action=add_template&amp;codename='.urlencode($codename),
			'description' => $lang->add_template_desc
		);

		$sub_tabs['export_theme'] = array(
			'title' => $lang->export_theme,
			'link' => 'index.php?module=style-themes&amp;action=export&amp;codename='.urlencode($codename),
			'description' => $lang->export_theme_desc
		);

		$sub_tabs['duplicate_theme'] = array(
			'title' => $lang->duplicate_theme,
			'link' => 'index.php?module=style-themes&amp;action=duplicate&amp;codename='.urlencode($codename),
			'description' => $lang->duplicate_theme_desc
		);
	}
}

$plugins->run_hooks('admin_style_themes_begin');

if ($action == 'browse') {
	$plugins->run_hooks('admin_style_themes_browse');

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

if ($action == 'import') {
	$plugins->run_hooks("admin_style_themes_import");

	if ($mybb->request_method == "post") {
		$type = $mybb->get_input('import', MyBB::INPUT_INT);
		if (!($type == 0 && !empty($_FILES['local_file'])
		      ||
		      $type == 1 && !empty($mybb->input['url'])
		      ||
		      $type == 2 && !empty($mybb->input['staged_theme'])
		     )
		) {
			$errors[] = $lang->error_missing_import_source;
		} else {
			$codename = $staged_path = $zip_filepath = $tmp_dir = '';
			switch ($type) {
				case 0:
					if ($_FILES['local_file']['error'] == 4) {
						// UPLOAD_ERR_NO_FILE
						$errors[] = $lang->error_uploadfailed_php4;
					} else {
						// Find out if there was an error with the uploaded file
						if ($_FILES['local_file']['error'] != 0) {
							$errors[] = $lang->error_uploadfailed.$lang->error_uploadfailed_detail;
							switch($_FILES['local_file']['error']) {
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

						if (!$errors) {
							// Was the temporary file found?
							if (!is_uploaded_file($_FILES['local_file']['tmp_name'])) {
								$errors[] = $lang->error_uploadfailed_lost;
							}
							$zip_filepath = $_FILES['local_file']['tmp_name'];
						}
					}
					break;
				case 1:
					// Get the contents of the remote file
					$contents = fetch_remote_file($mybb->input['url']);
					if (!$contents) {
						$errors[] = $lang->sprintf($lang->error_missing_or_empty_remote_file, htmlspecialchars_uni($mybb->input['url']));
					} else {
						// Save them to a file in a temporary directory
						$tmp_dir = create_temp_dir();
						$zip_filepath = $tmp_dir.'/theme.zip';
						if (!$tmp_dir || !file_put_contents($zip_filepath, $contents)) {
							$errors[] = $lang->error_failed_to_save_remote_file;
						}
					}
					break;
				case 2:
					$codename = $mybb->input['staged_theme'];
					if (!is_valid_theme_code($codename)) {
						$errors[] = $lang->sprintf($lang->error_invalid_staged_theme_tld, htmlspecialchars_uni($codename));
					} else {
						$staged_path = MYBB_ROOT.'staging/themes/'.$codename;
					}
					break;
			}

			if (!$errors) {
				if ($zip_filepath) {
					if ($tmp_dir) {
						$extract_dir = $tmp_dir.'/extracted';
					} else	$tmp_dir = $extract_dir = create_temp_dir();
					if (!$extract_dir) {
						$errors[] = $lang->error_failed_to_create_tmpdir;
					}

					if (!$errors) {
						// Unzip the file and move its main directory into
						// the staging directory, first checking that no
						// directory with the same name already exists in
						// staging.
						if (!class_exists('ZipArchive')) {
							$errors[] = $lang->error_no_ziparchive_for_theme;
						} else {
							$za = new ZipArchive;
							$res = $za->open($zip_filepath);
							if ($res !== true) {
								$errors[] = $lang->sprintf($lang->error_theme_unzip_open_failed, $res);
							} else {
								if (!$za->extractTo($extract_dir)) {
									$errors[] = $lang->error_theme_unzip_failed;
								} else {
									$top_lvl_files = glob("$extract_dir/*");
									if (count($top_lvl_files) != 1) {
										rmdir_recursive($tmpdir);
										$errors[] = $lang->error_theme_unzip_multi_or_none_root;
									} else {
										$codename = basename($top_lvl_files[0]);
										if (!is_valid_theme_code($codename)) {
											$errors[] = $lang->sprintf($lang->error_invalid_theme_archive_tld, htmlspecialchars_uni($codename));
										} else {
											$staged_path = MYBB_ROOT.'staging/themes/'.$codename;
											if (file_exists($staged_path)) {
												$errors[] = $lang->sprintf($lang->error_theme_already_staged, htmlspecialchars_uni($codename));
											// I (Laird) have no idea why, but a simple `rename()` fails on my dev setup,
											// presumably due to some obscure permission restrictions on the `/tmp` directory,
											// even though they superficially look fine to me, so we are forced to call this
											// more elaborate custom function.
											} else if (!cp_or_mv_recursively("$extract_dir/$codename", $staged_path, true)) {
												$errors[] = $lang->sprintf($lang->error_theme_move_fail, htmlspecialchars_uni("$extract_dir/$codename"), htmlspecialchars_uni($staged_path));
											}
										}
									}
								}
								$za->close();
							}
						}
					}

					@unlink($zip_filepath);
				}
			}

			if (!$errors) {
				$infofile2 = '';
				$infofile = "$staged_path/theme.json";
				if (!($themeinfo = read_json_file($infofile))) {
					$errors[] = $lang->sprintf($lang->error_bad_staged_json_file, htmlspecialchars_uni($infofile));
				} else if (empty($themeinfo['codename'])) {
					$errors[] = $lang->sprintf($lang->error_missing_theme_file_property, 'codename', htmlspecialchars_uni($infofile));
				} else if ($themeinfo['codename'] != $codename) {
					$errors[] = $lang->sprintf($lang->error_codename_mismatch, htmlspecialchars_uni($themeinfo['codename']), htmlspecialchars_uni($codename));
				} else if (file_exists(MYBB_ROOT."inc/themes/{$codename}/current")) {
					$infofile2 = MYBB_ROOT."inc/themes/{$codename}/current/theme.json";
					if (!($themeinfo2 = read_json_file($infofile2))) {
						$errors[] = $lang->sprintf($lang->error_bad_json_file, htmlspecialchars_uni($infofile2));
					} else if (empty($themeinfo2['version'])) {
						$errors[] = $lang->sprintf($lang->error_missing_theme_file_property, 'version', htmlspecialchars_uni($infofile2));
					} else if (empty($themeinfo['version'])) {
						$errors[] = $lang->sprintf($lang->error_missing_theme_file_property, 'version', htmlspecialchars_uni($infofile));
					} else if ($themeinfo['version'] == $themeinfo2['version']) {
						$errors[] = $lang->sprintf($lang->error_identical_theme_version, htmlspecialchars_uni($codename), htmlspecialchars_uni($themeinfo['version']));
					}
				}
			}
			if(!$errors) {
				if (!$mybb->get_input('version_compat', MyBB::INPUT_INT) && !empty($themeinfo['compatibility']) && !is_compatible($themeinfo['compatibility'])) {
					$errors[] = $lang->sprintf($lang->error_theme_incompatible_with_mybb_version, htmlspecialchars_uni($themeinfo['compatibility']), htmlspecialchars_uni($mybb->version));
				} else {
					if (!$errors) {
						$found = false;
						$name = !empty($mybb->input['name']) ? $mybb->input['name'] : $themeinfo['name'];
						foreach (['devdist', 'current'] as $mode1) {
							foreach ($themelet_hierarchy[$mode1]['themes'] as $theme) {
								if ($theme['properties']['name'] == $name && $theme['properties']['codename'] != $themeinfo['codename']) {
									$found = true;
									break;
								}
							}
						}
						if ($found) {
							$errors[] = $lang->sprintf($lang->error_theme_already_exists, htmlspecialchars_uni($themeinfo['name']));
						} else if (!empty($mybb->input['name'])) {
							$themeinfo['name'] = $mybb->input['name'];
							if (!write_json_file($infofile, $themeinfo)) {
								$errors[] = $lang->error_failed_to_save_theme;
							}
						}
					}
				}

				if (!$errors) {
					// If $infofile2 is non-empty, then we are upgrading or
					// downgrading, so, try to archive the existing theme.
					// TODO: consider whether/how to warn on downgrade.
					if ($infofile2) {
						if (!archive_themelet($codename, /*$is_plugin_themelet = */false, $err_msg)) {
							$errors[] = $lang->error_theme_archival_failed;
						}
					}
				}
				if (!$errors) {
					$installed_path = MYBB_ROOT."inc/themes/{$codename}/current";
					if (!rename($staged_path, $installed_path)) {
						$errors[] = $lang->error_theme_rename_failed;
					} else if ($infofile2) {
						$cache->update_themelet_dirs();
						// TODO: generate and store information about new
						// conflicts with board themes that descend from this one.
					}
				}

				if (!$errors) {
					$plugins->run_hooks("admin_style_themes_import_commit");

					// Log admin action
					log_admin_action($codename);

					$msg = $infofile2
					         ? (
						    $themeinfo2['version'] < $themeinfo['version']
						      ? $lang->sprintf($lang->success_upgraded_theme, htmlspecialchars_uni($themeinfo['name']), htmlspecialchars_uni($themeinfo2['version']), htmlspecialchars_uni($themeinfo['version']))
						      : $lang->sprintf($lang->success_downgraded_theme, htmlspecialchars_uni($themeinfo['name']), htmlspecialchars_uni($themeinfo2['version']), htmlspecialchars_uni($themeinfo['version']))
						   )
						 : $lang->sprintf($lang->success_imported_theme, htmlspecialchars_uni($themeinfo['name']));
					flash_message($msg, 'success');
					admin_redirect('index.php?module=style-themes&amp;action=edit&amp;codename='.urlencode($codename));
				}
			}

			rmdir_recursive($tmp_dir);
			if ($errors && $zip_filepath) {
				rmdir_recursive($staged_path);
			}
		}
	}

	$page->add_breadcrumb_item($lang->import_a_theme, "index.php?module=style-themes&amp;action=import");

	$page->output_header("{$lang->themes} - {$lang->import_a_theme}");

	$page->output_nav_tabs($sub_tabs, 'import_theme');

	$import_checked = array_fill(1, 3, '');
	if ($errors) {
		$page->output_inline_error($errors);
		$import_checked[$mybb->get_input('import', MyBB::INPUT_INT) + 1] = "checked=\"checked\"";
	}
	else {
		$import_checked[1] = "checked=\"checked\"";
	}

	$form = new Form("index.php?module=style-themes&amp;action=import", "post", "", 1);

	$staged_themes = get_staged_themes();

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
					<td>'.$form->generate_text_box("url", $mybb->get_input('url')).'</td>
				</tr>
		</table></dd>
';
	if ($staged_themes) {
		$opts = [];
		foreach ($staged_themes as $codename => $props) {
			$opts[$codename] = empty($props['name']) ? '['.$codename.']' : $props['name'];
		}
		$actions .= '		<dt><label style="display: block;"><input type="radio" name="import" value="2" '.$import_checked[3].' class="imports_check" onclick="checkAction(\'import\');" style="vertical-align: middle;" /> '.$lang->staged_theme.'</label></dt>
		<dd style="margin-top: 0; margin-bottom: 0; width: 100%;" id="import_2" class="imports">
		<table cellpadding="4">
				<tr>
					<td>'.$form->generate_select_box('staged_theme', $opts, [$mybb->get_input('staged_theme')]).'</td>
				</tr>
		</table></dd>
';
	}
	$actions .= '	</dl>
	<script type="text/javascript">
	checkAction(\'import\');
	</script>';

	$form_container = new FormContainer($lang->import_a_theme);
	$form_container->output_row($lang->import_from, $lang->import_from_desc, $actions, 'file');
	$form_container->output_row($lang->new_name, $lang->new_name_desc, $form->generate_text_box('name', $mybb->get_input('name'), array('id' => 'name')), 'name');
	$form_container->output_row($lang->advanced_options, "", $form->generate_check_box('version_compat', '1', $lang->ignore_version_compatibility, array('checked' => $mybb->get_input('version_compat'), 'id' => 'version_compat'))."<br /><small>{$lang->ignore_version_compat_desc}</small>");

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->import_theme);

	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

if ($action == 'export') {
	$plugins->run_hooks("admin_style_themes_export");

	if($mybb->request_method == "post")
	{
		if (!class_exists('ZipArchive')) {
			flash_message($lang->error_no_ziparchive_for_theme, 'error');
			admin_redirect('index.php?module=style-themes');
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
		log_admin_action($theme['codename'], $theme['name']);

		$data = file_get_contents($zip_filepath);
		header("Content-disposition: attachment; filename={$theme['codename']}.zip");
		header("Content-type: application/zip");
		header("Content-Length: ".strlen($data));
		header("Pragma: no-cache");
		header("Expires: 0");
		echo $data;
		exit;
	}

	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), 'index.php?module=style-themes&amp;action=edit&amp;codename='.urlencode($codename));

	$page->add_breadcrumb_item($lang->export_theme, "index.php?module=style-themes&amp;action=export");

	$page->output_header("{$lang->themes} - {$lang->export_theme}");

	$page->output_nav_tabs($sub_tabs, 'export_theme');

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=style-themes&amp;action=export", "post");
	echo $form->generate_hidden_field('codename', $theme['codename']);

	$form_container = new FormContainer($lang->export_theme.": ".htmlspecialchars_uni($theme['name']).get_theme_name_apx($codename));
	$form_container->output_row($lang->include_custom_only, $lang->include_custom_only_desc, $form->generate_yes_no_radio('custom_theme', $mybb->get_input('custom_theme')), 'custom_theme');

	if (!empty($themelet_hierarchy['devdist']['themes'][$codename])
	    &&
	    !empty($themelet_hierarchy['current']['themes'][$codename])
	) {
		$form_container->output_row($lang->export_devdist, $lang->export_devdist_desc, $form->generate_yes_no_radio('export_devdist', $mybb->settings['themelet_dev_mode'] ? '1' : '0', 'export_devdist'));
	}

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->export_theme);

	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

if ($action == 'duplicate') {
	// Does the theme not exist?
	$src_dir = MYBB_ROOT."inc/themes/{$codename}";
	if (!is_dir($src_dir)) {
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	$have_current = is_dir("{$src_dir}/current");
	$have_devdist = is_dir("{$src_dir}/devdist");

	// Does the theme have no contents?
	if (!$have_current && !$have_devdist) {
		flash_message($lang->error_theme_has_no_contents, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	// Determine which modes (current/devdist) exist in the source theme
	$modes = [];
	if ($have_current) {
		$modes[] = 'current';
	}
	if ($have_devdist) {
		$modes[] = 'devdist';
	}

	$plugins->run_hooks("admin_style_themes_duplicate");

	if($mybb->request_method == "post")
	{
		if (empty($mybb->input['new_codename'])) {
			$errors[] = $lang->error_missing_theme_codename;
		} else if (!is_valid_theme_code($mybb->input['new_codename'])) {
			$errors[] = $lang->sprintf($lang->error_invalid_theme_codename, htmlspecialchars_uni($mybb->input['new_codename']));
		} else if ($mybb->input['name'] == '') {
			$errors[] = $lang->error_missing_name;
		} else {
			foreach (['devdist', 'current'] as $mode1) {
				foreach ($themelet_hierarchy[$mode1]['themes'] as $theme) {
					if ($theme['properties']['name'] == $mybb->get_input('name')) {
						$errors[] = $lang->sprintf($lang->error_theme_already_exists, htmlspecialchars_uni($mybb->get_input('name')));
					} else if ($theme['properties']['codename'] == $mybb->input['new_codename']) {
						$errors[] = $lang->sprintf($lang->error_theme_codename_exists, htmlspecialchars_uni($mybb->input['new_codename']));
					}
				}
			}
		}

		if (!$errors) {
			$dest_dir = MYBB_ROOT."inc/themes/{$mybb->input['new_codename']}";

			// If we are copying all resolved (including inherited) resources for
			// the applicable modes....
			if (in_array($mybb->input['dup_type'], ['child_resolved', 'sibling_resolved'])) {
				// ...then get the ancestor list of themes for the source theme and copy
				// their directories recursively in reverse order into the
				// destination directory.
				foreach ($modes as $mode1) {
					foreach (array_reverse($themelet_hierarchy[$mode1]['themes'][$codename]['ancestors']) as $anc_code) {
						$source_path = MYBB_ROOT."inc/themes/{$anc_code}/{$mode1}/";
						$dest_path   = "$dest_dir/{$mode1}";
						if (!cp_or_mv_recursively($source_path, $dest_path, false, $err_msg)) {
							flash_message($err_msg, 'error');
							admin_redirect('index.php?module=style-themes');
						}
					}

				}
			// Otherwise, if we are copying only resources in the source theme...
			} else if (in_array($mybb->input['dup_type'], ['child_exact', 'sibling_exact'])) {
				// ...then copy just the source dir for the relevant modes.
				foreach ($modes as $mode1) {
					$source_path = "{$src_dir}/{$mode1}";
					$dest_path   = "{$dest_dir}/{$mode1}";
					if (!cp_or_mv_recursively($source_path, $dest_path, false, $err_msg)) {
						flash_message($err_msg, 'error');
						admin_redirect('index.php?module=style-themes');
					}
				}
			}

			// Then, copy theme.json and resources.json to the dest dirs for the
			// relevant modes, at the same time editing theme.json to update codename,
			// name, and (if necessary) parent codename.
			foreach ($modes as $mode1) {
				foreach (['theme.json', 'resources.json'] as $json_file) {
					if (!is_dir("{$dest_dir}/{$mode1}")) {
						@mkdir("{$dest_dir}/{$mode1}", 0755, true);
					}
					$source_path = "{$src_dir}/{$mode1}/{$json_file}";
					$dest_path   = "{$dest_dir}/{$mode1}/{$json_file}";
					if (!copy($source_path, $dest_path)) {
						flash_message($lang->sprintf($lang->error_cp_failed, htmlspecialchars_uni($source_path), htmlspecialchars_uni($dest_path)), 'error');
						admin_redirect('index.php?module=style-themes');
					} else if ($json_file == 'theme.json') {
						$theme_properties = read_json_file($dest_path, $err_msg, false);
						if (!$theme_properties && $err_msg) {
							flash_message($err_msg, 'error');
							admin_redirect('index.php?module=style-themes');
						} else {
							$theme_properties['codename'] = $mybb->input['new_codename'];
							$theme_properties['name'] = $mybb->input['name'];
							if (in_array($mybb->input['dup_type'], ['child_exact', 'child_full', 'child_resolved'])) {
								$theme_properties['parent'] = $codename;
							}
							if (!write_json_file($dest_path, $theme_properties)) {
								flash_message($lang->error_failed_to_save_theme, 'error');
								admin_redirect('index.php?module=style-themes');
							}
						}
					}
				}
			}

			// Then, if this is a child, update its resource derivation info.
			if (in_array($mybb->input['dup_type'], ['child_full', 'child_exact', 'child_resolved'])) {
				$ignored_files = ['resources.json', 'theme.json', 'LICENCE', 'LICENSE'];
				foreach ($modes as $mode1) {
					$all_resources = [];
					// First, build a list of all resources in ancestors.
					foreach (array_reverse($themelet_hierarchy[$mode1]['themes'][$codename]['ancestors']) as $anc_code) {
						$base = MYBB_ROOT."inc/themes/$anc_code/$mode1/";
						if (!file_exists($base)) {
							continue;
						}
						$baselen = strlen($base);
						$rci = new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS);
						foreach (
							$iterator = new \RecursiveIteratorIterator(
								$rci,
								\RecursiveIteratorIterator::SELF_FIRST
							) as $item
						) {
							if ($item->isFile() && !in_array($item->getFilename(), $ignored_files)) {
								$resource = substr($item->getPathname(), $baselen);
								if (!in_array($resource, $all_resources)) {
									$all_resources[] = $resource;
								}
							}
						}

					}
					// Now, build a list of resources (with inheritance broken) in the theme being duplicated,
					// at the same time adding them to the above list if they are not in it already.
					$theme_resources = [];
					$base = MYBB_ROOT."inc/themes/{$mybb->input['new_codename']}/$mode1/";
					$baselen = strlen($base);
					if (file_exists($base)) {
						$rci = new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS);
						foreach (
							$iterator = new \RecursiveIteratorIterator(
								$rci,
								\RecursiveIteratorIterator::SELF_FIRST
							) as $item
						) {
							if ($item->isFile() && !in_array($item->getFilename(), $ignored_files)) {
								$resource = substr($item->getPathname(), $baselen);
								if (!in_array($resource, $theme_resources)) {
									$theme_resources[] = $resource;
								}
								if (!in_array($resource, $all_resources)) {
									$all_resources[] = $resource;
								}
							}
						}
					}
					// Finally, update the derivation list and then save the resource file.
					$resources = read_json_file($base.'resources.json', $err_msg, false);
					if (!$resources) {
						$errors[] = $err_msg;
					} else {
						if (empty($resources['derivations'])) {
							$resources['derivations'] = [];
						}
						$derivations = &$resources['derivations'];
						$prefix = 'board.';
						if (substr($codename, 0, strlen($prefix)) == $prefix) {
							$vers = 'board';
						} else {
							$vers = $themelet_hierarchy[$mode1]['themes'][$codename]['properties']['version'];
						}
						foreach ($all_resources as $resource) {
							if (empty($derivations[$resource])) {
								$derivations[$resource] = [];
							}
							array_unshift($derivations[$resource], [$codename, in_array($resource, $theme_resources) ? $vers : 'inherit', /*is_plugin*/false]);
						}
						unset($derivations);
						write_json_file($base.'resources.json', $resources);
					}
				}
			}

			// Finally, if creating a new current/devdist directory, then copy the now existing one (in the destination) to the other.
			$to = $from = '';
			if (!$have_devdist && !empty($mybb->input['create_devdist'])) {
				$from = 'current';
				$to = 'devdist';
			} else if (!$have_current && !empty($mybb->input['create_current'])) {
				$from = 'devdist';
				$to = 'current';
			}
			if ($to) {
				$base = MYBB_ROOT."inc/themes/{$mybb->input['new_codename']}/";
				if (!cp_or_mv_recursively("{$base}{$from}", "{$base}{$to}", /*$del_source = */false, $error)) {
					$errors[] = $error;
				}
			}

			if (!$errors) {
				$plugins->run_hooks("admin_style_themes_duplicate_commit");

				$cache->update_themelet_dirs();

				// Log admin action
				log_admin_action($mybb->input['new_codename'], $codename);

				flash_message($lang->success_duplicated_theme, 'success');
				admin_redirect('index.php?module=style-themes&action=edit&amp;codename='.urlencode($mybb->input['new_codename']));
			}
		}
	}

	$page->add_breadcrumb_item($lang->duplicate_theme, 'index.php?module=style-themes&amp;action=duplicate&amp;codename='.urlencode($mybb->get_input('codename')));

	$page->output_header("{$lang->themes} - {$lang->duplicate_theme}");

	$page->output_nav_tabs($sub_tabs, 'duplicate_theme');

	if ($errors) {
		$page->output_inline_error($errors);
	}

	// Suggest a name for the duplicate theme if none has yet been provided
	if (empty($mybb->input['name'])) {
		$name_val_base = $themelet_hierarchy[$modes[0]]['themes'][$codename]['properties']['name'];
		for ($i = 1; $i < 20; $i++) {
			$name_val = $name_val_base.' (copy '.$i.')';
			$found = false;
			foreach (['devdist', 'current'] as $mode1) {
				foreach ($themelet_hierarchy[$mode1]['themes'] as $theme) {
					if ($name_val == $theme['properties']['name']) {
						$found = true;
						break;
					}
				}
			}
			if (!$found) break;
		}
	} else {
		$name_val = $mybb->get_input('name');
	}

	// Suggest a codename for the duplicate theme if none has yet been provided
	if (empty($mybb->input['new_codename'])) {
		$new_codename_val_base = $codename;
		$a = explode('.', $new_codename_val_base, 2);
		if (count($a) == 2) {
			$new_codename_val_base = $a[1];
		}
		$new_codename_val_base = 'board.'.$new_codename_val_base;
		$new_codename_val = $new_codename_val_base;
		for ($i = 1; $i <= 26; $i++) {
			$found = false;
			foreach (['devdist', 'current'] as $mode1) {
				foreach ($themelet_hierarchy[$mode1]['themes'] as $theme) {
					if ($new_codename_val == $theme['properties']['codename']) {
						$found = true;
						break;
					}
				}
			}
			if (!$found) break;
			$new_codename_val = $new_codename_val_base.'_'.chr(96+$i);
		}
	} else {
		$new_codename_val = $mybb->get_input('new_codename');
	}

	$form = new Form('index.php?module=style-themes&amp;action=duplicate&amp;codename='.urlencode($codename), 'post');

	$form_container = new FormContainer($lang->sprintf($lang->duplicate_theme_named, htmlspecialchars_uni($themelet_hierarchy[$modes[0]]['themes'][$codename]['properties']['name']).get_theme_name_apx($codename)));
	$form_container->output_row($lang->new_name, $lang->new_name_duplicate_desc, $form->generate_text_box('name', $name_val, array('id' => 'name')), 'name');
	$form_container->output_row($lang->new_codename, $lang->new_codename_duplicate_desc.' '.$lang->original_vs_board_themes_desc, $form->generate_text_box('new_codename', $new_codename_val, array('id' => 'new_codename')), 'new_codename');
	$dup_checked = ['child_full' => '', 'child_exact' => '', 'child_resolved' => '', 'sibling_exact' => '', 'sibling_resolved' => ''];
	if (!empty($mybb->input['dup_type'])) {
		$dup_checked[$mybb->input['dup_type']] = 'checked="checked"';
	} else {
		$dup_checked['child_full'] = 'checked="checked"';
	}
	$form_container->output_row($lang->dup_type, $lang->dup_type_desc, '<dl style="margin-top: 0; margin-bottom: 0; width: 40%;">
		<dt><label style="display: block;"><input type="radio" name="dup_type" value="child_full" '.$dup_checked['child_full'].' style="vertical-align: middle;" /> '.$lang->dup_type_child_full.'</label></dt><br />
		<dt><label style="display: block;"><input type="radio" name="dup_type" value="child_exact" '.$dup_checked['child_exact'].' style="vertical-align: middle;" /> '.$lang->dup_type_child_exact.'</label></dt><br />
		<dt><label style="display: block;"><input type="radio" name="dup_type" value="child_resolved" '.$dup_checked['child_resolved'].' style="vertical-align: middle;" /> '.$lang->dup_type_child_resolved.'</label></dt><br />
		<dt><label style="display: block;"><input type="radio" name="dup_type" value="sibling_exact" '.$dup_checked['sibling_exact'].' style="vertical-align: middle;" /> '.$lang->dup_type_sibling_exact.'</label></dt><br />
		<dt><label style="display: block;"><input type="radio" name="dup_type" value="sibling_resolved" '.$dup_checked['sibling_resolved'].' style="vertical-align: middle;" /> '.$lang->dup_type_sibling_resolved.'</label></dt>
	</dl>');
	if (!$have_devdist) {
		$form_container->output_row('', '', $form->generate_check_box('create_devdist', '1', $lang->create_devdist, array('checked' => $mybb->get_input('create_devdist'), 'id' => 'create_devdist'))."<br /><small>{$lang->create_devdist_desc}</small>");
	} else if (!$have_current) {
		$form_container->output_row('', '', $form->generate_check_box('create_current', '1', $lang->create_current, array('checked' => $mybb->get_input('create_current'), 'id' => 'create_current'))."<br /><small>{$lang->create_current_desc}</small>");
	}

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->duplicate_theme);

	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

if ($action == 'add') {
	$plugins->run_hooks("admin_style_themes_add");

	$theme_opts = build_fs_theme_select(/*$name = */'', /*$selected = */'', /*$effective_uid = */0, /*$usergroup_override = */true, /*$footer = */false, /*$count_override = */false, /*$return_opts_only = */true, /*$ignoredtheme = */'', /*$skip_core_orig = */false);

	$page->add_breadcrumb_item($lang->create_new_theme, "index.php?module=style-themes&amp;action=add");

	$page->output_header("{$lang->themes} - {$lang->create_new_theme}");

	$page->output_nav_tabs($sub_tabs, 'create_theme');

	$table = new Table;

	$table->construct_header($lang->based_on_theme);

	foreach ($theme_opts as $codename => $name) {
		// Count the initial indenting dashes so that we can replace them with non-breaking spaces,
		// because they were designed for the dropdown and look ugly in this context.
		$i = 0;
		while ($i < strlen($name) && $name[$i] == '-') {
			$i++;
		}
		if ($i > 0) {
			$indent = str_repeat('&nbsp;', $i * 3);
			$name = substr($name, $i);
		} else {
			$indent = '';
		}
		$table->construct_cell('<a href="index.php?module=style-themes&amp;action=duplicate&amp;codename='.urlencode($codename).'">'.$indent.htmlspecialchars_uni($name).'</a><br /><br />');
		$table->construct_row();
	}

	$table->output($lang->create_a_theme);

	$page->output_footer();
}

if ($action == 'delete') {
	// Does the theme not exist?
	$src_dir = MYBB_ROOT."inc/themes/{$codename}";
	if (!is_dir($src_dir)) {
		flash_message($lang->error_invalid_theme, 'error');
		admin_redirect("index.php?module=style-themes");
	}

	// Are we trying to delete a core theme?
	if (strpos($codename, 'core.') === 0) {
		flash_message($lang->error_undeletable_core_theme, 'error');
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
		$props = read_json_file("{$src_dir}/current/theme.json");
		if (!$props) {
			$props = read_json_file("{$src_dir}/devdist/theme.json");
		}
		$theme_name = !empty($props['name']) ? $props['name'] : '';

		rmdir_recursive($src_dir);

		$plugins->run_hooks("admin_style_themes_delete_commit");

		// Log admin action
		log_admin_action($codename, $theme_name);

		flash_message($lang->success_theme_deleted, 'success');
		admin_redirect("index.php?module=style-themes");
	}
	else
	{
		$page->output_confirm_action('index.php?module=style-themes&amp;action=delete&amp;codename='.urlencode($theme['codename']), $lang->confirm_theme_deletion);
	}
}

if ($action == 'edit') {
	$plugins->run_hooks("admin_style_themes_edit");

	if ($mybb->request_method == 'post' && !$mybb->get_input('do')) {
		if (!is_mutable_theme($codename, $mode)) {
			$errors[] = $lang->error_immutable_theme;
		} else {
			$new_properties = array(
				'name' => $mybb->get_input('name'),
				'description' => $mybb->get_input('description'),
				'editortheme' => $mybb->get_input('editortheme'),
				'logo' => $mybb->get_input('logo'),
				'tablespace' => $mybb->get_input('tablespace', MyBB::INPUT_INT),
				'borderwidth' => $mybb->get_input('borderwidth', MyBB::INPUT_INT),
				'color' => $mybb->get_input('color')
			);

			if (is_unsafe_path($new_properties['logo'])) {
				$errors[] = $lang->error_logo_with_directory_separator;
			}

			if($new_properties['color'] == 'none')
			{
				unset($new_properties['color']);
			}

			if (isset($mybb->input['colors'])) {
				$colors = explode("\n", $mybb->get_input('colors'));

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
						$errors[] = $lang->sprintf($lang->error_invalid_color, htmlspecialchars_uni($color));
					}
				}
			}

			$allowedgroups = array();
			foreach($mybb->get_input('allowedgroups', MyBB::INPUT_ARRAY) as $gid)
			{
				if($gid == "all")
				{
					$allowedgroups = "all";
					break;
				}
				$gid = (int)$gid;
				$allowedgroups[$gid] = $gid;
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
					if ($a['properties']['name'] == $new_properties['name'] && $codename != $t_code) {
						$unused_name = false;
						break;
					}
				}
				if (!$unused_name) {
					$errors[] = $lang->sprintf($lang->error_theme_already_exists, htmlspecialchars_uni($new_properties['name']));
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
					MYBB_ROOT."inc/themes/{$codename}/{$mode}/theme.json",
					$new_properties
				)) {
					$errors[] = $lang->error_failed_to_save_theme;
					$theme = $new_properties;
				} else {
					if (!$cache->read('default_theme')) {
						$cache->update_default_theme();
					}
					$def_theme = $cache->read('default_theme');

					if ($codename == $def_theme['codename'])
					{
						$cache->update_default_theme($new_properties);
					}

					// Log admin action
					log_admin_action($theme['codename'], $theme['name']);

					flash_message($lang->success_theme_properties_updated, 'success');
					admin_redirect('index.php?module=style-themes&action=edit&amp;codename='.urlencode($theme['codename']));
				}
			} else {
				$theme = $new_properties;
			}
		}
	}

	$page->add_breadcrumb_item($lang->properties, 'index.php?module=style-themes&amp;action=edit&amp;codename='.urlencode($codename));

	$page->output_header("{$lang->themes} - {$lang->stylesheets}");

	$page->output_nav_tabs($sub_tabs, 'edit');

	if (!is_mutable_theme($codename, $mode)) {
		$page->output_alert($lang->warning_immutable_theme);
	}

	// Theme Properties table
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=style-themes&amp;action=edit", "post", "edit");
	echo $form->generate_hidden_field("codename", $theme['codename']);
	$form_container = new FormContainer($lang->sprintf($lang->edit_theme_properties_named, htmlspecialchars_uni($name).get_theme_name_apx($theme['codename'])));
	$form_container->output_row($lang->name." <em>*</em>", $lang->name_desc_edit, $form->generate_text_box('name', $theme['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->description." <em>*</em>", $lang->description_desc_edit, $form->generate_text_box('description', $theme['description'], array('id' => 'description')), 'description');

	$options = build_fs_theme_select($name, /*$selected = */'', /*$usergroup_override = */true, /*$effective_uid = */0, /*$footer = */false, /*$count_override = */false, /*$return_opts_only = */true, /*$ignoredtheme = */$codename, /*$skip_core_orig = */false);
	if (substr($codename, 0, 5) != 'core.') {
		$form_container->output_row($lang->parent_theme." <em>*</em>", $lang->parent_theme_desc, $form->generate_select_box('parent', $options, $theme['parent'], array('id' => 'parent')), 'parent');
	}

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

	if (is_mutable_theme($codename, $mode)) {
		$buttons = array();
		$buttons[] = $form->generate_submit_button($lang->save_theme_properties);
		$form->output_submit_wrapper($buttons);
	}

	$form->end();

	$page->output_footer();
}

if ($action == 'stylesheets') {
	$plugins->run_hooks('admin_stylesheets_do_edit');

	list($stylesheets_a, $disporders) = get_theme_stylesheets($codename);

	// Save any stylesheet orders
	if ($mybb->request_method == 'post' && $mybb->input['do'] == 'save_orders'
	    &&
	    !empty($mybb->input['disporder']) && !empty($mybb->input['disporder_specifiers'])) {
		if (!is_mutable_theme($codename, $mode)) {
			$errors[] = $lang->error_immutable_theme;
		} else {
			$disporders_in = $mybb->get_input('disporder', MyBB::INPUT_ARRAY);
			$disporder_specifiers = $mybb->get_input('disporder_specifiers', MyBB::INPUT_ARRAY);
			$disporders = [];
			foreach ($disporders_in as $key => $ordernum) {
				$disporders[$ordernum] = parse_res_spec1($disporder_specifiers[$key]);
			}
			ksort($disporders);
			$mode = $mybb->settings['themelet_dev_mode'] ? 'devdist' : 'current';
			$resource_file = MYBB_ROOT."inc/themes/{$codename}/{$mode}/resources.json";
			$resources = read_json_file($resource_file, $err_msg, false);
			if ($resources) {
				$new_ss_list = [];
				foreach ($disporders as $order_num => $arr) {
					$specifier = !empty($arr[0]/*plugin_code*/)
						? "@ext.{$arr[0]}/{$arr[2]}/{$arr[3]}"
						: "@{$arr[1]}/{$arr[2]}/{$arr[3]}";
					if ($resources['stylesheets'][$specifier]) {
						$new_ss_list[$specifier] = $resources['stylesheets'][$specifier];
						unset($resources['stylesheets'][$specifier]);
					} else	$new_ss_list[$specifier] = [];
				}
				$new_ss_list = array_merge($new_ss_list, $resources['stylesheets']);
				$resources['stylesheets'] = $new_ss_list;
				if (!write_json_file($resource_file, $resources)) {
					flash_message($lang->error_stylesheet_order_update, 'error');
					admin_redirect('index.php?module=style-themes&amp;action=edit&amp;codename='.urlencode($theme['codename']));
				} else {
					flash_message($lang->success_stylesheet_order_updated, 'success');
					admin_redirect('index.php?module=style-themes&action=edit&amp;codename='.urlencode($theme['codename']));
				}
			} else {
				flash_message($err_msg, 'error');
				admin_redirect('index.php?module=style-themes&amp;action=edit&amp;codename='.urlencode($theme['codename']));
			}
		}
	}

	$page->add_breadcrumb_item($lang->stylesheets, 'index.php?module=style-themes&amp;action=stylesheets&amp;codename='.urlencode($codename));

	$page->output_header("{$lang->themes} - {$lang->stylesheets}");

	$page->output_nav_tabs($sub_tabs, 'edit_stylesheets');

	if (!is_mutable_theme($codename, $mode)) {
		$page->output_alert($lang->warning_immutable_theme);
	}

	$table = new Table;
	$table->construct_header($lang->stylesheets);
	$table->construct_header($lang->display_order, array("class" => "align_center", "width" => 50));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	// Display Order form
	$form = new Form("index.php?module=style-themes&amp;action=stylesheets", "post", "edit");
	echo $form->generate_hidden_field("codename", $theme['codename']);
	echo $form->generate_hidden_field("do", 'save_orders');

	$ordered_stylesheets = [];
	ksort($disporders);
	foreach ($disporders as $order_num => $arr) {
		if (empty($arr[0]/*plugin_code*/)) {
			if (empty($arr[1])) {
				$namespace = 'frontend';
			} else {
				$namespace = $arr[1];
			}
			$key = "@$namespace/{$arr[2]}/{$arr[3]}";
		} else {
			$key = "@ext.{$arr[0]}/{$arr[2]}/{$arr[3]}";
		}
		$ordered_stylesheets[$key] = $stylesheets_a[$arr[0]][$key];
	}

	$order_num = 1;
	$i = 0;
	foreach ($ordered_stylesheets as $ss_name => $ss_arr) {
		list($plugin_code, $namespace, $component, $filename) = parse_res_spec1($ss_name);

		$inherited = '';

		$sep = ' ';
		$inheritance = $ss_arr[-1];
		unset($ss_arr[-1]);
		$inheritance_chain = (array)$inheritance['inheritance_chain'];
		array_shift($inheritance_chain); // Remove the themelet itself
		if ($inheritance['orig_plugin']) {
			$inherited .= ' <small>'.$lang->sprintf($lang->in_plugin_namespace, htmlspecialchars_uni(get_plugin_name($inheritance['orig_plugin']))).'</small>';
		} else if ($namespace) {
			$inherited .= ' <small>'.$lang->sprintf($lang->in_namespace, htmlspecialchars_uni($namespace)).'</small>';
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

				// Multiple colors can be selected, so colours, and only colours,
				// are arrayed in the 'script' property of a stylesheet.
				if (is_array($name)) {
					foreach ($name as $color) {
						if (array_key_exists($color, $theme['colors'])) {
							$colors[] = $theme['colors'][$color];
						}
					}
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

		$popup->add_item($lang->edit_style, 'index.php?module=style-themes&amp;action=edit_stylesheet&amp;file='.urlencode($ss_name).'&amp;codename='.urlencode($theme['codename']));
		$popup->add_item($lang->properties, 'index.php?module=style-themes&amp;action=stylesheet_properties&amp;file='.urlencode($ss_name).'&amp;codename='.urlencode($theme['codename']));

		if (empty($inheritance_chain) && is_mutable_theme($codename, $mode)) {
			$popup->add_item($lang->delete_revert, 'index.php?module=style-themes&amp;action=delete_stylesheet&amp;file='.urlencode($ss_name).'&amp;codename='.urlencode($theme['codename']).'&amp;my_post_key='.urlencode($mybb->post_code), "return AdminCP.deleteConfirmation(this, '{$lang->confirm_stylesheet_deletion}')");
		}

		$table->construct_cell('<strong><a href="index.php?module=style-themes&amp;action=edit_stylesheet&amp;file=
		'.urlencode($ss_name).'&amp;codename='.urlencode($theme['codename']).'">'.htmlspecialchars_uni($filename)."</a></strong>{$inherited}<br />{$attached_to}");
		$disporder_hidden = $form->generate_hidden_field('disporder_specifiers['.$i.']', $ss_name);
		$table->construct_cell($disporder_hidden.' '.$form->generate_numeric_field('disporder['.$i.']', $order_num, array('style' => 'width: 80%; text-align: center;', 'min' => 0)), array("class" => "align_center"));
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
		$i++;
		$order_num++;
	}

	$table->output("{$lang->stylesheets_in} ".htmlspecialchars_uni($theme['name']).get_theme_name_apx($codename));

	if (is_mutable_theme($codename, $mode)) {
		$buttons = array($form->generate_submit_button($lang->save_stylesheet_order));
		$form->output_submit_wrapper($buttons);
	}
	$form->end();

	echo '<br />';

	// Theme Properties table
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$page->output_footer();
}

if ($action == 'stylesheet_properties') {
	$plugins->run_hooks('admin_style_themes_stylesheet_properties');

	// Fetch list of all of the stylesheets for this theme
	list($stylesheets_a, $disporders) = get_theme_stylesheets($codename);

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
		$prefix = '@ext.';
		$plen   = strlen($prefix);
		if (substr($mybb->input['file'], 0, $plen) === $prefix) {
			list($plugin_code, $ss_filename) = explode('/', substr($mybb->input['file'], $plen), 2);
			if (isset($stylesheets_a[$plugin_code][$mybb->input['file']])) {
				$stylesheet_props = $stylesheets_a[$plugin_code][$mybb->input['file']];
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
		if (!is_mutable_theme($codename, $mode)) {
			$errors[] = $lang->error_immutable_theme;
		}
		if(!$errors)
		{
			$attached = get_ss_attach_from_input($errors);

			// Update the stylesheet

			$resource_file = MYBB_ROOT."inc/themes/{$codename}/{$mode}/resources.json";
			$resources = read_json_file($resource_file, $err_msg, false);
			if ($err_msg) {
				flash_message($err_msg, 'error');
				admin_redirect('index.php?module=style-themes&amp;action=stylesheet_properties&amp;codename='.urlencode($codename).'&file='.urlencode($mybb->input['file']));

			}

			if ($plugin_code) {
				// We are overriding a plugin's stylesheet.
				$ss_key = "@ext.{$plugin_code}/{$ss_filename}";
			} else {
				$ss_key = $ss_filename;
			}
			$resources['stylesheets'][$ss_key] = $attached;

			if (!write_json_file($resource_file, $resources)) {
				flash_message($lang->error_failed_to_save_stylesheet_props, 'error');
				admin_redirect('index.php?module=style-themes&amp;action=stylesheet_properties&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']));

			}

			$plugins->run_hooks("admin_style_themes_stylesheet_properties_commit");

			// Log admin action
			log_admin_action($mybb->input['file'], $theme['codename'], $theme['name']);

			flash_message($lang->success_stylesheet_properties_updated, 'success');
			admin_redirect('index.php?module=style-themes&action=stylesheet_properties&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']));
		}
	}

	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), 'index.php?module=style-themes&amp;action=edit&amp;codename='.urlencode($codename));
	$page->add_breadcrumb_item($lang->stylesheets, 'index.php?module=style-themes&amp;action=stylesheets&amp;codename='.urlencode($codename));
	$page->add_breadcrumb_item(htmlspecialchars_uni($mybb->input['file']), 'index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']));
	$page->add_breadcrumb_item($lang->properties, 'index.php?module=style-themes&amp;action=stylesheet_properties&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']));

	$page->output_header("{$lang->themes} - {$lang->stylesheet_properties}");

	if (!is_mutable_theme($codename, $mode)) {
		$page->output_alert($lang->warning_immutable_theme);
	}

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

	if(!empty($theme['colors']))
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

	echo $form->generate_hidden_field("file", $mybb->input['file'])."<br />\n";
	echo $form->generate_hidden_field("codename", $theme['codename'])."<br />\n";

	$form_container = new FormContainer("{$lang->edit_stylesheet_properties_for} ".htmlspecialchars_uni($mybb->input['file']));

	$form_container->output_row($lang->attached_to, $lang->attached_to_desc, $actions);

	$form_container->end();

	if (is_mutable_theme($codename, $mode)) {
		$buttons[] = $form->generate_submit_button($lang->save_stylesheet_properties);

		$form->output_submit_wrapper($buttons);
	}

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

// Perform the initialisation common to both "simple" and "advanced" stylesheet editing modes
if ($action == 'edit_stylesheet') {
	$simple_mode = (!isset($mybb->input['mode']) || $mybb->input['mode'] == 'simple');

	if ($simple_mode) {
		$plugins->run_hooks('admin_style_themes_edit_stylesheet_simple');
	} else {
		$plugins->run_hooks('admin_style_themes_edit_stylesheet_advanced');
	}


	// Fetch list of all of the stylesheets for this theme
	list($stylesheets_a, $disporders) = get_theme_stylesheets($codename);

	if (is_unsafe_path($mybb->get_input('file'))) {
		flash_message($lang->sprintf($lang->error_path_with_double_dot, $lang->stylesheet_filename), 'error');
		admin_redirect('index.php?module=style-themes&amp;action=stylesheets&amp;codename='.urlencode($codename));
	}

	list($plugin_code, $namespace, $component, $filename) = parse_res_spec1($mybb->get_input('file'));

	// Check that the stylesheet supplied for editing exists
	if (empty($stylesheets_a[$plugin_code][$mybb->get_input('file')])) {
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect('index.php?module=style-themes&amp;action=stylesheets&amp;codename='.urlencode($codename));
	}

	// Check the stylesheet's inheritance status and type (CSS vs SCSS)
	if ($plugin_code) {
		$specifier = "~p~{$codename}:{$plugin_code}:{$component}:{$filename}";
	} else {
		$specifier = "~t~{$codename}:{$namespace}:{$component}:{$filename}";
	}
	$inheritance = resolve_themelet_resource($specifier, /*$use_themelet_cache = */false, /*$return_type = */RTR_RETURN_INHERITANCE, /*$min_override = */true, /*$scss_override = */true, $is_scss);
	$is_inherited = (count($inheritance['inheritance_chain']) > 1);

	// Pull out the contents of the stylesheet from the appropriate file
	$stylesheet = resolve_themelet_resource($specifier, /*$use_themelet_cache = */false, /*$return_type = */RTR_RETURN_RESOURCE, /*$min_override = */true, /*$scss_override = */true);

	$page->add_breadcrumb_item(htmlspecialchars_uni($theme['name']), 'index.php?module=style-themes&amp;action=edit&amp;codename='.urlencode($codename));
	$page->add_breadcrumb_item($lang->stylesheets, 'index.php?module=style-themes&amp;action=stylesheets&amp;codename='.urlencode($codename));
}

// Shows the page where you can actually edit a particular selector or the whole stylesheet
if ($action == 'edit_stylesheet' && (!isset($mybb->input['mode']) || $mybb->input['mode'] == 'simple')) {
	if ($is_scss) {
		// Editing of SCSS in "simple" mode is not (yet?) supported: redirect to the advanced editor.
		admin_redirect('index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']).'&amp;mode=advanced');
	}

	if($mybb->request_method == "post")
	{
		$err_msg = false;
		if (!is_mutable_theme($codename, $mode)) {
			$err_msg = $lang->error_immutable_theme;
		} else {
			// TODO: Abstract this code given that it is duplicated for saving in "advanced" mode below
			$base_dir = MYBB_ROOT."inc/themes/{$codename}/$mode";
			if ($inheritance['orig_plugin']) {
				$save_dir = "$base_dir/ext.{$inheritance['orig_plugin']}/styles/";
			} else {
				$save_dir = "$base_dir/{$namespace}/{$component}/";
			}
			if (!file_exists($save_dir) && !mkdir($save_dir, 0755, true)) {
				$err_msg = $lang->sprintf($lang->error_failed_to_mkdir, htmlspecialchars_uni($save_dir));
			} else {
				$filename = preg_replace('(\\.[^\\.]*$)', '', $filename).'.'.($is_scss ? 'scss' : 'css');
				$save_path = $save_dir.$filename;

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

				$new_stylesheet = insert_into_css($css_to_insert, $mybb->input['selector'], $stylesheet);

				if (file_put_contents($save_path, $new_stylesheet) === false) {
					$err_msg = $lang->sprintf($lang->error_failed_write_stylesheet, htmlspecialchars_uni($save_path));
				}

				$plugins->run_hooks("admin_style_themes_edit_stylesheet_simple_commit");
			}
		}

		// Log admin action
		log_admin_action(htmlspecialchars_uni($theme['name']), $mybb->input['file']);

		if(!$mybb->input['ajax'])
		{
			if ($err_msg) {
				flash_message($err_msg, 'error');
				admin_redirect('index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']).'&mode=simple');
			} else {
				flash_message($lang->success_stylesheet_updated, 'success');
				if($mybb->input['save_close'])
				{
					admin_redirect('index.php?module=style-themes&amp;action=edit&amp;codename='.urlencode($codename));
				}
				else
				{
					admin_redirect('index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']));
				}
			}
		}
		else
		{
			echo $err_msg ? "<error>$err_msg</error>" : "1";
			exit;
		}
	}

	$css_array = css_to_array($stylesheet);
	$selector_list = get_selectors_as_options($css_array, $mybb->get_input('selector'));

	// Do we not have any selectors? Send em to the full edit page
	if(!$selector_list)
	{
		flash_message($lang->error_cannot_parse, 'error');
		admin_redirect('index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']).'&amp;mode=advanced');
		exit;
	}

	$page->extra_header .= "
	<script type=\"text/javascript\">
	var my_post_key = '".$mybb->post_code."';
	</script>";

	$page->add_breadcrumb_item(htmlspecialchars_uni($mybb->input['file']), 'index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']).'&amp;mode=simple');

	$page->output_header("{$lang->themes} - {$lang->edit_stylesheets}");

	if (!is_mutable_theme($codename, $mode)) {
		$page->output_alert($lang->warning_immutable_theme);
	} else if ($is_inherited) {
		// Show inherited warning
		// TODO: when immutability of original/core themes is implemented, adapt the logic here to suit,
		//       and improve the alerts to indicate the actual name of the theme/plugin from which we're
		//       inheriting, plus whether or not it is a plugin.
// 		if($stylesheet['tid'] == 1)
// 		{
// 			$page->output_alert($lang->sprintf($lang->stylesheet_inherited_default, $stylesheet_parent), "ajax_alert");
// 		}
// 		else
// 		{
			$page->output_alert($lang->sprintf($lang->stylesheet_inherited, htmlspecialchars_uni($inheritance['inheritance_chain'][1]['codename'])), "ajax_alert");
// 		}
	}

	$sub_tabs['edit_stylesheet'] = array(
		'title' => $lang->edit_stylesheet_simple_mode,
		'link' => 'index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file'])."&amp;mode=simple",
		'description' => $lang->edit_stylesheet_simple_mode_desc
	);

	$sub_tabs['edit_stylesheet_advanced'] = array(
		'title' => $lang->edit_stylesheet_advanced_mode,
		'link' => 'index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']).'&amp;mode=advanced',
	);

	$page->output_nav_tabs($sub_tabs, 'edit_stylesheet');

	// Output the selection box
	$form = new Form("index.php", "get", "selector_form");
	echo $form->generate_hidden_field("module", "style/themes")."\n";
	echo $form->generate_hidden_field("action", "edit_stylesheet")."\n";
	echo $form->generate_hidden_field("codename", $codename)."\n";
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
	echo $form->generate_hidden_field("codename", $codename, array('id' => "codename"))."\n";
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

	if (is_mutable_theme($codename, $mode)) {
		$buttons[] = $form->generate_reset_button($lang->reset);
		$buttons[] = $form->generate_submit_button($lang->save_changes, array('id' => 'save', 'name' => 'save'));
		$buttons[] = $form->generate_submit_button($lang->save_changes_and_close, array('id' => 'save_close', 'name' => 'save_close'));

		$form->output_submit_wrapper($buttons);
	}

	echo '<script type="text/javascript" src="./jscripts/themes.js?ver=1808"></script>';
	echo '<script type="text/javascript">

$(function() {
//<![CDATA[
	ThemeSelector.init("./index.php?module=style-themes&action=xmlhttp_stylesheet", "./index.php?module=style-themes&action=edit_stylesheet", $("#selector"), $("#stylesheet"), "'.addslashes($mybb->input['file']).'", $("#selector_form"), "'.addslashes($codename).'");
	lang.saving = "'.$lang->saving.'";
});
//]]>
</script>';

	$form->end();

	$page->output_footer();
}

if ($action == 'edit_stylesheet' && $mybb->input['mode'] == 'advanced') {
	// Note: initialisation common between this advanced editing mode and the simple editing
	// mode was performed above.

	// Possible TODO: Implement support on this same page for editing of any SCSS module
	// dependency files in the stylesheet's modules directory. At the moment, there is no
	// support for this in the UI, and filesystem editing is necessary.

	if($mybb->request_method == "post")
	{
		// Now we have the new stylesheet, save it

		if (!is_mutable_theme($codename, $mode)) {
			$err_msg = $lang->error_immutable_theme;
		} else {
			// TODO: Abstract this code given that it is duplicated for saving in "simple" mode above
			$err_msg = false;
			$base_dir = MYBB_ROOT."inc/themes/{$codename}/$mode";
			if ($inheritance['orig_plugin']) {
				$save_dir = "$base_dir/ext.{$inheritance['orig_plugin']}/styles/";
			} else {
				$save_dir = "$base_dir/{$namespace}/{$component}/";
			}
			if (!file_exists($save_dir) && !mkdir($save_dir, 0755, true)) {
				$err_msg = $lang->sprintf($lang->error_failed_to_mkdir, htmlspecialchars_uni($save_dir));
			} else {
				$filename = preg_replace('(\\.[^\\.]*$)', '', $filename).'.'.($mybb->input['stylesheet_type'] == 'scss' ? 'scss' : 'css');
				$save_path = $save_dir.$filename;
			}

			if (file_put_contents($save_path, $mybb->input['stylesheet']) === false) {
				$err_msg = $lang->sprintf($lang->error_failed_write_stylesheet, htmlspecialchars_uni($save_path));
			}

			// If we're changing from CSS to SCSS or the reverse, then...
			if ($is_scss != ($mybb->input['stylesheet_type'] == 'scss')) {
				// ...delete the other file. This is especially important for a change to
				// SCSS, because any CSS file will override the new SCSS file.
				$filename = preg_replace('(\\.[^\\.]*$)', '', $filename).($is_scss ? '.scss' : '.css');
				$rm_path = $save_dir.$filename;
				@unlink($rm_path);
			}

			if ($err_msg) {
				flash_message($err_msg, 'error');
				admin_redirect('index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']).'&amp;mode=advanced');
			}

			$plugins->run_hooks("admin_style_themes_edit_stylesheet_advanced_commit");

			// Log admin action
			log_admin_action($theme['name'], $mybb->input['file']);

			flash_message($lang->success_stylesheet_updated, 'success');

			if(!$mybb->get_input('save_close'))
			{
				admin_redirect('index.php?module=style-themes&amp;action=edit_stylesheet&amp;file='.urlencode($mybb->input['file']).'&amp;codename='.urlencode($codename).'&amp;mode=advanced');
			}
			else
			{
				admin_redirect('index.php?module=style-themes&amp;action=edit&amp;codename='.urlencode($theme['codename']));
			}
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

	$page->add_breadcrumb_item(htmlspecialchars_uni($mybb->input['file']), 'index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']).'&amp;mode=advanced');

	$page->output_header("{$lang->themes} - {$lang->edit_stylesheet_advanced_mode}");

	if (!is_mutable_theme($codename, $mode)) {
		$page->output_alert($lang->warning_immutable_theme);
	} else if ($is_inherited) {
		// Show inherited warning
		// TODO: when immutability of original/core themes is implemented, adapt the logic here to suit,
		//       and improve the alerts to indicate the actual name of the theme/plugin from which we're
		//       inheriting, plus whether or not it is a plugin.
// 		if($stylesheet['tid'] == 1)
// 		{
// 			$page->output_alert($lang->sprintf($lang->stylesheet_inherited_default, $stylesheet_parent), "ajax_alert");
// 		}
// 		else
// 		{
			$page->output_alert($lang->sprintf($lang->stylesheet_inherited, htmlspecialchars_uni($inheritance['inheritance_chain'][1]['codename'])), "ajax_alert");
// 		}
	}

	$sub_tabs['edit_stylesheet'] = array(
		'title' => $lang->edit_stylesheet_simple_mode,
		'link' => 'index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']).'&amp;mode=simple'
	);

	$sub_tabs['edit_stylesheet_advanced'] = array(
		'title' => $lang->edit_stylesheet_advanced_mode,
		'link' => 'index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($mybb->input['file']).'&amp;mode=advanced',
		'description' => $lang->sprintf($lang->edit_stylesheet_advanced_mode_desc, $is_scss ? 'SCSS' : 'CSS')
	);

	$page->output_nav_tabs($sub_tabs, 'edit_stylesheet_advanced');

	$form = new Form("index.php?module=style-themes&amp;action=edit_stylesheet&amp;mode=advanced", "post", "edit_stylesheet");
	echo $form->generate_hidden_field("codename", $codename)."\n";
	echo $form->generate_hidden_field("file", htmlspecialchars_uni($mybb->input['file']))."\n";

	$table = new Table;
	$table->construct_cell($form->generate_text_area('stylesheet', $stylesheet, array('id' => 'stylesheet', 'style' => 'width: 99%;', 'class' => '', 'rows' => '30')));
	$table->construct_row();
	$table->construct_cell('<strong>'.$lang->stylesheet_type.'</strong>'.$form->generate_select_box('stylesheet_type', ['css' => 'CSS', 'scss' => 'SCSS'], $is_scss ? 'scss' : 'css'), ['style' => 'text-align: center;']);
	$table->construct_row();
	$table->output($lang->full_stylesheet_for.' '.htmlspecialchars_uni($filename), 1, 'tfixed');

	if (is_mutable_theme($codename, $mode)) {
		$buttons[] = $form->generate_submit_button($lang->save_changes, array('id' => 'save', 'name' => 'save'));
		$buttons[] = $form->generate_submit_button($lang->save_changes_and_close, array('id' => 'save_close', 'name' => 'save_close'));

		$form->output_submit_wrapper($buttons);
	}

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

if ($action == 'delete_stylesheet') {
	$plugins->run_hooks("admin_style_themes_delete_stylesheet");

	if (!is_mutable_theme($codename, $mode)) {
		flash_message($lang->error_immutable_theme, 'error');
		admin_redirect('index.php?module=style-themes&amp;action=stylesheets&amp;codename='.urlencode($codename));
	}

	// Fetch list of all of the stylesheets for this theme
	list($stylesheets_a, $disporders) = get_theme_stylesheets($codename);

	list($plugin_code, $namespace, $component, $filename) = parse_res_spec1($mybb->get_input('file'));

	// If the stylesheet supplied for deletion does not exist, then error out
	if (empty($stylesheets_a[$plugin_code][$mybb->get_input('file')])) {
		flash_message($lang->error_invalid_stylesheet, 'error');
		admin_redirect('index.php?module=style-themes');
	}

	// Check the stylesheet's inheritance status
	if ($plugin_code) {
		$specifier = "~p~{$codename}:{$plugin_code}:{$component}:{$filename}";
	} else {
		$specifier = "~t~{$codename}:{$namespace}:{$component}:{$filename}";
	}
	$inheritance = resolve_themelet_resource($specifier, /*$use_themelet_cache = */false, /*$return_type = */RTR_RETURN_INHERITANCE, /*$min_override = */true, /*$scss_override = */true, $is_scss);

	// If this stylesheet is being inherited, and thus there is nothing to delete, then error out
	if (count($inheritance['inheritance_chain']) > 1) {
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
		if (!verify_post_check($mybb->get_input('my_post_key'))) {
			flash_message($lang->invalid_post_verify_key2, 'error');
			admin_redirect('index.php?module=style-themes&amp;action=stylesheets&amp;codename='.urlencode($codename));
		}

		// Delete the stylesheet file(s) from this theme, also deleting any potential SCSS
		// modules dependency directory.

		$base_abs_path = preg_replace('(\\.[^\\.]*$)', '', $filename);
		$base_abs_path = MYBB_ROOT."inc/themes/{$codename}/{$mode}/".($plugin_code ? 'ext.'.$plugin_code : $namespace)."/{$component}/{$base_abs_path}";

		if ($is_scss) {
			if (is_dir($base_abs_path)) {
				rmdir_recursive($base_abs_path);
			}

		}
		foreach (['.scss', '.css'] as $ext) {
			$filepath = "{$base_abs_path}{$ext}";
			if (file_exists($filepath)) {
				unlink($filepath);
			}
		}

		$err_msg = false;
		$resource_file = MYBB_ROOT."inc/themes/{$codename}/{$mode}/resources.json";
		$resources = read_json_file($resource_file, $err_msg, false);
		if ($resources) {
			unset($resources['stylesheets'][$mybb->input['file']]);
			if (!write_json_file($resource_file, $resources)) {
				$err_msg = $lang->error_failed_to_save_stylesheet_props;
			}
		}

		if ($err_msg) {
			flash_message($err_msg, 'error');
			admin_redirect("index.php?module=style-themes");
		}

		$plugins->run_hooks("admin_style_themes_delete_stylesheet_commit");

		// Log admin action
		log_admin_action($stylesheet['name'], $theme['codename'], $theme['name']);

		flash_message($lang->success_stylesheet_deleted, 'success');
		admin_redirect('index.php?module=style-themes&amp;action=edit&amp;codename='.urlencode($theme['codename']));
	}
	else
	{
		$page->output_confirm_action('index.php?module=style-themes&amp;action=force&amp;codename='.urlencode($theme['codename']), $lang->confirm_stylesheet_deletion);
	}
}

if ($action == 'add_stylesheet') {
	// Fetch list of all of the stylesheets for this theme
	list($stylesheets_a, $disporders) = get_theme_stylesheets($codename);

	$sheetnames = array();
	foreach ($stylesheets_a as $plugin_code => $ss_arr) {
		foreach ($ss_arr as $specifier => $script_actions) {
			list($pcode, $namespace, $component, $filename) = parse_res_spec1($specifier);

			$sheetnames[$specifier] = $filename;
			if ($plugin_code) {
				$sheetnames[$specifier] .= ' '.$lang->sprintf($lang->in_plugin_namespace, htmlspecialchars_uni(get_plugin_name($plugin_code)));
			} else if ($namespace) {
				$sheetnames[$specifier] .= ' '.$lang->sprintf($lang->in_namespace, htmlspecialchars_uni($namespace)).'</small>';
			}
		}
	}

	$plugins->run_hooks("admin_style_themes_add_stylesheet");

	if($mybb->request_method == "post")
	{
		if (!is_mutable_theme($codename, $mode)) {
			$errors[] = $lang->error_immutable_theme;
		} else {
			if (!verify_post_check($mybb->get_input('my_post_key'))) {
				flash_message($lang->invalid_post_verify_key2, 'error');
				admin_redirect('index.php?module=style-themes&amp;action=stylesheets&amp;codename='.urlencode($codename));
			}

			// Set default namespace if empty
			if (empty(trim($mybb->input['namespace']))) {
				$mybb->input['namespace'] = 'frontend';
			}
			// Validate namespace
			if (!is_valid_namespace($mybb->input['namespace'])) {
				$errors[] = $lang->sprintf($lang->error_invalid_namespace, htmlspecialchars_uni($mybb->input['namespace']));
			}

			// Validate name
			if (empty($mybb->input['name'])) {
				$errors[] = $lang->error_missing_stylesheet_name;
			} else if (!is_valid_resource_path($mybb->input['name'])) {
				$errors[] = $lang->sprintf($lang->error_invalid_stylesheet_name, htmlspecialchars_uni($mybb->input['name']));
			}
			if (get_extension($mybb->input['name']) != 'css')
			{
				// Does not end with '.css'
				$errors[] = $lang->sprintf($lang->error_missing_stylesheet_extension, htmlspecialchars_uni(rtrim(rtrim($mybb->input['name']), '.')));
			}

			$new_spec = '@'.$mybb->input['namespace'].'/styles/'.$mybb->input['name'];

			// Does a stylesheet with this name exist already?
			foreach ($stylesheets_a as $plugin_code => $ss_arr) {
				if (empty($plugin_code) && isset($ss_arr[$new_spec])) {
					$errors[] = $lang->error_stylesheet_already_exists;
					break;
				}
			}

			if(!$errors)
			{
				$dest_base = MYBB_ROOT."inc/themes/{$codename}/{$mode}/{$mybb->input['namespace']}/styles/";
				$is_scss = false;
				if($mybb->input['add_type'] == 1)
				{
					if (!in_array($mybb->input['import'], array_keys($sheetnames))) {
						$errors[] = $lang->sprintf($lang->error_stylesheet_not_found, htmlspecialchars_uni($mybb->input['import']));
					} else {
						list($plugin_code, $namespace, $component, $filename) = parse_res_spec1($mybb->input['import']);
						if ($plugin_code) {
							$specifier = "~p~{$codename}:{$plugin_code}:{$component}:{$filename}";
						} else {
							$specifier = "~t~{$codename}:{$namespace}:{$component}:{$mybb->input['import']}";
						}
						$stylesheet = resolve_themelet_resource($specifier, /*$use_themelet_cache = */false, /*$return_type = */RTR_RETURN_RESOURCE, /*$min_override = */true, /*$scss_override = */true, $is_scss);

						if ($is_scss) {
							// For a new SCSS stylesheet based on an existing
							// SCSS stylesheet, copy across any SCSS modules
							// subdirectory if it exists (it is named the same
							// as the SCSS file, without the .scss extension).
							$inheritance = resolve_themelet_resource($specifier, /*$use_themelet_cache = */false, /*$return_type = */RTR_RETURN_INHERITANCE, /*$min_override = */true, /*$scss_override = */true);
							$arr = array_pop($inheritance['inheritance_chain']);
							if ($arr['is_plugin']) {
								$src_base = MYBB_ROOT."inc/plugins/{$arr['codename']}/interface/{$mode}/ext/styles/";
							} else {
								$src_base = MYBB_ROOT."inc/themes/{$arr['codename']}/{$mode}/{$namespace}/styles/";
							}
							$src_deps_dir_name = preg_replace('(\\.[^\\.]*$)', '', $mybb->input['import']);
							$src_deps_path_abs = $src_base.$src_deps_dir_name;
							if (is_dir($src_deps_path_abs)) {
								$dest_deps_dir_name = preg_replace('(\\.[^\\.]*$)', '', $mybb->input['name']);
								$dest_deps_path_abs = $dest_base.$dest_deps_dir_name;
								if (!cp_or_mv_recursively($src_deps_path_abs, $dest_deps_path_abs, /*$del_source = */false, $error)) {
									$errors[] = $error;
								} else {
									$stylesheet = preg_replace('(@import\\s+["\']'.preg_quote($src_deps_dir_name).'/)', '@import "'.$dest_deps_dir_name.'/', $stylesheet);
								}
							}
						}
					}
				}
				else
				{
					// Custom stylesheet
					$stylesheet = $mybb->input['stylesheet'];

					if ($mybb->input['filename_ext'] == 'scss') {
						$is_scss = true;
					}
				}

				if (!$errors) {
					$filename = preg_replace('(\\.(s)?css$)', '', $mybb->input['name']).($is_scss ? '.scss' : '.css');
					$finalpath = $dest_base.$filename;
					if (!is_dir(dirname($finalpath))) {
						mkdir(dirname($finalpath), 0755, true);
					}
					if (file_put_contents($finalpath, $stylesheet) === false) {
						$errors[] = $lang->sprintf($lang->error_failed_write_stylesheet, htmlspecialchars_uni($finalpath));
					}
				}

				if (!$errors) {
					$attached = get_ss_attach_from_input($errors);

					$resource_file = MYBB_ROOT."inc/themes/{$codename}/{$mode}/resources.json";
					$resources = read_json_file($resource_file, $err_msg, false);
					if ($resources) {
						$resources['stylesheets'][$new_spec] = $attached;
						if (!write_json_file($resource_file, $resources)) {
							$errors[] = $lang->error_failed_to_save_stylesheet_props;
						}
					} else {
						$errors[] = $err_msg;
					}

					if (!$errors) {
						$plugins->run_hooks("admin_style_themes_add_stylesheet_commit");

						// Log admin action
						log_admin_action($mybb->input['import'], $theme['codename'], $theme['name']);

						flash_message($lang->success_stylesheet_added, 'success');
						admin_redirect('index.php?module=style-themes&amp;action=edit_stylesheet&amp;codename='.urlencode($codename).'&amp;file='.urlencode($new_spec));
					}
				}
			}
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

	$page->add_breadcrumb_item($lang->stylesheets, 'index.php?module=style-themes&amp;action=stylesheets&amp;codename='.urlencode($codename));
	$page->add_breadcrumb_item($lang->add_stylesheet);

	$page->output_header("{$lang->themes} - {$lang->add_stylesheet}");

	$page->output_nav_tabs($sub_tabs, 'add_stylesheet');

	if (!is_mutable_theme($codename, $mode)) {
		$page->output_alert($lang->warning_immutable_theme);
	}

	$stylesheet = $add_checked = array();

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
		$add_checked[1] = "";
		$add_checked[2] = "";
	}

	$global_checked[1] = "checked=\"checked\"";
	$global_checked[2] = "";
	$global_checked[3] = "";

	$form = new Form("index.php?module=style-themes&amp;action=add_stylesheet", "post", "add_stylesheet");

	echo $form->generate_hidden_field('codename', $codename)."\n";

	$specific_files = "<div id=\"attach_1\" class=\"attachs\">";
	$count = 0;
	$check_actions = "";
	$mybb->input['attach'] = $mybb->get_input('attach', MyBB::INPUT_INT);
	$stylesheet['colors'] = array();

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
		if(is_array($theme['colors']))
		{
			// We might have colors here...
			foreach($mybb->input['color'] as $color)
			{
				// Verify this is a color for this theme
				if(array_key_exists($color, $theme['colors']))
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

	if(isset($theme['colors']) && is_array($theme['colors']))
	{
		$specific_colors = "<br /><div id=\"attach_2\" class=\"attachs\">";
		$specific_colors_option = '<dt><label style="display: block;"><input type="radio" name="attach" value="2" '.$global_checked[3].' class="attachs_check" onclick="checkAction(\'attach\');" style="vertical-align: middle;" /> '.$lang->colors_specific_color.'</label></dt>';

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

	$form_container = new FormContainer($lang->add_stylesheet_to.' '.htmlspecialchars_uni($theme['name']), 'tfixed');

	$form_container->output_row($lang->namespace, $lang->namespace_desc, $form->generate_text_box('namespace', $mybb->get_input('namespace'), array('id' => 'namespace', 'style' => 'width: 200px;')), 'namespace');

	$form_container->output_row($lang->file_name, $lang->file_name_desc, $form->generate_text_box('name', $mybb->get_input('name'), array('id' => 'name', 'style' => 'width: 200px;')), 'name');

	$form_container->output_row($lang->attached_to, $lang->attached_to_desc, $actions);

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
		<span id=\"add_2\" class=\"adds\"><label>Type:</label> ".$form->generate_select_box('filename_ext', ['css' => 'CSS', 'scss' => 'SCSS'])."<br />".$form->generate_text_area('stylesheet', $mybb->get_input('stylesheet'), array('id' => 'stylesheet', 'style' => 'width: 99%;', 'class' => '', 'rows' => '30'))."</span>
	</dl>";

	$form_container->output_row("", "", $actions);

	$form_container->end();

	if (is_mutable_theme($codename, $mode)) {
		$buttons[] = $form->generate_submit_button($lang->save_stylesheet);

		$form->output_submit_wrapper($buttons);
	}

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

if ($action == 'set_default') {
	if (!is_mutable_theme($codename, $mode)) {
		flash_message($lang->error_cannot_set_to_default, 'error');
		admin_redirect('index.php?module=style-themes');
	}

	if (!verify_post_check($mybb->get_input('my_post_key'))) {
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect('index.php?module=style-themes');
	}

	$plugins->run_hooks("admin_style_themes_set_default");

	$cache->update('default_theme', $theme);

	$plugins->run_hooks("admin_style_themes_set_default_commit");

	// Log admin action
	log_admin_action($codename, $theme['name']);

	flash_message($lang->success_theme_set_default, 'success');
	admin_redirect("index.php?module=style-themes");
}

if ($action == 'force') {
	$mode = 'current';

	if (!is_mutable_theme($codename, $mode)) {
		flash_message($lang->error_cannot_force_theme, 'error');
		admin_redirect('index.php?module=style-themes');
	}

	if (!verify_post_check($mybb->get_input('my_post_key'))) {
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect('index.php?module=style-themes');
	}

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
		$page->output_confirm_action('index.php?module=style-themes&amp;action=force&amp;codename='.urlencode($theme['codename']), $lang->confirm_theme_forced);
	}
}

if ($action == 'templates') {
	$plugins->run_hooks('admin_style_templates');

	$page->add_breadcrumb_item($lang->templates, 'index.php?module=style-themes&amp;action=templates&amp;codename='.urlencode($codename));

	$page->output_header($lang->theme_templates);

	$page->output_nav_tabs($sub_tabs, 'edit_templates');

	if (!is_mutable_theme($codename, $mode)) {
		$page->output_alert($lang->warning_immutable_theme);
	}

	$table = new Table;
	$table->construct_header($lang->templates);
	$table->construct_header($lang->actions_ucf, array('class' => 'align_center'));

	$namespaces = [];
	$plugins_a = $themelet_hierarchy[$mode]['plugins'];

	foreach (array_merge(array_reverse($themes[$codename]['ancestors']), [$codename]) as $cname) {
		$tpl_root = MYBB_ROOT."inc/themes/{$cname}/{$mode}/";
		$dh = opendir($tpl_root);
		if ($dh) {
			while ($dir = readdir($dh)) {
				if (!in_array($dir, ['.', '..']) && is_dir("{$tpl_root}$dir")) {
					if (substr($dir, 0, 4) == 'ext.') {
						$pcode = substr($dir, 4);
						if (!in_array($pcode, $plugins_a)) {
							$plugins_a[] = $pcode;
						}
					} else if (!in_array($dir, $namespaces)) {
						$namespaces[] = $dir;
					}
				}
			}
			closedir($dh);
		}
	}

	sort($namespaces);
	$plugins_a = array_unique($plugins_a);
	sort($plugins_a);

	function get_tpls_r($dir, &$tpls_a = []) {
		if (empty($tpls_a['entries'])) $tpls_a['entries'] = [];
		$dh = opendir($dir);
		if ($dh) {
			while ($nm = readdir($dh)) {
				if (!in_array($nm, ['.', '..'])) {
					$path = "{$dir}/{$nm}";
					if (is_dir($path)) {
						$idx = -1;
						foreach ($tpls_a['entries'] as $i => $entry) {
							if (!empty($entry['dirname']) && $entry['dirname'] == $nm) {
								$idx = $i;
								break;
							}
						}
						if ($idx < 0) {
							$tpls_a['entries'][] = ['dirname' => $nm, 'entries' => []];
							$idx = end(array_keys($tpls_a['entries']));
						}
						get_tpls_r($path, $tpls_a['entries'][$idx]);
					} else if (strtolower(substr($nm, -5)) == '.twig') {
						if (!in_array($nm, $tpls_a['entries'])) {
							$tpls_a['entries'][] = $nm;
						}
					}
				}
			}
			closedir($dh);
		}
	}

	$tpls_a = [];
	foreach ($namespaces as $namespace) {
		if (empty($tpls_a[$namespace])) {
			$tpls_a[$namespace] = [];
		}
		foreach (array_merge(array_reverse($themes[$codename]['ancestors']), [$codename]) as $cname) {
			$dir = MYBB_ROOT."inc/themes/{$cname}/{$mode}/{$namespace}/templates";
			get_tpls_r($dir, $tpls_a[$namespace]);
		}
	}

	foreach ($plugins_a as $pcode) {
		$key = 'ext.'.$pcode;
		if (empty($tpls_a[$key])) {
			$tpls_a[$key] = [];
		}
		$dir = MYBB_ROOT."inc/plugins/{$pcode}/interface/{$mode}/ext/templates";
		get_tpls_r($dir, $tpls_a[$key]);

		// This handles the situation in which the plugin isn't currently present in the filesystem
		// yet the theme with codename $codename is overriding one of its templates anyway.
		foreach (array_merge(array_reverse($themes[$codename]['ancestors']), [$codename]) as $cname) {
			$dir = MYBB_ROOT."inc/themes/{$cname}/{$mode}/{$key}/templates";
			get_tpls_r($dir, $tpls_a[$key]);
		}
	}

	function output_tpls_r($codename, $mode, $namespace, $tpls_a, $table, $path = '', $indent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;') {
		global $mybb, $lang;

		sort($tpls_a);
		foreach ($tpls_a as $entry) {
			$path_sl = $path ? "{$path}/" : $path;
			if (is_array($entry)) {
				$table->construct_cell($indent.htmlspecialchars_uni($entry['dirname']));
				$table->construct_cell('<a href="index.php?module=style-themes&amp;action=add_template&amp;codename='.urlencode($codename).'&amp;namespace='.urlencode($namespace).'&amp;template_path='.urlencode("{$path_sl}{$entry['dirname']}")."\">{$lang->add_template_here}</a>", array('class' => 'align_center'));
				$table->construct_row();
				output_tpls_r($codename, $mode, $namespace, $entry['entries'], $table, "{$path_sl}{$entry['dirname']}", "{$indent}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;");
			} else {
				$path_to_test = MYBB_ROOT."inc/themes/{$codename}/{$mode}/{$namespace}/templates/{$path_sl}{$entry}";
				$is_inherited = !file_exists($path_to_test);
				if ($is_inherited) {
					$class = 'inherited_res';
					$key = 'inherited_lc_sq';
					$rev_del_html = '';
				} else {
					$class  = 'have_own_copy_res';
					$key = 'have_own_copy_lc_sq';
					$rev_del_html = ' | <a href="index.php?module=style-themes&amp;action=delete_template&amp;codename='.urlencode($codename).'&amp;namespace='.urlencode($namespace).'&amp;template_path='.urlencode("{$path_sl}{$entry}").'&amp;my_post_key='.urlencode($mybb->post_code)."\">{$lang->delete_revert}</a>";
				}
				$table->construct_cell("{$indent}<a href=\"index.php?module=style-themes&amp;action=edit_template&amp;codename=".urlencode($codename).'&amp;namespace='.urlencode($namespace)."&amp;template_path=".urlencode("{$path_sl}{$entry}")."\">{$entry}</a> <span class=\"{$class}\">{$lang->$key}</span><br>\n");
				$table->construct_cell("<a href=\"index.php?module=style-themes&amp;action=edit_template&amp;codename=".urlencode($codename).'&amp;namespace='.urlencode($namespace)."&amp;template_path=".urlencode("{$path_sl}{$entry}")."\">{$lang->edit_template}</a>{$rev_del_html}", array('class' => 'align_center'));
				$table->construct_row();
			}
		}
	}

	foreach ($tpls_a as $namespace => $tpls) {
		if (substr($namespace, 0, 4) == 'ext.') {
			$name = substr($namespace, 4);
			$lang_key = 'plugin_lc_sq';
		} else {
			$name = $namespace;
			$lang_key = 'namespace_lc_sq';
		}
		$table->construct_cell("<strong>{$name}</strong> {$lang->$lang_key}<br>\n");
		$table->construct_cell("<a href=\"index.php?module=style-themes&amp;action=add_template&amp;codename=".urlencode($codename)."&amp;namespace=".urlencode($namespace)."\">{$lang->add_template_here}</a>", array('class' => 'align_center'));
		$table->construct_row();
		output_tpls_r($codename, $mode, $namespace, $tpls['entries'], $table);
	}

	$table->output($lang->sprintf($lang->templates_in_theme, htmlspecialchars_uni($theme['name'])).get_theme_name_apx($codename));
	$page->output_footer();
}

if ($action == 'edit_template') {
	// Was a template path not supplied? Then error out
	$template_path = $mybb->get_input('template_path');
	if (!$template_path) {
		flash_message($lang->error_no_template_input, 'error');
		admin_redirect('index.php?module=style-themes&amp;action=templates&amp;codename='.urlencode($codename));
	}
	$template_path_new = $mybb->get_input('template_new');
	if (!$template_path_new) {
		$template_path_new = $template_path;
	}

	// Was a namespace not supplied? Then default to `frontend`.
	$namespace = $mybb->get_input('namespace');
	if (!$namespace) {
		$namespace = 'frontend';
	}
	$namespace_new = $mybb->get_input('namespace_new');
	if (!$namespace_new) {
		$namespace_new = $namespace;
	}

	$template_body = $mybb->get_input('template_body');

	$pcode = substr($namespace_new, 0, 4) == 'ext.' ? substr($namespace_new, 4) : '';

	$plugins->run_hooks('admin_style_themes_edit_template');

	if ($mybb->request_method == 'post') {
		if (!is_mutable_theme($codename, $mode)) {
			flash_message($lang->error_immutable_theme);
			admin_redirect('index.php?module=style-themes&amp;action=templates&amp;codename='.urlencode($codename));
		}

		if (!verify_post_check($mybb->get_input('my_post_key'))) {
			flash_message($lang->invalid_post_verify_key2, 'error');
			admin_redirect('index.php?module=style-themes');
		}

		if (!is_valid_namespace($namespace_new)) {
			$errors[] = $lang->sprintf($lang->error_invalid_namespace, htmlspecialchars_uni($namespace_new));
		}
		if (!is_valid_resource_path($template_path_new)) {
			$errors[] = $lang->sprintf($lang->error_invalid_template_name, htmlspecialchars_uni($template_path_new));
		}
		if (!$errors) {
			$path = MYBB_ROOT."inc/themes/{$codename}/{$mode}/{$namespace_new}/templates/{$template_path_new}";

			$plugins->run_hooks('admin_style_themes_edit_template_commit');

			mkdir(dirname($path), 0755, true);
			if (!file_put_contents($path, $template_body)) {
				$errors[] = $lang->error_failed_write_template;
			} else {
				$plugins->run_hooks('admin_style_themes_edit_template_commit_success');

				log_admin_action($codename, $namespace_new, $template_path_new);

				flash_message($lang->success_template_saved, 'success');

				if ($mybb->get_input('continue')) {
					admin_redirect('index.php?module=style-themes&amp;action=edit_template&amp;action=edit_template&amp;codename='.urlencode($codename).'&amp;namespace='.urlencode($namespace_new).'&amp;template_path='.urlencode($template_path_new));
				} else {
					admin_redirect('index.php?module=style-themes&amp;action=edit_template&amp;action=templates&amp;codename='.urlencode($codename));
				}
			}
		}
	} else {
		if (!array_key_exists('template_body', $mybb->input)) {
			if ($pcode) {
				$specifier = "~p~{$codename}:{$pcode}:templates:{$template_path}";
			} else {
				$specifier = "~t~{$codename}:{$namespace}:templates:{$template_path}";
			}
			$template_body = resolve_themelet_resource($specifier, /*$use_themelet_cache = */true, /*$return_type = */RTR_RETURN_RESOURCE);
		}
	}

	set_headers_for_codepress();

	$page->add_breadcrumb_item($lang->templates, 'index.php?module=style-themes&amp;action=templates&amp;codename='.urlencode($codename));

	$page->add_breadcrumb_item(htmlspecialchars_uni("@{$namespace}/{$template_path}"), 'index.php?module=style-themes&amp;action=templates&amp;codename='.urlencode($codename).'&amp;namespace='.urlencode($namespace).'&amp;template='.urlencode($template_path));

	$page->output_header($lang->sprintf($lang->editing_template, htmlspecialchars_uni("@$namespace/$template_path")));

	$sub_tabs['edit_template'] = array(
		'title' => $lang->edit_template,
		'link' => 'index.php?module=style-themes&amp;action=edit_template&amp;codename='.urlencode($codename).'&amp;namespace='.urlencode($namespace).'&amp;template_path='.urlencode($template_path),
		'description' => $lang->edit_template_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_template');

	if (!is_mutable_theme($codename, $mode)) {
		$page->output_alert($lang->warning_immutable_theme);
	}

	if ($errors) {
		$page->output_inline_error($errors);
	}

	$form = new Form('index.php?module=style-themes&amp;action=edit_template&amp;codename='.urlencode($codename).'&amp;namespace='.urlencode($namespace).'&amp;template_path='.urlencode($template_path), 'post', 'edit_template');
	$form_container = new FormContainer($lang->sprintf($lang->editing_template, htmlspecialchars_uni("@$namespace/$template_path")), 'tfixed');
	$form_container->output_row($lang->template_namespace, $lang->template_namespace_desc, $form->generate_text_box('namespace_new', $namespace_new, array('id' => 'namespace_new')), 'namespace_new');
	$form_container->output_row($lang->template_name, $lang->template_name_desc, $form->generate_text_box('template_new', $template_path_new, array('id' => 'template_new')), 'template_new');
	$form_container->output_row('', '', $form->generate_text_area('template_body', $template_body, array('id' => 'template_body', 'class' => '', 'style' => 'width: 100%; height: 500px;')));
	$form_container->end();

	if (is_mutable_theme($codename, $mode)) {
		$buttons[] = $form->generate_submit_button($lang->save_continue, array('name' => 'continue'));
		$buttons[] = $form->generate_submit_button($lang->save_close, array('name' => 'close'));

		$form->output_submit_wrapper($buttons);
	}

	$form->end();

	output_codepress_js();

	$page->output_footer();
}

if ($action == 'add_template') {
	$plugins->run_hooks('admin_style_templates_add_template');

	$template_body = $mybb->get_input('template_body');
	$namespace     = $mybb->get_input('namespace');
	if (!$namespace) {
		$namespace = 'frontend';
	}
	$template_path = $mybb->get_input('template_path');

	if ($mybb->request_method == 'post') {
		if (!is_mutable_theme($codename, $mode)) {
			flash_message($lang->error_immutable_theme);
			admin_redirect('index.php?module=style-themes&amp;action=templates&amp;codename='.urlencode($codename));
		}

		if (!verify_post_check($mybb->get_input('my_post_key'))) {
			flash_message($lang->invalid_post_verify_key2, 'error');
			admin_redirect('index.php?module=style-themes');
		}

		if (!$template_path) {
			$errors[] = $lang->error_missing_template_path;
		}
		if (!$template_body) {
			$errors[] = $lang->error_missing_template_body;
		}
		if (!is_valid_namespace($namespace)) {
			$errors[] = $lang->sprintf($lang->error_invalid_namespace, htmlspecialchars_uni($namespace));
		}
		if (!is_valid_resource_path($template_path)) {
			$errors[] = $lang->sprintf($lang->error_invalid_template_name, htmlspecialchars_uni($template_path));
		}

		if (!$errors) {
			$path = MYBB_ROOT."inc/themes/{$codename}/{$mode}/{$namespace}/templates/{$template_path}";

			if (file_exists($path)) {
				$errors[] = $lang->sprintf($lang->error_template_already_exists, '<a href="index.php?module=style-themes&amp;action=edit_template&amp;codename='.urlencode($codename).'&amp;namespace='.urlencode($namespace).'&amp;template_path='.urlencode($template_path).'">', '</a>');
			} else {
				$plugins->run_hooks('admin_style_themes_add_template_commit');

				mkdir(dirname($path), 0755, true);
				if (!file_put_contents($path, $template_body)) {
					$errors[] = $lang->error_failed_write_template;
				} else {
					log_admin_action($codename, $namespace, $template_path);

					flash_message($lang->success_new_template_saved, 'success');

					admin_redirect('index.php?module=style-themes&amp;action=edit_template&amp;codename='.urlencode($codename).'&amp;namespace='.urlencode($namespace).'&amp;template_path='.urlencode($template_path));
				}
			}
		}
	}

	set_headers_for_codepress();

	$page->add_breadcrumb_item($lang->templates, 'index.php?module=style-themes&amp;action=templates&amp;codename='.urlencode($codename));
	$page->add_breadcrumb_item($lang->add_template);

	$page->output_header("{$lang->themes} - {$lang->add_template}");

	$page->output_nav_tabs($sub_tabs, 'add_template');

	if (!is_mutable_theme($codename, $mode)) {
		$page->output_alert($lang->warning_immutable_theme);
	}

	if ($errors) {
		$page->output_inline_error($errors);
	}

	$form = new Form('index.php?module=style-themes&amp;action=add_template&amp;codename='.urlencode($codename), 'post', 'add_template');
	$form_container = new FormContainer($lang->sprintf($lang->add_template_named, htmlspecialchars_uni($name).get_theme_name_apx($theme['codename'])), 'tfixed');
	$form_container->output_row($lang->template_add_namespace, $lang->template_add_namespace_desc, $form->generate_text_box('namespace', $namespace, array('id' => 'namespace')), 'namespace');
	$form_container->output_row($lang->template_add_name, $lang->template_add_name_desc, $form->generate_text_box('template_path', $template_path, array('id' => 'template_path')), 'template_path');
	$form_container->output_row('', '', $form->generate_text_area('template_body', $template_body, array('id' => 'template_body', 'class' => '', 'style' => 'width: 100%; height: 500px;')));
	$form_container->end();

	if (is_mutable_theme($codename, $mode)) {
		$buttons[] = $form->generate_submit_button($lang->save, array('name' => 'save'));

		$form->output_submit_wrapper($buttons);
	}

	$form->end();

	output_codepress_js();

	$page->output_footer();
}

if ($action == 'delete_template') {
	if (!is_mutable_theme($codename, $mode)) {
		flash_message($lang->error_immutable_theme, 'error');
		admin_redirect('index.php?module=style-themes&amp;action=templates&amp;codename='.urlencode($codename));
	}

	$namespace     = $mybb->get_input('namespace');
	$template_path = $mybb->get_input('template_path');

	$path = MYBB_ROOT."inc/themes/{$codename}/{$mode}/{$namespace}/templates/{$template_path}";

	// Does the template not exist?
	if (!file_exists($path)) {
		flash_message($lang->error_invalid_template, 'error');
		admin_redirect('index.php?module=style-themes&amp;action=templates&amp;codename='.urlencode($codename));
	}

	// User clicked no
	if ($mybb->get_input('no')) {
		admin_redirect('index.php?module=style-themes&amp;action=templates&amp;codename='.urlencode($codename));
	}

	$plugins->run_hooks('admin_style_themes_delete_template');

	if ($mybb->request_method == 'post') {
		if (!verify_post_check($mybb->get_input('my_post_key'))) {
			flash_message($lang->invalid_post_verify_key2, 'error');
			admin_redirect('index.php?module=style-themes&amp;action=stylesheets&amp;codename='.urlencode($codename));
		}

		// Attempt to delete the template
		if (!unlink($path)) {
			flash_message($lang->error_failed_to_delete_template_file." {$path}", 'error');
			admin_redirect('index.php?module=style-themes&amp;action=templates&amp;codename='.urlencode($codename));
		} else {
			$plugins->run_hooks('admin_style_themes_delete_template_commit');

			// Log admin action
			log_admin_action($codename, $namespace, $template_path);

			flash_message($lang->success_template_deleted, 'success');
			admin_redirect('index.php?module=style-themes&action=templates&amp;codename='.urlencode($codename));
		}
	} else {
		$page->output_confirm_action('index.php?module=style-themes&amp;action=delete_template&amp;codename='.urlencode($codename).'&amp;namespace='.urlencode($namespace).'&amp;template_path='.urlencode($template_path), $lang->confirm_template_deletion);
	}
}

if (!$action) {
	$page->output_header($lang->themes);

	$plugins->run_hooks("admin_style_themes_start");

	$page->output_nav_tabs($sub_tabs, 'themes');

	$table = new Table;
	$table->construct_header($lang->theme);
	$table->construct_header($lang->num_users, array("class" => "align_center", "width" => 100));
	$table->construct_header($lang->quick_links, array('class' => 'align_center'));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

	build_theme_list();

	$table->output($lang->themes);

	$page->output_footer();
}

function set_headers_for_codepress() {
	global $admin_options, $page;

	if ($admin_options['codepress'] != 0) {
		$page->extra_header .= '
<link href="./jscripts/codemirror/lib/codemirror.css?ver=1813" rel="stylesheet">
<link href="./jscripts/codemirror/theme/mybb.css?ver=1813" rel="stylesheet">
<script src="./jscripts/codemirror/lib/codemirror.js?ver=1813"></script>
<script src="./jscripts/codemirror/mode/xml/xml.js?ver=1813"></script>
<script src="./jscripts/codemirror/mode/javascript/javascript.js?ver=1813"></script>
<script src="./jscripts/codemirror/mode/css/css.js?ver=1813"></script>
<script src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js?ver=1813"></script>
<script src="./jscripts/codemirror/mode/twig/twig.js?ver=1900"></script>
<link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css?ver=1813" rel="stylesheet">
<script src="./jscripts/codemirror/addon/dialog/dialog.js?ver=1813"></script>
<script src="./jscripts/codemirror/addon/search/searchcursor.js?ver=1813"></script>
<script src="./jscripts/codemirror/addon/search/search.js?ver=1821"></script>
<script src="./jscripts/codemirror/addon/fold/foldcode.js?ver=1813"></script>
<script src="./jscripts/codemirror/addon/fold/xml-fold.js?ver=1813"></script>
<script src="./jscripts/codemirror/addon/fold/foldgutter.js?ver=1813"></script>
<link href="./jscripts/codemirror/addon/fold/foldgutter.css?ver=1813" rel="stylesheet">
';
	}
}

function output_codepress_js() {
	global $admin_options;

	if ($admin_options['codepress'] != 0) {
		echo '<script type="text/javascript">
			var editor = CodeMirror.fromTextArea(document.getElementById("template_body"), {
				lineNumbers: true,
				lineWrapping: true,
				foldGutter: true,
				gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
				viewportMargin: Infinity,
				indentWithTabs: true,
				indentUnit: 4,
				mode: "twig",
				theme: "mybb"
			});
		</script>';
	}
}

/**
 * Gets a list of staged theme files.
 *
 * @param boolean $show_errs If true, and an error occurs, the error is displayed inline; otherwise, errors are ignored.
 * @return array Keys are theme codenames; values are theme manifest data as extracted from the relevant manifest file.
 */
function get_staged_themes($show_errs = true)
{
	global $lang, $page;

	$themes_list = [];
	$dh = @opendir(MYBB_ROOT.'staging/themes/');
	if ($dh) {
		while (($theme_code = readdir($dh))) {
			if (in_array($theme_code, ['.', '..']) || !is_dir(MYBB_ROOT."staging/themes/$theme_code")) {
				continue;
			}
			$info_file = MYBB_ROOT."staging/themes/$theme_code/theme.json";
			if ($themeinfo = read_json_file($info_file, $errmsg, $show_errs)) {
				if (empty($themeinfo['version'])) {
					if ($show_errs) {
						$page->output_inline_error($lang->sprintf($lang->error_missing_manifest_version, htmlspecialchars_uni($info_file)));
					}
				} else	$themes_list[$theme_code] = $themeinfo;
			} else if ($show_errs) {
				$page->output_inline_error($lang->sprintf($lang->error_bad_staged_json_file, htmlspecialchars_uni($info_file)));
			}
		}
		@closedir($dh);
	}

	return $themes_list;
}

function get_ss_attach_from_input(&$errors) {
	global $mybb;

	$attached = array();
	$type = $mybb->get_input('attach', MyBB::INPUT_INT);
	if ($type == 0) {
		$attached = [0 => ['script' => 'global', 'actions' => ['global']]];
	} else if ($type == 1) {
		// Our stylesheet is attached to custom pages in MyBB
		foreach ($mybb->input as $id => $value) {
			if (strpos($id, 'attached_') !== false) {
				$att = ['script' => $value];

				// We have a custom attached file
				$attached_id = (int)str_replace('attached_', '', $id);

				if ($mybb->input['action_'.$attached_id] == 1) {
					// We have custom actions for attached files
					$att['actions'] = explode(',', $mybb->input['action_list_'.$attached_id]);
				} else {
					$att['actions'] = ['global'];
				}

				$attached[] = $att;
			}
		}
	} else if ($type == 2) {
		if (!is_array($mybb->input['color'])) {
			$errors[] = $lang->error_no_color_picked;
		} else {
			$attached = [0 => ['script' => $mybb->input['color'], 'actions' => []]];
		}
	}

	return $attached;
}
