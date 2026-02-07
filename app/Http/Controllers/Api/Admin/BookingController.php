<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Models\Payment;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingCancelled;

class BookingController extends Controller
{
    /**
     * Display a listing of bookings.
     */
    public function index(Request $request)
    {
        try {
            $query = Booking::with(['user', 'room', 'payments'])
                ->orderBy('created_at', 'desc');

            // Поиск по номеру бронирования, email пользователя или ID
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'LIKE', "%$search%")
                        ->orWhere('booking_number', 'LIKE', "%$search%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('email', 'LIKE', "%$search%")
                                ->orWhere('name', 'LIKE', "%$search%")
                                ->orWhere('phone', 'LIKE', "%$search%");
                        })
                        ->orWhereHas('room', function ($roomQuery) use ($search) {
                            $roomQuery->where('room_number', 'LIKE', "%$search%")
                                ->orWhere('name', 'LIKE', "%$search%");
                        });
                });
            }

            // Фильтр по статусу
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Фильтр по типу номера
            if ($request->has('room_type') && $request->room_type) {
                $query->whereHas('room', function ($q) use ($request) {
                    $q->where('type', $request->room_type);
                });
            }

            // Фильтр по дате заезда
            if ($request->has('check_in_from') && $request->check_in_from) {
                $query->whereDate('check_in', '>=', Carbon::parse($request->check_in_from));
            }

            if ($request->has('check_in_to') && $request->check_in_to) {
                $query->whereDate('check_in', '<=', Carbon::parse($request->check_in_to));
            }

            // Фильтр по дате выезда
            if ($request->has('check_out_from') && $request->check_out_from) {
                $query->whereDate('check_out', '>=', Carbon::parse($request->check_out_from));
            }

            if ($request->has('check_out_to') && $request->check_out_to) {
                $query->whereDate('check_out', '<=', Carbon::parse($request->check_out_to));
            }

            // Фильтр по дате создания
            if ($request->has('created_from') && $request->created_from) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->created_from));
            }

            if ($request->has('created_to') && $request->created_to) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->created_to));
            }

            // Фильтр по пользователю
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            // Фильтр по номеру комнаты
            if ($request->has('room_id') && $request->room_id) {
                $query->where('room_id', $request->room_id);
            }

            // Статистика
            $stats = [
                'total_bookings' => Booking::count(),
                'pending_bookings' => Booking::where('status', 'pending')->count(),
                'confirmed_bookings' => Booking::where('status', 'confirmed')->count(),
                'cancelled_bookings' => Booking::where('status', 'cancelled')->count(),
                'active_bookings' => Booking::where('status', 'confirmed')
                    ->where('check_in', '<=', now())
                    ->where('check_out', '>=', now())
                    ->count(),
                'today_bookings' => Booking::whereDate('created_at', today())->count(),
                'total_revenue' => Booking::where('status', 'confirmed')->sum('total_price'),
                'avg_booking_value' => Booking::where('status', 'confirmed')->avg('total_price'),
            ];

            // Сортировка
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $bookings = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => BookingResource::collection($bookings),
                'stats' => $stats,
                'meta' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка бронирований',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created booking (admin creates booking for user).
     */
    public function store(StoreBookingRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $admin = $request->user();

            // Проверяем доступность номера на указанные даты
            $isAvailable = $this->checkRoomAvailability(
                $data['room_id'],
                $data['check_in'],
                $data['check_out'],
                null // исключаем текущее бронирование
            );

            if (!$isAvailable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Номер недоступен на выбранные даты'
                ], 400);
            }

            // Получаем информацию о номере для расчета стоимости
            $room = Room::findOrFail($data['room_id']);

            // Рассчитываем количество ночей
            $checkIn = Carbon::parse($data['check_in']);
            $checkOut = Carbon::parse($data['check_out']);
            $nights = $checkIn->diffInDays($checkOut);

            if ($nights < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Минимальное бронирование - 1 ночь'
                ], 400);
            }

            // Рассчитываем общую стоимость
            $totalPrice = $room->price_per_night * $nights;

            // Применяем скидку, если указана
            if (isset($data['discount_code'])) {
                $discount = $this->applyDiscount($data['discount_code'], $totalPrice);
                if ($discount) {
                    $totalPrice = $discount['final_price'];
                    $data['discount_amount'] = $discount['discount_amount'];
                    $data['discount_code'] = $discount['code'];
                }
            }

            // Генерируем номер бронирования
            $bookingNumber = 'BK' . strtoupper(uniqid());

            // Создаем бронирование
            $bookingData = array_merge($data, [
                'booking_number' => $bookingNumber,
                'total_price' => $totalPrice,
                'status' => 'confirmed', // Админ создает сразу подтвержденное бронирование
                'confirmed_at' => now(),
                'confirmed_by' => $admin->id,
                'admin_notes' => $request->get('admin_notes'),
            ]);

            $booking = Booking::create($bookingData);

            // Создаем запись о платеже, если указана оплата
            if ($request->has('payment_status') && $request->payment_status) {
                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'amount' => $totalPrice,
                    'payment_method' => $request->get('payment_method', 'manual'),
                    'status' => $request->payment_status,
                    'transaction_id' => $request->get('transaction_id'),
                    'payment_date' => now(),
                    'admin_notes' => $request->get('payment_notes'),
                ]);

                // Если оплата завершена, обновляем статус бронирования
                if ($request->payment_status === 'completed') {
                    $booking->update([
                        'payment_status' => 'paid',
                        'paid_at' => now()
                    ]);
                }
            }

            // Отправляем уведомление пользователю
            if ($booking->user) {
                $booking->user->notify(new BookingConfirmed($booking));
            }

            // Логируем действие
            $this->logAction($booking, 'create', 'Бронирование создано администратором');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Бронирование успешно создано',
                'data' => new BookingResource($booking->load(['user', 'room', 'payments']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании бронирования',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified booking.
     */
    public function show(Request $request, $id)
    {
        try {
            $booking = Booking::with([
                'user',
                'room',
                'payments',
                'reviews'
            ])->findOrFail($id);

            // Получаем историю изменений статуса
            $statusHistory = DB::table('booking_status_history')
                ->where('booking_id', $booking->id)
                ->orderBy('created_at', 'desc')
                ->get();

            // Собираем дополнительную информацию
            $additionalInfo = [
                'room_availability' => $this->getRoomAvailabilityForPeriod(
                    $booking->room_id,
                    $booking->check_in,
                    $booking->check_out
                ),
                'user_stats' => [
                    'total_bookings' => $booking->user->bookings()->count(),
                    'total_spent' => $booking->user->payments()->where('status', 'completed')->sum('amount'),
                    'avg_rating' => $booking->user->reviews()->avg('rating'),
                ],
                'similar_bookings' => Booking::where('room_id', $booking->room_id)
                    ->where('id', '!=', $booking->id)
                    ->where('status', 'confirmed')
                    ->orderBy('check_in', 'desc')
                    ->limit(5)
                    ->get(['id', 'check_in', 'check_out', 'total_price', 'status']),
            ];

            return response()->json([
                'success' => true,
                'data' => new BookingResource($booking),
                'status_history' => $statusHistory,
                'additional_info' => $additionalInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Бронирование не найдено'
            ], 404);
        }
    }

    /**
     * Update the specified booking.
     */
    public function update(UpdateBookingRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $booking = Booking::with(['room', 'user'])->findOrFail($id);
            $data = $request->validated();
            $admin = $request->user();

            // Сохраняем старые значения для логов
            $oldValues = $booking->toArray();
            $oldStatus = $booking->status;

            // Проверяем изменение дат
            if (isset($data['check_in']) || isset($data['check_out'])) {
                $newCheckIn = isset($data['check_in']) ? Carbon::parse($data['check_in']) : $booking->check_in;
                $newCheckOut = isset($data['check_out']) ? Carbon::parse($data['check_out']) : $booking->check_out;

                // Проверяем доступность номера на новые даты
                $isAvailable = $this->checkRoomAvailability(
                    $booking->room_id,
                    $newCheckIn,
                    $newCheckOut,
                    $booking->id
                );

                if (!$isAvailable) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Номер недоступен на выбранные даты'
                    ], 400);
                }

                // Пересчитываем стоимость при изменении дат
                if ($newCheckIn != $booking->check_in || $newCheckOut != $booking->check_out) {
                    $nights = $newCheckIn->diffInDays($newCheckOut);
                    $data['total_price'] = $booking->room->price_per_night * $nights;
                }
            }

            // Обновляем бронирование
            $booking->update($data);

            // Обрабатываем изменение статуса
            if (isset($data['status']) && $data['status'] !== $oldStatus) {
                $this->handleStatusChange($booking, $oldStatus, $data['status'], $admin);
            }

            // Логируем изменения
            $changedFields = array_diff_assoc($booking->toArray(), $oldValues);
            if (!empty($changedFields)) {
                $this->logAction($booking, 'update', 'Бронирование обновлено администратором', $oldValues, $changedFields);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Бронирование обновлено',
                'data' => new BookingResource($booking->load(['user', 'room', 'payments']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении бронирования',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Confirm booking.
     */
    public function confirm(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $booking = Booking::with('user')->findOrFail($id);
            $admin = $request->user();

            if ($booking->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Бронирование уже обработано'
                ], 400);
            }

            // Проверяем доступность номера
            $isAvailable = $this->checkRoomAvailability(
                $booking->room_id,
                $booking->check_in,
                $booking->check_out,
                $booking->id
            );

            if (!$isAvailable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Номер недоступен на выбранные даты'
                ], 400);
            }

            $oldStatus = $booking->status;
            $booking->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => $admin->id,
                'confirmation_notes' => $request->get('notes'),
            ]);

            // Отправляем уведомление пользователю
            if ($booking->user) {
                $booking->user->notify(new BookingConfirmed($booking));
            }

            // Логируем действие
            $this->logAction($booking, 'confirm', 'Бронирование подтверждено администратором');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Бронирование подтверждено',
                'data' => new BookingResource($booking)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при подтверждении бронирования',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Cancel booking.
     */
    public function cancel(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $booking = Booking::with(['user', 'payments'])->findOrFail($id);
            $admin = $request->user();

            if (!in_array($booking->status, ['pending', 'confirmed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Невозможно отменить бронирование с текущим статусом'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
                'refund_amount' => 'nullable|numeric|min:0|max:' . $booking->total_price,
                'refund_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldStatus = $booking->status;
            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $admin->id,
                'cancellation_reason' => $request->reason,
                'cancellation_notes' => $request->get('notes'),
            ]);

            // Обрабатываем возврат средств
            if ($request->has('refund_amount') && $request->refund_amount > 0) {
                $this->processRefund($booking, $request->refund_amount, $request->refund_notes);
            }

            // Отправляем уведомление пользователю
            if ($booking->user) {
                $booking->user->notify(new BookingCancelled($booking, $request->reason));
            }

            // Логируем действие
            $this->logAction($booking, 'cancel', "Бронирование отменено. Причина: {$request->reason}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Бронирование отменено',
                'data' => new BookingResource($booking)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отмене бронирования',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get booking calendar/availability.
     */
    public function calendar(Request $request)
    {
        try {
            $startDate = $request->get('start', Carbon::now()->startOfMonth()->toDateString());
            $endDate = $request->get('end', Carbon::now()->addMonths(2)->endOfMonth()->toDateString());
            $roomId = $request->get('room_id');

            $query = Booking::where('status', 'confirmed')
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('check_in', [$startDate, $endDate])
                        ->orWhereBetween('check_out', [$startDate, $endDate])
                        ->orWhere(function ($q2) use ($startDate, $endDate) {
                            $q2->where('check_in', '<', $startDate)
                                ->where('check_out', '>', $endDate);
                        });
                });

            if ($roomId) {
                $query->where('room_id', $roomId);
            }

            $bookings = $query->with(['room', 'user'])
                ->get()
                ->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'title' => "{$booking->room->room_number} - {$booking->user->name}",
                        'start' => $booking->check_in->toDateString(),
                        'end' => $booking->check_out->toDateString(),
                        'color' => $this->getBookingColor($booking->status),
                        'extendedProps' => [
                            'booking_number' => $booking->booking_number,
                            'user_name' => $booking->user->name,
                            'user_email' => $booking->user->email,
                            'room_number' => $booking->room->room_number,
                            'room_type' => $booking->room->type,
                            'guests' => $booking->guests_count,
                            'total_price' => $booking->total_price,
                            'status' => $booking->status,
                        ]
                    ];
                });

            // Получаем список всех номеров для фильтра
            $rooms = Room::where('status', 'active')
                ->get(['id', 'room_number', 'type', 'capacity', 'price_per_night']);

            return response()->json([
                'success' => true,
                'data' => $bookings,
                'rooms' => $rooms,
                'calendar_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении календаря бронирований',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get booking statistics.
     */
    public function statistics(Request $request)
    {
        try {
            $period = $request->get('period', '30days');

            switch ($period) {
                case '7days':
                    $startDate = Carbon::now()->subDays(7);
                    break;
                case '30days':
                    $startDate = Carbon::now()->subDays(30);
                    break;
                case '90days':
                    $startDate = Carbon::now()->subDays(90);
                    break;
                default:
                    $startDate = Carbon::now()->subDays(30);
            }

            $stats = [
                'bookings_overview' => [
                    'total' => Booking::where('created_at', '>=', $startDate)->count(),
                    'confirmed' => Booking::where('status', 'confirmed')->where('created_at', '>=', $startDate)->count(),
                    'pending' => Booking::where('status', 'pending')->where('created_at', '>=', $startDate)->count(),
                    'cancelled' => Booking::where('status', 'cancelled')->where('created_at', '>=', $startDate)->count(),
                    'completion_rate' => $this->calculateCompletionRate($startDate),
                ],
                'revenue' => [
                    'total' => Booking::where('status', 'confirmed')->where('created_at', '>=', $startDate)->sum('total_price'),
                    'by_month' => $this->getRevenueByMonth($startDate),
                    'average_booking_value' => Booking::where('status', 'confirmed')->where('created_at', '>=', $startDate)->avg('total_price'),
                    'projected_revenue' => $this->getProjectedRevenue(),
                ],
                'occupancy' => [
                    'average_occupancy_rate' => $this->calculateOccupancyRate($startDate),
                    'most_popular_room_type' => $this->getMostPopularRoomType($startDate),
                    'peak_days' => $this->getPeakDays($startDate),
                ],
                'cancellations' => [
                    'total_cancellations' => Booking::where('status', 'cancelled')->where('created_at', '>=', $startDate)->count(),
                    'cancellation_rate' => $this->calculateCancellationRate($startDate),
                    'refund_amount' => $this->getTotalRefunds($startDate),
                ],
                'bookings_by_source' => $this->getBookingsBySource($startDate),
                'top_customers' => $this->getTopCustomers($startDate, 10),
                'booking_trends' => $this->getBookingTrends($startDate),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period' => $period
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики бронирований',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Export bookings.
     */
    public function export(Request $request)
    {
        try {
            $query = Booking::with(['user', 'room', 'payments'])
                ->orderBy('created_at', 'desc');

            // Применяем фильтры
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from')) {
                $query->whereDate('check_in', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_to')) {
                $query->whereDate('check_out', '<=', Carbon::parse($request->date_to));
            }

            if ($request->has('room_id')) {
                $query->where('room_id', $request->room_id);
            }

            $bookings = $query->get();

            $format = $request->get('format', 'csv');

            if ($format === 'excel') {
                return $this->exportToExcel($bookings);
            }

            return $this->exportToCsv($bookings);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при экспорте бронирований',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk actions on bookings.
     */
    public function bulkActions(Request $request)
    {
        try {
            DB::beginTransaction();

            $action = $request->action;
            $bookingIds = $request->booking_ids;
            $admin = $request->user();

            if (empty($bookingIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не выбраны бронирования'
                ], 400);
            }

            $bookings = Booking::whereIn('id', $bookingIds)->get();

            if ($bookings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Бронирования не найдены'
                ], 404);
            }

            $processed = 0;
            $errors = [];

            foreach ($bookings as $booking) {
                try {
                    switch ($action) {
                        case 'confirm':
                            if ($booking->status === 'pending') {
                                $booking->update([
                                    'status' => 'confirmed',
                                    'confirmed_at' => now(),
                                    'confirmed_by' => $admin->id,
                                ]);
                                if ($booking->user) {
                                    $booking->user->notify(new BookingConfirmed($booking));
                                }
                            }
                            break;

                        case 'cancel':
                            if (in_array($booking->status, ['pending', 'confirmed'])) {
                                $booking->update([
                                    'status' => 'cancelled',
                                    'cancelled_at' => now(),
                                    'cancelled_by' => $admin->id,
                                    'cancellation_reason' => $request->reason ?? 'Массовая отмена',
                                ]);
                                if ($booking->user) {
                                    $booking->user->notify(new BookingCancelled($booking, $request->reason ?? 'Массовая отмена'));
                                }
                            }
                            break;

                        case 'delete':
                            // Можно удалять только определенные статусы
                            if (in_array($booking->status, ['cancelled', 'rejected'])) {
                                $booking->delete();
                            }
                            break;

                        case 'send_reminder':
                            $this->sendReminderEmail($booking);
                            break;

                        default:
                            throw new \Exception('Неизвестное действие');
                    }

                    $processed++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Обработано $processed бронирований",
                'processed' => $processed,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при массовых действиях',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get upcoming check-ins.
     */
    public function upcomingCheckIns(Request $request)
    {
        try {
            $days = $request->get('days', 7);
            $date = Carbon::now()->addDays($days);

            $bookings = Booking::with(['user', 'room'])
                ->where('status', 'confirmed')
                ->whereDate('check_in', '>=', today())
                ->whereDate('check_in', '<=', $date)
                ->orderBy('check_in')
                ->get();

            return response()->json([
                'success' => true,
                'data' => BookingResource::collection($bookings),
                'meta' => [
                    'days_ahead' => $days,
                    'total' => $bookings->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении предстоящих заездов'
            ], 500);
        }
    }

    /**
     * Get current stays.
     */
    public function currentStays(Request $request)
    {
        try {
            $bookings = Booking::with(['user', 'room'])
                ->where('status', 'confirmed')
                ->where('check_in', '<=', now())
                ->where('check_out', '>=', now())
                ->orderBy('check_in')
                ->get();

            return response()->json([
                'success' => true,
                'data' => BookingResource::collection($bookings),
                'meta' => [
                    'total' => $bookings->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении текущих проживаний'
            ], 500);
        }
    }

    /**
     * Helper method to check room availability.
     */
    private function checkRoomAvailability($roomId, $checkIn, $checkOut, $excludeBookingId = null)
    {
        $query = Booking::where('room_id', $roomId)
            ->where('status', 'confirmed')
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out', [$checkIn, $checkOut])
                    ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                        $q2->where('check_in', '<', $checkIn)
                            ->where('check_out', '>', $checkOut);
                    });
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->count() === 0;
    }

    /**
     * Helper method to apply discount.
     */
    private function applyDiscount($discountCode, $totalPrice)
    {
        // Реализуйте логику применения скидки
        // Проверьте валидность кода, срок действия и т.д.

        // Временная заглушка
        return null;
    }

    /**
     * Helper method to handle status change.
     */
    private function handleStatusChange($booking, $oldStatus, $newStatus, $admin)
    {
        // Записываем в историю статусов
        DB::table('booking_status_history')->insert([
            'booking_id' => $booking->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $admin->id,
            'notes' => 'Изменено администратором',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Обновляем соответствующие поля в зависимости от статуса
        switch ($newStatus) {
            case 'confirmed':
                $booking->update([
                    'confirmed_at' => now(),
                    'confirmed_by' => $admin->id,
                ]);
                break;

            case 'cancelled':
                $booking->update([
                    'cancelled_at' => now(),
                    'cancelled_by' => $admin->id,
                ]);
                break;

            case 'completed':
                $booking->update([
                    'completed_at' => now(),
                ]);
                break;
        }
    }

    /**
     * Helper method to process refund.
     */
    private function processRefund($booking, $amount, $notes = null)
    {
        // Создаем запись о возврате
        $refund = Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'payment_method' => 'refund',
            'status' => 'refunded',
            'transaction_id' => 'REF-' . strtoupper(uniqid()),
            'payment_date' => now(),
            'admin_notes' => $notes,
        ]);

        // Обновляем статус оригинального платежа
        if ($booking->payments->isNotEmpty()) {
            $booking->payments->first()->update([
                'status' => 'partially_refunded',
                'refunded_amount' => $amount,
                'refunded_at' => now(),
            ]);
        }

        return $refund;
    }

    /**
     * Helper method to get booking color for calendar.
     */
    private function getBookingColor($status)
    {
        $colors = [
            'pending' => '#ffc107', // желтый
            'confirmed' => '#28a745', // зеленый
            'cancelled' => '#dc3545', // красный
            'completed' => '#6c757d', // серый
        ];

        return $colors[$status] ?? '#007bff'; // синий по умолчанию
    }

    /**
     * Helper method to calculate completion rate.
     */
    private function calculateCompletionRate($startDate)
    {
        $total = Booking::where('created_at', '>=', $startDate)->count();
        $completed = Booking::where('status', 'confirmed')->where('created_at', '>=', $startDate)->count();

        if ($total === 0) return 0;
        return round(($completed / $total) * 100, 2);
    }

    /**
     * Helper method to get revenue by month.
     */
    private function getRevenueByMonth($startDate)
    {
        return Booking::where('status', 'confirmed')
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(total_price) as revenue')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->month => $item->revenue];
            });
    }

    /**
     * Helper method to calculate occupancy rate.
     */
    private function calculateOccupancyRate($startDate)
    {
        // Упрощенный расчет загрузки
        $totalRoomNights = Room::count() * Carbon::parse($startDate)->diffInDays(now());
        $bookedRoomNights = Booking::where('status', 'confirmed')
            ->where('created_at', '>=', $startDate)
            ->select(DB::raw('SUM(DATEDIFF(check_out, check_in)) as nights'))
            ->first()->nights ?? 0;

        if ($totalRoomNights === 0) return 0;
        return round(($bookedRoomNights / $totalRoomNights) * 100, 2);
    }

    /**
     * Helper method to export bookings to CSV.
     */
    private function exportToCsv($bookings)
    {
        $fileName = 'bookings-export-' . date('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function() use ($bookings) {
            $file = fopen('php://output', 'w');

            // Заголовки CSV
            fputcsv($file, [
                'Номер брони',
                'Дата создания',
                'Статус',
                'Клиент',
                'Email клиента',
                'Телефон клиента',
                'Номер комнаты',
                'Тип комнаты',
                'Дата заезда',
                'Дата выезда',
                'Ночей',
                'Гостей',
                'Общая стоимость',
                'Статус оплаты',
                'Способ оплаты',
                'ID транзакции',
                'Примечания'
            ]);

            // Данные
            foreach ($bookings as $booking) {
                fputcsv($file, [
                    $booking->booking_number,
                    $booking->created_at->format('Y-m-d H:i:s'),
                    $this->getStatusLabel($booking->status),
                    $booking->user->name,
                    $booking->user->email,
                    $booking->user->phone ?? '-',
                    $booking->room->room_number,
                    $booking->room->type,
                    $booking->check_in->format('Y-m-d'),
                    $booking->check_out->format('Y-m-d'),
                    $booking->check_in->diffInDays($booking->check_out),
                    $booking->guests_count,
                    $booking->total_price,
                    $booking->payment_status ?? '-',
                    $booking->payments->first()->payment_method ?? '-',
                    $booking->payments->first()->transaction_id ?? '-',
                    $booking->admin_notes ?? '-'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Helper method to log actions.
     */
    private function logAction($booking, $actionType, $description, $oldValues = null, $newValues = null)
    {
        \App\Models\AuditLog::create([
            'user_id' => $booking->user_id,
            'admin_id' => request()->user()->id,
            'action_type' => $actionType,
            'model_type' => Booking::class,
            'model_id' => $booking->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);
    }

    /**
     * Helper method to get status label.
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'pending' => 'В ожидании',
            'confirmed' => 'Подтверждено',
            'cancelled' => 'Отменено',
            'completed' => 'Завершено',
            'rejected' => 'Отклонено',
        ];

        return $labels[$status] ?? $status;
    }
}
