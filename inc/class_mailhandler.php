<?php
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: class_mailhandler.php 4850 2010-03-31 08:14:21Z RyanGordon $
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
	 * Who the email should return to.
	 *
	 * @var string
	 */
	var $return_email;

	/**
	 * The subject of mail.
	 *
	 * @var string
	 */
	var $subject;
	
	/**
	 * The unaltered subject of mail.
	 *
	 * @var string
	 */
	var $orig_subject;

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
	 * @param string format of the email (HTML, plain text, or both?).
	 * @param string plain text version of the email.
	 * @param string the return email address.
	 */
	function build_message($to, $subject, $message, $from="", $charset="", $headers="", $format="text", $message_text="", $return_email="")
	{
		global $parser, $lang, $mybb;
		
		$this->message = '';
		$this->headers = $headers;

		if($from)
		{
			$this->from = $from;
		}
		else
		{
			$this->from = "";
		}
		
		if($return_email)
		{
			$this->return_email = $return_email;
		}
		else
		{
			$this->return_email = "";
		}

		$this->set_to($to);
		$this->set_subject($subject);
		if($charset)
		{
			$this->set_charset($charset);
		}
		$this->parse_format = $format;
		$this->set_common_headers();
		$this->set_message($message, $message_text);

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
	function set_message($message, $message_text="")
	{		
		if($this->parse_format == "html" || $this->parse_format == "both")
		{
			$this->set_html_headers($message, $message_text);
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
		$this->orig_subject = $this->cleanup($subject);
		$this->subject = $this->utf8_encode($this->orig_subject);
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
	function set_html_headers($message, $message_text="")
	{
		if(!$message_text && $this->parse_format == 'both')
		{
			$message_text = strip_tags($message);
		}
		
		if($this->parse_format == 'both')
		{
			$mime_boundary = "=_NextPart".md5(TIME_NOW);

			$this->headers .= "Content-Type: multipart/alternative; boundary=\"{$mime_boundary}\"{$this->delimiter}";
			$this->message = "This is a multi-part message in MIME format.{$this->delimiter}{$this->delimiter}";

			$this->message .= "--{$mime_boundary}{$this->delimiter}";
			$this->message .= "Content-Type: text/plain; charset=\"{$this->charset}\"{$this->delimiter}";
			$this->message .= "Content-Transfer-Encoding: 8bit{$this->delimiter}{$this->delimiter}";
			$this->message .= $message_text."{$this->delimiter}{$this->delimiter}";

			$this->message .= "--{$mime_boundary}{$this->delimiter}";

			$this->message .= "Content-Type: text/html; charset=\"{$this->charset}\"{$this->delimiter}";
			$this->message .= "Content-Transfer-Encoding: 8bit{$this->delimiter}{$this->delimiter}";
			$this->message .= $message."{$this->delimiter}{$this->delimiter}";

			$this->message .= "--{$mime_boundary}--{$this->delimiter}{$this->delimiter}";
		}
		else
		{
			$this->headers .= "Content-Type: text/html; charset=\"{$this->charset}\"{$this->delimiter}";
			$this->headers .= "Content-Transfer-Encoding: 8bit{$this->delimiter}{$this->delimiter}";
			$this->message = $message."{$this->delimiter}{$this->delimiter}";
		}
	}

	/**
	 * Sets the common headers.
	 */
	function set_common_headers()
	{
		global $mybb;

		// Build mail headers
		if(!trim($this->from))
		{
			if($mybb->settings['mail_handler'] == 'smtp')
			{
				$this->from = $mybb->settings['adminemail'];
			}
			else
			{
				$this->from = '"'.$this->utf8_encode($mybb->settings['bbname']).'"';
				$this->from .= " <{$mybb->settings['adminemail']}>";
			}
		}

		$this->headers .= "From: {$this->from}{$this->delimiter}";
		
		if($this->return_email)
		{
			$this->headers .= "Return-Path: {$this->return_email}{$this->delimiter}";
			$this->headers .= "Reply-To: {$this->return_email}{$this->delimiter}";
		}
		elseif($mybb->settings['returnemail'])
		{
			$this->headers .= "Return-Path: {$mybb->settings['returnemail']}{$this->delimiter}";
			$this->headers .= "Reply-To: {$mybb->settings['adminemail']}{$this->delimiter}";
		}
		else
		{
			$this->headers .= "Return-Path: {$mybb->settings['adminemail']}{$this->delimiter}";
			$this->headers .= "Reply-To: {$mybb->settings['adminemail']}{$this->delimiter}";
		}

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

		$msg_id = md5(uniqid(TIME_NOW)) . "@" . $http_host;

		$this->headers .= "Message-ID: <{$msg_id}>{$this->delimiter}";
		$this->headers .= "Content-Transfer-Encoding: 8bit{$this->delimiter}";
		$this->headers .= "X-Priority: 3{$this->delimiter}";
		$this->headers .= "X-MSMail-Priority: Normal{$this->delimiter}";
		$this->headers .= "X-Mailer: MyBB{$this->delimiter}";
		$this->headers .= "MIME-Version: 1.0{$this->delimiter}";
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
			"subject" => $db->escape_string($this->orig_subject),
			"message" => $db->escape_string($this->message),
			"toaddress" => $db->escape_string($this->to),
			"fromaddress" => $db->escape_string($this->from),
			"dateline" => TIME_NOW,
			"error" => $db->escape_string($error),
			"smtperror" => $db->escape_string($this->data),
			"smtpcode" => intval($this->code)
		);
		$db->insert_query("mailerrors", $mail_error);
		
		// Another neat feature would be the ability to notify the site administrator via email - but wait, with email down, how do we do that? How about private message and hope the admin checks their PMs?
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
	
	/**
	 * Encode a string based on the character set enabled. Used to encode subjects
	 * and recipients in email messages going out so that they show up correctly
	 * in email clients.
	 *
	 * @param string The string to be encoded.
	 * @return string The encoded string.
	 */
	function utf8_encode($string)
	{
		if(strtolower($this->charset) == 'utf-8' && preg_match('/[^\x20-\x7E]/', $string))
		{
			$chunk_size = 47; // Derived from floor((75 - strlen("=?UTF-8?B??=")) * 0.75);
			$len = strlen($string);
			$output = '';
			$pos = 0;

            while($pos < $len)
			{
				$newpos = min($pos + $chunk_size, $len);

				while(ord($string[$newpos]) >= 0x80 && ord($string[$newpos]) < 0xC0)
				{
					// Reduce len until it's safe to split UTF-8.
					$newpos--;
				}

				$chunk = substr($string, $pos, $newpos - $pos);
				$pos = $newpos;

				$output .= " =?UTF-8?B?".base64_encode($chunk)."?=\n";
			}
			return trim($output);
		}
		return $string;
	} 
}
?>
