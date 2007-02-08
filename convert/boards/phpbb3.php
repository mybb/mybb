<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
 *
 * $Id$
 */
 
// Board Name: phpBB 3

class Convert_phpbb3 extends Converter {

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "phpBB 3";
	
	/**
	 * Array of all of the modules
	 *
	 * @var array
	 */
	var $modules = array("db_configuration" => array("name" => "Database Configuration",
									  "dependencies" => ""),
						 "import_users" => array("name" => "Import phpBB 3 Users",
									  "dependencies" => "db_configuration"),
						 "import_usergroups" => array("name" => "Import phpBB 3 Usergroups",
									  "dependencies" => "db_configuration,import_users"),
						 "import_forums" => array("name" => "Import phpBB 3 Forums",
									  "dependencies" => "db_configuration,import_users"),
						 "import_threads" => array("name" => "Import phpBB 3 Threads",
									  "dependencies" => "db_configuration,import_forums"),
						 "import_icons" => array("name" => "Import phpBB 3 Icons",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_polls" => array("name" => "Import phpBB 3 Polls",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Import phpBB 3 Poll Votes",
									  "dependencies" => "db_configuration,import_polls"),
						 "import_posts" => array("name" => "Import phpBB 3 Posts",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_privatemessages" => array("name" => "Import phpBB 3 Private Messages",
						 			  "dependencies" => "db_configuration,import_users"),
						 "import_moderators" => array("name" => "Import phpBB 3 Moderators",
									  "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_smilies" => array("name" => "Import phpBB 3 Smilies",
									  "dependencies" => "db_configuration"),
						 "import_settings" => array("name" => "Import phpBB 3 Settings",
									  "dependencies" => "db_configuration"),
						 "import_attachtypes" => array("name" => "Import phpBB3 Attachment Types",
									  "dependencies" => "db_configuration"),
						 "import_attachments" => array("name" => "Import phpBB 3 Attachments",
									  "dependencies" => "db_configuration,import_posts"),
						);

	function phpbb_db_connect()
	{
		global $import_session;

		// TEMPORARY
		if($import_session['old_db_engine'] != "mysql" && $import_session['old_db_engine'] != "mysqli")
		{
			require_once MYBB_ROOT."inc/db_{$import_session['old_db_engine']}.php";
		}
		$this->old_db = new databaseEngine;

		$this->old_db->connect($import_session['old_db_host'], $import_session['old_db_user'], $import_session['old_db_pass'], 0, true);
		$this->old_db->select_db($import_session['old_db_name']);
		$this->old_db->set_table_prefix($import_session['old_tbl_prefix']);

		define('PHPBB_TABLE_PREFIX', $import_session['old_tbl_prefix']);
	}

	function db_configuration()
	{
		global $mybb, $output, $import_session, $db, $dboptions, $dbengines, $dbhost, $dbuser, $dbname, $tableprefix;

		// Just posted back to this form?
		if($mybb->input['dbengine'])
		{
			if(!file_exists(MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php"))
			{
				$errors[] = 'You have selected an invalid database engine. Please make your selection from the list below.';
			}
			else
			{
				// Attempt to connect to the db
				// TEMPORARY
				if($mybb->input['dbengine'] != "mysql" && $mybb->input['dbengine'] != "mysqli")
				{
					require_once MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php";
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

				// Need to check if phpBB is actually installed here
				$this->old_db->set_table_prefix($mybb->input['tableprefix']);
				if(!$this->old_db->table_exists("users"))
				{
					$errors[] = "The phpBB table '{$mybb->input['tableprefix']}users' could not be found in database '{$mybb->input['dbname']}'.  Please ensure phpBB exists at this database and with this table prefix.";
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

		$output->print_header("phpBB 3 Database Configuration");

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
			echo "<p>Please enter the database details for your current installation of phpBB 3.</p>";
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

		$output->print_database_details_table("phpBB 3");

		$output->print_footer();
	}
	
	function import_users()
	{
		global $mybb, $output, $import_session, $db;

		$this->phpbb_db_connect();

		// Get number of members
		if(!isset($import_session['total_members']))
		{
			$query = $this->old_db->simple_select("users", "COUNT(*) as count");
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
			$query = $this->old_db->query("
				SELECT * 
				FROM ".PHPBB_TABLE_PREFIX."users u
				LEFT JOIN ".PHPBB_TABLE_PREFIX."user_group ug ON(u.user_id=ug.user_id)
				WHERE u.user_id > 0 AND u.username != 'Alexa' AND u.username != 'Fastcrawler' AND u.username != 'Googlebot' AND u.username != 'Inktomi'
				LIMIT ".$import_session['start_users'].", ".$import_session['users_per_screen']
			);

			while($user = $this->old_db->fetch_array($query))
			{
				++$total_users;

				// Check for duplicate users
				$query1 = $db->simple_select("users", "username,email,uid", " LOWER(username)='".$db->escape_string(my_strtolower($user['username']))."'");
				$duplicate_user = $db->fetch_array($query1);
				if($duplicate_user['username'] && my_strtolower($user['user_email']) == my_strtolower($duplicate_user['email']))
				{
					echo "Merging user #{$user['user_id']} with user #{$duplicate_user['uid']}... ";
					$db->update_query("users", array('import_uid' => $user['user_id']), "uid = '{$duplicate_user['uid']}'");
					echo "done.<br />";
					
					continue;
				}
				else if($duplicate_user['username'])
				{					
					$insert_user['username'] = $duplicate_user['username']."_phpbb3_import".$total_users;
				}

				echo "Adding user #{$user['user_id']}... ";

				// phpBB 3 Values
				$insert_user['usergroup'] = $this->get_group_id($user['user_id'], true);
				$insert_user['additionalgroups'] = str_replace($insert_user['usergroup'], '', $this->get_group_id($user['user_id']));
				$insert_user['displaygroup'] = $this->get_group_id($user['user_id'], true);
				$insert_user['import_usergroup'] = $this->get_group_id($user['user_id'], true, true);
				$insert_user['import_additionalgroups'] = $this->get_group_id($user['user_id'], false, true);
				$insert_user['import_displaygroup'] = $user['group_id'];
				$insert_user['import_uid'] = $user['user_id'];
				$insert_user['username'] = $user['username'];
				$insert_user['email'] = $user['user_email'];
				$insert_user['regdate'] = $user['user_regdate'];
				$insert_user['postnum'] = $user['user_posts'];
				$insert_user['lastactive'] = $user['user_lastvisit'];
				$insert_user['lastvisit'] = $user['user_lastvisit'];
				$insert_user['website'] = $user['user_website'];
				$insert_user['avatardimensions'] = $user['user_avatar_width'].'x'.$user['user_avatar_height'];
				if($insert_user['avatardimensions'] == '0x0')
				{
					$insert_user['avatardimensions'] = '';
				}
				$insert_user['avatartype'] = $user['user_avatar_type'];
				$insert_user['avatar'] = $user['avatar'];
				$insert_user['lastpost'] = $user['user_lastpost_time'];
				$insert_user['birthday'] = $user['user_birthday'];
				$insert_user['icq'] = $user['user_icq'];
				$insert_user['aim'] = $user['user_aim'];
				$insert_user['yahoo'] = $user['user_yim'];
				$insert_user['msn'] = $user['user_msnm'];
				$insert_user['hideemail'] = int_to_yesno($user['user_allow_viewemail']);
				$insert_user['invisible'] = int_to_yesno($user['user_allow_viewonline']);
				$insert_user['allownotices'] = int_to_yesno($user['user_notify']);
				$insert_user['emailnotify'] = int_to_yesno($user['user_notify']);
				$insert_user['receivepms'] = int_to_yesno($user['user_allow_pm']);
				$insert_user['pmpopup'] = int_to_yesno($user['user_notify_pm']);
				$insert_user['pmnotify'] = int_to_yesno($user['user_notify_pm']);
				$insert_user['timeformat'] = $user['user_dateformat'];
				$insert_user['timezone'] = $user['user_timezone'];
				$insert_user['timezone'] = str_replace(array('.0', '.00'), array('', ''), $insert_user['timezone']);	
				$insert_user['dst'] = $user['user_dst'];
				$insert_user['style'] = $user['user_style'];
				$insert_user['regip'] = $user['user_ip'];
				$insert_user['totalpms'] = $this->get_private_messages($user['user_id']);
				$insert_user['unreadpms'] = $user['user_unread_privmsg'];

				// Default values
				$insert_user['remember'] = "yes";
				$insert_user['showsigs'] = 'yes';
				$insert_user['showavatars'] = 'yes';
				$insert_user['showquickreply'] = "yes";
				$insert_user['ppp'] = "0";
				$insert_user['tpp'] = "0";
				$insert_user['daysprune'] = "0";				
				$insert_user['buddylist'] = "";
				$insert_user['ignorelist'] = "";				
				$insert_user['away'] = "no";
				$insert_user['awaydate'] = "0";
				$insert_user['returndate'] = "0";
				$insert_user['referrer'] = "0";
				$insert_user['reputation'] = "0";				
				$insert_user['timeonline'] = "0";				
				$insert_user['pmfolders'] = '1**Inbox$%%$2**Sent Items$%%$3**Drafts$%%$4**Trash Can';	

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
	
	function import_usergroups()
	{
		global $mybb, $output, $import_session, $db;

		$this->phpbb_db_connect();

		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("groups", "COUNT(*) as count");
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
			$query = $this->old_db->simple_select("groups", "*", "group_id > 6", array('limit_start' => $import_session['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
			while($group = $this->old_db->fetch_array($query))
			{
				echo "Inserting group #{$group['group_id']} as a ";

				// phpBB 3 values
				$insert_group['import_gid'] = $group['group_id'];
				$insert_group['title'] = $group['group_name'];
				$insert_group['description'] = $group['group_desc'];

				// Default values
				$insert_group['type'] = 2;
				$insert_group['namestyle'] = '{username}';
				$insert_group['stars'] = 0;
				$insert_group['starimage'] = 'images/star.gif';
				$insert_group['image'] = '';
				$insert_group['disporder'] = 0;
				$insert_group['isbannedgroup'] = 'no';
				$insert_group['canview'] = 'yes';
				$insert_group['canviewthreads'] = 'yes';
				$insert_group['canviewprofiles'] = 'yes';
				$insert_group['candlattachments'] = 'yes';
				$insert_group['canpostthreads'] = 'yes';
				$insert_group['canpostreplys'] = 'yes';
				$insert_group['canpostattachments'] = 'yes';
				$insert_group['canratethreads'] = 'yes';
				$insert_group['caneditposts'] = 'yes';
				$insert_group['candeleteposts'] = 'yes';
				$insert_group['candeletethreads'] = 'yes';
				$insert_group['caneditattachments'] = 'yes';
				$insert_group['canpostpolls'] = 'yes';
				$insert_group['canvotepolls'] = 'yes';
				$insert_group['canusepms'] = 'yes';
				$insert_group['cansendpms'] = 'yes';
				$insert_group['cantrackpms'] = 'yes';
				$insert_group['candenypmreceipts'] = 'yes';
				$insert_group['pmquota'] = '0';
				$insert_group['maxpmrecipients'] = '5';
				$insert_group['cansendemail'] = 'yes';
				$insert_group['canviewmemberlist'] = 'yes';
				$insert_group['canviewcalendar'] = 'yes';
				$insert_group['canaddpublicevents'] = 'yes';
				$insert_group['canaddprivateevents'] = 'yes';
				$insert_group['canviewonline'] = 'yes';
				$insert_group['canviewwolinvis'] = 'no';
				$insert_group['canviewonlineips'] = 'no';
				$insert_group['cancp'] = 'no';
				$insert_group['issupermod'] = 'no';
				$insert_group['cansearch'] = 'yes';
				$insert_group['canusercp'] = 'yes';
				$insert_group['canuploadavatars'] = 'yes';
				$insert_group['canratemembers'] = 'yes';
				$insert_group['canchangename'] = 'no';
				$insert_group['showforumteam'] = 'no';
				$insert_group['usereputationsystem'] = 'yes';
				$insert_group['cangivereputations'] = 'yes';
				$insert_group['reputationpower'] = '1';
				$insert_group['maxreputationsday'] = '5';
				$insert_group['candisplaygroup'] = 'yes';
				$insert_group['attachquota'] = '0';
				$insert_group['cancustomtitle'] = 'yes';

				echo "custom usergroup...";

				$gid = $this->insert_usergroup($insert_group);

				// Restore connections
				$db->update_query("users", array('usergroup' => $gid), "import_usergroup = '{$group['group_id']}' OR import_displaygroup = '{$group['group_id']}'");

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
	
	function import_forums()
	{
		global $mybb, $output, $import_session, $db;

		$this->phpbb_db_connect();

		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("forums", "COUNT(*) as count");
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
			
			$query = $this->old_db->simple_select("forums", "*", "", array('limit_start' => $import_session['start_forums'], 'limit' => $import_session['forums_per_screen']));
			while($forum = $this->old_db->fetch_array($query))
			{
				echo "Inserting forum #{$forum['forum_id']}... ";

				// phpBB 3 values
				$insert_forum['import_fid'] = intval($forum['forum_id']);
				$insert_forum['name'] = $forum['forum_name'];
				$insert_forum['description'] = $forum['forum_desc'];				
				$insert_forum['disporder'] = $forum['left_id'];
				$insert_forum['threads'] = $forum['forum_topics'];
				$insert_forum['posts'] = $forum['forum_posts'];
				$insert_forum['open'] = int_to_noyes($forum['forum_status']);
				$insert_forum['style'] = $forum['forum_style'];
				$insert_forum['password'] = $forum['forum_password'];
				$insert_forum['unapprovedthreads'] = $this->get_invisible_threads($forum['forum_id']);
				$insert_forum['unapprovedposts'] = $this->get_invisible_posts('', $forum['forum_id']);

				// Are there rules for this forum?
				if($forum['forum_rules_link'])
				{
					$insert_forum['rules'] = $forum['forum_rules_link'];
				}
				else
				{
					$insert_forum['rules'] = $forum['forum_rules'];
				}
				$insert_forum['rulestype'] = 1;

				// We have a category
				if($forum['forum_type'] == '0')
				{
					$insert_forum['type'] = 'c';
					$insert_forum['import_fid'] = (-1 * $forum['forum_id']);
					$insert_forum['lastpost'] = 0;
					$insert_forum['lastposteruid'] = 0;
					$insert_forum['lastposttid'] = 0;
					$insert_forum['lastpostsubject'] = '';
				}
				// We have a forum
				else
				{
					// Is this a redirect forum?
					if($forum['forum_type'] == '2')
					{
						$insert_forum['linkto'] = $forum['forum_link'];
					}
					$insert_forum['type'] = 'f';
					$insert_forum['pid'] = $this->get_import_fid((-1) * $forum['parent_id']);
					$insert_forum['lastpost'] = $forum['forum_last_post_time'];
					$insert_forum['lastposteruid'] = $this->get_import_uid($forum['forum_last_poster_id']);
					$insert_forum['lastposttid'] = ((-1) * $forum['forum_last_post_id']);
					$insert_forum['lastpostsubject'] = $forum['forum_last_post_subject'];
					$insert_forum['lastposter'] = $this->get_import_username($forum['forum_last_poster_id']);
				}

				// Default values
				$insert_forum['parentlist'] = '';
				$insert_forum['active'] = 'yes';
				$insert_forum['allowhtml'] = 'no';
				$insert_forum['allowmycode'] = 'yes';
				$insert_forum['allowsmilies'] = 'yes';
				$insert_forum['allowimgcode'] = 'yes';
				$insert_forum['allowpicons'] = 'yes';
				$insert_forum['allowtratings'] = 'yes';
				$insert_forum['status'] = 1;
				$insert_forum['showinjump'] = 'yes';
				$insert_forum['modposts'] = 'no';
				$insert_forum['modthreads'] = 'no';
				$insert_forum['modattachments'] = 'no';
				$insert_forum['overridestyle'] = 'no';
				$insert_forum['defaultdatecut'] = 0;
				$insert_forum['defaultsortby'] = '';
				$insert_forum['defaultsortorder'] = '';
				$insert_forum['usepostcounts'] = 'yes';
	
				$fid = $this->insert_forum($insert_forum);

				// Update parent list.
				if($forum['forum_type'] == '0')
				{
					$update_array = array('parentlist' => $fid);					
				}
				else
				{
					$update_array = array('parentlist' => $insert_forum['pid'].','.$fid);										
				}

				$db->update_query("forums", $update_array, "fid = {$fid}");

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
	
	function import_threads()
	{
		global $mybb, $output, $import_session, $db;

		$this->phpbb_db_connect();

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("topics", "COUNT(*) as count");
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
			echo "There are ".($import_session['total_threads']-$import_session['start_threads'])." threads left to import and ".round((($import_session['total_threads']-$import_session['start_threads'])/$import_session['threads_per_screen']))." threads left at a rate of {$import_session['threads_per_screen']} per page.<br /><br />";

			$query = $this->old_db->simple_select("topics", "*", "", array('order_by' => 'topic_first_post_id', 'order_dir' => 'DESC', 'limit_start' => $import_session['start_threads'], 'limit' => $import_session['threads_per_screen']));
			while($thread = $this->old_db->fetch_array($query))
			{
				echo "Inserting thread #{$thread['topic_id']}... ";

				// phpBB 3 values
				$insert_thread['import_tid'] = $thread['topic_id'];
				$insert_thread['sticky'] = $thread['topic_type'];
				$insert_thread['fid'] = $this->get_import_fid($thread['forum_id']);
				$insert_thread['firstpost'] = ((-1) * $thread['topic_first_post_id']);			
				$insert_thread['icon'] = ((-1) * $thread['icon_id']);
				$insert_thread['dateline'] = $thread['topic_time'];
				$insert_thread['subject'] = $thread['topic_title'];				
				$insert_thread['poll'] = $this->get_poll_pid($thread['topic_id']);
				$insert_thread['uid'] = $this->get_import_uid($thread['topic_poster']);				
				$insert_thread['import_uid'] = $thread['topic_poster'];
				$insert_thread['username'] = $thread['topic_first_poster_name'];
				$insert_thread['views'] = $thread['topic_views'];
				$insert_thread['replies'] = $thread['topic_replies'];
				$insert_thread['closed'] = int_to_yesno($thread['topic_status']);				
				if($insert_thread['closed'] == 'no')
				{
					$insert_thread['closed'] = '';
				}

				$insert_thread['visible'] = $thread['topic_approved'];
				$insert_thread['lastpost'] = $thread['topic_last_post_time'];
				$insert_thread['lastposter'] = $thread['topic_last_poster_name'];				
				$insert_thread['unapprovedposts'] = $this->get_invisible_posts($thread['topic_id']);	
				$insert_thread['lastposteruid'] = $this->get_import_uid($thread['topic_last_poster_id']);				

				// Default values
				$insert_thread['totalratings'] = '0';
				$insert_thread['notes'] = '';
				$insert_thread['numratings'] = '0';
				$insert_thread['attachmentcount'] = '0';		

				$tid = $this->insert_thread($insert_thread);

				$db->update_query("forums", array('lastposttid' => $tid), "lastposttid = '".((-1) * $thread['topic_id'])."'");

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
	
	function import_icons()
	{
		global $mybb, $output, $import_session, $db;

		$this->phpbb_db_connect();

		// Get number of threads
		if(!isset($import_session['total_icons']))
		{
			$query = $this->old_db->simple_select("icons", "COUNT(*) as count", "icons_id > 10");
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

		$error_phpbbpath = false;

		if(!empty($mybb->input['phpbbpath']))
		{
			$import_session['phpbbpath'] = $mybb->input['phpbbpath'];
			if($import_session['phpbbpath']{strlen($import_session['phpbbpath'])-1} != '/') 
			{
				$import_session['phpbbpath'] .= '/';
			}

			// Doesn't work?
			if($this->url_exists($import_session['phpbbpath'].'adm/index.php') === false)
			{
				echo $import_session['phpbbpath'].'adm/index.php';
				echo "<p><span style=\"color: red;\">The link you provided is not correct. Please enter in a valid url.</span></p>";
				$error_phpbbpath = true;
			}
		}

		// Set uploads path
		if(!isset($import_session['uploadspath']) && !empty($import_session['phpbbpath']) && !$error_phpbbpath)
		{
			$query = $this->old_db->simple_select("config", "config_value", "config_name = 'upload_path'", array('limit' => 1));
			$import_session['uploadspath'] = $import_session['phpbbpath'].$this->old_db->fetch_field($query, 'config_value');
		}

		$phpbbpath = false;

		if(empty($import_session['phpbbpath']) || $error_phpbbpath)
		{
			echo "<p>Please input the link to your phpBB 3 installation. This should be the url you use to access your phpBB 3 forum:</p>
<p><input type=\"text\" name=\"phpbbpath\" value=\"{$import_session['phpbbpath']}\" /></p>";

			$phpbbpath = true;
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

			$query = $this->old_db->simple_select("icons", "*", "icons_id > 10", array('limit_start' => $import_session['start_icons'], 'limit' => $import_session['icons_per_screen']));
			while($icon = $this->old_db->fetch_array($query))
			{
				echo "Inserting icon #{$icon['icons_id']}... ";
				flush(); // Show status as soon as possible to avoid inconsistent status reporting	

				// Invision Power Board 2 values
				$insert_icon['import_iid'] = $icon['icons_id'];
				$insert_icon['name'] = str_replace(array('smilie/', 'misc/'), array('', ''), $icon['icons_url']);
				$insert_icon['path'] = 'images/icons/'.str_replace(array('smilie/', 'misc/'), array('', ''), $icon['icons_url']);				

				$iid = $this->insert_icon($insert_icon);

				// Restore connections
				$db->update_query("threads", array('icon' => $iid), "icon = '".((-1) * $icon['icons_id'])."'");

				// Transfer icons
				if(file_exists($import_session['phpbbpath'].$icon['icons_url']))
				{
					$icondata = file_get_contents($import_session['phpbbpath'].$icon['icons_url']);
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

				echo "done.<br />\n";			
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

	function import_polls()
	{
		global $mybb, $output, $import_session, $db;

		$this->phpbb_db_connect();

		// Get number of threads
		if(!isset($import_session['total_polls']))
		{
			$query = $this->old_db->simple_select("poll_votes", "COUNT(*) as count");
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
			$done_array = array();

			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_polls']-$import_session['start_polls'])." polls left to import and ".round((($import_session['total_polls']-$import_session['start_polls'])/$import_session['polls_per_screen']))." pages left at a rate of {$import_session['polls_per_screen']} per page.<br /><br />";

			$query = $this->old_db->simple_select("poll_options", "*", "", array('order_by' => 'topic_id', 'limit_start' => $import_session['start_polls'], 'limit' => $import_session['polls_per_screen']));
			while($poll = $this->old_db->fetch_array($query))
			{
				if(in_array($poll['topic_id'], $done_array))
				{
					continue;
				}

				echo "Inserting poll of topic #{$poll['topic_id']}... ";		

				// phpBB 3 values
				$insert_poll['import_pid'] = 0;
				$insert_poll['tid'] = $this->get_import_tid($poll['topic_id']);

				$query1 = $this->old_db->simple_select("topics", "poll_title,poll_start,poll_length", "topic_id = '{$poll['topic_id']}'");
				$poll_details = $this->old_db->fetch_array($query1);

				$seperator = '';
				$options = '';
				$votes = '';
				$vote_count = 0;
				$options_count = 0;

				$query2 = $this->old_db->simple_select("poll_options", "*", "topic_id = '{$poll['topic_id']}'");
				while($vote_result = $this->old_db->fetch_array($query2))
				{
					$options .= $seperator.$db->escape_string($vote_result['poll_option_text']);
					$votes .= $seperator.$vote_result['poll_option_total'];
					++$options_count;
					$vote_count += $vote_result['poll_option_total'];
					$seperator = '||~|~||';
				}

				$insert_poll['question'] = $poll_details['poll_title'];
				$insert_poll['dateline'] = $poll_details['poll_start'];
				$insert_poll['options'] = $options;
				$insert_poll['votes'] = $votes;
				$insert_poll['numoptions'] = $options_count;
				$insert_poll['numvotes'] = $vote_count;
				$insert_poll['timeout'] = $poll_details['poll_length'];

				// Default values
				$insert_poll['multiple'] = 'no';
				$poll['closed'] = '';				

				$pid = $this->insert_poll($insert_poll);

				$done_array[] = $poll['topic_id'];

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

		$this->phpbb_db_connect();

		// Get number of threads
		if(!isset($import_session['total_pollvotes']))
		{
			$query = $this->old_db->simple_select("poll_votes", "COUNT(*) as count");
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

			$query = $this->old_db->simple_select("poll_votes", "*", "", array('limit_start' => $import_session['start_pollvotes'], 'limit' => $import_session['pollvotes_per_screen']));
			while($pollvote = $this->old_db->fetch_array($query))
			{
				echo "Inserting poll vote of topic #{$pollvote['topic_id']}... ";				

				$query1 = $db->simple_select("threads", "dateline,poll", "tid = '".$this->get_import_tid($pollvote['topic_id'])."'");
				$poll = $db->fetch_array($query1);

				$insert_pollvote['uid'] = $this->get_import_uid($pollvote['vote_user_id']);
				$insert_pollvote['dateline'] = $poll['dateline'];
				$insert_pollvote['voteoption'] = $pollvote['poll_option_id'];
				$insert_pollvote['pid'] = $poll['poll'];

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

	function import_posts()
	{
		global $mybb, $output, $import_session, $db;

		$this->phpbb_db_connect();

		// Get number of posts
		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->simple_select("posts", "COUNT(*) as count");
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

			$query = $this->old_db->simple_select("posts", "*", "", array('limit_start' => $import_session['start_posts'], 'limit' => $import_session['posts_per_screen']));
			while($post = $this->old_db->fetch_array($query))
			{
				echo "Inserting post #{$post['post_id']}... ";

				// phpBB 3 values
				$insert_post['import_pid'] = $post['post_id'];
				$insert_post['tid'] = $this->get_import_tid($post['topic_id']);

				// Get Username
				$topic_poster = $this->get_user($post['poster_id']);
				$post['username'] = $topic_poster['username'];

				$insert_post['fid'] = $this->get_import_fid($post['forum_id']);
				$insert_post['subject'] = $post['post_subject'];				
				$insert_post['uid'] = $this->get_import_uid($post['poster_id']);
				$insert_post['import_uid'] = $post['poster_id'];
				$insert_post['username'] = $this->get_import_username($insert_post['import_uid']);
				$insert_post['dateline'] = $post['post_time'];
				$insert_post['message'] = str_replace(':'.$post['bbcode_uid'], '', htmlspecialchars_decode($post['post_text']));
				$insert_post['ipaddress'] = $post['poster_ip'];
				$insert_post['includesig'] = int_to_yesno($post['enable_sig']);		
				$insert_post['smilieoff'] = int_to_noyes($post['enable_smilies']);

				// Default values
				$insert_post['pid'] = 0;
				$insert_post['icon'] = 0;	
				$insert_post['edituid'] = 0;				
				$insert_post['edittime'] = 0;
				$insert_post['visible'] = 1;
				$insert_post['posthash'] = '';

				$pid = $this->insert_post($insert_post);

				// Update thread count
				update_thread_count($insert_post['tid']);

				// Restore first post connections
				$db->update_query("threads", array('firstpost' => $pid), "tid = '{$insert_post['tid']}' AND firstpost = '".((-1) * $import_post['pid'])."'");
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

		$this->phpbb_db_connect();		

		// Get number of threads
		if(!isset($import_session['total_attachments']))
		{
			$query = $this->old_db->simple_select("attachments", "COUNT(*) as count");
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

		$error_phpbbpath = false;

		if(!empty($mybb->input['phpbbpath']))
		{
			$import_session['phpbbpath'] = $mybb->input['phpbbpath'];
			if($import_session['phpbbpath']{strlen($import_session['phpbbpath'])-1} != '/') 
			{
				$import_session['phpbbpath'] .= '/';
			}

			// Doesn't work?
			if($this->url_exists($import_session['phpbbpath'].'adm/index.php') === false)
			{
				echo $import_session['phpbbpath'].'adm/index.php';
				echo "<p><span style=\"color: red;\">The link you provided is not correct. Please enter in a valid url.</span></p>";
				$error_phpbbpath = true;
			}
		}

		// Set uploads path
		if(!isset($import_session['uploadspath']) && !empty($import_session['phpbbpath']) && !$error_phpbbpath)
		{
			$query = $this->old_db->simple_select("config", "config_value", "config_name = 'upload_path'", array('limit' => 1));
			$import_session['uploadspath'] = $import_session['phpbbpath'].$this->old_db->fetch_field($query, 'config_value');
		}

		$phpbbpath = false;

		if(empty($import_session['phpbbpath']) || $error_phpbbpath)
		{
			echo "<p>Please input the link to your phpBB 3 installation. This should be the url you use to access your phpBB 3 forum:</p>
<p><input type=\"text\" name=\"phpbbpath\" value=\"{$import_session['phpbbpath']}\" /></p>";

			$phpbbpath = true;
		}

		if(empty($import_session['attachments_per_screen']) || $phpbbpath)
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

			$query = $this->old_db->simple_select("attachments", "*", "", array('limit_start' => $import_session['start_attachments'], 'limit' => $import_session['attachments_per_screen']));
			while($attachment = $this->old_db->fetch_array($query))
			{
				echo "Inserting attachment #{$attachment['attach_id']}... ";
				flush(); // We do this to show status because the transfer of the file might take a while. Otherwise the user will just think the script froze.

				// phpBB 3 values
				$insert_attachment['import_aid'] = $attachment['attach_id'];
				$insert_attachment['pid'] = $this->get_import_pid($attachment['post_msg_id']);				
				$insert_attachment['uid'] = $this->get_import_uid($attachment['poster_id']);
				$insert_attachment['filename'] = $attachment['real_filename'];
				$insert_attachment['attachname'] = "post_".$insert_attachment['uid']."_".$attachment['filetime'].".attach";;
				$insert_attachment['filetype'] = $attachment['mimetype'];
				$insert_attachment['filesize'] = $attachment['filesize'];
				$insert_attachment['downloads'] = $attachment['download_count'];

				// Default values
				$insert_attachment['visible'] = 'yes';
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

				// Transfer attachment
				$file_not_transfered = "";
				if(file_exists($import_session['uploadspath'].'/'.$attachment['physical_filename']))
				{
					// Get the contents
					$attachmentdata = file_get_contents($import_session['uploadspath'].'/'.$attachment['physical_filename']);
					
					// Put the contents
					$file = fopen($mybb->settings['uploadspath'].'/'.$insert_attachment['attachname'], 'w');
					fwrite($file, $attachmentdata);
					fclose($file);
					
					// Give permissions
					@chmod($mybb->settings['uploadspath'].'/'.$insert_attachment['attachname'], 0777);
				}
				else
				{
					$file_not_transfered = " (Note: The file could not be found)";
				}

				if(!$posthash)
				{
					// Restore connection
					$db->update_query("posts", array('posthash' => $insert_attachment['posthash']), "pid = '{$insert_attachment['pid']}'");
				}
				$db->query("UPDATE ".TABLE_PREFIX."threads SET attachmentcount = attachmentcount + 1 WHERE tid = '".$posthash['tid']."'");

				echo "done. {$file_not_transfered}<br />\n";
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

		$this->phpbb_db_connect();

		// Get number of usergroups
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("privmsgs", "COUNT(*) as count");
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

			$query = $this->old_db->simple_select("privmsgs", "*", "", array('limit_start' => $import_session['start_privatemessages'], 'limit' => $import_session['privatemessages_per_screen']));

			while($pm = $this->old_db->fetch_array($query))
			{
				echo "Inserting Private Message #{$pm['msg_id']}... ";

				// phpBB 3 values
				$insert_pm['pmid'] = null;
				$insert_pm['import_pmid'] = $pm['msg_id'];
				$insert_pm['uid'] = $this->get_import_uid($pm['author_id']);
				$insert_pm['fromid'] = $this->get_import_uid($pm['author_id']);
				$insert_pm['toid'] = $this->get_import_uid($pm['to_address']);
				$insert_pm['recipients'] = 'a:1:{s:2:"to";a:1:{i:0;s:'.strlen($insert_pm['toid']).':"'.$insert_pm['toid'].'";}}';				
				$insert_pm['subject'] = $pm['message_subject'];				
				$insert_pm['readtime'] = time();
				$insert_pm['dateline'] = $pm['message_time'];
				$insert_pm['message'] = str_replace($pm['bbcode_uid'], '', htmlspecialchars_decode($pm['message_text']));
				$insert_pm['includesig'] = int_to_yesno($pm['enable_sig']);
				$insert_pm['smilieoff'] = int_to_noyes($pm['enable_smilies']);
				if($insert_pm['smilieoff'] == 'no')
				{
					$insert_pm['smilieoff'] = '';
				}

				// Default values
				$insert_pm['folder'] = '1';
				$insert_pm['icon'] = '0';
				$insert_pm['status'] = '1';
				$insert_pm['receipt'] = '2';

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

		$this->phpbb_db_connect();

		// Get number of moderators
		if(!isset($import_session['total_mods']))
		{
			$query = $this->old_db->simple_select("moderator_cache", "COUNT(*) as count");
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

			$query = $this->old_db->simple_select("moderator_cache", "*", "", array('limit_start' => $import_session['start_mods'], 'limit' => $import_session['mods_per_screen']));
			while($mod = $this->old_db->fetch_array($query))
			{
				echo "Inserting user #{$mod['user_id']} as moderator to forum #{$mod['forum_id']}... ";

				// phpBB 3 values
				$insert_mod['fid'] = $this->get_import_fid($mod['forum_id']);
				$insert_mod['uid'] = $this->get_import_uid($mod['user_id']);

				// Default values
				$insert_mod['caneditposts'] = 'yes';
				$insert_mod['candeleteposts'] = 'yes';
				$insert_mod['canviewips'] = 'yes';
				$insert_mod['canopenclosethreads'] = 'yes';
				$insert_mod['canmanagethreads'] = 'yes';
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

		$this->phpbb_db_connect();

		// Get number of smilies
		if(!isset($import_session['total_smilies']))
		{
			$query = $this->old_db->simple_select("smilies", "COUNT(*) as count", "smiley_id > 23");
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

		$error_phpbbpath = false;

		if(!empty($mybb->input['phpbbpath']))
		{
			$import_session['phpbbpath'] = $mybb->input['phpbbpath'];
			if($import_session['phpbbpath']{strlen($import_session['phpbbpath'])-1} != '/') 
			{
				$import_session['phpbbpath'] .= '/';
			}

			// Doesn't work?
			if(!file_exists($import_session['phpbbpath'].'adm/index.php'))
			{
				echo $import_session['phpbbpath'].'adm/index.php';
				echo "<p><span style=\"color: red;\">The link you provided is not correct. Please enter in a valid url.</span></p>";
				$error_phpbbpath = true;
			}
		}

		// Set uploads path
		if(!isset($import_session['smiliesurl']) && !empty($import_session['phpbbpath']) && !$error_phpbbpath)
		{
			$query = $this->old_db->simple_select("config", "config_value", "config_name = 'smilies_path'", array('limit' => 1));
			$import_session['smiliesurl'] = $import_session['phpbbpath'].$this->old_db->fetch_field($query, 'config_value');
		}

		$phpbbpath = false;

		if(empty($import_session['phpbbpath']) || $error_phpbbpath)
		{
			echo "<p>Please input the link to your phpBB 3 installation. This should be the url you use to access your phpBB 3 forum:</p>
<p><input type=\"text\" name=\"phpbbpath\" value=\"{$import_session['phpbbpath']}\" /></p>";

			$phpbbpath = true;
		}

		if(empty($import_session['smilies_per_screen']))
		{
			$import_session['start_smilies'] = 0;
			echo "<p>Please select how many smilies to import at a time:</p>
<p><input type=\"text\" name=\"smilies_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_smilies']-$import_session['start_smilies'])." smilies left to import and ".round((($import_session['total_smilies']-$import_session['start_smilies'])/$import_session['smilies_per_screen']))." pages left at a rate of {$import_session['smilies_per_screen']} per page.<br /><br />";
			
			$query = $this->old_db->simple_select("smilies", "*", "smiley_id > 23", array('limit_start' => $import_session['start_smilies'], 'limit' => $import_session['smilies_per_screen']));
			while($smilie = $this->old_db->fetch_array($query))
			{
				echo "Inserting smilie #{$smilie['smiley_id']}... ";
				flush(); // Show status as soon as possible to avoid inconsistent status reporting

				// phpBB 3 values
				$insert_smilie['name'] = $smilie['emotion'];
				$insert_smilie['find'] = $smilie['code'];
				$insert_smilie['image'] = 'images/smilies/'.$smilie['smiley_url'];
				$insert_smilie['disporder'] = $smilie['smiley_order'];
				$insert_smilie['showclickable'] = int_to_yesno($smilie['display_on_posting']);				

				$this->insert_smilie($insert_smilie);

				// Transfer smilies
				if(file_exists($import_session['smiliesurl'].$smilie['smiley_url']))
				{
					$smiliedata = file_get_contents($import_session['smiliesurl'].$smilie['smiley_url']);
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

		$this->phpbb_db_connect();

		// What settings do we need to get and what is their MyBB equivalent?
		$settings_array = array(
			"avatar_path" => "avataruploadpath",
			"avatar_max_height" => "maxavatardims",
			"avatar_max_width" => "maxavatardims",
			"avatar_gallery_path" => "avatardir",
			"avatar_filesize" => "avatarsize",
			"sitename" => "bbname",
			"max_sig_chars" => "siglength",
			"hot_threshold" => "hottopic",
			"max_poll_options" => "maxpolloptions",
			"allow_privmsg" => "enablepms",
			"board_timezone" => "timezoneoffset",
			"board_email" => "adminemail",
			"posts_per_page" => "postsperpage",
			"topics_per_page" => "threadsperpage",
			"flood_interval" => "postfloodsecs",
			"search_interval" => "searchfloodtime",
			"min_search_author_chars" => "minsearchword",
			"enable_confirm" => "captchaimage",
			"max_login_attempts" => "failedlogincount",
			"gzip_compress" => "gzipoutput",
			"search_type" => "searchtype",
			/* MyBB 1.4
			"smtp_host"		=> "smtp_host",
			"smtp_password"	=> "smtp_pass",
			"smtp_port"		=> "smtp_port",
			"smtp_username"	=> "smtp_user",
			"smtp_delivery" => "mail_handler"
			*/
		);

		$settings = "'".implode("','", array_keys($settings_array))."'";

		$int_to_yes_no = array(
			"allow_privmsg" => 1,
			"gzip_compress" => 1
		);

		$int_to_on_off = array(
			"enable_confirm" => 1
		);

		// Get number of threads
		if(!isset($import_session['total_settings']))
		{
			$query = $this->old_db->simple_select("config", "COUNT(*) as count", "config_name IN({$settings})");
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

			$query = $this->old_db->simple_select("config", "config_name, config_value", "config_name IN({$settings})", array('limit_start' => $import_session['start_settings'], 'limit' => $import_session['settings_per_screen']));

			while($setting = $this->old_db->fetch_array($query))
			{
				// phpBB 3 values
				$name = $settings_array[$setting['config_name']];
				$value = $setting['config_value'];

				echo "Updating setting ".$setting['config_name']." from the phpBB database to {$name} in the MyBB database... ";
				
				if($setting['config_name'] == "avatar_max_height")
				{
					$avatar_setting = "x".$value;
					echo "done.<br />\n";
					continue;
				}
				else if($setting['config_name'] == "avatar_max_width")
				{
					$value = $value.$avatar_setting;
					unset($avatar_setting);
				}
				
				if($setting['config_name'] == "avatar_filesize")
				{
					$value = ceil($value / 1024);
				}

				if(($value == 0 || $value == 1) && isset($int_to_yes_no[$setting['config_name']]))
				{
					$value = int_to_yes_no($value, $int_to_yes_no[$setting['config_name']]);
				}
				
				if(($value == 0 || $value == 1) && isset($int_to_on_off[$setting['config_name']]))
				{
					$value = int_to_on_off($value, $int_to_on_off[$setting['config_name']]);
				}
				
				if($setting['config_name'] == 'search_type')
				{
					$value = "fulltext";
				}

				if($setting['config_name'] == 'board_timezone')
				{
					if(strpos($value, '-') === false && $value != 0)
					{
						$value = "+" . $value;
					}
				}

				/* MyBB 1.4
				if($setting['config_name'] == "smtp_delivery" && $value == 1)
				{
					$value = "smtp";
				}
				*/

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

	function import_attachtypes()
	{
		global $mybb, $output, $import_session, $db;

		$this->phpbb_db_connect();

		// Get number of attachment types
		if(!isset($import_session['total_attachtypes']))
		{
			$query = $this->old_db->simple_select("extensions", "COUNT(*) as count");
			$import_session['total_attachtypes'] = $this->old_db->fetch_field($query, 'count');
		}

		if($import_session['start_attachtypes'])
		{
			// If there are more attachment types to do, continue, or else, move onto next module
			if($import_session['total_attachtypes'] - $import_session['start_attachtypes'] <= 0)
			{
				$import_session['disabled'][] = 'import_attachtypes';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of attachment types per screen from form
		if(isset($mybb->input['attachtypes_per_screen']))
		{
			$import_session['attachtypes_per_screen'] = intval($mybb->input['attachtypes_per_screen']);
		}
		
		$error_phpbbpath = false;
		
		if(!empty($mybb->input['phpbbpath']))
		{
			$import_session['phpbbpath'] = $mybb->input['phpbbpath'];
			if($import_session['phpbbpath']{strlen($import_session['phpbbpath'])-1} != '/') 
			{
				$import_session['phpbbpath'] .= '/';
			}
			
			
			// Doesn't work?
			if(!file_exists($import_session['phpbbpath'].'adm/index.php'))
			{
				echo $import_session['phpbbpath'].'adm/index.php';
				echo "<p><span style=\"color: red;\">The link you provided is not correct. Please enter in a valid url.</span></p>";
				$error_phpbbpath = true;
			}
		}
		
		// Set uploads path
		if(!isset($import_session['uploadiconsurl']) && !empty($import_session['phpbbpath']) && !$error_phpbbpath)
		{
			$query = $this->old_db->simple_select("config", "config_value", "config_name = 'upload_icons_path'", array('limit' => 1));
			$import_session['uploadiconsurl'] = $import_session['phpbbpath'].$this->old_db->fetch_field($query, 'config_value');
		}
					
		$phpbbpath = false;
		
		if(empty($import_session['phpbbpath']) || $error_phpbbpath)
		{
			echo "<p>Please input the link to your phpBB 3 installation. This should be the url you use to access your phpBB 3 forum:</p>
<p><input type=\"text\" name=\"phpbbpath\" value=\"{$import_session['phpbbpath']}\" /></p>";

			$phpbbpath = true;
		}
		
		if(empty($import_session['attachtypes_per_screen']))
		{
			$import_session['start_attachtypes'] = 0;
			echo "<p>Please select how many attachment types to import at a time:</p>
<p><input type=\"text\" name=\"attachtypes_per_screen\" value=\"200\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_attachtypes']-$import_session['start_attachtypes'])." attachment types left to import and ".round((($import_session['total_attachtypes']-$import_session['start_attachtypes'])/$import_session['attachtypes_per_screen']))." pages left at a rate of {$import_session['attachtypes_per_screen']} per page.<br /><br />";
			
			// Get existing attachment types
			$query = $db->simple_select("attachtypes", "extension");
			while($row = $db->fetch_array($query))
			{
				$existing_types[$row['extension']] = true;
			}
			
			// Get default max filesize
			$query = $this->old_db->simple_select("config", "config_value", "config_name='max_filesize'");
			$default_max_filesize = $this->old_db->fetch_field($query, 'config_value');
			$default_max_filesize = round(intval($default_max_filesize) / 1000);
			
			$query = $this->old_db->query("
				SELECT e.*, g.* 
				FROM {$this->old_db->table_prefix}extensions e
				LEFT JOIN {$this->old_db->table_prefix}extension_groups g ON (e.group_id = g.group_id)
				LIMIT {$import_session['start_attachtypes']}, {$import_session['attachtypes_per_screen']}
			");
			while($type = $this->old_db->fetch_array($query))
			{

				echo "Inserting attachment type #{$type['extension_id']}... ";				

				$insert_attachtype['import_atid'] = $type['extension_id'];
				$insert_attachtype['name'] = $type['extension'].' - '.$type['group_name'];
				$insert_attachtype['mimetype'] = '';
				$insert_attachtype['extension'] = $type['extension'];
				if($type['max_filesize'] == 0)
				{
					$insert_attachtype['maxsize'] = $default_max_filesize;
				}
				else
				{
					$insert_attachtype['maxsize'] = round($type['max_filesize'] / 1000);
				}
				$insert_attachtype['icon'] = '';
				if(!empty($type['upload_icon']))
				{
					$insert_attachtype['icon'] = 'images/attachtypes/'.$type['upload_icon'];
				}
				
				$this->insert_attachtype($insert_attachtype);
				
				if(isset($existing_types[$type['extension']]))
				{
					echo " (Note: extension already exists)\n";
				}
				
				// Transfer attachment icons
				if(file_exists($import_session['uploadiconsurl'].$type['upload_icon']))
				{
					$attachicondata = file_get_contents($import_session['uploadiconsurl'].$type['upload_icon']);
					$file = fopen(MYBB_ROOT.$insert_attachtype['icon'], 'w');
					fwrite($file, $attachicondata);
					fclose($file);
					@chmod(MYBB_ROOT.$insert_attachtype['icon'], 0777);
				}
				else
				{
					echo " (Note: Could not transfer attachment icons. - \"Not Found\")";
				}
				
				echo "<br />\n";
				++$i;
			}
			
			if($import_session['total_attachtypes'] == 0)
			{
				echo "There are no attachment types to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_attachtypes'] += $import_session['attachtypes_per_screen'];
		$output->print_footer();
	}
	
	/**
	 * Count number of invisible posts from the phpBB 3 database
	 *
	 * @param int thread id (optional)
	 * @param int forum id (optional)
	 * @return int number of invisible posts
	 */
	function get_invisible_posts($tid='', $fid='')
	{
		$tidbit = "";
		if(!empty($tid))
		{
			$tidbit = " AND topic_id = '".intval($tid)."'";
		}
		
		$fidbit = "";
		if(!empty($fid))
		{
			$fidbit = " AND forum_id = '".intval($fid)."'";
		}
		
		$query = $this->old_db->simple_select("posts", "COUNT(*) as invisible", "post_approved = '0'{$tidbit}{$fidbit}");
		return $this->old_db->fetch_field($query, "invisible");
	}
	
	/**
	 * Count number of invisible threads from the phpBB 3 database
	 *
	 * @param int forum id (optional)
	 * @return int number of invisible threads
	 */
	function get_invisible_threads($fid='')
	{
		$fidbit = "";
		if(!empty($fid))
		{
			$fidbit = " AND forum_id = '".intval($fid)."'";
		}
		
		$query = $this->old_db->simple_select("topics", "COUNT(*) as invisible", "topic_approved = '0'{$fidbit}");
		return $this->old_db->fetch_field($query, "invisible");
	}
	
	/**
	 * Get poll option id from the phpBB 3 database
	 *
	 * @param int thread id
	 * @return int poll option id
	 */
	function get_poll_pid($tid)
	{
		$query = $this->old_db->simple_select("poll_options", "poll_option_id", "topic_id = '{$tid}'", array('limit' => 1));
		return $this->old_db->fetch_field($query, "poll_option_id");
	}
	
	/**
	 * Get total number of Private Messages the user has from the phpBB database
	 *
	 * @param int User ID
	 * @return int Number of Private Messages
	 */
	function get_private_messages($uid)
	{
		$query = $this->old_db->simple_select("privmsgs", "COUNT(*) as pms", "to_address = '{$uid}' OR author_id = '{$uid}'");
		
		return $this->old_db->fetch_field($query, 'pms');
	}
	
	/**
	 * Get a post from the phpBB database
	 *
	 * @param int Post ID
	 * @return array The post
	 */
	function get_post($pid)
	{		
		$query = $this->old_db->simple_select("posts", "*", "post_id = '{$pid}'", array('limit' => 1));
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Get a user from the phpBB database
	 *
	 * @param int User ID
	 * @return array If the uid is 0, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_user($uid)
	{
		if($uid == 0)
		{
			return array(
				'username' => 'Guest',
				'user_id' => 0,
			);
		}
		
		$query = $this->old_db->simple_select("users", "*", "user_id = '{$uid}'", array('limit' => 1));
		
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Checks if a URL exists (if it is correct or not)
	 *
	 * @param string url to check
	 * @return boolean true if the url is correct, false otherwise
	 */
	function url_exists($url)
	{
		$handle = @fopen($url, "r");
 		if ($handle === false)
		{
  			return false;
		}
		
 		fclose($handle);		
 		return true;
	}
	
	/**
	 * Gets the time of the last post of a user from the phpBB database
	 *
	 * @param int User ID
	 * @return int Last post time
	 */
	function get_last_post($uid)
	{
		$query = $this->old_db->simple_select("posts", "post_time", "poster_id = '{$uid}'", array('order_by' => 'post_time', 'order_dir' => 'DESC', 'limit' => 1));
		return $this->old_db->fetch_field($query, "post_time");
	}
	
	/**
	 * Convert a phpBB 3 group ID into a MyBB group ID
	 *
	 * @param int Group ID
	 * @param boolean single group or multiple?
	 * @param boolean original group values?
	 * @return mixed group id(s)
	 */
	function get_group_id($uid, $not_multiple=false, $orig=false)
	{
		$settings = array();
		if($not_multiple == false)
		{
			$query = $this->old_db->simple_select("user_group", "COUNT(*) as rows", "user_id = '{$uid}'");
			$settings = array('limit_start' => '1', 'limit' => $this->old_db->fetch_field($query, 'rows'));
		}
		
		$query = $this->old_db->simple_select("user_group", "*", "user_id = '{$uid}'", $settings);
		
		$comma = $group = '';
		while($phpbbgroup = $this->old_db->fetch_array($query))
		{
			if($orig == true)
			{
				$group .= $phpbbgroup['group_id'].$comma;
			}
			else
			{
				// Deal with non-activated people
				if($phpbbgroup['user_pending'] != '0')
				{
					return 5;
				}
				
				$group .= $comma;
				switch($phpbbgroup['group_id'])
				{
					case 1: // Guests
					case 6: // Bots
						$group .= 1;
						break;
					case 2: // Register
					case 3: // Registered coppa
						$group .= 2;
						break;
					case 4: // Super Moderator
						$group .= 3;
						break;
					case 5: // Administrator
						$group .= 4;
						break;
					default:
						$gid = $this->get_import_gid($phpbbgroup['group_id']);
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
}

?>