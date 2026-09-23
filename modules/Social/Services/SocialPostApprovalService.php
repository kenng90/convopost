<?php

namespace Modules\Social\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\PlanEntitlementResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostActivity;

class SocialPostApprovalService
{
    public function __construct(private readonly PlanEntitlementResolver $entitlements)
    {
    }

    public function approvalsEnabledForUser(User $user): bool
    {
        return $this->entitlements->userHasCapability($user, 'social_approvals');
    }

    public function canReview(User $user): bool
    {
        if (! $this->approvalsEnabledForUser($user)) {
            return false;
        }

        return $user->hasRole('owner') || $user->isOrganizationManager();
    }

    public function canSubmit(User $user, SocialPost $post): bool
    {
        if (! $this->approvalsEnabledForUser($user)) {
            return false;
        }

        if ($this->canReview($user)) {
            return false;
        }

        if (! in_array($post->approval_status, ['none', 'rejected'], true)) {
            return false;
        }

        if (! in_array($post->status, ['draft', 'scheduled'], true)) {
            return false;
        }

        return (int) $post->user_id === (int) $user->id
            || $user->hasRole('staff')
            || $user->isOrganizationAgent();
    }

    public function record(SocialPost $post, string $action, ?User $user = null, ?string $message = null, array $meta = []): SocialPostActivity
    {
        return SocialPostActivity::query()->create([
            'company_id' => $post->company_id,
            'social_post_id' => $post->id,
            'user_id' => $user?->id,
            'action' => $action,
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    public function submit(SocialPost $post, User $user): SocialPost
    {
        if (! $this->canSubmit($user, $post)) {
            throw ValidationException::withMessages([
                'approval' => __('You cannot submit this post for approval.'),
            ]);
        }

        return DB::transaction(function () use ($post, $user) {
            $post->update([
                'approval_status' => 'pending',
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'status' => $post->status === 'scheduled' ? 'draft' : $post->status,
                'scheduled_at' => $post->status === 'scheduled' ? null : $post->scheduled_at,
            ]);

            $this->record($post, 'submitted', $user, __('Submitted for approval'));

            return $post->fresh();
        });
    }

    public function approve(SocialPost $post, User $user): SocialPost
    {
        if (! $this->canReview($user)) {
            throw ValidationException::withMessages([
                'approval' => __('Only owners can approve posts.'),
            ]);
        }

        if ($post->approval_status !== 'pending') {
            throw ValidationException::withMessages([
                'approval' => __('This post is not awaiting approval.'),
            ]);
        }

        return DB::transaction(function () use ($post, $user) {
            $post->update([
                'approval_status' => 'approved',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->record($post, 'approved', $user, __('Approved'));

            return $post->fresh();
        });
    }

    public function reject(SocialPost $post, User $user, string $reason): SocialPost
    {
        if (! $this->canReview($user)) {
            throw ValidationException::withMessages([
                'approval' => __('Only owners can reject posts.'),
            ]);
        }

        if ($post->approval_status !== 'pending') {
            throw ValidationException::withMessages([
                'approval' => __('This post is not awaiting approval.'),
            ]);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => __('A rejection reason is required.'),
            ]);
        }

        return DB::transaction(function () use ($post, $user, $reason) {
            $post->update([
                'approval_status' => 'rejected',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
                'status' => 'draft',
                'scheduled_at' => null,
            ]);

            $this->record($post, 'rejected', $user, $reason);

            return $post->fresh();
        });
    }

    public function requiresApprovalBeforePublish(SocialPost $post): bool
    {
        return in_array($post->approval_status, ['pending', 'rejected'], true);
    }

    public function companyRequiresApprovalWorkflow(?Company $company): bool
    {
        if (! $company?->user) {
            return false;
        }

        return $this->approvalsEnabledForUser($company->user);
    }
}
