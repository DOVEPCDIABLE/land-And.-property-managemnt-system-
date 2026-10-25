<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class ZeptoMailTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email || ! config('zeptomail.key')) {
            throw new TransportException('ZeptoMail requires a configured API key and an email message.');
        }
        $address = fn (Address $address): array => ['address' => $address->getAddress(), 'name' => $address->getName()];
        $recipients = fn (array $addresses): array => array_map(fn (Address $item): array => ['email_address' => $address($item)], $addresses);
        $payload = ['from' => $address($email->getFrom()[0]), 'to' => $recipients($email->getTo()), 'subject' => $email->getSubject(), 'htmlbody' => $email->getHtmlBody(), 'textbody' => $email->getTextBody()];
        if ($email->getCc()) {
            $payload['cc'] = $recipients($email->getCc());
        }
        if ($email->getBcc()) {
            $payload['bcc'] = $recipients($email->getBcc());
        }
        if ($email->getReplyTo()) {
            $payload['reply_to'] = array_map($address, $email->getReplyTo());
        }
        foreach ($email->getAttachments() as $attachment) {
            $body = $attachment->getBody();
            if (is_resource($body)) {
                $body = stream_get_contents($body);
            }
            $payload['attachments'][] = ['name' => $attachment->getFilename(), 'mime_type' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(), 'content' => base64_encode($body)];
        }
        $response = Http::asJson()->acceptJson()->withHeaders(['Authorization' => 'Zoho-enczapikey '.config('zeptomail.key')])->connectTimeout(5)->timeout(20)->post(config('zeptomail.url'), array_filter($payload, fn ($value): bool => $value !== null));
        if (! $response->successful() || $response->json('error')) {
            throw new TransportException('ZeptoMail rejected the email (HTTP '.$response->status().').');
        }
    }

    public function __toString(): string
    {
        return 'zeptomail';
    }
}
