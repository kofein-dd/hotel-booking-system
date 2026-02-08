<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\Review;
use App\Models\Facility;
use App\Models\HotelImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HotelController extends Controller
{
    /**
     * Показать главную страницу отеля
     */
    public function index(Request $request)
    {
        try {
            // Получаем основной отель (предполагаем один отель в системе)
            $hotel = Cache::remember('hotel_main_page', 3600, function () {
                return Hotel::with([
                    'images' => function($query) {
                        $query->orderBy('sort_order', 'asc');
                    },
                    'facilities' => function($query) {
                        $query->where('is_active', true)
                            ->orderBy('sort_order', 'asc');
                    },
                    'rooms' => function($query) {
                        $query->where('is_active', true)
                            ->where('is_available', true)
                            ->orderBy('sort_order', 'asc')
                            ->limit(6);
                    }
                ])->first();
            });

            if (!$hotel) {
                // Если отель не создан, показываем заглушку
                return Inertia::render('Frontend/Hotel/Index', [
                    'hotel' => null,
                    'featuredRooms' => [],
                    'reviews' => [],
                    'facilities' => [],
                    'stats' => [
                        'rooms_count' => 0,
                        'happy_guests' => 0,
                        'years_experience' => 0,
                        'rating' => 0
                    ]
                ]);
            }

            // Получаем отзывы
            $reviews = Cache::remember('hotel_reviews_' . $hotel->id, 1800, function () use ($hotel) {
                return Review::with(['user', 'room'])
                    ->whereHas('room', function($query) use ($hotel) {
                        $query->where('hotel_id', $hotel->id);
                    })
                    ->where('is_approved', true)
                    ->where('rating', '>=', 4)
                    ->orderBy('created_at', 'desc')
                    ->limit(8)
                    ->get();
            });

            // Получаем все удобства
            $facilities = Cache::remember('hotel_facilities_' . $hotel->id, 3600, function () {
                return Facility::where('is_active', true)
                    ->orderBy('sort_order', 'asc')
                    ->get();
            });

            // Получаем статистику
            $stats = Cache::remember('hotel_stats_' . $hotel->id, 3600, function () use ($hotel) {
                return [
                    'rooms_count' => Room::where('hotel_id', $hotel->id)
                        ->where('is_active', true)
                        ->count(),
                    'happy_guests' => Booking::whereHas('room', function($query) use ($hotel) {
                        $query->where('hotel_id', $hotel->id);
                    })
                        ->where('status', 'completed')
                        ->count(),
                    'years_experience' => $hotel->years_experience ?? 5,
                    'rating' => Review::whereHas('room', function($query) use ($hotel) {
                            $query->where('hotel_id', $hotel->id);
                        })
                            ->where('is_approved', true)
                            ->avg('rating') ?? 4.8
                ];
            });

            // Получаем рекомендуемые номера
            $featuredRooms = Cache::remember('featured_rooms_' . $hotel->id, 1800, function () use ($hotel) {
                return Room::where('hotel_id', $hotel->id)
                    ->where('is_active', true)
                    ->where('is_available', true)
                    ->where('is_featured', true)
                    ->with(['images', 'facilities'])
                    ->orderBy('sort_order', 'asc')
                    ->limit(4)
                    ->get();
            });

            return Inertia::render('Frontend/Hotel/Index', [
                'hotel' => $hotel,
                'featuredRooms' => $featuredRooms,
                'reviews' => $reviews,
                'facilities' => $facilities,
                'stats' => $stats,
                'seo' => [
                    'title' => $hotel->seo_title ?? $hotel->name,
                    'description' => $hotel->seo_description ?? substr(strip_tags($hotel->description), 0, 160),
                    'keywords' => $hotel->seo_keywords
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка при загрузке главной страницы отеля: ' . $e->getMessage());

            return Inertia::render('Frontend/Hotel/Index', [
                'hotel' => null,
                'featuredRooms' => [],
                'reviews' => [],
                'facilities' => [],
                'stats' => [
                    'rooms_count' => 0,
                    'happy_guests' => 0,
                    'years_experience' => 0,
                    'rating' => 0
                ],
                'error' => 'Произошла ошибка при загрузке данных'
            ]);
        }
    }

    /**
     * Показать страницу "Об отеле"
     */
    public function about()
    {
        try {
            $hotel = Cache::remember('hotel_about_page', 3600, function () {
                return Hotel::with([
                    'images' => function($query) {
                        $query->where('type', 'about')
                            ->orWhere('type', 'gallery')
                            ->orderBy('sort_order', 'asc');
                    },
                    'facilities' => function($query) {
                        $query->where('is_active', true)
                            ->orderBy('sort_order', 'asc');
                    }
                ])->first();
            });

            if (!$hotel) {
                abort(404, 'Отель не найден');
            }

            // Получаем команду отеля (если есть такое поле или связанная таблица)
            $team = []; // Заглушка, нужно реализовать модель Team

            return Inertia::render('Frontend/Hotel/About', [
                'hotel' => $hotel,
                'team' => $team,
                'facilities' => $hotel->facilities ?? [],
                'seo' => [
                    'title' => 'Об отеле ' . $hotel->name . ' | ' . ($hotel->seo_title ?? 'Морской Отель'),
                    'description' => 'Узнайте больше об отеле ' . $hotel->name . '. ' .
                        ($hotel->short_description ?? substr(strip_tags($hotel->description), 0, 140))
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка при загрузке страницы "Об отеле": ' . $e->getMessage());
            abort(500, 'Ошибка при загрузке страницы');
        }
    }

    /**
     * Показать галерею отеля
     */
    public function gallery()
    {
        try {
            $hotel = Hotel::with([
                'images' => function($query) {
                    $query->orderBy('sort_order', 'asc');
                }
            ])->first();

            if (!$hotel) {
                abort(404, 'Отель не найден');
            }

            // Группируем изображения по категориям
            $gallery = [
                'all' => $hotel->images,
                'rooms' => $hotel->images->where('type', 'room'),
                'territory' => $hotel->images->where('type', 'territory'),
                'restaurant' => $hotel->images->where('type', 'restaurant'),
                'pool' => $hotel->images->where('type', 'pool'),
                'spa' => $hotel->images->where('type', 'spa'),
            ];

            return Inertia::render('Frontend/Hotel/Gallery', [
                'hotel' => $hotel,
                'gallery' => $gallery,
                'seo' => [
                    'title' => 'Фотогалерея ' . $hotel->name,
                    'description' => 'Фотографии номеров, территории, ресторана и других зон отеля ' . $hotel->name
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка при загрузке галереи отеля: ' . $e->getMessage());
            abort(500, 'Ошибка при загрузке галереи');
        }
    }

    /**
     * Показать страницу контактов
     */
    public function contact()
    {
        try {
            $hotel = Hotel::first();

            if (!$hotel) {
                abort(404, 'Отель не найден');
            }

            // Парсим координаты если они в формате "lat,lng"
            $coordinates = null;
            if ($hotel->coordinates) {
                $coords = explode(',', $hotel->coordinates);
                if (count($coords) === 2) {
                    $coordinates = [
                        'lat' => trim($coords[0]),
                        'lng' => trim($coords[1])
                    ];
                }
            }

            // Форматируем контактную информацию
            $contactInfo = [
                'address' => $hotel->address,
                'phone' => $hotel->phone,
                'email' => $hotel->email,
                'whatsapp' => $hotel->whatsapp,
                'telegram' => $hotel->telegram,
                'viber' => $hotel->viber,
                'work_hours' => $hotel->work_hours,
            ];

            return Inertia::render('Frontend/Hotel/Contact', [
                'hotel' => $hotel,
                'contactInfo' => $contactInfo,
                'coordinates' => $coordinates,
                'seo' => [
                    'title' => 'Контакты | ' . $hotel->name,
                    'description' => 'Контактная информация отеля ' . $hotel->name .
                        '. Адрес, телефон, email, карта проезда.'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка при загрузке страницы контактов: ' . $e->getMessage());
            abort(500, 'Ошибка при загрузке страницы контактов');
        }
    }

    /**
     * Обработка формы обратной связи
     */
    public function sendContactForm(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:2000',
                'captcha' => 'required|captcha'
            ]);

            // Получаем настройки отеля
            $hotel = Hotel::first();
            $adminEmail = $hotel->email ?? config('mail.from.address');

            // Отправляем email администратору
            \Mail::to($adminEmail)->send(new \App\Mail\ContactFormMail($validated));

            // Сохраняем в базу (если есть модель ContactMessage)
            if (class_exists('\App\Models\ContactMessage')) {
                \App\Models\ContactMessage::create($validated);
            }

            // Отправляем уведомление в Telegram если настроен бот
            if (config('services.telegram.bot_token')) {
                $this->sendTelegramNotification($validated);
            }

            return response()->json([
                'success' => true,
                'message' => 'Сообщение успешно отправлено! Мы свяжемся с вами в ближайшее время.'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Ошибка при отправке формы обратной связи: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при отправке сообщения. Попробуйте позже.'
            ], 500);
        }
    }

    /**
     * Показать страницу "Услуги"
     */
    public function services()
    {
        try {
            $hotel = Hotel::with([
                'facilities' => function($query) {
                    $query->where('is_active', true)
                        ->orderBy('sort_order', 'asc');
                }
            ])->first();

            if (!$hotel) {
                abort(404, 'Отель не найден');
            }

            // Группируем услуги по категориям
            $servicesByCategory = $hotel->facilities->groupBy('category');

            // Дополнительные услуги (не входящие в Facility)
            $additionalServices = [
                [
                    'title' => 'Трансфер',
                    'icon' => 'car',
                    'description' => 'Встреча в аэропорту и трансфер до отеля',
                    'price' => 'от 1500 руб.'
                ],
                [
                    'title' => 'Экскурсии',
                    'icon' => 'map-marked-alt',
                    'description' => 'Организация экскурсий по достопримечательностям',
                    'price' => 'от 2000 руб.'
                ],
                [
                    'title' => 'Аренда авто',
                    'icon' => 'car-side',
                    'description' => 'Помощь в аренде автомобиля',
                    'price' => 'по запросу'
                ],
                [
                    'title' => 'SPA-процедуры',
                    'icon' => 'spa',
                    'description' => 'Расслабляющие массажи и процедуры',
                    'price' => 'от 3000 руб.'
                ]
            ];

            return Inertia::render('Frontend/Hotel/Services', [
                'hotel' => $hotel,
                'servicesByCategory' => $servicesByCategory,
                'additionalServices' => $additionalServices,
                'seo' => [
                    'title' => 'Услуги отеля ' . $hotel->name,
                    'description' => 'Все услуги и удобства отеля ' . $hotel->name .
                        '. Ресторан, бассейн, спа, Wi-Fi и многое другое.'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка при загрузке страницы услуг: ' . $e->getMessage());
            abort(500, 'Ошибка при загрузке страницы услуг');
        }
    }

    /**
     * Показать страницу "Как добраться"
     */
    public function location()
    {
        try {
            $hotel = Hotel::first();

            if (!$hotel) {
                abort(404, 'Отель не найден');
            }

            // Парсим координаты
            $coordinates = null;
            if ($hotel->coordinates) {
                $coords = explode(',', $hotel->coordinates);
                if (count($coords) === 2) {
                    $coordinates = [
                        'lat' => trim($coords[0]),
                        'lng' => trim($coords[1])
                    ];
                }
            }

            // Инструкции как добраться
            $instructions = [
                'from_airport' => $hotel->instructions_from_airport ?? [
                        'title' => 'Из аэропорта',
                        'description' => 'На такси: 30-40 минут, стоимость 1500-2000 руб.<br>На автобусе: маршрут №101 до центра, затем такси',
                        'distance' => '25 км',
                        'time' => '30-40 мин'
                    ],
                'from_station' => $hotel->instructions_from_station ?? [
                        'title' => 'С ж/д вокзала',
                        'description' => 'На такси: 15-20 минут, стоимость 500-800 руб.<br>На общественном транспорте: автобусы №5, №7',
                        'distance' => '8 км',
                        'time' => '15-20 мин'
                    ],
                'by_car' => $hotel->instructions_by_car ?? [
                        'title' => 'На автомобиле',
                        'description' => 'По трассе М4, съезд на 125 км. Далее по указателям "Морской Отель"',
                        'parking' => 'Бесплатная охраняемая парковка на территории'
                    ]
            ];

            // Ближайшие достопримечательности
            $attractions = $hotel->attractions ?? [
                [
                    'name' => 'Морской пляж',
                    'distance' => '100 м',
                    'description' => 'Песчаный пляж с шезлонгами и зонтиками'
                ],
                [
                    'name' => 'Исторический центр',
                    'distance' => '2 км',
                    'description' => 'Старинные улочки, музеи и рестораны'
                ],
                [
                    'name' => 'Аквапарк',
                    'distance' => '3 км',
                    'description' => 'Крупнейший аквапарк в регионе'
                ],
                [
                    'name' => 'Горнолыжный курорт',
                    'distance' => '15 км',
                    'description' => 'Зимой - горные лыжи, летом - пешие походы'
                ]
            ];

            return Inertia::render('Frontend/Hotel/Location', [
                'hotel' => $hotel,
                'coordinates' => $coordinates,
                'instructions' => $instructions,
                'attractions' => $attractions,
                'seo' => [
                    'title' => 'Как добраться до отеля ' . $hotel->name,
                    'description' => 'Схема проезда, координаты GPS, инструкции как добраться из аэропорта и с вокзала'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка при загрузке страницы "Как добраться": ' . $e->getMessage());
            abort(500, 'Ошибка при загрузке страницы');
        }
    }

    /**
     * Отправить уведомление в Telegram
     */
    private function sendTelegramNotification(array $data)
    {
        try {
            $botToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.chat_id');

            if (!$botToken || !$chatId) {
                return;
            }

            $message = "📨 *Новое сообщение с сайта*\n\n";
            $message .= "👤 *Имя:* " . $data['name'] . "\n";
            $message .= "📧 *Email:* " . $data['email'] . "\n";
            $message .= "📱 *Телефон:* " . $data['phone'] . "\n";
            $message .= "📝 *Тема:* " . $data['subject'] . "\n";
            $message .= "💬 *Сообщение:*\n" . $data['message'] . "\n\n";
            $message .= "🕐 *Время:* " . now()->format('d.m.Y H:i');

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

            $client = new \GuzzleHttp\Client();
            $client->post($url, [
                'form_params' => [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка при отправке в Telegram: ' . $e->getMessage());
        }
    }

    /**
     * Получить доступные даты для бронирования
     */
    public function getAvailableDates(Request $request)
    {
        try {
            $request->validate([
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:' . date('Y') . '|max:' . (date('Y') + 1)
            ]);

            $hotel = Hotel::first();
            if (!$hotel) {
                return response()->json(['available_dates' => []]);
            }

            // Здесь должна быть логика проверки доступности дат
            // Пока возвращаем заглушку - все даты доступны кроме прошедших
            $availableDates = [];
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $request->month, $request->year);

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = \Carbon\Carbon::create($request->year, $request->month, $day);
                if ($date->isFuture() || $date->isToday()) {
                    $availableDates[] = $date->format('Y-m-d');
                }
            }

            return response()->json([
                'available_dates' => $availableDates,
                'blocked_dates' => $hotel->blocked_dates ?? []
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка при получении доступных дат: ' . $e->getMessage());
            return response()->json(['error' => 'Ошибка сервера'], 500);
        }
    }
}
