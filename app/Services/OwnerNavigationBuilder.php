<?php

namespace App\Services;

use App\Models\User;
use App\Support\Offering;
use Illuminate\Support\Facades\Route;

class OwnerNavigationBuilder
{
    /**
     * @return array<int, array{label: string, menus: array<int, array<string, mixed>>}>
     */
    public function build(User $user): array
    {
        return $this->buildFromPool($user, $user->collectOwnerModuleMenus());
    }

    /**
     * @param  array<int, array<string, mixed>>  $pool
     * @return array<int, array{label: string, menus: array<int, array<string, mixed>>}>
     */
    public function buildFromPool(User $user, array $pool, bool $excludeWorkspace = false): array
    {
        if (! config('owner-navigation.enabled', true)) {
            return $this->wrapFlat($pool);
        }

        $sections = [];
        $sectionLabels = collect(config('owner-navigation.sections', []))
            ->pluck('label', 'id')
            ->all();

        foreach (array_keys($sectionLabels) as $sectionId) {
            $sections[$sectionId] = [];
        }

        $absorbedRoutes = config('owner-navigation.absorb_routes', []);
        $menuIdMap = config('owner-navigation.menu_ids', []);
        $routeMap = config('owner-navigation.routes', []);

        foreach ($pool as $index => $menu) {
            $menuId = $menu['id'] ?? null;
            $route = $menu['route'] ?? null;

            if ($route && in_array($route, $absorbedRoutes, true)) {
                unset($pool[$index]);

                continue;
            }

            if ($menuId && isset($menuIdMap[$menuId])) {
                $sections[$menuIdMap[$menuId]][] = $menu;
                unset($pool[$index]);

                continue;
            }

            if ($route && isset($routeMap[$route])) {
                $sections[$routeMap[$route]][] = $menu;
                unset($pool[$index]);
            }
        }

        foreach (config('owner-navigation.groups', []) as $groupKey => $groupConfig) {
            if ($excludeWorkspace && ($groupConfig['id'] ?? null) === 'workspaceMenu') {
                continue;
            }

            $group = $this->buildSyntheticGroup($user, $groupConfig, $excludeWorkspace);

            if ($group === null) {
                continue;
            }

            $sectionId = $groupConfig['section'];
            $sections[$sectionId][] = $group;
        }

        $result = [];

        foreach (config('owner-navigation.sections', []) as $section) {
            $id = $section['id'];
            $menus = $sections[$id] ?? [];

            if (empty($menus)) {
                continue;
            }

            usort($menus, function ($a, $b) {
                return ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100);
            });

            $result[] = [
                'label' => __($section['label']),
                'menus' => array_values($menus),
            ];
        }

        $remaining = array_values($pool);

        if (! empty($remaining)) {
            usort($remaining, fn ($a, $b) => ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100));

            $result[] = [
                'label' => __('More'),
                'menus' => $remaining,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $groupConfig
     * @return array<string, mixed>|null
     */
    protected function buildSyntheticGroup(User $user, array $groupConfig, bool $excludeWorkspace = false): ?array
    {
        if (($groupConfig['id'] ?? null) === 'workspaceMenu') {
            return $excludeWorkspace ? null : $this->buildWorkspaceGroup($user);
        }

        $plugin = $groupConfig['plugin'] ?? null;

        if ($plugin && Offering::isDormantModule($plugin)) {
            return null;
        }

        if ($plugin && ! $user->canUsePlanPlugin($plugin)) {
            return null;
        }

        $parentRoute = $groupConfig['route'] ?? null;

        if ($parentRoute && Offering::isDormantRoute($parentRoute)) {
            return null;
        }

        if ($parentRoute && ! Route::has($parentRoute)) {
            return null;
        }

        $submenus = [];

        foreach ($groupConfig['menus'] ?? [] as $submenu) {
            $subRoute = $submenu['route'] ?? null;
            $subPlugin = $submenu['plugin'] ?? $plugin;

            if (! $subRoute || ! Route::has($subRoute)) {
                continue;
            }

            if (Offering::isDormantRoute($subRoute)) {
                continue;
            }

            if ($subPlugin && Offering::isDormantModule($subPlugin)) {
                continue;
            }

            if ($subPlugin && ! $user->canUsePlanPlugin($subPlugin)) {
                continue;
            }

            $submenus[] = $submenu;
        }

        if (empty($submenus) && ! $parentRoute) {
            return null;
        }

        if (empty($submenus) && $parentRoute) {
            return [
                'name' => $groupConfig['name'],
                'icon' => $groupConfig['icon'] ?? 'ni ni-app',
                'route' => $parentRoute,
                'priority' => $groupConfig['priority'] ?? 100,
            ];
        }

        return [
            'id' => $groupConfig['id'],
            'name' => $groupConfig['name'],
            'icon' => $groupConfig['icon'] ?? 'ni ni-app',
            'route' => $parentRoute ?? ($submenus[0]['route'] ?? null),
            'isGroup' => true,
            'menus' => $submenus,
            'priority' => $groupConfig['priority'] ?? 100,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildWorkspaceGroup(User $user): ?array
    {
        $submenus = [];

        if (! config('settings.hide_company_profile', false)) {
            $companyId = $user->currentCompany()?->id;

            if ($companyId && Route::has('admin.companies.edit')) {
                $submenus[] = [
                    'name' => 'Company',
                    'icon' => 'ni ni-shop text-primary',
                    'route' => 'admin.companies.edit',
                    'params' => ['company' => $companyId],
                ];
            }
        }

        if (! config('settings.hide_company_apps', false) && Route::has('admin.apps.company')) {
            $submenus[] = [
                'name' => 'Apps',
                'icon' => 'ni ni-spaceship text-red',
                'route' => 'admin.apps.company',
            ];
        }

        if (config('settings.enable_pricing') && Route::has('plans.current')) {
            $submenus[] = [
                'name' => 'Plan & billing',
                'icon' => 'ni ni-credit-card text-orange',
                'route' => 'plans.current',
            ];
        }

        if (! config('settings.hide_share_link', false) && Route::has('admin.share')) {
            $submenus[] = [
                'name' => 'Share',
                'icon' => 'ni ni-send text-green',
                'route' => 'admin.share',
            ];
        }

        if (empty($submenus)) {
            return null;
        }

        return [
            'id' => 'workspaceMenu',
            'name' => 'Workspace',
            'icon' => 'ni ni-shop text-primary',
            'route' => $submenus[0]['route'],
            'params' => $submenus[0]['params'] ?? [],
            'isGroup' => true,
            'menus' => $submenus,
            'priority' => 200,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $menus
     * @return array<int, array{label: string, menus: array<int, array<string, mixed>>}>
     */
    protected function wrapFlat(array $menus): array
    {
        if (empty($menus)) {
            return [];
        }

        return [
            [
                'label' => '',
                'menus' => $menus,
            ],
        ];
    }
}
