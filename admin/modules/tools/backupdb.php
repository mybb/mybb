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

// Allows us to refresh cache to prevent over flowing
function clear_overflow($fp, &$contents)
{
	global $mybb;

	if($mybb->input['method'] == 'disk')
	{
		if($mybb->input['filetype'] == 'gzip')
		{
			gzwrite($fp, $contents);
		}
		else
		{
			fwrite($fp, $contents);
		}
	}
	else
	{
		if($mybb->input['filetype'] == "gzip")
		{
			echo gzencode($contents);
		}
		else
		{
			echo $contents;
		}
	}

	$contents = '';
}

$page->add_breadcrumb_item($lang->database_backups, "index.php?module=tools-backupdb");

$plugins->run_hooks("admin_tools_backupdb_begin");

if($mybb->input['action'] == "dlbackup")
{
	if(empty($mybb->input['file']))
	{
		flash_message($lang->error_file_not_specified, 'error');
		admin_redirect("index.php?module=tools-backupdb");
	}

	$plugins->run_hooks("admin_tools_backupdb_dlbackup");

	$file = basename($mybb->input['file']);
	$ext = get_extension($file);

	if(file_exists(MYBB_ADMIN_DIR.'backups/'.$file) && filetype(MYBB_ADMIN_DIR.'backups/'.$file) == 'file' && ($ext == 'gz' || $ext == 'sql'))
	{
		$plugins->run_hooks("admin_tools_backupdb_dlbackup_commit");

		// Log admin action
		log_admin_action($file);

		header('Content-disposition: attachment; filename='.$file);
		header("Content-type: ".$ext);
		header("Content-length: ".filesize(MYBB_ADMIN_DIR.'backups/'.$file));
		echo file_get_contents(MYBB_ADMIN_DIR.'backups/'.$file);
	}
	else
	{
		flash_message($lang->error_invalid_backup, 'error');
		admin_redirect("index.php?module=tools-backupdb");
	}
}

if($mybb->input['action'] == "delete")
{
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=tools-backupdb");
	}

	$file = basename($mybb->input['file']);

	if(!trim($mybb->input['file']) || !file_exists(MYBB_ADMIN_DIR.'backups/'.$file))
	{
		flash_message($lang->error_backup_doesnt_exist, 'error');
		admin_redirect("index.php?module=tools-backupdb");
	}

	$plugins->run_hooks("admin_tools_backupdb_delete");

	if($mybb->request_method == "post")
	{
		$delete = @unlink(MYBB_ADMIN_DIR.'backups/'.$file);

		if($delete)
		{
			$plugins->run_hooks("admin_tools_backupdb_delete_commit");

			// Log admin action
			log_admin_action($file);

			flash_message($lang->success_backup_deleted, 'success');
			admin_redirect("index.php?module=tools-backupdb");
		}
		else
		{
			flash_message($lang->error_backup_not_deleted, 'error');
			admin_redirect("index.php?module=tools-backupdb");
		}
	}
	else
	{
		$page->output_confirm_action("index.php?module=tools-backupdb&amp;action=delete&amp;file={$mybb->input['file']}", $lang->confirm_backup_deletion);
	}
}

if($mybb->input['action'] == "backup")
{
	$plugins->run_hooks("admin_tools_backupdb_backup");

	if($mybb->request_method == "post")
	{
		if(!is_array($mybb->input['tables']))
		{
			flash_message($lang->error_tables_not_selected, 'error');
			admin_redirect("index.php?module=tools-backupdb&action=backup");
		}

		@set_time_limit(0);

		if($mybb->input['method'] == 'disk')
		{
			$file = MYBB_ADMIN_DIR.'backups/backup_'.date("_Ymd_His_").random_str(16);

			if($mybb->input['filetype'] == 'gzip')
			{
				if(!function_exists('gzopen')) // check zlib-ness
				{
					flash_message($lang->error_no_zlib, 'error');
					admin_redirect("index.php?module=tools-backupdb&action=backup");
				}

				$fp = gzopen($file.'.incomplete.sql.gz', 'w9');
			}
			else
			{
				$fp = fopen($file.'.incomplete.sql', 'w');
			}
		}
		else
		{
			$file = 'backup_'.substr(md5($mybb->user['uid'].TIME_NOW), 0, 10).random_str(54);
			if($mybb->input['filetype'] == 'gzip')
			{
				if(!function_exists('gzopen')) // check zlib-ness
				{
					flash_message($lang->error_no_zlib, 'error');
					admin_redirect("index.php?module=tools-backupdb&action=backup");
				}

				// Send headers for gzip file
				header('Content-Encoding: gzip');
				header('Content-Type: application/x-gzip');
				header('Content-Disposition: attachment; filename="'.$file.'.sql.gz"');
			}
			else
			{
				// Send standard headers for .sql
				header('Content-Type: text/x-sql');
				header('Content-Disposition: attachment; filename="'.$file.'.sql"');
			}
		}
		$db->set_table_prefix('');

		$time = date('dS F Y \a\t H:i', TIME_NOW);
		$header = "-- MyBB Database Backup\n-- Generated: {$time}\n-- -------------------------------------\n\n";
		$contents = $header;
		foreach($mybb->input['tables'] as $table)
		{
			if(!$db->table_exists($db->escape_string($table)))
			{
				continue;
			}
			if($mybb->input['analyzeoptimize'] == 1)
			{
				$db->optimize_table($table);
				$db->analyze_table($table);
			}

			$field_list = array();
			$fields_array = $db->show_fields_from($table);
			foreach($fields_array as $field)
			{
				$field_list[] = $field['Field'];
			}

			$fields = "`".implode("`,`", $field_list)."`";
			if($mybb->input['contents'] != 'data')
			{
				$structure = $db->show_create_table($table).";\n";
				$contents .= $structure;
				clear_overflow($fp, $contents);
			}

			if($mybb->input['contents'] != 'structure')
			{
				if($db->engine == 'mysqli')
				{
					$query = mysqli_query($db->read_link, "SELECT * FROM {$db->table_prefix}{$table}", MYSQLI_USE_RESULT);
				}
				else
				{
					$query = $db->simple_select($table);
				}

				while($row = $db->fetch_array($query))
				{
					$insert = "INSERT INTO {$table} ($fields) VALUES (";
					$comma = '';
					foreach($field_list as $field)
					{
						if(!isset($row[$field]) || is_null($row[$field]))
						{
							$insert .= $comma."NULL";
						}
						else if($db->engine == 'mysqli')
						{
							$insert .= $comma."'".mysqli_real_escape_string($db->read_link, $row[$field])."'";
						}
						else
						{
							$insert .= $comma."'".$db->escape_string($row[$field])."'";
						}
						$comma = ',';
					}
					$insert .= ");\n";
					$contents .= $insert;
					clear_overflow($fp, $contents);
				}
				$db->free_result($query);
			}
		}

		$db->set_table_prefix(TABLE_PREFIX);

		if($mybb->input['method'] == 'disk')
		{
			if($mybb->input['filetype'] == 'gzip')
			{
				gzwrite($fp, $contents);
				gzclose($fp);
				rename($file.'.incomplete.sql.gz', $file.'.sql.gz');
			}
			else
			{
				fwrite($fp, $contents);
				fclose($fp);
				rename($file.'.incomplete.sql', $file.'.sql');
			}

			if($mybb->input['filetype'] == 'gzip')
			{
				$ext = '.sql.gz';
			}
			else
			{
				$ext = '.sql';
			}

			$plugins->run_hooks("admin_tools_backupdb_backup_disk_commit");

			// Log admin action
			log_admin_action("disk", $file.$ext);

			$file_from_admindir = 'index.php?module=tools-backupdb&amp;action=dlbackup&amp;file='.basename($file).$ext;
			flash_message("<span><em>{$lang->success_backup_created}</em></span><p>{$lang->backup_saved_to}<br />{$file}{$ext} (<a href=\"{$file_from_admindir}\">{$lang->download}</a>)</p>", 'success');
			admin_redirect("index.php?module=tools-backupdb");
		}
		else
		{
			$plugins->run_hooks("admin_tools_backupdb_backup_download_commit");

			// Log admin action
			log_admin_action("download");

			if($mybb->input['filetype'] == 'gzip')
			{
				echo gzencode($contents);
			}
			else
			{
				echo $contents;
			}
		}

		exit;
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

	$page->add_breadcrumb_item($lang->new_database_backup);
	$page->output_header($lang->new_database_backup);

	$sub_tabs['database_backup'] = array(
		'title' => $lang->database_backups,
		'link' => "index.php?module=tools-backupdb"
	);

	$sub_tabs['new_backup'] = array(
		'title' => $lang->new_backup,
		'link' => "index.php?module=tools-backupdb&amp;action=backup",
		'description' => $lang->new_backup_desc
	);

	$page->output_nav_tabs($sub_tabs, 'new_backup');

	// Check if file is writable, before allowing submission
	if(!is_writable(MYBB_ADMIN_DIR."/backups"))
	{
		$lang->update_button = '';
		$page->output_alert($lang->alert_not_writable);
		$cannot_write = true;
	}

	$table = new Table;
	$table->construct_header($lang->table_selection);
	$table->construct_header($lang->backup_options);

	$table_selects = array();
	$table_list = $db->list_tables($config['database']['database']);
	foreach($table_list as $id => $table_name)
	{
		$table_selects[$table_name] = $table_name;
	}

	$form = new Form("index.php?module=tools-backupdb&amp;action=backup", "post", "table_selection", 0, "table_selection");

	$table->construct_cell("{$lang->table_select_desc}\n<br /><br />\n<a href=\"javascript:changeSelection('select', 0);\">{$lang->select_all}</a><br />\n<a href=\"javascript:changeSelection('deselect', 0);\">{$lang->deselect_all}</a><br />\n<a href=\"javascript:changeSelection('forum', '".TABLE_PREFIX."');\">{$lang->select_forum_tables}</a>\n<br /><br />\n<div class=\"form_row\">".$form->generate_select_box("tables[]", $table_selects, false, array('multiple' => true, 'id' => 'table_select', 'size' => 20))."</div>", array('rowspan' => 5, 'width' => '50%', 'style' => 'border-bottom: 0px'));
	$table->construct_row();

	$table->construct_cell("<strong>{$lang->file_type}</strong><br />\n{$lang->file_type_desc}<br />\n<div class=\"form_row\">".$form->generate_radio_button("filetype", "gzip", $lang->gzip_compressed, array('checked' => 1))."<br />\n".$form->generate_radio_button("filetype", "plain", $lang->plain_text)."</div>", array('width' => '50%'));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->save_method}</strong><br />\n{$lang->save_method_desc}<br /><div class=\"form_row\">".$form->generate_radio_button("method", "disk", $lang->backup_directory)."<br />\n".$form->generate_radio_button("method", "download", $lang->download, array('checked' => 1))."</div>", array('width' => '50%'));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->backup_contents}</strong><br />\n{$lang->backup_contents_desc}<br /><div class=\"form_row\">".$form->generate_radio_button("contents", "both", $lang->structure_and_data, array('checked' => 1))."<br />\n".$form->generate_radio_button("contents", "structure", $lang->structure_only)."<br />\n".$form->generate_radio_button("contents", "data", $lang->data_only)."</div>", array('width' => '50%'));
	$table->construct_row();
	$table->construct_cell("<strong>{$lang->analyze_and_optimize}</strong><br />\n{$lang->analyze_and_optimize_desc}<br /><div class=\"form_row\">".$form->generate_yes_no_radio("analyzeoptimize")."</div>", array('width' => '50%'));
	$table->construct_row();

	$table->output($lang->new_database_backup);

	$buttons[] = $form->generate_submit_button($lang->perform_backup);
	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$page->add_breadcrumb_item($lang->backups);
	$page->output_header($lang->database_backups);

	$sub_tabs['database_backup'] = array(
		'title' => $lang->database_backups,
		'link' => "index.php?module=tools-backupdb",
		'description' => $lang->database_backups_desc
	);

	$sub_tabs['new_backup'] = array(
		'title' => $lang->new_backup,
		'link' => "index.php?module=tools-backupdb&amp;action=backup",
	);

	$plugins->run_hooks("admin_tools_backupdb_start");

	$page->output_nav_tabs($sub_tabs, 'database_backup');

	$backups = array();
	$dir = MYBB_ADMIN_DIR.'backups/';
	$handle = opendir($dir);

	if($handle !== false)
	{
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
		closedir($handle);
	}

	$count = count($backups);
	krsort($backups);

	$table = new Table;
	$table->construct_header($lang->backup_filename);
	$table->construct_header($lang->file_size, array("class" => "align_center"));
	$table->construct_header($lang->creation_date);
	$table->construct_header($lang->controls, array("class" => "align_center"));

	foreach($backups as $backup)
	{
		$time = "-";
		if($backup['time'])
		{
			$time = my_date('relative', $backup['time']);
		}

		$table->construct_cell("<a href=\"index.php?module=tools-backupdb&amp;action=dlbackup&amp;file={$backup['file']}\">{$backup['file']}</a>");
		$table->construct_cell(get_friendly_size(filesize(MYBB_ADMIN_DIR.'backups/'.$backup['file'])), array("class" => "align_center"));
		$table->construct_cell($time);
		$table->construct_cell("<a href=\"index.php?module=tools-backupdb&amp;action=backup&amp;action=delete&amp;file={$backup['file']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_backup_deletion}')\">{$lang->delete}</a>", array("class" => "align_center"));
		$table->construct_row();
	}

	if($count == 0)
	{
		$table->construct_cell($lang->no_backups, array('colspan' => 4));
		$table->construct_row();
	}

	$table->output($lang->existing_database_backups);
	$page->output_footer();
}
