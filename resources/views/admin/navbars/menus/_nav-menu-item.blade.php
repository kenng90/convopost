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
            @php
                $ungroupedItems = [];
                $sectionGroups = [];
                $currentSection = null;

                foreach ($menu['menus'] ?? [] as $submenu) {
                    if (! isset($submenu['route']) || ! Route::has($submenu['route'])) {
                        continue;
                    }

                    if (isset($submenu['navSection'])) {
                        $currentSection = $submenu['navSection'];
                    }

                    if ($currentSection === null) {
                        $ungroupedItems[] = $submenu;
                    } else {
                        $sectionGroups[$currentSection][] = $submenu;
                    }
                }

                $useCollapsibleSections = ($menu['navSectionsCollapsible'] ?? false) && ! empty($sectionGroups);
            @endphp

            @if ($useCollapsibleSections)
                @foreach ($ungroupedItems as $submenu)
                    @include('admin.navbars.menus._nav-submenu-link', ['submenu' => $submenu])
                @endforeach

                @foreach ($sectionGroups as $sectionName => $sectionItems)
                    @php
                        $sectionDomId = $menuDomId.'-'.\Illuminate\Support\Str::slug($sectionName);
                        $sectionIsActive = collect($sectionItems)->pluck('route')->contains(Route::currentRouteName());
                    @endphp
                    <li class="nav-item mt-2">
                        <a class="nav-link text-muted text-uppercase d-flex align-items-center justify-content-between py-1"
                            style="font-size: 0.65rem; letter-spacing: 0.05em;"
                            href="#navbar-{{ $sectionDomId }}"
                            data-toggle="collapse"
                            role="button"
                            aria-expanded="{{ $sectionIsActive ? 'true' : 'false' }}"
                            aria-controls="navbar-{{ $sectionDomId }}">
                            <span>{{ __($sectionName) }}</span>
                            <!-- <i class="ni ni-bold-down" style="font-size: 0.6rem;"></i> -->
                        </a>
                    </li>
                    <li class="nav-item">
                        <div class="collapse @if ($sectionIsActive) show @endif" id="navbar-{{ $sectionDomId }}">
                            <ul class="nav nav-sm flex-column pl-2">
                                @foreach ($sectionItems as $submenu)
                                    @include('admin.navbars.menus._nav-submenu-link', ['submenu' => $submenu])
                                @endforeach
                            </ul>
                        </div>
                    </li>
                @endforeach
            @else
                @php $previousNavSection = null; @endphp
                @foreach ($menu['menus'] as $submenu)
                    @if (isset($submenu['navSection']) && $submenu['navSection'] !== $previousNavSection)
                        <li class="nav-item mt-2">
                            <span class="nav-link text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em; padding-top: 0.25rem; padding-bottom: 0.25rem;">
                                {{ __($submenu['navSection']) }}
                            </span>
                        </li>
                        @php $previousNavSection = $submenu['navSection']; @endphp
                    @endif
                    @if (Route::has($submenu['route'] ?? ''))
                        @include('admin.navbars.menus._nav-submenu-link', ['submenu' => $submenu])
                    @endif
                @endforeach
            @endif
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
