<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Social\Services\SocialInsightsService;

class InsightsController extends Controller
{
    public function __construct(private readonly SocialInsightsService $insights)
    {
    }

    public function index(Request $request): View
    {
        $company = $request->user()->currentCompany();

        $empty = [
            'content' => [
                'published_posts' => 0,
                'connected_accounts' => 0,
                'impressions' => 0,
                'reach' => 0,
                'engagement' => 0,
                'comments' => 0,
                'clicks' => 0,
            ],
            'commerce' => [
                'offer_clicks' => 0,
                'orders' => 0,
                'paid_orders' => 0,
                'revenue' => 0.0,
                'conversion_rate' => 0.0,
                'average_order_value' => 0.0,
            ],
            'totals' => [
                'impressions' => 0,
                'reach' => 0,
                'engagement' => 0,
                'clicks' => 0,
                'orders' => 0,
                'revenue' => 0.0,
            ],
            'posts' => collect(),
            'posts_that_sold' => collect(),
        ];

        $payload = $company ? $this->insights->forCompany($company) : $empty;

        return view('social::insights.index', [
            'content' => $payload['content'],
            'commerce' => $payload['commerce'],
            'totals' => $payload['totals'],
            'posts' => $payload['posts'],
            'postsThatSold' => $payload['posts_that_sold'],
            'company' => $company,
        ]);
    }
}
