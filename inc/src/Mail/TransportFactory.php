<?php

namespace MyBB\Mail;

class TransportFactory
{
	const DEFAULT_SMTP_PORT = 25;

	const SECURE_SMTP_PORT = 465;

	/**
	 * Create a transport for sending emails.
	 *
	 * @param \MyBB $mybb
	 *
	 * @return \Swift_Transport
	 */
	public static function createTransport(\MyBB $mybb)
	{
		switch ($mybb->settings['mail_handler']) {
			case 'smtp':
				return static::createSmtpTransport($mybb);
			default:
				return static::createSendmailTransport();
		}
	}

	/**
	 * Create an SMTP mail transport for use by SwiftMailer.
	 *
	 * @param \MyBB $mybb
	 *
	 * @return \Swift_SmtpTransport
	 */
	private static function createSmtpTransport(\MyBB $mybb)
	{
		$protocol = null;
		switch ($mybb->settings['secure_smtp']) {
			case MYBB_SSL:
				$protocol = 'ssl';
				break;
			case MYBB_TLS:
				$protocol = 'tls';
				break;
		}

		if (empty($mybb->settings['smtp_host'])) {
			$host = @ini_get('SMTP');
		} else {
			$host = $mybb->settings['smtp_host'];
		}

		$port = static::DEFAULT_SMTP_PORT;
		if (empty($mybb->settings['smtp_port']) && !empty($protocol) && !@ini_get('smtp_port')) {
			$port = static::SECURE_SMTP_PORT;
		} else if (empty($mybb->settings['smtp_port']) && @ini_get('smtp_port')) {
			$port = (int)@ini_get('smtp_port');
		} else if (!empty($mybb->settings['smtp_port'])) {
			$port = (int)$mybb->settings['smtp_port'];
		}

		return (new \Swift_SmtpTransport($host, $port, $protocol))
			->setUsername($mybb->settings['smtp_user'])
			->setPassword($mybb->settings['smtp_pass']);
	}

	/**
	 * Create a Sendmail transport for use by SwiftMailer.
	 *
	 * @return \Swift_SendmailTransport
	 */
	private static function createSendmailTransport()
	{
		$sendMail = @ini_get('sendmail_path');

		if ($sendMail) {
			// We just want the command, no parameters - check for a space
			$indexOfSpace = my_strpos($sendMail, ' ');

			if ($indexOfSpace !== false) {
				$sendMail = my_substr($sendMail, 0, $indexOfSpace);
			}

			return (new \Swift_SendmailTransport("{$sendMail} -bs"));
		} else {
			return new \Swift_SendmailTransport();
		}
	}
}