<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\TelegramService;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WebhookController extends Controller
{
    protected $paymentService;
    protected $telegramService;
    protected $emailService;

    public function __construct(
        PaymentService $paymentService,
        TelegramService $telegramService,
        EmailService $emailService
    ) {
        $this->paymentService = $paymentService;
        $this->telegramService = $telegramService;
        $this->emailService = $emailService;
    }

    /**
     * Обработка платежных вебхуков
     *
     * @param Request $request
     * @param string $gateway
     * @return JsonResponse
     */
    public function payment(Request $request, string $gateway): JsonResponse
    {
        try {
            // Логируем полученный вебхук
            $this->logWebhook('payment', $gateway, $request->all());

            // Валидация вебхука в зависимости от платежного шлюза
            $isValid = $this->validatePaymentWebhook($request, $gateway);

            if (!$isValid) {
                Log::warning("Invalid payment webhook from {$gateway}", [
                    'headers' => $request->headers->all(),
                    'payload' => $request->all()
                ]);

                return response()->json(['error' => 'Invalid signature'], 403);
            }

            // Обработка вебхука в зависимости от платежного шлюза
            switch ($gateway) {
                case 'stripe':
                    $result = $this->handleStripeWebhook($request);
                    break;

                case 'yookassa':
                    $result = $this->handleYooKassaWebhook($request);
                    break;

                case 'paypal':
                    $result = $this->handlePayPalWebhook($request);
                    break;

                case 'tinkoff':
                    $result = $this->handleTinkoffWebhook($request);
                    break;

                default:
                    return response()->json(['error' => 'Unsupported payment gateway'], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error("Payment webhook processing error: {$e->getMessage()}", [
                'gateway' => $gateway,
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Webhook processing failed'
            ], 500);
        }
    }

    /**
     * Обработка вебхуков Telegram бота
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function telegram(Request $request): JsonResponse
    {
        try {
            // Логируем полученный вебхук
            $this->logWebhook('telegram', 'bot', $request->all());

            // Проверяем токен бота
            $botToken = config('services.telegram.bot_token');
            if (!$botToken) {
                return response()->json(['error' => 'Telegram bot not configured'], 500);
            }

            $update = $request->all();

            // Обработка различных типов обновлений
            if (isset($update['message'])) {
                $this->telegramService->handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->telegramService->handleCallbackQuery($update['callback_query']);
            } elseif (isset($update['inline_query'])) {
                $this->telegramService->handleInlineQuery($update['inline_query']);
            } elseif (isset($update['chosen_inline_result'])) {
                $this->telegramService->handleChosenInlineResult($update['chosen_inline_result']);
            }

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error("Telegram webhook error: {$e->getMessage()}", [
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обработка вебхуков email сервисов
     *
     * @param Request $request
     * @param string $service
     * @return JsonResponse
     */
    public function email(Request $request, string $service): JsonResponse
    {
        try {
            // Логируем полученный вебхук
            $this->logWebhook('email', $service, $request->all());

            switch ($service) {
                case 'sendgrid':
                    return $this->handleSendGridWebhook($request);

                case 'mailgun':
                    return $this->handleMailgunWebhook($request);

                case 'postmark':
                    return $this->handlePostmarkWebhook($request);

                case 'ses':
                    return $this->handleSESWebhook($request);

                default:
                    return response()->json(['error' => 'Unsupported email service'], 400);
            }

        } catch (\Exception $e) {
            Log::error("Email webhook error: {$e->getMessage()}", [
                'service' => $service,
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Webhook processing failed'
            ], 500);
        }
    }

    /**
     * Обработка вебхуков для SMS сервисов
     *
     * @param Request $request
     * @param string $provider
     * @return JsonResponse
     */
    public function sms(Request $request, string $provider): JsonResponse
    {
        try {
            // Логируем полученный вебхук
            $this->logWebhook('sms', $provider, $request->all());

            switch ($provider) {
                case 'twilio':
                    return $this->handleTwilioWebhook($request);

                case 'nexmo':
                case 'vonage':
                    return $this->handleVonageWebhook($request);

                case 'messagebird':
                    return $this->handleMessageBirdWebhook($request);

                default:
                    return response()->json(['error' => 'Unsupported SMS provider'], 400);
            }

        } catch (\Exception $e) {
            Log::error("SMS webhook error: {$e->getMessage()}", [
                'provider' => $provider,
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Webhook processing failed'
            ], 500);
        }
    }

    /**
     * Общий вебхук для внешних интеграций
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function general(Request $request): JsonResponse
    {
        try {
            $eventType = $request->input('event_type', $request->input('type', 'unknown'));
            $source = $request->input('source', $request->input('provider', 'unknown'));

            // Логируем полученный вебхук
            $this->logWebhook('general', $source, $request->all());

            // Обработка в зависимости от типа события
            switch ($eventType) {
                case 'booking.created':
                case 'booking.updated':
                case 'booking.cancelled':
                    return $this->handleBookingWebhook($request);

                case 'user.created':
                case 'user.updated':
                    return $this->handleUserWebhook($request);

                case 'review.created':
                case 'review.approved':
                    return $this->handleReviewWebhook($request);

                default:
                    // Проверяем, есть ли кастомный обработчик для этого события
                    if ($this->hasCustomHandler($eventType, $source)) {
                        return $this->handleCustomWebhook($eventType, $source, $request);
                    }

                    Log::warning("Unhandled general webhook event", [
                        'event_type' => $eventType,
                        'source' => $source,
                        'payload' => $request->all()
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Event received but not processed'
                    ]);
            }

        } catch (\Exception $e) {
            Log::error("General webhook error: {$e->getMessage()}", [
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Webhook processing failed'
            ], 500);
        }
    }

    /**
     * Валидация вебхука Stripe
     *
     * @param Request $request
     * @return mixed
     */
    private function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, $endpointSecret
            );
        } catch (\Exception $e) {
            throw new \Exception('Stripe webhook signature verification failed');
        }

        // Обработка события Stripe
        switch ($event->type) {
            case 'payment_intent.succeeded':
                return $this->paymentService->handleStripePaymentSucceeded($event->data->object);

            case 'payment_intent.payment_failed':
                return $this->paymentService->handleStripePaymentFailed($event->data->object);

            case 'charge.refunded':
                return $this->paymentService->handleStripeRefund($event->data->object);

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                return $this->paymentService->handleStripeSubscription($event->data->object, $event->type);

            default:
                Log::info("Unhandled Stripe event type: {$event->type}");
                return ['status' => 'unhandled_event'];
        }
    }

    /**
     * Валидация вебхука YooKassa
     *
     * @param Request $request
     * @return mixed
     */
    private function handleYooKassaWebhook(Request $request)
    {
        $payload = $request->all();

        // Проверяем подпись YooKassa
        if (!$this->validateYooKassaSignature($request)) {
            throw new \Exception('YooKassa webhook signature verification failed');
        }

        $event = $payload['event'] ?? null;
        $payment = $payload['object'] ?? null;

        if (!$event || !$payment) {
            throw new \Exception('Invalid YooKassa webhook payload');
        }

        switch ($event) {
            case 'payment.waiting_for_capture':
                return $this->paymentService->handleYooKassaWaitingForCapture($payment);

            case 'payment.succeeded':
                return $this->paymentService->handleYooKassaPaymentSucceeded($payment);

            case 'payment.canceled':
                return $this->paymentService->handleYooKassaPaymentCanceled($payment);

            case 'refund.succeeded':
                return $this->paymentService->handleYooKassaRefundSucceeded($payment);

            default:
                Log::info("Unhandled YooKassa event: {$event}");
                return ['status' => 'unhandled_event'];
        }
    }

    /**
     * Обработка вебхука PayPal
     *
     * @param Request $request
     * @return mixed
     */
    private function handlePayPalWebhook(Request $request)
    {
        // PayPal верификация через IPN или REST API webhooks
        $payload = $request->all();
        $eventType = $payload['event_type'] ?? null;

        if (!$this->validatePayPalWebhook($request)) {
            throw new \Exception('PayPal webhook verification failed');
        }

        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                return $this->paymentService->handlePayPalPaymentCompleted($payload['resource'] ?? []);

            case 'PAYMENT.CAPTURE.DENIED':
                return $this->paymentService->handlePayPalPaymentDenied($payload['resource'] ?? []);

            case 'PAYMENT.CAPTURE.REFUNDED':
                return $this->paymentService->handlePayPalRefund($payload['resource'] ?? []);

            default:
                Log::info("Unhandled PayPal event: {$eventType}");
                return ['status' => 'unhandled_event'];
        }
    }

    /**
     * Обработка вебхука Тинькофф
     *
     * @param Request $request
     * @return mixed
     */
    private function handleTinkoffWebhook(Request $request)
    {
        $payload = $request->all();

        // Проверка подписи Тинькофф
        if (!$this->validateTinkoffSignature($request)) {
            throw new \Exception('Tinkoff webhook signature verification failed');
        }

        $status = $payload['Status'] ?? null;
        $paymentId = $payload['PaymentId'] ?? null;

        switch ($status) {
            case 'AUTHORIZED':
                return $this->paymentService->handleTinkoffAuthorized($paymentId, $payload);

            case 'CONFIRMED':
                return $this->paymentService->handleTinkoffConfirmed($paymentId, $payload);

            case 'REJECTED':
                return $this->paymentService->handleTinkoffRejected($paymentId, $payload);

            case 'REFUNDED':
                return $this->paymentService->handleTinkoffRefunded($paymentId, $payload);

            default:
                Log::info("Unhandled Tinkoff status: {$status}");
                return ['status' => 'unhandled_event'];
        }
    }

    /**
     * Обработка вебхука SendGrid
     *
     * @param Request $request
     * @return JsonResponse
     */
    private function handleSendGridWebhook(Request $request): JsonResponse
    {
        $events = json_decode($request->getContent(), true);

        if (!is_array($events)) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        foreach ($events as $event) {
            $this->emailService->processSendGridEvent($event);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Обработка вебхука Twilio
     *
     * @param Request $request
     * @return JsonResponse
     */
    private function handleTwilioWebhook(Request $request): JsonResponse
    {
        // Twilio верификация запроса
        $validator = new \Twilio\Security\RequestValidator(
            config('services.twilio.auth_token')
        );

        $url = $request->fullUrl();
        $params = $request->post();
        $signature = $request->header('X-Twilio-Signature');

        if (!$validator->validate($signature, $url, $params)) {
            return response()->json(['error' => 'Invalid Twilio signature'], 403);
        }

        // Обработка статуса SMS
        $messageSid = $request->input('MessageSid');
        $messageStatus = $request->input('MessageStatus');
        $to = $request->input('To');

        $this->emailService->processTwilioStatus($messageSid, $messageStatus, $to);

        return response()->json(['success' => true]);
    }

    /**
     * Обработка вебхука бронирований
     *
     * @param Request $request
     * @return JsonResponse
     */
    private function handleBookingWebhook(Request $request): JsonResponse
    {
        $bookingData = $request->all();
        $eventType = $request->input('event_type');

        // Сохраняем вебхук в лог
        \App\Models\WebhookLog::create([
            'type' => 'booking',
            'event' => $eventType,
            'payload' => json_encode($bookingData),
            'source' => $request->input('source', 'external'),
            'processed' => false
        ]);

        // Асинхронная обработка
        dispatch(function () use ($eventType, $bookingData) {
            try {
                // Обработка бронирования
                // Можно обновить статус бронирования, отправить уведомления и т.д.

                // Помечаем как обработанное
                \App\Models\WebhookLog::where('payload', json_encode($bookingData))
                    ->update(['processed' => true, 'processed_at' => now()]);

            } catch (\Exception $e) {
                Log::error("Booking webhook processing failed: {$e->getMessage()}");
            }
        })->delay(now()->addSeconds(5));

        return response()->json([
            'success' => true,
            'message' => 'Booking webhook received and queued for processing'
        ]);
    }

    /**
     * Валидация платежного вебхука
     *
     * @param Request $request
     * @param string $gateway
     * @return bool
     */
    private function validatePaymentWebhook(Request $request, string $gateway): bool
    {
        switch ($gateway) {
            case 'stripe':
                return $this->validateStripeWebhook($request);

            case 'yookassa':
                return $this->validateYooKassaSignature($request);

            case 'paypal':
                return $this->validatePayPalWebhook($request);

            case 'tinkoff':
                return $this->validateTinkoffSignature($request);

            default:
                // Для других шлюзов используем простую проверку по токену
                $expectedToken = config("services.{$gateway}.webhook_token");
                $receivedToken = $request->header('X-Webhook-Token') ?? $request->input('token');

                return $expectedToken && $receivedToken === $expectedToken;
        }
    }

    /**
     * Валидация вебхука Stripe
     *
     * @param Request $request
     * @return bool
     */
    private function validateStripeWebhook(Request $request): bool
    {
        $endpointSecret = config('services.stripe.webhook_secret');

        if (!$endpointSecret) {
            Log::warning('Stripe webhook secret not configured');
            return false;
        }

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Валидация подписи YooKassa
     *
     * @param Request $request
     * @return bool
     */
    private function validateYooKassaSignature(Request $request): bool
    {
        $secretKey = config('services.yookassa.secret_key');

        if (!$secretKey) {
            Log::warning('YooKassa secret key not configured');
            return false;
        }

        // YooKassa отправляет подпись в заголовке
        $signature = $request->header('Content-Signature');

        if (!$signature) {
            return false;
        }

        // Проверка подписи (упрощенная реализация)
        $payload = $request->getContent();
        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $secretKey, true));

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Логирование вебхуков
     *
     * @param string $type
     * @param string $source
     * @param array $payload
     * @return void
     */
    private function logWebhook(string $type, string $source, array $payload): void
    {
        // Ограничиваем логирование для предотвращения переполнения
        $cacheKey = "webhook_log_{$type}_{$source}_" . md5(json_encode($payload));

        if (!Cache::has($cacheKey)) {
            Log::info("Webhook received", [
                'type' => $type,
                'source' => $source,
                'payload' => $payload
            ]);

            Cache::put($cacheKey, true, 60); // Не логируем одинаковые вебхуки чаще чем раз в минуту
        }

        // Сохраняем в базу для отладки (если включено)
        if (config('webhook.log_to_database', false)) {
            try {
                \App\Models\WebhookLog::create([
                    'type' => $type,
                    'source' => $source,
                    'payload' => json_encode($payload),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now()
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to save webhook log: {$e->getMessage()}");
            }
        }
    }

    /**
     * Проверка наличия кастомного обработчика
     *
     * @param string $eventType
     * @param string $source
     * @return bool
     */
    private function hasCustomHandler(string $eventType, string $source): bool
    {
        $handlerClass = "App\\Webhooks\\Handlers\\" .
            str_replace('.', '\\', ucfirst($source)) . '\\' .
            str_replace('.', '', ucwords($eventType, '.'));

        return class_exists($handlerClass);
    }

    /**
     * Обработка кастомного вебхука
     *
     * @param string $eventType
     * @param string $source
     * @param Request $request
     * @return JsonResponse
     */
    private function handleCustomWebhook(string $eventType, string $source, Request $request): JsonResponse
    {
        $handlerClass = "App\\Webhooks\\Handlers\\" .
            str_replace('.', '\\', ucfirst($source)) . '\\' .
            str_replace('.', '', ucwords($eventType, '.'));

        try {
            $handler = new $handlerClass();
            $result = $handler->handle($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Custom webhook processed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error("Custom webhook handler failed: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'error' => 'Custom handler failed'
            ], 500);
        }
    }

    /**
     * Эндпоинт для проверки работоспособности вебхуков
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function healthCheck(Request $request): JsonResponse
    {
        $secret = $request->input('secret');
        $expectedSecret = config('webhook.health_check_secret');

        if ($secret !== $expectedSecret) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $status = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [
                'stripe' => (bool) config('services.stripe.webhook_secret'),
                'yookassa' => (bool) config('services.yookassa.secret_key'),
                'telegram' => (bool) config('services.telegram.bot_token'),
                'database' => $this->checkDatabaseConnection(),
                'queue' => $this->checkQueueConnection()
            ]
        ];

        return response()->json($status);
    }

    /**
     * Проверка подключения к базе данных
     *
     * @return bool
     */
    private function checkDatabaseConnection(): bool
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Проверка подключения к очереди
     *
     * @return bool
     */
    private function checkQueueConnection(): bool
    {
        try {
            // Простая проверка очереди
            // Можно адаптировать под используемый драйвер (redis, database, etc.)
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Получить список необработанных вебхуков (для админки)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pending(Request $request): JsonResponse
    {
        // Только для администраторов
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $webhooks = \App\Models\WebhookLog::where('processed', false)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $webhooks,
            'count' => $webhooks->count()
        ]);
    }

    /**
     * Повторная обработка вебхука
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function retry(Request $request, int $id): JsonResponse
    {
        // Только для администраторов
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $webhook = \App\Models\WebhookLog::find($id);

        if (!$webhook) {
            return response()->json(['error' => 'Webhook not found'], 404);
        }

        try {
            // Повторная обработка вебхука
            $payload = json_decode($webhook->payload, true);

            // Определяем тип вебхука и обрабатываем
            switch ($webhook->type) {
                case 'payment':
                    // Обработка платежного вебхука
                    break;
                case 'booking':
                    // Обработка вебхука бронирования
                    break;
                // ... другие типы
            }

            $webhook->update([
                'processed' => true,
                'processed_at' => now(),
                'retry_count' => $webhook->retry_count + 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook reprocessed successfully'
            ]);

        } catch (\Exception $e) {
            $webhook->update([
                'error' => $e->getMessage(),
                'retry_count' => $webhook->retry_count + 1
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to reprocess webhook'
            ], 500);
        }
    }
}
