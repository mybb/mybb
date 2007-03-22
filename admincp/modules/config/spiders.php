<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

$page->add_breadcrumb_item("Spiders / Bots", "index.php?".SID."&module=config/spiders");

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = "You did not enter a name for this bot";
		}

		if(!trim($mybb->input['useragent']))
		{
			$errors[] = "You did not enter a user agent string for this bot";
		}

		if(!$errors)
		{
			$new_spider = array(
				"name" => $db->escape_string($mybb->input['name']),
				"theme" => intval($mybb->input['theme']),
				"language" => $db->escape_string($mybb->input['language']),
				"usergroup" => intval($mybb->input['usergroup']),
				"useragent" => $db->escape_string($mybb->input['useragent']),
				"lastvisit" => 0
			);
			$db->insert_query("spiders", $new_spider);

			$cache->update_spiders();

			flash_message('The bot has successfully been created.', 'success');
			admin_redirect("index.php?".SID."&module=config/spiders");
		}
	}

	$page->add_breadcrumb_item("Add New Bot");
	$page->output_header("Spiders / Bots - Add New Bot");
	
	$form = new Form("index.php?".SID."&module=config/spiders&action=add", "post");

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer("Add New Bot");
	$form_container->output_row("Name <em>*</em>", "Enter the name of this bot which you want to identify it by", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row("User Agent String", "Enter the string which will be matched against the bots user agent (partial matches are accepted)", $form->generate_text_box('useragent', $mybb->input['useragent'], array('id' => 'useragent')), 'useragent');
	
	$languages = array('' => 'Use Board Default');
	$languages = array_merge($languages, $lang->get_languages());
	$form_container->output_row("Language", "Select the language pack the bot will use when viewing the board.", $form->generate_select_box("language", $languages, $mybb->input['language'], array("id" => "language")), 'language');
	
	$form_container->output_row("Theme", "Select the theme the bot will use when viewing the board.", build_theme_select("theme", $mybb->input['theme'], 0, "", 1), 'theme');

	$query = $db->simple_select("usergroups", "*", "", array("order_by" => "title", "order_dir" => "asc"));
	while($usergroup = $db->fetch_array($query))
	{
		$usergroups[$usergroup['gid']] = $usergroup['title'];
	}
	if(!$mybb->input['usergroup'])
	{
		$mybb->input['usergroup'] = 1;
	}
	$form_container->output_row("User Group", "Select the user group permissions will be applied from for this board (Note: It is not recommended you change this from the default Guests group)", $form->generate_select_box("usergroup", $usergroups, $mybb->input['usergroup'], array("id" => "usergroup")), 'usergroup');


	$form_container->end();
	$buttons[] = $form->generate_submit_button("Save Bot");
	$form->output_submit_wrapper($buttons);
	$form->end();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("spiders", "*", "sid='".intval($mybb->input['sid'])."'");
	$spider = $db->fetch_array($query);

	// Does the spider not exist?
	if(!$spider['sid'])
	{
		flash_message('The specified bot does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/spiders");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=config/spiders");
	}

	if($mybb->request_method == "post")
	{
		// Delete the spider
		$db->delete_query("spiders", "sid='{$spider['sid']}'");

		$cache->update_spiders();

		flash_message('The bot has been deleted.', 'success');
		admin_redirect("index.php?".SID."&module=config/spiders");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&module=config/spiders&action=delete&sid={$spider['sid']}", "Are you sure you wish to delete this bot?");
	}
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("spiders", "*", "sid='".intval($mybb->input['sid'])."'");
	$spider = $db->fetch_array($query);

	// Does the spider not exist?
	if(!$spider['sid'])
	{
		flash_message('The specified spider does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/badwords");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = "You did not enter a name for this bot";
		}

		if(!trim($mybb->input['useragent']))
		{
			$errors[] = "You did not enter a user agent string for this bot";
		}

		if(!$errors)
		{
			$updated_spider = array(
				"name" => $db->escape_string($mybb->input['name']),
				"theme" => intval($mybb->input['theme']),
				"language" => $db->escape_string($mybb->input['language']),
				"usergroup" => intval($mybb->input['usergroup']),
				"useragent" => $db->escape_string($mybb->input['useragent'])
			);
			$db->update_query("spiders", $updated_spider, "sid='{$spider['sid']}'");

			$cache->update_spiders();

			flash_message('The bot has successfully been updated.', 'success');
			admin_redirect("index.php?".SID."&module=config/spiders");
		}
	}

	$page->add_breadcrumb_item("Edit Bot");
	$page->output_header("Spiders / Bots - Edit Bot");
	
	$form = new Form("index.php?".SID."&module=config/spiders&action=edit&sid={$spider['sid']}", "post");

	if($errors)
	{
		$page->output_inline_error($errors);
		$spider_data = $mybb->input;
	}
	else
	{
		$spider_data = $spider;
	}

	$form_container = new FormContainer("Edit Bot");
	$form_container->output_row("Name <em>*</em>", "Enter the name of this bot which you want to identify it by", $form->generate_text_box('name', $spider_data['name'], array('id' => 'name')), 'name');
	$form_container->output_row("User Agent String", "Enter the string which will be matched against the bots user agent (partial matches are accepted)", $form->generate_text_box('useragent', $spider_data['useragent'], array('id' => 'useragent')), 'useragent');
	
	$languages = array('' => 'Use Board Default');
	$languages = array_merge($languages, $lang->get_languages());
	$form_container->output_row("Language", "Select the language pack the bot will use when viewing the board.", $form->generate_select_box("language", $languages, $spider_data['language'], array("id" => "language")), 'language');

	$form_container->output_row("Theme", "Select the theme the bot will use when viewing the board.", build_theme_select("theme", $spider_data['theme'], 0, "", 1), 'theme');

	$query = $db->simple_select("usergroups", "*", "", array("order_by" => "title", "order_dir" => "asc"));
	while($usergroup = $db->fetch_array($query))
	{
		$usergroups[$usergroup['gid']] = $usergroup['title'];
	}
	if(!$mybb->input['usergroup'])
	{
		$mybb->input['usergroup'] = 1;
	}
	$form_container->output_row("User Group", "Select the user group permissions will be applied from for this board (Note: It is not recommended you change this from the default Guests group)", $form->generate_select_box("usergroup", $usergroups, $mybb->input['usergroup'], array("id" => "usergroup")), 'usergroup');

	$form_container->end();
	$buttons[] = $form->generate_submit_button("Save Bot");
	$form->output_submit_wrapper($buttons);
	$form->end();
}

if(!$mybb->input['action'])
{
	$page->output_header("Spiders / Bots");

	$sub_tabs['spiders'] = array(
		'title' => "Spiders / Bots",
		'description' => "This section allows you to manage the search engine spiders &amp; bots automatically detected by your forum. You're also able to see when a particular bot last visited."
	);
	$sub_tabs['add_spider'] = array(
		'title' => "Add New Bot",
		'link' => "index.php?".SID."&amp;module=config/spiders&amp;action=add"
	);

	$page->output_nav_tabs($sub_tabs, "spiders");

	$table = new Table;
	$table->construct_header("Bot");
	$table->construct_header("Last Visit", array("class" => "align_center", "width" => "200"));
	$table->construct_header("Controls", array("class" => "align_center", "width" => 150, "colspan" => 2));

	$query = $db->simple_select("spiders", "*", "", array("order_by" => "lastvisit", "order_dir" => "desc"));
	while($spider = $db->fetch_array($query))
	{
		$spider['name'] = htmlspecialchars_uni($spider['name']);
		if($spider['lastvisit'])
		{
			$lastvisit = my_date($mybb->settings['dateformat'], $spider['lastvisit']).", ".my_date($mybb->settings['timeformat'], $spider['lastvisit']);
		}
		else
		{
			$lastvisit = 'Never';
		}
		$table->construct_cell($spider['name']);
		$table->construct_cell($lastvisit, array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/spiders&amp;action=edit&amp;sid={$spider['sid']}\">Edit</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/spiders&amp;action=delete&amp;sid={$spider['sid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this spider?');\">Delete</a>", array("class" => "align_center"));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are no search engine spiders or web crawlers being tracked by this forum.", array("colspan" => 4));
		$table->construct_row();
	}

	$table->output("Spiders / Bots");

	$page->output_footer();
 }
?>