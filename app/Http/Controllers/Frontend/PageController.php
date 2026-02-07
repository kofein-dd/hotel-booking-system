<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

class PageController extends Controller
{
    /**
     * Display the specified page by slug.
     */
    public function show(Request $request, $slug)
    {
        try {
            // Кэшируем страницу на 1 час для производительности
            $page = Cache::remember("page_{$slug}", 3600, function () use ($slug) {
                return Page::where('slug', $slug)
                    ->where('status', 'published')
                    ->where(function ($query) {
                        $query->whereNull('published_at')
                            ->orWhere('published_at', '<=', now());
                    })
                    ->where(function ($query) {
                        $query->whereNull('expired_at')
                            ->orWhere('expired_at', '>=', now());
                    })
                    ->first();
            });

            if (!$page) {
                // Проверяем, есть ли страница с таким slug, но не опубликованная
                $unpublishedPage = Page::where('slug', $slug)->first();

                if ($unpublishedPage) {
                    // Страница существует, но не опубликована
                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Страница не доступна'
                        ], 404);
                    }

                    abort(404, 'Страница не доступна');
                }

                // Пробуем найти страницу по ID, если slug - число
                if (is_numeric($slug)) {
                    $page = Cache::remember("page_id_{$slug}", 3600, function () use ($slug) {
                        return Page::where('id', $slug)
                            ->where('status', 'published')
                            ->where(function ($query) {
                                $query->whereNull('published_at')
                                    ->orWhere('published_at', '<=', now());
                            })
                            ->where(function ($query) {
                                $query->whereNull('expired_at')
                                    ->orWhere('expired_at', '>=', now());
                            })
                            ->first();
                    });

                    if ($page && $page->slug !== $slug) {
                        // Редирект на правильный slug
                        return redirect()->route('page.show', $page->slug, 301);
                    }
                }

                if (!$page) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Страница не найдена'
                        ], 404);
                    }

                    abort(404, 'Страница не найдена');
                }
            }

            // Увеличиваем счетчик просмотров
            if (!$request->expectsJson()) {
                $this->incrementViewCount($page);
            }

            // Подготавливаем мета-теги
            $metaTags = $this->prepareMetaTags($page);

            // Получаем связанные страницы
            $relatedPages = $this->getRelatedPages($page);

            // Для API возвращаем JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'page' => $page,
                        'meta_tags' => $metaTags,
                        'related_pages' => $relatedPages
                    ]
                ]);
            }

            // Для веб-интерфейса
            return view('frontend.pages.show', [
                'page' => $page,
                'metaTags' => $metaTags,
                'relatedPages' => $relatedPages,
                'breadcrumbs' => $this->getBreadcrumbs($page)
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при загрузке страницы',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            // Если ошибка, показываем общую страницу ошибки
            return view('frontend.errors.500', [
                'message' => 'Ошибка при загрузке страницы'
            ]);
        }
    }

    /**
     * Display page by ID (with redirect to slug).
     */
    public function showById(Request $request, $id)
    {
        try {
            $page = Page::where('id', $id)
                ->where('status', 'published')
                ->where(function ($query) {
                    $query->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>=', now());
                })
                ->first();

            if (!$page) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Страница не найдена'
                    ], 404);
                }

                abort(404, 'Страница не найдена');
            }

            // Редирект на slug-версию для SEO
            return redirect()->route('page.show', $page->slug, 301);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при загрузке страницы'
                ], 500);
            }

            abort(500, 'Ошибка при загрузке страницы');
        }
    }

    /**
     * Display page by custom route.
     */
    public function customRoute(Request $request, $route)
    {
        try {
            // Ищем страницу по custom_route
            $page = Cache::remember("page_route_{$route}", 3600, function () use ($route) {
                return Page::where('custom_route', $route)
                    ->where('status', 'published')
                    ->where(function ($query) {
                        $query->whereNull('published_at')
                            ->orWhere('published_at', '<=', now());
                    })
                    ->where(function ($query) {
                        $query->whereNull('expired_at')
                            ->orWhere('expired_at', '>=', now());
                    })
                    ->first();
            });

            if (!$page) {
                // Если не найдено по custom_route, ищем по slug
                return $this->show($request, $route);
            }

            // Увеличиваем счетчик просмотров
            if (!$request->expectsJson()) {
                $this->incrementViewCount($page);
            }

            // Подготавливаем мета-теги
            $metaTags = $this->prepareMetaTags($page);

            // Получаем связанные страницы
            $relatedPages = $this->getRelatedPages($page);

            // Для API возвращаем JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'page' => $page,
                        'meta_tags' => $metaTags,
                        'related_pages' => $relatedPages
                    ]
                ]);
            }

            // Для веб-интерфейса
            return view('frontend.pages.show', [
                'page' => $page,
                'metaTags' => $metaTags,
                'relatedPages' => $relatedPages,
                'breadcrumbs' => $this->getBreadcrumbs($page, true)
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при загрузке страницы'
                ], 500);
            }

            return view('frontend.errors.500', [
                'message' => 'Ошибка при загрузке страницы'
            ]);
        }
    }

    /**
     * Display special pages (home, about, contact, etc.).
     */
    public function specialPage(Request $request, $pageType)
    {
        try {
            // Определяем slug для специальных страниц
            $slugMap = [
                'home' => 'home',
                'about' => 'about-us',
                'contact' => 'contact',
                'privacy' => 'privacy-policy',
                'terms' => 'terms-conditions',
                'faq' => 'faq',
                'blog' => 'blog',
                'news' => 'news',
            ];

            $slug = $slugMap[$pageType] ?? $pageType;

            // Ищем страницу
            $page = Cache::remember("page_special_{$slug}", 3600, function () use ($slug) {
                return Page::where('slug', $slug)
                    ->orWhere('custom_route', $slug)
                    ->where('status', 'published')
                    ->where(function ($query) {
                        $query->whereNull('published_at')
                            ->orWhere('published_at', '<=', now());
                    })
                    ->where(function ($query) {
                        $query->whereNull('expired_at')
                            ->orWhere('expired_at', '>=', now());
                    })
                    ->first();
            });

            // Если страница не найдена, создаем дефолтный контент
            if (!$page) {
                $page = $this->getDefaultPageContent($pageType);

                if (!$page) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Страница не найдена'
                        ], 404);
                    }

                    abort(404, 'Страница не найдена');
                }
            }

            // Увеличиваем счетчик просмотров
            if (!$request->expectsJson()) {
                $this->incrementViewCount($page);
            }

            // Подготавливаем мета-теги
            $metaTags = $this->prepareMetaTags($page);

            // Для домашней страницы получаем дополнительные данные
            $additionalData = [];
            if ($pageType === 'home') {
                $additionalData = $this->getHomePageData();
            }

            // Для API возвращаем JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'page' => $page,
                        'meta_tags' => $metaTags,
                        'additional_data' => $additionalData
                    ]
                ]);
            }

            // Определяем шаблон
            $template = $page->template ?: 'default';
            $viewName = "frontend.pages.templates.{$template}";

            if (!View::exists($viewName)) {
                $viewName = 'frontend.pages.show';
            }

            return view($viewName, [
                'page' => $page,
                'metaTags' => $metaTags,
                'additionalData' => $additionalData,
                'breadcrumbs' => $this->getBreadcrumbs($page, false, $pageType)
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при загрузке страницы',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return view('frontend.errors.500', [
                'message' => 'Ошибка при загрузке страницы'
            ]);
        }
    }

    /**
     * Display pages by category.
     */
    public function category(Request $request, $categorySlug)
    {
        try {
            // Ищем страницы категории
            $pages = Cache::remember("pages_category_{$categorySlug}", 1800, function () use ($categorySlug) {
                return Page::where('category', $categorySlug)
                    ->where('status', 'published')
                    ->where(function ($query) {
                        $query->whereNull('published_at')
                            ->orWhere('published_at', '<=', now());
                    })
                    ->where(function ($query) {
                        $query->whereNull('expired_at')
                            ->orWhere('expired_at', '>=', now());
                    })
                    ->orderBy('order')
                    ->orderBy('created_at', 'desc')
                    ->paginate(12);
            });

            if ($pages->isEmpty()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Категория не найдена или пуста'
                    ], 404);
                }

                abort(404, 'Категория не найдена или пуста');
            }

            // Получаем информацию о категории
            $categoryInfo = $this->getCategoryInfo($categorySlug);

            // Для API возвращаем JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'pages' => $pages,
                        'category_info' => $categoryInfo,
                        'meta' => [
                            'total' => $pages->total(),
                            'per_page' => $pages->perPage(),
                            'current_page' => $pages->currentPage(),
                            'last_page' => $pages->lastPage(),
                        ]
                    ]
                ]);
            }

            return view('frontend.pages.category', [
                'pages' => $pages,
                'categoryInfo' => $categoryInfo,
                'breadcrumbs' => $this->getCategoryBreadcrumbs($categorySlug, $categoryInfo)
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при загрузке категории'
                ], 500);
            }

            return view('frontend.errors.500', [
                'message' => 'Ошибка при загрузке категории'
            ]);
        }
    }

    /**
     * Display pages by tag.
     */
    public function tag(Request $request, $tag)
    {
        try {
            // Ищем страницы по тегу
            $pages = Cache::remember("pages_tag_{$tag}", 1800, function () use ($tag) {
                return Page::where('tags', 'LIKE', "%\"{$tag}\"%")
                    ->orWhere('tags', 'LIKE', "%{$tag}%")
                    ->where('status', 'published')
                    ->where(function ($query) {
                        $query->whereNull('published_at')
                            ->orWhere('published_at', '<=', now());
                    })
                    ->where(function ($query) {
                        $query->whereNull('expired_at')
                            ->orWhere('expired_at', '>=', now());
                    })
                    ->orderBy('created_at', 'desc')
                    ->paginate(12);
            });

            if ($pages->isEmpty()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Тег не найден или нет страниц'
                    ], 404);
                }

                abort(404, 'Тег не найден или нет страниц');
            }

            // Для API возвращаем JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'pages' => $pages,
                        'tag' => $tag,
                        'meta' => [
                            'total' => $pages->total(),
                            'per_page' => $pages->perPage(),
                            'current_page' => $pages->currentPage(),
                            'last_page' => $pages->lastPage(),
                        ]
                    ]
                ]);
            }

            return view('frontend.pages.tag', [
                'pages' => $pages,
                'tag' => $tag,
                'breadcrumbs' => $this->getTagBreadcrumbs($tag)
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при загрузке страниц по тегу'
                ], 500);
            }

            return view('frontend.errors.500', [
                'message' => 'Ошибка при загрузке страниц по тегу'
            ]);
        }
    }

    /**
     * Search pages.
     */
    public function search(Request $request)
    {
        try {
            $query = $request->get('q', '');
            $category = $request->get('category');
            $tag = $request->get('tag');

            if (empty($query) && empty($category) && empty($tag)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Введите поисковый запрос'
                    ], 400);
                }

                return redirect()->back()->with('error', 'Введите поисковый запрос');
            }

            $searchQuery = Page::where('status', 'published')
                ->where(function ($q) {
                    $q->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('expired_at')
                        ->orWhere('expired_at', '>=', now());
                });

            // Поиск по тексту
            if (!empty($query)) {
                $searchQuery->where(function ($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                        ->orWhere('content', 'LIKE', "%{$query}%")
                        ->orWhere('excerpt', 'LIKE', "%{$query}%")
                        ->orWhere('meta_keywords', 'LIKE', "%{$query}%");
                });
            }

            // Фильтр по категории
            if (!empty($category)) {
                $searchQuery->where('category', $category);
            }

            // Фильтр по тегу
            if (!empty($tag)) {
                $searchQuery->where('tags', 'LIKE', "%\"{$tag}\"%")
                    ->orWhere('tags', 'LIKE', "%{$tag}%");
            }

            $pages = $searchQuery->orderBy('created_at', 'desc')
                ->paginate(12);

            // Получаем категории для фильтра
            $categories = Page::where('status', 'published')
                ->whereNotNull('category')
                ->select('category')
                ->distinct()
                ->pluck('category');

            // Для API возвращаем JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'pages' => $pages,
                        'search_query' => $query,
                        'categories' => $categories,
                        'meta' => [
                            'total' => $pages->total(),
                            'per_page' => $pages->perPage(),
                            'current_page' => $pages->currentPage(),
                            'last_page' => $pages->lastPage(),
                        ]
                    ]
                ]);
            }

            return view('frontend.pages.search', [
                'pages' => $pages,
                'searchQuery' => $query,
                'categories' => $categories,
                'selectedCategory' => $category,
                'selectedTag' => $tag,
                'breadcrumbs' => $this->getSearchBreadcrumbs($query)
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при поиске'
                ], 500);
            }

            return view('frontend.errors.500', [
                'message' => 'Ошибка при поиске'
            ]);
        }
    }

    /**
     * Get sitemap for pages.
     */
    public function sitemap(Request $request)
    {
        try {
            // Получаем все опубликованные страницы
            $pages = Page::where('status', 'published')
                ->where(function ($query) {
                    $query->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>=', now());
                })
                ->where('in_sitemap', true)
                ->orderBy('updated_at', 'desc')
                ->get();

            // Группируем по частоте обновления
            $sitemapData = [
                'always' => $pages->where('change_frequency', 'always'),
                'hourly' => $pages->where('change_frequency', 'hourly'),
                'daily' => $pages->where('change_frequency', 'daily'),
                'weekly' => $pages->where('change_frequency', 'weekly'),
                'monthly' => $pages->where('change_frequency', 'monthly'),
                'yearly' => $pages->where('change_frequency', 'yearly'),
                'never' => $pages->where('change_frequency', 'never'),
            ];

            // Для API возвращаем JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'pages' => $pages,
                        'sitemap_data' => $sitemapData
                    ]
                ]);
            }

            // Для XML sitemap
            if ($request->wantsXml()) {
                return response()->view('frontend.pages.sitemap', [
                    'pages' => $pages
                ])->header('Content-Type', 'text/xml');
            }

            // Для HTML просмотра
            return view('frontend.pages.sitemap-html', [
                'pages' => $pages,
                'sitemapData' => $sitemapData
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при создании карты сайта'
                ], 500);
            }

            return response('Ошибка при создании карты сайта', 500);
        }
    }

    /**
     * Get pages for navigation menu.
     */
    public function navigation(Request $request, $menuPosition = 'main')
    {
        try {
            $pages = Cache::remember("navigation_{$menuPosition}", 3600, function () use ($menuPosition) {
                return Page::where('status', 'published')
                    ->where('in_navigation', true)
                    ->where('navigation_position', $menuPosition)
                    ->where(function ($query) {
                        $query->whereNull('published_at')
                            ->orWhere('published_at', '<=', now());
                    })
                    ->where(function ($query) {
                        $query->whereNull('expired_at')
                            ->orWhere('expired_at', '>=', now());
                    })
                    ->orderBy('navigation_order')
                    ->orderBy('title')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => $pages
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении навигации'
            ], 500);
        }
    }

    /**
     * Get page by slug for API.
     */
    public function getBySlug(Request $request, $slug)
    {
        return $this->show($request, $slug);
    }

    /**
     * Get pages list for API.
     */
    public function getPages(Request $request)
    {
        try {
            $query = Page::where('status', 'published')
                ->where(function ($query) {
                    $query->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>=', now());
                });

            // Фильтр по категории
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Фильтр по тегу
            if ($request->has('tag')) {
                $query->where('tags', 'LIKE', "%\"{$request->tag}\"%")
                    ->orWhere('tags', 'LIKE', "%{$request->tag}%");
            }

            // Поиск
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                        ->orWhere('content', 'LIKE', "%{$search}%")
                        ->orWhere('excerpt', 'LIKE', "%{$search}%");
                });
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $pages = $query->paginate($perPage);

            // Получаем категории
            $categories = Page::where('status', 'published')
                ->whereNotNull('category')
                ->select('category')
                ->distinct()
                ->pluck('category');

            return response()->json([
                'success' => true,
                'data' => $pages,
                'categories' => $categories,
                'meta' => [
                    'total' => $pages->total(),
                    'per_page' => $pages->perPage(),
                    'current_page' => $pages->currentPage(),
                    'last_page' => $pages->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении страниц'
            ], 500);
        }
    }

    /**
     * Helper method to increment view count.
     */
    private function incrementViewCount($page)
    {
        // Используем кэш для счетчиков просмотров
        $cacheKey = "page_views_{$page->id}";
        $views = Cache::get($cacheKey, 0) + 1;

        Cache::put($cacheKey, $views, 3600); // Храним 1 час

        // Каждые 10 просмотров обновляем в базе
        if ($views % 10 === 0) {
            $page->increment('views', 10);
        }
    }

    /**
     * Helper method to prepare meta tags.
     */
    private function prepareMetaTags($page)
    {
        return [
            'title' => $page->meta_title ?: $page->title,
            'description' => $page->meta_description ?: $page->excerpt,
            'keywords' => $page->meta_keywords,
            'og_title' => $page->og_title ?: $page->title,
            'og_description' => $page->og_description ?: $page->excerpt,
            'og_image' => $page->og_image ? asset('storage/' . $page->og_image) : null,
            'og_type' => $page->og_type ?: 'website',
            'twitter_card' => $page->twitter_card ?: 'summary_large_image',
            'twitter_title' => $page->twitter_title ?: $page->title,
            'twitter_description' => $page->twitter_description ?: $page->excerpt,
            'twitter_image' => $page->twitter_image ? asset('storage/' . $page->twitter_image) : null,
            'canonical_url' => route('page.show', $page->slug),
            'robots' => $page->robots_meta ?: 'index, follow',
        ];
    }

    /**
     * Helper method to get related pages.
     */
    private function getRelatedPages($page)
    {
        return Cache::remember("related_pages_{$page->id}", 1800, function () use ($page) {
            $query = Page::where('id', '!=', $page->id)
                ->where('status', 'published')
                ->where(function ($q) {
                    $q->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('expired_at')
                        ->orWhere('expired_at', '>=', now());
                })
                ->limit(6);

            // Ищем по категории
            if ($page->category) {
                $query->where('category', $page->category);
            }

            // Ищем по тегам
            if ($page->tags) {
                $tags = json_decode($page->tags, true) ?: [];
                if (!empty($tags)) {
                    foreach ($tags as $tag) {
                        $query->orWhere('tags', 'LIKE', "%\"{$tag}\"%");
                    }
                }
            }

            return $query->orderBy('views', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
        });
    }

    /**
     * Helper method to get breadcrumbs.
     */
    private function getBreadcrumbs($page, $isCustomRoute = false, $pageType = null)
    {
        $breadcrumbs = [
            ['url' => route('home'), 'title' => 'Главная']
        ];

        if ($pageType === 'home') {
            return $breadcrumbs;
        }

        // Добавляем категорию, если есть
        if ($page->category) {
            $breadcrumbs[] = [
                'url' => route('pages.category', $page->category),
                'title' => $this->getCategoryName($page->category)
            ];
        }

        // Добавляем страницу
        $breadcrumbs[] = [
            'url' => $isCustomRoute ? route('pages.custom', $page->custom_route) : route('page.show', $page->slug),
            'title' => $page->title,
            'current' => true
        ];

        return $breadcrumbs;
    }

    /**
     * Helper method to get default page content.
     */
    private function getDefaultPageContent($pageType)
    {
        $defaultPages = [
            'home' => [
                'title' => 'Добро пожаловать в наш отель',
                'content' => '<h1>Добро пожаловать в наш отель у моря</h1><p>Здесь вы найдете всю необходимую информацию о нашем отеле и услугах.</p>',
                'excerpt' => 'Лучший отель у моря для вашего отдыха',
                'slug' => 'home',
            ],
            'about' => [
                'title' => 'О нас',
                'content' => '<h1>О нашем отеле</h1><p>Наш отель предлагает лучший сервис и комфорт для вашего отдыха.</p>',
                'excerpt' => 'Информация о нашем отеле и команде',
                'slug' => 'about-us',
            ],
            'contact' => [
                'title' => 'Контакты',
                'content' => '<h1>Свяжитесь с нами</h1><p>Мы всегда рады ответить на ваши вопросы.</p>',
                'excerpt' => 'Контактная информация отеля',
                'slug' => 'contact',
            ],
        ];

        if (!isset($defaultPages[$pageType])) {
            return null;
        }

        return (object) $defaultPages[$pageType];
    }

    /**
     * Helper method to get home page data.
     */
    private function getHomePageData()
    {
        return [
            'featured_rooms' => \App\Models\Room::where('is_featured', true)
                ->where('status', 'active')
                ->limit(6)
                ->get(),
            'latest_news' => Page::where('category', 'news')
                ->where('status', 'published')
                ->where(function ($q) {
                    $q->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get(),
            'testimonials' => \App\Models\Review::with('user')
                ->where('rating', '>=', 4)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * Helper method to get category info.
     */
    private function getCategoryInfo($categorySlug)
    {
        // Можно расширить для получения описания категории из отдельной таблицы
        return [
            'name' => $this->getCategoryName($categorySlug),
            'slug' => $categorySlug,
            'description' => "Страницы в категории: " . $this->getCategoryName($categorySlug),
        ];
    }

    /**
     * Helper method to get category name.
     */
    private function getCategoryName($categorySlug)
    {
        $categories = [
            'news' => 'Новости',
            'blog' => 'Блог',
            'about' => 'О нас',
            'services' => 'Услуги',
            'offers' => 'Акции',
            'help' => 'Помощь',
        ];

        return $categories[$categorySlug] ?? ucfirst(str_replace('-', ' ', $categorySlug));
    }

    /**
     * Helper method to get category breadcrumbs.
     */
    private function getCategoryBreadcrumbs($categorySlug, $categoryInfo)
    {
        return [
            ['url' => route('home'), 'title' => 'Главная'],
            ['url' => route('pages.category', $categorySlug), 'title' => $categoryInfo['name'], 'current' => true]
        ];
    }

    /**
     * Helper method to get tag breadcrumbs.
     */
    private function getTagBreadcrumbs($tag)
    {
        return [
            ['url' => route('home'), 'title' => 'Главная'],
            ['url' => route('pages.tag', $tag), 'title' => "Тег: {$tag}", 'current' => true]
        ];
    }

    /**
     * Helper method to get search breadcrumbs.
     */
    private function getSearchBreadcrumbs($query)
    {
        return [
            ['url' => route('home'), 'title' => 'Главная'],
            ['url' => route('pages.search') . '?q=' . urlencode($query), 'title' => "Поиск: {$query}", 'current' => true]
        ];
    }
}
