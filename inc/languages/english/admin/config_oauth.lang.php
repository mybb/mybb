<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */

$l = [
	'admin_oauth_title' => 'oAuth Configuration',

	'admin_oauth_tabs_oauth_clients' => 'Clients',
	'admin_oauth_tabs_oauth_clients_description' => 'Manage your oAuth clients from here.',
	'admin_oauth_tabs_configure_client' => 'Configure Client',
	'admin_oauth_tabs_configure_client_description' => 'Configure your oAuth client here.',
	'admin_oauth_tabs_client_tokens' => 'Tokens',
	'admin_oauth_tabs_client_tokens_description' => 'Manage issued client tokens from here.',

	'admin_oauth_clients_table_title' => 'oAuth Clients',

	'admin_oauth_clients_table_head_provider' => 'Provider',
	'admin_oauth_clients_table_head_scope' => 'Scope',
	'admin_oauth_clients_table_head_enabled' => 'Enabled',
	'admin_oauth_clients_table_controls_configure' => 'Configure',

	'admin_oauth_clients_table_empty' => 'There are no providers to display.',

	'admin_oauth_configure_client_form_title' => 'Configure {1} Client',
	'admin_oauth_configure_client_form_client_id' => 'Client ID',
	'admin_oauth_configure_client_form_client_id_description' => 'The Client ID provided by the oAuth provider.',
	'admin_oauth_configure_client_form_client_secret' => 'Client Secret',
	'admin_oauth_configure_client_form_client_secret_description' => 'The Client Secret provided by the oAuth provider.',
	'admin_oauth_configure_client_form_oauth_scopes' => 'Scope',
	'admin_oauth_configure_client_form_oauth_scopes_description' => 'The oAuth scope to request during authorization, separated by spaces or commas.',
	'admin_oauth_configure_client_form_url_authorize' => 'Authorize URL',
	'admin_oauth_configure_client_form_url_authorize_description' => 'The URL used to authorize users.',
	'admin_oauth_configure_client_form_url_access_token' => 'Access Token URL',
	'admin_oauth_configure_client_form_url_access_token_description' => 'The URL used to obtain access tokens.',
	'admin_oauth_configure_client_form_url_owner_details' => 'Owner Details URL',
	'admin_oauth_configure_client_form_url_owner_details_description' => 'The URL used to obtain user details.',
	'admin_oauth_configure_client_form_store_token' => 'Store Access and Refresh Tokens',
	'admin_oauth_configure_client_form_store_token_description' => 'Whether to store the access token and refresh token in the database.',
	'admin_oauth_configure_client_form_button_update' => 'Update Configuration',

	'admin_client_tokens_table_title' => 'Client Tokens',

	'admin_client_tokens_table_head_provider' => 'Provider',
	'admin_client_tokens_table_head_user' => 'User',
	'admin_client_tokens_table_head_created_at' => 'Created At',
	'admin_client_tokens_table_head_expires_at' => 'Expired At',
	'admin_client_tokens_table_controls_refresh' => 'Refresh',
	'admin_client_tokens_table_controls_delete' => 'Delete',

	'admin_client_tokens_table_empty' => 'There are no granted tokens to display.',

	'admin_oauth_error_invalid_provider' => 'The specified oAuth provider does not exist.',
	'admin_oauth_error_invalid_provider_configuration' => 'The oAuth provider configuration is invalid.',
	'admin_oauth_error_required_url_configuration_missing' => 'One or more required URL configurations are missing.',

	'admin_oauth_success_success_updated_oauth' => 'The oAuth provider has been updated successfully.',
];
