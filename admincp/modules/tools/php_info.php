<?php

if($mybb->input['action'] == 'phpinfo')
{
	phpinfo();
	exit;
}

$page->add_breadcrumb_item("PHP Info", "index.php?".SID."&module=tools/php_info");

if(!$mybb->input['action'])
{
	$page->output_header("PHP Info");
	
	echo "<iframe src=\"index.php?".SID."&amp;module=tools/php_info&amp;action=phpinfo\" width=\"100%\" height=\"500\" frameborder=\"0\">Your browser does not support iframes</iframe>";
	
	$page->output_footer();
}

?>