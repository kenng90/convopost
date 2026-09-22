@extends('layouts.app', ['title' => __('Media library')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Media library') }}</h1>
                    <p class="mb-0 text-white opacity-8">{{ __('Upload images and videos to use in your social posts.') }}</p>
                </div>
                <div class="col-auto">
                    <a href="{{ route('social.home') }}" class="btn btn-sm btn-neutral">{{ __('Back to Social') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
    <div class="row mb-4">
        <div class="col-12">
            @include('partials.flash')
        </div>
        <div class="col-12">
            <div class="card shadow">
                <div class="card-body">
                    <form action="{{ route('social.media.store') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap align-items-end gap-3">
                        @csrf
                        <div class="flex-grow-1">
                            <label for="social-media-file" class="form-control-label">{{ __('Upload file') }}</label>
                            <input
                                id="social-media-file"
                                type="file"
                                name="file"
                                class="form-control @error('file') is-invalid @enderror"
                                accept=".{{ implode(',.', $allowedMimes) }}"
                                required
                            >
                            @error('file')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                {{ __('Accepted: :types. Max :max MB.', [
                                    'types' => strtoupper(implode(', ', $allowedMimes)),
                                    'max' => round($maxKilobytes / 1024, 1),
                                ]) }}
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('Upload') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @forelse ($assets as $asset)
            <div class="col-6 col-md-4 col-lg-3 mb-4">
                <div class="card shadow h-100">
                    <div class="card-img-top bg-dark d-flex align-items-center justify-content-center" style="height: 160px; overflow: hidden;">
                        @if ($asset->isImage())
                            <img src="{{ $asset->url() }}" alt="{{ $asset->original_name }}" class="img-fluid" style="object-fit: cover; width: 100%; height: 100%;">
                        @elseif ($asset->isVideo())
                            <div class="text-white text-center p-3">
                                <i class="ni ni-button-play ni-2x d-block mb-2"></i>
                                <span class="small">{{ __('Video') }}</span>
                            </div>
                        @else
                            <span class="text-white small">{{ __('File') }}</span>
                        @endif
                    </div>
                    <div class="card-body py-3">
                        <div class="text-truncate font-weight-bold" title="{{ $asset->original_name }}">
                            {{ $asset->original_name ?: __('Untitled') }}
                        </div>
                        <div class="text-muted small">
                            {{ strtoupper(pathinfo($asset->original_name ?? '', PATHINFO_EXTENSION) ?: ($asset->mime ?? '')) }}
                            @if ($asset->size)
                                · {{ number_format($asset->size / 1024, 0) }} KB
                            @endif
                        </div>
                        @if ($asset->width && $asset->height)
                            <div class="text-muted small">{{ $asset->width }}×{{ $asset->height }}</div>
                        @endif
                    </div>
                    @if (\Illuminate\Support\Facades\Route::has('social.media.destroy'))
                        <div class="card-footer py-2">
                            <form method="POST" action="{{ route('social.media.destroy', $asset) }}" onsubmit="return confirm('{{ __('Delete this media file?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger btn-block">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body">
                        <p class="mb-0 text-muted">{{ __('No media yet. Upload an image or video to get started.') }}</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    @if ($assets->hasPages())
        <div class="row">
            <div class="col">
                {{ $assets->links() }}
            </div>
        </div>
    @endif
</div>
@endsection
