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

$languages = $lang->get_languages();

$page->add_breadcrumb_item($lang->languages, "index.php?module=config-languages");

$plugins->run_hooks("admin_config_languages_begin");

$no_write = null;
if($mybb->input['action'] == "edit_properties")
{
	$editlang = basename($mybb->input['lang']);
	$file = MYBB_ROOT."inc/languages/".$editlang.".php";
	if(!file_exists($file))
	{
		flash_message($lang->error_invalid_file, 'error');
		admin_redirect("index.php?module=config-languages");
	}

	$plugins->run_hooks("admin_config_languages_edit_properties");

	if($mybb->request_method == "post")
	{
		if(!is_writable($file))
		{
			flash_message($lang->error_cannot_write_to_file, 'error');
			admin_redirect("index.php?module=config-languages");
		}

		foreach($mybb->input['info'] as $key => $info)
		{
			$info = str_replace("\\", "\\\\", $info);
			$info = str_replace('$', '\$', $info);

			if($key == 'admin' || $key == 'rtl')
			{
				$info = (int)$info;
			}

			$newlanginfo[$key] = str_replace("\"", '\"', $info);
		}

		// Get contents of existing file
		require $file;

		// Make the contents of the new file
		$newfile = "<?php
// The friendly name of the language
\$langinfo['name'] = \"{$newlanginfo['name']}\";

// The author of the language
\$langinfo['author'] = \"{$langinfo['author']}\";

// The language authors website
\$langinfo['website'] = \"{$langinfo['website']}\";

// Compatible version of MyBB
\$langinfo['version'] = \"{$langinfo['version']}\";

// Sets if the translation includes the Admin CP (1 = yes, 0 = no)
\$langinfo['admin'] = {$newlanginfo['admin']};

// Sets if the language is RTL (Right to Left) (1 = yes, 0 = no)
\$langinfo['rtl'] = {$newlanginfo['rtl']};

// Sets the lang in the <html> on all pages
\$langinfo['htmllang'] = \"{$newlanginfo['htmllang']}\";

// Sets the character set, blank uses the default.
\$langinfo['charset'] = \"{$newlanginfo['charset']}\";\n";

		// Put it in!
		if($file = fopen($file, "w"))
		{
			fwrite($file, $newfile);
			fclose($file);

			$plugins->run_hooks("admin_config_languages_edit_properties_commit");

			// Log admin action
			log_admin_action($editlang);

			flash_message($lang->success_langprops_updated, 'success');
			admin_redirect("index.php?module=config-languages&action=edit&lang=".htmlspecialchars_uni($editlang)."&editwith=".htmlspecialchars_uni($editlang));
		}
		else
		{
			$errors[] = $lang->error_cannot_write_to_file;
		}
	}

	$page->add_breadcrumb_item(preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($languages[$editlang])), "index.php?module=config-languages&amp;action=edit&amp;lang=".htmlspecialchars_uni($editlang));
	$page->add_breadcrumb_item($lang->nav_editing_set);

	$page->output_header($lang->languages);

	$sub_tabs['edit_properties'] = array(
		"title" => $lang->edit_properties,
		"link" => "index.php?module=config-languages",
		"description" => $lang->edit_properties_desc
	);
	$page->output_nav_tabs($sub_tabs, "edit_properties");

	// Get language info
	require $file;

	$form = new Form("index.php?module=config-languages&amp;action=edit_properties", "post", "editset");
	echo $form->generate_hidden_field("lang", $editlang);
	echo $form->generate_hidden_field("info[author]", $langinfo['author']);
	echo $form->generate_hidden_field("info[website]", $langinfo['website']);
	echo $form->generate_hidden_field("info[version]", $langinfo['version']);

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		if($langinfo['admin'])
		{
			$mybb->input['info']['admin'] = 1;
		}
		else
		{
			$mybb->input['info']['admin'] = 0;
		}

		if($langinfo['rtl'])
		{
			$mybb->input['info']['rtl'] = 1;
		}
		else
		{
			$mybb->input['info']['rtl'] = 0;
		}

		$mybb->input['info']['name'] = $langinfo['name'];
		$mybb->input['info']['htmllang'] = $langinfo['htmllang'];
		$mybb->input['info']['charset'] = $langinfo['charset'];
	}

	$form_container = new FormContainer($lang->edit_properties);

	$form_container->output_row($lang->friendly_name." <em>*</em>", "", $form->generate_text_box('info[name]', $mybb->input['info']['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->language_in_html." <em>*</em>", "", $form->generate_text_box('info[htmllang]', $mybb->input['info']['htmllang'], array('id' => 'htmllang')), 'htmllang');
	$form_container->output_row($lang->charset." <em>*</em>", "", $form->generate_text_box('info[charset]', $mybb->input['info']['charset'], array('id' => 'charset')), 'charset');
	$form_container->output_row($lang->rtl." <em>*</em>", "", $form->generate_yes_no_radio('info[rtl]', $mybb->input['info']['rtl'], array('id' => 'rtl')), 'rtl');
	$form_container->output_row($lang->admin." <em>*</em>", "", $form->generate_yes_no_radio('info[admin]', $mybb->input['info']['admin'], array('id' => 'admin')), 'admin');

	// Check if file is writable, before allowing submission
	if(!is_writable($file))
	{
		$no_write = 1;
		$page->output_alert($lang->alert_note_cannot_write);
	}

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_language_file, array('disabled' => $no_write));

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "quick_phrases")
{
	// Validate input
	$editlang = basename($mybb->input['lang']);

	if(in_array($editlang, array('.', '..')))
	{
		flash_message($lang->error_folders_fail, 'error');
		admin_redirect("index.php?module=config-languages");
	}

	$folder = MYBB_ROOT."inc/languages/".$editlang."/";

	$page->add_breadcrumb_item(preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($languages[$editlang])), "index.php?module=config-languages&amp;action=quick_edit&amp;lang=".htmlspecialchars_uni($editlang));

	// Validate that this language pack really exists
	if(file_exists(MYBB_ROOT."inc/languages/".$editlang.".php"))
	{
		// Then validate language pack folders (and try to fix them if missing)
		if(!is_dir($folder))
		{
			@mkdir($folder);
		}
		if(!is_dir($folder."admin"))
		{
			@mkdir($folder."admin");
		}
	}

	if(!file_exists($folder) || !file_exists($folder."admin"))
	{
		flash_message($lang->error_folders_fail, 'error');
		admin_redirect("index.php?module=config-languages");
	}

	$plugins->run_hooks("admin_config_languages_quick_phrases");

	$quick_phrases = array(
		'member.lang.php' => array(
			'agreement' => $lang->quickphrases_agreement,
			'agreement_1' => $lang->quickphrases_agreement_1,
			'agreement_2' => $lang->quickphrases_agreement_2,
			'agreement_3' => $lang->quickphrases_agreement_3,
			'agreement_4' => $lang->quickphrases_agreement_4,
			'agreement_5' => $lang->quickphrases_agreement_5
		),
		'messages.lang.php' => array(
			'error_nopermission_guest_1' => $lang->quickphrases_error_nopermission_guest_1,
			'error_nopermission_guest_2' => $lang->quickphrases_error_nopermission_guest_2,
			'error_nopermission_guest_3' => $lang->quickphrases_error_nopermission_guest_3,
			'error_nopermission_guest_4' => $lang->quickphrases_error_nopermission_guest_4
		)
	);

	if($mybb->request_method == 'post')
	{
		if($mybb->request_method == 'post')
		{
			// We have more than one file to edit, lets set flag for all of them.
			$editsuccess = true;
			foreach($quick_phrases as $file => $phrases)
			{
				@include $folder.$file;
				$contents_file = (array)$l;
				unset($l);

				foreach($phrases as $key => $value)
				{
					// validation - we fetch from input only variables that are defined in $quick_phrases array
					$contents_file[$key] = $mybb->input['edit'][$key];
				}
				// Save edited language file
				if($fp = @fopen($folder.$file, "w"))
				{
					// We need info about edited language files to generate credits for our file
					require MYBB_ROOT."inc/languages/".$editlang.".php";

					// Lets make nice credits header in language file
					$lang_file_credits  = "<?php\n/**\n";
					$lang_file_credits .= " * MyBB Copyright 2014 MyBB Group, All Rights Reserved\n *\n";
					$lang_file_credits .= " * Website: https://mybb.com\n";
					$lang_file_credits .= " * License: https://mybb.com/about/license\n *\n */\n\n";
					$lang_file_credits .= "// ".str_repeat('-',80)."\n";
					$lang_file_credits .= "// MyBB Language Pack File.\n";
					$lang_file_credits .= "// This file has been generated by MyBB - buildin language pack editor.\n";
					$lang_file_credits .= "// ".str_repeat('=',80)."\n";
					$lang_file_credits .= "// Friendly name of the language : ".preg_replace("#<\?|\?>|\r|\n#i", " ", $langinfo['name'])."\n";
					$lang_file_credits .= "// Author of the language pack : ".preg_replace("#<\?|\?>|\r|\n#i", " ", $langinfo['author'])."\n";
					$lang_file_credits .= "// Language pack translators website : ".preg_replace("#<\?|\?>|\r|\n#i", " ", $langinfo['website'])."\n";
					$lang_file_credits .= "// Compatible version of MyBB : ".preg_replace("#<\?|\?>|\r|\n#i", " ", $langinfo['version'])."\n";
					$lang_file_credits .= "// Last edited in MyBB Editor by : ".preg_replace("#<\?|\?>|\r|\n#i", " ", $mybb->user['username'])."\n";
					$lang_file_credits .= "// Last edited date : ".gmdate("r")."\n";
					$lang_file_credits .= "// ".str_repeat('-',80)."\n\n";

					$contents_wfile = $lang_file_credits;
					foreach($contents_file as $key => $value)
					{
						$contents_wfile .= "\$l['".$key."'] = ".var_export($value, true).";\n";
					}

					flock($fp, LOCK_EX);
					fwrite($fp, $contents_wfile);
					flock($fp, LOCK_UN);
					fclose($fp);
				}
				else
				{
					// One of files failed
					$editsuccess = false;
				}
			}

			if($editsuccess == true)
			{
				// Log admin action
				log_admin_action($editlang);

				flash_message($lang->success_quickphrases_updated, 'success');
				admin_redirect('index.php?module=config-languages&amp;action=edit&amp;lang='.$editlang);
			}
		}
	}

	$page->output_header($lang->languages);

	$sub_tabs['language_files'] = array(
		'title' => $lang->language_files,
		'link' => "index.php?module=config-languages&amp;action=edit&amp;lang=".$editlang,
		'description' => $lang->language_files_desc
	);

	$sub_tabs['quick_phrases'] = array(
		'title' => $lang->quick_phrases,
		'link' => "index.php?module=config-languages&amp;action=quick_phrases&amp;lang=".$editlang,
		'description' => $lang->quick_phrases_desc
	);

	$page->output_nav_tabs($sub_tabs, 'quick_phrases');

	$form = new Form('index.php?module=config-languages&amp;action=quick_phrases&amp;lang='.$editlang, 'post', 'quick_phrases');

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$table = new Table;

	// Check if files are writable, before allowing submission
	$no_write = null;
	foreach($quick_phrases as $file => $phrases)
	{
		if(file_exists($folder.$file) && !is_writable($folder.$file) || !is_writable($folder))
		{
			$no_write = 1;
		}
	}

	if($no_write)
	{
		$page->output_alert($lang->alert_note_cannot_write);
	}

	$form_container = new FormContainer($lang->quick_phrases);

	foreach($quick_phrases as $file => $phrases)
	{
		unset($langinfo);
		@include MYBB_ROOT."inc/languages/".$editlang.".php";
		$quickphrases_dir_class = " langeditor_ltr";
		if((int)$langinfo['rtl'] > 0)
		{
			$quickphrases_dir_class = " langeditor_rtl";
		}

		@include $folder.$file;
		foreach($phrases as $phrase => $description)
		{
			$value = $l[$phrase];
			if(my_strtolower($langinfo['charset']) == "utf-8")
			{
				$value = preg_replace_callback("#%u([0-9A-F]{1,4})#i", 'encode_language_string_utf8', $value);
			}
			else
			{
				$value = preg_replace_callback("#%u([0-9A-F]{1,4})#i", 'encode_language_string', $value);
			}

			$form_container->output_row($description, $phrase, $form->generate_text_area("edit[$phrase]", $value, array('id' => 'lang_'.$phrase, 'rows' => 2, 'class' => "langeditor_textarea_edit {$quickphrases_dir_class}")), 'lang_'.$phrase, array('width' => '50%'));
		}
	}

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_language_file, array('disabled' => $no_write));

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	// Validate input
	$editlang = basename($mybb->input['lang']);

	if(in_array($editlang, array('.', '..')))
	{
		flash_message($lang->error_folders_fail, 'error');
		admin_redirect("index.php?module=config-languages");
	}

	$folder = MYBB_ROOT."inc/languages/".$editlang."/";

	$page->add_breadcrumb_item(preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($languages[$editlang])), "index.php?module=config-languages&amp;action=edit&amp;lang=".htmlspecialchars_uni($editlang));

	$editwith = basename($mybb->get_input('editwith'));

	if(in_array($editwith, array('.', '..')))
	{
		flash_message($lang->error_folders_fail, 'error');
		admin_redirect("index.php?module=config-languages");
	}


	$editwithfolder = '';

	if($editwith)
	{
		$editwithfolder = MYBB_ROOT."inc/languages/".$editwith."/";
	}

	// Validate that edited language pack really exists
	if(file_exists(MYBB_ROOT."inc/languages/".$editlang.".php"))
	{
		// Then validate edited language pack folders (and try to fix them if missing)
		if(!is_dir($folder))
		{
			@mkdir($folder);
		}
		if(!is_dir($folder."admin"))
		{
			@mkdir($folder."admin");
		}
	}

	if(!file_exists($folder) || !file_exists($folder."admin"))
	{
		flash_message($lang->error_folders_fail, 'error');
		admin_redirect("index.php?module=config-languages");
	}

	// If we edit in compare mode, verify that at least folders of compared language exists
	if($editwithfolder && (!file_exists($editwithfolder) || !file_exists($editwithfolder)))
	{
		flash_message($lang->error_invalid_set, 'error');
		admin_redirect("index.php?module=config-languages");
	}

	$plugins->run_hooks("admin_config_languages_edit");

	if(isset($mybb->input['file']))
	{
		// Validate input
		$file = basename($mybb->input['file']);

		if(in_array($file, array('.', '..')))
		{
			flash_message($lang->error_folders_fail, 'error');
			admin_redirect("index.php?module=config-languages");
		}

		if($mybb->get_input('inadmin') == 1)
		{
			$file = 'admin/'.$file;
		}
		$page->add_breadcrumb_item(htmlspecialchars_uni($file));

		$editfile = $folder.$file;
		$withfile = '';

		$editwithfile = '';
		if($editwithfolder)
		{
			$editwithfile = $editwithfolder.$file;
		}

		if($mybb->request_method == "post")
		{
			// Save edited phrases to language file

			// To validate input - build array of keys that allready exist in files
			@include $editfile;
			$valid_keys = (array)$l;
			unset($l);

			if(!empty($editwithfile))
			{
				@include $editwithfile;
			}
			if(!empty($l))
			{
				$valid_keys = array_merge($valid_keys, (array)$l);
			}
			unset($l);

			$contents_wfile = null;

			// Then fetch from input only valid keys
			foreach($valid_keys as $key => $value)
			{
				$contents_wfile .= "\$l['".$key."'] = ".var_export($mybb->input['edit'][$key], true).";\n";
			}

			// Save edited language file
			if($fp = @fopen($editfile, "w"))
			{
				// We need info about edited language files to generate credits for our file
				require MYBB_ROOT."inc/languages/".$editlang.".php";

				// Lets make nice credits header in language file
				$lang_file_credits  = "<?php\n/**\n";
				$lang_file_credits .= " * MyBB Copyright 2014 MyBB Group, All Rights Reserved\n *\n";
				$lang_file_credits .= " * Website: https://mybb.com\n";
				$lang_file_credits .= " * License: https://mybb.com/about/license\n *\n */\n\n";
				$lang_file_credits .= "// ".str_repeat('-',80)."\n";
				$lang_file_credits .= "// MyBB Language Pack File.\n";
				$lang_file_credits .= "// This file has been generated by MyBB - buildin language pack editor.\n";
				$lang_file_credits .= "// ".str_repeat('=',80)."\n";
				$lang_file_credits .= "// Friendly name of the language : ".preg_replace("#<\?|\?>|\r|\n#i", " ", $langinfo['name'])."\n";
				$lang_file_credits .= "// Author of the language pack : ".preg_replace("#<\?|\?>|\r|\n#i", " ", $langinfo['author'])."\n";
				$lang_file_credits .= "// Language pack translators website : ".preg_replace("#<\?|\?>|\r|\n#i", " ", $langinfo['website'])."\n";
				$lang_file_credits .= "// Compatible version of MyBB : ".preg_replace("#<\?|\?>|\r|\n#i", " ", $langinfo['version'])."\n";
				$lang_file_credits .= "// Last edited in MyBB Editor by : ".preg_replace("#<\?|\?>|\r|\n#i", " ", $mybb->user['username'])."\n";
				$lang_file_credits .= "// Last edited date : ".gmdate("r")."\n";
				$lang_file_credits .= "// ".str_repeat('-',80)."\n\n";

				$contents_wfile = $lang_file_credits.$contents_wfile;

				flock($fp, LOCK_EX);
				fwrite($fp, $contents_wfile);
				flock($fp, LOCK_UN);
				fclose($fp);

				$plugins->run_hooks("admin_config_languages_edit_commit");

				// Log admin action
				log_admin_action($editlang, $editfile, $mybb->get_input('inadmin', MyBB::INPUT_INT));

				flash_message($lang->success_langfile_updated, 'success');
				admin_redirect("index.php?module=config-languages&action=edit&lang=".htmlspecialchars_uni($editlang)."&editwith=".htmlspecialchars_uni($editwith));
			}
			else
			{
				$errors[] = $lang->error_cannot_write_to_file;
			}
		}

		if(!empty($editwith))
		{
			unset($langinfo);
			@include MYBB_ROOT."inc/languages/".$editwith.".php";
			$editwith_dir_class = " langeditor_ltr";
			if((int)$langinfo['rtl'] > 0)
			{
				$editwith_dir_class = " langeditor_rtl";
			}
		}
		unset($langinfo);
		@include MYBB_ROOT."inc/languages/".$editlang.".php";
		$editlang_dir_class = " langeditor_ltr";
		if((int)$langinfo['rtl'] > 0)
		{
			$editlang_dir_class = " langeditor_rtl";
		}

		// Build and output form with edited phrases

		// Get file being edited in an array
		$editvars = array();
		unset($l);
		@include $editfile;
		if(isset($l))
		{
			$editvars = (array)$l;
			unset($l);
		}

		$withvars = array();
		// Get edit with file in an array if exists
		if($editwithfile)
		{
			// File we will compare to, may not exists, but dont worry we will auto switch to solo mode later if so
			@include $editwithfile;
			$withvars = (array)$l;
			unset($l);
		}

		// Start output
		$page->output_header($lang->languages);

		$sub_tabs['edit_language_variables'] = array(
			"title" => $lang->edit_language_variables,
			"link" => "index.php?module=config-languages",
			"description" => $lang->edit_language_variables_desc
		);
		$page->output_nav_tabs($sub_tabs, "edit_language_variables");

		$form = new Form("index.php?module=config-languages&amp;action=edit", "post", "edit");
		echo $form->generate_hidden_field("file", $file);
		echo $form->generate_hidden_field("lang", $editlang);
		echo $form->generate_hidden_field("editwith", $editwith);
		echo $form->generate_hidden_field("inadmin", $mybb->get_input('inadmin', MyBB::INPUT_INT));
		if($errors)
		{
			$page->output_inline_error($errors);
		}

		// Check if file is writable, before allowing submission
		$no_write = null;
		if(file_exists($editfile) && !is_writable($editfile) || !is_writable($folder))
		{
			$no_write = 1;
			$page->output_alert($lang->alert_note_cannot_write);
		}

		$form_container = new FormContainer(htmlspecialchars_uni($file));
		if($editwithfile && $withvars)
		{
			// Editing with another file

			$form_container->output_row_header(preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($languages[$editwith])));
			$form_container->output_row_header(preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($languages[$editlang])));

			foreach($withvars as $key => $value)
			{
				if(my_strtolower($langinfo['charset']) == "utf-8")
				{
					$withvars[$key] = preg_replace_callback("#%u([0-9A-F]{1,4})#i", 'encode_language_string_utf8', $withvars[$key]);
					$editvars[$key] = preg_replace_callback("#%u([0-9A-F]{1,4})#i", 'encode_language_string_utf8', $editvars[$key]);
				}
				else
				{
					$withvars[$key] = preg_replace_callback("#%u([0-9A-F]{1,4})#i", 'encode_language_string_utf8', $withvars[$key]);
					$editvars[$key] = preg_replace_callback("#%u([0-9A-F]{1,4})#i", 'encode_language_string', $editvars[$key]);
				}

				// Find problems and differences in editfile in comparision to editwithfile

				// Count {x} in left and right variable
				$withvars_value_cbvCount = preg_match_all("/{[ \t]*\d+[ \t]*}/", $withvars[$key], $matches);
				$editvars_value_cbvCount = preg_match_all("/{[ \t]*\d+[ \t]*}/", $editvars[$key], $matches);

				// If left contain something but right is empty or only spaces || count of {x} are different betwin left and right
				if($withvars[$key] && !$editvars[$key] || $withvars_value_cbvCount != $editvars_value_cbvCount)
				{
					$textarea_issue_class = " langeditor_textarea_issue";
				}
				else
				{
					$textarea_issue_class = "";
				}

				$form_container->output_row($key, "", $form->generate_text_area("", $withvars[$key], array('readonly' => true,  'rows' => 2, 'class' => "langeditor_textarea_editwith {$editwith_dir_class}")), "", array('width' => '50%', 'skip_construct' => true));
				$form_container->output_row($key, "", $form->generate_text_area("edit[$key]", $editvars[$key], array('id' => 'lang_'.$key, 'rows' => 2, 'class' => "langeditor_textarea_edit {$textarea_issue_class} {$editlang_dir_class}")), 'lang_'.$key, array('width' => '50%'));
			}

			// Create form fields for extra variables that are present only in edited file
			$present_in_edit_vars_only = (array)array_diff_key($editvars, $withvars);
			if($present_in_edit_vars_only)
			{
				foreach($present_in_edit_vars_only as $key => $value)
				{
					if(my_strtolower($langinfo['charset']) == "utf-8")
					{
						$editvars[$key] = preg_replace_callback("#%u([0-9A-F]{1,4})#i", 'encode_language_string_utf8', $editvars[$key]);
					}
					else
					{
						$editvars[$key] = preg_replace_callback("#%u([0-9A-F]{1,4})#i", 'encode_language_string', $editvars[$key]);
					}

					$form_container->output_row("", "", "", "", array('width' => '50%', 'skip_construct' => true));
					$form_container->output_row($key, "", $form->generate_text_area("edit[$key]", $editvars[$key], array('id' => 'lang_'.$key, 'rows' => 2, 'class' => "langeditor_textarea_edit {$editlang_dir_class}")), 'lang_'.$key, array('width' => '50%'));
				}
			}

		}
		else
		{
			// Editing individually
			$form_container->output_row_header(preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($languages[$editlang])));

			// Make each editing row from current file that we edit
			foreach($editvars as $key => $value)
			{
				if(my_strtolower($langinfo['charset']) == "utf-8")
				{
					$value = preg_replace_callback("#%u([0-9A-F]{1,4})#i", 'encode_language_string_utf8', $value);
				}
				else
				{
					$value = preg_replace_callback("#%u([0-9A-F]{1,4})#i", 'encode_language_string', $value);
				}
				$form_container->output_row($key, "", $form->generate_text_area("edit[$key]", $value, array('id' => 'lang_'.$key, 'rows' => 2, 'class' => "langeditor_textarea_edit {$editlang_dir_class}")), 'lang_'.$key, array('width' => '50%'));
			}
		}
		$form_container->end();

		if(!count($editvars))
		{
			$no_write = 1;
		}

		$buttons[] = $form->generate_submit_button($lang->save_language_file, array('disabled' => $no_write));

		$form->output_submit_wrapper($buttons);
		$form->end();
	}
	else
	{
		// Build and output list of available language files

		$page->output_header($lang->languages);

		$sub_tabs['language_files'] = array(
			'title' => $lang->language_files,
			'link' => "index.php?module=config-languages&amp;action=edit&amp;lang=".$editlang,
			'description' => $lang->language_files_desc
		);

		$sub_tabs['quick_phrases'] = array(
			'title' => $lang->quick_phrases,
			'link' => "index.php?module=config-languages&amp;action=quick_phrases&amp;lang=".$editlang,
			'description' => $lang->quick_phrases_desc
		);

		$page->output_nav_tabs($sub_tabs, 'language_files');

		if(!file_exists(MYBB_ROOT."inc/languages/".$editlang.".php"))
		{
			flash_message($lang->error_invalid_set, 'error');
			admin_redirect("index.php?module=config-languages");
		}
		require MYBB_ROOT."inc/languages/".$editlang.".php";

		$table = new Table;
		if($editwithfolder)
		{
			$table->construct_header(preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($languages[$editwith])));
			$table->construct_header($lang->phrases, array("class" => "align_center", "width" => 100));
			$table->construct_header(preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($languages[$editlang])));
			$table->construct_header($lang->issues, array("class" => "align_center", "width" => 100));
			$table->construct_header($lang->controls, array("class" => "align_center", "width" => 100));
		}
		else
		{
			$table->construct_header(preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($languages[$editlang])));
			$table->construct_header($lang->phrases, array("class" => "align_center", "width" => 100));
			$table->construct_header($lang->controls, array("class" => "align_center", "width" => 100));
		}

		// Get files in main folder
		$filenames = array();
		if($handle = opendir($folder))
		{
			while(false !== ($file = readdir($handle)))
			{
				if(preg_match("#\.lang\.php$#", $file))
				{
					$filenames[] = $file;
				}
			}
			closedir($handle);
			sort($filenames);
		}

		$edit_colspan = 3;
		// Get files from folder we want to peek at (if possible)
		if($editwithfolder)
		{
			$edit_colspan = 5;
			$filenameswith = array();
			if($handle = opendir($editwithfolder))
			{
				while(false !== ($file = readdir($handle)))
				{
					if(preg_match("#\.lang\.php$#", $file))
					{
						$filenameswith[] = $file;
					}
				}
				closedir($handle);
				sort($filenameswith);
			}
		}

		if($editwithfolder)
		{
			$files_left = array_diff($filenameswith, $filenames);
			$files_right = array_diff($filenames, $filenameswith);
			$files_both = array_intersect($filenameswith, $filenames);

			foreach($files_left as $key => $file)
			{
				$editvars_left = array();

				unset($l);
				@include $editwithfolder.$file;
				if(isset($l))
				{
					$editvars_left = (array)$l;
					unset($l);
				}

				$icon_issues = "<span class='langeditor_ok' title='".$lang->issues_ok."'></span>";
				if(count($editvars_left) > 0)
				{
					$icon_issues = "<span class='langeditor_warning' title='".$lang->issues_warning."'></span>";
				}

				$table->construct_cell(htmlspecialchars_uni($file), array("class" => "langeditor_editwithfile"));
				$table->construct_cell(count($editvars_left), array("class" => "langeditor_phrases"));
				$table->construct_cell("", array("class" => "langeditor_editfile"));
				$table->construct_cell($icon_issues, array("class" => "langeditor_issues"));
				$table->construct_cell("<a href=\"index.php?module=config-languages&amp;action=edit&amp;lang=".htmlspecialchars_uni($editlang)."&amp;editwith=".htmlspecialchars_uni($editwith)."&amp;file=".htmlspecialchars_uni($file)."\">{$lang->edit}</a>", array("class" => "langeditor_edit"));
				$table->construct_row();
			}
			foreach($files_right as $key => $file)
			{
				$editvars_right = array();

				unset($l);
				@include $folder.$file;
				if(isset($l))
				{
					$editvars_right = (array)$l;
					unset($l);
				}

				$icon_issues = "<span class='langeditor_ok' title='".$lang->issues_ok."'></span>";
				if(count($editvars_right) > 0)
				{
					$icon_issues = "<span class='langeditor_nothingtocompare' title='".$lang->issues_nothingtocompare."'></span>";
				}

				$table->construct_cell("", array("class" => "langeditor_editwithfile"));
				$table->construct_cell("", array("class" => "langeditor_phrases"));
				$table->construct_cell(htmlspecialchars_uni($file), array("class" => "langeditor_editfile"));
				$table->construct_cell($icon_issues, array("class" => "langeditor_issues"));
				$table->construct_cell("<a href=\"index.php?module=config-languages&amp;action=edit&amp;lang=".htmlspecialchars_uni($editlang)."&amp;editwith=".htmlspecialchars_uni($editwith)."&amp;file=".htmlspecialchars_uni($file)."\">{$lang->edit}</a>", array("class" => "langeditor_edit"));
				$table->construct_row();
			}
			foreach($files_both as $key => $file)
			{
				$editvars_right = $editvars_left = array();

				unset($l);
				@include $editwithfolder.$file;
				if(isset($l))
				{
					$editvars_left = (array)$l;
					unset($l);
				}
				@include $folder.$file;
				if(isset($l))
				{
					$editvars_right = (array)$l;
					unset($l);
				}

				$table->construct_cell(htmlspecialchars_uni($file), array("class" => "langeditor_editwithfile"));
				$table->construct_cell(count($editvars_left), array("class" => "langeditor_phrases"));
				$table->construct_cell(htmlspecialchars_uni($file), array("class" => "langeditor_editfile"));

				$icon_issues = "<span class='langeditor_ok' title='".$lang->issues_ok."'></span>";

				// Find problems and differences in editfile in comparision to editwithfile
				foreach($editvars_left as $editvars_left_key => $editvars_left_value)
				{
					// Count {x} in left and right variable
					$editvars_left_value_cbvCount  = preg_match_all("/{[ \t]*\d+[ \t]*}/", $editvars_left_value, $matches);
					$editvars_right_value_cbvCount = preg_match_all("/{[ \t]*\d+[ \t]*}/", $editvars_right[$editvars_left_key], $matches);
					// If left contain something but right is empty || count of {x} are different betwin left and right
					if($editvars_left_value && !$editvars_right[$editvars_left_key] || $editvars_left_value_cbvCount != $editvars_right_value_cbvCount)
					{
						$icon_issues = "<span class='langeditor_warning' title='".$lang->issues_warning."'></span>";
						// One difference is enought, so lets abort checking for more.
						break;
					}
				}

				$table->construct_cell($icon_issues, array("class" => "langeditor_issues"));
				$table->construct_cell("<a href=\"index.php?module=config-languages&amp;action=edit&amp;lang=".htmlspecialchars_uni($editlang)."&amp;editwith=".htmlspecialchars_uni($editwith)."&amp;file=".htmlspecialchars_uni($file)."\">{$lang->edit}</a>", array("class" => "langeditor_edit"));
				$table->construct_row();
			}
		}
		else
		{
			foreach($filenames as $key => $file)
			{
				unset($l);
				@include $folder.$file;
				$editvars_count = array();
				if(isset($l))
				{
					$editvars_count = (array)$l;
				}
				unset($l);

				$table->construct_cell(htmlspecialchars_uni($file), array("class" => "langeditor_editfile"));
				$table->construct_cell(count($editvars_count), array("class" => "langeditor_phrases"));
				$table->construct_cell("<a href=\"index.php?module=config-languages&amp;action=edit&amp;lang=".htmlspecialchars_uni($editlang)."&amp;editwith=".htmlspecialchars_uni($editwith)."&amp;file=".htmlspecialchars_uni($file)."\">{$lang->edit}</a>", array("class" => "langeditor_edit"));
				$table->construct_row();
			}
		}

		if($table->num_rows() == 0)
		{
			$table->construct_cell($lang->no_language_files_front_end, array('colspan' => $edit_colspan));
			$table->construct_row();
		}

		$table->output($lang->front_end);

		if($langinfo['admin'] != 0)
		{
			$table = new Table;
			if($editwithfolder)
			{
				$table->construct_header(preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($languages[$editwith])));
				$table->construct_header($lang->phrases, array("class" => "align_center", "width" => 100));
				$table->construct_header(preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($languages[$editlang])));
				$table->construct_header($lang->issues, array("class" => "align_center", "width" => 100));
				$table->construct_header($lang->controls, array("class" => "align_center", "width" => 100));
			}
			else
			{
				$table->construct_header(preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($languages[$editlang])));
				$table->construct_header($lang->phrases, array("class" => "align_center", "width" => 100));
				$table->construct_header($lang->controls, array("class" => "align_center", "width" => 100));
			}

			// Get files in admin folder
			$adminfilenames = array();
			if($handle = opendir($folder."admin"))
			{
				while(false !== ($file = readdir($handle)))
				{
					if(preg_match("#\.lang\.php$#", $file))
					{
						$adminfilenames[] = $file;
					}
				}
				closedir($handle);
				sort($adminfilenames);
			}

			$edit_colspan = 3;
			// Get files from admin folder we want to peek at (if possible)
			if($editwithfolder)
			{
				$edit_colspan = 5;
				$adminfilenameswith = array();
				if($handle = opendir($editwithfolder."admin"))
				{
					while(false !== ($file = readdir($handle)))
					{
						if(preg_match("#\.lang\.php$#", $file))
						{
							$adminfilenameswith[] = $file;
						}
					}
					closedir($handle);
					sort($adminfilenameswith);
				}
			}

			if($editwithfolder)
			{
				$files_left = array_diff($adminfilenameswith, $adminfilenames);
				$files_right = array_diff($adminfilenames, $adminfilenameswith);
				$files_both = array_intersect($adminfilenameswith, $adminfilenames);

				foreach($files_left as $key => $file)
				{
					@include $editwithfolder."admin/".$file;
					$editvars_left = (array)$l;
					unset($l);

					$icon_issues = "<span class='langeditor_ok' title='".$lang->issues_ok."'></span>";
					if(count($editvars_left) >0)
					{
						$icon_issues = "<span class='langeditor_warning' title='".$lang->issues_warning."'></span>";
					}

					$table->construct_cell(htmlspecialchars_uni($file), array("class" => "langeditor_editwithfile"));
					$table->construct_cell(count($editvars_left), array("class" => "langeditor_phrases"));
					$table->construct_cell("", array("class" => "langeditor_editfile"));
					$table->construct_cell($icon_issues, array("class" => "langeditor_issues"));
					$table->construct_cell("<a href=\"index.php?module=config-languages&amp;action=edit&amp;lang=".htmlspecialchars_uni($editlang)."&amp;editwith=".htmlspecialchars_uni($editwith)."&amp;file=".htmlspecialchars_uni($file)."&amp;inadmin=1\">{$lang->edit}</a>", array("class" => "langeditor_edit"));
					$table->construct_row();
				}
				foreach($files_right as $key => $file)
				{
					@include $folder."admin/".$file;
					$editvars_right = (array)$l;
					unset($l);

					$icon_issues = "<span class='langeditor_ok' title='".$lang->issues_ok."'></span>";
					if(count($editvars_right) >0)
					{
						$icon_issues = "<span class='langeditor_nothingtocompare' title='".$lang->issues_nothingtocompare."'></span>";
					}

					$table->construct_cell("", array("class" => "langeditor_editwithfile"));
					$table->construct_cell("", array("class" => "langeditor_phrases"));
					$table->construct_cell(htmlspecialchars_uni($file), array("class" => "langeditor_editfile"));
					$table->construct_cell($icon_issues, array("class" => "langeditor_issues"));
					$table->construct_cell("<a href=\"index.php?module=config-languages&amp;action=edit&amp;lang=".htmlspecialchars_uni($editlang)."&amp;editwith=".htmlspecialchars_uni($editwith)."&amp;file=".htmlspecialchars_uni($file)."&amp;inadmin=1\">{$lang->edit}</a>", array("class" => "langeditor_edit"));
					$table->construct_row();
				}
				foreach($files_both as $key => $file)
				{
					@include $editwithfolder."admin/".$file;
					$editvars_left = (array)$l;
					unset($l);
					@include $folder."admin/".$file;
					$editvars_right = (array)$l;
					unset($l);

					$table->construct_cell(htmlspecialchars_uni($file), array("class" => "langeditor_editwithfile"));
					$table->construct_cell(count($editvars_left), array("class" => "langeditor_phrases"));
					$table->construct_cell(htmlspecialchars_uni($file), array("class" => "langeditor_editfile"));

					$icon_issues = "<span class='langeditor_ok' title='".$lang->issues_ok."'></span>";

					// Find problems and differences in editfile in comparision to editwithfile
					foreach($editvars_left as $editvars_left_key => $editvars_left_value)
					{
						// Count {x} in left and right variable
						$editvars_left_value_cbvCount  = preg_match_all("/{[ \t]*\d+[ \t]*}/", $editvars_left_value, $matches);
						$editvars_right_value_cbvCount = preg_match_all("/{[ \t]*\d+[ \t]*}/", $editvars_right[$editvars_left_key], $matches);
						// If left contain something but right is empty || count of {x} are different betwin left and right
						if($editvars_left_value && !$editvars_right[$editvars_left_key] || $editvars_left_value_cbvCount != $editvars_right_value_cbvCount)
						{
							$icon_issues = "<span class='langeditor_warning' title='".$lang->issues_warning."'></span>";
							// One difference is enought.
							break;
						}
					}

					$table->construct_cell($icon_issues, array("class" => "langeditor_issues"));
					$table->construct_cell("<a href=\"index.php?module=config-languages&amp;action=edit&amp;lang=".htmlspecialchars_uni($editlang)."&amp;editwith=".htmlspecialchars_uni($editwith)."&amp;file=".htmlspecialchars_uni($file)."&amp;inadmin=1\">{$lang->edit}</a>", array("class" => "langeditor_edit"));
					$table->construct_row();
				}
			}
			else
			{
				foreach($adminfilenames as $key => $file)
				{
					@include $folder."admin/".$file;
					$editvars_count = (array)$l;
					unset($l);

					$table->construct_cell(htmlspecialchars_uni($file), array("class" => "langeditor_editfile"));
					$table->construct_cell(count($editvars_count), array("class" => "langeditor_phrases"));
					$table->construct_cell("<a href=\"index.php?module=config-languages&amp;action=edit&amp;lang=".htmlspecialchars_uni($editlang)."&amp;editwith=".htmlspecialchars_uni($editwith)."&amp;file=/".htmlspecialchars_uni($file)."&amp;inadmin=1\">{$lang->edit}</a>", array("class" => "langeditor_edit"));
					$table->construct_row();
				}
			}

			if($table->num_rows() == 0)
			{
				$table->construct_cell($lang->no_language_files_admin_cp, array('colspan' => $edit_colspan));
				$table->construct_row();
			}

			$table->output($lang->admin_cp);
		}
	}

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->languages);

	$sub_tabs['languages'] = array(
		'title' => $lang->languages,
		'link' => "index.php?module=config-languages",
		'description' => $lang->languages_desc
	);
	$sub_tabs['find_language'] = array(
		'title' => $lang->find_language_packs,
		'link' => "https://community.mybb.com/mods.php?action=browse&category=19",
		'link_target' => "_blank",
		'link_rel' => "noopener"
	);

	$plugins->run_hooks("admin_config_languages_start");

	$page->output_nav_tabs($sub_tabs, 'languages');

	$table = new Table;
	$table->construct_header($lang->languagevar);
	$table->construct_header($lang->version, array("class" => "align_center", "width" => 100));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 155));

	asort($languages);

	foreach($languages as $key1 => $langname1)
	{
		$langselectlangs[$key1] = $lang->sprintf($lang->edit_with, preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($langname1)));
	}

	foreach($languages as $key => $langname)
	{
		include MYBB_ROOT."inc/languages/".$key.".php";

		if(!empty($langinfo['website']))
		{
			$author = "<a href=\"".htmlspecialchars_uni($langinfo['website'])."\" target=\"_blank\" rel=\"noopener\">".htmlspecialchars_uni($langinfo['author'])."</a>";
		}
		else
		{
			$author = htmlspecialchars_uni($langinfo['author']);
		}

		$table->construct_cell("<span class='langeditor_info_name'>".preg_replace("<\?|\?>", "<span>?</span>", htmlspecialchars_uni($langinfo['name']))."</span><br /><span class='langeditor_info_author'>".$author."</span>");
		$table->construct_cell(htmlspecialchars_uni($langinfo['version']), array("class" => "align_center"));

		$popup = new PopupMenu("language_".htmlspecialchars_uni($key), $lang->options);
		$popup->add_item($lang->edit_language_variables, "index.php?module=config-languages&amp;action=edit&amp;lang=".htmlspecialchars_uni($key));
		foreach($langselectlangs as $key1 => $langname1)
		{
			if($key != $key1)
			{
				$popup->add_item($langname1, "index.php?module=config-languages&amp;action=edit&amp;lang=".htmlspecialchars_uni($key)."&amp;editwith=".htmlspecialchars_uni($key1));
			}
		}
		$popup->add_item($lang->edit_properties, "index.php?module=config-languages&amp;action=edit_properties&amp;lang=".htmlspecialchars_uni($key));
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_language, array('colspan' => 3));
		$table->construct_row();
	}

	$table->output($lang->installed_language_packs);

	$page->output_footer();
}

/**
 * Fixes url encoded unicode characters
 *
 * @param string $string The string to encode.
 * @return string The encoded string.
 */
function encode_language_string_utf8($matches)
{
	return dec_to_utf8(hexdec($matches[1]));
}

/**
 * Fixes url encoded unicode characters
 *
 * @param string $string The string to encode.
 * @return string The encoded string.
 */
function encode_language_string($matches)
{
	return "&#".hexdec($matches[1]).";";
}
