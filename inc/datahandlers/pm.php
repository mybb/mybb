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

*/

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
	var $language_file = 'datahandler_pm';

	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	var $language_prefix = 'pmdata';

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
		if(!$subject)
		{
			$subject = "[no subject]";
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
		if(trim($message) == '')
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

		// Fetch the senders profile data.
		$sender = get_user($pm['fromid']);

		// Collect user permissions for the sender.
		$sender_permissions = user_permissions($pm['fromid']);

		// Check if the sender is over their quota or not - if they are, disable draft sending
		if($pm['options']['savecopy'] != "no" && !$pm['saveasdraft'])
		{
			if($sender_permissions['pmquota'] != "0" && $sender['totalpms'] >= $sender_permissions['pmquota'] && $this->admin_override != true)
			{
				$pm['options']['savecopy'] = "no";
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
	 * Verifies if a recipient for the private message is valid.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_recipient()
	{
		global $db, $mybb, $lang;

		$pm = &$this->data;

		// No user ID is specified, we need to query for it based on the username.
		if(!isset($pm['toid']))
		{
			$query = $db->simple_select("users", "uid", "username='".$db->escape_string($pm['username'])."'", array("limit" => 1));
			$user = $db->fetch_array($query);
			$pm['toid'] = $user['uid'];
		}

		// Cache the to user information.
		$touser = get_user($pm['toid']);

		// Check if we have a valid recipient or not.
		if(!$touser['uid'] && !$pm['saveasdraft'])
		{
			$this->set_error("invalid_recipient");
			return false;
		}

		// Collect group permissions for the sender and recipient.
		$recipient_permissions = user_permissions($touser['uid']);
		$sender_permissions = user_permissions($pm['fromid']);

		// See if the sender is on the recipients ignore list and that either
		// - admin_override is set or
		// - sender is an administrator

		if($this->admin_override != true && $sender_permissions['cancp'] != "yes")
		{
			$ignorelist = explode(",", $touser['ignorelist']);
			foreach($ignorelist as $uid)
			{
				if($uid == $pm['fromid'])
				{
					$this->set_error("recipient_is_ignoring");
					return false;
				}
			}
		}

		// Can the recipient actually receive private messages based on their permissions or user setting?
		if($touser['receivepms'] == "no" || $recipient_permissions['canusepms'] == "no" && !$pm['saveasdraft'])
		{
			$this->set_error("recipient_pms_disabled");
			return false;
		}

		// Check to see if the user has reached their private message quota - if they have, email them.
		if($recipient_permissions['pmquota'] != "0" && $touser['pms_total'] >= $recipient_permissions['pmquota'] && $recipient_permissions['cancp'] != "yes" && $sender_permissions['cancp'] != "yes" && !$pm['saveasdraft'] && !$this->admin_override)
		{
			if(trim($touser['language']) != '' && $lang->language_exists($touser['language']))
			{
				$uselang = trim($touser['language']);
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
			$emailmessage = sprintf($emailmessage, $touser['username'], $mybb->settings['bbname'], $mybb->settings['bburl']);
			$emailsubject = sprintf($emailsubject, $mybb->settings['bbname']);
			mymail($touser['email'], $emailsubject, $emailmessage);

			$this->set_error("recipient_reached_quota");
			return false;
		}

		// Everything looks good, assign some specifics about the recipient
		$pm['recipient'] = array(
			"uid" => $touser['uid'],
			"username" => $touser['username'],
			"email" => $touser['email'],
			"lastactive" => $touser['lastactive'],
			"pmpopup" => $touser['pmpopup'],
			"pmnotify" => $touser['pmnotify'],
			"language" => $touser['language']
		);
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

		$this->verify_yesno_option($options, 'signature', 'yes');
		$this->verify_yesno_option($options, 'savecopy', 'yes');

		// Requesting a read receipt?
		if(isset($options['readreceipt']) && $options['readreceipt'] == "yes")
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
		
		// Verify all PM assets.
		$this->verify_subject();

		$this->verify_sender();

		$this->verify_recipient();
		
		$this->verify_message();

		$this->verify_options();

		$plugins->run_hooks_by_ref("datahandler_pm_validate", $this);

		// Choose the appropriate folder to save in.
		if($pm['saveasdraft'])
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
		global $db, $mybb, $plugins, $lang;

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
		$pm['pmid'] = intval($pm['pmid']);
		
		if(!$pm['icon'] || $pm['icon'] < 0)
		{
			$pm['icon'] = 0;
		}

		// Send email notification of new PM if it is enabled for the recipient
		$query = $db->query("SELECT dateline FROM ".TABLE_PREFIX."privatemessages WHERE uid='".$pm['recipient']['uid']."' AND folder='1' ORDER BY dateline DESC LIMIT 1");
		$lastpm = $db->fetch_array($query);
		if($pm['recipient']['pmnotify'] == "yes" && $pm['recipient']['lastactive'] > $lastpm['dateline'] && !$mybb->input['saveasdraft'])
		{
			if($pm['recipient']['language'] != "" && $lang->language_exists($touser['language']))
			{
				$uselang = $touser['language'];
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
				$emailsubject = $lang->emailsubject_newpm;
				$emailmessage = $lang->email_newpm;
			}
			else
			{
				$userlang = new MyLanguage;
				$userlang->set_path("./inc/languages");
				$userlang->set_language($uselang);
				$userlang->load("messages");
				$emailsubject = $userlang->emailsubject_newpm;
				$emailmessage = $userlang->email_newpm;
			}
			$emailmessage = sprintf($emailmessage, $pm['recipient']['username'], $pm['sender']['username'], $mybb->settings['bbname'], $mybb->settings['bburl']);
			$emailsubject = sprintf($emailsubject, $mybb->settings['bbname']);
			mymail($pm['recipient']['email'], $emailsubject, $emailmessage);
		}
		
		// Check if we're updating a draft or not.
		$query = $db->simple_select("privatemessages", "pmid", "folder='3' AND uid='{$pm['sender']['uid']}' AND pmid='{$pm['pmid']}'");
		$draftcheck = $db->fetch_array($query);

		// This PM was previously a draft - update it
		if($draftcheck['pmid'])
		{
			$updateddraft = array(
				'toid' => $pm['recipient']['uid'],
				'fromid' => $pm['sender']['uid'],
				'folder' => $pm['folder'],
				'subject' => $db->escape_string($pm['subject']),
				'icon' => intval($pm['icon']),
				'message' => $db->escape_string($pm['message']),
				'dateline' => time(),
				'status' => 0,
				'includesig' => $pm['options']['signature'],
				'smilieoff' => $pm['options']['disablesmilies'],
				'receipt' => intval($pm['options']['readreceipt']),
				'readtime' => 0
			);

			if($pm['saveasdraft'])
			{
				$updateddraft['uid'] = $pm['sender']['uid'];
			}
			else
			{
				$updateddraft['uid'] = $pm['recipient']['uid'];
			}
			$plugins->run_hooks_by_ref("datahandler_pm_insert_updatedraft", $this);
			$db->update_query(TABLE_PREFIX."privatemessages", $updateddraft, "pmid='{$pm['pmid']}' AND uid='{$pm['sender']['uid']}'");
		}
		else
		{
			$newpm = array(
				'uid' => $pm['recipient']['uid'],
				'toid' => $pm['recipient']['uid'],
				'fromid' => $pm['sender']['uid'],
				'folder' => $pm['folder'],
				'subject' => $db->escape_string($pm['subject']),
				'icon' => intval($pm['icon']),
				'message' => $db->escape_string($pm['message']),
				'dateline' => time(),
				'status' => 0,
				'includesig' => $pm['options']['signature'],
				'smilieoff' => $pm['options']['disablesmilies'],
				'receipt' => intval($pm['options']['readreceipt']),
				'readtime' => 0
			);

			if($pm['saveasdraft'])
			{
				$newpm['uid'] = $pm['sender']['uid'];
			}
			$plugins->run_hooks_by_ref("datahandler_pm_insert", $this);
			$db->insert_query(TABLE_PREFIX."privatemessages", $newpm);

			// Update private message count (total, new and unread) for recipient
			update_pm_count($pm['recipient']['uid'], 7, $pm['recipient']['lastactive']);
		}

		// Are we replying or forwarding an existing PM?
		if($pm['pmid'] && !$pm['saveasdraft'])
		{
			if($pm['do'] == "reply")
			{
				$sql_array = array(
					'status' => 3
				);
				$db->update_query(TABLE_PREFIX."privatemessages", $sql_array, "pmid={$pm['pmid']} AND uid={$pm['sender']['uid']}");
			}
			elseif($pm['do'] == "forward")
			{
				$sql_array = array(
					'status' => 4
				);
				$db->update_query(TABLE_PREFIX."privatemessages", $sql_array, "pmid={$pm['pmid']} AND uid={$pm['sender']['uid']}");
			}
		}

		// If we're saving a copy
		if($pm['options']['savecopy'] != "no" && !$pm['saveasdraft'])
		{
			$savedcopy = array(
				'uid' => $pm['sender']['uid'],
				'toid' => $pm['recipient']['uid'],
				'fromid' => $pm['sender']['uid'],
				'folder' => 2,
				'subject' => $db->escape_string($pm['subject']),
				'icon' => intval($pm['icon']),
				'message' => $db->escape_string($pm['message']),
				'dateline' => time(),
				'status' => 1,
				'includesig' => $pm['options']['signature'],
				'smilieoff' => $pm['options']['disablesmilies'],
				'receipt' => 0
			);
			$plugins->run_hooks_by_ref("datahandler_pm_insert_savedcopy", $this);
			$db->insert_query(TABLE_PREFIX."privatemessages", $savedcopy);

			// Because the sender saved a copy, update their total pm count
			update_pm_count($pm['sender']['uid'], 1);
		}

		// If the recipient has pm popup functionality enabled, update it to show the popup.

		if($pm['recipient']['pmpopup'] != "no" && !$pm['saveasdraft'])
		{
			$sql_array = array(
				"pmpopup" => "new"
			);
			$db->update_query(TABLE_PREFIX."users", $sql_array, "uid={$pm['recipient']['uid']}");
		}


		// Return back with appropriate data
		if($pm['saveasdraft'])
		{
			return array(
				"draftsaved" => 1
			);
		}
		else
		{
			return array(
				"messagesent" => 1
			);
		}
	}
}
?>