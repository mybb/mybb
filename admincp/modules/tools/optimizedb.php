<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->optimize_database, "index.php?".SID."&amp;module=tools/optimizedb");

if(!$mybb->input['action'])
{
	if($mybb->request_method == "post")
	{
		
		if(!is_array($mybb->input['tables']))
		{
			flash_message($lang->error_no_tables_selected, 'error');
			admin_redirect("index.php?".SID."&module=tools/optimizedb");
		}
		
		@set_time_limit(0);
		
		$db->set_table_prefix('');

		foreach($mybb->input['tables'] as $table)
		{			
			$db->optimize_table($table);
			$db->analyze_table($table);
		}
		
		$db->set_table_prefix(TABLE_PREFIX);
		
		// Log admin action
		log_admin_action(serialize($mybb->input['tables']));
		
		flash_message($lang->success_tables_optimized, 'success');
		admin_redirect("index.php?".SID."&module=tools/optimizedb");
	}
	
	$page->extra_header = "	<script type=\"text/javascript\" language=\"Javascript\">
	function changeSelection(action, prefix)
	{
		var select_box = document.getElementById('table_select');
		
		for(var i = 0; i < select_box.length; i++)
		{
			if(action == 'select')
			{
				document.table_selection.table_select[i].selected = true;
			}
			else if(action == 'deselect')
			{
				document.table_selection.table_select[i].selected = false;
			}
			else if(action == 'forum' && prefix != 0)
			{
				var row = document.table_selection.table_select[i].value;
				var subString = row.substring(prefix.length, 0);
				if(subString == prefix)
				{
					document.table_selection.table_select[i].selected = true;
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
	
	$form = new Form("index.php?".SID."&amp;module=tools/optimizedb", "post", 0, "table_selection", "table_selection");
	
	$table->construct_cell("{$lang->tables_select_desc}\n<br /><br />\n<a href=\"javascript:changeSelection('select', 0);\">{$lang->select_all}</a><br />\n<a href=\"javascript:changeSelection('deselect', 0);\">{$lang->deselect_all}</a><br />\n<a href=\"javascript:changeSelection('forum', '".TABLE_PREFIX."');\">{$lang->select_forum_tables}</a>\n<br /><br />\n<div class=\"form_row\">".$form->generate_select_box("tables[]", $table_selects, false, array('multiple' => true, 'id' => 'table_select', 'size' => 20))."</div>", array('rowspan' => 5, 'width' => '50%'));
	$table->construct_row();
		
	$table->output($lang->optimize_database);
	
	$buttons[] = $form->generate_submit_button($lang->optimize_selected_tables);
	$form->output_submit_wrapper($buttons);
	
	$form->end();
		
	$page->output_footer();
}

?>