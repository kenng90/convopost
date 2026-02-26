<?php
namespace Modules\Flowmaker\Services;
require __DIR__.'/../vendor/autoload.php';

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class WebsiteScraperService
{
    public function extractText(string $url): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; WebsiteScraperService/1.0)'
                ])
                ->get($url);
            
            if (!$response->successful()) {
                Log::error('Failed to fetch website: ' . $url . ' - Status: ' . $response->status());
                return [
                    'title' => '',
                    'url' => $url,
                    'content' => ''
                ];
            }

            $crawler = new Crawler($response->body());

            $title = $crawler->filter('title')->count() > 0 
                ? $crawler->filter('title')->text() 
                : '';
                
            $paragraphs = $crawler->filter('h1, h2, h3, p')->each(function ($node) {
                return $node->text();
            });

            $content = $title . "\n" . implode("\n", $paragraphs);

            return [
                'title' => $title,
                'url' => $url,
                'content' => $content
            ];
        } catch (\Exception $e) {
            Log::error('Error scraping website: ' . $e->getMessage());
            return [
                'title' => '',
                'url' => $url,
                'content' => ''
            ];
        }
    }
} 