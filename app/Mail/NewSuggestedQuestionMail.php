<?php

namespace App\Mail;

use App\Models\SuggestedQuestion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewSuggestedQuestionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SuggestedQuestion $suggestedQuestion,
        public ?User $suggestedBy = null
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Новый предложенный вопрос для FAQ',
            tags: ['faq', 'suggestion', 'moderation'],
            metadata: [
                'question_id' => (string) $this->suggestedQuestion->id,
                'user_id' => (string) $this->suggestedQuestion->user_id,
                'status' => $this->suggestedQuestion->status,
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.faq.suggestion-new',
            with: [
                'question' => $this->suggestedQuestion,
                'suggestedBy' => $this->suggestedBy,
                'questionUrl' => route('admin.faq.suggestions.show', $this->suggestedQuestion),
                'adminUrl' => route('admin.faq.suggestions.index'),
                'reviewDeadline' => now()->addDays(3)->format('d.m.Y'),
                'votesCount' => $this->suggestedQuestion->votes,
                'hasSimilarQuestions' => $this->checkSimilarQuestions(),
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
        return $this->markdown('emails.faq.suggestion-new')
            ->with([
                'question' => $this->suggestedQuestion,
                'suggestedBy' => $this->suggestedBy,
            ]);
    }

    /**
     * Проверить наличие похожих вопросов
     */
    private function checkSimilarQuestions(): bool
    {
        // Здесь может быть логика поиска похожих вопросов
        // Для упрощения возвращаем false
        return false;
    }
}
