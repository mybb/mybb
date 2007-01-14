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
		
		if($this->charset == "UTF-8")
		{
			$this->charset = 'uni';
		}
	
		// Build mail headers
		if(my_strlen(trim($this->from)) == 0)
		{
			$from = "\"".$mybb->settings['bbname']." Mailer\" <".$mybb->settings['adminemail'].">";
		}
		$this->headers .= "From: {$from}\n";
		$this->headers .= "Return-Path: {$mybb->settings['adminemail']}\n";
		if($_SERVER['SERVER_NAME'])
		{
			$http_host = $_SERVER['SERVER_NAME'];
		}
		else if($_SERVER['HTTP_HOST'])
		{
			$http_host = $_SERVER['HTTP_HOST'];
		}
		else
		{
			$http_host = "unknown.local";
		}
		$this->headers .= "Message-ID: <". md5(uniqid(time()))."@{$http_host}>\n";
		$this->headers .= "MIME-Version: 1.0\n";
		$this->headers .= "Content-Type: text/plain;";
		if(!function_exists('mb_send_mail'))
		{
			$this->headers .= " charset=\"{$this->charset}\"\n";
		}
		else
		{
			$this->headers .= "\n";
		}
		$this->headers .= "Content-Transfer-Encoding: 8bit\n";
		$this->headers .= "X-Priority: 3\n";
		$this->headers .= "X-MSMail-Priority: Normal\n";
		$this->headers .= "X-Mailer: MyBB\n";
	
		// For some reason sendmail/qmail doesn't like \r\n
		$sendmail = @ini_get('sendmail_path');
		if($sendmail)
		{
			$this->headers = preg_replace("#(\r\n|\r|\n)#s", "\n", $this->headers);
			$this->message = preg_replace("#(\r\n|\r|\n)#s", "\n", $this->message);
		}
		else
		{
			$this->headers = preg_replace("#(\r\n|\r|\n)#s", "\r\n", $this->headers);
			$this->message = preg_replace("#(\r\n|\r|\n)#s", "\r\n", $this->message);
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
			
			if(!empty($this->from))
			{
				$this->headers .= '\nFrom: '.$this->from;
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