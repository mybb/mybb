<?php
// Board Name: SMF 1.0.8

class Convert_smf extends Converter {
	var $bbname = "SMF";
	var $modules = array(0 => array("name" => "Import SMF Users",
									  "function" => "import_users",
									  "dependancies" => ""),
						 1 => array("name" => "Import SMF Categories",
									  "function" => "import_categories",
									  "dependancies" => ""),
						 2 => array("name" => "Import SMF Forums",
									  "function" => "import_forums",
									  "dependancies" => ""),
						 3 => array("name" => "Import SMF Threads",
						 			  "function" => "import_threads",
									  "dependancies" => ""),				
						);

	function import_users()
	{
		global $mybb, $output, $session, $db;
		$module_id = 0;
		
		// Get number of users per screen from form
		if(isset($mybb->input['users_per_screen']))
		{
			$session['users_per_screen'] = intval($mybb->input['users_per_screen']);
		}
		
		if(empty($session['users_per_screen']))
		{
			$session['start_users'] = 0;
			echo "<p>Please select how many users to import at a time:</p>
<p><input type=\"text\" name=\"users_per_screen\" value=\"\" /></p>";
			$output->print_footer($module_id, 'module', 1);
		}
		else
		{
			// Get number of members
			if(!isset($session['total_members']))
			{
				$query = $this->olddb->simple_select("members", "COUNT(*) as count");
				$session['total_members'] = $this->olddb->fetch_field($query, 'count');				
			}

			// Get members
			$query = $this->olddb->simple_select("members", "*", "", array('limit_start' => $session['start_users'], 'limit' => $session['users_per_screen']));

			while($user = $this->olddb->fetch_array($query))
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
				$output->print_footer($module_id, 'module', 1);
			}
		}
	}
	
	function import_categories()
	{
		global $mybb, $output, $session, $db;
		$module_id = 1;

		// Get number of categories per screen from form
		if(isset($mybb->input['cats_per_screen']))
		{
			$session['cats_per_screen'] = intval($mybb->input['cats_per_screen']);
		}
		
		if(empty($session['cats_per_screen']))
		{
			$session['start_cats'] = 0;
			echo "<p>Please select how many categories to import at a time:</p>
<p><input type=\"text\" name=\"cats_per_screen\" value=\"\" /></p>";
			$output->print_footer($module_id, 'module', 1);
		}
		else
		{	
			// Get number of categories
			if(!isset($session['total_cats']))
			{
				$query = $this->olddb->simple_select("categories", "COUNT(*) as count");
				$session['total_cats'] = $this->olddb->fetch_field($query, 'count');				
			}
			
			$query = $this->olddb->simple_select("categories", "*", "", array('limit_start' => $session['start_cats'], 'limit' => $session['cats_per_screen']));

			while($cat = $this->olddb->fetch_array($query))
			{
				echo "Inserting category #{$cat['ID_CAT']}... ";
				
				// Values from SMF
				$insert_forum['importfid'] = (-1 * intval($cat['ID_CAT']));
				$insert_forum['name'] = $cat['name'];
				$insert_forum['disporder'] = $cat['catOrder'];
				
				// Default values
				$insert_forum['description'] = '';
				$insert_forum['linkto'] = '';
				$insert_forum['type'] = 'c';
				$insert_forum['pid'] = '0';
				$insert_forum['parentlist'] = '';
				$insert_forum['active'] = 'yes';
				$insert_forum['open'] = 'yes';
				$insert_forum['threads'] = 0;
				$insert_forum['posts'] = 0;
				$insert_forum['lastpost'] = 0;
				$insert_forum['lastposteruid'] = 0;
				$insert_forum['lastposttid'] = 0;
				$insert_forum['lastpostsubject'] = '';
				$insert_forum['allowhtml'] = 'no';
				$insert_forum['allowmycode'] = 'yes';
				$insert_forum['allowsmilies'] = 'yes';
				$insert_forum['allowimgcode'] = 'yes';
				$insert_forum['allowpicons'] = 'yes';
				$insert_forum['allowtratings'] = 'yes';
				$insert_forum['status'] = 1;
				$insert_forum['usepostcounts'] = 'yes';
				$insert_forum['password'] = '';
				$insert_forum['showinjump'] = 'yes';
				$insert_forum['modposts'] = 'no';
				$insert_forum['modthreads'] = 'no';
				$insert_forum['modattachments'] = 'no';
				$insert_forum['style'] = 0;
				$insert_forum['overridestyle'] = 'no';
				$insert_forum['rulestype'] = 0;
				$insert_forum['rules'] = '';
				$insert_forum['unapprovedthreads'] = 0;
				$insert_forum['unapprovedposts'] = 0;
				$insert_forum['defaultdatecut'] = 0;
				$insert_forum['defaultsortby'] = '';
				$insert_forum['defaultsortorder'] = '';
	
				$fid = $this->insert_forum($insert_forum);
				
				// Update parent list.
				$update_array = array('parentlist' => $fid);
				$db->update_query("forums", $update_array);
				
				echo "done.<br />\n";			
			}
			
			// If there are more categories to do, continue, or else, move onto next module
			if($session['total_cats'] > $session['start_cats'] + $session['cats_per_screen'])
			{
				$session['start_cats'] = $session['start_cats'] + $session['cats_per_screen'];
				$output->print_footer($module_id, 'module', 1);
			}
		}			
	}
	
	function import_forums()
	{
		global $mybb, $output, $session, $db;
		$module_id = 2;

		// Get number of forums per screen from form
		if(isset($mybb->input['forums_per_screen']))
		{
			$session['forums_per_screen'] = intval($mybb->input['forums_per_screen']);
		}
		
		if(empty($session['forums_per_screen']))
		{
			$session['start_forums'] = 0;
			echo "<p>Please select how many forums to import at a time:</p>
<p><input type=\"text\" name=\"forums_per_screen\" value=\"\" /></p>";
			$output->print_footer($module_id, 'module', 1);
		}
		else
		{	
			// Get number of forums
			if(!isset($session['total_forums']))
			{
				$query = $this->olddb->simple_select("boards", "COUNT(*) as count");
				$session['total_forums'] = $this->olddb->fetch_field($query, 'count');				
			}
			
			$query = $this->olddb->simple_select("boards", "*", "", array('limit_start' => $session['start_cats'], 'limit' => $session['cats_per_screen']));

			while($forum = $this->olddb->fetch_array($query))
			{
				echo "Inserting forum #{$forum['ID_BOARD']}... ";
				
				// Values from SMF
				$insert_forum['importfid'] = intval($forum['ID_BOARD']);
				$insert_forum['name'] = $forum['name'];
				$insert_forum['description'] = $forum['description'];
				$insert_forum['pid'] = $this->get_import_fid((-1) * $forum['ID_CAT']);
				$insert_forum['disporder'] = $forum['boardOrder'];
				$insert_forum['threads'] = $forum['numTopics'];
				$insert_forum['posts'] = $forum['numPosts'];
				$insert_forum['usepostcounts'] = int_to_yesno($forum['countPosts']);
				
				// Default values
				$insert_forum['linkto'] = '';
				$insert_forum['type'] = 'f';
				$insert_forum['parentlist'] = '';
				$insert_forum['active'] = 'yes';
				$insert_forum['open'] = 'yes';
				$insert_forum['lastpost'] = 0;
				$insert_forum['lastposteruid'] = 0;
				$insert_forum['lastposttid'] = 0;
				$insert_forum['lastpostsubject'] = '';
				$insert_forum['allowhtml'] = 'no';
				$insert_forum['allowmycode'] = 'yes';
				$insert_forum['allowsmilies'] = 'yes';
				$insert_forum['allowimgcode'] = 'yes';
				$insert_forum['allowpicons'] = 'yes';
				$insert_forum['allowtratings'] = 'yes';
				$insert_forum['status'] = 1;
				$insert_forum['password'] = '';
				$insert_forum['showinjump'] = 'yes';
				$insert_forum['modposts'] = 'no';
				$insert_forum['modthreads'] = 'no';
				$insert_forum['modattachments'] = 'no';
				$insert_forum['style'] = 0;
				$insert_forum['overridestyle'] = 'no';
				$insert_forum['rulestype'] = 0;
				$insert_forum['rules'] = '';
				$insert_forum['unapprovedthreads'] = 0;
				$insert_forum['unapprovedposts'] = 0;
				$insert_forum['defaultdatecut'] = 0;
				$insert_forum['defaultsortby'] = '';
				$insert_forum['defaultsortorder'] = '';
	
				$fid = $this->insert_forum($insert_forum);
				
				// Update parent list.
				$update_array = array('parentlist' => $insert_forum['pid'].','.$fid);
				$db->update_query("forums", $update_array);
				
				echo "done.<br />\n";			
			}
			
			// If there are more forums to do, continue, or else, move onto next module
			if($session['total_forums'] > $session['start_forums'] + $session['forums_per_screen'])
			{
				$session['start_forums'] = $session['start_forums'] + $session['forums_per_screen'];
				$output->print_footer($module_id, 'module', 1);
			}
		}			
	}

	function import_threads()
	{
		global $mybb, $output, $session, $db;
		$module_id = 3;

		// Get number of threads per screen from form
		if(isset($mybb->input['threads_per_screen']))
		{
			$session['threads_per_screen'] = intval($mybb->input['threads_per_screen']);
		}
		
		if(empty($session['threads_per_screen']))
		{
			$session['start_threads'] = 0;
			echo "<p>Please select how many threads to import at a time:</p>
<p><input type=\"text\" name=\"threads_per_screen\" value=\"\" /></p>";
			$output->print_footer($module_id, 'module', 1);
		}
		else
		{	
			// Get number of threads
			if(!isset($session['total_threads']))
			{
				$query = $this->olddb->simple_select("topics", "COUNT(*) as count");
				$session['total_threads'] = $this->olddb->fetch_field($query, 'count');				
			}
			
			$query = $this->olddb->simple_select("topics", "*", "", array('limit_start' => $session['start_threads'], 'limit' => $session['threads_per_screen']));

			while($thread = $this->olddb->fetch_array($query))
			{
				echo "Inserting thread #{$thread['ID_TOPIC']}... ";
				
				$insert_thread['importtid'] = $thread['ID_TOPIC'];
				$insert_thread['sticky'] = $thread['isSticky'];
				$insert_thread['fid'] = $this->get_import_fid($thread['ID_BOARD']);
				$insert_thread['firstpost'] = $thread['ID_FIRST_MSG'];				

				$first_post = $this->get_post($thread['ID_FIRST_MSG']);				
				$insert_thread['icon'] = $first_post['icon'];
				$insert_thread['dateline'] = $first_post['posterTime'];
				$insert_thread['subject'] = $first_post['subject'];
				
				$insert_thread['poll'] = $thread['ID_POLL'];
				$insert_thread['uid'] = $this->get_import_uid($thread['ID_MEMBER_STARTED']);
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
				
				$query1 = $this->olddb->simple_select("messages", "ID_MSG", "ID_TOPIC='{$thread['ID_TOPIC']}'");
				while($post = $this->olddb->fetch_array($query1))
				{
					$pids .= $comma.$post['ID_MSG'];
					$comma = ', ';
				}
				
				$query1 = $this->olddb->simple_select("attachments", "COUNT(*) as numattachments", "ID_MSG IN($pids)");
				$insert_thread['attachmentcount'] = $db->fetch_field($query1, 'numattachments');
				
				$last_post = $this->get_post($thread['ID_LAST_MSG']);
				$insert_thread['lastpost'] = $last_post['posterTime'];
				$insert_thread['lastposteruid'] = $this->get_import_uid($last_post['ID_MEMBER']);
				
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
				$output->print_footer($module_id, 'module', 1);
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
		$query = $this->olddb->simple_select("messages", "*", "ID_MSG='{$pid}'", array('limit' => 1));
		
		return $this->olddb->fetch_array($query);
	}
	
	/**
	 * Get a user from the SMF database
	 * @param int User ID
	 * @return array If the uid is 0, returns an array of posterName and memberName as Guest.  Otherwise returns the user
	 */
	function get_user($uid)
	{
		if($uid == 0)
		{
			return array(
				'posterName' => 'Guest',
				'memberName' => 'Guest'
			);
		}
		
		$query = $this->olddb->simple_select("members", "*", "ID_MEMBER='{$uid}'", array('limit' => 1));
		
		return $this->olddb->fetch_array($query);
	}
	
	/**
	 * Gets the time of the last post of a user from the SMF database
	 * @param int User ID
	 * @param int Last post time
	 */
	function get_last_post($uid)
	{
		$query = $this->olddb->simple_select("messages", "posterTime", "ID_MEMBER='{$uid}'", array('order_by' => 'posterTime', 'order_dir' => 'DESC', 'limit' => 1));
		return $this->olddb->fetch_field($query, "posterTime");
	}
	
	/**
	 * Convert a SMF group ID into a MyBB group ID
	 */
	function get_group_id($user, $row)
	{
		if(empty($user[$row]))
		{
			return 2; // Return regular registered user.
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
			// Deal with non-activated people
			if($user['is_activated'] != '1' && $row='ID_GROUP')
			{
				return 5;
			}
			
			$group .= $comma;
			switch($smfgroup)
			{
				case 1: // Administrator
					$group .= 4;
					break;
				case 2: // Super moderator
					$group .= 3;
					break;
				case 3: // Moderator
					$group .= 6;
					break;
				default: // The lot
					$group .= 2;
			}
			$comma = ',';
		}
		
		return $group;
	}

}


?>