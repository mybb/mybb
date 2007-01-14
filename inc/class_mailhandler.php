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
	 * The currently used delimiter new lines.
	 *
	 * @var string.
	 */
	var $delimiter = "\r\n";

	/**
	 * How it should parse the email (HTML or plain text?)
	 *
	 * @var array
	 */
	var $parse_format = 'text';

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
		
		$this->headers = preg_replace("#(\r\n|\r|\n)#s", $this->delimiter, $this->headers);
		$this->message = preg_replace("#(\r\n|\r|\n)#s", $this->delimiter, $this->message);

		$this->headers = $headers;
		$this->from = $from;

		$this->set_to($to);
		$this->set_subject($subject);
		$this->set_charset($charset);
		$this->set_common_headers();
		$this->set_message($message);

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
	 * Sets and formats the email message.
	 *
	 * @param string message
	 */
	function set_message($message)
	{		
		if($this->parse_format == "html")
		{
			$this->set_html_headers($message);
		}
		else
		{
			$this->message = $message;
			$this->set_plain_headers();
		}
	}

	/**
	 * Sets and formats the email subject.
	 *
	 * @param string subject
	 */
	function set_subject($subject)
	{
		$this->subject = $this->cleanup($subject);
	}

	/**
	 * Sets and formats the recipient address.
	 *
	 * @param string to
	 */
	function set_to($to)
	{
		$to = $this->cleanup($to);

		$this->to = $this->cleanup($to);
	}

	/**
	 * Sets the plain headers, text/plain
	 */
	function set_plain_headers()
	{
		$this->headers .= "Content-Type: text/plain; charset={$this->charset}{$this->delimiter}";
	}

	/**
	 * Sets the alternative headers, text/html and text/plain.
	 *
	 * @param string message
	 */
	function set_html_headers($message)
	{
		$mime_boundary = "----=_NextPart".md5(time());

		$this->headers .= "Content-Type: multipart/alternative; boundary=\"{$mime_boundary}\"{$this->delimiter}{$this->delimiter}";
		$this->message = "This is a multi-part message in MIME format.{$this->delimiter}{$this->delimiter}";

		$this->message .= "--{$mime_boundary}{$this->delimiter}";
		$this->message .= "Content-Type: text/plain; charset=\"{$this->charset}\"{$this->delimiter}";
		$this->message .= "Content-Transfer-Encoding: 8bit{$this->delimiter}{$this->delimiter}";
		$this->message .= strip_tags($message)."{$this->delimiter}{$this->delimiter}";
		
		$this->message .= "--{$mime_boundary}{$this->delimiter}{$this->delimiter}";
		$this->message .= "Content-Type: text/html; charset=\"{$this->charset}\"{$this->delimiter}";
		$this->message .= "Content-Transfer-Encoding: quoted-printable{$this->delimiter}{$this->delimiter}";
		$this->message .= $message."{$this->delimiter}{$this->delimiter}";
		
		$this->message .= "--{$mime_boundary}--{$this->delimiter}{$this->delimiter}";
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

		$this->headers .= "From: {$this->from}{$this->delimiter}";
		$this->headers .= "Return-Path: {$mybb->settings['adminemail']}{$this->delimiter}";

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

		$this->headers .= "Message-ID: <{$msg_id}>{$this->delimiter}";
		$this->headers .= "MIME-Version: 1.0{$this->delimiter}";
		$this->headers .= "Content-Transfer-Encoding: 8bit{$this->delimiter}";
		$this->headers .= "X-Priority: 3{$this->delimiter}";
		$this->headers .= "X-MSMail-Priority: Normal{$this->delimiter}";
		$this->headers .= "X-Mailer: MyBB{$this->delimiter}";
	}
	
	/**
	 * Log a fatal error message to the database.
	 *
	 * @param string The error message
	 * @param string Any additional information
	 */
	function fatal_error($error)
	{
		global $db;
		
		$mail_error = array(
			"subject" => $db->escape_string($this->subject),
			"toaddress" => $db->escape_string($this->to),
			"fromaddress" => $db->escape_string($this->from),
			"dateline" => time(),
			"error" => $db->escape_string($error),
			"smtperror" => $db->escape_string($this->data),
			"smtpcode" => intval($this->code)
		);
		$db->insert_query(TABLE_PREFIX."mailerrors", $mail_error);
		
		// Another neat feature would be the ability to notify the site administrator via email - but wait, with email down, how do we do that?
	}
	
	/**
	 * Rids pesky characters from subjects, recipients, from addresses etc (prevents mail injection too)
	 *
	 * @param string The string being checked
	 * @return string The cleaned string
	 */
	function cleanup($string)
	{
		$string = str_replace(array("\r", "\n", "\r\n"), "", $string);
		$string = trim($string);
		return $string;
	}
}
?>