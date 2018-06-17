<?php

namespace MyBB\Mail;

class MessageBuilder
{
    /**
     * Plain text format email.
     */
    const FORMAT_PLAIN = 'plain';

    /**
     * HTML format email.
     */
    const FORMAT_HTML = 'html';

    /**
     * HTML format email with a plain text part associated.
     */
    const FORMAT_BOTH = 'both';

    /**
     * @var \MyBB $mybb
     */
    protected $mybb;

    /**
     * @var \MyLanguage $lang
     */
    protected $lang;

    public function __construct(\MyBB $mybb, \MyLanguage $lang)
    {
        $this->mybb = $mybb;
        $this->lang = $lang;
    }

    /**
     * Trim an email name, including removing the wrapping `<>`.
     *
     * @param string $name The name to trim.
     *
     * @return string The trimmed name.
     */
    protected static function trimName(string $name) : string
    {
        return trim($name, " \t\n\r\0\x0B<>");
    }

    /**
     * Build the to address from a string.
     *
     * If the string is in the format `address <name>`, an array [address => name] will be returned.
     *
     * Otherwise, the returned array will only contain the address.
     *
     * @param string $to
     *
     * @return array The to address as an array.
     * If a name is associated, the array will be in the form [address => name].
     */
    protected function buildTo(string $to): array
    {
        $to = trim($to);

        // Split the address by spaces, to handle the case of 'address <name>'.
        $addressParts = explode(' ', $to, 2);

        if (count($addressParts) === 2) {
            $to = trim($addressParts[0]);
            $toName = static::trimName(implode(' ', $addressParts[1]));

            return [$to => $toName];
        } else {
            return [$to];
        }
    }

    /**
     * Build an email address/name pair based upon an optional string.
     *
     * If the string is null or empty, the board settings will be used to determine the address.
     *
     * The name is either the board name, or the name found in `$address` if it takes the form `email <name>`.
     *
     * @param null|string $address An optional address to use.
     *
     * @return array An array of [`addres` => `name`].
     */
    protected function buildAddressWithBoardFallback(?string $address) : array
    {
        $address = trim($address);

        if (empty($address)) {
            if (trim($this->mybb->settings['returnemail'])) {
                $address = $this->mybb->settings['returnemail'];
            } else {
                $address = $this->mybb->settings['adminemail'];
            }

            $addressName = $this->mybb->settings['bbname'];
        } else {
            // Split the address by spaces, to handle the case of 'address <name>'.
            $addressParts = explode(' ', $address, 2);

            if (count($addressParts) === 2) {
                $address = trim($addressParts[0]);
                $addressName = static::trimName(implode(' ', $addressParts[1]));
            } else {
                $addressName = $this->mybb->settings['bbname'];
            }
        }

        return [$address => $addressName];
    }

    /**
     * Build a message object to send.
     *
     * @param string $to The email address to send the message to.
     * May be either a plain email address `address@domain.tld` or an email and name pair `address@domain.tld <name>`.
     * @param string $subject The subject of the message to send.
     * @param string $body The body of the message to send.
     * If this body is HTML, then the `$format` parameter must be set to `FORMAT_HTML` or `FORMAT_BOTH`.
     * @param null|string $from The from address to send the email from.
     * May be either a plain email address `address@domain.tld` or an email and name pair `address@domain.tld <name>`.
     * If this is null, the board settings are used instead.
     * @param null|string $charset The character set to use for the language.
     * If this is null, the character set for the current language is used instead.
     * @param null|array $headers An array of optional headers in key/value format.
     * @param string $format The format of the message. Should be one of the `MessageBuilder::FORMAT_*` constants.
     * @param null|string $messageText The plain text of the message, if the `format` is `FORMAT_HTML` or `FORMAT_BOTH`.
     * If this is null and the format is `FORMAT_BOTH`, the plain text will be built by stripping tags from the `body`.
     * @param null|string $replyTo The reply to email.
     * May be either a plain email address `address@domain.tld` or an email and name pair `address@domain.tld <name>`.
     * If this is null, the board settings are used instead.
     *
     * @return \Swift_Message The created message object to send.
     */
    public function build(
        string $to,
        string $subject,
        string $body,
        ?string $from = null,
        ?string $charset = null,
        ?array $headers = null,
        string $format = MessageBuilder::FORMAT_PLAIN,
        ?string $messageText = null,
        ?string $replyTo = null
    ) : \Swift_Message {
        $message = (new \Swift_Message())
            ->setTo($this->buildTo($to))
            ->setFrom($this->buildAddressWithBoardFallback($from))
            ->setReplyTo($this->buildAddressWithBoardFallback($replyTo))
            ->setSubject($subject);

        if (!empty($charset)) {
            $message = $message->setCharset($charset);
        } else {
            $message = $message->setCharset($this->lang->settings['charset']);
        }

        if ($format === static::FORMAT_HTML || $format === static::FORMAT_BOTH) {
            $message = $message->setBody($body, 'text/html');

            if ($format === static::FORMAT_BOTH) {
                if (empty($messageText)) {
                    $messageText = strip_tags($body);
                }

                $message->addPart($messageText, 'text/plain');
            }
        } else {
            $message = $message->setBody($body, 'text/plain');
        }

        if (!empty($headers)) {
            /** @var \Swift_Mime_SimpleHeaderSet $messageHeaders */
            $messageHeaders = $message->getHeaders();

            foreach ($headers as $key => $value) {
                $messageHeaders->addTextHeader($key, $value);
            }
        }

        return $message;
    }
}
