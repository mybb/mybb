<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */

$l['system_health'] = "System Health";
$l['system_health_desc'] = "Here you can view information on your system's health.";
$l['utf8_conversion'] = "UTF-8 Conversion";
$l['utf8_conversion_desc'] = "You are currently converting a database table to the UTF-8 format. Be aware that this process may take up to several hours depending on the size of your forum and this table. When the process is complete, you will be returned to the UTF-8 Conversion main page.";
$l['utf8_conversion_desc2'] = "This tool checks the database tables to make sure they are in the UTF-8 format and allows you to convert them if they are not.";

$l['convert_all'] = "Convert All";
$l['converting_to_utf8'] = "MyBB is currently converting \"{1}\" table to UTF-8 language encoding from {2} encoding.";
$l['convert_to_utf8'] = "You are about to convert the \"{1}\" table to UTF-8 language encoding from {2} encoding.";
$l['convert_all_to_utf'] = "You are about to convert ALL tables to UTF-8 language encoding from {1} encoding.";
$l['convert_all_to_utf8mb4'] = "You are about to convert ALL tables to 4-Byte UTF-8 Unicode language encoding from {1} encoding.";
$l['converting_to_utf8mb4'] = "MyBB is currently converting \"{1}\" table to 4-Byte UTF-8 Unicode language encoding from {2} encoding.";
$l['please_wait'] = "Please wait&hellip;";
$l['converting_table'] = "Converting Table: {1}";
$l['convert_table'] = "Convert Table";
$l['convert_tables'] = "Convert All Tables";
$l['convert_database_table'] = "Convert Database Table";
$l['convert_database_tables'] = "Convert All Database Tables";
$l['table'] = "Table";
$l['status_utf8'] = "UTF-8 Status";
$l['status_utf8mb4'] = "4-Byte UTF-8 Support<br />(requires MySQL 5.5.3 or above)";
$l['not_available'] = "Not available";
$l['all_tables'] = "All Tables";
$l['convert_now'] = "Convert Now";
$l['totals'] = "Totals";
$l['attachments'] = "Attachments";
$l['total_database_size'] = "Total Database Size";
$l['attachment_space_used'] = "Attachment Space used";
$l['total_cache_size'] = "Total Cache Size";
$l['estimated_attachment_bandwidth_usage'] = "Estimated Attachment Bandwidth Usage";
$l['max_upload_post_size'] = "Max Upload / POST Size";
$l['average_attachment_size'] = "Average Attachment Size";
$l['stats'] = "Stats";
$l['task'] = "Task";
$l['run_time'] = "Run Time";
$l['next_3_tasks'] = "Next 3 Tasks";
$l['no_tasks'] = "There are no tasks running at this time.";
$l['backup_time'] = "Backup Time";
$l['no_backups'] = "There are currently no backups made yet.";
$l['existing_db_backups'] = "Existing Database Backups";
$l['writable'] = "Writable";
$l['not_writable'] = "Not Writable";
$l['please_chmod_777'] = "Please CHMOD to 777.";
$l['chmod_info'] = "Please change the CHMOD settings to the ones specified with the file below. For more information on CHMODing, see the";
$l['file'] = "File";
$l['location'] = "Location";
$l['settings_file'] = "Settings File";
$l['config_file'] = "Configuration File";
$l['file_upload_dir'] = "File Uploads Directory";
$l['avatar_upload_dir'] = "Avatar Uploads Directory";
$l['language_files'] = "Language Files";
$l['backup_dir'] = "Backups Directory";
$l['cache_dir'] = "Cache Directory";
$l['themes_dir'] = "Themes Directory";
$l['chmod_files_and_dirs'] = "CHMOD Files and Directories";

$l['notice_process_long_time'] = "This process may take up to several hours depending on the size of your forum and this table. <strong>It is highly recommend to create a database backup as this process can't be undone.</strong>";
$l['notice_mb4_warning'] = "4-Byte UTF-8 Support requires MySQL 5.5.3 or above. You will not be able to import your database on a MySQL server with another version.";

$l['check_templates'] = "Check Templates";
$l['check_templates_desc'] = "Checks all installed templates for known security issues.";
$l['check_templates_title'] = "Check Template Security";
$l['check_templates_info'] = "This process will check your templates against security issues that could affect your forum and the server it runs on. This might take a while if you've installed many themes.
<br /><br />To start the process, press the 'Proceed' button below.";
$l['check_templates_info_desc'] = "The templates below matched known security issues. Please review them.";
$l['full_edit'] = "Full Edit";

$l['error_chmod'] = "of the required files and directories do not have proper CHMOD settings.";
$l['error_invalid_table'] = "The specified table does not exist.";
$l['error_db_encoding_not_set'] = "Your current setup of MyBB is not setup to use this tool yet. Please see the <a href=\"https://docs.mybb.com/1.8/administration/utf-8-setup/\">MyBB Docs</a> for more information on how to set it up.";
$l['error_not_supported'] = "Your current Database Engine is not supported by the UTF-8 Conversion Tool.";
$l['error_invalid_input'] = "There was a problem checking the templates. Please try again or contact the MyBB Group for support.";
$l['error_master_templates_altered'] = "The Master Templates have been altered. Please contact the MyBB Group for support on how to alter these.";
$l['error_utf8mb4_version'] = "Your MySQL version doesn't support 4-Byte UTF-8 encoding.";


$l['warning_multiple_encodings'] = "It is recommend not to use different encodings in your database. This may cause unexpected behavior or MySQL errors.";
$l['warning_utf8mb4_config'] = "For full 4-Byte UTF-8 support you need to change <i>\$config['database']['encoding'] = 'utf8';</i> to <i>\$config['database']['encoding'] = 'utf8mb4';</i> in your inc/config.php.";

$l['success_templates_checked'] = "Templates checked successfully - no security issues were found!";
$l['success_all_tables_already_converted'] = "All tables have already been converted or are already in UTF-8 format.";
$l['success_table_converted'] = "The selected table \"{1}\" has been converted to UTF-8 successfully.";
$l['success_chmod'] = "All of the required files and directories have the proper CHMOD settings.";
