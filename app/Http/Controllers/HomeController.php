<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Hotel;
use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function index()
    {
        // Получаем выделенные номера
        $featuredRooms = Room::where('status', 'available')
            ->where('is_featured', true)
            ->when(Schema::hasColumn('rooms', 'sort_order'), function ($query) {
                return $query->orderBy('sort_order');
            }, function ($query) {
                return $query->orderBy('id');
            })
            ->limit(6)
            ->get();

        // Получаем удобства
        $facilities = Facility::where('is_active', true)
            ->when(Schema::hasColumn('facilities', 'sort_order'), function ($query) {
                return $query->orderBy('sort_order');
            }, function ($query) {
                return $query->orderBy('id');
            })
            ->orderBy('name')
            ->limit(8)
            ->get();

        // Получаем активные отели
        $hotels = Hotel::where('status', 'active')
            ->when(Schema::hasColumn('hotels', 'sort_order'), function ($query) {
                return $query->orderBy('sort_order');
            }, function ($query) {
                return $query->orderBy('id');
            })
            ->orderBy('name')
            ->limit(4)
            ->get();

        // ВОТ ПРОБЛЕМА: переменная $rooms не передается в шаблон
        // Нужно добавить:
        $rooms = Room::where('status', 'available')
            ->when(Schema::hasColumn('rooms', 'sort_order'), function ($query) {
                return $query->orderBy('sort_order');
            }, function ($query) {
                return $query->orderBy('created_at', 'desc');
            })
            ->limit(8)
            ->get();

        return view('frontend.home.index', compact(
            'featuredRooms',
            'facilities',
            'hotels',
            'rooms' // Добавляем эту переменную
        ));
    }
}
