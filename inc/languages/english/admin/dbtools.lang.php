<?php
/**
 * MyBB 1.2 English Language Pack
 * Copyright © 2006 MyBB Group, All Rights Reserved
 * 
 * $Id$
 */

$l['nav_db_tools'] = "Database Tools";
$l['backup_database'] = "Backup Database";
$l['existing_backups'] = "Existing Backups";
$l['confirm_delete'] = "Confirm Backup Deletion";

$l['table_selection'] = "Table Selection";
$l['backup_options'] = "Backup Options";
$l['file_name'] = "Backup Filename";
$l['file_size'] = "File Size";
$l['file_type'] = "File Type";
$l['creation_date'] = "Creation Date";
$l['file_delete'] = "Delete";

$l['table_selection_desc'] = "You may select the database tables you wish to peform this action on here.  Hold down CTRL to select multiple tables.";
$l['export_file_type'] = "Export File Type";
$l['download_save'] = "Save Backup";
$l['contents'] = "Backup Contents";
$l['analyse_optimise'] = "Analyse and Optimise Selected Tables";
$l['gzip_compressed'] = "GZIP Compressed";
$l['plain_text'] = "Plain Text";
$l['save_backup_directory'] = "Backup Directory";
$l['download'] = "Download";
$l['structure_data'] = "Structure and Data";
$l['structure_only'] = "Structure";
$l['data_only'] = "Data Only";
$l['select_all'] = "Select all";
$l['deselect_all'] = "Deselect all";
$l['select_forum_tables'] = "Select Forum Tables";
$l['perform_backup'] = "Perform Backup";
$l['sequential_backup'] = "Perform a sequential backup?";

$l['utf8_conversion'] = "UTF-8 Conversion";
$l['converting_to_utf8'] = "MyBB is currently converting \"{1}\" table to UTF-8 language encoding from {2} encoding.";
$l['convert_to_utf'] = "You are about to convert the \"{1}\" table to UTF-8 language encoding from {2} encoding.";
$l['convert_all_to_utf'] = "You are about to convert ALL tables to UTF-8 language encoding from {1} encoding.";
$l['notice_proccess_long_time'] = "This proccess may take up to several hours depending on the size of your forum and this table.";
$l['please_wait'] = "Please wait...";
$l['converting_table'] = "Converting Table:";
$l['convert_table'] = "Convert Table:";
$l['convert_tables'] = "Convert All Tables:";
$l['convert_all'] = "Convert All";
$l['convert_database_table'] = "Convert Database Table";
$l['convert_database_tables'] = "Convert All Database Tables";
$l['table'] = "Table";
$l['status'] = "Status";
$l['convert_now'] = "Convert Now";
$l['error_invalid_table'] = "The specified table does not exist.";
$l['success_table_converted'] = "The specified table \"{1}\" has been sucessfully converted to UTF-8.";

$l['tables_optimized'] = "The selected database tables have successfully been optimized.";
$l['optimize_tables'] = "Optimize Database Tables";

$l['convert_to_utf8'] = "Convert to UTF-8";

$l['restore_database_desc'] = "Below is a list of the existing database backups stored in the MyBB backups directory.";
$l['no_existing_backups'] = "There are currently no existing database backups stored in the MyBB backups directory.";
$l['delete'] = "delete";

$l['confirm_delete_text'] = "Are you sure you would like to delete the selected backup?<br />This process cannot be undone.";

$l['backup_complete'] = "Backup generated successfully.<br /><br />The backup was saved to:<br />{1}<br /><br /><a href=\"{2}\">Download this backup</a>.";
$l['backup_deleted'] = "Backup Deleted Successfully";

$l['error_no_tables_selected'] = "You did not select any tables.";
$l['error_no_backup_specified'] = "No backup was selected for {1}.";
$l['error_delete_fail'] = "Could not delete selected backup.";
$l['error_backup_not_found'] = "The selected backup could not be found.";
$l['error_no_zlib'] = "The zlib library for PHP is not enabled, so your request could not be performed.";
$l['error_download_no_file'] = "You did not specify a database backup to download, so your request could not be performed.";
$l['error_download_fail'] = "An error occured while attempting to download your database backup.";

$l['note_cannot_write_backup'] = "Your backups directory (within the Admin CP directory) is not writable. You cannot save backups on the server.";

$l['deletion'] = "deletion";
?>