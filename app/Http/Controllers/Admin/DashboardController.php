<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Payment;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Общая статистика
        $stats = [
            'total_users' => User::count(),
            'active_bookings' => Booking::whereIn('status', ['pending', 'confirmed'])->count(),
            'total_rooms' => Room::count(),
            'available_rooms' => Room::where('status', 'available')->count(),
            'today_checkins' => Booking::whereDate('check_in', today())->where('status', 'confirmed')->count(),
            'today_checkouts' => Booking::whereDate('check_out', today())->where('status', 'confirmed')->count(),
            'revenue_today' => Payment::whereDate('created_at', today())
                ->where('status', 'completed')
                ->sum('amount'),
            'revenue_month' => Payment::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->where('status', 'completed')
                ->sum('amount'),
        ];

        // Последние бронирования
        $recentBookings = Booking::with(['user', 'room'])
            ->latest()
            ->take(10)
            ->get();

        // Последние пользователи
        $recentUsers = User::latest()
            ->take(10)
            ->get();

        // Статистика по статусам бронирований
        $bookingStatuses = [
            'pending' => Booking::where('status', 'pending')->count(),
            'confirmed' => Booking::where('status', 'confirmed')->count(),
            'cancelled' => Booking::where('status', 'cancelled')->count(),
            'completed' => Booking::where('status', 'completed')->count(),
        ];

        // Доход по месяцам (последние 6 месяцев)
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $revenue = Payment::whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->where('status', 'completed')
                ->sum('amount');

            $monthlyRevenue[$month->format('M Y')] = $revenue;
        }

        return view('admin.dashboard', compact(
            'stats',
            'recentBookings',
            'recentUsers',
            'bookingStatuses',
            'monthlyRevenue'
        ));
    }
}
