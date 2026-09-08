<?php

namespace Nexor\Cms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Письмо с формы обратной связи.
 *
 * Отдельный Mailable, а не сырая отправка: так письмо видно в тестах, его можно
 * поставить в очередь и подменить своим в приложении.
 */
class FormMessage extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $body  Готовый текст письма
     * @param  bool  $isHtml  Именно `isHtml`: `$html` у Mailable уже занято и без типа
     * @param  array<string, mixed>  $data  Что заполнил посетитель
     */
    public function __construct(
        public string $subjectLine,
        public string $body,
        public bool $isHtml = false,
        public array $data = [],
    ) {}

    public function envelope(): Envelope
    {
        $email = $this->data['email'] ?? null;

        return new Envelope(
            subject: $this->subjectLine,
            // Ответить прямо из почтового клиента, не копируя адрес руками.
            replyTo: $email ? [new Address($email, (string) ($this->data['name'] ?? ''))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->isHtml ? $this->body : nl2br(e($this->body)),
        );
    }
}
