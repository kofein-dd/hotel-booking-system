<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\Facility;
use App\Models\Photo;
use App\Models\Video;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class HotelController extends Controller
{
    /**
     * Display hotel settings and information.
     */
    public function index(): View
    {
        if (!Gate::allows('manage-hotel')) {
            abort(403);
        }

        // Получаем или создаем отель (у нас один отель)
        $hotel = Hotel::first();

        if (!$hotel) {
            $hotel = Hotel::create([
                'name' => 'Отель у Моря',
                'slug' => 'hotel-u-morya',
                'status' => 'active',
            ]);
        }

        $hotel->load(['photos', 'videos', 'facilities']);

        // Статистика отеля
        $stats = $this->getHotelStats($hotel);

        return view('admin.hotel.index', compact('hotel', 'stats'));
    }

    /**
     * Show the form for editing hotel information.
     */
    public function edit(Hotel $hotel): View
    {
        if (!Gate::allows('edit-hotel', $hotel)) {
            abort(403);
        }

        $hotel->load(['photos', 'videos', 'facilities']);

        // Все удобства для выбора
        $allFacilities = Facility::all();
        $selectedFacilities = $hotel->facilities->pluck('id')->toArray();

        return view('admin.hotel.edit', compact('hotel', 'allFacilities', 'selectedFacilities'));
    }

    /**
     * Update hotel information.
     */
    public function update(Request $request, Hotel $hotel): RedirectResponse
    {
        if (!Gate::allows('edit-hotel', $hotel)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:hotels,slug,' . $hotel->id,
            'description' => 'required|string|min:10',
            'short_description' => 'nullable|string|max:500',
            'address' => 'required|string|max:500',
            'coordinates' => 'nullable|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'telegram' => 'nullable|string|max:100',
            'check_in_time' => 'required|date_format:H:i',
            'check_out_time' => 'required|date_format:H:i',
            'policy' => 'nullable|string',
            'cancellation_policy' => 'nullable|string',
            'status' => 'required|in:active,maintenance,closed',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:500',
            'facilities' => 'nullable|array',
            'facilities.*' => 'exists:facilities,id',
        ]);

        // Обновляем основную информацию
        $hotel->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'],
            'short_description' => $validated['short_description'],
            'address' => $validated['address'],
            'coordinates' => $validated['coordinates'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'whatsapp' => $validated['whatsapp'],
            'telegram' => $validated['telegram'],
            'check_in_time' => $validated['check_in_time'],
            'check_out_time' => $validated['check_out_time'],
            'policy' => $validated['policy'],
            'cancellation_policy' => $validated['cancellation_policy'],
            'status' => $validated['status'],
            'meta_title' => $validated['meta_title'],
            'meta_description' => $validated['meta_description'],
            'meta_keywords' => $validated['meta_keywords'],
        ]);

        // Синхронизируем удобства
        if (isset($validated['facilities'])) {
            $hotel->facilities()->sync($validated['facilities']);
        } else {
            $hotel->facilities()->detach();
        }

        // Очищаем кэш
        Cache::forget('hotel_info');
        Cache::forget('hotel_facilities');

        return redirect()->route('admin.hotel.index')
            ->with('success', 'Информация об отеле обновлена.');
    }

    /**
     * Upload hotel photos.
     */
    public function uploadPhotos(Request $request, Hotel $hotel): RedirectResponse
    {
        if (!Gate::allows('edit-hotel', $hotel)) {
            abort(403);
        }

        $request->validate([
            'photos' => 'required|array|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB
            'titles' => 'nullable|array',
            'titles.*' => 'nullable|string|max:255',
            'descriptions' => 'nullable|array',
            'descriptions.*' => 'nullable|string|max:500',
            'is_main' => 'nullable|integer', // ID главного фото
        ]);

        $uploadedPhotos = [];

        foreach ($request->file('photos') as $index => $photo) {
            try {
                // Генерируем уникальное имя файла
                $fileName = 'hotel_' . time() . '_' . Str::random(10) . '.' . $photo->getClientOriginalExtension();

                // Сохраняем оригинал
                $path = $photo->storeAs('hotel/photos', $fileName, 'public');

                // Создаем миниатюры
                $this->createThumbnails($photo, $fileName);

                // Создаем запись в базе данных
                $photoRecord = Photo::create([
                    'hotel_id' => $hotel->id,
                    'path' => $path,
                    'title' => $request->titles[$index] ?? null,
                    'description' => $request->descriptions[$index] ?? null,
                    'order' => Photo::where('hotel_id', $hotel->id)->max('order') + 1,
                    'is_main' => false,
                ]);

                $uploadedPhotos[] = $photoRecord;
            } catch (\Exception $e) {
                \Log::error('Failed to upload hotel photo: ' . $e->getMessage());
                continue;
            }
        }

        // Устанавливаем главное фото
        if ($request->has('is_main') && $request->is_main) {
            Photo::where('hotel_id', $hotel->id)->update(['is_main' => false]);
            Photo::where('id', $request->is_main)->update(['is_main' => true]);
        }

        // Очищаем кэш
        Cache::forget('hotel_photos');

        return back()->with('success', 'Фотографии успешно загружены.');
    }

    /**
     * Create thumbnails for uploaded photo.
     */
    private function createThumbnails($photo, $fileName): void
    {
        $sizes = [
            'thumb' => [300, 200],
            'medium' => [800, 600],
            'large' => [1200, 800],
        ];

        $image = Image::make($photo);

        foreach ($sizes as $sizeName => $dimensions) {
            $thumbnailPath = 'hotel/photos/' . $sizeName . '/' . $fileName;

            $image->resize($dimensions[0], $dimensions[1], function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            Storage::disk('public')->put($thumbnailPath, $image->encode());
        }
    }

    /**
     * Update photo information.
     */
    public function updatePhoto(Request $request, Photo $photo): RedirectResponse
    {
        if (!Gate::allows('edit-hotel', $photo->hotel)) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'order' => 'nullable|integer|min:0',
            'is_main' => 'nullable|boolean',
        ]);

        // Если устанавливаем как главное
        if ($request->has('is_main') && $request->is_main) {
            Photo::where('hotel_id', $photo->hotel_id)->update(['is_main' => false]);
            $validated['is_main'] = true;
        }

        $photo->update($validated);

        Cache::forget('hotel_photos');

        return back()->with('success', 'Информация о фотографии обновлена.');
    }

    /**
     * Delete a photo.
     */
    public function deletePhoto(Photo $photo): RedirectResponse
    {
        if (!Gate::allows('edit-hotel', $photo->hotel)) {
            abort(403);
        }

        // Удаляем файлы
        $paths = [
            $photo->path,
            'hotel/photos/thumb/' . basename($photo->path),
            'hotel/photos/medium/' . basename($photo->path),
            'hotel/photos/large/' . basename($photo->path),
        ];

        foreach ($paths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        // Если это было главное фото, устанавливаем новое
        if ($photo->is_main) {
            $newMainPhoto = Photo::where('hotel_id', $photo->hotel_id)
                ->where('id', '!=', $photo->id)
                ->first();

            if ($newMainPhoto) {
                $newMainPhoto->update(['is_main' => true]);
            }
        }

        $photo->delete();

        Cache::forget('hotel_photos');

        return back()->with('success', 'Фотография удалена.');
    }

    /**
     * Reorder photos.
     */
    public function reorderPhotos(Request $request, Hotel $hotel): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('edit-hotel', $hotel)) {
            abort(403);
        }

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'exists:photos,id',
        ]);

        foreach ($request->order as $position => $photoId) {
            Photo::where('id', $photoId)->update(['order' => $position]);
        }

        Cache::forget('hotel_photos');

        return response()->json(['success' => true]);
    }

    /**
     * Upload hotel videos.
     */
    public function uploadVideos(Request $request, Hotel $hotel): RedirectResponse
    {
        if (!Gate::allows('edit-hotel', $hotel)) {
            abort(403);
        }

        $validated = $request->validate([
            'videos' => 'required|array',
            'videos.*.url' => 'required|url|max:500',
            'videos.*.title' => 'nullable|string|max:255',
            'videos.*.description' => 'nullable|string|max:500',
            'videos.*.type' => 'required|in:youtube,vimeo,direct',
            'videos.*.thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        foreach ($validated['videos'] as $videoData) {
            $thumbnailPath = null;

            // Загружаем миниатюру, если есть
            if (isset($videoData['thumbnail']) && $videoData['thumbnail']->isValid()) {
                $thumbnailName = 'video_' . time() . '_' . Str::random(10) . '.' .
                    $videoData['thumbnail']->getClientOriginalExtension();
                $thumbnailPath = $videoData['thumbnail']->storeAs('hotel/videos', $thumbnailName, 'public');
            }

            Video::create([
                'hotel_id' => $hotel->id,
                'url' => $videoData['url'],
                'title' => $videoData['title'] ?? null,
                'description' => $videoData['description'] ?? null,
                'type' => $videoData['type'],
                'thumbnail_path' => $thumbnailPath,
                'order' => Video::where('hotel_id', $hotel->id)->max('order') + 1,
            ]);
        }

        Cache::forget('hotel_videos');

        return back()->with('success', 'Видео успешно добавлены.');
    }

    /**
     * Update video information.
     */
    public function updateVideo(Request $request, Video $video): RedirectResponse
    {
        if (!Gate::allows('edit-hotel', $video->hotel)) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'order' => 'nullable|integer|min:0',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Обновляем миниатюру, если новая загружена
        if ($request->hasFile('thumbnail')) {
            // Удаляем старую миниатюру
            if ($video->thumbnail_path && Storage::disk('public')->exists($video->thumbnail_path)) {
                Storage::disk('public')->delete($video->thumbnail_path);
            }

            $thumbnailName = 'video_' . time() . '_' . Str::random(10) . '.' .
                $request->file('thumbnail')->getClientOriginalExtension();
            $validated['thumbnail_path'] = $request->file('thumbnail')->storeAs('hotel/videos', $thumbnailName, 'public');
        }

        $video->update($validated);

        Cache::forget('hotel_videos');

        return back()->with('success', 'Информация о видео обновлена.');
    }

    /**
     * Delete a video.
     */
    public function deleteVideo(Video $video): RedirectResponse
    {
        if (!Gate::allows('edit-hotel', $video->hotel)) {
            abort(403);
        }

        // Удаляем миниатюру
        if ($video->thumbnail_path && Storage::disk('public')->exists($video->thumbnail_path)) {
            Storage::disk('public')->delete($video->thumbnail_path);
        }

        $video->delete();

        Cache::forget('hotel_videos');

        return back()->with('success', 'Видео удалено.');
    }

    /**
     * Manage hotel facilities.
     */
    public function facilities(): View
    {
        if (!Gate::allows('manage-hotel')) {
            abort(403);
        }

        $facilities = Facility::orderBy('category')->orderBy('order')->get();
        $categories = Facility::distinct()->pluck('category')->toArray();

        $hotel = Hotel::first();
        $hotelFacilities = $hotel ? $hotel->facilities->pluck('id')->toArray() : [];

        return view('admin.hotel.facilities', compact('facilities', 'categories', 'hotelFacilities'));
    }

    /**
     * Create a new facility.
     */
    public function createFacility(Request $request): RedirectResponse
    {
        if (!Gate::allows('manage-hotel')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:facilities,name',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:100',
            'category' => 'required|string|max:100',
            'order' => 'nullable|integer|min:0',
        ]);

        Facility::create($validated);

        Cache::forget('all_facilities');

        return back()->with('success', 'Удобство добавлено.');
    }

    /**
     * Update facility.
     */
    public function updateFacility(Request $request, Facility $facility): RedirectResponse
    {
        if (!Gate::allows('manage-hotel')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:facilities,name,' . $facility->id,
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:100',
            'category' => 'required|string|max:100',
            'order' => 'nullable|integer|min:0',
        ]);

        $facility->update($validated);

        Cache::forget('all_facilities');

        return back()->with('success', 'Удобство обновлено.');
    }

    /**
     * Delete facility.
     */
    public function deleteFacility(Facility $facility): RedirectResponse
    {
        if (!Gate::allows('manage-hotel')) {
            abort(403);
        }

        // Удаляем привязки к отелю
        $facility->hotels()->detach();

        $facility->delete();

        Cache::forget('all_facilities');

        return back()->with('success', 'Удобство удалено.');
    }

    /**
     * Manage homepage notifications/banners.
     */
    public function notifications(): View
    {
        if (!Gate::allows('manage-hotel')) {
            abort(403);
        }

        $notifications = Notification::where('type', 'homepage')
            ->orderBy('start_date', 'desc')
            ->paginate(20);

        return view('admin.hotel.notifications', compact('notifications'));
    }

    /**
     * Create homepage notification.
     */
    public function createNotification(Request $request): RedirectResponse
    {
        if (!Gate::allows('manage-hotel')) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:info,warning,success,danger',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'nullable|boolean',
            'link' => 'nullable|url|max:500',
            'link_text' => 'nullable|string|max:100',
        ]);

        Notification::create([
            'type' => 'homepage',
            'title' => $validated['title'],
            'message' => $validated['message'],
            'notification_type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_active' => $validated['is_active'] ?? true,
            'link' => $validated['link'],
            'link_text' => $validated['link_text'],
        ]);

        Cache::forget('homepage_notifications');

        return back()->with('success', 'Уведомление добавлено.');
    }

    /**
     * Update notification.
     */
    public function updateNotification(Request $request, Notification $notification): RedirectResponse
    {
        if (!Gate::allows('manage-hotel', $notification)) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:info,warning,success,danger',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'nullable|boolean',
            'link' => 'nullable|url|max:500',
            'link_text' => 'nullable|string|max:100',
        ]);

        $notification->update($validated);

        Cache::forget('homepage_notifications');

        return back()->with('success', 'Уведомление обновлено.');
    }

    /**
     * Delete notification.
     */
    public function deleteNotification(Notification $notification): RedirectResponse
    {
        if (!Gate::allows('manage-hotel', $notification)) {
            abort(403);
        }

        $notification->delete();

        Cache::forget('homepage_notifications');

        return back()->with('success', 'Уведомление удалено.');
    }

    /**
     * Get hotel statistics.
     */
    private function getHotelStats(Hotel $hotel): array
    {
        return Cache::remember('hotel_stats_' . $hotel->id, 300, function () use ($hotel) {
            return [
                'total_photos' => $hotel->photos()->count(),
                'total_videos' => $hotel->videos()->count(),
                'total_facilities' => $hotel->facilities()->count(),
                'total_rooms' => $hotel->rooms()->count(),
                'active_rooms' => $hotel->rooms()->where('status', 'active')->count(),
                'active_notifications' => Notification::where('type', 'homepage')
                    ->where('is_active', true)
                    ->where(function ($query) {
                        $query->whereNull('end_date')
                            ->orWhere('end_date', '>=', now());
                    })
                    ->count(),
                'last_updated' => $hotel->updated_at->diffForHumans(),
            ];
        });
    }

    /**
     * Backup hotel data (photos, videos, etc.).
     */
    public function backup(): RedirectResponse
    {
        if (!Gate::allows('manage-hotel')) {
            abort(403);
        }

        try {
            // Создаем архив с данными
            $backupData = [
                'hotel' => Hotel::first()->toArray(),
                'photos' => Photo::all()->toArray(),
                'videos' => Video::all()->toArray(),
                'facilities' => Facility::all()->toArray(),
                'notifications' => Notification::where('type', 'homepage')->get()->toArray(),
            ];

            $fileName = 'hotel_backup_' . date('Y-m-d_H-i-s') . '.json';
            $filePath = storage_path('app/backups/' . $fileName);

            // Создаем директорию, если её нет
            if (!file_exists(storage_path('app/backups'))) {
                mkdir(storage_path('app/backups'), 0755, true);
            }

            file_put_contents($filePath, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return back()->with('success', 'Бэкап создан: ' . $fileName);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Ошибка при создании бэкапа: ' . $e->getMessage()]);
        }
    }

    /**
     * Clear all caches related to hotel.
     */
    public function clearCache(): RedirectResponse
    {
        if (!Gate::allows('manage-hotel')) {
            abort(403);
        }

        $caches = [
            'hotel_info',
            'hotel_photos',
            'hotel_videos',
            'hotel_facilities',
            'all_facilities',
            'homepage_notifications',
            'hotel_stats_' . Hotel::first()->id,
        ];

        foreach ($caches as $cache) {
            Cache::forget($cache);
        }

        return back()->with('success', 'Кэш очищен.');
    }

    /**
     * Manage SEO settings.
     */
    public function seo(): View
    {
        if (!Gate::allows('manage-hotel')) {
            abort(403);
        }

        $hotel = Hotel::first();
        $pages = [
            'home' => 'Главная страница',
            'rooms' => 'Номера',
            'about' => 'Об отеле',
            'contact' => 'Контакты',
            'booking' => 'Бронирование',
        ];

        return view('admin.hotel.seo', compact('hotel', 'pages'));
    }

    /**
     * Update SEO settings.
     */
    public function updateSeo(Request $request, Hotel $hotel): RedirectResponse
    {
        if (!Gate::allows('edit-hotel', $hotel)) {
            abort(403);
        }

        $validated = $request->validate([
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:500',
            'og_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'seo_title' => 'nullable|string|max:255',
            'seo_text' => 'nullable|string',
            'structured_data' => 'nullable|json',
        ]);

        // Обновляем OG-изображение
        if ($request->hasFile('og_image')) {
            // Удаляем старое изображение
            if ($hotel->og_image && Storage::disk('public')->exists($hotel->og_image)) {
                Storage::disk('public')->delete($hotel->og_image);
            }

            $ogImageName = 'og_' . time() . '.' . $request->file('og_image')->getClientOriginalExtension();
            $validated['og_image'] = $request->file('og_image')->storeAs('hotel/seo', $ogImageName, 'public');
        }

        $hotel->update($validated);

        Cache::forget('hotel_seo');

        return back()->with('success', 'SEO настройки обновлены.');
    }

    /**
     * Manage social media links.
     */
    public function social(): View
    {
        if (!Gate::allows('manage-hotel')) {
            abort(403);
        }

        $hotel = Hotel::first();

        return view('admin.hotel.social', compact('hotel'));
    }

    /**
     * Update social media links.
     */
    public function updateSocial(Request $request, Hotel $hotel): RedirectResponse
    {
        if (!Gate::allows('edit-hotel', $hotel)) {
            abort(403);
        }

        $validated = $request->validate([
            'facebook' => 'nullable|url|max:500',
            'instagram' => 'nullable|url|max:500',
            'twitter' => 'nullable|url|max:500',
            'youtube' => 'nullable|url|max:500',
            'vk' => 'nullable|url|max:500',
            'tiktok' => 'nullable|url|max:500',
            'telegram_channel' => 'nullable|url|max:500',
        ]);

        $hotel->update($validated);

        Cache::forget('hotel_social');

        return back()->with('success', 'Социальные сети обновлены.');
    }

    /**
     * Get hotel settings for API.
     */
    public function apiSettings()
    {
        $hotel = Hotel::first();

        if (!$hotel) {
            return response()->json(['error' => 'Hotel not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'hotel' => $hotel->only(['name', 'description', 'address', 'email', 'phone', 'check_in_time', 'check_out_time']),
                'social' => $hotel->only(['facebook', 'instagram', 'twitter', 'youtube', 'vk', 'telegram']),
                'photos' => $hotel->photos()->where('is_main', true)->first()?->path,
            ]
        ]);
    }
}
