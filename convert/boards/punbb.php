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
 
// Board Name: punBB 1.2

class Convert_punbb extends Converter {
	var $bbname = "punBB 1.2";
	var $modules = array("db_configuration" => array("name" => "Database Configuration",
									  "dependencies" => ""),
						 "import_usergroups" => array("name" => "Import punBB 1.2 Usergroups",
									  "dependencies" => "db_configuration"),
						 "import_categories" => array("name" => "Import punBB 1.2 Categories",
									  "dependencies" => "db_configuration"),
						 "import_forums" => array("name" => "Import punBB 1.2 Forums",
									  "dependencies" => "db_configuration,import_categories"),
						 "import_threads" => array("name" => "Import punBB 1.2 Threads",
									  "dependencies" => "db_configuration,import_forums"),
						 "import_posts" => array("name" => "Import punBB 1.2 Posts",
									  "dependencies" => "db_configuration,import_threads"),
						 "import_users" => array("name" => "Import punBB 1.2 Users",
									  "dependencies" => "db_configuration,import_usergroups"),
						);

	function punbb_db_connect()
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

		define('PUNBB_TABLE_PREFIX', $import_session['old_tbl_prefix']);
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

				// Need to check if punBB is actually installed here
				$this->old_db->set_table_prefix($mybb->input['tableprefix']);
				if(!$this->old_db->table_exists("users"))
				{
					$errors[] = "The punBB table '{$mybb->input['tableprefix']}users' could not be found in database '{$mybb->input['dbname']}'.  Please ensure punBB exists at this database and with this table prefix.";
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

		$output->print_header("punBB Database Configuration");

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
			echo "<p>Please enter the database details for your current installation of punBB.</p>";
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
<div class="title">punBB Database Configuration</div>
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
		
		$this->punbb_db_connect();
		
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
		
		if($import_session['users_per_screen'] == 0)
		{
			$import_session['start_users'] = 0;
			echo "<p>Please select how many users to import at a time:</p>
<p><input type=\"text\" name=\"users_per_screen\" value=\"100\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{
			// Count the total number of users so we can generate a unique id if we have a duplicate user
			$query = $db->simple_select("users", "COUNT(*) as totalusers");
			$total_users = $db->fetch_field($query, "totalusers");
			
			// Get members
			$query = $this->old_db->simple_select("users", "*", "username != 'Guest'", array('limit_start' => $import_session['start_users'], 'limit' => $import_session['users_per_screen']));

			while($user = $this->old_db->fetch_array($query))
			{
				++$total_users;
					
				$query1 = $db->simple_select("users", "username,email,uid", " LOWER(username)='".$db->escape_string(strtolower($user['username']))."'");
				$duplicate_user = $db->fetch_array($query1);
				if($duplicate_user['username'] && strtolower($user['email']) == strtolower($duplicate_user['email']))
				{
					echo "Merging user #{$user['id']} with user #{$duplicate_user['uid']}... done.<br />";
					continue;
				}
				else if($duplicate_user['username'])
				{					
					$import_user['username'] = $duplicate_user['username']."_vb3_import".$total_users;
				}
				
				echo "Adding user #{$user['id']}... ";

				$insert_user['usergroup'] = $this->get_group_id($user['id'], true);
				$insert_user['additionalgroups'] = $this->get_group_id($user['id']);
				$insert_user['displaygroup'] = $this->get_group_id($user['id'], true);
				$insert_user['import_usergroup'] = $this->get_group_id($user['id'], true, true);
				$insert_user['import_additionalgroups'] = $this->get_group_id($user['id'], false, true);
				$insert_user['import_displaygroup'] = $user['group_id'];
				$insert_user['import_uid'] = $user['id'];
				$insert_user['username'] = $user['username'];
				$insert_user['email'] = $user['email'];
				$insert_user['regdate'] = $user['registered'];
				$insert_user['postnum'] = $user['num_posts'];
				$insert_user['lastactive'] = $user['last_visit'];
				$insert_user['lastvisit'] = $user['last_visit'];
				$insert_user['website'] = $user['url'];
				//$user['avatardimensions']
				//$user['avatartype']
				$insert_user['lastpost'] = $user['last_post'];
				$insert_user['birthday'] = '';
				$insert_user['icq'] = $user['icq'];
				$insert_user['aim'] = $user['aim'];
				$insert_user['yahoo'] = $user['yahoo'];
				$insert_user['msn'] = $user['msn'];
				$insert_user['hideemail'] = int_to_yesno($user['email_setting']);
				$insert_user['invisible'] = 'no';
				$insert_user['allownotices'] = int_to_yesno($user['notify_with_post']);
				$insert_user['emailnotify'] = 'yes';
				$insert_user['receivepms'] = 'yes';
				$insert_user['pmpopup'] = 'yes';
				$insert_user['pmnotify'] = 'yes';
				$insert_user['remember'] = 'yes';
				$insert_user['showsigs'] = int_to_yesno($user['show_sig']);
				$insert_user['showavatars'] = int_to_yesno($user['show_avatars']); // Check ?
				$insert_user['showquickreply'] = 'yes';
				$insert_user['ppp'] = 0;
				$insert_user['tpp'] = 0;
				$insert_user['daysprune'] = 0;
				$insert_user['timeformat'] = 'd M';				
				$insert_user['timezone'] = $user['timezone'];
				$insert_user['timezone'] = str_replace(array('.0', '.00'), array('', ''), $insert_user['timezone']);	
				$insert_user['dst'] = 'no';
				$insert_user['buddylist'] = '';
				$insert_user['ignorelist'] = '';
				$insert_user['style'] = 0;
				$insert_user['away'] = 'no';
				$insert_user['awaydate'] = 0;
				$insert_user['returndate'] = 0;
				$insert_user['referrer'] = 0;
				$insert_user['reputation'] = 0;
				$insert_user['regip'] = $user['registration_ip'];
				$insert_user['timeonline'] = 0;
				$insert_user['totalpms'] = 0;
				$insert_user['unreadpms'] = 0;
				$insert_user['pmfolders'] = '1**Inbox$%%$2**Sent Items$%%$3**Drafts$%%$4**Trash Can';
				$insert_user['signature'] = '';
				$insert_user['notepad'] = '';
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

		$this->punbb_db_connect();

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
			$query = $this->old_db->simple_select("categories", "*", "", array('limit_start' => $import_session['start_cats'], 'limit' => $import_session['cats_per_screen']));
			while($cat = $this->old_db->fetch_array($query))
			{
				echo "Inserting category #{$cat['id']}... ";
				
				// punBB Values
				$insert_forum['import_fid'] = (-1 * intval($cat['id']));
				$insert_forum['name'] = $cat['cat_name'];
				$insert_forum['disporder'] = $cat['disp_position'];
				
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

		$this->punbb_db_connect();

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
			$query = $this->old_db->simple_select("forums", "*", "", array('limit_start' => $import_session['start_forums'], 'limit' => $import_session['forums_per_screen']));
			while($forum = $this->old_db->fetch_array($query))
			{
				echo "Inserting forum #{$forum['id']}... ";

				// Values from punBB
				$insert_forum['import_fid'] = intval($forum['id']);
				$insert_forum['name'] = $forum['forum_name'];
				$insert_forum['description'] = $forum['forum_desc'];
				$insert_forum['pid'] = $this->get_import_fid((-1) * $forum['cat_id']);
				$insert_forum['disporder'] = $forum['disp_position'];
				$insert_forum['threads'] = $forum['num_topics'];
				$insert_forum['posts'] = $forum['num_posts'];
				$insert_forum['linkto'] = $forum['redirect_url'];
				$insert_forum['lastpost'] = $forum['last_post'];
				$insert_forum['parentlist'] = $forum['cat_id'];
				$insert_forum['defaultsortby'] = $forum['sort_by'];
				
				$last_post = $this->get_last_post($forum['id']);
				$insert_forum['lastposter'] = $last_post['post']['poster'];
				$insert_forum['lastposttid'] = $last_post['post']['id'];
				$insert_forum['lastposteruid'] = $last_post['post']['poster_id'];				
				$insert_forum['lastpostsubject'] = $last_post['thread']['subject'];
				
				// Default values				
				$insert_forum['type'] = 'f';				
				$insert_forum['active'] = 'yes';
				$insert_forum['open'] = 'yes';				
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
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_forums'] += $import_session['forums_per_screen'];
		$output->print_footer();	
	}
	
	function import_threads()
	{
		global $mybb, $output, $import_session, $db;

		$this->punbb_db_connect();

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
			$query = $this->old_db->simple_select("topics", "*", "", array('limit_start' => $import_session['start_threads'], 'limit' => $import_session['threads_per_screen']));
			while($thread = $this->old_db->fetch_array($query))
			{
				echo "Inserting thread #{$thread['id']}... ";
				
				$insert_thread['import_tid'] = $thread['id'];
				$insert_thread['sticky'] = $thread['sticky'];
				$insert_thread['fid'] = $this->get_import_fid($thread['forum_id']);
				//$insert_thread['firstpost'] = $thread['topic_first_post_id'];	// To do			
				$insert_thread['icon'] = 0;
				$insert_thread['dateline'] = $thread['posted'];
				$insert_thread['subject'] = $thread['subject'];
				
				$user = $this->get_user($thread['poster']);
				
				$insert_thread['poll'] = 0;
				$insert_thread['uid'] = $this->get_import_uid($user['id']);
				$insert_thread['import_uid'] = $user['id'];
				$insert_thread['views'] = $thread['num_views'];
				$insert_thread['replies'] = $thread['num_replies'];
				$insert_thread['closed'] = int_to_yesno($thread['closed']);
				if($insert_thread['closed'] == "no")
				{
					$insert_thread['closed'] = '';
				}
				
				$insert_thread['totalratings'] = 0;
				$insert_thread['notes'] = '';
				$insert_thread['visible'] = 1;
				$insert_thread['unapprovedposts'] = 0;
				$insert_thread['numratings'] = 0;
				$insert_thread['attachmentcount'] = 0;				
				$insert_thread['lastpost'] = $thread['last_post'];
				$insert_thread['lastposter'] = $thread['last_poster'];
				$insert_thread['username'] = $thread['poster'];
				
				$lastpost_user = $this->get_user($thread['last_poster']);				
				$insert_thread['lastposteruid'] = $this->get_import_uid($lastpost_user['id']);
				
				$this->insert_thread($insert_thread);
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

		$this->punbb_db_connect();

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
			$query = $this->old_db->simple_select("posts", "*", "", array('limit_start' => $import_session['start_posts'], 'limit' => $import_session['posts_per_screen']));

			while($post = $this->old_db->fetch_array($query))
			{
				echo "Inserting post #{$post['id']}... ";

				$insert_post['import_pid'] = $post['id'];
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

				$query2 = $db->simple_select("threads", "*", "tid='".$this->get_import_tid($post['topic_id'])."'");
				$thread = $db->fetch_array($query2);
				$insert_post['subject'] = $thread['subject'];

				// Get Username
				$topic_poster = $this->get_user($post['poster_id']);
				$post['username'] = $topic_poster['username'];
				
				// Check usernames for guests
				if($post['username'] == 'NULL')
				{
					$post['username'] = 'Guest';
				}

				$insert_post['fid'] = $this->get_import_fid($thread['fid']);
				$insert_post['icon'] = 0;
				$insert_post['uid'] = $this->get_import_uid($post['poster_id']);
				$insert_post['import_uid'] = $post['poster_id'];
				$insert_post['username'] = $post['poster'];
				$insert_post['dateline'] = $post['posted'];
				$insert_post['message'] = $post['message'];
				$insert_post['ipaddress'] = $post['poster_ip'];
				$insert_post['includesig'] = 'yes';
				$insert_post['smilieoff'] = int_to_yesno($post['hide_smilies']);
				if($post['edited'] != 0)
				{
					$user = $this->get_user($post['edited_by']);
					$insert_post['edituid'] = $user['id'];
					$insert_post['edittime'] = $post['edited'];
				}
				else
				{	
					$insert_post['edituid'] = 0;
					$insert_post['edittime'] = 0;
				}
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
				define('BACK_BUTTON', false);
			}
		}
		$import_session['start_posts'] += $import_session['posts_per_screen'];
		$output->print_footer();
	}
	
	function import_usergroups()
	{
		global $mybb, $output, $import_session, $db;

		$this->punbb_db_connect();

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
			// Get only non-staff groups.
			$query = $this->old_db->simple_select("groups", "*", "g_id > 3", array('limit_start' => $import_session['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
			while($group = $this->old_db->fetch_array($query))
			{
				if($usergroup['g_title'] == 'Administrators' || $usergroup['g_title'] == 'Moderators' || $usergroup['g_title'] == 'Guest' || $usergroup['g_title'] == 'Members')
				{
					continue;
				}

				echo "Inserting group #{$group['g_id']} as a ";
				// Make this into a usergroup
				
				// PunBB Values
				$insert_group['import_gid'] = $group['g_id'];
				$insert_group['type'] = 2;
				$insert_group['title'] = $group['g_title'];
				$insert_group['description'] = '';
				$insert_group['namestyle'] = '{username}';
				$insert_group['canview'] = int_to_yesno($group['g_read_board']);
				$insert_group['canviewthreads'] = 'yes';
				$insert_group['canviewprofiles'] = 'yes';
				$insert_group['candlattachments'] = 'yes';
				$insert_group['canpostthreads'] = int_to_yesno($usergroup['g_post_topics']);
				$insert_group['canpostreplys'] = int_to_yesno($usergroup['g_post_replies']);
				$insert_group['canpostattachments'] = 'yes';
				$insert_group['canratethreads'] = 'yes';
				$insert_group['caneditposts'] = int_to_yesno($usergroup['g_edit_posts']);
				$insert_group['candeleteposts'] = int_to_yesno($usergroup['g_delete_posts']);
				$insert_group['candeletethreads'] = int_to_yesno($usergroup['g_delete_topics']);
				$insert_group['caneditattachments'] = 'yes';
				$insert_group['canpostpolls'] = 'yes';
				$insert_group['canvotepolls'] = 'yes';
				$insert_group['cansearch'] = int_to_yesno($usergroup['g_search']);
				$insert_group['canviewmemberlist'] = int_to_yesno($usergroup['g_search_users']);				

				// Default values
				$insert_group['stars'] = 0;
				$insert_group['starimage'] = 'images/star.gif';
				$insert_group['image'] = '';
				$insert_group['disporder'] = 0;
				$insert_group['isbannedgroup'] = 'no';				
				$insert_group['canusepms'] = 'yes';
				$insert_group['cansendpms'] = 'yes';
				$insert_group['cantrackpms'] = 'yes';
				$insert_group['candenypmreceipts'] = 'yes';
				$insert_group['pmquota'] = '0';
				$insert_group['maxpmrecipients'] = '5';
				$insert_group['cansendemail'] = 'yes';
				$insert_group['canviewcalendar'] = 'yes';
				$insert_group['canaddpublicevents'] = 'yes';
				$insert_group['canaddprivateevents'] = 'yes';
				$insert_group['canviewonline'] = 'yes';
				$insert_group['canviewwolinvis'] = 'no';
				$insert_group['canviewonlineips'] = 'no';
				$insert_group['cancp'] = 'no';
				$insert_group['issupermod'] = 'no';				
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
				$db->update_query("users", $update_array, "import_usergroup = '{$group['group_id']}' OR import_displaygroup = '{$group['group_id']}'");

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
	
	/**
	 * Get a post from the punBB database
	 * @param int Post ID
	 * @return array The post
	 */
	function get_post($pid)
	{
		$query = $this->old_db->simple_select("posts", "*", "id='{$pid}'");
		
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Get a user from the punBB database
	 * @param string Username
	 * @return array If the uid is 0, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_user($username)
	{		
		$query = $this->old_db->simple_select("users", "id, username", "username='{$username}'", array('limit' => 1));
		
		return $this->old_db->fetch_array($query);
	}
	
	/**
	 * Gets the time of the last post of a user from the punBB database
	 * @return array Post
	 */
	function get_last_post($fid)
	{
		$query = $this->old_db->simple_select("topics", "*", "forum_id = '$fid'", array('order_by' => 'posted', 'order_dir' => 'DESC', 'limit' => 1));
		$thread = $this->old_db->fetch_array($query);
		$query = $this->old_db->simple_select("posts", "*", "topic_id = '{$thread['id']}'", array('order_by' => 'posted', 'order_dir' => 'DESC', 'limit' => 1));
		return array(
			'post' => $this->old_db->fetch_array($query),
			'thread' => $thread
		);
	}
	
	/**
	 * Convert a punBB group ID into a MyBB group ID
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
		while($punbbgroup = $this->old_db->fetch_array($query))
		{
			if($orig == true)
			{
				$group .= $punbbgroup['g_id'].$comma;
			}
			else
			{
				$group .= $comma;
				switch($punbbgroup['g_id'])
				{
					case 1: // Administrator
						$group .= 4;
						break;
					case 2:
						$group .= 6;
						break;
					case 4:
						$group .= 2;
						break;	
					default:
						if($this->get_import_gid($punbbgroup) > 0)
						{
							// If there is an associated custom group...
							$group .= $this->get_import_gid($punbbgroup);
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