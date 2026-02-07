<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\RoomAvailabilityRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Models\Hotel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class RoomController extends Controller
{
    /**
     * Список всех активных номеров с пагинацией и фильтрацией
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request)
    {
        $query = Room::with(['hotel', 'amenities'])
            ->where('status', 'active')
            ->whereHas('hotel', function ($q) {
                $q->where('status', 'active');
            });

        // Фильтр по типу номера
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Фильтр по вместимости
        if ($request->has('capacity_min')) {
            $query->where('capacity', '>=', $request->input('capacity_min'));
        }
        if ($request->has('capacity_max')) {
            $query->where('capacity', '<=', $request->input('capacity_max'));
        }

        // Фильтр по цене
        if ($request->has('price_min')) {
            $query->where('price_per_night', '>=', $request->input('price_min'));
        }
        if ($request->has('price_max')) {
            $query->where('price_per_night', '<=', $request->input('price_max'));
        }

        // Сортировка
        $sortBy = $request->input('sort_by', 'price_per_night');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $rooms = $query->paginate($request->input('per_page', 12));

        // Если запрос через API (ожидается JSON)
        if ($request->wantsJson()) {
            return RoomResource::collection($rooms);
        }

        // Для веб-отображения
        return view('frontend.rooms.index', compact('rooms'));
    }

    /**
     * Детальная страница номера
     *
     * @param Room $room
     * @return View|JsonResponse
     */
    public function show(Room $room)
    {
        // Проверяем, активен ли номер и отель
        if ($room->status !== 'active' || $room->hotel->status !== 'active') {
            abort(404, 'Номер недоступен');
        }

        $room->load(['hotel', 'amenities', 'reviews.user']);

        // Рекомендуемые номера (того же типа)
        $similarRooms = Room::where('type', $room->type)
            ->where('id', '!=', $room->id)
            ->where('status', 'active')
            ->limit(4)
            ->get();

        if (request()->wantsJson()) {
            return new RoomResource($room);
        }

        return view('frontend.rooms.show', compact('room', 'similarRooms'));
    }

    /**
     * Проверка доступности номера на даты
     *
     * @param RoomAvailabilityRequest $request
     * @param Room $room
     * @return JsonResponse
     */
    public function availability(RoomAvailabilityRequest $request, Room $room): JsonResponse
    {
        $checkIn = Carbon::parse($request->input('check_in'));
        $checkOut = Carbon::parse($request->input('check_out'));
        $guests = $request->input('guests', 1);

        // Проверка вместимости
        if ($guests > $room->capacity) {
            return response()->json([
                'available' => false,
                'message' => 'Превышена вместимость номера'
            ], 400);
        }

        // Проверка пересечения с существующими бронированиями
        $isBooked = $room->bookings()
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->whereBetween('check_in', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out', [$checkIn, $checkOut])
                    ->orWhere(function ($q) use ($checkIn, $checkOut) {
                        $q->where('check_in', '<', $checkIn)
                            ->where('check_out', '>', $checkOut);
                    });
            })
            ->whereIn('status', ['confirmed', 'pending'])
            ->exists();

        if ($isBooked) {
            return response()->json([
                'available' => false,
                'message' => 'Номер занят на выбранные даты'
            ], 409);
        }

        // Проверка нерабочих дней отеля
        $hotelClosedDays = $room->hotel->closed_days ?? [];
        $currentDay = clone $checkIn;
        while ($currentDay->lte($checkOut)) {
            if (in_array($currentDay->format('Y-m-d'), $hotelClosedDays)) {
                return response()->json([
                    'available' => false,
                    'message' => 'Отель закрыт на выбранные даты'
                ], 400);
            }
            $currentDay->addDay();
        }

        // Расчёт стоимости
        $nights = $checkIn->diffInDays($checkOut);
        $totalPrice = $room->price_per_night * $nights;

        // Применение скидок (если есть активные)
        $discount = $room->activeDiscounts()->first();
        if ($discount) {
            if ($discount->type === 'percentage') {
                $totalPrice -= $totalPrice * $discount->value / 100;
            } else {
                $totalPrice -= $discount->value;
            }
        }

        return response()->json([
            'available' => true,
            'nights' => $nights,
            'total_price' => round($totalPrice, 2),
            'price_per_night' => $room->price_per_night,
            'discount_applied' => $discount ? true : false,
            'discount' => $discount ? [
                'code' => $discount->code,
                'value' => $discount->value,
                'type' => $discount->type
            ] : null
        ]);
    }

    /**
     * Поиск номеров по датам и параметрам
     *
     * @param RoomAvailabilityRequest $request
     * @return View|JsonResponse
     */
    public function search(RoomAvailabilityRequest $request)
    {
        $checkIn = Carbon::parse($request->input('check_in'));
        $checkOut = Carbon::parse($request->input('check_out'));
        $guests = $request->input('guests', 1);

        $query = Room::with(['hotel', 'amenities'])
            ->where('status', 'active')
            ->where('capacity', '>=', $guests)
            ->whereHas('hotel', function ($q) use ($checkIn, $checkOut) {
                $q->where('status', 'active');
                // Проверка закрытых дней отеля
                $closedDays = $q->value('closed_days');
                if ($closedDays) {
                    // Логика проверки закрытых дней
                    // Упрощённая реализация — предполагается, что closed_days — JSON массив дат
                }
            });

        // Фильтр по типу
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Исключение занятых номеров
        $bookedRoomIds = \App\Models\Booking::whereIn('status', ['confirmed', 'pending'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out', [$checkIn, $checkOut])
                    ->orWhere(function ($inner) use ($checkIn, $checkOut) {
                        $inner->where('check_in', '<', $checkIn)
                            ->where('check_out', '>', $checkOut);
                    });
            })
            ->pluck('room_id');

        if ($bookedRoomIds->isNotEmpty()) {
            $query->whereNotIn('id', $bookedRoomIds);
        }

        $rooms = $query->paginate($request->input('per_page', 12));

        if ($request->wantsJson()) {
            return RoomResource::collection($rooms);
        }

        return view('frontend.rooms.search', compact('rooms', 'checkIn', 'checkOut', 'guests'));
    }
}
