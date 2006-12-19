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
 
// Board Name: SMF 1.1

class Convert_smf extends Converter {
	var $bbname = "SMF 1.1";
	var $modules = array("db_configuration" => array("name" => "Database Configuration",
									  "dependencies" => ""),
						 "import_usergroups" => array("name" => "Import SMF Usergroups",
									  "dependencies" => "db_configuration"),
						 "import_users" => array("name" => "Import SMF Users",
									  "dependencies" => "db_configuration"),
						 "import_categories" => array("name" => "Import SMF Categories",
									  "dependencies" => "db_configuration"),
						 "import_forums" => array("name" => "Import SMF Forums",
									  "dependencies" => "db_configuration,import_categories"),
						 "import_threads" => array("name" => "Import SMF Threads",
									  "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Import SMF Polls",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Import SMF Poll Votes",
									  "dependencies" => "db_configuration,import_polls"),
						 "import_icons" => array("name" => "Import SMF Icons",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_posts" => array("name" => "Import SMF Posts",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_privatemessages" => array("name" => "Import SMF Private Messages",
						 			  "dependencies" => "db_configuration,import_users"),
						 "import_moderators" => array("name" => "Import SMF Moderators",
									  "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_smilies" => array("name" => "Import SMF Smilies",
									  "dependencies" => "db_configuration"),
						);

	function smf_db_connect()
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
		
		define('SMF_TABLE_PREFIX', $import_session['old_tbl_prefix']);
	}

	function db_configuration()
	{
		global $mybb, $output, $import_session, $db, $dboptions;

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

				// Need to check if SMF is actually installed here
				$this->old_db->set_table_prefix($mybb->input['tableprefix']);
				if(!$this->old_db->table_exists("members"))
				{
					$errors[] = "The SMF table '{$mybb->input['tableprefix']}members' could not be found in database '{$mybb->input['dbname']}'.  Please ensure SMF exists at this database and with this table prefix.";
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

		$output->print_header("SMF Database Configuration");

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
			echo "<p>Please enter the database details for your current installation of SMF.</p>";
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

		echo <<<EOF
<div class="border_wrapper">
<div class="title">SMF Database Configuration</div>
<table class="general" cellspacing="0">
<tr>
	<th colspan="2" class="first last">Database Settings</th>
</tr>
<tr class="first">
	<td class="first"><label for="dbengine">Database Engine:</label></td>
	<td class="last alt_col"><select name="dbengine" id="dbengine">{$dbengines}</select></td>
</tr>
<tr class="alt_row">
	<td class="first"><label for="dbhost">Database Host:</label></td>
	<td class="last alt_col"><input type="text" class="text_input" name="dbhost" id="dbhost" value="{$dbhost}" /></td>
</tr>
<tr>
	<td class="first"><label for="dbuser">Database Username:</label></td>
	<td class="last alt_col"><input type="text" class="text_input" name="dbuser" id="dbuser" value="{$dbuser}" /></td>
</tr>
<tr class="alt_row">
	<td class="first"><label for="dbpass">Database Password:</label></td>
	<td class="last alt_col"><input type="password" class="text_input" name="dbpass" id="dbpass" value="" /></td>
</tr>
<tr class="last">
	<td class="first"><label for="dbname">Database Name:</label></td>
	<td class="last alt_col"><input type="text" class="text_input" name="dbname" id="dbname" value="{$dbname}" /></td>
</tr>
<tr>
	<th colspan="2" class="first last">Table Settings</th>
</tr>
<tr class="last">
	<td class="first"><label for="tableprefix">Table Prefix:</label></td>
	<td class="last alt_col"><input type="text" class="text_input" name="tableprefix" id="tableprefix" value="{$tableprefix}" /></td>
</tr>
</table>
</div>
<p>Once you have checked these details are correct, click next to continue.</p>
EOF;
		$output->print_footer();
	}

	// Loop through database engines
	function import_users()
	{
		global $mybb, $output, $import_session, $db;

		$this->smf_db_connect();

		// Get number of members
		if(!isset($import_session['total_members']))
		{
			$query = $this->old_db->simple_select("members", "COUNT(*) as count");
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
			$query = $this->old_db->simple_select("members", "*", "", array('limit_start' => $import_session['start_users'], 'limit' => $import_session['users_per_screen']));

			while($user = $this->old_db->fetch_array($query))
			{
				++$total_users;
					
				$query1 = $db->simple_select("users", "username,email,uid", " LOWER(username)='".$db->escape_string(my_strtolower($user['memberName']))."'");
				$duplicate_user = $db->fetch_array($query1);
				if($duplicate_user['username'] && my_strtolower($user['emailAddress']) == my_strtolower($duplicate_user['email']))
				{
					echo "Merging user #{$user['ID_MEMBER']} with user #{$duplicate_user['uid']}... ";
					$db->update_query("users", array('import_uid' => $user['ID_MEMBER']), "uid = '{$duplicate_user['uid']}'");
					echo "done.<br />";
					
					continue;
				}
				else if($duplicate_user['username'])
				{
					$user['memberName'] = $duplicate_user['username']."_smf1.1_import".$total_users;
				}
				
				echo "Adding user #{$user['ID_MEMBER']}... ";
				
				$insert_user['usergroup'] = $this->get_group_id($user, 'ID_GROUP');
				$insert_user['additionalgroups'] = $this->get_group_id($user, 'additionalGroups');
				$insert_user['displaygroup'] = $insert_user['usergroup'];
				$insert_user['import_usergroup'] = $user['ID_GROUP'];
				$insert_user['import_additionalgroups'] = $user['additionalGroups'];
				$insert_user['import_displaygroup'] = $user['ID_GROUP'];
				$insert_user['import_uid'] = $user['ID_MEMBER'];
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
				$insert_user['timezone'] = str_replace(array('.0', '.00'), array('', ''), $insert_user['timezone']);	
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
				$uid = $this->insert_user($insert_user);
				
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no Users to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_users'] += $import_session['users_per_screen'];
		$output->print_footer();
	}
	
	function import_categories()
	{
		global $mybb, $output, $import_session, $db;

		$this->smf_db_connect();

		// Get number of categories
		if(!isset($import_session['total_cats']))
		{
			$query = $this->old_db->simple_select("categories", "COUNT(*) as count");
			$import_session['total_cats'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_cats'])
		{
			// If there are more categories to do, continue, or else, move onto next module
			if($import_session['total_cats'] - $import_session['start_cats'] <= 0)
			{
				$import_session['disabled'][] = 'import_categories';
				return "finished";
			}
		}

		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of categories per screen from form
		if(isset($mybb->input['cats_per_screen']))
		{
			$import_session['cats_per_screen'] = intval($mybb->input['cats_per_screen']);
		}
		
		if(empty($import_session['cats_per_screen']))
		{
			$import_session['start_cats'] = 0;
			echo "<p>Please select how many categories to import at a time:</p>
<p><input type=\"text\" name=\"cats_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_cats']-$import_session['start_cats'])." categories left to import and ".round((($import_session['total_cats']-$import_session['start_cats'])/$import_session['cats_per_screen']))." pages left at a rate of {$import_session['cats_per_screen']} per page.<br /><br />";
			
			$query = $this->old_db->simple_select("categories", "*", "", array('limit_start' => $import_session['start_cats'], 'limit' => $import_session['cats_per_screen']));
			while($cat = $this->old_db->fetch_array($query))
			{
				echo "Inserting category #{$cat['ID_CAT']}... ";
				
				// Values from SMF
				$insert_forum['import_fid'] = (-1 * intval($cat['ID_CAT']));
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
				$db->update_query("forums", $update_array, "fid = '{$fid}'");
				
				echo "done.<br />\n";	
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no Categories to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}			
		$import_session['start_cats'] += $import_session['cats_per_screen'];
		$output->print_footer();
	}
	
	function import_forums()
	{
		global $mybb, $output, $import_session, $db;

		$this->smf_db_connect();

		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("boards", "COUNT(*) as count");
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
			
			$query = $this->old_db->simple_select("boards", "*", "", array('limit_start' => $import_session['start_forums'], 'limit' => $import_session['forums_per_screen']));
			while($forum = $this->old_db->fetch_array($query))
			{
				echo "Inserting forum #{$forum['ID_BOARD']}... ";
				
				// Values from SMF
				$insert_forum['import_fid'] = intval($forum['ID_BOARD']);
				$insert_forum['name'] = $forum['name'];
				$insert_forum['description'] = $forum['description'];
				$insert_forum['pid'] = $this->get_import_fid((-1) * $forum['ID_CAT']);
				$insert_forum['disporder'] = $forum['boardOrder'];
				$insert_forum['threads'] = $forum['numTopics'];
				$insert_forum['posts'] = $forum['numPosts'];
				$insert_forum['usepostcounts'] = int_to_yesno($forum['countPosts']);
				
				$lastpost = $this->get_last_post_fid($forum['ID_BOARD']);
				$insert_forum['lastpost'] = $lastpost['posterTime'];
				$insert_forum['lastposter'] = $this->get_import_username($lastpost['ID_MEMBER']);
				$insert_forum['lastposteruid'] = $this->get_import_uid($lastpost['ID_MEMBER']);
				$insert_forum['lastposttid'] = (-1 * $lastpost['ID_TOPIC']);
				$insert_forum['lastpostsubject'] = $lastpost['subject'];
				
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
				$db->update_query("forums", $update_array, "fid='{$fid}'");
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no Forums to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_forums'] += $import_session['forums_per_screen'];
		$output->print_footer();	
	}

	function import_threads()
	{
		global $mybb, $output, $import_session, $db;

		$this->smf_db_connect();

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

			$query = $this->old_db->simple_select("topics", "*", "", array('limit_start' => $import_session['start_threads'], 'limit' => $import_session['threads_per_screen']));
			while($thread = $this->old_db->fetch_array($query))
			{
				echo "Inserting thread #{$thread['ID_TOPIC']}... ";
				
				$insert_thread['import_tid'] = $thread['ID_TOPIC'];
				$insert_thread['sticky'] = $thread['isSticky'];
				$insert_thread['fid'] = $this->get_import_fid($thread['ID_BOARD']);
				$insert_thread['firstpost'] = $thread['ID_FIRST_MSG'];			

				$first_post = $this->get_post($thread['ID_FIRST_MSG']);
				$query1 = $this->old_db->simple_select("message_icons", "ID_ICON", "filename = '{$first_post['icon']}'");
				$first_post['icon'] = $this->old_db->fetch_field($query1, "ID_ICON");		
				$insert_thread['icon'] = ((-1) * $first_post['icon']);
				$insert_thread['dateline'] = $first_post['posterTime'];
				$insert_thread['subject'] = $first_post['subject'];
				
				$insert_thread['poll'] = 0;
				$insert_thread['import_poll'] = $thread['ID_POLL'];
				$insert_thread['uid'] = $this->get_import_uid($thread['ID_MEMBER_STARTED']);
				$insert_thread['import_uid'] = $thread['ID_MEMBER_STARTED'];
				$insert_thread['views'] = $thread['numViews'];
				$insert_thread['replies'] = $thread['numReplies'];
				$insert_thread['closed'] = int_to_yesno($thread['locked']);
				if($insert_thread['closed'] == "no")
				{
					$insert_thread['closed'] = '';
				}
				
				$insert_thread['totalratings'] = '0';
				$insert_thread['notes'] = '';
				$insert_thread['visible'] = '1';
				$insert_thread['unapprovedposts'] = '0';
				$insert_thread['numratings'] = '0';
				
				$pids = '';
				$comma = '';
				$count = 0;
				
				$query1 = $this->old_db->simple_select("messages", "ID_MSG", "ID_TOPIC='{$thread['ID_TOPIC']}'");
				while($post = $this->old_db->fetch_array($query1))
				{
					$pids .= $comma.$post['ID_MSG'];
					$comma = ', ';
				}
				
				$query1 = $this->old_db->simple_select("attachments", "COUNT(*) as numattachments", "ID_MSG IN($pids)");
				$insert_thread['attachmentcount'] = $db->fetch_field($query1, 'numattachments');
				
				$last_post = $this->get_post($thread['ID_LAST_MSG']);
				$insert_thread['lastpost'] = $last_post['posterTime'];
				$insert_thread['lastposteruid'] = $this->get_import_uid($last_post['ID_MEMBER']);
				
				$last_post_member = $this->get_user($last_post['ID_MEMBER']);
				$insert_thread['lastposter'] = $last_post_member['posterName'];
				
				
				$member_started = $this->get_user($thread['ID_MEMBER_STARTED']);
				$insert_thread['username'] = $member_started['memberName'];
				$tid = $this->insert_thread($insert_thread);
				
				// Restore connections
				$db->update_query("forums", array('lastposttid' => $tid), "lastposttid='".(-1 * $thread['ID_TOPIC'])."'");				
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no Threads to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_threads'] += $import_session['threads_per_screen'];
		$output->print_footer();
	}
	
	function import_icons()
	{
		global $mybb, $output, $import_session, $db;

		$this->smf_db_connect();

		// Get number of threads
		if(!isset($import_session['total_icons']))
		{
			$query = $this->old_db->simple_select("message_icons", "COUNT(*) as count", "ID_ICON > 12");
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
			
			$query = $this->old_db->simple_select("message_icons", "*", "ID_ICON > 12", array('limit_start' => $import_session['start_icons'], 'limit' => $import_session['icons_per_screen']));
			while($icon = $this->old_db->fetch_array($query))
			{
				echo "Inserting icon #{$icon['ID_ICON']}... ";		
				
				// Invision Power Board 2 values
				$insert_icon['import_iid'] = $icon['ID_ICON'];
				$insert_icon['name'] = $icon['title'];
				$insert_icon['path'] = 'images/icons/'.$icon['filename'].'.gif';
				
			
				$iid = $this->insert_icon($insert_icon);
				
				// Restore connections
				$db->update_query("threads", array('icon' => $iid), "icon = '".((-1) * $icon['ID_ICON'])."'");
				
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
	
	function import_smilies()
	{
		global $mybb, $output, $import_session, $db;

		$this->smf_db_connect();

		// Get number of threads
		if(!isset($import_session['total_smilies']))
		{
			$query = $this->old_db->simple_select("smileys", "COUNT(*) as count");
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
			
			$query = $this->old_db->simple_select("smileys", "*", "ID_SMILEY > 19", array('limit_start' => $import_session['start_icons'], 'limit' => $import_session['icons_per_screen']));
			while($smilie = $this->old_db->fetch_array($query))
			{
				echo "Inserting smilie #{$smilie['ID_SMILEY']}... ";		
				
				// Invision Power Board 2 values
				$insert_smilie['import_iid'] = $smilie['ID_SMILEY'];
				$insert_smilie['name'] = $smilie['description'];
				$insert_smilie['find'] = $smilie['code'];
				$insert_smilie['path'] = 'images/smilies/'.$smilie['filename'];
				$insert_smilie['disporder'] = $smilie['smileyOrder'];
				$insert_smilie['showclickable'] = int_to_noyes($smilie['hidden']);				
			
				$this->insert_smilie($insert_smilie);
				
				echo "done.<br />\n";			
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
	
	function import_polls()
	{
		global $mybb, $output, $import_session, $db;

		$this->smf_db_connect();

		// Get number of threads
		if(!isset($import_session['total_polls']))
		{
			$query = $this->old_db->simple_select("polls", "COUNT(*) as count");
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
			
			$query = $this->old_db->simple_select("polls", "*", "", array('limit_start' => $import_session['start_polls'], 'limit' => $import_session['polls_per_screen']));
			while($poll = $this->old_db->fetch_array($query))
			{
				echo "Inserting poll #{$poll['ID_POLL']}... ";		
				
				// Invision Power Board 2 values
				$insert_poll['import_pid'] = $poll['ID_POLL'];
								
				$query1 = $db->simple_select("threads", "tid,dateline", "import_poll = '{$poll['ID_POLL']}'");
				$thread = $db->fetch_array($query1);
				
				$insert_poll['tid'] = $thread['tid'];
				$insert_poll['dateline'] = $thread['dateline'];

				$seperator = '';
				$options = '';
				$votes = '';
				$vote_count = 0;
				$options_count = 0;
				
				$query2 = $this->old_db->simple_select("poll_choices", "*", "ID_POLL = '{$poll['ID_POLL']}'");
				while($vote_result = $this->old_db->fetch_array($query2))
				{							
					$options .= $seperator.$db->escape_string($vote_result['label']);
					$votes .= $seperator.$vote_result['votes'];
					++$options_count;
					$vote_count += $vote_result['votes'];
					$seperator = '||~|~||';
				}
								
				$insert_poll['question'] = $poll['question'];
				$insert_poll['options'] = $options;
				$insert_poll['votes'] = $votes;
				$insert_poll['numoptions'] = $options_count;
				$insert_poll['numvotes'] = $vote_count;
				$insert_poll['timeout'] = $poll['expireTime'];
				
				
				// Default values
				$insert_poll['multiple'] = 'no';			
				$insert_poll['closed'] = '';				
				
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

		$this->smf_db_connect();

		// Get number of threads
		if(!isset($import_session['total_pollvotes']))
		{
			$query = $this->old_db->simple_select("log_polls", "COUNT(*) as count");
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

			$query = $this->old_db->simple_select("log_polls", "*", "", array('limit_start' => $import_session['start_pollvotes'], 'limit' => $import_session['pollvotes_per_screen']));
			while($pollvote = $this->old_db->fetch_array($query))
			{
				echo "Inserting poll vote #{$pollvote['ID_POLL']}... ";				
				
				$query1 = $db->simple_select("polls", "*", "pid = '".$this->get_import_pid($pollvote['ID_POLL'])."'");
				$poll = $db->fetch_array($query1);
				
				$insert_pollvote['uid'] = $this->get_import_uid($pollvote['ID_MEMBER']);
				$insert_pollvote['dateline'] = $poll['dateline'];
				$insert_pollvote['voteoption'] = $pollvote['ID_CHOICE'];
				$insert_pollvote['pid'] = $poll['pid'];
				
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

		$this->smf_db_connect();

		// Get number of posts
		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->simple_select("messages", "COUNT(*) as count");
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
			
			$query = $this->old_db->simple_select("messages", "*", "", array('limit_start' => $import_session['start_posts'], 'limit' => $import_session['posts_per_screen']));
			while($post = $this->old_db->fetch_array($query))
			{
				echo "Inserting post #{$post['ID_MSG']}... ";
				
				$insert_post['import_pid'] = $post['ID_MSG'];
				$insert_post['tid'] = $this->get_import_tid($post['ID_TOPIC']);
				
				// Find if this is the first post in thread
				$query1 = $db->simple_select("threads", "firstpost", "tid='{$insert_post['tid']}'");
				$first_post = $db->fetch_field($query1, "firstpost");
				
				// Make the replyto the first post of thread unless it is the first post
				if($first_post == $post['ID_MSG'])
				{
					$insert_post['replyto'] = 0;
				}
				else
				{
					$insert_post['replyto'] = $first_post;
				}

				$insert_post['fid'] = $this->get_import_fid($post['ID_BOARD']);
				$insert_post['subject'] = $post['subject'];
				$insert_post['icon'] = 0;
				$insert_post['uid'] = $this->get_import_uid($post['ID_MEMBER']);
				$insert_post['import_uid'] = $post['ID_MEMBER'];
				$insert_post['username'] = $post['posterName'];
				$insert_post['dateline'] = $post['posterTime'];
				$insert_post['message'] = str_replace('<br />', "\n", unhtmlentities($post['body']));
				$insert_post['ipaddress'] = $post['posterIP'];
				$insert_post['includesig'] = 'yes';
				
				if($post['smileysEnabled'] == '1')
				{
					$insert_post['smilieoff'] = 'no';					
				}
				else
				{
					$insert_post['smilieoff'] = 'yes';
				}
				
				// Get edit name
				if(!empty($post['modifiedName']))
				{
					$query1 = $db->simple_select("users", "uid", "username='{$post['modifiedName']}'", array('limit' => 1));
					$insert_post['edituid'] = $db->fetch_field($query1, "uid");
				}
				else
				{
					$insert_post['edituid'] = 0;
				}
				
				$insert_post['edittime'] = $post['modifiedTime'];
				$insert_post['visible'] = 1;
				$insert_post['posthash'] = '';

				$pid = $this->insert_post($insert_post);
				
				$update_post['message'] = $db->escape_string(preg_replace('#\[quote author\=(.*?) link\=topic\=([0-9]*).msg([0-9]*)\#msg([0-9]*) date\=(.*?)\]#i', "[quote author=$1 link=topic={$insert_post['tid']}.msg{$pid}#msg{$pid} date=$5]", $insert_post['message']));
				$db->update_query("posts", $update_post, "pid='{$pid}'");
				
				// Update thread count
				update_thread_count($insert_post['tid']);
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no Posts to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_posts'] += $import_session['posts_per_screen'];
		$output->print_footer();
	}
	
	function import_moderators()
	{
		global $mybb, $output, $import_session, $db;

		$this->smf_db_connect();

		// Get number of moderators
		if(!isset($import_session['total_mods']))
		{
			$query = $this->old_db->simple_select("moderators", "COUNT(*) as count");
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
			
			$query = $this->old_db->simple_select("moderators", "*", "", array('limit_start' => $import_session['start_mods'], 'limit' => $import_session['mods_per_screen']));
			while($mod = $this->old_db->fetch_array($query))
			{
				echo "Inserting user #{$mod['ID_MEMBER']} as moderator to forum #{$mod['ID_BOARD']}... ";
				
				$insert_mod['fid'] = $this->get_import_fid($mod['ID_BOARD']);
				$insert_mod['uid'] = $this->get_import_uid($mod['ID_MEMBER']);
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
	
	function import_usergroups()
	{
		global $mybb, $output, $import_session, $db;

		$this->smf_db_connect();

		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("membergroups", "COUNT(*) as count");
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
			
			// Cache permissions
			$permissions = $this->get_group_permissions();
			
			// Get only non-staff groups.
			$query = $this->old_db->simple_select("membergroups", "*", "ID_GROUP > 3", array('limit_start' => $import_session['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
			while($group = $this->old_db->fetch_array($query))
			{
				echo "Inserting group #{$group['ID_GROUP']} as a ";
				
				if($group['minPosts'] == -1)
				{
					// Make this into a usergroup
					$insert_group['import_gid'] = $group['ID_GROUP'];
					$insert_group['type'] = 2;
					$insert_group['title'] = $group['groupName'];
					$insert_group['description'] = 'SMF-imported group';
					if(!empty($group['onlineColor']))
					{
						$insert_group['namestyle'] = "<span style=\"color: {$group['onlineColor']}\">{username}</span>";
					}
					else
					{
						$insert_group['namestyle'] = '{username}';
					}
					
					$star_info = explode('#', $group['stars']);
					$insert_group['stars'] = $star_info[0];
					$insert_group['starimage'] = 'images/'.$star_info[1];
					$insert_group['image'] = '';
					$insert_group['disporder'] = 0;
					$insert_group['isbannedgroup'] = 'no';
					$insert_group['canview'] = 'yes';
					$insert_group['canviewthreads'] = 'yes';
					$insert_group['canviewprofiles'] = int_to_yesno($permissions[$group['ID_GROUP']]['profile_view_any']);
					$insert_group['candlattachments'] = int_to_yesno($permissions[$group['ID_GROUP']]['view_attachments']);
					$insert_group['canpostthreads'] = int_to_yesno($permissions[$group['ID_GROUP']]['post_new']);
					$insert_group['canpostreplys'] = int_to_yesno($permissions[$group['ID_GROUP']]['post_reply_any']);
					$insert_group['canpostattachments'] = int_to_yesno($permissions[$group['ID_GROUP']]['post_attachment']);
					$insert_group['canratethreads'] = 'yes';
					$insert_group['caneditposts'] = int_to_yesno($permissions[$group['ID_GROUP']]['modify_own']);
					$insert_group['candeleteposts'] = int_to_yesno($permissions[$group['ID_GROUP']]['remove_own']);
					$insert_group['candeletethreads'] = int_to_yesno($permissions[$group['ID_GROUP']]['delete_own']);
					$insert_group['caneditattachments'] = int_to_yesno($permissions[$group['ID_GROUP']]['post_attachment']);
					$insert_group['canpostpolls'] = int_to_yesno($permissions[$group['ID_GROUP']]['poll_post']);
					$insert_group['canvotepolls'] = int_to_yesno($permissions[$group['ID_GROUP']]['poll_vote']);
					$insert_group['canusepms'] = int_to_yesno($permissions[$group['ID_GROUP']]['pm_read']);
					$insert_group['cansendpms'] = int_to_yesno($permissions[$group['ID_GROUP']]['pm_send']);
					$insert_group['cantrackpms'] = 'yes';
					$insert_group['candenypmreceipts'] = 'yes';
					$insert_group['pmquota'] = '0';
					$insert_group['maxpmrecipients'] = '5';
					$insert_group['cansendemail'] = 'yes';
					$insert_group['canviewmemberlist'] = int_to_yesno($permissions[$group['ID_GROUP']]['view_mlist']);
					$insert_group['canviewcalendar'] = int_to_yesno($permissions[$group['ID_GROUP']]['calendar_view']);
					$insert_group['canaddpublicevents'] = int_to_yesno($permissions[$group['ID_GROUP']]['calendar_post']);
					$insert_group['canaddprivateevents'] = int_to_yesno($permissions[$group['ID_GROUP']]['calendar_post']);
					$insert_group['canviewonline'] = int_to_yesno($permissions[$group['ID_GROUP']]['who_view']);
					$insert_group['canviewwolinvis'] = 'no';
					$insert_group['canviewonlineips'] = 'no';
					$insert_group['cancp'] = int_to_yesno($permissions[$group['ID_GROUP']]['admin_forum']);
					$insert_group['issupermod'] = int_to_yesno($permissions[$group['ID_GROUP']]['moderate_board']);
					$insert_group['cansearch'] = int_to_yesno($permissions[$group['ID_GROUP']]['search_posts']);
					$insert_group['canusercp'] = int_to_yesno($permissions[$group['ID_GROUP']]['profile_identity_own']);
					$insert_group['canuploadavatars'] = 'yes';
					$insert_group['canratemembers'] = 'yes';
					$insert_group['canchangename'] = 'no';
					$insert_group['showforumteam'] = 'no';
					$insert_group['usereputationsystem'] = int_to_yesno($permissions[$group['ID_GROUP']]['karma_edit']);
					$insert_group['cangivereputations'] = int_to_yesno($permissions[$group['ID_GROUP']]['karma_edit']);
					$insert_group['reputationpower'] = '1';
					$insert_group['maxreputationsday'] = '5';
					$insert_group['candisplaygroup'] = 'yes';
					$insert_group['attachquota'] = '0';
					$insert_group['cancustomtitle'] = int_to_yesno($permissions[$group['ID_GROUP']]['profile_title_own']);
					
					echo "custom usergroup...";
	
					$gid = $this->insert_usergroup($insert_group);
					
					// Restore connections
					$update_array = array('usergroup' => $gid);
					$db->update_query("users", $update_array, "import_usergroup = '{$group['ID_GROUP']}' OR import_displaygroup = '{$group['ID_GROUP']}'");
					$query1 = $db->simple_select("users", "uid, import_additionalgroups AS additionalGroups", "CONCAT(',', import_additionalgroups, ',') LIKE '%,{$group['ID_GROUP']},%'");
					
					$this->import_gids = null; // Force cache refresh
					
					while($user = $db->fetch_array($query1))
					{
						$update_array = array('additionalgroups' => $this->get_group_id($user, 'additionalGroups'));
						$db->update_array("users", $update_array, "uid = '{$user['uid']}'");
					}
				}
				else
				{
					// Make this into a user title
					$insert_title['posts'] = $group['minPosts'];
					$insert_title['title'] = $group['groupName'];
					$star_info = explode('#', $group['stars']);
					$insert_title['stars'] = $star_info[0];
					$insert_title['starimage'] = 'images/'.$star_info[1];
					
					echo "user title...";
					
					$this->insert_usertitle($insert_title);
				}
				
				echo "done.<br />\n";	
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no Usergroups to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_usergroups'] += $import_session['usergroups_per_screen'];
		$output->print_footer();
	}
	
	function import_privatemessages()
	{
		global $mybb, $output, $import_session, $db;

		$this->smf_db_connect();

		// Get number of usergroups
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("personal_messages", "COUNT(*) as count");
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
			
			$query = $this->old_db->query("
				SELECT * 
				FROM ".SMF_TABLE_PREFIX."personal_messages p
				LEFT JOIN ".SMF_TABLE_PREFIX."pm_recipients r ON(p.ID_PM=r.ID_PM)
				LIMIT ".$import_session['start_privatemessages'].", ".$import_session['privatemessages_per_screen']
			);
			
			while($pm = $this->old_db->fetch_array($query))
			{
				echo "Inserting Private Message #{$pm['ID_PM']}... ";
				$insert_pm['pmid'] = null;
				$insert_pm['import_pmid'] = $pm['ID_PM'];
				$insert_pm['uid'] = $this->get_import_uid($pm['ID_MEMBER_FROM']);
				$insert_pm['fromid'] = $this->get_import_uid($pm['ID_MEMBER_FROM']);
				$insert_pm['toid'] = $this->get_import_uid($pm['ID_MEMBER']);
				$insert_pm['recipients'] = 'a:1:{s:2:"to";a:1:{i:0;s:'.strlen($insert_pm['toid']).':"'.$insert_pm['toid'].'";}}';
				$insert_pm['folder'] = '1';
				$insert_pm['subject'] = $pm['subject'];
				$insert_pm['status'] = $pm['is_read'];
				$insert_pm['dateline'] = $pm['msgtime'];
				$insert_pm['message'] = $pm['body'];
				$insert_pm['includesig'] = 'no';
				$insert_pm['smilieoff'] = '';
				$insert_pm['icon'] = '0';
				if($insert_pm['status'] == '1')
				{
					$insert_pm['readtime'] = time();
					$insert_pm['receipt'] = '2';
				}
				else
				{
					$insert_pm['readtime'] = '0';
					$insert_pm['receipt'] = '0';
				}
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
	
	/**
	 * Get a post from the SMF database
	 * @param int Post ID
	 * @return array The post
	 */
	function get_post($pid)
	{		
		$query = $this->old_db->simple_select("messages", "*", "ID_MSG='{$pid}'", array('limit' => 1));
		
		return $this->old_db->fetch_array($query);
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
		
		$query = $this->old_db->simple_select("members", "*", "ID_MEMBER='{$uid}'", array('limit' => 1));
		
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Gets the time of the last post of a user from the SMF database
	 * @param int User ID
	 * @param int Last post time
	 */
	function get_last_post($uid)
	{
		$query = $this->old_db->simple_select("messages", "*", "ID_MEMBER='{$uid}'", array('order_by' => 'posterTime', 'order_dir' => 'DESC', 'limit' => 1));
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Gets the time of the last post of a forum from the SMF database
	 * @param int Forum ID
	 * @param int Last post time
	 */
	function get_last_post_fid($fid)
	{
		$query = $this->old_db->simple_select("messages", "*", "ID_BOARD='{$fid}'", array('order_by' => 'posterTime', 'order_dir' => 'DESC', 'limit' => 1));
		return $this->old_db->fetch_array($query);
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
				default: 
					$gid = $this->get_import_gid($smfgroup);
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
			$comma = ',';
		}
		
		return $group;
	}
	
	/**
	 * Get the usergroup permissions from SMF
	 */
	function get_group_permissions()
	{
		$query = $this->old_db->simple_select("permissions", "*", "addDeny = 1");
		$permissions = array();
		while($permission = $this->old_db->fetch_array($query))
		{
			$permissions[$permission['ID_GROUP']][$permission['permission']] = 1;
		}
		
		$query = $this->old_db->simple_select("board_permissions", "ID_GROUP, permission", "addDeny = 1 AND ID_BOARD = 0");
		$permissions = array();
		while($permission = $this->old_db->fetch_array($query))
		{
			$permissions[$permission['ID_GROUP']][$permission['permission']] = 1;
		}
		return $permissions;
	}

}

?>