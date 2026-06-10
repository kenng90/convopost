<!-- Extra menus (staff / legacy flat owner nav) -->
<ul class="navbar-nav">
@foreach (auth()->user()->getExtraMenus() as $menu)
    @include('admin.navbars.menus._nav-menu-item', ['menu' => $menu])
@endforeach
</ul>
