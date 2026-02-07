<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    /**
     * Display a listing of bookings with filters.
     */
    public function index(Request $request): View
    {
        // Разрешение на просмотр бронирований
        if (!Gate::allows('view-bookings')) {
            abort(403);
        }

        $query = Booking::with(['user', 'room', 'payment'])
            ->latest();

        // Фильтры
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        if ($request->filled('date_from')) {
            $query->where('check_in', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('check_out', '<=', $request->date_to);
        }

        $bookings = $query->paginate(20);

        $users = User::where('role', 'user')->get();
        $rooms = Room::where('status', 'active')->get();

        return view('admin.bookings.index', compact('bookings', 'users', 'rooms'));
    }

    /**
     * Display the specified booking.
     */
    public function show(Booking $booking): View
    {
        if (!Gate::allows('view-booking', $booking)) {
            abort(403);
        }

        $booking->load(['user', 'room.hotel', 'payment']);

        return view('admin.bookings.show', compact('booking'));
    }

    /**
     * Show the form for editing the specified booking.
     */
    public function edit(Booking $booking): View
    {
        if (!Gate::allows('edit-booking', $booking)) {
            abort(403);
        }

        $rooms = Room::where('status', 'active')
            ->where('id', '!=', $booking->room_id)
            ->get();

        return view('admin.bookings.edit', compact('booking', 'rooms'));
    }

    /**
     * Update the specified booking.
     */
    public function update(Request $request, Booking $booking): RedirectResponse
    {
        if (!Gate::allows('edit-booking', $booking)) {
            abort(403);
        }

        $validated = $request->validate([
            'room_id' => 'sometimes|exists:rooms,id',
            'check_in' => 'sometimes|date|after_or_equal:today',
            'check_out' => 'sometimes|date|after:check_in',
            'guests_count' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:pending,confirmed,cancelled,completed',
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        // Проверка доступности номера при изменении
        if (isset($validated['room_id']) || isset($validated['check_in']) || isset($validated['check_out'])) {
            $roomId = $validated['room_id'] ?? $booking->room_id;
            $checkIn = $validated['check_in'] ?? $booking->check_in;
            $checkOut = $validated['check_out'] ?? $booking->check_out;

            $isAvailable = Booking::where('room_id', $roomId)
                ->where('id', '!=', $booking->id)
                ->where(function ($query) use ($checkIn, $checkOut) {
                    $query->whereBetween('check_in', [$checkIn, $checkOut])
                        ->orWhereBetween('check_out', [$checkIn, $checkOut])
                        ->orWhere(function ($q) use ($checkIn, $checkOut) {
                            $q->where('check_in', '<', $checkIn)
                                ->where('check_out', '>', $checkOut);
                        });
                })
                ->whereIn('status', ['pending', 'confirmed'])
                ->doesntExist();

            if (!$isAvailable) {
                return back()->withErrors(['room_id' => 'Номер уже забронирован на выбранные даты.']);
            }
        }

        // Обновление суммы при изменении номера или дат
        if (isset($validated['room_id']) || isset($validated['check_in']) || isset($validated['check_out'])) {
            $room = Room::find($roomId);
            $nights = (strtotime($checkOut) - strtotime($checkIn)) / (60 * 60 * 24);
            $validated['total_price'] = $room->price_per_night * $nights;
        }

        // Если отмена брони
        if (isset($validated['status']) && $validated['status'] === 'cancelled') {
            $validated['cancelled_at'] = now();

            // Возврат средств если оплата была
            if ($booking->payment && $booking->payment->status === 'completed') {
                // Логика возврата средств
                $booking->payment->update(['status' => 'refunded']);
            }
        }

        $booking->update($validated);

        // Отправка уведомления пользователю при изменении статуса
        if (isset($validated['status'])) {
            // Логика отправки уведомления (email/telegram)
            // Notification::send($booking->user, new BookingStatusChanged($booking));
        }

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', 'Бронирование успешно обновлено.');
    }

    /**
     * Confirm booking.
     */
    public function confirm(Booking $booking): RedirectResponse
    {
        if (!Gate::allows('confirm-booking', $booking)) {
            abort(403);
        }

        if ($booking->status !== 'pending') {
            return back()->withErrors(['status' => 'Можно подтверждать только бронирования со статусом "ожидание".']);
        }

        $booking->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        // Отправка уведомления пользователю
        // Notification::send($booking->user, new BookingConfirmed($booking));

        return back()->with('success', 'Бронирование подтверждено.');
    }

    /**
     * Cancel booking.
     */
    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        if (!Gate::allows('cancel-booking', $booking)) {
            abort(403);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        // Возврат средств если нужно
        if ($booking->payment && $booking->payment->status === 'completed') {
            $booking->payment->update(['status' => 'refunded']);
        }

        // Отправка уведомления
        // Notification::send($booking->user, new BookingCancelled($booking));

        return back()->with('success', 'Бронирование отменено.');
    }

    /**
     * Get booking statistics.
     */
    public function statistics(): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        // Статистика по месяцам
        $monthlyStats = Booking::select(
            DB::raw('YEAR(created_at) as year'),
            DB::raw('MONTH(created_at) as month'),
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as confirmed'),
            DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled'),
            DB::raw('SUM(total_price) as revenue')
        )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        // Статистика по номерам
        $roomStats = Room::withCount(['bookings' => function ($query) {
            $query->whereIn('status', ['confirmed', 'completed']);
        }])
            ->withSum(['bookings' => function ($query) {
                $query->whereIn('status', ['confirmed', 'completed']);
            }], 'total_price')
            ->orderBy('bookings_count', 'desc')
            ->limit(10)
            ->get();

        // Статистика по статусам
        $statusStats = Booking::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        return view('admin.bookings.statistics', compact('monthlyStats', 'roomStats', 'statusStats'));
    }

    /**
     * View calendar with bookings.
     */
    public function calendar(): View
    {
        if (!Gate::allows('view-calendar')) {
            abort(403);
        }

        $rooms = Room::with(['bookings' => function ($query) {
            $query->whereIn('status', ['pending', 'confirmed'])
                ->select(['id', 'room_id', 'check_in', 'check_out', 'status']);
        }])->where('status', 'active')->get();

        return view('admin.bookings.calendar', compact('rooms'));
    }

    /**
     * Export bookings to CSV/Excel.
     */
    public function export(Request $request)
    {
        if (!Gate::allows('export-bookings')) {
            abort(403);
        }

        $bookings = Booking::with(['user', 'room', 'payment'])
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->where('check_in', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->where('check_out', '<=', $request->date_to);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->get();

        // Здесь должна быть логика экспорта (Laravel Excel или ручной CSV)
        // Для примера простой CSV

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bookings_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($bookings) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Пользователь', 'Номер', 'Заезд', 'Выезд', 'Гостей', 'Сумма', 'Статус', 'Дата создания']);

            foreach ($bookings as $booking) {
                fputcsv($file, [
                    $booking->id,
                    $booking->user->email,
                    $booking->room->name ?? $booking->room->id,
                    $booking->check_in,
                    $booking->check_out,
                    $booking->guests_count,
                    $booking->total_price,
                    $booking->status,
                    $booking->created_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Soft delete booking.
     */
    public function destroy(Booking $booking): RedirectResponse
    {
        if (!Gate::allows('delete-booking', $booking)) {
            abort(403);
        }

        // Проверка возможности удаления
        if ($booking->status === 'confirmed' && $booking->check_in <= now()->addDays(7)) {
            return back()->withErrors(['status' => 'Нельзя удалять подтвержденные бронирования менее чем за 7 дней до заезда.']);
        }

        $booking->delete();

        return redirect()->route('admin.bookings.index')
            ->with('success', 'Бронирование удалено.');
    }

    /**
     * Restore soft deleted booking.
     */
    public function restore($id): RedirectResponse
    {
        $booking = Booking::withTrashed()->findOrFail($id);

        if (!Gate::allows('restore-booking', $booking)) {
            abort(403);
        }

        $booking->restore();

        return back()->with('success', 'Бронирование восстановлено.');
    }
}
