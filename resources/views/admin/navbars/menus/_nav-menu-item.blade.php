@php
    $routeParams = $menu['params'] ?? [];
    $menuDomId = $menu['id'] ?? \Illuminate\Support\Str::slug($menu['name'] ?? 'menu');
@endphp

@if (isset($menu['isGroup']) && $menu['isGroup'])
    <a class="nav-link" href="#navbar-{{ $menuDomId }}" data-toggle="collapse" role="button"
        aria-expanded="false" aria-controls="navbar-{{ $menuDomId }}">
        <i class="{{ $menu['icon'] ?? 'ni ni-app' }}"></i>
        <span class="nav-link-text">{{ __($menu['name']) }}</span>
    </a>
    <div class="collapse @if (
        (isset($menu['route']) && Route::currentRouteName() == $menu['route'])
        || (isset($menu['menus']) && collect($menu['menus'])->pluck('route')->contains(Route::currentRouteName()))
        || request()->is('whatsapp-flows*')
    ) show @endif"
        id="navbar-{{ $menuDomId }}">
        <ul class="nav nav-sm flex-column">
            @foreach ($menu['menus'] as $submenu)
                @if (Route::has($submenu['route']))
                    @php $subParams = $submenu['params'] ?? []; @endphp
                    <li class="nav-item">
                        <a class="nav-link @if (Route::currentRouteName() == $submenu['route'] || ($submenu['route'] === 'whatsapp-flows.index' && request()->is('whatsapp-flows*') && ! request()->is('whatsapp-flows/responses*'))) active @endif"
                            href="{{ route($submenu['route'], $subParams) }}">
                            <i class="{{ $submenu['icon'] ?? 'ni ni-app' }}"></i> {{ __($submenu['name']) }}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
@else
    <li class="nav-item">
        @if (isset($menu['route']) && Route::has($menu['route']))
            <a class="nav-link @if (Route::currentRouteName() == $menu['route']) active @endif"
                href="{{ route($menu['route'], $routeParams) }}">
                @if (isset($menu['svg']))
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="{{ $menu['color'] ?? '#5e72e4' }}" viewBox="0 0 16 16">
                        <path d="{{ $menu['svg'] }}"/>
                    </svg>
                    <span style="margin-left: 20px">{{ __($menu['name']) }}</span>
                @else
                    <i class="{{ $menu['icon'] ?? 'ni ni-app' }}"></i>{{ __($menu['name']) }}
                @endif
            </a>
        @endif
    </li>
@endif
