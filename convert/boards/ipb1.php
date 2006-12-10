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
						 "import_posts" => array("name" => "Import Invision Power Board 1 Posts",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_privatemessages" => array("name" => "Import Invision Power Board 1 Private Messages",
						 			  "dependencies" => "db_configuration,import_users"),
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

		echo <<<EOF
<div class="border_wrapper">
<div class="title">Invision Power Board 1 Database Configuration</div>
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
			echo "There are ".($import_session['total_usegroups']-$import_session['start_usegroups'])." usegroups left to import and ".round((($import_session['total_usegroups']-$import_session['start_usegroups'])/$import_session['usegroups_per_screen']))." pages left at a rate of {$import_session['usegroups_per_screen']} per page.<br /><br />";
			
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
				echo "There are no Usergroups to import. Please press next to continue.";
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
					
				$query1 = $db->simple_select("users", "username,email,uid", "LOWER(username)='".$db->escape_string(strtolower($user['name']))."'");
				$duplicate_user = $db->fetch_array($query1);
				if($duplicate_user['username'] && strtolower($user['email']) == strtolower($duplicate_user['email']))
				{
					echo "Merging user #{$user['id']} with user #{$duplicate_user['uid']}... done.<br />";
					continue;
				}
				else if($duplicate_user['username'])
				{				
					$user['name'] = $duplicate_user['username']."_ipb2_import".$total_users;
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

		$this->ipb_db_connect();

		// Get number of forums
		if(!isset($import_session['total_cats']))
		{
			$query = $this->old_db->simple_select("categories", "COUNT(*) as count", "id != '-1'");
			$import_session['total_cats'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['total_cats'])
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

		$this->ipb_db_connect();

		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("forums", "COUNT(*) as count");
			$import_session['total_forums'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['total_forums'])
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
				$insert_forum['lastposter'] = $this->get_import_username($forum['last_poster_id']); // to do
				
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

		if($import_session['total_threads'])
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
				$insert_thread['sticky'] = int_to_yesno($thread['pinned']);
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
				echo "There are no Threads to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_threads'] += $import_session['threads_per_screen'];
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
				echo "There are no Posts to import. Please press next to continue.";
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_posts'] += $import_session['posts_per_screen'];
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
	
	function get_firstpost($tid)
	{
		$query = $this->old_db->simple_select("posts", "pid", "topic_id='".intval($tid)."'", array('order_by' => 'pid', 'order_dir' => 'asc', 'limit' => 1));
		return $this->old_db->fetch_field($query, 'pid');
	}
	
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
	
	function get_invisible_threads($fid='')
	{
		$fidbit = "";
		if(!empty($fid))
		{
			$fidbit = "AND forum_id='".intval($fid)."'";
		}
		$query = $this->old_db->simple_select("topics", "COUNT(*) as invisible", "approved='0'$fidbit");
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
	 */
	function get_group_id($gid, $not_multiple=false, $orig=false)
	{
		$settings = array();
		if($not_mutliple == false)
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
				$group .= $vbgroup['g_id'].$comma;
			}
			else
			{
				$group .= $comma;
				switch($vbgroup['g_id'])
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
						if($this->get_import_gid($ipbgroup) > 0)
						{
							// If there is an associated custom group...
							$group .= $this->get_import_gid($ipbgroup);
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