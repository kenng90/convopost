@foreach (auth()->user()->getOwnerNavigationSections() as $section)
    @if (!empty($section['label']))
        <!-- <hr class="my-1"> -->
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
