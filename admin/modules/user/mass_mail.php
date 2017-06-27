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

require_once MYBB_ROOT."/inc/functions_massmail.php";

$page->add_breadcrumb_item($lang->mass_mail, "index.php?module=user-mass_mail");

if($mybb->input['action'] == "send" || $mybb->input['action'] == "archive" || !$mybb->input['action'])
{
	$sub_tabs['mail_queue'] = array(
		'title' => $lang->mass_mail_queue,
		'link' => 'index.php?module=user-mass_mail',
		'description' => $lang->mass_mail_queue_desc
	);

	$sub_tabs['send_mass_mail'] = array(
		'title' => $lang->create_mass_mail,
		'link' => 'index.php?module=user-mass_mail&action=send',
		'description' => $lang->create_mass_mail_desc
	);

	$sub_tabs['archive'] = array(
		'title' => $lang->mass_mail_archive,
		'link' => 'index.php?module=user-mass_mail&action=archive',
		'description' => $lang->mass_mail_archive_desc
	);
}

$plugins->run_hooks("admin_user_mass_email");

if($mybb->input['action'] == "edit")
{
	$page->add_breadcrumb_item($lang->edit_mass_mail);

	$query = $db->simple_select("massemails", "*", "mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");
	$email = $db->fetch_array($query);
	if(!$email['mid'])
	{
		flash_message($lang->error_invalid_mid, 'error');
		admin_redirect("index.php?module=user-mass_mail");
	}

	$plugins->run_hooks("admin_user_mass_email_edit_start");

	if($email['conditions'] != '')
	{
		$email['conditions'] = my_unserialize($email['conditions']);
	}

	$sub_tabs['edit_mass_mail'] = array(
		'title' => $lang->edit_mass_mail,
		'link' => 'index.php?module=user-mass_mail&amp;action=edit&amp;mid='.$email['mid'],
		'description' => $lang->edit_mass_mail_desc
	);

	$replacement_fields = array(
		"{username}" => $lang->username,
		"{email}" => $lang->email_addr,
		"{bbname}" => $lang->board_name,
		"{bburl}" => $lang->board_url
	);

	$html_personalisation = $text_personalisation = "<script type=\"text/javascript\">\n<!--\ndocument.write('{$lang->personalize_message} ";
	foreach($replacement_fields as $value => $name)
	{
		$html_personalisation .= " [<a href=\"#\" onclick=\"insertText(\'{$value}\', \'htmlmessage\'); return false;\">{$name}</a>], ";
		$text_personalisation .= " [<a href=\"#\" onclick=\"insertText(\'{$value}\', \'message\'); return false;\">{$name}</a>], ";
	}
	$html_personalisation = substr($html_personalisation, 0, -2)."');\n// --></script>\n";
	$text_personalisation = substr($text_personalisation, 0, -2)."');\n// --></script>\n";

	$localized_time_offset = (float)$mybb->user['timezone']*3600 + $mybb->user['dst']*3600;
	
	// All done here
	if($mybb->request_method == "post")
	{
		// Sending this message now
		if($mybb->input['delivery_type'] == "now")
		{
			$delivery_date = TIME_NOW;
		}
		// Delivering in the future
		else
		{
			if(stristr($mybb->input['deliverytime_time'], "pm"))
			{
				$mybb->input['deliveryhour'] += 12;
			}

			$exploded = explode(':', $mybb->input['endtime_time']);
			$mybb->input['deliveryhour'] = (int)$exploded[0];

			$exploded = explode(' ', $exploded[1]);
			$mybb->input['deliveryminute'] = (int)$exploded[0];

			$delivery_date = gmmktime($mybb->input['deliveryhour'], $mybb->input['deliveryminute'], 0, $mybb->input['endtime_month'], $mybb->input['endtime_day'], $mybb->input['endtime_year']) - $localized_time_offset;
			if($delivery_date <= TIME_NOW)
			{
				$errors[] = $lang->error_only_in_future;
			}
		}

		// Need to perform the search to fetch the number of users we're emailing
		$member_query = build_mass_mail_query($mybb->input['conditions']);
		$query = $db->simple_select("users u", "COUNT(uid) AS num", $member_query);
		$num = $db->fetch_field($query, "num");

		if($num == 0)
		{
			$errors[] = $lang->error_no_users;
		}

		if(!trim($mybb->input['subject']))
		{
			$errors[] = $lang->error_missing_subject;
		}

		if($mybb->input['type'] == 1)
		{
			if(!$mybb->input['message'])
			{
				$errors[] = $lang->error_missing_message;
			}
		}
		else
		{
			if($mybb->input['format'] == 2 && $mybb->input['automatic_text'] == 0 && !$mybb->input['message'])
			{
				$errors[] = $lang->error_missing_plain_text;
			}

			if(($mybb->input['format'] == 1 || $mybb->input['format'] == 2) && !$mybb->input['htmlmessage'])
			{
				$errors[] = $lang->error_missing_html;
			}
			else if($mybb->input['format'] == 0 && !$mybb->input['message'])
			{
				$errors[] = $lang->error_missing_plain_text;
			}
		}

		if(!$errors)
		{
			// Sending via a PM
			if($mybb->input['type'] == 1)
			{
				$mybb->input['format'] = 0;
				$mybb->input['htmlmessage'] = '';
			}
			// Sending via email
			else
			{
				// Do we need to generate a text based version?
				if($mybb->input['format'] == 2 && $mybb->input['automatic_text'])
				{
					$mybb->input['message'] = create_text_message($mybb->input['htmlmessage']);
				}
				else if($mybb->input['format'] == 1)
				{
					$mybb->input['message'] = '';
				}
				else if($mybb->input['format'] == 0)
				{
					$mybb->input['htmlmessage'] = '';
				}
			}

			// Mark as queued for delivery
			$updated_email = array(
				"status" => 1,
				"senddate" => $delivery_date,
				"totalcount" => $num,
				"conditions" => $db->escape_string(my_serialize($mybb->input['conditions'])),
				"message" => $db->escape_string($mybb->input['message']),
				"subject" => $db->escape_string($mybb->input['subject']),
				"htmlmessage" => $db->escape_string($mybb->input['htmlmessage']),
				"format" => $mybb->get_input('format', MyBB::INPUT_INT),
				"type" => $mybb->get_input('type', MyBB::INPUT_INT),
				"perpage" => $mybb->get_input('perpage', MyBB::INPUT_INT)
			);

			$plugins->run_hooks("admin_user_mass_email_edit_commit");

			$db->update_query("massemails", $updated_email, "mid='{$email['mid']}'");

			flash_message($lang->success_mass_mail_saved, 'success');
			admin_redirect("index.php?module=user-mass_mail");
		}
	}

	$page->output_header($lang->edit_mass_mail);

	$page->output_nav_tabs($sub_tabs, 'edit_mass_mail');

	// If we have any error messages, show them
	if($errors)
	{
		$page->output_inline_error($errors);
		$input = $mybb->input;
	}
	else
	{
		$input = $email;

		if($email['senddate'] != 0)
		{
			if($email['senddate'] <= TIME_NOW)
			{
				$input['delivery_type'] = "now";
				$delivery_type_checked['now'] = " checked=\"checked\"";
			}
			else
			{
				// correct date by timezone and dst
				$offset = 
				$input['delivery_type'] = "future";
				$time = gmdate("d-n-Y", $email['senddate'] + $localized_time_offset);
				$time = explode('-', $time);
				$input['deliverymonth'] = (int)$time[1];
				$input['deliveryday'] = (int)$time[0];
				$input['deliveryyear'] = (int)$time[2];
				$input['endtime_time'] = gmdate($mybb->settings['timeformat'], $email['senddate'] + $localized_time_offset);
				$delivery_type_checked['future'] = " checked=\"checked\"";
			}
		}
		else
		{
			$input['delivery_type'] = "now";
			$delivery_type_checked['now'] = " checked=\"checked\"";
		}
	}
	
	if(!$input['endtime_time'])
	{
		$input['endtime_time'] = gmdate($mybb->settings['timeformat'], TIME_NOW + $localized_time_offset);
	}

	if(!$input['deliveryyear'])
	{
		$enddateyear = gmdate('Y', TIME_NOW + $localized_time_offset);
	}
	else
	{
		$enddateyear = (int)$input['deliveryyear'];
	}

	if(!$input['deliverymonth'])
	{
		$input['enddatemonth'] = gmdate('n', TIME_NOW + $localized_time_offset);
	}
	else
	{
		$input['enddatemonth'] = (int)$input['deliverymonth'];
	}

	if(!$input['deliveryday'])
	{
		$input['enddateday'] = gmdate('j', TIME_NOW + $localized_time_offset);
	}
	else
	{
		$input['enddateday'] = (int)$input['deliveryday'];
	}

	$form = new Form("index.php?module=user-mass_mail&amp;action=edit", "post");
	echo $form->generate_hidden_field("mid", $email['mid']);

	$mid_add = '';
	if($email['mid'])
	{
		$mid_add = "&amp;mid={$email['mid']}";
	}

	$form_container = new FormContainer("{$lang->edit_mass_mail}: {$lang->message_settings}");

	$form_container->output_row("{$lang->subject}: <em>*</em>", $lang->subject_desc, $form->generate_text_box('subject', $input['subject'], array('id' => 'subject')), 'subject');

	if($input['type'] == 0)
	{
		$type_email_checked = true;
		$type_pm_checked = false;
	}
	else if($input['type'] == 1)
	{
		$type_email_checked = false;
		$type_pm_checked = true;
	}

	$type_options = array(
		$form->generate_radio_button("type", 0, $lang->send_via_email, array("id" => "type_email", "checked" => $type_email_checked)),
		$form->generate_radio_button("type", 1, $lang->send_via_pm, array("id" => "type_pm", "checked" => $type_pm_checked))
	);
	$form_container->output_row("{$lang->message_type}: <em>*</em>", "", implode("<br />", $type_options));

	$monthnames = array(
		"offset",
		$lang->january,
		$lang->february,
		$lang->march,
		$lang->april,
		$lang->may,
		$lang->june,
		$lang->july,
		$lang->august,
		$lang->september,
		$lang->october,
		$lang->november,
		$lang->december,
	);

	$enddatemonth = "";
	foreach($monthnames as $key => $month)
	{
		if($month == "offset")
		{
			continue;
		}

		if($key == $input['enddatemonth'])
		{
			$enddatemonth .= "<option value=\"{$key}\" selected=\"selected\">{$month}</option>\n";
		}
		else
		{
			$enddatemonth .= "<option value=\"{$key}\">{$month}</option>\n";
		}
	}

	$enddateday = "";

	// Construct option list for days
	for($i = 1; $i <= 31; ++$i)
	{
		if($i == $input['enddateday'])
		{
			$enddateday .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
		}
		else
		{
			$enddateday .= "<option value=\"{$i}\">{$i}</option>\n";
		}
	}

	$actions = "<script type=\"text/javascript\">
		function checkAction(id)
		{
			var checked = '';

			$('.'+id+'s_check').each(function(e, val)
			{
				if($(this).prop('checked') == true)
				{
					checked = $(this).val();
				}
			});
			$('.'+id+'s').each(function(e)
			{
				$(this).hide();
			});
			if($('#'+id+'_'+checked))
			{
				$('#'+id+'_'+checked).show();
			}
		}
	</script>
		<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"delivery_type\" value=\"now\" {$delivery_type_checked['now']} class=\"delivery_types_check\" onclick=\"checkAction('delivery_type');\" style=\"vertical-align: middle;\" /> <strong>{$lang->deliver_immediately}</strong></label></dt>

		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"delivery_type\" value=\"future\" {$delivery_type_checked['future']} class=\"delivery_types_check\" onclick=\"checkAction('delivery_type');\" style=\"vertical-align: middle;\" /> <strong>{$lang->deliver_specific}</strong></label></dt>
			<dd style=\"margin-top: 4px;\" id=\"delivery_type_future\" class=\"delivery_types\">
				<table cellpadding=\"4\">
					<tr>
						<td><select name=\"endtime_day\">\n{$enddateday}</select>\n &nbsp; \n<select name=\"endtime_month\">\n{$enddatemonth}</select>\n &nbsp; \n<input type=\"text\" name=\"endtime_year\" value=\"{$enddateyear}\" class=\"text_input\" size=\"4\" maxlength=\"4\" />\n - {$lang->time} ".$form->generate_text_box('endtime_time', $input['endtime_time'], array('id' => 'endtime_time', 'style' => 'width: 60px;'))."</td>
					</tr>
				</table>
			</dd>
		</dl>
		<script type=\"text/javascript\">
		checkAction('delivery_type');
		</script>";
	$form_container->output_row("{$lang->delivery_date}: <em>*</em>", $lang->delivery_date_desc, $actions);

	$form_container->output_row("{$lang->per_page}: <em>*</em>", $lang->per_page_desc, $form->generate_numeric_field('perpage', $input['perpage'], array('id' => 'perpage', 'min' => 1)), 'perpage');

	$format_options = array(
		0 => $lang->plain_text_only,
		1 => $lang->html_only,
		2 => $lang->html_and_plain_text
	);

	$form_container->output_row("{$lang->message_format}: <em>*</em>", "", $form->generate_select_box('format', $format_options, $input['format'], array('id' => 'format')), 'format', null, array("id" => "format_container"));

	$form_container->end();

	if($input['format'] == 2)
	{
		if($input['automatic_text'] && !$email['mid'])
		{
			$automatic_text_check = true;
			$text_display = 'display: none';
			$automatic_display = 'display: none;';
		}
	}
	else if($input['format'] == 1 && $input['type'] != 1)
	{
		$text_display = 'display: none;';
	}
	else if($input['format'] == 0 || $input['type'] == 1)
	{
		$html_display = 'display: none';
	}

	echo "<div id=\"message_html\" style=\"{$html_display}\">";
	$form_container = new FormContainer("{$lang->edit_mass_mail}: {$lang->define_html_message}");
	$form_container->output_row("{$lang->define_html_message_desc}:", $html_personalisation, $form->generate_text_area('htmlmessage', $input['htmlmessage'], array('id' => 'htmlmessage', 'rows' => 15, 'cols '=> 70, 'style' => 'width: 95%'))."<div id=\"automatic_display\" style=\"{$automatic_display}\">".$form->generate_check_box('automatic_text', 1, $lang->auto_gen_plain_text, array('checked' => $automatic_text_check, "id" => "automatic_text"))."</div>");
	$form_container->end();
	echo "</div>";

	echo "<div id=\"message_text\" style=\"{$text_display}\">";
	$form_container = new FormContainer("{$lang->edit_mass_mail}: {$lang->define_text_version}");
	$form_container->output_row("{$lang->define_text_version_desc}:", $text_personalisation, $form->generate_text_area('message', $input['message'], array('id' => 'message', 'rows' => 15, 'cols '=> 70, 'style' => 'width: 95%')));
	$form_container->end();
	echo "</div>";

	echo "
	<script type=\"text/javascript\">
		function ToggleFormat()
		{
			var v = $('#format option:selected').val();
			if(v == 2)
			{
				$('#automatic_display').show();
				$('#message_html').show();
				if($('#automatic_text').checked)
				{
					$('#message_text').hide();
				}
				else
				{
					$('#message_text').show();
				}
			}
			else if(v == 1)
			{
				$('#message_text').hide();
				$('#message_html').show();
				$('#automatic_display').hide();
			}
			else
			{
				$('#message_text').show();
				$('#message_html').hide();
			}
		}
		$(document).on('change', '#format', function() {
			ToggleFormat();
		});

		function ToggleType()
		{
			var v = $('#type_pm').prop('checked');
			if(v == true)
			{
				$('#message_html').hide();
				$('#message_text').show();
				$('#format_container').hide();
			}
			else
			{
				$('#message_html').show();
				$('#format_container').show();
				ToggleFormat();
			}
		}
		$('#type_pm').on('click', function() {
			ToggleType();
		});
		$('#type_email').on('click', function() {
			ToggleType();
		});
		ToggleType();

		function ToggleAutomatic()
		{
			var v = $('#automatic_text').prop('checked');
			if(v == true)
			{
				$('#message_text').hide();
			}
			else
			{
				$('#message_text').show();
			}
		}

		$('#automatic_text').on('click', function() {
			ToggleAutomatic();
		});

		function insertText(value, textarea)
		{
			textarea = document.getElementById(textarea);
			// Internet Explorer
			if(document.selection)
			{
				textarea.focus();
				var selection = document.selection.createRange();
				selection.text = value;
			}
			// Firefox
			else if(textarea.selectionStart || textarea.selectionStart == '0')
			{
				var start = textarea.selectionStart;
				var end = textarea.selectionEnd;
				textarea.value = textarea.value.substring(0, start)	+ value	+ textarea.value.substring(end, textarea.value.length);
			}
			else
			{
				textarea.value += value;
			}
		}

	</script>";

	$form_container = new FormContainer("{$lang->edit_mass_mail}: {$lang->define_the_recipients}");

	$form_container->output_row($lang->username_contains, "", $form->generate_text_box('conditions[username]', htmlspecialchars_uni($input['conditions']['username']), array('id' => 'username')), 'username');
	$form_container->output_row($lang->email_addr_contains, "", $form->generate_text_box('conditions[email]', $input['conditions']['email'], array('id' => 'email')), 'email');

	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));

	$options = array();
	while($usergroup = $db->fetch_array($query))
	{
		$options[$usergroup['gid']] = $usergroup['title'];
	}

	$form_container->output_row($lang->members_of, $lang->additional_user_groups_desc, $form->generate_select_box('conditions[usergroup][]', $options, $input['conditions']['usergroup'], array('id' => 'usergroups', 'multiple' => true, 'size' => 5)), 'usergroups');

	$greater_options = array(
		"greater_than" => $lang->greater_than,
		"is_exactly" => $lang->is_exactly,
		"less_than" => $lang->less_than
	);
	$form_container->output_row($lang->post_count_is, "", $form->generate_select_box('conditions[postnum_dir]', $greater_options, $input['conditions']['postnum_dir'], array('id' => 'postnum_dir'))." ".$form->generate_numeric_field('conditions[postnum]', $input['conditions']['postnum'], array('id' => 'postnum', 'min' => 0)), 'postnum');

	$more_options = array(
		"more_than" => $lang->more_than,
		"less_than" => $lang->less_than
	);

	$date_options = array(
		"hours" => $lang->hours,
		"days" => $lang->days,
		"weeks" => $lang->weeks,
		"months" => $lang->months,
		"years" => $lang->years
	);
	$form_container->output_row($lang->user_registered, "", $form->generate_select_box('conditions[regdate_dir]', $more_options, $input['conditions']['regdate_dir'], array('id' => 'regdate_dir'))." ".$form->generate_numeric_field('conditions[regdate]', $input['conditions']['regdate'], array('id' => 'regdate', 'min' => 0))." ".$form->generate_select_box('conditions[regdate_date]', $date_options, $input['conditions']['regdate_date'], array('id' => 'regdate_date'))." {$lang->ago}", 'regdate');

	$form_container->output_row($lang->user_last_active, "", $form->generate_select_box('conditions[lastactive_dir]', $more_options, $input['conditions']['lastactive_dir'], array('id' => 'lastactive_dir'))." ".$form->generate_numeric_field('conditions[lastactive]', $input['conditions']['lastactive'], array('id' => 'lastactive', 'min' => 0))." ".$form->generate_select_box('conditions[lastactive_date]', $date_options, $input['conditions']['lastactive_date'], array('id' => 'lastactive_date'))." {$lang->ago}", 'lastactive');

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_mass_mail);
	$form->output_submit_wrapper($buttons);

	$form->end();
	$page->output_footer();
}

if($mybb->input['action'] == "send")
{
	$page->add_breadcrumb_item($lang->send_mass_mail);

	if($mybb->input['step'])
	{
		$query = $db->simple_select("massemails", "*", "status=0 and mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");
		$email = $db->fetch_array($query);
		if(!$email['mid'] && $mybb->input['step'] != 1)
		{
			flash_message($lang->error_invalid_mid, 'error');
			admin_redirect("index.php?module=user-mass_mail");
		}
	}

	$replacement_fields = array(
		"{username}" => $lang->username,
		"{email}" => $lang->email_addr,
		"{bbname}" => $lang->board_name,
		"{bburl}" => $lang->board_url
	);

	$html_personalisation = $text_personalisation = "<script type=\"text/javascript\">\n<!--\ndocument.write('{$lang->personalize_message} ";
	foreach($replacement_fields as $value => $name)
	{
		$html_personalisation .= " [<a href=\"#\" onclick=\"insertText(\'{$value}\', \'htmlmessage\'); return false;\">{$name}</a>], ";
		$text_personalisation .= " [<a href=\"#\" onclick=\"insertText(\'{$value}\', \'message\'); return false;\">{$name}</a>], ";
	}
	$html_personalisation = substr($html_personalisation, 0, -2)."');\n// --></script>\n";
	$text_personalisation = substr($text_personalisation, 0, -2)."');\n// --></script>\n";

	$plugins->run_hooks("admin_user_mass_email_send_start");
	
	$localized_time_offset = (float)$mybb->user['timezone']*3600 + $mybb->user['dst']*3600;

	if($mybb->input['step'] == 4)
	{
		// All done here
		if($mybb->request_method == "post")
		{
			// Sending this message now
			if($mybb->input['delivery_type'] == "now")
			{
				$delivery_date = TIME_NOW;
			}
			// Delivering in the future
			else
			{
				if(stristr($mybb->input['deliverytime_time'], "pm"))
				{
					$mybb->input['deliveryhour'] += 12;
				}

				$exploded = explode(':', $mybb->input['endtime_time']);
				$mybb->input['deliveryhour'] = (int)$exploded[0];

				$exploded = explode(' ', $exploded[1]);
				$mybb->input['deliveryminute'] = (int)$exploded[0];

				$delivery_date = gmmktime($mybb->input['deliveryhour'], $mybb->input['deliveryminute'], 0, $mybb->input['endtime_month'], $mybb->input['endtime_day'], $mybb->input['endtime_year'])- $localized_time_offset;
				if($delivery_date <= TIME_NOW)
				{
					$errors[] = $lang->error_only_in_future;
				}
			}

			if(!$errors)
			{
				// Mark as queued for delivery
				$updated_email = array(
					"status" => 1,
					"senddate" => $delivery_date
				);

				$plugins->run_hooks("admin_user_mass_email_send_finalize_commit");

				$db->update_query("massemails", $updated_email, "mid='{$email['mid']}'");

				flash_message($lang->success_mass_mail_saved, 'success');
				admin_redirect("index.php?module=user-mass_mail");
			}
		}

		// Show summary of the mass email we've just been creating and allow the user to specify the delivery date
		$page->output_header("{$lang->send_mass_mail}: {$lang->step_four}");

		$page->output_nav_tabs($sub_tabs, 'send_mass_mail');

		// If we have any error messages, show them
		if($errors)
		{
			$page->output_inline_error($errors);
			$input = $mybb->input;
		}
		else
		{
			$input = array();
			if($email['senddate'] != 0)
			{
				if($email['senddate'] <= TIME_NOW)
				{
					$input['delivery_type'] = "now";
					$delivery_type_checked['now'] = " checked=\"checked\"";
				}
				else
				{
					$input['delivery_type'] = "future";
					$time = gmdate("d-n-Y", $email['senddate'] + $localized_time_offset);
					$time = explode('-', $time);
					$input['deliverymonth'] = (int)$time[1];
					$input['deliveryday'] = (int)$time[0];
					$input['deliveryyear'] = (int)$time[2];
					$input['endtime_time'] = gmdate($mybb->settings['timeformat'], $email['senddate'] + $localized_time_offset);
					$delivery_type_checked['future'] = " checked=\"checked\"";
				}
			}
			else
			{
				$input['delivery_type'] = "now";
				$delivery_type_checked['now'] = " checked=\"checked\"";
			}
		}

		$table = new Table;
		$table->construct_cell("<strong>{$lang->delivery_method}:</strong>", array('width' => '25%'));
		if($email['type'] == 1)
		{
			$delivery_type = $lang->private_message;
		}
		else if($email['type'] == 0)
		{
			$delivery_type = $lang->email;
		}
		$table->construct_cell($delivery_type);
		$table->construct_row();

		$table->construct_cell("<strong>{$lang->subject}:</strong>");
		$table->construct_cell(htmlspecialchars_uni($email['subject']));
		$table->construct_row();

		$table->construct_cell("<strong>{$lang->message}:</strong>");
		$format_preview = '';
		if($email['format'] == 0 || $email['format'] == 2)
		{
			$format_preview .= "{$lang->text_based} - <a href=\"#\" onclick=\"javascript:MyBB.popupWindow('index.php?module=user-mass_mail&amp;action=preview&amp;mid={$email['mid']}&amp;format=text', null, true);\">{$lang->preview}</a>";
		}
		if($email['format'] == 2)
		{
			$format_preview .= " {$lang->and} <br />";
		}
		if($email['format'] == 1 || $email['format'] == 2)
		{
			$format_preview.= "{$lang->html_based} - <a href=\"#\" onclick=\"javascript:MyBB.popupWindow('index.php?module=user-mass_mail&amp;action=preview&amp;mid={$email['mid']}', null, true);\">{$lang->preview}</a>";
		}
		$table->construct_cell($format_preview);
		$table->construct_row();

		// Recipient counts & details
		$table->construct_cell("<strong>{$lang->total_recipients}:</strong>");
		$table->construct_cell(my_number_format($email['totalcount'])." - <a href=\"index.php?module=user-mass_mail&amp;action=send&amp;step=3&amp;mid={$email['mid']}\">{$lang->change_recipient_conds}</a>");
		$table->construct_row();

		$table->output("{$lang->send_mass_mail}: {$lang->step_four} - {$lang->review_message}");

		if(!$input['endtime_time'])
		{
			$input['endtime_time'] = gmdate($mybb->settings['timeformat'], TIME_NOW + $localized_time_offset);
		}

		if(!$input['deliveryyear'])
		{
			$enddateyear = gmdate('Y', TIME_NOW + $localized_time_offset);
		}
		else
		{
			$enddateyear = (int)$input['deliveryyear'];
		}

		if(!$input['deliverymonth'])
		{
			$input['enddatemonth'] = gmdate('n', TIME_NOW + $localized_time_offset);
		}
		else
		{
			$input['enddatemonth'] = (int)$input['deliverymonth'];
		}

		if(!$input['deliveryday'])
		{
			$input['enddateday'] = gmdate('j', TIME_NOW + $localized_time_offset);
		}
		else
		{
			$input['enddateday'] = (int)$input['deliveryday'];
		}

		$monthnames = array(
			"offset",
			$lang->january,
			$lang->february,
			$lang->march,
			$lang->april,
			$lang->may,
			$lang->june,
			$lang->july,
			$lang->august,
			$lang->september,
			$lang->october,
			$lang->november,
			$lang->december,
		);

		$enddatemonth = "";
		foreach($monthnames as $key => $month)
		{
			if($month == "offset")
			{
				continue;
			}

			if($key == $input['enddatemonth'])
			{
				$enddatemonth .= "<option value=\"{$key}\" selected=\"selected\">{$month}</option>\n";
			}
			else
			{
				$enddatemonth .= "<option value=\"{$key}\">{$month}</option>\n";
			}
		}

		$enddateday = "";

		// Construct option list for days
		for($i = 1; $i <= 31; ++$i)
		{
			if($i == $input['enddateday'])
			{
				$enddateday .= "<option value=\"{$i}\" selected=\"selected\">{$i}</option>\n";
			}
			else
			{
				$enddateday .= "<option value=\"{$i}\">{$i}</option>\n";
			}
		}

		$form = new Form("index.php?module=user-mass_mail&amp;action=send&amp;step=4&amp;mid={$email['mid']}", "post");
		$form_container = new FormContainer("{$lang->send_mass_mail}: {$lang->step_four} - {$lang->define_delivery_date}");

			$actions = "<script type=\"text/javascript\">
			function checkAction(id)
			{
				var checked = '';

				$('.'+id+'s_check').each(function(e, val)
				{
					if($(this).prop('checked') == true)
					{
						checked = $(this).val();
					}
				});
				$('.'+id+'s').each(function(e)
				{
					$(this).hide();
				});
				if($('#'+id+'_'+checked))
				{
					$('#'+id+'_'+checked).show();
				}
			}
		</script>
			<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
			<dt><label style=\"display: block;\"><input type=\"radio\" name=\"delivery_type\" value=\"now\" {$delivery_type_checked['now']} class=\"delivery_types_check\" onclick=\"checkAction('delivery_type');\" style=\"vertical-align: middle;\" /> <strong>{$lang->deliver_immediately}</strong></label></dt>

			<dt><label style=\"display: block;\"><input type=\"radio\" name=\"delivery_type\" value=\"future\" {$delivery_type_checked['future']} class=\"delivery_types_check\" onclick=\"checkAction('delivery_type');\" style=\"vertical-align: middle;\" /> <strong>{$lang->deliver_specific}</strong></label></dt>
				<dd style=\"margin-top: 4px;\" id=\"delivery_type_future\" class=\"delivery_types\">
					<table cellpadding=\"4\">
						<tr>
							<td><select name=\"endtime_day\">\n{$enddateday}</select>\n &nbsp; \n<select name=\"endtime_month\">\n{$enddatemonth}</select>\n &nbsp; \n<input type=\"text\" name=\"endtime_year\" class=\"text_input\" value=\"{$enddateyear}\" size=\"4\" maxlength=\"4\" />\n - {$lang->time} ".$form->generate_text_box('endtime_time', $input['endtime_time'], array('id' => 'endtime_time', 'style' => 'width: 60px;'))."</td>
						</tr>
					</table>
				</dd>
			</dl>
			<script type=\"text/javascript\">
			checkAction('delivery_type');
			</script>";
			$form_container->output_row("{$lang->delivery_date}: <em>*</em>", $lang->delivery_date_desc, $actions);

		$form_container->end();

		$buttons[] = $form->generate_submit_button($lang->schedule_for_delivery);
		$form->output_submit_wrapper($buttons);

		$form->end();
		$page->output_footer();
	}
	elseif($mybb->input['step'] == 3)
	{
		// Define the recipients/conditions
		if($mybb->request_method == "post")
		{
			// Need to perform the search to fetch the number of users we're emailing
			$member_query = build_mass_mail_query($mybb->input['conditions']);
			$query = $db->simple_select("users u", "COUNT(uid) AS num", $member_query);
			$num = $db->fetch_field($query, "num");

			if($num == 0)
			{
				$errors[] = $lang->error_no_users;
			}
			// Got one or more results
			else
			{
				$updated_email = array(
					"totalcount" => $num,
					"conditions" => $db->escape_string(my_serialize($mybb->input['conditions']))
				);

				$plugins->run_hooks("admin_user_mass_email_send_define_commit");

				$db->update_query("massemails", $updated_email, "mid='{$email['mid']}'");

				// Take the user to the next step
				admin_redirect("index.php?module=user-mass_mail&action=send&step=4&mid={$email['mid']}");
			}
		}

		$page->output_header("{$lang->send_mass_mail}: {$lang->step_three}");

		$form = new Form("index.php?module=user-mass_mail&amp;action=send&amp;step=3&amp;mid={$email['mid']}", "post");
		$page->output_nav_tabs($sub_tabs, 'send_mass_mail');

		// If we have any error messages, show them
		if($errors)
		{
			$page->output_inline_error($errors);
			$input = $mybb->input;
		}
		else
		{
			if($email['conditions'] != '')
			{
				$input = array(
					"conditions" => my_unserialize($email['conditions'])
				);
			}
			else
			{
				$input = array();
			}
		}

		$options = array(
			'username', 'email', 'postnum_dir', 'postnum', 'regdate', 'regdate_date', 'regdate_dir', 'lastactive', 'lastactive_date', 'lastactive_dir'
		);

		foreach($options as $option)
		{
			if(!isset($input['conditions'][$option]))
			{
				$input['conditions'][$option] = '';
			}
		}
		if(!isset($input['conditions']['usergroup']) || !is_array($input['conditions']['usergroup']))
		{
			$input['conditions']['usergroup'] = array();
		}

		$form_container = new FormContainer("{$lang->send_mass_mail}: {$lang->step_three} - {$lang->define_the_recipients}");

		$form_container->output_row($lang->username_contains, "", $form->generate_text_box('conditions[username]', htmlspecialchars_uni($input['conditions']['username']), array('id' => 'username')), 'username');
		$form_container->output_row($lang->email_addr_contains, "", $form->generate_text_box('conditions[email]', $input['conditions']['email'], array('id' => 'email')), 'email');

		$options = array();
		$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
		while($usergroup = $db->fetch_array($query))
		{
			$options[$usergroup['gid']] = $usergroup['title'];
		}

		$form_container->output_row($lang->members_of, $lang->additional_user_groups_desc, $form->generate_select_box('conditions[usergroup][]', $options, $input['conditions']['usergroup'], array('id' => 'usergroups', 'multiple' => true, 'size' => 5)), 'usergroups');

		$greater_options = array(
			"greater_than" => $lang->greater_than,
			"is_exactly" => $lang->is_exactly,
			"less_than" => $lang->less_than
		);
		$form_container->output_row($lang->post_count_is, "", $form->generate_select_box('conditions[postnum_dir]', $greater_options, $input['conditions']['postnum_dir'], array('id' => 'postnum_dir'))." ".$form->generate_numeric_field('conditions[postnum]', $input['conditions']['postnum'], array('id' => 'postnum', 'min' => 0)), 'postnum');

		$more_options = array(
			"more_than" => $lang->more_than,
			"less_than" => $lang->less_than
		);

		$date_options = array(
			"hours" => $lang->hours,
			"days" => $lang->days,
			"weeks" => $lang->weeks,
			"months" => $lang->months,
			"years" => $lang->years
		);
		$form_container->output_row($lang->user_registered, "", $form->generate_select_box('conditions[regdate_dir]', $more_options, $input['conditions']['regdate_dir'], array('id' => 'regdate_dir'))." ".$form->generate_numeric_field('conditions[regdate]', $input['conditions']['regdate'], array('id' => 'regdate', 'min' => 0))." ".$form->generate_select_box('conditions[regdate_date]', $date_options, $input['conditions']['regdate_date'], array('id' => 'regdate_date'))." {$lang->ago}", 'regdate');

		$form_container->output_row($lang->user_last_active, "", $form->generate_select_box('conditions[lastactive_dir]', $more_options, $input['conditions']['lastactive_dir'], array('id' => 'lastactive_dir'))." ".$form->generate_numeric_field('conditions[lastactive]', $input['conditions']['lastactive'], array('id' => 'lastactive', 'min' => 0))." ".$form->generate_select_box('conditions[lastactive_date]', $date_options, $input['conditions']['lastactive_date'], array('id' => 'lastactive_date'))." {$lang->ago}", 'lastactive');

		$form_container->end();

		$buttons[] = $form->generate_submit_button($lang->next_step);
		$form->output_submit_wrapper($buttons);

		$form->end();
		$page->output_footer();
	}
	// Reviewing the automatic text based version of the message.
	elseif($mybb->input['step'] == 2)
	{
		// Update text based version
		if($mybb->request_method == "post")
		{
			if(!trim($mybb->input['message']))
			{
				$errors[] = $lang->error_missing_plain_text;
			}
			else
			{
				$updated_email = array(
					"message" => $db->escape_string($mybb->input['message'])
				);

				$plugins->run_hooks("admin_user_mass_email_send_review_commit");

				$db->update_query("massemails", $updated_email, "mid='{$email['mid']}'");

				// Take the user to the next step
				admin_redirect("index.php?module=user-mass_mail&action=send&step=3&mid={$email['mid']}");
			}
		}

		$page->output_header("{$lang->send_mass_mail}: {$lang->step_two}");

		$form = new Form("index.php?module=user-mass_mail&amp;action=send&amp;step=2&amp;mid={$email['mid']}", "post");
		$page->output_nav_tabs($sub_tabs, 'send_mass_mail');

		// If we have any error messages, show them
		if($errors)
		{
			$page->output_inline_error($errors);
		}

		$form_container = new FormContainer("{$lang->send_mass_mail}: {$lang->step_two} - {$lang->review_text_version}");
		$form_container->output_row("{$lang->review_text_version_desc}:", $text_personalisation, $form->generate_text_area('message', $email['message'], array('id' => 'message', 'rows' => 15, 'cols '=> 70, 'style' => 'width: 95%')));
		$form_container->end();

		$buttons[] = $form->generate_submit_button($lang->next_step);
		$form->output_submit_wrapper($buttons);

		$form->end();
		$page->output_footer();
	}
	elseif(!$mybb->input['step'] || $mybb->input['step'] == 1)
	{
		if($mybb->request_method == "post")
		{
			if(!trim($mybb->input['subject']))
			{
				$errors[] = $lang->error_missing_subject;
			}

			if($mybb->input['type'] == 1)
			{
				if(!$mybb->input['message'])
				{
					$errors[] = $lang->error_missing_message;
				}
			}
			else
			{
				if($mybb->input['format'] == 2 && $mybb->input['automatic_text'] == 0 && !$mybb->input['message'])
				{
					$errors[] = $lang->error_missing_plain_text;
				}

				if(($mybb->input['format'] == 1 || $mybb->input['format'] == 2) && !$mybb->input['htmlmessage'])
				{
					$errors[] = $lang->error_missing_html;
				}
				else if($mybb->input['format'] == 0 && !$mybb->input['message'])
				{
					$errors[] = $lang->error_missing_plain_text;
				}
			}

			// No errors, insert away
			if(!$errors)
			{
				if(!$new_email['mid'])
				{
					// Sending via a PM
					if($mybb->input['type'] == 1)
					{
						$mybb->input['format'] = 0;
						$mybb->input['htmlmessage'] = '';
					}
					// Sending via email
					else
					{
						// Do we need to generate a text based version?
						if($mybb->input['format'] == 2 && $mybb->input['automatic_text'])
						{
							$mybb->input['message'] = create_text_message($mybb->input['htmlmessage']);
						}
						else if($mybb->input['format'] == 1)
						{
							$mybb->input['message'] = '';
						}
						else if($mybb->input['format'] == 0)
						{
							$mybb->input['htmlmessage'] = '';
						}
					}

					$new_email = array(
						"uid" => $mybb->user['uid'],
						"subject" => $db->escape_string($mybb->input['subject']),
						"message" => $db->escape_string($mybb->input['message']),
						"htmlmessage" => $db->escape_string($mybb->input['htmlmessage']),
						"format" => $mybb->get_input('format', MyBB::INPUT_INT),
						"type" => $mybb->get_input('type', MyBB::INPUT_INT),
						"dateline" => TIME_NOW,
						"senddate" => 0,
						"status" => 0,
						"sentcount" => 0,
						"totalcount" => 0,
						"conditions" => "",
						"perpage" => $mybb->get_input('perpage', MyBB::INPUT_INT)
					);

					$mid = $db->insert_query("massemails", $new_email);

					$plugins->run_hooks("admin_user_mass_email_send_insert_commit");
				}
				// Updating an existing one
				else
				{
					$updated_email = array(
						"subject" => $db->escape_string($mybb->input['subject']),
						"message" => $db->escape_string($mybb->input['message']),
						"htmlmessage" => $db->escape_string($mybb->input['htmlmessage']),
						"format" => $mybb->get_input('format', MyBB::INPUT_INT),
						"type" => $mybb->get_input('type', MyBB::INPUT_INT),
						"perpage" => $mybb->get_input('perpage', MyBB::INPUT_INT)
					);

					$plugins->run_hooks("admin_user_mass_email_send_update_commit");

					$db->update_query("massemails", $updated_email, "mid='{$email['mid']}'");
					$mid = $email['mid'];
				}

				if($mybb->input['format'] == 2 && $mybb->input['automatic_text'] == 1)
				{
					$next = 2;
				}
				else
				{
					$next = 3;
				}
				admin_redirect("index.php?module=user-mass_mail&action=send&step={$next}&mid={$mid}");
			}
		}

		$page->output_header("{$lang->send_mass_mail}: {$lang->step_one}");

		$mid_add = '';
		if($email['mid'])
		{
			$mid_add = "&amp;mid={$email['mid']}";
		}

		$form = new Form("index.php?module=user-mass_mail&amp;action=send{$mid_add}", "post");
		$page->output_nav_tabs($sub_tabs, 'send_mass_mail');

		// If we have any error messages, show them
		if($errors)
		{
			$page->output_inline_error($errors);
			$input = $mybb->input;
		}
		else if(!$email)
		{
			$input = array(
				"type" => 0,
				"format" => 2,
				"automatic_text" => 1,
				"perpage" => 50,
			);
		}
		else
		{
			$input = $email;
		}

		$form_container = new FormContainer("{$lang->send_mass_mail}: {$lang->step_one} - {$lang->message_settings}");

		$form_container->output_row("{$lang->subject}: <em>*</em>", $lang->subject_desc, $form->generate_text_box('subject', $input['subject'], array('id' => 'subject')), 'subject');

		if($mybb->input['type'] == 0)
		{
			$type_email_checked = true;
			$type_pm_checked = false;
		}
		else if($mybb->input['type'] == 1)
		{
			$type_email_checked = false;
			$type_pm_checked = true;
		}

		$type_options = array(
			$form->generate_radio_button("type", 0, $lang->send_via_email, array("id" => "type_email", "checked" => $type_email_checked)),
			$form->generate_radio_button("type", 1, $lang->send_via_pm, array("id" => "type_pm", "checked" => $type_pm_checked))
		);
		$form_container->output_row("{$lang->message_type}:", "", implode("<br />", $type_options));

		$format_options = array(
			0 => $lang->plain_text_only,
			1 => $lang->html_only,
			2 => $lang->html_and_plain_text
		);

		$form_container->output_row("{$lang->message_format}:", "", $form->generate_select_box('format', $format_options, $input['format'], array('id' => 'format')), 'format', null, array("id" => "format_container"));

		$form_container->output_row("{$lang->per_page}: <em>*</em>", $lang->per_page_desc, $form->generate_numeric_field('perpage', $input['perpage'], array('id' => 'perpage', 'min' => 1)), 'perpage');

		$form_container->end();

		if($mybb->input['format'] == 2)
		{
			if($mybb->input['automatic_text'] && !$email['mid'])
			{
				$automatic_text_check = true;
				$text_display = 'display: none';
				$automatic_display = 'display: none;';
			}
		}
		else if($mybb->input['format'] == 1 && $mybb->input['type'] != 1)
		{
			$text_display = 'display: none;';
		}
		else if($mybb->input['format'] == 0 || $mybb->input['type'] == 1)
		{
			$html_display = 'display: none';
		}

		echo "<div id=\"message_html\" style=\"{$html_display}\">";
		$form_container = new FormContainer("{$lang->send_mass_mail}: {$lang->step_one} - {$lang->define_html_message}");
		$form_container->output_row("{$lang->define_html_message_desc}:", $html_personalisation, $form->generate_text_area('htmlmessage', $input['htmlmessage'], array('id' => 'htmlmessage', 'rows' => 15, 'cols '=> 70, 'style' => 'width: 95%'))."<div id=\"automatic_display\" style=\"{$automatic_display}\">".$form->generate_check_box('automatic_text', 1, $lang->auto_gen_plain_text, array('checked' => $automatic_text_check, "id" => "automatic_text"))."</div>");
		$form_container->end();
		echo "</div>";

		echo "<div id=\"message_text\" style=\"{$text_display}\">";
		$form_container = new FormContainer("{$lang->send_mass_mail}: {$lang->step_one} - {$lang->define_text_version}");
		$form_container->output_row("{$lang->define_text_version_desc}:", $text_personalisation, $form->generate_text_area('message', $input['message'], array('id' => 'message', 'rows' => 15, 'cols '=> 70, 'style' => 'width: 95%')));
		$form_container->end();
		echo "</div>";

		echo "
		<script type=\"text/javascript\">
		function ToggleFormat()
		{
			var v = $('#format option:selected').val();
			if(v == 2)
			{
				$('#automatic_display').show();
				$('#message_html').show();
				if($('#automatic_text').checked)
				{
					$('#message_text').hide();
				}
				else
				{
					$('#message_text').show();
				}
			}
			else if(v == 1)
			{
				$('#message_text').hide();
				$('#message_html').show();
				$('#automatic_display').hide();
			}
			else
			{
				$('#message_text').show();
				$('#message_html').hide();
			}
		}
		$(document).on('change', '#format', function() {
			ToggleFormat();
		});

		function ToggleType()
		{
			var v = $('#type_pm').prop('checked');
			if(v == true)
			{
				$('#message_html').hide();
				$('#message_text').show();
				$('#format_container').hide();
			}
			else
			{
				$('#message_html').show();
				$('#format_container').show();
				ToggleFormat();
			}
		}
		$('#type_pm').on('click', function() {
			ToggleType();
		});
		$('#type_email').on('click', function() {
			ToggleType();
		});
		ToggleType();

		function ToggleAutomatic()
		{
			var v = $('#automatic_text').prop('checked');
			if(v == true)
			{
				$('#message_text').hide();
			}
			else
			{
				$('#message_text').show();
			}
		}

		$('#automatic_text').on('click', function() {
			ToggleAutomatic();
		});

		function insertText(value, textarea)
		{
			textarea = document.getElementById(textarea);
			// Internet Explorer
			if(document.selection)
			{
				textarea.focus();
				var selection = document.selection.createRange();
				selection.text = value;
			}
			// Firefox
			else if(textarea.selectionStart || textarea.selectionStart == '0')
			{
				var start = textarea.selectionStart;
				var end = textarea.selectionEnd;
				textarea.value = textarea.value.substring(0, start)	+ value	+ textarea.value.substring(end, textarea.value.length);
			}
			else
			{
				textarea.value += value;
			}
		}

		</script>";

		$buttons[] = $form->generate_submit_button($lang->next_step);
		$form->output_submit_wrapper($buttons);

		$form->end();
		$page->output_footer();
	}

	$plugins->run_hooks("admin_user_mass_email_preview_end");
}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("massemails", "*", "mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");
	$mass_email = $db->fetch_array($query);

	if(!$mass_email['mid'])
	{
		flash_message($lang->error_delete_invalid_mid, 'error');
		admin_redirect("index.php?module=user-mass_mail");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=user-mass_mail");
	}

	$plugins->run_hooks("admin_user_mass_email_delete_start");

	if($mybb->request_method == "post")
	{
		$db->delete_query("massemails", "mid='{$mass_email['mid']}'");

		$plugins->run_hooks("admin_user_mass_email_delete_commit");

		// Log admin action
		log_admin_action($mass_email['mid'], $mass_email['subject']);

		if($mybb->input['archive'] == 1)
		{
			flash_message($lang->success_mass_mail_deleted, 'success');
			admin_redirect("index.php?module=user-mass_mail&action=archive");
		}
		else
		{
			flash_message($lang->success_mass_mail_deleted, 'success');
			admin_redirect("index.php?module=user-mass_mail");
		}
	}
	else
	{
		if($mybb->input['archive'] == 1)
		{
			$page->output_confirm_action("index.php?module=user-mass_mail&amp;action=delete&amp;mid={$mass_email['mid']}&amp;archive=1", $lang->mass_mail_deletion_confirmation);
		}
		else
		{
			$page->output_confirm_action("index.php?module=user-mass_mail&amp;action=delete&amp;mid={$mass_email['mid']}", $lang->mass_mail_deletion_confirmation);
		}
	}
}

if($mybb->input['action'] == "preview")
{
	$query = $db->simple_select("massemails", "*", "mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");
	$mass_email = $db->fetch_array($query);

	if(!$mass_email['mid'])
	{
		flash_message($lang->error_invalid_mid, 'error');
		admin_redirect("index.php?module=user-mass_mail");
	}

	$plugins->run_hooks("admin_user_mass_email_preview_start");

	echo '<div class="modal">
	<div style="overflow-y: auto; max-height: 400px;">';
	
	$table = new Table();
	
	if($mybb->input['format'] == 'text' || !$mass_email['htmlmessage'])
	{
		// Show preview of the text version
		$table->construct_cell(nl2br($mass_email['message']));
	}
	else
	{
		// Preview the HTML version
		$table->construct_cell($mass_email['htmlmessage']);
	}

	$plugins->run_hooks("admin_user_mass_email_preview_end");

	$table->construct_row();

	$table->output($lang->mass_mail_preview);

	echo '</div>
</div>';
	exit;
}

if($mybb->input['action'] == "resend")
{
	// Copy and resend an email
	$query = $db->simple_select("massemails", "*", "mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");
	$mass_email = $db->fetch_array($query);

	if(!$mass_email['mid'])
	{
		flash_message($lang->error_invalid_mid, 'error');
		admin_redirect("index.php?module=user-mass_mail");
	}

	$plugins->run_hooks("admin_user_mass_email_resend_start");

	// Need to perform the search to fetch the number of users we're emailing
	$member_query = build_mass_mail_query(my_unserialize($mass_email['conditions']));
	$query = $db->simple_select("users u", "COUNT(uid) AS num", $member_query);
	$total_recipients = $db->fetch_field($query, "num");

	// Create the new email based off the old one.
	$new_email = array(
		"uid" => $mass_email['uid'],
		"subject" => $db->escape_string($mass_email['subject']),
		"message" => $db->escape_string($mass_email['message']),
		"htmlmessage" => $db->escape_string($mass_email['htmlmessage']),
		"type" => $db->escape_string($mass_email['type']),
		"format" => $db->escape_string($mass_email['format']),
		"dateline" => TIME_NOW,
		"senddate" => '0',
		"status" => 0,
		"sentcount" => 0,
		"totalcount" => $total_recipients,
		"conditions" => $db->escape_string($mass_email['conditions']),
		"perpage" => $mass_email['perpage']
	);

	$mid = $db->insert_query("massemails", $new_email);

	$plugins->run_hooks("admin_user_mass_email_resend_end");

	// Redirect the user to the summary page so they can select when to deliver this message
	flash_message($lang->success_mass_mail_resent, 'success');
	admin_redirect("index.php?module=user-mass_mail&action=send&step=4&mid={$mid}");
	exit;
}

if($mybb->input['action'] == "cancel")
{
	if(!verify_post_check($mybb->input['my_post_key']))
	{
		flash_message($lang->invalid_post_verify_key2, 'error');
		admin_redirect("index.php?module=user-users");
	}

	// Cancel the delivery of a mass-email.
	$query = $db->simple_select("massemails", "*", "mid='".$mybb->get_input('mid', MyBB::INPUT_INT)."'");
	$mass_email = $db->fetch_array($query);

	if(!$mass_email['mid'])
	{
		flash_message($lang->error_invalid_mid, 'error');
		admin_redirect("index.php?module=user-mass_mail");
	}

	$updated_email = array(
		'status' => 4
	);

	$plugins->run_hooks("admin_user_mass_email_cancel");

	$db->update_query("massemails", $updated_email, "mid='{$mass_email['mid']}'");

	flash_message($lang->success_mass_mail_canceled, 'success');
	admin_redirect("index.php?module=user-mass_mail");
	exit;
}

if($mybb->input['action'] == "archive")
{
	// View a list of archived email messages
	$page->output_header($lang->mass_mail_archive);

	$plugins->run_hooks("admin_user_mass_email_archive_start");

	$page->output_nav_tabs($sub_tabs, 'archive');

	$table = new Table;
	$table->construct_header($lang->subject);
	$table->construct_header($lang->status, array('width' => '130', 'class' => 'align_center'));
	$table->construct_header($lang->delivery_date, array('width' => '130', 'class' => 'align_center'));
	$table->construct_header($lang->recipients, array('width' => '130', 'class' => 'align_center'));
	$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2, "width" => 200));

	$query = $db->simple_select("massemails", "*", "status NOT IN (0, 1, 2)", array('order_by' => 'senddate'));
	while($email = $db->fetch_array($query))
	{
		$email['subject'] = htmlspecialchars_uni($email['subject']);
		if($email['senddate'] < TIME_NOW)
		{
			$table->construct_cell("<strong>{$email['subject']}</strong>");
		}
		if($email['status'] == 3)
		{
			$status = $lang->delivered;
		}
		else if($email['status'] == 4)
		{
			$status = $lang->canceled;
		}
		$table->construct_cell($status, array("class" => "align_center"));

		$delivery_date = my_date($mybb->settings['dateformat'], $email['senddate']);

		$table->construct_cell($delivery_date, array("class" => "align_center"));
		$table->construct_cell(my_number_format($email['totalcount']), array("class" => "align_center"));

		$table->construct_cell("<a href=\"index.php?module=user-mass_mail&amp;action=resend&amp;mid={$email['mid']}\">{$lang->resend}</a>", array("width" => 100, "class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=user-mass_mail&amp;action=delete&amp;mid={$email['mid']}&amp;my_post_key={$mybb->post_code}&amp;archive=1\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->mass_mail_deletion_confirmation}')\">{$lang->delete}</a>", array("width" => 100, "class" => "align_center"));

		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_archived_messages, array('colspan' => 6));
		$table->construct_row();
		$no_results = true;
	}

	$plugins->run_hooks("admin_user_mass_email_archive_end");

	$table->output($lang->mass_mail_archive);

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->mass_mail_queue);

	$plugins->run_hooks("admin_user_mass_email_start");

	$page->output_nav_tabs($sub_tabs, 'mail_queue');

	$table = new Table;
	$table->construct_header($lang->subject);
	$table->construct_header($lang->status, array('width' => '130', 'class' => 'align_center'));
	$table->construct_header($lang->delivery_date, array('width' => '130', 'class' => 'align_center'));
	$table->construct_header($lang->recipients, array('width' => '130', 'class' => 'align_center'));
	$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2, "width" => 200));

	$query = $db->simple_select("massemails", "*", "status IN (0, 1, 2)", array('order_by' => 'senddate'));
	while($email = $db->fetch_array($query))
	{
		$email['subject'] = htmlspecialchars_uni($email['subject']);
		if(TIME_NOW >= $email['senddate'] && $email['status'] > 1)
		{
			$table->construct_cell("<a href=\"index.php?module=user-mass_mail&amp;action=edit&amp;mid={$email['mid']}\"><strong>{$email['subject']}</strong></a>");
		}
		else
		{
			$table->construct_cell("<strong>{$email['subject']}</strong>");
		}
		if($email['status'] == 0)
		{
			$status = $lang->draft;
		}
		else if($email['status'] == 1)
		{
			$status = $lang->queued;
		}
		else if($email['status'] == 2)
		{
			$progress = ceil($email['sentcount']/$email['totalcount']*100);
			if($progress > 100)
			{
				$progress = 100;
			}
			$status = "{$lang->delivering} ({$progress}%)";
		}
		$table->construct_cell($status, array("class" => "align_center"));

		if($email['status'] != 0)
		{
			$delivery_date = my_date($mybb->settings['dateformat'], $email['senddate']);
		}
		else
		{
			$delivery_date = $lang->na;
		}

		$table->construct_cell($delivery_date, array("class" => "align_center"));
		$table->construct_cell(my_number_format($email['totalcount']), array("class" => "align_center"));
		if(TIME_NOW >= $email['senddate'] && $email['status'] > 1)
		{
			$table->construct_cell("<a href=\"index.php?module=user-mass_mail&amp;action=cancel&amp;mid={$email['mid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->mass_mail_cancel_confirmation}')\">{$lang->cancel}</a>", array("width" => 100, "colspan" => 2, "class" => "align_center"));
		}
		else
		{
			$table->construct_cell("<a href=\"index.php?module=user-mass_mail&amp;action=edit&amp;mid={$email['mid']}\">{$lang->edit}</a>", array("width" => 100, "class" => "align_center"));
			$table->construct_cell("<a href=\"index.php?module=user-mass_mail&amp;action=delete&amp;mid={$email['mid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->mass_mail_deletion_confirmation}')\">{$lang->delete}</a>", array("width" => 100, "class" => "align_center"));
		}
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_unsent_messages, array('colspan' => 6));
		$table->construct_row();
		$no_results = true;
	}

	$plugins->run_hooks("admin_user_mass_email_end");

	$table->output($lang->mass_mail_queue);

	$page->output_footer();
}
