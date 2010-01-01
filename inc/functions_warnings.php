<?php

function fetch_friendly_expiration($time)
{
	if($time == 0)
	{
		return array("period" => "never");
	}
	else if($time % 2592000 == 0)
	{
		return array("time" => $time/2592000, "period" => "months");
	}
	else if($time % 604800 == 0)
	{
		return array("time" => $time/604800, "period" => "weeks");
	}
	else if($time % 86400 == 0)
	{
		return array("time" => $time/86400, "period" => "days");
	}
	else
	{
		return array("time" => ceil($time/3600), "period" => "hours");
	}
}
?>