<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $notificationType;
    public ?string $customMessage;
    public array $testData;

    /**
     * Create a new message instance.
     */
    public function __construct(string $notificationType = 'test', ?string $customMessage = null, array $testData = [])
    {
        $this->notificationType = $notificationType;
        $this->customMessage = $customMessage;
        $this->testData = $testData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->notificationType) {
            'booking' => 'Тестовое уведомление о бронировании',
            'payment' => 'Тестовое уведомление об оплате',
            'system' => 'Тестовое системное уведомление',
            default => 'Тестовое уведомление',
        };

        return new Envelope(
            subject: $subject,
            tags: ['test', 'notification', 'system'],
            metadata: [
                'test_id' => uniqid('test_', true),
                'type' => $this->notificationType,
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.test',
            with: [
                'notificationType' => $this->notificationType,
                'customMessage' => $this->customMessage,
                'testData' => $this->testData,
                'timestamp' => now()->format('d.m.Y H:i:s'),
                'systemInfo' => [
                    'app_name' => config('app.name'),
                    'env' => config('app.env'),
                    'url' => config('app.url'),
                ],
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->markdown('emails.notifications.test')
            ->with([
                'notificationType' => $this->notificationType,
                'customMessage' => $this->customMessage,
                'testData' => $this->testData,
            ]);
    }
}
