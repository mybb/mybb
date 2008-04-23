<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
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
	var $additional_parameters = '';

	/**
	 * Sends the email.
	 *
	 * @return true/false whether or not the email got sent or not.
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

		if(function_exists('mb_send_mail'))
		{
			if(function_exists('mb_language'))
			{
				if($this->charset == "UTF-8")
				{
					$language = 'uni';
				}
				else
				{
					$language = $lang->settings['htmllang'];
				}
				@mb_language($language);
			}
			
			$sent = @mb_send_mail($this->to, $this->subject, $this->message, trim($this->headers), $this->additional_parameters);
			$function_used = 'mb_send_mail()';
		}
		else
		{
			$sent = @mail($this->to, $this->subject, $this->message, trim($this->headers), $this->additional_parameters);
			$function_used = 'mail()';
		}

		if(!$sent)
		{
			$this->fatal_error("MyBB was unable to send the email using the PHP {$function_used} function.");
			return false;
		}

		return true;
	}
}
?>