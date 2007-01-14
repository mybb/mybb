<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/**
 * PHP mail handler class.
 */
class PhpMail extends MailHandler
{
	/**
	 * The currently used delimiter new lines.
	 *
	 * @var string.
	 */
	var $delimiter = "\r\n";
	
	/**
	 * Additional parameters to pass to PHPs mail() function.
	 *
	 * @var string
	*/
	var $additional_parameters = '';
		
	/**
	 * Sends the email.
	 *
	 * @return true/false wether or not the email got sent or not.
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
			
			$sent = mb_send_mail($this->to, $this->subject, $this->message, trim($this->headers), $additional_parameters);
		}
		else
		{
			$sent = mail($this->to, $this->subject, $this->message, trim($this->headers), $additional_parameters);
		}
		
		if(!$sent)
		{
			$this->fatal_error("MyBB was unable to send the email using the PHP mail() function.");
			return false
		}
		
		return true;
	}
}
?>