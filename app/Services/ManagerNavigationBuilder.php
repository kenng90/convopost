<?php

namespace App\Services;

use App\Models\User;

class ManagerNavigationBuilder
{
    public function __construct(
        private readonly OwnerNavigationBuilder $ownerNavigationBuilder,
        private readonly OrgAuthorization $orgAuthorization,
    ) {
    }

    /**
     * @return array<int, array{label: string, menus: array<int, array<string, mixed>>}>
     */
    public function build(User $user): array
    {
        $membership = $this->orgAuthorization->currentMembership($user);

        if ($membership === null || ! $membership->isManager()) {
            return [];
        }

        $pool = collect($user->collectOwnerModuleMenus())
            ->filter(fn (array $menu) => $this->menuIsAllowed($user, $menu))
            ->values()
            ->all();

        return $this->ownerNavigationBuilder->buildFromPool($user, $pool, excludeWorkspace: true);
    }

    /**
     * @param  array<string, mixed>  $menu
     */
    private function menuIsAllowed(User $user, array $menu): bool
    {
        if (! empty($menu['menus']) && is_array($menu['menus'])) {
            $submenus = array_values(array_filter(
                $menu['menus'],
                fn (array $submenu) => $this->menuIsAllowed($user, $submenu)
            ));

            if (empty($submenus)) {
                return false;
            }

            return true;
        }

        $routeName = $menu['route'] ?? null;

        if ($routeName === null) {
            return false;
        }

        if ($this->orgAuthorization->isProtectedRoute($routeName)) {
            return false;
        }

        $moduleAlias = $this->orgAuthorization->resolveModuleForRoute($routeName);

        if ($moduleAlias === null) {
            return false;
        }

        return $this->orgAuthorization->canAccessModule($user, $moduleAlias);
    }
}
