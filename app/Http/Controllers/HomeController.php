<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\Review;
use App\Models\Notification;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Photo;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    /**
     * Display the homepage.
     */
    public function index(): View
    {
        // Кэшируем главную страницу на 15 минут для производительности
        $pageData = Cache::remember('homepage_data', 900, function () {
            // Основная информация об отеле
            $hotel = Hotel::first();

            if (!$hotel) {
                $hotel = Hotel::create([
                    'name' => 'Отель у Моря',
                    'slug' => 'hotel-u-morya',
                    'status' => 'active',
                ]);
            }

            // Популярные номера (топ 6)
            $featuredRooms = Room::where('status', 'active')
                ->where('is_featured', true)
                ->with(['photos', 'type', 'amenities'])
                ->orderBy('order')
                ->limit(6)
                ->get();

            // Если нет избранных номеров, берем последние активные
            if ($featuredRooms->isEmpty()) {
                $featuredRooms = Room::where('status', 'active')
                    ->with(['photos', 'type', 'amenities'])
                    ->orderBy('created_at', 'desc')
                    ->limit(6)
                    ->get();
            }

            // Последние отзывы (топ 5)
            $recentReviews = Review::where('status', 'approved')
                ->with(['user', 'booking.room'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            // Удобства отеля (топ 8)
            $facilities = Facility::orderBy('order')
                ->limit(8)
                ->get();

            // Галерея фотографий (топ 8)
            $galleryPhotos = Photo::whereHas('room', function ($query) {
                $query->where('status', 'active');
            })
                ->orWhereHas('hotel')
                ->inRandomOrder()
                ->limit(8)
                ->get();

            // Видео об отеле
            $videos = Video::whereHas('hotel')
                ->orderBy('order')
                ->limit(3)
                ->get();

            // Активные уведомления/баннеры
            $notifications = Notification::where('type', 'homepage')
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('start_date')
                        ->orWhere('start_date', '<=', now());
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return [
                'hotel' => $hotel,
                'featuredRooms' => $featuredRooms,
                'recentReviews' => $recentReviews,
                'facilities' => $facilities,
                'galleryPhotos' => $galleryPhotos,
                'videos' => $videos,
                'notifications' => $notifications,
            ];
        });

        // Проверяем доступность на ближайшие популярные даты
        $availabilityData = $this->getPopularDatesAvailability();

        return view('home.index', array_merge($pageData, $availabilityData));
    }

    /**
     * Get availability for popular dates.
     */
    private function getPopularDatesAvailability(): array
    {
        $popularDates = [
            'next_weekend' => [
                'check_in' => Carbon::now()->next(Carbon::FRIDAY)->format('Y-m-d'),
                'check_out' => Carbon::now()->next(Carbon::SUNDAY)->addDay()->format('Y-m-d'),
                'label' => 'Ближайшие выходные',
            ],
            'next_week' => [
                'check_in' => Carbon::now()->addDays(7)->format('Y-m-d'),
                'check_out' => Carbon::now()->addDays(9)->format('Y-m-d'),
                'label' => 'Следующая неделя',
            ],
            'next_month' => [
                'check_in' => Carbon::now()->addMonth()->startOfMonth()->format('Y-m-d'),
                'check_out' => Carbon::now()->addMonth()->startOfMonth()->addDays(2)->format('Y-m-d'),
                'label' => 'Начало следующего месяца',
            ],
        ];

        $availableRoomsCount = [];

        foreach ($popularDates as $key => $dateRange) {
            $availableRoomsCount[$key] = $this->checkAvailableRoomsCount(
                $dateRange['check_in'],
                $dateRange['check_out']
            );
        }

        return [
            'popularDates' => $popularDates,
            'availableRoomsCount' => $availableRoomsCount,
        ];
    }

    /**
     * Check how many rooms are available for given dates.
     */
    private function checkAvailableRoomsCount(string $checkIn, string $checkOut): int
    {
        $checkInDate = Carbon::parse($checkIn);
        $checkOutDate = Carbon::parse($checkOut);

        // Все активные номера
        $totalRooms = Room::where('status', 'active')->count();

        // Занятые номера на эти даты
        $bookedRooms = Booking::whereIn('status', ['pending', 'confirmed'])
            ->where(function ($query) use ($checkInDate, $checkOutDate) {
                $query->whereBetween('check_in', [$checkInDate, $checkOutDate])
                    ->orWhereBetween('check_out', [$checkInDate, $checkOutDate])
                    ->orWhere(function ($q) use ($checkInDate, $checkOutDate) {
                        $q->where('check_in', '<', $checkInDate)
                            ->where('check_out', '>', $checkOutDate);
                    });
            })
            ->distinct('room_id')
            ->count('room_id');

        // Заблокированные номера
        $blockedRooms = Room::where('status', '!=', 'active')->count();

        return max(0, $totalRooms - $bookedRooms - $blockedRooms);
    }

    /**
     * Display about hotel page.
     */
    public function about(): View
    {
        $hotel = Hotel::first();

        if (!$hotel) {
            abort(404, 'Информация об отеле не найдена');
        }

        $hotel->load(['photos', 'videos', 'facilities']);

        // Все удобства сгруппированные по категориям
        $facilitiesByCategory = Facility::orderBy('category')
            ->orderBy('order')
            ->get()
            ->groupBy('category');

        // Статистика отеля
        $stats = [
            'rooms_count' => Room::where('status', 'active')->count(),
            'reviews_count' => Review::where('status', 'approved')->count(),
            'average_rating' => Review::where('status', 'approved')->avg('rating') ?? 0,
            'years_experience' => $hotel->created_at ? now()->diffInYears($hotel->created_at) : 5,
        ];

        return view('home.about', compact('hotel', 'facilitiesByCategory', 'stats'));
    }

    /**
     * Display contact page.
     */
    public function contact(): View
    {
        $hotel = Hotel::first();

        if (!$hotel) {
            abort(404, 'Информация об отеле не найдена');
        }

        return view('home.contact', compact('hotel'));
    }

    /**
     * Handle contact form submission.
     */
    public function sendContactMessage(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10|max:2000',
            'g-recaptcha-response' => 'required|recaptcha',
        ]);

        try {
            // Сохраняем сообщение в базе данных
            \App\Models\ContactMessage::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Отправляем email администратору
            // Mail::to(config('mail.contact_to'))->send(new ContactFormSubmitted($validated));

            // Отправляем email подтверждения пользователю
            // Mail::to($validated['email'])->send(new ContactConfirmation($validated));

            return back()->with('success', 'Ваше сообщение отправлено. Мы свяжемся с вами в ближайшее время.');
        } catch (\Exception $e) {
            \Log::error('Contact form error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Произошла ошибка при отправке сообщения. Пожалуйста, попробуйте позже.']);
        }
    }

    /**
     * Display FAQ page.
     */
    public function faq(): View
    {
        $faqs = \App\Models\FAQ::where('is_active', true)
            ->orderBy('order')
            ->get()
            ->groupBy('category');

        $categories = \App\Models\FAQ::distinct()->pluck('category');

        return view('home.faq', compact('faqs', 'categories'));
    }

    /**
     * Display terms and conditions page.
     */
    public function terms(): View
    {
        $hotel = Hotel::first();

        if (!$hotel) {
            abort(404);
        }

        return view('home.terms', compact('hotel'));
    }

    /**
     * Display privacy policy page.
     */
    public function privacy(): View
    {
        return view('home.privacy');
    }

    /**
     * Display cancellation policy page.
     */
    public function cancellationPolicy(): View
    {
        $hotel = Hotel::first();

        if (!$hotel) {
            abort(404);
        }

        return view('home.cancellation-policy', compact('hotel'));
    }

    /**
     * Display sitemap.
     */
    public function sitemap()
    {
        $hotel = Hotel::first();
        $rooms = Room::where('status', 'active')->get();
        $pages = ['about', 'contact', 'faq', 'terms', 'privacy', 'cancellation-policy'];

        $content = view('home.sitemap', compact('hotel', 'rooms', 'pages'))->render();

        return response($content, 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Display gallery page.
     */
    public function gallery(): View
    {
        $roomPhotos = Photo::whereHas('room', function ($query) {
            $query->where('status', 'active');
        })
            ->with('room')
            ->orderBy('created_at', 'desc')
            ->paginate(24);

        $hotelPhotos = Photo::whereHas('hotel')
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return view('home.gallery', compact('roomPhotos', 'hotelPhotos'));
    }

    /**
     * Display room types/categories page.
     */
    public function roomTypes(): View
    {
        $types = \App\Models\RoomType::withCount(['rooms' => function ($query) {
            $query->where('status', 'active');
        }])
            ->has('rooms')
            ->with(['rooms' => function ($query) {
                $query->where('status', 'active')
                    ->with(['photos', 'amenities'])
                    ->orderBy('price_per_night')
                    ->limit(3);
            }])
            ->get();

        return view('home.room-types', compact('types'));
    }

    /**
     * Display special offers page.
     */
    public function specialOffers(): View
    {
        $offers = \App\Models\Discount::where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereRaw('used_count < usage_limit');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return view('home.special-offers', compact('offers'));
    }

    /**
     * Display reviews page.
     */
    public function reviews(): View
    {
        $reviews = Review::where('status', 'approved')
            ->with(['user', 'booking.room'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Статистика отзывов
        $stats = [
            'total' => Review::where('status', 'approved')->count(),
            'average' => Review::where('status', 'approved')->avg('rating') ?? 0,
            'distribution' => Review::where('status', 'approved')
                ->select('rating', \DB::raw('COUNT(*) as count'))
                ->groupBy('rating')
                ->orderBy('rating', 'desc')
                ->get()
                ->pluck('count', 'rating'),
        ];

        return view('home.reviews', compact('reviews', 'stats'));
    }

    /**
     * Submit a review (form).
     */
    public function submitReview(Request $request)
    {
        // Проверяем, что пользователь авторизован
        if (!auth()->check()) {
            return redirect()->route('login')
                ->with('error', 'Пожалуйста, войдите в систему, чтобы оставить отзыв.');
        }

        // Проверяем, есть ли у пользователя завершенные бронирования
        $user = auth()->user();
        $completedBookings = $user->bookings()
            ->where('status', 'completed')
            ->where('check_out', '<', now()->subDays(1)) // Можно оставить отзыв через день после выезда
            ->get();

        if ($completedBookings->isEmpty()) {
            return back()->with('error', 'Вы можете оставить отзыв только после завершенного проживания.');
        }

        return view('home.submit-review', compact('completedBookings'));
    }

    /**
     * Process review submission.
     */
    public function storeReview(Request $request)
    {
        if (!auth()->check()) {
            abort(403);
        }

        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'required|string|max:255',
            'comment' => 'required|string|min:10|max:2000',
            'pros' => 'nullable|string|max:500',
            'cons' => 'nullable|string|max:500',
            'anonymous' => 'nullable|boolean',
        ]);

        // Проверяем, что бронирование принадлежит пользователю
        $booking = \App\Models\Booking::findOrFail($validated['booking_id']);

        if ($booking->user_id !== auth()->id()) {
            abort(403);
        }

        // Проверяем, что бронирование завершено
        if ($booking->status !== 'completed') {
            return back()->withErrors(['error' => 'Можно оставить отзыв только к завершенному бронированию.']);
        }

        // Проверяем, не оставлял ли уже пользователь отзыв на это бронирование
        $existingReview = \App\Models\Review::where('booking_id', $validated['booking_id'])->first();

        if ($existingReview) {
            return back()->withErrors(['error' => 'Вы уже оставляли отзыв на это бронирование.']);
        }

        // Создаем отзыв
        $review = \App\Models\Review::create([
            'user_id' => auth()->id(),
            'booking_id' => $validated['booking_id'],
            'rating' => $validated['rating'],
            'title' => $validated['title'],
            'comment' => $validated['comment'],
            'pros' => $validated['pros'],
            'cons' => $validated['cons'],
            'is_anonymous' => $validated['anonymous'] ?? false,
            'status' => 'pending', // На модерации
        ]);

        // Очищаем кэш отзывов
        Cache::forget('homepage_data');
        Cache::forget('recent_reviews');

        return redirect()->route('reviews')
            ->with('success', 'Спасибо за ваш отзыв! Он будет опубликован после проверки модератором.');
    }

    /**
     * Check room availability (AJAX).
     */
    public function checkAvailability(Request $request)
    {
        $validated = $request->validate([
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'guests' => 'required|integer|min:1|max:10',
            'room_type' => 'nullable|exists:room_types,id',
        ]);

        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);
        $nights = $checkIn->diffInDays($checkOut);

        // Получаем доступные номера
        $availableRooms = Room::where('status', 'active')
            ->where('capacity', '>=', $validated['guests'])
            ->when($request->filled('room_type'), function ($query) use ($validated) {
                $query->where('type_id', $validated['room_type']);
            })
            ->with(['photos', 'type', 'amenities'])
            ->get()
            ->filter(function ($room) use ($checkIn, $checkOut) {
                // Проверяем, свободен ли номер на эти даты
                $isBooked = Booking::where('room_id', $room->id)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where(function ($query) use ($checkIn, $checkOut) {
                        $query->whereBetween('check_in', [$checkIn, $checkOut])
                            ->orWhereBetween('check_out', [$checkIn, $checkOut])
                            ->orWhere(function ($q) use ($checkIn, $checkOut) {
                                $q->where('check_in', '<', $checkIn)
                                    ->where('check_out', '>', $checkOut);
                            });
                    })
                    ->exists();

                // Проверяем заблокированные даты
                $isBlocked = $room->blockedDates()
                    ->whereBetween('date', [$checkIn->format('Y-m-d'), $checkOut->copy()->subDay()->format('Y-m-d')])
                    ->exists();

                return !$isBooked && !$isBlocked;
            })
            ->map(function ($room) use ($nights, $checkIn, $checkOut) {
                // Рассчитываем стоимость
                $totalPrice = $this->calculateRoomPrice($room, $checkIn, $checkOut, $nights);

                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'description' => $room->short_description,
                    'capacity' => $room->capacity,
                    'bed_type' => $room->bed_type,
                    'bed_count' => $room->bed_count,
                    'size' => $room->size,
                    'size_unit' => $room->size_unit,
                    'price_per_night' => $room->price_per_night,
                    'total_price' => $totalPrice,
                    'nights' => $nights,
                    'photo' => $room->photos->where('is_main', true)->first()?->path,
                    'type' => $room->type->name ?? '',
                    'amenities' => $room->amenities->pluck('name')->take(5),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'nights' => $nights,
            'guests' => $validated['guests'],
            'available_rooms' => $availableRooms,
            'count' => $availableRooms->count(),
        ]);
    }

    /**
     * Calculate room price for specific dates.
     */
    private function calculateRoomPrice(Room $room, Carbon $checkIn, Carbon $checkOut, int $nights): float
    {
        $totalPrice = 0;

        for ($i = 0; $i < $nights; $i++) {
            $currentDate = $checkIn->copy()->addDays($i);
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

    /**
     * Get room details for modal.
     */
    public function getRoomDetails(Request $request, Room $room)
    {
        if ($request->ajax()) {
            $room->load(['photos', 'type', 'amenities', 'reviews' => function ($query) {
                $query->where('status', 'approved')
                    ->with('user')
                    ->limit(3);
            }]);

            $room->reviews_count = $room->reviews()->where('status', 'approved')->count();
            $room->average_rating = $room->reviews()->where('status', 'approved')->avg('rating') ?? 0;

            return response()->json([
                'success' => true,
                'room' => $room,
                'html' => view('partials.room-details-modal', compact('room'))->render(),
            ]);
        }

        abort(404);
    }

    /**
     * Display 404 page.
     */
    public function notFound()
    {
        return response()->view('errors.404', [], 404);
    }

    /**
     * Display 500 page.
     */
    public function serverError()
    {
        return response()->view('errors.500', [], 500);
    }

    /**
     * Clear homepage cache (admin only).
     */
    public function clearCache()
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            abort(403);
        }

        Cache::forget('homepage_data');
        Cache::forget('recent_reviews');
        Cache::forget('featured_rooms');

        return back()->with('success', 'Кэш главной страницы очищен.');
    }

    /**
     * Display subscription form.
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:newsletter_subscriptions,email',
        ]);

        try {
            \App\Models\NewsletterSubscription::create([
                'email' => $validated['email'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Спасибо за подписку! Проверьте ваш email для подтверждения.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Subscription error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка. Пожалуйста, попробуйте позже.',
            ], 500);
        }
    }

    /**
     * Verify subscription email.
     */
    public function verifySubscription(string $token)
    {
        $subscription = \App\Models\NewsletterSubscription::where('verification_token', $token)
            ->whereNull('verified_at')
            ->first();

        if (!$subscription) {
            return redirect()->route('home')
                ->with('error', 'Неверная или устаревшая ссылка подтверждения.');
        }

        $subscription->update([
            'verified_at' => now(),
            'verification_token' => null,
        ]);

        return redirect()->route('home')
            ->with('success', 'Ваша подписка подтверждена! Спасибо.');
    }

    /**
     * Unsubscribe from newsletter.
     */
    public function unsubscribe(string $token)
    {
        $subscription = \App\Models\NewsletterSubscription::where('unsubscribe_token', $token)
            ->whereNull('unsubscribed_at')
            ->first();

        if (!$subscription) {
            return redirect()->route('home')
                ->with('error', 'Неверная или устаревшая ссылка отписки.');
        }

        $subscription->update([
            'unsubscribed_at' => now(),
            'unsubscribe_token' => null,
        ]);

        return redirect()->route('home')
            ->with('success', 'Вы успешно отписались от рассылки.');
    }
}
