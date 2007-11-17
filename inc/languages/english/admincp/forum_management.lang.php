<?php
/**
 * MyBB 1.2 English Language Pack
 * Copyright © 2007 MyBB Group, All Rights Reserved
 * 
 * $Id: user_users.lang.php 3373 2007-10-06 17:35:33Z Tikitiki $
 */

$l['forum_management'] = "Forum Management";
$l['forum_management_desc'] = "This section allows you to manage the categories and forums on your board. You can manage forum permissions and forum-specific moderators as well. If you change the display order for one or more forums or categories, make sure you submit the form at the bottom of the page.";
$l['add_forum'] = "Add Forum";
$l['add_forum_desc'] = "Here you can add a new forum or category to your board. You may also set initial permissions for this forum.";
$l['copy_forum'] = "Copy Forum";
$l['copy_forum_desc'] = "";
$l['forum_permissions'] = "Permissions";
$l['forum_permissions_desc'] = "Here you can modify the full permissions for an individual group for a single forum";
$l['view_forum'] = "View Forum";
$l['view_forum_desc'] = "Here you can view sub forums, quickly edit permissions and add moderators to your forum.";
$l['add_child_forum'] = "Add Child Forum";
$l['edit_forum_settings'] = "Edit Forum Settings";
$l['edit_forum'] = "Edit Forum";
$l['edit_mod'] = "Edit Moderator";
$l['edit_mod_desc'] = "Here you can modify a particular moderator's settings.";

$l['manage_forums'] = "Manage Forums";
$l['forum'] = "Forum";
$l['order'] = "Order";

$l['edit_forum'] = "Edit Forum";
$l['subforums'] = "Sub Forums";
$l['moderators'] = "Moderators";
$l['permissions'] = "Permissions";
$l['add_child_forum'] = "Add Child Forum";
$l['copy_forum'] = "Copy Forum";
$l['delete_forum'] = "Delete Forum";

$l['sub_forums'] = "Sub Forums";
$l['update_forum_orders'] = "Update Forum Orders";
$l['update_forum_permissions'] = "Update Forum Permissions";
$l['reset'] = "Reset";
$l['in_forums'] = "Forums in \"{1}\"";
$l['forum_permissions_in'] = "Forum Permissions in \"{1}\"";
$l['moderators_assigned_to'] = "Moderators Assigned to \"{1}\"";
$l['add_moderator'] = "Add Moderator >>";
$l['quick_permissions'] = "Quick Permissions >>";
$l['edit_permissions'] = "Edit Permissions";
$l['set_permissions'] = "Set Permissions";
$l['using_custom_perms'] = "Using Custom Permissions";
$l['using_default_perms'] = "Using Default Permissions";

$l['permissions_use_group_default'] = "Use Group Default";
$l['permissions_group'] = "Group";
$l['permissions_canview'] = "Can View";
$l['permissions_canpostthreads'] = "Can Post Threads";
$l['permissions_canpostreplys'] = "Can Post Replies";
$l['permissions_canpostpolls'] = "Can Post Polls";
$l['permissions_canuploadattachments'] = "Can Upload Attachments";
$l['permissions_all'] = "All?";

$l['moderator_permissions'] = "Moderator Permissions";
$l['forum_desc'] = "Forum the moderator manages.";
$l['edit_mod_for'] = "Edit moderator options for \"{1}\"";
$l['can_edit_posts'] = "Can edit posts";
$l['can_delete_posts'] = "Can delete posts";
$l['can_view_ips'] = "Can view IPs";
$l['can_open_close_threads'] = "Can open/close threads";
$l['can_manage_threads'] = "Can manage threads (split, move, copy, merge)";
$l['can_move_to_other_forums'] = "Can move threads to another forum this user doesn't moderate";
$l['save_mod'] = "Save Moderator";

$l['no_forums'] = "There are no forums found.";
$l['no_moderators'] = "There are no moderators found.";

$l['success_forum_disporder_updated'] = "Successfully updated the forum display order.";
$l['success_forum_deleted'] = "Successfully delete the specified forum.";
$l['success_moderator_deleted'] = "Successfully delete the specified moderator.";
$l['success_forum_permissions_updated'] = "Successfully updated the forum permissions.";
$l['success_forum_updated'] = "Successfully updated the forum.";
$l['success_moderator_updated'] = "Successfully updated the moderator.";

$l['error_invalid_forum'] = "Please input a valid forum to delete.";
$l['error_invalid_moderator'] = "Please input a valid moderator to delete.";
$l['error_invalid_fid'] = "Invalid Forum ID selected.";
$l['error_forum_parent_child'] = "You can't set the parent forum to one of the forums own children.";
$l['error_forum_parent_itself'] = "The forum parent cannot be the forum itself.";
$l['error_incorrect_moderator'] = "Please input a valid moderator.";

$l['confirm_moderator_deletion'] = "Are you sure you wish to remove this moderator from this forum?";
$l['confirm_forum_deletion'] = "Are you sure you wish to delete this forum?";

$l['create_a'] = "Create a";
$l['create_a_desc'] = "Select the type of forum you are creating - a forum you can post in, or a category, which contains other forums.";
$l['forum'] = "Forum";
$l['category'] = "Category";
$l['title'] = "Title";
$l['description'] = "Description";
$l['save_forum'] = "Save Forum";
$l['parent_forum'] = "Parent Forum";
$l['parent_forum_desc'] = "The Forum that contains this forum. Categories do not have a parent forum - in this case, select 'None' - however, categories can be specified to have a parent forum.";
$l['none'] = "None";
$l['display_order'] = "Display Order";

$l['additional_forum_options'] = "Additional Forum Options";
$l['forum_link'] = "Forum Link";
$l['forum_link_desc'] = "To make a forum redirect to another location, enter the URL to the destination you wish to redirect to. Entering a URL in this field will remove the forum functionality; however, permissions can still be set for it.";
$l['forum_password'] = "Forum Password";
$l['forum_password_desc'] = "To protect this forum further, you can choose a password that must be entered for access. Note: User groups still need permissions to access this forum.";
$l['access_options'] = "Access Options";
$l['forum_is_active'] = "Forum is Active";
$l['forum_is_active_desc'] = "If unselected, this forum will not be shown to users and will not \"exist\".";
$l['forum_is_open'] = "Forum is Open";
$l['forum_is_open_desc'] = "If unselected, users will not be able to post in this forum regardless of permissions.";

$l['moderation_options'] = "Moderation Options";
$l['mod_new_posts'] = "Moderate New Posts";
$l['mod_new_threads'] = "Moderate New Threads";
$l['mod_new_attachments'] = "Moderate New Attachments";
$l['mod_after_edit'] = "Moderate After Edit";
$l['override_user_style'] = "Override User's Selected Style";
$l['style_options'] = "Style Options";
$l['forum_specific_style'] = "Forum-Specific Style:";
$l['use_default'] = "Use Default";
$l['dont_display_rules'] = "Don't Display Rules";
$l['display_rules_inline'] = "Display Rules Inline";
$l['display_rules_link'] = "Display Rules Link";
$l['display_method'] = "Display Method:";
$l['title'] = "Title";
$l['rules'] = "Rules:";
$l['forum_rules'] = "Forum Rules";
$l['username'] = "Username";
$l['moderator_username_desc'] = "Username of the moderator to be added";
$l['add_moderator'] = "Add Moderator";

$l['default_view_options'] = "Default View Options";
$l['default_date_cut'] = "Default Date Cut:";
$l['default_sort_by'] = "Default Sort By:";
$l['default_sort_order'] = "Default Sort Order:";

$l['board_default'] = "Board Default";

$l['datelimit_1day'] = "Last day";
$l['datelimit_5days'] = "Last 5 days";
$l['datelimit_10days'] = "Last 10 days";
$l['datelimit_20days'] = "Last 20 days";
$l['datelimit_50days'] = "Last 50 days";
$l['datelimit_75days'] = "Last 75 days";
$l['datelimit_100days'] = "Last 100 days";
$l['datelimit_lastyear'] = "Last year";
$l['datelimit_beginning'] = "The beginning";

$l['sort_by_subject'] = "Thread subject";
$l['sort_by_lastpost'] = "Last post time";
$l['sort_by_starter'] = "Thread starter";
$l['sort_by_started'] = "Thread creation time";
$l['sort_by_rating'] = "Thread rating";
$l['sort_by_replies'] = "Number of replies";
$l['sort_by_views'] = "Number of views";

$l['sort_order_asc'] = "Ascending";
$l['sort_order_desc'] = "Descending";

$l['misc_options'] = "Miscellaneous Options";
$l['allow_html'] = "Allow HTML";
$l['allow_mycode'] = "Allow MyCode";
$l['allow_smilies'] = "Allow Smilies";
$l['allow_img_code'] = "Allow [img] Code";
$l['allow_post_icons'] = "Allow Post Icons";
$l['allow_thread_ratings'] = "Allow Thread Ratings";
$l['show_forum_jump'] = "Show in Forum Jump";
$l['use_postcounts'] = "Make posts in this forum count towards user post counts";

$l['use_permissions'] = "Use Permissions";
$l['use_permissions_desc'] = "Select the permissions you would like to use for this user group - inheritied permissions (will delete custom permissions) or custom permissions.";
$l['inherit_permissions'] = "Use user group permissions or inherit from parent forums";
$l['custom_permissions'] = "Use custom permissions (below)";

$l['save_permissions'] = "Save Forum Permissions";

$l['error_missing_title'] = "You must enter in a title.";
$l['error_no_parent'] = "You must select a parent forum.";

$l['success_forum_added'] = "Successfully added the forum.";
$l['success_moderator_added'] = "The moderator has sucessfully been added to the forum.";
$l['success_forum_permissions_saved'] = "Succesffully saved the forum permissions.";

$l['error_moderator_already_added'] = "The specified moderator is already a moderator of the forum.";
$l['error_moderator_not_found'] = "The specified username was not found.";

$l['group_viewing'] = "Viewing";
$l['group_posting_rating'] = "Posting / Rating";
$l['group_editing'] = "Editing";
$l['group_polls'] = "Polls";
$l['group_misc'] = "Miscellaneous";

$l['viewing_field_canview'] = "Can view forum";
$l['viewing_field_canviewthreads'] = "Can view threads within forum";
$l['viewing_field_candlattachments'] = "Can download attachments";

$l['posting_rating_field_canpostthreads'] = "Can post threads";
$l['posting_rating_field_canpostreplys'] = "Can post replies";
$l['posting_rating_field_canpostattachments'] = "Can post attachments";
$l['posting_rating_field_canratethreads'] = "Can rate threads";

$l['editing_field_caneditposts'] = "Can edit own posts";
$l['editing_field_candeleteposts'] = "Can delete own posts";
$l['editing_field_candeletethreads'] = "Can delete own threads";
$l['editing_field_caneditattachments'] = "Can edit own attachments";

$l['polls_field_canpostpolls'] = "Can post polls";
$l['polls_field_canvotepolls'] = "Can vote in polls";

$l['misc_field_cansearch'] = "Can search forum";

?>