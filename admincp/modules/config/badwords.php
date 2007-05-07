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

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->bad_words, "index.php?".SID."&module=config/badwords");

if($mybb->input['action'] == "add" && $mybb->request_method == "post")
{
	if(!trim($mybb->input['badword']))
	{
		$errors[] = $lang->error_missing_bad_word;
	}

	if(!$errors)
	{
		$new_badword = array(
			"badword" => $db->escape_string($mybb->input['badword']),
			"replacement" => $db->escape_string($mybb->input['replacement'])
		);

		$db->insert_query("badwords", $new_badword);

		$cache->update_badwords();
		flash_message($lang->success_added_bad_word, 'success');
		admin_redirect("index.php?".SID."&module=config/badwords");
	}
	else
	{
		$mybb->input['action'] = '';
	}
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("badwords", "*", "bid='".intval($mybb->input['bid'])."'");
	$badword = $db->fetch_array($query);

	// Does the bad word not exist?
	if(!$badword['bid'])
	{
		flash_message($lang->error_invalid_bid, 'error');
		admin_redirect("index.php?".SID."&module=config/badwords");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=config/badwords");
	}

	if($mybb->request_method == "post")
	{
		// Delete the bad word
		$db->delete_query("badwords", "bid='{$badword['bid']}'");

		$cache->update_badwords();

		flash_message($lang->success_deleted_bad_word, 'success');
		admin_redirect("index.php?".SID."&module=config/badwords");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&module=config/badwords&action=delete&bid={$badword['bid']}", $lang->confirm_bad_word_deletion);
	}
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("badwords", "*", "bid='".intval($mybb->input['bid'])."'");
	$badword = $db->fetch_array($query);

	// Does the bad word not exist?
	if(!$badword['bid'])
	{
		flash_message($lang->error_invalid_bid, 'error');
		admin_redirect("index.php?".SID."&module=config/badwords");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['badword']))
		{
			$errors[] = $lang->error_missing_bad_word;
		}

		if(!$errors)
		{
			$updated_badword = array(
				"badword" => $db->escape_string($mybb->input['badword']),
				"replacement" => $db->escape_string($mybb->input['replacement'])
			);

			$db->update_query("badwords", $updated_badword, "bid='{$badword['bid']}'");

			$cache->update_badwords();

			flash_message($lang->success_updated_bad_word, 'success');
			admin_redirect("index.php?".SID."&module=config/badwords");
		}
	}

	$page->add_breadcrumb_item($lang->edit_bad_word);
	$page->output_header($lang->bad_words." - ".$lang->edit_bad_word);
	
	$form = new Form("index.php?".SID."&amp;module=config/badwords&amp;action=edit&amp;bid={$badword['bid']}", "post");

	if($errors)
	{
		$page->output_inline_error($errors);
		$badword_data = $mybb->input;
	}
	else
	{
		$badword_data = $badword;
	}

	$form_container = new FormContainer($lang->edit_bad_word);
	$form_container->output_row($lang->bad_word." <em>*</em>", $lang->bad_word_desc, $form->generate_text_box('badword', $badword_data['badword'], array('id' => 'badword')), 'badword');
	$form_container->output_row($lang->replacement, $lang->replacement_desc, $form->generate_text_box('replacement', $badword_data['replacement'], array('id' => 'replacement')), 'replacement');
	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->save_bad_word);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->bad_words);

	$sub_tabs['badwords'] = array(
		'title' => $lang->bad_word_filters,
		'description' => $lang->bad_word_filters_desc
	);

	$page->output_nav_tabs($sub_tabs, "badwords");

	$table = new Table;
	$table->construct_header($lang->bad_word);
	$table->construct_header($lang->replacements, array("width" => "50%"));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150, "colspan" => 2));

	$query = $db->simple_select("badwords", "*", "", array("order_by" => "badword", "order_dir" => "asc"));
	while($badword = $db->fetch_array($query))
	{
		$badword['badword'] = htmlspecialchars_uni($badword['badword']);
		$badword['replacement'] = htmlspecialchars_uni($badword['replacement']);
		$table->construct_cell($badword['badword']);
		$table->construct_cell($badword['replacement']);
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/badwords&amp;action=edit&amp;bid={$badword['bid']}\">{$lang->edit}</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/badwords&amp;action=delete&amp;bid={$badword['bid']}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_bad_word_deletion}');\">{$lang->delete}</a>", array("class" => "align_center"));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell($lang->no_bad_words, array("colspan" => 4));
		$table->construct_row();
	}
	
	$table->output($lang->bad_word_filters);

	$form = new Form("index.php?".SID."&amp;module=config/badwords&amp;action=add", "post", "add");
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	$form_container = new FormContainer($lang->add_bad_word);
	$form_container->output_row($lang->bad_word." <em>*</em>", $lang->bad_word_desc, $form->generate_text_box('badword', $mybb->input['badword'], array('id' => 'badword')), 'badword');
	$form_container->output_row($lang->replacement, $lang->replacement_desc, $form->generate_text_box('replacement', $mybb->input['replacement'], array('id' => 'replacement')), 'replacement');
	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->add_bad_word);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
 }
?>