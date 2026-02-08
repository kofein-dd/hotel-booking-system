<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Http\Requests\Facility\StoreFacilityRequest;
use App\Http\Requests\Facility\UpdateFacilityRequest;
use Illuminate\Http\Request;

class FacilityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Facility::query();

        // Фильтрация по типу
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Фильтрация по активности
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Поиск по названию
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('description', 'like', '%' . $request->search . '%');
        }

        $facilities = $query->ordered()->paginate(20);

        $types = [
            'general' => 'Общие',
            'room' => 'Для номеров',
            'hotel' => 'Для отеля',
            'bathroom' => 'Ванная комната',
            'kitchen' => 'Кухня',
        ];

        return view('admin.facilities.index', compact('facilities', 'types'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $types = [
            'general' => 'Общие',
            'room' => 'Для номеров',
            'hotel' => 'Для отеля',
            'bathroom' => 'Ванная комната',
            'kitchen' => 'Кухня',
        ];

        return view('admin.facilities.create', compact('types'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFacilityRequest $request)
    {
        $facility = Facility::create($request->validated());

        return redirect()->route('admin.facilities.index')
            ->with('success', 'Удобство успешно создано.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Facility $facility)
    {
        return view('admin.facilities.show', compact('facility'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Facility $facility)
    {
        $types = [
            'general' => 'Общие',
            'room' => 'Для номеров',
            'hotel' => 'Для отеля',
            'bathroom' => 'Ванная комната',
            'kitchen' => 'Кухня',
        ];

        return view('admin.facilities.edit', compact('facility', 'types'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFacilityRequest $request, Facility $facility)
    {
        $facility->update($request->validated());

        return redirect()->route('admin.facilities.index')
            ->with('success', 'Удобство успешно обновлено.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Facility $facility)
    {
        $facility->delete();

        return redirect()->route('admin.facilities.index')
            ->with('success', 'Удобство успешно удалено.');
    }

    /**
     * Восстановить удаленное удобство.
     */
    public function restore($id)
    {
        $facility = Facility::withTrashed()->findOrFail($id);
        $facility->restore();

        return redirect()->route('admin.facilities.index')
            ->with('success', 'Удобство успешно восстановлено.');
    }

    /**
     * Полностью удалить удобство.
     */
    public function forceDelete($id)
    {
        $facility = Facility::withTrashed()->findOrFail($id);
        $facility->forceDelete();

        return redirect()->route('admin.facilities.index')
            ->with('success', 'Удобство полностью удалено.');
    }
}
