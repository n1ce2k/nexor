<?php

namespace Nexor\Cms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Письмо с формы обратной связи (сущности «Формы ОС») — с файлами во вложениях.
 */
class FeedbackSubmissionMessage extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{path: string, name: string, size: int}>  $files
     * @param  array<int, string>  $bccAddresses
     */
    public function __construct(
        public string $subjectLine,
        public string $body,
        public bool $isHtml = false,
        public array $files = [],
        public string $disk = 'local',
        public ?string $fromAddress = null,
        public ?string $replyToAddress = null,
        public array $bccAddresses = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromAddress && filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL) ? new Address($this->fromAddress) : null,
            replyTo: $this->replyToAddress && filter_var($this->replyToAddress, FILTER_VALIDATE_EMAIL) ? [new Address($this->replyToAddress)] : [],
            bcc: $this->bccAddresses,
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->isHtml ? $this->body : nl2br(e($this->body)),
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $file) => Attachment::fromStorageDisk($this->disk, $file['path'])->as($file['name']),
            $this->files,
        );
    }
}
