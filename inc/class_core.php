<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

class MyBB {
	/**
	 * The friendly version number of MyBB we're running.
	 *
	 * @var string
	 */
	public $version = "1.8.19";

	/**
	 * The version code of MyBB we're running.
	 *
	 * @var integer
	 */
	public $version_code = 1819;

	/**
	 * The current working directory.
	 *
	 * @var string
	 */
	public $cwd = ".";

	/**
	 * Input variables received from the outer world.
	 *
	 * @var array
	 */
	public $input = array();

	/**
	 * Cookie variables received from the outer world.
	 *
	 * @var array
	 */
	public $cookies = array();

	/**
	 * Information about the current user.
	 *
	 * @var array
	 */
	public $user = array();

	/**
	 * Information about the current usergroup.
	 *
	 * @var array
	 */
	public $usergroup = array();

	/**
	 * MyBB settings.
	 *
	 * @var array
	 */
	public $settings = array();

	/**
	 * Whether or not magic quotes are enabled.
	 *
	 * @var int
	 */
	public $magicquotes = 0;

	/**
	 * Whether or not MyBB supports SEO URLs
	 *
	 * @var boolean
	 */
	public $seo_support = false;

	/**
	 * MyBB configuration.
	 *
	 * @var array
	 */
	public $config = array();

	/**
	 * The request method that called this page.
	 *
	 * @var string
	 */
	public $request_method = "";

	/**
	 * Whether or not PHP's safe_mode is enabled
	 *
	 * @var boolean
	 */
	public $safemode = false;

	/**
	 * Loads templates directly from the master theme and disables the installer locked error
	 *
	 * @var boolean
	 */
	public $dev_mode = false;

	/**
	 * Variables that need to be clean.
	 *
	 * @var array
	 */
	public $clean_variables = array(
		"int" => array(
			"tid", "pid", "uid",
			"eid", "pmid", "fid",
			"aid", "rid", "sid",
			"vid", "cid", "bid",
			"hid", "gid", "mid",
			"wid", "lid", "iid",
			"did", "qid", "id"
		),
		"pos" => array(
			"page", "perpage"
		),
		"a-z" => array(
			"sortby", "order"
		)
	);

	/**
	 * Variables that are to be ignored from cleansing process
	 *
	 * @var array
	 */
	public $ignore_clean_variables = array();

	/**
	 * Using built in shutdown functionality provided by register_shutdown_function for < PHP 5?
	 *
	 * @var bool
	 */
	public $use_shutdown = true;

	/**
	 * Debug mode?
	 *
	 * @var bool
	 */
	public $debug_mode = false;

	/**
	 * Binary database fields need to be handled differently
	 *
	 * @var array
	 */
	public $binary_fields = array(
		'adminlog' => array('ipaddress' => true),
		'adminsessions' => array('ip' => true),
		'maillogs' => array('ipaddress' => true),
		'moderatorlog' => array('ipaddress' => true),
		'pollvotes' => array('ipaddress' => true),
		'posts' => array('ipaddress' => true),
		'privatemessages' => array('ipaddress' => true),
		'searchlog' => array('ipaddress' => true),
		'sessions' => array('ip' => true),
		'threadratings' => array('ipaddress' => true),
		'users' => array('regip' => true, 'lastip' => true),
		'spamlog' => array('ipaddress' => true),
	);

	/**
	 * The cache instance to use.
	 *
	 * @var datacache
	 */
	public $cache;

	/**
	 * The base URL to assets.
	 *
	 * @var string
	 */
	public $asset_url = null;
	/**
	 * String input constant for use with get_input().
	 *
	 * @see get_input
	 */
	const INPUT_STRING = 0;
	/**
	 * Integer input constant for use with get_input().
	 *
	 * @see get_input
	 */
	const INPUT_INT = 1;
	/**
	 * Array input constant for use with get_input().
	 *
	 * @see get_input
	 */
	const INPUT_ARRAY = 2;
	/**
	 * Float input constant for use with get_input().
	 *
	 * @see get_input
	 */
	const INPUT_FLOAT = 3;
	/**
	 * Boolean input constant for use with get_input().
	 *
	 * @see get_input
	 */
	const INPUT_BOOL = 4;

	/**
	 * Constructor of class.
	 */
	function __construct()
	{
		// Set up MyBB
		$protected = array("_GET", "_POST", "_SERVER", "_COOKIE", "_FILES", "_ENV", "GLOBALS");
		foreach($protected as $var)
		{
			if(isset($_POST[$var]) || isset($_GET[$var]) || isset($_COOKIE[$var]) || isset($_FILES[$var]))
			{
				die("Hacking attempt");
			}
		}

		if(defined("IGNORE_CLEAN_VARS"))
		{
			if(!is_array(IGNORE_CLEAN_VARS))
			{
				$this->ignore_clean_variables = array(IGNORE_CLEAN_VARS);
			}
			else
			{
				$this->ignore_clean_variables = IGNORE_CLEAN_VARS;
			}
		}

		// Determine Magic Quotes Status (< PHP 6.0)
		if(version_compare(PHP_VERSION, '6.0', '<'))
		{
			if(@get_magic_quotes_gpc())
			{
				$this->magicquotes = 1;
				$this->strip_slashes_array($_POST);
				$this->strip_slashes_array($_GET);
				$this->strip_slashes_array($_COOKIE);
			}
			@set_magic_quotes_runtime(0);
			@ini_set("magic_quotes_gpc", 0);
			@ini_set("magic_quotes_runtime", 0);
		}

		// Determine input
		$this->parse_incoming($_GET);
		$this->parse_incoming($_POST);

		if($_SERVER['REQUEST_METHOD'] == "POST")
		{
			$this->request_method = "post";
		}
		else if($_SERVER['REQUEST_METHOD'] == "GET")
		{
			$this->request_method = "get";
		}

		// If we've got register globals on, then kill them too
		if(@ini_get("register_globals") == 1)
		{
			$this->unset_globals($_POST);
			$this->unset_globals($_GET);
			$this->unset_globals($_FILES);
			$this->unset_globals($_COOKIE);
		}
		$this->clean_input();

		$safe_mode_status = @ini_get("safe_mode");
		if($safe_mode_status == 1 || strtolower($safe_mode_status) == 'on')
		{
			$this->safemode = true;
		}

		// Are we running on a development server?
		if(isset($_SERVER['MYBB_DEV_MODE']) && $_SERVER['MYBB_DEV_MODE'] == 1)
		{
			$this->dev_mode = 1;
		}

		// Are we running in debug mode?
		if(isset($this->input['debug']) && $this->input['debug'] == 1)
		{
			$this->debug_mode = true;
		}

		if(isset($this->input['action']) && $this->input['action'] == "mybb_logo")
		{
			require_once dirname(__FILE__)."/mybb_group.php";
			output_logo();
		}

		if(isset($this->input['intcheck']) && $this->input['intcheck'] == 1)
		{
			die("&#077;&#089;&#066;&#066;");
		}
	}

	/**
	 * Parses the incoming variables.
	 *
	 * @param array $array The array of incoming variables.
	 */
	function parse_incoming($array)
	{
		if(!is_array($array))
		{
			return;
		}

		foreach($array as $key => $val)
		{
			$this->input[$key] = $val;
		}
	}

	/**
	 * Parses the incoming cookies
	 *
	 */
	function parse_cookies()
	{
		if(!is_array($_COOKIE))
		{
			return;
		}

		$prefix_length = strlen($this->settings['cookieprefix']);

		foreach($_COOKIE as $key => $val)
		{
			if($prefix_length && substr($key, 0, $prefix_length) == $this->settings['cookieprefix'])
			{
				$key = substr($key, $prefix_length);

				// Fixes conflicts with one board having a prefix and another that doesn't on the same domain
				// Gives priority to our cookies over others (overwrites them)
				if($this->cookies[$key])
				{
					unset($this->cookies[$key]);
				}
			}

			if(empty($this->cookies[$key]))
			{
				$this->cookies[$key] = $val;
			}
		}
	}

	/**
	 * Strips slashes out of a given array.
	 *
	 * @param array $array The array to strip.
	 */
	function strip_slashes_array(&$array)
	{
		foreach($array as $key => $val)
		{
			if(is_array($array[$key]))
			{
				$this->strip_slashes_array($array[$key]);
			}
			else
			{
				$array[$key] = stripslashes($array[$key]);
			}
		}
	}

	/**
	 * Unsets globals from a specific array.
	 *
	 * @param array $array The array to unset from.
	 */
	function unset_globals($array)
	{
		if(!is_array($array))
		{
			return;
		}

		foreach(array_keys($array) as $key)
		{
			unset($GLOBALS[$key]);
			unset($GLOBALS[$key]); // Double unset to circumvent the zend_hash_del_key_or_index hole in PHP <4.4.3 and <5.1.4
		}
	}

	/**
	 * Cleans predefined input variables.
	 *
	 */
	function clean_input()
	{
		foreach($this->clean_variables as $type => $variables)
		{
			foreach($variables as $var)
			{
				// If this variable is in the ignored array, skip and move to next.
				if(in_array($var, $this->ignore_clean_variables))
				{
					continue;
				}

				if(isset($this->input[$var]))
				{
					switch($type)
					{
						case "int":
							$this->input[$var] = $this->get_input($var, MyBB::INPUT_INT);
							break;
						case "a-z":
							$this->input[$var] = preg_replace("#[^a-z\.\-_]#i", "", $this->get_input($var));
							break;
						case "pos":
							if(($this->input[$var] < 0 && $var != "page") || ($var == "page" && $this->input[$var] != "last" && $this->input[$var] < 0))
								$this->input[$var] = 0;
							break;
					}
				}
			}
		}
	}

	/**
	 * Checks the input data type before usage.
	 *
	 * @param string $name Variable name ($mybb->input)
	 * @param int $type The type of the variable to get. Should be one of MyBB::INPUT_INT, MyBB::INPUT_ARRAY or MyBB::INPUT_STRING.
	 *
	 * @return int|float|array|string Checked data. Type depending on $type
	 */
	function get_input($name, $type = MyBB::INPUT_STRING)
	{
		switch($type)
		{
			case MyBB::INPUT_ARRAY:
				if(!isset($this->input[$name]) || !is_array($this->input[$name]))
				{
					return array();
				}
				return $this->input[$name];
			case MyBB::INPUT_INT:
				if(!isset($this->input[$name]) || !is_numeric($this->input[$name]))
				{
					return 0;
				}
				return (int)$this->input[$name];
			case MyBB::INPUT_FLOAT:
				if(!isset($this->input[$name]) || !is_numeric($this->input[$name]))
				{
					return 0.0;
				}
				return (float)$this->input[$name];
			case MyBB::INPUT_BOOL:
				if(!isset($this->input[$name]) || !is_scalar($this->input[$name]))
				{
					return false;
				}
				return (bool)$this->input[$name];
			default:
				if(!isset($this->input[$name]) || !is_scalar($this->input[$name]))
				{
					return '';
				}
				return $this->input[$name];
		}
	}

	/**
	 * Get the path to an asset using the CDN URL if configured.
	 *
	 * @param string $path    The path to the file.
	 * @param bool   $use_cdn Whether to use the configured CDN options.
	 *
	 * @return string The complete URL to the asset.
	 */
	public function get_asset_url($path = '', $use_cdn = true)
	{
		$path = (string) $path;
		$path = ltrim($path, '/');

		if(substr($path, 0, 4) != 'http')
		{
			if(substr($path, 0, 2) == './')
			{
				$path = substr($path, 2);
			}

			if($use_cdn && $this->settings['usecdn'] && !empty($this->settings['cdnurl']))
			{
				$base_path = rtrim($this->settings['cdnurl'], '/');
			}
			else
			{
				$base_path = rtrim($this->settings['bburl'], '/');
			}

			$url = $base_path;

			if(!empty($path))
			{
				$url = $base_path . '/' . $path;
			}
		}
		else
		{
			$url = $path;
		}

		return $url;
	}

	/**
	 * Triggers a generic error.
	 *
	 * @param string $code The error code.
	 */
	function trigger_generic_error($code)
	{
		global $error_handler;

		switch($code)
		{
			case "cache_no_write":
				$message = "The data cache directory (cache/) needs to exist and be writable by the web server. Change its permissions so that it is writable (777 on Unix based servers).";
				$error_code = MYBB_CACHE_NO_WRITE;
				break;
			case "install_directory":
				$message = "The install directory (install/) still exists on your server and is not locked. To access MyBB please either remove this directory or create an empty file in it called 'lock'.";
				$error_code = MYBB_INSTALL_DIR_EXISTS;
				break;
			case "board_not_installed":
				$message = "Your board has not yet been installed and configured. Please do so before attempting to browse it.";
				$error_code = MYBB_NOT_INSTALLED;
				break;
			case "board_not_upgraded":
				$message = "Your board has not yet been upgraded. Please do so before attempting to browse it.";
				$error_code = MYBB_NOT_UPGRADED;
				break;
			case "sql_load_error":
				$message = "MyBB was unable to load the SQL extension. Please contact the MyBB Group for support. <a href=\"https://mybb.com\">MyBB Website</a>";
				$error_code = MYBB_SQL_LOAD_ERROR;
				break;
			case "apc_load_error":
				$message = "APC needs to be configured with PHP to use the APC cache support.";
				$error_code = MYBB_CACHEHANDLER_LOAD_ERROR;
				break;
			case "eaccelerator_load_error":
				$message = "eAccelerator needs to be configured with PHP to use the eAccelerator cache support.";
				$error_code = MYBB_CACHEHANDLER_LOAD_ERROR;
				break;
			case "memcache_load_error":
				$message = "Your server does not have memcache support enabled.";
				$error_code = MYBB_CACHEHANDLER_LOAD_ERROR;
				break;
			case "memcached_load_error":
				$message = "Your server does not have memcached support enabled.";
				$error_code = MYBB_CACHEHANDLER_LOAD_ERROR;
				break;
			case "xcache_load_error":
				$message = "Xcache needs to be configured with PHP to use the Xcache cache support.";
				$error_code = MYBB_CACHEHANDLER_LOAD_ERROR;
				break;
			default:
				$message = "MyBB has experienced an internal error. Please contact the MyBB Group for support. <a href=\"https://mybb.com\">MyBB Website</a>";
				$error_code = MYBB_GENERAL;
		}
		$error_handler->trigger($message, $error_code);
	}

	function __destruct()
	{
		// Run shutdown function
		if(function_exists("run_shutdown"))
		{
			run_shutdown();
		}
	}
}

/**
 * Do this here because the core is used on every MyBB page
 */

$grouppermignore = array("gid", "type", "title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$groupzerogreater = array("pmquota", "maxpmrecipients", "maxreputationsday", "attachquota", "maxemails", "maxposts", "edittimelimit", "maxreputationsperuser", "maxreputationsperthread", "emailfloodtime");
$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");

// These are fields in the usergroups table that are also forum permission specific.
$fpermfields = array(
	'canview',
	'canviewthreads',
	'candlattachments',
	'canpostthreads',
	'canpostreplys',
	'canpostattachments',
	'canratethreads',
	'caneditposts',
	'candeleteposts',
	'candeletethreads',
	'caneditattachments',
	'canviewdeletionnotice',
	'modposts',
	'modthreads',
	'modattachments',
	'mod_edit_posts',
	'canpostpolls',
	'canvotepolls',
	'cansearch'
);
