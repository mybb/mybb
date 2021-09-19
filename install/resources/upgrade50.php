<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Upgrade Script: 1.8.22
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade50_dbchanges()
{
	global $output, $cache, $db, $mybb;

	$output->print_header("Updating Database");

	echo "<p>Updating cache...</p>";

	$cache->delete("banned");

	// Moved PM wrong folder correction
	$db->update_query("privatemessages", array('folder' => 1), "folder='0'");

	// PM folder structure conversion
	$db->update_query('users', array('pmfolders' => "0**$%%$1**$%%$2**$%%$3**$%%$4**"), "pmfolders = ''");
	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$update = "'0**$%%$' || pmfolders";
			break;
		default:
			$update = "CONCAT('0**$%%$', pmfolders)";
	}
	$db->write_query("UPDATE ".TABLE_PREFIX."users SET pmfolders=".$update." WHERE pmfolders NOT LIKE '0%'");

	$db->update_query('settings', array('value' => 1), "name='nocacheheaders'");

	// Add hCaptcha support
	echo "<p>Updating settings...</p>";
	$db->update_query("settings", array('name' => 'recaptchapublickey'), "name='captchapublickey'");
	$db->update_query("settings", array('name' => 'recaptchaprivatekey'), "name='captchaprivatekey'");
	$db->update_query("settings", array('optionscode' => 'select\r\n0=No CAPTCHA\r\n1=MyBB Default CAPTCHA\r\n2=reCAPTCHA\r\n3=NoCAPTCHA reCAPTCHA\r\n4=reCAPTCHA invisible\r\n5=hCAPTCHA\r\n6=hCAPTCHA invisible\r\n7=reCAPTCHA v3'), "name='captchaimage'");

	// If using fulltext then enforce minimum word length given by database
	if($mybb->settings['minsearchword'] > 0 && $mybb->settings['searchtype'] == "fulltext" && $db->supports_fulltext_boolean("posts") && $db->supports_fulltext("threads"))
	{
		// Attempt to determine minimum word length from MySQL for fulltext searches
		$query = $db->query("SHOW VARIABLES LIKE 'ft_min_word_len';");
		$min_length = $db->fetch_field($query, 'Value');
		if(is_numeric($min_length) && $mybb->settings['minsearchword'] < $min_length)
		{
			$min_length = (int) $min_length;
			$old_min_length = (int) $mybb->settings['minsearchword'];
			echo "<p>Updating Minimum Search Word Length setting to match the database system configuration (was {$old_min_length}, now {$min_length})</p>";
			$db->update_query("settings", array('value' => $min_length), "name='minsearchword'");
		}
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("50_verify_email");
}

function upgrade50_verify_email()
{
	global $output, $mybb, $errors, $checked;

	$output->print_header("Admin Email");

	if(!is_array($errors))
	{
		echo "<p>Checking if Admin Email setting is set correctly...</p>";
		flush();
	}
	
	if(empty($mybb->settings['adminemail']))
	{
		if(is_array($errors))
		{
			$error_list = "<ul>\n";
			foreach($errors as $error)
			{
				$error_list .= "<li>{$error}</li>\n";
			}
			$error_list .= "</ul>\n";
			echo '<div class="error">
<h3>Error</h3>
<p>There seems to be one or more errors with the configuration you supplied:</p>
'.$error_list.'
<p>Once the above are corrected, continue with the upgrade.</p>
</div>';
		}
		else
		{
			$checked = array();
		}

		echo '
<div class="border_wrapper">
		<div class="title">Admin Email</div>

	<table class="general" cellspacing="0">
	<thead>
	<tr>
		<th colspan="2" class="first last">Please select an option to use for the board\'s outgoing email address.</th>
	</tr>
	</thead>
	<tr class="first" id="userpresetemail_row">
		<td class="first"><label for="usepresetemail">Select an option:</label></td>
		<td class="alt_col last" width="70%">
			<input type="radio" name="usepresetemail" value="current_user"'.$checked['current_user'].' />Use current user email: '.htmlspecialchars_uni($mybb->user['email']).'<br />';
		
		if($mybb->settings['mail_handler'] == 'smtp' && !empty($mybb->settings['smtp_user']) && filter_var($mybb->settings['smtp_user'], FILTER_VALIDATE_EMAIL) !== false)
		{
			echo '
			<input type="radio" name="usepresetemail" value="smtp"'.$checked['smtp'].' />Use SMTP username: '.htmlspecialchars_uni($mybb->settings['smtp_user']).'<br />';
		}
		
		echo '
			<input type="radio" name="usepresetemail" value="custom"'.$checked['custom'].' />Use custom
		</td>
	</tr>
	<tr class="last" id="custom_adminemail">
		<td class="first"><label for="adminemail">Custom Email Address:</label></td>
		<td class="alt_col last"><input type="text" class="text_input" name="adminemail" id="adminemail" value="'.htmlspecialchars_uni($mybb->input['adminemail']).'" /></td>
	</tr>
</table>
</div>
<script type="text/javascript">
$("#userpresetemail_row input").change(function()
{
	if(this.checked && this.value == "custom")
	{
		$("#custom_adminemail").show();
		$("#userpresetemail_row").removeClass("last");
	}
	else
	{
		$("#custom_adminemail").hide();
		$("#userpresetemail_row").addClass("last");
	}
});
</script>
<p>Once you\'ve correctly entered the details above and are ready to proceed, click Next.</p>';

		$output->print_footer("50_submit_email");
	}
	else
	{
		echo "<p>Admin email verified success...</p>";		
		$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
		$output->print_footer("50_done");
	}
}

function upgrade50_submit_email()
{
	global $output, $db, $mybb, $cache, $errors, $checked;
	
	if(empty($mybb->input['usepresetemail']) || !in_array($mybb->input['usepresetemail'], array('current_user', 'smtp', 'custom')))
 	{
		$errors[] = "Please select an option for the admin email.";
	}
	
	if($mybb->input['usepresetemail'] == 'smtp' && !($mybb->settings['mail_handler'] == 'smtp' && !empty($mybb->settings['smtp_user'])))
	{
		$errors[] = "Please select a different option. SMTP user setting is not configured.";
	}
	
	switch ($mybb->input['usepresetemail'])
	{
		case 'current_user':
			$email = $mybb->user['email'];
			break;
		case 'smtp':
			$email = $mybb->settings['smtp_user'];
			break;
		case 'custom':
			$email = $mybb->input['adminemail'];
			break;
	}
	
	$checked = array(
		$mybb->input['usepresetemail'] => ' checked="checked"'
	);
	
	if(empty($errors) && filter_var($email, FILTER_VALIDATE_EMAIL) === false)
	{
		$errors[] = "The email address given was invalid. Please enter a valid email address.";
	}
	
	if(!empty($errors))
	{
		upgrade50_verify_email();
		return;
 	}

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	$db->update_query('settings', array('value' => $db->escape_string($email)), "name='adminemail'");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("50_done");
}
