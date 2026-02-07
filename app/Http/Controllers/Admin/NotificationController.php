<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::with('user')
            ->latest()
            ->paginate(20);

        return view('admin.notifications.index', compact('notifications'));
    }

    public function create()
    {
        $users = User::where('status', 'active')->get();
        return view('admin.notifications.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:info,warning,reminder,promo',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'send_to' => 'required|in:all,selected,new_users',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'scheduled_at' => 'nullable|date|after:now',
            'is_urgent' => 'boolean',
        ]);

        // Логика отправки уведомлений
        if ($validated['send_to'] === 'all') {
            $users = User::where('status', 'active')->get();
        } elseif ($validated['send_to'] === 'selected') {
            $users = User::whereIn('id', $validated['user_ids'] ?? [])->get();
        } elseif ($validated['send_to'] === 'new_users') {
            $users = User::where('status', 'active')
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->get();
        }

        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => $validated['type'],
                'title' => $validated['title'],
                'message' => $validated['message'],
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'is_urgent' => $validated['is_urgent'] ?? false,
            ]);
        }

        return redirect()->route('admin.notifications.index')
            ->with('success', 'Уведомления отправлены');
    }

    public function destroy(Notification $notification)
    {
        $notification->delete();
        return redirect()->route('admin.notifications.index')
            ->with('success', 'Уведомление удалено');
    }

    public function sendNow(Notification $notification)
    {
        $notification->update([
            'scheduled_at' => null,
            'sent_at' => now()
        ]);

        // Здесь можно добавить отправку через email/telegram

        return redirect()->back()
            ->with('success', 'Уведомление отправлено немедленно');
    }
}
