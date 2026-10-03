<?php

namespace App\Mail\Transport;

use App\Services\AiGateway\GatewayClient;
use App\Services\AiGateway\GatewayUnavailable;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Laravel mailer that hands each mail to the MatBao relay on the gateway.
 *
 * The website never holds the provider credential: it sends the parts of the
 * message as JSON with its project token, and the gateway sends it from Mắt
 * Bão's verified address. Failures throw TransportException, so a queued mail is
 * retried by the queue like any other transport failure.
 */
class MatbaoGatewayTransport extends AbstractTransport
{
    public function __construct(private readonly GatewayClient $gateway)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $addresses = fn (array $list) => array_map(fn (Address $address) => $address->getAddress(), $list);

        $payload = array_filter([
            'to' => $addresses($email->getTo()),
            'cc' => $addresses($email->getCc()),
            'bcc' => $addresses($email->getBcc()),
            'reply_to' => ($email->getReplyTo()[0] ?? null)?->getAddress(),
            'from_name' => ($email->getFrom()[0] ?? null)?->getName() ?: config('mail.from.name'),
            'subject' => (string) $email->getSubject(),
            'html' => $email->getHtmlBody() !== null ? (string) $email->getHtmlBody() : null,
            'text' => $email->getTextBody() !== null ? (string) $email->getTextBody() : null,
            'attachments' => array_map(fn ($part) => [
                'filename' => $part->getFilename() ?: 'attachment',
                'content' => base64_encode($part->getBody()),
            ], $email->getAttachments()),
        ], fn ($value) => $value !== null && $value !== []);

        try {
            $this->gateway->post('v1/mail/send', [], $payload);
        } catch (GatewayUnavailable $e) {
            throw new TransportException('MatBao mail relay refused the message: '.$e->reason);
        }
    }

    public function __toString(): string
    {
        return 'matbao';
    }
}
