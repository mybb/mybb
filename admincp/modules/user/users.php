<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// should also have a 'view coppa awaiting activation' view
require_once MYBB_ROOT."inc/functions_upload.php";


$page->add_breadcrumb_item("Users", "index.php?".SID."&amp;module=user/users");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "merge" || $mybb->input['action'] == "search" || !$mybb->input['action'])
{
	$sub_tabs['browse_users'] = array(
		'title' => "Browse Users",
		'link' => "index.php?".SID."&amp;module=user/users",
		'description' => "Below you can browse users of your forums in different defined views. Views are particularly useful for generating different result sets with different information - think of them as saved live searches."
	);

	$sub_tabs['find_users'] = array(
		'title' => "Find Users",
		'link' => "index.php?".SID."&amp;module=user/users&amp;action=search",
		'description' => "Here you can search for users of your forum. The fewer fields you fill in, the broader your search is; the more you fill in, the narrower your search is."
	);

	$sub_tabs['create_user'] = array(
		'title' => "Create New User",
		'link' => "index.php?".SID."&amp;module=user/users&amp;action=add",
		'description' => "Here you can create a new user."
	);

	$sub_tabs['merge_users'] = array(
		'title' => "Merge Users",
		'link' => "index.php?".SID."&module=user/users&action=merge",
		'description' => "Here you can merge two user accounts in to one. The \"Source Account\" will  be merged in to the \"Destination Account\" leaving <strong>only</strong> the destination account. The source accounts posts, threads, private messages, calendar events, post count and buddy list will be merged in to the destination account.<br /><span style=\"font-size: 15px;\">Please be aware that this process cannot be undone.</span>"
	);
}

$user_view_fields = array(
	"avatar" => array(
		"title" => "Avatar",
		"width" => "24",
		"align" => ""
	),

	"username" => array(
		"title" => "Username",
		"width" => "",
		"align" => ""
	),

	"email" => array(
		"title" => "Email",
		"width" => "",
		"align" => "center"
	),

	"usergroup" => array(
		"title" => "Primary Group",
		"width" => "",
		"align" => "center"
	),

	"additionalgroups" => array(
		"title" => "Additional Groups",
		"width" => "",
		"align" => "center"
	),

	"regdate" => array(
		"title" => "Registered",
		"width" => "",
		"align" => "center"
	),

	"lastactive" => array(
		"title" => "Last Active",
		"width" => "",
		"align" => "center"
	),

	"postnum" => array(
		"title" => "Post Count",
		"width" => "",
		"align" => "center"
	),

	"reputation" => array(
		"title" => "Reputation",
		"width" => "",
		"align" => "center"
	),

	"warninglevel" => array(
		"title" => "Warning Level",
		"width" => "",
		"align" => "center"
	),

	"regip" => array(
		"title" => "Registration IP",
		"width" => "",
		"align" => "center"
	),

	"lastip" => array(
		"title" => "Last Known IP",
		"width" => "",
		"align" => "center"
	),

	"controls" => array(
		"title" => "Controls",
		"width" => "",
		"align" => "center"
	)
);

$sort_options = array(
	"username" => "Username",
	"regdate" => "Registration Date",
	"lastactive" => "Last Active",
	"numposts" => "Post Count",
	"reputation" => "Reputation",
	"warninglevel" => "Warning Level"
);

// Initialise the views manager for user based views
require MYBB_ADMIN_DIR."inc/functions_view_manager.php";
if($mybb->input['action'] == "views")
{
	view_manager("index.php?".SID."&amp;module=user/users", "user", $user_view_fields, $sort_options, "user_search_conditions");
}

if($mybb->input['action'] == "avatar_gallery")
{
	$user = get_user($mybb->input['uid']);
	if(!$user['uid'])
	{
		exit;
	}

	// We've selected a new avatar for this user!
	if($mybb->input['avatar'])
	{
		if(file_exists("../".$mybb->settings['avatardir']."/".$mybb->input['avatar']))
		{
			$dimensions = @getimagesize("../".$mybb->settings['avatardir']."/".$mybb->input['avatar']);
			$updated_avatar = array(
				"avatar" => $db->escape_string($mybb->settings['avatardir']."/".$mybb->input['avatar']),
				"avatardimensions" => "{$dimensions[0]}|{$dimensions[1]}",
				"avatartype" => "gallery"
			);

			$db->update_query("users", $updated_avatar, "uid='".$user['uid']."'");

			// Log admin action
			log_admin_action($user['uid'], $user['username']);
		}
		remove_avatars($mybb->user['uid']);
		// Now a tad of javascript to submit the parent window form
		echo "<script type=\"text/javascript\">window.parent.submitUserForm();</script>";
		exit;
	}

	echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
	echo "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n";
	echo "<head profile=\"http://gmpg.org/xfn/1\">\n";
	echo "	<title>Avatar Gallery</title>\n";
	echo "	<link rel=\"stylesheet\" href=\"styles/".$page->style."/main.css\" type=\"text/css\" />\n";
	echo "	<link rel=\"stylesheet\" href=\"styles/".$page->style."/avatar_gallery.css\" type=\"text/css\" />\n";
	echo "	<script type=\"text/javascript\" src=\"../jscripts/prototype.js\"></script>\n";
	echo "	<script type=\"text/javascript\" src=\"../jscripts/general.js\"></script>\n";
	echo "</head>\n";
	echo "<body id=\"avatar_gallery\">\n";

	// Sanitize incoming path if we have one
	$gallery = str_replace(array("..", "\x0"), "", $mybb->input['gallery']);
	
	$breadcrumb = "<a href=\"index.php?".SID."&amp;module=user/users&action=avatar_gallery&uid={$user['uid']}\">Default Gallery</a>";

	$mybb->settings['avatardir'] = "../".$mybb->settings['avatardir'];

	// Within a gallery
	if($gallery)
	{
		$path = $gallery."/";
		$real_path = $mybb->settings['avatardir']."/".$path;
		if(is_dir($path))
		{
			// Build friendly gallery breadcrumb
			$gallery_path = explode("/", $gallery);
			foreach($gallery_path as $key => $url_bit)
			{
				if($breadcrumb_url) $breadcrumb_url .= "/";
				$breadcrumb_url .= $url_bit;
				$gallery_name = str_replace(array("_", "%20"), " ", $url_bit);
				$gallery_name = ucwords($gallery_name);

				if($gallery_path[$key+1])
				{
					$breadcrumb .= " &raquo; <a href=\"index.php?".SID."&amp;module=user/users&action=avatar_gallery&uid={$user['uid']}&amp;gallery={$breadcrumb_url}\">{$gallery_name}</a>";
				}
				else
				{
					$breadcrumb .= " &raquo; {$gallery_name}";
				}
			}
		}
		else
		{
			exit;
		}
	}
	else
	{
		$path = "";
		$real_path = $mybb->settings['avatardir'];
	}

	// Get a listing of avatars/directories within this gallery
	$sub_galleries = $avatars = array();
	$files = @scandir($real_path);
	foreach($files as $file)
	{
		if($file == "." || $file == ".." || $file == ".svn") continue;
		// Build friendly name
		$friendly_name = str_replace(array("_", "%20"), " ", $file);
		$friendly_name = ucwords($friendly_name);
		if(is_dir($real_path."/".$file))
		{
			// Only add this gallery if there are avatars or galleries inside it (no empty directories!)
			$has = 0;
			$dh = @opendir($real_path."/".$file);
			while(false !== ($sub_file = readdir($dh)))
			{
				if(preg_match("#\.(jpg|jpeg|gif|bmp|png)$#i", $sub_file) || is_dir($real_path."/".$file."/".$sub_file))
				{
					$has = 1;
					break;
				}
			}
			@closedir($dh);
			if($has == 1)
			{
				$sub_galleries[] = array(
					"path" => $path.$file,
					"friendly_name" => $friendly_name
				);
			}
		}
		else if(preg_match("#\.(jpg|jpeg|gif|bmp|png)$#i", $file))
		{
			$friendly_name = preg_replace("#\.(jpg|jpeg|gif|bmp|png)$#i", "", $friendly_name);

			// Fetch dimensions
			$dimensions = @getimagesize($real_path."/".$file);

			$avatars[] = array(
				"path" => $path.$file,
				"friendly_name" => $friendly_name,
				"width" => $dimensions[0],
				"height" => $dimensions[1]
			);
		}
	}

	require_once MYBB_ROOT."inc/functions_image.php";

	// Now we're done, we can simply show our gallery page
	echo "<div id=\"gallery_breadcrumb\">{$breadcrumb}</div>\n";
	echo "<div id=\"gallery\">\n";
	echo "<ul id=\"galleries\">\n";
	if(is_array($sub_galleries))
	{
		foreach($sub_galleries as $gallery)
		{
			if(!$gallery['thumb'])
			{
				$gallery['thumb'] = "styles/{$page->style}/images/avatar_gallery.gif";
				$gallery['thumb_width'] = 64;
				$gallery['thumb_height'] = 64;
			}
			else
			{
				$gallery['thumb'] = "../{$mybb->settings['avatardir']}/{$gallery['thumb']}";
			}
			$scaled_dimensions = scale_image($gallery['thumb_width'], $gallery['thumb_height'], 80, 80);
			$top = ceil((80-$scaled_dimensions['height'])/2);
			$left = ceil((80-$scaled_dimensions['width'])/2);
			echo "<li><a href=\"index.php?".SID."&amp;module=user/users&action=avatar_gallery&uid={$user['uid']}&gallery={$gallery['path']}\"><span class=\"image\"><img src=\"{$gallery['thumb']}\" alt=\"\" style=\"margin-top: {$top}px;\" align=\"center\"  height=\"{$scaled_dimensions['height']}\" width=\"{$scaled_dimensions['width']}\"></span><span class=\"title\">{$gallery['friendly_name']}</span></a></li>\n";
		}
	}
	echo "</ul>\n";
	// Build the list of any actual avatars we have
	echo "<ul id=\"avatars\">\n";
	if(is_array($avatars))
	{
		foreach($avatars as $avatar)
		{
			$scaled_dimensions = scale_image($avatar['width'], $avatar['height'], 80, 80);
			$top = ceil((80-$scaled_dimensions['height'])/2);
			$left = ceil((80-$scaled_dimensions['width'])/2);
			echo "<li><a href=\"index.php?".SID."&amp;module=user/users&action=avatar_gallery&uid={$user['uid']}&avatar={$avatar['path']}\"><span class=\"image\"><img src=\"../{$mybb->settings['avatardir']}/{$avatar['path']}\" alt=\"\" style=\"margin-top: {$top}px;\" align=\"center\" height=\"{$scaled_dimensions['height']}\" width=\"{$scaled_dimensions['width']}\" /></span><span class=\"title\">{$avatar['friendly_name']}</span></a></li>\n";
		}
	}
	echo "</ul>\n";
	echo "</div>";
	echo "</div>";
	echo "</body>";
	echo "</html>";
	exit;
}

if($mybb->input['action'] == "coppa_activate")
{
	$query = $db->simple_select("users", "*", "uid='".intval($mybb->input['uid'])."'");
	$user = $db->fetch_array($query);

	// Does the user not exist?
	if(!$user['uid'] || $user['coppauser'] != 1)
	{
		flash_message("You have selected an invalid user.", 'error');
		admin_redirect("index.php?".SID."&module=user/users");
	}

	// Update
	$updated_user = array(
		"coppauser" => 0
	);

	// Move out of awaiting activation if they're in it.
	if($user['usergroup'] == 5)
	{
		$updated_user['usergroup'] = 2;
	}

	$db->update_query("users", $updated_user, "uid='{$user['uid']}'");

	// Log admin action
	log_admin_action($user['uid'], $user['username']);

	flash_message("The COPPA user has successfully been activated.", 'success');
	admin_redirect("index.php?".SID."&module=user/users&amp;action=edit&amp;uid={$user['uid']}");
}

if($mybb->input['action'] == "add")
{
	if($mybb->request_method == "post")
	{
		// Determine the usergroup stuff
		if(is_array($mybb->input['additionalgroups']))
		{
			foreach($mybb->input['additionalgroups'] as $gid)
			{
				if($gid == $mybb->input['usergroup'])
				{
					unset($mybb->input['additionalgroups'][$gid]);
				}
			}
			$additionalgroups = implode(",", $mybb->input['additionalgroups']);
		}
		else
		{
			$additionalgroups = '';
		}

		// Set up user handler.
		require_once MYBB_ROOT."inc/datahandlers/user.php";
		$userhandler = new UserDataHandler('update');

		// Set the data for the new user.
		$new_user = array(
			"uid" => $mybb->input['uid'],
			"username" => $mybb->input['username'],
			"password" => $mybb->input['password'],
			"password2" => $mybb->input['confirm_password'],
			"email" => $mybb->input['email'],
			"email2" => $mybb->input['email'],
			"usergroup" => $mybb->input['usergroup'],
			"additionalgroups" => $additionalgroups,
			"displaygroup" => $mybb->input['displaygroup'],
			"profile_fields" => $mybb->input['profile_fields'],
			"profile_fields_editable" => true,
		);

		// Set the data of the user in the datahandler.
		$userhandler->set_data($new_user);
		$errors = '';

		// Validate the user and get any errors that might have occurred.
		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			$user_info = $userhandler->insert_user();

			// Log admin action
			log_admin_action($user_info['uid'], $user_info['username']);

			flash_message("The user has successfully been created.", 'success');
			admin_redirect("index.php?".SID."&module=user/users&action=edit&uid={$user_info['uid']}");
		}
	}

	// Fetch custom profile fields - only need required profile fields here
	$query = $db->simple_select("profilefields", "*", "required=1", array('order_by' => 'disporder'));
	while($profile_field = $db->fetch_array($query))
	{
		$profile_fields['required'][] = $profile_field;
	}

	$page->output_header("Create New User");
		
	$form = new Form("index.php?".SID."&module=user/users&action=add", "post");

	$page->output_nav_tabs($sub_tabs, 'create_user');

	// If we have any error messages, show them
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = array(
			"usergroup" => 2
		);
	}

	$form_container = new FormContainer("Required Profile Information");
	$form_container->output_row("Username <em>*</em>", "", $form->generate_text_box('username', $mybb->input['username'], array('id' => 'username')), 'username');
	$form_container->output_row("Password", "", $form->generate_password_box('password', $mybb->input['password'], array('id' => 'password')), 'password');
	$form_container->output_row("Confirm Password", "", $form->generate_password_box('confirm_password', $mybb->input['confirm_password'], array('id' => 'confirm_password')), 'confirm_new_password');
	$form_container->output_row("Email Address <em>*</em>", "", $form->generate_text_box('email', $mybb->input['email'], array('id' => 'email')), 'email');

	$display_group_options[0] = "Use Primary User Group";
	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		$options[$usergroup['gid']] = $usergroup['title'];
		$display_group_options[$usergroup['gid']] = $usergroup['title'];
	}

	$form_container->output_row("Primary User Group <em>*</em>", "", $form->generate_select_box('usergroup', $options, $mybb->input['usergroup'], array('id' => 'usergroup')), 'usergroup');
	$form_container->output_row("Additional User Groups", "Use CTRL to select multiple groups", $form->generate_select_box('additionalgroups[]', $options, $mybb->input['additionalgroups'], array('id' => 'additionalgroups', 'multiple' => true, 'size' => 5)), 'additionalgroups');
	$form_container->output_row("Display User Group <em>*</em>", "", $form->generate_select_box('displaygroup', $display_group_options, $mybb->input['displaygroup'], array('id' => 'displaygroup')), 'displaygroup');

	// Output custom profile fields - required
	output_custom_profile_fields($profile_fields['required'], $mybb->input['profile_fields'], $form_container, $form);

	$form_container->end();
	$buttons[] = $form->generate_submit_button("Save User");
	$form->output_submit_wrapper($buttons);

	$form->end();
	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("users", "*", "uid='".intval($mybb->input['uid'])."'");
	$user = $db->fetch_array($query);

	// Does the user not exist?
	if(!$user['uid'])
	{
		flash_message("You have selected an invalid user", 'error');
		admin_redirect("index.php?".SID."&module=user/users");
	}

	if($mybb->request_method == "post")
	{
		if(is_super_admin($mybb->input['uid']) && $mybb->user['uid'] != $mybb->input['uid'] && !is_super_admin($mybb->user['uid']))
		{
			flash_message("You do not have permission to edit this user because you are not a super administrator.", 'error');
			admin_redirect("index.php?".SID."&module=user/users");
		}

		// Determine the usergroup stuff
		if(is_array($mybb->input['additionalgroups']))
		{
			foreach($mybb->input['additionalgroups'] as $gid)
			{
				if($gid == $mybb->input['usergroup'])
				{
					unset($mybb->input['additionalgroups'][$gid]);
				}
			}
			$additionalgroups = implode(",", $mybb->input['additionalgroups']);
		}
		else
		{
			$additionalgroups = '';
		}

		// Set up user handler.
		require_once MYBB_ROOT."inc/datahandlers/user.php";
		$userhandler = new UserDataHandler('update');

		// Set the data for the new user.
		$updated_user = array(
			"uid" => $mybb->input['uid'],
			"username" => $mybb->input['username'],
			"email" => $mybb->input['email'],
			"email2" => $mybb->input['email'],
			"usergroup" => $mybb->input['usergroup'],
			"additionalgroups" => $additionalgroups,
			"displaygroup" => $mybb->input['displaygroup'],
			"postnum" => $mybb->input['postnum'],
			"usertitle" => $mybb->input['usertitle'],
			"timezone" => $mybb->input['timezoneoffset'],
			"language" => $mybb->input['language'],
			"profile_fields" => $mybb->input['profile_fields'],
			"profile_fields_editable" => true,
			"website" => $mybb->input['website'],
			"icq" => $mybb->input['icq'],
			"aim" => $mybb->input['aim'],
			"yahoo" => $mybb->input['yahoo'],
			"msn" => $mybb->input['msn'],
			"style" => $mybb->input['style'],
			"signature" => $mybb->input['signature'],
			"dateformat" => intval($mybb->input['dateformat']),
			"timeformat" => intval($mybb->input['timeformat']),
			"language" => $mybb->input['language']
		);

		if($user['usergroup'] == 5 && $mybb->input['usergroup'] != 5)
		{
			if($user['coppauser'] == 1)
			{
				$updated_user['coppa_user'] = 0;
			}
		}
		if($mybb->input['new_password'])
		{
			$updated_user['password'] = $mybb->input['new_password'];
			$updated_user['password2'] = $mybb->input['confirm_new_password'];
		}

		$updated_user['birthday'] = array(
			"day" => $mybb->input['birthday_day'],
			"month" => $mybb->input['birthday_month'],
			"year" => $mybb->input['birthday_year']
		);

		$updated_user['options'] = array(
			"allownotices" => $mybb->input['allownotices'],
			"hideemail" => $mybb->input['hideemail'],
			"subscriptionmethod" => $mybb->input['subscriptionmethod'],
			"invisible" => $mybb->input['invisible'],
			"dstcorrection" => $mybb->input['dstcorrection'],
			"threadmode" => $mybb->input['threadmode'],
			"showsigs" => $mybb->input['showsigs'],
			"showavatars" => $mybb->input['showavatars'],
			"showquickreply" => $mybb->input['showquickreply'],
			"remember" => $mybb->input['remember'],
			"receivepms" => $mybb->input['receivepms'],
			"pmnotice" => $mybb->input['pmnotice'],
			"daysprune" => $mybb->input['daysprune'],
			"showcodebuttons" => intval($mybb->input['showcodebuttons']),
			"pmnotify" => $mybb->input['pmnotify'],
			"showredirect" => $mybb->input['showredirect']
		);

		if($mybb->settings['usertppoptions'])
		{
			$updated_user['options']['tpp'] = intval($mybb->input['tpp']);
		}

		if($mybb->settings['userpppoptions'])
		{
			$updated_user['options']['ppp'] = intval($mybb->input['ppp']);
		}

		// Set the data of the user in the datahandler.
		$userhandler->set_data($updated_user);
		$errors = '';

		// Validate the user and get any errors that might have occurred.
		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			// Are we removing an avatar from this user?
			if($mybb->input['remove_avatar'])
			{
				$extra_user_updates = array(
					"avatar" => "",
					"avatardimensions" => "",
					"avatartype" => ""
				);
				remove_avatars($mybb->user['uid']);
			}


			// Are we uploading a new avatar?
			if($_FILES['avatar_upload']['name'])
			{
				$avatar = upload_avatar($_FILES['avatar_upload'], $user['uid']);
				if($avatar['error'])
				{
					$errors[] = array($avatar['error']);
				}
				else
				{
					if($avatar['width'] > 0 && $avatar['height'] > 0)
					{
						$avatar_dimensions = $avatar['width']."|".$avatar['height'];
					}
					$extra_user_updates = array(
						"avatar" => $avatar['avatar'],
						"avatardimensions" => $avatar_dimensions,
						"avatartype" => "upload"
					);
				}
			}
			// Are we setting a new avatar from a URL?
			else if($mybb->input['avatar_url'] && $mybb->input['avatar_url'] != $user['avatar'])
			{
				$mybb->input['avatar_url'] = preg_replace("#script:#i", "", $mybb->input['avatar_url']);
				$mybb->input['avatar_url'] = htmlspecialchars($mybb->input['avatar_url']);
				$ext = get_extension($mybb->input['avatar_url']);

				// Copy the avatar to the local server (work around remote URL access disabled for getimagesize)
				$file = fetch_remote_file($mybb->input['avatar_url']);
				if(!$file)
				{
					$avatar_error = $lang->error_invalidavatarurl;
				}
				else
				{
					$tmp_name = "../".$mybb->settings['avataruploadpath']."/remote_".md5(uniqid(rand(), true));
					$fp = @fopen($tmp_name, "wb");
					if(!$fp)
					{
						$avatar_error = $lang->error_invalidavatarurl;
					}
					else
					{
						fwrite($fp, $file);
						fclose($fp);
						list($width, $height, $type) = @getimagesize($tmp_name);
						@unlink($tmp_name);
						echo $type;
						if(!$type)
						{
							$avatar_error = $lang->error_invalidavatarurl;
						}
					}
				}

				if(empty($avatar_error))
				{
					if($width && $height && $mybb->settings['maxavatardims'] != "")
					{
						list($maxwidth, $maxheight) = explode("x", $mybb->settings['maxavatardims']);
						if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
						{
							$lang->error_avatartoobig = sprintf($lang->error_avatartoobig, $maxwidth, $maxheight);
							$avatar_error = $lang->error_avatartoobig;
						}
					}
				}
				
				if(empty($avatar_error))
				{
					if($width > 0 && $height > 0)
					{
						$avatar_dimensions = intval($width)."|".intval($height);
					}
					$extra_user_updates = array(
						"avatar" => $db->escape_string($mybb->input['avatar_url']),
						"avatardimensions" => $avatar_dimensions,
						"avatartype" => "remote"
					);
					remove_avatars($user['uid']);
				}
				else
				{
					$errors = array($avatar_error);
				}
			}

			if(!$errors)
			{
				$user_info = $userhandler->update_user();
				$db->update_query("users", $extra_user_updates, "uid='{$user['uid']}'");

				// Log admin action
				log_admin_action($user['uid'], $mybb->input['username']);

				flash_message("The user has successfully been updated.", 'success');
				admin_redirect("index.php?".SID."&module=user/users");
			}
		}
	}

	if(!$errors)
	{
		$mybb->input = $user;

		// We need to fetch this users profile field values
		$query = $db->simple_select("userfields", "*", "ufid='{$user['uid']}'");
		$mybb->input['profile_fields'] = $db->fetch_array($query);
	}

	// Fetch custom profile fields
	$query = $db->simple_select("profilefields", "*", "", array('order_by' => 'disporder'));
	while($profile_field = $db->fetch_array($query))
	{
		if($profile_field['required'] == 1)
		{
			$profile_fields['required'][] = $profile_field;
		}
		else
		{
			$profile_fields['optional'][] = $profile_field;
		}
	}

	$page->output_header("Edit User");
		
	$sub_tabs['edit_user'] = array(
		'title' => "Edit User",
		'description' => "Here you can edit this users profile, settings, and signature; see general statistics; and visit other pages for further information relating to this user."
	);

	$form = new Form("index.php?".SID."&module=user/users&action=edit&uid={$user['uid']}", "post");
	echo "<script type=\"text/javascript\">\n function submitUserForm() { $('tab_overview').up('FORM').submit(); }</script>\n";

	$page->output_nav_tabs($sub_tabs, 'edit_user');

	// If we have any error messages, show them
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$tabs = array(
		"overview" => "Overview",
		"profile" => "Profile",
		"settings" => "Account Settings",
		"signature" => "Signature",
		"avatar" => "Avatar"
	);
	$page->output_tab_control($tabs);

	// Is this user a COPPA user? We show a warning & activate link
	if($user['coppauser'])
	{
		echo "<p class=\"alert\"><strong>Warning: </strong> This user is awaiting COPPA validation. <a href=\"index.php?".SID."&module=user/users&action=coppa_activate&uid={$user['uid']}\">Activate Account</a></p>";
	}

	//
	// OVERVIEW
	//
	echo "<div id=\"tab_overview\">\n";
	$table = new Table;
	$table->construct_header("Avatar", array('class' => 'align_center'));
	$table->construct_header("General Account Statistics", array('colspan' => '2', 'class' => 'align_center'));

	// Avatar
	$avatar_dimensions = explode("|", $user['avatardimensions']);
	if($user['avatar'])
	{
		if($user['avatardimensions'])
		{
			require_once MYBB_ROOT."inc/functions_image.php";
			list($width, $height) = explode("|", $user['avatardimensions']);
			$scaled_dimensions = scale_image($width, $height, 120, 120);
		}
		else
		{
			$scaled_dimensions = array(
				"width" => 120,
				"height" => 120
			);
		}
		if (!stristr($user['avatar'], 'http://'))
		{
			$user['avatar'] = "../{$user['avatar']}\n";
		}
	}
	else
	{
		$user['avatar'] = "styles/{$page->style}/images/default_avatar.gif";
		$scaled_dimensions = array(
			"width" => 120,
			"height" => 120
		);
	}
	$avatar_top = ceil((126-$scaled_dimensions['height'])/2);
	if($user['lastactive'])
	{
		$last_active = my_date($mybb->settings['dateformat'], $user['lastactive']).", ".my_date($mybb->settings['timeformat'], $user['lastactive']);
	}
	else
	{
		$last_active = "Never";
	}
	$reg_date = my_date($mybb->settings['dateformat'], $user['regdate']).", ".my_date($mybb->settings['timeformat'], $user['regdate']);
	if($user['dst'] == 1)
	{
		$timezone = $user['timezone']+1;
	}
	else
	{
		$timezone = $user['timezone'];
	}
	$local_time = gmdate($mybb->settings['dateformat'], TIME_NOW + ($timezone * 3600)).", ".gmdate($mybb->settings['timeformat'], TIME_NOW + ($timezone * 3600));
	$days_registered = (TIME_NOW - $user['regdate']) / (24*3600);
	$posts_per_day = round($user['postnum'] / $daysreg, 2);
	if($posts_per_day > $user['postnum'])
	{
		$posts_per_day = $user['postnum'];
	}
	$stats = $cache->read("stats");
	$posts = $stats['numposts'];
	if($posts == 0)
	{
		$percent_posts = "0";
	}
	else
	{
		$percent_posts = round($memprofile['postnum']*100/$posts, 2);;
	}

	$user_permissions = user_permissions($user['uid']);

	// Fetch the reputation for this user
	if($user_permissions['usereputationsystem'] == 1 && $mybb->settings['enablereputation'] == 1)
	{
		$reputation = get_reputation($user['reputation']);
	}
	else
	{
		$reputation = "-";
	}

	if($mybb->settings['enablewarningsystem'] != 0 && $user_permissions['canreceivewarnings'] != 0)
	{
		$warning_level = round($user['warningpoints']/$mybb->settings['maxwarningpoints']*100);
		if($warning_level > 100)
		{
			$warning_level = 100;
		}
		$warning_level = get_colored_warning_level($warning_level);
	}

	$table->construct_cell("<div style=\"width: 126px; height: 126px;\" class=\"user_avatar\"><img src=\"{$user['avatar']}\" style=\"margin-top: {$avatar_top}px\" width=\"{$scaled_dimensions['width']}\" height=\"{$scaled_dimensions['height']}\" alt=\"\" /></div>", array('rowspan' => 6, 'width' => 1));
	$table->construct_cell("<strong>Email Address:</strong> <a href=\"mailto:".htmlspecialchars_uni($user['email'])."\">".htmlspecialchars_uni($user['email'])."</a>");
	$table->construct_cell("<strong>Last Active:</strong> {$last_active}");
	$table->construct_row();
	$table->construct_cell("<strong>Registration Date:</strong> {$reg_date}");
	$table->construct_cell("<strong>Local Time:</strong> {$local_time}");
	$table->construct_row();
	$table->construct_cell("<strong>Posts:</strong> {$user['postnum']}");
	$table->construct_cell("<strong>Age:</strong> {$age}");
	$table->construct_row();
	$table->construct_cell("<strong>Posts per day:</strong> {$posts_per_day}");
	$table->construct_cell("<strong>Reputation:</strong> {$reputation}");
	$table->construct_row();
	$table->construct_cell("<strong>Percent of total posts:</strong> {$percent_posts}");
	$table->construct_cell("<strong>Warning Level:</strong> {$warning_level}");
	$table->construct_row();
	$table->construct_cell("<strong>Registration IP:</strong> {$user['regip']}");
	$table->construct_cell("<strong>Last Known IP:</strong> {$user['lastip']}");
	$table->construct_row();
	
	$table->output("User Overview: {$user['username']}");
	echo "</div>\n";

	//
	// PROFILE
	//
	echo "<div id=\"tab_profile\">\n";

	$form_container = new FormContainer("Required Profile Information: {$user['username']}");
	$form_container->output_row("Username <em>*</em>", "", $form->generate_text_box('username', $mybb->input['username'], array('id' => 'username')), 'username');
	$form_container->output_row("New Password", "Only required if changing", $form->generate_password_box('new_password', $mybb->input['new_password'], array('id' => 'new_password')), 'new_password');
	$form_container->output_row("Confirm New Password", "Only required if changing", $form->generate_password_box('confirm_new_password', $mybb->input['confirm_new_password'], array('id' => 'confirm_new_password')), 'confirm_new_password');
	$form_container->output_row("Email Address <em>*</em>", "", $form->generate_text_box('email', $mybb->input['email'], array('id' => 'email')), 'email');

	$display_group_options[0] = "Use Primary User Group";
	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		$options[$usergroup['gid']] = $usergroup['title'];
		$display_group_options[$usergroup['gid']] = $usergroup['title'];
	}

	$form_container->output_row("Primary User Group <em>*</em>", "", $form->generate_select_box('usergroup', $options, $mybb->input['usergroup'], array('id' => 'usergroup')), 'usergroup');
	$form_container->output_row("Additional User Groups", "Use CTRL to select multiple groups", $form->generate_select_box('additionalgroups[]', $options, $mybb->input['additionalgroups'], array('id' => 'additionalgroups', 'multiple' => true, 'size' => 5)), 'additionalgroups');
	$form_container->output_row("Display User Group <em>*</em>", "", $form->generate_select_box('displaygroup', $display_group_options, $mybb->input['displaygroup'], array('id' => 'displaygroup')), 'displaygroup');
	$form_container->output_row("Post Count <em>*</em>", "", $form->generate_text_box('postnum', $mybb->input['postnum'], array('id' => 'postnum')), 'postnum');

	// Output custom profile fields - required
	output_custom_profile_fields($profile_fields['required'], $mybb->input['profile_fields'], $form_container, $form);

	$form_container->end();
	
	$form_container = new FormContainer("Optional Profile Information: {$user['username']}");
	$form_container->output_row("Custom User Title", "If empty, the group user title will be used", $form->generate_text_box('usertitle', $mybb->input['usertitle'], array('id' => 'usertitle')), 'usertitle');
	$form_container->output_row("Website", "", $form->generate_text_box('website', $mybb->input['website'], array('id' => 'website')), 'website');
	$form_container->output_row("ICQ Number", "", $form->generate_text_box('icq', $mybb->input['icq'], array('id' => 'icq')), 'icq');
	$form_container->output_row("AIM Handle", "", $form->generate_text_box('aim', $mybb->input['aim'], array('id' => 'aim')), 'aim');
	$form_container->output_row("Yahoo! Messenger Handle", "", $form->generate_text_box('yahoo', $mybb->input['yahoo'], array('id' => 'yahoo')), 'yahoo');
	$form_container->output_row("MSN Messenger Handle", "", $form->generate_text_box('msn', $mybb->input['msn'], array('id' => 'msn')), 'msn');
	// Birthday

	// Output custom profile fields - optional
	output_custom_profile_fields($profile_fields['optional'], $mybb->input['profile_fields'], $form_container, $form);

	$form_container->end();
	echo "</div>\n";

	//
	// ACCOUNT SETTINGS
	//

	// Plugin hook note - we should add hooks in above each output_row for the below so users can add their own options to each group :>

	echo "<div id=\"tab_settings\">\n";
	$form_container = new FormContainer("Account Settings: {$user['username']}");
	$login_options = array(
		$form->generate_check_box("invisible", 1, "Hide from the Who's Online list", array("checked" => $mybb->input['invisible'])),
		$form->generate_check_box("remember", 1, "Remember login details for future visits", array("checked" => $mybb->input['remember']))
	);
	$form_container->output_row("Login, Cookies &amp; Privacy", "", "<div class=\"user_settings_bit\">".implode("</div><div class=\"user_settings_bit\">", $login_options)."</div>");

	$messaging_options = array(
		$form->generate_check_box("allownotices", 1, "Receive emails from administrators", array("checked" => $mybb->input['allownotices'])),
		$form->generate_check_box("hideemail", 1, "Hide email address from other members", array("checked" => $mybb->input['hideemail'])),
		$form->generate_check_box("receivepms", 1, "Receive private messages from other users", array("checked" => $mybb->input['receivepms'])),
		$form->generate_check_box("pmnotice", 1, "Alert with notice when new private message is received", array("checked" => $mybb->input['pmnotice'])),
		$form->generate_check_box("pmnotify", 1, "Notify by email when new private message is received", array("checked" => $mybb->input['pmnotify'])),
		"<label for=\"subscriptionmethod\">Default thread subscription mode:</label><br />".$form->generate_select_box("subscriptionmethod", array("Do not subscribe", "No email notification", "Instant email notification"), $mybb->input['subscriptionmethod'], array('id' => 'subscriptionmethod'))
	);
	$form_container->output_row("Messaging &amp; Notification", "", "<div class=\"user_settings_bit\">".implode("</div><div class=\"user_settings_bit\">", $messaging_options)."</div>");

	$date_format_options = array("Use Default");
	foreach($date_formats as $key => $format)
	{
		$date_format_options[$key] = my_date($format, TIME_NOW, "", 0);
	}

	$time_format_options = array("Use Default");
	foreach($time_formats as $key => $format)
	{
		$time_format_options[$key] = my_date($format, TIME_NOW, "", 0);
	}

	$date_options = array(
		"<label for=\"dateformat\">Date Format:</label><br />".$form->generate_select_box("dateformat", $date_format_options, $mybb->input['dateformat'], array('id' => 'dateformat')),
		"<label for=\"dateformat\">Time Format:</label><br />".$form->generate_select_box("timeformat", $time_format_options, $mybb->input['timeformat'], array('id' => 'timeformat')),
		"<label for=\"timezone\">Time Zone:</label><br />".build_timezone_select("timezone", $mybb->user['timezone']),
		"<label for=\"dstcorrection\">Daylight Savings Time correction:</label><br />".$form->generate_select_box("dstcorrection", array(2 => "Automatically detect DST settings", 1 => "Always use DST correction", 1 => "Never use DST correction"), $mybb->input['dstcorrection'], array('id' => 'dstcorrection'))
	);
	$form_container->output_row("Date &amp; Time Options", "", "<div class=\"user_settings_bit\">".implode("</div><div class=\"user_settings_bit\">", $date_options)."</div>");


	$tpp_options = array("Use Default");
	if($mybb->settings['usertppoptions'])
	{
		$explodedtpp = explode(",", $mybb->settings['usertppoptions']);
		if(is_array($explodedtpp))
		{
			foreach($explodedtpp as $tpp)
			{
				if($tpp <= 0) continue;
				$tpp_options[$tpp] = $tpp;
			}
		}
	}

	$thread_age_options = array(
		0 => "Use Default",
		1 => "Show threads from the last day",
		5 => "Show threads from the last 5 days",
		10 => "Show threads from the last 10 days",
		20 => "Show threads from the last 20 days",
		50 => "Show threads from the last 50 days",
		75 => "Show threads from the last 75 days",
		100 => "Show threads from the last 100 days",
		365 => "Show threads from the last 100 days",
		9999 => "Show all threads"
	);

	$forum_options = array(
		"<label for=\"tpp\">Threads Per Page:</label><br />".$form->generate_select_box("tpp", $tpp_options, $mybb->input['tpp'], array('id' => 'tpp')),
		"<label for=\"daysprune\">Default Thread Age View:</label><br />".$form->generate_select_box("daysprune", $thread_age_options, $mybb->input['daysprune'], array('id' => 'daysprune'))
	);
	$form_container->output_row("Forum Display Options", "", "<div class=\"user_settings_bit\">".implode("</div><div class=\"user_settings_bit\">", $forum_options)."</div>");

	$ppp_options = array("Use Default");
	if($mybb->settings['userpppoptions'])
	{
		$explodedppp = explode(",", $mybb->settings['userpppoptions']);
		if(is_array($explodedppp))
		{
			foreach($explodedppp as $ppp)
			{
				if($ppp <= 0) continue;
				$ppp_options[$ppp] = $ppp;
			}
		}
	}

	$thread_options = array(
		$form->generate_check_box("showsigs", 1, "Display users' signatures in their posts", array("checked" => $mybb->input['showsigs'])),
		$form->generate_check_box("showavatars", 1, "Display users' avatars in their posts", array("checked" => $mybb->input['showavatars'])),
		$form->generate_check_box("showquickreply", 1, "Show the quick reply box at the bottom of the thread view", array("checked" => $mybb->input['showquickreply'])),
		"<label for=\"ppp\">Posts Per Page:</label><br />".$form->generate_select_box("ppp", $ppp_options, $mybb->input['ppp'], array('id' => 'ppp')),
		"<label for=\"threadmode\">Default Thread View Mode:</label><br />".$form->generate_select_box("threadmode", array("" => "Use Default", "linear" => "Linear Mode", "threaded" => "Threaded Mode"), $mybb->input['threadmode'], array('id' => 'threadmode'))
	);
	$form_container->output_row("Thread View Options", "", "<div class=\"user_settings_bit\">".implode("</div><div class=\"user_settings_bit\">", $thread_options)."</div>");

	$other_options = array(
		$form->generate_check_box("showredirect", 1, "Show friendly redirection pages", array("checked" => $mybb->input['showredirect'])),
		$form->generate_check_box("showcodebuttons", "1", "Show MyCode formatting options on posting pages", array("checked" => $mybb->input['showcodebuttons'])),
		"<label for=\"style\">Theme:</label><br />".build_theme_select("style", $mybb->input['style'], 0, "", 1),
		"<label for=\"language\">Board Language:</label><br />".$form->generate_select_box("language", $lang->get_languages(), $mybb->input['language'], array('id' => 'language'))
	);
	$form_container->output_row("Other Options", "", "<div class=\"user_settings_bit\">".implode("</div><div class=\"user_settings_bit\">", $other_options)."</div>");

	$form_container->end();
	echo "</div>\n";

	//
	// SIGNATURE EDITOR
	//
	$signature_editor = $form->generate_text_area("signature", $mybb->input['signature'], array('id' => 'signature', 'rows' => 15, 'cols' => '70', 'style' => 'width: 95%'));
	$sig_smilies = "off";
	if($mybb->settings['sigsmilies'] == 1)
	{
		$sig_smilies = "on";
	}
	$sig_mycode = "off";
	if($mybb->settings['sigmycode'] == 1)
	{
		$sig_mycode = "on";
		$signature_editor .= build_mycode_inserter("signature");
	}
	$sig_html = "off";
	if($mybb->settings['sightml'] == 1)
	{
		$sig_html = "on";
	}
	$sig_imcode = "on";
	if($mybb->settings['sigimgcode'] == 1)
	{
		$sig_imgcode = "off";
	}
	echo "<div id=\"tab_signature\">\n";
	$form_container = new FormContainer("Signature: {$user['username']}");
	$form_container->output_row("Signature", "Formatting options: MyCode is {$sig_mycode}, smilies are {$sig_smilies}, IMG code is {$sig_imgcode}, HTML is {$sig_html}", $signature_editor, 'signature');

	$signature_options = array(
		$form->generate_radio_button("update_posts", "enable", "Enable signature in all posts", array("checked" => 0)),
		$form->generate_radio_button("update_posts", "disable", "Disable signature in all posts", array("checked" => 0)),
		$form->generate_radio_button("update_posts", "no", "Do not change signature preferences", array("checked" => 1))
	);

	$form_container->output_row("Signature Preferences", "", implode("<br />", $signature_options));

	$form_container->end();
	echo "</div>\n";

	//
	// AVATAR MANAGER
	//
	echo "<div id=\"tab_avatar\">\n";
	$table = new Table;
	$table->construct_header("Current Avatar", array('colspan' => 2));

	$table->construct_cell("<div style=\"width: 126px; height: 126px;\" class=\"user_avatar\"><img src=\"{$user['avatar']}\" width=\"{$scaled_dimensions['width']}\" style=\"margin-top: {$avatar_top}px\" height=\"{$scaled_dimensions['height']}\" alt=\"\" /></div>", array('width' => 1));

	if($user['avatartype'] == "upload" || stristr($user['avatar'], $mybb->settings['avataruploadpath']))
	{
		$current_avatar_msg = "<br /><strong>This user is currently using an uploaded avatar.</strong>";
	}
	else if($user['avatartype'] == "gallery" || stristr($user['avatar'], $mybb->settings['avatardir']))
	{
		$current_avatar_msg = "<br /><strong>This user is currently using an avatar from the avatar gallery.</strong>";
	}
	elseif($user['avatartype'] == "remote" || my_strpos(my_strtolower($user['avatar']), "http://") !== false)
	{
		$current_avatar_msg = "<br /><strong>This user is currently using a remotely linked avatar.</strong>";
		$avatar_url = $user['avatar'];
	}

	if($errors)
	{
		$avatar_url = $mybb->input['avatar_url'];
	}

	if($mybb->settings['maxavatardims'] != "")
	{
		list($max_width, $max_height) = explode("x", $mybb->settings['maxavatardims']);
		$max_size = "<br />The maximum dimensions for avatars are {$max_width}x{$max_height}";
	}

	if($mybb->settings['avatarsize'])
	{
		$maximum_size = get_friendly_size($mybb->settings['avatarsize']*1024);
		$max_size .= "<br />Avatars can be a maximum of {$maximum_size}";
	}

	if($user['avatar'])
	{
		$remove_avatar = "<br /><br />".$form->generate_check_box("remove_avatar", 1, "<strong>Remove current avatar?</strong>");
	}

	$table->construct_cell("Below you can manage the avatar for this user. Avatars are small identifying images which are placed under the authors username when they make a post.{$remove_avatar}<br /><small>{$max_size}</small>");
	$table->construct_row();
	
	$table->output("Avatar: {$user['username']}");

	// Custom avatar
	if($mybb->settings['avatarresizing'] == "auto")
	{
		$auto_resize = "If the avatar is too large, it will automatically be resized";
	}
	else if($mybb->settings['avatarresizing'] == "user")
	{
		$auto_resize = "<input type=\"checkbox\" name=\"auto_resize\" value=\"1\" checked=\"checked\" id=\"auto_resize\" /> <label for=\"auto_resize\">Attempt to resize this avatar if it is too large?</label></span>";
	}
	$form_container = new FormContainer("Specify Custom Avatar");
	$form_container->output_row("Upload Avatar", $auto_resize, $form->generate_file_upload_box('avatar_upload', array('id' => 'avatar_upload')), 'avatar_upload');
	$form_container->output_row("or Specify Avatar URL", "", $form->generate_text_box('avatar_url', $avatar_url, array('id' => 'avatar_url')), 'avatar_url');
	$form_container->end();

	// Select an image from the gallery
	echo "<div class=\"border_wrapper\">";
	echo "<div class=\"title\">.. or select from Avatar Gallery</div>";
	echo "<iframe src=\"index.php?".SID."&amp;module=user/users&amp;action=avatar_gallery&amp;uid={$user['uid']}\" width=\"100%\" height=\"350\" frameborder=\"0\"></iframe>";
	echo "</div>";
	echo "</div>";

	$buttons[] = $form->generate_submit_button("Save User");
	$form->output_submit_wrapper($buttons);

	$form->end();
	$page->output_footer();

}

if($mybb->input['action'] == "delete")
{
	$query = $db->simple_select("users", "*", "uid='".intval($mybb->input['uid'])."'");
	$user = $db->fetch_array($query);

	// Does the user not exist?
	if(!$user['uid'])
	{
		flash_message("You have selected an invalid user.", 'error');
		admin_redirect("index.php?".SID."&module=user/users");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=user/users");
	}

	if($mybb->request_method == "post")
	{
		// Delete the user
		$db->query("UPDATE ".TABLE_PREFIX."posts SET uid='0' WHERE uid='{$user['uid']}'");
		$db->query("DELETE FROM ".TABLE_PREFIX."users WHERE uid='{$user['uid']}'");
		$db->query("DELETE FROM ".TABLE_PREFIX."userfields WHERE ufid='{$user['uid']}'");
		$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE uid='{$user['uid']}'");
		$db->query("DELETE FROM ".TABLE_PREFIX."events WHERE author='{$user['uid']}'");
		$db->query("DELETE FROM ".TABLE_PREFIX."moderators WHERE uid='{$user['uid']}'");
		$db->query("DELETE FROM ".TABLE_PREFIX."forumsubscriptions WHERE uid='{$user['uid']}'");
		$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE uid='{$user['uid']}'");
		$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE uid='{$user['uid']}'");
		$db->query("DELETE FROM ".TABLE_PREFIX."banned WHERE uid='{$user['uid']}'");

		// Update forum stats
		update_stats(array('numusers' => '-1'));

		// Log admin action
		log_admin_action($user['username']);


		flash_message("The user has successfully been deleted.", 'success');
		admin_redirect("index.php?".SID."&module=user/users");
	}
	else
	{
		$page->output_confirm_action("index.php?".SID."&module=user/users&action=delete&uid={$user['uid']}", $lang->confirm_bot_deletion);
	}
}

if($mybb->input['action'] == "referrers")
{
	$page->output_header("Show Referrers");
	
	$sub_tabs['referrers'] = array(
		'title' => "Show Referrers Users",
		'link' => "index.php?".SID."&amp;module=user/users&amp;action=referrers&amp;uid={$mybb->input['uid']}",
		'description' => "The results to your search criteria are shown below. You can view the results in either a table view or business card view."
	);
	
	$page->output_nav_tabs($sub_tabs, 'referrers');
	
	// Fetch default admin view
	$default_view = fetch_default_view("user");
	$query = $db->simple_select("adminviews", "*", "type='user' AND (vid='{$default_view}' OR uid=0)", array("order_by" => "uid", "order_dir" => "desc"));
	$admin_view = $db->fetch_field($query);

	if($mybb->input['type'])
	{
		$admin_view['view_type'] = $mybb->input['type'];
	}

	echo build_users_view($admin_view);
	
	$page->output_footer();
}

if($mybb->input['action'] == "ipaddresses")
{
	$page->output_header("IP Addresses");
	
	$sub_tabs['ipaddresses'] = array(
		'title' => "Show IP Addresses",
		'link' => "index.php?".SID."&amp;module=user/users&amp;action=ipaddresses&amp;uid={$mybb->input['uid']}",
		'description' => "The registration IP address and the post IPs for the selected users are shown below. The first IP address is the registration IP (it is marked as such). Any other IP addresses are IP addresses the user has posted with."
	);
	
	$page->output_nav_tabs($sub_tabs, 'ipaddresses');
	
	$query = $db->simple_select("users", "uid, regip, username", "uid='{$mybb->input['uid']}'", array('limit' => 1));
	$user = $db->fetch_array($query);

	// Log admin action
	log_admin_action($user['uid'], $user['username']);
	
	$popup = new PopupMenu("user_{$mybb->input['uid']}", $lang->options);
	$popup->add_item("Show users who have registered with this IP", "index.php?".SID."&amp;module=user/users&amp;action=search&amp;regip={$user['regip']}");
	$popup->add_item("Show users who have posted with this IP", "index.php?".SID."&amp;module=user/users&amp;action=search&amp;postip={$user['regip']}");
	$popup->add_item("Ban IP", "index.php?".SID."&amp;module=config/banning&amp;filter={$user['regip']}");
	$controls = $popup->fetch();
	
	$table = new Table;
	
	$table->construct_header("IP Address");
	$table->construct_header($lang->controls, array('width' => 200, 'class' => "align_center"));
	
	$popup = new PopupMenu("user_last", $lang->options);
	$popup->add_item("Show users who have registered with this IP", "index.php?".SID."&amp;module=user/users&amp;action=search&amp;regip={$user['lastip']}");
	$popup->add_item("Show users who have posted with this IP", "index.php?".SID."&amp;module=user/users&amp;action=search&amp;postip={$user['lastip']}");
	$popup->add_item("Ban IP", "index.php?".SID."&amp;module=config/banning&amp;filter={$user['lastip']}");
	$controls = $popup->fetch();
	$table->construct_cell("<strong>Last Known IP:</strong> {$user['lastip']}");
	$table->construct_cell($controls, array('class' => "align_center"));
	$table->construct_row();

	$popup = new PopupMenu("user_reg", $lang->options);
	$popup->add_item("Show users who have registered with this IP", "index.php?".SID."&amp;module=user/users&amp;action=search&amp;regip={$user['regip']}");
	$popup->add_item("Show users who have posted with this IP", "index.php?".SID."&amp;module=user/users&amp;action=search&amp;postip={$user['regip']}");
	$popup->add_item("Ban IP", "index.php?".SID."&amp;module=config/banning&amp;filter={$user['regip']}");
	$controls = $popup->fetch();
	$table->construct_cell("<strong>Registration IP:</strong> {$user['regip']}");
	$table->construct_cell($controls, array('class' => "align_center"));
	$table->construct_row();
	
	$query = $db->simple_select("posts", "DISTINCT ipaddress, pid", "uid='{$mybb->input['uid']}'");
	while($ip = $db->fetch_array($query))
	{
		if(!$done_ip[$ip['ipaddress']])
		{
			$popup = new PopupMenu("post_{$ip['pid']}", $lang->options);
			$popup->add_item("Show users who have registered with this IP", "index.php?".SID."&amp;module=user/users&amp;action=search&amp;regip={$ip['ipaddress']}");
			$popup->add_item("Show users who have posted with this IP", "index.php?".SID."&amp;module=user/users&amp;action=search&amp;postip={$ip['ipaddress']}");
			$popup->add_item("Ban IP", "index.php?".SID."&amp;module=config/banning&amp;filter={$ip['ipaddress']}");
			$controls = $popup->fetch();
		
			$table->construct_cell($ip['ipaddress']);
			$table->construct_cell($controls, array('class' => "align_center"));
			$table->construct_row();
			$done_ip[$ip['ipaddres']] = 1;
		}
	}
	
	$table->output("IP Addresses for {$user['username']}");
	
	$page->output_footer();
}

if($mybb->input['action'] == "merge")
{
	if($mybb->request_method == "post")
	{
		$query = $db->simple_select("users", "*", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['source_username']))."'");
		$source_user = $db->fetch_array($query);
		if(!$source_user['uid'])
		{
			$errors[] = "The source account username you entered does not exist";
		}

		$query = $db->simple_select("users", "*", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['destination_username']))."'");
		$destination_user = $db->fetch_array($query);
		if(!$destination_user['uid'])
		{
			$errors[] = "The destination account username you entered does not exist";
		}

		// Begin to merge the accounts
		$uid_update = array(
			"uid" => $destination_user['uid']
		);
		$query = $db->simple_select("adminoptions", "uid", "uid='{$destination_user['uid']}'");
		$existing_admin_options = $db->fetch_field($query, "uid");

		// Only carry over admin options/permissions if we don't already have them
		if(!$existing_admin_options)
		{
			$db->update_query("adminoptions", $uid_update, "uid='{$source_user['uid']}'");
		}
		
		$db->update_query("adminlog", $uid_update, "uid='{$source_user['uid']}'");
		$db->update_query("announcements", $uid_update, "uid='{$source_user['uid']}'");
		$db->update_query("events", $uid_update, "uid='{$source_user['uid']}'");
		$db->update_query("favorites", $uid_update, "uid='{$source_user['uid']}'");
		$db->update_query("forumsubscriptions", $uid_update, "uid='{$source_user['uid']}'");
		$db->update_query("moderatorlog", $uid_update, "uid='{$source_user['uid']}'");
		$db->update_query("pollvotes", $uid_update, "uid='{$source_user['uid']}'");
		$db->update_query("posts", $uid_update, "uid='{$source_user['uid']}'");
		$db->update_query("privatemessages", $uid_update, "uid='{$source_user['uid']}'");
		$db->update_query("reputation", $uid_update, "uid='{$source_user['uid']}'");
		$db->update_query("threadratings", $uid_update, "uid='{$source_user['uid']}'");
		$db->update_query("threads", $uid_update, "uid='{$source_user['uid']}'");

		// Additional updates for non-uid fields
		$last_poster = array(
			"lastposteruid" => $destination_user['uid'],
			"lastposter" => $db->escape_string($destination_user['username'])
		);
		$db->update_query("forums", $last_poster, "lastposteruid='{$source_user['uid']}'");
		$db->update_query("threads", $last_poster, "lastposteruid='{$source_user['uid']}'");
		$edit_uid = array(
			"edit_uid" => $destination_user['uid']
		);
		$db->update_query("posts", $edit_uid, "edituid='{$source_user['uid']}'");

		$from_uid = array(
			"fromid" => $destination_user['uid']
		);	
		$db->update_query("privatemessages", $from_uid, "fromid='{$source_user['uid']}'");
		$to_uid = array(
			"toid" => $destination_user['uid']
		);	
		$db->update_query("privatemessages", $to_uid, "toid='{$source_user['uid']}'");

		// Delete the old user
		$db->delete_query("users", "uid='{$source_user['uid']}'");
		$db->delete_query("banned", "uid='{$source_user['uid']}'");
		
		// Update user post count
		$query = $db->query("SELECT COUNT(*) AS postnum FROM ".TABLE_PREFIX."posts WHERE uid='".$destuser['uid']."'");
		$num = $db->fetch_array($query);
		$updated_count = array(
			"postnum" => $num['postnum']
		);
		$db->update_query("users", $updated_count, "uid='{$destination_user['uid']}'");

		update_stats(array('numusers' => '-1'));

		// Log admin action
		log_admin_action($source_user['username'], $destination_user['uid'], $destination_user['username']);


		// Redirect!
		flash_message("<strong>{$source_user['username']}</strong> has successfully been merged in to {$destination_user['username']}", "success");
		admin_redirect("index.php?".SID."&module=user/users");
		exit;
	}

	$page->output_header("Merge Users");
	
	$page->output_nav_tabs($sub_tabs, 'merge_users');

	// If we have any error messages, show them
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?".SID."&module=user/users&action=merge", "post");

	$form_container = new FormContainer("Merge Users");
	$form_container->output_row("Source Account <em>*</em>", "This is the account that will be merged in to the destination account. It will be removed after this process.", $form->generate_text_box('source_username', $mybb->input['source_username'], array('id' => 'source_username')), 'source_username');
	$form_container->output_row("Destination Account <em>*</em>", "This is the account that the source account will be merged in to. It will remain after this process.", $form->generate_text_box('destination_username', $mybb->input['destination_username'], array('id' => 'destination_username')), 'destination_username');
	$form_container->end();

	// Autocompletion for usernames
	echo '
	<script type="text/javascript" src="../jscripts/autocomplete.js?ver=140"></script>
	<script type="text/javascript">
	<!--
		new autoComplete("source_username", "xmlhttp.php?action=get_users", {valueSpan: "username"});
		new autoComplete("destination_username", "xmlhttp.php?action=get_users", {valueSpan: "username"});
	// -->
	</script>';

	$buttons[] = $form->generate_submit_button("Merge User Accounts");
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "search")
{
	if($mybb->request_method == "post" || $mybb->input['results'] == 1)
	{
		// Build view options from incoming search options
		if($mybb->input['vid'])
		{
			$query = $db->simple_select("adminviews", "*", "vid='".intval($mybb->input['vid'])."'");
			$admin_view = $db->fetch_array($query);
			// View does not exist or this view is private and does not belong to the current user
			if(!$admin_view['vid'] || ($admin_view['visibility'] == 1 && $admin_view['uid'] != $mybb->user['uid']))
			{
				unset($admin_view);
			}
		}

		// Don't have a view? Fetch the default
		if(!$admin_view)
		{
			$default_view = fetch_default_view("user");
			$query = $db->simple_select("adminviews", "*", "type='user' AND (vid='{$default_view}' OR uid=0)", array("order_by" => "uid", "order_dir" => "desc"));
			$admin_view = $db->fetch_array($query);
		}

		// Override specific parts of the view
		unset($admin_view['vid']);
		$admin_view['sortby'] = $mybb->input['sortby'];
		$admin_view['sortorder'] = $mybb->input['order'];
		$admin_view['conditions'] = $mybb->input['conditions'];
		$admin_view['url'] = "index.php?".SID."&module=user/users&action=search&results=1";

		if($mybb->input['type'])
		{
			$admin_view['view_type'] = $mybb->input['type'];
		}

		$results = build_users_view($admin_view);

		if($results)
		{
			$page->output_header("Find Users");
			echo "<script type=\"text/javascript\" src=\"jscripts/users.js\"></script>";
			$page->output_nav_tabs($sub_tabs, 'find_users');
			echo $results;
			$page->output_footer();
		}
		else
		{
			$errors[] = "No users were found matching the specified search criteria. Please modify your search criteria and try again.";
		}
	}

	$page->output_header("Find Users");
	
	$page->output_nav_tabs($sub_tabs, 'find_users');

	// If we have any error messages, show them
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?".SID."&module=user/users&action=search", "post");

	user_search_conditions($mybb->input, $form);

	$form_container = new FormContainer("Display Options");
	$sort_directions = array(
		"asc" => "Ascending",
		"desc" => "Descending"
	);
	$form_container->output_row("Sort results by", "", $form->generate_select_box('sortby', $sort_options, $mybb->input['sortby'], array('id' => 'sortby'))." in ".$form->generate_select_box('order', $sort_directions, $mybb->input['order'], array('id' => 'order')), 'sortby');
	$form_container->output_row("Results per page", "", $form->generate_text_box('perpage', $mybb->input['perpage'], array('id' => 'perpage')), 'perpage');
	$form_container->output_row("Display results as", "", $form->generate_radio_button('displayas', 'table', 'Table', array('checked' => ($mybb->input['displayas'] != "card" ? "checked" : "")))."<br />".$form->generate_radio_button('displayas', 'card', 'Business cards', array('checked' => ($mybb->input['displayas'] == "card" ? "checked" : ""))));
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Find Users");
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header("Browse Users");
	echo "<script type=\"text/javascript\" src=\"jscripts/users.js\"></script>";
	
	$page->output_nav_tabs($sub_tabs, 'browse_users');

	// Fetch a list of all of the views for this user
	$popup = new PopupMenu("views", "Views");

	$query = $db->simple_select("adminviews", "*", "type='user' AND (visibility=2 OR uid={$mybb->user['uid']})", array("order_by" => "title"));
	while($view = $db->fetch_array($query))
	{
		$popup->add_item(htmlspecialchars_uni($view['title']), "index.php?".SID."&amp;module=user/users&amp;vid={$view['vid']}");
	}
	$popup->add_item("<em>Manage Views</em>", "index.php?".SID."&amp;module=user/users&amp;action=views");
	echo "<div style=\"text-align: right; margin-bottom: 10px;\">".$popup->fetch()."</div>";

	if($mybb->input['view'])
	{
		$admin_view = $mybb->input['view'];
		$admin_view['fields'] = unserialize(base64_decode($admin_view['fields']));
		$admin_view['conditions'] = unserialize(base64_decode($admin_view['conditions']));
		unset($admin_view['extra_sql']);
	}
	else
	{
		// Showing a specific view
		if($mybb->input['vid'])
		{
			$query = $db->simple_select("adminviews", "*", "vid='".intval($mybb->input['vid'])."'");
			$admin_view = $db->fetch_array($query);
			// View does not exist or this view is private and does not belong to the current user
			if(!$admin_view['vid'] || ($admin_view['visibility'] == 1 && $admin_view['uid'] != $mybb->user['uid']))
			{
				unset($admin_view);
			}
		}

		// Don't have a view? Fetch the default
		if(!$admin_view)
		{
			$default_view = fetch_default_view("user");
			$query = $db->simple_select("adminviews", "*", "type='user' AND (vid='{$default_view}' OR uid=0)", array("order_by" => "uid", "order_dir" => "desc"));
			$admin_view = $db->fetch_array($query);
		}
	}

	if($mybb->input['type'])
	{
		$admin_view['view_type'] = $mybb->input['type'];
	}

	echo build_users_view($admin_view);

	$page->output_footer();
}

function build_users_view($view)
{
	global $mybb, $db, $cache, $lang, $user_view_fields, $page;

	if($view['title'])
	{
		$view_title .= " (".htmlspecialchars_uni($view['title']).")";
	}

	// Build the URL to this view
	if(!$view['url'])
	{
		$view['url'] = "index.php?".SID."&module=user/users";
	}
	if(!is_array($view['conditions']))
	{
		$view['conditions'] = unserialize($view['conditions']);
	}
	if(!is_array($view['fields']))
	{
		$view['fields'] = unserialize($view['fields']);
	}
	if($view['vid'])
	{
		$view['url'] .= "&vid={$view['vid']}";
	}
	else
	{
		// If this is a custom view we need to save everything ready to pass it on from page to page
		foreach($view as $key => $val)
		{
			if($key == "url" || $key == "title") continue;
			if(is_array($val))
			{
				$val = base64_encode(serialize($val));
			}
			$view['url'] .= "&view[{$key}]=".urlencode($val);
		}
	}

	$table = new Table;

	// Build header for table based view
	if($view['view_type'] != "card")
	{
		foreach($view['fields'] as $field)
		{
			if(!$user_view_fields[$field])
			{
				continue;
			}
			$view_field = $user_view_fields[$field];
			$field_options = array();
			if($view_field['width'])
			{
				$field_options['width'] = $view_field['width'];
			}
			if($view_field['align'])
			{
				$field_options['class'] = "align_".$view_field['align'];
			}
			$table->construct_header($view_field['title'], $field_options);
		}
	}


	$search_sql = '1=1';

	// Build the search SQL for users

	// List of valid LIKE search fields
	$user_like_fields = array("username", "email", "website", "icq", "aim", "yahoo", "msn", "signature", "usertitle");
	foreach($user_like_fields as $search_field)
	{
		if($view['conditions'][$search_field])
		{
			$search_sql .= " AND u.{$search_field} LIKE '%".$db->escape_string_like($view['conditions'][$search_field])."%'";
		}
	}

	// EXACT matching fields
	$user_exact_fields = array("referrer");
	foreach($user_exact_fields as $search_field)
	{
		if($view['conditions'][$search_field])
		{
			$search_sql .= " AND u.{$search_field}='".$db->escape_string($view['conditions'][$search_field])."'";
		}
	}

	// LESS THAN or GREATER THAN
	$direction_fields = array("postnum");
	foreach($direction_fields as $search_field)
	{
		$direction_field = $search_field."_dir";
		if($view['conditions'][$search_field] && $view['conditions'][$direction_field])
		{
			switch($view['conditions'][$direction_field])
			{
				case "greater_than":
					$direction = ">";
					break;
				case "less_than":
					$direction = "<";
					break;
				default:
					$direction = "=";
			}
			$search_sql .= " AND u.{$search_field}{$direction_field}'".$db->escape_string($view['conditions'][$search_field])."'";
		}
	}

	// IP searching
	$ip_fields = array("regip", "lastip");
	foreach($like_fields as $search_field)
	{
		if($view['conditions'][$search_field])
		{
			$view['conditions'][$search_field] = str_replace("*", "%", $view['conditions'][$search_field]);
			
			// IPv6 IP
			if(strpos($view['conditions'][$search_field], ":") !== false)
			{
				$ip_sql = "{$search_field} LIKE '".$db->escape_string($view['conditions'][$search_field])."'";
			}
			else
			{
				$ip_range = fetch_longipv4_range($view['conditions'][$search_field]);
				if(!is_array($ip_range))
				{
					$ip_sql = "long{$search_field}='{$ip_range}'";
				}
				else
				{
					$ip_sql = "long{$search_field} > '{$ip_range[0]}' AND long{$search_field} < '{$ip_range[1]}'";
				}
			}
			$search_sql .= " AND {$ip_sql}";
		}
	}

	// Usergroup based searching
	if($view['conditions']['usergroup'])
	{
		if(!is_array($view['conditions']['usergroup']))
		{
			$view['conditions']['usergroup'] = array($view['conditions']['usergroup']);
		}

		foreach($view['conditions']['usergroup'] as $usergroup)
		{
			switch($db->type)
			{
				case "sqlite3":
				case "sqlite2":
					$additional_sql .= " OR ','||additionalgroups||',' LIKE '%,{$usergroup},%')";
				default:
					$additional_sql .= "OR CONCAT(',',additionalgroups,',') LIKE '%,{$usergroup},%')";
			}
		}
		$search_sql .= " AND (u.usergroup IN (".implode(",", $view['conditions']['usergroup']).") {$additional_sql})";
	}

	// COPPA users only?
	if($view['conditions']['coppa'])
	{
		$search_sql .= " AND u.coppauser=1 AND u.usergroup=5";
	}

	// Extra SQL?
	if($view['extra_sql'])
	{
		$search_sql .= $view['extra_sql'];
	}

	// Lets fetch out how many results we have
	$query = $db->query("
		SELECT COUNT(u.uid) AS num_results
		FROM ".TABLE_PREFIX."users u
		WHERE {$search_sql}
	");
	$num_results = $db->fetch_field($query, "num_results");

	// No matching results then return false
	if(!$num_results)
	{
		return false;
	}
	// Generate the list of results
	else
	{
		if(!$view['perpage'])
		{
			$view['perpage'] = 20;
		}
		$view['perpage'] = intval($view['perpage']);

		// Establish which page we're viewing and the starting index for querying
		$mybb->input['page'] = intval($mybb->input['page']);
		if($mybb->input['page'])
		{
			$start = ($mybb->input['page'] - 1) * $view['perpage'];
		}
		else
		{
			$start = 0;
			$mybb->input['page'] = 1;
		}

		switch($view['sortby'])
		{
			case "regdate":
			case "lastactive":
			case "postnum":
			case "reputation":
			case "warninglevel":
				break;
			default:
				$view['sortby'] = "username";
		}

		if($view['sortorder'] != "desc")
		{
			$view['sortorder'] = "asc";
		}

		$usergroups = $cache->read("usergroups");

		// Fetch matching users
		$query = $db->query("
			SELECT u.*
			FROM ".TABLE_PREFIX."users u
			WHERE {$search_sql}
			ORDER BY {$view['sortby']} {$view['sortorder']}
			LIMIT {$start}, {$view['perpage']}
		");
		while($user = $db->fetch_array($query))
		{
			$user['view']['username'] = "<a href=\"index.php?".SID."&amp;module=user/users&amp;action=edit&amp;uid={$user['uid']}\">".format_name($user['username'], $user['usergroup'], $user['displaygroup'])."</a>";
			$user['view']['usergroup'] = $usergroups[$user['usergroup']]['title'];
			$additional_groups = explode(",", $user['additionalgroups']);
			foreach($additional_groups as $group)
			{
				$groups_list .= "{$comma}{$usergroups[$group]['title']}";
				$comma = ", ";
			}
			$comma = $groups_list = '';
			if(!$groups_list) $groups_list = "None";
			$user['view']['additionalgroups'] = "<small>{$groups_list}</small>";
			$user['view']['email'] = "<a href=\"mailto:".htmlspecialchars_uni($user['email'])."\">".htmlspecialchars_uni($user['email'])."</a>";
			$user['view']['regdate'] = my_date($mybb->settings['dateformat'], $user['regdate']).", ".my_date($mybb->settings['timeformat'], $user['regdate']);
			$user['view']['lastactive'] = my_date($mybb->settings['dateformat'], $user['lastactive']).", ".my_date($mybb->settings['timeformat'], $user['lastactive']);

			// Build popup menu
			$popup = new PopupMenu("user_{$user['uid']}", $lang->options);
			$popup->add_item("Edit Profile &amp; Settings", "index.php?".SID."&amp;module=user/users&amp;action=edit&amp;uid={$user['uid']}");
			$popup->add_item("Ban User", "index.php?".SID."&amp;module=user/users&amp;action=ban&amp;uid={$user['uid']}");
			$popup->add_item("Delete User", "index.php?".SID."&amp;module=user/users&amp;action=delete&amp;uid={$user['uid']}");
			$popup->add_item("Show Referred Users", "index.php?".SID."&amp;module=user/users&amp;action=referrers&amp;uid={$user['uid']}");
			$popup->add_item("Show IP Addresses", "index.php?".SID."&amp;module=user/users&amp;action=ipaddresses&amp;uid={$user['uid']}");
			$popup->add_item("Show Attachments", "index.php?".SID."&amp;module=user/users&amp;action=attachments&amp;uid={$user['uid']}");
			$user['view']['controls'] = $popup->fetch();

			// Fetch the reputation for this user
			if($usergroups[$user['usergroup']]['usereputationsystem'] == 1 && $mybb->settings['enablereputation'] == 1)
			{
				$user['view']['reputation'] = get_reputation($user['reputation']);
			}
			else
			{
				$reputation = "-";
			}

			if($mybb->settings['enablewarningsystem'] != 0 && $usergroups[$user['usergroup']]['canreceivewarnings'] != 0)
			{
				$warning_level = round($user['warningpoints']/$mybb->settings['maxwarningpoints']*100);
				if($warning_level > 100)
				{
					$warning_level = 100;
				}
				$user['view']['warninglevel'] = get_colored_warning_level($warning_level);
			}

			if($user['avatar'] && !stristr($user['avatar'], 'http://'))
			{
				$user['avatar'] = "../{$user['avatar']}";
			}
			if($view['view_type'] == "card")
			{
				$scaled_avatar = fetch_scaled_avatar($user, 80, 80);
			}
			else
			{
				$scaled_avatar = fetch_scaled_avatar($user, 24, 24);
			}
			if(!$user['avatar'])
			{
				$user['avatar'] = "styles/{$page->style}/images/default_avatar.gif";
			}
			$user['view']['avatar'] = "<img src=\"{$user['avatar']}\" alt=\"\" width=\"{$scaled_avatar['width']}\" height=\"{$scaled_avatar['height']}\" />";

			if($view['view_type'] == "card")
			{
				$users .= build_user_view_card($user, $view, &$i);
			}
			else
			{
				build_user_view_table($user, $view, &$table);
			}
		}

		// If card view, we need to output the results
		if($view['view_type'] == "card")
		{
			$table->construct_cell($users);
			$table->construct_row();
		}
	}
	
	if(!$view['table_id'])
	{
		$view['table_id'] = "users_list";
	}

	$switch_view = "<div class=\"float_right\">";
	$switch_url = $view['url'];
	if($mybb->input['page'] > 0)
	{
		$switch_url .= "&page=".intval($mybb->input['page']);
	}
	if($view['view_type'] != "card")
	{
		$switch_view .= "<strong>Table View</strong> | <a href=\"{$switch_url}&amp;type=card\" style=\"font-weight: normal;\">Card View</a>";
	}
	else
	{
		$switch_view .= "<a href=\"{$switch_url}&amp;type=table\" style=\"font-weight: normal;\">Table View</a> | <strong>Card View</strong>";
	}
	$switch_view .= "</div>";

	// Do we need to construct the pagination?
	if($num_results > $view['perpage'])
	{
		$pagination = draw_admin_pagination($mybb->input['page'], $view['perpage'], $num_results, $view['url']."&type={$view['type']}");
	}

	$built_view = $pagination;
	$built_view .= $table->construct_html("{$switch_view}Users{$view_title}", 1, "", $view['table_id']);
	$built_view .= $pagination;

	return $built_view;
}

function build_user_view_card($user, $view, $i)
{
	global $user_view_fields;

	++$i;
	if($i == 3)
	{
		$i = 1;
	}

	// Loop through fields user wants to show
	foreach($view['fields'] as $field)
	{
		if(!$user_view_fields[$field])
		{
			continue;
		}

		$view_field = $user_view_fields[$field];
		
		// Special conditions for avatar
		if($field == "avatar")
		{
			$avatar = $user['view']['avatar'];
		}
		else if($field == "controls")
		{
			$controls = $user['view']['controls'];
		}
		// Otherwise, just user data
		else if($field != "username")
		{
			if($user['view'][$field])
			{
				$value = $user['view'][$field];
			}
			else
			{
				$value = $user[$field];
			}
			$user_details[] = "<strong>{$view_field['title']}:</strong> {$value}";
		}

	}
	// Floated to the left or right?
	if($i == 1)
	{
		$float = "left";
	}
	else
	{
		$float = "right";
	}

	// And build the final card
	$card = "<fieldset style=\"width: 47%; float: {$float};\">\n";
	$card .= "<legend>{$user['view']['username']}</legend>\n";
	if($avatar)
	{
		$card .= "<div class=\"user_avatar\">{$avatar}</div>\n";
	}
	if($user_details)
	{
		$card .= "<div class=\"user_details\">".implode("<br />", $user_details)."</div>\n";
	}
	if($controls)
	{
		$card .= "<div class=\"float_right\">{$controls}</div>\n";
	}
	$card .= "</fieldset>";
	return $card;

}

function build_user_view_table($user, $view, $table)
{
	global $user_view_fields;

	foreach($view['fields'] as $field)
	{
		if(!$user_view_fields[$field])
		{
			continue;
		}
		$view_field = $user_view_fields[$field];
		$field_options = array();
		if($view_field['align'])
		{
			$field_options['class'] = "align_".$view_field['align'];
		}
		if($user['view'][$field])
		{
			$value = $user['view'][$field];
		}
		else
		{
			$value = $user[$field];
		}
		$table->construct_cell($value, $field_options);
	}
	$table->construct_row();
}

function fetch_scaled_avatar($user, $max_width=80, $max_height=80)
{
	$avatar_dimensions = explode("|", $user['avatardimensions']);

	$scaled_dimensions = array(
		"width" => $max_width,
		"height" => $max_height,
	);

	if($user['avatar'])
	{
		if($user['avatardimensions'])
		{
			require_once MYBB_ROOT."inc/functions_image.php";
			list($width, $height) = explode("|", $user['avatardimensions']);
			$scaled_dimensions = scale_image($width, $height, 44, 44);
		}
	}

	return array("width" => $scaled_dimensions['width'], "height" => $scaled_dimensions['height']);
}

function output_custom_profile_fields($fields, $values, &$form_container, &$form)
{
	if(!is_array($fields))
	{
		return;
	}
	foreach($fields as $profile_field)
	{
		$profile_field['type'] = htmlspecialchars_uni($profile_field['type']);
		list($type, $options) = explode("\n", $profile_field['type'], 2);
		$type = trim($type);
		$field_name = "fid{$profile_field['fid']}";
		switch($type)
		{
			case "multiselect":
				if(!is_array($values[$field_name]))
				{
					$user_options = explode("\n", $values[$field_name]);
				}
				else
				{
					$user_options = $values[$field_name];
				}
				foreach($user_options as $val)
				{
					$selected_options[$val] = $val;
				}
				$select_options = explode("\n", $options);
				$options = array();
				foreach($select_options as $val)
				{
					$val = trim($val);
					$options[$val] = $val;
				}
				if(!$profile_field['length'])
				{
					$profile_field['length'] = 3;
				}
				$code = $form->generate_select_box('profile_fields[{$field_name}][]', $options, $selected_options, array('id' => "profile_field_{$field_name}", 'multiple' => true, 'size' => $profile_field['length']));
				break;
			case "select":
				$select_options = explode("\n", $options);
				$options = array();
				foreach($select_options as $val)
				{
					$val = trim($val);
					$options[$val] = $val;
				}
				if(!$profile_field['length'])
				{
					$profile_field['length'] = 1;
				}
				$code = $form->generate_select_box('profile_fields[{$field_name}]', $options, $values[$field_name], array('id' => "profile_field_{$field_name}", 'size' => $profile_field['length']));
				break;
			case "radio":
				$radio_options = explode("\n", $options);
				foreach($radio_options as $val)
				{
					$val = trim($val);
					$code .= $form->generate_radio_button('profile_fields[{$field_name}]', $val, $val, array('id' => "profile_field_{$field_name}", 'checked' => ($val == $values[$field_name] ? true : false)))."<br />";
				}
				break;
			case "checkbox":
				if(!is_array($values[$field_name]))
				{
					$user_options = explode("\n", $values[$field_name]);
				}
				else
				{
					$user_options = $values[$field_name];
				}
				foreach($user_options as $val)
				{
					$selected_options[$val] = $val;
				}
				$select_options = explode("\n", $options);
				$options = array();
				foreach($select_options as $val)
				{
					$val = trim($val);
					$options[$val] = $val;
					$code .= $form->generate_check_box('profile_fields[{$field_name}][]', $options, $val, array('id' => "profile_field_{$field_name}", 'checked' => ($val == $values[$field_name] ? true : false)))."<br />";
				}
				break;
			case "textarea":
				$code = $form->generate_text_area('profile_fields[{$field_name}]', $values[$field_name], array('id' => "profile_field_{$field_name}", 'rows' => 6, 'cols' => 50));
				break;
			default:
				$code = $form->generate_text_box('profile_fields[{$field_name}]', $values[$field_name], array('id' => "profile_field_{$field_name}", 'maxlength' => $profile_field['maxlength'], 'length' => $profile_field['length']));
				break;
		}
		$form_container->output_row($profile_field['name'], $profile_field['description'], $code, array('id' => "profile_field_{$field_name}"));
		$code = $user_options = $selected_options = $radio_options = $val = $options = '';
	}
}

function user_search_conditions($input=array(), &$form)
{
	global $mybb, $db, $lang;

	if(!$input)
	{
		$input = $mybb->input;
	}

	$form_container = new FormContainer("Find users where...");
	$form_container->output_row("Username contains", "", $form->generate_text_box('conditions[username]', $input['conditions']['username'], array('id' => 'username')), 'username');
	$form_container->output_row("Email address contains", "", $form->generate_text_box('conditions[email]', $input['conditions']['email'], array('id' => 'email')), 'email');

	$query = $db->simple_select("usergroups", "gid, title", "gid != '1'", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		$options[$usergroup['gid']] = $usergroup['title'];
	}

	$form_container->output_row("Is member of these user groups", "Use CTRL to select multiple groups", $form->generate_select_box('conditions[usergroups][]', $options, $input['conditions']['usergroups'], array('id' => 'usergroups', 'multiple' => true, 'size' => 5)), 'usergroups');

	$form_container->output_row("Website contains", "", $form->generate_text_box('conditions[website]', $input['conditions']['website'], array('id' => 'website')), 'website');
	$form_container->output_row("ICQ number contains", "", $form->generate_text_box('conditions[icq]', $input['conditions']['icq'], array('id' => 'icq')), 'icq');
	$form_container->output_row("AIM handle contains", "", $form->generate_text_box('conditions[aim]', $input['conditions']['aim'], array('id' => 'aim')), 'aim');
	$form_container->output_row("Yahoo! Messenger handle contains", "", $form->generate_text_box('conditions[yahoo]', $input['conditions']['yahoo'], array('id' => 'yahoo')), 'yahoo');
	$form_container->output_row("MSN Messenger handle contains", "", $form->generate_text_box('conditions[msn]', $input['conditions']['msn'], array('id' => 'msn')), 'msn');
	$form_container->output_row("Signature contains", "", $form->generate_text_box('conditions[signature]', $input['conditions']['signature'], array('id' => 'signature')), 'signature');
	$form_container->output_row("Custom user title contains", "", $form->generate_text_box('conditions[usertitle]', $input['conditions']['usertitle'], array('id' => 'usertitle')), 'usertitle');
	$greater_options = array(
		"greater_than" => "Greater than",
		"is_exactly" => "Is exactly",
		"less_than" => "Less than"
	);
	$form_container->output_row("Post count is", "", $form->generate_select_box('conditions[numposts_dir]', $greater_options, $input['conditions']['numposts_dir'], array('id' => 'numposts_dir'))." ".$form->generate_text_box('conditions[numposts]', $input['conditions']['numposts'], array('id' => 'numposts')), 'numposts');

	$form_container->output_row("Registration IP address matches", "* denominates a wildcard", $form->generate_text_box('conditions[regip]', $input['conditions']['regip'], array('id' => 'regip')), 'regip');
	$form_container->output_row("Last known IP address matches", "* denominates a wildcard", $form->generate_text_box('conditions[lastip]', $input['conditions']['lastip'], array('id' => 'lastip')), 'lastip');
	$form_container->output_row("Has posted with the IP address", "* denominates a wildcard", $form->generate_text_box('conditions[postip]', $input['conditions']['postip'], array('id' => 'postip')), 'postip');

	$form_container->end();

	// Custom profile fields go here
}

?>