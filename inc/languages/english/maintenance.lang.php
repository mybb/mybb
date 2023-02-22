<?php

/**
 * Language strings used on maintenance pages and in Process interfaces.
 *
 * Strings in this file may need to remain readable after `strip_tags()` is applied
 */

#region Process - common
$l['operation_error_title'] = '{1} — Error';
$l['operation_error_message'] = 'The operation failed. Please check error logs for details.';
$l['operation_warning_title'] = '{1} — Warning';

// navigation
$l['retry'] = 'Retry';
$l['retry_operation_confirm'] = 'Retry operation before proceeding?';
$l['retry_parameter_input'] = 'Change parameter values?';
$l['step_finish'] = 'Next';
$l['step_final_finish'] = 'Finish';
$l['waiting_for_operation'] = 'Waiting for {1}…';
$l['waiting_for_operation_hidden'] = 'Waiting…';
$l['redirecting'] = 'Redirecting…';
#endregion

#region CLI
$l['using_env_parameter_value'] = 'Using environment variable <{3}> for {1}';
$l['using_env_parameter_value_revealed'] = 'Using environment variable <{3}> for {1}: "{2}"';

$l['using_default_parameter_value'] = 'Using default parameter value for {1}';
$l['using_default_parameter_value_revealed'] = 'Using default parameter value for {1}: "{2}"';

$l['unknown_operation'] = 'Unknown operation "{1}"';
$l['unknown_parameter'] = 'Unknown process parameter "{1}"';
#endregion

#region GUI
$l['optional'] = 'optional';
$l['show_password'] = 'Show Password';
$l['weak_password'] = 'Weak password';

// language select
$l['select_language'] = 'Select language';
$l['change_language'] = 'Go';

// versionCheck action
$l['version_check'] = 'Check Latest Version';
$l['version_check_active'] = 'Checking for updates…';
$l['version_check_newer'] = 'The most recent version is <a href="https://mybb.com/download" target="_blank">{1}</a>';
$l['version_check_latest'] = 'Latest version';
$l['version_check_error'] = 'Could not check automatically — visit <a href="https://mybb.com" target="_blank" rel="noreferrer">MyBB.com</a>';

// insecure transport warning
$l['insecure_transport'] = 'No Secure Connection';
$l['insecure_transport_message'] = 'Sensitive information entered here may be visible to others. You should ensure the server supports HTTPS, and use the <code>https://</code> protocol before proceeding.';

// miscellaneous
$l['support'] = 'Need help? Check the MyBB <a href="https://docs.mybb.com" target="_blank">Documentation</a> and <a href="https://mybb.com/support" target="_blank">Support channels</a>.';
$l['powered_by_phrases'] = 'Constructing new forum on
Bringing people together with
Getting ready for
Connecting people through
A community just right on
Instilling potential with
Forum to the letter on
Soon to be powered by';
#endregion

#region fatal errors
$l['locked_title'] = 'Unlock to Continue';
$l['locked'] = 'Please remove the <code>{1}</code> file from the <code>install/</code> directory and try again in order to install or upgrade this MyBB board.';

$l['empty_config_to_reinstall_title'] = 'Before Re-Installing MyBB';
$l['empty_config_to_reinstall'] = '<p>If you are sure you want to overwrite the existing forum and install MyBB again, empty or delete the configuration file <code>inc/config.php</code> and refresh this page.
<br><br>
<p>If you intend to upgrade your forum instead, <a href="./upgrade.php"><strong>click here</strong></a>.';

$l['upgrade_initialization_failed_title'] = 'Initialization Failed';
$l['upgrade_initialization_failed'] = 'To upgrade MyBB, a functioning board is required. If you intend to set up a new board, launch the <a href="./index.php?process=install"><strong>installation process</strong></a> instead.';

$l['upgrade_not_authorized_title'] = 'Authorization Required to Upgrade';
$l['upgrade_not_authorized'] = 'Create a file named <code>{1}</code> in the installation directory and refresh this page.';

$l['upgrade_not_needed_title'] = 'Already Up to Date';
$l['upgrade_not_needed'] = 'Running the upgrade process is not needed for this version.<br><br>If you believe this may be an error, you can <a href="./index.php?process=upgrade&force=1">force the upgrade</a>. Otherwise, you can delete the installation directory.</p>';
#endregion

#region common operations content
// flag listing
$l['flags'] = 'Custom Options';
$l['flag_value'] = '{1}: {2}';
$l['flag_development_mode'] = 'Development mode';
$l['flag_no_discovery'] = 'No database suggestions';
$l['flag_force'] = 'Force update';
$l['flag_fast'] = 'Development mode & use defaults';

// installation state
$l['installation_state'] = 'Current Status';
$l['installation_state_none'] = 'Not installed';
$l['installation_state_none_description'] = 'No installed MyBB board was detected.';
$l['installation_state_configuration_file'] = '<abbr title="inc/config.php">Configuration file</abbr> with no working database connection';
$l['installation_state_configuration_file_description'] = 'A <abbr title="inc/config.php">Configuration file</abbr> exists, but no working database connection can be established.';
$l['installation_state_database_connection'] = '<abbr title="inc/config.php">Configuration file</abbr> with valid database credentials, no data in database';
$l['installation_state_database_connection_description'] = 'A <abbr title="inc/config.php">Configuration file</abbr> with valid database credentials exists, but no data was found in the database.';
$l['installation_state_installed'] = 'Installed version {1} or newer';
#endregion

#region data seeding
$l['board_name_default'] = 'My Board';
$l['welcome_thread_username'] = 'MyBB Installer';
$l['welcome_thread_subject'] = 'Welcome to MyBB';
$l['welcome_thread_message'] = 'Your forum has been successfully installed.

To access MyBB support resources, documentation, troubleshoot problems, or submit feedback, please visit [url=https://mybb.com/support/]mybb.com/support[/url].

We invite you to become a part of the MyBB Community formed by users, webmasters, extension authors, and developers by joining the [url=https://community.mybb.com]Community Forums[/url].

Thanks,
The MyBB Team[color=#ffffff]. [i]Free never tasted so good.[/i][/color]
';
$l['welcome_thread_message_devmode'] = 'MyBB {2} installed at {3} in development mode with [i]{4}[/i] using [i]{5}[/i].

[list]
[*][url={1}?process=install][color=#007AC8][b]Reinstall[/b][/color][/url]
[*][url={1}?process=install&dev][color=#007AC8][b]Reinstall[/b] in development mode[/color][/url]
[*][url={1}?process=install&fast][color=#007AC8][b]Fast Reinstall[/b] (development mode & use defaults)[/color][/url]
[/list]
[list]
[*][url={1}?process=upgrade][color=#218463][b]Upgrade[/b][/color][/url]
[*][url={1}?process=upgrade&dev][color=#218463][b]Upgrade[/b] in development mode[/color][/url]
[*][url={1}?process=upgrade&fast][color=#218463][b]Fast Upgrade[/b] (development mode & use defaults)[/color][/url]
[/list]';
#endregion

#region miscellaneous
$l['task_versioncheck_ran'] = 'The version check task successfully ran.';
$l['na'] = 'N/A';
#endregion

#region Process Model: install
// general
$l['install_page_title'] = 'MyBB Installation';
$l['install_header_title'] = 'Installation';

// steps
$l['install_step_start_title'] = 'Start';
$l['install_step_database_title'] = 'Database';
$l['install_step_settings_title'] = 'Settings';
$l['install_step_account_title'] = 'My Account';

// step pages
$l['install_step_start_heading'] = 'Install New Board';
$l['install_step_start_heading_reinstall'] = 'Re-install Board';
$l['install_step_start_description'] = 'Welcome to the MyBB setup. During this process the forum software will be installed on your server.';
$l['install_step_start_description_reinstall'] = 'Welcome to the MyBB setup. During this process the forum software will be installed again on your server, overwriting the existing board. A new forum will be created — current threads, posts, users and other information will be deleted (<a href="./index.php?process=upgrade"><strong>upgrade instead</strong></a>?).';
$l['install_step_start_description_reinstall_cli'] = 'Welcome to the MyBB setup. During this process the forum software will be installed again on your server, overwriting the existing board. A fresh forum will be created and current threads, posts, users and other information will be deleted.';

$l['install_step_database_heading'] = 'Connect to a Database';
$l['install_step_database_instructions'] = 'Provide connection details for the database where forum content will be stored. If you do not have this information, it can be usually obtained from your web host, or a database can be created in your hosting control panel.';

$l['install_step_settings_heading'] = 'Set Board Settings';
$l['install_step_settings_instructions'] = 'Configure and review basic settings for your new board. These can be easily changed later.';

$l['install_step_account_heading'] = 'Create an Account';
$l['install_step_account_instructions'] = 'Set up login details for your account with administrator permissions.';

// operations
$l['operation_requirements_check_title'] = 'Requirements Check';
$l['operation_file_verification_title'] = 'File Verification';
$l['operation_statistics_title'] = 'Statistics';
$l['operation_configuration_file_title'] = 'Configuration File';
$l['operation_database_structure_title'] = 'Database Structure';
$l['operation_database_population_title'] = 'Database Population';
$l['operation_board_settings_title'] = 'Board Settings';
$l['operation_user_account_title'] = 'User Account';
$l['operation_build_cache_title'] = 'Cache Building';
$l['operation_lock_title'] = 'Installer Lock';

$l['operation_requirements_check_error_title'] = 'Some requirements not met';

// parameters
$l['parameter_send_specifications_title'] = 'Send one-time anonymous statistics with server specifications to improve MyBB <a href="https://docs.mybb.com/1.8/install/anonymous-statistics/" target="_blank" rel="noreferrer" title="More information on MyBB.com" class="information"><i class="fas fa-info-circle"></i></a>';

$l['parameter_db_engine_title'] = 'Database Engine';
$l['parameter_db_host_title'] = 'Database Hostname';
$l['parameter_db_user_title'] = 'Database Username';
$l['parameter_db_password_title'] = 'Database Password';
$l['parameter_db_name_title'] = 'Database Name';
$l['parameter_db_path_title'] = 'Database Path';
$l['parameter_db_table_prefix_title'] = 'Table Prefix';

$l['parameter_bbname_title'] = 'Board Name';
$l['parameter_bbname_value'] = 'Forums';
$l['parameter_bburl_title'] = 'Board URL';
$l['parameter_adminemail_title'] = 'Administrative Email Address';
$l['parameter_adminemail_description'] = 'Used for outgoing emails and the contact form.';
$l['parameter_cookiedomain_title'] = 'Cookie Domain';
$l['parameter_cookiepath_title'] = 'Cookie Path';
$l['parameter_acp_pin_title'] = 'Admin Control Panel PIN';
$l['parameter_acp_pin_description'] = 'An optional code required to enter the ACP, in addition to individual account passwords.';

$l['parameter_account_username_title'] = 'Username';
$l['parameter_account_email_title'] = 'Email Address';
$l['parameter_account_password_title'] = 'Password';

// parameter notes
$l['deferred_default_parameter_note_db_host'] = 'Discovered running server';
$l['deferred_default_parameter_note_db_user'] = 'Discovered accepted credentials';
$l['deferred_default_parameter_note_db_name'] = 'Discovered accessible database';

// parameter feedback
$l['parameter_feedback_settings_bburl_loopback'] = 'Loopback address may be unavailable on other devices';

$l['parameter_feedback_database_check_server_success'] = 'Able to connect';
$l['parameter_feedback_database_check_server_error'] = 'Cannot connect';
$l['parameter_feedback_database_check_authentication_success'] = 'Able to authenticate';
$l['parameter_feedback_database_check_authentication_error'] = 'Not able to authenticate';
$l['parameter_feedback_database_check_database_success'] = 'Able to use database';
$l['parameter_feedback_database_check_database_error'] = 'Not able to use database';
$l['parameter_feedback_database_check_prefix_tables_success'] = 'No existing tables with this prefix';
$l['parameter_feedback_database_check_prefix_tables_warning'] = '{1} existing table(s) with this prefix to overwrite';

// operations content
$l['version_to_be_installed'] = 'Version to be Installed';

$l['php_version_incompatible'] = 'PHP {1} or newer required (currently on version {2})';
$l['no_multi_byte_extensions'] = 'No PHP extensions for multi-byte support available (<code>mbstring</code>, <code>iconv</code>)';
$l['no_database_drivers'] = 'No available database engine drivers (MySQL, PostgreSQL, SQLite)';
$l['no_xml_support'] = 'No PHP extension for XML support available (<code>xml</code>)';
$l['directory_not_writable'] = 'The <code>{1}</code> directory is not writable. Please <a href="https://docs.mybb.com/1.8/administration/security/file-permissions" target="_blank" rel="noreferrer">adjust permissions</a> to allow it to be written to.';
$l['file_not_writable'] = 'The <code>{1}</code> file is not writable. Please <a href="https://docs.mybb.com/1.8/administration/security/file-permissions" target="_blank" rel="noreferrer">adjust permissions</a> to allow it to be written to.';
$l['lock_file_not_writable'] = 'Could not create the lock file <code>{1}</code>. Please remove the install directory before proceeding to your forum.';

$l['file_verification_checksums_missing'] = 'Could not verify files because the <code>{1}</code> file is missing. This file may not be included in development-only versions.';
$l['file_verification_failed'] = 'The following files ({1}) may be corrupted. You should copy them again from the <a href="https://mybb.com/download" target="_blank">original package</a> before proceeding.';
$l['file_verification_changed'] = 'Modified: {1}';
$l['file_verification_missing'] = 'Missing: {1}';

$l['could_not_write_configuration_file'] = 'Could not write data to the configuration file.';
$l['could_not_connect_to_database'] = 'Could not connect to the database using saved parameters.';
$l['database_parameter_check_failed'] = 'Could not connect to the database using provided parameters.';
$l['configuration_file_not_installed'] = 'No installed configuration file found.';
#endregion

#region Process Model: upgrade
// general
$l['upgrade_page_title'] = 'MyBB Upgrade';
$l['upgrade_header_title'] = 'Upgrade';

// steps
$l['upgrade_step_start_title'] = 'Start';
$l['upgrade_step_migration_title'] = 'Migration';
$l['upgrade_step_rebuilding_title'] = 'Rebuilding';

// step pages
$l['upgrade_step_start_heading'] = 'Upgrade Board Software';
$l['upgrade_step_start_description'] = 'Welcome to the upgrade of MyBB. This process will update your board to use the new software version.<br><br>We strongly recommend creating a backup of your database and files and verifying it before the upgrade.';
$l['upgrade_step_start_description_link'] = 'Welcome to the upgrade of MyBB. This process will update your board to use the new software version (<a href="./index.php?process=install"><strong>re-install instead</strong></a>?).<br><br>We strongly recommend creating a backup of your database and files and verifying it before the upgrade.';

$l['upgrade_step_migration_heading'] = 'Migrate Data';
$l['upgrade_step_migration_description'] = 'Applicable upgrade scripts will now be executed.';

$l['upgrade_step_rebuilding_heading'] = 'Rebuild Information';
$l['upgrade_step_rebuilding_description'] = 'Common information and caches will be re-generated.';

// operations
$l['operation_upgrade_plan_title'] = 'Upgrade Planning';
$l['operation_update_settings_title'] = 'Settings Rebuilding';

// parameters
$l['parameter_upgrade_start_title'] = 'Starting Version Number';
$l['parameter_upgrade_start_description'] = 'The selected upgrade, and all upgrades with higher versions, will be applied.';

// operations content
$l['upgrade_version_to_be_installed'] = 'Version After Upgrade';
$l['upgrades_to_apply'] = 'Upgrades to Apply';
$l['upgrade_to_apply'] = '#{1} (from version {2})';
#endregion
