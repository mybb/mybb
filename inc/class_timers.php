<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: class_timers.php 4304 2009-01-02 01:11:56Z chris $
 */

class timer {
	
	/**
	 * The timer name.
	 *
	 * @var string
	 */
	var $name;
	
	/**
	 * The start time of this timer.
	 *
	 * @var int
	 */
	var $start;
	
	/**
	 * The end time of this timer.
	 *
	 * @var int
	 */
	var $end;
	
	/**
	 * The total time this timer has run.
	 *
	 * @var int
	 */
	var $totaltime;
	
	/**
	 * The formatted total time this timer has run.
	 *
	 * @var string
	 */
	var $formatted;

	/**
	 * Constructor of class.
	 *
	 */
	function timer()
	{
		$this->add();
	}
	
	/**
	 * Starts the timer.
	 *
	 */
	function add()
	{
		if(!$this->start) 
		{
			$mtime1 = explode(" ", microtime());
			$this->start = $mtime1[1] + $mtime1[0];
		}
	}

	/**
	 * Gets the time for which the timer has run up until this point.
	 *
	 * @return string|boolean The formatted time up until now or false when timer is no longer running.
	 */
	function getTime()
	{
		if($this->end) // timer has been stopped
		{
			return $this->totaltime;
		}
		elseif($this->start && !$this->end) // timer is still going
		{
			$mtime2 = explode(" ", microtime());
			$currenttime = $mtime2[1] + $mtime2[0];
			$totaltime = $currenttime - $this->start;
			return $this->format($totaltime);
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Stops the timer.
	 *
	 * @return string The formatted total time.
	 */
	function stop()
	{
		if($this->start)
		{
			$mtime2 = explode(" ", microtime());
			$this->end = $mtime2[1] + $mtime2[0];
			$totaltime = $this->end - $this->start;
			$this->totaltime = $totaltime;
			$this->formatted = $this->format($totaltime);
			return $this->formatted;
		}
	}
	
	/**
	 * Removes the timer.
	 *
	 */
	function remove()
	{
		$this->name = "";
		$this->start = "";
		$this->end = "";
		$this->totaltime = "";
		$this->formatted = "";
	}
	
	/**
	 * Formats the timer time in a pretty way.
	 *
	 * @param string The time string.
	 * @return The formatted time string.
	 */
	function format($string)
	{
		return number_format($string, 7);
	}
}
?>