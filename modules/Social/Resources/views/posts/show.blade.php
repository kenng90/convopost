@extends('layouts.app', ['title' => __('Social post')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Post details') }}</h1>
                    <p class="mb-0 text-white opacity-8">{{ __('Review content, approval status, and publishing targets.') }}</p>
                </div>
                <div class="col-auto">
                    <a href="{{ route('social.posts.index') }}" class="btn btn-sm btn-neutral">{{ __('Back to posts') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
    <div class="row mb-3">
        <div class="col-12">
            @include('partials.flash')
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">{{ __('Content') }}</h3>
                    <div>
                        @php
                            $statusBadge = match ($post->status) {
                                'published' => 'success',
                                'scheduled' => 'info',
                                'failed' => 'danger',
                                default => 'secondary',
                            };
                            $approvalBadge = match ($post->approval_status) {
                                'approved' => 'success',
                                'pending' => 'warning',
                                'rejected' => 'danger',
                                default => 'secondary',
                            };
                        @endphp
                        <span class="badge badge-{{ $statusBadge }}">{{ __(ucfirst($post->status)) }}</span>
                        <span class="badge badge-{{ $approvalBadge }}">
                            {{ __('Approval: :status', ['status' => __(ucfirst($post->approval_status))]) }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mb-0" style="white-space: pre-wrap;">{{ $post->defaultVersion?->content ?? __('(No content)') }}</p>

                    @if ($post->rejection_reason)
                        <div class="alert alert-danger mt-3 mb-0">
                            <strong>{{ __('Rejection reason') }}:</strong>
                            {{ $post->rejection_reason }}
                        </div>
                    @endif
                </div>
                <div class="card-footer">
                    <div class="d-flex flex-wrap gap-2">
                        @if ($canSubmit)
                            <form method="POST" action="{{ route('social.posts.submit-approval', $post) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('Submit for approval') }}</button>
                            </form>
                        @endif

                        @if ($canReview && $post->isPendingApproval())
                            <form method="POST" action="{{ route('social.posts.approve', $post) }}">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm">{{ __('Approve') }}</button>
                            </form>
                        @endif
                    </div>

                    @if ($canReview && $post->isPendingApproval())
                        <form method="POST" action="{{ route('social.posts.reject', $post) }}" class="mt-3">
                            @csrf
                            <label class="form-control-label" for="rejection_reason">{{ __('Reject with reason') }}</label>
                            <textarea
                                id="rejection_reason"
                                name="rejection_reason"
                                class="form-control @error('rejection_reason') is-invalid @enderror"
                                rows="3"
                                required
                            >{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <button type="submit" class="btn btn-outline-danger btn-sm mt-2">{{ __('Reject') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Details') }}</h3>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>{{ __('Author') }}</dt>
                        <dd>{{ $post->author?->name ?? __('Unknown') }}</dd>
                        <dt>{{ __('Scheduled') }}</dt>
                        <dd>{{ $post->scheduled_at?->format('M d, Y H:i') ?? '—' }}</dd>
                        <dt>{{ __('Accounts') }}</dt>
                        <dd>
                            @forelse ($post->accounts as $account)
                                <span class="badge badge-secondary mr-1">{{ $account->name ?: $account->provider }}</span>
                            @empty
                                <span class="text-muted">{{ __('None') }}</span>
                            @endforelse
                        </dd>
                        @if ($post->offerLink)
                            <dt>{{ __('Offer') }}</dt>
                            <dd>{{ __(ucfirst($post->offerLink->offer_type)) }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
            <div class="card shadow mb-4">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Activity') }}</h3>
                </div>
                <div class="card-body">
                    @forelse ($post->activities as $activity)
                        <div class="mb-3 pb-3 {{ ! $loop->last ? 'border-bottom' : '' }}" wire:key="activity-{{ $activity->id }}">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="font-weight-bold">{{ __(ucfirst(str_replace('_', ' ', $activity->action))) }}</div>
                                    @if ($activity->message)
                                        <div class="text-sm text-muted">{{ $activity->message }}</div>
                                    @endif
                                    <div class="text-xs text-muted mt-1">
                                        {{ $activity->user?->name ?? __('System') }}
                                    </div>
                                </div>
                                <div class="text-xs text-muted text-nowrap">
                                    {{ $activity->created_at?->format('M d, Y H:i') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="mb-0 text-muted">{{ __('No activity yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
