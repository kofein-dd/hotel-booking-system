<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Payment system configurations
     */
    protected $paymentSystems = [
        'stripe' => [
            'name' => 'Stripe',
            'currencies' => ['USD', 'EUR', 'GBP'],
            'commission' => 2.9,
        ],
        'yookassa' => [
            'name' => 'ЮKassa',
            'currencies' => ['RUB'],
            'commission' => 3.5,
        ],
        'paypal' => [
            'name' => 'PayPal',
            'currencies' => ['USD', 'EUR'],
            'commission' => 3.4,
        ],
        'tinkoff' => [
            'name' => 'Тинькофф',
            'currencies' => ['RUB'],
            'commission' => 2.5,
        ],
        'sberbank' => [
            'name' => 'Сбербанк',
            'currencies' => ['RUB'],
            'commission' => 2.0,
        ],
    ];

    /**
     * Display a listing of payments.
     */
    public function index(Request $request): View
    {
        if (!Gate::allows('manage-payments')) {
            abort(403);
        }

        $query = Payment::with(['booking.user', 'booking.room']);

        // Фильтры
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_system')) {
            $query->where('payment_system', $request->payment_system);
        }

        if ($request->filled('user_id')) {
            $query->whereHas('booking.user', function ($q) use ($request) {
                $q->where('id', $request->user_id);
            });
        }

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->booking_id);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('amount_from')) {
            $query->where('amount', '>=', $request->amount_from);
        }

        if ($request->filled('amount_to')) {
            $query->where('amount', '<=', $request->amount_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('booking.user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        // Сортировка
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        if (in_array($sortField, ['amount', 'created_at', 'payment_date'])) {
            $query->orderBy($sortField, $sortDirection);
        }

        $payments = $query->paginate(30);

        $users = User::where('role', 'user')->get(['id', 'name', 'email']);
        $statuses = ['pending', 'completed', 'failed', 'refunded', 'partially_refunded', 'cancelled'];
        $paymentSystems = array_keys($this->paymentSystems);

        // Суммарная статистика для отображения
        $totalAmount = $payments->sum('amount');
        $completedAmount = $payments->where('status', 'completed')->sum('amount');
        $pendingAmount = $payments->where('status', 'pending')->sum('amount');

        return view('admin.payments.index', compact(
            'payments',
            'users',
            'statuses',
            'paymentSystems',
            'totalAmount',
            'completedAmount',
            'pendingAmount'
        ));
    }

    /**
     * Display the specified payment.
     */
    public function show(Payment $payment): View
    {
        if (!Gate::allows('view-payment', $payment)) {
            abort(403);
        }

        $payment->load([
            'booking.user',
            'booking.room',
            'refunds'
        ]);

        // Детали платежной системы
        $paymentSystemInfo = $this->paymentSystems[$payment->payment_system] ?? null;

        // История статусов
        $statusHistory = $payment->status_history ?? [];

        return view('admin.payments.show', compact('payment', 'paymentSystemInfo', 'statusHistory'));
    }

    /**
     * Process manual payment (for admin).
     */
    public function createManual(Request $request): View|RedirectResponse
    {
        if (!Gate::allows('create-payments')) {
            abort(403);
        }

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'booking_id' => 'required|exists:bookings,id',
                'amount' => 'required|numeric|min:0.01',
                'payment_system' => 'required|in:' . implode(',', array_keys($this->paymentSystems)),
                'currency' => 'required|string|size:3',
                'description' => 'nullable|string|max:500',
                'payment_date' => 'nullable|date',
                'is_test' => 'nullable|boolean',
            ]);

            $booking = Booking::findOrFail($validated['booking_id']);

            // Проверяем, не оплачен ли уже заказ
            if ($booking->payments()->where('status', 'completed')->exists()) {
                return back()->withErrors(['booking_id' => 'Это бронирование уже оплачено.']);
            }

            // Создаем платеж
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'amount' => $validated['amount'],
                'payment_system' => $validated['payment_system'],
                'currency' => $validated['currency'],
                'description' => $validated['description'],
                'payment_date' => $validated['payment_date'] ?? now(),
                'status' => 'completed',
                'transaction_id' => 'MANUAL_' . time() . '_' . Str::random(8),
                'is_manual' => true,
                'is_test' => $validated['is_test'] ?? false,
                'admin_id' => auth()->guard('admin')->id(),
                'status_history' => [[
                    'status' => 'completed',
                    'timestamp' => now()->toDateTimeString(),
                    'admin_id' => auth()->guard('admin')->id(),
                    'note' => 'Ручной платеж',
                ]],
            ]);

            // Обновляем статус бронирования
            $booking->update(['payment_status' => 'paid']);

            // Отправляем уведомление пользователю
            // Notification::send($booking->user, new PaymentCompleted($payment));

            return redirect()->route('admin.payments.show', $payment)
                ->with('success', 'Платеж успешно создан.');
        }

        $bookings = Booking::whereDoesntHave('payments', function ($query) {
            $query->where('status', 'completed');
        })
            ->with(['user', 'room'])
            ->whereIn('status', ['confirmed', 'pending'])
            ->latest()
            ->limit(50)
            ->get();

        $currencies = ['RUB', 'USD', 'EUR', 'GBP'];

        return view('admin.payments.create-manual', compact('bookings', 'currencies'));
    }

    /**
     * Process refund for payment.
     */
    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        if (!Gate::allows('refund-payments')) {
            abort(403);
        }

        if ($payment->status !== 'completed') {
            return back()->withErrors(['error' => 'Можно вернуть только завершенные платежи.']);
        }

        if ($payment->refunded_amount >= $payment->amount) {
            return back()->withErrors(['error' => 'Платеж уже полностью возвращен.']);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . ($payment->amount - $payment->refunded_amount),
            'reason' => 'required|string|max:500',
            'refund_type' => 'required|in:full,partial',
            'notify_user' => 'nullable|boolean',
        ]);

        try {
            // Если платеж через внешнюю систему - вызываем API возврата
            if (!$payment->is_manual && $payment->payment_system !== 'cash') {
                $refundResult = $this->processExternalRefund($payment, $validated['amount']);

                if (!$refundResult['success']) {
                    throw new \Exception($refundResult['message']);
                }

                $refundId = $refundResult['refund_id'];
            } else {
                $refundId = 'MANUAL_REFUND_' . time() . '_' . Str::random(8);
            }

            // Создаем запись о возврате
            $refund = $payment->refunds()->create([
                'refund_id' => $refundId,
                'amount' => $validated['amount'],
                'reason' => $validated['reason'],
                'status' => 'completed',
                'processed_by' => auth()->guard('admin')->id(),
                'metadata' => [
                    'refund_type' => $validated['refund_type'],
                    'original_payment' => $payment->transaction_id,
                ],
            ]);

            // Обновляем платеж
            $newRefundedAmount = $payment->refunded_amount + $validated['amount'];
            $newStatus = $newRefundedAmount >= $payment->amount ? 'refunded' : 'partially_refunded';

            $payment->update([
                'refunded_amount' => $newRefundedAmount,
                'status' => $newStatus,
                'status_history' => array_merge(
                    $payment->status_history ?? [],
                    [[
                        'status' => $newStatus,
                        'timestamp' => now()->toDateTimeString(),
                        'admin_id' => auth()->guard('admin')->id(),
                        'note' => 'Возврат средств: ' . $validated['reason'],
                        'refund_id' => $refund->id,
                    ]]
                ),
            ]);

            // Обновляем статус бронирования если нужно
            if ($newStatus === 'refunded') {
                $payment->booking->update(['payment_status' => 'refunded']);
            }

            // Отправляем уведомление пользователю
            if ($validated['notify_user'] ?? true) {
                // Notification::send($payment->user, new PaymentRefunded($refund));
            }

            return back()->with('success', 'Возврат успешно выполнен.');
        } catch (\Exception $e) {
            Log::error('Refund failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Ошибка возврата: ' . $e->getMessage()]);
        }
    }

    /**
     * Process refund through external payment system.
     */
    private function processExternalRefund(Payment $payment, float $amount): array
    {
        switch ($payment->payment_system) {
            case 'stripe':
                return $this->processStripeRefund($payment, $amount);

            case 'yookassa':
                return $this->processYooKassaRefund($payment, $amount);

            case 'paypal':
                return $this->processPayPalRefund($payment, $amount);

            default:
                return [
                    'success' => false,
                    'message' => 'Автоматический возврат для этой платежной системы не поддерживается.',
                ];
        }
    }

    /**
     * Process Stripe refund.
     */
    private function processStripeRefund(Payment $payment, float $amount): array
    {
        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            $refund = $stripe->refunds->create([
                'payment_intent' => $payment->transaction_id,
                'amount' => (int)($amount * 100), // Stripe работает в центах
                'reason' => 'requested_by_customer',
            ]);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'message' => 'Возврат успешно создан в Stripe.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Stripe error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process YooKassa refund.
     */
    private function processYooKassaRefund(Payment $payment, float $amount): array
    {
        try {
            $shopId = config('services.yookassa.shop_id');
            $secretKey = config('services.yookassa.secret_key');

            $response = Http::withBasicAuth($shopId, $secretKey)
                ->post('https://api.yookassa.ru/v3/refunds', [
                    'payment_id' => $payment->transaction_id,
                    'amount' => [
                        'value' => number_format($amount, 2, '.', ''),
                        'currency' => $payment->currency,
                    ],
                    'description' => 'Возврат по запросу администратора',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'refund_id' => $data['id'],
                    'message' => 'Возврат успешно создан в ЮKassa.',
                ];
            }

            return [
                'success' => false,
                'message' => 'YooKassa error: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'YooKassa error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Mark payment as failed.
     */
    public function markAsFailed(Request $request, Payment $payment): RedirectResponse
    {
        if (!Gate::allows('update-payments')) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $payment->update([
            'status' => 'failed',
            'status_history' => array_merge(
                $payment->status_history ?? [],
                [[
                    'status' => 'failed',
                    'timestamp' => now()->toDateTimeString(),
                    'admin_id' => auth()->guard('admin')->id(),
                    'note' => $validated['reason'],
                ]]
            ),
        ]);

        // Обновляем статус бронирования
        $payment->booking->update(['payment_status' => 'failed']);

        return back()->with('success', 'Платеж помечен как неудачный.');
    }

    /**
     * Retry failed payment.
     */
    public function retry(Payment $payment): RedirectResponse
    {
        if (!Gate::allows('create-payments')) {
            abort(403);
        }

        if ($payment->status !== 'failed') {
            return back()->withErrors(['error' => 'Можно повторить только неудачные платежи.']);
        }

        // Создаем новый платеж на основе старого
        $newPayment = $payment->replicate();
        $newPayment->transaction_id = 'RETRY_' . time() . '_' . Str::random(8);
        $newPayment->status = 'pending';
        $newPayment->status_history = [[
            'status' => 'pending',
            'timestamp' => now()->toDateTimeString(),
            'note' => 'Повторная попытка оплаты',
        ]];
        $newPayment->save();

        // Генерируем ссылку для оплаты
        $paymentUrl = $this->generatePaymentLink($newPayment);

        // Отправляем уведомление пользователю с ссылкой
        // Notification::send($payment->user, new PaymentRetry($newPayment, $paymentUrl));

        return back()->with('success', 'Новая попытка оплаты создана. Ссылка: ' . $paymentUrl);
    }

    /**
     * Generate payment link for external systems.
     */
    private function generatePaymentLink(Payment $payment): string
    {
        // Здесь должна быть логика генерации ссылки для каждой платежной системы
        return route('payment.process', ['payment' => $payment->id]);
    }

    /**
     * Get payment statistics.
     */
    public function statistics(Request $request): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        // Фильтры
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Общая статистика
        $totalStats = Payment::select(
            DB::raw('COUNT(*) as total_count'),
            DB::raw('SUM(amount) as total_amount'),
            DB::raw('AVG(amount) as avg_amount')
        )
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->first();

        // Статистика по статусам
        $statusStats = Payment::select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as amount'))
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('status')
            ->get();

        // Статистика по платежным системам
        $systemStats = Payment::select('payment_system', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as amount'))
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->where('status', 'completed')
            ->groupBy('payment_system')
            ->get();

        // Ежедневная статистика (последние 30 дней)
        $dailyStats = Payment::where('status', 'completed')
            ->select(
                DB::raw('DATE(payment_date) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as amount'),
                DB::raw('AVG(amount) as avg_amount')
            )
            ->whereBetween('payment_date', [now()->subDays(30), now()])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Статистика по времени суток
        $hourlyStats = Payment::where('status', 'completed')
            ->select(
                DB::raw('HOUR(payment_date) as hour'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as amount')
            )
            ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Самые крупные платежи
        $largestPayments = Payment::with(['booking.user', 'booking.room'])
            ->where('status', 'completed')
            ->orderBy('amount', 'desc')
            ->limit(10)
            ->get();

        // Статистика конверсии
        $conversionStats = $this->calculateConversionStats($dateFrom, $dateTo);

        return view('admin.payments.statistics', compact(
            'totalStats',
            'statusStats',
            'systemStats',
            'dailyStats',
            'hourlyStats',
            'largestPayments',
            'conversionStats',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Calculate payment conversion statistics.
     */
    private function calculateConversionStats(string $dateFrom, string $dateTo): array
    {
        // Количество созданных бронирований
        $totalBookings = Booking::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])->count();

        // Количество бронирований с оплатой
        $paidBookings = Booking::whereHas('payments', function ($query) use ($dateFrom, $dateTo) {
            $query->where('status', 'completed')
                ->whereBetween('payment_date', [$dateFrom, $dateTo . ' 23:59:59']);
        })
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->count();

        // Количество неудачных платежей
        $failedPayments = Payment::where('status', 'failed')
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->count();

        // Среднее время оплаты
        $avgPaymentTime = Payment::where('status', 'completed')
            ->whereNotNull('booking_id')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, bookings.created_at, payments.payment_date)) as avg_minutes'))
            ->whereBetween('payments.payment_date', [$dateFrom, $dateTo . ' 23:59:59'])
            ->first()
            ->avg_minutes ?? 0;

        return [
            'total_bookings' => $totalBookings,
            'paid_bookings' => $paidBookings,
            'conversion_rate' => $totalBookings > 0 ? round(($paidBookings / $totalBookings) * 100, 2) : 0,
            'failed_payments' => $failedPayments,
            'avg_payment_time' => round($avgPaymentTime, 2),
        ];
    }

    /**
     * Export payments to CSV.
     */
    public function export(Request $request)
    {
        if (!Gate::allows('export-payments')) {
            abort(403);
        }

        $payments = Payment::with(['booking.user', 'booking.room'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('payment_system'), function ($query) use ($request) {
                $query->where('payment_system', $request->payment_system);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->where('payment_date', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->where('payment_date', '<=', $request->date_to . ' 23:59:59');
            })
            ->orderBy('payment_date', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="payments_' . date('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function() use ($payments) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM для корректного отображения кириллицы в Excel
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'ID платежа',
                'ID бронирования',
                'Пользователь',
                'Email',
                'Номер',
                'Сумма',
                'Валюта',
                'Платежная система',
                'Статус',
                'ID транзакции',
                'Дата платежа',
                'Возвращено',
                'Комиссия',
                'Создан',
            ], ';');

            foreach ($payments as $payment) {
                fputcsv($file, [
                    $payment->id,
                    $payment->booking_id,
                    $payment->booking->user->name ?? 'N/A',
                    $payment->booking->user->email ?? 'N/A',
                    $payment->booking->room->name ?? 'N/A',
                    $payment->amount,
                    $payment->currency,
                    $this->paymentSystems[$payment->payment_system]['name'] ?? $payment->payment_system,
                    $this->getStatusName($payment->status),
                    $payment->transaction_id,
                    $payment->payment_date->format('d.m.Y H:i'),
                    $payment->refunded_amount,
                    $this->calculateCommission($payment),
                    $payment->created_at->format('d.m.Y H:i'),
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get payment status name in Russian.
     */
    private function getStatusName(string $status): string
    {
        return match($status) {
            'pending' => 'Ожидает',
            'completed' => 'Завершен',
            'failed' => 'Неудачный',
            'refunded' => 'Возвращен',
            'partially_refunded' => 'Частично возвращен',
            'cancelled' => 'Отменен',
            default => $status,
        };
    }

    /**
     * Calculate commission for payment.
     */
    private function calculateCommission(Payment $payment): float
    {
        $system = $this->paymentSystems[$payment->payment_system] ?? null;

        if (!$system || $payment->status !== 'completed') {
            return 0;
        }

        $commission = $system['commission'] ?? 0;
        return round($payment->amount * ($commission / 100), 2);
    }

    /**
     * Process webhook from payment system.
     */
    public function webhook(Request $request, string $system)
    {
        // Валидация вебхука
        if (!$this->validateWebhook($request, $system)) {
            Log::warning('Invalid webhook signature', ['system' => $system]);
            abort(400, 'Invalid signature');
        }

        $payload = $request->all();

        try {
            switch ($system) {
                case 'stripe':
                    $this->handleStripeWebhook($payload);
                    break;

                case 'yookassa':
                    $this->handleYooKassaWebhook($payload);
                    break;

                case 'paypal':
                    $this->handlePayPalWebhook($payload);
                    break;

                default:
                    Log::warning('Unsupported payment system webhook', ['system' => $system]);
                    abort(400, 'Unsupported system');
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed: ' . $e->getMessage(), [
                'system' => $system,
                'payload' => $payload,
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Validate webhook signature.
     */
    private function validateWebhook(Request $request, string $system): bool
    {
        switch ($system) {
            case 'stripe':
                $signature = $request->header('Stripe-Signature');
                $secret = config('services.stripe.webhook_secret');
                return $this->validateStripeWebhook($request->getContent(), $signature, $secret);

            case 'yookassa':
                // ЮKassa использует IP-фильтрацию и базовую аутентификацию
                return true;

            default:
                return true;
        }
    }

    /**
     * Handle Stripe webhook.
     */
    private function handleStripeWebhook(array $payload): void
    {
        $eventType = $payload['type'];

        switch ($eventType) {
            case 'payment_intent.succeeded':
                $this->processStripePaymentSuccess($payload['data']['object']);
                break;

            case 'payment_intent.payment_failed':
                $this->processStripePaymentFailure($payload['data']['object']);
                break;

            case 'charge.refunded':
                $this->processStripeRefund($payload['data']['object']);
                break;
        }
    }

    /**
     * Process successful Stripe payment.
     */
    private function processStripePaymentSuccess(array $paymentIntent): void
    {
        $transactionId = $paymentIntent['id'];
        $amount = $paymentIntent['amount'] / 100; // Конвертируем из центов

        $payment = Payment::where('transaction_id', $transactionId)->first();

        if ($payment) {
            $payment->update([
                'status' => 'completed',
                'payment_date' => now(),
                'status_history' => array_merge(
                    $payment->status_history ?? [],
                    [[
                        'status' => 'completed',
                        'timestamp' => now()->toDateTimeString(),
                        'note' => 'Webhook от Stripe',
                    ]]
                ),
            ]);

            // Обновляем статус бронирования
            $payment->booking->update(['payment_status' => 'paid']);
        }
    }

    /**
     * Handle YooKassa webhook.
     */
    private function handleYooKassaWebhook(array $payload): void
    {
        $event = $payload['event'];

        switch ($event) {
            case 'payment.succeeded':
                $this->processYooKassaPaymentSuccess($payload['object']);
                break;

            case 'payment.canceled':
                $this->processYooKassaPaymentCanceled($payload['object']);
                break;

            case 'refund.succeeded':
                $this->processYooKassaRefundSuccess($payload['object']);
                break;
        }
    }

    /**
     * Dashboard widget data.
     */
    public function dashboardWidget(): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('view-dashboard')) {
            abort(403);
        }

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayPayments = Payment::whereDate('payment_date', $today)
            ->where('status', 'completed')
            ->sum('amount');

        $yesterdayPayments = Payment::whereDate('payment_date', $yesterday)
            ->where('status', 'completed')
            ->sum('amount');

        $pendingPayments = Payment::where('status', 'pending')->count();
        $failedToday = Payment::whereDate('created_at', $today)
            ->where('status', 'failed')
            ->count();

        return response()->json([
            'today_amount' => $todayPayments,
            'yesterday_amount' => $yesterdayPayments,
            'growth_percent' => $yesterdayPayments > 0
                ? round((($todayPayments - $yesterdayPayments) / $yesterdayPayments) * 100, 2)
                : 0,
            'pending_count' => $pendingPayments,
            'failed_today' => $failedToday,
        ]);
    }

    /**
     * Get payment systems configuration.
     */
    public function getPaymentSystems(): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('manage-payments')) {
            abort(403);
        }

        $systems = [];
        foreach ($this->paymentSystems as $key => $system) {
            $systems[] = [
                'id' => $key,
                'name' => $system['name'],
                'currencies' => $system['currencies'],
                'commission' => $system['commission'],
                'is_active' => config("services.{$key}.enabled", false),
            ];
        }

        return response()->json([
            'success' => true,
            'systems' => $systems,
        ]);
    }
}
