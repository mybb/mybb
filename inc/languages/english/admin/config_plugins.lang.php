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
$l['active_plugin'] = "Active Plugins";
$l['inactive_plugin'] = "Inactive Plugins";
$l['staged_plugin'] = "Staged Plugins";
$l['your_version'] = "Your Version";
$l['latest_version'] = "Latest Version";
$l['download'] = "Download";
$l['deactivate'] = "Deactivate";
$l['activate'] = "Activate";
$l['install_and_activate'] = "Install &amp; Activate";
$l['integrate_install_and_activate'] = "Integrate, Install &amp; Activate";
$l['upgrade_plugin'] = "Upgrade to this version";
$l['upgrade_install_activate_plugin'] = "Upgrade to this version, Install &amp; Activate";
$l['uninstall'] = "Uninstall";
$l['created_by'] = "Created by";
$l['no_plugins'] = "There are no plugins on your forum at this time.";
$l['no_active_plugins'] = "There are no active plugins on your forum.";
$l['no_inactive_plugins'] = "There are no inactive plugins available.";

$l['upload_plugin'] = 'Upload a plugin to install/upgrade: ';
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
$l['error_bad_staged_plugin_file'] = "The staged plugin file '{1}' either does not exist or is not readable.";
$l['error_bad_staged_json_file'] = "Failed to parse the staged plugin JSON file '{1}'.";
$l['error_pl_json_both_key_and_raw'] = "Both {1} and {2} are specified in the JSON file for the staged plugin {3}.";
$l['error_staged_plugin_less_or_equal_vers_int'] = 'The integrated version of this plugin is greater than or equal to that of this staged version. To anyway install this staged version of the plugin, please first delete those of its files already integrated into the filesystem, in particular its `inc/plugins/{1}.php` file.';
$l['error_staged_plugin_less_or_equal_vers_ins'] = 'The installed version of this plugin is greater than or equal to that of this staged version. To anyway install this staged version of the plugin, please first uninstall the current version (via the link in the above panel), then delete those of its files already integrated into the filesystem, in particular its `inc/plugins/{1}.php` file.';
$l['error_staged_plugin_themelet_uses_curr'] = 'The staged plugin\'s themelet contains a `current` directory. This is disallowed. It must instead use a `devdist` directory.';

$l['success_plugins_up_to_date'] = "Congratulations, all of your plugins are up to date.";
$l['success_plugin_activated'] = "The selected plugin has been activated successfully.";
$l['success_plugin_upgraded_install_activated'] = 'The selected plugin has been upgraded, installed, and activated successfully.';
$l['success_plugin_deactivated'] = "The selected plugin has been deactivated successfully.";
$l['success_plugin_installed'] = "The selected plugin has been installed and activated successfully.";
$l['success_plugin_upgraded'] = 'The selected plugin has been upgraded successfully.';
$l['success_plugin_uninstalled'] = "The selected plugin has been uninstalled successfully.";
