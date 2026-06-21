<?php

namespace App\Services;

use Akaunting\Module\Facade as Module;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;

class OrgAuthorization
{
    /** @var array<string, string|null>|null */
    private static ?array $routeModuleMap = null;

    public function currentMembership(?User $user = null): ?CompanyMembership
    {
        $user ??= auth()->user();

        if ($user === null) {
            return null;
        }

        $companyId = session('company_id') ?? $user->company_id;

        if ($companyId === null) {
            return null;
        }

        return $this->membershipForCompany($user, (int) $companyId);
    }

    public function membershipForCompany(User $user, int $companyId): ?CompanyMembership
    {
        return CompanyMembership::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('status', CompanyMembership::STATUS_ACTIVE)
            ->with('modules')
            ->first();
    }

    public function isOwnerAccount(User $user): bool
    {
        return $user->hasRole('owner');
    }

    public function isPlatformAdmin(User $user): bool
    {
        return $user->hasRole('admin') && ! session()->has('impersonate');
    }

    public function isOrganizationManager(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        $membership = $this->currentMembership($user);

        return $membership !== null && $membership->isManager();
    }

    public function canViewDashboardMetrics(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if ($this->isOwnerAccount($user) || $user->hasRole('staff')) {
            return true;
        }

        if ($this->isOrganizationManager($user)) {
            return $this->canAccessModule($user, 'wpbox');
        }

        return false;
    }

    public function isOrganizationAgent(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        $membership = $this->currentMembership($user);

        if ($membership !== null) {
            return $membership->isAgent();
        }

        return $user->hasRole('staff');
    }

    public function canAccessCompany(User $user, Company|int $company): bool
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        if ($user->hasRole('owner')) {
            return Company::query()
                ->where('id', $companyId)
                ->where('user_id', $user->id)
                ->exists();
        }

        return CompanyMembership::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('status', CompanyMembership::STATUS_ACTIVE)
            ->exists();
    }

    public function canAccessModule(User $user, string $moduleAlias, ?CompanyMembership $membership = null, string $minimumPermission = 'view'): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        if ($this->isOwnerAccount($user)) {
            return $user->canUsePlanPlugin($moduleAlias);
        }

        if (in_array($moduleAlias, config('org-access.protected_modules', []), true)) {
            return false;
        }

        $membership ??= $this->currentMembership($user);

        if ($membership === null || ! $membership->isManager()) {
            return false;
        }

        if (in_array($moduleAlias, config('org-access.plan_exempt_modules', []), true)) {
            return $membership->hasModule($moduleAlias, $minimumPermission);
        }

        if (! $membership->company->hasPlanPlugin($moduleAlias)) {
            return false;
        }

        return $membership->hasModule($moduleAlias, $minimumPermission);
    }

    public function canAccessRoute(?User $user, ?string $routeName, ?CompanyMembership $membership = null): bool
    {
        if ($user === null || $routeName === null) {
            return true;
        }

        if ($this->isPlatformAdmin($user) || $this->isOwnerAccount($user)) {
            return true;
        }

        if ($this->isProtectedRoute($routeName)) {
            return false;
        }

        if ($this->isOrganizationManager($user) && in_array($routeName, config('org-access.manager_forbidden_routes', []), true)) {
            return false;
        }

        if (in_array($routeName, config('org-access.org_member_routes', []), true)) {
            return $this->isOrganizationManager($user)
                || $this->isOrganizationAgent($user)
                || $user->hasRole('staff');
        }

        if (in_array($routeName, config('org-access.manage_routes', []), true)) {
            return $this->canManageRoute($user, $routeName, $membership);
        }

        $membership ??= $this->currentMembership($user);

        if ($membership === null) {
            return $user->hasRole('staff');
        }

        if ($membership->isAgent()) {
            return true;
        }

        $moduleAlias = $this->resolveModuleForRoute($routeName);

        if ($moduleAlias === null) {
            return false;
        }

        return $this->canAccessModule($user, $moduleAlias, $membership);
    }

    public function canManageRoute(?User $user, ?string $routeName, ?CompanyMembership $membership = null): bool
    {
        if ($user === null || $routeName === null) {
            return true;
        }

        if ($this->isPlatformAdmin($user) || $this->isOwnerAccount($user)) {
            return true;
        }

        $membership ??= $this->currentMembership($user);

        if ($membership === null || ! $membership->isManager()) {
            return $user->hasRole('staff');
        }

        $moduleAlias = $this->resolveModuleForRoute($routeName);

        if ($moduleAlias === null) {
            return false;
        }

        if (in_array($moduleAlias, config('org-access.plan_exempt_modules', []), true)) {
            return $membership->hasModule($moduleAlias, 'manage');
        }

        if (! $membership->company->hasPlanPlugin($moduleAlias)) {
            return false;
        }

        return $membership->hasModule($moduleAlias, 'manage');
    }

    public function isProtectedRoute(?string $routeName): bool
    {
        if ($routeName === null) {
            return false;
        }

        if (in_array($routeName, config('org-access.protected_routes', []), true)) {
            return true;
        }

        foreach (config('org-access.protected_route_prefixes', []) as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        if ($routeName === 'admin.companies.edit') {
            return true;
        }

        return false;
    }

    public function resolveModuleForRoute(string $routeName): ?string
    {
        $map = $this->routeModuleMap();

        if (array_key_exists($routeName, $map)) {
            return $map[$routeName];
        }

        foreach ($map as $mappedRoute => $alias) {
            if ($alias !== null && str_starts_with($routeName, explode('.', $mappedRoute)[0].'.')) {
                $routeRoot = explode('.', $routeName)[0];
                $mappedRoot = explode('.', $mappedRoute)[0];

                if ($routeRoot === $mappedRoot) {
                    return $alias;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array{alias: string, label: string}>
     */
    public function grantableModulesForCompany(Company $company): array
    {
        $labels = config('org-access.module_labels', []);
        $protected = config('org-access.protected_modules', []);
        $modules = [];

        foreach (Module::all() as $module) {
            $alias = (string) $module->get('alias');

            if ($alias === '' || in_array($alias, $protected, true)) {
                continue;
            }

            if (! is_array($module->get('ownermenus')) && ! is_array($module->get('staffmenus'))) {
                continue;
            }

            if ((int) $module->get('active') !== 1) {
                continue;
            }

            if (! $company->hasPlanPlugin($alias)) {
                continue;
            }

            $modules[] = [
                'alias' => $alias,
                'label' => $labels[$alias] ?? ucfirst($alias),
            ];
        }

        usort($modules, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return $modules;
    }

    public static function clearRouteModuleMapCache(): void
    {
        self::$routeModuleMap = null;
    }

    /**
     * @return array<string, string|null>
     */
    public function routeModuleMap(): array
    {
        if (self::$routeModuleMap !== null) {
            return self::$routeModuleMap;
        }

        $map = [];

        foreach (Module::all() as $module) {
            $alias = (string) $module->get('alias');

            foreach (['ownermenus', 'staffmenus'] as $menuKey) {
                $menus = $module->get($menuKey);

                if (! is_array($menus)) {
                    continue;
                }

                $this->collectRoutesFromMenus($menus, $alias, $map);
            }
        }

        $map['agent.index'] = 'agents';
        $map['agent.create'] = 'agents';
        $map['agent.store'] = 'agents';
        $map['agent.edit'] = 'agents';
        $map['agent.update'] = 'agents';
        $map['agent.delete'] = 'agents';
        $map['agent.loginas'] = 'agents';
        $map['orgmanager.index'] = 'managers';
        $map['orgmanager.create'] = 'managers';
        $map['orgmanager.store'] = 'managers';
        $map['orgmanager.edit'] = 'managers';
        $map['orgmanager.update'] = 'managers';
        $map['orgmanager.delete'] = 'managers';
        $map['orgmanager.loginas'] = 'managers';

        $map['health-alerts.index'] = 'wpbox';
        $map['customer360.show'] = 'wpbox';
        $map['copilot.suggest'] = 'wpbox';
        $map['copilot.templates'] = 'wpbox';
        $map['flow-templates.index'] = 'flowmaker';
        $map['flow-templates.install'] = 'flowmaker';
        $map['flows.create-from-template'] = 'flowmaker';
        $map['flow-templates.generate'] = 'flowmaker';

        return self::$routeModuleMap = $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $menus
     * @param  array<string, string|null>  $map
     */
    private function collectRoutesFromMenus(array $menus, string $alias, array &$map): void
    {
        foreach ($menus as $menu) {
            if (! empty($menu['route'])) {
                $map[(string) $menu['route']] = $alias;
            }

            if (! empty($menu['menus']) && is_array($menu['menus'])) {
                $this->collectRoutesFromMenus($menu['menus'], $alias, $map);
            }
        }
    }

    public function assertOwnerAccount(?User $user = null): void
    {
        $user ??= auth()->user();

        if ($user === null || ! $this->isOwnerAccount($user)) {
            abort(403, __('Unauthorized action.'));
        }
    }

    public function assertModuleAccess(string $moduleAlias, string $minimumPermission = 'view', ?User $user = null): void
    {
        $user ??= auth()->user();

        if ($user === null) {
            abort(403);
        }

        if ($this->isOwnerAccount($user) || $this->isPlatformAdmin($user)) {
            return;
        }

        $membership = $this->currentMembership($user);

        if ($membership === null || ! $membership->isManager()) {
            abort(403, __('Unauthorized action.'));
        }

        if (in_array($moduleAlias, config('org-access.plan_exempt_modules', []), true)) {
            if (! $membership->hasModule($moduleAlias, $minimumPermission)) {
                abort(403, __('You do not have access to this module.'));
            }

            return;
        }

        if (! $membership->company->hasPlanPlugin($moduleAlias)) {
            abort(403, __('This feature is not included in your plan.'));
        }

        if (! $membership->hasModule($moduleAlias, $minimumPermission)) {
            abort(403, __('You do not have access to this module.'));
        }
    }

    public function canManageAgents(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if ($this->isOwnerAccount($user) || $this->isPlatformAdmin($user)) {
            return true;
        }

        $membership = $this->currentMembership($user);

        return $membership !== null
            && $membership->isManager()
            && $membership->hasModule('agents', 'manage');
    }
}
