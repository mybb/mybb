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

class timer {
	var $name;
	var $start;
	var $end;
	var $totaltime;
	var $formatted;

	
	function timer()
	{
		$this->add();
	}
	
	function add()
	{
		if(!$this->start) {
			$mtime1 = explode(" ", microtime());
			$this->start = $mtime1[1] + $mtime1[0];
		}
	}

	function gettime()
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
	function remove()
	{
		$this->name = "";
		$this->start = "";
		$this->end = "";
		$this->totaltime = "";
		$this->formatted = "";
	}
	function format($string)
	{
		return number_format($string, 7);
	}
}
?>