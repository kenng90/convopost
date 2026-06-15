<?php

namespace App\Models;

use Akaunting\Module\Facade as Module;
use App\Services\OrgAuthorization;
use App\Traits\HasConfig;
use App\Traits\HasCredit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\WelcomeNotification\ReceivesWelcomeNotification;

class User extends Authenticatable
{
    use Billable;
    use HasApiTokens;
    use HasConfig;
    use HasCredit;
    use HasFactory;
    use HasProfilePhoto;
    use HasRoles;
    use Notifiable;
    use ReceivesWelcomeNotification;
    use TwoFactorAuthenticatable;

    protected $modelName = "App\Models\User";

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    public function company()
    {
        if ($this->hasRole('owner')) {
            return $this->hasOne(Company::class);
        }

        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    public function memberships()
    {
        return $this->hasMany(CompanyMembership::class);
    }

    public function activeMemberships()
    {
        return $this->memberships()->where('status', CompanyMembership::STATUS_ACTIVE);
    }

    public function currentMembership(): ?CompanyMembership
    {
        return app(OrgAuthorization::class)->currentMembership($this);
    }

    public function isOrganizationManager(): bool
    {
        return app(OrgAuthorization::class)->isOrganizationManager($this);
    }

    public function isOrganizationAgent(): bool
    {
        return app(OrgAuthorization::class)->isOrganizationAgent($this);
    }

    public function isOrgMember(): bool
    {
        return $this->isOrganizationManager() || $this->isOrganizationAgent();
    }

    public function currentCompany()
    {
        if ($this->hasRole('admin') && ! session()->has('impersonate')) {
            return null;
        }

        if ($this->hasRole('owner')) {
            if (session()->has('company_id')) {
                $company = Company::query()
                    ->where('id', session('company_id'))
                    ->where('user_id', $this->id)
                    ->first();

                if ($company !== null) {
                    return $company;
                }
            }

            if ($this->company_id !== null) {
                $company = Company::query()
                    ->where('id', $this->company_id)
                    ->where('user_id', $this->id)
                    ->first();

                if ($company !== null) {
                    return $company;
                }
            }

            $company = Company::query()->where('user_id', $this->id)->oldest('id')->first();

            if ($company === null) {
                auth()->logout();
                abort(403);
            }

            return $company;
        }

        if ($this->hasRole('org_manager') || $this->hasRole('staff')) {
            $companyId = session('company_id', $this->company_id);

            if ($companyId !== null) {
                $membership = CompanyMembership::query()
                    ->where('user_id', $this->id)
                    ->where('company_id', $companyId)
                    ->where('status', CompanyMembership::STATUS_ACTIVE)
                    ->first();

                if ($membership !== null) {
                    return Company::findOrFail($companyId);
                }
            }

            $membership = $this->activeMemberships()->with('company')->first();

            if ($membership !== null) {
                session(['company_id' => $membership->company_id]);

                return $membership->company;
            }
        }

        if ($this->company_id !== null) {
            return Company::findOrFail($this->company_id);
        }

        return null;
    }

    public function activeCompanyId(): ?int
    {
        return $this->currentCompany()?->id;
    }

    public function ownsCompany(Company|int $company): bool
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        if ($this->hasRole('owner')) {
            return Company::query()
                ->where('id', $companyId)
                ->where('user_id', $this->id)
                ->exists();
        }

        return app(OrgAuthorization::class)->canAccessCompany($this, $companyId);
    }

    public function getCurrentCompany()
    {
        return $this->currentCompany();
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public function plan()
    {
        return $this->hasOne(\App\Models\Plans::class, 'id', 'plan_id');
    }

    public function mplanid()
    {
        return $this->plan_id ? $this->plan_id : intval(config('settings.free_pricing_id'));
    }

    /**
     * Whether this user may use a plan-gated plugin in the owner portal.
     */
    public function canUsePlanPlugin(string $alias): bool
    {
        if ($this->hasRole('admin') && ! session()->has('impersonate')) {
            return false;
        }

        $company = $this->currentCompany();

        return $company && $company->hasPlanPlugin($alias);
    }

    public function getExtraMenus()
    {
        $menus = [];
        if ($this->hasRole('admin')) {
            foreach (Module::all() as $key => $module) {
                if (is_array($module->get('adminmenus'))) {
                    foreach ($module->get('adminmenus') as $key => $menu) {
                        $menu['alias'] = $module->get('alias');
                        if (isset($menu['onlyin'])) {
                            if (config('app.'.$menu['onlyin'])) {
                                array_push($menus, $menu);
                            }
                        } else {
                            array_push($menus, $menu);
                        }

                    }
                }

                $availableApps = config('settings.apps_available', '');
                //If empty string, all apps allowed, if not filter the apps
                if ($availableApps != '') {
                    $availableApps = explode(',', $availableApps);
                    $menus = array_filter($menus, function ($menu) use ($availableApps) {
                        return in_array($menu['alias'], $availableApps);
                    });
                }
            }
        } elseif ($this->hasRole('client')) {
            foreach (Module::all() as $key => $module) {
                if (is_array($module->get('clientmenus'))) {
                    foreach ($module->get('clientmenus') as $key => $menu) {
                        if (isset($menu['onlyin'])) {
                            if (config('app.'.$menu['onlyin'])) {
                                array_push($menus, $menu);
                            }
                        } else {
                            array_push($menus, $menu);
                        }

                    }
                }
            }
        } elseif ($this->hasRole('owner')) {
            $menus = $this->collectOwnerModuleMenus();
        } elseif ($this->isOrganizationManager()) {
            $menus = $this->collectManagerModuleMenus();
        } elseif ($this->hasRole('staff') || $this->isOrganizationAgent()) {
            foreach (Module::all() as $key => $module) {
                if (($module->get('alias') ?? '') === 'reports') {
                    continue;
                }

                if (! is_array($module->get('staffmenus'))) {
                    continue;
                }

                foreach ($module->get('staffmenus') as $menu) {
                    if (isset($menu['onlyin']) && ! str_contains((string) $menu['onlyin'], config('settings.app_code_name'))) {
                        continue;
                    }

                    $routeName = $menu['route'] ?? '';
                    if ($routeName === '' || ! \Illuminate\Support\Facades\Route::has($routeName)) {
                        continue;
                    }

                    $menus[] = $menu;
                }
            }
        }

        //Sort the menus by priority
        usort($menus, function ($a, $b) {
            return (isset($a['priority']) ? $a['priority'] : 100) <=> (isset($b['priority']) ? $b['priority'] : 100);
        });

        return $menus;
    }

    /**
     * Raw owner menus from enabled modules (before job-based grouping).
     *
     * @return array<int, array<string, mixed>>
     */
    public function collectOwnerModuleMenus(): array
    {
        $menus = [];
        $allowedPluginsPerPlan = $this->currentCompany()
            ? $this->currentCompany()->getPlanAttribute()['allowedPluginsPerPlan']
            : null;

        foreach (Module::all() as $module) {
            if (! is_array($module->get('ownermenus'))) {
                continue;
            }

            if (! ($module->get('alwayson') || $allowedPluginsPerPlan === null || in_array($module->get('alias'), $allowedPluginsPerPlan, true))) {
                continue;
            }

            foreach ($module->get('ownermenus') as $menu) {
                if (isset($menu['onlyin']) && ! str_contains($menu['onlyin'], config('settings.app_code_name'))) {
                    continue;
                }

                $menus[] = $menu;
            }
        }

        usort($menus, fn ($a, $b) => ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100));

        return $menus;
    }

    /**
     * Owner sidebar navigation grouped by job (inbox, automations, etc.).
     *
     * @return array<int, array{label: string, menus: array<int, array<string, mixed>>}>
     */
    public function getOwnerNavigationSections(): array
    {
        return app(\App\Services\OwnerNavigationBuilder::class)->build($this);
    }

    /**
     * Manager sidebar navigation grouped by job.
     *
     * @return array<int, array{label: string, menus: array<int, array<string, mixed>>}>
     */
    public function getManagerNavigationSections(): array
    {
        return app(\App\Services\ManagerNavigationBuilder::class)->build($this);
    }

    /**
     * Flat manager menus before grouping.
     *
     * @return array<int, array<string, mixed>>
     */
    public function collectManagerModuleMenus(): array
    {
        $orgAuth = app(OrgAuthorization::class);

        return array_values(array_filter(
            $this->collectOwnerModuleMenus(),
            function (array $menu) use ($orgAuth) {
                $routeName = $menu['route'] ?? null;

                if ($routeName === null) {
                    return ! empty($menu['menus']);
                }

                if ($orgAuth->isProtectedRoute($routeName)) {
                    return false;
                }

                $moduleAlias = $orgAuth->resolveModuleForRoute($routeName);

                return $moduleAlias !== null && $orgAuth->canAccessModule($this, $moduleAlias);
            }
        ));
    }

    public function setImpersonating($id)
    {
        Session::put('impersonate', $id);

        if (auth()->check()) {
            Session::put('impersonator_id', auth()->id());
        }
    }

    public function stopImpersonating()
    {
        app(\App\Services\ImpersonationService::class)->stop();
    }

    public function isImpersonating()
    {
        return app(\App\Services\ImpersonationService::class)->isActive();
    }

    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Get all companies accessible to this user
     */
    public function accessibleCompanies()
    {
        if ($this->hasRole('owner')) {
            return Company::where('user_id', $this->id)->get();
        }

        if ($this->hasRole('admin')) {
            return Company::all();
        }

        $companyIds = $this->activeMemberships()->pluck('company_id');

        if ($companyIds->isEmpty() && $this->company_id !== null) {
            return Company::where('id', $this->company_id)->get();
        }

        return Company::query()->whereIn('id', $companyIds)->get();
    }

    public function routeNotificationForExpo()
    {
        return $this->expotoken.''; //"ExponentPushToken[".$this->expotoken."]";
    }

    protected static function booted()
    {
        parent::booted();

        static::updated(function ($user) {

            Log::info('User updated: '.$user->email);
            if ($user->hasRole('admin')) {
                // Update the translation table with the latest admin user info
                // Assuming the translation table is 'ltu_contributors' as per seeder
                DB::table('ltu_contributors')
                    ->where('email', $user->getOriginal('email'))
                    ->update([
                        'name' => $user->name,
                        'email' => $user->email,
                        'password' => $user->password,
                        'updated_at' => now(),
                    ]);
            }
        });
    }
}
