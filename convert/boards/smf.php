<?php
// Board Name: SMF 1.0.8

class Convert_smf extends Converter {
	var $bbname = "SMF";
	var $modules = array("0" => array("name" => "Import SMF Users",
									  "function" => "import_users",
									  "dependancies" => ""),
						 "1" => array("name" => "Import SMF Threads",
						 			  "function" => "import_threads",
									  "dependancies" => ""),				
						);

	function import_users()
	{
		global $mybb, $output, $session, $db, $olddb;
		
		if(!isset($session['start_users']))
		{
			$session['start_users'] = 0;
			echo "<p>Please select how many users to import at a time:</p>
<p><input type=\"text\" name=\"users_per_screen\" value=\"\" /></p>";
			$output->print_footer(0, 'module', 1);
		}
		else
		{
			
			// Get number of users per screen from form
			if(isset($mybb->input['users_per_screen']))
			{
				$session['users_per_screen'] = intval($mybb->input['users_per_screen']);
			}
			
			// Get number of members
			if(!isset($session['total_members']))
			{
				$query = $olddb->simple_select("members", "COUNT(*) as count");
				$session['total_members'] = $olddb->fetch_field($query, 'count');				
			}

			// Get members
			$query = $olddb->simple_select("members", "*", "", array('limit_start' => $session['start_users'], 'limit' => $session['users_per_screen']));

			while($user = $olddb->fetch_array($query))
			{
				echo "Adding user #{$user['ID_MEMBER']}... ";
				$insert_user['usergroup'] = $this->get_group_id($user, 'ID_GROUP');
				$insert_user['additionalgroups'] = $this->get_group_id($user, 'additionalGroups');
				$insert_user['displaygroup'] = $insert_user['usergroup'];
				$insert_user['importuid'] = $user['ID_MEMBER'];
				$insert_user['username'] = $user['memberName'];
				$insert_user['email'] = $user['emailAddress'];
				$insert_user['regdate'] = $user['dateRegistered'];
				$insert_user['postnum'] = $user['posts'];
				$insert_user['lastactive'] = $user['lastLogin'];
				$insert_user['lastvisit'] = $user['lastLogin'];
				$insert_user['website'] = $user['websiteUrl'];
				//$user['avatardimensions']
				//$user['avatartype']
				$insert_user['lastpost'] = $this->get_last_post($user['ID_MEMBER']);
				$insert_user['birthday'] = date("n-j-Y", strtotime($user['birthdate']));
				$insert_user['icq'] = $user['ICQ'];
				$insert_user['aim'] = $user['AIM'];
				$insert_user['yahoo'] = $user['YIM'];
				$insert_user['msn'] = $user['MSN'];
				$insert_user['hideemail'] = int_to_yesno($user['hideEmail']);
				$insert_user['invisible'] = int_to_yesno($user['showOnline']);
				$insert_user['allownotices'] = "yes";
				$insert_user['emailnotify'] = "no";
				$insert_user['receivepms'] = "yes";
				$insert_user['pmpopup'] = "yes";
				$insert_user['pmnotify'] = int_to_yesno($user['pm_email_notify']);
				$insert_user['remember'] = "yes";
				$insert_user['showsigs'] = "yes";
				$insert_user['showavatars'] = "yes";
				$insert_user['showquickreply'] = "yes";
				$insert_user['ppp'] = "0";
				$insert_user['tpp'] = "0";
				$insert_user['daysprune'] = "0";
				$insert_user['timeformat'] = $user['timeFormat'];
				$insert_user['timezone'] = $user['timeOffset'];
				$insert_user['dst'] = "no";
				$insert_user['buddylist'] = $user['buddy_list'];
				$insert_user['ignorelist'] = $user['pm_ignore_list'];
				$insert_user['style'] = $user['ID_THEME'];
				$insert_user['away'] = "no";
				$insert_user['awaydate'] = "0";
				$insert_user['returndate'] = "0";
				$insert_user['referrer'] = "0";
				$insert_user['reputation'] = "0";
				$insert_user['regip'] = $user['memberIP'];
				$insert_user['timeonline'] = $user['totalTimeLoggedIn'];
				$insert_user['totalpms'] = $user['instantMessages'];
				$insert_user['unreadpms'] = $user['unreadMessages'];
				$insert_user['pmfolders'] = '1**Inbox$%%$2**Sent Items$%%$3**Drafts$%%$4**Trash Can';		
				$this->insert_user($insert_user);
				echo "done.<br />\n";
			}

			// If there are more users to do, continue, or else, move onto next module
			if($session['total_members'] > $session['start_users'] + $session['users_per_screen'])
			{
				$session['start_users'] = $session['start_users'] + $session['users_per_screen'];
				$output->print_footer(0, 'module', 1);
			}
		}
	}

	function import_threads()
	{
		global $mybb, $output, $session, $db, $olddb;
		
		if(!isset($session['start_threads']))
		{
			$session['start_threads'] = 0;
			echo "<p>Please select how many threads to import at a time:</p>
<p><input type=\"text\" name=\"threads_per_screen\" value=\"\" /></p>";
			$output->print_footer(1, 'module', 1);
		}
		else
		{	
			// Get number of users per screen from form
			if(isset($mybb->input['threads_per_screen']))
			{
				$session['threads_per_screen'] = intval($mybb->input['threads_per_screen']);
			}
			
			// Get number of threads
			if(!isset($session['total_threads']))
			{
				$query = $olddb->simple_select("topics", "COUNT(*) as count");
				$session['total_threads'] = $olddb->fetch_field($query, 'count');				
			}
			
			$query = $olddb->simple_select("topics", "*", "", array('limit_start' => $session['start_threads'], 'limit' => $session['threads_per_screen']));

			while($thread = $olddb->fetch_array($query))
			{
				echo "Inserting thread #{$thread['ID_TOPIC']}... ";
				
				$insert_thread['importtid'] = $thread['ID_TOPIC'];
				$insert_thread['sticky'] = $thread['isSticky'];
				$insert_thread['fid'] = $thread['ID_BOARD'];
				$insert_thread['firstpost'] = $thread['ID_FIRST_MSG'];				

				$first_post = $this->get_post($thread['ID_FIRST_MSG']);				
				$insert_thread['icon'] = $first_post['icon'];
				$insert_thread['dateline'] = $first_post['posterTime'];
				$insert_thread['subject'] = $first_post['subject'];
				
				$insert_thread['poll'] = $thread['ID_POLL'];
				$insert_thread['uid'] = $thread['ID_MEMBER_STARTED'];
				$insert_thread['views'] = $thread['numViews'];
				$insert_thread['replies'] = $thread['numReplies'];
				$insert_thread['closed'] = $thread['locked'];
				$insert_thread['totalratings'] = '0';
				$insert_thread['notes'] = '';
				$insert_thread['visible'] = '1';
				$insert_thread['unapprovedposts'] = '0';
				$insert_thread['numratings'] = '0';
				
				$pids = '';
				$comma = '';
				$count = 0;
				
				$query1 = $olddb->simple_select("messages", "ID_MSG", "ID_TOPIC='{$thread['ID_TOPIC']}'");
				while($post = $olddb->fetch_array($query1))
				{
					$pids .= $comma.$post['ID_MSG'];
					$comma = ', ';
				}
				
				$query1 = $olddb->simple_select("attachments", "COUNT(*) as numattachments", "ID_MSG IN($pids)");
				$insert_thread['attachmentcount'] = $db->fetch_field($query1, 'numattachments');
				
				$last_post = $this->get_post($thread['ID_LAST_MSG']);
				$insert_thread['lastpost'] = $last_post['posterTime'];
				$insert_thread['lastposteruid'] = $last_post['ID_MEMBER'];
				
				$last_post_member = $this->get_user($last_post['ID_MEMBER']);
				$insert_thread['lastposter'] = $last_post_member['posterName'];
				
				
				$member_started = $this->get_user($thread['ID_MEMBER_STARTED']);
				$insert_thread['username'] = $member_started['memberName'];
				$this->insert_thread($insert_thread);
				echo "done.<br />\n";			
			}
			
			// If there are more threads to do, continue, or else, move onto next module
			if($session['total_threads'] > $session['start_threads'] + $session['threads_per_screen'])
			{
				$session['start_threads'] = $session['start_threads'] + $session['threads_per_screen'];
				$output->print_footer(1, 'module', 1);
			}
		}			
	}
	
	/**
	 * Get a post from the SMF database
	 * @param int Post ID
	 * @return array The post
	 */
	function get_post($pid)
	{
		global $olddb;
		
		$query = $olddb->simple_select("messages", "*", "ID_MSG='{$pid}'", array('limit' => 1));
		
		return $olddb->fetch_array($query);
	}
	
	/**
	 * Get a user from the SMF database
	 * @param int User ID
	 * @return array If the uid is 0, returns an array of posterName and memberName as Guest.  Otherwise returns the user
	 */
	function get_user($uid)
	{
		global $olddb;
		
		if($uid == 0)
		{
			return array(
				'posterName' => 'Guest',
				'memberName' => 'Guest'
			);
		}
		
		$query = $olddb->simple_select("members", "*", "ID_MEMBER='{$uid}'", array('limit' => 1));
		
		return $olddb->fetch_array($query);
	}
	
	/**
	 * Gets the time of the last post of a user from the SMF database
	 * @param int User ID
	 * @param int Last post time
	 */
	function get_last_post($uid)
	{
		global $olddb;
		
		$query = $olddb->simple_select("messages", "posterTime", "ID_MEMBER='{$uid}'", array('order_by' => 'posterTime', 'order_dir' => 'DESC', 'limit' => 1));
		return $olddb->fetch_field($query, "posterTime");
	}
	
	/**
	 * Convert a SMF group ID into a MyBB group ID
	 */
	function get_group_id($user, $row)
	{
		global $olddb;
		
		if(empty($user[$row]))
		{
			return "";
		}
		
		if(!is_numeric($user[$row]))
		{
			$groups = explode(',', $user[$row]);
		}
		else
		{
			$groups = array($user[$row]);
		}
		
		
		$comma = $group = '';
		foreach($groups as $key => $smfgroup)
		{
			$group .= $comma;
			if($smfgroup == 1)
			{
				$group .= 4;
			}
			elseif($smfgroup == 2)
			{
				$group .= 3;
			}
			elseif($smfgroup == 3)
			{
				$group .= 6;
			}
			elseif($user['is_activated'] != '1' && $row='ID_GROUP')
			{
				return 5;
			}
			$comma = ',';
		}
		
		return $group;
	}
}


?>