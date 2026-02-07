<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BookingRequest;
use App\Http\Requests\Api\CancelBookingRequest;
use App\Http\Requests\Api\CheckAvailabilityRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;

        // Применяем middleware аутентификации ко всем методам, кроме checkAvailability
        $this->middleware('auth:sanctum')->except(['checkAvailability']);
    }

    /**
     * Проверка доступности номера на даты
     *
     * @param CheckAvailabilityRequest $request
     * @param Room $room
     * @return JsonResponse
     */
    public function checkAvailability(CheckAvailabilityRequest $request, Room $room): JsonResponse
    {
        try {
            $data = $request->validated();

            $checkIn = Carbon::parse($data['check_in']);
            $checkOut = Carbon::parse($data['check_out']);
            $guests = $data['guests'] ?? 1;

            // Проверка доступности
            $availability = $this->bookingService->checkRoomAvailability(
                $room,
                $checkIn,
                $checkOut,
                $guests
            );

            if (!$availability['available']) {
                return response()->json([
                    'success' => false,
                    'message' => $availability['message'],
                    'data' => $availability
                ], 409);
            }

            // Расчет стоимости
            $priceDetails = $this->bookingService->calculatePrice(
                $room,
                $checkIn,
                $checkOut,
                $guests,
                $data['promo_code'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Номер доступен для бронирования',
                'data' => array_merge($availability, $priceDetails)
            ]);

        } catch (\Exception $e) {
            \Log::error('Check availability error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при проверке доступности',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Создание нового бронирования
     *
     * @param BookingRequest $request
     * @return JsonResponse
     */
    public function store(BookingRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = $request->user();

            // Проверяем, авторизован ли пользователь
            if (!$user) {
                // Для неавторизованных пользователей создаем временного пользователя
                // или требуем авторизацию (в зависимости от настроек)
                if (!config('booking.allow_anonymous', false)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Для бронирования необходимо авторизоваться',
                        'requires_auth' => true
                    ], 401);
                }
            }

            $room = Room::findOrFail($data['room_id']);

            // Проверка доступности
            $checkIn = Carbon::parse($data['check_in']);
            $checkOut = Carbon::parse($data['check_out']);

            $availability = $this->bookingService->checkRoomAvailability(
                $room,
                $checkIn,
                $checkOut,
                $data['guests']
            );

            if (!$availability['available']) {
                return response()->json([
                    'success' => false,
                    'message' => $availability['message'],
                    'data' => $availability
                ], 409);
            }

            // Создание бронирования
            $booking = $this->bookingService->createBooking(array_merge($data, [
                'user_id' => $user ? $user->id : null,
                'room_id' => $room->id,
                'hotel_id' => $room->hotel_id,
                'status' => 'pending',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]));

            // Отправка уведомлений
            $this->bookingService->sendBookingNotifications($booking);

            return response()->json([
                'success' => true,
                'message' => 'Бронирование успешно создано',
                'data' => new BookingResource($booking)
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Create booking error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании бронирования',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Список бронирований пользователя
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = Booking::with(['room.hotel', 'payment'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // Фильтрация по статусу
            if ($request->has('status')) {
                $statuses = explode(',', $request->input('status'));
                $query->whereIn('status', $statuses);
            }

            // Фильтрация по дате
            if ($request->has('date_from')) {
                $query->whereDate('check_in', '>=', $request->input('date_from'));
            }

            if ($request->has('date_to')) {
                $query->whereDate('check_out', '<=', $request->input('date_to'));
            }

            // Пагинация
            $perPage = $request->input('per_page', 15);
            $bookings = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => BookingResource::collection($bookings),
                'meta' => [
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                    'per_page' => $bookings->perPage(),
                    'total' => $bookings->total(),
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('List bookings error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка бронирований',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Просмотр деталей бронирования
     *
     * @param Request $request
     * @param Booking $booking
     * @return JsonResponse
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            // Проверяем, принадлежит ли бронирование пользователю
            if ($booking->user_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            $booking->load(['room.hotel', 'payment', 'user', 'reviews']);

            return response()->json([
                'success' => true,
                'data' => new BookingResource($booking)
            ]);

        } catch (\Exception $e) {
            \Log::error('Show booking error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении информации о бронировании',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Отмена бронирования
     *
     * @param CancelBookingRequest $request
     * @param Booking $booking
     * @return JsonResponse
     */
    public function cancel(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            // Проверяем, принадлежит ли бронирование пользователю
            if ($booking->user_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            // Проверяем, можно ли отменить бронирование
            $canCancel = $this->bookingService->canCancelBooking($booking);

            if (!$canCancel['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $canCancel['message'],
                    'data' => $canCancel
                ], 400);
            }

            // Отмена бронирования
            $cancellation = $this->bookingService->cancelBooking(
                $booking,
                $user,
                $request->input('reason'),
                $request->input('refund_requested', false)
            );

            return response()->json([
                'success' => true,
                'message' => 'Бронирование успешно отменено',
                'data' => array_merge(
                    ['booking' => new BookingResource($booking->fresh())],
                    $cancellation
                )
            ]);

        } catch (\Exception $e) {
            \Log::error('Cancel booking error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отмене бронирования',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Подтверждение бронирования (для администраторов)
     *
     * @param Request $request
     * @param Booking $booking
     * @return JsonResponse
     */
    public function confirm(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            // Только администраторы могут подтверждать бронирования
            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            // Проверяем, можно ли подтвердить бронирование
            if (!in_array($booking->status, ['pending', 'payment_pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Бронирование не может быть подтверждено в текущем статусе'
                ], 400);
            }

            // Подтверждение бронирования
            $booking->update([
                'status' => 'confirmed',
                'confirmed_by' => $user->id,
                'confirmed_at' => now()
            ]);

            // Отправка уведомления пользователю
            $this->bookingService->sendBookingConfirmation($booking);

            return response()->json([
                'success' => true,
                'message' => 'Бронирование успешно подтверждено',
                'data' => new BookingResource($booking->fresh())
            ]);

        } catch (\Exception $e) {
            \Log::error('Confirm booking error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при подтверждении бронирования',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Отклонение бронирования (для администраторов)
     *
     * @param Request $request
     * @param Booking $booking
     * @return JsonResponse
     */
    public function reject(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            // Только администраторы могут отклонять бронирования
            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            // Проверяем, можно ли отклонить бронирование
            if (!in_array($booking->status, ['pending', 'payment_pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Бронирование не может быть отклонено в текущем статусе'
                ], 400);
            }

            // Отклонение бронирования
            $booking->update([
                'status' => 'rejected',
                'rejection_reason' => $request->input('reason'),
                'rejected_by' => $user->id,
                'rejected_at' => now()
            ]);

            // Отправка уведомления пользователю
            $this->bookingService->sendBookingRejection($booking, $request->input('reason'));

            // Возврат средств, если была оплата
            if ($booking->payment && $booking->payment->status === 'completed') {
                $this->bookingService->processRefund($booking, $request->input('reason'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Бронирование отклонено',
                'data' => new BookingResource($booking->fresh())
            ]);

        } catch (\Exception $e) {
            \Log::error('Reject booking error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отклонении бронирования',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Изменение дат бронирования
     *
     * @param Request $request
     * @param Booking $booking
     * @return JsonResponse
     */
    public function changeDates(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            // Проверяем, принадлежит ли бронирование пользователю
            if ($booking->user_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            $request->validate([
                'check_in' => 'required|date|after:today',
                'check_out' => 'required|date|after:check_in',
                'reason' => 'nullable|string|max:500'
            ]);

            // Проверяем, можно ли изменить даты
            if (!in_array($booking->status, ['confirmed', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Даты не могут быть изменены для бронирования в текущем статусе'
                ], 400);
            }

            $checkIn = Carbon::parse($request->input('check_in'));
            $checkOut = Carbon::parse($request->input('check_out'));

            // Проверяем доступность на новые даты
            $availability = $this->bookingService->checkRoomAvailability(
                $booking->room,
                $checkIn,
                $checkOut,
                $booking->guests,
                $booking->id // исключаем текущее бронирование из проверки
            );

            if (!$availability['available']) {
                return response()->json([
                    'success' => false,
                    'message' => $availability['message'],
                    'data' => $availability
                ], 409);
            }

            // Расчет разницы в стоимости
            $priceDifference = $this->bookingService->calculateDateChangePrice(
                $booking,
                $checkIn,
                $checkOut
            );

            // Если пользователь запрашивает изменение дат
            if ($request->isMethod('post')) {
                $changeRequest = $this->bookingService->createDateChangeRequest(
                    $booking,
                    $checkIn,
                    $checkOut,
                    $priceDifference,
                    $request->input('reason'),
                    $user
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Запрос на изменение дат отправлен на рассмотрение',
                    'data' => [
                        'booking' => new BookingResource($booking->fresh()),
                        'change_request' => $changeRequest,
                        'price_difference' => $priceDifference
                    ]
                ]);
            }

            // Если это предварительный расчет
            return response()->json([
                'success' => true,
                'message' => 'Доступность проверена',
                'data' => [
                    'available' => true,
                    'price_difference' => $priceDifference,
                    'new_total' => $booking->total_price + $priceDifference
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Change dates error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при изменении дат бронирования',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Запрос на досрочный выезд
     *
     * @param Request $request
     * @param Booking $booking
     * @return JsonResponse
     */
    public function earlyCheckout(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            // Проверяем, принадлежит ли бронирование пользователю
            if ($booking->user_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            $request->validate([
                'check_out_date' => 'required|date|after:today|before_or_equal:' . $booking->check_out,
                'reason' => 'required|string|max:500'
            ]);

            // Проверяем, можно ли сделать досрочный выезд
            if ($booking->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Досрочный выезд возможен только для активных бронирований'
                ], 400);
            }

            $newCheckOut = Carbon::parse($request->input('check_out_date'));

            // Расчет возврата средств
            $refundAmount = $this->bookingService->calculateEarlyCheckoutRefund(
                $booking,
                $newCheckOut
            );

            // Если пользователь подтверждает досрочный выезд
            if ($request->input('confirm', false)) {
                $earlyCheckout = $this->bookingService->processEarlyCheckout(
                    $booking,
                    $newCheckOut,
                    $refundAmount,
                    $request->input('reason'),
                    $user
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Досрочный выезд оформлен',
                    'data' => [
                        'booking' => new BookingResource($booking->fresh()),
                        'refund_amount' => $refundAmount,
                        'early_checkout' => $earlyCheckout
                    ]
                ]);
            }

            // Если это предварительный расчет
            return response()->json([
                'success' => true,
                'message' => 'Расчет выполнен',
                'data' => [
                    'refund_amount' => $refundAmount,
                    'new_check_out' => $newCheckOut->format('Y-m-d'),
                    'nights_cancelled' => $booking->check_in->diffInDays($newCheckOut),
                    'refund_percentage' => config('booking.early_checkout_refund_percentage', 50)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Early checkout error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при оформлении досрочного выезда',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Получение квитанции бронирования
     *
     * @param Request $request
     * @param Booking $booking
     * @return JsonResponse
     */
    public function receipt(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            // Проверяем, принадлежит ли бронирование пользователю
            if ($booking->user_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            // Генерация данных для квитанции
            $receipt = $this->bookingService->generateReceipt($booking);

            return response()->json([
                'success' => true,
                'data' => $receipt
            ]);

        } catch (\Exception $e) {
            \Log::error('Receipt generation error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при генерации квитанции',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Статистика бронирований пользователя
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $stats = [
                'total_bookings' => Booking::where('user_id', $user->id)->count(),
                'active_bookings' => Booking::where('user_id', $user->id)
                    ->whereIn('status', ['confirmed', 'active'])
                    ->count(),
                'completed_bookings' => Booking::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->count(),
                'cancelled_bookings' => Booking::where('user_id', $user->id)
                    ->where('status', 'cancelled')
                    ->count(),
                'total_spent' => Booking::where('user_id', $user->id)
                    ->where('status', '!=', 'cancelled')
                    ->sum('total_price'),
                'favorite_room_type' => Booking::where('user_id', $user->id)
                    ->join('rooms', 'bookings.room_id', '=', 'rooms.id')
                    ->selectRaw('rooms.type, COUNT(*) as count')
                    ->groupBy('rooms.type')
                    ->orderBy('count', 'desc')
                    ->value('type'),
                'average_stay_length' => Booking::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->selectRaw('AVG(DATEDIFF(check_out, check_in)) as avg_days')
                    ->value('avg_days')
            ];

            // Ближайшее бронирование
            $upcomingBooking = Booking::where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'active'])
                ->where('check_in', '>=', today())
                ->orderBy('check_in')
                ->first();

            if ($upcomingBooking) {
                $stats['next_booking'] = new BookingResource($upcomingBooking);
                $stats['days_until_next'] = today()->diffInDays($upcomingBooking->check_in);
            }

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            \Log::error('Booking statistics error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
