<?php

namespace App\Support;

use Akaunting\Module\Facade as Module;
use Modules\Blog\Models\Blog;
use Throwable;

class SitemapBuilder
{
    /**
     * Public URLs Google should crawl for the marketing site.
     *
     * @return array<int, array{loc: string, changefreq: string, priority: string, lastmod?: string}>
     */
    public function urls(): array
    {
        $urls = [
            $this->entry(url('/'), 'weekly', '1.0'),
            $this->entry(route('services.automation'), 'monthly', '0.8'),
            $this->entry(route('policy.show'), 'yearly', '0.4'),
            $this->entry(route('terms.show'), 'yearly', '0.4'),
            $this->entry(route('login'), 'monthly', '0.6'),
        ];

        if (! config('settings.disable_registration_page', false)) {
            $urls[] = $this->entry(route('register'), 'monthly', '0.6');
        }

        return array_merge($urls, $this->blogUrls());
    }

    /**
     * @return array<int, array{loc: string, changefreq: string, priority: string, lastmod?: string}>
     */
    private function blogUrls(): array
    {
        try {
            if (! Module::has('blog') || ! class_exists(Blog::class)) {
                return [];
            }

            $urls = [
                $this->entry(url('/blog'), 'weekly', '0.8'),
            ];

            $posts = Blog::query()
                ->where('status', 'published')
                ->orderByDesc('updated_at')
                ->get(['slug', 'updated_at']);
        } catch (Throwable $exception) {
            return [];
        }

        foreach ($posts as $post) {
            $urls[] = $this->entry(
                url('/blog/'.$post->slug),
                'monthly',
                '0.6',
                optional($post->updated_at)->toAtomString()
            );
        }

        return $urls;
    }

    /**
     * @return array{loc: string, changefreq: string, priority: string, lastmod?: string}
     */
    private function entry(string $loc, string $changefreq, string $priority, ?string $lastmod = null): array
    {
        $entry = [
            'loc' => $loc,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];

        if ($lastmod) {
            $entry['lastmod'] = $lastmod;
        }

        return $entry;
    }
}
