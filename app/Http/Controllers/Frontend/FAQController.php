<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\FAQ;
use App\Models\FAQCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class FAQController extends Controller
{
    /**
     * Display a listing of FAQs.
     */
    public function index(Request $request)
    {
        try {
            $query = FAQ::with('category')
                ->where('status', 'published')
                ->where(function ($query) {
                    $query->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>=', now());
                });

            // Фильтр по категории
            if ($request->has('category') && $request->category) {
                $query->where('category_id', $request->category);
            }

            // Поиск
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('question', 'LIKE', "%{$search}%")
                        ->orWhere('answer', 'LIKE', "%{$search}%")
                        ->orWhere('tags', 'LIKE', "%{$search}%");
                });
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'order');
            $sortOrder = $request->get('sort_order', 'asc');

            if ($sortBy === 'popular') {
                $query->orderBy('views', 'desc');
            } elseif ($sortBy === 'latest') {
                $query->orderBy('created_at', 'desc');
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Получаем категории для фильтра
            $categories = Cache::remember('faq_categories_active', 3600, function () {
                return FAQCategory::where('status', 'active')
                    ->whereHas('faqs', function ($query) {
                        $query->where('status', 'published')
                            ->where(function ($q) {
                                $q->whereNull('published_at')
                                    ->orWhere('published_at', '<=', now());
                            })
                            ->where(function ($q) {
                                $q->whereNull('expired_at')
                                    ->orWhere('expired_at', '>=', now());
                            });
                    })
                    ->orderBy('order')
                    ->orderBy('name')
                    ->get();
            });

            // Получаем популярные FAQ
            $popularFaqs = Cache::remember('faq_popular', 1800, function () {
                return FAQ::where('status', 'published')
                    ->where(function ($query) {
                        $query->whereNull('published_at')
                            ->orWhere('published_at', '<=', now());
                    })
                    ->where(function ($query) {
                        $query->whereNull('expired_at')
                            ->orWhere('expired_at', '>=', now());
                    })
                    ->orderBy('views', 'desc')
                    ->limit(10)
                    ->get();
            });

            // Для веб-интерфейса - группируем по категориям
            if (!$request->expectsJson() && !$request->has('search') && !$request->has('category')) {
                $groupedFaqs = Cache::remember('faq_grouped_categories', 3600, function () use ($categories) {
                    $grouped = [];

                    foreach ($categories as $category) {
                        $grouped[$category->id] = [
                            'category' => $category,
                            'faqs' => FAQ::where('category_id', $category->id)
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
                                ->orderBy('question')
                                ->get()
                        ];
                    }

                    return $grouped;
                });

                // Подготавливаем мета-теги
                $metaTags = $this->prepareMetaTags();

                return view('frontend.faq.index', [
                    'groupedFaqs' => $groupedFaqs,
                    'categories' => $categories,
                    'popularFaqs' => $popularFaqs,
                    'metaTags' => $metaTags,
                    'breadcrumbs' => $this->getBreadcrumbs(),
                    'searchQuery' => $request->search,
                    'selectedCategory' => $request->category
                ]);
            }

            // Для API и при поиске/фильтрации - пагинация
            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $faqs = $query->paginate($perPage);

            // Увеличиваем счетчик просмотров для популярных
            $this->incrementPopularViews($popularFaqs);

            // Для API возвращаем JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'faqs' => $faqs,
                        'categories' => $categories,
                        'popular_faqs' => $popularFaqs,
                        'meta' => [
                            'total' => $faqs->total(),
                            'per_page' => $faqs->perPage(),
                            'current_page' => $faqs->currentPage(),
                            'last_page' => $faqs->lastPage(),
                        ]
                    ]
                ]);
            }

            // Для веб-интерфейса с поиском/фильтрацией
            return view('frontend.faq.list', [
                'faqs' => $faqs,
                'categories' => $categories,
                'popularFaqs' => $popularFaqs,
                'metaTags' => $this->prepareMetaTags(),
                'breadcrumbs' => $this->getBreadcrumbs(),
                'searchQuery' => $request->search,
                'selectedCategory' => $request->category,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при получении FAQ',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return view('frontend.errors.500', [
                'message' => 'Ошибка при загрузке FAQ'
            ]);
        }
    }

    /**
     * Display FAQ categories.
     */
    public function categories(Request $request)
    {
        try {
            $categories = Cache::remember('faq_categories_all', 3600, function () {
                return FAQCategory::where('status', 'active')
                    ->whereHas('faqs', function ($query) {
                        $query->where('status', 'published')
                            ->where(function ($q) {
                                $q->whereNull('published_at')
                                    ->orWhere('published_at', '<=', now());
                            })
                            ->where(function ($q) {
                                $q->whereNull('expired_at')
                                    ->orWhere('expired_at', '>=', now());
                            });
                    })
                    ->withCount(['faqs' => function ($query) {
                        $query->where('status', 'published')
                            ->where(function ($q) {
                                $q->whereNull('published_at')
                                    ->orWhere('published_at', '<=', now());
                            })
                            ->where(function ($q) {
                                $q->whereNull('expired_at')
                                    ->orWhere('expired_at', '>=', now());
                            });
                    }])
                    ->orderBy('order')
                    ->orderBy('name')
                    ->get();
            });

            // Для API возвращаем JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $categories
                ]);
            }

            return view('frontend.faq.categories', [
                'categories' => $categories,
                'metaTags' => $this->prepareCategoriesMetaTags(),
                'breadcrumbs' => $this->getCategoriesBreadcrumbs()
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при получении категорий FAQ'
                ], 500);
            }

            return view('frontend.errors.500', [
                'message' => 'Ошибка при загрузке категорий FAQ'
            ]);
        }
    }

    /**
     * Display FAQ category.
     */
    public function category(Request $request, $slug)
    {
        try {
            $category = Cache::remember("faq_category_{$slug}", 3600, function () use ($slug) {
                return FAQCategory::where('slug', $slug)
                    ->where('status', 'active')
                    ->withCount(['faqs' => function ($query) {
                        $query->where('status', 'published')
                            ->where(function ($q) {
                                $q->whereNull('published_at')
                                    ->orWhere('published_at', '<=', now());
                            })
                            ->where(function ($q) {
                                $q->whereNull('expired_at')
                                    ->orWhere('expired_at', '>=', now());
                            });
                    }])
                    ->first();
            });

            if (!$category) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Категория не найдена'
                    ], 404);
                }

                abort(404, 'Категория FAQ не найдена');
            }

            $query = FAQ::where('category_id', $category->id)
                ->where('status', 'published')
                ->where(function ($query) {
                    $query->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>=', now());
                });

            // Сортировка
            $sortBy = $request->get('sort_by', 'order');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Пагинация
            $perPage = $request->has('per_page') ? $request->per_page : 20;
            $faqs = $query->paginate($perPage);

            // Получаем другие категории
            $otherCategories = FAQCategory::where('id', '!=', $category->id)
                ->where('status', 'active')
                ->whereHas('faqs', function ($query) {
                    $query->where('status', 'published')
                        ->where(function ($q) {
                            $q->whereNull('published_at')
                                ->orWhere('published_at', '<=', now());
                        })
                        ->where(function ($q) {
                            $q->whereNull('expired_at')
                                ->orWhere('expired_at', '>=', now());
                        });
                })
                ->orderBy('order')
                ->orderBy('name')
                ->limit(6)
                ->get();

            // Для API возвращаем JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'category' => $category,
                        'faqs' => $faqs,
                        'other_categories' => $otherCategories,
                        'meta' => [
                            'total' => $faqs->total(),
                            'per_page' => $faqs->perPage(),
                            'current_page' => $faqs->currentPage(),
                            'last_page' => $faqs->lastPage(),
                        ]
                    ]
                ]);
            }

            return view('frontend.faq.category', [
                'category' => $category,
                'faqs' => $faqs,
                'otherCategories' => $otherCategories,
                'metaTags' => $this->prepareCategoryMetaTags($category),
                'breadcrumbs' => $this->getCategoryBreadcrumbs($category),
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при получении категории FAQ'
                ], 500);
            }

            return view('frontend.errors.500', [
                'message' => 'Ошибка при загрузке категории FAQ'
            ]);
        }
    }

    /**
     * Display the specified FAQ.
     */
    public function show(Request $request, $slug)
    {
        try {
            $faq = Cache::remember("faq_{$slug}", 3600, function () use ($slug) {
                return FAQ::with('category')
                    ->where('slug', $slug)
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

            if (!$faq) {
                // Пробуем найти по ID
                if (is_numeric($slug)) {
                    $faq = FAQ::with('category')
                        ->where('id', $slug)
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

                    if ($faq && $faq->slug !== $slug) {
                        // Редирект на правильный slug
                        return redirect()->route('faq.show', $faq->slug, 301);
                    }
                }

                if (!$faq) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'FAQ не найден'
                        ], 404);
                    }

                    abort(404, 'FAQ не найден');
                }
            }

            // Увеличиваем счетчик просмотров
            $this->incrementViewCount($faq);

            // Получаем связанные FAQ
            $relatedFaqs = $this->getRelatedFaqs($faq);

            // Получаем популярные FAQ категории
            $popularCategoryFaqs = FAQ::where('category_id', $faq->category_id)
                ->where('id', '!=', $faq->id)
                ->where('status', 'published')
                ->where(function ($query) {
                    $query->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>=', now());
                })
                ->orderBy('views', 'desc')
                ->limit(5)
                ->get();

            // Для API возвращаем JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'faq' => $faq,
                        'related_faqs' => $relatedFaqs,
                        'popular_category_faqs' => $popularCategoryFaqs
                    ]
                ]);
            }

            return view('frontend.faq.show', [
                'faq' => $faq,
                'relatedFaqs' => $relatedFaqs,
                'popularCategoryFaqs' => $popularCategoryFaqs,
                'metaTags' => $this->prepareFaqMetaTags($faq),
                'breadcrumbs' => $this->getFaqBreadcrumbs($faq)
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при получении FAQ'
                ], 500);
            }

            return view('frontend.errors.500', [
                'message' => 'Ошибка при загрузке FAQ'
            ]);
        }
    }

    /**
     * Search FAQs.
     */
    public function search(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'q' => 'required|string|min:2|max:100'
            ]);

            if ($validator->fails()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Минимум 2 символа для поиска',
                        'errors' => $validator->errors()
                    ], 422);
                }

                return redirect()->route('faq.index')
                    ->with('error', 'Минимум 2 символа для поиска');
            }

            $query = $request->q;

            $faqs = FAQ::with('category')
                ->where('status', 'published')
                ->where(function ($q) use ($query) {
                    $q->where('question', 'LIKE', "%{$query}%")
                        ->orWhere('answer', 'LIKE', "%{$query}%")
                        ->orWhere('tags', 'LIKE', "%{$query}%");
                })
                ->where(function ($query) {
                    $query->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>=', now());
                })
                ->orderByRaw("
                    CASE
                        WHEN question LIKE ? THEN 1
                        WHEN answer LIKE ? THEN 2
                        ELSE 3
                    END
                ", ["%{$query}%", "%{$query}%"])
                ->paginate(20);

            // Получаем категории для фильтра
            $categories = FAQCategory::where('status', 'active')
                ->whereHas('faqs', function ($q) use ($query) {
                    $q->where('status', 'published')
                        ->where(function ($q2) use ($query) {
                            $q2->where('question', 'LIKE', "%{$query}%")
                                ->orWhere('answer', 'LIKE', "%{$query}%")
                                ->orWhere('tags', 'LIKE', "%{$query}%");
                        });
                })
                ->orderBy('name')
                ->get();

            // Для API возвращаем JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'faqs' => $faqs,
                        'search_query' => $query,
                        'categories' => $categories,
                        'meta' => [
                            'total' => $faqs->total(),
                            'per_page' => $faqs->perPage(),
                            'current_page' => $faqs->currentPage(),
                            'last_page' => $faqs->lastPage(),
                        ]
                    ]
                ]);
            }

            return view('frontend.faq.search', [
                'faqs' => $faqs,
                'searchQuery' => $query,
                'categories' => $categories,
                'metaTags' => $this->prepareSearchMetaTags($query),
                'breadcrumbs' => $this->getSearchBreadcrumbs($query)
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при поиске FAQ'
                ], 500);
            }

            return view('frontend.errors.500', [
                'message' => 'Ошибка при поиске FAQ'
            ]);
        }
    }

    /**
     * Submit a new question.
     */
    public function submitQuestion(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'question' => 'required|string|min:10|max:500',
                'email' => 'required|email|max:100',
                'name' => 'required|string|max:100',
                'category_id' => 'nullable|exists:faq_categories,id',
                'phone' => 'nullable|string|max:20',
                'agree_to_terms' => 'required|accepted'
            ]);

            if ($validator->fails()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ошибка валидации',
                        'errors' => $validator->errors()
                    ], 422);
                }

                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            // Создаем запись в предложенных вопросах
            $suggestedQuestion = \App\Models\SuggestedQuestion::create([
                'question' => $request->question,
                'email' => $request->email,
                'name' => $request->name,
                'category_id' => $request->category_id,
                'phone' => $request->phone,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'pending'
            ]);

            // Отправляем уведомление администратору
            $this->sendNotificationToAdmin($suggestedQuestion);

            // Отправляем подтверждение пользователю
            $this->sendConfirmationToUser($suggestedQuestion);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Ваш вопрос успешно отправлен. Мы рассмотрим его и добавим в FAQ.',
                    'data' => $suggestedQuestion
                ]);
            }

            return redirect()->route('faq.index')
                ->with('success', 'Ваш вопрос успешно отправлен. Мы рассмотрим его и добавим в FAQ.');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при отправке вопроса'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Ошибка при отправке вопроса')
                ->withInput();
        }
    }

    /**
     * Rate FAQ (helpful/not helpful).
     */
    public function rate(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'rating' => 'required|in:helpful,not_helpful',
                'feedback' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $faq = FAQ::findOrFail($id);

            // Проверяем, не голосовал ли уже пользователь с этого IP
            $ip = $request->ip();
            $cacheKey = "faq_rating_{$faq->id}_{$ip}";

            if (Cache::has($cacheKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Вы уже оценили этот FAQ'
                ], 400);
            }

            // Обновляем рейтинг
            if ($request->rating === 'helpful') {
                $faq->increment('helpful_count');
            } else {
                $faq->increment('not_helpful_count');
            }

            // Сохраняем фидбек
            if ($request->feedback) {
                \App\Models\FAQFeedback::create([
                    'faq_id' => $faq->id,
                    'rating' => $request->rating,
                    'feedback' => $request->feedback,
                    'ip_address' => $ip,
                    'user_agent' => $request->userAgent()
                ]);
            }

            // Кэшируем голос на 24 часа
            Cache::put($cacheKey, true, 86400);

            return response()->json([
                'success' => true,
                'message' => 'Спасибо за вашу оценку!',
                'data' => [
                    'helpful_count' => $faq->helpful_count,
                    'not_helpful_count' => $faq->not_helpful_count
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при оценке FAQ'
            ], 500);
        }
    }

    /**
     * Get FAQs for API.
     */
    public function getFaqs(Request $request)
    {
        return $this->index($request);
    }

    /**
     * Get FAQ by slug for API.
     */
    public function getFaqBySlug(Request $request, $slug)
    {
        return $this->show($request, $slug);
    }

    /**
     * Get FAQ categories for API.
     */
    public function getCategories(Request $request)
    {
        return $this->categories($request);
    }

    /**
     * Get random FAQs for widget.
     */
    public function random(Request $request)
    {
        try {
            $limit = $request->get('limit', 5);

            $faqs = Cache::remember("faq_random_{$limit}", 1800, function () use ($limit) {
                return FAQ::where('status', 'published')
                    ->where(function ($query) {
                        $query->whereNull('published_at')
                            ->orWhere('published_at', '<=', now());
                    })
                    ->where(function ($query) {
                        $query->whereNull('expired_at')
                            ->orWhere('expired_at', '>=', now());
                    })
                    ->inRandomOrder()
                    ->limit($limit)
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => $faqs
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении случайных FAQ'
            ], 500);
        }
    }

    /**
     * Get most viewed FAQs.
     */
    public function mostViewed(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            $categoryId = $request->get('category_id');

            $query = FAQ::where('status', 'published')
                ->where(function ($query) {
                    $query->whereNull('published_at')
                        ->orWhere('published_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('expired_at')
                        ->orWhere('expired_at', '>=', now());
                });

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            $faqs = $query->orderBy('views', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $faqs
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении популярных FAQ'
            ], 500);
        }
    }

    /**
     * Helper method to increment view count.
     */
    private function incrementViewCount($faq)
    {
        $cacheKey = "faq_views_{$faq->id}";
        $views = Cache::get($cacheKey, 0) + 1;

        Cache::put($cacheKey, $views, 3600);

        if ($views % 5 === 0) {
            $faq->increment('views', 5);
        }
    }

    /**
     * Helper method to increment popular views.
     */
    private function incrementPopularViews($popularFaqs)
    {
        foreach ($popularFaqs as $faq) {
            $this->incrementViewCount($faq);
        }
    }

    /**
     * Helper method to get related FAQs.
     */
    private function getRelatedFaqs($faq)
    {
        return Cache::remember("related_faqs_{$faq->id}", 1800, function () use ($faq) {
            $query = FAQ::where('id', '!=', $faq->id)
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
            if ($faq->category_id) {
                $query->where('category_id', $faq->category_id);
            }

            // Ищем по тегам
            if ($faq->tags) {
                $tags = json_decode($faq->tags, true) ?: [];
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
     * Helper method to prepare meta tags for FAQ index.
     */
    private function prepareMetaTags()
    {
        return [
            'title' => 'Часто задаваемые вопросы (FAQ) | Наш отель',
            'description' => 'Ответы на часто задаваемые вопросы об отеле, бронировании, услугах и правилах проживания.',
            'keywords' => 'FAQ, вопросы, ответы, помощь, отель, бронирование',
            'og_title' => 'Часто задаваемые вопросы (FAQ) | Наш отель',
            'og_description' => 'Ответы на часто задаваемые вопросы об отеле',
            'og_type' => 'website',
            'canonical_url' => route('faq.index'),
            'robots' => 'index, follow',
        ];
    }

    /**
     * Helper method to prepare meta tags for categories.
     */
    private function prepareCategoriesMetaTags()
    {
        return [
            'title' => 'Категории FAQ | Наш отель',
            'description' => 'Категории часто задаваемых вопросов об отеле и услугах.',
            'keywords' => 'категории FAQ, разделы вопросов, темы',
            'og_title' => 'Категории FAQ | Наш отель',
            'og_description' => 'Категории часто задаваемых вопросов',
            'og_type' => 'website',
            'canonical_url' => route('faq.categories'),
            'robots' => 'index, follow',
        ];
    }

    /**
     * Helper method to prepare meta tags for category.
     */
    private function prepareCategoryMetaTags($category)
    {
        return [
            'title' => "{$category->name} - FAQ | Наш отель",
            'description' => $category->description ?: "Вопросы и ответы в категории {$category->name}",
            'keywords' => "FAQ {$category->name}, вопросы {$category->name}, {$category->name}",
            'og_title' => "{$category->name} - FAQ | Наш отель",
            'og_description' => $category->description ?: "Вопросы и ответы в категории {$category->name}",
            'og_type' => 'website',
            'canonical_url' => route('faq.category', $category->slug),
            'robots' => 'index, follow',
        ];
    }

    /**
     * Helper method to prepare meta tags for FAQ.
     */
    private function prepareFaqMetaTags($faq)
    {
        return [
            'title' => "{$faq->question} | FAQ | Наш отель",
            'description' => $faq->excerpt ?: substr(strip_tags($faq->answer), 0, 160),
            'keywords' => $faq->tags ?: "FAQ, {$faq->category->name}",
            'og_title' => $faq->question,
            'og_description' => $faq->excerpt ?: substr(strip_tags($faq->answer), 0, 160),
            'og_type' => 'article',
            'canonical_url' => route('faq.show', $faq->slug),
            'robots' => 'index, follow',
        ];
    }

    /**
     * Helper method to prepare meta tags for search.
     */
    private function prepareSearchMetaTags($query)
    {
        return [
            'title' => "Поиск: {$query} | FAQ | Наш отель",
            'description' => "Результаты поиска по запросу '{$query}' в FAQ",
            'keywords' => "поиск FAQ, {$query}, вопросы и ответы",
            'og_title' => "Поиск: {$query} | FAQ | Наш отель",
            'og_description' => "Результаты поиска по запросу '{$query}'",
            'og_type' => 'website',
            'canonical_url' => route('faq.search') . '?q=' . urlencode($query),
            'robots' => 'index, follow',
        ];
    }

    /**
     * Helper method to get breadcrumbs for FAQ index.
     */
    private function getBreadcrumbs()
    {
        return [
            ['url' => route('home'), 'title' => 'Главная'],
            ['url' => route('faq.index'), 'title' => 'FAQ', 'current' => true]
        ];
    }

    /**
     * Helper method to get breadcrumbs for categories.
     */
    private function getCategoriesBreadcrumbs()
    {
        return [
            ['url' => route('home'), 'title' => 'Главная'],
            ['url' => route('faq.index'), 'title' => 'FAQ'],
            ['url' => route('faq.categories'), 'title' => 'Категории', 'current' => true]
        ];
    }

    /**
     * Helper method to get breadcrumbs for category.
     */
    private function getCategoryBreadcrumbs($category)
    {
        return [
            ['url' => route('home'), 'title' => 'Главная'],
            ['url' => route('faq.index'), 'title' => 'FAQ'],
            ['url' => route('faq.category', $category->slug), 'title' => $category->name, 'current' => true]
        ];
    }

    /**
     * Helper method to get breadcrumbs for FAQ.
     */
    private function getFaqBreadcrumbs($faq)
    {
        return [
            ['url' => route('home'), 'title' => 'Главная'],
            ['url' => route('faq.index'), 'title' => 'FAQ'],
            ['url' => route('faq.category', $faq->category->slug), 'title' => $faq->category->name],
            ['url' => route('faq.show', $faq->slug), 'title' => $faq->question, 'current' => true]
        ];
    }

    /**
     * Helper method to get breadcrumbs for search.
     */
    private function getSearchBreadcrumbs($query)
    {
        return [
            ['url' => route('home'), 'title' => 'Главная'],
            ['url' => route('faq.index'), 'title' => 'FAQ'],
            ['url' => route('faq.search') . '?q=' . urlencode($query), 'title' => "Поиск: {$query}", 'current' => true]
        ];
    }

    /**
     * Helper method to send notification to admin.
     */
    private function sendNotificationToAdmin($suggestedQuestion)
    {
        try {
            // Отправляем email администратору
            \Illuminate\Support\Facades\Mail::to(config('mail.admin_email'))
                ->send(new \App\Mail\NewSuggestedQuestionMail($suggestedQuestion));

            // Создаем уведомление в системе
            \App\Models\Notification::create([
                'type' => 'new_suggested_question',
                'title' => 'Новый предложенный вопрос для FAQ',
                'message' => "Пользователь {$suggestedQuestion->name} предложил новый вопрос: {$suggestedQuestion->question}",
                'data' => [
                    'question_id' => $suggestedQuestion->id,
                    'question' => $suggestedQuestion->question,
                    'user_email' => $suggestedQuestion->email
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка при отправке уведомления администратору: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to send confirmation to user.
     */
    private function sendConfirmationToUser($suggestedQuestion)
    {
        try {
            \Illuminate\Support\Facades\Mail::to($suggestedQuestion->email)
                ->send(new \App\Mail\QuestionSubmittedConfirmationMail($suggestedQuestion));

        } catch (\Exception $e) {
            \Log::error('Ошибка при отправке подтверждения пользователю: ' . $e->getMessage());
        }
    }
}
