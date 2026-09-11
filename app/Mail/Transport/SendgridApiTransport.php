<?php

namespace App\Mail\Transport;

use SendGrid;
use SendGrid\Mail\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class SendgridApiTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $mail = new Mail();

        $froms = $email->getFrom();
        $from  = $froms[0];
        $mail->setFrom($from->getAddress(), $from->getName() ?: null);

        $mail->setSubject($email->getSubject() ?? '');

        foreach ($email->getTo() as $to) {
            $mail->addTo($to->getAddress(), $to->getName() ?: null);
        }

        foreach ($email->getCc() as $cc) {
            $mail->addCc($cc->getAddress(), $cc->getName() ?: null);
        }

        foreach ($email->getBcc() as $bcc) {
            $mail->addBcc($bcc->getAddress(), $bcc->getName() ?: null);
        }

        if ($text = $email->getTextBody()) {
            $mail->addContent('text/plain', $text);
        }

        if ($html = $email->getHtmlBody()) {
            $mail->addContent('text/html', $html);
        }

        $sg       = new SendGrid($this->apiKey);
        $response = $sg->send($mail);

        if ($response->statusCode() >= 400) {
            throw new \RuntimeException(
                'SendGrid API error ' . $response->statusCode() . ': ' . $response->body()
            );
        }
    }

    public function __toString(): string
    {
        return 'sendgrid+api://api.sendgrid.com';
    }
}
