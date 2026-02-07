<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactFormSubmitted;
use App\Mail\ContactFormConfirmation;
use App\Models\ContactMessage;
use App\Models\Hotel;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ContactController extends Controller
{
    /**
     * Показать страницу контактов
     *
     * @return View
     */
    public function index(): View
    {
        // Получаем информацию об отеле для контактов
        $hotel = Cache::remember('hotel_contact_info', 3600, function () {
            return Hotel::where('status', 'active')
                ->firstOrFail();
        });

        // Получаем настройки контактов
        $contactSettings = Cache::remember('contact_settings', 3600, function () {
            return Setting::where('category', 'contact')
                ->orWhere('key', 'like', 'contact_%')
                ->pluck('value', 'key')
                ->toArray();
        });

        // Формируем контактную информацию
        $contactInfo = [
            'phone' => $contactSettings['contact_phone'] ?? $hotel->phone,
            'email' => $contactSettings['contact_email'] ?? $hotel->email,
            'address' => $contactSettings['contact_address'] ?? $hotel->address,
            'work_hours' => $contactSettings['contact_work_hours'] ?? 'Пн-Вс: 08:00 - 22:00',
            'emergency_phone' => $contactSettings['contact_emergency_phone'] ?? null,
        ];

        // Координаты для карты
        $coordinates = $hotel->coordinates ?? [
            'lat' => $contactSettings['map_latitude'] ?? 44.605401,
            'lng' => $contactSettings['map_longitude'] ?? 33.522200,
        ];

        if (is_string($coordinates)) {
            $coordinates = json_decode($coordinates, true);
        }

        // FAQ для раздела контактов
        $faqItems = Cache::remember('contact_faq', 86400, function () {
            return \App\Models\FAQ::where('category', 'contact')
                ->where('is_active', true)
                ->orderBy('order')
                ->get();
        });

        return view('contact.index', compact(
            'hotel',
            'contactInfo',
            'coordinates',
            'faqItems'
        ));
    }

    /**
     * Отправить контактное сообщение
     *
     * @param ContactRequest $request
     * @return RedirectResponse|JsonResponse
     */
    public function send(ContactRequest $request)
    {
        // Проверка reCAPTCHA, если включена
        if (config('services.recaptcha.enabled', false)) {
            $recaptchaValid = $this->validateRecaptcha($request->input('g-recaptcha-response'));

            if (!$recaptchaValid) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'errors' => ['recaptcha' => ['Ошибка проверки reCAPTCHA. Пожалуйста, попробуйте еще раз.']]
                    ], 422);
                }

                return back()
                    ->withInput()
                    ->withErrors(['recaptcha' => 'Ошибка проверки reCAPTCHA. Пожалуйста, попробуйте еще раз.']);
            }
        }

        // Проверка защиты от спама (скрытое поле)
        if (!empty($request->input('website'))) {
            // Скорее всего, это бот (заполнил скрытое поле)
            // В реальном приложении можно залогировать или игнорировать
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Сообщение отправлено успешно'
                ]);
            }

            return redirect()
                ->route('contact.index')
                ->with('success', 'Сообщение отправлено успешно! Мы свяжемся с вами в ближайшее время.');
        }

        // Проверка частоты отправки сообщений (ограничение спама)
        if (!$this->checkRateLimit($request->ip(), $request->input('email'))) {
            if ($request->wantsJson()) {
                return response()->json([
                    'errors' => ['rate_limit' => ['Слишком много запросов. Пожалуйста, попробуйте позже.']]
                ], 429);
            }

            return back()
                ->withInput()
                ->withErrors(['rate_limit' => 'Слишком много запросов. Пожалуйста, попробуйте позже.']);
        }

        // Сохраняем сообщение в базу данных
        $messageData = $request->validated();
        $messageData['ip_address'] = $request->ip();
        $messageData['user_agent'] = $request->userAgent();
        $messageData['user_id'] = auth()->id();

        $contactMessage = ContactMessage::create($messageData);

        // Определяем тему письма
        $subjectType = $request->input('subject_type', 'general');
        $subjects = [
            'general' => 'Общий вопрос',
            'booking' => 'Вопрос по бронированию',
            'payment' => 'Вопрос по оплате',
            'cancellation' => 'Отмена бронирования',
            'complaint' => 'Жалоба',
            'suggestion' => 'Предложение',
        ];

        $subject = $subjects[$subjectType] ?? 'Общий вопрос';

        // Отправляем уведомление администратору
        try {
            $adminEmail = config('mail.contact_to', config('mail.from.address'));

            Mail::to($adminEmail)->send(new ContactFormSubmitted(
                $contactMessage,
                $subject
            ));
        } catch (\Exception $e) {
            \Log::error('Ошибка отправки email администратору: ' . $e->getMessage());
        }

        // Отправляем подтверждение пользователю
        if ($request->input('send_copy', false)) {
            try {
                Mail::to($contactMessage->email)->send(new ContactFormConfirmation(
                    $contactMessage,
                    $subject
                ));
            } catch (\Exception $e) {
                \Log::error('Ошибка отправки подтверждения пользователю: ' . $e->getMessage());
            }
        }

        // Отправляем уведомление в Telegram, если настроено
        if (config('services.telegram.enabled', false)) {
            $this->sendTelegramNotification($contactMessage, $subject);
        }

        // Увеличиваем счетчик отправленных сообщений для rate limit
        $this->incrementRateLimit($request->ip(), $request->input('email'));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Сообщение отправлено успешно! Мы свяжемся с вами в ближайшее время.',
                'message_id' => $contactMessage->id
            ]);
        }

        return redirect()
            ->route('contact.index')
            ->with('success', 'Сообщение отправлено успешно! Мы свяжемся с вами в ближайшее время.');
    }

    /**
     * Страница подтверждения отправки сообщения
     *
     * @param ContactMessage $message
     * @return View|RedirectResponse
     */
    public function success(ContactMessage $message)
    {
        // Проверяем, принадлежит ли сообщение текущему пользователю
        if (auth()->check() && $message->user_id !== auth()->id()) {
            abort(403);
        }

        // Или проверяем по email (для неавторизованных пользователей)
        if (!auth()->check() && session('contact_email') !== $message->email) {
            abort(403);
        }

        return view('contact.success', compact('message'));
    }

    /**
     * Страница "Свяжитесь с нами" для конкретного бронирования
     *
     * @param Request $request
     * @param string|null $bookingId
     * @return View|RedirectResponse
     */
    public function bookingContact(Request $request, $bookingId = null)
    {
        $booking = null;

        if ($bookingId) {
            if (auth()->check()) {
                $booking = \App\Models\Booking::where('id', $bookingId)
                    ->where('user_id', auth()->id())
                    ->first();
            }

            if (!$booking) {
                return redirect()->route('contact.index')
                    ->with('warning', 'Бронирование не найдено');
            }
        }

        $subjectTypes = [
            'booking' => 'Вопрос по бронированию',
            'payment' => 'Вопрос по оплате',
            'cancellation' => 'Отмена бронирования',
            'change' => 'Изменение бронирования',
            'other' => 'Другой вопрос'
        ];

        return view('contact.booking', compact('booking', 'subjectTypes'));
    }

    /**
     * API для получения контактной информации
     *
     * @return JsonResponse
     */
    public function getInfo(): JsonResponse
    {
        $hotel = Hotel::where('status', 'active')->first();

        if (!$hotel) {
            return response()->json(['error' => 'Отель не найден'], 404);
        }

        $contactInfo = [
            'name' => $hotel->name,
            'phone' => $hotel->phone,
            'email' => $hotel->email,
            'address' => $hotel->address,
            'coordinates' => $hotel->coordinates,
            'website' => config('app.url'),
            'social_links' => $hotel->social_links ?? [],
            'work_hours' => Setting::where('key', 'contact_work_hours')->value('value') ?? 'Пн-Вс: 08:00 - 22:00',
            'emergency_phone' => Setting::where('key', 'contact_emergency_phone')->value('value')
        ];

        return response()->json($contactInfo);
    }

    /**
     * Часто задаваемые вопросы
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function faq(Request $request)
    {
        $categories = \App\Models\FAQ::distinct()->pluck('category');

        $faqByCategory = [];
        foreach ($categories as $category) {
            $faqByCategory[$category] = \App\Models\FAQ::where('category', $category)
                ->where('is_active', true)
                ->orderBy('order')
                ->get();
        }

        if ($request->wantsJson()) {
            return response()->json($faqByCategory);
        }

        return view('contact.faq', compact('faqByCategory'));
    }

    /**
     * Проверка reCAPTCHA
     *
     * @param string $recaptchaResponse
     * @return bool
     */
    private function validateRecaptcha(string $recaptchaResponse): bool
    {
        $secretKey = config('services.recaptcha.secret_key');

        if (empty($secretKey)) {
            return true; // Если ключ не настроен, пропускаем проверку
        }

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $secretKey,
            'response' => $recaptchaResponse,
            'remoteip' => request()->ip()
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $result = json_decode($response, true);

        return $result['success'] ?? false;
    }

    /**
     * Проверка rate limit для отправки сообщений
     *
     * @param string $ip
     * @param string $email
     * @return bool
     */
    private function checkRateLimit(string $ip, string $email): bool
    {
        $ipKey = 'contact_rate_limit_ip:' . $ip;
        $emailKey = 'contact_rate_limit_email:' . md5($email);

        $ipCount = Cache::get($ipKey, 0);
        $emailCount = Cache::get($emailKey, 0);

        $maxPerHour = config('contact.rate_limit.max_per_hour', 5);
        $maxPerDay = config('contact.rate_limit.max_per_day', 20);

        // Проверка по IP
        if ($ipCount >= $maxPerDay) {
            return false;
        }

        // Проверка по email
        if ($emailCount >= $maxPerHour) {
            return false;
        }

        return true;
    }

    /**
     * Увеличить счетчик rate limit
     *
     * @param string $ip
     * @param string $email
     * @return void
     */
    private function incrementRateLimit(string $ip, string $email): void
    {
        $ipKey = 'contact_rate_limit_ip:' . $ip;
        $emailKey = 'contact_rate_limit_email:' . md5($email);

        Cache::increment($ipKey);
        Cache::increment($emailKey);

        // Устанавливаем время жизни для счетчиков
        Cache::put($ipKey, Cache::get($ipKey), now()->addDay());
        Cache::put($emailKey, Cache::get($emailKey), now()->addHour());
    }

    /**
     * Отправить уведомление в Telegram
     *
     * @param ContactMessage $message
     * @param string $subject
     * @return void
     */
    private function sendTelegramNotification(ContactMessage $message, string $subject): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.contact_chat_id');

        if (empty($botToken) || empty($chatId)) {
            return;
        }

        $text = "📨 *Новое сообщение с сайта*\n\n";
        $text .= "*Тема:* " . $subject . "\n";
        $text .= "*Имя:* " . $message->name . "\n";
        $text .= "*Email:* " . $message->email . "\n";
        $text .= "*Телефон:* " . ($message->phone ?? 'не указан') . "\n\n";
        $text .= "*Сообщение:*\n" . $message->message . "\n\n";
        $text .= "*IP:* " . $message->ip_address . "\n";
        $text .= "*Время:* " . $message->created_at->format('d.m.Y H:i');

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        ];

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            \Log::error('Ошибка отправки в Telegram: ' . $e->getMessage());
        }
    }

    /**
     * Скачать визитку отеля (vCard)
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadVCard()
    {
        $hotel = Hotel::where('status', 'active')->first();

        if (!$hotel) {
            abort(404);
        }

        $vCard = "BEGIN:VCARD\n";
        $vCard .= "VERSION:3.0\n";
        $vCard .= "FN:" . $hotel->name . "\n";
        $vCard .= "ORG:" . $hotel->name . "\n";
        $vCard .= "TEL;TYPE=WORK,VOICE:" . $hotel->phone . "\n";
        $vCard .= "EMAIL:" . $hotel->email . "\n";
        $vCard .= "ADR;TYPE=WORK:;;" . $hotel->address . "\n";
        $vCard .= "URL:" . config('app.url') . "\n";
        $vCard .= "END:VCARD\n";

        $filename = 'hotel-' . str_slug($hotel->name) . '.vcf';
        $filepath = storage_path('app/public/temp/' . $filename);

        \File::ensureDirectoryExists(storage_path('app/public/temp'));
        \File::put($filepath, $vCard);

        return response()->download($filepath, $filename)->deleteFileAfterSend(true);
    }
}
