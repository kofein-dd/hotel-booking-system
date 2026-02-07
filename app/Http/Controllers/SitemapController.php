<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Room;
use App\Models\Hotel;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Carbon\Carbon;

class SitemapController extends Controller
{
    /**
     * Главный sitemap index
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $sitemap = Cache::remember('sitemap_index', 3600, function () {
                $sitemapIndex = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                $sitemapIndex .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

                // Добавляем разделы сайта
                $sections = [
                    ['url' => route('sitemap.static'), 'lastmod' => $this->getStaticLastMod()],
                    ['url' => route('sitemap.rooms'), 'lastmod' => $this->getRoomsLastMod()],
                    ['url' => route('sitemap.blog'), 'lastmod' => $this->getBlogLastMod()],
                    ['url' => route('sitemap.pages'), 'lastmod' => $this->getPagesLastMod()],
                ];

                // Добавляем разделы по отелям, если их несколько
                $hotelsCount = Hotel::where('status', 'active')->count();
                if ($hotelsCount > 0) {
                    $sections[] = ['url' => route('sitemap.hotels'), 'lastmod' => $this->getHotelsLastMod()];
                }

                foreach ($sections as $section) {
                    $sitemapIndex .= "\t<sitemap>\n";
                    $sitemapIndex .= "\t\t<loc>" . htmlspecialchars($section['url']) . "</loc>\n";
                    $sitemapIndex .= "\t\t<lastmod>" . $section['lastmod'] . "</lastmod>\n";
                    $sitemapIndex .= "\t</sitemap>\n";
                }

                $sitemapIndex .= '</sitemapindex>';

                return $sitemapIndex;
            });

            return response($sitemap, 200, [
                'Content-Type' => 'application/xml'
            ]);

        } catch (\Exception $e) {
            \Log::error('Sitemap index generation error: ' . $e->getMessage());
            return $this->generateEmptySitemap();
        }
    }

    /**
     * Статические страницы
     *
     * @return \Illuminate\Http\Response
     */
    public function staticPages()
    {
        try {
            $sitemap = Cache::remember('sitemap_static', 3600, function () {
                $urls = $this->getStaticUrls();
                return $this->generateSitemapXml($urls);
            });

            return response($sitemap, 200, [
                'Content-Type' => 'application/xml'
            ]);

        } catch (\Exception $e) {
            \Log::error('Static sitemap generation error: ' . $e->getMessage());
            return $this->generateEmptySitemap();
        }
    }

    /**
     * Страницы номеров
     *
     * @return \Illuminate\Http\Response
     */
    public function rooms()
    {
        try {
            $sitemap = Cache::remember('sitemap_rooms', 3600, function () {
                $urls = $this->getRoomUrls();
                return $this->generateSitemapXml($urls);
            });

            return response($sitemap, 200, [
                'Content-Type' => 'application/xml'
            ]);

        } catch (\Exception $e) {
            \Log::error('Rooms sitemap generation error: ' . $e->getMessage());
            return $this->generateEmptySitemap();
        }
    }

    /**
     * Блог
     *
     * @return \Illuminate\Http\Response
     */
    public function blog()
    {
        try {
            $sitemap = Cache::remember('sitemap_blog', 3600, function () {
                $urls = $this->getBlogUrls();
                return $this->generateSitemapXml($urls);
            });

            return response($sitemap, 200, [
                'Content-Type' => 'application/xml'
            ]);

        } catch (\Exception $e) {
            \Log::error('Blog sitemap generation error: ' . $e->getMessage());
            return $this->generateEmptySitemap();
        }
    }

    /**
     * Динамические страницы (из админки)
     *
     * @return \Illuminate\Http\Response
     */
    public function pages()
    {
        try {
            $sitemap = Cache::remember('sitemap_pages', 3600, function () {
                $urls = $this->getPageUrls();
                return $this->generateSitemapXml($urls);
            });

            return response($sitemap, 200, [
                'Content-Type' => 'application/xml'
            ]);

        } catch (\Exception $e) {
            \Log::error('Pages sitemap generation error: ' . $e->getMessage());
            return $this->generateEmptySitemap();
        }
    }

    /**
     * Страницы отелей
     *
     * @return \Illuminate\Http\Response
     */
    public function hotels()
    {
        try {
            $sitemap = Cache::remember('sitemap_hotels', 3600, function () {
                $urls = $this->getHotelUrls();
                return $this->generateSitemapXml($urls);
            });

            return response($sitemap, 200, [
                'Content-Type' => 'application/xml'
            ]);

        } catch (\Exception $e) {
            \Log::error('Hotels sitemap generation error: ' . $e->getMessage());
            return $this->generateEmptySitemap();
        }
    }

    /**
     * Получение статических URL
     *
     * @return array
     */
    private function getStaticUrls(): array
    {
        $urls = [];

        // Главная страница
        $urls[] = [
            'loc' => url('/'),
            'lastmod' => now()->toIso8601String(),
            'changefreq' => 'daily',
            'priority' => '1.0',
            'images' => $this->getHomePageImages()
        ];

        // Основные разделы
        $staticRoutes = [
            ['route' => 'rooms.index', 'freq' => 'daily', 'priority' => '0.9'],
            ['route' => 'search', 'freq' => 'daily', 'priority' => '0.8'],
            ['route' => 'about', 'freq' => 'monthly', 'priority' => '0.7'],
            ['route' => 'contact', 'freq' => 'monthly', 'priority' => '0.7'],
            ['route' => 'faq', 'freq' => 'monthly', 'priority' => '0.7'],
            ['route' => 'reviews.index', 'freq' => 'daily', 'priority' => '0.8'],
            ['route' => 'blog.index', 'freq' => 'daily', 'priority' => '0.8'],
            ['route' => 'login', 'freq' => 'monthly', 'priority' => '0.3'],
            ['route' => 'register', 'freq' => 'monthly', 'priority' => '0.3'],
            ['route' => 'privacy', 'freq' => 'yearly', 'priority' => '0.5'],
            ['route' => 'terms', 'freq' => 'yearly', 'priority' => '0.5'],
            ['route' => 'cancellation', 'freq' => 'yearly', 'priority' => '0.5'],
        ];

        foreach ($staticRoutes as $route) {
            if (Route::has($route['route'])) {
                $urls[] = [
                    'loc' => route($route['route']),
                    'lastmod' => now()->toIso8601String(),
                    'changefreq' => $route['freq'],
                    'priority' => $route['priority']
                ];
            }
        }

        return $urls;
    }

    /**
     * Получение URL номеров
     *
     * @return array
     */
    private function getRoomUrls(): array
    {
        $urls = [];

        $rooms = Room::where('status', 'active')
            ->whereHas('hotel', function ($query) {
                $query->where('status', 'active');
            })
            ->with(['hotel', 'amenities'])
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($rooms as $room) {
            $url = route('rooms.show', ['room' => $room->id, 'slug' => $room->slug]);

            $urls[] = [
                'loc' => $url,
                'lastmod' => $room->updated_at->toIso8601String(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
                'images' => $this->getRoomImages($room),
                'mobile' => true
            ];
        }

        // Если номеров много, возвращаем только последние 1000
        if (count($urls) > 1000) {
            $urls = array_slice($urls, 0, 1000);
        }

        return $urls;
    }

    /**
     * Получение URL блога
     *
     * @return array
     */
    private function getBlogUrls(): array
    {
        $urls = [];

        // Главная страница блога
        if (Route::has('blog.index')) {
            $urls[] = [
                'loc' => route('blog.index'),
                'lastmod' => now()->toIso8601String(),
                'changefreq' => 'daily',
                'priority' => '0.8'
            ];
        }

        // Категории блога
        $categories = \App\Models\BlogCategory::where('status', 'active')
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($categories as $category) {
            if (Route::has('blog.category')) {
                $urls[] = [
                    'loc' => route('blog.category', ['category' => $category->slug]),
                    'lastmod' => $category->updated_at->toIso8601String(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7'
                ];
            }
        }

        // Посты блога
        $posts = BlogPost::where('status', 'published')
            ->where('published_at', '<=', now())
            ->orderBy('published_at', 'desc')
            ->limit(1000)
            ->get();

        foreach ($posts as $post) {
            if (Route::has('blog.show')) {
                $url = route('blog.show', ['post' => $post->slug]);

                $urls[] = [
                    'loc' => $url,
                    'lastmod' => $post->updated_at->toIso8601String(),
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                    'images' => $this->getBlogPostImages($post)
                ];
            }
        }

        return $urls;
    }

    /**
     * Получение URL динамических страниц
     *
     * @return array
     */
    private function getPageUrls(): array
    {
        $urls = [];

        $pages = Page::where('status', 'published')
            ->where('is_sitemap', true)
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($pages as $page) {
            $url = url('/page/' . $page->slug);

            $urls[] = [
                'loc' => $url,
                'lastmod' => $page->updated_at->toIso8601String(),
                'changefreq' => $this->getPageChangeFreq($page),
                'priority' => $this->getPagePriority($page)
            ];
        }

        return $urls;
    }

    /**
     * Получение URL отелей
     *
     * @return array
     */
    private function getHotelUrls(): array
    {
        $urls = [];

        $hotels = Hotel::where('status', 'active')
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($hotels as $hotel) {
            if (Route::has('hotels.show')) {
                $url = route('hotels.show', ['hotel' => $hotel->id, 'slug' => $hotel->slug]);

                $urls[] = [
                    'loc' => $url,
                    'lastmod' => $hotel->updated_at->toIso8601String(),
                    'changefreq' => 'weekly',
                    'priority' => '0.9',
                    'images' => $this->getHotelImages($hotel)
                ];
            }
        }

        return $urls;
    }

    /**
     * Генерация XML для sitemap
     *
     * @param array $urls
     * @return string
     */
    private function generateSitemapXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        $xml .= ' xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0"';
        $xml .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($urls as $urlData) {
            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . htmlspecialchars($urlData['loc']) . "</loc>\n";

            if (isset($urlData['lastmod'])) {
                $xml .= "\t\t<lastmod>" . $urlData['lastmod'] . "</lastmod>\n";
            }

            if (isset($urlData['changefreq'])) {
                $xml .= "\t\t<changefreq>" . $urlData['changefreq'] . "</changefreq>\n";
            }

            if (isset($urlData['priority'])) {
                $xml .= "\t\t<priority>" . $urlData['priority'] . "</priority>\n";
            }

            // Добавляем изображения
            if (!empty($urlData['images'])) {
                foreach ($urlData['images'] as $image) {
                    $xml .= "\t\t<image:image>\n";
                    $xml .= "\t\t\t<image:loc>" . htmlspecialchars($image['loc']) . "</image:loc>\n";

                    if (isset($image['title'])) {
                        $xml .= "\t\t\t<image:title>" . htmlspecialchars($image['title']) . "</image:title>\n";
                    }

                    if (isset($image['caption'])) {
                        $xml .= "\t\t\t<image:caption>" . htmlspecialchars($image['caption']) . "</image:caption>\n";
                    }

                    $xml .= "\t\t</image:image>\n";
                }
            }

            // Добавляем мобильную версию
            if (!empty($urlData['mobile'])) {
                $xml .= "\t\t<mobile:mobile/>\n";
            }

            // Добавляем альтернативные языки (если есть)
            if (!empty($urlData['alternates'])) {
                foreach ($urlData['alternates'] as $lang => $altUrl) {
                    $xml .= "\t\t<xhtml:link rel=\"alternate\" hreflang=\"{$lang}\" href=\"" . htmlspecialchars($altUrl) . "\"/>\n";
                }
            }

            $xml .= "\t</url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Получение изображений для главной страницы
     *
     * @return array
     */
    private function getHomePageImages(): array
    {
        $images = [];

        // Основное изображение отеля
        $hotel = Hotel::where('status', 'active')->first();
        if ($hotel && !empty($hotel->main_photo)) {
            $images[] = [
                'loc' => asset('storage/' . $hotel->main_photo),
                'title' => $hotel->name . ' - Главное фото',
                'caption' => $hotel->description ? substr(strip_tags($hotel->description), 0, 200) : ''
            ];
        }

        // Дополнительные изображения из настроек
        $galleryImages = config('sitemap.homepage_images', []);
        foreach ($galleryImages as $image) {
            if (file_exists(public_path($image))) {
                $images[] = [
                    'loc' => asset($image),
                    'title' => 'Фото отеля'
                ];
            }
        }

        return array_slice($images, 0, 10); // Ограничиваем 10 изображениями
    }

    /**
     * Получение изображений номера
     *
     * @param Room $room
     * @return array
     */
    private function getRoomImages(Room $room): array
    {
        $images = [];

        // Основное фото номера
        if (!empty($room->main_photo)) {
            $images[] = [
                'loc' => asset('storage/' . $room->main_photo),
                'title' => $room->name . ' - Главное фото',
                'caption' => $room->description ? substr(strip_tags($room->description), 0, 200) : ''
            ];
        }

        // Дополнительные фото
        $roomPhotos = $room->photos ?? [];
        if (is_string($roomPhotos)) {
            $roomPhotos = json_decode($roomPhotos, true) ?? [];
        }

        foreach ($roomPhotos as $index => $photo) {
            if ($index >= 10) break; // Ограничиваем 10 изображениями

            $images[] = [
                'loc' => asset('storage/' . $photo),
                'title' => $room->name . ' - Фото ' . ($index + 1)
            ];
        }

        return $images;
    }

    /**
     * Получение изображений поста блога
     *
     * @param BlogPost $post
     * @return array
     */
    private function getBlogPostImages(BlogPost $post): array
    {
        $images = [];

        // Изображение поста
        if (!empty($post->image)) {
            $images[] = [
                'loc' => asset('storage/' . $post->image),
                'title' => $post->title . ' - Изображение',
                'caption' => $post->excerpt ? substr(strip_tags($post->excerpt), 0, 200) : ''
            ];
        }

        // Изображения из контента (базовая реализация)
        preg_match_all('/<img[^>]+src="([^">]+)"/', $post->content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $index => $src) {
                if ($index >= 5) break; // Ограничиваем 5 изображениями

                // Преобразуем относительные URL в абсолютные
                if (strpos($src, 'http') !== 0) {
                    $src = asset($src);
                }

                $images[] = [
                    'loc' => $src,
                    'title' => $post->title . ' - Изображение в статье'
                ];
            }
        }

        return $images;
    }

    /**
     * Получение изображений отеля
     *
     * @param Hotel $hotel
     * @return array
     */
    private function getHotelImages(Hotel $hotel): array
    {
        $images = [];

        // Главное фото отеля
        if (!empty($hotel->main_photo)) {
            $images[] = [
                'loc' => asset('storage/' . $hotel->main_photo),
                'title' => $hotel->name . ' - Главное фото',
                'caption' => $hotel->description ? substr(strip_tags($hotel->description), 0, 200) : ''
            ];
        }

        // Галерея отеля
        $hotelPhotos = $hotel->photos ?? [];
        if (is_string($hotelPhotos)) {
            $hotelPhotos = json_decode($hotelPhotos, true) ?? [];
        }

        foreach ($hotelPhotos as $index => $photo) {
            if ($index >= 10) break; // Ограничиваем 10 изображениями

            $images[] = [
                'loc' => asset('storage/' . $photo),
                'title' => $hotel->name . ' - Фото ' . ($index + 1)
            ];
        }

        return $images;
    }

    /**
     * Определение частоты обновления для страницы
     *
     * @param Page $page
     * @return string
     */
    private function getPageChangeFreq(Page $page): string
    {
        $typeToFreq = [
            'home' => 'daily',
            'contact' => 'monthly',
            'about' => 'monthly',
            'terms' => 'yearly',
            'privacy' => 'yearly',
            'faq' => 'monthly',
            'services' => 'monthly'
        ];

        return $typeToFreq[$page->type] ?? 'monthly';
    }

    /**
     * Определение приоритета для страницы
     *
     * @param Page $page
     * @return string
     */
    private function getPagePriority(Page $page): string
    {
        $typeToPriority = [
            'home' => '1.0',
            'contact' => '0.7',
            'about' => '0.7',
            'terms' => '0.5',
            'privacy' => '0.5',
            'faq' => '0.6',
            'services' => '0.6'
        ];

        return $typeToPriority[$page->type] ?? '0.5';
    }

    /**
     * Получение даты последнего изменения статических страниц
     *
     * @return string
     */
    private function getStaticLastMod(): string
    {
        return now()->toIso8601String();
    }

    /**
     * Получение даты последнего изменения номеров
     *
     * @return string
     */
    private function getRoomsLastMod(): string
    {
        $latestRoom = Room::where('status', 'active')
            ->orderBy('updated_at', 'desc')
            ->first();

        return $latestRoom ? $latestRoom->updated_at->toIso8601String() : now()->toIso8601String();
    }

    /**
     * Получение даты последнего изменения блога
     *
     * @return string
     */
    private function getBlogLastMod(): string
    {
        $latestPost = BlogPost::where('status', 'published')
            ->where('published_at', '<=', now())
            ->orderBy('updated_at', 'desc')
            ->first();

        return $latestPost ? $latestPost->updated_at->toIso8601String() : now()->toIso8601String();
    }

    /**
     * Получение даты последнего изменения страниц
     *
     * @return string
     */
    private function getPagesLastMod(): string
    {
        $latestPage = Page::where('status', 'published')
            ->orderBy('updated_at', 'desc')
            ->first();

        return $latestPage ? $latestPage->updated_at->toIso8601String() : now()->toIso8601String();
    }

    /**
     * Получение даты последнего изменения отелей
     *
     * @return string
     */
    private function getHotelsLastMod(): string
    {
        $latestHotel = Hotel::where('status', 'active')
            ->orderBy('updated_at', 'desc')
            ->first();

        return $latestHotel ? $latestHotel->updated_at->toIso8601String() : now()->toIso8601String();
    }

    /**
     * Генерация пустого sitemap (при ошибках)
     *
     * @return \Illuminate\Http\Response
     */
    private function generateEmptySitemap()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= "\t<url>\n";
        $xml .= "\t\t<loc>" . url('/') . "</loc>\n";
        $xml .= "\t\t<lastmod>" . now()->toIso8601String() . "</lastmod>\n";
        $xml .= "\t\t<changefreq>daily</changefreq>\n";
        $xml .= "\t\t<priority>1.0</priority>\n";
        $xml .= "\t</url>\n";
        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml'
        ]);
    }

    /**
     * Роботс.txt
     *
     * @return \Illuminate\Http\Response
     */
    public function robots()
    {
        $robots = "User-agent: *\n";
        $robots .= "Disallow: /admin/\n";
        $robots .= "Disallow: /ajax/\n";
        $robots .= "Disallow: /api/\n";
        $robots .= "Disallow: /cron/\n";
        $robots .= "Disallow: /webhook/\n";
        $robots .= "Allow: /$\n";
        $robots .= "Allow: /rooms\n";
        $robots .= "Allow: /blog\n";
        $robots .= "\n";
        $robots .= "Sitemap: " . route('sitemap.index') . "\n";

        // Добавляем хосты для поисковых систем
        if (app()->environment('production')) {
            $robots .= "Host: " . config('app.url') . "\n";
        }

        return response($robots, 200, [
            'Content-Type' => 'text/plain'
        ]);
    }

    /**
     * Очистка кэша sitemap (для админки)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache(Request $request)
    {
        // Только для администраторов
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Cache::forget('sitemap_index');
        Cache::forget('sitemap_static');
        Cache::forget('sitemap_rooms');
        Cache::forget('sitemap_blog');
        Cache::forget('sitemap_pages');
        Cache::forget('sitemap_hotels');

        return response()->json([
            'success' => true,
            'message' => 'Sitemap cache cleared'
        ]);
    }

    /**
     * Статистика sitemap
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(Request $request)
    {
        // Только для администраторов
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = [
            'rooms' => Room::where('status', 'active')->count(),
            'pages' => Page::where('status', 'published')->where('is_sitemap', true)->count(),
            'blog_posts' => BlogPost::where('status', 'published')->where('published_at', '<=', now())->count(),
            'hotels' => Hotel::where('status', 'active')->count(),
            'cache_status' => [
                'index' => Cache::has('sitemap_index') ? 'cached' : 'not cached',
                'static' => Cache::has('sitemap_static') ? 'cached' : 'not cached',
                'rooms' => Cache::has('sitemap_rooms') ? 'cached' : 'not cached',
                'blog' => Cache::has('sitemap_blog') ? 'cached' : 'not cached',
                'pages' => Cache::has('sitemap_pages') ? 'cached' : 'not cached',
                'hotels' => Cache::has('sitemap_hotels') ? 'cached' : 'not cached'
            ],
            'last_generated' => [
                'index' => Cache::get('sitemap_last_generated_index', 'Never'),
                'rooms' => Cache::get('sitemap_last_generated_rooms', 'Never')
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Генерация sitemap по запросу (без кэша)
     *
     * @param Request $request
     * @param string $type
     * @return \Illuminate\Http\Response
     */
    public function generate(Request $request, string $type)
    {
        // Только для администраторов
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $methodName = $type . 'Pages';

        if (!method_exists($this, $methodName)) {
            return response()->json(['error' => 'Invalid sitemap type'], 400);
        }

        // Генерируем без кэша
        $sitemap = $this->$methodName();

        // Обновляем время генерации
        Cache::put('sitemap_last_generated_' . $type, now()->toIso8601String(), 86400);

        return $sitemap;
    }
}
