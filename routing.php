<?php

/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * The deal with this file is that it handles all of the XML HTTP Requests for MyBB.
 *
 * It contains a stripped down version of the MyBB core which does not load things
 * such as themes, who's online data, all of the language packs and more.
 *
 * This is done to make response times when using XML HTTP Requests faster and
 * less intense on the server.
 */

use MyBB\Database\Repositories\ThemeRepository;
use MyBB\Extensions\Theme\Theme;
use MyBB\View\Runtime\Runtime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\HttpFoundation\Request;

use function MyBB\app;

define('IN_MYBB', 1);

const NO_ONLINE = true;

define('THIS_SCRIPT', 'routing.php');

// Load MyBB core files
require_once dirname(__FILE__).'/inc/init.php';

$shutdown_queries = $shutdown_functions = array();

// Load some of the stock caches we'll be using.
$groupscache = $cache->read('usergroups');

if (!is_array($groupscache)) {
	$cache->update_usergroups();
	$groupscache = $cache->read('usergroups');
}

// Send no cache headers
header('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Create the session
require_once MYBB_ROOT.'inc/class_session.php';
$session = new session();
$session->init();
$mybb->session = &$session;

// Load the language we'll be using
if (!isset($mybb->settings['bblanguage'])) {
	$mybb->settings['bblanguage'] = 'english';
}
if (isset($mybb->user['language']) && $lang->language_exists($mybb->user['language'])) {
	$mybb->settings['bblanguage'] = $mybb->user['language'];
}
$lang->set_language($mybb->settings['bblanguage']);

if (function_exists('mb_internal_encoding') && !empty($lang->settings['charset'])) {
	@mb_internal_encoding($lang->settings['charset']);
}

// Load the theme
// 1. Check cookies
if (!$mybb->user['uid'] && !empty($mybb->cookies['mybbtheme'])) {
	$mybb->user['style'] = (int)$mybb->cookies['mybbtheme'];
}

// 2. Load style
$repository = app(ThemeRepository::class);
$tid = null;
$theme_model = null;

if (isset($mybb->user['style']) && (int)$mybb->user['style'] !== 0) {
	$tid = (int)$mybb->user['style'];
}

// Fetch the theme to load
if ($tid !== null) {
	$theme_model = $repository->find($tid);

	if ($theme_model && !$theme_model->allowedForUser($mybb->user)) {
		if (isset($mybb->cookies['mybbtheme'])) {
			my_unsetcookie('mybbtheme');
		}

		$tid = null;
	}
}

if ($tid === null) {
	$theme_model = $repository->findDefault();
}

// No Theme model was found - load fallback values
if (!$theme_model) {
	// Missing theme was from a user, run a query to set any users using the theme to the default
	$db->update_query('users', array('style' => 0), "style = '{$mybb->user['style']}'");

	$theme_model = $repository->getFallback();
}

app()->instance(
	\MyBB\Database\Models\Theme::class,
	$theme_model,
);

if ($theme_model->package->exists()) {
	// override initial binding
	app()->instance(
		Theme::class,
		$theme_model->package,
	);
}

$view = app(Runtime::class);

$view->setContext([
	'script' => basename($_SERVER['PHP_SELF']),
	'action' => $mybb->get_input('action'),
]);

$view->setMainNamespace('frontend');

$theme = $view->getGlobalThemeArray();

if ($lang->settings['charset']) {
	$charset = $lang->settings['charset'];
} // If not, revert to UTF-8
else {
	$charset = 'UTF-8';
}

$lang->load('global');
$lang->load('messages');

$closed_bypass = array('refresh_captcha', 'validate_captcha');

$mybb->input['action'] = $mybb->get_input('action');

/** @var RouteCollection $routeCollection */
$routeCollection = app()->make(RouteCollection::class);

/** @var UrlMatcher $urlMatcher */
$urlMatcher = app(UrlMatcher::class);

try {
	$request = Request::createFromGlobals();

	$parameters = $urlMatcher->match($request->getPathInfo());

	$response = $parameters['_controller']($parameters);

	$response->send();
} catch (Exception $e) {
	error(status_code: Response::HTTP_NOT_FOUND);
}
