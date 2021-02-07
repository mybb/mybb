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

$page->add_breadcrumb_item($lang->optimize_database, "index.php?module=tools-optimizedb");

$plugins->run_hooks("admin_tools_optimizedb_begin");

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_tools_optimizedb_start");

	if($mybb->request_method == "post")
	{
		if(empty($mybb->input['tables']) || !is_array($mybb->input['tables']))
		{
			flash_message($lang->error_no_tables_selected, 'error');
			admin_redirect("index.php?module=tools-optimizedb");
		}

		@set_time_limit(0);

		$db->set_table_prefix('');

		foreach($mybb->input['tables'] as $table)
		{
			if($db->table_exists($db->escape_string($table)))
			{
				$db->optimize_table($table);
				$db->analyze_table($table);
			}
		}

		$db->set_table_prefix(TABLE_PREFIX);

		$plugins->run_hooks("admin_tools_optimizedb_start_begin");

		// Log admin action
		log_admin_action(my_serialize($mybb->input['tables']));

		flash_message($lang->success_tables_optimized, 'success');
		admin_redirect("index.php?module=tools-optimizedb");
	}

	$page->extra_header = "	<script type=\"text/javascript\">
	function changeSelection(action, prefix)
	{
		var select_box = document.getElementById('table_select');

		for(var i = 0; i < select_box.length; i++)
		{
			if(action == 'select')
			{
				select_box[i].selected = true;
			}
			else if(action == 'deselect')
			{
				select_box[i].selected = false;
			}
			else if(action == 'forum' && prefix != 0)
			{
				select_box[i].selected = false;
				var row = select_box[i].value;
				var subString = row.substring(prefix.length, 0);
				if(subString == prefix)
				{
					select_box[i].selected = true;
				}
			}
		}
	}
	</script>\n";

	$page->output_header($lang->optimize_database);

	$table = new Table;
	$table->construct_header($lang->table_selection);

	$table_selects = array();
	$table_list = $db->list_tables($config['database']['database']);
	foreach($table_list as $id => $table_name)
	{
		$table_selects[$table_name] = $table_name;
	}

	$form = new Form("index.php?module=tools-optimizedb", "post", "table_selection", 0, "table_selection");

	$table->construct_cell("{$lang->tables_select_desc}\n<br /><br />\n<a href=\"javascript:changeSelection('select', 0);\">{$lang->select_all}</a><br />\n<a href=\"javascript:changeSelection('deselect', 0);\">{$lang->deselect_all}</a><br />\n<a href=\"javascript:changeSelection('forum', '".TABLE_PREFIX."');\">{$lang->select_forum_tables}</a>\n<br /><br />\n<div class=\"form_row\">".$form->generate_select_box("tables[]", $table_selects, false, array('multiple' => true, 'id' => 'table_select', 'size' => 20))."</div>", array('rowspan' => 5, 'width' => '50%'));
	$table->construct_row();

	$table->output($lang->optimize_database);

	$buttons[] = $form->generate_submit_button($lang->optimize_selected_tables);
	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

