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

/**
 * PM handling class, provides common structure to handle private messaging data.
 *
 */
class PMDataHandler extends DataHandler
{
	/**
	* The language file used in the data handler.
	*
	* @var string
	*/
	public $language_file = 'datahandler_pm';

	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	public $language_prefix = 'pmdata';

	/**
	 * Array of data inserted in to a private message.
	 *
	 * @var array
	 */
	public $pm_insert_data = array();

	/**
	 * Array of data used to update a private message.
	 *
	 * @var array
	 */
	public $pm_update_data = array();

	/**
	 * PM ID currently being manipulated by the datahandlers.
	 */
	public $pmid = 0;

	/**
	 * Values to be returned after inserting a PM.
	 *
	 * @var array
	 */
	public $return_values = array();

	/**
	 * Verifies a private message subject.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_subject()
	{
		$subject = &$this->data['subject'];

		// Subject is over 85 characters, too long.
		if(my_strlen($subject) > 85)
		{
			$this->set_error("too_long_subject");
			return false;
		}
		// No subject, apply the default [no subject]
		if(!trim_blank_chrs($subject))
		{
			$this->set_error("missing_subject");
			return false;
		}
		return true;
	}

	/**
	 * Verifies if a message for a PM is valid.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_message()
	{
		$message = &$this->data['message'];

		// No message, return an error.
		if(trim_blank_chrs($message) == '')
		{
			$this->set_error("missing_message");
			return false;
		}
		return true;
	}

	/**
	 * Verifies if the specified sender is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_sender()
	{
		global $db, $mybb, $lang;

		$pm = &$this->data;

		// Return if we've already validated
		if(!empty($pm['sender']))
		{
			return true;
		}

		// Fetch the senders profile data.
		$sender = get_user($pm['fromid']);

		// Collect user permissions for the sender.
		$sender_permissions = user_permissions($pm['fromid']);

		// Check if the sender is over their quota or not - if they are, disable draft sending
		if(isset($pm['options']['savecopy']) && $pm['options']['savecopy'] != 0 && empty($pm['saveasdraft']))
		{
			if($sender_permissions['pmquota'] != "0" && $sender['totalpms'] >= $sender_permissions['pmquota'] && $this->admin_override != true)
			{
				$pm['options']['savecopy'] = 0;
			}
		}

		// Assign the sender information to the data.
		$pm['sender'] = array(
			"uid" => $sender['uid'],
			"username" => $sender['username']
		);

		return true;
	}

	/**
	 * Verifies if an array of recipients for a private message are valid
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_recipient()
	{
		global $cache, $db, $mybb, $lang;

		$pm = &$this->data;

		$recipients = array();

		$invalid_recipients = array();
		// We have our recipient usernames but need to fetch user IDs
		if(array_key_exists("to", $pm))
		{
			foreach(array("to", "bcc") as $recipient_type)
			{
				if(!isset($pm[$recipient_type]))
				{
					$pm[$recipient_type] = array();
				}
				if(!is_array($pm[$recipient_type]))
				{
					$pm[$recipient_type] = array($pm[$recipient_type]);
				}

				$pm[$recipient_type] = array_map('trim', $pm[$recipient_type]);
				$pm[$recipient_type] = array_filter($pm[$recipient_type]);

				// No recipients? Skip query
				if(empty($pm[$recipient_type]))
				{
					if($recipient_type == 'to' && !$pm['saveasdraft'])
					{
						$this->set_error("no_recipients");
						return false;
					}
					continue;
				}

				$recipientUsernames = array_map(array($db, 'escape_string'), $pm[$recipient_type]);
				$recipientUsernames = "'".implode("','", $recipientUsernames)."'";

				$query = $db->simple_select('users', '*', 'username IN('.$recipientUsernames.')');

				$validUsernames = array();

				while($user = $db->fetch_array($query))
				{
					if($recipient_type == "bcc")
					{
						$user['bcc'] = 1;
					}

					$recipients[] = $user;
					$validUsernames[] = $user['username'];
				}

				foreach($pm[$recipient_type] as $username)
				{
					if(!in_array($username, $validUsernames))
					{
						$invalid_recipients[] = $username;
					}
				}
			}
		}
		// We have recipient IDs
		else
		{
			foreach(array("toid", "bccid") as $recipient_type)
			{
				if(!isset($pm[$recipient_type]))
				{
					$pm[$recipient_type] = array();
				}
				if(!is_array($pm[$recipient_type]))
				{
					$pm[$recipient_type] = array($pm[$recipient_type]);
				}
				$pm[$recipient_type] = array_map('intval', $pm[$recipient_type]);
				$pm[$recipient_type] = array_filter($pm[$recipient_type]);

				// No recipients? Skip query
				if(empty($pm[$recipient_type]))
				{
					if($recipient_type == 'toid' && !$pm['saveasdraft'])
					{
						$this->set_error("no_recipients");
						return false;
					}
					continue;
				}

				$recipientUids = "'".implode("','", $pm[$recipient_type])."'";

				$query = $db->simple_select('users', '*', 'uid IN('.$recipientUids.')');

				$validUids = array();

				while($user = $db->fetch_array($query))
				{
					if($recipient_type == "bccid")
					{
						$user['bcc'] = 1;
					}

					$recipients[] = $user;
					$validUids[] = $user['uid'];
				}

				foreach($pm[$recipient_type] as $uid)
				{
					if(!in_array($uid, $validUids))
					{
						$invalid_recipients[] = $uid;
					}
				}
			}
		}

		// If we have one or more invalid recipients and we're not saving a draft, error
		if(count($invalid_recipients) > 0)
		{
			$invalid_recipients = implode(", ", array_map("htmlspecialchars_uni", $invalid_recipients));
			$this->set_error("invalid_recipients", array($invalid_recipients));
			return false;
		}

		$sender_permissions = user_permissions($pm['fromid']);

		// Are we trying to send this message to more users than the permissions allow?
		if($sender_permissions['maxpmrecipients'] > 0 && count($recipients) > $sender_permissions['maxpmrecipients'] && $this->admin_override != true)
		{
			$this->set_error("too_many_recipients", array($sender_permissions['maxpmrecipients']));
		}

		// Now we're done with that we loop through each recipient
		foreach($recipients as $user)
		{
			// Collect group permissions for this recipient.
			$recipient_permissions = user_permissions($user['uid']);

			// See if the sender is on the recipients ignore list and that either
			// - admin_override is set or
			// - sender is an administrator
			if(($this->admin_override != true && $sender_permissions['cancp'] != 1) && $sender_permissions['canoverridepm'] != 1)
			{
				$ignorelist = explode(",", $user['ignorelist']);
				if(!empty($ignorelist) && in_array($pm['fromid'], $ignorelist))
				{
					$this->set_error("recipient_is_ignoring", array($user['username']));
				}

				// Is the recipient only allowing private messages from their buddy list?
				if($mybb->settings['allowbuddyonly'] == 1 && $user['receivefrombuddy'] == 1)
				{
					$buddylist = explode(",", $user['buddylist']);
					if(!empty($buddylist) && !in_array($pm['fromid'], $buddylist))
					{
						$this->set_error("recipient_has_buddy_only", array(htmlspecialchars_uni($user['username'])));
					}
				}

				// Can the recipient actually receive private messages based on their permissions or user setting?
				if(($user['receivepms'] == 0 || $recipient_permissions['canusepms'] == 0) && empty($pm['saveasdraft']))
				{
					$this->set_error("recipient_pms_disabled", array($user['username']));
					return false;
				}
			}

			// Check to see if the user has reached their private message quota - if they have, email them.
			if($recipient_permissions['pmquota'] != "0" && $user['totalpms'] >= $recipient_permissions['pmquota'] && $recipient_permissions['cancp'] != 1 && $sender_permissions['cancp'] != 1 && empty($pm['saveasdraft']) && !$this->admin_override)
			{
				if(trim($user['language']) != '' && $lang->language_exists($user['language']))
				{
					$uselang = trim($user['language']);
				}
				elseif($mybb->settings['bblanguage'])
				{
					$uselang = $mybb->settings['bblanguage'];
				}
				else
				{
					$uselang = "english";
				}
				if($uselang == $mybb->settings['bblanguage'] || !$uselang)
				{
					$emailsubject = $lang->emailsubject_reachedpmquota;
					$emailmessage = $lang->email_reachedpmquota;
				}
				else
				{
					$userlang = new MyLanguage;
					$userlang->set_path(MYBB_ROOT."inc/languages");
					$userlang->set_language($uselang);
					$userlang->load("messages");
					$emailsubject = $userlang->emailsubject_reachedpmquota;
					$emailmessage = $userlang->email_reachedpmquota;
				}
				$emailmessage = $lang->sprintf($emailmessage, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl']);
				$emailsubject = $lang->sprintf($emailsubject, $mybb->settings['bbname'], $pm['subject']);

				$new_email = array(
					"mailto" => $db->escape_string($user['email']),
					"mailfrom" => '',
					"subject" => $db->escape_string($emailsubject),
					"message" => $db->escape_string($emailmessage),
					"headers" => ''
				);

				$db->insert_query("mailqueue", $new_email);
				$cache->update_mailqueue();

				if($this->admin_override != true)
				{
					$this->set_error("recipient_reached_quota", array($user['username']));
				}
			}

			// Everything looks good, assign some specifics about the recipient
			$pm['recipients'][$user['uid']] = array(
				"uid" => $user['uid'],
				"username" => $user['username'],
				"email" => $user['email'],
				"lastactive" => $user['lastactive'],
				"pmnotice" => $user['pmnotice'],
				"pmnotify" => $user['pmnotify'],
				"language" => $user['language']
			);

			// If this recipient is defined as a BCC recipient, save it
			if(isset($user['bcc']) && $user['bcc'] == 1)
			{
				$pm['recipients'][$user['uid']]['bcc'] = 1;
			}
		}
		return true;
	}

	/**
	* Verify that the user is not flooding the system.
	*
	* @return boolean True
	*/
	function verify_pm_flooding()
	{
		global $mybb, $db;

		$pm = &$this->data;

		// Check if post flooding is enabled within MyBB or if the admin override option is specified.
		if($mybb->settings['pmfloodsecs'] > 0 && $pm['fromid'] != 0 && $this->admin_override == false)
		{
			// Fetch the senders profile data.
			$sender = get_user($pm['fromid']);

			// Calculate last post
			$query = $db->simple_select("privatemessages", "dateline", "fromid='".$db->escape_string($pm['fromid'])."' AND toid != '0'", array('order_by' => 'dateline', 'order_dir' => 'desc', 'limit' => 1));
			$sender['lastpm'] = $db->fetch_field($query, "dateline");

			// A little bit of calculation magic and moderator status checking.
			if(TIME_NOW-$sender['lastpm'] <= $mybb->settings['pmfloodsecs'] && !is_moderator("", "", $pm['fromid']))
			{
				// Oops, user has been flooding - throw back error message.
				$time_to_wait = ($mybb->settings['pmfloodsecs'] - (TIME_NOW-$sender['lastpm'])) + 1;
				if($time_to_wait == 1)
				{
					$this->set_error("pm_flooding_one_second");
				}
				else
				{
					$this->set_error("pm_flooding", array($time_to_wait));
				}
				return false;
			}
		}
		// All is well that ends well - return true.
		return true;
	}

	/**
	 * Verifies if the various 'options' for sending PMs are valid.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_options()
	{
		$options = &$this->data['options'];

		$this->verify_yesno_option($options, 'signature', 1);
		$this->verify_yesno_option($options, 'savecopy', 1);
		$this->verify_yesno_option($options, 'disablesmilies', 0);

		// Requesting a read receipt?
		if(isset($options['readreceipt']) && $options['readreceipt'] == 1)
		{
			$options['readreceipt'] = 1;
		}
		else
		{
			$options['readreceipt'] = 0;
		}
		return true;
	}

	/**
	 * Validate an entire private message.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_pm()
	{
		global $plugins;

		$pm = &$this->data;

		if(empty($pm['savedraft']))
		{
			$this->verify_pm_flooding();
		}

		// Verify all PM assets.
		$this->verify_subject();

		$this->verify_sender();

		$this->verify_recipient();

		$this->verify_message();

		$this->verify_options();

		$plugins->run_hooks("datahandler_pm_validate", $this);

		// Choose the appropriate folder to save in.
		if(!empty($pm['saveasdraft']))
		{
			$pm['folder'] = 3;
		}
		else
		{
			$pm['folder'] = 1;
		}

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
	 * Insert a new private message.
	 *
	 * @return array Array of PM useful data.
	 */
	function insert_pm()
	{
		global $cache, $db, $mybb, $plugins, $lang;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The PM needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The PM is not valid.");
		}

		// Assign data to common variable
		$pm = &$this->data;

		if(empty($pm['pmid']))
		{
			$pm['pmid'] = 0;
		}
		$pm['pmid'] = (int)$pm['pmid'];

		if(empty($pm['icon']) || $pm['icon'] < 0)
		{
			$pm['icon'] = 0;
		}

		$uid = 0;

		if(!is_array($pm['recipients']))
		{
			$recipient_list = array();
		}
		else
		{
			// Build recipient list
			foreach($pm['recipients'] as $recipient)
			{
				if(!empty($recipient['bcc']))
				{
					$recipient_list['bcc'][] = $recipient['uid'];
				}
				else
				{
					$recipient_list['to'][] = $recipient['uid'];
					$uid = $recipient['uid'];
				}
			}
		}

		$this->pm_insert_data = array(
			'fromid' => (int)$pm['sender']['uid'],
			'folder' => $pm['folder'],
			'subject' => $db->escape_string($pm['subject']),
			'icon' => (int)$pm['icon'],
			'message' => $db->escape_string($pm['message']),
			'dateline' => TIME_NOW,
			'status' => 0,
			'includesig' => $pm['options']['signature'],
			'smilieoff' => $pm['options']['disablesmilies'],
			'receipt' => (int)$pm['options']['readreceipt'],
			'readtime' => 0,
			'recipients' => $db->escape_string(my_serialize($recipient_list)),
			'ipaddress' => $db->escape_binary($pm['ipaddress'])
		);

		// Check if we're updating a draft or not.
		$query = $db->simple_select("privatemessages", "pmid, deletetime", "folder='3' AND uid='".(int)$pm['sender']['uid']."' AND pmid='{$pm['pmid']}'");
		$draftcheck = $db->fetch_array($query);

		// This PM was previously a draft
		if($draftcheck['pmid'])
		{
			if($draftcheck['deletetime'])
			{
				// This draft was a reply to a PM
				$pm['pmid'] = $draftcheck['deletetime'];
				$pm['do'] = "reply";
			}

			// Delete the old draft as we no longer need it
			$db->delete_query("privatemessages", "pmid='{$draftcheck['pmid']}'");
		}

		// Saving this message as a draft
		if(!empty($pm['saveasdraft']))
		{
			$this->pm_insert_data['uid'] = $pm['sender']['uid'];

			// If this is a reply, then piggyback into the deletetime to let us know in the future
			if($pm['do'] == "reply" || $pm['do'] == "replyall")
			{
				$this->pm_insert_data['deletetime'] = $pm['pmid'];
			}

			$plugins->run_hooks("datahandler_pm_insert_updatedraft", $this);
			$db->insert_query("privatemessages", $this->pm_insert_data);

			// If this is a draft, end it here - below deals with complete messages
			return array(
				"draftsaved" => 1
			);
		}

		$this->pmid = array();

		// Save a copy of the PM for each of our recipients
		foreach($pm['recipients'] as $recipient)
		{
			// Send email notification of new PM if it is enabled for the recipient
			$query = $db->simple_select("privatemessages", "dateline", "uid='".$recipient['uid']."' AND folder='1'", array('order_by' => 'dateline', 'order_dir' => 'desc', 'limit' => 1));
			$lastpm = $db->fetch_array($query);
			if($recipient['pmnotify'] == 1 && $recipient['lastactive'] > $lastpm['dateline'])
			{
				if($recipient['language'] != "" && $lang->language_exists($recipient['language']))
				{
					$uselang = $recipient['language'];
				}
				elseif($mybb->settings['bblanguage'])
				{
					$uselang = $mybb->settings['bblanguage'];
				}
				else
				{
					$uselang = "english";
				}
				if($uselang == $mybb->settings['bblanguage'] && !empty($lang->emailsubject_newpm))
				{
					$emailsubject = $lang->emailsubject_newpm;
					$emailmessage = $lang->email_newpm;
				}
				else
				{
					$userlang = new MyLanguage;
					$userlang->set_path(MYBB_ROOT."inc/languages");
					$userlang->set_language($uselang);
					$userlang->load("messages");
					$emailsubject = $userlang->emailsubject_newpm;
					$emailmessage = $userlang->email_newpm;
				}

				if(!$pm['sender']['username'])
				{
					$pm['sender']['username'] = $lang->mybb_engine;
				}

				require_once MYBB_ROOT.'inc/class_parser.php';
				$parser = new Postparser;
				$pm['message'] = $parser->text_parse_message($pm['message'], array('me_username' => $pm['sender']['username'], 'filter_badwords' => 1, 'safe_html' => 1));

				$emailmessage = $lang->sprintf($emailmessage, $recipient['username'], $pm['sender']['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $pm['message']);
				$emailsubject = $lang->sprintf($emailsubject, $mybb->settings['bbname'], $pm['subject']);

				$new_email = array(
					"mailto" => $db->escape_string($recipient['email']),
					"mailfrom" => '',
					"subject" => $db->escape_string($emailsubject),
					"message" => $db->escape_string($emailmessage),
					"headers" => ''
				);

				$db->insert_query("mailqueue", $new_email);
				$cache->update_mailqueue();
			}

			$this->pm_insert_data['uid'] = $recipient['uid'];
			$this->pm_insert_data['toid'] = $recipient['uid'];

			$plugins->run_hooks("datahandler_pm_insert", $this);
			$this->pmid[] = $db->insert_query("privatemessages", $this->pm_insert_data);

			// If PM noices/alerts are on, show!
			if($recipient['pmnotice'] == 1)
			{
				$updated_user = array(
					"pmnotice" => 2
				);
				$db->update_query("users", $updated_user, "uid='{$recipient['uid']}'");
			}

			// Update private message count (total, new and unread) for recipient
			require_once MYBB_ROOT."/inc/functions_user.php";
			update_pm_count($recipient['uid'], 7, $recipient['lastactive']);
		}

		// Are we replying or forwarding an existing PM?
		if($pm['pmid'])
		{
			if($pm['do'] == "reply" || $pm['do'] == "replyall")
			{
				$sql_array = array(
					'status' => 3,
					'statustime' => TIME_NOW
				);
				$db->update_query("privatemessages", $sql_array, "pmid={$pm['pmid']} AND uid={$pm['sender']['uid']}");
			}
			elseif($pm['do'] == "forward")
			{
				$sql_array = array(
					'status' => 4,
					'statustime' => TIME_NOW
				);
				$db->update_query("privatemessages", $sql_array, "pmid={$pm['pmid']} AND uid={$pm['sender']['uid']}");
			}
		}

		// If we're saving a copy
		if($pm['options']['savecopy'] != 0)
		{
			if(isset($recipient_list['to']) && count($recipient_list['to']) == 1)
			{
				$this->pm_insert_data['toid'] = $uid;
			}
			else
			{
				$this->pm_insert_data['toid'] = 0;
			}
			$this->pm_insert_data['uid'] = (int)$pm['sender']['uid'];
			$this->pm_insert_data['folder'] = 2;
			$this->pm_insert_data['status'] = 1;
			$this->pm_insert_data['receipt'] = 0;

			$plugins->run_hooks("datahandler_pm_insert_savedcopy", $this);
			$db->insert_query("privatemessages", $this->pm_insert_data);

			// Because the sender saved a copy, update their total pm count
			require_once MYBB_ROOT."/inc/functions_user.php";
			update_pm_count($pm['sender']['uid'], 1);
		}

		// Return back with appropriate data
		$this->return_values = array(
			"messagesent" => 1,
			"pmids" => $this->pmid
		);

		$plugins->run_hooks("datahandler_pm_insert_end", $this);

		return $this->return_values;
	}
}
