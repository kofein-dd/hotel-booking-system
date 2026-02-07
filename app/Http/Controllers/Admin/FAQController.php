<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FAQRequest;
use App\Models\FAQ;
use App\Models\FAQCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class FAQController extends Controller
{
    /**
     * Список всех вопросов
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request)
    {
        $query = FAQ::with(['category', 'author'])
            ->orderBy('order')
            ->orderBy('created_at', 'desc');

        // Фильтр по категории
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Фильтр по статусу
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Поиск
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        $faqs = $query->paginate($request->input('per_page', 20));

        // Категории для фильтра
        $categories = FAQCategory::where('status', 'active')
            ->orderBy('name')
            ->get();

        $statuses = ['draft', 'published', 'hidden', 'archived'];

        if ($request->wantsJson()) {
            return response()->json([
                'faqs' => $faqs,
                'categories' => $categories
            ]);
        }

        return view('admin.faq.index', compact('faqs', 'categories', 'statuses'));
    }

    /**
     * Форма создания нового вопроса
     *
     * @return View
     */
    public function create(): View
    {
        $categories = FAQCategory::where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id');

        $relatedFaqs = FAQ::where('status', 'published')
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get(['id', 'question']);

        return view('admin.faq.create', compact('categories', 'relatedFaqs'));
    }

    /**
     * Сохранение нового вопроса
     *
     * @param FAQRequest $request
     * @return RedirectResponse|JsonResponse
     */
    public function store(FAQRequest $request)
    {
        $data = $request->validated();

        // Автор вопроса
        $data['author_id'] = auth()->id();

        // Если порядок не указан, ставим в конец категории
        if (empty($data['order'])) {
            $maxOrder = FAQ::where('category_id', $data['category_id'])
                ->max('order') ?? 0;
            $data['order'] = $maxOrder + 1;
        }

        // Обработка тегов
        if (!empty($data['tags'])) {
            if (is_string($data['tags'])) {
                $data['tags'] = array_map('trim', explode(',', $data['tags']));
            }
            $data['tags'] = json_encode($data['tags']);
        }

        $faq = FAQ::create($data);

        // Очищаем кэш FAQ
        $this->clearFaqCache($faq->category_id);

        // Создаем первую версию вопроса
        $this->createFaqVersion($faq, 'Создание вопроса');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Вопрос успешно создан',
                'faq' => $faq->load('category')
            ], 201);
        }

        return redirect()
            ->route('admin.faq.edit', $faq)
            ->with('success', 'Вопрос успешно создан');
    }

    /**
     * Форма редактирования вопроса
     *
     * @param FAQ $faq
     * @return View
     */
    public function edit(FAQ $faq): View
    {
        $categories = FAQCategory::where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id');

        $relatedFaqs = FAQ::where('status', 'published')
            ->where('id', '!=', $faq->id)
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get(['id', 'question']);

        // Версии вопроса
        $versions = $faq->versions()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Популярные теги
        $popularTags = $this->getPopularTags();

        return view('admin.faq.edit', compact(
            'faq',
            'categories',
            'relatedFaqs',
            'versions',
            'popularTags'
        ));
    }

    /**
     * Обновление вопроса
     *
     * @param FAQRequest $request
     * @param FAQ $faq
     * @return RedirectResponse|JsonResponse
     */
    public function update(FAQRequest $request, FAQ $faq)
    {
        $data = $request->validated();

        // Старая категория для очистки кэша
        $oldCategoryId = $faq->category_id;

        // Кто обновил вопрос
        $data['updated_by'] = auth()->id();

        // Обработка тегов
        if (!empty($data['tags'])) {
            if (is_string($data['tags'])) {
                $data['tags'] = array_map('trim', explode(',', $data['tags']));
            }
            $data['tags'] = json_encode($data['tags']);
        }

        // Сохраняем старую версию перед обновлением
        $oldQuestion = $faq->question;
        $oldAnswer = $faq->answer;

        $faq->update($data);

        // Очищаем кэш для старой и новой категории
        $this->clearFaqCache($oldCategoryId);
        if ($oldCategoryId != $faq->category_id) {
            $this->clearFaqCache($faq->category_id);
        }

        // Создаем версию вопроса, если изменился вопрос или ответ
        if ($oldQuestion !== $data['question'] || $oldAnswer !== $data['answer']) {
            $comment = $request->input('version_comment', 'Обновление вопроса');
            $this->createFaqVersion($faq, $comment);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Вопрос успешно обновлен',
                'faq' => $faq->load('category')
            ]);
        }

        return redirect()
            ->route('admin.faq.edit', $faq)
            ->with('success', 'Вопрос успешно обновлен');
    }

    /**
     * Удаление вопроса
     *
     * @param FAQ $faq
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function destroy(FAQ $faq, Request $request)
    {
        // Сохраняем ID категории для очистки кэша
        $categoryId = $faq->category_id;

        // Удаляем версии вопроса
        $faq->versions()->delete();

        // Удаляем статистику просмотров
        $faq->views()->delete();

        $faq->delete();

        // Очищаем кэш FAQ
        $this->clearFaqCache($categoryId);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Вопрос успешно удален'
            ]);
        }

        return redirect()
            ->route('admin.faq.index')
            ->with('success', 'Вопрос успешно удален');
    }

    /**
     * Изменение статуса вопроса
     *
     * @param FAQ $faq
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleStatus(FAQ $faq, Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:draft,published,hidden,archived'
        ]);

        $oldStatus = $faq->status;
        $newStatus = $request->input('status');

        $faq->update([
            'status' => $newStatus,
            'updated_by' => auth()->id(),
            'published_at' => $newStatus === 'published' ? now() : $faq->published_at
        ]);

        // Очищаем кэш FAQ
        $this->clearFaqCache($faq->category_id);

        // Создаем версию вопроса
        $this->createFaqVersion($faq, "Изменение статуса: {$oldStatus} → {$newStatus}");

        return response()->json([
            'message' => 'Статус вопроса изменен',
            'status' => $newStatus,
            'published_at' => $faq->published_at
        ]);
    }

    /**
     * Сортировка вопросов (изменение порядка)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:faqs,id',
            'items.*.order' => 'required|integer',
            'items.*.category_id' => 'nullable|exists:faq_categories,id'
        ]);

        foreach ($request->input('items') as $item) {
            $faq = FAQ::find($item['id']);

            $updates = ['order' => $item['order']];

            // Если изменилась категория
            if (isset($item['category_id']) && $faq->category_id != $item['category_id']) {
                $oldCategoryId = $faq->category_id;
                $updates['category_id'] = $item['category_id'];

                $faq->update($updates);

                // Очищаем кэш для обеих категорий
                $this->clearFaqCache($oldCategoryId);
                $this->clearFaqCache($item['category_id']);
            } else {
                $faq->update($updates);
                $this->clearFaqCache($faq->category_id);
            }
        }

        return response()->json([
            'message' => 'Порядок вопросов обновлен'
        ]);
    }

    /**
     * Восстановление вопроса из версии
     *
     * @param FAQ $faq
     * @param Request $request
     * @return JsonResponse
     */
    public function restoreVersion(FAQ $faq, Request $request): JsonResponse
    {
        $request->validate([
            'version_id' => 'required|exists:faq_versions,id'
        ]);

        $version = $faq->versions()->findOrFail($request->input('version_id'));

        // Сохраняем текущую версию как новую версию
        $this->createFaqVersion($faq, 'Создание версии перед восстановлением');

        // Восстанавливаем вопрос и ответ из версии
        $faq->update([
            'question' => $version->question,
            'answer' => $version->answer,
            'updated_by' => auth()->id()
        ]);

        // Очищаем кэш FAQ
        $this->clearFaqCache($faq->category_id);

        // Создаем версию о восстановлении
        $this->createFaqVersion($faq, "Восстановление из версии от " . $version->created_at->format('d.m.Y H:i'));

        return response()->json([
            'message' => 'Вопрос восстановлен из версии',
            'version_date' => $version->created_at
        ]);
    }

    /**
     * Дублирование вопроса
     *
     * @param FAQ $faq
     * @return JsonResponse
     */
    public function duplicate(FAQ $faq): JsonResponse
    {
        $newFaq = $faq->replicate();
        $newFaq->question = $faq->question . ' (копия)';
        $newFaq->status = 'draft';
        $newFaq->author_id = auth()->id();
        $newFaq->updated_by = auth()->id();
        $newFaq->views_count = 0;
        $newFaq->helpful_count = 0;
        $newFaq->not_helpful_count = 0;
        $newFaq->published_at = null;
        $newFaq->save();

        // Очищаем кэш FAQ
        $this->clearFaqCache($newFaq->category_id);

        return response()->json([
            'message' => 'Вопрос успешно скопирован',
            'faq' => $newFaq,
            'edit_url' => route('admin.faq.edit', $newFaq)
        ]);
    }

    /**
     * Просмотр статистики вопроса
     *
     * @param FAQ $faq
     * @return View
     */
    public function stats(FAQ $faq): View
    {
        $faq->load(['category', 'author', 'updater']);

        // Статистика просмотров по дням
        $viewsByDay = $faq->views()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Популярные поисковые запросы, которые привели к этому FAQ
        $searchTerms = $faq->searchTerms()
            ->orderBy('count', 'desc')
            ->limit(20)
            ->get();

        return view('admin.faq.stats', compact('faq', 'viewsByDay', 'searchTerms'));
    }

    /**
     * Управление категориями FAQ
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function categories(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'icon' => 'nullable|string|max:50',
                'color' => 'nullable|string|max:7',
                'status' => 'required|in:active,inactive',
                'order' => 'nullable|integer'
            ]);

            $category = FAQCategory::create($request->all());

            // Очищаем кэш категорий
            Cache::forget('faq_categories');

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Категория создана',
                    'category' => $category
                ]);
            }

            return back()->with('success', 'Категория создана');
        }

        $categories = FAQCategory::withCount('faqs')
            ->orderBy('order')
            ->orderBy('name')
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json($categories);
        }

        return view('admin.faq.categories', compact('categories'));
    }

    /**
     * Редактирование категории
     *
     * @param FAQCategory $category
     * @param Request $request
     * @return JsonResponse
     */
    public function updateCategory(FAQCategory $category, Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'status' => 'required|in:active,inactive',
            'order' => 'nullable|integer'
        ]);

        $category->update($request->all());

        // Очищаем кэш категорий и FAQ этой категории
        Cache::forget('faq_categories');
        $this->clearFaqCache($category->id);

        return response()->json([
            'message' => 'Категория обновлена',
            'category' => $category
        ]);
    }

    /**
     * Удаление категории
     *
     * @param FAQCategory $category
     * @param Request $request
     * @return JsonResponse
     */
    public function destroyCategory(FAQCategory $category, Request $request): JsonResponse
    {
        // Проверяем, есть ли вопросы в категории
        if ($category->faqs()->exists() && !$request->input('force')) {
            return response()->json([
                'error' => 'В категории есть вопросы. Удалить категорию вместе с вопросами?',
                'has_faqs' => true
            ], 409);
        }

        // Если нужно удалить с вопросами
        if ($request->input('force')) {
            $category->faqs()->delete();
        } else {
            // Переносим вопросы в другую категорию
            $newCategoryId = $request->input('new_category_id');
            if (!$newCategoryId) {
                return response()->json([
                    'error' => 'Укажите новую категорию для вопросов'
                ], 400);
            }

            $category->faqs()->update(['category_id' => $newCategoryId]);
            $this->clearFaqCache($newCategoryId);
        }

        $category->delete();

        // Очищаем кэш категорий
        Cache::forget('faq_categories');

        return response()->json([
            'message' => 'Категория удалена'
        ]);
    }

    /**
     * Импорт FAQ из CSV/JSON
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,json,txt|max:5120',
            'category_id' => 'required|exists:faq_categories,id',
            'import_mode' => 'required|in:create,update'
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        $imported = 0;
        $updated = 0;
        $errors = [];

        if ($extension === 'json') {
            $data = json_decode(file_get_contents($file->path()), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Неверный формат JSON'], 400);
            }

            foreach ($data as $index => $item) {
                try {
                    $item['category_id'] = $request->input('category_id');
                    $item['author_id'] = auth()->id();
                    $item['status'] = $item['status'] ?? 'published';

                    if ($request->input('import_mode') === 'update' && isset($item['id'])) {
                        $faq = FAQ::find($item['id']);
                        if ($faq) {
                            $faq->update($item);
                            $updated++;
                        } else {
                            FAQ::create($item);
                            $imported++;
                        }
                    } else {
                        FAQ::create($item);
                        $imported++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Строка {$index}: " . $e->getMessage();
                }
            }
        } elseif ($extension === 'csv') {
            $handle = fopen($file->path(), 'r');
            $headers = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                try {
                    $item = array_combine($headers, $row);
                    $item['category_id'] = $request->input('category_id');
                    $item['author_id'] = auth()->id();
                    $item['status'] = $item['status'] ?? 'published';

                    FAQ::create($item);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Ошибка в строке: " . $e->getMessage();
                }
            }

            fclose($handle);
        }

        // Очищаем кэш FAQ
        $this->clearFaqCache($request->input('category_id'));

        return response()->json([
            'message' => 'Импорт завершен',
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors
        ]);
    }

    /**
     * Экспорт FAQ
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required|in:json,csv',
            'category_id' => 'nullable|exists:faq_categories,id',
            'status' => 'nullable|in:draft,published,hidden,archived'
        ]);

        $query = FAQ::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $faqs = $query->get();

        $filename = 'faq-export-' . date('Y-m-d-H-i-s') . '.' . $request->input('format');
        $filepath = storage_path('app/exports/' . $filename);

        \File::ensureDirectoryExists(storage_path('app/exports'));

        if ($request->input('format') === 'json') {
            $data = $faqs->map(function ($faq) {
                return [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category_id' => $faq->category_id,
                    'category_name' => $faq->category->name,
                    'order' => $faq->order,
                    'status' => $faq->status,
                    'tags' => json_decode($faq->tags, true),
                    'views_count' => $faq->views_count,
                    'helpful_count' => $faq->helpful_count,
                    'not_helpful_count' => $faq->not_helpful_count,
                    'author_id' => $faq->author_id,
                    'created_at' => $faq->created_at,
                    'updated_at' => $faq->updated_at
                ];
            });

            \File::put($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $handle = fopen($filepath, 'w');

            // Заголовки CSV
            fputcsv($handle, [
                'ID', 'Question', 'Answer', 'Category ID', 'Category Name',
                'Order', 'Status', 'Tags', 'Views', 'Helpful', 'Not Helpful',
                'Author ID', 'Created At', 'Updated At'
            ]);

            // Данные
            foreach ($faqs as $faq) {
                fputcsv($handle, [
                    $faq->id,
                    $faq->question,
                    $faq->answer,
                    $faq->category_id,
                    $faq->category->name,
                    $faq->order,
                    $faq->status,
                    $faq->tags,
                    $faq->views_count,
                    $faq->helpful_count,
                    $faq->not_helpful_count,
                    $faq->author_id,
                    $faq->created_at,
                    $faq->updated_at
                ]);
            }

            fclose($handle);
        }

        return response()->download($filepath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Получить популярные теги
     *
     * @return array
     */
    private function getPopularTags(): array
    {
        return Cache::remember('faq_popular_tags', 3600, function () {
            $tags = FAQ::whereNotNull('tags')
                ->where('status', 'published')
                ->pluck('tags')
                ->flatMap(function ($tagsJson) {
                    return json_decode($tagsJson, true) ?: [];
                })
                ->countBy()
                ->sortDesc()
                ->take(20)
                ->keys()
                ->toArray();

            return $tags;
        });
    }

    /**
     * Создать версию FAQ
     *
     * @param FAQ $faq
     * @param string $comment
     * @return void
     */
    private function createFaqVersion(FAQ $faq, string $comment): void
    {
        \App\Models\FAQVersion::create([
            'faq_id' => $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'user_id' => auth()->id(),
            'comment' => $comment
        ]);

        // Ограничиваем количество хранимых версий
        $maxVersions = 30;
        $versionsCount = $faq->versions()->count();

        if ($versionsCount > $maxVersions) {
            $oldVersions = $faq->versions()
                ->orderBy('created_at')
                ->limit($versionsCount - $maxVersions)
                ->pluck('id');

            \App\Models\FAQVersion::whereIn('id', $oldVersions)->delete();
        }
    }

    /**
     * Очистить кэш FAQ для категории
     *
     * @param int|null $categoryId
     * @return void
     */
    private function clearFaqCache(?int $categoryId = null): void
    {
        Cache::forget('faq_all');
        Cache::forget('faq_categories');

        if ($categoryId) {
            Cache::forget("faq_category_{$categoryId}");
            Cache::forget("faq_category_{$categoryId}_count");
        }
    }
}
