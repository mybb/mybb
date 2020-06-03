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
	global $output, $cache, $db;

	$output->print_header("Updating Database");

	echo "<p>Updating cache...</p>";

	$cache->delete("banned");

	$db->update_query('settings', array('value' => 1), "name='nocacheheaders'");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("50_verify_email");
}

function upgrade50_verify_email()
{
	global $output, $cache, $db, $mybb;

	$output->print_header("Verifying Admin Email");
	if(empty($mybb->settings['adminemail']))
	{
		echo "<p>Updating admin email settings...</p>";
		echo "<p><small>P.D: Field can not be empty</small></p>";
		echo '<div class="border_wrapper">
				<div class="title">Admin Email Configuration</div>
				<table class="general" cellspacing="0">
					<tbody>
					<tr>
						<th class="first last">Enter Admin Email</th>
					</tr>
					<tr class="first">
						<td class="last alt_col">						
							<input type="radio" name="email" value="current_admin_email" />Use Current User Email<br />						
							<input type="radio" name="email" value="ftp_admin_email" />Use Admin FTP Email<br />
							<input type="radio" name="email" value="custom_email" checked="checked" />Set Custom Email
							<input class="text" type="text" value="" name="input_email" placeholder="Enter Admin Email" />
						</td>
					</tr>
					</tbody>
				</table>
			</div>';

		$output->print_contents("<p>Select your desired option and click next to continue with the upgrade process.</p>");
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
	global $output, $cache, $db, $mybb;
	$output->print_header("Admin Email Update...");	
	if($mybb->input['email'] == 'custom_email')
		$email = $mybb->input['input_email'];
	else if($mybb->input['email'] == 'current_admin_email')
		$email = $mybb->user['email'];
	else if($mybb->input['email'] == 'ftp_admin_email')
		$email = $mybb->settings['smtp_user'];		
	if(filter_var($email, FILTER_VALIDATE_EMAIL))
	{
		$db->update_query('settings', array('value' => $db->escape_string($email)), "name='adminemail'");
		$output->print_contents("<p>Admin email updated successfully, click next to continue with the upgrade process.</p>");
		$output->print_footer("50_done");
	}
	else
	{
		echo "Error: Admin email must be a valid email address";
		$output->print_footer("50_verify_email");
	}
}
