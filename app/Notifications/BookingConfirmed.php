<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class BookingConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public ?string $additionalMessage = null
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        // Проверяем, есть ли у пользователя push-подписка
        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('✅ Ваше бронирование подтверждено!')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line('Мы рады сообщить, что ваше бронирование успешно подтверждено.')
            ->line('**Детали бронирования:**')
            ->line("- Номер: {$this->booking->room->name}")
            ->line("- Заезд: {$this->booking->check_in->format('d.m.Y')}")
            ->line("- Выезд: {$this->booking->check_out->format('d.m.Y')}")
            ->line("- Гостей: {$this->booking->guests_count}")
            ->line("- Итоговая сумма: {$this->booking->total_price} руб.")
            ->when($this->additionalMessage, function ($mail) {
                return $mail->line($this->additionalMessage);
            })
            ->action('Посмотреть детали бронирования', route('bookings.show', $this->booking))
            ->line('Спасибо, что выбрали наш отель! Ждем вас с нетерпением.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'booking_confirmed',
            'booking_id' => $this->booking->id,
            'room_name' => $this->booking->room->name,
            'check_in' => $this->booking->check_in->toDateString(),
            'check_out' => $this->booking->check_out->toDateString(),
            'total_price' => $this->booking->total_price,
            'message' => $this->additionalMessage,
            'action_url' => route('bookings.show', $this->booking),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => 'Бронирование подтверждено',
            'body' => "Ваше бронирование номера {$this->booking->room->name} подтверждено",
            'icon' => '✅',
            'action_url' => route('bookings.show', $this->booking),
            'booking_id' => $this->booking->id,
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'message' => 'Ваше бронирование подтверждено',
        ];
    }
}
