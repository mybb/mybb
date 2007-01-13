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
 * SMTP mail handler class.
 */
 
if(!defined('MYBB_SSL'))
{
	define('MYBB_SSL', 1);
}

if(!defined('MYBB_TLS'))
{
	define('MYBB_TLS', 2);
}

class SmtpMail extends MailHandler
{
	/**
	 * The SMTP connection.
	 *
	 * @var resource
	 */
	var $connection;

	/**
	 * SMTP username.
	 *
	 * @var string
	 */
	var $username = '';

	/**
	 * SMTP password.
	 *
	 * @var string
	 */
	var $password = '';

	/**
	 * Hello string sent to the smtp server with either HELO or EHLO.
	 *
	 * @var string
	 */
	var $helo = 'localhost';

	/**
	 * User authenticated or not.
	 *
	 * @var boolean
	 */
	var $authenticated = false;

	/**
	 * How long before timeout.
	 *
	 * @var integer
	 */
	var $timeout = 5;

	/**
	 * SMTP status.
	 *
	 * @var integer
	 */
	var $status = 0;

	/**
	 * SMTP default port.
	 *
	 * @var integer
	 */
	var $port = 25;
	
	/**
	 * SMTP default secure port.
	 *
	 * @var integer
	 */
	var $secure_port = 465;

	/**
	 * SMTP host.
	 *
	 * @var string
	 */
	var $host = '';

	function SmtpMail()
	{
		global $mybb;

		$this->__construct();
	}

	function __construct()
	{
		global $mybb;

		$protocol = '';
		switch($mybb->settings['secure_smtp'])
		{
			case MYBB_SSL:
				$protocol = 'ssl://';
				break;
			case MYBB_TLS:
				$protocol = 'tls://';
				break;
		}

		if(empty($mybb->settings['smtp_host']))
		{
			$this->host = @ini_get('SMTP');
		}
		else
		{
			$this->host = $mybb->settings['smtp_host'];
		}
		
		$this->helo = $this->host;

		$this->host = $protocol . $this->host;

		if(empty($mybb->settings['smtp_port']) && !empty($protocol) && !@ini_get('smtp_port'))
		{
			$this->port = $this->secure_port;
		}
		else if(empty($mybb->settings['smtp_port']) && @ini_get('smtp_port'))
		{
			$this->port = @ini_get('smtp_port');
		}
		else if(!empty($mybb->settings['smtp_port']))
		{
			$this->port = $mybb->settings['smtp_port'];
		}

		if($mybb->settings['smtp_encrypt'] == "yes")
		{
			$this->password = base64_encode($mybb->settings['smtp_pass']);
			$this->username = base64_encode($mybb->settings['smtp_user']);
		}
		else
		{
			$this->password = $mybb->settings['smtp_pass'];
			$this->username = $mybb->settings['smtp_user'];
		}
	}

	/**
	 * Sends the email.
	 *
	 * @return true/false wether or not the email got sent or not.
	 */
	function send()
	{
		global $lang, $mybb, $error_handler;

		$this->check_errors();

		if(!$this->connect())
		{
			return $this->errors;
		}

		if($this->connected())
		{
			$this->send_data('MAIL FROM:<'.$this->from.'>' . $this->delimiter, '250');
			$emails = explode(',', $this->to);
			foreach($emails as $to)
			{
				$this->send_data('RCPT TO:<'.$to.'>' . $this->delimiter, '250');
			}

			if($this->send_data('DATA' . $this->delimiter, '354'))
			{
				$this->send_data('Date: ' . gmdate('r'));
				$this->send_data('To: ' . $this->to);
				$this->send_data(trim($this->headers));
				$this->send_data('Subject: ' . $this->subject);
				$this->send_data($this->delimiter);

				$this->message = preg_replace('#^\.' . $this->delimiter . '#m', '..' . $this->delimiter, $this->message);
				$this->send_data($this->message);

				$this->check_status('250');
			}

			$this->send_data('.', '250');

			$this->send_data('QUIT');
			fclose($this->connection);
			$this->status = 0;

			if($this->check_errors())
			{
				return $this->errors;
			}
			else
			{
				return true;
			}
		}
		else
		{
			return false;
		}
	}

	function connect()
	{
		global $lang, $mybb, $error_handler;

		$this->connection = @fsockopen($this->host, $this->port, $error_number, $error_string, $this->timeout);
		if(function_exists('stream_set_timeout') && substr(PHP_OS, 0, 3) != "WIN")
		{
			@stream_set_timeout($this->connection, $this->timeout, 0);
		}

		if(is_resource($this->connection))
		{
			$this->status = 1;
			$this->check_status('220');

			if(!empty($this->username) && !empty($this->password))
			{
				$this->send_data('EHLO ' . $this->helo . $this->delimiter, '250');
				$this->auth();
			}
			else
			{
				$this->send_data('HELO ' . $this->helo . $this->delimiter, '250');
			}

			if($this->authenticated || !$this->check_errors())
			{
				return true;
			}
			else
			{
				$this->set_error('error_no_connection', $this->get_data(), true);
				$this->status = 0;
				return false;
			}
		}
		else
		{
			$this->set_error('error_no_connection', $error_number . " - " . $error_string, true);
		}
	}

	function auth()
	{
		global $lang, $error_handler, $mybb;

		$this->send_data('AUTH LOGIN' . $this->delimiter, '334');

		$this->send_data($this->username . $this->delimiter, '334');
		$this->send_data($this->password . $this->delimiter, '235');

		if(!$this->check_errors())
		{
			$this->authenticated = true;
			return true;
		}
		return false;
	}

	function get_data()
	{
		$string = '';

		if(function_exists('stream_get_line'))
		{
			while((($line = @stream_get_line($this->connection, 515)) !== false))
			{
				$string .= $line;
				if(substr($line, 3, 1) == ' ')
				{
					break;
				}
			}
		}
		else
		{
			while((($line = @fgets($this->connection, 515)) !== false))
			{
				$string .= $line;
				if(substr($line, 3, 1) == ' ')
				{
					break;
				}
			}
		}
		return $string;
	}

	function connected()
	{
		if($this->status == 1)
		{
			return true;
		}
		return false;
	}

	function send_data($data, $status_num = false)
	{
		if($this->connected())
		{
			if(fwrite($this->connection, $data."\n"))
			{
				if($status_num != false)
				{
					if($this->check_status($status_num))
					{
						return true;
					}
					else
					{
						return false;
					}
				}
				return true;
			}
			else
			{
				$this->set_error('error_data_not_sent', $data);
			}
		}
		return false;
	}

	function check_status($status_num)
	{
		$data = $this->get_data();
		if(substr(trim($data), 0, 3) == $status_num)
		{
			return true;
		}
		else
		{
			$this->set_error('error_status_missmatch', $data);
			return false;
		}
	}
}
?>