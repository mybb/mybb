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
 
// Board Name: phpBB 2

class Convert_phpbb2 extends Converter {
	var $bbname = "phpBB 2";
	var $modules = array("db_configuration" => array("name" => "Database Configuration",
									  "dependencies" => ""),
						 "import_usergroups" => array("name" => "Import phpBB 2 Usergroups",
									  "dependencies" => "db_configuration"),
						 "import_categories" => array("name" => "Import phpBB 2 Categories",
									  "dependencies" => "db_configuration"),
						 "import_forums" => array("name" => "Import phpBB 2 Forums",
									  "dependencies" => "db_configuration,import_categories"),
						 "import_threads" => array("name" => "Import phpBB 2 Threads",
									  "dependencies" => "db_configuration,import_forums"),
						 "import_posts" => array("name" => "Import phpBB 2 Posts",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_users" => array("name" => "Import phpBB 2 Users",
									  "dependencies" => "db_configuration,import_usergroups"),
						 "import_privatemessages" => array("name" => "Import phpBB 2 Private Messages",
						 			  "dependencies" => "db_configuration,import_users"),
						);

	function phpbb_db_connect()
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
		
		define('PHPBB_TABLE_PREFIX', $import_session['old_tbl_prefix']);
	}

	function db_configuration()
	{
		global $mybb, $output, $import_session, $db, $dboptions;

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

				$connection = $this->old_db->connect($mybb->input['dbhost'], $mybb->input['dbuser'], $mybb->input['dbpass']);
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

		$output->print_header("phpBB 2 Database Configuration");

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
			echo "<p>Please enter the database details for your current installation of phpBB 2.</p>";
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
<div class="title">phpBB 2 Database Configuration</div>
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
			if($import_session['total_members'] <= $import_session['start_users'] + $import_session['users_per_screen'])
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
<p><input type=\"text\" name=\"users_per_screen\" value=\"\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// Setup member array, for checking for duplicate members
			$members_cache = array();
						
			$query = $db->simple_select("users", "username");
			while($user = $db->fetch_array($query))
			{
				$members_cache[] = $user['username'];
			}
			
			// Get a unique number to avoid more duplicates.
			$member_dup_count = count($members_cache);
			
			// Get members
			$query = $this->old_db->query("
				SELECT * 
				FROM ".PHPBB_TABLE_PREFIX."users u
				LEFT JOIN ".PHPBB_TABLE_PREFIX."user_group ug ON(u.user_id=ug.user_id)
				WHERE u.user_id > 0
				LIMIT ".$import_session['start_users'].", ".$import_session['users_per_screen']
			);

			while($user = $this->old_db->fetch_array($query))
			{
				echo "Adding user #{$user['user_id']}... ";
					
				// Check for duplicate members
				if(in_array($user['username'], $members_cache))
				{
					++$member_dup_count;
					$user['username'] .= "_phpbb_import".$member_dup_count;
				}
				
				$members_cache[] = $user['username'];				
				
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
				//$user['avatardimensions']
				//$user['avatartype']
				$insert_user['lastpost'] = $this->get_last_post($user['user_id']);
				$insert_user['birthday'] = '';
				$insert_user['icq'] = $user['user_icq'];
				$insert_user['aim'] = $user['user_aim'];
				$insert_user['yahoo'] = $user['user_yim'];
				$insert_user['msn'] = $user['user_msnm'];
				$insert_user['hideemail'] = int_to_yesno($user['hideEmail']); // to do: should be 'yes'
				$insert_user['invisible'] = int_to_yesno($user['user_allow_viewonline']);
				$insert_user['allownotices'] = int_to_yesno($user['user_notify']);
				$insert_user['emailnotify'] = int_to_yesno($user['user_notify']);
				$insert_user['receivepms'] = int_to_yesno($user['user_allow_pm']);
				$insert_user['pmpopup'] = int_to_yesno($user['user_popup_pm']);
				$insert_user['pmnotify'] = int_to_yesno($user['pm_email_notify']);
				$insert_user['remember'] = "yes";
				$insert_user['showsigs'] = int_to_yesno($user['user_attachsig']);
				$insert_user['showavatars'] = int_to_yesno($user['user_allowavatar']); // Check ?
				$insert_user['showquickreply'] = "yes";
				$insert_user['ppp'] = "0";
				$insert_user['tpp'] = "0";
				$insert_user['daysprune'] = "0";
				$insert_user['timeformat'] = $user['user_dateformat'];
				$insert_user['timezone'] = $user['user_timezone'];
				$insert_user['dst'] = "no";
				$insert_user['buddylist'] = "";
				$insert_user['ignorelist'] = "";
				$insert_user['style'] = $user['user_style'];
				$insert_user['away'] = "no";
				$insert_user['awaydate'] = "0";
				$insert_user['returndate'] = "0";
				$insert_user['referrer'] = "0";
				$insert_user['reputation'] = "0";
				$last_post = $this->get_last_post($user['user_id']);
				$insert_user['regip'] = $last_post['poster_ip'];
				$insert_user['timeonline'] = "0";
				$insert_user['totalpms'] = $this->get_private_messages($user['user_id']);
				$insert_user['unreadpms'] = $user['user_unread_privmsg'];
				$insert_user['pmfolders'] = '1**Inbox$%%$2**Sent Items$%%$3**Drafts$%%$4**Trash Can';		
				$uid = $this->insert_user($insert_user);
				
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no Users to import. Please press next to continue.";
			}
		}
		$import_session['start_users'] += $import_session['users_per_screen'];
		$output->print_footer();
	}
	
	function import_categories()
	{
		global $mybb, $output, $import_session, $db;

		$this->phpbb_db_connect();

		// Get number of categories
		if(!isset($import_session['total_cats']))
		{
			$query = $this->old_db->simple_select("categories", "COUNT(*) as count");
			$import_session['total_cats'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['start_cats'])
		{
			// If there are more categories to do, continue, or else, move onto next module
			if($import_session['total_cats'] <= $import_session['start_cats'] + $import_session['cats_per_screen'])
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
<p><input type=\"text\" name=\"cats_per_screen\" value=\"\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{	
			$query = $this->old_db->simple_select("categories", "*", "", array('limit_start' => $import_session['start_cats'], 'limit' => $import_session['cats_per_screen']));
			while($cat = $this->old_db->fetch_array($query))
			{
				echo "Inserting category #{$cat['cat_id']}... ";
				
				// Values from phpBB
				$insert_forum['import_fid'] = (-1 * intval($cat['cat_id']));
				$insert_forum['name'] = $cat['cat_title'];
				$insert_forum['disporder'] = $cat['cat_order'];
				
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
			}
		}			
		$import_session['start_cats'] += $import_session['cats_per_screen'];
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

		if($import_session['total_forums'])
		{
			// If there are more forums to do, continue, or else, move onto next module
			if($import_session['total_forums'] <= $import_session['start_forums'] + $import_session['forums_per_screen'])
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
<p><input type=\"text\" name=\"forums_per_screen\" value=\"\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{	
			$query = $this->old_db->simple_select("forums", "*", "", array('limit_start' => $import_session['start_forums'], 'limit' => $import_session['forums_per_screen']));
			while($forum = $this->old_db->fetch_array($query))
			{
				echo "Inserting forum #{$forum['forum_id']}... ";
				
				// Values from phpBB
				$insert_forum['import_fid'] = intval($forum['forum_id']);
				$insert_forum['name'] = $forum['forum_name'];
				$insert_forum['description'] = $forum['forum_desc'];
				$insert_forum['pid'] = $this->get_import_fid((-1) * $forum['cat_id']);
				$insert_forum['disporder'] = $forum['forum_order'];
				$insert_forum['threads'] = $forum['forum_topics'];
				$insert_forum['posts'] = $forum['forum_posts'];
				
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
				$insert_forum['usepostcounts'] = 'yes';
	
				$fid = $this->insert_forum($insert_forum);
				
				// Update parent list.
				$update_array = array('parentlist' => $insert_forum['pid'].','.$fid);
				$db->update_query("forums", $update_array, "fid = {$fid}");
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no Forums to import. Please press next to continue.";
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

		if($import_session['total_threads'])
		{
			// If there are more threads to do, continue, or else, move onto next module
			if($import_session['total_threads'] <= $import_session['start_threads'] + $import_session['threads_per_screen'])
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
<p><input type=\"text\" name=\"threads_per_screen\" value=\"\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{		
			$query = $this->old_db->simple_select("topics", "*", "", array('limit_start' => $import_session['start_threads'], 'limit' => $import_session['threads_per_screen']));
			while($thread = $this->old_db->fetch_array($query))
			{
				echo "Inserting thread #{$thread['topic_id']}... ";
				
				$insert_thread['import_tid'] = $thread['topic_id'];
				$insert_thread['sticky'] = int_to_yesno($thread['topic_type']);
				$insert_thread['fid'] = $this->get_import_fid($thread['forum_id']);
				$insert_thread['firstpost'] = $thread['topic_first_post_id'];				
				$insert_thread['icon'] = '';
				$insert_thread['dateline'] = $thread['topic_time'];
				$insert_thread['subject'] = $thread['topic_title'];
				
				$insert_thread['poll'] = $thread['topic_vote'];
				$insert_thread['uid'] = $this->get_import_uid($thread['topic_poster']);
				$insert_thread['import_uid'] = $thread['topic_poster'];
				$insert_thread['views'] = $thread['topic_views'];
				$insert_thread['replies'] = $thread['topic_replies'];
				$insert_thread['closed'] = int_to_yesno($thread['topic_status']);
				$insert_thread['totalratings'] = '0';
				$insert_thread['notes'] = '';
				$insert_thread['visible'] = '1';
				$insert_thread['unapprovedposts'] = '0';
				$insert_thread['numratings'] = '0';
				$insert_thread['attachmentcount'] = '0';
				
				$last_post = $this->get_post($thread['topic_last_post_id']);
				$insert_thread['lastpost'] = $last_post['post_time'];
				$insert_thread['lastposteruid'] = $this->get_import_uid($last_post['user_id']);
				
				$last_post_member = $this->get_user($last_post['topic_poster']);
				$insert_thread['lastposter'] = $last_post_member['username'];
				
				$member_started = $this->get_post($thread['topic_first_post_id']);
				$member_started = $this->get_user($member_started['poster_id']);
				$insert_thread['username'] = $member_started['username'];
				$this->insert_thread($insert_thread);
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no Threads to import. Please press next to continue.";
			}
		}
		$import_session['start_threads'] += $import_session['threads_per_screen'];
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
			if($import_session['total_posts'] <= $import_session['start_posts'] + $import_session['posts_per_screen'])
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
<p><input type=\"text\" name=\"posts_per_screen\" value=\"\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{	
			$query = $this->old_db->query("
				SELECT * 
				FROM ".PHPBB_TABLE_PREFIX."posts p
				LEFT JOIN ".PHPBB_TABLE_PREFIX."posts_text pt ON(p.post_id=pt.post_id)
				LIMIT ".$import_session['start_posts'].", ".$import_session['posts_per_screen']
			);
			while($post = $this->old_db->fetch_array($query))
			{
				echo "Inserting post #{$post['post_id']}... ";
				
				$insert_post['import_pid'] = $post['post_id'];
				$insert_post['tid'] = $this->get_import_tid($post['topic_id']);
				
				// Find if this is the first post in thread
				$query1 = $db->simple_select("threads", "firstpost", "tid='{$insert_post['tid']}'");
				$first_post = $db->fetch_field($query1, "firstpost");
				
				// Make the replyto the first post of thread unless it is the first post
				if($first_post == $post['post_id'])
				{
					$insert_post['replyto'] = 0;
				}
				else
				{
					$insert_post['replyto'] = $first_post;
				}
				
				// Check the last post for any NULL's, converted by phpBB's parser to a default topic
				if($post['post_subject'] == 'NULL')
				{
					$post['post_subject'] = 'Welcome to phpBB 2';
				}
				
				// Get Username
				$topic_poster = $this->get_user($post['poster_id']);
				$post['username'] = $topic_poster['username'];
				
				// Check usernames for guests
				if($post['username'] == 'NULL')
				{
					$post['username'] = 'Guest';
				}

				$insert_post['fid'] = $this->get_import_fid($post['forum_id']);
				$insert_post['subject'] = $post['post_subject'];
				$insert_post['icon'] = 0;
				$insert_post['uid'] = $this->get_import_uid($post['poster_id']);
				$insert_post['import_uid'] = $post['poster_id'];
				$insert_post['username'] = $post['username'];
				$insert_post['dateline'] = $post['post_time'];
				$insert_post['message'] = str_replace($post['bbcode_uid'], '', htmlspecialchars_decode($post['post_text']));
				$insert_post['ipaddress'] = $this->decode_ip($post['poster_ip']);
				$insert_post['includesig'] = int_to_yesno($post['enable_sig']);		
				$insert_post['smilieoff'] = int_to_noyes($post['enable_smilies']);		
				$insert_post['edituid'] = 0;				
				$insert_post['edittime'] = 0;
				$insert_post['visible'] = 1;
				$insert_post['posthash'] = '';

				$pid = $this->insert_post($insert_post);
				
				// Update thread count
				update_thread_count($insert_post['tid']);
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no Posts to import. Please press next to continue.";
			}
		}
		$import_session['start_posts'] += $import_session['posts_per_screen'];
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
			if($import_session['total_usergroups'] <= $import_session['start_usergroups'] + $import_session['usergroups_per_screen'])
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
<p><input type=\"text\" name=\"usergroups_per_screen\" value=\"\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{	
			// Get only non-staff groups.
			$query = $this->old_db->simple_select("groups", "*", "group_id > 2", array('limit_start' => $import_session['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
			while($group = $this->old_db->fetch_array($query))
			{
				echo "Inserting group #{$group['group_id']} as a ";
				
				// Make this into a usergroup
				$insert_group['import_gid'] = $group['group_id'];
				$insert_group['type'] = 2;
				$insert_group['title'] = $group['group_name'];
				$insert_group['description'] = $group['group_description'];
				
				// Default values
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
				$update_array = array('usergroup' => $gid);
				$db->update_query("users", $update_array, "import_usergroup = '{$group['group_id']}'");
				$db->update_query("users", $update_array, "import_displaygroup = '{$group['group_id']}'");
				$query1 = $db->simple_select("users", "uid, import_additionalgroups AS additionalGroups", "CONCAT(',', import_additionalgroups, ',') LIKE '%,{$group['group_id']},%'");
				
				$this->import_gids = null; // Force cache refresh
				
				echo "done.<br />\n";	
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no Usergroups to import. Please press next to continue.";
			}
		}
		$import_session['start_usergroups'] += $import_session['usergroups_per_screen'];
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
			if($import_session['total_privatemessages'] <= $import_session['start_privatemessages'] + $import_session['privatemessages_per_screen'])
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
<p><input type=\"text\" name=\"privatemessages_per_screen\" value=\"\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			$query = $this->old_db->query("
				SELECT * 
				FROM ".PHPBB_TABLE_PREFIX."privmsgs p
				LEFT JOIN ".PHPBB_TABLE_PREFIX."privmsgs_text pt ON(p.privmsgs_id=pt.privmsgs_text_id)
				LIMIT ".$import_session['start_privatemessages'].", ".$import_session['privatemessages_per_screen']
			);
			
			while($pm = $this->old_db->fetch_array($query))
			{
				echo "Inserting Private Message #{$pm['privmsgs_id']}... ";
				
				$insert_pm['pmid'] = null;
				$insert_pm['import_pmid'] = $pm['privmsgs_id'];
				$insert_pm['uid'] = $this->get_import_uid($pm['privmsgs_from_userid']);
				$insert_pm['fromid'] = $this->get_import_uid($pm['privmsgs_from_userid']);
				$insert_pm['toid'] = $this->get_import_uid($pm['privmsgs_to_userid']);
				$insert_pm['recipients'] = 'a:1:{s:2:"to";a:1:{i:0;s:'.strlen($insert_pm['toid']).':"'.$insert_pm['toid'].'";}}';
				$insert_pm['folder'] = '1';
				$insert_pm['subject'] = $pm['privmsgs_subject'];
				$insert_pm['status'] = $pm['is_read'];
				$insert_pm['dateline'] = $pm['privmsgs_date'];
				$insert_pm['message'] = str_replace($pm['privmsgs_bbcode_uid'], '', htmlspecialchars_decode($pm['privmsgs_text']));
				$insert_pm['includesig'] = int_to_yesno($pm['privmsgs_attach_sig']);
				$insert_pm['smilieoff'] = int_to_noyes($pm['privmsgs_enable_smilies']);
				if($insert_pm['smilieoff'] == 'no')
				{
					$insert_pm['smilieoff'] = '';
				}
				$insert_pm['icon'] = '0';
				$insert_pm['readtime'] = time();
				$insert_pm['receipt'] = '2';

				$this->insert_privatemessage($insert_pm);
				echo "done.<br />\n";
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no Private Messages to import. Please press next to continue.";
			}
		}
		$import_session['start_privatemessages'] += $import_session['privatemessages_per_screen'];
		$output->print_footer();
	}
	
	/**
	 * Get total number of Private Messages the user has from the phpBB database
	 * @param int User ID
	 * @return int Number of Private Messages
	 */
	 function get_private_messages($uid)
	 {
	 	$query = $this->old_db->simple_select("privmsgs", "COUNT(*) as pms", "privmsgs_to_userid = '$uid' OR privmsgs_from_userid = '$uid'");
		
		return $this->old_db->fetch_field($query, 'pms');
	 }
	
	/**
	 * Get a post from the phpBB database
	 * @param int Post ID
	 * @return array The post
	 */
	function get_post($pid)
	{		
		$query = $this->old_db->query("
			SELECT *
			FROM ".PHPBB_TABLE_PREFIX."posts p
			LEFT JOIN ".PHPBB_TABLE_PREFIX."posts_text pt ON (p.post_id=pt.post_id)
			WHERE p.post_id='{$pid}'
			LIMIT 1
		");
		
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Get a user from the phpBB database
	 * @param int User ID
	 * @return array If the uid is 0, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_user($uid)
	{
		if($uid == 0)
		{
			return array(
				'username' => 'Guest',
			);
		}
		
		$query = $this->old_db->simple_select("users", "*", "user_id='{$uid}'", array('limit' => 1));
		
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Gets the time of the last post of a user from the phpBB database
	 * @param int User ID
	 * @return int Last post time
	 */
	function get_last_post($uid)
	{
		$query = $this->old_db->simple_select("posts", "post_time", "poster_id='{$uid}'", array('order_by' => 'post_time', 'order_dir' => 'DESC', 'limit' => 1));
		return $this->old_db->fetch_field($query, "post_time");
	}
	
	/**
	 * Convert a phpBB group ID into a MyBB group ID
	 */
	function get_group_id($uid, $not_multiple=false, $orig=false)
	{
		$settings = array();
		if($not_mutliple == false)
		{
			$query = $this->old_db->simple_select("user_group", "COUNT(*) as rows", "user_id='{$uid}'");
			$settings = array('limit_start' => '1', $this->old_db->fetch_field($query, 'rows'));
		}
		
		$query = $this->old_db->simple_select("user_group", "*", "user_id='{$uid}'", $settings);
		
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
					case 2: // Administrator
						$group .= 4;
						break;
					default:
						if($this->get_import_gid($phpbbgroup) > 0)
						{
							// If there is an associated custom group...
							$group .= $this->get_import_gid($phpbbgroup);
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
	
	// Decode function for phpBB's IP Addresses
	function decode_ip($ip)
	{
		$hex_ip = explode('.', chunk_split($ip, 2, '.'));
		return hexdec($hex_ip[0]). '.' . hexdec($hex_ip[1]) . '.' . hexdec($hex_ip[2]) . '.' . hexdec($hex_ip[3]);
	}
}

?>