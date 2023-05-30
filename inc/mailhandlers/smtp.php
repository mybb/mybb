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
	public $connection;

	/**
	 * SMTP username.
	 *
	 * @var string
	 */
	public $username = '';

	/**
	 * SMTP password.
	 *
	 * @var string
	 */
	public $password = '';

	/**
	 * Hello string sent to the smtp server with either HELO or EHLO.
	 *
	 * @var string
	 */
	public $helo = 'localhost';

	/**
	 * User authenticated or not.
	 *
	 * @var boolean
	 */
	public $authenticated = false;

	/**
	 * How long before timeout.
	 *
	 * @var integer
	 */
	public $timeout = 5;

	/**
	 * SMTP status.
	 *
	 * @var integer
	 */
	public $status = 0;

	/**
	 * SMTP default port.
	 *
	 * @var integer
	 */
	public $port = 25;

	/**
	 * SMTP default secure port.
	 *
	 * @var integer
	 */
	public $secure_port = 465;

	/**
	 * SMTP host.
	 *
	 * @var string
	 */
	public $host = '';

	/**
	 * The last received error message from the SMTP server.
	 *
	 * @var string
	 */
	public $last_error = '';

	/**
	 * Are we keeping the connection to the SMTP server alive?
	 *
	 * @var boolean
	 */
	public $keep_alive = false;

	/**
	 * Whether to use TLS encryption.
	 *
	 * @var boolean
	 */
	public $use_tls = false;

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
				$this->use_tls = true;
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

		$local = array('127.0.0.1', '::1', 'localhost');
		if(!in_array($this->host, $local))
		{
			if(function_exists('gethostname') && gethostname() !== false)
			{
				$this->helo = gethostname();
			}
			elseif(function_exists('php_uname'))
			{
				$helo = php_uname('n');
				if(!empty($helo))
				{
					$this->helo = $helo;
				}
			}
			elseif(!empty($_SERVER['SERVER_NAME']))
			{
				$this->helo = $_SERVER['SERVER_NAME'];
			}
		}

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

		$this->password = $mybb->settings['smtp_pass'];
		$this->username = $mybb->settings['smtp_user'];
	}

	/**
	 * Sends the email.
	 *
	 * @return bool whether or not the email got sent or not.
	 */
	function send()
	{
		global $lang, $mybb;

		if(!$this->connected())
		{
			if(!$this->connect())
			{
				$this->close();
			}
		}

		if($this->connected())
		{
			if(!$this->send_data('MAIL FROM:<'.$this->from.'>', 250))
			{
				$this->fatal_error("The mail server does not understand the MAIL FROM command. Reason: ".$this->get_error());
				return false;
			}

			// Loop through recipients
			$emails = explode(',', $this->to);
			foreach($emails as $to)
			{
				$to = trim($to);
				if(!$this->send_data('RCPT TO:<'.$to.'>', 250))
				{
					$this->fatal_error("The mail server does not understand the RCPT TO command. Reason: ".$this->get_error());
					return false;
				}
			}

			if($this->send_data('DATA', 354))
			{
				$this->send_data('Date: ' . gmdate('r'));
				$this->send_data('To: ' . $this->to);

				$this->send_data('Subject: ' . $this->subject);

				// Only send additional headers if we've got any
				if(trim($this->headers))
				{
					$this->send_data(trim($this->headers));
				}

				$this->send_data("");

				// Queue the actual message
				$this->message = str_replace("\n.", "\n..", $this->message);
				$this->send_data($this->message);
			}
			else
			{
				$this->fatal_error("The mail server did not understand the DATA command");
				return false;
			}

			if(!$this->send_data('.', 250))
			{
				$this->fatal_error("Mail may not be delivered. Reason: ".$this->get_error());
			}

			if(!$this->keep_alive)
			{
				$this->close();
			}
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Connect to the SMTP server.
	 *
	 * @return boolean True if connection was successful
	 */
	function connect()
	{
		global $lang, $mybb;

		$this->connection = @fsockopen($this->host, $this->port, $error_number, $error_string, $this->timeout);

		// DIRECTORY_SEPARATOR checks if running windows
		if(is_resource($this->connection) && function_exists('stream_set_timeout') && DIRECTORY_SEPARATOR != '\\')
		{
			@stream_set_timeout($this->connection, $this->timeout, 0);
		}

		if(is_resource($this->connection))
		{
			$this->status = 1;
			$this->get_data();
			if(!$this->check_status('220'))
			{
				$this->fatal_error("The mail server is not ready, it did not respond with a 220 status message.");
				return false;
			}

			if($this->use_tls || (!empty($this->username) && !empty($this->password)))
			{
				$helo = 'EHLO';
			}
			else
			{
				$helo = 'HELO';
			}

			$data = $this->send_data("{$helo} {$this->helo}", 250);
			if(!$data)
			{
				$this->fatal_error("The server did not understand the {$helo} command");
				return false;
			}

			if($this->use_tls && preg_match("#250( |-)STARTTLS#mi", $data))
			{
				if(!$this->send_data('STARTTLS', 220))
				{
					$this->fatal_error("The server did not understand the STARTTLS command. Reason: ".$this->get_error());
					return false;
				}
				if(!@stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
				{
					$this->fatal_error("Failed to start TLS encryption");
					return false;
				}
				// Resend EHLO to get updated service list
				$data = $this->send_data("{$helo} {$this->helo}", 250);
				if(!$data)
				{
					$this->fatal_error("The server did not understand the EHLO command");
					return false;
				}
			}

			if(!empty($this->username) && !empty($this->password))
			{
				if(!preg_match("#250( |-)AUTH( |=)(.+)$#mi", $data, $matches))
				{
					$this->fatal_error("The server did not understand the AUTH command");
					return false;
				}
				if(!$this->auth($matches[3]))
				{
					return false;
				}
			}
			return true;
		}
		else
		{
			$this->fatal_error("Unable to connect to the mail server with the given details. Reason: {$error_number}: {$error_string}");
			return false;
		}
	}

	/**
	 * Authenticate against the SMTP server.
	 *
	 * @param string $auth_methods A list of authentication methods supported by the server
	 * @return boolean True on success
	 */
	function auth($auth_methods)
	{
		global $lang, $mybb;

		$auth_methods = explode(" ", trim($auth_methods));

		if(in_array("LOGIN", $auth_methods))
		{
			if(!$this->send_data("AUTH LOGIN", 334))
			{
				if($this->code == 503)
				{
					return true;
				}
				$this->fatal_error("The SMTP server did not respond correctly to the AUTH LOGIN command");
				return false;
			}

			if(!$this->send_data(base64_encode($this->username), 334))
			{
				$this->fatal_error("The SMTP server rejected the supplied SMTP username. Reason: ".$this->get_error());
				return false;
			}

			if(!$this->send_data(base64_encode($this->password), 235))
			{
				$this->fatal_error("The SMTP server rejected the supplied SMTP password. Reason: ".$this->get_error());
				return false;
			}
		}
		else if(in_array("PLAIN", $auth_methods))
		{
			if(!$this->send_data("AUTH PLAIN", 334))
			{
				if($this->code == 503)
				{
					return true;
				}
				$this->fatal_error("The SMTP server did not respond correctly to the AUTH PLAIN command");
				return false;
			}
			$auth = base64_encode(chr(0).$this->username.chr(0).$this->password);
			if(!$this->send_data($auth, 235))
			{
				$this->fatal_error("The SMTP server rejected the supplied login username and password. Reason: ".$this->get_error());
				return false;
			}
		}
		else if(in_array("CRAM-MD5", $auth_methods))
		{
			$data = $this->send_data("AUTH CRAM-MD5", 334);
			if(!$data)
			{
				if($this->code == 503)
				{
					return true;
				}
				$this->fatal_error("The SMTP server did not respond correctly to the AUTH CRAM-MD5 command");
				return false;
			}

			$challenge = base64_decode(substr($data, 4));
			$auth = base64_encode($this->username.' '.$this->cram_md5_response($this->password, $challenge));

			if(!$this->send_data($auth, 235))
			{
				$this->fatal_error("The SMTP server rejected the supplied login username and password. Reason: ".$this->get_error());
				return false;
			}
		}
		else
		{
			$this->fatal_error("The SMTP server does not support any of the AUTH methods that MyBB supports");
			return false;
		}

		// Still here, we're authenticated
		return true;
	}

	/**
	 * Fetch data from the SMTP server.
	 *
	 * @return string The data from the SMTP server
	 */
	function get_data()
	{
		$string = '';

		while((($line = fgets($this->connection, 515)) !== false))
		{
			$string .= $line;
			if(substr($line, 3, 1) == ' ')
			{
				break;
			}
		}
		$string = trim($string);
		$this->data = $string;
		$this->code = substr($this->data, 0, 3);
		return $string;
	}

	/**
	 * Check if we're currently connected to an SMTP server
	 *
	 * @return boolean true if connected
	 */
	function connected()
	{
		if($this->status == 1)
		{
			return true;
		}
		return false;
	}

	/**
	 * Send data through to the SMTP server.
	 *
	 * @param string $data The data to be sent
	 * @param int|bool $status_num The response code expected back from the server (if we have one)
	 * @return boolean True on success
	 */
	function send_data($data, $status_num = false)
	{
		if($this->connected())
		{
			if(fwrite($this->connection, $data."\r\n"))
			{
				if($status_num != false)
				{
					$rec = $this->get_data();
					if($this->check_status($status_num))
					{
						return $rec;
					}
					else
					{
						$this->set_error($rec);
						return false;
					}
				}
				return true;
			}
			else
			{
				$this->fatal_error("Unable to send the data to the SMTP server");
				return false;
			}
		}
		return false;
	}

	/**
	 * Checks if the received status code matches the one we expect.
	 *
	 * @param int $status_num The status code we expected back from the server
	 * @return string|bool
	 */
	function check_status($status_num)
	{
		if($this->code == $status_num)
		{
			return $this->data;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Close the connection to the SMTP server.
	 */
	function close()
	{
		if($this->status == 1)
		{
			$this->send_data('QUIT');
			fclose($this->connection);
			$this->status = 0;
		}
	}

	/**
	 * Get the last error message response from the SMTP server
	 *
	 * @return string The error message response from the SMTP server
	 */
	function get_error()
	{
		if(!$this->last_error)
		{
			$this->last_error = "N/A";
		}

		return $this->last_error;
	}

	/**
	 * Set the last error message response from the SMTP server
	 *
	 * @param string $error The error message response
	 */
	function set_error($error)
	{
		$this->last_error = $error;
	}

	/**
	 * Generate a CRAM-MD5 response from a server challenge.
	 *
	 * @param string $password Password.
	 * @param string $challenge Challenge sent from SMTP server.
	 *
	 * @return string CRAM-MD5 response.
	 */
	function cram_md5_response($password, $challenge)
	{
		if(strlen($password) > 64)
		{
			$password = pack('H32', md5($password));
		}

		if(strlen($password) < 64)
		{
			$password = str_pad($password, 64, chr(0));
		}

		$k_ipad = substr($password, 0, 64) ^ str_repeat(chr(0x36), 64);
		$k_opad = substr($password, 0, 64) ^ str_repeat(chr(0x5C), 64);

		$inner = pack('H32', md5($k_ipad.$challenge));

		return md5($k_opad.$inner);
	}
}
