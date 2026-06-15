<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyMembershipAuditLog;
use App\Models\CompanyMembershipModule;
use App\Models\CompanyRoleTemplate;
use App\Models\CompanyRoleTemplateModule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyMembershipService
{
    public function __construct(private readonly OrgAuthorization $orgAuthorization)
    {
    }

    public function countManagers(Company $company): int
    {
        return (int) CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('role', CompanyMembership::ROLE_MANAGER)
            ->where('status', CompanyMembership::STATUS_ACTIVE)
            ->count();
    }

    public function canAddManager(Company $company): bool
    {
        if (auth()->check() && auth()->user()->hasRole('admin')) {
            return true;
        }

        $limit = (int) config('org-access.manager_seat_limit', 0);

        if ($limit <= 0) {
            return true;
        }

        return $this->countManagers($company) < $limit;
    }

    public function managerLimitExceededMessage(Company $company): string
    {
        $limit = (int) config('org-access.manager_seat_limit', 0);

        return __('You have reached your manager seat limit (:used of :limit). Remove a manager or contact support.', [
            'used' => $this->countManagers($company),
            'limit' => $limit,
        ]);
    }

    /**
     * @param  array<int, array{module_alias: string, permission?: string}>  $moduleGrants
     */
    public function createManager(
        Company $company,
        User $invitedBy,
        string $name,
        string $email,
        string $password,
        array $moduleGrants,
    ): CompanyMembership {
        return DB::transaction(function () use ($company, $invitedBy, $name, $email, $password, $moduleGrants) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'api_token' => Str::random(80),
                    'company_id' => $company->id,
                ]);
            } else {
                $user->forceFill([
                    'name' => $name,
                    'company_id' => $user->company_id ?? $company->id,
                ])->save();
            }

            if (! $user->hasRole('org_manager')) {
                $user->assignRole('org_manager');
            }

            $membership = CompanyMembership::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                ],
                [
                    'role' => CompanyMembership::ROLE_MANAGER,
                    'status' => CompanyMembership::STATUS_ACTIVE,
                    'invited_by' => $invitedBy->id,
                    'accepted_at' => now(),
                ]
            );

            $this->syncModules($membership, $moduleGrants);
            $this->audit($company, $invitedBy, $membership, 'manager.created', [
                'email' => $email,
                'modules' => $moduleGrants,
            ]);

            return $membership->fresh(['user', 'modules']);
        });
    }

    /**
     * @param  array<int, array{module_alias: string, permission?: string}>  $moduleGrants
     */
    public function updateManager(
        CompanyMembership $membership,
        User $actor,
        string $name,
        string $email,
        ?string $password,
        array $moduleGrants,
    ): CompanyMembership {
        return DB::transaction(function () use ($membership, $actor, $name, $email, $password, $moduleGrants) {
            $user = $membership->user;
            $user->name = $name;
            $user->email = $email;

            if ($password !== null && strlen($password) > 2) {
                $user->password = Hash::make($password);
            }

            $user->save();

            $this->syncModules($membership, $moduleGrants);
            $this->audit($membership->company, $actor, $membership, 'manager.updated', [
                'email' => $email,
                'modules' => $moduleGrants,
            ]);

            return $membership->fresh(['user', 'modules']);
        });
    }

    public function suspendManager(CompanyMembership $membership, User $actor): void
    {
        $membership->update(['status' => CompanyMembership::STATUS_SUSPENDED]);
        $this->audit($membership->company, $actor, $membership, 'manager.suspended');
    }

    public function activateManager(CompanyMembership $membership, User $actor): void
    {
        $membership->update(['status' => CompanyMembership::STATUS_ACTIVE]);
        $this->audit($membership->company, $actor, $membership, 'manager.activated');
    }

    public function deleteManager(CompanyMembership $membership, User $actor): void
    {
        DB::transaction(function () use ($membership, $actor) {
            $this->audit($membership->company, $actor, $membership, 'manager.deleted', [
                'email' => $membership->user->email,
            ]);

            $user = $membership->user;
            $membership->delete();

            if (! CompanyMembership::query()->where('user_id', $user->id)->exists()) {
                if ($user->hasRole('org_manager')) {
                    $user->removeRole('org_manager');
                }
            }
        });
    }

    public function ensureAgentMembership(User $user, Company $company, ?User $invitedBy = null): CompanyMembership
    {
        $membership = CompanyMembership::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'company_id' => $company->id,
            ],
            [
                'role' => CompanyMembership::ROLE_AGENT,
                'status' => CompanyMembership::STATUS_ACTIVE,
                'invited_by' => $invitedBy?->id,
                'accepted_at' => now(),
            ]
        );

        if ($membership->role !== CompanyMembership::ROLE_AGENT) {
            $membership->update(['role' => CompanyMembership::ROLE_AGENT]);
        }

        return $membership;
    }

    /**
     * @param  array<int, array{module_alias: string, permission?: string}>  $moduleGrants
     */
    public function syncModules(CompanyMembership $membership, array $moduleGrants): void
    {
        $company = $membership->company;
        $allowedAliases = collect($this->orgAuthorization->grantableModulesForCompany($company))
            ->pluck('alias')
            ->all();

        $membership->modules()->delete();

        foreach ($moduleGrants as $grant) {
            $alias = $grant['module_alias'] ?? null;
            $permission = $grant['permission'] ?? 'manage';

            if ($alias === null || ! in_array($alias, $allowedAliases, true)) {
                continue;
            }

            if (! in_array($permission, ['view', 'manage'], true)) {
                $permission = 'manage';
            }

            CompanyMembershipModule::create([
                'company_membership_id' => $membership->id,
                'module_alias' => $alias,
                'permission' => $permission,
            ]);
        }
    }

    public function applyTemplate(CompanyMembership $membership, CompanyRoleTemplate $template, User $actor): void
    {
        $this->syncModules($membership, $template->moduleGrants());
        $this->audit($membership->company, $actor, $membership, 'manager.template_applied', [
            'template' => $template->name,
        ]);
    }

    public function seedSystemTemplates(): void
    {
        if (CompanyRoleTemplate::query()->where('is_system', true)->exists()) {
            return;
        }

        $templates = [
            [
                'name' => 'Operations manager',
                'description' => 'Inbox, contacts, campaigns, and workflows.',
                'modules' => [
                    ['module_alias' => 'wpbox', 'permission' => 'manage'],
                    ['module_alias' => 'contacts', 'permission' => 'manage'],
                    ['module_alias' => 'flowmaker', 'permission' => 'manage'],
                    ['module_alias' => 'agents', 'permission' => 'manage'],
                ],
            ],
            [
                'name' => 'Support lead',
                'description' => 'Inbox, contacts, and knowledge base (view reports).',
                'modules' => [
                    ['module_alias' => 'wpbox', 'permission' => 'manage'],
                    ['module_alias' => 'contacts', 'permission' => 'manage'],
                    ['module_alias' => 'knowledge', 'permission' => 'manage'],
                    ['module_alias' => 'reports', 'permission' => 'view'],
                ],
            ],
            [
                'name' => 'Analytics viewer',
                'description' => 'Read-only access to reports.',
                'modules' => [
                    ['module_alias' => 'reports', 'permission' => 'view'],
                ],
            ],
        ];

        foreach ($templates as $templateData) {
            $template = CompanyRoleTemplate::create([
                'company_id' => null,
                'name' => $templateData['name'],
                'description' => $templateData['description'],
                'is_system' => true,
            ]);

            foreach ($templateData['modules'] as $module) {
                CompanyRoleTemplateModule::create([
                    'company_role_template_id' => $template->id,
                    'module_alias' => $module['module_alias'],
                    'permission' => $module['permission'],
                ]);
            }
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, CompanyRoleTemplate>
     */
    public function templatesForCompany(Company $company)
    {
        return CompanyRoleTemplate::query()
            ->where(function ($query) use ($company) {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $company->id);
            })
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<int, array{module_alias: string, permission?: string}>  $moduleGrants
     */
    public function createCompanyTemplate(Company $company, User $actor, string $name, ?string $description, array $moduleGrants): CompanyRoleTemplate
    {
        return DB::transaction(function () use ($company, $actor, $name, $description, $moduleGrants) {
            $template = CompanyRoleTemplate::create([
                'company_id' => $company->id,
                'name' => $name,
                'description' => $description,
                'is_system' => false,
            ]);

            foreach ($moduleGrants as $grant) {
                CompanyRoleTemplateModule::create([
                    'company_role_template_id' => $template->id,
                    'module_alias' => $grant['module_alias'],
                    'permission' => $grant['permission'] ?? 'manage',
                ]);
            }

            CompanyMembershipAuditLog::create([
                'company_id' => $company->id,
                'actor_user_id' => $actor->id,
                'action' => 'template.created',
                'metadata' => ['name' => $name],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return $template->fresh('modules');
        });
    }

    public function deleteTemplate(CompanyRoleTemplate $template, User $actor): void
    {
        if ($template->is_system) {
            abort(403, __('System templates cannot be deleted.'));
        }

        CompanyMembershipAuditLog::create([
            'company_id' => $template->company_id,
            'actor_user_id' => $actor->id,
            'action' => 'template.deleted',
            'metadata' => ['name' => $template->name],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        $template->delete();
    }

    private function audit(Company $company, User $actor, CompanyMembership $membership, string $action, array $metadata = []): void
    {
        CompanyMembershipAuditLog::create([
            'company_id' => $company->id,
            'actor_user_id' => $actor->id,
            'target_membership_id' => $membership->id,
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
