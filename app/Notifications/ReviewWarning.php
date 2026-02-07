<?php

namespace App\Notifications;

use App\Models\Review;
use App\Models\ReviewReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class ReviewWarning extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Review $review,
        public ?ReviewReport $report = null,
        public string $warningType = 'violation',
        public ?string $adminMessage = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('⚠️ Предупреждение: Нарушение правил отзывов')
            ->greeting("Уважаемый(ая) {$notifiable->name},")
            ->line('Мы обнаружили нарушение правил публикации отзывов в вашем отзыве.')
            ->line('')
            ->line('**Информация о вашем отзыве:**')
            ->line("- Отель: {$this->review->hotel->name}")
            ->line("- Рейтинг: {$this->review->rating}/5")
            ->line("- Дата: {$this->review->created_at->format('d.m.Y H:i')}")
            ->line('')
            ->line('**Краткое содержание отзыва:**')
            ->line(\Illuminate\Support\Str::limit($this->review->comment, 200));

        if ($this->report) {
            $mail->line('')
                ->line('**Причина жалобы:**')
                ->line("- Тип нарушения: {$this->report->report_type}")
                ->line("- Комментарий: {$this->report->report_comment}");
        }

        if ($this->adminMessage) {
            $mail->line('')
                ->line('**Сообщение от администратора:**')
                ->line($this->adminMessage);
        }

        $mail->line('')
            ->line('**Возможные последствия:**')
            ->line('- Ваш отзыв может быть скрыт или удален')
            ->line('- При повторных нарушениях возможна блокировка аккаунта')
            ->line('')
            ->action('Просмотреть свой отзыв', route('reviews.show', $this->review))
            ->line('Если вы считаете, что это ошибка, пожалуйста, свяжитесь с поддержкой.')
            ->line('')
            ->line('С уважением,')
            ->line('Команда модерации');

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'review_warning',
            'review_id' => $this->review->id,
            'report_id' => $this->report?->id,
            'warning_type' => $this->warningType,
            'hotel_name' => $this->review->hotel->name,
            'rating' => $this->review->rating,
            'comment_preview' => \Illuminate\Support\Str::limit($this->review->comment, 100),
            'report_type' => $this->report?->report_type,
            'admin_message' => $this->adminMessage,
            'action_url' => route('reviews.show', $this->review),
            'requires_action' => true,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => 'Предупреждение о нарушении правил',
            'body' => "Ваш отзыв об отеле {$this->review->hotel->name} нарушает правила",
            'icon' => '⚠️',
            'action_url' => route('reviews.show', $this->review),
            'review_id' => $this->review->id,
            'warning_type' => $this->warningType,
            'sound' => 'warning',
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'review_id' => $this->review->id,
            'warning_type' => $this->warningType,
            'hotel_name' => $this->review->hotel->name,
            'admin_message' => $this->adminMessage,
        ];
    }
}
