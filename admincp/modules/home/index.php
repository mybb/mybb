<?php
if(!$mybb->input['action'])
{
	if($mybb->request_method == "post" && isset($mybb->input['adminnotes']))
	{
		// Update Admin Notes cache
		$update_cache = array(
			"adminmessage" => $mybb->input['adminnotes']
		);
		
		$cache->update("adminnotes", $update_cache);
	
		flash_message("The Admin Notes have been successfully updated.", 'success');
		admin_redirect("index.php?".SID);
	}
	
	$page->add_breadcrumb_item("Dashboard");
	$page->output_header("Dashboard", array("stylesheets" => array("home.css")));
	
	$sub_tabs['dashboard'] = array(
		'title' => "Dashboard",
		'link' => "index.php?".SID,
		'description' => "This section allows you to see some of the various statistics relating to your board. You may also add other notes for other administrators to see."
	);

	$page->output_nav_tabs($sub_tabs, 'dashboard');
	
	$serverload = get_server_load();
	if(!$serverload)
	{
		$serverload = $lang->unknown;
	}
	// Get the number of users
	$query = $db->simple_select("users", "COUNT(*) AS numusers");
	$users = $db->fetch_field($query, "numusers");

	// Get the number of users awaiting validation
	$query = $db->simple_select("users", "COUNT(*) AS awaitingusers", "usergroup='5'");
	$awaitingusers = $db->fetch_field($query, "awaitingusers");

	// Get the number of new users for today
	$timecut = time() - 86400;
	$query = $db->simple_select("users", "COUNT(*) AS newusers", "regdate > '$timecut'");
	$newusers = $db->fetch_field($query, "newusers");

	// Get the number of active users today
	$query = $db->simple_select("users", "COUNT(*) AS activeusers", "lastvisit > '$timecut'");
	$activeusers = $db->fetch_field($query, "activeusers");

	// Get the number of threads
	$query = $db->simple_select("threads", "COUNT(*) AS numthreads", "visible='1' AND closed NOT LIKE 'moved|%'");
	$threads = $db->fetch_field($query, "numthreads");

	// Get the number of unapproved threads
	$query = $db->simple_select("threads", "COUNT(*) AS numthreads", "visible='0' AND closed NOT LIKE 'moved|%'");
	$unapproved_threads = $db->fetch_field($query, "numthreads");

	// Get the number of new threads for today
	$query = $db->simple_select("threads", "COUNT(*) AS newthreads", "dateline > '$timecut' AND visible='1' AND closed NOT LIKE 'moved|%'");
	$newthreads = $db->fetch_field($query, "newthreads");

	// Get the number of posts
	$query = $db->simple_select("posts", "COUNT(*) AS numposts", "visible='1'");
	$posts = $db->fetch_field($query, "numposts");

	// Get the number of unapproved posts
	$query = $db->simple_select("posts", "COUNT(*) AS numposts", "visible='0'");
	$unapproved_posts = $db->fetch_field($query, "numposts");

	// Get the number of new posts for today
	$query = $db->simple_select("posts", "COUNT(*) AS newposts", "dateline > '$timecut' AND visible='1'");
	$newposts = $db->fetch_field($query, "newposts");

	// Get the number and total file size of attachments
	$query = $db->simple_select("attachments", "COUNT(*) AS numattachs, SUM(filesize) as spaceused", "visible='1' AND pid > '0'");
	$attachs = $db->fetch_array($query);
	$attachs['spaceused'] = get_friendly_size($attachs['spaceused']);

	// Get the number of unapproved attachments
	$query = $db->simple_select("attachments", "COUNT(*) AS numattachs", "visible='0' AND pid > '0'");
	$unapproved_attachs = $db->fetch_field($query, "numattachs");

	/*
	// Fetch the last time an update check was run
	$update_check = $cache->read("update_check");

	// If last update check was greater than two weeks ago (14 days) show an alert
	if($update_check['last_check'] <= time()-60*60*24*14)
	{
		$lang->last_update_check_two_weeks = sprintf($lang->last_update_check_two_weeks, "index.php?".SID."&amp;action=vercheck");
		makewarning($lang->last_update_check_two_weeks);
	}

	// If the update check contains information about a newer version, show an alert
	if($update_check['latest_version_code'] > $mybb->version_code)
	{
		$lang->new_version_available = sprintf($lang->new_version_available, "MyBB {$mybb->version}", "<a href=\"http://www.mybboard.com/?fwlink=release_{$update_check['latest_version_code']}\" target=\"_new\">MyBB {$update_check['latest_version']}</a>");
		makewarning($lang->new_version_available);
	}*/
	
	$adminmessage = $cache->read("adminnotes");

	$table = new Table;
	$table->construct_header("MyBB and Server Statistics");
	$table->construct_header("&nbsp;");
	$table->construct_header("Forum Statistics");
	$table->construct_header("&nbsp;");
	
	$table->construct_cell("<strong>MyBB Version</strong>", array('width' => '25%'));
	$table->construct_cell($mybb->version, array('width' => '25%'));
	$table->construct_cell("<strong>Threads</strong>", array('width' => '25%'));
	$table->construct_cell("<strong>{$threads['numthreads']}</strong> Threads<br /><strong>{$newthreads}</strong> New Today<br /><a href=\"\"><strong>{$unapproved_threads}</strong> Unapproved</a>", array('width' => '25%'));
	$table->construct_row("&nbsp;");
	
	$table->construct_cell("<strong>PHP Version</strong>", array('width' => '25%'));
	$table->construct_cell(phpversion(), array('width' => '25%'));
	$table->construct_cell("<strong>Posts</strong>", array('width' => '25%'));
	$table->construct_cell("<strong>{$posts['numposts']}</strong> Posts<br /><strong>{$newposts}</strong> New Today<br /><a href=\"\"><strong>{$unapproved_posts}</strong> Unapproved</a>", array('width' => '25%'));
	$table->construct_row("&nbsp;");
	
	$table->construct_cell("<strong>MySQL Engine</strong>", array('width' => '25%'));
	$table->construct_cell($db->title." ".$db->get_version(), array('width' => '25%'));
	$table->construct_cell("<strong>Users</strong>", array('width' => '25%'));
	$table->construct_cell("<a href=\"\"><strong>{$users}</strong> Registered Users</a><br /><strong>{$activeusers}</strong> Active Users<br /><strong>{$newusers}</strong> Registrations Today<br /><a href=\"\"><strong>{$awaitingusers}</strong> Awaiting Activation</a>", array('width' => '25%'));
	$table->construct_row("&nbsp;");
	
	$table->construct_cell("<strong>Server Load</strong>", array('width' => '25%'));
	$table->construct_cell($serverload, array('width' => '25%'));
	$table->construct_cell("<strong>Attachments</strong>", array('width' => '25%'));
	$table->construct_cell("<strong>{$attachs['numattachs']}</strong> Attachments<br /><a href=\"\"><strong>{$unapproved_attachs}</strong> Unapproved</a><br /><strong>{$attachs['spaceused']}</strong> Used", array('width' => '25%'));
	$table->construct_row("&nbsp;");
	
	$table->output("Dashboard");
	
	$table->construct_header("These admin notes are public to all administrators");
	
	$form = new Form("index.php?".SID, "post");
	$table->construct_cell($form->generate_text_area("adminnotes", $adminmessage['adminmessage'], array('style' => 'width: 99%; height: 200px;')));
	$table->construct_row();
	
	$table->output("Administrator Notes");	
	
	$buttons[] = $form->generate_submit_button("Save Notes");
	$form->output_submit_wrapper($buttons);
	
	$form->end();
	
	$page->output_footer();
}
?>