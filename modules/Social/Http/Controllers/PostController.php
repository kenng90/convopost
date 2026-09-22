<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Social\Models\SocialPost;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->currentCompany();
        $status = (string) $request->query('status', 'all');
        $allowed = ['all', 'draft', 'scheduled', 'published', 'failed'];

        if (! in_array($status, $allowed, true)) {
            $status = 'all';
        }

        $posts = SocialPost::query()
            ->with(['defaultVersion', 'accounts', 'offerLink'])
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('social::posts.index', [
            'posts' => $posts,
            'status' => $status,
            'statusFilters' => [
                'all' => __('All'),
                'draft' => __('Draft'),
                'scheduled' => __('Scheduled'),
                'published' => __('Published'),
                'failed' => __('Failed'),
            ],
        ]);
    }
}
