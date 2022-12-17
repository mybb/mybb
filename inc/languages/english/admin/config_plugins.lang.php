<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */

$l['plugins'] = "Plugins";
$l['plugins_desc'] = "This section allows you to activate, deactivate, and manage the plugins that you have uploaded to your forum's <strong>inc/plugins</strong> directory. To hide a plugin from view, but not lose any stored information from it, click the Deactivate link.";
$l['plugin_updates'] = "Plugin Updates";
$l['plugin_updates_desc'] = "This section allows you to check for updates on all your plugins.";
$l['browse_plugins'] = "Browse Plugins";
$l['browse_plugins_desc'] = "Here you may browse the official MyBB modifications site for plugins compatible with your series of MyBB.";
$l['browse_all_plugins'] = "Browse All Plugins";

$l['plugin'] = "Plugin";
$l['upload_plugin'] = 'Upload Plugin';
$l['active_plugin'] = "Active Plugins";
$l['inactive_plugin'] = "Inactive Plugins";
$l['your_version'] = "Your Version";
$l['latest_version'] = "Latest Version";
$l['download'] = "Download";
$l['deactivate'] = "Deactivate";
$l['activate'] = "Activate";
$l['install_and_activate'] = "Install &amp; Activate";
$l['upgrade_plugin'] = "Upgrade to this version";
$l['upgrade_install_activate_plugin'] = "Upgrade to this version, Install &amp; Activate";
$l['uninstall'] = "Uninstall";
$l['created_by'] = "Created by";
$l['no_plugins'] = "There are no plugins on your forum at this time.";
$l['no_active_plugins'] = "There are no active plugins on your forum.";
$l['no_inactive_plugins'] = "There are no inactive plugins available.";

$l['upload_plugin_desc'] = 'Upload a plugin archive (a file ending in .zip) to install or to which to upgrade';
$l['plugin_ignore_vers'] = 'Allow downgrades/reinstalls when plugin already exists';
$l['install_uploaded_plugin'] = 'Upload and install/upgrade';

$l['plugin_incompatible'] = "This plugin is incompatible with MyBB {1}";

$l['recommended_plugins_for_mybb'] = "Recommended Plugins for MyBB {1}";
$l['browse_results_for_mybb'] = "Browse Results for MyBB {1}";
$l['search_for_plugins'] = "Search for Plugins";
$l['search'] = "Search";

$l['error_vcheck_no_supported_plugins'] = "None of the plugins installed support version checking.";
$l['error_vcheck_communications_problem'] = "There was a problem communicating with the MyBB modifications version server. Please try again in a few minutes.";
$l['error_vcheck_vulnerable'] = "[Vulnerable plugin]:";
$l['error_vcheck_vulnerable_notes'] = "This submission has currently been marked as vulnerable by the MyBB Staff. We recommend complete removal of this modification. Please see the notes below: ";
$l['error_no_input'] = "Error code 1: No input specified.";
$l['error_no_pids'] = "Error code 2: No plugin ids specified.";
$l['error_communication_problem'] = "There was a problem communicating with the MyBB modifications server. Please try again in a few minutes.";
$l['error_invalid_plugin'] = "The selected plugin does not exist.";
$l['error_no_results_found'] = "No results were found for the specified keywords.";
$l['error_pl_json_both_key_and_raw'] = "Both {1} and {2} are specified in the JSON file for the plugin {3}.";
$l['error_no_plugin_uploaded'] = 'No uploaded plugin file was received.';
$l['error_no_ziparchive_for_plugin'] = 'The ZipArchive class was not found. This class is necessary to automatically unzip plugins. If you are unable to install the PHP package providing this class, then instead simply unzip your plugin manually into the `inc/plugins/` directory and refresh this page. It should then show up, allowing you to install/upgrade it.';
$l['error_plugin_unzip_open_failed'] = 'Unable to open the uploaded zip file. ZipArchive::open() returned code: {1}.';
$l['error_plugin_unzip_tmpdir_failed'] = 'Unable to create a temporary directory into which to unzip the plugin file.';
$l['error_plugin_unzip_multi_or_none_root'] = 'Invalid plugin archive: either no or multiple file/directory entries found in root. Expected one plugin directory entry.';
$l['error_plugin_move_fail'] = 'Failed to move the unzipped plugin from its temporary directory to `inc/plugins`. Error message is: {1}.';
$l['error_move_uploaded_plugin_failed'] = 'Failed to safely move the uploaded plugin file to a temporary directory.';
$l['error_plugin_unzip_failed'] = 'Failed to unzip the plugin archive.';
$l['error_plugin_uploaded_less_or_equal_vers'] = 'The integrated/installed version of this plugin is greater than or equal to the version which you uploaded. Aborting. To force a downgrade/reinstall, please check the "Allow downgrades/reinstalls when plugin already exists" checkbox.';
$l['error_missing_manifest_version'] = 'The `version` property is missing in the JSON file "{1}".';
$l['error_mv_failed'] = 'Failed to move "{1}" to "{2}".';

$l['success_plugins_up_to_date'] = "Congratulations, all of your plugins are up to date.";
$l['success_plugin_activated'] = "The selected plugin has been activated successfully.";
$l['success_plugin_upgraded_install_activated'] = 'The selected plugin has been upgraded, installed, and activated successfully.';
$l['success_plugin_deactivated'] = "The selected plugin has been deactivated successfully.";
$l['success_plugin_installed'] = "The selected plugin has been installed and activated successfully.";
$l['success_plugin_updated'] = 'The selected plugin has been updated successfully.';
$l['success_plugin_uninstalled'] = "The selected plugin has been uninstalled successfully.";
