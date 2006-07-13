<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/*
EXAMPLE USE:

$post = get from POST data
$thread = get from DB using POST data id

$postHandler = new postDataHandler();
if($postHandler->validate_post($post))
{
	$postHandler->insert_post($post);
}

*/

/**
 * Post handling class, provides common structure to handle post data.
 *
 */
class PostDataHandler extends DataHandler
{
	/**
	* The language file used in the data handler.
	*
	* @var string
	*/
	var $language_file = 'datahandler_post';
	
	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	var $language_prefix = 'postdata';

	/**
	 * What are we performing?
	 * post = New post
	 * thread = New thread
	 * edit = Editing a thread or post
	 */
	var $action;

	/**
	 * Verifies the author of a post and fetches the username if necessary.
	 *
	 * @return boolean True if the author information is valid, false if invalid.
	 */
	function verify_author()
	{
		global $mybb;

		$post = &$this->data;

		// Don't have a user ID at all - not good (note, a user id of 0 will still work).
		if(!isset($post['uid']))
		{
			$this->set_error("invalid_user_id");
			return false;
		}
		// If we have a user id but no username then fetch the username.
		else if($post['uid'] > 0 && !$post['username'])
		{
			$user = get_user($post['uid']);
			$post['username'] = $user['username'];
		}

		// After all of this, if we still don't have a username, force the username as "Guest" (Note, this is not translatable as it is always a fallback)
		if(!$post['username'])
		{
			$post['username'] = "Guest";
		}

		// Sanitize the username
		$post['username'] = htmlspecialchars_uni($post['username']);
		return true;
	}

	/**
	 * Verifies a post subject.
	 *
	 * @param string True if the subject is valid, false if invalid.
	 * @return boolean True when valid, false when not valid.
	 */
	function verify_subject()
	{
		global $db;
		$post = &$this->data;
		$subject = &$post['subject'];

		$subject = trim($subject);
		
		// Are we editing an existing thread or post?
		if($this->method == "update" && $post['pid'])
		{
			if(!$post['tid'])
			{
				$query = $db->simple_select("posts", "tid", "pid='".intval($post['pid'])."'");
				$post['tid'] = $db->fetch_field($query, "tid");
			}
			// Here we determine if we're editing the first post of a thread or not.
			$options = array(
				"limit" => 1,
				"limit_start" => 0,
				"order_by" => "dateline",
				"order_dir" => "asc"
			);
			$query = $db->simple_select("posts", "pid", "tid='".$post['tid']."'", $options);
			$first_check = $db->fetch_array($query);
			if($first_check['pid'] == $post['pid'])
			{
				$first_post = 1;
			}
			else
			{
				$first_post = 0;
			}

			// If this is the first post there needs to be a subject, else make it the default one.
			if(my_strlen($subject) == 0 && $first_post)
			{
				$this->set_error("firstpost_no_subject");
				return false;
			}
			elseif(my_strlen($subject) == 0)
			{
				$thread = get_thread($post['tid']);
				$subject = "RE: ".$thread['subject'];
			}
		}

		// This is a new post
		else if($this->action == "post")
		{
			if(my_strlen($subject) == 0)
			{
				$thread = get_thread($post['tid']);
				$subject = "RE: ".$thread['subject'];
			}
		}

		// This is a new thread and we require that a subject is present.
		else
		{
			if(my_strlen($subject) == 0)
			{
				$this->set_error("missing_subject");
				return false;
			}
		}

		// Subject is valid - return true.
		return true;
	}

	/**
	 * Verifies a post message.
	 *
	 * @param string The message content.
	 */
	function verify_message()
	{
		global $mybb;
		
		$post = &$this->data;

		$post['message'] = trim($post['message']);
		// Do we even have a message at all?
		if(my_strlen($post['message']) == 0)
		{
			$this->set_error("missing_message");
			return false;
		}

		// If this board has a maximum message length check if we're over it.
		else if(my_strlen($post['message']) > $mybb->settings['maxmessagelength'] && $mybb->settings['maxmessagelength'] > 0 && is_moderator($post['fid'], "", $post['uid']) != "yes")
		{
			$this->set_error("message_too_long", array($mybb->settings['maxmessagelength']));
			return false;
		}

		// And if we've got a minimum message length do we meet that requirement too?
		else if(my_strlen($post['message']) < $mybb->settings['minmessagelength'] && $mybb->settings['minmessagelength'] > 0 && is_moderator($post['fid'], "", $post['uid']) != "yes")
		{
			$this->set_error("message_too_short", array($mybb->settings['minmessagelength']));
			return false;
		}
		return true;
	}

	/**
	 * Verifies the specified post options are correct.
	 *
	 * @return boolean True
	 */
	function verify_options()
	{
		$options = &$this->data['options'];

		// Verify yes/no options.
		$this->verify_yesno_option($options, 'signature', 'no');
		$this->verify_yesno_option($options, 'emailnotify', 'no');
		$this->verify_yesno_option($options, 'disablesmilies', 'no');

		return true;
	}

	/**
	* Verify that the user is not flooding the system.
	*
	* @return boolean True
	*/
	function verify_post_flooding()
	{
		global $mybb;

		$post = &$this->data;

		// Check if post flooding is enabled within MyBB or if the admin override option is specified.
		if($mybb->settings['postfloodcheck'] == "on" && $post['uid'] != 0 && $this->admin_override == false)
		{
			// Fetch the user information for this post - used to check their last post date.
			$user = get_user($post['uid']);

			// A little bit of calculation magic and moderator status checking.
			if(time()-$user['lastpost'] <= $mybb->settings['postfloodsecs'] && is_moderator($post['fid'], "", $user['uid']) != "yes")
			{
				// Oops, user has been flooding - throw back error message.
				$time_to_wait = ($mybb->settings['postfloodsecs'] - (time()-$user['lastpost'])) + 1;
				if($time_to_wait == 1)
				{
					$this->set_error("post_flooding_one_second");
				}
				else
				{
					$this->set_error("post_flooding", array($time_to_wait));
				}
				return false;
			}
		}
		// All is well that ends well - return true.
		return true;
	}

	/**
	* Verifies the image count.
	*
	* @return boolean True when valid, false when not valid.
	*/
	function verify_image_count()
	{
		global $mybb, $db;

		$post = &$this->data;

		// Get the permissions of the user who is making this post or thread
		$permissions = user_permissions($post['uid']);

		// Fetch the forum this post is being made in
		$forum = get_forum($post['fid']);

		// Check if this post contains more images than the forum allows
		if($post['savedraft'] != 1 && $mybb->settings['maxpostimages'] != 0 && $permissions['cancp'] != "yes")
		{
			if($post['options']['disablesmilies'] == "yes")
			{
				require_once MYBB_ROOT."inc/class_parser.php";
				$parser = new postParser;
				
				// Parse the message.
				$parser_options = array(
					"allow_html" => $forum['allowhtml'],
					"allow_mycode" => $forum['allowmycode'],
					"allow_smilies" => $forum['allowmilies'],
					"allow_imgcode" => $forum['allowimgcode']
				);

				$image_check = $parser->parse_message($post['message'], $parser_options);

				// And count the number of image tags in the message.
				$image_count = substr_count($image_check, "<img");
				if($image_count > $mybb->settings['maxpostimages'])
				{
					// Throw back a message if over the count with the number of images as well as the maximum number of images per post.
					$this->set_error("too_many_images", array(1 => $image_count, 2 => $mybb->settings['maxpostimages']));
					return false;
				}

			}
		}
	}

	/**
	* Verify the reply-to post.
	*
	* @return boolean True when valid, false when not valid.
	*/
	function verify_reply_to()
	{
		global $db;
		$post = &$this->data;

		// Check if the post being replied to actually exists in this thread.
		if($post['replyto'])
		{
			$query = $db->simple_select("posts", "pid", "pid='{$post['replyto']}'");
			$valid_post = $db->fetch_array($query);
			if(!$valid_post['pid'])
			{
				$post['replyto'] = 0;
			}
			else
			{
				return true;
			}
		}

		// If this post isn't a reply to a specific post, attach it to the first post.
		if(!$post['replyto'])
		{
			$options = array(
				"limit_start" => 0,
				"limit" => 1,
				"order_by" => "dateline",
				"order_dir" => "asc"
			);
			$query = $db->simple_select("posts", "pid", "tid='{$post['tid']}'", $options);
			$reply_to = $db->fetch_array($query);
			$post['replyto'] = $reply_to['pid'];
		}

		return true;
	}

	/**
	* Verify the post icon.
	*
	* @return boolean True when valid, false when not valid.
	*/
	function verify_post_icon()
	{
		global $cache;

		$post = &$this->data;

		// If we don't assign it as 0.
		if(!$post['icon'] || $post['icon'] < 0)
		{
			$post['icon'] = 0;
		}
		return true;
	}

	/**
	* Verify the dateline.
	*
	* @return boolean True when valid, false when not valid.
	*/
	function verify_dateline()
	{
		$dateline = &$this->data['dateline'];

		// The date has to be numeric and > 0.
		if($dateline < 0 || is_numeric($dateline) == false)
		{
			$dateline = time();
		}
	}

	/**
	 * Validate a post.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_post()
	{
		global $mybb, $db, $plugins;

		$post = &$this->data;
		$time = time();

		// Verify all post assets.

		if($this->method == "insert" || array_key_exists('uid', $post))
		{
			$this->verify_author();
		}

		if($this->method == "insert" || array_key_exists('subject', $post))
		{
			$this->verify_subject();
		}

		if($this->method == "insert" || array_key_exists('message', $post))
		{
			$this->verify_message();
			$this->verify_image_count();
		}

		if($this->method == "insert" || array_key_exists('dateline', $post))
		{
			$this->verify_dateline();
		}

		if($this->method != "update" && !$post['savedraft'])
		{
			$this->verify_post_flooding();
		}

		if($this->method == "insert" || array_key_exists('replyto', $post))
		{
			$this->verify_reply_to();
		}

		if($this->method == "insert" || array_key_exists('icon', $post))
		{
			$this->verify_post_icon();
		}

		if($this->method == "insert" || array_key_exists('options', $post))
		{
			$this->verify_options();
		}

		$plugins->run_hooks_by_ref("datahandler_post_validate_post", $this);

		// We are done validating, return.
		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}


	/**
	 * Insert a post into the database.
	 *
	 * @return array Array of new post details, pid and visibility.
	 */
	function insert_post()
	{
		global $db, $mybb, $plugins, $cache, $lang;

		$post = &$this->data;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The post is not valid.");
		}

		// This post is being saved as a draft.
		if($post['savedraft'])
		{
			$visible = -2;
		}

		// Otherwise this post is being made now and we have a bit to do.
		else
		{
			// Automatic subscription to the thread
			if($post['options']['emailnotify'] != "no" && $post['uid'] > 0)
			{
				$query = $db->simple_select("favorites", "fid", "tid='".intval($post['uid'])."' AND tid='".intval($post['tid'])."' AND type='s'", array("limit" => 1));
				$already_subscribed = $db->fetch_field($query, "fid");
				if(!$already_subscribed)
				{
					$favoriteadd = array(
						"uid" => intval($post['uid']),
						"tid" => intval($post['tid']),
						"type" => "s"
					);
					$db->insert_query(TABLE_PREFIX."favorites", $favoriteadd);
				}
			}

			// Perform any selected moderation tools.
			if(is_moderator($post['fid'], "", $post['uid']) == "yes" && $post['modoptions'])
			{
				// Fetch the thread
				$thread = get_thread($post['tid']);

				$modoptions = $post['modoptions'];
				$modlogdata['fid'] = $thread['fid'];
				$modlogdata['tid'] = $thread['tid'];

				// Close the thread.
				if($modoptions['closethread'] == "yes" && $thread['closed'] != "yes")
				{
					$newclosed = "closed='yes'";
					log_moderator_action($modlogdata, "Thread closed");
				}

				// Open the thread.
				if($modoptions['closethread'] != "yes" && $thread['closed'] == "yes")
				{
					$newclosed = "closed='no'";
					log_moderator_action($modlogdata, "Thread opened");
				}

				// Stick the thread.
				if($modoptions['stickthread'] == "yes" && $thread['sticky'] != 1)
				{
					$newstick = "sticky='1'";
					log_moderator_action($modlogdata, "Thread stuck");
				}

				// Unstick the thread.
				if($modoptions['stickthread'] != "yes" && $thread['sticky'])
				{
					$newstick = "sticky='0'";
					log_moderator_action($modlogdata, "Thread unstuck");
				}

				// Execute moderation options.
				if($newstick && $newclosed)
				{
					$sep = ",";
				}
				if($newstick || $newclosed)
				{
					$db->query("
						UPDATE ".TABLE_PREFIX."threads
						SET $newclosed$sep$newstick
						WHERE tid='{$thread['tid']}'
					");
				}
			}

			// Fetch the forum this post is being made in
			$forum = get_forum($post['fid']);

			// Decide on the visibility of this post.
			if($forum['modposts'] == "yes" && $mybb->usergroup['cancp'] != "yes")
			{
				$visible = 0;
			}
			else
			{
				$visible = 1;
			}
		}
		
		if($post['username'] == '')
		{
			echo "Something has gone horribly wrong. Report this as a bug and include the information below.<hr /><pre>";
			print_r($this);
			echo "</pre></hr />";
			echo "Good evening, and good night.";
			exit;
		}
		
		$query = $db->simple_select("posts", "pid", "pid='{$post['pid']}' AND uid='{$post['uid']}' AND visible='-2'");
		$draft_check = $db->fetch_field($query, "pid");

		// Are we updating a post which is already a draft? Perhaps changing it into a visible post?
		if($draft_check)
		{
			// Update a post that is a draft
			$updatedpost = array(
				"subject" => $db->escape_string($post['subject']),
				"icon" => intval($post['icon']),
				"uid" => intval($post['uid']),
				"username" => $db->escape_string($post['username']),
				"dateline" => intval($post['dateline']),
				"message" => $db->escape_string($post['message']),
				"ipaddress" => $db->escape_string($post['ipaddress']),
				"includesig" => $post['options']['signature'],
				"smilieoff" => $post['options']['disablesmilies'],
				"visible" => $visible,
				"posthash" => $db->escape_string($post['posthash'])
				);
			$db->update_query(TABLE_PREFIX."posts", $updatedpost, "pid='{$post['pid']}'");
			$pid = $post['pid'];
		}
		else
		{
			// Insert the post.
			$newreply = array(
				"tid" => intval($post['tid']),
				"replyto" => intval($post['replyto']),
				"fid" => intval($post['fid']),
				"subject" => $db->escape_string($post['subject']),
				"icon" => intval($post['icon']),
				"uid" => intval($post['uid']),
				"username" => $db->escape_string($post['username']),
				"dateline" => $post['dateline'],
				"message" => $db->escape_string($post['message']),
				"ipaddress" => $db->escape_string($post['ipaddress']),
				"includesig" => $post['options']['signature'],
				"smilieoff" => $post['options']['disablesmilies'],
				"visible" => $visible,
				"posthash" => $db->escape_string($post['posthash'])
				);

			$db->insert_query(TABLE_PREFIX."posts", $newreply);
			$pid = $db->insert_id();
		}

		// Assign any uploaded attachments with the specific posthash to the newly created post.
		if($post['posthash'])
		{
			$post['posthash'] = $db->escape_string($post['posthash']);
			$attachmentassign = array(
				"pid" => $pid
			);
			$db->update_query(TABLE_PREFIX."attachments", $attachmentassign, "posthash='{$post['posthash']}'");
		}

		$plugins->run_hooks_by_ref("datahandler_post_insert_post", $this);

		if($visible == 1)
		{
			$thread = get_thread($post['tid']);
			require_once MYBB_ROOT.'inc/class_parser.php';
			$parser = new Postparser();

			$subject = $parser->parse_badwords($thread['subject']);
			$excerpt = $parser->strip_mycode($post['message']);
			$excerpt = my_substr($excerpt, 0, $mybb->settings['subscribeexcerpt']).$lang->emailbit_viewthread;
			
			// Fetch any users subscribed to this thread and queue up their subscription notices
			$query = $db->query("
				SELECT u.username, u.email, u.uid, u.language
				FROM ".TABLE_PREFIX."favorites f, ".TABLE_PREFIX."users u
				WHERE f.type='s' AND f.tid='{$post['tid']}'
				AND u.uid=f.uid
				AND f.uid!='{$mybb->user['uid']}'
				AND u.lastactive>'{$thread['lastpost']}'
			");
			while($subscribedmember = $db->fetch_array($query))
			{
				if($done_users[$subscribedmember['uid']])
				{
					continue;
				}
				$done_users[$subscribedmember['uid']] = 1;
				if($subscribedmember['language'] != '' && $lang->language_exists($subscribedmember['language']))
				{
					$uselang = $subscribedmember['language'];
				}
				elseif($mybb->settings['bblanguage'])
				{
					$uselang = $mybb->settings['bblanguage'];
				}
				else
				{
					$uselang = "english";
				}

				if($uselang == $mybb->settings['bblanguage'])
				{
					$emailsubject = $lang->emailsubject_subscription;
					$emailmessage = $lang->email_subscription;
				}
				else
				{
					if(!isset($langcache[$uselang]['emailsubject_subscription']))
					{
						$userlang = new MyLanguage;
						$userlang->set_path(MYBB_ROOT."inc/languages");
						$userlang->set_language($uselang);
						$userlang->load("messages");
						$langcache[$uselang]['emailsubject_subscription'] = $userlang->emailsubject_subscription;
						$langcache[$uselang]['email_subscription'] = $userlang->email_subscription;
						unset($userlang);
					}
					$emailsubject = $langcache[$uselang]['emailsubject_subscription'];
					$emailmessage = $langcache[$uselang]['email_subscription'];
				}
				$emailsubject = sprintf($emailsubject, $subject);
				$emailmessage = sprintf($emailmessage, $subscribedmember['username'], $post['username'], $mybb->settings['bbname'], $subject, $excerpt, $mybb->settings['bburl'], $thread['tid']);
				$new_email = array(
					"mailto" => $db->escape_string($subscribedmember['email']),
					"mailfrom" => '',
					"subject" => $db->escape_string($emailsubject),
					"message" => $db->escape_string($emailmessage)
				);
				$db->insert_query(TABLE_PREFIX."mailqueue", $new_email);
				unset($userlang);
				$queued_email = 1;
			}
			// Have one or more emails been queued? Update the queue count
			if($queued_email == 1)
			{
				$cache->updatemailqueue();
			}

			// Update forum count
			update_thread_count($post['tid']);
			update_forum_count($post['fid']);
			$cache->updatestats();
		}
		// Post is stuck in moderation queue
		else if($visible == 0)
		{
			// Update the unapproved posts count for the current thread and current forum
			update_thread_count($post['tid']);
			update_forum_count($post['fid']);
		}

		if($visible != 2)
		{
			$now = time();
			if($forum['usepostcounts'] != "no")
			{
					$queryadd = ",postnum=postnum+1";
			}
			else
			{
				$queryadd = '';
			}
			$db->query("UPDATE ".TABLE_PREFIX."users SET lastpost='{$now}' {$queryadd} WHERE uid='{$post['uid']}'");
		}

		// Return the post's pid and whether or not it is visible.
		return array(
			"pid" => $pid,
			"visible" => $visible
		);
	}

	/**
	 * Validate a thread.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_thread()
	{
		global $mybb, $db, $plugins;

		$thread = &$this->data;

		// Validate all thread assets.

		if($this->method == "insert" || array_key_exists('uid', $thread))
		{
			$this->verify_author();
		}

		if($this->method == "insert" || array_key_exists('subject', $thread))
		{
			$this->verify_subject();
		}

		if($this->method == "insert" || array_key_exists('message', $thread))
		{
			$this->verify_message();
			$this->verify_image_count();
		}

		if($this->method == "insert" || array_key_exists('dateline', $thread))
		{
			$this->verify_dateline();
		}

		if($this->method == "insert" || array_key_exists('icon', $thread))
		{
			$this->verify_post_icon();
		}

		if($this->method == "insert" || array_key_exists('options', $thread))
		{
			$this->verify_options();
		}

		if(!$thread['savedraft'])
		{
			$this->verify_post_flooding();
		}
		
		$plugins->run_hooks_by_ref("datahandler_post_validate_thread", $this);

		// We are done validating, return.
		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Insert a thread into the database.
	 *
	 * @return array Array of new thread details, tid and visibility.
	 */
	function insert_thread()
	{
		global $db, $mybb, $plugins, $cache, $lang;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The thread needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The thread is not valid.");
		}

		$thread = &$this->data;

		// Fetch the forum this thread is being made in
		$forum = get_forum($thread['fid']);

		// This thread is being saved as a draft.
		if($thread['savedraft'])
		{
			$visible = -2;
		}

		// Thread is being made now and we have a bit to do.
		else
		{

			// Fetch the permissions for this user
			$user_permisions = user_permissions($thread['uid']);

			// Decide on the visibility of this post.
			if($forum['modposts'] == "yes" && $user_permissions['cancp'] != "yes")
			{
				$visible = 0;
			}
			else
			{
				$visible = 1;
			}
		}
		
		// Have a post ID but not a thread ID - fetch thread ID
		if($thread['pid'] && !$thread['tid'])
		{
			$db->simple_select("posts", "tid", "pid='{$thread['pid']}");
			$thread['tid'] = $db->fetch_field($query, "tid");
		}
		
		if($thread['username'] == '')
		{
			echo "Something has gone horribly wrong. Report this as a bug and include the information below.<hr /><pre>";
			print_r($this);
			echo "</pre></hr />";
			echo "Good evening, and good night.";
			exit;
		}		

		$query = $db->simple_select("posts", "pid", "pid='{$thread['pid']}' AND uid='{$thread['uid']}' AND visible='-2'");
		$draft_check = $db->fetch_field($query, "pid");
		
		// Are we updating a post which is already a draft? Perhaps changing it into a visible post?
		if($draft_check)
		{
			$newthread = array(
				"subject" => $db->escape_string($thread['subject']),
				"icon" => intval($thread['icon']),
				"username" => $db->escape_string($thread['username']),
				"dateline" => intval($thread['dateline']),
				"lastpost" => intval($thread['dateline']),
				"lastposter" => $db->escape_string($thread['username']),
				"visible" => $visible
				);

			$plugins->run_hooks_by_ref("datahandler_post_insert_thread", $this);

			$db->update_query(TABLE_PREFIX."threads", $newthread, "tid='{$thread['tid']}'");

			$newpost = array(
				"subject" => $db->escape_string($thread['subject']),
				"icon" => intval($thread['icon']),
				"username" => $db->escape_string($thread['username']),
				"dateline" => intval($thread['dateline']),
				"message" => $db->escape_string($thread['message']),
				"ipaddress" => get_ip(),
				"includesig" => $thread['options']['signature'],
				"smilieoff" => $thread['options']['disablesmilies'],
				"visible" => $visible,
				"posthash" => $db->escape_string($thread['posthash'])
			);
			$plugins->run_hooks_by_ref("datahandler_post_insert_thread_post", $this);

			$db->update_query(TABLE_PREFIX."posts", $newpost, "pid='{$thread['pid']}'");
			$tid = $thread['tid'];
			$pid = $thread['pid'];
		}

		// Inserting a new thread into the database.
		else
		{
			$newthread = array(
				"fid" => $thread['fid'],
				"subject" => $db->escape_string($thread['subject']),
				"icon" => intval($thread['icon']),
				"uid" => $thread['uid'],
				"username" => $db->escape_string($thread['username']),
				"dateline" => intval($thread['dateline']),
				"lastpost" => intval($thread['dateline']),
				"lastposter" => $db->escape_string($thread['username']),
				"views" => 0,
				"replies" => 0,
				"visible" => $visible
			);

			$plugins->run_hooks_by_ref("datahandler_post_insert_thread", $this);

			$db->insert_query(TABLE_PREFIX."threads", $newthread);
			$tid = $db->insert_id();

			$newpost = array(
				"tid" => $tid,
				"fid" => $thread['fid'],
				"subject" => $db->escape_string($thread['subject']),
				"icon" => intval($thread['icon']),
				"uid" => $thread['uid'],
				"username" => $db->escape_string($thread['username']),
				"dateline" => intval($thread['dateline']),
				"message" => $db->escape_string($thread['message']),
				"ipaddress" => get_ip(),
				"includesig" => $thread['options']['signature'],
				"smilieoff" => $thread['options']['disablesmilies'],
				"visible" => $visible,
				"posthash" => $db->escape_string($thread['posthash'])
			);
			$plugins->run_hooks_by_ref("datahandler_post_insert_thread_post", $this);			
			$db->insert_query(TABLE_PREFIX."posts", $newpost);
			$pid = $db->insert_id();

			// Now that we have the post id for this first post, update the threads table.
			$firstpostup = array("firstpost" => $pid);
			$db->update_query(TABLE_PREFIX."threads", $firstpostup, "tid='{$tid}'");
		}

		// If we're not saving a draft there are some things we need to check now
		if(!$thread['savedraft'])
		{

			// Automatic subscription to the thread
			if($thread['options']['emailnotify'] != "no" && $thread['uid'] > 0)
			{
				$favoriteadd = array(
					"uid" => intval($thread['uid']),
					"tid" => intval($tid),
					"type" => "s"
				);
				$db->insert_query(TABLE_PREFIX."favorites", $favoriteadd);
			}

			// Perform any selected moderation tools.
			if(is_moderator($thread['fid'], "", $thread['uid']) == "yes" && is_array($thread['modoptions']))
			{
				$modoptions = $thread['modoptions'];
				$modlogdata['fid'] = $tid;
				$modlogdata['tid'] = $thread['tid'];

				// Close the thread.
				if($modoptions['closethread'] == "yes")
				{
					$newclosed = "closed='yes'";
					log_moderator_action($modlogdata, "Thread closed");
				}

				// Stick the thread.
				if($modoptions['stickthread'] == "yes")
				{
					$newstick = "sticky='1'";
					log_moderator_action($modlogdata, "Thread stuck");
				}

				// Execute moderation options.
				if($newstick && $newclosed)
				{
					$sep = ",";
				}
				if($newstick || $newclosed)
				{
					$db->query("
						UPDATE ".TABLE_PREFIX."threads
						SET $newclosed$sep$newstick
						WHERE tid='{$tid}'
					");
				}
			}
			// If we have a registered user then update their post count and last post times.
			if($thread['uid'] > 0)
			{
				$user = get_user($thread['uid']);
				$update_query = array();
				// Only update the lastpost column of the user if the date of the thread is newer than their last post.
				if($thread['dateline'] > $user['lastpost'])
				{
					$update_query[] = "lastpost='".$thread['dateline']."'";
				}
				// Update the post count if this forum allows post counts to be tracked
				if($forum['usepostcounts'] != "no")
				{
					$update_query[] = "postnum=postnum+1";
				}

				// Only update the table if we need to.
				if(is_array($update_query))
				{
					$update_query = implode(", ", $update_query);
					$db->query("UPDATE ".TABLE_PREFIX."users SET $update_query WHERE uid='".$thread['uid']."'");
				}
			}

			// Queue up any forum subscription notices to users who are subscribed to this forum.
			$excerpt = my_substr($thread['message'], 0, $mybb->settings['subscribeexcerpt']).$lang->emailbit_viewthread;
			$query = $db->query("
				SELECT u.username, u.email, u.uid, u.language
				FROM ".TABLE_PREFIX."forumsubscriptions fs, ".TABLE_PREFIX."users u
				WHERE fs.fid='".intval($thread['fid'])."'
				AND u.uid=fs.uid
				AND fs.uid!='".intval($thread['uid'])."'
				AND u.lastactive>'{$forum['lastpost']}'
			");
			while($subscribedmember = $db->fetch_array($query))
			{
				if($done_users[$subscribedmember['uid']])
				{
					continue;
				}
				$done_users[$subscribedmember['uid']] = 1;
				// Determine the language pack we'll be using to send this email in and load it if it isn't already.
				if($subscribedmember['language'] != '' && $lang->language_exists($subscribedmember['language']))
				{
					$uselang = $subscribedmember['language'];
				}
				else if($mybb->settings['bblanguage'])
				{
					$uselang = $mybb->settings['bblanguage'];
				}
				else
				{
					$uselang = "english";
				}

				if($uselang == $mybb->settings['bblanguage'])
				{
					$emailsubject = $lang->emailsubject_forumsubscription;
					$emailmessage = $lang->email_forumsubscription;
				}
				else
				{
					if(!isset($langcache[$uselang]['emailsubject_forumsubscription']))
					{
						$userlang = new MyLanguage;
						$userlang->set_path(MYBB_ROOT."inc/languages");
						$userlang->set_language($uselang);
						$userlang->load("messages");
						$langcache[$uselang]['emailsubject_forumsubscription'] = $userlang->emailsubject_forumsubscription;
						$langcache[$uselang]['email_forumsubscription'] = $userlang->email_forumsubscription;
						unset($userlang);
					}
					$emailsubject = $langcache[$uselang]['emailsubject_forumsubscription'];
					$emailmessage = $langcache[$uselang]['email_forumsubscription'];
				}
				$emailsubject = sprintf($emailsubject, $forum['name']);
				$emailmessage = sprintf($emailmessage, $subscribedmember['username'], $thread['username'], $forum['name'], $mybb->settings['bbname'], $thread['subject'], $excerpt, $mybb->settings['bburl'], $tid, $thread['fid']);
				$new_email = array(
					"mailto" => $db->escape_string($subscribedmember['email']),
					"mailfrom" => '',
					"subject" => $db->escape_string($emailsubject),
					"message" => $db->escape_string($emailmessage)
				);
				$db->insert_query(TABLE_PREFIX."mailqueue", $new_email);
				unset($userlang);
				$queued_email = 1;
			}
			// Have one or more emails been queued? Update the queue count
			if($queued_email == 1)
			{
				$cache->updatemailqueue();
			}
			// Automatically subscribe the user to this thread if they've chosen to.
			if($thread['options']['emailnotify'] != "no" && $thread['uid'] > 0)
			{
				$insert_favorite = array(
					'uid' => intval($thread['uid']),
					'tid' => $tid,
					'type' => 's'
				);
				$db->insert_query(TABLE_PREFIX.'favorites', $insert_favorite);
			}
		}

		// Assign any uploaded attachments with the specific posthash to the newly created post.
		if($thread['posthash'])
		{
			$thread['posthash'] = $db->escape_string($thread['posthash']);
			$attachmentassign = array(
				"pid" => $pid
			);
			$db->update_query(TABLE_PREFIX."attachments", $attachmentassign, "posthash='{$thread['posthash']}'");
		}

		$plugins->run_hooks_by_ref("datahandler_post_insert_post", $this);

		// Thread is public - update the forum counts.
		if($visible == 1 || $visible == 0)
		{
			$cache->updatestats();
			update_thread_count($tid);
			update_forum_count($thread['fid']);
		}

		// Return the post's pid and whether or not it is visible.
		return array(
			"pid" => $pid,
			"tid" => $tid,
			"visible" => $visible
		);
	}

	/**
	 * Updates a post that is already in the database.
	 *
	 */
	function update_post()
	{
		global $db, $mybb, $plugins;

		// Yes, validating is required.
		if($this->get_validated() != true)
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The post is not valid.");
		}

		$post = &$this->data;

		$post['pid'] = intval($post['pid']);

		// If we don't have a tid then we need to fetch it along with the forum id.
		if(!$post['tid'] || !$post['fid'])
		{
			$query = $db->simple_select("posts", "tid,fid", "pid='".intval($post['pid'])."'");
			$tid_fetch = $db->fetch_array($query);
			$post['tid'] = $tid_fetch['tid'];
			$post['fid'] = $tid_fetch['fid'];
		}
		// Check if this is the first post in a thread.
		$options = array(
			"order_by" => "dateline",
			"order_dir" => "asc",
			"limit_start" => 0,
			"limit" => 1
		);
		$query = $db->simple_select("posts", "pid", "tid='".intval($post['tid'])."'", $options);
		$first_post_check = $db->fetch_array($query);
		if($first_post_check['pid'] == $post['pid'])
		{
			$first_post = 1;
		}
		else
		{
			$first_post = 0;
		}
		// Update the thread details that might have been changed first.
		if($first_post)
		{
			$updatethread = array();

			if(isset($post['subject']))
			{
				$updatethread['subject'] = $db->escape_string($post['subject']);
			}

			if(isset($post['icon']))
			{
				$updatethread['icon'] = intval($post['icon']);
			}
			if(count($updatethread) > 0)
			{
				$db->update_query(TABLE_PREFIX."threads", $updatethread, "tid='".intval($post['tid'])."'");
			}
		}

		// Prepare array for post updating.
		$updatepost = array();

		if(isset($post['subject']))
		{
			$updatepost['subject'] = $db->escape_string($post['subject']);
		}

		if(isset($post['message']))
		{
			$updatepost['message'] = $db->escape_string($post['message']);
		}

		if(isset($post['icon']))
		{
			$updatepost['icon'] = intval($post['icon']);
		}

		if(isset($post['options']))
		{
			if(isset($post['options']['disablesmilies']))
			{
				$updatepost['smilieoff'] = $db->escape_string($post['options']['disablesmilies']);
			}
			if(isset($post['options']['signature']))
			{
				$updatepost['includesig'] = $db->escape_string($post['options']['signature']);
			}
		}

		// If we need to show the edited by, let's do so.
		if(($mybb->settings['showeditedby'] == "yes" && is_moderator($post['fid'], "caneditposts", $post['edit_uid']) != "yes") || ($mybb->settings['showeditedbyadmin'] == "yes" && is_moderator($post['fid'], "caneditposts", $post['edit_uid']) == "yes"))
		{
			$updatepost['edituid'] = intval($post['edit_uid']);
			$updatepost['edittime'] = time();
		}
		
		$plugins->run_hooks_by_ref("datahandler_post_update", $this);
		$db->update_query(TABLE_PREFIX."posts", $updatepost, "pid='".intval($post['pid'])."'");
		
		// Automatic subscription to the thread
		if($post['options']['emailnotify'] != "no" && $post['uid'] > 0)
		{
			$query = $db->simple_select("favorites", "fid", "uid='".intval($post['uid'])."' AND tid='".intval($tid)."' AND type='s'", array("limit" => 1));
			$already_subscribed = $db->fetch_field($query, "fid");
			if(!$already_subscribed)
			{
				$favoriteadd = array(
					"uid" => intval($post['uid']),
					"tid" => intval($post['tid']),
					"type" => "s"
				);
				$db->insert_query(TABLE_PREFIX."favorites", $favoriteadd);
			}
		}
		else
		{
			$db->delete_query(TABLE_PREFIX."favorites", "type='s' AND uid='{$post['uid']}' AND tid='{$post['tid']}'");
		}
		update_thread_attachment_count($post['tid']);

		update_forum_count($post['fid']);
	}
}
?>