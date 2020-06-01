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

$page->add_breadcrumb_item($lang->preferences_and_personal_notes, "index.php?module=home-preferences");

$plugins->run_hooks("admin_home_preferences_begin");

if($mybb->input['action'] == "recovery_codes")
{
	$page->add_breadcrumb_item($lang->recovery_codes, "index.php?module=home-preferences&action=recovery_codes");

	// First: regenerate the codes
	$codes = generate_recovery_codes();
	$db->update_query("adminoptions", array("recovery_codes" => $db->escape_string(my_serialize($codes))), "uid='{$mybb->user['uid']}'");

	// And now display them
	$page->output_header($lang->recovery_codes);

	$table = new Table;
	$table->construct_header($lang->recovery_codes);

	$table->construct_cell("{$lang->recovery_codes_warning} <strong><a href=\"javascript:window.print()\">{$lang->print_recovery_codes}</a></strong>");
	$table->construct_row();

	$table->construct_cell(implode("<br />", $codes));
	$table->construct_row();

	$table->output($lang->recovery_codes);

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	require_once MYBB_ROOT."inc/3rdparty/2fa/GoogleAuthenticator.php";
	$auth = new PHPGangsta_GoogleAuthenticator;

	$plugins->run_hooks("admin_home_preferences_start");

	if($mybb->request_method == "post")
	{
		$query = $db->simple_select("adminoptions", "permissions, defaultviews, authsecret, recovery_codes", "uid='{$mybb->user['uid']}'");
		$adminopts = $db->fetch_array($query);

		$secret = $adminopts['authsecret'];
		// Was the option changed? empty = disabled so ==
		if($mybb->input['2fa'] == empty($secret))
		{
			// 2FA was enabled -> create secret and log
			if($mybb->input['2fa'])
			{
				$secret = $auth->createSecret();
				// We don't want to close this session now
				$db->update_query("adminsessions", array("authenticated" => 1), "sid='".$db->escape_string($mybb->cookies['adminsid'])."'");
				log_admin_action("enabled");
			}
			// 2FA was disabled -> clear secret
			else
			{
				$secret = "";
				$adminopts['recovery_codes'] = "";
				log_admin_action("disabled");
			}
		}

		$sqlarray = array(
			"notes" => $db->escape_string($mybb->input['notes']),
			"cpstyle" => $db->escape_string($mybb->input['cpstyle']),
			"cplanguage" => $db->escape_string($mybb->input['cplanguage']),
			"permissions" => $db->escape_string($adminopts['permissions']),
			"defaultviews" => $db->escape_string($adminopts['defaultviews']),
			"uid" => $mybb->user['uid'],
			"codepress" => $mybb->get_input('codepress', MyBB::INPUT_INT), // It's actually CodeMirror but for compatibility purposes lets leave it codepress
			"authsecret" => $db->escape_string($secret),
			"recovery_codes" => $db->escape_string($adminopts['recovery_codes']),
		);

		$db->replace_query("adminoptions", $sqlarray, "uid");

		$plugins->run_hooks("admin_home_preferences_start_commit");

		flash_message($lang->success_preferences_updated, 'success');
		admin_redirect("index.php?module=home-preferences");
	}

	$page->output_header($lang->preferences_and_personal_notes);

	$sub_tabs['preferences'] = array(
		'title' => $lang->preferences_and_personal_notes,
		'link' => "index.php?module=home-preferences",
		'description' => $lang->prefs_and_personal_notes_description
	);

	$page->output_nav_tabs($sub_tabs, 'preferences');

	$query = $db->simple_select("adminoptions", "notes, cpstyle, cplanguage, codepress, authsecret", "uid='".$mybb->user['uid']."'", array('limit' => 1));
	$admin_options = $db->fetch_array($query);

	$form = new Form("index.php?module=home-preferences", "post");
	$dir = @opendir(MYBB_ADMIN_DIR."/styles");

	$folders = array();
	while($folder = readdir($dir))
	{
		if($folder != "." && $folder != ".." && @file_exists(MYBB_ADMIN_DIR."/styles/$folder/main.css"))
		{
			$folders[$folder] = ucfirst($folder);
		}
	}
	closedir($dir);
	ksort($folders);
	$styles = array_merge(array('' => $lang->use_default), $folders);
	$setting_code = $form->generate_select_box("cpstyle", $styles, $admin_options['cpstyle']);

	$languages = array_merge(array('' => $lang->use_default), $lang->get_languages(1));
	$language_code = $form->generate_select_box("cplanguage", $languages, $admin_options['cplanguage']);

	$form_container = new FormContainer($lang->preferences);
	$form_container->output_row_header($lang->global_preferences);	
	$form_container->output_row($lang->acp_theme, $lang->select_acp_theme, $setting_code);
	$form_container->output_row($lang->acp_language, $lang->select_acp_language, $language_code);
	$form_container->output_row($lang->codemirror, $lang->use_codemirror_desc, $form->generate_on_off_radio('codepress', $admin_options['codepress']));
	
	// If 2FA is enabled we need to display a link to the recovery codes page
	if(!empty($admin_options['authsecret']))
	{
		$lang->use_2fa_desc .= "<br />".$lang->recovery_codes_desc." ".$lang->recovery_codes_warning;
	}	
	$form_container->output_row($lang->my2fa, $lang->use_2fa_desc, $form->generate_on_off_radio('2fa', (int)!empty($admin_options['authsecret'])));
	
	if(!empty($admin_options['authsecret']))
	{
		$qr = $auth->getQRCodeGoogleUrl($mybb->user['username']."@".str_replace(" ", "", $mybb->settings['bbname']), $admin_options['authsecret']);
		$form_container->output_row($lang->my2fa_qr . "<br /><img src=\"{$qr}\"");
	}	
				
	$form_container->end();

	$table = new Table;

	$table->construct_header($lang->notes_not_shared);

	$table->construct_cell($form->generate_text_area("notes", $admin_options['notes'], array('style' => 'width: 99%; height: 300px;')));
	$table->construct_row();

	$table->output($lang->personal_notes);

	$buttons[] = $form->generate_submit_button($lang->save_notes_and_prefs);
	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

/**
 * Generate 10 random recovery codes, each with a length of 6 and without duplicates
 *
 * @return array
 */
function generate_recovery_codes()
{
	$t = array();
	while(count($t) < 10)
	{
		$g = random_str(6);
		if(!in_array($g, $t))
		{
			$t[] = $g;
		}
	}
	return $t;
}
