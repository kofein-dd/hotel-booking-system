<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Hotel;
use App\Models\Booking;
use App\Models\Review;
use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class RoomController extends Controller
{
    /**
     * Display a listing of rooms.
     */
    public function index(Request $request)
    {
        try {
            $query = Room::with(['hotel', 'bookings', 'reviews'])
                ->orderBy('created_at', 'desc');

            // Поиск по номеру комнаты, названию или описанию
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('room_number', 'LIKE', "%$search%")
                        ->orWhere('name', 'LIKE', "%$search%")
                        ->orWhere('description', 'LIKE', "%$search%")
                        ->orWhereHas('hotel', function ($hotelQuery) use ($search) {
                            $hotelQuery->where('name', 'LIKE', "%$search%");
                        });
                });
            }

            // Фильтр по отелю
            if ($request->has('hotel_id') && $request->hotel_id) {
                $query->where('hotel_id', $request->hotel_id);
            }

            // Фильтр по типу комнаты
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            // Фильтр по статусу
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Фильтр по вместимости
            if ($request->has('capacity_min') && $request->capacity_min) {
                $query->where('capacity', '>=', $request->capacity_min);
            }

            if ($request->has('capacity_max') && $request->capacity_max) {
                $query->where('capacity', '<=', $request->capacity_max);
            }

            // Фильтр по цене
            if ($request->has('price_min') && $request->price_min) {
                $query->where('price_per_night', '>=', $request->price_min);
            }

            if ($request->has('price_max') && $request->price_max) {
                $query->where('price_per_night', '<=', $request->price_max);
            }

            // Фильтр по наличию удобств
            if ($request->has('amenities') && $request->amenities) {
                $amenities = explode(',', $request->amenities);
                foreach ($amenities as $amenity) {
                    $query->where('amenities', 'LIKE', "%\"$amenity\"%");
                }
            }

            // Фильтр по доступности на даты
            if ($request->has('check_in') && $request->has('check_out')) {
                $checkIn = Carbon::parse($request->check_in);
                $checkOut = Carbon::parse($request->check_out);

                $bookedRoomIds = Booking::where('status', 'confirmed')
                    ->where(function ($q) use ($checkIn, $checkOut) {
                        $q->whereBetween('check_in', [$checkIn, $checkOut])
                            ->orWhereBetween('check_out', [$checkIn, $checkOut])
                            ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                                $q2->where('check_in', '<', $checkIn)
                                    ->where('check_out', '>', $checkOut);
                            });
                    })
                    ->pluck('room_id');

                if ($request->has('available_only') && $request->available_only) {
                    $query->whereNotIn('id', $bookedRoomIds);
                } else {
                    $query->with(['bookings' => function ($q) use ($checkIn, $checkOut) {
                        $q->where('status', 'confirmed')
                            ->where(function ($q2) use ($checkIn, $checkOut) {
                                $q2->whereBetween('check_in', [$checkIn, $checkOut])
                                    ->orWhereBetween('check_out', [$checkIn, $checkOut])
                                    ->orWhere(function ($q3) use ($checkIn, $checkOut) {
                                        $q3->where('check_in', '<', $checkIn)
                                            ->where('check_out', '>', $checkOut);
                                    });
                            });
                    }]);
                }
            }

            // Статистика
            $stats = [
                'total_rooms' => Room::count(),
                'active_rooms' => Room::where('status', 'active')->count(),
                'inactive_rooms' => Room::where('status', 'inactive')->count(),
                'under_maintenance' => Room::where('status', 'maintenance')->count(),
                'occupied_now' => $this->getOccupiedRoomsCount(),
                'available_now' => $this->getAvailableRoomsCount(),
                'avg_price' => Room::avg('price_per_night'),
                'total_capacity' => Room::sum('capacity'),
            ];

            // Типы комнат
            $roomTypes = Room::select('type', DB::raw('COUNT(*) as count'))
                ->groupBy('type')
                ->orderBy('count', 'desc')
                ->get();

            // Сортировка
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $rooms = $query->paginate($perPage);

            // Получаем список отелей для фильтра
            $hotels = Hotel::get(['id', 'name']);

            return response()->json([
                'success' => true,
                'data' => RoomResource::collection($rooms),
                'stats' => $stats,
                'room_types' => $roomTypes,
                'hotels' => $hotels,
                'meta' => [
                    'total' => $rooms->total(),
                    'per_page' => $rooms->perPage(),
                    'current_page' => $rooms->currentPage(),
                    'last_page' => $rooms->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка номеров',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created room.
     */
    public function store(StoreRoomRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $admin = $request->user();

            // Проверяем уникальность номера комнаты в отеле
            $existingRoom = Room::where('hotel_id', $data['hotel_id'])
                ->where('room_number', $data['room_number'])
                ->first();

            if ($existingRoom) {
                return response()->json([
                    'success' => false,
                    'message' => 'Номер комнаты уже существует в этом отеле'
                ], 400);
            }

            // Обработка удобств
            if (isset($data['amenities']) && is_array($data['amenities'])) {
                $data['amenities'] = json_encode($data['amenities']);
            }

            // Создаем комнату
            $room = Room::create($data);

            // Загрузка фотографий
            if ($request->hasFile('photos')) {
                $this->uploadRoomPhotos($room, $request->file('photos'));
            }

            // Логируем действие
            $this->logAction($room, 'create', 'Номер создан администратором');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Номер успешно создан',
                'data' => new RoomResource($room->load('hotel'))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании номера',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified room.
     */
    public function show(Request $request, $id)
    {
        try {
            $room = Room::with([
                'hotel',
                'bookings' => function ($query) {
                    $query->orderBy('check_in', 'desc')->limit(10);
                },
                'reviews' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(10);
                }
            ])->findOrFail($id);

            // Статистика комнаты
            $roomStats = [
                'total_bookings' => $room->bookings()->count(),
                'confirmed_bookings' => $room->bookings()->where('status', 'confirmed')->count(),
                'total_revenue' => $room->bookings()->where('status', 'confirmed')->sum('total_price'),
                'avg_rating' => $room->reviews()->avg('rating'),
                'total_reviews' => $room->reviews()->count(),
                'occupancy_rate' => $this->calculateRoomOccupancyRate($room),
                'avg_stay_length' => $this->calculateAverageStayLength($room),
                'popular_periods' => $this->getPopularPeriods($room),
            ];

            // Доступность на ближайшие 30 дней
            $availability = $this->getRoomAvailability($room, 30);

            // Похожие комнаты
            $similarRooms = Room::where('hotel_id', $room->hotel_id)
                ->where('id', '!=', $room->id)
                ->where('type', $room->type)
                ->where('status', 'active')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => new RoomResource($room),
                'stats' => $roomStats,
                'availability' => $availability,
                'similar_rooms' => RoomResource::collection($similarRooms)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Номер не найден'
            ], 404);
        }
    }

    /**
     * Update the specified room.
     */
    public function update(UpdateRoomRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $room = Room::findOrFail($id);
            $data = $request->validated();
            $admin = $request->user();

            // Проверяем уникальность номера комнаты при изменении
            if (isset($data['room_number']) && $data['room_number'] !== $room->room_number) {
                $existingRoom = Room::where('hotel_id', $room->hotel_id)
                    ->where('room_number', $data['room_number'])
                    ->where('id', '!=', $room->id)
                    ->first();

                if ($existingRoom) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Номер комнаты уже существует в этом отеле'
                    ], 400);
                }
            }

            // Сохраняем старые значения для логов
            $oldValues = $room->toArray();

            // Обработка удобств
            if (isset($data['amenities']) && is_array($data['amenities'])) {
                $data['amenities'] = json_encode($data['amenities']);
            }

            // Обновляем комнату
            $room->update($data);

            // Загрузка новых фотографий
            if ($request->hasFile('photos')) {
                $this->uploadRoomPhotos($room, $request->file('photos'));
            }

            // Удаление фотографий
            if ($request->has('delete_photos') && is_array($request->delete_photos)) {
                $this->deleteRoomPhotos($room, $request->delete_photos);
            }

            // Логируем изменения
            $changedFields = array_diff_assoc($room->toArray(), $oldValues);
            if (!empty($changedFields)) {
                $this->logAction($room, 'update', 'Номер обновлен администратором', $oldValues, $changedFields);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Данные номера обновлены',
                'data' => new RoomResource($room->load('hotel'))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении номера',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified room.
     */
    public function destroy(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $room = Room::with(['bookings', 'reviews'])->findOrFail($id);
            $admin = $request->user();

            // Проверяем, есть ли активные бронирования
            $activeBookings = $room->bookings()
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('check_out', '>', now())
                ->exists();

            if ($activeBookings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Невозможно удалить номер с активными бронированиями'
                ], 400);
            }

            // Проверяем, есть ли отзывы
            $hasReviews = $room->reviews()->exists();

            if ($hasReviews) {
                return response()->json([
                    'success' => false,
                    'message' => 'Невозможно удалить номер с отзывами. Вместо удаления установите статус "неактивен"'
                ], 400);
            }

            // Логируем перед удалением
            $this->logAction($room, 'delete', 'Номер удален администратором');

            // Удаляем фотографии
            $this->deleteAllRoomPhotos($room);

            // Удаляем комнату
            $room->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Номер удален'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении номера',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Toggle room status (active/inactive).
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $room = Room::findOrFail($id);
            $admin = $request->user();

            $oldStatus = $room->status;
            $newStatus = $room->status === 'active' ? 'inactive' : 'active';

            // Проверяем, можно ли деактивировать номер
            if ($newStatus === 'inactive') {
                $activeBookings = $room->bookings()
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where('check_out', '>', now())
                    ->exists();

                if ($activeBookings) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Невозможно деактивировать номер с активными бронированиями'
                    ], 400);
                }
            }

            $room->update(['status' => $newStatus]);

            // Логируем действие
            $this->logAction($room, 'status_change', "Статус номера изменен с '$oldStatus' на '$newStatus'");

            return response()->json([
                'success' => true,
                'message' => "Статус номера изменен на '$newStatus'",
                'data' => new RoomResource($room)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при изменении статуса номера',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Set room maintenance status.
     */
    public function setMaintenance(Request $request, $id)
    {
        try {
            $room = Room::findOrFail($id);
            $admin = $request->user();

            $validator = Validator::make($request->all(), [
                'maintenance_reason' => 'required|string|max:500',
                'estimated_completion' => 'nullable|date|after:now',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldStatus = $room->status;
            $room->update([
                'status' => 'maintenance',
                'maintenance_reason' => $request->maintenance_reason,
                'maintenance_start' => now(),
                'maintenance_estimated_completion' => $request->estimated_completion,
                'maintenance_notes' => $request->notes,
            ]);

            // Отменяем будущие бронирования
            $this->cancelFutureBookings($room, $request->maintenance_reason);

            // Логируем действие
            $this->logAction($room, 'maintenance', "Номер переведен в режим обслуживания. Причина: {$request->maintenance_reason}");

            return response()->json([
                'success' => true,
                'message' => 'Номер переведен в режим обслуживания',
                'data' => new RoomResource($room)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при переводе номера в режим обслуживания',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get room bookings.
     */
    public function bookings(Request $request, $id)
    {
        try {
            $room = Room::findOrFail($id);

            $query = $room->bookings()->with(['user', 'payments'])
                ->orderBy('check_in', 'desc');

            // Фильтрация по статусу
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Фильтрация по дате заезда
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('check_in', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('check_in', '<=', Carbon::parse($request->date_to));
            }

            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $bookings = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => BookingResource::collection($bookings),
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
                'message' => 'Ошибка при получении бронирований номера'
            ], 500);
        }
    }

    /**
     * Get room reviews.
     */
    public function reviews(Request $request, $id)
    {
        try {
            $room = Room::findOrFail($id);

            $query = $room->reviews()->with(['user', 'booking'])
                ->orderBy('created_at', 'desc');

            // Фильтрация по рейтингу
            if ($request->has('rating_min') && $request->rating_min) {
                $query->where('rating', '>=', $request->rating_min);
            }

            if ($request->has('rating_max') && $request->rating_max) {
                $query->where('rating', '<=', $request->rating_max);
            }

            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $reviews = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ReviewResource::collection($reviews),
                'meta' => [
                    'total' => $reviews->total(),
                    'per_page' => $reviews->perPage(),
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении отзывов номера'
            ], 500);
        }
    }

    /**
     * Get room availability calendar.
     */
    public function availability(Request $request, $id)
    {
        try {
            $room = Room::findOrFail($id);

            $startDate = $request->get('start', Carbon::now()->toDateString());
            $endDate = $request->get('end', Carbon::now()->addMonths(3)->toDateString());

            $bookings = $room->bookings()
                ->where('status', 'confirmed')
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('check_in', [$startDate, $endDate])
                        ->orWhereBetween('check_out', [$startDate, $endDate])
                        ->orWhere(function ($q2) use ($startDate, $endDate) {
                            $q2->where('check_in', '<', $startDate)
                                ->where('check_out', '>', $endDate);
                        });
                })
                ->get(['id', 'check_in', 'check_out', 'status', 'booking_number']);

            // Формируем календарь доступности
            $calendar = [];
            $currentDate = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            while ($currentDate <= $end) {
                $isAvailable = true;
                $bookingInfo = null;

                foreach ($bookings as $booking) {
                    if ($currentDate->between($booking->check_in, $booking->check_out->subDay())) {
                        $isAvailable = false;
                        $bookingInfo = [
                            'booking_id' => $booking->id,
                            'booking_number' => $booking->booking_number,
                            'status' => $booking->status,
                        ];
                        break;
                    }
                }

                $calendar[] = [
                    'date' => $currentDate->toDateString(),
                    'available' => $isAvailable,
                    'booking' => $bookingInfo,
                    'price' => $room->price_per_night,
                    'status' => $room->status,
                ];

                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'data' => $calendar,
                'room_info' => [
                    'id' => $room->id,
                    'room_number' => $room->room_number,
                    'name' => $room->name,
                    'type' => $room->type,
                    'price_per_night' => $room->price_per_night,
                    'status' => $room->status,
                ],
                'calendar_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                    'days' => count($calendar)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении доступности номера',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check room availability for specific dates.
     */
    public function checkAvailability(Request $request, $id)
    {
        try {
            $room = Room::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'check_in' => 'required|date|after:yesterday',
                'check_out' => 'required|date|after:check_in',
                'guests' => 'nullable|integer|min:1|max:' . $room->capacity
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $checkIn = Carbon::parse($request->check_in);
            $checkOut = Carbon::parse($request->check_out);
            $guests = $request->guests ?? 1;

            // Проверяем доступность
            $isAvailable = $this->isRoomAvailable($room, $checkIn, $checkOut);

            // Проверяем вместимость
            $hasCapacity = $guests <= $room->capacity;

            // Рассчитываем стоимость
            $nights = $checkIn->diffInDays($checkOut);
            $totalPrice = $room->price_per_night * $nights;

            // Собираем информацию о бронированиях в этот период
            $conflictingBookings = $room->bookings()
                ->where('status', 'confirmed')
                ->where(function ($q) use ($checkIn, $checkOut) {
                    $q->whereBetween('check_in', [$checkIn, $checkOut])
                        ->orWhereBetween('check_out', [$checkIn, $checkOut])
                        ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                            $q2->where('check_in', '<', $checkIn)
                                ->where('check_out', '>', $checkOut);
                        });
                })
                ->get(['id', 'check_in', 'check_out', 'booking_number', 'user_id']);

            return response()->json([
                'success' => true,
                'data' => [
                    'available' => $isAvailable && $hasCapacity && $room->status === 'active',
                    'room_status' => $room->status,
                    'has_capacity' => $hasCapacity,
                    'check_in' => $checkIn->toDateString(),
                    'check_out' => $checkOut->toDateString(),
                    'nights' => $nights,
                    'price_per_night' => $room->price_per_night,
                    'total_price' => $totalPrice,
                    'room_capacity' => $room->capacity,
                    'requested_guests' => $guests,
                    'conflicting_bookings' => $conflictingBookings,
                    'reasons' => !$isAvailable ? 'Номер занят на выбранные даты' :
                        ($room->status !== 'active' ? "Номер неактивен (статус: {$room->status})" :
                            (!$hasCapacity ? "Превышена вместимость номера" : 'Доступен'))
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при проверке доступности',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get room statistics.
     */
    public function statistics(Request $request, $id = null)
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

            if ($id) {
                // Статистика конкретного номера
                $room = Room::findOrFail($id);

                $roomStats = [
                    'bookings' => [
                        'total' => $room->bookings()->where('created_at', '>=', $startDate)->count(),
                        'confirmed' => $room->bookings()->where('status', 'confirmed')->where('created_at', '>=', $startDate)->count(),
                        'cancelled' => $room->bookings()->where('status', 'cancelled')->where('created_at', '>=', $startDate)->count(),
                        'revenue' => $room->bookings()->where('status', 'confirmed')->where('created_at', '>=', $startDate)->sum('total_price'),
                        'by_month' => $this->getRoomBookingsByMonth($room, $startDate),
                    ],
                    'occupancy' => [
                        'rate' => $this->calculateRoomOccupancyRate($room, $startDate),
                        'total_nights' => $this->getBookedNights($room, $startDate),
                        'avg_stay_length' => $this->calculateAverageStayLength($room, $startDate),
                        'peak_months' => $this->getPeakMonths($room, $startDate),
                    ],
                    'reviews' => [
                        'total' => $room->reviews()->where('created_at', '>=', $startDate)->count(),
                        'average_rating' => $room->reviews()->where('created_at', '>=', $startDate)->avg('rating'),
                        'rating_distribution' => $this->getRatingDistribution($room, $startDate),
                    ],
                    'performance' => [
                        'revenue_per_night' => $this->calculateRevenuePerNight($room, $startDate),
                        'utilization_rate' => $this->calculateUtilizationRate($room, $startDate),
                        'cancellation_rate' => $this->calculateRoomCancellationRate($room, $startDate),
                    ]
                ];

                return response()->json([
                    'success' => true,
                    'data' => $roomStats,
                    'period' => $period
                ]);

            } else {
                // Общая статистика по всем номерам
                $stats = [
                    'overview' => [
                        'total_rooms' => Room::count(),
                        'active_rooms' => Room::where('status', 'active')->count(),
                        'occupancy_rate' => $this->calculateOverallOccupancyRate($startDate),
                        'total_revenue' => Booking::where('status', 'confirmed')->where('created_at', '>=', $startDate)->sum('total_price'),
                        'avg_daily_rate' => $this->calculateAverageDailyRate($startDate),
                    ],
                    'by_type' => Room::select('type', DB::raw('COUNT(*) as count'))
                        ->groupBy('type')
                        ->orderBy('count', 'desc')
                        ->get(),
                    'by_status' => Room::select('status', DB::raw('COUNT(*) as count'))
                        ->groupBy('status')
                        ->orderBy('count', 'desc')
                        ->get(),
                    'top_performing_rooms' => $this->getTopPerformingRooms($startDate, 10),
                    'least_performing_rooms' => $this->getLeastPerformingRooms($startDate, 10),
                    'revenue_by_room_type' => $this->getRevenueByRoomType($startDate),
                ];

                return response()->json([
                    'success' => true,
                    'data' => $stats,
                    'period' => $period
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики номеров',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Export rooms list.
     */
    public function export(Request $request)
    {
        try {
            $query = Room::with('hotel')->orderBy('hotel_id')->orderBy('room_number');

            // Применяем фильтры
            if ($request->has('hotel_id')) {
                $query->where('hotel_id', $request->hotel_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            $rooms = $query->get();

            $format = $request->get('format', 'csv');

            if ($format === 'excel') {
                return $this->exportToExcel($rooms);
            }

            return $this->exportToCsv($rooms);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при экспорте номеров',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk update room prices.
     */
    public function bulkUpdatePrices(Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'room_ids' => 'required|array',
                'room_ids.*' => 'exists:rooms,id',
                'price_per_night' => 'required|numeric|min:0',
                'increase_percentage' => 'nullable|numeric|min:0|max:100',
                'reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $roomIds = $request->room_ids;
            $basePrice = $request->price_per_night;
            $increasePercentage = $request->increase_percentage;
            $reason = $request->reason;
            $admin = $request->user();

            $rooms = Room::whereIn('id', $roomIds)->get();
            $updatedCount = 0;

            foreach ($rooms as $room) {
                $oldPrice = $room->price_per_night;
                $newPrice = $basePrice;

                if ($increasePercentage) {
                    $newPrice = $oldPrice * (1 + ($increasePercentage / 100));
                }

                $room->update([
                    'price_per_night' => $newPrice,
                    'price_last_updated' => now(),
                    'price_update_reason' => $reason,
                ]);

                // Логируем изменение цены
                $this->logAction($room, 'price_update',
                    "Цена изменена с $oldPrice на $newPrice. Причина: $reason",
                    ['price_per_night' => $oldPrice],
                    ['price_per_night' => $newPrice]
                );

                $updatedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Цены обновлены для $updatedCount номеров",
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при массовом обновлении цен',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Helper method to upload room photos.
     */
    private function uploadRoomPhotos($room, $photos)
    {
        $uploadedPhotos = [];

        foreach ($photos as $photo) {
            $path = $photo->store("rooms/{$room->id}/photos", 'public');
            $uploadedPhotos[] = $path;
        }

        // Объединяем с существующими фото
        $existingPhotos = json_decode($room->photos ?? '[]', true);
        $allPhotos = array_merge($existingPhotos, $uploadedPhotos);

        $room->update(['photos' => json_encode($allPhotos)]);
    }

    /**
     * Helper method to delete room photos.
     */
    private function deleteRoomPhotos($room, $photoPaths)
    {
        $existingPhotos = json_decode($room->photos ?? '[]', true);
        $updatedPhotos = array_diff($existingPhotos, $photoPaths);

        // Удаляем файлы с диска
        foreach ($photoPaths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $room->update(['photos' => json_encode(array_values($updatedPhotos))]);
    }

    /**
     * Helper method to delete all room photos.
     */
    private function deleteAllRoomPhotos($room)
    {
        $photos = json_decode($room->photos ?? '[]', true);

        foreach ($photos as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * Helper method to get occupied rooms count.
     */
    private function getOccupiedRoomsCount()
    {
        return Booking::where('status', 'confirmed')
            ->where('check_in', '<=', now())
            ->where('check_out', '>=', now())
            ->distinct('room_id')
            ->count('room_id');
    }

    /**
     * Helper method to get available rooms count.
     */
    private function getAvailableRoomsCount()
    {
        $totalRooms = Room::where('status', 'active')->count();
        $occupiedRooms = $this->getOccupiedRoomsCount();

        return max(0, $totalRooms - $occupiedRooms);
    }

    /**
     * Helper method to calculate room occupancy rate.
     */
    private function calculateRoomOccupancyRate($room, $startDate = null)
    {
        $totalDays = $startDate ? Carbon::parse($startDate)->diffInDays(now()) : 365;
        $bookedDays = $this->getBookedNights($room, $startDate);

        if ($totalDays === 0) return 0;
        return round(($bookedDays / $totalDays) * 100, 2);
    }

    /**
     * Helper method to calculate average stay length.
     */
    private function calculateAverageStayLength($room, $startDate = null)
    {
        $bookings = $room->bookings()->where('status', 'confirmed');

        if ($startDate) {
            $bookings->where('created_at', '>=', $startDate);
        }

        $totalNights = $bookings->select(DB::raw('SUM(DATEDIFF(check_out, check_in)) as total_nights'))
            ->first()->total_nights ?? 0;

        $bookingCount = $bookings->count();

        if ($bookingCount === 0) return 0;
        return round($totalNights / $bookingCount, 2);
    }

    /**
     * Helper method to get room availability.
     */
    private function getRoomAvailability($room, $days = 30)
    {
        $availability = [];
        $startDate = Carbon::now();
        $endDate = Carbon::now()->addDays($days);

        $bookings = $room->bookings()
            ->where('status', 'confirmed')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('check_in', [$startDate, $endDate])
                    ->orWhereBetween('check_out', [$startDate, $endDate]);
            })
            ->get(['check_in', 'check_out']);

        for ($date = $startDate; $date <= $endDate; $date->addDay()) {
            $isAvailable = true;

            foreach ($bookings as $booking) {
                if ($date->between($booking->check_in, $booking->check_out->subDay())) {
                    $isAvailable = false;
                    break;
                }
            }

            $availability[] = [
                'date' => $date->toDateString(),
                'available' => $isAvailable && $room->status === 'active',
                'price' => $room->price_per_night
            ];
        }

        return $availability;
    }

    /**
     * Helper method to check if room is available.
     */
    private function isRoomAvailable($room, $checkIn, $checkOut)
    {
        if ($room->status !== 'active') {
            return false;
        }

        return $room->bookings()
            ->where('status', 'confirmed')
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out', [$checkIn, $checkOut])
                    ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                        $q2->where('check_in', '<', $checkIn)
                            ->where('check_out', '>', $checkOut);
                    });
            })
            ->doesntExist();
    }

    /**
     * Helper method to cancel future bookings.
     */
    private function cancelFutureBookings($room, $reason)
    {
        $futureBookings = $room->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_in', '>', now())
            ->get();

        foreach ($futureBookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => 'system',
                'cancellation_reason' => "Номер на обслуживании: $reason",
            ]);

            // Уведомляем пользователей
            if ($booking->user) {
                $booking->user->notify(new \App\Notifications\BookingCancelled($booking, "Номер на обслуживании: $reason"));
            }
        }
    }

    /**
     * Helper method to log actions.
     */
    private function logAction($room, $actionType, $description, $oldValues = null, $newValues = null)
    {
        \App\Models\AuditLog::create([
            'user_id' => null, // Системное действие
            'admin_id' => request()->user()->id,
            'action_type' => $actionType,
            'model_type' => Room::class,
            'model_id' => $room->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);
    }
}
