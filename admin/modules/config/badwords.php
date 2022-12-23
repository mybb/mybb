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

$page->add_breadcrumb_item($lang->bad_words, "index.php?module=config-badwords");

$plugins->run_hooks("admin_config_badwords_begin");

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_config_badwords_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['badword']))
		{
			$errors[] = $lang->error_missing_bad_word;
		}

		if(strlen(trim($mybb->input['badword'])) > 100)
		{
			$errors[] = $lang->bad_word_max;
		}

		if(strlen($mybb->input['replacement']) > 100)
		{
			$errors[] = $lang->replacement_word_max;
		}

		if(!$errors)
		{
			$query = $db->simple_select("badwords", "bid", "badword = '".$db->escape_string($mybb->input['badword'])."'");

			if($db->num_rows($query))
			{
				$errors[] = $lang->error_bad_word_filtered;
			}
		}

		$badword = trim($mybb->input['badword']);

		if($mybb->get_input('regex', MyBB::INPUT_INT))
		{
			// Check validity of defined regular expression
			if((@preg_match('#'.$badword.'#is', '') === false))
			{
				$errors[] = $lang->error_invalid_regex;
			}
		}
		else
		{
			if(!isset($parser) || !is_object($parser))
			{
				require_once MYBB_ROOT."inc/class_parser.php";
				$parser = new postParser;
			}

			$badword = $parser->generate_regex($badword);
		}

		// Don't allow certain badword replacements to be added if it would cause an infinite recursive loop.
		if(@preg_match('#'.$badword.'#is', $mybb->input['replacement']))
		{
			$errors[] = $lang->error_replacement_word_invalid;
		}

		if(!$errors)
		{
			$new_badword = array(
				"badword" => $db->escape_string($mybb->input['badword']),
				"regex" => $mybb->get_input('regex', MyBB::INPUT_INT),
				"replacement" => $db->escape_string($mybb->input['replacement'])
			);

			$bid = $db->insert_query("badwords", $new_badword);

			$plugins->run_hooks("admin_config_badwords_add_commit");

			// Log admin action
			log_admin_action($bid, $mybb->input['badword']);

			$cache->update_badwords();
			flash_message($lang->success_added_bad_word, 'success');
			admin_redirect("index.php?module=config-badwords");
		}
	}

	$page->add_breadcrumb_item($lang->add_bad_word);
	$page->output_header($lang->bad_words." - ".$lang->add_bad_word);

	$sub_tabs['badwords'] = array(
		'title' => $lang->bad_word_filters,
		'description' => $lang->bad_word_filters_desc,
		'link' => "index.php?module=config-badwords"
	);

	$sub_tabs['add_badword'] = array(
		'title' => $lang->add_bad_word,
		'description' => $lang->add_bad_word_desc,
		'link' => "index.php?module=config-badwords&amp;action=add"
	);

	$page->output_nav_tabs($sub_tabs, "add_badword");

	$form = new Form("index.php?module=config-badwords&amp;action=add", "post", "add");

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form_container = new FormContainer($lang->add_bad_word);
	$form_container->output_row($lang->bad_word." <em>*</em>", $lang->bad_word_desc, $form->generate_text_box('badword', $mybb->get_input('badword'), array('id' => 'badword')), 'badword');
	$form_container->output_row($lang->replacement, $lang->replacement_desc, $form->generate_text_box('replacement', $mybb->get_input('replacement'), array('id' => 'replacement')), 'replacement');
	$form_container->output_row($lang->regex, $lang->regex_desc, $form->generate_yes_no_radio('regex', $mybb->get_input('regex', MyBB::INPUT_INT), array('id' => 'regex')), 'regex');
	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->save_bad_word);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("badwords", "*", "bid='".$mybb->get_input('bid', MyBB::INPUT_INT)."'");
	$badword = $db->fetch_array($query);

	// Does the bad word not exist?
	if(!$badword['bid'])
	{
		flash_message($lang->error_invalid_bid, 'error');
		admin_redirect("index.php?module=config-badwords");
	}

	// User clicked no
	if($mybb->get_input('no'))
	{
		admin_redirect("index.php?module=config-badwords");
	}

	$plugins->run_hooks("admin_config_badwords_delete");

	if($mybb->request_method == "post")
	{
		// Delete the bad word
		$db->delete_query("badwords", "bid='{$badword['bid']}'");

		$plugins->run_hooks("admin_config_badwords_delete_commit");

		// Log admin action
		log_admin_action($badword['bid'], $badword['badword']);

		$cache->update_badwords();

		flash_message($lang->success_deleted_bad_word, 'success');
		admin_redirect("index.php?module=config-badwords");
	}
	else
	{
		$page->output_confirm_action("index.php?module=config-badwords&action=delete&bid={$badword['bid']}", $lang->confirm_bad_word_deletion);
	}
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("badwords", "*", "bid='".$mybb->get_input('bid', MyBB::INPUT_INT)."'");
	$badword = $db->fetch_array($query);

	// Does the bad word not exist?
	if(!$badword['bid'])
	{
		flash_message($lang->error_invalid_bid, 'error');
		admin_redirect("index.php?module=config-badwords");
	}

	$plugins->run_hooks("admin_config_badwords_edit");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['badword']))
		{
			$errors[] = $lang->error_missing_bad_word;
		}

		if(strlen(trim($mybb->input['badword'])) > 100)
		{
			$errors[] = $lang->bad_word_max;
		}

		if(strlen($mybb->input['replacement']) > 100)
		{
			$errors[] = $lang->replacement_word_max;
		}

		if(!$errors)
		{
			$query = $db->simple_select("badwords", "bid", "badword = '".$db->escape_string($mybb->input['badword'])."' AND bid != '".$badword['bid']."'");

			if($db->num_rows($query))
			{
				$errors[] = $lang->error_bad_word_filtered;
			}
		}

		$badword_check = trim($mybb->input['badword']);

		if($mybb->get_input('regex', MyBB::INPUT_INT))
		{
			// Check validity of defined regular expression
			if((@preg_match('#'.$badword_check.'#is', '') === false))
			{
				$errors[] = $lang->error_invalid_regex;
			}
		}
		else
		{
			if(!isset($parser) || !is_object($parser))
			{
				require_once MYBB_ROOT."inc/class_parser.php";
				$parser = new postParser;
			}

			$badword_check = $parser->generate_regex($badword_check);
		}

		// Don't allow certain badword replacements to be added if it would cause an infinite recursive loop.
		if(@preg_match('#'.$badword_check.'#is', $mybb->input['replacement']))
		{
			$errors[] = $lang->error_replacement_word_invalid;
		}

		if(!$errors)
		{
			$updated_badword = array(
				"badword" => $db->escape_string($mybb->input['badword']),
				"regex" => $mybb->get_input('regex', MyBB::INPUT_INT),
				"replacement" => $db->escape_string($mybb->input['replacement'])
			);

			$plugins->run_hooks("admin_config_badwords_edit_commit");

			$db->update_query("badwords", $updated_badword, "bid='{$badword['bid']}'");

			// Log admin action
			log_admin_action($badword['bid'], $mybb->input['badword']);

			$cache->update_badwords();

			flash_message($lang->success_updated_bad_word, 'success');
			admin_redirect("index.php?module=config-badwords");
		}
	}

	$page->add_breadcrumb_item($lang->edit_bad_word);
	$page->output_header($lang->bad_words." - ".$lang->edit_bad_word);

	$sub_tabs['editbadword'] = array(
		'title' => $lang->edit_bad_word,
		'description' => $lang->edit_bad_word_desc,
		'link' => "index.php?module=config-badwords"
	);

	$page->output_nav_tabs($sub_tabs, "editbadword");

	$form = new Form("index.php?module=config-badwords&amp;action=edit&amp;bid={$badword['bid']}", "post");

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
	$form_container->output_row($lang->regex, $lang->regex_desc, $form->generate_yes_no_radio('regex', (int)$badword_data['regex'], array('id' => 'regex')), 'regex');
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
		'description' => $lang->bad_word_filters_desc,
		'link' => "index.php?module=config-badwords"
	);

	$sub_tabs['add_badword'] = array(
		'title' => $lang->add_bad_word,
		'description' => $lang->add_bad_word_desc,
		'link' => "index.php?module=config-badwords&amp;action=add"
	);

	$plugins->run_hooks("admin_config_badwords_start");

	$page->output_nav_tabs($sub_tabs, "badwords");

	$query = $db->simple_select("badwords", "COUNT(bid) AS badwords");
	$total_rows = $db->fetch_field($query, "badwords");

	$pagenum = $mybb->get_input('page', MyBB::INPUT_INT);
	if($pagenum)
	{
		$start = ($pagenum - 1) * 20;
		$pages = ceil($total_rows / 20);
		if($pagenum > $pages)
		{
			$start = 0;
			$pagenum = 1;
		}
	}
	else
	{
		$start = 0;
		$pagenum = 1;
	}

	$table = new Table;
	$table->construct_header($lang->bad_word);
	$table->construct_header($lang->replacement, array("width" => "50%"));
	$table->construct_header($lang->regex, array("class" => "align_center", "width" => "20%"));
	$table->construct_header($lang->controls, array("class" => "align_center", "width" => 150, "colspan" => 2));

	$query = $db->simple_select("badwords", "*", "", array('limit_start' => $start, 'limit' => 20, "order_by" => "badword", "order_dir" => "asc"));
	while($badword = $db->fetch_array($query))
	{
		$badword['badword'] = htmlspecialchars_uni($badword['badword']);
		$badword['replacement'] = htmlspecialchars_uni($badword['replacement']);
		if(!$badword['replacement'])
		{
			$badword['replacement'] = '*****';
		}

		$regex = $lang->no;
		if($badword['regex'])
		{
			$regex = $lang->yes;
		}

		$table->construct_cell($badword['badword']);
		$table->construct_cell($badword['replacement']);
		$table->construct_cell($regex, array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=config-badwords&amp;action=edit&amp;bid={$badword['bid']}\">{$lang->edit}</a>", array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=config-badwords&amp;action=delete&amp;bid={$badword['bid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_bad_word_deletion}');\">{$lang->delete}</a>", array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_bad_words, array("colspan" => 4));
		$table->construct_row();
	}

	$table->output($lang->bad_word_filters);

	echo "<br />".draw_admin_pagination($pagenum, "20", $total_rows, "index.php?module=config-badwords&amp;page={page}");

	$page->output_footer();
}

