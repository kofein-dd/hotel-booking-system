<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\PaymentRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->middleware('auth');
        $this->paymentService = $paymentService;
    }

    /**
     * Страница выбора метода оплаты для бронирования
     *
     * @param Booking $booking
     * @return View|RedirectResponse
     */
    public function create(Booking $booking)
    {
        // Проверка, что бронирование принадлежит текущему пользователю
        if ($booking->user_id !== Auth::id()) {
            abort(403, 'У вас нет доступа к этой оплате');
        }

        // Проверка статуса бронирования
        if ($booking->status !== 'pending') {
            return redirect()
                ->route('frontend.bookings.show', $booking)
                ->with('error', 'Это бронирование нельзя оплатить');
        }

        // Проверка, не оплачено ли уже
        if ($booking->payment && $booking->payment->status === 'completed') {
            return redirect()
                ->route('frontend.bookings.show', $booking)
                ->with('info', 'Это бронирование уже оплачено');
        }

        $availableMethods = $this->paymentService->getAvailableMethods();

        return view('frontend.payments.create', compact('booking', 'availableMethods'));
    }

    /**
     * Инициирование платежа
     *
     * @param PaymentRequest $request
     * @param Booking $booking
     * @return JsonResponse|RedirectResponse
     */
    public function store(PaymentRequest $request, Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'error' => 'Бронирование недоступно для оплаты'
            ], 400);
        }

        $paymentMethod = $request->input('payment_method');

        try {
            $paymentResult = $this->paymentService->createPayment(
                $booking,
                $paymentMethod,
                $request->ip()
            );

            // Если это онлайн-платеж с перенаправлением
            if (isset($paymentResult['redirect_url'])) {
                return response()->json([
                    'redirect_url' => $paymentResult['redirect_url']
                ]);
            }

            // Если оплата прошла мгновенно (например, карта)
            if ($paymentResult['status'] === 'completed') {
                return redirect()
                    ->route('frontend.bookings.show', $booking)
                    ->with('success', 'Оплата прошла успешно!');
            }

            // Ожидание подтверждения
            return redirect()
                ->route('frontend.payments.pending', $booking)
                ->with('info', 'Ожидаем подтверждение платежа');

        } catch (\Exception $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', 'Ошибка при создании платежа: ' . $e->getMessage());
        }
    }

    /**
     * Страница успешной оплаты
     *
     * @param Booking $booking
     * @return View|RedirectResponse
     */
    public function success(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }

        $payment = $booking->payment;

        if (!$payment || $payment->status !== 'completed') {
            return redirect()
                ->route('frontend.bookings.show', $booking)
                ->with('warning', 'Платеж не найден или еще не завершен');
        }

        return view('frontend.payments.success', compact('booking', 'payment'));
    }

    /**
     * Страница неуспешной оплаты
     *
     * @param Booking $booking
     * @return View|RedirectResponse
     */
    public function failure(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }

        $payment = $booking->payment;

        if (!$payment || $payment->status === 'completed') {
            return redirect()
                ->route('frontend.bookings.show', $booking);
        }

        return view('frontend.payments.failure', compact('booking', 'payment'));
    }

    /**
     * Страница ожидания подтверждения платежа
     *
     * @param Booking $booking
     * @return View|RedirectResponse
     */
    public function pending(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }

        $payment = $booking->payment;

        if (!$payment || !in_array($payment->status, ['pending', 'processing'])) {
            return redirect()
                ->route('frontend.bookings.show', $booking);
        }

        // Для некоторых платежных систем можно проверить статус
        if ($this->paymentService->shouldPollStatus($payment)) {
            $checkStatusUrl = route('frontend.payments.status', $booking);
            return view('frontend.payments.pending', compact('booking', 'payment', 'checkStatusUrl'));
        }

        return view('frontend.payments.pending', compact('booking', 'payment'));
    }

    /**
     * Проверка статуса платежа (для AJAX запросов)
     *
     * @param Booking $booking
     * @return JsonResponse
     */
    public function status(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payment = $booking->payment;

        if (!$payment) {
            return response()->json([
                'status' => 'not_found'
            ]);
        }

        // Обновляем статус из платежной системы
        $updatedPayment = $this->paymentService->checkPaymentStatus($payment);

        return response()->json([
            'status' => $updatedPayment->status,
            'redirect_url' => null
        ]);
    }

    /**
     * История платежей пользователя
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request)
    {
        $payments = Payment::whereHas('booking', function ($query) {
            $query->where('user_id', Auth::id());
        })
            ->with(['booking.room'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        if ($request->wantsJson()) {
            return response()->json($payments);
        }

        return view('frontend.payments.index', compact('payments'));
    }

    /**
     * Детали платежа
     *
     * @param Payment $payment
     * @return View|RedirectResponse
     */
    public function show(Payment $payment)
    {
        if ($payment->booking->user_id !== Auth::id()) {
            abort(403);
        }

        $payment->load(['booking.room.hotel', 'refunds']);

        return view('frontend.payments.show', compact('payment'));
    }

    /**
     * Повторная попытка оплаты
     *
     * @param Booking $booking
     * @return RedirectResponse
     */
    public function retry(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }

        // Проверяем, есть ли неудачный платеж
        if (!$booking->payment || $booking->payment->status !== 'failed') {
            return redirect()
                ->route('frontend.bookings.show', $booking)
                ->with('error', 'Нельзя повторить этот платеж');
        }

        // Удаляем старый неудачный платеж
        $booking->payment()->delete();

        return redirect()
            ->route('frontend.payments.create', $booking)
            ->with('info', 'Попробуйте оплатить снова');
    }
}
