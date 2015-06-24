<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

if($mybb->input['action'] == "do_graph")
{
	$range = array(
		'start' => $mybb->get_input('start', MyBB::INPUT_INT),
		'end' => $mybb->get_input('end', MyBB::INPUT_INT)
	);
	create_graph($mybb->input['type'], $range);
	die;
}

$page->add_breadcrumb_item($lang->statistics, "index.php?module=tools-statistics");

$sub_tabs['overall_statistics'] = array(
	'title' => $lang->overall_statistics,
	'link' => "index.php?module=tools-statistics",
	'description' => $lang->overall_statistics_desc
);

$plugins->run_hooks("admin_tools_statistics_begin");

if(!$mybb->input['action'])
{
	$query = $db->simple_select("stats", "COUNT(*) as total");
	if($db->fetch_field($query, "total") == 0)
	{
		flash_message($lang->error_no_statistics_available_yet, 'error');
		admin_redirect("index.php?module=tools");
	}

	$per_page = 20;

	$plugins->run_hooks("admin_tools_statistics_overall_begin");

	// Do we have date range criteria?
	if($mybb->input['from_year'])
	{
		$start_dateline = mktime(0, 0, 0, $mybb->get_input('from_month', MyBB::INPUT_INT), $mybb->get_input('from_day', MyBB::INPUT_INT), $mybb->get_input('from_year', MyBB::INPUT_INT));
		$end_dateline = mktime(23, 59, 59, $mybb->get_input('to_month', MyBB::INPUT_INT), $mybb->get_input('to_day', MyBB::INPUT_INT), $mybb->get_input('to_year', MyBB::INPUT_INT));
		$range = "&amp;start={$start_dateline}&amp;end={$end_dateline}";
	}

	// Otherwise default to the last 30 days
	if(!$mybb->input['from_year'] || $start_dateline > TIME_NOW || $end_dateline > mktime(23, 59, 59))
	{
		$start_dateline = TIME_NOW-(60*60*24*30);
		$end_dateline = TIME_NOW;

		list($mybb->input['from_day'], $mybb->input['from_month'], $mybb->input['from_year']) = explode('-', date('j-n-Y', $start_dateline));
		list($mybb->input['to_day'], $mybb->input['to_month'], $mybb->input['to_year']) = explode('-', date('j-n-Y', $end_dateline));

		$range = "&amp;start={$start_dateline}&amp;end={$end_dateline}";
	}

	$last_dateline = 0;

	if($mybb->input['page'] && $mybb->input['page'] > 1)
	{
		$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);
		$start = ($mybb->input['page']*$per_page)-$per_page;
	}
	else
	{
		$mybb->input['page'] = 1;
		$start = 0;
	}

	$query = $db->simple_select("stats", "*", "dateline >= '".(int)$start_dateline."' AND dateline <= '".(int)$end_dateline."'", array('order_by' => 'dateline', 'order_dir' => 'asc'));

	$stats = array();
	while($stat = $db->fetch_array($query))
	{
		if($last_dateline)
		{
			$stat['change_users'] = ($stat['numusers'] - $stats[$last_dateline]['numusers']);
			$stat['change_threads'] = ($stat['numthreads'] - $stats[$last_dateline]['numthreads']);
			$stat['change_posts'] = ($stat['numposts'] - $stats[$last_dateline]['numposts']);
		}

		$stats[$stat['dateline']] = $stat;

		$last_dateline = $stat['dateline'];
	}

	if(empty($stats))
	{
		flash_message($lang->error_no_results_found_for_criteria, 'error');
		admin_redirect("index.php?module=tools");
	}

	krsort($stats, SORT_NUMERIC);

	$page->add_breadcrumb_item($lang->overall_statistics, "index.php?module=tools-statistics");

	$page->output_header($lang->statistics." - ".$lang->overall_statistics);

	$page->output_nav_tabs($sub_tabs, 'overall_statistics');

	// Date range fields
	$form = new Form("index.php?module=tools-statistics", "post", "overall");
	echo "<fieldset><legend>{$lang->date_range}</legend>\n";
	echo "{$lang->from} ".$form->generate_date_select('from', $mybb->input['from_day'], $mybb->input['from_month'], $mybb->input['from_year']);
	echo " {$lang->to} ".$form->generate_date_select('to', $mybb->input['to_day'], $mybb->input['to_month'], $mybb->input['to_year']);
	echo " ".$form->generate_submit_button($lang->view);
	echo "</fieldset>\n";
	$form->end();

	echo "<fieldset><legend>{$lang->users}</legend>\n";
	echo "<img src=\"index.php?module=tools-statistics&amp;action=do_graph&amp;type=users{$range}\" />\n";
	echo "</fieldset>\n";

	echo "<fieldset><legend>{$lang->threads}</legend>\n";
	echo "<img src=\"index.php?module=tools-statistics&amp;action=do_graph&amp;type=threads{$range}\" />\n";
	echo "</fieldset>\n";

	echo "<fieldset><legend>{$lang->posts}</legend>\n";
	echo "<img src=\"index.php?module=tools-statistics&amp;action=do_graph&amp;type=posts{$range}\" />\n";
	echo "</fieldset>\n";

	$total_rows = count($stats);

	$table = new Table;
	$table->construct_header($lang->date);
	$table->construct_header($lang->users);
	$table->construct_header($lang->threads);
	$table->construct_header($lang->posts);
	$query = $db->simple_select("stats", "*", "dateline >= '".(int)$start_dateline."' AND dateline <= '".(int)$end_dateline."'", array('order_by' => 'dateline', 'order_dir' => 'desc', 'limit_start' => $start, 'limit' => $per_page));
	while($stat = $db->fetch_array($query))
	{
		$table->construct_cell("<strong>".date($mybb->settings['dateformat'], $stat['dateline'])."</strong>");
		$table->construct_cell(my_number_format($stat['numusers'])." <small>".generate_growth_string($stats[$stat['dateline']]['change_users'])."</small>");
		$table->construct_cell(my_number_format($stat['numthreads'])." <small>".generate_growth_string($stats[$stat['dateline']]['change_threads'])."</small>");
		$table->construct_cell(my_number_format($stat['numposts'])." <small>".generate_growth_string($stats[$stat['dateline']]['change_posts'])."</small>");
		$table->construct_row();
	}
	$table->output($lang->overall_statistics);

	$url_range = "&amp;from_month=".$mybb->get_input('from_month', MyBB::INPUT_INT)."&amp;from_day=".$mybb->get_input('from_day', MyBB::INPUT_INT)."&amp;from_year=".$mybb->get_input('from_year', MyBB::INPUT_INT);
	$url_range .= "&amp;to_month=".$mybb->get_input('to_month', MyBB::INPUT_INT)."&amp;to_day=".$mybb->get_input('to_day', MyBB::INPUT_INT)."&amp;to_year=".$mybb->get_input('to_year', MyBB::INPUT_INT);

	echo draw_admin_pagination($mybb->input['page'], $per_page, $total_rows, "index.php?module=tools-statistics{$url_range}&amp;page={page}");

	$page->output_footer();
}

/**
 * @param int $number
 *
 * @return string
 */
function generate_growth_string($number)
{
	global $lang, $cp_style;

	if($number === null)
	{
		return "";
	}

	$number = (int)$number;
	$friendly_number = my_number_format(abs($number));

	if($number > 0)
	{
		$growth_string = "(<img src=\"./styles/{$cp_style}/images/icons/increase.png\" alt=\"{$lang->increase}\" title=\"{$lang->increase}\" style=\"vertical-align: middle; margin-top: -2px;\" /> {$friendly_number})";
	}
	elseif($number == 0)
	{
		$growth_string = "(<img src=\"./styles/{$cp_style}/images/icons/no_change.png\" alt=\"{$lang->no_change}\" title=\"{$lang->no_change}\" style=\"vertical-align: middle; margin-top: -2px;\" /> {$friendly_number})";
	}
	else
	{
		$growth_string = "(<img src=\"./styles/{$cp_style}/images/icons/decrease.png\" alt=\"{$lang->decrease}\" title=\"{$lang->decrease}\" style=\"vertical-align: middle; margin-top: -2px;\" /> {$friendly_number})";
	}

	return $growth_string;
}

/**
 * @param string $type users, threads, posts
 * @param array $range
 */
function create_graph($type, $range=null)
{
	global $db;

	// Do we have date range criteria?
	if($range['end'] || $range['start'])
	{
		$start = (int)$range['start'];
		$end = (int)$range['end'];
	}
	// Otherwise default to the last 30 days
	else
	{
		$start = TIME_NOW-(60*60*24*30);
		$end = TIME_NOW;
	}

	$allowed_types = array('users', 'threads', 'posts');
	if(!in_array($type, $allowed_types))
	{
		die;
	}

	require_once MYBB_ROOT.'inc/class_graph.php';

	$points = $stats = $datelines = array();
	if($start == 0)
	{
		$query = $db->simple_select("stats", "dateline,num{$type}", "dateline <= '".(int)$end."'", array('order_by' => 'dateline', 'order_dir' => 'desc', 'limit' => 2));
		while($stat = $db->fetch_array($query))
		{
			$stats[] = $stat['num'.$type];
			$datelines[] = $stat['dateline'];
			$x_labels[] = date("m/j", $stat['dateline']);
		}
		$points[$datelines[0]] = 0;
		$points[$datelines[1]] = $stats[0]-$stats[1];
		ksort($points, SORT_NUMERIC);
	}
	elseif($end == 0)
	{
		$query = $db->simple_select("stats", "dateline,num{$type}", "dateline >= '".(int)$start."'", array('order_by' => 'dateline', 'order_dir' => 'asc', 'limit' => 2));
		while($stat = $db->fetch_array($query))
		{
			$stats[] = $stat['num'.$type];
			$datelines[] = $stat['dateline'];
			$x_labels[] = date("m/j", $stat['dateline']);
		}
		$points[$datelines[0]] = 0;
		$points[$datelines[1]] = $stats[1]-$stats[0];
		ksort($points, SORT_NUMERIC);
	}
	else
	{
		$query = $db->simple_select("stats", "dateline,num{$type}", "dateline >= '".(int)$start."' AND dateline <= '".(int)$end."'", array('order_by' => 'dateline', 'order_dir' => 'asc'));
		while($stat = $db->fetch_array($query))
		{
			$points[$stat['dateline']] = $stat['num'.$type];
			$datelines[] = $stat['dateline'];
			$x_labels[] = date("m/j", $stat['dateline']);
		}
	}

	sort($datelines, SORT_NUMERIC);

	// Find our year(s) label
	$start_year = date('Y', $datelines[0]);
	$last_year = date('Y', $datelines[count($datelines)-1]);
	if(($last_year - $start_year) == 0)
	{
		$bottom_label = $start_year;
	}
	else
	{
		$bottom_label = $start_year." - ".$last_year;
	}

	// Create the graph outline
	$graph = new Graph();
	$graph->add_points(array_values($points));
	$graph->add_x_labels($x_labels);
	$graph->set_bottom_label($bottom_label);
	$graph->render();
	$graph->output();
}
