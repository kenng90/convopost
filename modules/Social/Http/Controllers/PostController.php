<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Social\Http\Requests\RejectSocialPostRequest;
use Modules\Social\Models\SocialPost;
use Modules\Social\Services\SocialPostApprovalService;

class PostController extends Controller
{
    public function __construct(private readonly SocialPostApprovalService $approvals)
    {
    }

    public function index(Request $request): View
    {
        $company = $request->user()->currentCompany();
        $status = (string) $request->query('status', 'all');
        $allowed = ['all', 'draft', 'scheduled', 'published', 'failed'];

        if (! in_array($status, $allowed, true)) {
            $status = 'all';
        }

        $posts = SocialPost::query()
            ->with(['defaultVersion', 'accounts', 'offerLink', 'postAccounts'])
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
            'canReviewApprovals' => $this->approvals->canReview($request->user()),
        ]);
    }

    public function create(): View
    {
        return view('social::posts.create');
    }

    public function show(Request $request, SocialPost $post): View
    {
        $this->ensureCompanyPost($request, $post);

        $post->load([
            'defaultVersion',
            'versions',
            'accounts',
            'offerLink',
            'author',
            'reviewer',
            'activities.user',
            'postAccounts',
        ]);

        return view('social::posts.show', [
            'post' => $post,
            'canReview' => $this->approvals->canReview($request->user()),
            'canSubmit' => $this->approvals->canSubmit($request->user(), $post),
        ]);
    }

    public function submitForApproval(Request $request, SocialPost $post): RedirectResponse
    {
        $this->ensureCompanyPost($request, $post);
        $this->approvals->submit($post, $request->user());

        return redirect()
            ->route('social.posts.show', $post)
            ->with('success', __('Post submitted for approval.'));
    }

    public function approve(Request $request, SocialPost $post): RedirectResponse
    {
        $this->ensureCompanyPost($request, $post);
        $this->approvals->approve($post, $request->user());

        return redirect()
            ->route('social.posts.show', $post)
            ->with('success', __('Post approved.'));
    }

    public function reject(RejectSocialPostRequest $request, SocialPost $post): RedirectResponse
    {
        $this->ensureCompanyPost($request, $post);

        $this->approvals->reject($post, $request->user(), $request->validated('rejection_reason'));

        return redirect()
            ->route('social.posts.show', $post)
            ->with('success', __('Post rejected.'));
    }

    protected function ensureCompanyPost(Request $request, SocialPost $post): void
    {
        $company = $request->user()->currentCompany();

        if (! $company || (int) $post->company_id !== (int) $company->id) {
            abort(404);
        }
    }
}
