<?php

namespace App\Mail;

use App\Models\SuggestedQuestion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuestionSubmittedConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SuggestedQuestion $suggestedQuestion,
        public ?User $user = null,
        public ?string $email = null
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ваш вопрос получен',
            tags: ['faq', 'confirmation', 'support'],
            metadata: [
                'question_id' => (string) $this->suggestedQuestion->id,
                'tracking_code' => 'FAQ-' . str_pad($this->suggestedQuestion->id, 6, '0', STR_PAD_LEFT),
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.faq.submission-confirmation',
            with: [
                'question' => $this->suggestedQuestion,
                'user' => $this->user,
                'email' => $this->email,
                'trackingCode' => 'FAQ-' . str_pad($this->suggestedQuestion->id, 6, '0', STR_PAD_LEFT),
                'estimatedReviewTime' => '1-3 рабочих дня',
                'faqUrl' => route('faq.index'),
                'contactUrl' => route('contact'),
                'statusCheckUrl' => route('faq.suggestion-status', $this->suggestedQuestion->id),
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
        $recipientEmail = $this->user?->email ?? $this->email;

        return $this->to($recipientEmail)
            ->markdown('emails.faq.submission-confirmation')
            ->with([
                'question' => $this->suggestedQuestion,
                'user' => $this->user,
                'trackingCode' => 'FAQ-' . str_pad($this->suggestedQuestion->id, 6, '0', STR_PAD_LEFT),
            ]);
    }
}
