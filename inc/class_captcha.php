<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * This class is based from reCAPTCHA's PHP library, adapted for use in MyBB.
 *
 * Copyright (c) 2007 reCAPTCHA -- http://recaptcha.net
 * AUTHORS:
 *   Mike Crawford
 *   Ben Maurer
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 */

class captcha
{
	/**
	 * Type of CAPTCHA.
	 *
	 * 1 = Default CAPTCHA
	 * 2 = reCAPTCHA
	 * 3 = Are You a Human
	 * 4 = NoCATPCHA reCAPTCHA
	 *
	 * @var int
	 */
	public $type = 0;

	/**
	 * The template to display the CAPTCHA in
	 *
	 * @var string
	 */
	 public $captch_template = '';

	/**
	 * CAPTCHA Server URL
	 *
	 * @var string
	 */
	public $server = '';

	/**
	 * CAPTCHA Secure Server URL
	 *
	 * @var string
	 */
	public $secure_server = '';

	/**
	 * CAPTCHA Verify Server
	 *
	 * @var string
	 */
	public $verify_server = '';

	/**
	 * Are You a Human configuration
	 *
	 * @var string
	 */
	public $ayah_web_service_host = '';
	public $ayah_publisher_key = '';
	public $ayah_scoring_key = '';
	public $ayah_debug_mode = '';
	public $ayah_use_curl = '';

	/**
	 * HTML of the built CAPTCHA
	 *
	 * @var string
	 */
	public $html = '';

	/**
	 * The errors that occurred when handling data.
	 *
	 * @var array
	 */
	public $errors = array();

	function __construct($build = false, $template = "")
	{
		global $mybb, $plugins;

		$this->type = $mybb->settings['captchaimage'];

		$args = array(
			'this' => &$this,
			'build' => &$build,
			'template' => &$template,
		);

		$plugins->run_hooks('captcha_build_start', $args);

		// Prepare the build template
		if($template)
		{
			$this->captcha_template = $template;

			if($this->type == 2)
			{
				$this->captcha_template .= "_recaptcha";
			}
			else if($this->type == 3)
			{
				$this->captcha_template .= "_ayah";
			}
			else if($this->type == 4){
				$this->captcha_template .= "_nocaptcha";
			}
		}

		// Work on which CAPTCHA we've got installed
		if($this->type == 3 && $mybb->settings['ayahpublisherkey'] && $mybb->settings['ayahscoringkey'])
		{
			// We want to use Are You a Human, set configuration options
			$this->ayah_web_service_host = "ws.areyouahuman.com";
			$this->ayah_publisher_key = $mybb->settings['ayahpublisherkey'];
			$this->ayah_scoring_key = $mybb->settings['ayahscoringkey'];
			$this->ayah_debug_mode = false;
			$this->ayah_use_curl = true;

			if($build == true)
			{
				$this->build_ayah();
			}
		}
		else if($this->type == 2 && $mybb->settings['captchapublickey'] && $mybb->settings['captchaprivatekey'])
		{
			// We want to use reCAPTCHA, set the server options
			$this->server = "http://www.google.com/recaptcha/api";
			$this->secure_server = "https://www.google.com/recaptcha/api";
			$this->verify_server = "www.google.com";

			if($build == true)
			{
				$this->build_recaptcha();
			}
		}
		else if($this->type == 4 && $mybb->settings['captchapublickey'] && $mybb->settings['captchaprivatekey'])
		{
			// We want to use reCAPTCHA, set the server options
			$this->server = "http://www.google.com/recaptcha/api.js";
			$this->secure_server = "https://www.google.com/recaptcha/api.js";
			$this->verify_server = "https://www.google.com/recaptcha/api/siteverify";

			if($build == true)
			{
				$this->build_recaptcha();
			}
		}
		else if($this->type == 1)
		{
			if(!function_exists("imagecreatefrompng"))
			{
				// We want to use the default CAPTCHA, but it's not installed
				return false;
			}
			else if($build == true)
			{
				$this->build_captcha();
			}
		}

		$plugins->run_hooks('captcha_build_end', $args);
	}

	function build_captcha($return = false)
	{
		global $db, $lang, $templates, $theme, $mybb;

		// This will build a MyBB CAPTCHA
		$randomstr = random_str(5);
		$imagehash = md5(random_str(12));

		$insert_array = array(
			"imagehash" => $imagehash,
			"imagestring" => $randomstr,
			"dateline" => TIME_NOW
		);

		$db->insert_query("captcha", $insert_array);
		eval("\$this->html = \"".$templates->get($this->captcha_template)."\";");
		//eval("\$this->html = \"".$templates->get("member_register_regimage")."\";");
	}

	function build_recaptcha()
	{
		global $lang, $mybb, $templates;

		// This will build a reCAPTCHA
		$server = $this->server;
		$public_key = $mybb->settings['captchapublickey'];

		if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
		{
			// Use secure server if HTTPS
			$server = $this->secure_server;
		}

		eval("\$this->html = \"".$templates->get($this->captcha_template, 1, 0)."\";");
		//eval("\$this->html = \"".$templates->get("member_register_regimage_recaptcha")."\";");
	}

	function build_ayah()
	{
		global $lang, $mybb, $templates;

		define('AYAH_PUBLISHER_KEY', $this->ayah_publisher_key);
		define('AYAH_SCORING_KEY', $this->ayah_scoring_key);
		define('AYAH_USE_CURL', $this->ayah_use_curl);
		define('AYAH_DEBUG_MODE', $this->ayah_debug_mode);
		define('AYAH_WEB_SERVICE_HOST', $this->ayah_web_service_host);

		require_once MYBB_ROOT."inc/3rdparty/ayah/ayah.php";
		$ayah = new AYAH();
		$output = $ayah->getPublisherHTML();

		if(!empty($output))
		{
			eval("\$this->html = \"".$templates->get($this->captcha_template, 1, 0)."\";");
			//eval("\$this->html = \"".$templates->get("member_register_regimage_ayah")."\";");
		}
	}

	function build_hidden_captcha()
	{
		global $db, $mybb, $templates;

		$field = array();

		if($this->type == 1)
		{
			// Names
			$hash = "imagehash";
			$string = "imagestring";

			// Values
			$field['hash'] = $db->escape_string($mybb->input['imagehash']);
			$field['string'] = $db->escape_string($mybb->input['imagestring']);
		}
		else if($this->type == 2)
		{
			// Names
			$hash = "recaptcha_challenge_field";
			$string = "recaptcha_response_field";

			// Values
			$field['hash'] = $mybb->input['recaptcha_challenge_field'];
			$field['string'] = $mybb->input['recaptcha_response_field'];
		}
		else if($this->type == 3)
		{
			// Are You a Human can't be built as a hidden captcha
			continue;
		}

		eval("\$this->html = \"".$templates->get("post_captcha_hidden")."\";");
		return $this->html;
	}

	function validate_captcha()
	{
		global $db, $lang, $mybb, $session, $plugins;

		$plugins->run_hooks('captcha_validate_start', $this);

		if($this->type == 1)
		{
			// We have a normal CAPTCHA to handle
			$imagehash = $db->escape_string($mybb->input['imagehash']);
			$imagestring = $db->escape_string(my_strtolower($mybb->input['imagestring']));

			switch($db->type)
			{
				case 'mysql':
				case 'mysqli':
					$field = 'imagestring';
					break;
				default:
					$field = 'LOWER(imagestring)';
					break;
			}

			$query = $db->simple_select("captcha", "*", "imagehash = '{$imagehash}' AND {$field} = '{$imagestring}'");
			$imgcheck = $db->fetch_array($query);

			if(!$imgcheck)
			{
				$this->set_error($lang->invalid_captcha_verify);
				$db->delete_query("captcha", "imagehash = '{$imagehash}'");
			}
		}
		elseif($this->type == 2)
		{
			$challenge = $mybb->input['recaptcha_challenge_field'];
			$response = $mybb->input['recaptcha_response_field'];

			if(!$challenge || strlen($challenge) == 0 || !$response || strlen($response) == 0)
			{
				$this->set_error($lang->invalid_captcha);
			}
			else
			{
				// We have a reCAPTCHA to handle
				$data = $this->_qsencode(array(
					'privatekey' => $mybb->settings['captchaprivatekey'],
					'remoteip' => $session->ipaddress,
					'challenge' => $challenge,
					'response' => $response
				));

				// Contact Google and see if our reCAPTCHA was successful
				$http_request  = "POST /recaptcha/api/verify HTTP/1.0\r\n";
				$http_request .= "Host: $this->verify_server\r\n";
				$http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
				$http_request .= "Content-Length: ".strlen($data)."\r\n";
				$http_request .= "User-Agent: reCAPTCHA/PHP\r\n";
				$http_request .= "\r\n";
				$http_request .= $data;

				$fs = @fsockopen($this->verify_server, 80, $errno, $errstr, 10);

				if($fs == false)
				{
					$this->set_error($lang->invalid_captcha_transmit);
				}
				else
				{
					// We connected, but is it correct?
					fwrite($fs, $http_request);

					while(!feof($fs))
					{
						$response .= fgets($fs, 1160);
					}

					fclose($fs);

					$response = explode("\r\n\r\n", $response, 2);
					$answer = explode("\n", $response[1]);

					if(trim($answer[0]) != 'true')
					{
						// We got it wrong! Oh no...
						$this->set_error($lang->invalid_captcha_verify);
					}
				}
			}
		}
		elseif($this->type == 4)
		{
			$response = $mybb->input['g-recaptcha-response'];
			if(!$response || strlen($response) == 0)
			{
				$this->set_error($lang->invalid_nocaptcha);
			}
			else
			{
				// We have a noCAPTCHA to handle
				// Contact Google and see if our reCAPTCHA was successful
				$response = fetch_remote_file($this->verify_server, array(
					'secret' => $mybb->settings['captchaprivatekey'],
					'remoteip' => $session->ipaddress,
					'response' => $response
				));

				if($response == false)
				{
					$this->set_error($lang->invalid_nocaptcha_transmit);
				}
				else
				{
					$answer = json_decode($response, true);

					if($answer['success'] != 'true')
					{
						// We got it wrong! Oh no...
						$this->set_error($lang->invalid_nocaptcha);
					}
				}
			}
		}
		elseif($this->type == 3)
		{
			define('AYAH_PUBLISHER_KEY', $this->ayah_publisher_key);
			define('AYAH_SCORING_KEY', $this->ayah_scoring_key);
			define('AYAH_USE_CURL', $this->ayah_use_curl);
			define('AYAH_DEBUG_MODE', $this->ayah_debug_mode);
			define('AYAH_WEB_SERVICE_HOST', $this->ayah_web_service_host);

			require_once MYBB_ROOT."inc/3rdparty/ayah/ayah.php";
			$ayah = new AYAH();

			$result = $ayah->scoreResult();

			if($result == false)
			{
				$this->set_error($lang->invalid_ayah_result);
			}
		}

		$plugins->run_hooks('captcha_validate_end', $this);

		if(count($this->errors) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	function invalidate_captcha()
	{
		global $db, $mybb, $plugins;

		if($this->type == 1)
		{
			// We have a normal CAPTCHA to handle
			$imagehash = $db->escape_string($mybb->input['imagehash']);
			if($imagehash)
			{
				$db->delete_query("captcha", "imagehash = '{$imagehash}'");
			}
		}
		// Not necessary for reCAPTCHA or Are You a Human

		$plugins->run_hooks('captcha_invalidate_end', $this);
	}

	/**
	 * Add an error to the error array.
	 */
	function set_error($error, $data='')
	{
		$this->errors[$error] = array(
			"error_code" => $error,
			"data" => $data
		);
	}

	/**
	 * Returns the error(s) that occurred when handling data
	 * in a format that MyBB can handle.
	 *
	 * @return An array of errors in a MyBB format.
	 */
	function get_errors()
	{
		global $lang;

		foreach($this->errors as $error)
		{
			$lang_string = $error['error_code'];

			if(!$lang_string)
			{
				if($lang->invalid_captcha_verify)
				{
					$lang_string = 'invalid_captcha_verify';
				}
				else
				{
					$lang_string = 'unknown_error';
				}
			}

			if(!isset($lang->$lang_string))
			{
				$errors[] = $error['error_code'];
				continue;
			}

			if(!empty($error['data']) && !is_array($error['data']))
			{
				$error['data'] = array($error['data']);
			}

			if(is_array($error['data']))
			{
				array_unshift($error['data'], $lang->$lang_string);
				$errors[] = call_user_func_array(array($lang, "sprintf"), $error['data']);
			}
			else
			{
				$errors[] = $lang->$lang_string;
			}
		}

		return $errors;
	}

	private function _qsencode($data)
	{
		$req = '';
		foreach($data as $key => $value)
		{
			$req .= $key.'='.urlencode(stripslashes($value)).'&';
		}

		$req = substr($req, 0, (strlen($req) - 1));

		return $req;
	}
}