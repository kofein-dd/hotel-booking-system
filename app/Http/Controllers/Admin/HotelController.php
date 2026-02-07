<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HotelController extends Controller
{
    public function index()
    {
        $hotels = Hotel::all();
        return view('admin.hotels.index', compact('hotels'));
    }

    public function edit(Hotel $hotel)
    {
        return view('admin.hotels.edit', compact('hotel'));
    }

    public function update(Request $request, Hotel $hotel)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'contact_info' => 'required|string',
            'coordinates' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        // Обработка фото
        if ($request->hasFile('photos')) {
            $photos = [];
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('hotel/photos', 'public');
                $photos[] = $path;
            }
            $validated['photos'] = array_merge($hotel->photos ?? [], $photos);
        }

        // Обработка видео
        if ($request->hasFile('videos')) {
            $videos = [];
            foreach ($request->file('videos') as $video) {
                $path = $video->store('hotel/videos', 'public');
                $videos[] = $path;
            }
            $validated['videos'] = array_merge($hotel->videos ?? [], $videos);
        }

        $hotel->update($validated);

        return redirect()->route('admin.hotels.index')
            ->with('success', 'Информация об отеле обновлена');
    }

    public function removePhoto(Hotel $hotel, $photoIndex)
    {
        $photos = $hotel->photos;
        if (isset($photos[$photoIndex])) {
            Storage::disk('public')->delete($photos[$photoIndex]);
            unset($photos[$photoIndex]);
            $hotel->update(['photos' => array_values($photos)]);
        }

        return redirect()->back()->with('success', 'Фото удалено');
    }

    public function removeVideo(Hotel $hotel, $videoIndex)
    {
        $videos = $hotel->videos;
        if (isset($videos[$videoIndex])) {
            Storage::disk('public')->delete($videos[$videoIndex]);
            unset($videos[$videoIndex]);
            $hotel->update(['videos' => array_values($videos)]);
        }

        return redirect()->back()->with('success', 'Видео удалено');
    }
}
