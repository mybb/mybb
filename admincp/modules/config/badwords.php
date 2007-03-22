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

$page->add_breadcrumb_item("Bad Words", "index.php?".SID."&module=config/badwords");

if($mybb->input['action'] == "add" && $mybb->request_method == "post")
{
	if(!trim($mybb->input['badword']))
	{
		$errors[] = "You did not enter a bad word";
	}

	if(!$errors)
	{
		$new_badword = array(
			"badword" => $db->escape_string($mybb->input['badword']),
			"replacement" => $db->escape_string($mybb->input['replacement'])
		);

		$db->insert_query("badwords", $new_badword);

		$cache->update_badwords();
		flash_message('The word has successfully been added to the list of bad words.', 'success');
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
		flash_message('The specified bad word does not exist.', 'error');
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

		flash_message('The bad word has been deleted.', 'success');
		admin_redirect("index.php?".SID."&module=config/badwords");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&module=config/badwords&action=delete&bid={$badword['bid']}", "Are you sure you wish to delete this bad word?");
	}
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("badwords", "*", "bid='".intval($mybb->input['bid'])."'");
	$badword = $db->fetch_array($query);

	// Does the bad word not exist?
	if(!$badword['bid'])
	{
		flash_message('The specified bad word does not exist.', 'error');
		admin_redirect("index.php?".SID."&module=config/badwords");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['badword']))
		{
			$errors[] = "You did not enter a bad word";
		}

		if(!$errors)
		{
			$updated_badword = array(
				"badword" => $db->escape_string($mybb->input['badword']),
				"replacement" => $db->escape_string($mybb->input['replacement'])
			);

			$db->update_query("badwords", $updated_badword, "bid='{$badword['bid']}'");

			$cache->update_badwords();

			flash_message('The bad word has successfully been updated.', 'success');
			admin_redirect("index.php?".SID."&module=config/badwords");
		}
	}

	$page->add_breadcrumb_item("Edit Bad Word");
	$page->output_header("Bad Words - Edit Bad Word");
	
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

	$form_container = new FormContainer("Edit Bad Word");
	$form_container->output_row("Bad Word <em>*</em>", "Enter the word which you wish to be filtered", $form->generate_text_box('badword', $badword_data['badword'], array('id' => 'badword')), 'badword');
	$form_container->output_row("Replacement", "Enter the string which will replace this badword (Leave blank to use asterisks)", $form->generate_text_box('replacement', $badword_data['replacement'], array('id' => 'replacement')), 'replacement');
	$form_container->end();
	$buttons[] = $form->generate_submit_button("Save Bad Word");
	$form->output_submit_wrapper($buttons);
	$form->end();
}

if(!$mybb->input['action'])
{
	$page->output_header("Bad Words");

	$sub_tabs['badwords'] = array(
		'title' => "Bad Word Filters",
		'description' => "This feature allows you to manage a listing of words which are automatically replaced in posts on your forum. It is useful for replacing swear words and such."
	);

	$page->output_nav_tabs($sub_tabs, "badwords");

	$table = new Table;
	$table->construct_header("Bad Word");
	$table->construct_header("Replacement", array("width" => "50%"));
	$table->construct_header("Controls", array("class" => "align_center", "width" => 150, "colspan" => 2));

	$query = $db->simple_select("badwords", "*", "", array("order_by" => "badword", "order_dir" => "asc"));
	while($badword = $db->fetch_array($query))
	{
		$badword['badword'] = htmlspecialchars_uni($badword['badword']);
		$badword['replacement'] = htmlspecialchars_uni($badword['replacement']);
		$table->construct_cell($badword['badword']);
		$table->construct_cell($badword['replacement']);
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/badwords&amp;action=edit&amp;bid={$badword['bid']}\">Edit</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/badwords&amp;action=delete&amp;bid={$badword['bid']}\" onclick=\"return AdminCP.deleteConfirmation(this, 'Are you sure you wish to delete this bad word?');\">Delete</a>", array("class" => "align_center"));
		$table->construct_row();
	}
	
	if(count($table->rows) == 0)
	{
		$table->construct_cell("There are no bad words currently set at this time.", array("colspan" => 4));
		$table->construct_row();
	}
	
	$table->output("Bad Word Filters");

	$form = new Form("index.php?".SID."&amp;module=config/badwords&amp;action=add", "post", "add");
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	$form_container = new FormContainer("Add a Bad Word");
	$form_container->output_row("Bad Word <em>*</em>", "Enter the word which you wish to be filtered", $form->generate_text_box('badword', $mybb->input['badword'], array('id' => 'badword')), 'badword');
	$form_container->output_row("Replacement", "Enter the string which will replace this badword (Leave blank to use asterisks)", $form->generate_text_box('replacement', $mybb->input['replacement'], array('id' => 'replacement')), 'replacement');
	$form_container->end();
	$buttons[] = $form->generate_submit_button("Save Bad Word");
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
 }
?>