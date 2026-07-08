@extends('layouts.app', ['title' => __('Apps')])

@section('content')
<div class="header bg-gradient-default pb-6 pt-5 pt-md-8">
</div>

<div class="container-fluid mt--7">
    <div class="row">

        <!-- LEFT NAV -->
        <div class="col-xl-3 flex-row">
            <div class="nav-wrapper flex-row">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <ul class="nav nav-pills nav-fill flex-column" id="tabs-icons-text" role="tablist">

                                    @foreach ($separators as $separator)
                                        <li class="nav-item pb-2">
                                            <a class="nav-link mb-sm-3 mb-md-0 @if ($loop->first) active @endif"
                                               id="{{ $separator['snake'].'_tab' }}"
                                               data-toggle="tab"
                                               href="#{{ $separator['snake'] }}"
                                               role="tab"
                                               aria-controls="{{ $separator['snake'] }}"
                                               aria-selected="true">
                                                {{ $separator['icon'] }} {{ $separator['name'] }}
                                            </a>
                                        </li>
                                    @endforeach

                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT CONTENT -->
        <div class="col-xl-9 mt-3">
            @include('partials.flash')

            <!-- ✅ FIXED FORM -->
            <form id="restorant-apps-form"
                  method="POST"
                  action="{{ route('admin.owner.updateApps') }}"
                  autocomplete="off"
                  enctype="multipart/form-data">

                @csrf
                @method('PUT')

                <div class="card shadow">
                    <div class="card-body">

                        <div class="tab-content" id="myTabContent">
                            @foreach ($separators as $separator)
                                <div class="tab-pane fade show @if ($loop->first) active @endif"
                                     id="{{ $separator['snake'] }}"
                                     role="tabpanel"
                                     aria-labelledby="{{ $separator['snake'].'_tab' }}">

                                    @include('partials.fields', ['fields' => $separator['fields']])

                                    @if ($separator['snake'] === 'facebook_developer')
                                        @livewire('whatsapp-flow-encryption-settings')
                                    @endif

                                </div>
                            @endforeach
                        </div>

                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-success mt-4">
                        {{ __('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabLinks = Array.from(document.querySelectorAll('#tabs-icons-text a[data-toggle="tab"]'));
            const tabPanes = Array.from(document.querySelectorAll('#myTabContent .tab-pane'));

            if (!tabLinks.length || !tabPanes.length) {
                return;
            }

            const activateTab = (targetSelector, pushHash = false) => {
                if (!targetSelector || !targetSelector.startsWith('#')) {
                    return;
                }

                const targetPane = document.querySelector(targetSelector);
                if (!targetPane) {
                    return;
                }

                tabLinks.forEach((link) => {
                    const isActive = link.getAttribute('href') === targetSelector;
                    link.classList.toggle('active', isActive);
                    link.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                tabPanes.forEach((pane) => {
                    const isActive = '#'+pane.id === targetSelector;
                    pane.classList.toggle('active', isActive);
                    pane.classList.toggle('show', isActive);
                });

                if (pushHash) {
                    window.history.replaceState(null, '', targetSelector);
                }
            };

            tabLinks.forEach((link) => {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    activateTab(this.getAttribute('href'), true);
                });
            });

            if (window.location.hash) {
                activateTab(window.location.hash);
            }
        });
    </script>
@endsection