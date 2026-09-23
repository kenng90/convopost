@extends('layouts.app', ['title' => __('Social comments')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Comments') }}</h1>
                    <p class="mb-0 text-white opacity-8">{{ __('Read-only comments synced from Facebook and Instagram. Replies stay on the network for now.') }}</p>
                </div>
                <div class="col-auto">
                    <a href="{{ route('social.posts.index') }}" class="btn btn-sm btn-neutral">{{ __('Posts') }}</a>
                    <a href="{{ route('social.home') }}" class="btn btn-sm btn-neutral">{{ __('Back to Social') }}</a>
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
        <div class="col">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Recent comments') }}</h3>
                </div>
                <div class="table-responsive">
                    <table class="table align-items-center table-flush mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ __('When') }}</th>
                                <th>{{ __('Network') }}</th>
                                <th>{{ __('Author') }}</th>
                                <th>{{ __('Comment') }}</th>
                                <th>{{ __('Post') }}</th>
                                <th>{{ __('Contact') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($comments as $comment)
                                <tr wire:key="comment-{{ $comment->id }}">
                                    <td class="text-sm text-nowrap">
                                        {{ $comment->commented_at?->format('M d, Y H:i') ?? '—' }}
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">{{ __(ucfirst($comment->provider)) }}</span>
                                    </td>
                                    <td class="text-sm">{{ $comment->authorLabel() }}</td>
                                    <td class="text-sm" style="max-width: 320px;">
                                        <div class="text-truncate" title="{{ $comment->body }}">
                                            {{ $comment->body ?: '—' }}
                                        </div>
                                    </td>
                                    <td class="text-sm">
                                        @if ($comment->post)
                                            <a href="{{ route('social.posts.show', $comment->post) }}">
                                                {{ \Illuminate\Support\Str::limit($comment->post->defaultVersion?->content ?? __('Post #:id', ['id' => $comment->post->id]), 40) }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-sm text-nowrap">
                                        @if ($comment->contact_id)
                                            <a href="{{ route('contacts.edit', $comment->contact_id) }}" class="btn btn-sm btn-outline-primary">
                                                {{ __('View contact') }}
                                            </a>
                                        @elseif ($comment->canCreateContact())
                                            <form method="POST" action="{{ route('social.comments.create-contact', $comment) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary">{{ __('Save as contact') }}</button>
                                            </form>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        {{ __('No comments synced yet. Comments appear after published Facebook or Instagram posts are synced.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($comments->hasPages())
                    <div class="card-footer">
                        {{ $comments->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
