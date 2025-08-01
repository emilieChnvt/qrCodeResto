<?php

namespace App\Service;

use Mailgun\Mailgun;

class MailgunService
{
    private string $domain;
    private string $apiKey;

    public function __construct(string $domain, string $apiKey)
    {
        $this->domain = $domain;
        $this->apiKey = $apiKey;
    }

    public function send(string $to, string $subject, string $text)
    {
        $mg = Mailgun::create($this->apiKey, 'https://api.eu.mailgun.net');
        $mg->messages()->send($this->domain, [
            'from'    => 'Emilie <postmaster@' . $this->domain . '>',
            'to'      => $to,
            'subject'  => $subject,
            'text'     => $text,
        ]);
    }

}
