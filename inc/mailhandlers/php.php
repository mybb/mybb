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
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * PHP mail handler class.
 */
class PhpMail extends MailHandler
{
	/**
	 * Additional parameters to pass to PHPs mail() function.
	 *
	 * @var string
	*/
	public $additional_parameters = '';

	/**
	 * Sends the email.
	 *
	 * @return bool whether or not the email got sent or not.
	 */
	function send()
	{
		global $lang, $mybb;

		// For some reason sendmail/qmail doesn't like \r\n
		$this->sendmail = @ini_get('sendmail_path');
		if($this->sendmail)
		{
			$this->headers = str_replace("\r\n", "\n", $this->headers);
			$this->message = str_replace("\r\n", "\n", $this->message);
			$this->delimiter = "\n";
		}

		// Some mail providers ignore email's with incorrect return-to path's so try and fix that here
		$this->sendmail_from = @ini_get('sendmail_from');
		if($this->sendmail_from != $mybb->settings['adminemail'])
		{
			@ini_set("sendmail_from", $mybb->settings['adminemail']);
		}

		$dir = "/{$mybb->config['admin_dir']}/";
		$pos = strrpos($_SERVER['PHP_SELF'], $dir);
		if(defined('IN_ADMINCP') && $pos !== false)
		{
			$temp_script_path = $_SERVER['PHP_SELF'];
			$_SERVER['PHP_SELF'] = substr($_SERVER['PHP_SELF'], $pos + strlen($dir) - 1);
		}

		// If safe mode is on, don't send the additional parameters as we're not allowed to
		if($mybb->safemode)
		{
			$sent = @mail($this->to, $this->subject, $this->message, trim($this->headers));
		}
		else
		{
			$sent = @mail($this->to, $this->subject, $this->message, trim($this->headers), $this->additional_parameters);
		}
		$function_used = 'mail()';

		if(defined('IN_ADMINCP') && $pos !== false)
		{
			$_SERVER['PHP_SELF'] = $temp_script_path;
		}

		if(!$sent)
		{
			$this->fatal_error("MyBB was unable to send the email using the PHP {$function_used} function.");
			return false;
		}

		return true;
	}
}
