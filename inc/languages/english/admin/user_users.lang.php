<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */

$l['users'] = "Users";

$l['search_for_user'] = "Search for a User";
$l['browse_users'] = "Browse Users";
$l['browse_users_desc'] = "Below you can browse users of your forums in different defined views. Views are particularly useful for generating different result sets with different information - think of them as saved live searches.";
$l['find_users'] = "Find Users";
$l['find_users_desc'] = "Here you can search for users of your forum. The fewer fields you fill in, the broader your search is; the more you fill in, the narrower your search is.";
$l['create_user'] = "Create New User";
$l['create_user_desc'] = "Here you can create a new user.";
$l['merge_users'] = "Merge Users";
$l['merge_users_desc'] = "Here you can merge two user accounts in to one. The \"Source Account\" will  be merged in to the \"Destination Account\" leaving <strong>only</strong> the destination account. The source accounts posts, threads, private messages, calendar events, post count and buddy list will be merged in to the destination account.<br /><span style=\"font-size: 15px;\">Please be aware that this process cannot be undone.</span>";
$l['edit_user'] = "Edit User";
$l['edit_user_desc'] = "Here you can edit this users profile, settings, and signature; see general statistics; and visit other pages for further information relating to this user.";
$l['show_referrers'] = "Show Referrers";
$l['show_referrers_desc'] = "The results to your search criteria are shown below. You can view the results in either a table view or business card view.";
$l['show_ip_addresses'] = "Show IP Addresses";
$l['show_ip_addresses_desc'] = "The registration IP address and the post IPs for the selected users are shown below. The first IP address is the registration IP (it is marked as such). Any other IP addresses are IP addresses the user has posted with.";
$l['manage_users'] = "Manage Users";
$l['manage_users_desc'] = "Mass-managing users makes it a lot easier to do common tasks.";
$l['inline_edit'] = "Inline User Moderation:";
$l['inline_activate'] = "Activate User(s)";
$l['inline_ban'] = "Ban User(s)";
$l['inline_usergroup'] = "Change Users' Usergroup";
$l['inline_delete'] = "Delete User(s)";
$l['inline_prune'] = "Prune/Delete Users' Posts";
$l['inline_activated'] = "{1} user(s) were successfully activated.";
$l['inline_activated_more'] = "<small>{1} user(s) you selected were already activated.</small>";
$l['inline_activated_failed'] = "All the users you selected were already activated.";
$l['ban_time'] = "Ban Length <em>*</em>";
$l['ban_reason'] = "Ban Reason";
$l['mass_ban'] = "Mass Ban Users";
$l['important'] = "Important";
$l['mass_ban_info'] = "This action will affect {1} user(s). Only continue if you are sure you want to do this.";
$l['ban_users'] = "Ban Users";
$l['users_banned'] = "{1} user(s) have been banned.";
$l['confirm_multilift'] = "Are you sure you want to lift bans for the user(s) you selected?";
$l['success_ban_lifted'] = "Bans for {1} user(s) you selected have been lifted.";
$l['edit_ban'] = "Edit Ban";
$l['lift_ban'] = "Lift Ban";
$l['lift_bans'] = "Lift Bans";
$l['confirm_multidelete'] = "Are you sure you want to delete these {1} user(s)? This cannot be undone.";
$l['users_deleted'] = "{1} user(s) have been deleted.";
$l['mass_prune_info'] = "This action will affect {1} user(s). If you continue, it will remove all the users' posts older than the date you enter below.<br /><br /><strong>Please note that if any users' post is the first post of a thread, the entire thread will be removed.</strong>";
$l['mass_prune_posts'] = "Mass Prune Posts";
$l['manual_date'] = "Enter a manual date";
$l['relative_date'] = "Or select a delete option";
$l['multi_selected_dates'] = "You've selected both a manual date and a set option. Please select either a manual date or a set option.";
$l['incorrect_date'] = "The date you entered is invalid. Please enter a valid date, or leave blank and select a set option.";
$l['prune_complete'] = "Prune completed successfully.";
$l['prune_fail'] = "No posts were found for the selected user(s). No posts were pruned.";
$l['no_prune_option'] = "Please enter a date or select an option to continue.";
$l['prune_posts'] = "Prune Posts";
$l['delete_posts'] = "Delete Posts";
$l['usergroup_info'] = "The following action will affect {1} user(s). By choosing the options below, you will be overwriting the selected users' primary / additional / display usergroup.";
$l['mass_usergroups'] = "Mass Usergroup Change";
$l['success_mass_usergroups'] = "User(s) updated successfully.";
$l['alter_usergroups'] = "Save Changes";
$l['no_usergroup_changed'] = "None of the user(s) you selected can have their usergroups changed.";
$l['no_set_option'] = "A valid set date was not selected. Please select an option from the dropdown box or enter a manual date.";
$l['select_an_option'] = "(Select an Option)";

$l['month_1'] = "January";
$l['month_2'] = "February";
$l['month_3'] = "March";
$l['month_4'] = "April";
$l['month_5'] = "May";
$l['month_6'] = "June";
$l['month_7'] = "July";
$l['month_8'] = "August";
$l['month_9'] = "September";
$l['month_10'] = "October";
$l['month_11'] = "November";
$l['month_12'] = "December";

$l['option_1'] = "More than a month old";
$l['option_2'] = "More than 3 months old";
$l['option_3'] = "More than 6 months old";
$l['option_4'] = "More than a year old";
$l['option_5'] = "More than 18 months old";
$l['option_6'] = "More than 2 years old";

$l['error_avatartoobig'] = "Sorry, but we cannot change your avatar as the new avatar you specified is too big. The maximum dimensions are {1}x{2} (width x height)";
$l['error_invalidavatarurl'] = "The URL you entered for your avatar does not appear to be valid. Please ensure you enter a valid URL.";
$l['error_invalid_user'] = "You have selected an invalid user.";
$l['error_no_perms_super_admin'] = "You do not have permission to edit this user because you are not a super administrator.";
$l['error_invalid_user_source'] = "The source account username you entered does not exist";
$l['error_invalid_user_destination'] = "The destination account username you entered does not exist";
$l['error_cannot_merge_same_account'] = "The source and destination accounts must be different";
$l['error_no_users_found'] = "No users were found matching the specified search criteria. Please modify your search criteria and try again.";
$l['error_invalid_admin_view'] = "You selected an invalid administration view.";
$l['error_missing_view_title'] = "You did not enter a title for this view.";
$l['error_no_view_fields'] = "You did not select any fields to display on this view";
$l['error_invalid_view_perpage'] = "You have entered an invalid number of results to show per page";
$l['error_invalid_view_sortby'] = "You have selected an invalid field to sort results by";
$l['error_invalid_view_sortorder'] = "You have selected an invalid sort order";
$l['error_invalid_view_delete'] = "You selected an invalid administration view to delete";
$l['error_cannot_delete_view'] = "You must have at least 1 administration view.";
$l['error_inline_no_users_selected'] = "Sorry, but you did not select any users. Please select some users and try again.";
$l['error_cannot_delete_user'] = "This user cannot be deleted.";
$l['error_no_referred_users'] = "The selected user does not have any referred users.";

$l['user_deletion_confirmation'] = "Are you sure you wish to delete this user?";

$l['success_coppa_activated'] = "The selected COPPA user has been activated successfully.";
$l['success_activated'] = "The selected user has been activated successfully.";
$l['success_user_created'] = "The user has been created successfully.";
$l['success_user_updated'] = "The selected user has been updated successfully.";
$l['success_user_deleted'] = "The selected user has been deleted successfully.";
$l['success_merged'] = "has successfully been merged in to";
$l['succuss_view_set_as_default'] = "The selected administration view has been set as your default successfully";
$l['success_view_created'] = "The administration view has been created successfully.";
$l['success_view_updated'] = "The selected administration view has been updated successfully.";
$l['success_view_deleted'] = "The selected administration view has been deleted successfully.";

$l['confirm_view_deletion'] = "Are you sure you want to delete the selected view?";

$l['warning_coppa_user'] = "<p class=\"alert\"><strong>Warning: </strong> This user is awaiting COPPA validation. <a href=\"index.php?module=user-users&amp;action=activate_user&amp;uid={1}\">Activate Account</a></p>";

$l['required_profile_info'] = "Required Profile Information";
$l['password'] = "Password";
$l['confirm_password'] = "Confirm Password";
$l['email_address'] = "Email Address";
$l['use_primary_user_group'] = "Use Primary User Group";
$l['primary_user_group'] = "Primary User Group";
$l['additional_user_groups'] = "Additional User Groups";
$l['additional_user_groups_desc'] = "Use CTRL to select multiple groups";
$l['display_user_group'] = "Display User Group";
$l['save_user'] = "Save User";

$l['overview'] = "Overview";
$l['profile'] = "Profile";
$l['account_settings'] = "Account Settings";
$l['signature'] = "Signature";
$l['avatar'] = "Avatar";
$l['mod_options'] = "Moderator Options";
$l['general_account_stats'] = "General Account Statistics";
$l['local_time'] = "Local Time";
$l['local_time_format'] = "{1} at {2}";
$l['posts'] = "Posts";
$l['age'] = "Age";
$l['posts_per_day'] = "Posts per day";
$l['percent_of_total_posts'] = "Percent of total posts";
$l['user_overview'] = "User Overview";

$l['new_password'] = "New Password";
$l['new_password_desc'] = "Only required if changing";
$l['confirm_new_password'] = "Confirm New Password";

$l['optional_profile_info'] = "Optional Profile Information";
$l['custom_user_title'] = "Custom User Title";
$l['custom_user_title_desc'] = "If empty, the group user title will be used";
$l['website'] = "Website";
$l['icq_number'] = "ICQ Number";
$l['aim_handle'] = "AIM Handle";
$l['yahoo_messanger_handle'] = "Yahoo! Messenger Handle";
$l['skype_handle'] = "Skype Handle";
$l['google_handle'] = "Google Talk Handle";
$l['birthday'] = "Date of Birth";

$l['away_information'] = "Away Information";
$l['away_status'] = "Away Status:";
$l['away_status_desc'] = "Allows you to leave an away message if you are going away for a while.";
$l['im_away'] = "I'm Away";
$l['im_here'] = "I'm Here";
$l['away_reason'] = "Away Reason:";
$l['away_reason_desc'] = "Allows you to enter a small description of why you are away  (max 200 characters).";
$l['return_date'] = "Return Date:";
$l['return_date_desc'] = "If you know when you will be back, you can enter your return date here.";
$l['error_acp_return_date_past'] = "You cannot return in the past!";

$l['hide_from_whos_online'] = "Hide from the Who's Online list";
$l['login_cookies_privacy'] = "Login, Cookies &amp; Privacy";
$l['recieve_admin_emails'] = "Receive emails from administrators";
$l['hide_email_from_others'] = "Hide email address from other members";
$l['recieve_pms_from_others'] = "Receive private messages from other users";
$l['recieve_pms_from_buddy'] = "Only receive private messages from buddy list";
$l['alert_new_pms'] = "Alert with notice when new private message is received";
$l['email_notify_new_pms'] = "Notify by email when new private message is received";
$l['default_thread_subscription_mode'] = "Default thread subscription mode";
$l['do_not_subscribe'] = "Do not subscribe";
$l['no_email_notification'] = "No email notification";
$l['instant_email_notification'] = "Instant email notification";
$l['messaging_and_notification'] = "Messaging &amp; Notification";
$l['use_default'] = "Use Default";
$l['date_format'] = "Date Format";
$l['time_format'] = "Time Format";
$l['time_zone'] = "Time Zone";
$l['daylight_savings_time_correction'] = "Daylight Saving Time correction";
$l['automatically_detect'] = "Automatically detect DST settings";
$l['always_use_dst_correction'] = "Always use DST correction";
$l['never_use_dst_correction'] = "Never use DST correction";
$l['date_and_time_options'] = "Date &amp; Time Options";
$l['show_threads_last_day'] = "Show threads from the last day";
$l['show_threads_last_5_days'] = "Show threads from the last 5 days";
$l['show_threads_last_10_days'] = "Show threads from the last 10 days";
$l['show_threads_last_20_days'] = "Show threads from the last 20 days";
$l['show_threads_last_50_days'] = "Show threads from the last 50 days";
$l['show_threads_last_75_days'] = "Show threads from the last 75 days";
$l['show_threads_last_100_days'] = "Show threads from the last 100 days";
$l['show_threads_last_year'] = "Show threads from the last year";
$l['show_all_threads'] = "Show all threads";
$l['threads_per_page'] = "Threads Per Page";
$l['default_thread_age_view'] = "Default Thread Age View";
$l['forum_display_options'] = "Forum Display Options";
$l['show_classic_postbit'] = "Display posts in classic mode";
$l['display_images'] = "Display images in posts";
$l['display_videos'] = "Display videos in posts";
$l['display_users_sigs'] = "Display users' signatures in their posts";
$l['display_users_avatars'] = "Display users' avatars in their posts";
$l['show_quick_reply'] = "Show the quick reply box at the bottom of the thread view";
$l['posts_per_page'] = "Posts Per Page";
$l['default_thread_view_mode'] = "Default Thread View Mode";
$l['linear_mode'] = "Linear Mode";
$l['threaded_mode'] = "Threaded Mode";
$l['thread_view_options'] = "Thread View Options";
$l['show_redirect'] = "Show friendly redirection pages";
$l['show_code_buttons'] = "Show MyCode formatting options on posting pages";
$l['source_editor'] = "Put the editor in source mode by default";
$l['theme'] = "Theme";
$l['board_language'] = "Board Language";
$l['other_options'] = "Other Options";
$l['signature_desc'] = "Formatting options: MyCode is {1}, smilies are {2}, IMG code is {3}, HTML is {4}";
$l['enable_sig_in_all_posts'] = "Enable signature in all posts";
$l['disable_sig_in_all_posts'] = "Disable signature in all posts";
$l['do_nothing'] = "Do not change signature preferences";
$l['signature_preferences'] = "Signature Preferences";
$l['suspend_sig'] = "Suspend Signature";
$l['suspend_sig_box'] = "Suspend this user's signature";
$l['suspend_sig_perm'] = "<small>Suspended permanently.</small>";
$l['suspend_sig_info'] = "If a signature is suspended, the user can't edit it and it won't be shown on their profile or in their posts";
$l['suspend_sig_extend'] = "<small>Enter a new time below to change, or untick this option to remove this suspension.</small>";
$l['suspend_expire_info'] = "<small>Remaining: <span style=\"color: {2};\">{1}</span></small>";
$l['suspend_never_expire'] = "<small>{1}'s suspension will never expire (permanently suspended).</small>";
$l['suspend_sig_error'] = "You entered an incorrect time to suspend this user's signature for. Please enter a correct time.";

$l['moderate_posts'] = "Moderate Posts";
$l['moderate_posts_info'] = "Moderate new posts made by {1}.";
$l['moderate_for'] = "Moderate for:";
$l['moderated_perm'] = "<p><small>Moderated permanently.<br />Enter a new time below to change or untick this option to remove this moderation.</small></p>";
$l['moderate_length'] = "<p><small>Remaining Moderation: <span style=\"color: {2};\">{1}</span>.<br />Enter a new time below to change or untick this option to remove this moderation.</small></p>";

$l['suspend_posts'] = "Suspend Posts";
$l['suspend_posts_info'] = "Suspend {1} from making new posts.";
$l['suspend_for'] = "Suspend for:";
$l['suspended_perm'] = "<p><small>Suspended permanently.<br />Enter a new time below to change or untick this option to remove this suspension.</small></p>";
$l['suspend_length'] = "<p><small>Remaining Suspension: <span style=\"color: {2};\">{1}</span>.<br />Enter a new time below to change or untick this option to remove this suspension.</small></p>";

$l['suspendsignature_error'] = "You selected to suspend this user's signature, but didn't enter a valid time period. Please enter a valid time to continue or untick the option to cancel.";
$l['moderateposting_error'] = "You selected to moderate this user's posts, but didn't enter a valid time period. Please enter a valid time to continue or untick the option to cancel.";
$l['suspendposting_error'] = "You selected to suspend this user's posts, but didn't enter a valid time period. Please enter a valid time to continue or untick the option to cancel.";
$l['suspendmoderate_error'] = "You've selected to suspend and moderate the user's posts. Please select only one type of moderation.";

$l['expire_length'] = "Suspension length:";
$l['expire_hours'] = "hour(s)";
$l['expire_days'] = "day(s)";
$l['expire_weeks'] = "week(s)";
$l['expire_months'] = "month(s)";
$l['expire_never'] = "Never";
$l['expire_permanent'] = "Permanent";

$l['username'] = "Username";
$l['email'] = "Email";
$l['primary_group'] = "Primary Group";
$l['additional_groups'] = "Additional Groups";
$l['registered'] = "Registered";
$l['last_active'] = "Last Active";
$l['post_count'] = "Post Count";
$l['thread_count'] = "Thread Count";
$l['reputation'] = "Reputation";
$l['warning_level'] = "Warning Level";
$l['registration_ip'] = "Registration IP";
$l['last_known_ip'] = "Last Known IP";
$l['registration_date'] = "Registration Date";
$l['info_on_ip'] = "Information on this IP address";

$l['current_avatar'] = "Current Avatar";
$l['user_current_using_uploaded_avatar'] = "This user is currently using an uploaded avatar.";
$l['user_currently_using_remote_avatar'] = "This user is currently using a remotely linked avatar.";
$l['max_dimensions_are'] = "The maximum dimensions for avatars are";
$l['avatar_max_size'] = "Avatars can be a maximum of";
$l['remove_avatar'] = "Remove current avatar?";
$l['avatar_desc'] = "Below you can manage the avatar for this user. Avatars are small identifying images which are placed under the authors username when they make a post.";
$l['avatar_auto_resize'] = "If the avatar is too large, it will automatically be resized";
$l['attempt_to_auto_resize'] = "Attempt to resize this avatar if it is too large?";
$l['specify_custom_avatar'] = "Specify Custom Avatar";
$l['upload_avatar'] = "Upload Avatar";
$l['or_specify_avatar_url'] = "or Specify Avatar/Gravatar URL";

$l['user_notes'] = "User Notes";

$l['ip_addresses'] = "IP Addresses";
$l['ip_address'] = "IP Address";
$l['show_users_regged_with_ip'] = "Show users who have registered with this IP";
$l['show_users_posted_with_ip'] = "Show users who have posted with this IP";
$l['ban_ip'] = "Ban IP";
$l['ip_address_for'] = "IP Addresses for";

$l['source_account'] = "Source Account";
$l['source_account_desc'] = "This is the account that will be merged in to the destination account. It will be removed after this process.";
$l['destination_account'] = "Destination Account";
$l['destination_account_desc'] = "This is the account that the source account will be merged in to. It will remain after this process.";
$l['merge_user_accounts'] = "Merge User Accounts";

$l['display_options'] = "Display Options";
$l['ascending'] = "Ascending";
$l['descending'] = "Descending";
$l['sort_results_by'] = "Sort results by";
$l['in'] = "in";
$l['results_per_page'] = "Results per page";
$l['display_results_as'] = "Display results as";
$l['business_card'] = "Business cards";
$l['views'] = "Views";
$l['views_desc'] = "The view manager allows you to create different kinds of views for this specific area. Different views are useful for generating a variety of reports.";
$l['manage_views'] = "Manage Views";
$l['none'] = "None";
$l['search'] = "Search";

$l['view_profile'] = "View Profile";
$l['edit_profile_and_settings'] = "Edit Profile &amp; Settings";
$l['ban_user'] = "Ban User";
$l['approve_coppa_user'] = "Activate COPPA User";
$l['approve_user'] = "Activate User";
$l['delete_user'] = "Delete User";
$l['show_referred_users'] = "Show Referred Users";
$l['show_attachments'] = "Show Attachments";
$l['table_view'] = "Table View";
$l['card_view'] = "Card View";

$l['find_users_where'] = "Find users where...";
$l['username_contains'] = "Username contains";
$l['email_address_contains'] = "Email address contains";
$l['is_member_of_groups'] = "Is member of one or more of these user groups";
$l['website_contains'] = "Website contains";
$l['icq_number_contains'] = "ICQ number contains";
$l['aim_handle_contains'] = "AIM handle contains";
$l['yahoo_contains'] = "Yahoo! Messenger handle contains";
$l['skype_contains'] = "Skype handle contains";
$l['google_contains'] = "Google Talk handle contains";
$l['signature_contains'] = "Signature contains";
$l['user_title_contains'] = "Custom user title contains";
$l['greater_than'] = "Greater than";
$l['is_exactly'] = "Is exactly";
$l['less_than'] = "Less than";
$l['post_count_is'] = "Post count is";
$l['thread_count_is'] = "Thread count is";
$l['reg_ip_matches'] = "Registration IP address matches";
$l['wildcard'] = "To search for ranges of IP addresses use * (Ex: 127.0.0.*) or CIDR notation (Ex: 127.0.0.0/8)";
$l['posted_with_ip'] = "Has posted with the IP address";
$l['custom_profile_fields_match'] = "Where custom profile fields match...";
$l['is_not_blank'] = " is not empty";
$l['or'] = "or";
$l['reg_in_x_days'] = "Registered in the last";
$l['days'] = "days";

$l['view'] = "View";
$l['create_new_view'] = "Create New View";
$l['create_new_view_desc'] = "Here you can define a new view for this area. You can define which fields you want to be shown, any search criteria and sorting options.";
$l['view_manager'] = "View Manager";
$l['set_as_default_view'] = "Set as Default View?";
$l['enabled'] = "Enabled";
$l['disabled'] = "Disabled";
$l['fields_to_show'] = "Fields to Show";
$l['fields_to_show_desc'] = "Please select the fields you wish to display";
$l['edit_view'] = "Edit View";
$l['edit_view_desc'] = "Whilst editing a view you can define which fields you want to be shown, any search criteria and sorting options.";
$l['private'] = "Private";
$l['private_desc'] = "This view is only visible to you";
$l['public'] = "Public";
$l['public_desc'] = "All other administrators can see this view";
$l['visibility'] = "Visibility";
$l['save_view'] = "Save View";
$l['created_by'] = "Created by";
$l['default'] = "Default";
$l['this_is_a_view'] = "This is a {1} view";
$l['set_as_default'] = "Set as Default";
$l['delete_view'] = "Delete View";
$l['default_view_desc'] = "Default view created by MyBB. Cannot be edited or removed.";
$l['public_view_desc'] = "Public view visible to all administrators.";
$l['private_view_desc'] = "Private view visible only to yourself.";
$l['table'] = "Table";
$l['title'] = "Title";

$l['view_title_1'] = "All Users";

$l['emailsubject_activateaccount'] = "Account Activation at {1}";
$l['email_adminactivateaccount'] = "{1},

The administrator has activated your forum account on {2}.

To proceed, please go to

{3}

You will be able to login with the credentials you registered with.

Thank you,
{2} Staff";

$l['ipaddress_misc_info'] = "Misc. Information for '{1}'";
$l['ipaddress_host_name'] = "Host Name";
$l['ipaddress_location'] = "GeoIP Location";
