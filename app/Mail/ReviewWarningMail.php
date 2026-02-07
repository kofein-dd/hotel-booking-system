<?php

namespace App\Mail;

use App\Models\Review;
use App\Models\ReviewReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Review $review,
        public ?ReviewReport $report = null,
        public string $warningType = 'violation',
        public ?string $adminMessage = null,
        public ?array $violationDetails = null
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Предупреждение: Нарушение правил публикации отзывов',
            tags: ['review', 'warning', 'moderation'],
            metadata: [
                'review_id' => (string) $this->review->id,
                'user_id' => (string) $this->review->user_id,
                'warning_type' => $this->warningType,
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reviews.warning',
            with: [
                'review' => $this->review,
                'report' => $this->report,
                'warningType' => $this->warningType,
                'adminMessage' => $this->adminMessage,
                'violationDetails' => $this->violationDetails,
                'user' => $this->review->user,
                'hotel' => $this->review->hotel,
                'reviewUrl' => route('reviews.show', $this->review),
                'supportUrl' => route('contact'),
                'rulesUrl' => route('pages.show', 'review-rules'),
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
        return $this->markdown('emails.reviews.warning')
            ->with([
                'review' => $this->review,
                'report' => $this->report,
                'warningType' => $this->warningType,
                'adminMessage' => $this->adminMessage,
                'violationDetails' => $this->violationDetails,
                'user' => $this->review->user,
                'hotel' => $this->review->hotel,
            ]);
    }
}
