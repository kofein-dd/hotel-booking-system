<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * Список всех страниц
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request)
    {
        $query = Page::with(['author', 'updater'])
            ->orderBy('order')
            ->orderBy('title');

        // Фильтр по статусу
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Фильтр по типу
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Поиск
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $pages = $query->paginate($request->input('per_page', 20));

        if ($request->wantsJson()) {
            return response()->json($pages);
        }

        $statuses = Page::distinct()->pluck('status');
        $types = Page::distinct()->pluck('type');

        return view('admin.pages.index', compact('pages', 'statuses', 'types'));
    }

    /**
     * Форма создания новой страницы
     *
     * @return View
     */
    public function create(): View
    {
        $templates = $this->getAvailableTemplates();
        $layouts = $this->getAvailableLayouts();
        $parentPages = Page::where('status', 'published')
            ->whereNull('parent_id')
            ->orderBy('title')
            ->pluck('title', 'id');

        return view('admin.pages.create', compact('templates', 'layouts', 'parentPages'));
    }

    /**
     * Сохранение новой страницы
     *
     * @param PageRequest $request
     * @return RedirectResponse|JsonResponse
     */
    public function store(PageRequest $request)
    {
        $data = $request->validated();

        // Генерация slug, если не указан
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        // Проверка уникальности slug
        if (Page::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $data['slug'] . '-' . uniqid();
        }

        // Автор страницы
        $data['author_id'] = auth()->id();
        $data['created_by'] = auth()->id();

        // SEO-параметры
        if (empty($data['meta_title'])) {
            $data['meta_title'] = $data['title'];
        }

        if (empty($data['meta_description'])) {
            $data['meta_description'] = Str::limit(strip_tags($data['content']), 160);
        }

        $page = Page::create($data);

        // Очищаем кэш страниц
        $this->clearPageCache();

        // Создаем первую версию страницы
        $this->createPageVersion($page, 'Создание страницы');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Страница успешно создана',
                'page' => $page
            ], 201);
        }

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('success', 'Страница успешно создана');
    }

    /**
     * Форма редактирования страницы
     *
     * @param Page $page
     * @return View
     */
    public function edit(Page $page): View
    {
        $templates = $this->getAvailableTemplates();
        $layouts = $this->getAvailableLayouts();
        $parentPages = Page::where('status', 'published')
            ->where('id', '!=', $page->id)
            ->whereNull('parent_id')
            ->orderBy('title')
            ->pluck('title', 'id');

        // Версии страницы
        $versions = $page->versions()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.pages.edit', compact('page', 'templates', 'layouts', 'parentPages', 'versions'));
    }

    /**
     * Обновление страницы
     *
     * @param PageRequest $request
     * @param Page $page
     * @return RedirectResponse|JsonResponse
     */
    public function update(PageRequest $request, Page $page)
    {
        $data = $request->validated();

        // Обновление slug, если изменился заголовок
        if ($page->title !== $data['title'] && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        // Проверка уникальности slug
        if (isset($data['slug']) && $page->slug !== $data['slug']) {
            if (Page::where('slug', $data['slug'])->where('id', '!=', $page->id)->exists()) {
                $data['slug'] = $data['slug'] . '-' . uniqid();
            }
        }

        // Кто обновил страницу
        $data['updated_by'] = auth()->id();

        // SEO-параметры
        if (empty($data['meta_title'])) {
            $data['meta_title'] = $data['title'];
        }

        if (empty($data['meta_description'])) {
            $data['meta_description'] = Str::limit(strip_tags($data['content']), 160);
        }

        // Сохраняем старую версию перед обновлением
        $oldContent = $page->getOriginal('content');

        $page->update($data);

        // Очищаем кэш страниц
        $this->clearPageCache();

        // Создаем версию страницы, если изменился контент
        if ($oldContent !== $data['content']) {
            $comment = $request->input('version_comment', 'Обновление контента');
            $this->createPageVersion($page, $comment);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Страница успешно обновлена',
                'page' => $page
            ]);
        }

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('success', 'Страница успешно обновлена');
    }

    /**
     * Удаление страницы
     *
     * @param Page $page
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function destroy(Page $page, Request $request)
    {
        // Проверяем, можно ли удалять системные страницы
        if ($page->is_system && !$request->input('force')) {
            $message = 'Системные страницы нельзя удалить';

            if ($request->wantsJson()) {
                return response()->json(['error' => $message], 403);
            }

            return back()->with('error', $message);
        }

        // Проверяем, есть ли дочерние страницы
        if ($page->children()->exists()) {
            if (!$request->input('delete_with_children')) {
                $message = 'У страницы есть дочерние страницы. Удалить их вместе с родительской?';

                if ($request->wantsJson()) {
                    return response()->json([
                        'error' => $message,
                        'has_children' => true
                    ], 409);
                }

                return back()
                    ->with('warning', $message)
                    ->with('page_id', $page->id);
            }

            // Удаляем дочерние страницы
            $page->children()->delete();
        }

        // Удаляем версии страницы
        $page->versions()->delete();

        $page->delete();

        // Очищаем кэш страниц
        $this->clearPageCache();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Страница успешно удалена'
            ]);
        }

        return redirect()
            ->route('admin.pages.index')
            ->with('success', 'Страница успешно удалена');
    }

    /**
     * Массовое удаление страниц
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:pages,id',
            'delete_with_children' => 'boolean'
        ]);

        $ids = $request->input('ids');
        $deleteWithChildren = $request->input('delete_with_children', false);

        $pages = Page::whereIn('id', $ids)
            ->where('is_system', false)
            ->get();

        $deleted = 0;
        $errors = [];

        foreach ($pages as $page) {
            try {
                if ($page->children()->exists() && !$deleteWithChildren) {
                    $errors[] = "Страница '{$page->title}' имеет дочерние страницы";
                    continue;
                }

                if ($deleteWithChildren) {
                    $page->children()->delete();
                }

                $page->versions()->delete();
                $page->delete();
                $deleted++;
            } catch (\Exception $e) {
                $errors[] = "Ошибка при удалении страницы '{$page->title}': " . $e->getMessage();
            }
        }

        if ($deleted > 0) {
            $this->clearPageCache();
        }

        return response()->json([
            'message' => "Удалено страниц: {$deleted}",
            'deleted' => $deleted,
            'errors' => $errors
        ]);
    }

    /**
     * Изменение статуса страницы
     *
     * @param Page $page
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleStatus(Page $page, Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:draft,published,hidden,archived'
        ]);

        $oldStatus = $page->status;
        $newStatus = $request->input('status');

        $page->update([
            'status' => $newStatus,
            'updated_by' => auth()->id(),
            'published_at' => $newStatus === 'published' ? now() : $page->published_at
        ]);

        // Очищаем кэш страниц
        $this->clearPageCache();

        // Создаем версию страницы
        $this->createPageVersion($page, "Изменение статуса: {$oldStatus} → {$newStatus}");

        return response()->json([
            'message' => 'Статус страницы изменен',
            'status' => $newStatus,
            'published_at' => $page->published_at
        ]);
    }

    /**
     * Сортировка страниц (изменение порядка)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'pages' => 'required|array',
            'pages.*.id' => 'required|exists:pages,id',
            'pages.*.order' => 'required|integer',
            'pages.*.parent_id' => 'nullable|exists:pages,id'
        ]);

        foreach ($request->input('pages') as $pageData) {
            Page::where('id', $pageData['id'])->update([
                'order' => $pageData['order'],
                'parent_id' => $pageData['parent_id'] ?? null
            ]);
        }

        $this->clearPageCache();

        return response()->json([
            'message' => 'Порядок страниц обновлен'
        ]);
    }

    /**
     * Восстановление страницы из версии
     *
     * @param Page $page
     * @param Request $request
     * @return JsonResponse
     */
    public function restoreVersion(Page $page, Request $request): JsonResponse
    {
        $request->validate([
            'version_id' => 'required|exists:page_versions,id'
        ]);

        $version = $page->versions()->findOrFail($request->input('version_id'));

        // Сохраняем текущую версию как новую версию
        $this->createPageVersion($page, 'Создание версии перед восстановлением');

        // Восстанавливаем контент из версии
        $page->update([
            'content' => $version->content,
            'updated_by' => auth()->id()
        ]);

        // Очищаем кэш страниц
        $this->clearPageCache();

        // Создаем версию о восстановлении
        $this->createPageVersion($page, "Восстановление из версии от " . $version->created_at->format('d.m.Y H:i'));

        return response()->json([
            'message' => 'Страница восстановлена из версии',
            'version_date' => $version->created_at
        ]);
    }

    /**
     * Дублирование страницы
     *
     * @param Page $page
     * @return JsonResponse
     */
    public function duplicate(Page $page): JsonResponse
    {
        $newPage = $page->replicate();
        $newPage->title = $page->title . ' (копия)';
        $newPage->slug = $page->slug . '-copy-' . uniqid();
        $newPage->status = 'draft';
        $newPage->author_id = auth()->id();
        $newPage->created_by = auth()->id();
        $newPage->updated_by = auth()->id();
        $newPage->published_at = null;
        $newPage->save();

        // Копируем SEO-параметры
        $newPage->meta_title = $page->meta_title;
        $newPage->meta_description = $page->meta_description;
        $newPage->meta_keywords = $page->meta_keywords;
        $newPage->save();

        // Очищаем кэш страниц
        $this->clearPageCache();

        return response()->json([
            'message' => 'Страница успешно скопирована',
            'page' => $newPage,
            'edit_url' => route('admin.pages.edit', $newPage)
        ]);
    }

    /**
     * Предпросмотр страницы
     *
     * @param Page $page
     * @return View
     */
    public function preview(Page $page): View
    {
        if ($page->status !== 'published') {
            abort_if(!auth()->user()->isAdmin(), 404);
        }

        $template = $page->template ?: 'default';
        $layout = $page->layout ?: 'app';

        return view("pages.templates.{$template}", [
            'page' => $page,
            'layout' => $layout
        ]);
    }

    /**
     * Экспорт страниц
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        $pages = Page::when($request->has('type'), function ($query) use ($request) {
            $query->where('type', $request->input('type'));
        })
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->get();

        $data = $pages->map(function ($page) {
            return [
                'title' => $page->title,
                'slug' => $page->slug,
                'content' => $page->content,
                'type' => $page->type,
                'status' => $page->status,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'meta_keywords' => $page->meta_keywords,
                'order' => $page->order,
                'parent_id' => $page->parent_id,
                'template' => $page->template,
                'layout' => $page->layout,
                'is_system' => $page->is_system,
                'created_at' => $page->created_at,
                'updated_at' => $page->updated_at
            ];
        });

        $filename = 'pages-export-' . date('Y-m-d-H-i-s') . '.json';
        $filepath = storage_path('app/exports/' . $filename);

        \File::ensureDirectoryExists(storage_path('app/exports'));
        \File::put($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->download($filepath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Импорт страниц
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:json|max:5120',
            'import_mode' => 'required|in:create,update,replace'
        ]);

        $fileContent = file_get_contents($request->file('file')->path());
        $pagesData = json_decode($fileContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'error' => 'Неверный формат JSON файла'
            ], 400);
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($pagesData as $index => $pageData) {
            try {
                $pageData['author_id'] = auth()->id();
                $pageData['created_by'] = auth()->id();
                $pageData['updated_by'] = auth()->id();

                if ($request->input('import_mode') === 'replace') {
                    // Удаляем существующую страницу с таким slug
                    Page::where('slug', $pageData['slug'])->delete();
                }

                $existingPage = Page::where('slug', $pageData['slug'])->first();

                if ($existingPage) {
                    if ($request->input('import_mode') === 'create') {
                        $skipped++;
                        continue;
                    }

                    $existingPage->update($pageData);
                    $updated++;
                } else {
                    Page::create($pageData);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Строка {$index}: " . $e->getMessage();
                $skipped++;
            }
        }

        if ($imported > 0 || $updated > 0) {
            $this->clearPageCache();
        }

        return response()->json([
            'message' => 'Импорт завершен',
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors
        ]);
    }

    /**
     * Получить список доступных шаблонов
     *
     * @return array
     */
    private function getAvailableTemplates(): array
    {
        $templates = [
            'default' => 'Стандартный',
            'home' => 'Главная страница',
            'contact' => 'Контакты',
            'about' => 'О нас',
            'terms' => 'Условия',
            'privacy' => 'Политика конфиденциальности',
            'faq' => 'FAQ',
            'services' => 'Услуги'
        ];

        // Можно также получать шаблоны из папки resources/views/pages/templates
        $templateFiles = glob(resource_path('views/pages/templates/*.blade.php'));

        foreach ($templateFiles as $file) {
            $name = basename($file, '.blade.php');
            if (!isset($templates[$name])) {
                $templates[$name] = ucfirst(str_replace('_', ' ', $name));
            }
        }

        return $templates;
    }

    /**
     * Получить список доступных макетов
     *
     * @return array
     */
    private function getAvailableLayouts(): array
    {
        return [
            'app' => 'Основной макет',
            'admin' => 'Админ-панель',
            'auth' => 'Авторизация',
            'guest' => 'Гостевой',
            'clean' => 'Чистый (без шапки и подвала)'
        ];
    }

    /**
     * Создать версию страницы
     *
     * @param Page $page
     * @param string $comment
     * @return void
     */
    private function createPageVersion(Page $page, string $comment): void
    {
        \App\Models\PageVersion::create([
            'page_id' => $page->id,
            'content' => $page->content,
            'user_id' => auth()->id(),
            'comment' => $comment
        ]);

        // Ограничиваем количество хранимых версий
        $maxVersions = 50;
        $versionsCount = $page->versions()->count();

        if ($versionsCount > $maxVersions) {
            $oldVersions = $page->versions()
                ->orderBy('created_at')
                ->limit($versionsCount - $maxVersions)
                ->pluck('id');

            \App\Models\PageVersion::whereIn('id', $oldVersions)->delete();
        }
    }

    /**
     * Очистить кэш страниц
     *
     * @return void
     */
    private function clearPageCache(): void
    {
        Cache::forget('pages_menu');
        Cache::forget('pages_footer');
        Cache::forget('pages_system');

        // Очищаем кэш для каждой страницы
        Page::all()->each(function ($page) {
            Cache::forget("page_{$page->slug}");
        });
    }
}
