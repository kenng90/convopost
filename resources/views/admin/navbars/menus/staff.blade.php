<ul class="navbar-nav">
    <li class="nav-item">
        <a class="nav-link" href="{{ route('home') }}">
            <i class="ni ni-tv-2 text-primary"></i> {{ __('Dashboard') }}
        </a>
    </li>
    @foreach (auth()->user()->getExtraMenus() as $menu)
        @if (($menu['route'] ?? '') !== 'staff.index')
            @include('admin.navbars.menus._nav-menu-item', ['menu' => $menu])
        @endif
    @endforeach
</ul>
