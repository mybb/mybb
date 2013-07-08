<?php
/**
 * MyBB 1.8
 * Copyright 2013 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * $Id$
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'contact.php');

$templatelist = "contact";
require_once "./global.php";
require_once MYBB_ROOT.'inc/class_captcha.php';

// Load global language phrases
$lang->load("contact");

$plugins->run_hooks('contact_start');

// Make navigation
add_breadcrumb($lang->nav_contact, "contact.php");

if(!$mybb->user['uid'])
{
	if($mybb->settings['contact_guests'] == 1)
	{
		error_no_permission();
	}
}

$errors = '';

if($mybb->request_method == "post" && isset($mybb->input['submit']))
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	// Validate input
	if(!isset($mybb->input['message']) || trim_blank_chrs($mybb->input['message']) == '')
	{
		$errors[] = $lang->contact_no_message;
	}

	if(!isset($mybb->input['subject']) || trim_blank_chrs($mybb->input['subject']) == '')
	{
		$errors[] = $lang->contact_no_subject;
	}

	if(!isset($mybb->input['email']) || trim_blank_chrs($mybb->input['email']) == '')
	{
		$errors[] = $lang->contact_no_email;
	}
	else
	{
		// Validate email
		if(!validate_email_format($mybb->input['email']))
		{
			$errors[] = $lang->contact_no_email;
		}
	}

	// Should we have a CAPTCHA? Perhaps yes...
	if($mybb->settings['captchaimage'])
	{
		$captcha = new captcha;

		if($captcha->validate_captcha() == false)
		{
			// CAPTCHA validation failed
			foreach($captcha->get_errors() as $error)
			{
				$errors[] = $error;
			}
		}
	}

	if(empty($errors))
	{
		// Email the administrator
		my_mail($mybb->settings['adminemail'], trim_blank_chrs($mybb->input['subject']), trim_blank_chrs($mybb->input['message']), trim_blank_chrs($mybb->input['email']));

		// Redirect
		redirect('contact.php', $lang->contact_success_message);
	}
	else
	{
		$errors = inline_error($errors);
	}
}

// Generate CAPTCHA?
if($mybb->settings['captchaimage'])
{
	$post_captcha = new captcha(true, "post_captcha");

	if($post_captcha->html)
	{
		$captcha = $post_captcha->html;
	}
}
else
{
	$captcha = '';
}

$mybb->input['subject'] = htmlspecialchars_uni($mybb->input['subject']);
$mybb->input['message'] = htmlspecialchars_uni($mybb->input['message']);
$mybb->input['email'] = htmlspecialchars_uni($mybb->input['email']);

// Contact page
eval("\$page = \"".$templates->get("contact")."\";");
output_page($page);
exit;

?>