<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Models\Room;
use App\Models\Hotel;
use App\Models\Amenity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SearchController extends Controller
{
    /**
     * Главная страница поиска с формой
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        // Получаем популярные направления/отели для рекомендаций
        $popularHotels = Cache::remember('popular_hotels', 3600, function () {
            return Hotel::where('status', 'active')
                ->with(['rooms' => function ($query) {
                    $query->where('status', 'active')
                        ->orderBy('price_per_night')
                        ->take(1);
                }])
                ->whereHas('reviews', function ($query) {
                    $query->where('status', 'approved')
                        ->where('rating', '>=', 4);
                })
                ->orderByRaw('(SELECT COUNT(*) FROM reviews WHERE reviews.hotel_id = hotels.id AND reviews.status = "approved") DESC')
                ->limit(6)
                ->get();
        });

        // Получаем доступные удобства для фильтра
        $amenities = Amenity::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Получаем типы номеров
        $roomTypes = Room::where('status', 'active')
            ->distinct()
            ->pluck('type')
            ->filter()
            ->values();

        // Минимальная и максимальная цена
        $priceRange = Cache::remember('price_range', 86400, function () {
            $minPrice = Room::where('status', 'active')->min('price_per_night') ?? 0;
            $maxPrice = Room::where('status', 'active')->max('price_per_night') ?? 1000;

            return [
                'min' => floor($minPrice / 100) * 100, // округляем до сотен
                'max' => ceil($maxPrice / 100) * 100
            ];
        });

        // Значения по умолчанию для формы
        $defaultDates = [
            'check_in' => Carbon::today()->addDays(1)->format('Y-m-d'),
            'check_out' => Carbon::today()->addDays(3)->format('Y-m-d'),
        ];

        return view('search.index', compact(
            'popularHotels',
            'amenities',
            'roomTypes',
            'priceRange',
            'defaultDates'
        ));
    }

    /**
     * Выполнить поиск номеров
     *
     * @param SearchRequest $request
     * @return View|JsonResponse
     */
    public function search(SearchRequest $request)
    {
        $validated = $request->validated();

        // Параметры поиска
        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);
        $guests = $validated['guests'] ?? 1;
        $nights = $checkIn->diffInDays($checkOut);

        // Базовый запрос
        $query = Room::with(['hotel', 'amenities', 'reviews'])
            ->where('status', 'active')
            ->where('capacity', '>=', $guests)
            ->whereHas('hotel', function ($q) {
                $q->where('status', 'active');
            });

        // Фильтр по отелю, если указан
        if (!empty($validated['hotel_id'])) {
            $query->where('hotel_id', $validated['hotel_id']);
        }

        // Фильтр по типу номера
        if (!empty($validated['room_type'])) {
            $query->where('type', $validated['room_type']);
        }

        // Фильтр по цене
        if (!empty($validated['min_price'])) {
            $query->where('price_per_night', '>=', $validated['min_price']);
        }
        if (!empty($validated['max_price'])) {
            $query->where('price_per_night', '<=', $validated['max_price']);
        }

        // Фильтр по удобствам
        if (!empty($validated['amenities'])) {
            $amenities = is_array($validated['amenities'])
                ? $validated['amenities']
                : explode(',', $validated['amenities']);

            $query->whereHas('amenities', function ($q) use ($amenities) {
                $q->whereIn('amenities.id', $amenities);
            }, '=', count($amenities));
        }

        // Фильтр по рейтингу
        if (!empty($validated['min_rating'])) {
            $query->whereHas('reviews', function ($q) use ($validated) {
                $q->where('status', 'approved')
                    ->selectRaw('AVG(rating) as avg_rating')
                    ->havingRaw('AVG(rating) >= ?', [$validated['min_rating']]);
            });
        }

        // Исключаем занятые номера на выбранные даты
        $bookedRoomIds = $this->getBookedRoomIds($checkIn, $checkOut);
        if ($bookedRoomIds->isNotEmpty()) {
            $query->whereNotIn('id', $bookedRoomIds);
        }

        // Сортировка
        $sortBy = $validated['sort_by'] ?? 'price_per_night';
        $sortOrder = $validated['sort_order'] ?? 'asc';

        switch ($sortBy) {
            case 'price':
                $query->orderBy('price_per_night', $sortOrder);
                break;
            case 'rating':
                $query->orderBy('average_rating', $sortOrder === 'asc' ? 'desc' : 'asc');
                break;
            case 'capacity':
                $query->orderBy('capacity', $sortOrder);
                break;
            default:
                $query->orderBy('price_per_night', 'asc');
        }

        // Пагинация
        $perPage = $validated['per_page'] ?? 12;
        $rooms = $query->paginate($perPage)
            ->appends($request->except('page'));

        // Добавляем информацию о доступности и цене для каждого номера
        $rooms->getCollection()->transform(function ($room) use ($checkIn, $checkOut, $nights, $guests) {
            $room->available_for_dates = true;
            $room->total_price = $room->price_per_night * $nights;
            $room->nights = $nights;

            // Проверяем скидки
            $discount = $room->activeDiscounts()->first();
            if ($discount) {
                if ($discount->type === 'percentage') {
                    $room->total_price -= $room->total_price * $discount->value / 100;
                } else {
                    $room->total_price -= $discount->value;
                }
                $room->discount_applied = $discount;
            }

            $room->price_per_night_formatted = number_format($room->price_per_night, 0, '.', ' ');
            $room->total_price_formatted = number_format($room->total_price, 0, '.', ' ');

            return $room;
        });

        // Статистика поиска
        $searchStats = [
            'total_found' => $rooms->total(),
            'nights' => $nights,
            'check_in' => $checkIn->format('d.m.Y'),
            'check_out' => $checkOut->format('d.m.Y'),
            'guests' => $guests,
        ];

        // Сохраняем параметры поиска в сессии для повторного использования
        session(['last_search_params' => $validated]);

        // Если это AJAX-запрос (бесконечная прокрутка или фильтрация)
        if ($request->ajax() && !$request->isMethod('get')) {
            return response()->json([
                'rooms' => $rooms,
                'search_stats' => $searchStats,
                'html' => $request->has('html') ? view('search.partials.results', compact('rooms'))->render() : null
            ]);
        }

        // Для обычного запроса
        if ($request->wantsJson()) {
            return response()->json([
                'rooms' => $rooms,
                'search_stats' => $searchStats
            ]);
        }

        return view('search.results', compact('rooms', 'searchStats'));
    }

    /**
     * Быстрый поиск (автодополнение)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function quickSearch(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // Поиск отелей
        $hotels = Hotel::where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('location', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get(['id', 'name', 'location', 'main_photo']);

        // Поиск типов номеров
        $roomTypes = Room::where('status', 'active')
            ->where('type', 'like', "%{$query}%")
            ->distinct()
            ->limit(5)
            ->pluck('type')
            ->map(function ($type) {
                return ['type' => $type, 'name' => ucfirst($type)];
            });

        // Поиск по удобствам
        $amenities = Amenity::where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->limit(5)
            ->get(['id', 'name', 'icon']);

        $results = [
            'hotels' => $hotels,
            'room_types' => $roomTypes,
            'amenities' => $amenities,
        ];

        return response()->json($results);
    }

    /**
     * Поиск доступных дат для номера
     *
     * @param Request $request
     * @param Room $room
     * @return JsonResponse
     */
    public function availableDates(Request $request, Room $room): JsonResponse
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
            'guests' => 'nullable|integer|min:1|max:' . $room->capacity
        ]);

        $month = Carbon::parse($request->input('month'));
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();
        $guests = $request->input('guests', 1);

        // Получаем забронированные даты
        $bookings = $room->bookings()
            ->whereIn('status', ['confirmed', 'pending'])
            ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                $query->whereBetween('check_in', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('check_out', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($q) use ($startOfMonth, $endOfMonth) {
                        $q->where('check_in', '<', $startOfMonth)
                            ->where('check_out', '>', $endOfMonth);
                    });
            })
            ->get(['check_in', 'check_out']);

        // Собираем занятые даты
        $bookedDates = [];
        foreach ($bookings as $booking) {
            $start = max($booking->check_in, $startOfMonth);
            $end = min($booking->check_out, $endOfMonth);

            $current = $start->copy();
            while ($current->lte($end)) {
                $bookedDates[$current->format('Y-m-d')] = true;
                $current->addDay();
            }
        }

        // Получаем закрытые дни отеля
        $hotelClosedDays = $room->hotel->closed_days ?? [];
        if (is_string($hotelClosedDays)) {
            $hotelClosedDays = json_decode($hotelClosedDays, true) ?: [];
        }

        // Формируем календарь доступности
        $calendar = [];
        $currentDate = $startOfMonth->copy();

        while ($currentDate->lte($endOfMonth)) {
            $dateStr = $currentDate->format('Y-m-d');
            $isPast = $currentDate->isPast() && !$currentDate->isToday();
            $isBooked = isset($bookedDates[$dateStr]);
            $isClosed = in_array($dateStr, $hotelClosedDays);

            $calendar[$dateStr] = [
                'date' => $dateStr,
                'day' => $currentDate->day,
                'available' => !$isPast && !$isBooked && !$isClosed,
                'is_today' => $currentDate->isToday(),
                'is_past' => $isPast,
                'reason' => $isPast ? 'Прошедшая дата' :
                    ($isBooked ? 'Забронировано' :
                        ($isClosed ? 'Отель закрыт' : null))
            ];

            $currentDate->addDay();
        }

        // Минимальное и максимальное количество ночей
        $minNights = $room->min_nights ?? 1;
        $maxNights = $room->max_nights ?? 30;

        return response()->json([
            'calendar' => $calendar,
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'capacity' => $room->capacity,
                'min_nights' => $minNights,
                'max_nights' => $maxNights,
                'price_per_night' => $room->price_per_night
            ],
            'month' => $month->format('F Y')
        ]);
    }

    /**
     * Популярные поисковые запросы
     *
     * @return JsonResponse
     */
    public function popularSearches(): JsonResponse
    {
        $searches = Cache::remember('popular_searches', 3600, function () {
            // В реальном приложении здесь была бы статистика поисковых запросов
            // Пока возвращаем заранее подготовленные популярные варианты

            return [
                [
                    'check_in' => Carbon::today()->addDays(7)->format('Y-m-d'),
                    'check_out' => Carbon::today()->addDays(10)->format('Y-m-d'),
                    'guests' => 2,
                    'label' => 'Ближайшие выходные'
                ],
                [
                    'check_in' => Carbon::today()->addDays(30)->format('Y-m-d'),
                    'check_out' => Carbon::today()->addDays(37)->format('Y-m-d'),
                    'guests' => 4,
                    'label' => 'Отдых с семьей'
                ],
                [
                    'check_in' => Carbon::today()->addDays(60)->format('Y-m-d'),
                    'check_out' => Carbon::today()->addDays(67)->format('Y-m-d'),
                    'guests' => 2,
                    'label' => 'Романтический отдых'
                ]
            ];
        });

        return response()->json($searches);
    }

    /**
     * Получить ID занятых номеров на указанные даты
     *
     * @param Carbon $checkIn
     * @param Carbon $checkOut
     * @return \Illuminate\Support\Collection
     */
    private function getBookedRoomIds(Carbon $checkIn, Carbon $checkOut): \Illuminate\Support\Collection
    {
        return \App\Models\Booking::whereIn('status', ['confirmed', 'pending'])
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->whereBetween('check_in', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out', [$checkIn, $checkOut])
                    ->orWhere(function ($q) use ($checkIn, $checkOut) {
                        $q->where('check_in', '<', $checkIn)
                            ->where('check_out', '>', $checkOut);
                    });
            })
            ->pluck('room_id')
            ->unique();
    }

    /**
     * Сохранить поисковый запрос для статистики
     *
     * @param array $params
     * @return void
     */
    private function logSearch(array $params): void
    {
        // В реальном приложении здесь можно сохранять статистику поисков
        // Например, в таблицу search_logs или через сервис аналитики
        if (config('app.log_searches', false)) {
            \App\Models\SearchLog::create([
                'params' => json_encode($params),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'user_id' => auth()->id()
            ]);
        }
    }
}
