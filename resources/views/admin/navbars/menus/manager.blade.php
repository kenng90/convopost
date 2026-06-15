<ul class="navbar-nav">
    <li class="nav-item">
        <a class="nav-link @if (Route::currentRouteName() == 'dashboard') active @endif"
            href="{{ route('dashboard') }}">
            <i class="ni ni-tv-2 text-primary"></i> {{ __('Dashboard') }}
        </a>
    </li>

    @if (config('owner-navigation.enabled', true))
        @foreach (auth()->user()->getManagerNavigationSections() as $section)
            @if (!empty($section['label']))
                <h6 class="navbar-heading p-0 text-muted">
                    <span class="docs-normal">{{ $section['label'] }}</span>
                </h6>
            @endif

            <ul class="navbar-nav mb-md-1">
                @foreach ($section['menus'] as $menu)
                    @include('admin.navbars.menus._nav-menu-item', ['menu' => $menu])
                @endforeach
            </ul>
        @endforeach
    @else
        @include('admin.navbars.menus.extra')
    @endif
</ul>
