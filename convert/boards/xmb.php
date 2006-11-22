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
 
// Board Name: XMB

class Convert_xmb extends Converter {
	var $bbname = "XMB";
	var $modules = array("db_configuration" => array("name" => "Database Configuration",
									  "dependencies" => ""),
						 "import_users" => array("name" => "Import XMB Users",
									  "dependencies" => "db_configuration"),
						 "import_categories" => array("name" => "Import XMB Categories",
						 			  "dependencies" => "db_configuration,import_users"),
						 "import_forums" => array("name" => "Import XMB Forums",
									  "dependencies" => "db_configuration,import_categories"),
						 "import_threads" => array("name" => "Import XMB Threads",
									  "dependencies" => "db_configuration,import_forums"),
						 "import_posts" => array("name" => "Import XMB Posts",
									  "dependencies" => "db_configuration,import_threads"),
						);

	function xmb_db_connect()
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
		
		define('XMB_TABLE_PREFIX', $import_session['old_tbl_prefix']);
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
				if(!$this->old_db->table_exists("members"))
				{
					$errors[] = "The XMB table '{$mybb->input['tableprefix']}members' could not be found in database '{$mybb->input['dbname']}'.  Please ensure phpBB exists at this database and with this table prefix.";
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

		$output->print_header("XMB Database Configuration");

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
			echo "<p>Please enter the database details for your current installation of XMB.</p>";
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
<div class="title">XMB Database Configuration</div>
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
		
		$this->xmb_db_connect();
		
		// Get number of members
		if(!isset($import_session['total_members']))
		{
			$query = $this->old_db->simple_select("members", "COUNT(*) as count");
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
			$query = $this->old_db->simple_select("members", "*", "", array('limit_start' => $import_session['start_users'], 'limit' => $import_session['users_per_screen']));

			while($user = $this->old_db->fetch_array($query))
			{
				echo "Adding user #{$user['uid']}... ";
					
				$query2 = $this->old_db->simple_select("posts", "dateline", "author='{$user['username']}'", array('order_by' => 'dateline', 'order_dir' => 'desc', 'limit' => 1));
				$insert_user['lastpost'] = $this->old_db->fetch_field($query2, 'dateline');
				
				// Check for duplicate members
				if(in_array($user['username'], $members_cache))
				{
					++$member_dup_count;
					$user['username'] .= "_xmb_import".$member_dup_count;
				}
				
				$members_cache[] = $user['username'];				
				
				if($user['status'] == 'Super Administrator')
				{
					$insert_user['usergroup'] = 4;
				}
				else
				{
					$insert_user['usergroup'] = 2;
				}
				$insert_user['additionalgroups'] = '';
				$insert_user['displaygroup'] = $insert_user['usergroup'];
				$insert_user['import_usergroup'] = $insert_user['usergroup'];
				$insert_user['import_additionalgroups'] = '';
				$insert_user['import_displaygroup'] = $insert_user['usergroup'];
				$insert_user['import_uid'] = $user['uid'];
				$insert_user['username'] = $user['username'];
				$insert_user['email'] = $user['email'];
				$insert_user['regdate'] = $user['regdate'];
				$insert_user['postnum'] = $user['postnum'];
				$insert_user['lastactive'] = $user['lastvisit'];
				$insert_user['lastvisit'] = $user['lastvisit'];
				$insert_user['website'] = $user['site'];
				$insert_user['avatardimensions'] = '';
				$insert_user['avatartype'] = '';
				$insert_user['avatar'] = $user['avatar'];
				if($user['bday'] == '0000-00-00')
				{
					$user['bday'] = '';
				}
				$insert_user['birthday'] = $user['bday'];
				$insert_user['icq'] = $user['icq'];
				$insert_user['aim'] = $user['aim'];
				$insert_user['yahoo'] = $user['yahoo'];
				$insert_user['msn'] = $user['msn'];
				$insert_user['hideemail'] = $user['showemail'];
				$insert_user['invisible'] = int_to_yesno($user['invisible']);
				$insert_user['allownotices'] = 'yes';
				$insert_user['emailnotify'] = 'yes';
				$insert_user['receivepms'] = 'yes';
				$insert_user['pmpopup'] = 'yes';
				$insert_user['pmnotify'] = 'yes';				
				$insert_user['remember'] = "yes";
				$insert_user['showsigs'] = 'yes';
				$insert_user['showavatars'] = 'yes';
				$insert_user['showquickreply'] = "yes";
				$insert_user['ppp'] = $user['ppp'];
				$insert_user['tpp'] = $user['tpp'];
				$insert_user['daysprune'] = "0";
				$insert_user['timeformat'] = $user['timeformat'];
				$insert_user['timezone'] = $user['timeoffset'];
				$insert_user['dst'] = '';
				$insert_user['buddylist'] = "";
				$insert_user['ignorelist'] = "";
				$insert_user['style'] = $user['theme'];
				$insert_user['away'] = "no";
				$insert_user['awaydate'] = "0";
				$insert_user['returndate'] = "0";
				$insert_user['referrer'] = "0";
				$insert_user['reputation'] = "0";
				$insert_user['regip'] = '';
				$insert_user['timeonline'] = 0;
				$insert_user['totalpms'] = 0;
				$insert_user['unreadpms'] = 0;
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

		$this->xmb_db_connect();

		// Get number of forums
		if(!isset($import_session['total_cats']))
		{
			$query = $this->old_db->simple_select("forums", "COUNT(*) as count", "type='group'");
			$import_session['total_cats'] = $this->old_db->fetch_field($query, 'count');				
		}

		if($import_session['total_cats'])
		{
			// If there are more forums to do, continue, or else, move onto next module
			if($import_session['total_cats'] <= $import_session['start_cats'] + $import_session['cats_per_screen'])
			{
				$import_session['disabled'][] = 'import_categories';
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
			echo "<p>Please select how many categories to import at a time:</p>
<p><input type=\"text\" name=\"cats_per_screen\" value=\"\" /></p>";
			$output->print_footer($import_session['module'], 'module', 1);
		}
		else
		{	
			$query = $this->old_db->simple_select("forums", "*", "type='group'", array('limit_start' => $import_session['start_cats'], 'limit' => $import_session['cats_per_screen']));
			while($cat = $this->old_db->fetch_array($query))
			{
				echo "Inserting category #{$cat['fid']}... ";
				
				// Values from XMB
				$insert_forum['import_fid'] = intval($cat['fid']);
				$insert_forum['name'] = $cat['name'];
				$insert_forum['description'] = $cat['description'];				
				$insert_forum['disporder'] = $cat['displayorder'];
				$insert_forum['threads'] = $cat['threads'];
				$insert_forum['posts'] = $cat['posts'];
				
				if($cat['status'] == 'on')
				{
					$insert_forum['active'] = 'yes';
				}
				else
				{
					$insert_forum['active'] = 'no';
				}
				
				$insert_forum['style'] = $cat['theme'];
				$insert_forum['password'] = $cat['password'];
				$insert_forum['import_fid'] = (-1 * intval($cat['fid']));
				
				$lastpost = @explode('|', $cat['lastpost']);				
				$insert_forum['lastpost'] = $lastpost[0];
				$insert_forum['lastposteruid'] = $this->get_import_uid($lastpost[2]);
				$query2 = $this->old_db->simple_select("posts", "tid,subject", "fid='{$cat['fid']}'", array('order_by' => 'dateline', 'order_dir' => 'desc', 'limit' => 1));
				$insert_forum['lastposttid'] = $this->old_db->fetch_field($query2, 'tid');
				$insert_forum['lastpostsubject'] = $this->old_db->fetch_field($query2, 'subject');
				
				// Default values
				$insert_forum['type'] = 'c';				
				$insert_forum['lastpost'] = 0;
				$insert_forum['lastposteruid'] = 0;
				$insert_forum['lastposttid'] = 0;
				$insert_forum['lastpostsubject'] = '';
				$insert_forum['allowhtml'] = 'no';
				$insert_forum['allowmycode'] = 'yes';
				$insert_forum['allowsmilies'] = 'yes';
				$insert_forum['allowimgcode'] = 'yes';
				$insert_forum['parentlist'] = '';
				$insert_forum['open'] = 'yes';
				$insert_forum['allowpicons'] = 'yes';
				$insert_forum['allowtratings'] = 'yes';
				$insert_forum['status'] = 1;
				$insert_forum['showinjump'] = 'yes';
				$insert_forum['modposts'] = 'no';
				$insert_forum['modthreads'] = 'no';
				$insert_forum['modattachments'] = 'no';
				$insert_forum['overridestyle'] = 'no';
				$insert_forum['unapprovedthreads'] = '';
				$insert_forum['unapprovedposts'] = '';
				$insert_forum['defaultdatecut'] = 0;
				$insert_forum['defaultsortby'] = '';
				$insert_forum['defaultsortorder'] = '';
				$insert_forum['usepostcounts'] = 'yes';
	
				$fid = $this->insert_forum($insert_forum);
				
				// Update parent list.
				$update_array = array('parentlist' => $fid);
				$db->update_query("forums", $update_array, "fid = '{$fid}'");
				
				echo "done.<br />\n";			
			}
			
			if($this->old_db->num_rows($query) == 0)
			{
				echo "There are no categories to import. Please press next to continue.";
			}
		}
		$import_session['start_cats'] += $import_session['cats_per_screen'];
		$output->print_footer();	
	}
	
	function import_forums()
	{
		global $mybb, $output, $import_session, $db;

		$this->xmb_db_connect();

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
			$query = $this->old_db->simple_select("forums", "*", "type != 'group'", array('limit_start' => $import_session['start_forums'], 'limit' => $import_session['forums_per_screen']));
			while($forum = $this->old_db->fetch_array($query))
			{
				echo "Inserting forum #{$forum['fid']}... ";
				
				// Values from XMB
				$insert_forum['import_fid'] = intval($forum['fid']);
				$insert_forum['name'] = $forum['name'];
				$insert_forum['description'] = $forum['description'];				
				$insert_forum['disporder'] = $forum['displayorder'];
				$insert_forum['threads'] = $forum['threads'];
				$insert_forum['posts'] = $forum['posts'];
				
				if($forum['status'] == 'on')
				{
					$insert_forum['active'] = 'yes';
				}
				else
				{
					$insert_forum['active'] = 'no';
				}
				
				$insert_forum['style'] = $forum['theme'];
				$insert_forum['password'] = $forum['password'];
				
				$lastpost = @explode('|', $forum['lastpost']);	
				$insert_forum['lastpost'] = $lastpost[0];
				$insert_forum['lastposteruid'] = $this->get_import_uid($lastpost[2]);
				$insert_forum['lastposter'] = $this->get_import_username($lastpost[2]);
				
				$lastpost = $this->get_lastpost($forum['fid']);
				$insert_forum['lastposttid'] = $lastpost['tid'];
				$insert_forum['lastpostsubject'] = $lastpost['subject'];
				
				$insert_forum['type'] = 'f';
				
				// Check if this forum has a parent id
				if($forum['fup'] == 0)
				{
					// We don't have a parent. Assign it to the latest category
					$query3 = $db->simple_select("forums", "fid", "type='c'", array('limit' => 1, 'order_by' => 'fid', 'order_dir' => 'desc'));
					$insert_forum['pid'] = $db->fetch_field($query3, "fid");
				}
				else
				{	
					// Otherwise, assign it to it's proper category
					$insert_forum['pid'] = $this->get_import_fid((-1) * $forum['fup']);
				}
				
				if($forum['allowhtml'] == 'off')
				{
					$forum['allowhtml'] = 'no';
				}
				
				if($forum['allowbbcode'] == 'off')
				{
					$forum['allowbbcode'] = 'no';
				}
				
				if($forum['allowsmilies'] == 'off')
				{
					$forum['allowsmilies'] = 'no';
				}
				
				if($forum['allowimgcode'] == 'off')
				{
					$forum['allowimgcode'] = 'no';
				}
				
				$insert_forum['allowhtml'] = $forum['allowhtml'];
				$insert_forum['allowmycode'] = $forum['allowbbcode'];
				$insert_forum['allowsmilies'] = $forum['allowsmilies'];
				$insert_forum['allowimgcode'] = $forum['allowimgcode'];
		
				
				// Default values
				$insert_forum['parentlist'] = '';
				$insert_forum['open'] = 'yes';
				$insert_forum['allowpicons'] = 'yes';
				$insert_forum['allowtratings'] = 'yes';
				$insert_forum['status'] = 1;
				$insert_forum['showinjump'] = 'yes';
				$insert_forum['modposts'] = 'no';
				$insert_forum['modthreads'] = 'no';
				$insert_forum['modattachments'] = 'no';
				$insert_forum['overridestyle'] = 'no';
				$insert_forum['unapprovedthreads'] = '';
				$insert_forum['unapprovedposts'] = '';
				$insert_forum['defaultdatecut'] = 0;
				$insert_forum['defaultsortby'] = '';
				$insert_forum['defaultsortorder'] = '';
				$insert_forum['usepostcounts'] = 'yes';
				
				$fid = $this->insert_forum($insert_forum);
				
				// Update parent list.
				$update_array = array('parentlist' => $insert_forum['pid'].','.$fid);
				$db->update_query("forums", $update_array, "fid = '{$fid}'");
				
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

		$this->xmb_db_connect();

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("threads", "COUNT(*) as count");
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
			$query = $this->old_db->simple_select("threads", "*", "", array('limit_start' => $import_session['start_threads'], 'limit' => $import_session['threads_per_screen']));
			while($thread = $this->old_db->fetch_array($query))
			{
				echo "Inserting thread #{$thread['tid']}... ";
				
				// Check usernames for guests
				if($thread['author'] == 'Anonymous')
				{
					$thread['author'] = 'Guest';
				}
				
				$insert_thread['import_tid'] = $thread['tid'];
				$insert_thread['sticky'] = int_to_yesno($thread['topped']);
				$insert_thread['fid'] = $this->get_import_fid($thread['fid']);
				$insert_thread['username'] = $this->get_import_username($this->get_uid($thread['author']));
				
				$firstpost = $this->get_firstpost($thread['fid']);
				$insert_thread['firstpost'] = (-1 * intval($firstpost['pid']));
				$insert_thread['icon'] = $thread['icon'];
				$insert_thread['dateline'] = $firstpost['dateline'];
				$insert_thread['subject'] = $thread['subject'];
				$insert_thread['poll'] = 0;
				
				if($thread['pollopts'])
				{
					$poll_options = explode("#|#", $thread['pollopts']);
	      			$poll_count_opt = count($poll_options)-1;
				
					$num_votes = 0;
					$seperator = '';
					$numvotes = 0;
					$numoptions = 0;
					$votes = '';
					$options = '';
	
					for ($i=0; $i < $poll_count_opt; $i++)
					{
						$poll_option = @explode('||~|~||', $poll_options[$i]);
						$options .= $seperator.$poll_option[0];
						$votes .= $seperator.$poll_option[1];
						++$numoptions;
						$numvotes += $poll_option[1];
						$seperator = '||~|~||';
					}
					
					$insert_poll['import_tid'] = $thread['tid'];
					$insert_poll['tid'] = '';
					$insert_poll['pid'] = '';
					$insert_poll['question'] = $thread['subject'];
					$insert_poll['dateline'] = $insert_thread['dateline'];
					$insert_poll['options'] = preg_replace("#\r|\n|\r\n#i", '', $options);
					$insert_poll['votes'] = str_replace(' ' , '', $votes);
					$insert_poll['numoptions'] = $numoptions;
					$insert_poll['numvotes'] = $numvotes;
					$insert_poll['timeout'] = 0;
					$insert_poll['closed'] = '';
					$insert_poll['multiple'] = 'no';
					$insert_poll['public'] = 'no';
					$pollid = $this->insert_poll($insert_poll);
					// Needto add to mybb_pollvote table here
					
					$insert_thread['poll'] = $pollid;
				}
				
				$insert_thread['import_uid'] = $this->get_uid($thread['author']);
				$insert_thread['uid'] = $this->get_import_uid($insert_thread['uid']);
				$insert_thread['views'] = $thread['views'];
				$insert_thread['replies'] = $thread['replies'];
				$insert_thread['closed'] = $thread['closed'];
				$insert_thread['totalratings'] = '0';
				$insert_thread['notes'] = '';
				$insert_thread['visible'] = 1;
				$insert_thread['unapprovedposts'] = '0';
				$insert_thread['numratings'] = '0';
				$insert_thread['attachmentcount'] = '0';
				
				$lastpost = @explode('|', $thread['lastpost']);
				
				$insert_thread['lastpost'] = $lastpost[0];
				$insert_thread['lastposteruid'] = $this->get_import_uid($lastpost[2]);				
				$insert_thread['lastposter'] = $this->get_import_username($lastpost[2]);
				
				$tid = $this->insert_thread($insert_thread);
				
				// Fix Poll connections
				if($pollid)
				{
					$db->update_query("polls", array('tid' => $tid), "import_tid='".$thread['tid']."'");
				}
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

		$this->xmb_db_connect();

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
			$query = $this->old_db->simple_select("posts", "*", "", array('limit_start' => $import_session['start_posts'], 'limit' => $import_session['posts_per_screen']));
			
			while($post = $this->old_db->fetch_array($query))
			{
				echo "Inserting post #{$post['pid']}... ";
				
				$insert_post['import_pid'] = $post['pid'];
				$insert_post['tid'] = $this->get_import_tid($post['tid']);
				
				// Find if this is the first post in thread
				$query2 = $db->simple_select("threads", "firstpost", "tid='{$insert_post['tid']}'");
				$first_post = $db->fetch_field($query2, "firstpost");
				
				// Make the replyto the first post of thread unless it is the first post
				if($first_post == $post['pid'])
				{
					$insert_post['replyto'] = 0;
				}
				else
				{
					$insert_post['replyto'] = $first_post;
				}
				
				// Check usernames for guests
				if($post['author'] == 'Anonymous')
				{
					$post['author'] = 'Guest';
				}
				
				$insert_post['fid'] = $this->get_import_fid($post['fid']);
				$insert_post['subject'] = $post['subject'];
				$insert_post['icon'] = $post['icon'];
				$insert_post['import_uid'] = $this->get_uid($post['author']);
				$insert_post['uid'] = $this->get_import_uid($insert_post['import_uid']);				
				$insert_post['username'] = $post['author'];
				$insert_post['dateline'] = $post['dateline'];
				$insert_post['message'] = $post['message'];
				$insert_post['ipaddress'] = $post['useip'];
				$insert_post['includesig'] = $post['usesig'];		
				$insert_post['smilieoff'] = $post['smileyoff'];		
				$insert_post['edituid'] = 0;
				$insert_post['edittime'] = 0;
				$insert_post['visible'] = 1;
				$insert_post['posthash'] = '';

				$pid = $this->insert_post($insert_post);
				
				// Update thread count
				update_thread_count($insert_post['tid']);
				
				// Restore connection
				$db->update_query("threads", array('firstpost' => $pid), "firstpost='".((-1) * $post['pid'])."'");
				
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
	
	function get_lastpost($fid)
	{
		$query = $this->old_db->simple_select("posts", "tid,subject", "fid='{$fid}'", array('order_by' => 'dateline', 'order_dir' => 'desc', 'limit' => 1));
		return $this->old_db->fetch_array($query);
	}
	
	function get_firstpost($fid)
	{
		$query = $this->old_db->simple_select("posts", "pid,dateline", "fid='{$fid}'", array('order_by' => 'dateline', 'order_dir' => 'asc', 'limit' => 1));
		return $this->old_db->fetch_array($query);
	}
	
	function get_uid($username)
	{
		if($username == 'Guest')
		{
			return 0;
		}
		$query = $this->old_db->simple_select("members", "uid", "username = '{$username}'");
		return $this->old_db->fetch_field($query, "uid");
	}	
}

?>