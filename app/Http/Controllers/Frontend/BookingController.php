<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Discount;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    /**
     * Middleware для проверки аутентификации на определенных шагах.
     */
    public function __construct()
    {
        $this->middleware('auth')->except(['showBookingForm', 'checkAvailability']);
        $this->middleware('verified')->except(['showBookingForm', 'checkAvailability']);
    }

    /**
     * Show booking form step 1: Select dates and room.
     */
    public function showBookingForm(Request $request): View|RedirectResponse
    {
        // Если пользователь не авторизован, сохраняем данные для редиректа
        if (!Auth::check() && ($request->has('room_id') || $request->has('check_in'))) {
            session(['booking_redirect' => $request->fullUrl()]);
        }

        // Если передан room_id, показываем форму для конкретного номера
        if ($request->has('room_id')) {
            return $this->showRoomBookingForm($request);
        }

        // Иначе показываем общую форму поиска
        $defaultCheckIn = Carbon::today()->addDays(1);
        $defaultCheckOut = Carbon::today()->addDays(3);

        $searchParams = [
            'check_in' => $request->get('check_in', $defaultCheckIn->format('Y-m-d')),
            'check_out' => $request->get('check_out', $defaultCheckOut->format('Y-m-d')),
            'guests' => $request->get('guests', 2),
        ];

        // Типы номеров для фильтра
        $roomTypes = \App\Models\RoomType::has('rooms')->get();

        return view('frontend.booking.step1-search', compact('searchParams', 'roomTypes'));
    }

    /**
     * Show booking form for specific room.
     */
    private function showRoomBookingForm(Request $request): View
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'guests' => 'required|integer|min:1',
        ]);

        $room = Room::where('status', 'active')
            ->with(['photos', 'type', 'amenities'])
            ->findOrFail($validated['room_id']);

        // Проверяем доступность
        $availability = $this->checkRoomAvailability(
            $room,
            $validated['check_in'],
            $validated['check_out'],
            $validated['guests']
        );

        if (!$availability['available']) {
            return redirect()->route('rooms.show', $room->slug)
                ->withErrors(['error' => $availability['message']]);
        }

        // Рассчитываем стоимость
        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);
        $nights = $checkIn->diffInDays($checkOut);

        $priceDetails = $this->calculateBookingPrice($room, $checkIn, $checkOut, $nights);

        // Сохраняем данные в сессии
        session([
            'booking.room_id' => $room->id,
            'booking.check_in' => $validated['check_in'],
            'booking.check_out' => $validated['check_out'],
            'booking.guests' => $validated['guests'],
            'booking.nights' => $nights,
            'booking.base_price' => $priceDetails['total'],
            'booking.price_breakdown' => $priceDetails['breakdown'],
        ]);

        // Если пользователь авторизован, переходим к шагу 2
        if (Auth::check()) {
            return redirect()->route('booking.step2');
        }

        // Иначе показываем форму с возможностью войти или продолжить как гость
        return view('frontend.booking.step1-room', [
            'room' => $room,
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'guests' => $validated['guests'],
            'nights' => $nights,
            'price_details' => $priceDetails,
        ]);
    }

    /**
     * Check room availability.
     */
    private function checkRoomAvailability(Room $room, string $checkIn, string $checkOut, int $guests): array
    {
        $checkInDate = Carbon::parse($checkIn);
        $checkOutDate = Carbon::parse($checkOut);

        // Проверяем вместимость
        if ($room->capacity < $guests) {
            return [
                'available' => false,
                'message' => 'Выбранный номер вмещает максимум ' . $room->capacity . ' гостей.',
            ];
        }

        // Проверяем бронирования
        $isBooked = Booking::where('room_id', $room->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($query) use ($checkInDate, $checkOutDate) {
                $query->whereBetween('check_in', [$checkInDate, $checkOutDate])
                    ->orWhereBetween('check_out', [$checkInDate, $checkOutDate])
                    ->orWhere(function ($q) use ($checkInDate, $checkOutDate) {
                        $q->where('check_in', '<', $checkInDate)
                            ->where('check_out', '>', $checkOutDate);
                    });
            })
            ->exists();

        if ($isBooked) {
            return [
                'available' => false,
                'message' => 'Номер уже забронирован на выбранные даты.',
            ];
        }

        // Проверяем заблокированные даты
        $isBlocked = $room->blockedDates()
            ->whereBetween('date', [$checkInDate->format('Y-m-d'), $checkOutDate->copy()->subDay()->format('Y-m-d')])
            ->exists();

        if ($isBlocked) {
            return [
                'available' => false,
                'message' => 'Номер недоступен на выбранные даты.',
            ];
        }

        return ['available' => true, 'message' => 'Номер доступен.'];
    }

    /**
     * Calculate booking price.
     */
    private function calculateBookingPrice(Room $room, Carbon $checkIn, Carbon $checkOut, int $nights): array
    {
        $totalPrice = 0;
        $priceBreakdown = [];

        for ($i = 0; $i < $nights; $i++) {
            $currentDate = $checkIn->copy()->addDays($i);
            $dateStr = $currentDate->format('Y-m-d');
            $isWeekend = $currentDate->isWeekend();

            // Базовая цена
            $basePrice = $room->price_per_night;

            // Проверяем специальную цену
            $specialPrice = $room->specialPrices()
                ->where('date', $dateStr)
                ->first();

            if ($specialPrice) {
                $dailyPrice = $this->applySpecialPrice($basePrice, $specialPrice);
                $priceType = 'special';
            } elseif ($isWeekend && $room->weekend_price) {
                $dailyPrice = $room->weekend_price;
                $priceType = 'weekend';
            } else {
                $dailyPrice = $basePrice;
                $priceType = 'standard';
            }

            $totalPrice += $dailyPrice;
            $priceBreakdown[] = [
                'date' => $dateStr,
                'price' => $dailyPrice,
                'type' => $priceType,
                'is_weekend' => $isWeekend,
                'formatted_price' => number_format($dailyPrice, 0, '.', ' ') . ' ₽',
            ];
        }

        // Дополнительные услуги (если есть)
        $additionalServices = [];
        $servicesTotal = 0;

        return [
            'total' => round($totalPrice + $servicesTotal, 2),
            'room_total' => round($totalPrice, 2),
            'services_total' => $servicesTotal,
            'nights' => $nights,
            'price_per_night' => round($totalPrice / $nights, 2),
            'breakdown' => $priceBreakdown,
            'additional_services' => $additionalServices,
        ];
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
     * Step 2: Guest information.
     */
    public function step2(): View|RedirectResponse
    {
        // Проверяем, что есть данные о бронировании в сессии
        if (!session()->has('booking.room_id')) {
            return redirect()->route('booking.step1')
                ->with('error', 'Пожалуйста, выберите номер и даты для бронирования.');
        }

        $room = Room::with(['photos', 'type'])->findOrFail(session('booking.room_id'));

        $user = Auth::user();
        $countries = $this->getCountriesList();

        // Если пользователь авторизован, заполняем форму его данными
        $guestData = $user ? [
            'first_name' => $user->first_name ?? explode(' ', $user->name)[0] ?? '',
            'last_name' => $user->last_name ?? explode(' ', $user->name)[1] ?? '',
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
            'city' => $user->city,
            'address' => $user->address,
            'postal_code' => $user->postal_code,
        ] : [];

        return view('frontend.booking.step2-guest', compact('room', 'user', 'guestData', 'countries'));
    }

    /**
     * Process step 2: Save guest information.
     */
    public function processStep2(Request $request): RedirectResponse
    {
        // Проверяем, что есть данные о бронировании в сессии
        if (!session()->has('booking.room_id')) {
            return redirect()->route('booking.step1')
                ->with('error', 'Сессия бронирования истекла. Пожалуйста, начните заново.');
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'postal_code' => 'nullable|string|max:20',
            'special_requests' => 'nullable|string|max:1000',
            'newsletter_subscribe' => 'nullable|boolean',
            'create_account' => 'nullable|boolean',
            'password' => 'required_if:create_account,true|nullable|string|min:8|confirmed',
        ]);

        // Если пользователь не авторизован и хочет создать аккаунт
        if (!Auth::check() && $request->has('create_account') && $request->create_account) {
            // Проверяем, нет ли уже пользователя с таким email
            $existingUser = User::where('email', $validated['email'])->first();

            if ($existingUser) {
                return back()->withErrors([
                    'email' => 'Пользователь с таким email уже существует. Пожалуйста, войдите в систему.'
                ]);
            }

            // Создаем пользователя
            $user = User::create([
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => bcrypt($validated['password']),
                'country' => $validated['country'],
                'city' => $validated['city'],
                'address' => $validated['address'],
                'postal_code' => $validated['postal_code'],
                'status' => 'active',
                'role' => 'user',
                'email_verified_at' => now(), // Автоподтверждение при бронировании
            ]);

            // Авторизуем пользователя
            Auth::login($user);

            // Отправляем приветственное письмо
            // Mail::to($user)->send(new WelcomeEmail($user));
        }

        // Сохраняем данные гостя в сессии
        session([
            'booking.guest_info' => [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'country' => $validated['country'],
                'city' => $validated['city'],
                'address' => $validated['address'],
                'postal_code' => $validated['postal_code'],
                'special_requests' => $validated['special_requests'] ?? '',
            ],
            'booking.newsletter_subscribe' => $request->has('newsletter_subscribe') && $request->newsletter_subscribe,
        ]);

        return redirect()->route('booking.step3');
    }

    /**
     * Step 3: Additional services and extras.
     */
    public function step3(): View|RedirectResponse
    {
        // Проверяем данные в сессии
        if (!session()->has('booking.room_id') || !session()->has('booking.guest_info')) {
            return redirect()->route('booking.step1')
                ->with('error', 'Пожалуйста, заполните информацию о госте.');
        }

        $room = Room::with(['type'])->findOrFail(session('booking.room_id'));

        // Дополнительные услуги
        $additionalServices = $this->getAdditionalServices();

        // Трансферы
        $transferOptions = $this->getTransferOptions();

        // Экскурсии
        $excursionOptions = $this->getExcursionOptions();

        return view('frontend.booking.step3-extras', compact(
            'room',
            'additionalServices',
            'transferOptions',
            'excursionOptions'
        ));
    }

    /**
     * Get additional services list.
     */
    private function getAdditionalServices(): array
    {
        return [
            [
                'id' => 'breakfast',
                'name' => 'Завтрак "шведский стол"',
                'description' => 'Включенный завтрак на каждого гостя',
                'price_per_person' => 1200,
                'price_per_day' => false,
                'max_quantity' => 10,
            ],
            [
                'id' => 'spa',
                'name' => 'SPA-процедуры',
                'description' => 'Расслабляющий массаж (60 мин)',
                'price_per_person' => 3500,
                'price_per_day' => false,
                'max_quantity' => 2,
            ],
            [
                'id' => 'late_checkout',
                'name' => 'Поздний выезд',
                'description' => 'Выезд до 18:00',
                'price_per_person' => 0,
                'price_per_day' => 2500,
                'max_quantity' => 1,
            ],
            [
                'id' => 'early_checkin',
                'name' => 'Ранний заезд',
                'description' => 'Заезд с 10:00',
                'price_per_person' => 0,
                'price_per_day' => 2000,
                'max_quantity' => 1,
            ],
            [
                'id' => 'airport_transfer',
                'name' => 'Трансфер из аэропорта',
                'description' => 'Встреча в аэропорту на авто бизнес-класса',
                'price_per_person' => 0,
                'price_per_day' => 4500,
                'max_quantity' => 1,
            ],
        ];
    }

    /**
     * Get transfer options.
     */
    private function getTransferOptions(): array
    {
        return [
            [
                'id' => 'transfer_economy',
                'name' => 'Эконом трансфер',
                'description' => 'Стандартный автомобиль, водитель, встреча с табличкой',
                'price' => 2500,
                'capacity' => 3,
            ],
            [
                'id' => 'transfer_comfort',
                'name' => 'Комфорт трансфер',
                'description' => 'Бизнес-класс, вода, wi-fi, помощь с багажом',
                'price' => 4500,
                'capacity' => 3,
            ],
            [
                'id' => 'transfer_vip',
                'name' => 'VIP трансфер',
                'description' => 'Премиум автомобиль, персональный гид, приоритетная встреча',
                'price' => 8500,
                'capacity' => 3,
            ],
            [
                'id' => 'transfer_minivan',
                'name' => 'Минивэн',
                'description' => 'Для большой компании или семьи с детьми',
                'price' => 6500,
                'capacity' => 7,
            ],
        ];
    }

    /**
     * Get excursion options.
     */
    private function getExcursionOptions(): array
    {
        return [
            [
                'id' => 'excursion_city',
                'name' => 'Обзорная экскурсия по городу',
                'description' => '3-часовая экскурсия с гидом по основным достопримечательностям',
                'price_per_person' => 1800,
                'duration' => '3 часа',
                'min_people' => 2,
                'max_people' => 15,
            ],
            [
                'id' => 'excursion_sea',
                'name' => 'Морская прогулка',
                'description' => 'Прогулка на яхте вдоль побережья с купанием',
                'price_per_person' => 3500,
                'duration' => '4 часа',
                'min_people' => 4,
                'max_people' => 12,
            ],
            [
                'id' => 'excursion_wine',
                'name' => 'Винный тур',
                'description' => 'Посещение винодельни с дегустацией',
                'price_per_person' => 4200,
                'duration' => '5 часов',
                'min_people' => 2,
                'max_people' => 8,
            ],
        ];
    }

    /**
     * Process step 3: Save additional services.
     */
    public function processStep3(Request $request): RedirectResponse
    {
        // Проверяем данные в сессии
        if (!session()->has('booking.room_id') || !session()->has('booking.guest_info')) {
            return redirect()->route('booking.step1')
                ->with('error', 'Сессия бронирования истекла. Пожалуйста, начните заново.');
        }

        $validated = $request->validate([
            'additional_services' => 'nullable|array',
            'additional_services.*.id' => 'required|string',
            'additional_services.*.quantity' => 'required|integer|min:0',
            'transfer_option' => 'nullable|string',
            'excursions' => 'nullable|array',
            'excursions.*.id' => 'required|string',
            'excursions.*.people' => 'required|integer|min:1',
        ]);

        // Рассчитываем стоимость дополнительных услуг
        $additionalServices = $this->calculateAdditionalServicesCost($validated);

        // Сохраняем в сессии
        session([
            'booking.additional_services' => $additionalServices['selected'],
            'booking.additional_services_total' => $additionalServices['total'],
            'booking.transfer_option' => $validated['transfer_option'] ?? null,
            'booking.excursions' => $validated['excursions'] ?? [],
        ]);

        return redirect()->route('booking.step4');
    }

    /**
     * Calculate additional services cost.
     */
    private function calculateAdditionalServicesCost(array $data): array
    {
        $allServices = $this->getAdditionalServices();
        $selectedServices = [];
        $total = 0;

        $guests = session('booking.guests', 2);
        $nights = session('booking.nights', 1);

        if (!empty($data['additional_services'])) {
            foreach ($data['additional_services'] as $serviceData) {
                $service = collect($allServices)->firstWhere('id', $serviceData['id']);

                if ($service && $serviceData['quantity'] > 0) {
                    if ($service['price_per_day']) {
                        $serviceTotal = $serviceData['quantity'] * $service['price_per_day'];
                    } else {
                        $serviceTotal = $serviceData['quantity'] * $service['price_per_person'] * $guests;
                    }

                    $selectedServices[] = [
                        'id' => $service['id'],
                        'name' => $service['name'],
                        'description' => $service['description'],
                        'quantity' => $serviceData['quantity'],
                        'price_per_unit' => $service['price_per_day'] ?: $service['price_per_person'],
                        'total' => $serviceTotal,
                    ];

                    $total += $serviceTotal;
                }
            }
        }

        // Трансфер
        if (!empty($data['transfer_option'])) {
            $transferOptions = $this->getTransferOptions();
            $transfer = collect($transferOptions)->firstWhere('id', $data['transfer_option']);

            if ($transfer) {
                $selectedServices[] = [
                    'id' => $transfer['id'],
                    'name' => $transfer['name'],
                    'description' => $transfer['description'],
                    'quantity' => 1,
                    'price_per_unit' => $transfer['price'],
                    'total' => $transfer['price'],
                ];

                $total += $transfer['price'];
            }
        }

        // Экскурсии
        if (!empty($data['excursions'])) {
            $excursionOptions = $this->getExcursionOptions();

            foreach ($data['excursions'] as $excursionData) {
                $excursion = collect($excursionOptions)->firstWhere('id', $excursionData['id']);

                if ($excursion) {
                    $excursionTotal = $excursionData['people'] * $excursion['price_per_person'];

                    $selectedServices[] = [
                        'id' => $excursion['id'],
                        'name' => $excursion['name'],
                        'description' => $excursion['description'],
                        'quantity' => $excursionData['people'],
                        'price_per_unit' => $excursion['price_per_person'],
                        'total' => $excursionTotal,
                    ];

                    $total += $excursionTotal;
                }
            }
        }

        return [
            'selected' => $selectedServices,
            'total' => $total,
        ];
    }

    /**
     * Step 4: Review and discount.
     */
    public function step4(): View|RedirectResponse
    {
        // Проверяем данные в сессии
        if (!session()->has('booking.room_id') || !session()->has('booking.guest_info')) {
            return redirect()->route('booking.step1')
                ->with('error', 'Сессия бронирования истекла. Пожалуйста, начните заново.');
        }

        $room = Room::with(['photos', 'type'])->findOrFail(session('booking.room_id'));

        $bookingSummary = $this->getBookingSummary();
        $discountApplied = session('booking.discount', null);

        return view('frontend.booking.step4-review', compact(
            'room',
            'bookingSummary',
            'discountApplied'
        ));
    }

    /**
     * Get booking summary.
     */
    private function getBookingSummary(): array
    {
        $roomPrice = session('booking.base_price', 0);
        $servicesTotal = session('booking.additional_services_total', 0);
        $subtotal = $roomPrice + $servicesTotal;

        // Применяем скидку если есть
        $discount = session('booking.discount', null);
        $discountAmount = 0;

        if ($discount) {
            if ($discount['type'] === 'percentage') {
                $discountAmount = $subtotal * ($discount['value'] / 100);
                if ($discount['max_discount'] && $discountAmount > $discount['max_discount']) {
                    $discountAmount = $discount['max_discount'];
                }
            } else {
                $discountAmount = min($discount['value'], $subtotal);
            }
        }

        $total = $subtotal - $discountAmount;

        // Налоги и сборы (пример)
        $taxRate = 0.20; // 20% НДС
        $taxAmount = $total * $taxRate;
        $finalTotal = $total + $taxAmount;

        return [
            'room' => [
                'name' => Room::find(session('booking.room_id'))->name,
                'check_in' => session('booking.check_in'),
                'check_out' => session('booking.check_out'),
                'nights' => session('booking.nights'),
                'guests' => session('booking.guests'),
                'price' => $roomPrice,
                'price_breakdown' => session('booking.price_breakdown', []),
            ],
            'guest_info' => session('booking.guest_info'),
            'additional_services' => session('booking.additional_services', []),
            'services_total' => $servicesTotal,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'discount_amount' => $discountAmount,
            'total_before_tax' => $total,
            'tax_rate' => $taxRate * 100,
            'tax_amount' => $taxAmount,
            'final_total' => $finalTotal,
        ];
    }

    /**
     * Apply discount code.
     */
    public function applyDiscount(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $discount = Discount::where('code', strtoupper($validated['code']))
            ->where('status', 'active')
            ->first();

        if (!$discount) {
            return response()->json([
                'success' => false,
                'message' => 'Код скидки не найден или неактивен.',
            ]);
        }

        // Проверяем срок действия
        $now = now();
        if ($discount->valid_from && $now->lt($discount->valid_from)) {
            return response()->json([
                'success' => false,
                'message' => 'Скидка ещё не действует.',
            ]);
        }

        if ($discount->valid_to && $now->gt($discount->valid_to)) {
            return response()->json([
                'success' => false,
                'message' => 'Срок действия скидки истёк.',
            ]);
        }

        // Проверяем лимит использования
        if ($discount->usage_limit && $discount->used_count >= $discount->usage_limit) {
            return response()->json([
                'success' => false,
                'message' => 'Лимит использования скидки исчерпан.',
            ]);
        }

        // Проверяем минимальную сумму
        $subtotal = session('booking.base_price', 0) + session('booking.additional_services_total', 0);
        if ($discount->min_booking_amount && $subtotal < $discount->min_booking_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Скидка применяется только к заказам от ' .
                    number_format($discount->min_booking_amount, 0, '.', ' ') . ' руб.',
            ]);
        }

        // Проверяем применимость к номеру
        $roomId = session('booking.room_id');
        if ($discount->applicable_to === 'specific_rooms') {
            if (!$discount->rooms()->where('room_id', $roomId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Скидка не действует для выбранного номера.',
                ]);
            }
        }

        // Проверяем применимость к пользователю
        $userId = Auth::id();
        if ($discount->applicable_to === 'specific_users' && $userId) {
            if (!$discount->users()->where('user_id', $userId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Скидка не действует для вашего аккаунта.',
                ]);
            }
        }

        // Проверяем лимит на пользователя
        if ($userId && $discount->user_limit) {
            $userUsageCount = $discount->bookings()
                ->where('user_id', $userId)
                ->count();

            if ($userUsageCount >= $discount->user_limit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Вы уже использовали эту скидку максимальное количество раз.',
                ]);
            }
        }

        // Сохраняем скидку в сессии
        session(['booking.discount' => [
            'id' => $discount->id,
            'code' => $discount->code,
            'name' => $discount->name,
            'type' => $discount->type,
            'value' => $discount->value,
            'max_discount' => $discount->max_discount,
        ]]);

        $bookingSummary = $this->getBookingSummary();

        return response()->json([
            'success' => true,
            'message' => 'Скидка успешно применена!',
            'discount' => session('booking.discount'),
            'summary_html' => view('frontend.booking.partials.booking-summary', compact('bookingSummary'))->render(),
        ]);
    }

    /**
     * Remove discount code.
     */
    public function removeDiscount(): \Illuminate\Http\JsonResponse
    {
        session()->forget('booking.discount');

        $bookingSummary = $this->getBookingSummary();

        return response()->json([
            'success' => true,
            'message' => 'Скидка удалена.',
            'summary_html' => view('frontend.booking.partials.booking-summary', compact('bookingSummary'))->render(),
        ]);
    }

    /**
     * Step 5: Payment.
     */
    public function step5(): View|RedirectResponse
    {
        // Проверяем данные в сессии
        if (!session()->has('booking.room_id') || !session()->has('booking.guest_info')) {
            return redirect()->route('booking.step1')
                ->with('error', 'Сессия бронирования истекла. Пожалуйста, начните заново.');
        }

        $room = Room::with(['photos', 'type'])->findOrFail(session('booking.room_id'));
        $bookingSummary = $this->getBookingSummary();

        // Платежные системы
        $paymentSystems = [
            'yookassa' => [
                'name' => 'ЮKassa',
                'description' => 'Банковская карта, ЮMoney, СБП',
                'icon' => 'credit-card',
                'currencies' => ['RUB'],
            ],
            'stripe' => [
                'name' => 'Stripe',
                'description' => 'Visa, Mastercard, American Express',
                'icon' => 'credit-card',
                'currencies' => ['USD', 'EUR', 'GBP'],
            ],
            'paypal' => [
                'name' => 'PayPal',
                'description' => 'PayPal аккаунт',
                'icon' => 'paypal',
                'currencies' => ['USD', 'EUR'],
            ],
            'sberbank' => [
                'name' => 'Сбербанк',
                'description' => 'Онлайн-банк Сбербанк',
                'icon' => 'bank',
                'currencies' => ['RUB'],
            ],
            'tinkoff' => [
                'name' => 'Тинькофф',
                'description' => 'Банк Тинькофф',
                'icon' => 'bank',
                'currencies' => ['RUB'],
            ],
        ];

        return view('frontend.booking.step5-payment', compact(
            'room',
            'bookingSummary',
            'paymentSystems'
        ));
    }

    /**
     * Process payment and create booking.
     */
    public function processPayment(Request $request): RedirectResponse
    {
        // Проверяем данные в сессии
        if (!session()->has('booking.room_id') || !session()->has('booking.guest_info')) {
            return redirect()->route('booking.step1')
                ->with('error', 'Сессия бронирования истекла. Пожалуйста, начните заново.');
        }

        $validated = $request->validate([
            'payment_system' => 'required|in:yookassa,stripe,paypal,sberbank,tinkoff,cash',
            'currency' => 'required|in:RUB,USD,EUR,GBP',
            'agree_terms' => 'required|accepted',
            'save_payment_method' => 'nullable|boolean',
        ]);

        // Проверяем доступность номера еще раз
        $room = Room::findOrFail(session('booking.room_id'));
        $availability = $this->checkRoomAvailability(
            $room,
            session('booking.check_in'),
            session('booking.check_out'),
            session('booking.guests')
        );

        if (!$availability['available']) {
            return redirect()->route('booking.step1')
                ->withErrors(['error' => 'К сожалению, номер больше недоступен. ' . $availability['message']]);
        }

        DB::beginTransaction();

        try {
            // Создаем бронирование
            $booking = $this->createBooking();

            // Создаем платеж
            $payment = $this->createPayment($booking, $validated);

            // Применяем скидку если есть
            if (session()->has('booking.discount')) {
                $this->applyDiscountToBooking($booking);
            }

            // Обновляем счетчик использований скидки
            if ($booking->discount_id) {
                Discount::where('id', $booking->discount_id)->increment('used_count');
            }

            // Подписываем на рассылку если выбрано
            if (session('booking.newsletter_subscribe')) {
                $this->subscribeToNewsletter(session('booking.guest_info.email'));
            }

            DB::commit();

            // Очищаем сессию бронирования
            session()->forget([
                'booking.room_id',
                'booking.check_in',
                'booking.check_out',
                'booking.guests',
                'booking.nights',
                'booking.base_price',
                'booking.price_breakdown',
                'booking.guest_info',
                'booking.additional_services',
                'booking.additional_services_total',
                'booking.transfer_option',
                'booking.excursions',
                'booking.discount',
                'booking.newsletter_subscribe',
            ]);

            // Редирект в зависимости от способа оплаты
            if ($validated['payment_system'] === 'cash') {
                // Оплата наличными при заезде
                return redirect()->route('booking.confirmation', $booking)
                    ->with('success', 'Бронирование создано! Оплата при заезде.');
            } else {
                // Перенаправляем на страницу оплаты
                return $this->redirectToPaymentGateway($payment);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Booking creation failed: ' . $e->getMessage());

            return back()->withErrors([
                'error' => 'Произошла ошибка при создании бронирования. Пожалуйста, попробуйте еще раз.'
            ]);
        }
    }

    /**
     * Create booking record.
     */
    private function createBooking(): Booking
    {
        $userId = Auth::id();
        $guestInfo = session('booking.guest_info');

        // Если пользователь не авторизован, создаем запись с guest_id
        if (!$userId) {
            // Можно создать guest запись или оставить null
            $userId = null;
        }

        $booking = Booking::create([
            'user_id' => $userId,
            'room_id' => session('booking.room_id'),
            'check_in' => session('booking.check_in'),
            'check_out' => session('booking.check_out'),
            'guests_count' => session('booking.guests'),
            'total_price' => $this->getBookingSummary()['final_total'],
            'status' => 'pending',
            'guest_first_name' => $guestInfo['first_name'],
            'guest_last_name' => $guestInfo['last_name'],
            'guest_email' => $guestInfo['email'],
            'guest_phone' => $guestInfo['phone'],
            'guest_country' => $guestInfo['country'],
            'guest_city' => $guestInfo['city'],
            'guest_address' => $guestInfo['address'] ?? null,
            'guest_postal_code' => $guestInfo['postal_code'] ?? null,
            'special_requests' => $guestInfo['special_requests'] ?? null,
            'additional_services' => session('booking.additional_services', []),
            'discount_id' => session('booking.discount.id') ?? null,
            'discount_amount' => $this->getBookingSummary()['discount_amount'],
            'booking_source' => 'website',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Отправляем уведомление администратору
        // Notification::sendAdmin(new NewBookingCreated($booking));

        // Отправляем подтверждение гостю
        // Mail::to($guestInfo['email'])->send(new BookingConfirmation($booking));

        return $booking;
    }

    /**
     * Create payment record.
     */
    private function createPayment(Booking $booking, array $paymentData): Payment
    {
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'amount' => $booking->total_price,
            'currency' => $paymentData['currency'],
            'payment_system' => $paymentData['payment_system'],
            'status' => $paymentData['payment_system'] === 'cash' ? 'pending' : 'pending',
            'transaction_id' => 'TEMP_' . Str::random(16),
            'payment_method_saved' => $paymentData['save_payment_method'] ?? false,
            'metadata' => [
                'booking_details' => [
                    'room_id' => $booking->room_id,
                    'check_in' => $booking->check_in,
                    'check_out' => $booking->check_out,
                    'guests' => $booking->guests_count,
                ],
                'guest_info' => [
                    'name' => $booking->guest_first_name . ' ' . $booking->guest_last_name,
                    'email' => $booking->guest_email,
                    'phone' => $booking->guest_phone,
                ],
            ],
        ]);

        // Если оплата наличными, обновляем статус бронирования
        if ($paymentData['payment_system'] === 'cash') {
            $booking->update(['payment_status' => 'pending_cash']);
        }

        return $payment;
    }

    /**
     * Apply discount to booking.
     */
    private function applyDiscountToBooking(Booking $booking): void
    {
        $discountData = session('booking.discount');

        $booking->update([
            'discount_id' => $discountData['id'],
            'discount_amount' => $this->getBookingSummary()['discount_amount'],
            'discount_code' => $discountData['code'],
        ]);
    }

    /**
     * Subscribe to newsletter.
     */
    private function subscribeToNewsletter(string $email): void
    {
        \App\Models\NewsletterSubscription::updateOrCreate(
            ['email' => $email],
            [
                'subscribed_at' => now(),
                'source' => 'booking_form',
                'ip_address' => request()->ip(),
            ]
        );
    }

    /**
     * Redirect to payment gateway.
     */
    private function redirectToPaymentGateway(Payment $payment): RedirectResponse
    {
        switch ($payment->payment_system) {
            case 'yookassa':
                // return redirect()->route('payment.yookassa.create', $payment);
                return redirect()->route('booking.confirmation', $payment->booking)
                    ->with('info', 'Интеграция с ЮKassa в разработке.');

            case 'stripe':
                // return redirect()->route('payment.stripe.checkout', $payment);
                return redirect()->route('booking.confirmation', $payment->booking)
                    ->with('info', 'Интеграция со Stripe в разработке.');

            case 'paypal':
                // return redirect()->route('payment.paypal.create', $payment);
                return redirect()->route('booking.confirmation', $payment->booking)
                    ->with('info', 'Интеграция с PayPal в разработке.');

            default:
                return redirect()->route('booking.confirmation', $payment->booking)
                    ->with('info', 'Выбранный способ оплаты временно недоступен.');
        }
    }

    /**
     * Booking confirmation page.
     */
    public function confirmation(Booking $booking): View
    {
        // Проверяем, что пользователь имеет доступ к этому бронированию
        if (Auth::check()) {
            if ($booking->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                abort(403);
            }
        } else {
            // Для гостей проверяем email
            if ($booking->guest_email !== session('booking_guest_email')) {
                abort(403);
            }
        }

        $booking->load(['room.photos', 'room.type', 'payment']);

        $room = $booking->room;
        $payment = $booking->payment;

        return view('frontend.booking.confirmation', compact('booking', 'room', 'payment'));
    }

    /**
     * Download booking confirmation (PDF).
     */
    public function downloadConfirmation(Booking $booking)
    {
        // Проверяем доступ
        if (Auth::check() && $booking->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403);
        }

        $booking->load(['room', 'payment']);

        // Генерируем PDF
        // $pdf = PDF::loadView('pdf.booking-confirmation', compact('booking'));

        // return $pdf->download("booking-confirmation-{$booking->id}.pdf");

        return back()->with('info', 'Функция скачивания подтверждения временно недоступна.');
    }

    /**
     * Send booking confirmation to email.
     */
    public function sendConfirmationEmail(Booking $booking): RedirectResponse
    {
        // Проверяем доступ
        if (Auth::check() && $booking->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403);
        }

        // Отправляем email
        // Mail::to($booking->guest_email)->send(new BookingConfirmation($booking));

        return back()->with('success', 'Подтверждение отправлено на email.');
    }

    /**
     * Cancel booking (for user).
     */
    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        // Проверяем доступ
        if (Auth::check() && $booking->user_id !== Auth::id()) {
            abort(403);
        }

        // Проверяем статус бронирования
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return back()->withErrors(['error' => 'Невозможно отменить бронирование с текущим статусом.']);
        }

        // Проверяем срок отмены
        $checkInDate = Carbon::parse($booking->check_in);
        $daysUntilCheckIn = now()->diffInDays($checkInDate, false);

        // Политика отмены
        $cancellationPolicy = $this->getCancellationPolicy($daysUntilCheckIn);

        if (!$cancellationPolicy['allowed']) {
            return back()->withErrors(['error' => $cancellationPolicy['message']]);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        // Обновляем статус бронирования
        $booking->update([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['cancellation_reason'],
            'cancelled_at' => now(),
            'cancelled_by_user' => true,
        ]);

        // Возвращаем средства если нужно
        if ($cancellationPolicy['refund'] && $booking->payment && $booking->payment->status === 'completed') {
            $booking->payment->update([
                'status' => 'refunded',
                'refund_reason' => 'Отмена бронирования пользователем',
                'refunded_at' => now(),
            ]);
        }

        // Отправляем уведомление
        // Notification::sendAdmin(new BookingCancelledByUser($booking));
        // Mail::to($booking->guest_email)->send(new BookingCancelled($booking));

        return redirect()->route('profile.bookings')
            ->with('success', 'Бронирование успешно отменено. ' . $cancellationPolicy['refund_message']);
    }

    /**
     * Get cancellation policy.
     */
    private function getCancellationPolicy(int $daysUntilCheckIn): array
    {
        if ($daysUntilCheckIn < 0) {
            return [
                'allowed' => false,
                'message' => 'Бронирование уже началось.',
                'refund' => false,
                'refund_message' => '',
            ];
        }

        if ($daysUntilCheckIn >= 30) {
            return [
                'allowed' => true,
                'message' => 'Бесплатная отмена за 30 дней до заезда.',
                'refund' => true,
                'refund_message' => 'Полный возврат средств будет произведен в течение 5-10 рабочих дней.',
            ];
        } elseif ($daysUntilCheckIn >= 7) {
            return [
                'allowed' => true,
                'message' => 'Отмена за 7-29 дней до заезда.',
                'refund' => true,
                'refund_message' => 'Будет возвращено 50% от суммы бронирования.',
            ];
        } else {
            return [
                'allowed' => false,
                'message' => 'Отмена менее чем за 7 дней до заезда невозможна. Свяжитесь с администрацией.',
                'refund' => false,
                'refund_message' => '',
            ];
        }
    }

    /**
     * Check availability (AJAX).
     */
    public function checkAvailability(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'guests' => 'required|integer|min:1',
        ]);

        $room = Room::findOrFail($validated['room_id']);

        $availability = $this->checkRoomAvailability(
            $room,
            $validated['check_in'],
            $validated['check_out'],
            $validated['guests']
        );

        if (!$availability['available']) {
            return response()->json([
                'success' => false,
                'message' => $availability['message'],
            ]);
        }

        // Рассчитываем стоимость
        $checkIn = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);
        $nights = $checkIn->diffInDays($checkOut);

        $priceDetails = $this->calculateBookingPrice($room, $checkIn, $checkOut, $nights);

        return response()->json([
            'success' => true,
            'available' => true,
            'room_id' => $room->id,
            'room_name' => $room->name,
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'nights' => $nights,
            'guests' => $validated['guests'],
            'price_details' => $priceDetails,
            'booking_url' => route('booking.step1', [
                'room_id' => $room->id,
                'check_in' => $validated['check_in'],
                'check_out' => $validated['check_out'],
                'guests' => $validated['guests'],
            ]),
        ]);
    }

    /**
     * Get booking session data (AJAX).
     */
    public function getSessionData(): \Illuminate\Http\JsonResponse
    {
        $hasSession = session()->has('booking.room_id');

        if (!$hasSession) {
            return response()->json([
                'success' => false,
                'has_session' => false,
            ]);
        }

        $room = Room::with(['photos', 'type'])->find(session('booking.room_id'));
        $bookingSummary = $this->getBookingSummary();

        return response()->json([
            'success' => true,
            'has_session' => true,
            'room' => $room,
            'summary' => $bookingSummary,
            'current_step' => $this->getCurrentStep(),
        ]);
    }

    /**
     * Get current booking step.
     */
    private function getCurrentStep(): int
    {
        if (!session()->has('booking.room_id')) {
            return 1;
        }

        if (!session()->has('booking.guest_info')) {
            return 2;
        }

        if (!session()->has('booking.additional_services')) {
            return 3;
        }

        return 4;
    }

    /**
     * Clear booking session.
     */
    public function clearSession(): \Illuminate\Http\JsonResponse
    {
        session()->forget([
            'booking.room_id',
            'booking.check_in',
            'booking.check_out',
            'booking.guests',
            'booking.nights',
            'booking.base_price',
            'booking.price_breakdown',
            'booking.guest_info',
            'booking.additional_services',
            'booking.additional_services_total',
            'booking.transfer_option',
            'booking.excursions',
            'booking.discount',
            'booking.newsletter_subscribe',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Сессия бронирования очищена.',
        ]);
    }

    /**
     * Get countries list.
     */
    private function getCountriesList(): array
    {
        return [
            'RU' => 'Россия',
            'UA' => 'Украина',
            'BY' => 'Беларусь',
            'KZ' => 'Казахстан',
            'US' => 'США',
            'DE' => 'Германия',
            'FR' => 'Франция',
            'IT' => 'Италия',
            'ES' => 'Испания',
            'GB' => 'Великобритания',
            'CN' => 'Китай',
            'JP' => 'Япония',
            'KR' => 'Южная Корея',
            'IN' => 'Индия',
            'BR' => 'Бразилия',
            'CA' => 'Канада',
            'AU' => 'Австралия',
            'TR' => 'Турция',
            'EG' => 'Египет',
            'TH' => 'Тайланд',
            'VN' => 'Вьетнам',
            'ID' => 'Индонезия',
            'MY' => 'Малайзия',
            'SG' => 'Сингапур',
            'AE' => 'ОАЭ',
        ];
    }

    /**
     * Get booking terms and conditions.
     */
    public function terms(): View
    {
        return view('frontend.booking.terms');
    }

    /**
     * Get cancellation policy page.
     */
    public function cancellationPolicy(): View
    {
        return view('frontend.booking.cancellation-policy');
    }

    /**
     * Get privacy policy page.
     */
    public function privacyPolicy(): View
    {
        return view('frontend.booking.privacy-policy');
    }
}
