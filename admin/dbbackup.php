<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

require "./global.php";

// Load language packs for this section
global $lang;
$lang->load('dbbackup');

checkadminpermissions("canrundbtools");
logadmin();

addacpnav($lang->nav_db_tools, 'dbbackup.php?'.SID.'&action=backup');
switch($mybb->input['action'])
{
	case 'delete':
		addacpnav($lang->confirm_delete);
		break;
	case 'confirm_restore':
		addacpnav($lang->confirm_restore);
		break;
	case 'restore':
		addacpnav($lang->restore_database);
		break;
	case 'backup':
	default:
		addacpnav($lang->backup_database);
		break;
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
				cpredirect('dbbackup.php?'.SID.'&action=restore', $lang->backup_deleted);
			}
			else
			{
				cperror($lang->error_delete_fail);
			}
		}
	}
	else
	{
		$mybb->input['action'] = 'restore';
	}
}

if($mybb->input['action'] == 'do_restore')
{
	if($mybb->input['restoresubmit'])
	{
		$file = basename($mybb->input['file']);
		
		if(file_exists(MYBB_ADMIN_DIR.'backups/'.$file))
		{
			$timer = new timer();
			
			$explode = explode('.', $file);
			$file_name = $explode[0];
			$file_ext = $explode[1];
			
			if($file_ext == 'gz')
			{
				$type = 'gzip';
				$fp = gzopen(MYBB_ADMIN_DIR.'backups/'.$file, 'r');
			}
			else
			{
				$type = 'sql';
				$fp = fopen(MYBB_ADMIN_DIR.'backups/'.$file, 'r');
			}
			
			if(!$fp)
			{
				cperror($lang->error_restore_fail);
			}
			while(($type == 'gzip') ? !gzeof($fp) : !feof($fp))
			{
				if($type == 'gzip')
				{
					$output = gzgets($fp, 20480);
				}
				else
				{
					$output = fgets($fp, 20480);
				}
				
				if(substr($output, -2) == ";\n") // Find the end of line to execute query
				{
					$row .= substr($output, 0, -2);
					$db->query($row);
					$row = '';
				}
				elseif(substr($row, 0, 12) == 'CREATE TABLE' && substr($output, 0, 1) == ')') // Find the end of the CREATE TABLE
				{
					$row .= $output;
					$db->query($row);
					$row = '';
				}
				else
				{
					if(substr($output, 0, 2) == '--') // Continue to next line if this line is a comment
					{
						continue;
					}
					// Append this output to the existing query as this line is not the end of the query
					$row .= $output;
				}
			}
			
			if($type == 'gzip')
			{
				gzclose($fp);
			}
			else
			{
				fclose($fp);
			}
			
			$timer->stop();
			
			$lang->restore_complete = sprintf($lang->restore_complete, $timer->gettime());
			cpredirect('dbbackup.php?'.SID.'&action=backup', $lang->restore_complete);
		}
		else
		{
			cperror($lang->error_backup_not_found);
		}
	}
	else
	{
		$mybb->input['action'] = 'restore';
	}
}

if($mybb->input['action'] == 'do_backup')
{
	if(!is_array($mybb->input['tables']))
	{
		cperror($lang->error_no_tables_selected);
	}
	
	$timer = new timer;
	
	$host = explode('.', $_SERVER['REMOTE_ADDR']);
	$host = sprintf('%02X', $host[0]).sprintf('%02X', $host[1]).sprintf('%02X', $host[2]).sprintf('%02X', $host[3]);
	
	$file_ext = ($mybb->input['type'] == 'gzip') ? 'gz' : 'sql';
	$file = MYBB_ADMIN_DIR.'backups/'.time().$host.'.'.$file_ext;
	
	if($mybb->input['type'] == 'gzip')
	{
		$type = 'gzip';
		$fp = gzopen($file, 'w9');
	}
	else
	{
		$type = 'text';
		$fp = fopen($file, 'w');
	}
	
	$time = date('dS F Y \a\t H:i', time());
	$header = "-- MyBB Database Backup\n-- Generated: ".$time."\n---------------------------------------\n\n";
	
	if($type == 'gzip')
	{
		gzwrite($fp, $header, strlen($header));
	}
	else
	{
		fputs($fp, $header, strlen($header));
	}
	
	$check = array(chr(10), chr(13), chr(39));
	$replace = array(chr(92).chr(110), '', chr(92).chr(39));
	
	$table_list = $mybb->input['tables'];
	
	$comma = '';
	foreach($table_list as $table)
	{
		if($mybb->input['analyse'] == 'yes')
		{
			$tables .= $comma.$table;
			$comma = ', ';
			
			$db->optimize_table($table);
		}
		
		$fields = $db->show_fields_from($table);
		
		if($mybb->input['defs'] == 'yes')
		{
			$output = "DROP TABLE IF EXISTS ".$table.";\n";
			$output .= $db->show_create_table($table)."\n";
			$output .= "DELETE FROM ".$table.";\n";
		}
		else
		{
			$output = "DELETE FROM ".$table.";\n";
		}
		
		if($type == 'gzip')
		{
			gzwrite($fp, $output, strlen($output));
		}
		else
		{
			fputs($fp, $output, strlen($output));
		}
		
		$query = $db->simple_select($table);
		while($row = $db->fetch_array($query))
		{
			$output = "INSERT INTO ".$table." VALUES(";
			
			foreach($fields as $field)
			{
				if(!strstr($field['Type'], 'blob'))
				{
					$tmp = $row[$field['Field']];
					$tmp = "'".str_replace($check, $replace, $tmp)."',";
				}
				else
				{
					if(strlen($row[$field['Field']]) == 0)
					{
						$tmp = "'',";
					}
					else
					{
						$tmp = "0x".bin2hex($row[$field['Field']]).",";
					}
				}
				
				$output .= $tmp;
			}
			
			$output = substr($output, 0, -1);
			$output .= ");\n";
				
			if($type == 'gzip')
			{
				gzwrite($fp, $output, strlen($output));
			}
			else
			{
				fputs($fp, $output, strlen($output));
			}
		}
			
		$output = "\n";
		if($type == 'gzip')
		{
			gzwrite($fp, $output, strlen($output));
		}
		else
		{
			fputs($fp, $output, strlen($output));
		}
	}
	
	if($mybb->input['analyse'] == 'yes')
	{
		$output = "OPTIMIZE TABLE ".$tables."\n";
		$output .= "ANALYZE TABLE ".$tables."\n";
		
		if($type == 'gzip')
		{
			gzwrite($fp, $output, strlen($output));
		}
		else
		{
			fputs($fp, $output, strlen($output));
		}
	}
	
	if($type == 'gzip')
	{
		gzclose($fp);
	}
	else
	{
		fclose($fp);
	}
	
	$timer->stop();
	
	$lang->backup_complete = sprintf($lang->backup_complete, $timer->gettime());
	cpredirect('dbbackup.php?'.SID.'&action=backup', $lang->backup_complete);
}

if($mybb->input['action'] == "confirm_restore")
{
	if(!$mybb->input['backup'])
	{
		$lang->error_no_backup_specified = sprintf($lang->error_no_backup_specified, $lang->restoration);
		cperror($lang->error_no_backup_specified);
	}
	
	cpheader();
	startform('dbbackup.php', '', 'do_restore');
	makehiddencode('file', $mybb->input['backup']);
	starttable();
	tableheader($lang->confirm_restore);
	$yes_button = makebuttoncode('restoresubmit', $lang->yes);
	$no_button = makebuttoncode('no', $lang->no);
	makelabelcode('<div align="center">'.$lang->confirm_restore_text.'<br /><br />'.$yes_button.$no_button.'</div>');
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "delete")
{
	if(!$mybb->input['backup'])
	{
		$lang->error_no_backup_specified = sprintf($lang->error_no_backup_specified, $lang->deletion);
		cperror($lang->error_no_backup_specified);
	}
	
	cpheader();
	startform('dbbackup.php', '', 'do_delete');
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

if($mybb->input['action'] == "restore")
{
	cpheader();
	startform('dbbackup.php', '', 'do_restore');
	starttable();
	tableheader($lang->restore_database, 'restore_database', 5);
	
	$backups = array();
	$dir = MYBB_ADMIN_DIR.'backups/';
	$handle = opendir($dir);
	while(($file = readdir($handle)) !== false)
	{
		if(filetype(MYBB_ADMIN_DIR.'backups/'.$file) == 'file')
		{
			$ext = explode('.', basename($file));
			
			if($ext[1] == 'gz' || $ext[1] == 'sql')
			{
				$time = substr($ext[0], 0, 10);
				$backups[$time] = $file;
			}
		}
	}
	
	$keys = array_keys($backups);
	$count = count($backups);
	
	if($count != 0)
	{
		makelabelcode($lang->restore_database_desc, '', 5);
		echo "<tr>\n";
		echo "<td class=\"subheader\">".$lang->file_name."</td>\n";
		echo "<td class=\"subheader\" align=\"center\">".$lang->file_size."</td>\n";
		echo "<td class=\"subheader\" align=\"center\">".$lang->file_type."</td>\n";
		echo "<td class=\"subheader\" align=\"center\">".$lang->creation_date."</td>\n";
		echo "<td class=\"subheader\" align=\"center\">".$lang->options."</td>\n";
		echo "</tr>\n";

		$dir = './backups/';
		
		foreach($keys as $key)
		{
			$file = explode('.', $backups[$key]);
			$filename = $file[0];
			$type = $file[1];
			$file = $dir.$filename.'.'.$type;
			$bgcolor = getaltbg();
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\"><a href=\"".$file."\">".$filename."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".filesize($file)."</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".strtoupper($type)."</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".date('jS M Y \a\t H:i', $key)."</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><a href=\"dbbackup.php?".SID."&action=confirm_restore&backup=".$filename.'.'.$type."\">[ Restore ]</a> <a href=\"dbbackup.php?".SID."&action=delete&backup=".$filename.'.'.$type."\">[ Delete ]</a></td>\n";
			echo "</tr>\n";
		}
	}
	else
	{
		makelabelcode($lang->no_existing_backups);
	}
	
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == 'backup' || $mybb->input['action'] == '')
{
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
	cpheader();
	startform('dbbackup.php', 'table_selection', 'do_backup');
	starttable();
	tableheader($lang->backup_database);
	tablesubheader($lang->table_selection);
	$bgcolor = getaltbg();
	echo "<tr>\n";
	echo "<td class=\"$bgcolor\" valign=\"top\">".$lang->table_selection_desc."<br /><br /><a href=\"javascript:changeSelection('select', 0);\">".$lang->select_all."</a><br /><a href=\"javascript:changeSelection('deselect', 0);\">".$lang->deselect_all."</a><br /><a href=\"javascript:changeSelection('forum', '".TABLE_PREFIX."');\">".$lang->select_forum_tables."</a></td>\n";
	echo "<td class=\"$bgcolor\">\n";
	echo "<select id=\"table_select\" name=\"tables[]\" multiple=\"multiple\">\n";
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
	echo "<label><input type=\"radio\" name=\"type\" value=\"gzip\" checked=\"checked\" /> ".$lang->gzip_compressed."</label>\n";
	echo "<label><input type=\"radio\" name=\"type\" value=\"text\" /> ".$lang->plain_text."</label>\n";
	echo "</td>\n";
	echo "</tr>\n";
	makeyesnocode($lang->include_table_defs, 'defs');
	makeyesnocode($lang->analyse_optimise, 'analyse');
	endtable();
	endform($lang->perform_backup);
	cpfooter();
}
?>
