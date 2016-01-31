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

$page->add_breadcrumb_item($lang->system_health, "index.php?module=tools-system_health");

$sub_tabs['system_health'] = array(
	'title' => $lang->system_health,
	'link' => "index.php?module=tools-system_health",
	'description' => $lang->system_health_desc
);

$sub_tabs['utf8_conversion'] = array(
	'title' => $lang->utf8_conversion,
	'link' => "index.php?module=tools-system_health&amp;action=utf8_conversion",
	'description' => $lang->utf8_conversion_desc2
);

$sub_tabs['template_check'] = array(
	'title' => $lang->check_templates,
	'link' => "index.php?module=tools-system_health&amp;action=check_templates",
	'description' => $lang->check_templates_desc
);

$plugins->run_hooks("admin_tools_system_health_begin");

if($mybb->input['action'] == "do_check_templates" && $mybb->request_method == "post")
{
	$query = $db->simple_select("templates", "*", "", array("order_by" => "sid, title", "order_dir" => "ASC"));

	if(!$db->num_rows($query))
	{
		flash_message($lang->error_invalid_input, 'error');
		admin_redirect("index.php?module=tools-system_health");
	}

	$plugins->run_hooks("admin_tools_system_health_template_do_check_start");

	$t_cache = array();
	while($template = $db->fetch_array($query))
	{
		if(check_template($template['template']) == true)
		{
			$t_cache[$template['sid']][] = $template;
		}
	}

	if(empty($t_cache))
	{
		flash_message($lang->success_templates_checked, 'success');
		admin_redirect("index.php?module=tools-system_health");
	}

	$plugins->run_hooks("admin_tools_system_health_template_do_check");

	$page->add_breadcrumb_item($lang->check_templates);
	$page->output_header($lang->check_templates);

	$page->output_nav_tabs($sub_tabs, 'template_check');
	$page->output_inline_error(array($lang->check_templates_info_desc));

	$templatesets = array(
		-2 => array(
			"title" => "MyBB Master Templates"
		)
	);
	$query = $db->simple_select("templatesets", "*");
	while($set = $db->fetch_array($query))
	{
		$templatesets[$set['sid']] = $set;
	}

	$count = 0;
	foreach($t_cache as $sid => $templates)
	{
		if(!$done_set[$sid])
		{
			$table = new Table();
			$table->construct_header($templatesets[$sid]['title'], array("colspan" => 2));

			$done_set[$sid] = 1;
			++$count;
		}

		if($sid == -2)
		{
			// Some cheeky clown has altered the master templates!
			$table->construct_cell($lang->error_master_templates_altered, array("colspan" => 2));
			$table->construct_row();
		}

		foreach($templates as $template)
		{
			if($sid == -2)
			{
				$table->construct_cell($template['title'], array('colspan' => 2));
			}
			else
			{
				$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
				$popup->add_item($lang->full_edit, "index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid={$sid}");

				$table->construct_cell("<a href=\"index.php?module=style-templates&amp;action=edit_template&amp;title=".urlencode($template['title'])."&amp;sid={$sid}&amp;from=diff_report\">{$template['title']}</a>", array('width' => '80%'));
				$table->construct_cell($popup->fetch(), array("class" => "align_center"));
			}

			$table->construct_row();
		}

		if($done_set[$sid] && !$done_output[$sid])
		{
			$done_output[$sid] = 1;
			if($count == 1)
			{
				$table->output($lang->check_templates);
			}
			else
			{
				$table->output();
			}
		}
	}

	$page->output_footer();
}

if($mybb->input['action'] == "check_templates")
{
	$page->add_breadcrumb_item($lang->check_templates);
	$page->output_header($lang->check_templates);

	$plugins->run_hooks("admin_tools_system_health_template_check");

	$page->output_nav_tabs($sub_tabs, 'template_check');

	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=tools-system_health", "post", "check_set");
	echo $form->generate_hidden_field("action", "do_check_templates");

	$form_container = new FormContainer($lang->check_templates);
	$form_container->output_row($lang->check_templates_title, "", $lang->check_templates_info);
	$form_container->end();

	$buttons = array();
	$buttons[] = $form->generate_submit_button($lang->proceed);

	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "utf8_conversion")
{
	if($db->type == "sqlite" || $db->type == "pgsql")
	{
		flash_message($lang->error_not_supported, 'error');
		admin_redirect("index.php?module=tools-system_health");
	}

	$plugins->run_hooks("admin_tools_system_health_utf8_conversion");

	if($mybb->request_method == "post" || ($mybb->input['do'] == "all" && !empty($mybb->input['table'])))
	{
		if(!empty($mybb->input['mb4']) && version_compare($db->get_version(), '5.5.3', '<'))
		{
			flash_message($lang->error_utf8mb4_version, 'error');
			admin_redirect("index.php?module=tools-system_health&action=utf8_conversion");
		}
		@set_time_limit(0);

		$old_table_prefix = $db->table_prefix;
		$db->set_table_prefix('');

		if(!$db->table_exists($db->escape_string($mybb->input['table'])))
		{
			$db->set_table_prefix($old_table_prefix);
			flash_message($lang->error_invalid_table, 'error');
			admin_redirect("index.php?module=tools-system_health&action=utf8_conversion");
		}

		$db->set_table_prefix($old_table_prefix);

		$page->add_breadcrumb_item($lang->utf8_conversion, "index.php?module=tools-system_health&amp;action=utf8_conversion");

		$page->output_header($lang->system_health." - ".$lang->utf8_conversion);

		$sub_tabs['system_health'] = array(
			'title' => $lang->system_health,
			'link' => "index.php?module=tools-system_health",
			'description' => $lang->system_health_desc
		);

		$sub_tabs['utf8_conversion'] = array(
			'title' => $lang->utf8_conversion,
			'link' => "index.php?module=tools-system_health&amp;action=utf8_conversion",
			'description' => $lang->utf8_conversion_desc2
		);

		$page->output_nav_tabs($sub_tabs, 'utf8_conversion');

		$old_table_prefix = $db->table_prefix;
		$db->set_table_prefix('');

		$table = new Table;

		$table1 = $db->show_create_table($db->escape_string($mybb->input['table']));
        		preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table1, $matches);
		$charset = $matches[1];

		if(!empty($mybb->input['mb4']))
		{
			$table->construct_cell("<strong>".$lang->sprintf($lang->converting_to_utf8mb4, $mybb->input['table'], $charset)."</strong>");
		}
		else
		{
			$table->construct_cell("<strong>".$lang->sprintf($lang->converting_to_utf8, $mybb->input['table'], $charset)."</strong>");
		}
		$table->construct_row();

		$table->construct_cell($lang->please_wait);
		$table->construct_row();

		$table->output($converting_table." {$mybb->input['table']}");

		$db->set_table_prefix($old_table_prefix);

		$page->output_footer(false);

		$old_table_prefix = $db->table_prefix;
		$db->set_table_prefix('');

		flush();

		$types = array(
			'text' => 'blob',
			'mediumtext' => 'mediumblob',
			'longtext' => 'longblob',
			'char' => 'varbinary',
			'varchar' => 'varbinary',
			'tinytext' => 'tinyblob'
		);

		$blob_types = array( 'blob', 'tinyblob', 'mediumblog', 'longblob', 'text', 'tinytext', 'mediumtext', 'longtext' );

		// Get next table in list
		$convert_to_binary = '';
		$convert_to_utf8 = '';
		$comma = '';

		if(!empty($mybb->input['mb4']))
		{
			$character_set = 'utf8mb4';
			$collation = 'utf8mb4_general_ci';
		}
		else
		{
			$character_set = 'utf8';
			$collation = 'utf8_general_ci';
		}

		// Set table default charset
		$db->write_query("ALTER TABLE {$mybb->input['table']} DEFAULT CHARACTER SET {$character_set} COLLATE {$collation}");

		// Fetch any fulltext keys
		if($db->supports_fulltext($mybb->input['table']))
		{
			$table_structure = $db->show_create_table($mybb->input['table']);
			switch($db->type)
			{
				case "mysql":
				case "mysqli":
					preg_match_all("#FULLTEXT KEY `?([a-zA-Z0-9_]+)`? \(([a-zA-Z0-9_`,']+)\)#i", $table_structure, $matches);
					if(is_array($matches))
					{
						foreach($matches[0] as $key => $matched)
						{
							$db->write_query("ALTER TABLE {$mybb->input['table']} DROP INDEX {$matches[1][$key]}");
							$fulltext_to_create[$matches[1][$key]] = $matches[2][$key];
						}
					}
			}
		}

		// Find out which columns need converting and build SQL statements
		$query = $db->query("SHOW FULL COLUMNS FROM {$mybb->input['table']}");
		while($column = $db->fetch_array($query))
		{
			list($type) = explode('(', $column['Type']);
			if(array_key_exists($type, $types))
			{
				// Build the actual strings for converting the columns
				$names = "CHANGE `{$column['Field']}` `{$column['Field']}` ";

				if(($db->type == 'mysql' || $db->type == 'mysqli') && in_array($type, $blob_types))
				{
					if($column['Null'] == 'YES')
					{
						$attributes = 'NULL';
					}
					else
					{
						$attributes = 'NOT NULL';
					}
				}
				else
				{
					$attributes = " DEFAULT ";
					if($column['Default'] == 'NULL')
					{
						$attributes .= "NULL ";
					}
					else
					{
						$attributes .= "'".$db->escape_string($column['Default'])."' ";

						if($column['Null'] == 'YES')
						{
							$attributes .= 'NULL';
						}
						else
						{
							$attributes .= 'NOT NULL';
						}
					}
				}

				$convert_to_binary .= $comma.$names.preg_replace('/'.$type.'/i', $types[$type], $column['Type']).' '.$attributes;
				$convert_to_utf8 .= "{$comma}{$names}{$column['Type']} CHARACTER SET {$character_set} COLLATE {$collation} {$attributes}";

				$comma = $lang->comma;
			}
		}

		if(!empty($convert_to_binary))
		{
			// This converts the columns to UTF-8 while also doing the same for data
			$db->write_query("ALTER TABLE {$mybb->input['table']} {$convert_to_binary}");
			$db->write_query("ALTER TABLE {$mybb->input['table']} {$convert_to_utf8}");
		}

		// Any fulltext indexes to recreate?
		if(is_array($fulltext_to_create))
		{
			foreach($fulltext_to_create as $name => $fields)
			{
				$db->create_fulltext_index($mybb->input['table'], $fields, $name);
			}
		}

		$db->set_table_prefix($old_table_prefix);

		$plugins->run_hooks("admin_tools_system_health_utf8_conversion_commit");

		// Log admin action
		log_admin_action($mybb->input['table']);

		flash_message($lang->sprintf($lang->success_table_converted, $mybb->input['table']), 'success');

		if($mybb->input['do'] == "all")
		{
			$old_table_prefix = $db->table_prefix;
			$db->set_table_prefix('');

			$tables = $db->list_tables($mybb->config['database']['database']);
			foreach($tables as $key => $tablename)
			{
				if(substr($tablename, 0, strlen(TABLE_PREFIX)) == TABLE_PREFIX)
				{
					$table = $db->show_create_table($tablename);
					preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table, $matches);
					if(empty($mybb->input['mb4']) && (fetch_iconv_encoding($matches[1]) == 'utf-8' || $matches[1] == 'utf8mb4') && $mybb->input['table'] != $tablename)
					{
						continue;
					}
					elseif(!empty($mybb->input['mb4']) && fetch_iconv_encoding($matches[1]) != 'utf-8' && $mybb->input['table'] != $tablename)
					{
						continue;
					}

					$mybb_tables[$key] = $tablename;
				}
			}

			asort($mybb_tables);
			reset($mybb_tables);

			$is_next = false;
			$nexttable = "";

			foreach($mybb_tables as $key => $tablename)
			{
				if($is_next == true)
				{
					$nexttable = $tablename;
					break;
				}
				else if($mybb->input['table'] == $tablename)
				{
					$is_next = true;
				}
			}

			$db->set_table_prefix($old_table_prefix);

			if($nexttable)
			{
				$nexttable = $db->escape_string($nexttable);
				$mb4 = '';
				if(!empty($mybb->input['mb4']))
				{
					$mb4 = "&amp;mb4=1";
				}
				admin_redirect("index.php?module=tools-system_health&action=utf8_conversion&do=all&table={$nexttable}{$mb4}");
				exit;
			}
		}

		admin_redirect("index.php?module=tools-system_health&action=utf8_conversion");

		exit;
	}

	if($mybb->input['table'] || $mybb->input['do'] == "all")
	{
		if(!empty($mybb->input['mb4']) && version_compare($db->get_version(), '5.5.3', '<'))
		{
			flash_message($lang->error_utf8mb4_version, 'error');
			admin_redirect("index.php?module=tools-system_health&action=utf8_conversion");
		}

		$old_table_prefix = $db->table_prefix;
		$db->set_table_prefix('');

		if($mybb->input['do'] != "all" && !$db->table_exists($db->escape_string($mybb->input['table'])))
		{
			$db->set_table_prefix($old_table_prefix);
			flash_message($lang->error_invalid_table, 'error');
			admin_redirect("index.php?module=tools-system_health&action=utf8_conversion");
		}

		if($mybb->input['do'] == "all")
		{
			$tables = $db->list_tables($mybb->config['database']['database']);
			foreach($tables as $key => $tablename)
			{
				if(substr($tablename, 0, strlen(TABLE_PREFIX)) == TABLE_PREFIX)
				{
					$table = $db->show_create_table($tablename);
					preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table, $matches);
					if(empty($mybb->input['mb4']) && (fetch_iconv_encoding($matches[1]) == 'utf-8' || $matches[1] == 'utf8mb4'))
					{
						continue;
					}
					elseif(!empty($mybb->input['mb4']) && fetch_iconv_encoding($matches[1]) != 'utf-8')
					{
						continue;
					}
					$mybb_tables[$key] = $tablename;
				}
			}

			if(is_array($mybb_tables))
			{
				asort($mybb_tables);
				reset($mybb_tables);
				$nexttable = current($mybb_tables);
				$table = $db->show_create_table($db->escape_string($nexttable));
				$mybb->input['table'] = $nexttable;
			}
			else
			{
				$db->set_table_prefix($old_table_prefix);
				flash_message($lang->success_all_tables_already_converted, 'success');
				admin_redirect("index.php?module=tools-system_health");
			}
		}
		else
		{
			$table = $db->show_create_table($db->escape_string($mybb->input['table']));
		}

		$page->add_breadcrumb_item($lang->utf8_conversion, "index.php?module=tools-system_health&amp;action=utf8_conversion");

		$db->set_table_prefix($old_table_prefix);

		$page->output_header($lang->system_health." - ".$lang->utf8_conversion);

		$sub_tabs['system_health'] = array(
			'title' => $lang->system_health,
			'link' => "index.php?module=tools-system_health",
			'description' => $lang->system_health_desc
		);

		$sub_tabs['utf8_conversion'] = array(
			'title' => $lang->utf8_conversion,
			'link' => "index.php?module=tools-system_health&amp;action=utf8_conversion",
			'description' => $lang->utf8_conversion_desc2
		);

		$page->output_nav_tabs($sub_tabs, 'utf8_conversion');

		$old_table_prefix = $db->table_prefix;
		$db->set_table_prefix('');

        		preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table, $matches);
		$charset = $matches[1];

		$mb4 = '';
		if(!empty($mybb->input['mb4']))
		{
			$mb4 = "&amp;mb4=1";
		}

		$form = new Form("index.php?module=tools-system_health&amp;action=utf8_conversion{$mb4}", "post", "utf8_conversion");
		echo $form->generate_hidden_field("table", $mybb->input['table']);

		if($mybb->input['do'] == "all")
		{
			echo $form->generate_hidden_field("do", "all");
		}

		$table = new Table;

		if(!empty($mybb->input['mb4']))
		{
			$table->construct_cell("<strong>".$lang->sprintf($lang->convert_all_to_utf8mb4, $charset)."</strong>");
			$lang->notice_process_long_time .= "<br /><br /><strong>{$lang->notice_mb4_warning}</strong>";
		}
		else
		{
			if($mybb->input['do'] == "all")
			{
				$table->construct_cell("<strong>".$lang->sprintf($lang->convert_all_to_utf, $charset)."</strong>");
			}
			else
			{
				$table->construct_cell("<strong>".$lang->sprintf($lang->convert_to_utf8, $mybb->input['table'], $charset)."</strong>");
			}
		}

		$table->construct_row();

		$table->construct_cell($lang->notice_process_long_time);
		$table->construct_row();

		if($mybb->input['do'] == "all")
		{
			$table->output($lang->convert_tables);
			$buttons[] = $form->generate_submit_button($lang->convert_database_tables);
		}
		else
		{
			$table->output($lang->convert_table.": {$mybb->input['table']}");
			$buttons[] = $form->generate_submit_button($lang->convert_database_table);
		}

		$form->output_submit_wrapper($buttons);

		$form->end();

		$db->set_table_prefix($old_table_prefix);

		$page->output_footer();

		exit;
	}

	if(!$mybb->config['database']['encoding'])
	{
		flash_message($lang->error_db_encoding_not_set, 'error');
		admin_redirect("index.php?module=tools-system_health");
	}

	$tables = $db->list_tables($mybb->config['database']['database']);

	$old_table_prefix = $db->table_prefix;
	$db->set_table_prefix('');

	$encodings = array();

	foreach($tables as $key => $tablename)
	{
		if(substr($tablename, 0, strlen($old_table_prefix)) == $old_table_prefix)
		{
			$table = $db->show_create_table($tablename);
        	preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table, $matches);
			$encodings[$key] = fetch_iconv_encoding($matches[1]);
			$mybb_tables[$key] = $tablename;
		}
	}

	$db->set_table_prefix($old_table_prefix);

	$page->add_breadcrumb_item($lang->utf8_conversion, "index.php?module=tools-system_health&amp;action=utf8_conversion");

	$page->output_header($lang->system_health." - ".$lang->utf8_conversion);

	$page->output_nav_tabs($sub_tabs, 'utf8_conversion');

	asort($mybb_tables);

	$unique = array_unique($encodings);

	$convert_utf8 = $convert_utf8mb4 = false;
	foreach($unique as $encoding)
	{
		if($encoding == 'utf-8')
		{
			$convert_utf8mb4 = true;
		}
		elseif($encoding != 'utf8mb4')
		{
			$convert_utf8 = true;
		}
	}

	if(count($unique) > 1)
	{
		$page->output_error("<p><em>{$lang->warning_multiple_encodings}</em></p>");
	}

	if(in_array('utf8mb4', $unique) && $mybb->config['database']['encoding'] != 'utf8mb4')
	{
		$page->output_error("<p><em>{$lang->warning_utf8mb4_config}</em></p>");
	}

	$table = new Table;
	$table->construct_header($lang->table);
	$table->construct_header($lang->status_utf8, array("class" => "align_center"));
	$table->construct_header($lang->status_utf8mb4, array("class" => "align_center"));

	$all_utf8 = $all_utf8mb4 = '-';
	if($convert_utf8)
	{
		$all_utf8 = "<strong><a href=\"index.php?module=tools-system_health&amp;action=utf8_conversion&amp;do=all\">{$lang->convert_all}</a></strong>";
	}
	if($convert_utf8mb4)
	{
		$all_utf8mb4 = "<strong><a href=\"index.php?module=tools-system_health&amp;action=utf8_conversion&amp;do=all&amp;mb4=1\">{$lang->convert_all}</a></strong>";
	}
	$table->construct_cell("<strong>{$lang->all_tables}</strong>");
	$table->construct_cell($all_utf8, array("class" => "align_center", 'width' => '15%'));
	$table->construct_cell($all_utf8mb4, array("class" => "align_center", 'width' => '25%'));
	$table->construct_row();

	$db_version = $db->get_version();

	foreach($mybb_tables as $key => $tablename)
	{
		if($encodings[$key] != 'utf-8' && $encodings[$key] != 'utf8mb4')
		{
			$status = "<a href=\"index.php?module=tools-system_health&amp;action=utf8_conversion&amp;table={$tablename}\" style=\"background: url(styles/{$page->style}/images/icons/cross.png) no-repeat; padding-left: 20px;\">{$lang->convert_now}</a>";
		}
		else
		{
			$status = "<img src=\"styles/{$page->style}/images/icons/tick.png\" alt=\"{$lang->ok}\" />";
		}
		if(version_compare($db_version, '5.5.3', '<'))
		{
			$utf8mb4 = $lang->not_available;
		}
		elseif($encodings[$key] == 'utf8mb4')
		{
			$utf8mb4 = "<img src=\"styles/{$page->style}/images/icons/tick.png\" alt=\"{$lang->ok}\" />";
		}
		elseif($encodings[$key] == 'utf-8')
		{
			$utf8mb4 = "<a href=\"index.php?module=tools-system_health&amp;action=utf8_conversion&amp;table={$tablename}&amp;mb4=1\" style=\"background: url(styles/{$page->style}/images/icons/cross.png) no-repeat; padding-left: 20px;\">{$lang->convert_now}</a>";
		}
		else
		{
			$utf8mb4 = "-";
		}
		$table->construct_cell("<strong>{$tablename}</strong>");
		$table->construct_cell($status, array("class" => "align_center", 'width' => '15%'));
		$table->construct_cell($utf8mb4, array("class" => "align_center", 'width' => '25%'));
		$table->construct_row();
	}

	$table->output("<div>{$lang->utf8_conversion}</div>");

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->system_health);

	$plugins->run_hooks("admin_tools_system_health_start");

	$page->output_nav_tabs($sub_tabs, 'system_health');

	$table = new Table;
	$table->construct_header($lang->totals, array("colspan" => 2));
	$table->construct_header($lang->attachments, array("colspan" => 2));

	$query = $db->simple_select("attachments", "COUNT(*) AS numattachs, SUM(filesize) as spaceused, SUM(downloads*filesize) as bandwidthused", "visible='1' AND pid > '0'");
	$attachs = $db->fetch_array($query);

	$table->construct_cell("<strong>{$lang->total_database_size}</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size($db->fetch_size()), array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->attachment_space_used}</strong>", array('width' => '200'));
	$table->construct_cell(get_friendly_size((int)$attachs['spaceused']), array('width' => '200'));
	$table->construct_row();

	if($attachs['spaceused'] > 0)
	{
		$attach_average_size = round($attachs['spaceused']/$attachs['numattachs']);
		$bandwidth_average_usage = round($attachs['bandwidthused']);
	}
	else
	{
		$attach_average_size = 0;
		$bandwidth_average_usage = 0;
	}

	$table->construct_cell("<strong>{$lang->total_cache_size}</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size($cache->size_of()), array('width' => '25%'));
	$table->construct_cell("<strong>{$lang->estimated_attachment_bandwidth_usage}</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size($bandwidth_average_usage), array('width' => '25%'));
	$table->construct_row();


	$table->construct_cell("<strong>{$lang->max_upload_post_size}</strong>", array('width' => '200'));
	$table->construct_cell(@ini_get('upload_max_filesize').' / '.@ini_get('post_max_size'), array('width' => '200'));
	$table->construct_cell("<strong>{$lang->average_attachment_size}</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size($attach_average_size), array('width' => '25%'));
	$table->construct_row();

	$table->output($lang->stats);

	$table->construct_header($lang->task);
	$table->construct_header($lang->run_time, array("width" => 200, "class" => "align_center"));

	$task_cache = $cache->read("tasks");
	$nextrun = $task_cache['nextrun'];

	$query = $db->simple_select("tasks", "*", "nextrun >= '{$nextrun}' AND enabled='1'", array("order_by" => "nextrun", "order_dir" => "asc", 'limit' => 3));
	while($task = $db->fetch_array($query))
	{
		$task['title'] = htmlspecialchars_uni($task['title']);
		$next_run = date($mybb->settings['dateformat'], $task['nextrun']).", ".date($mybb->settings['timeformat'], $task['nextrun']);
		$table->construct_cell("<strong>{$task['title']}</strong>");
		$table->construct_cell($next_run, array("class" => "align_center"));

		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_tasks, array('colspan' => 2));
		$table->construct_row();
	}

	$table->output($lang->next_3_tasks);

	if(isset($mybb->admin['permissions']['tools']['backupdb']) && $mybb->admin['permissions']['tools']['backupdb'] == 1)
	{
		$backups = array();
		$dir = MYBB_ADMIN_DIR.'backups/';
		$handle = opendir($dir);
		while(($file = readdir($handle)) !== false)
		{
			if(filetype(MYBB_ADMIN_DIR.'backups/'.$file) == 'file')
			{
				$ext = get_extension($file);
				if($ext == 'gz' || $ext == 'sql')
				{
					$backups[@filemtime(MYBB_ADMIN_DIR.'backups/'.$file)] = array(
						"file" => $file,
						"time" => @filemtime(MYBB_ADMIN_DIR.'backups/'.$file),
						"type" => $ext
					);
				}
			}
		}

		$count = count($backups);
		krsort($backups);

		$table = new Table;
		$table->construct_header($lang->name);
		$table->construct_header($lang->backup_time, array("width" => 200, "class" => "align_center"));

		$backupscnt = 0;
		foreach($backups as $backup)
		{
			++$backupscnt;

			if($backupscnt == 4)
			{
				break;
			}

			$time = "-";
			if($backup['time'])
			{
				$time = my_date('relative', $backup['time']);
			}

			$table->construct_cell("<a href=\"index.php?module=tools-backupdb&amp;action=dlbackup&amp;file={$backup['file']}\">{$backup['file']}</a>");
			$table->construct_cell($time, array("class" => "align_center"));
			$table->construct_row();
		}

		if($count == 0)
		{
			$table->construct_cell($lang->no_backups, array('colspan' => 2));
			$table->construct_row();
		}

		$table->output($lang->existing_db_backups);
	}

	if(is_writable(MYBB_ROOT.'inc/settings.php'))
	{
		$message_settings = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_settings = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}

	if(is_writable(MYBB_ROOT.'inc/config.php'))
	{
		$message_config = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_config = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}

	if(is_writable('.'.$mybb->settings['uploadspath']))
	{
		$message_upload = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_upload = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}

	if(is_writable('../'.$mybb->settings['avataruploadpath']))
	{
		$message_avatar = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_avatar = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}

	if(is_writable(MYBB_ROOT.'inc/languages/'))
	{
		$message_language = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_language = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}

	if(is_writable(MYBB_ROOT.$config['admin_dir'].'/backups/'))
	{
		$message_backup = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_backup = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}

	if(is_writable(MYBB_ROOT.'/cache/'))
	{
		$message_cache = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_cache = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}

	if(is_writable(MYBB_ROOT.'/cache/themes/'))
	{
		$message_themes = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_themes = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}

	if($errors)
	{
		$page->output_error("<p><em>{$errors} {$lang->error_chmod}</span></strong> {$lang->chmod_info} <a href=\"http://docs.mybb.com/HowTo_Chmod.html\" target=\"_blank\">MyBB Docs</a>.</em></p>");
	}
	else
	{
		$page->output_success("<p><em>{$lang->success_chmod}</em></p>");
	}

	$table = new Table;
	$table->construct_header($lang->file);
	$table->construct_header($lang->location, array("colspan" => 2, 'width' => 250));

	$table->construct_cell("<strong>{$lang->config_file}</strong>");
	$table->construct_cell("./inc/config.php");
	$table->construct_cell($message_config);
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->settings_file}</strong>");
	$table->construct_cell("./inc/settings.php");
	$table->construct_cell($message_settings);
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->file_upload_dir}</strong>");
	$table->construct_cell($mybb->settings['uploadspath']);
	$table->construct_cell($message_upload);
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->avatar_upload_dir}</strong>");
	$table->construct_cell($mybb->settings['avataruploadpath']);
	$table->construct_cell($message_avatar);
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->language_files}</strong>");
	$table->construct_cell("./inc/languages");
	$table->construct_cell($message_language);
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->backup_dir}</strong>");
	$table->construct_cell('./'.$config['admin_dir'].'/backups');
	$table->construct_cell($message_backup);
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->cache_dir}</strong>");
	$table->construct_cell('./cache');
	$table->construct_cell($message_cache);
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->themes_dir}</strong>");
	$table->construct_cell('./cache/themes');
	$table->construct_cell($message_themes);
	$table->construct_row();

	$plugins->run_hooks("admin_tools_system_health_output_chmod_list");

	$table->output($lang->chmod_files_and_dirs);

	$page->output_footer();
}
