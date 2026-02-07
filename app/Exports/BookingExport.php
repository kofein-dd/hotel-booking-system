<?php

namespace App\Exports;

use App\Models\Booking;
use Illuminate\Support\Collection;

class BookingExport extends GenericExport
{
    public function __construct(Collection $collection)
    {
        parent::__construct(
            $collection,
            'Бронирования',
            [
                'ID',
                'Номер',
                'Гость',
                'Email',
                'Телефон',
                'Заезд',
                'Выезд',
                'Гостей',
                'Сумма',
                'Статус',
                'Статус оплаты',
                'Дата создания',
                'Дата подтверждения',
                'Дата отмены',
            ]
        );

        // Установить форматы для столбцов с датами и числами
        $this->setColumnFormats([
            'F' => 'DD.MM.YYYY',
            'G' => 'DD.MM.YYYY',
            'I' => '#,##0.00" руб."',
            'L' => 'DD.MM.YYYY HH:MM',
            'M' => 'DD.MM.YYYY HH:MM',
            'N' => 'DD.MM.YYYY HH:MM',
        ]);
    }

    /**
     * Преобразование данных бронирований
     */
    public function map($booking): array
    {
        return [
            $booking->id,
            $booking->room ? $booking->room->name : 'Удален',
            $booking->user ? $booking->user->name : 'Гость',
            $booking->user ? $booking->user->email : '-',
            $booking->user ? $booking->user->phone : '-',
            $booking->check_in->format('d.m.Y'),
            $booking->check_out->format('d.m.Y'),
            $booking->guests_count,
            $booking->total_price,
            $this->translateStatus($booking->status),
            $booking->payment ? $this->translatePaymentStatus($booking->payment->status) : 'Не оплачено',
            $booking->created_at->format('d.m.Y H:i'),
            $booking->confirmed_at ? $booking->confirmed_at->format('d.m.Y H:i') : '-',
            $booking->cancelled_at ? $booking->cancelled_at->format('d.m.Y H:i') : '-',
        ];
    }

    /**
     * Перевод статуса бронирования
     */
    private function translateStatus(string $status): string
    {
        $translations = [
            'pending' => 'Ожидание',
            'confirmed' => 'Подтверждено',
            'cancelled' => 'Отменено',
            'completed' => 'Завершено',
            'no_show' => 'Не приехал',
        ];

        return $translations[$status] ?? $status;
    }

    /**
     * Перевод статуса оплаты
     */
    private function translatePaymentStatus(string $status): string
    {
        $translations = [
            'pending' => 'Ожидание',
            'paid' => 'Оплачено',
            'failed' => 'Ошибка',
            'refunded' => 'Возврат',
            'partially_refunded' => 'Частичный возврат',
        ];

        return $translations[$status] ?? $status;
    }
}
