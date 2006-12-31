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
 
// Board Name: Invision Power Board 1

class Convert_ipb1 extends Converter {
	var $bbname = "Invision Power Board 1";
	var $modules = array("db_configuration" => array("name" => "Database Configuration",
									  "dependencies" => ""),
						 "import_usergroups" => array("name" => "Import Invision Power Board 1 Usergroups",
									  "dependencies" => "db_configuration"),
						 "import_users" => array("name" => "Import Invision Power Board 1 Users",
									  "dependencies" => "db_configuration,import_usergroups"),
						 "import_categories" => array("name" => "Import Invision Power Board 1 Categories",
									  "dependencies" => "db_configuration,import_users"),
						 "import_forums" => array("name" => "Import Invision Power Board 1 Forums",
									  "dependencies" => "db_configuration,import_categories"),
						 "import_threads" => array("name" => "Import Invision Power Board 1 Threads",
									  "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Import Invision Power Board 2 Polls",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Import Invision Power Board 2 Poll Votes",
									  "dependencies" => "db_configuration,import_polls"),
						 "import_icons" => array("name" => "Import Invision Power Board 1 Icons",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_posts" => array("name" => "Import Invision Power Board 1 Posts",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_moderators" => array("name" => "Import Invision Power Board 1 Moderators",
									  "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_privatemessages" => array("name" => "Import Invision Power Board 1 Private Messages",
						 			  "dependencies" => "db_configuration,import_users"),
						 "import_smilies" => array("name" => "Import Invision Power Board 1 Smilies",
									  "dependencies" => "db_configuration"),
						 "import_settings" => array("name" => "Import Invision Power Board 1 Settings",
									  "dependencies" => "db_configuration"),
						 "import_events" => array("name" => "Import Invision Power Board 1 Calendar Events",
									  "dependencies" => "db_configuration,import_users"),
						 "import_attachtypes" => array("name" => "Import Invision Power Board 1 Attachment Types",
									  "dependencies" => "db_configuration"),
						);

	function ipb_db_connect()
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
		
		define('IPB_TABLE_PREFIX', $import_session['old_tbl_prefix']);
	}

	function db_configuration()
	{
		global $mybb, $output, $import_session, $db, $dboptions, $dbengines;

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

				// Need to check if IPB is actually installed here
				$this->old_db->set_table_prefix($mybb->input['tableprefix']);
				if(!$this->old_db->table_exists("members"))
				{
					$errors[] = "The Invision Power Board table '{$mybb->input['tableprefix']}members' could not be found in database '{$mybb->input['dbname']}'.  Please ensure phpBB exists at this database and with this table prefix.";
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

		$output->print_header("Invision Power Board 1 Database Configuration");

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
			echo "<p>Please enter the database details for your current installation of Invision Power Board 1.</p>";
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
		
		$output->print_database_details_table("Invision Power Board 1");
		
		$output->print_footer();
	}
	
	function import_usergroups()
	{
		global $mybb, $output, $import_session, $db;

		$this->ipb_db_connect();

		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("groups", "COUNT(*) as count", "g_id > 6");
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
			$query = $this->old_db->simple_select("groups", "*", "g_id > 6", array('limit_start' => $import_session['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
			while($group = $this->old_db->fetch_array($query))
			{
				echo "Inserting group #{$group['g_id']} as a ";
				
				// Invision Power Board 1 values
				$insert_group['import_gid'] = $group['g_id'];
				$insert_group['type'] = 2;
				$insert_group['title'] = $group['g_title'];
				$insert_group['description'] = '';
				$insert_group['pmquota'] = $group['g_max_messages'];
				$insert_group['maxpmrecipients'] = $group['g_max_mass_pm'];
				$insert_group['attachquota'] = $group['g_attach_max'];
				$insert_group['caneditposts'] = int_to_yesno($group['g_edit_posts']);
				$insert_group['candeleteposts'] = int_to_yesno($group['g_delete_own_posts']);
				$insert_group['candeletethreads'] = int_to_yesno($group['g_delete_own_topics']);
				$insert_group['canpostpolls'] = int_to_yesno($group['g_post_polls']);
				$insert_group['canvotepolls'] = int_to_yesno($group['g_vote_polls']);
				$insert_group['canusepms'] = int_to_yesno($group['g_use_pm']);
				$insert_group['cancp'] = int_to_yesno($group['g_access_cp']);
				$insert_group['issupermod'] = int_to_yesno($group['g_is_supermod']);
				$insert_group['cansearch'] = int_to_yesno($group['g_use_search']);
				$insert_group['canuploadavatars'] = int_to_yesno($group['g_avatar_upload']);
				$insert_group['canview'] = int_to_yesno($group['g_view_board']);
				$insert_group['canviewprofiles'] = int_to_yesno($group['g_mem_info']);
				$insert_group['canpostthreads'] = int_to_yesno($group['g_post_new_topics']);
				$insert_group['canpostreplys'] = int_to_yesno($group['g_reply_other_topics']);
				
				// Default values
				$insert_group['namestyle'] = '{username}';
				$insert_group['stars'] = 0;
				$insert_group['starimage'] = 'images/star.gif';
				$insert_group['image'] = '';
				$insert_group['disporder'] = 0;
				$insert_group['isbannedgroup'] = 'no';				
				$insert_group['canviewthreads'] = 'yes';				
				$insert_group['candlattachments'] = 'yes';				
				$insert_group['canpostattachments'] = 'yes';
				$insert_group['canratethreads'] = 'yes';				
				$insert_group['caneditattachments'] = 'yes';				
				$insert_group['cansendpms'] = 'yes';
				$insert_group['cantrackpms'] = 'yes';
				$insert_group['candenypmreceipts'] = 'yes';
				$insert_group['cansendemail'] = 'yes';
				$insert_group['canviewmemberlist'] = 'yes';
				$insert_group['canviewcalendar'] = 'yes';
				$insert_group['canaddpublicevents'] = 'yes';
				$insert_group['canaddprivateevents'] = 'yes';
				$insert_group['canviewonline'] = 'yes';
				$insert_group['canviewwolinvis'] = 'no';
				$insert_group['canviewonlineips'] = 'no';				
				$insert_group['canusercp'] = 'yes';				
				$insert_group['canratemembers'] = 'yes';
				$insert_group['canchangename'] = 'no';
				$insert_group['showforumteam'] = 'no';
				$insert_group['usereputationsystem'] = 'yes';
				$insert_group['cangivereputations'] = 'yes';
				$insert_group['reputationpower'] = '1';
				$insert_group['maxreputationsday'] = '5';
				$insert_group['candisplaygroup'] = 'yes';
				$insert_group['cancustomtitle'] = 'yes';
				
				echo "custom usergroup...";

				$gid = $this->insert_usergroup($insert_group);
				
				// Restore connections
				$update_array = array('usergroup' => $gid);
				$db->update_query("users", $update_array, "import_usergroup = '{$group['g_id']}' OR import_displaygroup = '{$group['g_id']}'");
				
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
		
		$this->ipb_db_connect();
		
		// Get number of members
		if(!isset($import_session['total_members']))
		{
			$query = $this->old_db->simple_select("members", "COUNT(*) as count", "name != 'Guest'");
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
			$query = $this->old_db->simple_select("members", "*", "name != 'Guest'", array('limit_start' => $import_session['start_users'], 'limit' => $import_session['users_per_screen']));

			while($user = $this->old_db->fetch_array($query))
			{
				++$total_users;
					
				$query1 = $db->simple_select("users", "username,email,uid", "LOWER(username)='".$db->escape_string(my_strtolower($user['name']))."'");
				$duplicate_user = $db->fetch_array($query1);
				if($duplicate_user['username'] && my_strtolower($user['email']) == my_strtolower($duplicate_user['email']))
				{
					echo "Merging user #{$user['id']} with user #{$duplicate_user['uid']}... ";
					$db->update_query("users", array('import_uid' => $user['id']), "uid = '{$duplicate_user['uid']}'");
					echo "done.<br />";
					
					continue;
				}
				else if($duplicate_user['username'])
				{				
					$user['name'] = $duplicate_user['username']."_ipb1_import".$total_users;
				}
				
				echo "Adding user #{$user['id']}... ";
						
				// Invision Power Board 1 values
				$insert_user['usergroup'] = $this->get_group_id($user['mgroup'], true);
				$insert_user['additionalgroups'] = str_replace($insert_user['mgroup'], '', $this->get_group_id($user['mgroup']));
				$insert_user['displaygroup'] = $this->get_group_id($user['mgroup'], true);
				$insert_user['import_usergroup'] = $this->get_group_id($user['mgroup'], true, true);
				$insert_user['import_additionalgroups'] = $this->get_group_id($user['mgroup'], false, true);
				$insert_user['import_displaygroup'] = $user['mgroup'];
				$insert_user['import_uid'] = $user['id'];
				$insert_user['username'] = $user['name'];
				$insert_user['email'] = $user['email'];
				$insert_user['regdate'] = $user['joined'];
				$insert_user['postnum'] = $user['posts'];
				$insert_user['lastactive'] = $user['last_activity'];
				$insert_user['lastvisit'] = $user['last_visit'];
				$insert_user['website'] = $user['website'];
				$insert_user['avatardimensions'] = $user['avatar_size'];		
				$insert_user['avatar'] = $user['avatar'];
				$insert_user['lastpost'] = $user['last_post'];
				$insert_user['birthday'] = $user['bday_day'].'-'.$user['bday_month'].'-'.$user['bday_year'];
				$insert_user['icq'] = $user['icq_number'];
				$insert_user['aim'] = $user['aim_name'];
				$insert_user['yahoo'] = $user['yahoo'];
				$insert_user['msn'] = $user['msnname'];
				$insert_user['timezone'] = str_replace(array('.0', '.00'), array('', ''), $insert_user['time_offset']);			
				$insert_user['style'] = $user['skin'];							
				$insert_user['regip'] = $user['ip_address'];				
				$insert_user['totalpms'] = $user['msg_total'];
				$insert_user['unreadpms'] = $user['new_msg'];
				$insert_user['dst'] = int_to_yesno($user['dst_in_use']);
				$insert_user['signature'] = $user['signature'];
				
				// Default values
				$insert_user['referrer'] = '';	
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
				$insert_user['buddylist'] = "";
				$insert_user['ignorelist'] = "";
				$insert_user['away'] = "no";
				$insert_user['awaydate'] = "0";
				$insert_user['returndate'] = "0";
				$insert_user['reputation'] = "0";
				$insert_user['timeonline'] = "0";
				$insert_user['pmfolders'] = '1**Inbox$%%$2**Sent Items$%%$3**Drafts$%%$4**Trash Can';	
				$insert_user['avatartype'] = '2';	
				$uid = $this->insert_user($insert_user);
				
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
	
	function import_categories()
	{
		global $mybb, $output, $import_session, $db;

		$this->ipb_db_connect();

		// Get number of forums
		if(!isset($import_session['total_cats']))
		{
			$query = $this->old_db->simple_select("categories", "COUNT(*) as count", "id != '-1'");
			$import_session['total_cats'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_cats'])
		{
			// If there are more forums to do, continue, or else, move onto next module
			if($import_session['total_cats'] - $import_session['start_cats'] <= 0)
			{
				$import_session['disabled'][] = 'import_cats';
				return "finished";
			}
		}
		
		$output->print_header($this->modules[$import_session['module']]['name']);

		// Get number of forums per screen from form
		if(isset($mybb->input['cats_per_screen']))
		{
			$import_session['cats_per_screen'] = intval($mybb->input['cats_per_screen']);
		}
		
		if(empty($import_session['cats_per_screen']))
		{
			$import_session['start_cats'] = 0;
			echo "<p>Please select how many forums to import at a time:</p>
<p><input type=\"text\" name=\"cats_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_cats']-$import_session['start_cats'])." categories left to import and ".round((($import_session['total_cats']-$import_session['start_cats'])/$import_session['cats_per_screen']))." pages left at a rate of {$import_session['cats_per_screen']} per page.<br /><br />";
			
			$query = $this->old_db->simple_select("categories", "*", "id != '-1'", array('limit_start' => $import_session['start_forums'], 'limit' => $import_session['forums_per_screen']));
			while($cat = $this->old_db->fetch_array($query))
			{
				echo "Inserting category #{$cat['id']}... ";
				
				// Invision Power Board 1 values
				$insert_forum['import_fid'] = $cat['id'];
				$insert_forum['name'] = $cat['name'];
				$insert_forum['description'] = $cat['description'];				
				$insert_forum['disporder'] = $cat['position'];
				$insert_forum['import_fid'] = (-1 * $cat['id']);
				
				// Default values
				$insert_forum['threads'] = 0;
				$insert_forum['posts'] = 0;
				$insert_forum['style'] = '';
				$insert_forum['password'] = '';
				$insert_forum['defaultsortby'] = '';
				$insert_forum['defaultsortorder'] = '';	
				$insert_forum['unapprovedthreads'] = 0;
				$insert_forum['unapprovedposts'] = 0;				
				$insert_forum['type'] = 'c';				
				$insert_forum['lastpost'] = 0;
				$insert_forum['lastposteruid'] = 0;
				$insert_forum['lastposttid'] = 0;
				$insert_forum['lastpostsubject'] = '';
				$insert_forum['parentlist'] = '';
				$insert_forum['open'] = 'yes';
				$insert_forum['rules'] = '';
				$insert_forum['rulestype'] = 1;
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
				$insert_forum['usepostcounts'] = 'yes';
	
				$fid = $this->insert_forum($insert_forum);
				
				// Update parent list.
				$update_array = array('parentlist' => $fid);					
				$db->update_query("forums", $update_array, "fid='{$fid}'");
				
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no categories to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_cats'] += $import_session['cats_per_screen'];
		$output->print_footer();
	}
	
	function import_forums()
	{
		global $mybb, $output, $import_session, $db;

		$this->ipb_db_connect();

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
			
			$query = $this->old_db->simple_select("forums", "*", "", array('order_by' => 'parent_id', 'order_dir' => 'asc', 'limit_start' => $import_session['start_forums'], 'limit' => $import_session['forums_per_screen']));
			while($forum = $this->old_db->fetch_array($query))
			{
				echo "Inserting forum #{$forum['id']}... ";
				
				// Invision Power Board 1 values
				$insert_forum['import_fid'] = $forum['id'];
				$insert_forum['name'] = $forum['name'];
				$insert_forum['description'] = $forum['description'];				
				$insert_forum['disporder'] = $forum['position'];
				$insert_forum['threads'] = $forum['topics'];
				$insert_forum['posts'] = $forum['posts'];
				$insert_forum['style'] = $forum['skin_id'];
				$insert_forum['password'] = $forum['password'];
				if($forum['sort_key'] == 'last_post')
				{
					$forum['sort_key'] = '';
				}
				$insert_forum['defaultsortby'] = $forum['sort_key'];
				if($forum['sort_order'] = 'A-Z')
				{
					$forum['sort_order'] = 'asc';
				}
				else
				{
					$forum['sort_order'] = 'desc';
				}
				$insert_forum['defaultsortorder'] = $forum['sort_order'];	
				$insert_forum['unapprovedthreads'] = $this->get_invisible_threads($forum['id']);
				$insert_forum['unapprovedposts'] = $this->get_invisible_posts('', $forum['id']);	
				
				$insert_forum['linkto'] = $forum['redirect_url'];
				$insert_forum['type'] = 'f';
				$insert_forum['pid'] = $this->get_import_fid((-1) * $forum['parent_id']);
				$insert_forum['lastpost'] = $forum['last_post'];
				$insert_forum['lastposteruid'] = $this->get_import_uid($forum['last_poster_id']);
				$insert_forum['lastposttid'] = ((-1) * $forum['last_id']);
				$insert_forum['lastpostsubject'] = $forum['last_title'];
				$insert_forum['lastposter'] = $this->get_import_username($forum['last_poster_id']);
				
				// Default values
				$insert_forum['parentlist'] = '';
				$insert_forum['open'] = 'yes';
				$insert_forum['rules'] = '';
				$insert_forum['rulestype'] = 1;
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
				$insert_forum['usepostcounts'] = 'yes';
	
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

		$this->ipb_db_connect();

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
				echo "Inserting thread #{$thread['tid']}... ";
				
				// Invision Power Board 1 values
				$insert_thread['import_tid'] = $thread['tid'];
				$insert_thread['sticky'] = $thread['pinned'];
				$insert_thread['fid'] = $this->get_import_fid($thread['forum_id']);
				$insert_thread['firstpost'] = ((-1) * $this->get_firstpost($thread['tid']));	
				$insert_thread['icon'] = $thread['icon_id'];
				$insert_thread['dateline'] = $thread['start_date'];
				$insert_thread['subject'] = $thread['title'];				
				$insert_thread['poll'] = $thread['poll_state'];
				$insert_thread['uid'] = $this->get_import_uid($thread['starter_id']);
				$insert_thread['import_uid'] = $thread['starter_id'];
				$insert_thread['views'] = $thread['views'];
				$insert_thread['replies'] = $thread['posts'];
				if($thread['state'] != 'open')
				{
					$insert_thread['closed'] = 'yes';
				}
				else
				{				
					$insert_thread['closed'] = '';	
				}

				$insert_thread['totalratings'] = 0;
				$insert_thread['notes'] = $thread['notes'];
				$insert_thread['visible'] = $thread['approved'];
				$insert_thread['unapprovedposts'] = $this->get_invisible_posts($thread['tid']);
				$insert_thread['numratings'] = 0;
				$insert_thread['attachmentcount'] = 0;
				$insert_thread['lastpost'] = $thread['last_post'];
				$insert_thread['lastposteruid'] = $this->get_import_uid($thread['last_poster_id']);				
				$insert_thread['lastposter'] = $this->get_import_username($thread['last_poster_id']);
				$insert_thread['username'] = $this->get_import_username($thread['starter_id']);
				
				$tid = $this->insert_thread($insert_thread);
				
				$db->update_query("forums", array('lastposttid' => $tid), "lastposttid='".((-1) * $thread['tid'])."'");
				
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

		$this->ipb_db_connect();

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
				echo "Inserting poll #{$poll['pid']}... ";				
				
				// Invision Power Board 2 values
				$insert_poll['import_pid'] = $poll['pid'];
				$insert_poll['tid'] = $this->get_import_tid($poll['tid']);
				$choices = unserialize($poll['choices']);
				$choices = $choices[1];

				$seperator = '';
				$choices1 = '';
				$choice_count = 0;
				foreach($choices['choice'] as $key => $choice)
				{
					++$choice_count;
					$choices1 .= $seperator.$db->escape_string($choice);
					$seperator = '||~|~||';
				}
				
				$seperator = '';
				$votes = '';
				foreach($choices['votes'] as $key => $vote)
				{
					$votes .= $seperator.$vote;
					$seperator = '||~|~||';
				}
				
				$insert_poll['question'] = $choices['question'];
				$insert_poll['dateline'] = $poll['start_date'];
				$insert_poll['options'] = $choices1;
				$insert_poll['votes'] = $votes;
				$insert_poll['numoptions'] = $choice_count;
				$insert_poll['numvotes'] = $poll['votes'];
				$insert_poll['multiple'] = int_to_yesno($choices['multi']);
				
				// Default values
				$poll['timeout'] = '';
				$poll['closed'] = '';				
				
				$pid = $this->insert_poll($insert_poll);
				
				// Restore connections
				$db->update_query("threads", array('poll' => $pid), "import_poll = '".$poll['pid']."'");
				
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

		$this->ipb_db_connect();

		// Get number of threads
		if(!isset($import_session['total_pollvotes']))
		{
			$query = $this->old_db->simple_select("voters", "COUNT(*) as count");
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

			$query = $this->old_db->simple_select("voters", "*", "", array('limit_start' => $import_session['start_pollvotes'], 'limit' => $import_session['pollvotes_per_screen']));
			while($pollvote = $this->old_db->fetch_array($query))
			{
				echo "Inserting poll vote #{$pollvote['vid']}... ";				
				
				$insert_pollvote['uid'] = $this->get_import_uid($pollvote['member_id']);
				$insert_pollvote['dateline'] = $pollvote['vote_date'];
				$insert_pollvote['voteoption'] = ''; // We dunno!
				
				// Get poll id from thread id
				$tid = $this->get_import_tid($pollvote['tid']);
				$query1 = $db->simple_select("threads", "poll", "tid = '$tid'");
				$insert_pollvote['pid'] = $db->fetch_field($query1, "poll");
				
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

		$this->ipb_db_connect();

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
				echo "Inserting post #{$post['pid']}... ";
				
				// Invision Power Board 1 values
				$insert_post['import_pid'] = $post['pid'];
				$insert_post['tid'] = $this->get_import_tid($post['topic_id']);			
				$insert_post['pid'] = 0;
				$thread = $this->get_thread($post['topic_id']);	
				$insert_post['fid'] = $this->get_import_fid($post['forum_id']);
				$insert_post['subject'] = $thread['title'];
				if($post['queued'] == 0)
				{
					$insert_post['visible'] = 1;
				}
				else
				{
					$insert_post['visible'] = 0;
				}
				$insert_post['uid'] = $this->get_import_uid($post['author_id']);
				$insert_post['import_uid'] = $post['author_id'];
				$insert_post['username'] = $this->get_import_username($insert_post['import_uid']);
				$insert_post['dateline'] = $post['post_date'];
				$insert_post['message'] = $post['post'];
				$insert_post['ipaddress'] = $post['ip_address'];
				$insert_post['includesig'] = int_to_yesno($post['use_sig']);		
				$insert_post['smilieoff'] = int_to_noyes($post['allowsmilie']);
				$insert_post['edituid'] = $this->get_import_uid($this->get_uid_from_username($post['edit_name']));		
				$insert_post['edittime'] = $post['edit_time'];
				$insert_post['icon'] = $post['icon_id'];
				$insert_post['posthash'] = '';
				

				$pid = $this->insert_post($insert_post);
				
				// Update thread count
				update_thread_count($insert_post['tid']);
				
				// Restore first post connections
				$db->update_query("threads", array('firstpost' => $pid), "tid='{$insert_post['tid']}' AND firstpost='".((-1) * $post['pid'])."'");
				if($db->affected_rows() == 0)
				{
					$query1 = $db->simple_select("threads", "firstpost", "tid='{$insert_post['tid']}'");
					$first_post = $db->fetch_field($query1, "firstpost");
					$db->update_query("posts", array('replyto' => $first_post), "pid='{$pid}'");
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
	
	function import_icons()
	{
		global $mybb, $output, $import_session, $db;

		$this->ipb_db_connect();

		// Get number of threads
		if(!isset($import_session['total_icons']))
		{
			$query = $this->old_db->simple_select("emoticons", "COUNT(*) as count", "id > 20");
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
			
			$query = $this->old_db->simple_select("emoticons", "*", "id > 20", array('limit_start' => $import_session['start_icons'], 'limit' => $import_session['icons_per_screen']));
			while($icon = $this->old_db->fetch_array($query))
			{
				echo "Inserting icon #{$icon['id']}... ";		
				
				// Invision Power Board 2 values
				$insert_icon['import_iid'] = $icon['id'];
				$insert_icon['name'] = $icon['typed'];
				$insert_icon['path'] = 'images/icons/'.$icon['image'];
				
			
				$iid = $this->insert_icon($insert_icon);
				
				// Restore connections
				$db->update_query("threads", array('icon' => $iid), "icon = '".((-1) * $icon['id'])."'");
				
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
		
	function import_moderators()
	{
		global $mybb, $output, $import_session, $db;

		$this->ipb_db_connect();

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
				echo "Inserting user #{$mod['member_id']} as moderator to forum #{$mod['forum_id']}... ";
				
				$insert_mod['fid'] = $this->get_import_fid($mod['forum_id']);
				$insert_mod['uid'] = $this->get_import_uid($mod['member_id']);
				$insert_mod['caneditposts'] = int_to_yesno($mod['edit_post']);
				$insert_mod['candeleteposts'] = int_to_yesno($mod['delete_post']);
				$insert_mod['canviewips'] = int_to_yesno($mod['view_ip']);
				$insert_mod['canopenclosethreads'] = int_to_yesno($mod['close_topic']);
				$insert_mod['canmanagethreads'] = 'yes';
				$insert_mod['canmovetononmodforum'] = int_to_yesno($mod['move_topic']);

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
	
	function import_privatemessages()
	{
		global $mybb, $output, $import_session, $db;

		$this->ipb_db_connect();

		// Get number of usergroups
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("messages", "COUNT(*) as count");
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
			
			$query = $this->old_db->simple_select("messages", "*", "", array('limit_start' => $import_session['start_privatemessages'], 'limit' => $import_session['privatemessages_per_screen']));
			
			while($pm = $this->old_db->fetch_array($query))
			{
				echo "Inserting Private Message #{$pm['msg_id']}... ";
				
				$insert_pm['pmid'] = '';
				$insert_pm['import_pmid'] = $pm['msg_id'];
				$insert_pm['uid'] = $this->get_import_uid($pm['member_id']);
				$insert_pm['fromid'] = $this->get_import_uid($pm['from_id']);
				$insert_pm['toid'] = $this->get_import_uid($pm['recipient_id']);
				$touserarray = explode('<br />', $pm['cc_users']);

				// Rebuild the recipients array
				$recipients = array();
				foreach($touserarray as $key => $to)
				{
					$username = $this->get_username($to);				
					$recipients['to'][] = $this->get_import_username($username['id']);
				}
				$insert_pm['recipients'] = serialize($recipients);
				$insert_pm['folder'] = 0;
				$insert_pm['subject'] = $pm['title'];
				$insert_pm['status'] = $pm['read_state'];
				$insert_pm['dateline'] = $pm['msg_date'];
				$insert_pm['message'] = $pm['message'];
				$insert_pm['includesig'] = 'no';
				$insert_pm['smilieoff'] = '';
				$insert_pm['readtime'] = $pm['read_date'];
				$insert_pm['icon'] = '';			
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
	
	function import_smilies()
	{
		global $mybb, $output, $import_session, $db;

		$this->ipb_db_connect();

		// Get number of threads
		if(!isset($import_session['total_smilies']))
		{
			$query = $this->old_db->simple_select("emoticons", "COUNT(*) as count", "id > 20");
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
			
			$query = $this->old_db->simple_select("emoticons", "*", "id > 20", array('limit_start' => $import_session['start_icons'], 'limit' => $import_session['icons_per_screen']));
			while($smilie = $this->old_db->fetch_array($query))
			{
				echo "Inserting smilie #{$smilie['id']}... ";		
				
				// Invision Power Board 2 values
				$insert_smilie['import_iid'] = $smilie['id'];
				$insert_smilie['name'] = $smilie['typed'];
				$insert_smilie['find'] = $smilie['typed'];
				$insert_smilie['path'] = 'images/smilies/'.$smilie['image'];
				$insert_smilie['disporder'] = $smilie['id'];
				$insert_smilie['showclickable'] = int_to_yesno($smilie['clickable']);				
			
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

	function import_settings()
	{
		global $mybb, $output, $import_session, $db;

		$this->ipb_db_connect();

		// Get number of settings
		if(!isset($import_session['total_settings']))
		{
			$query = $this->old_db->simple_select("config", "COUNT(*) as count");
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

			$query = $this->old_db->simple_select("config", "config_name, config_value", "", array('limit_start' => $import_session['start_settings'], 'limit' => $import_session['settings_per_screen']));
			while($setting = $this->old_db->fetch_array($query))
			{
				echo "Updating setting {$setting['config_name']} from phpBB database... ";

				// Invision Power Board 1 values
				$name = $value = "";

				switch($setting['config_name'])
				{
					case '':
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

		$this->ipb_db_connect();

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
				$insert_event['subject'] = $event['title'];
				$insert_event['author'] = $this->get_import_uid($event['userid']);
				$insert_event['date'] = date('j-n-Y', $event['unix_stamp']);
				$insert_event['description'] = $event['event_text'];
				$insert_event['private'] = int_to_yesno($event['priv_event']);				
				$insert_event['start_day'] = $event['mday'];
				$insert_event['start_month'] = $event['month'];
				$insert_event['start_year'] = $event['year'];
				$insert_event['end_day'] = $event['end_day'];
				$insert_event['end_month'] = $event['end_month'];
				$insert_event['end_year'] = $event['event_year'];
				$insert_event['repeat_days'] = $event['repeat_unit'];

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
	
	function import_attachtypes()
	{
		global $mybb, $output, $import_session, $db;

		$this->ipb_db_connect();

		// Get number of attachment types
		if(!isset($import_session['total_attachtypes']) && isset($import_session['ipb_conf_global']))
		{
			require($import_session['ipb_conf_global']);
			$types = $INFO['img_ext'];
			$types = explode('|', $types);
			$import_session['total_attachtypes'] = count($types);
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
		
		// Validate IPB configuration file location
		$conf_global_not_found = false;
		if(isset($mybb->input['ipb_conf_global']) && !file_exists($mybb->input['ipb_conf_global']))
		{
			unset($import_session['attachtypes_per_screen']);
			$conf_global_not_found = true;
		}
		elseif(isset($mybb->input['ipb_conf_global']))
		{
			$import_session['ipb_conf_global'] = $mybb->input['ipb_conf_global'];
			require($import_session['ipb_conf_global']);
			$types = $INFO['img_ext'];
			$types = explode('|', $types);
			$import_session['total_attachtypes'] = count($types);
		}
		
		if(empty($import_session['attachtypes_per_screen']))
		{
			$import_session['start_attachtypes'] = 0;
			echo "<p>Please select how many attachment types to import at a time:</p>
<p><input type=\"text\" name=\"attachtypes_per_screen\" value=\"200\" /></p>";
			$conf_global = dirname($_SERVER['SCRIPT_FILENAME']).'/conf_global.php';
			if($conf_global_not_found)
			{
				echo '<p style="color: red">The file specified was not found.</p>';
			}
			echo "<p>Please enter the path to the IPB1 conf_global.php file:</p>
<p><input type=\"text\" name=\"ipb_conf_global\" value=\"{$conf_global}\" style=\"width: 50%\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// A bit of stats to show the progress of the current import
			echo "There are ".($import_session['total_attachtypes']-$import_session['start_attachtypes'])." attachment types left to import and ".round((($import_session['total_attachtypes']-$import_session['start_attachtypes'])/$import_session['attachtypes_per_screen']))." pages left at a rate of {$import_session['attachtypes_per_screen']} per page.<br /><br />";
			
			// Get attachment types
			require($import_session['ipb_conf_global']);
			$types = $INFO['img_ext'];
			$types = explode('|', $types);
			
			// Get existing attachment types
			$query = $db->simple_select("attachtypes", "extension");
			while($row = $db->fetch_array($query))
			{
				$existing_types[$row['extension']] = true;
			}
			
			$start = $import_session['start_attachtypes'];
			$end = $import_session['start_attachtypes']+$import_session['attachtypes_per_screen'];
			if($end > $import_session['total_attachtypes'])
			{
				$end = $import_session['total_attachtypes'];
			}
			for($i = $start; $i < $end; $i++)
			{
				$i_present = $i + 1;
				echo "Inserting attachment type #{$i_present}... ";				

				$insert_attachtype['import_atid'] = ($i_present);
				$insert_attachtype['name'] = $types[$i].' file';
				$insert_attachtype['mimetype'] = '';
				$insert_attachtype['extension'] = $types[$i];
				$insert_attachtype['maxsize'] = 512;
				$insert_attachtype['icon'] = '';
				
				$this->insert_attachtype($insert_attachtype);

				echo "done.";
					
				if(isset($existing_types[$types[$i]]))
				{
					echo " (Note: extension already exists)\n";
				}
				
				echo "<br />\n";
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
	 * Get the first post from a thread in the XMB database
	 * @param int Thread ID
	 * @return array The first post
	 */
	function get_firstpost($tid)
	{
		$query = $this->old_db->simple_select("posts", "pid", "topic_id='".intval($tid)."'", array('order_by' => 'pid', 'order_dir' => 'asc', 'limit' => 1));
		return $this->old_db->fetch_field($query, 'pid');
	}
	
	/**
	 * Count number of invisible posts from the phpBB 3 database
	 * @param int thread id (optional)
	 * @param int forum id (optional)
	 * @return int number of invisible posts
	 */
	function get_invisible_posts($tid='', $fid='')
	{
		$fidbit = "";
		if(!empty($fid))
		{
			$fidbit = "AND forum_id='".intval($fid)."'";
		}
		
		$tidbit = "";
		if(!empty($tid))
		{
			$tidbit = " AND topic_id='".intval($tid)."'";
		}
		$query = $this->old_db->simple_select("posts", "COUNT(*) as invisible", "queued='1'{$tidbit}{$fidbit}");
		return $this->old_db->fetch_field($query, "invisible");
	}
	
	/**
	 * Count number of invisible threads from the phpBB 3 database
	 * @param int forum id (optional)
	 * @return int number of invisible threads
	 */
	function get_invisible_threads($fid='')
	{
		$fidbit = "";
		if(!empty($fid))
		{
			$fidbit = "AND forum_id='".intval($fid)."'";
		}
		$query = $this->old_db->simple_select("topics", "COUNT(*) as invisible", "approved='0'{$fidbit}");
		return $this->old_db->fetch_field($query, "invisible");
	}
	
	/**
	 * Get a thread from the IPB database
	 * @param int Thread ID
	 * @return array The thread
	 */
	function get_thread($tid)
	{		
		$query = $this->old_db->simple_select("topics", "*", "tid='{$tid}'", array('limit' => 1));
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Get a user from the IPB database
	 * @param int User ID
	 * @return array If the uid is 0, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_user($uid)
	{
		if($uid == 0)
		{
			return array(
				'username' => 'Guest',
				'id' => 0,
			);
		}
		
		$query = $this->old_db->simple_select("members", "*", "id='{$uid}'", array('limit' => 1));
		
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Get a user from the IPB database
	 * @param int Username
	 * @return array If the username is empty, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_username($username)
	{
		if($username == '')
		{
			return array(
				'username' => 'Guest',
				'id' => 0,
			);
		}
		
		$query = $this->old_db->simple_select("members", "*", "name='{$username}'", array('limit' => 1));
		
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Get a user id from a username in the IPB database
	 * @param int Username
	 * @return int If the username is blank it returns 0. Otherwise returns the user id
	 */
	function get_uid_from_username($username)
	{
		if($username == '')
		{
			return 0;
		}
		
		$query = $this->old_db->simple_select("members", "id", "name='{$username}'", array('limit' => 1));
		
		return $this->old_db->fetch_field($query, "id");
	}
	
	/**
	 * Convert a IPB group ID into a MyBB group ID
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
			$query = $this->old_db->simple_select("groups", "COUNT(*) as rows", "g_id='{$gid}'");
			$settings = array('limit_start' => '1', 'limit' => $this->old_db->fetch_field($query, 'rows'));
		}
		
		$query = $this->old_db->simple_select("groups", "*", "g_id='{$gid}'", $settings);
		
		$comma = $group = '';
		while($ipbgroup = $this->old_db->fetch_array($query))
		{
			if($orig == true)
			{
				$group .= $ipbgroup['g_id'].$comma;
			}
			else
			{
				$group .= $comma;
				switch($ipbgroup['g_id'])
				{
					case 1: // Awaiting activation
						$group .= 5;
						break;
					case 2: // Guests
						$group .= 1;
					case 3: // Registered
						$group .= 2;
						break;
					case 5: // Banned
						$group .= 7;
						break;
					case 4: // Root Admin
					case 6: // Administrator
						$group .= 4;
						break;
					default:
						$gid = $this->get_import_gid($ipbgroup['g_id']);
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
