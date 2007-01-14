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
	 * Sends the email.
	 *
	 * @return true/false wether or not the email got sent or not.
	 */
	function send()
	{
		global $lang, $mybb;

		if($this->check_errors())
		{
			return $this->errors;
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
			
			return mb_send_mail($this->to, $this->subject, $this->message, trim($this->headers));
		}
		else
		{
			return mail($this->to, $this->subject, $this->message, trim($this->headers));
		}
	}
}
?>