@extends('layouts.app', ['title' => __('Import history')])

@section('content')
    <div class="header pb-8 pt-5 pt-md-8"></div>
    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h3 class="mb-0">{{ __('Import history') }}</h3>
                            </div>
                            <div class="col-4 text-right">
                                <a href="{{ route('contacts.import.index') }}" class="btn btn-sm btn-primary">{{ __('New import') }}</a>
                                <a href="{{ route('contacts.index') }}" class="btn btn-sm btn-secondary">{{ __('Back to contacts') }}</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        @include('partials.flash')
                    </div>

                    @include('contacts::contacts.partials.import-active-banner', ['activeImports' => $activeImports])

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-items-center">
                                <thead>
                                    <tr>
                                        <th>{{ __('File') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Progress') }}</th>
                                        <th>{{ __('Created') }}</th>
                                        <th>{{ __('Updated') }}</th>
                                        <th>{{ __('Skipped') }}</th>
                                        <th>{{ __('When') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($imports as $import)
                                        <tr>
                                            <td>{{ $import->original_filename }}</td>
                                            <td>{{ ucfirst($import->status) }}</td>
                                            <td>{{ $import->progressPercent() }}%</td>
                                            <td>{{ number_format($import->created_count) }}</td>
                                            <td>{{ number_format($import->updated_count) }}</td>
                                            <td>{{ number_format($import->skipped_count) }}</td>
                                            <td>{{ $import->created_at?->diffForHumans() }}</td>
                                            <td class="text-right">
                                                <a href="{{ route('contacts.import.show', $import) }}" class="btn btn-sm btn-outline-primary">
                                                    {{ __('View') }}
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">{{ __('No imports yet.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{ $imports->links() }}
                    </div>
                </div>
            </div>
        </div>

        @include('layouts.footers.auth')
    </div>
@endsection

@section('js')
    @stack('js')
@endsection
