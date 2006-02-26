<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/*
EXAMPLE USE:

$thread = get from POST data
$forum = get from DB using POST data id

$threadHandler = new threadDataHandler();
if($threadHandler->validate_thread($thread))
{
	$threadHandler->insert_thread($thread);
}

*/

/**
 * Thread handling class, provides common structure to handle thread data.
 *
 */
class ThreadDataHandler extends Handler
{
	/**
	 * Validate a thread.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_thread(&$thread)
	{
		global $db, $mybb, $plugins;
		
		// ALL THE VALIDATION STUFF GOES HERE
		
		$plugins->run_hooks("datahandler_post_validate");
		
		// We are done validating, return.
		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}
	
	/**
	 * Insert a thread into the database.
	 *
	 * @param array The thread data array.
	 * @return array Array of new thread details, tid and visibility.
	 */
	function insert_thread($thread)
	{
		global $db, $mybb, $plugins;
		
		// Yes, validating is required.
		if(!$this->get_validated)
		{
			die("The thread needs to be validated before inserting it into the DB.");
		}
		if(!empty($this->get_errors()))
		{
			die("The thread is not valid.");
		}

		// THE THREAD INSERT CODE GOES HERE
		
		$plugins->run_hooks("datahandler_thread_insert");
		
		// Return the thread's tid and whether or not it is visible.
		return array(
			"tid" => $tid,
			"visible" => $visible
		);
	}
	
	/**
	 * Update a thread that is already in the database.
	 *
	 * @param array The thread data array.
	 */
	function update_thread($thread)
	{
		global $db, $mybb, $plugins;
		
		// Yes, validating is required.
		if(!$this->get_validated)
		{
			die("The thread needs to be validated before inserting it into the DB.");
		}
		if(!empty($this->get_errors()))
		{
			die("The thread is not valid.");
		}
		
		// THE THREAD UPDATE CODE GOES HERE	
		
		$plugins->run_hooks("datahandler_thread_update");
	}
	
	/**
	 * Delete a thread from the database.
	 *
	 * @param int The thread id of the thread that is to be deleted.
	 * @param int The forum id of the forum the post is in.
	 */
	function delete_by_tid($tid, $fid)
	{
		global $db;
		
		// THE THREAD DELETE CODE GOES HERE
		
		$plugins->run_hooks("datahandler_thread_delete");
	}
}

?>