<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RoomSearchRequest;
use App\Http\Requests\Api\RoomAvailabilityRequest;
use App\Http\Resources\RoomResource;
use App\Http\Resources\RoomDetailResource;
use App\Models\Room;
use App\Models\Hotel;
use App\Models\Amenity;
use App\Services\RoomService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RoomController extends Controller
{
    protected $roomService;

    public function __construct(RoomService $roomService)
    {
        $this->roomService = $roomService;
    }

    /**
     * Список всех доступных номеров
     *
     * @param RoomSearchRequest $request
     * @return JsonResponse
     */
    public function index(RoomSearchRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();

            // Используем сервис для поиска номеров
            $rooms = $this->roomService->searchRooms($filters);

            // Получаем дополнительные данные для фильтров
            $filterData = $this->getFilterData($filters);

            return response()->json([
                'success' => true,
                'data' => RoomResource::collection($rooms),
                'meta' => [
                    'current_page' => $rooms->currentPage(),
                    'last_page' => $rooms->lastPage(),
                    'per_page' => $rooms->perPage(),
                    'total' => $rooms->total(),
                    'filters' => $filterData
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Room list error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка номеров',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Детальная информация о номере
     *
     * @param Request $request
     * @param Room $room
     * @return JsonResponse
     */
    public function show(Request $request, Room $room): JsonResponse
    {
        try {
            // Проверяем, активен ли номер
            if ($room->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Номер недоступен'
                ], 404);
            }

            // Проверяем, активен ли отель
            if ($room->hotel->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Отель недоступен'
                ], 404);
            }

            // Загружаем связанные данные
            $room->load([
                'hotel',
                'amenities',
                'reviews' => function ($query) {
                    $query->where('status', 'approved')
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->with('user');
                }
            ]);

            // Увеличиваем счетчик просмотров
            if ($request->user()) {
                $room->increment('views_count');
            }

            // Похожие номера
            $similarRooms = Room::where('hotel_id', $room->hotel_id)
                ->where('id', '!=', $room->id)
                ->where('status', 'active')
                ->where('type', $room->type)
                ->with(['hotel', 'amenities'])
                ->limit(4)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'room' => new RoomDetailResource($room),
                    'similar_rooms' => RoomResource::collection($similarRooms),
                    'hotel_info' => [
                        'id' => $room->hotel->id,
                        'name' => $room->hotel->name,
                        'rating' => $room->hotel->average_rating,
                        'review_count' => $room->hotel->reviews()->where('status', 'approved')->count()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Room show error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении информации о номере',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
        try {
            $data = $request->validated();

            $checkIn = Carbon::parse($data['check_in']);
            $checkOut = Carbon::parse($data['check_out']);
            $guests = $data['guests'] ?? 1;

            // Проверка доступности
            $availability = $this->roomService->checkAvailability(
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
            $priceDetails = $this->roomService->calculatePrice(
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
            \Log::error('Room availability error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при проверке доступности',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Календарь доступности номера
     *
     * @param Request $request
     * @param Room $room
     * @return JsonResponse
     */
    public function calendar(Request $request, Room $room): JsonResponse
    {
        try {
            $request->validate([
                'month' => 'required|date_format:Y-m',
                'guests' => 'nullable|integer|min:1|max:' . $room->capacity
            ]);

            $month = Carbon::parse($request->input('month'));
            $startOfMonth = $month->copy()->startOfMonth();
            $endOfMonth = $month->copy()->endOfMonth();
            $guests = $request->input('guests', 1);

            // Получаем календарь доступности
            $calendar = $this->roomService->getAvailabilityCalendar(
                $room,
                $startOfMonth,
                $endOfMonth,
                $guests
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'calendar' => $calendar,
                    'room' => [
                        'id' => $room->id,
                        'name' => $room->name,
                        'capacity' => $room->capacity,
                        'min_nights' => $room->min_nights ?? 1,
                        'max_nights' => $room->max_nights ?? 30,
                        'price_per_night' => $room->price_per_night
                    ],
                    'month' => $month->format('F Y'),
                    'year' => $month->year,
                    'month_number' => $month->month
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Room calendar error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении календаря доступности',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Получение фотографий номера
     *
     * @param Request $request
     * @param Room $room
     * @return JsonResponse
     */
    public function photos(Request $request, Room $room): JsonResponse
    {
        try {
            $photos = $room->photos ?? [];

            if (is_string($photos)) {
                $photos = json_decode($photos, true) ?? [];
            }

            // Формируем полные URL для фотографий
            $photos = array_map(function ($photo) {
                return [
                    'url' => asset('storage/' . $photo),
                    'thumbnail' => asset('storage/' . $photo),
                    'original' => asset('storage/' . $photo)
                ];
            }, $photos);

            return response()->json([
                'success' => true,
                'data' => [
                    'room_id' => $room->id,
                    'room_name' => $room->name,
                    'photos' => $photos,
                    'total' => count($photos)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Room photos error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении фотографий номера',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Получение отзывов о номере
     *
     * @param Request $request
     * @param Room $room
     * @return JsonResponse
     */
    public function reviews(Request $request, Room $room): JsonResponse
    {
        try {
            $query = $room->reviews()
                ->where('status', 'approved')
                ->with('user')
                ->orderBy('created_at', 'desc');

            // Фильтрация по рейтингу
            if ($request->has('rating')) {
                $rating = $request->input('rating');
                if (is_numeric($rating)) {
                    $query->where('rating', $rating);
                } elseif (str_contains($rating, '-')) {
                    list($min, $max) = explode('-', $rating);
                    $query->whereBetween('rating', [(int)$min, (int)$max]);
                }
            }

            // Пагинация
            $perPage = $request->input('per_page', 10);
            $reviews = $query->paginate($perPage);

            // Статистика рейтингов
            $ratingStats = $room->reviews()
                ->where('status', 'approved')
                ->selectRaw('rating, COUNT(*) as count')
                ->groupBy('rating')
                ->orderBy('rating', 'desc')
                ->get()
                ->keyBy('rating');

            $totalReviews = $reviews->total();
            $averageRating = $room->average_rating;

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => $reviews->items(),
                    'rating_stats' => $ratingStats,
                    'average_rating' => $averageRating,
                    'total_reviews' => $totalReviews,
                    'meta' => [
                        'current_page' => $reviews->currentPage(),
                        'last_page' => $reviews->lastPage(),
                        'per_page' => $reviews->perPage(),
                        'total' => $totalReviews
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Room reviews error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении отзывов о номере',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Получение удобств номера
     *
     * @param Request $request
     * @param Room $room
     * @return JsonResponse
     */
    public function amenities(Request $request, Room $room): JsonResponse
    {
        try {
            $amenities = $room->amenities()
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->groupBy('category');

            return response()->json([
                'success' => true,
                'data' => [
                    'room_id' => $room->id,
                    'room_name' => $room->name,
                    'amenities' => $amenities,
                    'categories' => $amenities->keys()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Room amenities error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении удобств номера',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Популярные номера
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function popular(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 6);

            $popularRooms = Cache::remember("popular_rooms_{$limit}", 3600, function () use ($limit) {
                return Room::where('status', 'active')
                    ->whereHas('hotel', function ($query) {
                        $query->where('status', 'active');
                    })
                    ->with(['hotel', 'amenities'])
                    ->orderBy('views_count', 'desc')
                    ->orderBy('bookings_count', 'desc')
                    ->limit($limit)
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => RoomResource::collection($popularRooms)
            ]);

        } catch (\Exception $e) {
            \Log::error('Popular rooms error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении популярных номеров',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Рекомендуемые номера
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recommended(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $limit = $request->input('limit', 6);

            $recommendedRooms = Cache::remember("recommended_rooms_{$user->id}_{$limit}", 1800, function () use ($user, $limit) {
                // Если пользователь авторизован, используем его историю бронирований
                if ($user) {
                    $userRoomTypes = $user->bookings()
                        ->with('room')
                        ->get()
                        ->pluck('room.type')
                        ->filter()
                        ->unique()
                        ->toArray();

                    if (!empty($userRoomTypes)) {
                        return Room::where('status', 'active')
                            ->whereIn('type', $userRoomTypes)
                            ->whereHas('hotel', function ($query) {
                                $query->where('status', 'active');
                            })
                            ->with(['hotel', 'amenities'])
                            ->inRandomOrder()
                            ->limit($limit)
                            ->get();
                    }
                }

                // Если пользователь не авторизован или нет истории, возвращаем номера с лучшими отзывами
                return Room::where('status', 'active')
                    ->whereHas('hotel', function ($query) {
                        $query->where('status', 'active');
                    })
                    ->with(['hotel', 'amenities'])
                    ->orderBy('average_rating', 'desc')
                    ->orderBy('reviews_count', 'desc')
                    ->limit($limit)
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => RoomResource::collection($recommendedRooms)
            ]);

        } catch (\Exception $e) {
            \Log::error('Recommended rooms error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении рекомендуемых номеров',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Получение фильтров для поиска
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function filters(Request $request): JsonResponse
    {
        try {
            $hotelId = $request->input('hotel_id');

            $filters = Cache::remember("room_filters_{$hotelId}", 3600, function () use ($hotelId) {
                $query = Room::where('status', 'active');

                if ($hotelId) {
                    $query->where('hotel_id', $hotelId);
                }

                // Типы номеров
                $roomTypes = $query->clone()
                    ->distinct()
                    ->pluck('type')
                    ->filter()
                    ->values();

                // Диапазон цен
                $priceRange = [
                    'min' => (int) $query->clone()->min('price_per_night') ?? 0,
                    'max' => (int) $query->clone()->max('price_per_night') ?? 1000
                ];

                // Вместимость
                $capacityRange = [
                    'min' => (int) $query->clone()->min('capacity') ?? 1,
                    'max' => (int) $query->clone()->max('capacity') ?? 10
                ];

                // Удобства
                $amenities = Amenity::where('is_active', true)
                    ->orderBy('category')
                    ->orderBy('name')
                    ->get(['id', 'name', 'category', 'icon'])
                    ->groupBy('category');

                // Отели (если не указан конкретный отель)
                $hotels = [];
                if (!$hotelId) {
                    $hotels = Hotel::where('status', 'active')
                        ->orderBy('name')
                        ->get(['id', 'name', 'location']);
                }

                return [
                    'room_types' => $roomTypes,
                    'price_range' => $priceRange,
                    'capacity_range' => $capacityRange,
                    'amenities' => $amenities,
                    'hotels' => $hotels
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $filters
            ]);

        } catch (\Exception $e) {
            \Log::error('Room filters error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении фильтров',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Статистика номеров
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $hotelId = $request->input('hotel_id');

            $stats = Cache::remember("room_statistics_{$hotelId}", 1800, function () use ($hotelId) {
                $query = Room::where('status', 'active');

                if ($hotelId) {
                    $query->where('hotel_id', $hotelId);
                }

                $totalRooms = $query->count();
                $totalCapacity = $query->sum('capacity');
                $averagePrice = $query->avg('price_per_night');
                $averageRating = $query->avg('average_rating');

                // Распределение по типам
                $typeDistribution = $query->clone()
                    ->selectRaw('type, COUNT(*) as count, AVG(price_per_night) as avg_price')
                    ->groupBy('type')
                    ->orderBy('count', 'desc')
                    ->get();

                // Самые популярные номера
                $popularRooms = $query->clone()
                    ->orderBy('bookings_count', 'desc')
                    ->orderBy('views_count', 'desc')
                    ->limit(5)
                    ->get(['id', 'name', 'type', 'bookings_count', 'views_count']);

                // Доход по типам номеров (если есть данные о бронированиях)
                $revenueByType = \App\Models\Booking::where('status', 'completed')
                    ->when($hotelId, function ($q) use ($hotelId) {
                        $q->where('hotel_id', $hotelId);
                    })
                    ->join('rooms', 'bookings.room_id', '=', 'rooms.id')
                    ->selectRaw('rooms.type, SUM(bookings.total_price) as revenue, COUNT(*) as bookings_count')
                    ->groupBy('rooms.type')
                    ->orderBy('revenue', 'desc')
                    ->get();

                return [
                    'total_rooms' => $totalRooms,
                    'total_capacity' => $totalCapacity,
                    'average_price' => round($averagePrice, 2),
                    'average_rating' => round($averageRating, 1),
                    'type_distribution' => $typeDistribution,
                    'popular_rooms' => $popularRooms,
                    'revenue_by_type' => $revenueByType
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            \Log::error('Room statistics error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Получение данных для фильтров
     *
     * @param array $filters
     * @return array
     */
    private function getFilterData(array $filters): array
    {
        $filterData = [];

        // Если есть даты, добавляем информацию о них
        if (isset($filters['check_in']) && isset($filters['check_out'])) {
            $checkIn = Carbon::parse($filters['check_in']);
            $checkOut = Carbon::parse($filters['check_out']);
            $filterData['dates'] = [
                'check_in' => $checkIn->format('Y-m-d'),
                'check_out' => $checkOut->format('Y-m-d'),
                'nights' => $checkIn->diffInDays($checkOut)
            ];
        }

        // Если указан отель
        if (isset($filters['hotel_id'])) {
            $hotel = Hotel::find($filters['hotel_id']);
            if ($hotel) {
                $filterData['hotel'] = [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                    'location' => $hotel->location
                ];
            }
        }

        return $filterData;
    }
}
