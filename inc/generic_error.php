<?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
	<head>
		<title>MyBB Error</title>
	</head>
	<body>
	<pre>
<h1>MyBB Error</h1>
<strong>MyBB has generated a critical error and as a result cannot function correctly.</strong><br />
	MyBB Said:<br /><br />
		Error Code: <?php echo $code; ?><br />
		<?php echo $message; ?><br /><br />
	Please try clicking the <a href="javascript:window.location=window.location;">Refresh</a> button in your web browser to see if this corrects this problem.<br />
We apologise for any inconvenience.<br />
<hr />
<address>MyBB <?php echo $this->version; ?></address>
	</pre>
	</body>
</html>