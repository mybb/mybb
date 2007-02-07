<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
 *
 * $Id: vbulletin3.php 2656 2007-01-15 06:54:08Z Tikitiki $
 */
 
// Board Name: vBulletin 2.3

class Convert_vbulletin2 extends Converter {

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "vBulletin 2.3";
	
	/**
	 * Array of all of the modules
	 *
	 * @var array
	 */
	var $modules = array("db_configuration" => array("name" => "Database Configuration",
									  "dependencies" => ""),
						 "import_users" => array("name" => "Import vBulletin 2 Users",
									  "dependencies" => "db_configuration"),
						 "import_usergroups" => array("name" => "Import vBulletin 2 Usergroups",
									  "dependencies" => "db_configuration,import_users"),
						 "import_forums" => array("name" => "Import vBulletin 2 Forums",
									  "dependencies" => "db_configuration,import_users"),
						 "import_forumperms" => array("name" => "Import vBulletin 2 Forum Permissions",
									  "dependencies" => "db_configuration,import_forums"),
						 "import_threads" => array("name" => "Import vBulletin 2 Threads",
									  "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Import vBulletin 2 Polls",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Import vBulletin 2 Poll Votes",
									  "dependencies" => "db_configuration,import_polls"),
						 "import_icons" => array("name" => "Import vBulletin 2 Icons",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_posts" => array("name" => "Import vBulletin 2 Posts",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_moderators" => array("name" => "Import vBulletin 2 Moderators",
									  "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_privatemessages" => array("name" => "Import vBulletin 2 Private Messages",
						 			  "dependencies" => "db_configuration,import_users"),
						 "import_smilies" => array("name" => "Import vBulletin 2 Smilies",
									  "dependencies" => "db_configuration"),
						 "import_settings" => array("name" => "Import vBulletin 2 Settings",
									  "dependencies" => "db_configuration"),
						 "import_events" => array("name" => "Import vBulletin 2 Calendar Events",
									  "dependencies" => "db_configuration,import_users"),
						 "import_attachments" => array("name" => "Import vBulletin 2 Attachments",
									  "dependencies" => "db_configuration,import_posts"),
						);

	function vbulletin_db_connect()
	{
		global $import_session;

		// TEMPORARY
		if($import_session['old_db_engine'] != "mysql" && $import_session['old_db_engine'] != "mysqli")
		{
			require_once MYBB_ROOT."/inc/db_{$import_session['old_db_engine']}.php";
		}
		$this->old_db = new databaseEngine;

		$this->old_db->connect($import_session['old_db_host'], $import_session['old_db_user'], $import_session['old_db_pass'], 0, true);
		$this->old_db->select_db($import_session['old_db_name']);
		$this->old_db->set_table_prefix($import_session['old_tbl_prefix']);
		
		define('VB_TABLE_PREFIX', $import_session['old_tbl_prefix']);
	}

	function db_configuration()
	{
		global $mybb, $output, $import_session, $db, $dboptions, $dbengines, $dbhost, $dbuser, $dbname, $tableprefix;

		// Just posted back to this form?
		if($mybb->input['dbengine'])
		{
			if(!file_exists(MYBB_ROOT."/inc/db_{$mybb->input['dbengine']}.php"))
			{
				$errors[] = 'You have selected an invalid database engine. Please make your selection from the list below.';
			}
			else
			{
				// Attempt to connect to the db
				// TEMPORARY
				if($mybb->input['dbengine'] != "mysql" && $mybb->input['dbengine'] != "mysqli")
				{
					require_once MYBB_ROOT."/inc/db_{$mybb->input['dbengine']}.php";
				}
				$this->old_db = new databaseEngine;
				$this->old_db->error_reporting = 0;

				$connection = $this->old_db->connect($mybb->input['dbhost'], $mybb->input['dbuser'], $mybb->input['dbpass'], 0, true);
				if(!$connection)
				{
					$errors[]  = "Could not connect to the database server at '{$mybb->input['dbhost']} with the supplied username and password. Are you sure the hostname and user details are correct?";
				}

				// Select the database
				$dbselect = $this->old_db->select_db($mybb->input['dbname']);
				if(!$dbselect)
				{
					$errors[] = "Could not select the database '{$mybb->input['dbname']}'. Are you sure it exists and the specified username and password have access to it?";
				}

				// Need to check if vB is actually installed here
				$this->old_db->set_table_prefix($mybb->input['tableprefix']);
				if(!$this->old_db->table_exists("user"))
				{
					$errors[] = "The vBulletin table '{$mybb->input['tableprefix']}user' could not be found in database '{$mybb->input['dbname']}'.  Please ensure vB exists at this database and with this table prefix.";
				}

				// No errors? Save import DB info and then return finished
				if(!is_array($errors))
				{
					$import_session['old_db_engine'] = $mybb->input['dbengine'];
					$import_session['old_db_host'] = $mybb->input['dbhost'];
					$import_session['old_db_user'] = $mybb->input['dbuser'];
					$import_session['old_db_pass'] = $mybb->input['dbpass'];
					$import_session['old_db_name'] = $mybb->input['dbname'];
					$import_session['old_tbl_prefix'] = $mybb->input['tableprefix'];
					
					// Create temporary import data fields
					create_import_fields();
					
					return "finished";
				}
			}
		}

		$output->print_header("vBulletin 2 Database Configuration");

		// Check for errors
		if(is_array($errors))
		{
			$error_list = error_list($errors);
			echo "<div class=\"error\">
			      <h3>Error</h3>
				  <p>There seems to be one or more errors with the database configuration information that you supplied:</p>
				  {$error_list}
				  <p>Once the above are corrected, continue with the conversion.</p>
				  </div>";
			$dbhost = $mybb->input['dbhost'];
			$dbuser = $mybb->input['dbuser'];
			$dbname = $mybb->input['dbname'];
			$tableprefix = $mybb->input['tableprefix'];
		}
		else
		{
			echo "<p>Please enter the database details for your current installation of vBulletin 2.</p>";
			$dbhost = 'localhost';
			$tableprefix = '';
			$dbuser = '';
			$dbname = '';
		}

		if(function_exists('mysqli_connect'))
		{
			$dboptions['mysqli'] = 'MySQL Improved';
		}
		
		if(function_exists('mysql_connect'))
		{
			$dboptions['mysql'] = 'MySQL';
		}

		foreach($dboptions as $dbfile => $dbtype)
		{
			$dbengines .= "<option value=\"{$dbfile}\">{$dbtype}</option>";
		}

		$output->print_database_details_table("vBulletin 2");

		$output->print_footer();
	}
	
	function import_usergroups()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();

		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("usergroup", "COUNT(*) as count", "usergroupid > 8");
			$import_session['total_usergroups'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_usergroups'])
		{
			// If there are more usergroups to do, continue, or else, move onto next module
			if($import_session['total_usergroups'] - $import_session['start_usergroups'] <= 0)
			{
				$import_session['disabled'][] = 'import_usergroups';
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of posts per screen from form
		if(isset($mybb->input['usergroups_per_screen']))
		{
			$import_session['usergroups_per_screen'] = intval($mybb->input['usergroups_per_screen']);
		}
		
		if(empty($import_session['usergroups_per_screen']))
		{
			$import_session['start_usergroups'] = 0;
			echo "<p>Please select how many usergroups to import at a time:</p>
<p><input type=\"text\" name=\"usergroups_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_usergroups']-$import_session['start_usergroups'])." usergroups left to import and ".round((($import_session['total_usergroups']-$import_session['start_usergroups'])/$import_session['usergroups_per_screen']))." pages left at a rate of {$import_session['usergroups_per_screen']} per page.<br /><br />";
			
			// Get only non-staff groups.
			$query = $this->old_db->simple_select("usergroup", "*", "usergroupid > 8", array('limit_start' => $import_session['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
			while($group = $this->old_db->fetch_array($query))
			{
				echo "Inserting group #{$group['usergroupid']} as a custom usergroup...";
				
				// vBulletin 2 values
				$insert_group['import_gid'] = $group['usergroupid'];				
				$insert_group['title'] = $group['title'];
				$insert_group['canview'] = int_to_yesno($group['canview']);
				$insert_group['canviewthreads'] = int_to_yesno($group['canviewothers']);
				$insert_group['candlattachments'] = int_to_yesno($group['cangetattachment']);
				$insert_group['canpostthreads'] = int_to_yesno($group['canpostnew']);
				$insert_group['canpostreplys'] = int_to_yesno($group['canreplyothers']);
				$insert_group['canratethreads'] = int_to_yesno($group['canthreadrate']);
				$insert_group['caneditposts'] = int_to_yesno($group['caneditpost']);
				$insert_group['candeleteposts'] = int_to_yesno($group['candeletepost']);
				$insert_group['candeletethreads'] = int_to_yesno($group['candeletethread']);
				$insert_group['canpostpolls'] = int_to_yesno($group['canpostpoll']);
				$insert_group['canvotepolls'] = int_to_yesno($group['canvote']);
				$insert_group['canusepms'] = int_to_yesno($group['canusepm']);
				$insert_group['cantrackpms'] = int_to_yesno($group['cantrackpm']);
				$insert_group['candenypmreceipts'] = int_to_yesno($group['candenypmreceipts']);
				$insert_group['cansendemail'] = int_to_yesno($group['canemail']);
				$insert_group['canviewmemberlist'] = int_to_yesno($group['canviewmembers']);
				$insert_group['canviewonlineips'] = int_to_yesno($group['canwhosonlineip']);
				$insert_group['cancp'] = int_to_yesno($group['cancontrolpanel']);
				$insert_group['issupermod'] = int_to_yesno($group['ismoderator']);
				$insert_group['cansearch'] = int_to_yesno($group['cansearch']);
				$insert_group['showforumteam'] = int_to_yesno($group['showgroup']);
				$insert_group['canaddpublicevents'] = int_to_yesno($group['canpublicevent']);
				$insert_group['canviewonline'] = int_to_yesno($group['canwhosonline']);				
				$insert_group['maxpmrecipients'] = $group['maxbuddypm'];
				
				
				// Default values
				$insert_group['type'] = 2;
				$insert_group['namestyle'] = '{username}';
				$insert_group['stars'] = 0;
				$insert_group['starimage'] = 'images/star.gif';
				$insert_group['image'] = '';
				$insert_group['disporder'] = 0;
				$insert_group['isbannedgroup'] = 'no';
				$insert_group['description'] = '';
				$insert_group['pmquota'] = '';
				$insert_group['canviewprofiles'] = 'yes';
				$insert_group['attachquota'] = '';
				$insert_group['canpostattachments'] = 'yes';				
				$insert_group['caneditattachments'] = 'yes';				
				$insert_group['cansendpms'] = 'yes';				
				$insert_group['canviewcalendar'] = 'yes'; 				
				$insert_group['canaddprivateevents'] = 'yes';				
				$insert_group['canviewwolinvis'] = 'no';				
				$insert_group['canusercp'] = 'yes';
				$insert_group['canuploadavatars'] = 'yes';
				$insert_group['canratemembers'] = 'yes';
				$insert_group['canchangename'] = 'no';				
				$insert_group['usereputationsystem'] = 'yes';
				$insert_group['cangivereputations'] = 'yes';
				$insert_group['reputationpower'] = '1';
				$insert_group['maxreputationsday'] = '5';
				$insert_group['candisplaygroup'] = 'yes';
				$insert_group['cancustomtitle'] = 'yes';

				$gid = $this->insert_usergroup($insert_group);
				
				// Restore connections
				$db->update_query("users", array('usergroup' => $gid), "import_usergroup = '{$group['usergroupid']}' OR import_displaygroup = '{$group['usergroupid']}'");
				
				$this->import_gids = null; // Force cache refresh
				
				echo "done.<br />\n";	
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no usergroups to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_usergroups'] += $import_session['usergroups_per_screen'];
		$output->print_footer();
	}
	
	function import_users()
	{
		global $mybb, $output, $import_session, $db;
		
		$this->vbulletin_db_connect();
		
		// Get number of members
		if(!isset($import_session['total_members']))
		{
			$query = $this->old_db->simple_select("user", "COUNT(*) as count");
			$import_session['total_members'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_users'])
		{
			// If there are more users to do, continue, or else, move onto next module
			if($import_session['total_members'] - $import_session['start_users'] <= 0)
			{
				$import_session['disabled'][] = 'import_users';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);
		
		// Get number of users per screen from form
		if(isset($mybb->input['users_per_screen']))
		{
			$import_session['users_per_screen'] = intval($mybb->input['users_per_screen']);
		}
		
		if(empty($import_session['users_per_screen']))
		{
			$import_session['start_users'] = 0;
			echo "<p>Please select how many users to import at a time:</p>
<p><input type=\"text\" name=\"users_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_members']-$import_session['start_users'])." users left to import and ".round((($import_session['total_members']-$import_session['start_users'])/$import_session['users_per_screen']))." pages left at a rate of {$import_session['users_per_screen']} per page.<br /><br />";
			
			// Count the total number of users so we can generate a unique id if we have a duplicate user
			$query = $db->simple_select("users", "COUNT(*) as totalusers");
			$total_users = $db->fetch_field($query, "totalusers");
			
			// Get members
			$query = $this->old_db->simple_select("user", "*", "", array('limit_start' => $import_session['start_users'], 'limit' => $import_session['users_per_screen']));

			while($user = $this->old_db->fetch_array($query))
			{
				++$total_users;
				
				// Check for duplicate users
				$query1 = $db->simple_select("users", "username,email,uid", "LOWER(username)='".$db->escape_string(my_strtolower($user['username']))."'");
				$duplicate_user = $db->fetch_array($query1);
				if($duplicate_user['username'] && my_strtolower($user['email']) == my_strtolower($duplicate_user['email']))
				{
					echo "Merging user #{$user['userid']} with user #{$duplicate_user['uid']}... ";
					$db->update_query("users", array('import_uid' => $user['userid']), "uid = '{$duplicate_user['uid']}'");
					echo "done.<br />";
					
					continue;
				}
				else if($duplicate_user['username'])
				{				
					$user['username'] = $duplicate_user['username']."_vb2_import".$total_users;
				}
				
				echo "Adding user #{$user['userid']}... ";
						
				// vBulletin 2 values
				$insert_user['usergroup'] = $this->get_group_id($user['usergroupid'], true);
				$insert_user['additionalgroups'] = str_replace($insert_user['usergroup'], '', $this->get_group_id($user['usergroupid']));
				$insert_user['displaygroup'] = $this->get_group_id($user['usergroupid'], true);
				$insert_user['import_usergroup'] = $this->get_group_id($user['usergroupid'], true, true);
				$insert_user['import_additionalgroups'] = $this->get_group_id($user['usergroupid'], false, true);
				$insert_user['import_displaygroup'] = $user['displaygroupid'];
				$insert_user['import_uid'] = $user['userid'];
				$insert_user['username'] = $user['username'];
				$insert_user['email'] = $user['email'];
				$insert_user['regdate'] = $user['joindate'];
				$insert_user['postnum'] = $user['posts'];
				$insert_user['lastactive'] = $user['lastactivity'];
				$insert_user['lastvisit'] = $user['lastvisit'];
				$insert_user['website'] = $user['homepage'];
				$avatar = $this->get_avatar($user['avatarid']);
				$insert_user['avatardimensions'] = ''; // to do
				$insert_user['avatar'] = $avatar['avatarpath'];
				$insert_user['lastpost'] = $user['lastpost'];
				$insert_user['birthday'] = $user['birthday'];
				$insert_user['icq'] = $user['icq'];
				$insert_user['aim'] = $user['aim'];
				$insert_user['yahoo'] = $user['yahoo'];
				$insert_user['msn'] = '';
				if($avatar['avatar'] == '')
				{
					$user['avatartype'] = 0;
				}
				$insert_user['timezone'] = '';						
				$insert_user['style'] = $user['styleid'];
				$insert_user['referrer'] = $user['referrerid'];				
				$insert_user['regip'] = $user['ipaddress'];				
				$insert_user['totalpms'] = $this->get_private_messages($user['userid']);
				$insert_user['unreadpms'] = $this->get_private_messages($user['userid'], true);
				
				// Default values
				$insert_user['hideemail'] = 'yes';
				$insert_user['invisible'] = 'no';
				$insert_user['allownotices'] = 'yes';
				$insert_user['emailnotify'] = 'yes';
				$insert_user['receivepms'] = 'yes';
				$insert_user['pmpopup'] = 'yes';
				$insert_user['pmnotify'] = 'yes';
				$insert_user['remember'] = "yes";
				$insert_user['showsigs'] = 'yes';
				$insert_user['showavatars'] = 'yes';
				$insert_user['showquickreply'] = "yes";
				$insert_user['ppp'] = "0";
				$insert_user['tpp'] = "0";
				$insert_user['daysprune'] = "0";
				$insert_user['timeformat'] = 'd-m-Y';
				$insert_user['dst'] = 'no';
				$insert_user['buddylist'] = "";
				$insert_user['ignorelist'] = "";
				$insert_user['away'] = "no";
				$insert_user['awaydate'] = "0";
				$insert_user['returndate'] = "0";
				$insert_user['reputation'] = "0";
				$insert_user['timeonline'] = "0";
				$insert_user['pmfolders'] = '1**Inbox$%%$2**Sent Items$%%$3**Drafts$%%$4**Trash Can';	
				$insert_user['avatartype'] = '2';
				
				$this->insert_user($insert_user);
				
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no users to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_users'] += $import_session['users_per_screen'];
		$output->print_footer();
	}
	
	function import_forums()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();

		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("forum", "COUNT(*) as count");
			$import_session['total_forums'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_forums'])
		{
			// If there are more forums to do, continue, or else, move onto next module
			if($import_session['total_forums'] - $import_session['start_forums'] <= 0)
			{
				$import_session['disabled'][] = 'import_forums';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of forums per screen from form
		if(isset($mybb->input['forums_per_screen']))
		{
			$import_session['forums_per_screen'] = intval($mybb->input['forums_per_screen']);
		}
		
		if(empty($import_session['forums_per_screen']))
		{
			$import_session['start_forums'] = 0;
			echo "<p>Please select how many forums to import at a time:</p>
<p><input type=\"text\" name=\"forums_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_forums']-$import_session['start_forums'])." forums left to import and ".round((($import_session['total_forums']-$import_session['start_forums'])/$import_session['forums_per_screen']))." pages left at a rate of {$import_session['forums_per_screen']} per page.<br /><br />";
			
			$query = $this->old_db->simple_select("forum", "*", "", array('limit_start' => $import_session['start_forums'], 'limit' => $import_session['forums_per_screen']));
			while($forum = $this->old_db->fetch_array($query))
			{
				echo "Inserting forum #{$forum['forumid']}... ";
				
				// vBulletin 2 values
				$insert_forum['import_fid'] = $forum['forumid'];
				$insert_forum['name'] = $forum['title'];
				$insert_forum['description'] = $forum['description'];				
				$insert_forum['disporder'] = $forum['displayorder'];
				$insert_forum['threads'] = $forum['threadcount'];
				$insert_forum['posts'] = $forum['replycount'];
				$insert_forum['style'] = $forum['styleid'];
				$insert_forum['password'] = '';
				$insert_forum['defaultsortby'] = '';
				$insert_forum['defaultsortorder'] = '';	
				$insert_forum['unapprovedthreads'] = $this->get_invisible_threads();
				$insert_forum['unapprovedposts'] = $this->get_invisible_posts();			
				
				// We have a category
				if($forum['parentid'] == '-1')
				{
					$insert_forum['type'] = 'c';
					$insert_forum['import_fid'] = (-1 * $forum['forumid']);
					$insert_forum['lastpost'] = 0;
					$insert_forum['lastposteruid'] = 0;
					$insert_forum['lastposttid'] = 0;
					$insert_forum['lastpostsubject'] = '';
				}
				// We have a forum
				else
				{
					$insert_forum['linkto'] = '';
					$insert_forum['type'] = 'f';
					$insert_forum['pid'] = $this->get_import_fid((-1) * $forum['parentid']);
					$insert_forum['lastpost'] = $forum['lastpost'];
					$thread = $this->get_lastpost($forum['forumid']);
					$insert_forum['lastposteruid'] = $this->get_import_uid($thread['userid']);
					$insert_forum['lastposttid'] = ((-1) * $thread['threadid']);
					$insert_forum['lastpostsubject'] = $thread['title'];
					$insert_forum['lastposter'] = $this->get_import_username($thread['userid']);
				}
				
				
				// Default values
				$insert_forum['parentlist'] = '';
				$insert_forum['open'] = 'yes';
				$insert_forum['rules'] = '';
				$insert_forum['rulestype'] = 1;
				$insert_forum['active'] = int_to_yesno($forum['active']);
				$insert_forum['allowhtml'] = int_to_yesno($forum['allowhtml']);
				$insert_forum['allowmycode'] = int_to_yesno($forum['allowbbcode']);
				$insert_forum['allowsmilies'] = int_to_yesno($forum['allowsmilies']);
				$insert_forum['allowimgcode'] = int_to_yesno($forum['allowimages']);
				$insert_forum['allowpicons'] = int_to_yesno($forum['allowicons']);
				$insert_forum['allowtratings'] = int_to_yesno($forum['allowratings']);
				$insert_forum['status'] = 1;
				$insert_forum['showinjump'] = 'yes';
				$insert_forum['modposts'] = int_to_yesno($forum['moderatenew']);
				$insert_forum['modthreads'] = int_to_yesno($forum['moderatenew']);
				$insert_forum['modattachments'] = int_to_yesno($forum['moderateattach']);
				$insert_forum['overridestyle'] = int_to_yesno($forum['styleoverride']);
				$insert_forum['defaultdatecut'] = 0;
				$insert_forum['usepostcounts'] = int_to_yesno($forum['countposts']);
	
				$fid = $this->insert_forum($insert_forum);
				
				// Update parent list.
				if($forum['parentid'] == '-1')
				{
					$update_array = array('parentlist' => $fid);					
				}
				else
				{
					$update_array = array('parentlist' => $insert_forum['pid'].','.$fid);										
				}
				
				$db->update_query("forums", $update_array, "fid = '{$fid}'");
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no forums to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_forums'] += $import_session['forums_per_screen'];
		$output->print_footer();	
	}
	
	function import_forumperms()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();

		// Get number of threads
		if(!isset($import_session['total_forumperms']))
		{
			$query = $this->old_db->simple_select("forumpermission", "COUNT(*) as count");
			$import_session['total_forumperms'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_forumperms'])
		{
			// If there are more threads to do, continue, or else, move onto next module
			if($import_session['total_forumperms'] - $import_session['start_forumperms'] <= 0)
			{
				$import_session['disabled'][] = 'import_forumperms';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of threads per screen from form
		if(isset($mybb->input['forumperms_per_screen']))
		{
			$import_session['forumperms_per_screen'] = intval($mybb->input['forumperms_per_screen']);
		}
		
		if(empty($import_session['forumperms_per_screen']))
		{
			$import_session['start_forumperms'] = 0;
			echo "<p>Please select how many forum permissions to import at a time:</p>
<p><input type=\"text\" name=\"forumperms_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_forumperms']-$import_session['start_forumperms'])." pages left to import and ".round((($import_session['total_forumperms']-$import_session['start_forumperms'])/$import_session['forumperms_per_screen']))." forum permissions left at a rate of {$import_session['forumperms_per_screen']} per page.<br /><br />";
			
			$query = $this->old_db->simple_select("forumpermission", "*", "", array('limit_start' => $import_session['start_forumperms'], 'limit' => $import_session['forumperms_per_screen']));
			while($perm = $this->old_db->fetch_array($query))
			{
				echo "Inserting permission for forum #{$perm['forumid']}... ";
				
				// vBulletin 2 values
				$insert_perm['fid'] = $this->get_import_fid($perm['forumid']);
				$insert_perm['gid'] = $this->get_group_id($perm['usergroupid'], true);
				$insert_perm['canview'] = int_to_yesno($perm['canview']);
				$insert_perm['cansearch'] = int_to_yesno($perm['cansearch']);
				$insert_perm['canviewthreads'] = int_to_yesno($perm['canviewothers']);
				$insert_perm['canpostthreads'] = int_to_yesno($perm['canpostnew']);
				$insert_perm['candlattachments'] = int_to_yesno($perm['cangetattachment']);
				$insert_perm['canpostattachments'] = int_to_yesno($perm['canpostattachment']);
				$insert_perm['caneditposts'] = int_to_yesno($perm['caneditpost']);
				$insert_perm['candeletethreads'] = int_to_yesno($perm['candeletethread']);
				$insert_perm['canpostthreads'] = int_to_yesno($perm['canreplyothers']);
				$insert_perm['candeleteposts'] = int_to_yesno($perm['candeletepost']);
				$insert_perm['candeletethreads'] = int_to_yesno($perm['candeletethread']);
				$insert_perm['canpostpolls'] = int_to_yesno($perm['canpostpoll']);
				$insert_perm['canvotepolls'] = int_to_yesno($perm['canvote']);
				
				// Default values
				$insert_perm['canratethreads'] = 'yes';
				$insert_perm['caneditattachments'] = 'yes';		
					
				$this->insert_forumpermission($insert_perm);
				
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no forum permissions to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_forumperms'] += $import_session['forumperms_per_screen'];
		$output->print_footer();
	}
	
	function import_threads()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("thread", "COUNT(*) as count");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_threads'])
		{
			// If there are more threads to do, continue, or else, move onto next module
			if($import_session['total_threads'] - $import_session['start_threads'] <= 0)
			{
				$import_session['disabled'][] = 'import_threads';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of threads per screen from form
		if(isset($mybb->input['threads_per_screen']))
		{
			$import_session['threads_per_screen'] = intval($mybb->input['threads_per_screen']);
		}
		
		if(empty($import_session['threads_per_screen']))
		{
			$import_session['start_threads'] = 0;
			echo "<p>Please select how many threads to import at a time:</p>
<p><input type=\"text\" name=\"threads_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_threads']-$import_session['start_threads'])." threads left to import and ".round((($import_session['total_threads']-$import_session['start_threads'])/$import_session['threads_per_screen']))." pages left at a rate of {$import_session['threads_per_screen']} per page.<br /><br />";
			
			$query = $this->old_db->simple_select("thread", "*", "", array('order_by' => 'threadid', 'order_dir' => 'DESC', 'limit_start' => $import_session['start_threads'], 'limit' => $import_session['threads_per_screen']));
			while($thread = $this->old_db->fetch_array($query))
			{
				echo "Inserting thread #{$thread['threadid']}... ";
				
				// vBulletin 2 values
				$insert_thread['import_tid'] = $thread['threadid'];
				$insert_thread['sticky'] = $thread['sticky'];
				$insert_thread['fid'] = $this->get_import_fid($thread['forumid']);
				$insert_thread['firstpost'] = ((-1) * $this->get_firstpost($thread['threadid']));		
				$insert_thread['icon'] = $thread['iconid'];
				$insert_thread['dateline'] = $thread['dateline'];
				$insert_thread['subject'] = $thread['title'];				
				$insert_thread['import_poll'] = $thread['pollid'];
				$insert_thread['uid'] = $this->get_import_uid($thread['postuserid']);
				$insert_thread['import_uid'] = $thread['postuserid'];
				$insert_thread['views'] = $thread['views'];
				$insert_thread['replies'] = $thread['replycount'];
				$insert_thread['closed'] = int_to_noyes($thread['open']);
								
				if($insert_thread['closed'] == 'no')
				{
					$insert_thread['closed'] = '';
				}
				
				if($thread['open'] == '10')
				{
					$insert_thread['closed'] = 'moved|'.$this->get_import_tid($thread['pollid']);
				}
				
				$insert_thread['totalratings'] = $thread['votetotal'];
				$insert_thread['notes'] = $thread['notes'];
				$insert_thread['visible'] = $thread['visible'];				
				$insert_thread['unapprovedposts'] = $this->get_invisible_posts($thread['threadid']);
				$insert_thread['numratings'] = $thread['votenum'];
				$insert_thread['attachmentcount'] = $thread['attach'];	
				$insert_thread['lastpost'] = $thread['lastpost'];
				
				$post = $this->get_lastpost2($thread['threadid']);
				$insert_thread['lastposteruid'] = $this->get_import_uid($post['userid']);				
				$insert_thread['lastposter'] = $this->get_import_username($post['userid']);
				$insert_thread['username'] = $this->get_import_username($thread['postuserid']);
				
				$tid = $this->insert_thread($insert_thread);
				
				$db->update_query("forums", array('lastposttid' => $tid), "lastposttid = '".((-1) * $thread['threadid'])."'");
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no threads to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_threads'] += $import_session['threads_per_screen'];
		$output->print_footer();
	}
	
	function import_polls()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();

		// Get number of threads
		if(!isset($import_session['total_polls']))
		{
			$query = $this->old_db->simple_select("poll", "COUNT(*) as count");
			$import_session['total_polls'] = $this->old_db->fetch_field($query, 'count');			
		}

		if($import_session['start_polls'])
		{
			// If there are more polls to do, continue, or else, move onto next module
			if($import_session['total_polls'] - $import_session['start_polls'] <= 0)
			{
				$import_session['disabled'][] = 'import_polls';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of polls per screen from form
		if(isset($mybb->input['polls_per_screen']))
		{
			$import_session['polls_per_screen'] = intval($mybb->input['polls_per_screen']);
		}
		
		if(empty($import_session['polls_per_screen']))
		{
			$import_session['start_polls'] = 0;
			echo "<p>Please select how many polls to import at a time:</p>
<p><input type=\"text\" name=\"polls_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_polls']-$import_session['start_polls'])." polls left to import and ".round((($import_session['total_polls']-$import_session['start_polls'])/$import_session['polls_per_screen']))." pages left at a rate of {$import_session['polls_per_screen']} per page.<br /><br />";
			
			$query = $this->old_db->simple_select("poll", "*", "", array('limit_start' => $import_session['start_polls'], 'limit' => $import_session['polls_per_screen']));
			while($poll = $this->old_db->fetch_array($query))
			{
				echo "Inserting poll #{$poll['pollid']}... ";		
				
				// vBulletin 2 values
				$insert_poll['import_pid'] = $poll['pollid'];
								
				$query1 = $db->simple_select("threads", "tid", "import_poll = '{$poll['pollid']}'");
				$thread = $db->fetch_array($query1);
				
				$votes = @explode('|||', $poll['votes']);
				
				$insert_poll['tid'] = $thread['tid'];
				$insert_poll['dateline'] = $poll['dateline'];
				$insert_poll['question'] = $poll['question'];
				$insert_poll['options'] = str_replace('|||', '||~|~||', $poll['options']);
				$insert_poll['votes'] = str_replace('|||', '||~|~||', $poll['votes']);
				$insert_poll['numoptions'] = $poll['numberoptions'];
				$insert_poll['numvotes'] = count($votes);
				$insert_poll['timeout'] = $poll['timeout'];
				$insert_poll['multiple'] = int_to_yesno($poll['multiple']);
				$insert_poll['closed'] = int_to_noyes($poll['active']);								
				
				$pid = $this->insert_poll($insert_poll);
				
				// Restore connections
				$db->update_query("threads", array('poll' => $pid), "tid = '".$insert_poll['tid']."'");
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no polls to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_polls'] += $import_session['polls_per_screen'];
		$output->print_footer();
	}
	
	function import_pollvotes()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();

		// Get number of threads
		if(!isset($import_session['total_pollvotes']))
		{
			$query = $this->old_db->simple_select("pollvote", "COUNT(*) as count");
			$import_session['total_pollvotes'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_pollvotes'])
		{
			// If there are more threads to do, continue, or else, move onto next module
			if($import_session['total_pollvotes'] - $import_session['start_pollvotes'] <= 0)
			{
				$import_session['disabled'][] = 'import_pollvotes';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of poll votes per screen from form
		if(isset($mybb->input['pollvotes_per_screen']))
		{
			$import_session['pollvotes_per_screen'] = intval($mybb->input['pollvotes_per_screen']);
		}
		
		if(empty($import_session['pollvotes_per_screen']))
		{
			$import_session['start_pollvotes'] = 0;
			echo "<p>Please select how many poll votes to import at a time:</p>
<p><input type=\"text\" name=\"pollvotes_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_pollvotes']-$import_session['start_pollvotes'])." poll votes left to import and ".round((($import_session['total_pollvotes']-$import_session['start_pollvotes'])/$import_session['pollvotes_per_screen']))." pages left at a rate of {$import_session['pollvotes_per_screen']} per page.<br /><br />";

			$query = $this->old_db->simple_select("pollvote", "*", "", array('limit_start' => $import_session['start_pollvotes'], 'limit' => $import_session['pollvotes_per_screen']));
			while($pollvote = $this->old_db->fetch_array($query))
			{
				echo "Inserting poll vote #{$pollvote['pollvoteid']}... ";
				
				$insert_pollvote['uid'] = $this->get_import_uid($pollvote['userid']);
				$insert_pollvote['dateline'] = $pollvote['votedate'];
				$insert_pollvote['voteoption'] = $pollvote['voteoption'];
				$insert_pollvote['pid'] = $this->get_import_pid($pollvote['pollid']);
				
				$this->insert_pollvote($insert_pollvote);
				
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no poll votes to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_pollvotes'] += $import_session['pollvotes_per_screen'];
		$output->print_footer();
	}
	
	function import_icons()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();
		
		if(!isset($import_session['bburl']))
		{
			$query = $this->old_db->simple_select("setting", "value", "varname = 'bburl'");
			$import_session['bburl'] = $this->old_db->fetch_field($query, "value").'/';
		}

		// Get number of threads
		if(!isset($import_session['total_icons']))
		{
			$query = $this->old_db->simple_select("icon", "COUNT(*) as count", "iconid > 14");
			$import_session['total_icons'] = $this->old_db->fetch_field($query, 'count');			
		}

		if($import_session['start_icons'])
		{
			// If there are more polls to do, continue, or else, move onto next module
			if($import_session['total_icons'] - $import_session['start_icons'] <= 0)
			{
				$import_session['disabled'][] = 'import_icons';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of polls per screen from form
		if(isset($mybb->input['icons_per_screen']))
		{
			$import_session['icons_per_screen'] = intval($mybb->input['icons_per_screen']);
		}
		
		if(empty($import_session['icons_per_screen']))
		{
			$import_session['start_icons'] = 0;
			echo "<p>Please select how many icons to import at a time:</p>
<p><input type=\"text\" name=\"icons_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_icons']-$import_session['start_icons'])." icons left to import and ".round((($import_session['total_icons']-$import_session['start_icons'])/$import_session['icons_per_screen']))." pages left at a rate of {$import_session['icons_per_screen']} per page.<br /><br />";
			
			$query = $this->old_db->simple_select("icon", "*", "iconid > 14", array('limit_start' => $import_session['start_icons'], 'limit' => $import_session['icons_per_screen']));
			while($icon = $this->old_db->fetch_array($query))
			{
				echo "Inserting icon #{$icon['iconid']}... ";
				flush(); // Show status as soon as possible to avoid inconsistent status reporting	
				
				// vBulletin 2 values
				$insert_icon['import_iid'] = $icon['iconid'];
				$insert_icon['name'] = $icon['title'];
				$insert_icon['path'] = "images/icons".substr(strrchr($icon['iconpath'], "/"), 1);
			
				$iid = $this->insert_icon($insert_icon);
				
				// Restore connections
				$db->update_query("threads", array('icon' => $iid), "icon = '".((-1) * $icon['id'])."'");
				
				// Transfer icons
				if(file_exists($import_session['bburl'].$icon['iconpath']))
				{
					$icondata = file_get_contents($import_session['bburl'].$icon['iconpath']);
					$file = fopen(MYBB_ROOT.$insert_icon['path'], 'w');
					fwrite($file, $icondata);
					fclose($file);
					@chmod(MYBB_ROOT.$insert_icon['path'], 0777);
					$transfer_error = "";
				}
				else
				{
					$transfer_error = " (Note: Could not transfer icon. - \"Not Found\")";
				}
				
				echo "done.{$transfer_error}<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no icons to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_icons'] += $import_session['icons_per_screen'];
		$output->print_footer();
	}
	
	function import_posts()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();

		// Get number of posts
		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->simple_select("post", "COUNT(*) as count");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_posts'])
		{
			// If there are more posts to do, continue, or else, move onto next module
			if($import_session['total_posts'] - $import_session['start_posts'] <= 0)
			{
				$import_session['disabled'][] = 'import_posts';
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of posts per screen from form
		if(isset($mybb->input['posts_per_screen']))
		{
			$import_session['posts_per_screen'] = intval($mybb->input['posts_per_screen']);
		}
		
		if(empty($import_session['posts_per_screen']))
		{
			$import_session['start_posts'] = 0;
			echo "<p>Please select how many posts to import at a time:</p>
<p><input type=\"text\" name=\"posts_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_posts']-$import_session['start_posts'])." posts left to import and ".round((($import_session['total_posts']-$import_session['start_posts'])/$import_session['posts_per_screen']))." pages left at a rate of {$import_session['posts_per_screen']} per page.<br /><br />";
			
			$query = $this->old_db->simple_select("post", "*", "", array('limit_start' => $import_session['start_posts'], 'limit' => $import_session['posts_per_screen']));
			while($post = $this->old_db->fetch_array($query))
			{
				echo "Inserting post #{$post['postid']}... ";
				
				// vBulletin 2 values
				$insert_post['import_pid'] = $post['postid'];
				$insert_post['tid'] = $this->get_import_tid($post['threadid']);
				$thread = $this->get_thread($post['threadid']);				
				$insert_post['fid'] = $this->get_import_fid($thread['forumid']);
				$insert_post['subject'] = $post['title'];
				$insert_post['visible'] = $post['visible'];
				$insert_post['uid'] = $this->get_import_uid($post['userid']);
				$insert_post['import_uid'] = $post['userid'];
				$insert_post['username'] = $this->get_import_username($post['userid']);
				$insert_post['dateline'] = $post['dateline'];
				$insert_post['message'] = $post['pagetext'];
				$insert_post['ipaddress'] = $post['ipaddress'];
				$insert_post['icon'] = $this->get_import_iid($post['iconid']);	
				$insert_post['edituid'] = $this->get_import_uid($post['edituserid']);
				$insert_post['edittime'] = $post['editdate'];
				$insert_post['includesig'] = int_to_yesno($post['showsignature']);		
				$insert_post['smilieoff'] = int_to_noyes($post['allowsmilie']);	
				
				// Default values
				$insert_post['pid'] = 0;
				$insert_post['posthash'] = '';

				$pid = $this->insert_post($insert_post);
				
				// Update thread count
				update_thread_count($insert_post['tid']);
				
				// Restore first post connections
				$db->update_query("threads", array('firstpost' => $pid), "tid = '{$insert_post['tid']}' AND firstpost = '".((-1) * $post['pid'])."'");
				if($db->affected_rows() == 0)
				{
					$query1 = $db->simple_select("threads", "firstpost", "tid = '{$insert_post['tid']}'");
					$first_post = $db->fetch_field($query1, "firstpost");
					$db->update_query("posts", array('replyto' => $first_post), "pid = '{$pid}'");
				}				
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no posts to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_posts'] += $import_session['posts_per_screen'];
		$output->print_footer();
	}
	
	function import_attachments()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();

		// Get number of threads
		if(!isset($import_session['total_attachments']))
		{
			$query = $this->old_db->simple_select("attachment", "COUNT(*) as count");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_attachments'])
		{
			// If there are more attachments to do, continue, or else, move onto next module
			if($import_session['total_attachments'] - $import_session['start_attachments'] <= 0)
			{
				$import_session['disabled'][] = 'import_attachments';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of polls per screen from form
		if(isset($mybb->input['attachments_per_screen']))
		{
			$import_session['attachments_per_screen'] = intval($mybb->input['attachments_per_screen']);
		}
		
		if(empty($import_session['attachments_per_screen']))
		{
			$import_session['start_attachments'] = 0;
			echo "<p>Please select how many attachments to import at a time:</p>
<p><input type=\"text\" name=\"attachments_per_screen\" value=\"10\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_attachments']-$import_session['start_attachments'])." attachments left to import and ".round((($import_session['total_attachments']-$import_session['start_attachments'])/$import_session['attachments_per_screen']))." pages left at a rate of {$import_session['attachments_per_screen']} per page.<br /><br />";

			$query = $this->old_db->simple_select("attachment", "*", "", array('limit_start' => $import_session['start_attachments'], 'limit' => $import_session['attachments_per_screen']));
			while($attachment = $this->old_db->fetch_array($query))
			{
				echo "Inserting attachment #{$attachment['attachmentid']}... ";				
				
				// vBulletin 2 values
				$insert_attachment['import_aid'] = $attachment['attachmentid'];
				$insert_attachment['pid'] = $this->get_import_pid($attachment['postid']);
				$insert_attachment['uid'] = $this->get_import_uid($attachment['userid']);
				$insert_attachment['filename'] = $attachment['filename'];
				$insert_attachment['attachname'] = "post_".$import_attachment['uid']."_".$attachment['dateline'].".attach";
				$insert_attachment['filetype'] = @mime_content_type($attachment['extension']);
				$insert_attachment['filesize'] = $attachment['filesize'];
				$insert_attachment['downloads'] = $attachment['counter'];
				$insert_attachment['visible'] = int_to_yesno($attachment['visible']);
				
				// Default values
				$insert_attachment['thumbnail'] = '';
				
				$query2 = $db->simple_select("posts", "posthash, tid, uid", "pid = '{$insert_attachment['pid']}'");
				$posthash = $db->fetch_array($query2);
				if($posthash['posthash'])
				{
					$insert_attachment['posthash'] = $posthash['posthash'];
				}
				else
				{
					mt_srand ((double) microtime() * 1000000);
					$insert_attachment['posthash'] = md5($posthash['tid'].$posthash['uid'].mt_rand());
				}
				
				$this->insert_attachment($insert_attachment);
				
				// Transfer attachments
				$file = fopen($mybb->settings['uploadspath'].'/'.$insert_attachment['attachname'], 'w');
				fwrite($file, $attachment['filedata']);
				fclose($file);
				@chmod($mybb->settings['uploadspath'].'/'.$insert_attachment['attachname'], 0777);
				
				if(!$posthash)
				{
					// Restore connection
					$db->update_query("posts", array('posthash' => $insert_attachment['posthash']), "pid = '{$insert_attachment['pid']}'");
				}

				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no attachments to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_attachments'] += $import_session['attachments_per_screen'];
		$output->print_footer();
	}
	
	function import_privatemessages()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();

		// Get number of usergroups
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("privatemessage", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_privatemessages'])
		{
			// If there are more usergroups to do, continue, or else, move onto next module
			if($import_session['total_privatemessages'] - $import_session['start_privatemessages'] <= 0)
			{
				$import_session['disabled'][] = 'import_privatemessages';
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of posts per screen from form
		if(isset($mybb->input['privatemessages_per_screen']))
		{
			$import_session['privatemessages_per_screen'] = intval($mybb->input['privatemessages_per_screen']);
		}
		
		if(empty($import_session['privatemessages_per_screen']))
		{
			$import_session['start_privatemessages'] = 0;
			echo "<p>Please select how many Private Messages to import at a time:</p>
<p><input type=\"text\" name=\"privatemessages_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_privatemessages']-$import_session['start_privatemessages'])." private messages left to import and ".round((($import_session['total_privatemessages']-$import_session['start_privatemessages'])/$import_session['privatemessages_per_screen']))." pages left at a rate of {$import_session['privatemessages_per_screen']} per page.<br /><br />";
			
			$query = $this->old_db->simple_select("privatemessage", "*", "", array('limit_start' => $import_session['start_privatemessages'], 'limit' => $import_session['privatemessages_per_screen']));
			while($pm = $this->old_db->fetch_array($query))
			{
				echo "Inserting Private Message #{$pm['pmid']}... ";
				
				// vBulletin 2 values
				$insert_pm['import_pmid'] = $pm['pmid'];
				$insert_pm['uid'] = $this->get_import_uid($pm['userid']);
				$insert_pm['fromid'] = $this->get_import_uid($pm['fromuserid']);
				$insert_pm['toid'] = $insert_pm['uid']; // need to fix

				// Rebuild the recipients array
				$recipients = array();
				$recipients['to'][] = $this->get_import_username($insert_pm['toid']);
				
				$insert_pm['recipients'] = serialize($recipients);
				if($pm['folderid'] == -1)
				{
					$insert_pm['folder'] = 2;
				}
				else
				{
					$insert_pm['folder'] = 0;
				}
				
				$insert_pm['subject'] = $pm['title'];
				$insert_pm['status'] = $pm['messageread'];
				$insert_pm['dateline'] = $pm['dateline'];
				$insert_pm['message'] = $pm['message'];
				$insert_pm['includesig'] = int_to_yesno($pm['showsignature']);
				$insert_pm['smilieoff'] = 0;
				
				if($pm['messageread'] == 1)
				{
					$insert_pm['readtime'] = time();
				}
				$insert_pm['icon'] = $this->get_import_iid($pm['iconid']);
				
				// Default values		
				$insert_pm['receipt'] = '2';
				$insert_pm['pmid'] = '';

				$this->insert_privatemessage($insert_pm);
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no private messages to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_privatemessages'] += $import_session['privatemessages_per_screen'];
		$output->print_footer();
	}
	
	function import_moderators()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();

		// Get number of moderators
		if(!isset($import_session['total_mods']))
		{
			$query = $this->old_db->simple_select("moderator", "COUNT(*) as count", "forumid != '-1'");
			$import_session['total_mods'] = $this->old_db->fetch_field($query, 'count');	
		}

		if($import_session['start_mods'])
		{
			// If there are more moderators to do, continue, or else, move onto next module
			if($import_session['total_mods'] - $import_session['start_mods'] <= 0)
			{
				$import_session['disabled'][] = 'import_moderators';
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of posts per screen from form
		if(isset($mybb->input['mods_per_screen']))
		{
			$import_session['mods_per_screen'] = intval($mybb->input['mods_per_screen']);
		}
		
		if(empty($import_session['mods_per_screen']))
		{
			$import_session['start_mods'] = 0;
			echo "<p>Please select how many moderators to import at a time:</p>
<p><input type=\"text\" name=\"mods_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_mods']-$import_session['start_mods'])." moderators left to import and ".round((($import_session['total_mods']-$import_session['start_mods'])/$import_session['mods_per_screen']))." pages left at a rate of {$import_session['mods_per_screen']} per page.<br /><br />";
			
			$query = $this->old_db->simple_select("moderator", "*", "forumid != '-1'", array('limit_start' => $import_session['start_mods'], 'limit' => $import_session['mods_per_screen']));
			while($mod = $this->old_db->fetch_array($query))
			{
				echo "Inserting user #{$mod['userid']} as moderator to forum #{$mod['forumid']}... ";
				
				// vBulletin 2 values
				$insert_mod['fid'] = $this->get_import_fid($mod['forumid']);
				$insert_mod['uid'] = $this->get_import_uid($mod['userid']);
				$insert_mod['caneditposts'] = int_to_yesno($mod['caneditposts']);
				$insert_mod['candeleteposts'] = int_to_yesno($mod['candeleteposts']);
				$insert_mod['canviewips'] = int_to_yesno($mod['canviewips']);
				$insert_mod['canopenclosethreads'] = int_to_yesno($mod['canopenclose']);
				$insert_mod['canmanagethreads'] = int_to_yesno($mod['canmanagethreads']);
				
				// Default values
				$insert_mod['canmovetononmodforum'] = 'yes';

				$this->insert_moderator($insert_mod);
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no moderators to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_mods'] += $import_session['mods_per_screen'];
		$output->print_footer();
	}
	
	function import_smilies()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();
		
		if(!isset($import_session['bburl']))
		{
			$query = $this->old_db->simple_select("setting", "value", "varname = 'bburl'");
			$import_session['bburl'] = $this->old_db->fetch_field($query, "value").'/';
		}

		// Get number of threads
		if(!isset($import_session['total_smilies']))
		{
			$query = $this->old_db->simple_select("smilie", "COUNT(*) as count", "smilieid > 11");
			$import_session['total_smilies'] = $this->old_db->fetch_field($query, 'count');			
		}

		if($import_session['start_smilies'])
		{
			// If there are more polls to do, continue, or else, move onto next module
			if($import_session['total_smilies'] - $import_session['start_smilies'] <= 0)
			{
				$import_session['disabled'][] = 'import_smilies';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of polls per screen from form
		if(isset($mybb->input['smilies_per_screen']))
		{
			$import_session['smilies_per_screen'] = intval($mybb->input['smilies_per_screen']);
		}
		
		if(empty($import_session['smilies_per_screen']))
		{
			$import_session['start_icons'] = 0;
			echo "<p>Please select how many smilies to import at a time:</p>
<p><input type=\"text\" name=\"smilies_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_smilies']-$import_session['start_smilies'])." smilies left to import and ".round((($import_session['total_smilies']-$import_session['start_smilies'])/$import_session['smilies_per_screen']))." pages left at a rate of {$import_session['smilies_per_screen']} per page.<br /><br />";
			
			$query = $this->old_db->simple_select("smilie", "*", "smilieid > 11", array('limit_start' => $import_session['start_icons'], 'limit' => $import_session['icons_per_screen']));
			while($smilie = $this->old_db->fetch_array($query))
			{
				echo "Inserting smilie #{$smilie['smilieid']}... ";
				flush(); // Show status as soon as possible to avoid inconsistent status reporting
				
				// vBulletin 2 values
				$insert_smilie['name'] = $smilie['title'];
				$insert_smilie['find'] = $smilie['smilietext'];
				$insert_smilie['image'] = "images/smilies/".substr(strrchr($smilie['smiliepath'], "/"), 1);
				$insert_smilie['disporder'] = $smilie['smilieid'];
				
				// Default values
				$insert_smilie['showclickable'] = 'yes';
			
				$this->insert_smilie($insert_smilie);
				
				// Transfer smilies
				if(file_exists($import_session['bburl'].$smilie['smiliepath']))
				{
					$smiliedata = file_get_contents($import_session['bburl'].$smilie['smiliepath']);
					$file = fopen(MYBB_ROOT.$insert_smilie['path'], 'w');
					fwrite($file, $smiliedata);
					fclose($file);
					@chmod(MYBB_ROOT.$insert_smilie['path'], 0777);
					$transfer_error = "";
				}
				else
				{
					$transfer_error = " (Note: Could not transfer smilie. - \"Not Found\")";
				}
				echo "done.{$transfer_error}<br />\n";		
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no smilies to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_smilies'] += $import_session['smilies_per_screen'];
		$output->print_footer();
	}
	
	function import_settings()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();

		// What settings do we need to get and what is their MyBB equivalent?
		$settings_array = array(
			"addtemplatename" => "tplhtmlcomments",
			"allowregistration" => "disableregs",
			"allowkeepbannedemail" => "emailkeep",
			"banemail" => "bannedemails",
			"banip" => "bannedips",
			"bbactive" => "boardclosed",
			"bbclosedreason" => "boardclosed_reason", 
			"bbtitle" => "bbname",
			"dateformat" => "dateformat",
			"displayloggedin" => "showwol",
			"edittimelimit" => "edittimelimit",
			"enablememberlist" => "enablememberlist",
			"enablepms" => "enablepms",
			"floodchecktime" => "postfloodsecs",
			"forumhomedepth" => "subforumsindex",
			"gziplevel" => "gziplevel",
			"gzipoutput" => "gzipoutput",
			"hometitle" => "homename",
			"homeurl" => "homeurl",
			"hotnumberposts" => "hottopic",
			"hotnumberviews" => "hottopicviews",
			"illegalusernames" => "bannedusername",
			"loadlimit" => "load",
			"logip" => "logip",
			"maximages" => "maxpostimages",
			"maxpolloptions" => "maxpolloptions",
			"maxposts" => "postsperpage",
			"maxthreads" => "threadsperpage",
			"maxuserlength" => "maxnamelength",
			"memberlistperpage" => "membersperpage",
			"minsearchlength" => "minsearchword",
			"minuserlength" => "minnamelength",
			"moderatenewmembers" => "regtype",
			"nocacheheaders" => "nocacheheaders",
			"postmaxchars" => "maxmessagelength",
			"privallowbbcode" => "pmsallowmycode",
			"privallowbbimagecode" => "pmsallowimgcode",
			"privallowhtml" => "pmsallowhtml",
			"privallowsmilies" => "pmsallowsmilies",
			"registereddateformat" => "regdateformat",
			"searchfloodtime" => "searchfloodtime",
			"showbirthdays" => "showbirthdays",
			"showdots" => "dotfolders",
			"showforumdescription" => "showdescriptions",
			"showforumusers" => "browsingthisforum",
			"showprivateforums" => "hideprivateforums",
			"timeformat" => "timeformat",
			"timeoffset" => "timezoneoffset",
			"usereferrer" => "usereferrals",
			"usermaxposts" => "userpppoptions",
			"webmasteremail" => "adminemail",
			"WOLrefresh" => "refreshwol"
		);
		$settings = "'".implode("','", array_keys($settings_array))."'";
		$int_to_yes_no = array(
			"addtemplatename" => 1,
			"allowregistration" => 0,
			"allowkeepbannedemail" => 1,
			"bbactive" => 0,
			"displayloggedin" => 1,
			"enablememberlist" => 1,
			"enablepms" => 1,
			"gzipoutput" => 1,
			"nocacheheaders" => 1,
			"privallowbbcode" => 1,
			"privallowbbimagecode" => 1,
			"privallowhtml" => 1,
			"privallowsmilies" => 1,
			"showbirthdays" => 1,
			"showdots" => 1,
			"showforumdescription" => 1,
			"usereferrer" => 1
		);

		// Get number of settings
		if(!isset($import_session['total_settings']))
		{
			$query = $this->old_db->simple_select("setting", "COUNT(*) as count", "varname IN({$settings})");
			$import_session['total_settings'] = $this->old_db->fetch_field($query, 'count');		
		}

		if($import_session['start_settings'])
		{
			// If there are more settings to do, continue, or else, move onto next module
			if($import_session['total_settings'] - $import_session['start_settings'] <= 0)
			{
				$import_session['disabled'][] = 'import_settings';
				rebuildsettings();
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of settings per screen from form
		if(isset($mybb->input['settings_per_screen']))
		{
			$import_session['settings_per_screen'] = intval($mybb->input['settings_per_screen']);
		}

		if(empty($import_session['settings_per_screen']))
		{
			$import_session['start_settings'] = 0;
			echo "<p>Please select how many settings to modify at a time:</p>
<p><input type=\"text\" name=\"settings_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_settings']-$import_session['start_settings'])." settings left to import and ".round((($import_session['total_settings']-$import_session['start_settings'])/$import_session['settings_per_screen']))." pages left at a rate of {$import_session['settings_per_screen']} per page.<br /><br />";

			$query = $this->old_db->simple_select("setting", "varname, value", "varname IN({$settings})", array('limit_start' => $import_session['start_settings'], 'limit' => $import_session['settings_per_screen']));
			while($setting = $this->old_db->fetch_array($query))
			{
				// vBulletin 2 values
				$name = $settings_array[$setting['varname']];
				$value = $setting['value'];
				
				echo "Updating setting ".htmlspecialchars_uni($value)." from the vBulletin database to {$name} in the MyBB database... ";
				
				if(($value == 0 || $value == 1) && isset($int_to_yes_no[$setting['varname']]))
				{
					$value = int_to_yes_no($value, $int_to_yes_no[$setting['varname']]);
				}
				
				$this->update_setting($name, $value);
				
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no settings to update. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_settings'] += $import_session['settings_per_screen'];
		$output->print_footer();
	}
	
	function import_events()
	{
		global $mybb, $output, $import_session, $db;

		$this->vbulletin_db_connect();

		// Get number of threads
		if(!isset($import_session['total_events']))
		{
			$query = $this->old_db->simple_select("calendar_events", "COUNT(*) as count");
			$import_session['total_events'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_events'])
		{
			// If there are more polls to do, continue, or else, move onto next module
			if($import_session['total_events'] - $import_session['start_events'] <= 0)
			{
				$import_session['disabled'][] = 'import_events';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of polls per screen from form
		if(isset($mybb->input['events_per_screen']))
		{
			$import_session['events_per_screen'] = intval($mybb->input['events_per_screen']);
		}
		
		if(empty($import_session['events_per_screen']))
		{
			$import_session['start_events'] = 0;
			echo "<p>Please select how many events to import at a time:</p>
<p><input type=\"text\" name=\"events_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_events']-$import_session['start_events'])." events left to import and ".round((($import_session['total_events']-$import_session['start_events'])/$import_session['events_per_screen']))." pages left at a rate of {$import_session['events_per_screen']} per page.<br /><br />";
			
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("events");

			$query = $this->old_db->simple_select("calendar_events", "*", "", array('limit_start' => $import_session['start_events'], 'limit' => $import_session['events_per_screen']));
			while($event = $this->old_db->fetch_array($query))
			{
				echo "Inserting event #{$event['eventid']}... ";				

				$insert_event['import_eid'] = $event['eventid'];
				$insert_event['subject'] = $event['subject'];
				$insert_event['description'] = $event['event'];
				$insert_event['author'] = $this->get_import_uid($event['userid']);
				$insert_event['private'] = int_to_noyes($event['public']);
				$eventdate = explode('-', $event['eventdate']);
				$insert_event['date'] = $event[2].'-'.$event[1].'-'.$event[0];
				$insert_event['start_day'] = $event[2];
				$insert_event['start_month'] = $event[1];
				$insert_event['start_year'] = $event[0];
				$insert_event['end_day'] = 0;
				$insert_event['end_month'] = 0;
				$insert_event['end_year'] = 0;

				$this->insert_event($insert_event);

				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no events to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_events'] += $import_session['events_per_screen'];
		$output->print_footer();
	}
	
	/**
	 * Count number of invisible posts from the vB database
	 *
	 * @param int thread id (optional)
	 * @return int number of invisible posts
	 */
	function get_invisible_posts($tid='')
	{
		$tidbit = "";
		if(!empty($tid))
		{
			$tidbit = " AND threadid = '".intval($tid)."'";
		}
		$query = $this->old_db->simple_select("post", "COUNT(*) as invisible", "visible = '0'{$tidbit}");
		return $this->old_db->fetch_field($query, "invisible");
	}
	
	/**
	 * Count number of invisible threads from the vB database
	 *
	 * @param int forum id (optional)
	 * @return int number of invisible threads
	 */
	function get_invisible_threads($fid='')
	{
		$fidbit = "";
		if(!empty($fid))
		{
			$fidbit = " AND forumid = '".intval($fid)."'";
		}
		
		$query = $this->old_db->simple_select("thread", "COUNT(*) as invisible", "visible = '0'{$fidbit}");
		return $this->old_db->fetch_field($query, "invisible");
	}
	
	/**
	 * Get a post from the vB database
	 *
	 * @param int Post ID
	 * @return array The post
	 */
	function get_post($pid)
	{
		$pid = intval($pid);
		$query = $this->old_db->simple_select("post", "*", "postid = '{$pid}'", array('limit' => 1));
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Get a thread from the vB database
	 *
	 * @param int Thread ID
	 * @return array The thread
	 */
	function get_thread($tid)
	{
		$tid = intval($tid);
		$query = $this->old_db->simple_select("thread", "*", "threadid = '{$tid}'", array('limit' => 1));
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Get a user from the vB database
	 *
	 * @param int User ID
	 * @return array If the uid is 0, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_user($uid)
	{
		$uid = intval($uid);
		if(empty($uid))
		{
			return array(
				'username' => 'Guest',
				'userid' => 0,
			);
		}
		
		$query = $this->old_db->simple_select("user", "*", "userid = '{$uid}'", array('limit' => 1));
		
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Get a user from the vB database
	 *
	 * @param int Username
	 * @return array If the username is empty, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_username($username)
	{
		if(empty($username))
		{
			return array(
				'username' => 'Guest',
				'userid' => 0,
			);
		}
				
		$query = $this->old_db->simple_select("user", "*", "username = '{$username}'", array('limit' => 1));
		
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Convert a vB group ID into a MyBB group ID
	 *
	 * @param int Group ID
	 * @param boolean single group or multiple?
	 * @param boolean original group values?
	 * @return mixed group id(s)
	 */
	function get_group_id($gid, $not_multiple=false, $orig=false)
	{
		$settings = array();
		if($not_multiple == false)
		{
			$query = $this->old_db->simple_select("usergroup", "COUNT(*) as rows", "usergroupid='{$gid}'");
			$settings = array('limit_start' => '1', 'limit' => $this->old_db->fetch_field($query, 'rows'));
		}
		
		$query = $this->old_db->simple_select("usergroup", "*", "usergroupid='{$gid}'", $settings);
		
		$comma = $group = '';
		while($vbgroup = $this->old_db->fetch_array($query))
		{
			if($orig == true)
			{
				$group .= $vbgroup['usergroupid'].$comma;
			}
			else
			{
				$group .= $comma;
				switch($vbgroup['usergroupid'])
				{
					case 1: // Guests
						$group .= 1;
						break;
					case 2: // Register
					case 4: // Registered coppa
						$group .= 2;
						break;
					case 3: // Awaiting activation
						$group .= 5;
						break;
					case 5: // Super moderator
						$group .= 3;
						break;
					case 6: // Administrator
						$group .= 4;
						break;
					default:
						$gid = $this->get_import_gid($vbgroup['usergroupid']);
						if($gid > 0)
						{
							// If there is an associated custom group...
							$group .= $gid;
						}
						else
						{
							// The lot
							$group .= 2;
						}
				}			
			}
			$comma = ',';
			
			if(!$query)
			{
				return 2; // Return regular registered user.
			}			
			
			return $group;
		}
	}
	
	/**
	 * Get a avatar from the vB database
	 *
	 * @param int Avatar ID
	 * @return array The avatar
	 */
	function get_avatar($aid)
	{
		$aid = intval($aid);
		$query = $this->old_db->simple_select("avatar", "*", "avatarid = '{$aid}'");		
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Get the lastest post from the vB database
	 *
	 * @param int Forum ID
	 * @return array The lastest post
	 */
	function get_lastpost($fid)
	{
		$query = $this->old_db->query("
			SELECT * FROM ".VB_TABLE_PREFIX."post p
			LEFT JOIN ".VB_TABLE_PREFIX."thread t ON(p.threadid=t.threadid) 
			WHERE t.forumid = '".intval($fid)."' AND p.visible='1'
			ORDER BY p.dateline DESC
			LIMIT 1
		");
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Get the lastest post from the vB database
	 *
	 * @param int Thread ID
	 * @return array The lastest post
	 */
	function get_lastpost2($tid)
	{
		$query = $this->old_db->simple_select("post", "*", "threadid = '".intval($tid)."' AND visible='1'", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 1));
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Get the first post from the vB database
	 *
	 * @param int Thread ID
	 * @return array The first post
	 */
	function get_firstpost($tid)
	{
		$query = $this->old_db->simple_select("post", "*", "threadid = '".intval($tid)."'", array('order_by' => 'dateline', 'order_dir' => 'ASC', 'limit' => 1));
		return $this->old_db->fetch_field($query, "postid");
	}
	
	/**
	 * Get total number of Private Messages the user has from the vB database
	 *
	 * @param int User ID
	 * @param boolean Count unread messages
	 * @return int Number of Private Messages
	 */
	function get_private_messages($uid, $unread=false)
	{
		if($unread == false)
		{
			$query = $this->old_db->simple_select("privatemessage", "COUNT(*) as pms", "userid = '{$uid}'");
		}
		else
		{
			$query = $this->old_db->simple_select("privatemessage", "COUNT(*) as pms", "userid = '{$uid}' AND messageread = '0'");
		}
		
		return $this->old_db->fetch_field($query, 'pms');
	}
}

?>