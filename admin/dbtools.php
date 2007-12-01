<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/eula.html
 *
 * $Id$
 */

define("IN_MYBB", 1);

require_once "./global.php";

// Load language packs for this section
global $lang;
$lang->load('dbtools');

checkadminpermissions("canrundbtools");
logadmin();

addacpnav($lang->nav_db_tools, 'dbtools.php?'.SID);
switch($mybb->input['action'])
{
	case 'existing':
		addacpnav($lang->existing_backups);
		break;
	case 'backup':
		addacpnav($lang->backup_database);
		break;
	case 'optimize':
		addacpnav($lang->optimize_tables);
		break;
	case 'utf8_conversion':
		addacpnav($lang->convert_to_utf8);
		break;
}

if($mybb->input['action'] == "utf8_conversion")
{	
	cpheader();
	
	// The last step where we do the actual conversion process.
	if($mybb->request_method == "post" || ($mybb->input['table'] == "all" && isset($mybb->input['table2'])))
	{
		@set_time_limit(0);
		
		if($mybb->input['table'] == "all")
		{
			$all = true;
			$mybb->input['table'] = $mybb->input['table2'];
		}
		
		if(!$db->table_exists($db->escape_string($mybb->input['table'])))
		{
			cperror($lang->error_invalid_table);
		}
		
		starttable();
		
		$table1 = $db->show_create_table($db->escape_string($mybb->input['table']));
        preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table1, $matches);
		$charset = $matches[1];
		
		tableheader($converting_table." {$mybb->input['table']}", 'tablename', 1);
		
		echo "<tr>\n";
		echo "<td class=\"subheader\"><strong>".sprintf($lang->converting_to_utf8, $mybb->input['table'], $charset)."</strong></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td class=\"altbg1\" align=\"center\">{$lang->please_wait}</td>\n";
		echo "</tr>\n";
		
		flush();
		
		$types = array(
			'text' => 'blob',
			'mediumtext' => 'mediumblob',
			'longtext' => 'longblob',
			'char' => 'varbinary',
			'varchar' => 'varbinary',
			'tinytext' => 'tinyblob'
		);
		
		// Get next table in list
		$convert_to_binary = '';
		$convert_to_utf8 = '';
		$comma = '';
		
		// Set table default charset
		$db->query("ALTER TABLE {$mybb->input['table']} DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");

		// Fetch any fulltext keys
		if($db->supports_fulltext($mybb->input['table']))
		{
			$table_structure = $db->show_create_table($mybb->input['table']);
			preg_match_all("#FULLTEXT KEY `?([a-zA-Z0-9_]+)`? \(([a-zA-Z0-9_`,']+)\)#i", $table_structure, $matches);
			if(is_array($matches))
			{
				foreach($matches[0] as $key => $matched)
				{
					$db->query("ALTER TABLE {$mybb->input['table']} DROP INDEX {$matches[1][$key]}");
					$fulltext_to_create[$matches[1][$key]] = $matches[2][$key];
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
				$names = "CHANGE {$column['Field']} {$column['Field']} ";
				
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
				
				$convert_to_binary .= $comma.$names.preg_replace('/'.$type.'/i', $types[$type], $column['Type']).$attributes;
				$convert_to_utf8 .= "{$comma}{$names}{$column['Type']} CHARACTER SET utf8 COLLATE utf8_general_ci{$attributes}";
				
				$comma = ', ';
			}
		}
		
		if(!empty($convert_to_binary))
		{
			// This converts the columns to UTF-8 while also doing the same for data
			$db->query("ALTER TABLE {$mybb->input['table']} {$convert_to_binary}");
			$db->query("ALTER TABLE {$mybb->input['table']} {$convert_to_utf8}");
		}

		// Any fulltext indexes to recreate?
		if(is_array($fulltext_to_create))
		{
			foreach($fulltext_to_create as $name => $fields)
			{
				$db->create_fulltext_index($mybb->input['table'], $fields, $name);
			}
		}
		
		if($all == true)
		{
			$tables = $db->list_tables($config['database']);
			foreach($tables as $key => $tablename)
			{		
				if(substr($tablename, 0, strlen(TABLE_PREFIX)) == TABLE_PREFIX)
				{
					$table = $db->show_create_table($tablename);
					preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table, $matches);
					if(fetch_iconv_encoding($matches[1]) == 'utf-8' && $mybb->input['table'] != $tablename)
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
			
			if($nexttable)
			{
				$nexttable = $db->escape_string($nexttable);
				echo "<tr>\n";
				echo "<td class=\"altbg2\" align=\"center\">".sprintf($lang->success_table_converted, $mybb->input['table'])."</td>\n";
				echo "</tr>\n";
				endtable();
				echo "<meta http-equiv=\"Refresh\" content=\"5; url=dbtools.php?".SID."&amp;action=utf8_conversion&amp;table=all&amp;table2={$nexttable}\" />";
			}
			else
			{
				echo "<tr>\n";
				echo "<td class=\"altbg2\" align=\"center\">".sprintf($lang->success_table_converted, $mybb->input['table'])."</td>\n";
				echo "</tr>\n";
				endtable();
				echo "<meta http-equiv=\"Refresh\" content=\"5; url=dbtools.php?".SID."&amp;action=utf8_conversion\" />";
			}
		}
		else
		{	
			echo "<tr>\n";
			echo "<td class=\"altbg2\" align=\"center\">".sprintf($lang->success_table_converted, $mybb->input['table'])."</td>\n";
			echo "</tr>\n";
			endtable();
			echo "<meta http-equiv=\"Refresh\" content=\"5; url=dbtools.php?".SID."&amp;action=utf8_conversion\" />";
		}

		cpfooter();
		
		exit;
	}
	
	// This is the second step where we confirm the table we're about to convert.
	if($mybb->input['table'])
	{
		if($mybb->input['table'] != "all" && !$db->table_exists($db->escape_string($mybb->input['table'])))
		{
			cperror($lang->error_invalid_table);
		}
		
		if($mybb->input['table'] == "all")
		{
			$tables = $db->list_tables($config['database']);
			foreach($tables as $key => $tablename)
			{
				if(substr($tablename, 0, strlen(TABLE_PREFIX)) == TABLE_PREFIX)
				{
					$table = $db->show_create_table($tablename);
					preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table, $matches);
					if(fetch_iconv_encoding($matches[1]) == 'utf-8')
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
			}
			else
			{
				cperror($lang->error_all_tables_already_converted);
			}
		}
		else
		{
			$table = $db->show_create_table($db->escape_string($mybb->input['table']));
		}
				
        preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table, $matches);
		$charset = $matches[1];
		
		startform('dbtools.php', '', "utf8_conversion");
		makehiddencode('table', $mybb->input['table']);	
			
		
		if($mybb->input['table'] == "all")
		{
			makehiddencode('table2', $nexttable);
			starttable();
			tableheader($lang->convert_tables, 'converttable', 1);
			echo "<tr>\n";
			echo "<td class=\"subheader\"><strong>".sprintf($lang->convert_all_to_utf, $charset)."</strong></td>\n";
			echo "</tr>\n";
			echo "<tr>\n";
			echo "<td class=\"altbg1\" align=\"center\">{$lang->notice_proccess_long_time}</td>\n";
			echo "</tr>\n";
			
			$submit_button = makebuttoncode('submit', $lang->convert_database_tables);
		}
		else
		{	
			starttable();
			tableheader($lang->convert_table." {$mybb->input['table']}", 'converttable', 1);
			echo "<tr>\n";
			echo "<td class=\"subheader\"><strong>".sprintf($lang->convert_to_utf, $mybb->input['table'], $charset)."</strong></td>\n";
			echo "</tr>\n";
			echo "<tr>\n";
			echo "<td class=\"altbg1\" align=\"center\">{$lang->notice_proccess_long_time}</td>\n";
			echo "</tr>\n";
			
			$submit_button = makebuttoncode('submit', $lang->convert_database_table);
		}
		
		makelabelcode('<div align="center">'.$submit_button.'</div>');
		
		endtable();	
		endform();
		cpfooter();
		
		exit;	
	}
	
	$tables = $db->list_tables($config['database']);
	
	$not_okey_count = 0;
	$not_okey = array();
	$okay_count = 0;
	
	foreach($tables as $key => $tablename)
	{		
		if(substr($tablename, 0, strlen(TABLE_PREFIX)) == TABLE_PREFIX)
		{
			$table = $db->show_create_table($tablename);
        	preg_match("#CHARSET=([a-zA-Z0-9_]+)\s?#i", $table, $matches);
			if(fetch_iconv_encoding($matches[1]) != 'utf-8')
			{
				$not_okey[$key] = $tablename;
				++$not_okey_count;
			}
			else
			{
				++$okay_count;
			}
			
			$mybb_tables[$key] = $tablename;		
		}
	}
	
	asort($mybb_tables);
	
	if($not_okey_count == count($mybb_tables))
	{
		$disabled = " disabled=\"disabled\"";
	}
	else if($okay_count == count($mybb_tables))
	{
		cperror($lang->error_all_tables_already_converted);
	}
	
	// From here we display a list of tables to convert. This is the first step
	if(!$config['db_encoding'])
	{
		cperror($lang->error_db_encoding_not_set);
	}
	

	$hopto[] = "<input type=\"button\" value=\"{$lang->convert_all}\" onclick=\"hopto('dbtools.php?".SID."&amp;action=utf8_conversion&amp;table=all');\" class=\"hoptobutton\"{$disabled} />";
	makehoptolinks($hopto);
	
	starttable();
	tableheader($lang->utf8_conversion, 'utf8_conversion', 2);
	echo "<tr>\n";
	echo "<td class=\"subheader\">".$lang->table."</td>\n";
	echo "<td class=\"subheader\" align=\"center\">".$lang->status."</td>\n";
	echo "</tr>\n";
	
	foreach($mybb_tables as $key => $tablename)
	{
		if(array_key_exists($key, $not_okey))
		{
			$status = "<a href=\"dbtools.php?".SID."&amp;action=utf8_conversion&amp;table={$tablename}\">{$lang->convert_now}</a>";
		}
		else
		{
			$status = "OK";
		}
		$bgcolor = getaltbg();
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\"><strong>{$tablename}</strong></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\" width=\"15%\">{$status}</td>\n";
		echo "</tr>\n";
	}
	
	endtable();
	cpfooter();
}

if($mybb->input['action'] == 'do_delete')
{
	if($mybb->input['deletesubmit'])
	{
		$file = basename($mybb->input['file']);
		
		if(file_exists(MYBB_ADMIN_DIR.'backups/'.$file))
		{
			$delete = @unlink(MYBB_ADMIN_DIR.'backups/'.$file);
			
			if($delete)
			{
				cpredirect('dbtools.php?'.SID.'&action=existing', $lang->backup_deleted);
			}
			else
			{
				cperror($lang->error_delete_fail);
			}
		}
	}
	else
	{
		$mybb->input['action'] = 'existing';
	}
}

if($mybb->input['action'] == 'do_backup')
{
	if(!is_array($mybb->input['tables']))
	{
		cperror($lang->error_no_tables_selected);
	}
	
	@set_time_limit(0);
	
	if($mybb->input['write'] == 'disk')
	{
		$file = MYBB_ADMIN_DIR.'backups/backup_'.substr(md5($mybb->user['uid'].time().random_str()), 0, 10);
		
		if($mybb->input['type'] == 'gzip')
		{
			if(!function_exists('gzopen')) // check zlib-ness
			{
				cperror($lang->error_no_zlib);
			}
			
			$fp = gzopen($file.'.gz', 'w9');
		}
		else
		{
			$fp = fopen($file.'.sql', 'w');
		}
	}
	else
	{
		$file = 'backup_'.substr(md5($mybb->user['uid'].time().random_str()), 0, 10);
		if($mybb->input['type'] == 'gzip')
		{
			// Send headers for gzip file (do ob_start too)
			header('Content-Encoding: x-gzip');
			header('Content-Type: application/x-gzip');
			header('Content-Disposition: attachment; filename="'.$file.'.gz"');
		}
		else
		{
			// Send standard headers for .sql
			header('Content-Type: text/x-sql');
			header('Content-Disposition: attachment; filename="'.$file.'.sql"');
		}
	}
	
	$time = date('dS F Y \a\t H:i', time());
	$header = "-- MyBB Database Backup\n-- Generated: ".$time."\n-- -------------------------------------\n\n";
	$contents = $header;
	foreach($mybb->input['tables'] as $table)
	{
		$field_list = array();
		$query = $db->query("SHOW FIELDS FROM ".$table);
		while($row = $db->fetch_array($query))
		{
			$field_list[] = $row['Field'];
		}
		$fields = implode(",", $field_list);
		if($mybb->input['contents'] != 'data')
		{
			$structure = $db->show_create_table($table).";\n";
			$contents .= $structure;
			seq_backup($fp, $contents);
		}
		if($mybb->input['contents'] != 'structure')
		{
			$query = $db->simple_select($table);
			while($row = $db->fetch_array($query))
			{
				$insert = "INSERT INTO {$table} ($fields) VALUES (";
				$comma = '';
				foreach($field_list as $field)
				{
					if(!isset($row[$field]) || trim($row[$field]) == "")
					{
						$insert .= $comma."''";
					}
					else
					{
						$insert .= $comma."'".$db->escape_string($row[$field])."'";
					}
					$comma = ',';
				}
				$insert .= ");\n";
				$contents .= $insert;
				seq_backup($fp, $contents);
			}
		}
	}
	
	if($mybb->input['write'] == 'disk')
	{
		if($mybb->input['type'] == 'gzip')
		{
			gzwrite($fp, $contents);
			gzclose($fp);
		}
		else
		{
			fwrite($fp, $contents);
			fclose($fp);
		}
		
		if($mybb->input['type'] == 'gzip')
		{
			$ext = '.gz';
		}
		else
		{
			$ext = '.sql';
		}
		
		$file_from_admindir = 'dbtools.php?'.SID.'&amp;action=dlbackup&amp;file='.basename($file).$ext;
		$lang->backup_complete = sprintf($lang->backup_complete, $file.$ext, $file_from_admindir);
		cpmessage($lang->backup_complete);
	}
	else
	{
		if($mybb->input['type'] == "gzip")
		{
			echo gzencode($contents);
		}
		else
		{
			echo $contents;
		}
	}
}

if($mybb->input['action'] == "dlbackup")
{
	if(empty($mybb->input['file']))
	{
		cperror($lang->error_download_no_file);
	}
	
	@set_time_limit(0);
	
	$file = basename($mybb->input['file']);
	$ext = get_extension($file);	
		
	if(file_exists(MYBB_ADMIN_DIR.'backups/'.$file) && filetype(MYBB_ADMIN_DIR.'backups/'.$file) == 'file' && ($ext == 'gz' || $ext == 'sql'))
	{
		header('Content-disposition: attachment; filename='.$file);
		header("Content-type: ".$ext);
		header("Content-length: ".filesize(MYBB_ADMIN_DIR.'backups/'.$file));
		echo file_get_contents('./backups/'.$file);
	}
	else
	{
		cperror($lang->error_download_fail);
	}
}

if($mybb->input['action'] == "do_optimize")
{
	$plugins->run_hooks("admin_dbtools_do_optimize");

	if(!is_array($mybb->input['tables']))
	{
		cperror($lang->error_no_tables_selected);
	}
	
	@set_time_limit(0);
	
	foreach($mybb->input['tables'] as $table)
	{
		$db->optimize_table($table);
		$db->analyze_table($table);
	}
	
	cpmessage($lang->tables_optimized);
}

if($mybb->input['action'] == 'existing')
{
	cpheader();
	starttable();
	tableheader($lang->existing_backups, 'existing_backups', 5);
	
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
				$backups[] = array(
					"file" => $file,
					"time" => @filemtime(MYBB_ADMIN_DIR.'backups/'.$file),
					"type" => $ext
				);
			}
		}
	}
	
	$count = count($backups);
	
	if($count != 0)
	{
		makelabelcode($lang->restore_database_desc, '', 5);
		echo "<tr>\n";
		echo "<td class=\"subheader\">".$lang->file_name."</td>\n";
		echo "<td class=\"subheader\" align=\"center\">".$lang->file_size."</td>\n";
		echo "<td class=\"subheader\" align=\"center\">".$lang->file_type."</td>\n";
		echo "<td class=\"subheader\" align=\"center\">".$lang->creation_date."</td>\n";
		echo "<td class=\"subheader\" align=\"center\">".$lang->file_delete."</td>\n";
		echo "</tr>\n";

		$dir = './backups/';
		
		foreach($backups as $backup)
		{
			$filename = $backup['file'];
			if($backup['time'])
			{
				$time = my_date($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $backup['time']);
			}
			else
			{
				$time = "-";
			}
			$type = $backup['type'];
			$delete_link = "<a href=\"dbtools.php?".SID."&amp;action=delete&amp;backup=".$filename."\">[ ".$lang->delete." ]</a>";
			$bgcolor = getaltbg();
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\"><a href=\"dbtools.php?".SID."&amp;action=dlbackup&amp;file=".$filename."\">".$filename."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".get_friendly_size(filesize(MYBB_ADMIN_DIR.'backups/'.$filename))."</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".strtoupper($type)."</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">{$time}</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".$delete_link."</td>\n";
			echo "</tr>\n";
		}
	}
	else
	{
		makelabelcode($lang->no_existing_backups);
	}
	
	endtable();
	cpfooter();
}

if($mybb->input['action'] == 'delete')
{
	if(!$mybb->input['backup'])
	{
		$lang->error_no_backup_specified = sprintf($lang->error_no_backup_specified, $lang->deletion);
		cperror($lang->error_no_backup_specified);
	}
	
	cpheader();
	startform('dbtools.php', '', 'do_delete');
	makehiddencode('file', $mybb->input['backup']);
	starttable();
	tableheader($lang->confirm_delete);
	$yes_button = makebuttoncode('deletesubmit', $lang->yes);
	$no_button = makebuttoncode('no', $lang->no);
	makelabelcode('<div align="center">'.$lang->confirm_delete_text.'<br /><br />'.$yes_button.$no_button.'</div>');
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == 'optimize')
{
	$plugins->run_hooks("admin_dbtools_optimize");
	cpheader();
	echo "<script type=\"text/javascript\" language=\"Javascript\">
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
	</script>";	
	
	startform("dbtools.php", "table_selection" , "do_optimize");
	starttable();
	tableheader($lang->optimize_tables);
	tablesubheader($lang->table_selection);
	$bgcolor = getaltbg();
	echo "<tr>\n";
	echo "<td class=\"$bgcolor\" valign=\"top\">".$lang->table_selection_desc."<br /><br /><a href=\"javascript:changeSelection('select', 0);\">".$lang->select_all."</a><br /><a href=\"javascript:changeSelection('deselect', 0);\">".$lang->deselect_all."</a><br /><a href=\"javascript:changeSelection('forum', '".TABLE_PREFIX."');\">".$lang->select_forum_tables."</a></td>\n";
	echo "<td class=\"$bgcolor\">\n";
	echo "<select id=\"table_select\" name=\"tables[]\" size=\"20\" multiple=\"multiple\">\n";
	$table_list = $db->list_tables($config['database']);
	foreach($table_list as $id => $table_name)
	{
		echo "<option value=\"".$table_name."\" selected=\"selected\">".$table_name."</option>\n";
	}
	echo "</select>\n";
	echo "</td>\n";
	echo "</tr>\n";
	endtable();
	endform($lang->optimize_tables);
	cpfooter();
}

if($mybb->input['action'] == 'backup' || $mybb->input['action'] == '')
{
	cpheader();
	echo "<script type=\"text/javascript\" language=\"Javascript\">
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
		</script>";
	// Check if file is writable, before allowing submission
	if(!is_writable(MYBB_ADMIN_DIR."/backups"))
	{
		$lang->update_button = '';
		makewarning($lang->note_cannot_write_backup);
		$cannot_write = true;
	}
	startform('dbtools.php', 'table_selection', 'do_backup');
	starttable();
	tableheader($lang->backup_database);
	tablesubheader($lang->table_selection);
	$bgcolor = getaltbg();
	echo "<tr>\n";
	echo "<td class=\"$bgcolor\" valign=\"top\">".$lang->table_selection_desc."<br /><br /><a href=\"javascript:changeSelection('select', 0);\">".$lang->select_all."</a><br /><a href=\"javascript:changeSelection('deselect', 0);\">".$lang->deselect_all."</a><br /><a href=\"javascript:changeSelection('forum', '".TABLE_PREFIX."');\">".$lang->select_forum_tables."</a></td>\n";
	echo "<td class=\"$bgcolor\">\n";
	echo "<select id=\"table_select\" name=\"tables[]\" size=\"20\" multiple=\"multiple\">\n";
	$table_list = $db->list_tables($config['database']);
	foreach($table_list as $id => $table_name)
	{
		echo "<option value=\"".$table_name."\">".$table_name."</option>\n";
	}
	echo "</select>\n";
	echo "</td>\n";
	echo "</tr>\n";
	tablesubheader($lang->backup_options);
	$bgcolor = getaltbg();
	echo "<tr>\n";
	echo "<td class=\"$bgcolor\">".$lang->export_file_type."</td>\n";
	echo "<td class=\"$bgcolor\">\n";
	if(function_exists("gzwrite") && function_exists("gzencode"))
	{
		echo "<label><input type=\"radio\" name=\"type\" value=\"gzip\" checked=\"checked\" /> ".$lang->gzip_compressed."</label><br />\n";
		echo "<label><input type=\"radio\" name=\"type\" value=\"text\" /> ".$lang->plain_text."</label>\n";
	}
	else
	{
		echo "<label><input type=\"radio\" name=\"type\" value=\"text\" checked=\"checked\" /> ".$lang->plain_text."</label>\n";		
	}
	echo "</td>\n";
	echo "</tr>\n";
	$bgcolor = getaltbg();
	echo "<tr>\n";
	echo "<td class=\"$bgcolor\">".$lang->download_save."</td>\n";
	echo "<td class=\"$bgcolor\">\n";
	echo "<label><input type=\"radio\" name=\"write\" value=\"disk\" ".($cannot_write?"disabled=\"disabled\"":"")." /> ".$lang->save_backup_directory."</label><br />\n";
	echo "<label><input type=\"radio\" name=\"write\" value=\"download\" checked=\"checked\" /> ".$lang->download."</label>\n";
	echo "</td>\n";
	echo "</tr>\n";
	$bgcolor = getaltbg();
	echo "<tr>\n";
	echo "<td class=\"$bgcolor\">".$lang->contents."</td>\n";
	echo "<td class=\"$bgcolor\">\n";
	echo "<label><input type=\"radio\" name=\"contents\" value=\"both\" checked=\"checked\" /> ".$lang->structure_data."</label><br />\n";
	echo "<label><input type=\"radio\" name=\"contents\" value=\"structure\" /> ".$lang->structure_only."</label><br />\n";
	echo "<label><input type=\"radio\" name=\"contents\" value=\"data\" /> ".$lang->data_only."</label>\n";
	echo "</td>\n";
	echo "</tr>\n";	
	makeyesnocode($lang->analyse_optimise, 'analyse');
	makeyesnocode($lang->sequential_backup, 'sequential_backup');
	endtable();
	endform($lang->perform_backup);
	cpfooter();
}

function seq_backup($fp, &$contents) 
{
	global $mybb;
	
	if($mybb->input['sequential_backup'] == 'yes') 
	{
		if($mybb->input['write'] == 'disk') 
		{
			if($mybb->input['type'] == 'gzip') 
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
			if($mybb->input['type'] == "gzip")
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
}
?>