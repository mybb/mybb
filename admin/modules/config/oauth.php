<?php

/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
use MyBB\Database\Repositories\oAuthRepository;

use function MyBB\app;

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

$providers = [
	'discord' => [
		'urls_fillable' => false,
		'default_scopes' => [
			'identify',
			'email',
			'connections',
			'guilds',
			'guilds.join',
		],
	],
	'drupal' => [
		'urls_fillable' => true,
		'default_scopes' => [
			'openid',
			'email',
			'profile',
		],
	],
	'facebook' => [
		'urls_fillable' => false,
		'default_scopes' => [
			'public_profile',
			'email',
		],
	],
	'github' => [
		'urls_fillable' => false,
		'default_scopes' => [
			'public_profile',
			'user:email',
		],
	],
	'google' => [
		'urls_fillable' => false,
		'default_scopes' => [
			'openid',
			'email',
			'profile',
		],
	],
	'linkedin' => [
		'urls_fillable' => false,
		'default_scopes' => [
			'r_liteprofile',
			'r_emailaddress',
		],
	],
	'microsoft' => [
		'urls_fillable' => false,
		'default_scopes' => [
			'wl.basic',
			'wl.emails',
		],
	],
	'paypal' => [
		'urls_fillable' => false,
		'default_scopes' => [
			'openid',
		],
	],
	'spotify' => [
		'urls_fillable' => false,
		'default_scopes' => [
		],
	],
	'wordpress' => [
		'urls_fillable' => true,
		'default_scopes' => [
		],
	],
];

$page->add_breadcrumb_item($lang->oauth, 'index.php?module=config-oauth');

$sub_tabs = [
	'oauth_clients' => [
		'title' => $lang->admin_oauth_tabs_oauth_clients,
		'link' => 'index.php?module=config-oauth',
		'description' => $lang->admin_oauth_tabs_oauth_clients_description,
	],
	'client_tokens' => [
		'title' => $lang->admin_oauth_tabs_client_tokens,
		'link' => 'index.php?module=config-oauth&amp;action=client_tokens',
		'description' => $lang->admin_oauth_tabs_client_tokens_description,
	],
];

$userIdentifier = $mybb->get_input('user_id', MyBB::INPUT_INT);

$providerIdentifier = $mybb->get_input('provider_identifier');

/** @var oAuthRepository $repository */
$repository = app(oAuthRepository::class);

$plugins->run_hooks('admin_config_oauth_begin');

if ($mybb->get_input('action') === 'toggle_status') {
	$providerData = $repository->providerFetch(
		['provider_identifier' => $providerIdentifier],
		[
			'client_id',
			'client_secret',
			'url_authorize',
			'url_access_token',
			'url_owner_details',
			'is_enabled',
		],
	);

	if ($providerData === false || empty($providers[$providerIdentifier])) {
		flash_message($lang->admin_oauth_error_invalid_provider, 'error');

		admin_redirect('index.php?module=config-oauth');
	}

	if (empty($providerData->client_id) ||
		empty($providerData->client_secret) ||
		(!empty($providers[$providerIdentifier]['urls_fillable']) && (
				empty($providerData->url_authorize) ||
				empty($providerData->url_access_token) ||
				empty($providerData->url_owner_details)
			))) {
		flash_message($lang->admin_oauth_error_invalid_provider_configuration, 'error');

		admin_redirect('index.php?module=config-oauth');
	}

	$repository->providerInsertUpdate([
		'provider_identifier' => $providerIdentifier,
		'is_enabled' => (int)!$providerData->is_enabled,
	]);

	flash_message($lang->admin_oauth_success_success_updated_oauth, 'success');

	admin_redirect('index.php?module=config-oauth');
} elseif ($mybb->get_input('action') === 'refresh_token') {
	// todo, implement refresh token action
} elseif ($mybb->get_input('action') === 'delete_token') {
	// todo, implement delete token action
} elseif ($mybb->get_input('action') === 'configure_client') {
	$providerData = $repository->providerFetch(
		['provider_identifier' => $providerIdentifier],
		[
			'client_id',
			'client_secret',
			'oauth_scopes',
			'url_authorize',
			'url_access_token',
			'url_owner_details',
			'store_token',
		],
	);

	if ($providerData === false || empty($providers[$providerIdentifier])) {
		flash_message($lang->admin_oauth_error_invalid_provider, 'error');

		admin_redirect('index.php?module=config-oauth');
	}

	$sub_tabs['configure_client'] = [
		'title' => $lang->admin_oauth_tabs_configure_client,
		'link' => 'index.php?module=config-oauth&amp;action=configure_client&amp;provider_identifier='.$providerIdentifier,
		'description' => $lang->admin_oauth_tabs_configure_client_description,
		'align' => 'right',
	];

	$plugins->run_hooks('admin_config_oauth_configure_client');

	$errors = [];

	if ($mybb->request_method === 'post') {
		if (!verify_post_check($mybb->get_input('my_post_key'))) {
			flash_message($lang->invalid_post_verify_key2, 'error');

			admin_redirect('index.php?module=config-oauth');
		}

		$providerInsertData = [
			'provider_identifier' => $providerIdentifier,
			'client_id' => $mybb->get_input('client_id'),
			'client_secret' => $mybb->get_input('client_secret'),
			'oauth_scopes' => $mybb->get_input('oauth_scopes'),
			'store_token' => $mybb->get_input('store_token', MyBB::INPUT_INT),
		];

		if (!empty($providers[$providerIdentifier]['urls_fillable'])) {
			$providerInsertData['url_authorize'] = $mybb->get_input('url_authorize');
			$providerInsertData['url_access_token'] = $mybb->get_input('url_access_token');
			$providerInsertData['url_owner_details'] = $mybb->get_input('url_owner_details');

			if (!filter_var($providerInsertData['url_authorize'], FILTER_VALIDATE_URL) ||
				!filter_var($providerInsertData['url_access_token'], FILTER_VALIDATE_URL) ||
				!filter_var($providerInsertData['url_owner_details'], FILTER_VALIDATE_URL)) {
				$errors[] = $lang->admin_oauth_error_required_url_configuration_missing;
			}
		}

		if (!$errors) {
			$plugins->run_hooks('admin_config_oauth_configure_client_post');

			$repository->providerInsertUpdate($providerInsertData);

			log_admin_action($providerIdentifier, 'configure_provider_identifier');

			flash_message($lang->admin_oauth_success_success_updated_oauth, 'success');

			admin_redirect('index.php?module=config-oauth');
		}
	}

	$page->add_breadcrumb_item(
		htmlspecialchars_uni(ucfirst($providerIdentifier))
	);

	$page->output_header(
		$lang->sprintf(
			$lang->admin_oauth_configure_client_form_title,
			htmlspecialchars_uni(ucfirst($providerIdentifier))
		)
	);

	$page->output_nav_tabs($sub_tabs, 'configure_client');

	$form = new Form(
		'index.php?module=config-oauth&amp;action=configure_client&amp;provider_identifier='.$providerIdentifier,
		'post',
		'edit'
	);

	if ($errors) {
		$page->output_inline_error($errors);
	} else {
		$mybb->input = array_merge($mybb->input, $providerData->toArray());
	}

	$form_container = new FormContainer(
		$lang->sprintf(
			$lang->admin_oauth_configure_client_form_title,
			htmlspecialchars_uni(ucfirst($providerIdentifier))
		)
	);

	$form_container->output_row(
		$lang->admin_oauth_configure_client_form_client_id.' <em>*</em>',
		$lang->admin_oauth_configure_client_form_client_id_description,
		$form->generate_text_box('client_id', $mybb->get_input('client_id'), ['id' => 'client_id']),
		'client_id'
	);

	$form_container->output_row(
		$lang->admin_oauth_configure_client_form_client_secret.' <em>*</em>',
		$lang->admin_oauth_configure_client_form_client_secret_description,
		$form->generate_text_box('client_secret', $mybb->get_input('client_secret'), ['id' => 'client_secret']),
		'client_secret'
	);

	$form_container->output_row(
		$lang->admin_oauth_configure_client_form_oauth_scopes.' <em>*</em>',
		$lang->admin_oauth_configure_client_form_oauth_scopes_description,
		$form->generate_text_box('oauth_scopes', $mybb->get_input('oauth_scopes'), ['id' => 'oauth_scopes']),
		'oauth_scopes'
	);

	if (!empty($providers[$providerIdentifier]['urls_fillable'])) {
		$form_container->output_row(
			$lang->admin_oauth_configure_client_form_url_authorize.' <em>*</em>',
			$lang->admin_oauth_configure_client_form_url_authorize_description,
			$form->generate_text_box('url_authorize', $mybb->get_input('url_authorize'), ['id' => 'url_authorize']),
			'url_authorize'
		);

		$form_container->output_row(
			$lang->admin_oauth_configure_client_form_url_access_token.' <em>*</em>',
			$lang->admin_oauth_configure_client_form_url_access_token_description,
			$form->generate_text_box(
				'url_access_token',
				$mybb->get_input('url_access_token'),
				['id' => 'url_access_token']
			),
			'url_access_token'
		);

		$form_container->output_row(
			$lang->admin_oauth_configure_client_form_url_owner_details.' <em>*</em>',
			$lang->admin_oauth_configure_client_form_url_owner_details_description,
			$form->generate_text_box(
				'url_owner_details',
				$mybb->get_input('url_owner_details'),
				['id' => 'url_owner_details']
			),
			'url_owner_details'
		);
	}

	$form_container->output_row(
		$lang->admin_oauth_configure_client_form_store_token,
		$lang->admin_oauth_configure_client_form_store_token_description,
		$form->generate_yes_no_radio(
			'store_token',
			$mybb->get_input('store_token', MyBB::INPUT_INT),
		),
		'store_token'
	);

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->admin_oauth_configure_client_form_button_update);

	$form->output_submit_wrapper($buttons);

	$form->end();

	$page->output_footer();
} elseif ($mybb->get_input('action') === 'client_tokens') {
	$page->output_header($lang->admin_oauth_title);

	$page->output_nav_tabs($sub_tabs, 'client_tokens');

	$plugins->run_hooks('admin_config_client_tokens_start');

	if ($errors) {
		$page->output_inline_error($errors);
	}

	$table = new Table();

	$table->construct_header($lang->admin_client_tokens_table_head_provider);

	$table->construct_header($lang->admin_client_tokens_table_head_user, array('width' => '30%'));

	$table->construct_header(
		$lang->admin_client_tokens_table_head_created_at,
		array('width' => '20%', 'class' => 'align_center')
	);

	$table->construct_header(
		$lang->admin_client_tokens_table_head_expires_at,
		array('width' => '20%', 'class' => 'align_center')
	);

	$table->construct_header($lang->controls, array('width' => '1%'));


	foreach (
		$repository->tokensFetch(
			queryFields: ['user_id', 'provider_identifier', 'created_at', 'expires_at'],
			queryOptions: ['order_by' => 'provider_identifier', 'order_dir' => 'asc']
		) as $providerData
	) {
		$table->construct_cell(htmlspecialchars_uni(ucfirst($providerData->provider_identifier)));

		$userData = get_user($providerData->user_id);

		$table->construct_cell(format_name($userData['username'], $userData['usergroup'], $userData['displaygroup']));

		$table->construct_cell(my_date('normal', $providerData->created_at));

		$table->construct_cell(my_date('normal', $providerData->expires_at));

		$popup = new PopupMenu("provider_{$providerData->user_id}_{$providerIdentifier}", $lang->options);

		$popup->add_item(
			$lang->admin_client_tokens_table_controls_refresh,
			"index.php?module=config-oauth&amp;action=refresh_token&amp;user_id={$providerData->user_id}provider_identifier={$providerIdentifier}",
		);

		$popup->add_item(
			$lang->admin_client_tokens_table_controls_delete,
			"index.php?module=config-oauth&amp;action=delete_token&amp;user_id={$providerData->user_id}provider_identifier={$providerIdentifier}"
		);

		$table->construct_cell($popup->fetch(), array('class' => 'align_center'));

		$table->construct_row();
	}

	if (!$table->num_rows()) {
		$table->construct_cell($lang->admin_client_tokens_table_empty, array('colspan' => 5, 'class' => 'align_center')
		);

		$table->construct_row();
	}

	$table->output($lang->admin_client_tokens_table_title);

	$page->output_footer();
} else {
	$page->output_header($lang->admin_oauth_title);

	$page->output_nav_tabs($sub_tabs, 'oauth_clients');

	$plugins->run_hooks('admin_config_oauth_clients_start');

	if ($errors) {
		$page->output_inline_error($errors);
	}

	$table = new Table();

	$table->construct_header($lang->admin_oauth_clients_table_head_provider);

	$table->construct_header($lang->admin_oauth_clients_table_head_scope, ['width' => '30%']);

	$table->construct_header(
		$lang->admin_oauth_clients_table_head_enabled,
		['width' => '20%', 'class' => 'align_center']
	);

	$table->construct_header($lang->controls, ['width' => '1%']);

	foreach (
		$repository->providersFetch(
			queryFields: ['provider_identifier', 'oauth_scopes', 'is_enabled'],
			queryOptions: ['order_by' => 'provider_identifier', 'order_dir' => 'asc']
		) as $providerData
	) {
		$table->construct_cell(
			"<a href=\"index.php?module=config-oauth&amp;action=configure_client&amp;provider_identifier={$providerData->provider_identifier}\">".htmlspecialchars_uni(
				ucfirst($providerData->provider_identifier)
			).'</a>'
		);

		$table->construct_cell(
			htmlspecialchars_uni(
				implode(
					', ',
					explode(str_contains(',', $providerData->oauth_scopes) ? ',' : ' ', $providerData->oauth_scopes)
				)
			)
		);

		if ($providerData->is_enabled) {
			$table->construct_cell(
				"<img src=\"styles/{$page->style}/images/icons/bullet_on.png\" alt=\"({$lang->alt_enabled})\" title=\"{$lang->alt_enabled}\"  style=\"vertical-align: middle;\" />",
				array('class' => 'align_center')
			);
		} else {
			$table->construct_cell(
				"<img src=\"styles/{$page->style}/images/icons/bullet_off.png\" alt=\"({$lang->alt_disabled})\" title=\"{$lang->alt_disabled}\"  style=\"vertical-align: middle;\" />",
				array('class' => 'align_center')
			);
		}

		$popup = new PopupMenu("provider_{$providerData->provider_identifier}", $lang->options);

		$popup->add_item(
			$lang->admin_oauth_clients_table_controls_configure,
			"index.php?module=config-oauth&amp;action=configure_client&amp;provider_identifier={$providerData->provider_identifier}"
		);

		$popup->add_item(
			$providerData->is_enabled ? $lang->disable : $lang->enable,
			"index.php?module=config-oauth&amp;action=toggle_status&amp;provider_identifier={$providerData->provider_identifier}&amp;my_post_key={$mybb->post_code}"
		);

		$table->construct_cell($popup->fetch(), array('class' => 'align_center'));

		$table->construct_row();
	}

	if (!$table->num_rows()) {
		$table->construct_cell($lang->admin_oauth_clients_table_empty, array('colspan' => 4, 'class' => 'align_center')
		);

		$table->construct_row();
	}

	$table->output($lang->admin_oauth_clients_table_title);

	$page->output_footer();
}
