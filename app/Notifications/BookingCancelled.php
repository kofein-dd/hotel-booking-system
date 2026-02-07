<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class BookingCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public ?string $cancellationReason = null,
        public ?float $refundAmount = null
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        // Используем встроенную проверку из HasPushSubscriptions
        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = 'webpush';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('❌ Бронирование отменено')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line('Ваше бронирование было отменено.')
            ->line('**Детали отмененного бронирования:**')
            ->line("- Номер: {$this->booking->room->name}")
            ->line("- Заезд: {$this->booking->check_in->format('d.m.Y')}")
            ->line("- Выезд: {$this->booking->check_out->format('d.m.Y')}")
            ->line("- Гостей: {$this->booking->guests_count}")
            ->line("- Сумма: {$this->booking->total_price} руб.");

        if ($this->cancellationReason) {
            $mail->line("**Причина отмены:** {$this->cancellationReason}");
        }

        if ($this->refundAmount && $this->refundAmount > 0) {
            $mail->line("**Сумма к возврату:** {$this->refundAmount} руб.")
                ->line('Возврат будет произведен в течение 3-10 рабочих дней на вашу карту.');
        }

        return $mail->action('Посмотреть историю бронирований', route('bookings.index'))
            ->line('Надеемся увидеть вас в нашем отеле в будущем!');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'booking_cancelled',
            'booking_id' => $this->booking->id,
            'room_name' => $this->booking->room->name,
            'cancellation_reason' => $this->cancellationReason,
            'refund_amount' => $this->refundAmount,
            'action_url' => route('bookings.index'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $message = $this->refundAmount && $this->refundAmount > 0
            ? "Бронирование отменено. Возврат {$this->refundAmount} руб."
            : 'Бронирование отменено';

        return new BroadcastMessage([
            'title' => 'Бронирование отменено',
            'body' => $message,
            'icon' => '❌',
            'action_url' => route('bookings.index'),
            'booking_id' => $this->booking->id,
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'cancellation_reason' => $this->cancellationReason,
            'refund_amount' => $this->refundAmount,
        ];
    }

    public function toWebPush($notifiable, $notification)
    {
        return \NotificationChannels\WebPush\WebPushMessage::create()
            ->id($notification->id)
            ->title('✅ Бронирование подтверждено!')
            ->icon('/images/logo.png')
            ->body("Ваше бронирование номера {$this->booking->room->name} подтверждено")
            ->action('Посмотреть детали', 'view_booking')
            ->data([
                'url' => route('bookings.show', $this->booking),
                'booking_id' => $this->booking->id,
                'type' => 'booking_confirmed'
            ]);
    }
}
