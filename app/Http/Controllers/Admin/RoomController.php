<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Amenity;
use App\Models\Photo;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class RoomController extends Controller
{
    /**
     * Display a listing of rooms.
     */
    public function index(Request $request): View
    {
        if (!Gate::allows('manage-rooms')) {
            abort(403);
        }

        $query = Room::with(['hotel', 'type', 'amenities', 'photos']);

        // Фильтры
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('hotel_id')) {
            $query->where('hotel_id', $request->hotel_id);
        }

        if ($request->filled('type_id')) {
            $query->where('type_id', $request->type_id);
        }

        if ($request->filled('capacity')) {
            $query->where('capacity', '>=', $request->capacity);
        }

        if ($request->filled('price_from')) {
            $query->where('price_per_night', '>=', $request->price_from);
        }

        if ($request->filled('price_to')) {
            $query->where('price_per_night', '<=', $request->price_to);
        }

        if ($request->filled('amenities')) {
            $amenities = is_array($request->amenities) ? $request->amenities : [$request->amenities];
            $query->whereHas('amenities', function ($q) use ($amenities) {
                $q->whereIn('amenities.id', $amenities);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('room_number', 'like', "%{$search}%")
                    ->orWhereHas('hotel', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Сортировка
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        if (in_array($sortField, ['price_per_night', 'capacity', 'size', 'order', 'created_at'])) {
            $query->orderBy($sortField, $sortDirection);
        }

        $rooms = $query->paginate(30);

        $hotels = Hotel::where('status', 'active')->get(['id', 'name']);
        $types = RoomType::all();
        $amenitiesList = Amenity::orderBy('category')->orderBy('name')->get();
        $statuses = ['active', 'inactive', 'maintenance', 'renovation'];

        // Статистика для отображения
        $stats = [
            'total' => Room::count(),
            'active' => Room::where('status', 'active')->count(),
            'occupied' => $this->getOccupiedRoomsCount(),
            'maintenance' => Room::where('status', 'maintenance')->count(),
        ];

        return view('admin.rooms.index', compact(
            'rooms',
            'hotels',
            'types',
            'amenitiesList',
            'statuses',
            'stats'
        ));
    }

    /**
     * Show the form for creating a new room.
     */
    public function create(): View
    {
        if (!Gate::allows('create-rooms')) {
            abort(403);
        }

        $hotels = Hotel::where('status', 'active')->get();
        $types = RoomType::all();
        $amenities = Amenity::orderBy('category')->orderBy('name')->get();
        $categories = Amenity::distinct()->pluck('category');

        return view('admin.rooms.create', compact('hotels', 'types', 'amenities', 'categories'));
    }

    /**
     * Store a newly created room.
     */
    public function store(Request $request): RedirectResponse
    {
        if (!Gate::allows('create-rooms')) {
            abort(403);
        }

        $validated = $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'type_id' => 'required|exists:room_types,id',
            'name' => 'required|string|max:255',
            'room_number' => 'required|string|max:50|unique:rooms,room_number',
            'description' => 'required|string|min:10',
            'short_description' => 'nullable|string|max:500',
            'capacity' => 'required|integer|min:1',
            'max_capacity' => 'nullable|integer|min:1|gte:capacity',
            'price_per_night' => 'required|numeric|min:0',
            'weekend_price' => 'nullable|numeric|min:0',
            'seasonal_price' => 'nullable|numeric|min:0',
            'size' => 'nullable|numeric|min:0',
            'size_unit' => 'nullable|in:m²,ft²',
            'bed_type' => 'required|string|max:100',
            'bed_count' => 'required|integer|min:1',
            'view_type' => 'nullable|string|max:100',
            'floor' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive,maintenance,renovation',
            'is_smoking_allowed' => 'nullable|boolean',
            'is_pet_friendly' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
            'amenities' => 'nullable|array',
            'amenities.*' => 'exists:amenities,id',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'photo_titles' => 'nullable|array',
            'photo_titles.*' => 'nullable|string|max:255',
            'photo_descriptions' => 'nullable|array',
            'photo_descriptions.*' => 'nullable|string|max:500',
        ]);

        // Создаем номер
        $room = Room::create([
            'hotel_id' => $validated['hotel_id'],
            'type_id' => $validated['type_id'],
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name'] . '-' . $validated['room_number']),
            'room_number' => $validated['room_number'],
            'description' => $validated['description'],
            'short_description' => $validated['short_description'],
            'capacity' => $validated['capacity'],
            'max_capacity' => $validated['max_capacity'] ?? $validated['capacity'],
            'price_per_night' => $validated['price_per_night'],
            'weekend_price' => $validated['weekend_price'],
            'seasonal_price' => $validated['seasonal_price'],
            'size' => $validated['size'],
            'size_unit' => $validated['size_unit'] ?? 'm²',
            'bed_type' => $validated['bed_type'],
            'bed_count' => $validated['bed_count'],
            'view_type' => $validated['view_type'],
            'floor' => $validated['floor'],
            'status' => $validated['status'],
            'is_smoking_allowed' => $validated['is_smoking_allowed'] ?? false,
            'is_pet_friendly' => $validated['is_pet_friendly'] ?? false,
            'is_featured' => $validated['is_featured'] ?? false,
            'order' => $validated['order'] ?? Room::max('order') + 1,
            'meta_title' => $validated['name'],
            'meta_description' => $validated['short_description'] ?? substr($validated['description'], 0, 160),
        ]);

        // Привязываем удобства
        if (!empty($validated['amenities'])) {
            $room->amenities()->sync($validated['amenities']);
        }

        // Загружаем фотографии
        if ($request->hasFile('photos')) {
            $this->uploadRoomPhotos($request, $room);
        }

        // Создаем календарь доступности на год вперед
        $this->createAvailabilityCalendar($room);

        return redirect()->route('admin.rooms.show', $room)
            ->with('success', 'Номер успешно создан.');
    }

    /**
     * Display the specified room.
     */
    public function show(Room $room): View
    {
        if (!Gate::allows('view-room', $room)) {
            abort(403);
        }

        $room->load(['hotel', 'type', 'amenities', 'photos', 'bookings' => function ($query) {
            $query->whereIn('status', ['pending', 'confirmed'])
                ->where('check_out', '>=', now())
                ->orderBy('check_in')
                ->limit(10);
        }]);

        // Статистика номера
        $stats = $this->getRoomStatistics($room);

        // Ближайшие бронирования
        $upcomingBookings = $room->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_out', '>=', now())
            ->with('user')
            ->orderBy('check_in')
            ->limit(10)
            ->get();

        // Отзывы о номере
        $reviews = $room->reviews()
            ->where('status', 'approved')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.rooms.show', compact('room', 'stats', 'upcomingBookings', 'reviews'));
    }

    /**
     * Show the form for editing the specified room.
     */
    public function edit(Room $room): View
    {
        if (!Gate::allows('edit-room', $room)) {
            abort(403);
        }

        $room->load(['amenities', 'photos']);

        $hotels = Hotel::where('status', 'active')->get();
        $types = RoomType::all();
        $amenities = Amenity::orderBy('category')->orderBy('name')->get();
        $categories = Amenity::distinct()->pluck('category');
        $selectedAmenities = $room->amenities->pluck('id')->toArray();

        return view('admin.rooms.edit', compact(
            'room',
            'hotels',
            'types',
            'amenities',
            'categories',
            'selectedAmenities'
        ));
    }

    /**
     * Update the specified room.
     */
    public function update(Request $request, Room $room): RedirectResponse
    {
        if (!Gate::allows('edit-room', $room)) {
            abort(403);
        }

        $validated = $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'type_id' => 'required|exists:room_types,id',
            'name' => 'required|string|max:255',
            'room_number' => 'required|string|max:50|unique:rooms,room_number,' . $room->id,
            'description' => 'required|string|min:10',
            'short_description' => 'nullable|string|max:500',
            'capacity' => 'required|integer|min:1',
            'max_capacity' => 'nullable|integer|min:1|gte:capacity',
            'price_per_night' => 'required|numeric|min:0',
            'weekend_price' => 'nullable|numeric|min:0',
            'seasonal_price' => 'nullable|numeric|min:0',
            'size' => 'nullable|numeric|min:0',
            'size_unit' => 'nullable|in:m²,ft²',
            'bed_type' => 'required|string|max:100',
            'bed_count' => 'required|integer|min:1',
            'view_type' => 'nullable|string|max:100',
            'floor' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive,maintenance,renovation',
            'is_smoking_allowed' => 'nullable|boolean',
            'is_pet_friendly' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
            'amenities' => 'nullable|array',
            'amenities.*' => 'exists:amenities,id',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:500',
        ]);

        // Обновляем slug если изменилось имя
        if ($room->name !== $validated['name']) {
            $validated['slug'] = Str::slug($validated['name'] . '-' . $validated['room_number']);
        }

        $room->update($validated);

        // Обновляем удобства
        if (isset($validated['amenities'])) {
            $room->amenities()->sync($validated['amenities']);
        } else {
            $room->amenities()->detach();
        }

        // Очищаем кэш
        $this->clearRoomCache($room);

        return redirect()->route('admin.rooms.show', $room)
            ->with('success', 'Информация о номере обновлена.');
    }

    /**
     * Upload photos for room.
     */
    public function uploadPhotos(Request $request, Room $room): RedirectResponse
    {
        if (!Gate::allows('edit-room', $room)) {
            abort(403);
        }

        $request->validate([
            'photos' => 'required|array|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'titles' => 'nullable|array',
            'titles.*' => 'nullable|string|max:255',
            'descriptions' => 'nullable|array',
            'descriptions.*' => 'nullable|string|max:500',
            'is_main' => 'nullable|integer',
        ]);

        $uploadedPhotos = $this->uploadRoomPhotos($request, $room);

        // Устанавливаем главное фото
        if ($request->has('is_main') && $request->is_main) {
            Photo::where('room_id', $room->id)->update(['is_main' => false]);
            Photo::where('id', $request->is_main)->update(['is_main' => true]);
        }

        $this->clearRoomCache($room);

        return back()->with('success', 'Фотографии успешно загружены.');
    }

    /**
     * Upload room photos helper.
     */
    private function uploadRoomPhotos(Request $request, Room $room): array
    {
        $uploadedPhotos = [];

        foreach ($request->file('photos') as $index => $photo) {
            try {
                // Генерируем уникальное имя файла
                $fileName = 'room_' . $room->id . '_' . time() . '_' . Str::random(10) . '.' .
                    $photo->getClientOriginalExtension();

                // Сохраняем оригинал
                $path = $photo->storeAs('rooms/' . $room->id, $fileName, 'public');

                // Создаем миниатюры
                $this->createPhotoThumbnails($photo, $fileName, $room->id);

                // Создаем запись в базе данных
                $photoRecord = Photo::create([
                    'room_id' => $room->id,
                    'path' => $path,
                    'title' => $request->titles[$index] ?? null,
                    'description' => $request->descriptions[$index] ?? null,
                    'order' => Photo::where('room_id', $room->id)->max('order') + 1,
                    'is_main' => Photo::where('room_id', $room->id)->where('is_main', true)->count() === 0,
                ]);

                $uploadedPhotos[] = $photoRecord;
            } catch (\Exception $e) {
                \Log::error('Failed to upload room photo: ' . $e->getMessage());
                continue;
            }
        }

        return $uploadedPhotos;
    }

    /**
     * Create thumbnails for photo.
     */
    private function createPhotoThumbnails($photo, $fileName, $roomId): void
    {
        $sizes = [
            'thumb' => [300, 200],
            'medium' => [800, 600],
            'large' => [1200, 800],
        ];

        $image = Image::make($photo);

        foreach ($sizes as $sizeName => $dimensions) {
            $thumbnailPath = 'rooms/' . $roomId . '/' . $sizeName . '/' . $fileName;

            $image->resize($dimensions[0], $dimensions[1], function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            Storage::disk('public')->put($thumbnailPath, $image->encode());
        }
    }

    /**
     * Update photo information.
     */
    public function updatePhoto(Request $request, Photo $photo): RedirectResponse
    {
        if (!Gate::allows('edit-room', $photo->room)) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'order' => 'nullable|integer|min:0',
            'is_main' => 'nullable|boolean',
        ]);

        // Если устанавливаем как главное
        if ($request->has('is_main') && $request->is_main) {
            Photo::where('room_id', $photo->room_id)->update(['is_main' => false]);
            $validated['is_main'] = true;
        }

        $photo->update($validated);

        $this->clearRoomCache($photo->room);

        return back()->with('success', 'Информация о фотографии обновлена.');
    }

    /**
     * Delete a photo.
     */
    public function deletePhoto(Photo $photo): RedirectResponse
    {
        if (!Gate::allows('edit-room', $photo->room)) {
            abort(403);
        }

        // Удаляем файлы
        $paths = [
            $photo->path,
            'rooms/' . $photo->room_id . '/thumb/' . basename($photo->path),
            'rooms/' . $photo->room_id . '/medium/' . basename($photo->path),
            'rooms/' . $photo->room_id . '/large/' . basename($photo->path),
        ];

        foreach ($paths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        // Если это было главное фото, устанавливаем новое
        if ($photo->is_main) {
            $newMainPhoto = Photo::where('room_id', $photo->room_id)
                ->where('id', '!=', $photo->id)
                ->first();

            if ($newMainPhoto) {
                $newMainPhoto->update(['is_main' => true]);
            }
        }

        $photo->delete();

        $this->clearRoomCache($photo->room);

        return back()->with('success', 'Фотография удалена.');
    }

    /**
     * Change room status.
     */
    public function changeStatus(Request $request, Room $room): RedirectResponse
    {
        if (!Gate::allows('edit-room', $room)) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|in:active,inactive,maintenance,renovation',
            'reason' => 'nullable|string|max:500',
            'estimated_completion' => 'nullable|date|after:today',
        ]);

        $oldStatus = $room->status;
        $room->update($validated);

        // Отменяем будущие бронирования если номер стал недоступен
        if (in_array($validated['status'], ['maintenance', 'renovation', 'inactive'])) {
            $this->cancelFutureBookings($room, $validated['reason'] ?? 'Номер временно недоступен');
        }

        $this->clearRoomCache($room);

        return back()->with('success', 'Статус номера изменен с "' . $oldStatus . '" на "' . $validated['status'] . '".');
    }

    /**
     * Cancel future bookings for room.
     */
    private function cancelFutureBookings(Room $room, string $reason): void
    {
        $futureBookings = Booking::where('room_id', $room->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_in', '>', now())
            ->get();

        foreach ($futureBookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason . ' (автоматическая отмена)',
                'cancelled_at' => now(),
            ]);

            // Отправляем уведомление пользователю
            // Notification::send($booking->user, new BookingCancelled($booking, $reason));
        }
    }

    /**
     * Delete room.
     */
    public function destroy(Room $room): RedirectResponse
    {
        if (!Gate::allows('delete-room', $room)) {
            abort(403);
        }

        // Проверяем, есть ли активные бронирования
        $activeBookings = Booking::where('room_id', $room->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_out', '>=', now())
            ->count();

        if ($activeBookings > 0) {
            return back()->withErrors([
                'error' => 'Нельзя удалить номер с активными бронированиями. ' .
                    'Сначала отмените или завершите бронирования.'
            ]);
        }

        // Удаляем фотографии
        foreach ($room->photos as $photo) {
            $this->deletePhoto($photo);
        }

        // Удаляем связи
        $room->amenities()->detach();

        // Мягкое удаление
        $room->delete();

        return redirect()->route('admin.rooms.index')
            ->with('success', 'Номер удален.');
    }

    /**
     * Get room availability calendar.
     */
    public function calendar(Room $room): View
    {
        if (!Gate::allows('view-room', $room)) {
            abort(403);
        }

        $bookings = $room->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_out', '>=', now())
            ->orderBy('check_in')
            ->get();

        $unavailableDates = [];
        foreach ($bookings as $booking) {
            $start = \Carbon\Carbon::parse($booking->check_in);
            $end = \Carbon\Carbon::parse($booking->check_out);

            for ($date = $start; $date->lt($end); $date->addDay()) {
                $unavailableDates[] = $date->format('Y-m-d');
            }
        }

        // Специальные цены и блокировки
        $specialPrices = $room->specialPrices()
            ->where('date', '>=', now()->format('Y-m-d'))
            ->orderBy('date')
            ->get();

        $blockedDates = $room->blockedDates()
            ->where('date', '>=', now()->format('Y-m-d'))
            ->orderBy('date')
            ->get();

        return view('admin.rooms.calendar', compact(
            'room',
            'bookings',
            'unavailableDates',
            'specialPrices',
            'blockedDates'
        ));
    }

    /**
     * Set special price for date.
     */
    public function setSpecialPrice(Request $request, Room $room): RedirectResponse
    {
        if (!Gate::allows('edit-room', $room)) {
            abort(403);
        }

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'price' => 'required|numeric|min:0',
            'type' => 'required|in:increase,decrease,fixed',
            'reason' => 'nullable|string|max:500',
        ]);

        $room->specialPrices()->updateOrCreate(
            ['date' => $validated['date']],
            [
                'price' => $validated['price'],
                'type' => $validated['type'],
                'reason' => $validated['reason'],
            ]
        );

        $this->clearRoomCache($room);

        return back()->with('success', 'Специальная цена установлена.');
    }

    /**
     * Block date for room.
     */
    public function blockDate(Request $request, Room $room): RedirectResponse
    {
        if (!Gate::allows('edit-room', $room)) {
            abort(403);
        }

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'reason' => 'required|string|max:500',
        ]);

        // Проверяем, нет ли бронирований на эту дату
        $hasBooking = Booking::where('room_id', $room->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($query) use ($validated) {
                $query->whereDate('check_in', '<=', $validated['date'])
                    ->whereDate('check_out', '>', $validated['date']);
            })
            ->exists();

        if ($hasBooking) {
            return back()->withErrors(['date' => 'На эту дату уже есть бронирование.']);
        }

        $room->blockedDates()->create([
            'date' => $validated['date'],
            'reason' => $validated['reason'],
        ]);

        $this->clearRoomCache($room);

        return back()->with('success', 'Дата заблокирована.');
    }

    /**
     * Unblock date.
     */
    public function unblockDate(Request $request, Room $room): RedirectResponse
    {
        if (!Gate::allows('edit-room', $room)) {
            abort(403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $room->blockedDates()->where('date', $validated['date'])->delete();

        $this->clearRoomCache($room);

        return back()->with('success', 'Дата разблокирована.');
    }

    /**
     * Get room statistics.
     */
    private function getRoomStatistics(Room $room): array
    {
        $today = now();

        return [
            'total_bookings' => $room->bookings()->count(),
            'completed_bookings' => $room->bookings()->where('status', 'completed')->count(),
            'upcoming_bookings' => $room->bookings()
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('check_out', '>=', $today)
                ->count(),
            'occupancy_rate' => $this->calculateOccupancyRate($room),
            'total_revenue' => $room->bookings()
                ->where('status', 'completed')
                ->sum('total_price'),
            'average_rating' => $room->reviews()
                    ->where('status', 'approved')
                    ->avg('rating') ?? 0,
            'review_count' => $room->reviews()
                ->where('status', 'approved')
                ->count(),
        ];
    }

    /**
     * Calculate room occupancy rate.
     */
    private function calculateOccupancyRate(Room $room): float
    {
        $daysInPeriod = 30; // За последние 30 дней
        $endDate = now();
        $startDate = now()->subDays($daysInPeriod);

        $occupiedDays = 0;

        $bookings = $room->bookings()
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where('check_in', '<=', $endDate)
                    ->where('check_out', '>=', $startDate);
            })
            ->get();

        foreach ($bookings as $booking) {
            $checkIn = max($booking->check_in, $startDate);
            $checkOut = min($booking->check_out, $endDate);

            $occupiedDays += $checkIn->diffInDays($checkOut);
        }

        return $daysInPeriod > 0 ? round(($occupiedDays / $daysInPeriod) * 100, 2) : 0;
    }

    /**
     * Get occupied rooms count.
     */
    private function getOccupiedRoomsCount(): int
    {
        $today = now();

        return Booking::whereIn('status', ['pending', 'confirmed'])
            ->whereDate('check_in', '<=', $today)
            ->whereDate('check_out', '>=', $today)
            ->distinct('room_id')
            ->count('room_id');
    }

    /**
     * Create availability calendar for room.
     */
    private function createAvailabilityCalendar(Room $room): void
    {
        $startDate = now();
        $endDate = now()->addYear();

        // Здесь можно создать записи в таблице availability_calendar
        // для каждого дня на год вперед с базовой ценой
    }

    /**
     * Clear room cache.
     */
    private function clearRoomCache(Room $room): void
    {
        // Очищаем кэши связанные с номером
        $cacheKeys = [
            'room_' . $room->id,
            'room_photos_' . $room->id,
            'room_availability_' . $room->id,
            'rooms_list',
            'featured_rooms',
        ];

        foreach ($cacheKeys as $key) {
            \Cache::forget($key);
        }
    }

    /**
     * Get room statistics report.
     */
    public function statistics(Request $request): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Статистика по номерам
        $roomStats = Room::withCount(['bookings' => function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);
        }])
            ->withSum(['bookings' => function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->where('status', 'completed');
            }], 'total_price')
            ->withAvg(['reviews' => function ($query) {
                $query->where('status', 'approved');
            }], 'rating')
            ->orderBy('bookings_count', 'desc')
            ->get();

        // Распределение по типам номеров
        $typeStats = RoomType::withCount(['rooms'])
            ->withCount(['rooms as booked_count' => function ($query) use ($dateFrom, $dateTo) {
                $query->whereHas('bookings', function ($q) use ($dateFrom, $dateTo) {
                    $q->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);
                });
            }])
            ->get();

        // Статистика занятости
        $occupancyStats = $this->getOccupancyStatistics($dateFrom, $dateTo);

        // Популярные удобства
        $popularAmenities = Amenity::withCount(['rooms' => function ($query) {
            $query->where('status', 'active');
        }])
            ->orderBy('rooms_count', 'desc')
            ->limit(10)
            ->get();

        return view('admin.rooms.statistics', compact(
            'roomStats',
            'typeStats',
            'occupancyStats',
            'popularAmenities',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Get occupancy statistics.
     */
    private function getOccupancyStatistics(string $dateFrom, string $dateTo): array
    {
        $totalRooms = Room::where('status', 'active')->count();

        $bookedRooms = Booking::whereBetween('check_in', [$dateFrom, $dateTo])
            ->whereIn('status', ['confirmed', 'completed'])
            ->distinct('room_id')
            ->count('room_id');

        $occupancyRate = $totalRooms > 0 ? round(($bookedRooms / $totalRooms) * 100, 2) : 0;

        // Ежедневная занятость
        $dailyOccupancy = Booking::select(
            DB::raw('DATE(check_in) as date'),
            DB::raw('COUNT(DISTINCT room_id) as booked_rooms')
        )
            ->whereBetween('check_in', [$dateFrom, $dateTo])
            ->whereIn('status', ['confirmed', 'completed'])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'total_rooms' => $totalRooms,
            'booked_rooms' => $bookedRooms,
            'occupancy_rate' => $occupancyRate,
            'daily_occupancy' => $dailyOccupancy,
        ];
    }

    /**
     * Export rooms data.
     */
    public function export(Request $request)
    {
        if (!Gate::allows('export-rooms')) {
            abort(403);
        }

        $rooms = Room::with(['hotel', 'type', 'amenities', 'photos'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('hotel_id'), function ($query) use ($request) {
                $query->where('hotel_id', $request->hotel_id);
            })
            ->when($request->filled('type_id'), function ($query) use ($request) {
                $query->where('type_id', $request->type_id);
            })
            ->orderBy('hotel_id')
            ->orderBy('room_number')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="rooms_' . date('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function() use ($rooms) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'ID',
                'Отель',
                'Номер комнаты',
                'Название',
                'Тип',
                'Статус',
                'Вместимость',
                'Цена за ночь',
                'Размер',
                'Этаж',
                'Тип кровати',
                'Кол-во кроватей',
                'Вид из окна',
                'Курение',
                'Животные',
                'Удобства',
                'Фото',
                'Создан',
            ], ';');

            foreach ($rooms as $room) {
                $amenities = $room->amenities->pluck('name')->implode(', ');
                $photoCount = $room->photos->count();

                fputcsv($file, [
                    $room->id,
                    $room->hotel->name,
                    $room->room_number,
                    $room->name,
                    $room->type->name ?? 'N/A',
                    $room->status,
                    $room->capacity,
                    $room->price_per_night,
                    $room->size ? $room->size . ' ' . $room->size_unit : '',
                    $room->floor,
                    $room->bed_type,
                    $room->bed_count,
                    $room->view_type,
                    $room->is_smoking_allowed ? 'Да' : 'Нет',
                    $room->is_pet_friendly ? 'Да' : 'Нет',
                    $amenities,
                    $photoCount . ' фото',
                    $room->created_at->format('d.m.Y H:i'),
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk update room prices.
     */
    public function bulkUpdatePrices(Request $request): View|RedirectResponse
    {
        if (!Gate::allows('edit-rooms')) {
            abort(403);
        }

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'room_ids' => 'required|array',
                'room_ids.*' => 'exists:rooms,id',
                'action' => 'required|in:increase,decrease,set',
                'value' => 'required|numeric|min:0',
                'value_type' => 'required|in:percent,fixed',
            ]);

            $rooms = Room::whereIn('id', $validated['room_ids'])->get();
            $updated = 0;

            foreach ($rooms as $room) {
                $newPrice = $this->calculateNewPrice(
                    $room->price_per_night,
                    $validated['action'],
                    $validated['value'],
                    $validated['value_type']
                );

                if ($newPrice > 0) {
                    $room->update(['price_per_night' => $newPrice]);
                    $updated++;

                    $this->clearRoomCache($room);
                }
            }

            return back()->with('success', "Цены обновлены для {$updated} номеров.");
        }

        $rooms = Room::where('status', 'active')->get(['id', 'name', 'room_number', 'price_per_night']);

        return view('admin.rooms.bulk-update-prices', compact('rooms'));
    }

    /**
     * Calculate new price.
     */
    private function calculateNewPrice(float $currentPrice, string $action, float $value, string $valueType): float
    {
        if ($action === 'set') {
            return $value;
        }

        $change = $valueType === 'percent'
            ? $currentPrice * ($value / 100)
            : $value;

        return $action === 'increase'
            ? $currentPrice + $change
            : max(0, $currentPrice - $change);
    }

    /**
     * Dashboard widget data.
     */
    public function dashboardWidget(): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('view-dashboard')) {
            abort(403);
        }

        $totalRooms = Room::count();
        $availableRooms = Room::where('status', 'active')->count();
        $occupiedRooms = $this->getOccupiedRoomsCount();
        $maintenanceRooms = Room::where('status', 'maintenance')->count();

        return response()->json([
            'total_rooms' => $totalRooms,
            'available_rooms' => $availableRooms,
            'occupied_rooms' => $occupiedRooms,
            'occupancy_rate' => $availableRooms > 0
                ? round(($occupiedRooms / $availableRooms) * 100, 2)
                : 0,
            'maintenance_rooms' => $maintenanceRooms,
        ]);
    }

    /**
     * Check room availability.
     */
    public function checkAvailability(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        $room = Room::findOrFail($validated['room_id']);

        $isAvailable = !Booking::where('room_id', $room->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($query) use ($validated) {
                $query->whereBetween('check_in', [$validated['check_in'], $validated['check_out']])
                    ->orWhereBetween('check_out', [$validated['check_in'], $validated['check_out']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('check_in', '<', $validated['check_in'])
                            ->where('check_out', '>', $validated['check_out']);
                    });
            })
            ->exists();

        // Проверяем заблокированные даты
        $blockedDates = $room->blockedDates()
            ->whereBetween('date', [$validated['check_in'], $validated['check_out']])
            ->exists();

        $isAvailable = $isAvailable && !$blockedDates;

        // Рассчитываем цену
        $price = $this->calculateStayPrice($room, $validated['check_in'], $validated['check_out']);

        return response()->json([
            'available' => $isAvailable,
            'price' => $price,
            'room' => [
                'name' => $room->name,
                'capacity' => $room->capacity,
                'bed_type' => $room->bed_type,
            ],
        ]);
    }

    /**
     * Calculate stay price.
     */
    private function calculateStayPrice(Room $room, string $checkIn, string $checkOut): float
    {
        $checkInDate = \Carbon\Carbon::parse($checkIn);
        $checkOutDate = \Carbon\Carbon::parse($checkOut);
        $nights = $checkInDate->diffInDays($checkOutDate);

        $totalPrice = 0;

        for ($i = 0; $i < $nights; $i++) {
            $currentDate = $checkInDate->copy()->addDays($i);
            $dateStr = $currentDate->format('Y-m-d');

            // Проверяем специальную цену
            $specialPrice = $room->specialPrices()
                ->where('date', $dateStr)
                ->first();

            if ($specialPrice) {
                $totalPrice += $this->applySpecialPrice($room->price_per_night, $specialPrice);
            } else {
                // Проверяем выходной день
                $isWeekend = $currentDate->isWeekend();
                $totalPrice += $isWeekend && $room->weekend_price
                    ? $room->weekend_price
                    : $room->price_per_night;
            }
        }

        return round($totalPrice, 2);
    }

    /**
     * Apply special price.
     */
    private function applySpecialPrice(float $basePrice, $specialPrice): float
    {
        switch ($specialPrice->type) {
            case 'fixed':
                return $specialPrice->price;
            case 'increase':
                return $basePrice + $specialPrice->price;
            case 'decrease':
                return max(0, $basePrice - $specialPrice->price);
            default:
                return $basePrice;
        }
    }
}
