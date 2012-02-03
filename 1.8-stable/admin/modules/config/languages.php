<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: languages.php 5297 2010-12-28 22:01:14Z Tomm $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$languages = $lang->get_languages();

$page->add_breadcrumb_item($lang->languages, "index.php?module=config-languages");

$plugins->run_hooks("admin_config_languages_begin");

if($mybb->input['action'] == "edit_properties")
{
	$plugins->run_hooks("admin_config_languages_edit_properties");
	
	$editlang = basename($mybb->input['lang']);
	$file = MYBB_ROOT."inc/languages/".$editlang.".php";
	if(!file_exists($file))
	{
		flash_message($lang->error_invalid_file, 'error');
		admin_redirect("index.php?module=config-languages");
	}
	
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
\$langinfo['charset'] = \"{$newlanginfo['charset']}\";\n".
"?".">";
	
		// Put it in!
		if($file = fopen($file, "w"))
		{
			fwrite($file, $newfile);
			fclose($file);

			$plugins->run_hooks("admin_config_languages_edit_properties_commit");

			// Log admin action
			log_admin_action($editlang);

			flash_message($lang->success_langprops_updated, 'success');
			admin_redirect("index.php?module=config-languages&action=edit&lang={$editlang}&editwith={$editwith}");
		}
		else
		{
			$errors[] = $lang->error_cannot_write_to_file;
		}
	}
	
	$page->add_breadcrumb_item($languages[$editlang], "index.php?module=config-languages&amp;action=edit&amp;lang={$editlang}");
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
	$plugins->run_hooks("admin_config_languages_quick_phrases");
	
	// Validate input
	$editlang = basename($mybb->input['lang']);
	$folder = MYBB_ROOT."inc/languages/".$editlang."/";
	
	$page->add_breadcrumb_item($languages[$editlang], "index.php?module=config-languages&amp;action=quick_edit&amp;lang={$editlang}");
	
	if(!file_exists($folder) || ($editwithfolder && !file_exists($editwithfolder)))
	{
		flash_message($lang->error_invalid_set, 'error');
		admin_redirect("index.php?module=config-languages");
	}
	
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
			foreach($quick_phrases as $file => $phrases)
			{
				$lines = file($folder.$file);
				
				$fp = fopen($folder.$file, 'w');
				fwrite($fp, '<?php'.PHP_EOL);
				
				for($i = 1; $i < count($lines); $i++)
				{
					preg_match_all('/\$l\[\'([a-z].+)\'\]/', $lines[$i], $matches);
					$phrase = $matches[1][0];
					
					if($mybb->input['edit'][$phrase])
					{
						$new_line = '$l[\''.$phrase.'\'] = "'.str_replace('"', '\"', $mybb->input['edit'][$phrase]).'";'.PHP_EOL;
						fwrite($fp, $new_line);
					}
					else
					{
						fwrite($fp, $lines[$i]);
					}
				}
				
				fclose($fp);
			}
			
			// Log admin action
			log_admin_action($editlang);
			
			flash_message($lang->success_quickphrases_updated, 'success');
			admin_redirect('index.php?module=config-languages&amp;action=edit&amp;lang='.$editlang);
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
	
	// Check if file is writable, before allowing submission
	$no_write = 0;
	
	foreach($quick_phrases as $file => $phrases)
	{
		if(!is_writable($folder.$file))
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
		require $folder.$file;
		
		foreach($phrases as $phrase => $description)
		{
			$value = $l[$phrase];
			if(my_strtolower($langinfo['charset']) == "utf-8")
			{
				$value = preg_replace("#%u([0-9A-F]{1,4})#ie", "dec_to_utf8(hexdec('$1'));", $value);
			}
			else
			{
				$value = preg_replace("#%u([0-9A-F]{1,4})#ie", "'&#'.hexdec('$1').';'", $value);
			}
			
			$form_container->output_row($description, $phrase, $form->generate_text_area("edit[$phrase]", $value, array('id' => 'lang_'.$phrase, 'rows' => 2, 'style' => "width: 98%; padding: 4px;")), 'lang_'.$phrase, array('width' => '50%'));
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
	$plugins->run_hooks("admin_config_languages_edit");
	
	// Validate input
	$editlang = basename($mybb->input['lang']);
	$folder = MYBB_ROOT."inc/languages/".$editlang."/";
	
	$page->add_breadcrumb_item($languages[$editlang], "index.php?module=config-languages&amp;action=edit&amp;lang={$editlang}");
	
	$editwith = basename($mybb->input['editwith']);
	$editwithfolder = '';
	
	if($editwith)
	{
		$editwithfolder = MYBB_ROOT."inc/languages/".$editwith."/";
	}
	
	if(!file_exists($folder) || ($editwithfolder && !file_exists($editwithfolder)))
	{
		flash_message($lang->error_invalid_set, 'error');
		admin_redirect("index.php?module=config-languages");
	}
	
	if(isset($mybb->input['file']))
	{
		// Validate input
		$file = basename($mybb->input['file']);
		if($mybb->input['inadmin'] == 1)
		{
			$file = 'admin/'.$file;
		}
		$page->add_breadcrumb_item($file);
		
		$editfile = $folder.$file;
		$withfile = '';
		
		$editwithfile = '';
		if($editwithfolder)
		{
			$editwithfile = $editwithfolder.$file;
		}
		
		if(!file_exists($editfile) || ($editwithfile && !file_exists($editwithfile)))
		{
			flash_message($lang->error_invalid_file, 'error');
			admin_redirect("index.php?module=config-languages");
		}
		
		if($mybb->request_method == "post")
		{
			// Make the contents of the new file
			
			// Load the old file
			$contents = implode('', file($editfile));
			
			// Loop through and change entries
			foreach($mybb->input['edit'] as $key => $phrase)
			{
				// Sanitize (but it doesn't work well)
				$phrase = str_replace('$', '\$', $phrase);
				$phrase = str_replace("\\", "\\\\", $phrase);
				$phrase = str_replace("\"", '\"', $phrase);
				$key = str_replace("\\", '', $key);
				$key = str_replace('$', '', $key);
				$key = str_replace("'", '', $key);
				
				// Ugly regexp to find a variable and replace it.
				$contents = preg_replace('@\n\$l\[\''.$key.'\']([\s]*)=([\s]*)("(.*?)"|\'(.*?)\');([\s]*)\n@si', "\n\$l['{$key}'] = \"{$phrase}\";\n", $contents);
			}
			
			// Put it back!
			if($fp = @fopen($editfile, "w"))
			{
				fwrite($fp, $contents);
				fclose($fp);
				
				$plugins->run_hooks("admin_config_languages_edit_commit");

				// Log admin action
				log_admin_action($editlang, $editfile, $mybb->input['inadmin']);

				flash_message($lang->success_langfile_updated, 'success');
				admin_redirect("index.php?module=config-languages&action=edit&lang={$editlang}&editwith={$editwith}");
			}
			else
			{
				$errors[] = $lang->error_cannot_write_to_file;
			}
		}
		
		// Get file being edited in an array
		require $editfile;
		
		if(count($l) > 0)
		{
			$editvars = $l;
		}
		else
		{
			$editvars = array();
		}
		unset($l);

		$withvars = array();
		// Get edit with file in an array
		if($editwithfile)
		{
			require $editwithfile;
			$withvars = $l;
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
		echo $form->generate_hidden_field("inadmin", intval($mybb->input['inadmin']));
		if($errors)
		{
			$page->output_inline_error($errors);
		}

		// Check if file is writable, before allowing submission
		$no_write = 0;
		if(!is_writable($editfile))
		{
			$no_write = 1;
			$page->output_alert($lang->alert_note_cannot_write);
		}

		$form_container = new FormContainer($file);
		if($editwithfile)
		{
			// Editing with another file
			$form_container->output_row_header($languages[$editwith]);
			$form_container->output_row_header($languages[$editlang]);

			// Make each editing row
			foreach($editvars as $key => $value)
			{
				if(my_strtolower($langinfo['charset']) == "utf-8")
				{
					$withvars[$key] = preg_replace("#%u([0-9A-F]{1,4})#ie", "dec_to_utf8(hexdec('$1'));", $withvars[$key]);
					$value = preg_replace("#%u([0-9A-F]{1,4})#ie", "dec_to_utf8(hexdec('$1'));", $value);
				}
				else
				{
					$withvars[$key] = preg_replace("#%u([0-9A-F]{1,4})#ie", "dec_to_utf8(hexdec('$1'));", $withvars[$key]);
					$value = preg_replace("#%u([0-9A-F]{1,4})#ie", "'&#'.hexdec('$1').';'", $value);
				}
				$form_container->output_row($key, "", $form->generate_text_area("", $withvars[$key], array('disabled' => true, 'rows' => 2, 'style' => "width: 98%; padding: 4px;")), "", array('width' => '50%', 'skip_construct' => true));
				$form_container->output_row($key, "", $form->generate_text_area("edit[$key]", $value, array('id' => 'lang_'.$key, 'rows' => 2, 'style' => "width: 98%; padding: 4px;")), 'lang_'.$key, array('width' => '50%'));
			}
		}
		else
		{
			// Editing individually
			$form_container->output_row_header($languages[$editlang]);
	
			// Make each editing row
			foreach($editvars as $key => $value)
			{
				if(my_strtolower($langinfo['charset']) == "utf-8")
				{
					$value = preg_replace("#%u([0-9A-F]{1,4})#ie", "dec_to_utf8(hexdec('$1'));", $value);
				}
				else
				{
					$value = preg_replace("#%u([0-9A-F]{1,4})#ie", "'&#'.hexdec('$1').';'", $value);
				}
				$form_container->output_row($key, "", $form->generate_text_area("edit[$key]", $value, array('id' => 'lang_'.$key, 'rows' => 2, 'style' => "width: 98%; padding: 4px;")), 'lang_'.$key, array('width' => '50%'));
			}
		}
		$form_container->end();
		
		$buttons[] = $form->generate_submit_button($lang->save_language_file, array('disabled' => $no_write));

		$form->output_submit_wrapper($buttons);
		$form->end();
	}
	else
	{
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
		
		require MYBB_ROOT."inc/languages/".$editlang.".php";
		
		$table = new Table;
		$table->construct_header($lang->file);
		$table->construct_header($lang->controls, array("class" => "align_center", "width" => 100));
		
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
		
		foreach($filenames as $key => $file)
		{
			$table->construct_cell("<strong>{$file}</strong>");
			$table->construct_cell("<a href=\"index.php?module=config-languages&amp;action=edit&amp;lang={$editlang}&amp;editwith={$editwith}&amp;file={$file}\">{$lang->edit}</a>", array("class" => "align_center"));
			$table->construct_row();
		}
		
		if($table->num_rows() == 0)
		{
			$table->construct_cell($lang->no_language_files_front_end, array('colspan' => 3));
			$table->construct_row();
		}
		
		$table->output($lang->front_end);
		
		if($langinfo['admin'] != 0)
		{		
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
			
			$table = new Table;
			$table->construct_header($lang->file);
			$table->construct_header($lang->controls, array("class" => "align_center", "width" => 100));
			
			foreach($adminfilenames as $key => $file)
			{
				$table->construct_cell("<strong>{$file}</strong>");
				$table->construct_cell("<a href=\"index.php?module=config-languages&amp;action=edit&amp;lang={$editlang}&amp;editwith={$editwith}&amp;file={$config['admindir']}/{$file}&amp;inadmin=1\">{$lang->edit}</a>", array("class" => "align_center"));
				$table->construct_row();
			}
			
			if($table->num_rows()  == 0)
			{
				$table->construct_cell($lang->no_language_files_admin_cp, array('colspan' => 3));
				$table->construct_row();
			}
			
			$table->output($lang->admin_cp);
		}
	}
	
	$page->output_footer();
}


if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_config_languages_start");
	
	$page->output_header($lang->languages);

	$sub_tabs['languages'] = array(
		'title' => $lang->languages,
		'link' => "index.php?module=config-languages",
		'description' => $lang->languages_desc
	);
	$sub_tabs['find_language'] = array(
		'title' => $lang->find_language_packs,
		'link' => "http://mybb.com/downloads/translations",
		'target' => "_blank"
	);

	$page->output_nav_tabs($sub_tabs, 'languages');
	
	$table = new Table;
	$table->construct_header($lang->languagevar);
	$table->construct_header($lang->version, array("class" => "align_center", "width" => 100));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 155));
	
	asort($languages);
	
	foreach($languages as $key1 => $langname1)
	{
		$langselectlangs[$key1] = $lang->sprintf($lang->edit_with, $langname1);
	}
	
	foreach($languages as $key => $langname)
	{
		include MYBB_ROOT."inc/languages/".$key.".php";
		
		if(!empty($langinfo['website']))
		{
			$author = "<a href=\"{$langinfo['website']}\" target=\"_blank\">{$langinfo['author']}</a>";
		}
		else
		{
			$author = $langinfo['author'];
		}
		
		$table->construct_cell("<strong>{$langinfo['name']}</strong><br /><small>{$author}</small>");
		$table->construct_cell($langinfo['version'], array("class" => "align_center"));
		
		$popup = new PopupMenu("laguage_{$key}", $lang->options);
		$popup->add_item($lang->edit_language_variables, "index.php?module=config-languages&amp;action=edit&amp;lang={$key}");
		foreach($langselectlangs as $key1 => $langname1)
		{
			$popup->add_item($langname1, "index.php?module=config-languages&amp;action=edit&amp;lang={$key}&amp;editwith={$key1}");
 		}
		$popup->add_item($lang->edit_properties, "index.php?module=config-languages&amp;action=edit_properties&amp;lang={$key}");
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();
	}
	
	if($table->num_rows()  == 0)
	{
		$table->construct_cell($lang->no_language, array('colspan' => 3));
		$table->construct_row();
	}
	
	$table->output($lang->installed_language_packs);
	
	$page->output_footer();
}

?>