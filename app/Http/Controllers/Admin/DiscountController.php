<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Booking;
use App\Models\User;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DiscountController extends Controller
{
    /**
     * Display a listing of discounts.
     */
    public function index(Request $request): View
    {
        if (!Gate::allows('manage-discounts')) {
            abort(403);
        }

        $query = Discount::query();

        // Фильтры
        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':
                    $query->where('status', 'active')
                        ->where(function ($q) {
                            $q->whereNull('valid_from')
                                ->orWhere('valid_from', '<=', now());
                        })
                        ->where(function ($q) {
                            $q->whereNull('valid_to')
                                ->orWhere('valid_to', '>=', now());
                        })
                        ->where(function ($q) {
                            $q->whereNull('usage_limit')
                                ->orWhereRaw('used_count < usage_limit');
                        });
                    break;

                case 'expired':
                    $query->where(function ($q) {
                        $q->whereNotNull('valid_to')
                            ->where('valid_to', '<', now());
                    });
                    break;

                case 'inactive':
                    $query->where('status', 'inactive');
                    break;

                case 'usage_limit':
                    $query->whereNotNull('usage_limit')
                        ->whereRaw('used_count >= usage_limit');
                    break;
            }
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $discounts = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.discounts.index', compact('discounts'));
    }

    /**
     * Show the form for creating a new discount.
     */
    public function create(): View
    {
        if (!Gate::allows('create-discounts')) {
            abort(403);
        }

        $rooms = Room::where('status', 'active')->get();
        $users = User::where('role', 'user')->get();

        return view('admin.discounts.create', compact('rooms', 'users'));
    }

    /**
     * Store a newly created discount in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        if (!Gate::allows('create-discounts')) {
            abort(403);
        }

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:discounts,code',
                'regex:/^[A-Z0-9_-]+$/i'
            ],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:percentage,fixed_amount,free_night',
            'value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_booking_amount' => 'nullable|numeric|min:0',
            'valid_from' => 'nullable|date|after_or_equal:today',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'usage_limit' => 'nullable|integer|min:1',
            'user_limit' => 'nullable|integer|min:1',
            'status' => 'required|in:active,inactive',
            'applicable_to' => 'required|in:all_rooms,specific_rooms,specific_users',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'exists:rooms,id',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'exclude_dates' => 'nullable|array',
            'exclude_dates.*' => 'date',
            'blackout_dates' => 'nullable|array',
            'blackout_dates.*' => 'date',
        ]);

        // Генерация кода, если не указан
        if (empty($validated['code'])) {
            $validated['code'] = $this->generateDiscountCode();
        }

        // Обработка процентной скидки
        if ($validated['type'] === 'percentage' && $validated['value'] > 100) {
            return back()->withErrors(['value' => 'Процентная скидка не может превышать 100%.']);
        }

        // Создаем скидку
        $discount = Discount::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'],
            'type' => $validated['type'],
            'value' => $validated['value'],
            'max_discount' => $validated['max_discount'],
            'min_booking_amount' => $validated['min_booking_amount'],
            'valid_from' => $validated['valid_from'],
            'valid_to' => $validated['valid_to'],
            'usage_limit' => $validated['usage_limit'],
            'user_limit' => $validated['user_limit'],
            'status' => $validated['status'],
            'applicable_to' => $validated['applicable_to'],
            'exclude_dates' => $validated['exclude_dates'] ?? [],
            'blackout_dates' => $validated['blackout_dates'] ?? [],
            'created_by' => auth()->guard('admin')->id(),
        ]);

        // Привязываем комнаты, если указаны
        if (!empty($validated['room_ids'])) {
            $discount->rooms()->sync($validated['room_ids']);
        }

        // Привязываем пользователей, если указаны
        if (!empty($validated['user_ids'])) {
            $discount->users()->sync($validated['user_ids']);
        }

        return redirect()->route('admin.discounts.show', $discount)
            ->with('success', 'Скидка успешно создана.');
    }

    /**
     * Display the specified discount.
     */
    public function show(Discount $discount): View
    {
        if (!Gate::allows('view-discount', $discount)) {
            abort(403);
        }

        $discount->load(['rooms', 'users', 'bookings.user', 'creator']);

        // Статистика использования
        $usageStats = [
            'total_used' => $discount->used_count,
            'remaining' => $discount->usage_limit ? $discount->usage_limit - $discount->used_count : '∞',
            'total_saved' => $discount->bookings()->sum('discount_amount'),
            'last_used' => $discount->bookings()->latest()->first()?->created_at,
        ];

        // Бронирования с этой скидкой
        $bookings = $discount->bookings()
            ->with(['user', 'room'])
            ->latest()
            ->paginate(10);

        return view('admin.discounts.show', compact('discount', 'usageStats', 'bookings'));
    }

    /**
     * Show the form for editing the specified discount.
     */
    public function edit(Discount $discount): View
    {
        if (!Gate::allows('edit-discount', $discount)) {
            abort(403);
        }

        $rooms = Room::where('status', 'active')->get();
        $users = User::where('role', 'user')->get();

        $selectedRooms = $discount->rooms->pluck('id')->toArray();
        $selectedUsers = $discount->users->pluck('id')->toArray();

        return view('admin.discounts.edit', compact('discount', 'rooms', 'users', 'selectedRooms', 'selectedUsers'));
    }

    /**
     * Update the specified discount in storage.
     */
    public function update(Request $request, Discount $discount): RedirectResponse
    {
        if (!Gate::allows('edit-discount', $discount)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:percentage,fixed_amount,free_night',
            'value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_booking_amount' => 'nullable|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'usage_limit' => 'nullable|integer|min:1',
            'user_limit' => 'nullable|integer|min:1',
            'status' => 'required|in:active,inactive',
            'applicable_to' => 'required|in:all_rooms,specific_rooms,specific_users',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'exists:rooms,id',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'exclude_dates' => 'nullable|array',
            'exclude_dates.*' => 'date',
            'blackout_dates' => 'nullable|array',
            'blackout_dates.*' => 'date',
        ]);

        // Проверка, что не изменяем код, если уже есть использования
        if ($discount->used_count > 0 && $request->has('code') && $request->code !== $discount->code) {
            return back()->withErrors(['code' => 'Нельзя изменить код скидки, так как она уже использовалась.']);
        }

        if ($validated['type'] === 'percentage' && $validated['value'] > 100) {
            return back()->withErrors(['value' => 'Процентная скидка не может превышать 100%.']);
        }

        // Обновляем скидку
        $discount->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'type' => $validated['type'],
            'value' => $validated['value'],
            'max_discount' => $validated['max_discount'],
            'min_booking_amount' => $validated['min_booking_amount'],
            'valid_from' => $validated['valid_from'],
            'valid_to' => $validated['valid_to'],
            'usage_limit' => $validated['usage_limit'],
            'user_limit' => $validated['user_limit'],
            'status' => $validated['status'],
            'applicable_to' => $validated['applicable_to'],
            'exclude_dates' => $validated['exclude_dates'] ?? [],
            'blackout_dates' => $validated['blackout_dates'] ?? [],
        ]);

        // Обновляем привязки
        if (!empty($validated['room_ids'])) {
            $discount->rooms()->sync($validated['room_ids']);
        } else {
            $discount->rooms()->detach();
        }

        if (!empty($validated['user_ids'])) {
            $discount->users()->sync($validated['user_ids']);
        } else {
            $discount->users()->detach();
        }

        return redirect()->route('admin.discounts.show', $discount)
            ->with('success', 'Скидка успешно обновлена.');
    }

    /**
     * Activate the specified discount.
     */
    public function activate(Discount $discount): RedirectResponse
    {
        if (!Gate::allows('edit-discount', $discount)) {
            abort(403);
        }

        if ($discount->status === 'active') {
            return back()->with('warning', 'Скидка уже активна.');
        }

        $discount->update(['status' => 'active']);

        return back()->with('success', 'Скидка активирована.');
    }

    /**
     * Deactivate the specified discount.
     */
    public function deactivate(Discount $discount): RedirectResponse
    {
        if (!Gate::allows('edit-discount', $discount)) {
            abort(403);
        }

        if ($discount->status === 'inactive') {
            return back()->with('warning', 'Скидка уже неактивна.');
        }

        $discount->update(['status' => 'inactive']);

        return back()->with('success', 'Скидка деактивирована.');
    }

    /**
     * Delete the specified discount.
     */
    public function destroy(Discount $discount): RedirectResponse
    {
        if (!Gate::allows('delete-discount', $discount)) {
            abort(403);
        }

        if ($discount->used_count > 0) {
            return back()->withErrors(['error' => 'Нельзя удалить скидку, которая уже использовалась.']);
        }

        $discount->delete();

        return redirect()->route('admin.discounts.index')
            ->with('success', 'Скидка удалена.');
    }

    /**
     * Generate a unique discount code.
     */
    private function generateDiscountCode(): string
    {
        $code = '';
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }

            // Добавляем префикс в зависимости от типа
            $prefix = match(rand(1, 3)) {
                1 => 'HOTEL',
                2 => 'SUMMER',
                3 => 'SEA',
                default => 'DISCOUNT'
            };

            $code = $prefix . '_' . $code;
        } while (Discount::where('code', $code)->exists());

        return $code;
    }

    /**
     * Validate discount code (API endpoint).
     */
    public function validateCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'room_id' => 'nullable|exists:rooms,id',
            'user_id' => 'nullable|exists:users,id',
            'booking_amount' => 'required|numeric|min:0',
            'check_in' => 'nullable|date',
            'check_out' => 'nullable|date',
        ]);

        $discount = Discount::where('code', strtoupper($request->code))->first();

        if (!$discount) {
            return response()->json([
                'valid' => false,
                'message' => 'Код скидки не найден.'
            ], 404);
        }

        $validationResult = $this->validateDiscount($discount, $request->all());

        if (!$validationResult['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $validationResult['message']
            ], 400);
        }

        // Рассчитываем сумму скидки
        $discountAmount = $this->calculateDiscountAmount(
            $discount,
            $request->booking_amount,
            $request->check_in,
            $request->check_out
        );

        return response()->json([
            'valid' => true,
            'discount' => [
                'id' => $discount->id,
                'code' => $discount->code,
                'name' => $discount->name,
                'type' => $discount->type,
                'value' => $discount->value,
                'amount' => $discountAmount,
                'max_discount' => $discount->max_discount,
            ],
            'message' => 'Скидка применена успешно.'
        ]);
    }

    /**
     * Validate discount against rules.
     */
    private function validateDiscount(Discount $discount, array $data): array
    {
        // Проверка статуса
        if ($discount->status !== 'active') {
            return ['valid' => false, 'message' => 'Скидка неактивна.'];
        }

        // Проверка даты действия
        $now = now();
        if ($discount->valid_from && $now->lt($discount->valid_from)) {
            return ['valid' => false, 'message' => 'Скидка ещё не действует.'];
        }

        if ($discount->valid_to && $now->gt($discount->valid_to)) {
            return ['valid' => false, 'message' => 'Срок действия скидки истёк.'];
        }

        // Проверка лимита использования
        if ($discount->usage_limit && $discount->used_count >= $discount->usage_limit) {
            return ['valid' => false, 'message' => 'Лимит использования скидки исчерпан.'];
        }

        // Проверка минимальной суммы бронирования
        if ($discount->min_booking_amount && $data['booking_amount'] < $discount->min_booking_amount) {
            return ['valid' => false, 'message' => 'Скидка применяется только к бронированиям от ' . $discount->min_booking_amount . ' руб.'];
        }

        // Проверка применимости к комнате
        if ($data['room_id']) {
            if ($discount->applicable_to === 'specific_rooms' && !$discount->rooms->contains($data['room_id'])) {
                return ['valid' => false, 'message' => 'Скидка не действует для выбранного номера.'];
            }
        }

        // Проверка применимости к пользователю
        if ($data['user_id']) {
            // Проверка лимита на пользователя
            $userUsageCount = $discount->bookings()
                ->where('user_id', $data['user_id'])
                ->count();

            if ($discount->user_limit && $userUsageCount >= $discount->user_limit) {
                return ['valid' => false, 'message' => 'Вы уже использовали эту скидку максимальное количество раз.'];
            }

            // Проверка для конкретных пользователей
            if ($discount->applicable_to === 'specific_users' && !$discount->users->contains($data['user_id'])) {
                return ['valid' => false, 'message' => 'Скидка не действует для вашего аккаунта.'];
            }
        }

        // Проверка исключенных дат
        if (!empty($data['check_in']) && !empty($data['check_out'])) {
            $checkIn = Carbon::parse($data['check_in']);

            if (!empty($discount->exclude_dates)) {
                foreach ($discount->exclude_dates as $excludeDate) {
                    if ($checkIn->isSameDay(Carbon::parse($excludeDate))) {
                        return ['valid' => false, 'message' => 'Скидка не действует на выбранные даты.'];
                    }
                }
            }
        }

        // Проверка черных дат
        if (!empty($data['check_in']) && !empty($discount->blackout_dates)) {
            $checkIn = Carbon::parse($data['check_in']);
            foreach ($discount->blackout_dates as $blackoutDate) {
                if ($checkIn->isSameDay(Carbon::parse($blackoutDate))) {
                    return ['valid' => false, 'message' => 'На выбранные даты действуют специальные тарифы.'];
                }
            }
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * Calculate discount amount.
     */
    private function calculateDiscountAmount(Discount $discount, float $bookingAmount, ?string $checkIn, ?string $checkOut): float
    {
        $amount = 0;

        switch ($discount->type) {
            case 'percentage':
                $amount = $bookingAmount * ($discount->value / 100);
                if ($discount->max_discount && $amount > $discount->max_discount) {
                    $amount = $discount->max_discount;
                }
                break;

            case 'fixed_amount':
                $amount = min($discount->value, $bookingAmount);
                break;

            case 'free_night':
                if ($checkIn && $checkOut) {
                    $nights = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
                    $nightPrice = $bookingAmount / $nights;
                    $amount = min($nightPrice * $discount->value, $bookingAmount);
                }
                break;
        }

        return round($amount, 2);
    }

    /**
     * Get discount statistics.
     */
    public function statistics(): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        // Общая статистика
        $totalDiscounts = Discount::count();
        $activeDiscounts = Discount::where('status', 'active')->count();
        $totalSavings = Booking::whereNotNull('discount_id')->sum('discount_amount');
        $totalUsage = Discount::sum('used_count');

        // Статистика по типам
        $typeStats = Discount::select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(used_count) as used'))
            ->groupBy('type')
            ->get();

        // Самые популярные скидки
        $popularDiscounts = Discount::withCount(['bookings'])
            ->orderBy('bookings_count', 'desc')
            ->limit(10)
            ->get();

        // Статистика по месяцам
        $monthlyStats = Booking::whereNotNull('discount_id')
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as bookings_count'),
                DB::raw('SUM(discount_amount) as total_savings')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        // Эффективность скидок
        $effectiveness = Discount::where('used_count', '>', 0)
            ->select('id', 'code', 'name', 'used_count', DB::raw('(SELECT SUM(discount_amount) FROM bookings WHERE discount_id = discounts.id) as total_savings'))
            ->orderBy('used_count', 'desc')
            ->limit(15)
            ->get();

        return view('admin.discounts.statistics', compact(
            'totalDiscounts',
            'activeDiscounts',
            'totalSavings',
            'totalUsage',
            'typeStats',
            'popularDiscounts',
            'monthlyStats',
            'effectiveness'
        ));
    }

    /**
     * Export discounts to CSV.
     */
    public function export(Request $request)
    {
        if (!Gate::allows('export-discounts')) {
            abort(403);
        }

        $discounts = Discount::with(['rooms', 'users', 'creator'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('type', $request->type);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->where('created_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="discounts_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($discounts) {
            $file = fopen('php://output', 'w');

            // Заголовки
            fputcsv($file, [
                'ID', 'Код', 'Название', 'Тип', 'Значение', 'Макс. скидка',
                'Мин. сумма', 'Статус', 'Применяется к', 'Действует с',
                'Действует до', 'Лимит использования', 'Использовано',
                'Лимит на пользователя', 'Создано', 'Создатель'
            ]);

            foreach ($discounts as $discount) {
                $applicableTo = match($discount->applicable_to) {
                    'all_rooms' => 'Все номера',
                    'specific_rooms' => 'Выбранные номера (' . $discount->rooms->count() . ')',
                    'specific_users' => 'Выбранные пользователи (' . $discount->users->count() . ')',
                    default => $discount->applicable_to
                };

                fputcsv($file, [
                    $discount->id,
                    $discount->code,
                    $discount->name,
                    $this->getTypeName($discount->type),
                    $discount->value . ($discount->type === 'percentage' ? '%' : ' руб.'),
                    $discount->max_discount ? $discount->max_discount . ' руб.' : '-',
                    $discount->min_booking_amount ? $discount->min_booking_amount . ' руб.' : '-',
                    $discount->status === 'active' ? 'Активна' : 'Неактивна',
                    $applicableTo,
                    $discount->valid_from ? $discount->valid_from->format('d.m.Y') : '-',
                    $discount->valid_to ? $discount->valid_to->format('d.m.Y') : '-',
                    $discount->usage_limit ?: '-',
                    $discount->used_count,
                    $discount->user_limit ?: '-',
                    $discount->created_at->format('d.m.Y H:i'),
                    $discount->creator ? $discount->creator->name : '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get discount type name in Russian.
     */
    private function getTypeName(string $type): string
    {
        return match($type) {
            'percentage' => 'Процентная',
            'fixed_amount' => 'Фиксированная сумма',
            'free_night' => 'Бесплатная ночь',
            default => $type,
        };
    }

    /**
     * Bulk create discounts (for seasonal promotions).
     */
    public function bulkCreate(): View
    {
        if (!Gate::allows('create-discounts')) {
            abort(403);
        }

        return view('admin.discounts.bulk-create');
    }

    /**
     * Process bulk creation of discounts.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        if (!Gate::allows('create-discounts')) {
            abort(403);
        }

        $validated = $request->validate([
            'count' => 'required|integer|min:1|max:100',
            'prefix' => 'nullable|string|max:20',
            'type' => 'required|in:percentage,fixed_amount,free_night',
            'value' => 'required|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'usage_limit' => 'nullable|integer|min:1',
            'status' => 'required|in:active,inactive',
        ]);

        $created = 0;
        $failed = 0;

        for ($i = 0; $i < $validated['count']; $i++) {
            try {
                $code = $this->generateBulkCode($validated['prefix'] ?? 'BULK');

                Discount::create([
                    'code' => $code,
                    'name' => 'Промо-скидка ' . ($i + 1),
                    'type' => $validated['type'],
                    'value' => $validated['value'],
                    'valid_from' => $validated['valid_from'],
                    'valid_to' => $validated['valid_to'],
                    'usage_limit' => $validated['usage_limit'],
                    'status' => $validated['status'],
                    'applicable_to' => 'all_rooms',
                    'created_by' => auth()->guard('admin')->id(),
                ]);

                $created++;
            } catch (\Exception $e) {
                $failed++;
                \Log::error('Failed to create bulk discount: ' . $e->getMessage());
            }
        }

        $message = "Создано {$created} скидок.";
        if ($failed > 0) {
            $message .= " Не удалось создать {$failed} скидок.";
        }

        return redirect()->route('admin.discounts.index')
            ->with('success', $message);
    }

    /**
     * Generate code for bulk discounts.
     */
    private function generateBulkCode(string $prefix): string
    {
        do {
            $code = $prefix . '_' . strtoupper(uniqid());
        } while (Discount::where('code', $code)->exists());

        return $code;
    }
}
