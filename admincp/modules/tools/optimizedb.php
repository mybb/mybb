<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item("Optimize Database", "index.php?".SID."&amp;module=tools/optimizedb");

if(!$mybb->input['action'])
{
	if($mybb->request_method == "post")
	{
		
		if(!is_array($mybb->input['tables']))
		{
			flash_message("You did not select any database tables to optimize.", 'error');
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
		
		flash_message("The selected tables have been optimized and analyzed successfully.", 'success');
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
	
	$page->output_header("Optimize Database");

	$table = new Table;
	$table->construct_header("Table Selection");
	
	$table_selects = array();
	$table_list = $db->list_tables($config['database']);
	foreach($table_list as $id => $table_name)
	{
		$table_selects[$table_name] = $table_name;
	}
	
	$form = new Form("index.php?".SID."&amp;module=tools/optimizedb", "post", 0, "table_selection", "table_selection");
	
	$table->construct_cell("You may select the database tables you wish to perform this action on here. Hold down CTRL to select multiple tables.\n<br /><br />\n<a href=\"javascript:changeSelection('select', 0);\">Select All</a><br />\n<a href=\"javascript:changeSelection('deselect', 0);\">Deselect All</a><br />\n<a href=\"javascript:changeSelection('forum', '".TABLE_PREFIX."');\">Select Forum Tables</a>\n<br /><br />\n<div class=\"form_row\">".$form->generate_select_box("tables[]", $table_selects, false, array('multiple' => true, 'id' => 'table_select', 'size' => 20))."</div>", array('rowspan' => 5, 'width' => '50%'));
	$table->construct_row();
		
	$table->output("Optimize Database");
	
	$buttons[] = $form->generate_submit_button("Optimize Selected Tables");
	$form->output_submit_wrapper($buttons);
	
	$form->end();
		
	$page->output_footer();
}

?>