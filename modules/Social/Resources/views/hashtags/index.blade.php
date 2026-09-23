@extends('layouts.app', ['title' => __('Hashtag groups')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Hashtag groups') }}</h1>
                    <p class="mb-0 text-white opacity-8">{{ __('Save sets of hashtags and insert them while composing.') }}</p>
                </div>
                <div class="col-auto">
                    <a href="{{ route('social.posts.create') }}" class="btn btn-sm btn-primary">{{ __('Compose') }}</a>
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
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('New group') }}</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('social.hashtags.store') }}">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label class="form-control-label" for="hashtag-name">{{ __('Name') }}</label>
                                <input id="hashtag-name" type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                                @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group col-md-8">
                                <label class="form-control-label" for="hashtag-tags">{{ __('Hashtags') }}</label>
                                <input id="hashtag-tags" type="text" name="tags" value="{{ old('tags') }}" class="form-control @error('tags') is-invalid @enderror" placeholder="#kenya #mpesa #shop" required>
                                @error('tags') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                <small class="form-text text-muted">{{ __('Separate with spaces or commas.') }}</small>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('Save group') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Your groups') }}</h3>
                </div>
                @if ($groups->count())
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Tags') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($groups as $group)
                                    <tr>
                                        <td>{{ $group->name }}</td>
                                        <td>{{ $group->formattedTags() }}</td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('social.hashtags.destroy', $group) }}" class="d-inline" onsubmit="return confirm(@json(__('Delete this group?')));">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer py-4">
                        {{ $groups->links() }}
                    </div>
                @else
                    <div class="card-body">
                        <p class="mb-0 text-muted">{{ __('No hashtag groups yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
