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

        $payload = $company
            ? $this->insights->forCompany($company)
            : [
                'totals' => [
                    'impressions' => 0,
                    'reach' => 0,
                    'engagement' => 0,
                    'clicks' => 0,
                    'orders' => 0,
                    'revenue' => 0.0,
                ],
                'posts' => collect(),
            ];

        return view('social::insights.index', [
            'totals' => $payload['totals'],
            'posts' => $payload['posts'],
            'company' => $company,
        ]);
    }
}
