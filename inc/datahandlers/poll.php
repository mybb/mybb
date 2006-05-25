<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id:$
 */

/**
 * Poll handling class, provides common structure to handle poll data.
 *
 */
class PollDataHandler extends DataHandler
{
	/**
	* The language file used in the data handler.
	*
	* @var string
	*/
	var $language_file = 'datahandler_poll';

	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	var $language_prefix = 'polldata';

	function verify_thread()
	{
		$poll = &$this->data;
		$tid = $poll['tid'];

		$options = array(
			'limit' => 1
		);
		$query = $db->simple_select(TABLE_PREFIX.'threads', 'dateline, poll', "tid={$tid}", $options);
		$thread = $db->fetch_array($query);

		// Does the thread exist?
		if(!isset($thread['dateline']))
		{
			$this->set_error('invalid_thread');
			return false;
		}

		// Does the thread already have a poll?
		if(isset($thread['poll']))
		{
			$this->set_error('thread_already_poll');
			return false;
		}

		// Suitable thread.
		return true;
	}

	/**
	* Validate a poll.
	*
	*/
	function validate_poll()
	{
		$this->verify_thread();
	}

	/**
	* Insert a poll into the database.
	*
	*/
	function insert_poll()
	{

	}

	/**
	* Update an existing poll.
	*
	*/
	function update_poll()
	{

	}
}
?>