<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * Показать список всех настроек с группировкой по категориям
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request)
    {
        $categories = Setting::distinct()->pluck('category');

        $settingsByCategory = [];
        foreach ($categories as $category) {
            $settingsByCategory[$category] = Setting::where('category', $category)
                ->orderBy('order')
                ->get();
        }

        // Получить все настройки как плоский список для поиска
        $allSettings = Setting::orderBy('category')->orderBy('order')->get();

        if ($request->wantsJson()) {
            return response()->json($allSettings);
        }

        return view('admin.settings.index', compact('settingsByCategory', 'allSettings'));
    }

    /**
     * Показать форму создания новой настройки
     *
     * @return View
     */
    public function create(): View
    {
        $categories = Setting::distinct()->pluck('category');
        $dataTypes = ['string', 'integer', 'boolean', 'text', 'json', 'array', 'file'];

        return view('admin.settings.create', compact('categories', 'dataTypes'));
    }

    /**
     * Сохранить новую настройку
     *
     * @param SettingRequest $request
     * @return RedirectResponse|JsonResponse
     */
    public function store(SettingRequest $request)
    {
        $data = $request->validated();

        // Обработка файлов, если есть
        if ($request->hasFile('value') && $data['type'] === 'file') {
            $path = $request->file('value')->store('settings', 'public');
            $data['value'] = $path;
        }

        // Преобразование boolean
        if ($data['type'] === 'boolean') {
            $data['value'] = filter_var($data['value'], FILTER_VALIDATE_BOOLEAN);
        }

        // Преобразование integer
        if ($data['type'] === 'integer') {
            $data['value'] = (int) $data['value'];
        }

        $setting = Setting::create($data);

        // Очистить кэш настроек
        Cache::forget('app_settings');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Настройка создана успешно',
                'setting' => $setting
            ], 201);
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Настройка успешно создана');
    }

    /**
     * Показать форму редактирования настройки
     *
     * @param Setting $setting
     * @return View
     */
    public function edit(Setting $setting): View
    {
        $categories = Setting::distinct()->pluck('category');
        $dataTypes = ['string', 'integer', 'boolean', 'text', 'json', 'array', 'file'];

        return view('admin.settings.edit', compact('setting', 'categories', 'dataTypes'));
    }

    /**
     * Обновить настройку
     *
     * @param SettingRequest $request
     * @param Setting $setting
     * @return RedirectResponse|JsonResponse
     */
    public function update(SettingRequest $request, Setting $setting)
    {
        $data = $request->validated();

        // Обработка файлов
        if ($request->hasFile('value') && $setting->type === 'file') {
            // Удалить старый файл, если он существует
            if ($setting->value && \Storage::disk('public')->exists($setting->value)) {
                \Storage::disk('public')->delete($setting->value);
            }

            $path = $request->file('value')->store('settings', 'public');
            $data['value'] = $path;
        } elseif ($setting->type === 'file' && empty($data['value'])) {
            // Не обновлять путь к файлу, если не загружен новый файл
            unset($data['value']);
        }

        // Преобразование boolean
        if ($setting->type === 'boolean') {
            $data['value'] = filter_var($data['value'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        // Преобразование integer
        if ($setting->type === 'integer') {
            $data['value'] = (int) ($data['value'] ?? 0);
        }

        $setting->update($data);

        // Очистить кэш настроек
        Cache::forget('app_settings');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Настройка обновлена успешно',
                'setting' => $setting
            ]);
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Настройка успешно обновлена');
    }

    /**
     * Удалить настройку
     *
     * @param Setting $setting
     * @return RedirectResponse|JsonResponse
     */
    public function destroy(Setting $setting, Request $request)
    {
        // Проверка, можно ли удалять системные настройки
        if ($setting->is_protected) {
            $message = 'Защищенные настройки нельзя удалить';

            if ($request->wantsJson()) {
                return response()->json(['error' => $message], 403);
            }

            return back()->with('error', $message);
        }

        // Удалить файл, если это файловая настройка
        if ($setting->type === 'file' && $setting->value) {
            \Storage::disk('public')->delete($setting->value);
        }

        $setting->delete();

        // Очистить кэш настроек
        Cache::forget('app_settings');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Настройка успешно удалена'
            ]);
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Настройка успешно удалена');
    }

    /**
     * Массовое обновление настроек (с главной страницы настроек)
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function bulkUpdate(Request $request)
    {
        $settingsData = $request->input('settings', []);

        foreach ($settingsData as $id => $value) {
            $setting = Setting::find($id);

            if ($setting && !$setting->is_protected) {
                // Валидация в зависимости от типа
                if ($setting->type === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif ($setting->type === 'integer') {
                    $value = (int) $value;
                }

                $setting->update(['value' => $value]);
            }
        }

        // Очистить кэш настроек
        Cache::forget('app_settings');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Настройки успешно обновлены'
            ]);
        }

        return back()->with('success', 'Настройки успешно обновлены');
    }

    /**
     * Восстановить настройки по умолчанию
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function restoreDefaults(Request $request)
    {
        // Находим настройки по умолчанию (те, у которых есть default_value)
        $defaultSettings = Setting::whereNotNull('default_value')
            ->where('is_protected', false)
            ->get();

        foreach ($defaultSettings as $setting) {
            $setting->update(['value' => $setting->default_value]);
        }

        // Очистить кэш настроек
        Cache::forget('app_settings');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Настройки восстановлены по умолчанию',
                'restored_count' => $defaultSettings->count()
            ]);
        }

        return back()->with('success', 'Настройки восстановлены по умолчанию');
    }

    /**
     * Экспорт настроек в JSON
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export()
    {
        $settings = Setting::all()->map(function ($setting) {
            return [
                'key' => $setting->key,
                'value' => $setting->value,
                'category' => $setting->category,
                'type' => $setting->type,
                'description' => $setting->description
            ];
        });

        $filename = 'settings-export-' . date('Y-m-d-H-i-s') . '.json';
        $filepath = storage_path('app/exports/' . $filename);

        \File::ensureDirectoryExists(storage_path('app/exports'));
        \File::put($filepath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->download($filepath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Импорт настроек из JSON
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function import(Request $request)
    {
        $request->validate([
            'settings_file' => 'required|file|mimes:json|max:2048'
        ]);

        $fileContent = file_get_contents($request->file('settings_file')->path());
        $settingsData = json_decode($fileContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $message = 'Неверный формат JSON файла';

            if ($request->wantsJson()) {
                return response()->json(['error' => $message], 400);
            }

            return back()->with('error', $message);
        }

        $imported = 0;
        $skipped = 0;

        foreach ($settingsData as $settingData) {
            $setting = Setting::where('key', $settingData['key'])->first();

            if ($setting && !$setting->is_protected) {
                $setting->update(['value' => $settingData['value']]);
                $imported++;
            } else {
                $skipped++;
            }
        }

        // Очистить кэш настроек
        Cache::forget('app_settings');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Импорт завершен',
                'imported' => $imported,
                'skipped' => $skipped
            ]);
        }

        return back()->with('success', "Импорт завершен. Обновлено: $imported, пропущено: $skipped");
    }

    /**
     * Получить настройки для определенной категории (API)
     *
     * @param string $category
     * @return JsonResponse
     */
    public function getByCategory(string $category): JsonResponse
    {
        $settings = Setting::where('category', $category)
            ->where('is_visible', true)
            ->orderBy('order')
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->value];
            });

        return response()->json($settings);
    }
}
