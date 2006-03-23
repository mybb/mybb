<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
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
		
		// After all of this, if we still don't have a username, force the username as "Guest"
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
	 */
	function verify_subject()
	{
		$subject = &$this->data['subject'];
		// Check for correct subject content.
		
		// Are we editing an existing thread or post?
		if($this->action == "edit" && $post['pid'])
		{
			// Here we determine if we're editing the first post of a thread or not.
			$options = array(
				"limit" => 1,
				"limit_start" => 0,
				"order_by" => "dateline",
				"order_dir" => "asc"
			);
			$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid=".$tid, $options);
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
			if(strlen(trim($subject)) == 0 && $first_post)
			{
				$this->set_error("firstpost_no_subject");
				return false;
			}
			elseif(strlen(trim($subject)) == 0)
			{
				$subject = "[no subject]";
			}
		}
		
		// This is a new post
		else if($this->action == "post")
		{
			if(strlen(trim($subject)) == 0)
			{
				$subject = "[no subject]";
			}
		}
		
		// This is a new thread and we require that a subject is present.
		else
		{
			if(strlen(trim($subject)) == 0)
			{
				$this->set_error("no_subject");
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
		$message = &$this->data['message'];
		
		// Do we even have a message at all?
		if(trim($message) == "")
		{
			$this->set_error("no_message");
			return false;
		}
	
		// If this board has a maximum message length check if we're over it.
		else if(strlen($message) > $mybb->settings['messagelength'] && $mybb->settings['messagelength'] > 0 && ismod($post['fid'], "", $post['uid']) != "yes")
		{
			$this->set_error("message_too_long");
			return false;
		}
		
		// And if we've got a minimum message length do we meet that requirement too?
		else if(strlen($message) < $mybb->settings['minmessagelength'] && $mybb->settings['minmessagelength'] > 0 && ismod($post['fid'], "", $post['uid']) != "yes")
		{
			$this->set_error("message_too_short");
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
		
		if($options['signature'] != "yes")
		{
			$options['signature'] = "no";
		}
		if($options['emailnotify'] != "yes")
		{
			$options['emailnotify'] = "no";
		}
		if($options['disablesmilies'] != "yes")
		{
			$options['disablesmilies'] = "no";
		}
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

		$post = &$this->post;
		
		// Check if post flooding is enabled within MyBB or if the admin override option is specified.
		if($mybb->settings['postfloodcheck'] == "on" && $post['uid'] != 0 && $this->admin_override == false)
		{
			// Fetch the user information for this post - used to check their last post date.
			$user = get_user($post['uid']);
			
			// A little bit of calculation magic and moderator status checking.
			if(time()-$user['lastpost'] <= $mybb->settings['postfloodsecs'] && ismod($post['fid'], "", $user['uid']) != "yes")
			{
				// Oops, user has been flooding - throw back error message.
				$this->set_error("post_flooding");
				return false;
			}
		}
		// All is well that ends well - return true.
		return true;
	}
	
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
	
	function verify_reply_to()
	{
		global $db;
		$post = &$this->data;
		
		// Check if the post being replied to actually exists in this thread
		if($post['replyto'])
		{
			$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "pid='".$post['replyto']."'");
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
			$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid='".$post['tid']."'", $options);
			$reply_to = $db->fetch_array($query);
			$post['replyto'] = $reply_to['pid'];
		}
		
		return true;
	}
	
	function verify_post_icon()
	{
		global $cache;
		
		$post = &$this->data;
		
		// Verify that the post icon actually exists if we have one
		
		// If we don't assign it as 0
		if(!$post['icon'])
		{
			$post['icon'] = 0;
		}
		return true;
	}
	
	function verify_dateline()
	{
		$dateline = &$this->data['dateline'];
		
		if($dateline < 0 || is_int($dateline) == false)
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
		
		$this->verify_author();

		$this->verify_subject();

		$this->verify_message();
		
		$this->verify_dateline();

		$this->verify_post_flooding();
		
		$this->verify_image_count();
		
		$this->verify_reply_to();
		
		$this->verify_post_icon();

		$this->verify_post_icon();
		
		$this->verify_options();
		
		$plugins->run_hooks("datahandler_post_validate_post");

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
		global $db, $mybb, $plugins;
		
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
				$query = $db->simple_select(
					TABLE_PREFIX."favorites",
					"uid",
					"type='s' AND tid='".$post['tid']."' AND uid='".$post['uid']."'"
				);
				$subcheck = $db->fetch_array($query);
				if(!$subcheck['uid'])
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
			if(ismod($post['fid'], "", $post['uid']) == "yes" && $post['modoptions'])
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
					logmod($modlogdata, "Thread closed");
				}

				// Open the thread.
				if($modoptions['closethread'] != "yes" && $thread['closed'] == "yes")
				{
					$newclosed = "closed='no'";
					logmod($modlogdata, "Thread opened");
				}

				// Stick the thread.
				if($modoptions['stickthread'] == "yes" && $thread['sticky'] != 1)
				{
					$newstick = "sticky='1'";
					logmod($modlogdata, "Thread stuck");
				}

				// Unstick the thread.
				if($modoptions['stickthread'] != "yes" && $thread['sticky'])
				{
					$newstick = "sticky='0'";
					logmod($modlogdata, "Thread unstuck");
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
						WHERE tid='".$thread['tid']."'
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

		// Are we updating a post which is already a draft? Perhaps changing it into a visible post?
		if($post['pid'])
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
				"visible" => $visible
				);
			$db->update_query(TABLE_PREFIX."posts", $updatedpost, "pid='".$post['pid']."'");
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
				"visible" => $visible
				);

			$db->insert_query(TABLE_PREFIX."posts", $newreply);
			$pid = $db->insert_id();
		}

		// Assign any uploaded attachments with the specific posthash to the newly created post.
		if($post['posthash'])
		{
			$post['posthash'] = $db->escape_string($post['posthash']);
			$attachmentassign = array(
				"pid" => $post['pid']
			);
			$db->update_query(TABLE_PREFIX."attachments", $attachmentassign, "posthash='".$post['posthash']."'");
		}

		$plugins->run_hooks("datahandler_post_insert_post");

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
		
		$this->verify_author();

		$this->verify_subject();

		$this->verify_message();
		
		$this->verify_dateline();		

		$this->verify_post_flooding();
		
		$this->verify_image_count();
		
		$this->verify_post_icon();

		$this->verify_post_icon();
		
		$this->verify_options();
		
		$plugins->run_hooks("datahandler_post_validate_thread");

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
		global $db, $mybb, $plugins;

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

		// Are we updating a post which is already a draft? Perhaps changing it into a visible post?
		if($post['pid'])
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
			$db->update_query(TABLE_PREFIX."threads", $newthread, "tid='$tid'");

			$newpost = array(
				"subject" => $db->escape_string($thread['subject']),
				"icon" => intval($thread['icon']),
				"username" => $db->escape_string($thread['username']),
				"dateline" => intval($thread['dateline']),
				"message" => $db->escape_string($thread['message']),
				"ipaddress" => getip(),
				"includesig" => $thread['options']['signature'],
				"smilieoff" => $thread['options']['disablesmilies'],
				"visible" => $visible
			);

			$db->update_query(TABLE_PREFIX."posts", $newpost, "pid='$pid'");
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

			$plugins->run_hooks("newthread_do_newthread_process");

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
				"ipaddress" => getip(),
				"includesig" => $thread['options']['signature'],
				"smilieoff" => $thread['options']['disablesmilies'],
				"visible" => $visible
			);
			$db->insert_query(TABLE_PREFIX."posts", $newpost);
			$pid = $db->insert_id();
			
			// Now that we have the post id for this first post, update the threads table.
			$firstpostup = array("firstpost" => $pid);
			$db->update_query(TABLE_PREFIX."threads", $firstpostup, "tid='$tid'");
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
			if(ismod($thread['fid'], "", $thread['uid']) == "yes" && is_array($thread['modoptions']))
			{
				$modoptions = $thread['modoptions'];
				$modlogdata['fid'] = $tid;
				$modlogdata['tid'] = $thread['tid'];

				// Close the thread.
				if($modoptions['closethread'] == "yes")
				{
					$newclosed = "closed='yes'";
					logmod($modlogdata, "Thread closed");
				}

				// Stick the thread.
				if($modoptions['stickthread'] == "yes")
				{
					$newstick = "sticky='1'";
					logmod($modlogdata, "Thread stuck");
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
						WHERE tid='".$tid."'
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
						
			// Send out any forum subscription notices to users who are subscribed to this forum.
			$excerpt = substr($thread['message'], 0, $mybb->settings['subscribeexcerpt']).$lang->emailbit_viewthread;
			$query = $db->query("SELECT u.username, u.email, u.uid, u.language FROM ".TABLE_PREFIX."forumsubscriptions fs, ".TABLE_PREFIX."users u WHERE fs.fid='".intval($thread['fid'])."' AND u.uid=fs.uid AND fs.uid!='".intval($thread['uid'])."' AND u.lastactive>'".$forum['lastpost']."'");
			while($subscribedmember = $db->fetch_array($query))
			{
				// Determine the language pack we'll be using to send this email in and load it if it isn't already
				if($subscribedmember['language'] != '' && $lang->languageExists($subscribedmember['language']))
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
						$userlang->setPath("./inc/languages");
						$userlang->setLanguage($uselang);
						$userlang->load("messages");
						$langcache[$uselang]['emailsubject_forumsubscription'] = $userlang->emailsubject_forumsubscription;
						$langcache[$uselang]['email_forumsubscription'] = $userlang->email_forumsubscription;
						unset($userlang);
					}
					$emailsubject = $langcache[$uselang]['emailsubject_forumsubscription'];
					$emailmessage = $langcache[$uselang]['email_forumsubscription'];
				}
				$emailsubject = sprintf($emailsubject, $forum['name']);
				$emailmessage = sprintf($emailmessage, $subscribedmember['username'], $mybb->user['username'], $forum['name'], $mybb->settings['bbname'], $mybb->input['subject'], $excerpt, $mybb->settings['bburl'], $tid, $thread['fid']);
				mymail($subscribedmember['email'], $emailsubject, $emailmessage);
				unset($userlang);
			}

			// Automatically subscribe the user to this thread if they've chosen to.
			if($thread['options']['emailnotify'] != "no" && $thread['uid'] > 0)
			{
				$db->query("INSERT INTO ".TABLE_PREFIX."favorites (uid,tid,type) VALUES ('".intval($thread['uid'])."','$tid','s')");
			}
		}

		// Assign any uploaded attachments with the specific posthash to the newly created post.
		if($thread['posthash'])
		{
			$thread['posthash'] = $db->escape_string($thread['posthash']);
			$attachmentassign = array(
				"pid" => $pid
			);
			$db->update_query(TABLE_PREFIX."attachments", $attachmentassign, "posthash='".$thread['posthash']."'");
		}
		
		$plugins->run_hooks("datahandler_post_insert_post");
		
		// Thread is visible - update the forum counts.
		if($visible == 1)
		{
			$cache->updatestats();
			updatethreadcount($tid);
			updateforumcount($thread['fid']);			
		}
		// This thread is in the moderation queue. Update the moderation count for this forum.
		else if($visible == 0)
		{
			$db->query("UPDATE ".TABLE_PREFIX."threads SET unapprovedposts=unapprovedposts+1 WHERE tid='$tid'");
			$db->query("UPDATE ".TABLE_PREFIX."forums SET unapprovedthreads=unapprovedthreads+1, unapprovedposts=unapprovedposts+1 WHERE fid='".intval($thread['fid'])."'");
		
		}

		// Return the post's pid and whether or not it is visible.
		return array(
			"pid" => $pid,
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
		if(count($this->get_errors() > 0))
		{
			die("The post is not valid.");
		}
		
		$post = &$this->post;
		
		$post['pid'] = intval($post['pid']);

		// Check if this is the first post in a thread.
		$options = array(
			"orderby" => "dateline",
			"order_dir" => "asc",
			"limit_start" => 0,
			"limit" => 1
		);
		$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid=".$post['tid'], $options);
		$first_post_check = $db->fetch_array($query);
		if($first_post_check['pid'] == $pid)
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
			$updatethread = array(
				"subject" => $db->escape_string($post['subject']),
				"icon" => intval($post['icon']),
				);
			$db->update_query(TABLE_PREFIX."threads", $updatethread, "tid='".$post['pid']."'");
		}

		// Prepare array for post updating.
		$updatepost = array(
			"subject" => $db->escape_string($post['subject']),
			"message" => $db->escape_string($post['message']),
			"icon" => intval($post['icon']),
			"smilieoff" => $post['options']['disablesmilies'],
			"includesig" => $post['options']['signature']
		);

		// If we need to show the edited by, let's do so.
		if(($mybb->settings['showeditedby'] == "yes" && ismod($post['fid'], "caneditposts", $post['edit_uid']) != "yes") || ($mybb->settings['showeditedbyadmin'] == "yes" && ismod($post['fid'], "caneditposts", $post['edit_uid']) == "yes"))
		{
			$updatepost['edituid'] = intval($post['edit_uid']);
			$updatepost['edittime'] = time();
		}
		$plugins->run_hooks("datahandler_post_update");
		
		$db->update_query(TABLE_PREFIX."posts", $updatepost, "pid='".$post['pid']."'");
	}

	/**
	 * Delete a post from the database.
	 *
	 * @param int The post id of the post that is to be deleted.
	 */
	function delete_by_pid($pid)
	{
		global $db;
		
		$post = get_post($pid);
		
		if(!$post['pid'])
		{
			return false;
		}

		// Is this the first post of a thread? If so, we'll need to delete the whole thread.
		$options = array(
			"orderby" => "dateline",
			"order_dir" => "asc",
			"limit_start" => 0,
			"limit" => 1
		);
		$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid=".$post['tid'], $options);
		$firstcheck = $db->fetch_array($query);
		if($firstcheck['pid'] == $post['pid'])
		{
			$firstpost = true;
		}
		else
		{
			$firstpost = false;
		}

		// Delete the whole thread or this post only?
		if($firstpost === true)
		{
			deletethread($post['tid']);
			updateforumcount($post['fid']);
	
			$plugins->run_hooks("datahandler_post_delete_thread");		
		}
		else
		{
			deletepost($post['pid']);
			updatethreadcount($post['tid']);
			updateforumcount($post['fid']);
			
			$plugins->run_hooks("datahandler_post_delete_post");				
		}
		return true;
	}
}

?>