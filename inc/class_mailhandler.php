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
 * Base mail handler class.
 */
class MailHandler
{
	/**
	 * Which email it should send to.
	 *
	 * @var string
	 */
	var $to;

	/**
	 * 1/0 value weather it should show errors or not.
	 *
	 * @var integer
	 */
	var $show_errors = 1;

	/**
	 * Who it is from.
	 *
	 * @var string
	 */
	var $from;

	/**
	 * The subject of mail.
	 *
	 * @var string
	 */
	var $subject;

	/**
	 * The message of the mail.
	 *
	 * @var string
	 */
	var $message;

	/**
	 * The headers of the mail.
	 *
	 * @var string
	 */
	var $headers;

	/**
	 * The charset of the mail.
	 *
	 * @var string
	 * @default utf-8
	 */
	var $charset = "utf-8";

	/**
	 * The errors that occurred when handling email.
	 *
	 * @var array
	 */
	var $errors = array();

	/**
	 * The currently used delimiter new lines.
	 *
	 * @var string.
	 */
	var $delimiter = "\r\n";

	/**
	 * How it should parse the email.
	 *
	 * @var array
	 */
	var $parser_options = array(
		'allow_html' => 'yes',
		'filter_badwords' => 'yes',
		'allow_mycode' => 'yes',
		'allow_smilies' => 'yes',
		'allow_imgcode' => 'yes'
	);

	/**
	 * Will be set if sendmail is enable on the server.
	 *
	 * @var string
	 */
	var $sendmail;

	/**
	 * Builds the whole mail.
	 * To be used by the different email classes later.
	 *
	 * @param string to email.
	 * @param string subject of email.
	 * @param string message of email.
	 * @param string from email.
	 * @param string charset of email.
	 * @param string headers of email.
	 */
	function build_message($to, $subject, $message, $from="", $charset="", $headers="")
	{
		global $parser, $lang, $mybb;
		
		// For some reason sendmail/qmail doesn't like \r\n
		$this->sendmail = @ini_get('sendmail_path');
		if($this->sendmail)
		{
			$this->delimiter = "\n";
		}
		else
		{
			$this->delimiter = "\r\n";
		}

		$this->headers = $headers;
		$this->from = $from;

		$this->set_to($to);
		$this->set_subject($subject);
		$this->set_charset($charset);
		$this->set_common_headers();
		$this->set_message($message);

		$this->headers = preg_replace("#(\r\n|\r|\n)#s", $this->delimiter, $headers);
		$this->message = preg_replace("#(\r\n|\r|\n)#s", $this->delimiter, $this->message);
	}

	/**
	 * Sets the charset.
	 *
	 * @param string charset
	 */
	function set_charset($charset)
	{
		global $lang;

		if(empty($charset))
		{
			$this->charset = $lang->settings['charset'];
		}
		else
		{
			$this->charset = $charset;
		}
	}

	/**
	 * Checks if message is not a empty string and formats it.
	 *
	 * @param string message
	 */
	function set_message($message)
	{		
		if(trim($message) == '')
		{
			$this->set_error('error_no_message');
		}
		else
		{
			if($this->parser_options['allow_html'] == 'yes')
			{
				$this->set_html_headers($message);
			}
			else
			{
				$this->message = $message;
				$this->set_plain_headers();
			}
		}
	}

	/**
	 * Checks if subject is not a empty string.
	 *
	 * @param string subject
	 */
	function set_subject($subject)
	{
		if(trim($subject) == '')
		{
			$this->set_error('error_no_subject');
		}
		else
		{
			$this->subject = $subject;
		}
	}

	/**
	 * Checks if to is not a empty string.
	 *
	 * @param string to
	 */
	function set_to($to)
	{
		if(trim($to) == '')
		{
			$this->set_error('error_no_recipient');
		}
		else
		{
			$this->to = $to;
		}
	}

	/**
	 * Sets the plain headers, text/plain
	 */
	function set_plain_headers()
	{
		$this->headers .= "Content-Type: text/plain; charset=\"{$this->charset}\"\n";
	}

	/**
	 * Sets the alternative headers, text/html and text/plain.
	 *
	 * @param string message
	 */
	function set_html_headers($message)
	{
		global $parser;
		
		if(!is_object($parser))
		{
			require_once MYBB_ROOT."inc/class_parser.php";
			$parser = new postParser;
		}

		$mime_boundary = md5(time());

		$this->headers .= "Content-Type: multipart/alternative; boundary=\"{$mime_boundary}\"\n";
		$this->message = "--{$mime_boundary}\n";
		$this->message .= "Content-Type: text/plain; charset=\"{$this->charset}\"\n";
		$this->message .= "Content-Transfer-Encoding: 8bit\n";
		$this->message .= $message."\n\n";
		$this->message .= "--{$mime_boundary}\n";
		$this->message .= "Content-Type: text/html; charset=\"{$this->charset}\"\n";
		$this->message .= "Content-Transfer-Encoding: 8bit\n";
		$this->message .= $parser->parse_message($message, $this->parser_options)."\n\n";
		$this->message .= "--{$mime_boundary}--\n\n";
	}

	/**
	 * Sets the common headers.
	 */
	function set_common_headers()
	{
		global $mybb;

		// Build mail headers
		if(trim($this->from) == '')
		{
			$this->from = "\"{$mybb->settings['bbname']} Mailer\" <{$mybb->settings['adminemail']}>";
		}

		$this->headers .= "From: {$this->from}\n";
		$this->headers .= "Return-Path: {$mybb->settings['adminemail']}\n";

		if(isset($_SERVER['SERVER_NAME']))
		{
			$http_host = $_SERVER['SERVER_NAME'];
		}
		else if(isset($_SERVER['HTTP_HOST']))
		{
			$http_host = $_SERVER['HTTP_HOST'];
		}
		else
		{
			$http_host = "unknown.local";
		}

		$msg_id = md5(uniqid(time())) . "@" . $http_host;

		$this->headers .= "Message-ID: <{$msg_id}>\n";
		$this->headers .= "MIME-Version: 1.0\n";
		$this->headers .= "Content-Transfer-Encoding: 8bit\n";
		$this->headers .= "X-Priority: 3\n";
		$this->headers .= "X-MSMail-Priority: Normal\n";
		$this->headers .= "X-Mailer: MyBB\n";
	}

	/**
	 * Append a error to the errors array.
	 *
	 * @param string language error
	 * @param string additional error
	 * @param boolean if it should halt or not.
	 */
	function set_error($lang_error, $error = '', $halt = false)
	{
		if($this->show_errors == 1)
		{
			$this->errors[] = array(
				'lang_error' => $lang_error,
				'error' => $error
			);

			if($halt)
			{
				$this->check_errors(true);
			}
		}
	}

	/**
	 * Returns any errors unless the script has denifed otherwise.
	 */
	function check_errors($halt = false)
	{
		if(!empty($this->errors) && $this->show_errors == 1)
		{
			global $lang;

			$lang->load('mailhandler', true);

			$errors = "";
			foreach($this->errors as $key => $error)
			{
				if(!empty($error['error']) && isset($lang->$error['lang_error']))
				{
					$error_message = $lang->$error['lang_error'] . $error['error'];
				}
				else if(!empty($error['lang_error']))
				{
					$error_message = $lang->$error['lang_error'];
				}
				else
				{
					$error_message = $error['error'];
				}

				$errors .= "<li>{$error_message}</li>\n";
			}

			/*
			 * Only temporary..
			 */
			if(defined("IN_ADMINCP"))
			{
				cperror("{$lang->error_occurred}<ul>\n{$errors}</ul>");
			}

			if(!is_object($templates) || !method_exists($templates, 'get'))
			{
				$this->errors = "{$errors}</ul>\n";
			}
			else
			{
				error($errors, $lang->error_occurred);
			}

			return true;
		}
		return false;
	}
}
?>