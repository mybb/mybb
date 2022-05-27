<?php
/**
 * MyBB 1.9
 * Copyright 2022 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define('IN_MYBB', 1);

define('NO_ONLINE', 1);

define('THIS_SCRIPT', 'resource.php');

// We'd prefer not to load this file, and instead load inc/init.php alone, because it is more
// lightweight, but we need it for its resolution of the current theme for the current user, which
// it sets $theme to, and which is used within resolve_themelet_resource().
require_once './global.php';

if (empty($mybb->input['specifier'])) {
	error('Missing "specifier" query string parameter.');
}

require_once MYBB_ROOT.'inc/functions_themes.php';

$specifier = $mybb->get_input('specifier');

// TODO: Find, and use - in place of the basic, ad-hoc code below - a generic extension-to-mime-type function.
// Alternatively: develop a normative list of permitted resource file types, and use it to extend the below code.
$content_type = 'application/octet-stream';
switch (my_strtolower(get_extension($specifier))) {
	case 'jpg':
	case 'jpeg':
		$content_type = 'image/jpeg';
		break;
	case 'png':
		$content_type = 'image/png';
		break;
	case 'gif':
		$content_type = 'image/gif';
		break;
	case 'css':
		$content_type = 'text/css';
		break;
}

header("Content-Type: $content_type");
if ($mybb->settings['themelet_dev_mode']) {
	echo resolve_themelet_resource($specifier, /*$use_themelet_cache = */false, /*$return_resource = */true);
} else	echo file_get_contents(MYBB_ROOT.resolve_themelet_resource($specifier, /*$use_themelet_cache = */true, /*$return_resource = */false));

exit;
