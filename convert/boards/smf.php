<?php
// Board Name: SMF

class convert_smf {
	var $bbname = "SMF";
	var $modules = array("0" => array("name" => "Import SMF Users",
									  "function" => "import_users",
									  "dependancies" => ""),
						 "1" => array("name" => "Import SMF Threads",
						 			  "function" => "import_threads",
									  "dependances" => ""),				
						);

	function import_users()
	{
		global $mybb, $output, $session, $db;
		
		$mybb->input['perusers'] = intval($mybb->input['perusers']);
		
		if(empty($mybb->input['perusers']))
		{
			echo "<p>Please select how many users to import at a time:</p>
<p><input type=\"text\" name=\"perusers\" value=\"\" /></p>";
			$output->print_footer(0, 'module', 1);
		}
		else
		{
			$session['limit_start'] = $mybb->input['perusers'];
			
			if($mybb->input['perusers'])
			{
				$limit_start = 0;
			}
			else
			{
				$limit_start = $session['limit_start'];
			}
			
			$old_table_prefix = $db->table_prefix;
			
			$db->set_table_prefix($session['tableprefix']);
			
			$query = $db->simple_select("members", "*", "", array('limit_start' => $limit_start, 'limit' => $session['limit_start']));

			while($user = $db->fetch_array($query))
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
				
				$db->set_table_prefix($old_table_prefix);				
				insert_user($insert_user);				
				$db->set_table_prefix($session['tableprefix']);
				
				echo "done.<br />\n";
			}
			
			$db->set_table_prefix($old_table_prefix);
			
			if($db->num_rows($query) > $session['limit_start'])
			{
				$session['limit_start'] = $mybb->input['perusers']+20;
				$output->print_footer(0, 'module', 1);
			}
			else
			{
				$output->print_footer($session['module']+1, 'module', 1);
			}
		}
	}
	
	function import_threads()
	{
		global $mybb, $output, $session, $db;
		
		$mybb->input['perthreads'] = intval($mybb->input['perthreads']);
		
		if(empty($mybb->input['perthreads']))
		{
			echo "<p>Please select how many threads to import at a time:</p>
<p><input type=\"text\" name=\"perthreads\" value=\"\" /></p>";
			$output->print_footer(1, 'module', 1);
		}
		else
		{
			$session['limit_start'] = $mybb->input['perthreads'];
			
			if($mybb->input['perthreads'])
			{
				$limit_start = 0;
			}
			else
			{
				$limit_start = $session['limit_start'];
			}
			
			$old_table_prefix = $db->table_prefix;
			
			$db->set_table_prefix($session['tableprefix']);
			
			$query = $db->simple_select("topics", "*", "", array('limit_start' => $limit_start, 'limit' => $session['limit_start']));

			while($thread = $db->fetch_array($query))
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
				
				$query = $db->simple_select("messages", "ID_MSG", "ID_TOPIC='{$thread['ID_TOPIC']}'");
				$rows = $db->num_rows($query);
				while($post = $db->fetch_array($query))
				{
					++$count;
					$pids .= $post['ID_MSG'].$comma;
					if($count < $rows)
					{
						$comma = ', ';
					}
				}
				
				$query = $db->simple_select("attachments", "COUNT(*) as numattachments", "ID_MSG IN($pids)");
				$insert_thread['attachmentcount'] = $db->fetch_field($query, 'numattachments');
				
				$last_post = $this->get_post($thread['ID_LAST_MSG']);
				$insert_thread['lastpost'] = $last_post['posterTime'];
				$insert_thread['lastposteruid'] = $last_post['ID_MEMBER'];
				
				$last_post_member = $this->get_user($last_post['ID_MEMBER']);
				$insert_thread['lastposter'] = $last_post_member['posterName'];
				
				
				$member_started = $this->get_user($thread['ID_MEMBER_STARTED']);
				$insert_thread['username'] = $member_started['memberName'];
				
				$db->set_table_prefix($old_table_prefix);		
				insert_thread($insert_thread);	
				$db->set_table_prefix($session['tableprefix']);				
			}
			
			$db->set_table_prefix($old_table_prefix);
			
			if($db->num_rows($query) > $session['limit_start'])
			{
				$session['limit_start'] = $mybb->input['perthreads']+20;
				$output->print_footer(1, 'module', 1);
			}
			else
			{
				$output->print_footer($session['module']+1, 'module', 1);
			}
		}			
	}
	
	function get_post($pid)
	{
		global $db;
		
		$query = $db->simple_select("messages", "*", "ID_MSG='{$pid}'", array('limit' => 1));
		
		return $db->fetch_array($query);
	}
	
	function get_user($uid)
	{
		global $db;
		
		if($uid == 0)
		{
			return array(
				'posterName' => 'Guest',
				'memberName' => 'Guest'
			);
		}
		
		$query = $db->simple_select("members", "*", "ID_MEMBER='{$uid}'", array('limit' => 1));
		
		return $db->fetch_array($query);
	}
	
	function get_last_post($uid)
	{
		global $db;
		
		$query = $db->simple_select("messages", "posterTime", "ID_MEMBER='{$uid}'", array('order_by' => 'posterTime', 'order_dir' => 'DESC', 'limit' => 1));
		return $db->fetch_field($query, "posterTime");
	}
	
	function get_group_id($user, $row)
	{
		global $db;
		
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
		
		
		$comma = '';
		foreach($groups as $key => $group)
		{
			if($group == 1)
			{
				$group .= 4;
			}
			elseif($group == 2)
			{
				$group .= 3;
			}
			elseif($group == 3)
			{
				$group .= 6;
			}
			elseif($user['is_activated'] != '1' && $row='ID_GROUP')
			{
				return 5;
			}
			$group .= $comma;
			$comma = ',';
		}
		
		return $group;
	}
}


?>