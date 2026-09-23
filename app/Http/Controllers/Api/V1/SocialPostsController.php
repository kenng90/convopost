<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\Api\PublicApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Social\Http\Requests\StoreSocialPostRequest;
use Modules\Social\Models\SocialPost;
use Modules\Social\Services\SocialPostApprovalService;
use Modules\Social\Services\SocialPostComposerService;

class SocialPostsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request);
        [$posts, $meta] = $this->paginatePosts($request, $company);

        return PublicApiResponse::success($posts, 200, $meta);
    }

    public function show(Request $request, int $post): JsonResponse
    {
        $model = $this->findPost($request, $post);

        return PublicApiResponse::success($this->present($model));
    }

    public function store(Request $request): JsonResponse
    {
        $status = (string) $request->input('status', 'draft');
        $offerType = (string) $request->input('offer_type', 'none');

        $validated = $request->validate(StoreSocialPostRequest::composerRules($status, $offerType));

        $company = $this->company($request);
        $user = $this->user($request);

        $post = app(SocialPostComposerService::class)->create($company, $user, [
            'content' => $validated['content'],
            'account_ids' => $validated['account_ids'],
            'media_ids' => $validated['media_ids'] ?? [],
            'versions' => $validated['versions'] ?? [],
            'first_comment' => $validated['first_comment'] ?? null,
            'x_thread_replies' => $validated['x_thread_replies'] ?? [],
            'status' => $validated['status'],
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'offer_type' => $validated['offer_type'] ?? 'none',
            'offer_url' => $validated['offer_url'] ?? null,
            'offer_target_id' => $validated['offer_target_id'] ?? null,
        ]);

        return PublicApiResponse::success($this->present($post->fresh(['defaultVersion', 'postAccounts'])), 201);
    }

    public function schedule(Request $request, int $post): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $model = $this->findPost($request, $post);

        if (! in_array($model->status, ['draft', 'failed'], true)) {
            throw new HttpResponseException(
                PublicApiResponse::error(
                    'invalid_status',
                    'Only draft or failed posts can be scheduled.',
                    422
                )
            );
        }

        $model->forceFill([
            'status' => 'scheduled',
            'scheduled_at' => $validated['scheduled_at'],
        ])->save();

        app(SocialPostApprovalService::class)->record(
            $model,
            'scheduled',
            $this->user($request),
            __('Post scheduled via public API')
        );

        return PublicApiResponse::success($this->present($model->fresh(['defaultVersion', 'postAccounts'])));
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    public function paginatePosts(Request $request, Company $company): array
    {
        $limit = min(
            max((int) $request->input('limit', config('public-api.pagination.default_limit', 50)), 1),
            (int) config('public-api.pagination.max_limit', 100)
        );

        $query = SocialPost::query()
            ->with(['defaultVersion', 'postAccounts'])
            ->where('company_id', $company->id)
            ->orderByDesc('id');

        if ($request->filled('cursor')) {
            $query->where('id', '<', (int) $request->input('cursor'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $presented = $rows->map(fn (SocialPost $post) => $this->present($post))->values()->all();

        return [$presented, [
            'limit' => $limit,
            'next_cursor' => $hasMore ? (string) $rows->last()?->id : null,
            'has_more' => $hasMore,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(SocialPost $post): array
    {
        $post->loadMissing(['defaultVersion', 'postAccounts']);

        return [
            'id' => $post->id,
            'status' => $post->status,
            'approval_status' => $post->approval_status,
            'content' => $post->defaultVersion?->content,
            'first_comment' => $post->defaultVersion?->first_comment,
            'account_ids' => $post->postAccounts->pluck('social_account_id')->map(fn ($id) => (int) $id)->values()->all(),
            'scheduled_at' => optional($post->scheduled_at)?->toIso8601String(),
            'published_at' => optional($post->published_at)?->toIso8601String(),
            'created_at' => optional($post->created_at)?->toIso8601String(),
            'updated_at' => optional($post->updated_at)?->toIso8601String(),
        ];
    }

    private function findPost(Request $request, int $id): SocialPost
    {
        $post = SocialPost::query()
            ->with(['defaultVersion', 'postAccounts'])
            ->where('company_id', $this->company($request)->id)
            ->find($id);

        if (! $post) {
            throw new HttpResponseException(
                PublicApiResponse::error('not_found', 'Social post not found', 404)
            );
        }

        return $post;
    }

    private function company(Request $request): Company
    {
        return $request->attributes->get('public_api_company');
    }

    private function user(Request $request): User
    {
        return $request->attributes->get('public_api_user');
    }
}
