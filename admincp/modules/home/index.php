<?php
if(!$mybb->input['action'])
{
	$page->add_breadcrumb_item("Dashboard");
	$page->output_header("Dashboard", array("stylesheets" => array("home.css")));

	$page->output_footer();
}
?>