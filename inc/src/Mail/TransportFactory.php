<?php

namespace MyBB\Mail;

/**
 * Factory to build a Swiftmailer transport based upon settings.
 */
class TransportFactory
{
    const SSL_ENCRYPTION = 1;

    const TLS_ENCRYPTION = 2;

    public static function build(array $settings): \Swift_Transport
    {
        switch ($settings['mail_handler']) {
            case 'smtp':
                return static::buildSmtpTransport($settings);
            break;
            case 'sendmail':
            default:
                return static::buildSendMailTransport($settings);
                break;
        }
    }

    private static function buildSmtpTransport(array $settings): \Swift_Transport
    {
        switch ($settings['secure_smtp']) {
            case static::SSL_ENCRYPTION:
                $protocol = 'ssl';
                break;
            case static::TLS_ENCRYPTION:
                $protocol = 'tls';
                break;
            default:
                $protocol = '';
                break;
        }

        if (empty($settings['smtp_host'])) {
            $host = @ini_get('SMTP');
        } else {
            $host = $settings['smtp_host'];
        }

        if (empty($settings['smtp_port']) && !empty($protocol) && !@ini_get('smtp_port')) {
            $port = 465;
        } elseif (empty($settings['smtp_port']) && @ini_get('smtp_port')) {
            $port = (int) @ini_get('smtp_port');
        } elseif (!empty($settings['smtp_port'])) {
            $port = (int) $settings['smtp_port'];
        }

        $transport = new \Swift_SmtpTransport($host, $port, $protocol);

        if (!empty($settings['smtp_user'])) {
            $transport = $transport->setUsername($settings['smtp_user']);
        }

        if (!empty($settings['smtp_pass'])) {
            $transport = $transport->setPassword($settings['smtp_pass']);
        }

        return $transport;
    }

    private static function buildSendMailTransport(array $settings): \Swift_Transport
    {
        $iniSendMailPath = @ini_get('sendmail_path');
        if (!empty($settings['sendmail_path'])) {
            $sendMailPath = $settings['sendmail_path'];
        } elseif (!empty($iniSendMailPath)) {
            $sendMailPath = $iniSendMailPath;
        } else {
            $sendMailPath = '/usr/sbin/sendmail -bs';
        }

        if (false === strpos($sendMailPath, ' -bs') && false === strpos($sendMailPath, ' -t')) {
            // The sendmail transport requires either the -bs flag or the -t flag.
            $sendMailPath .= ' -bs';
        }

        return new \Swift_SendmailTransport($sendMailPath);
    }
}
