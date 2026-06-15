<?php

namespace Modules\Managers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CompanyMembership;
use App\Models\CompanyRoleTemplate;
use App\Models\User;
use App\Services\CompanyMembershipService;
use App\Services\ImpersonationService;
use App\Services\OrgAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class Main extends Controller
{
    private string $webroute_path = 'orgmanager.';

    private string $view_path = 'managers::';

    private string $parameter_name = 'membership';

    public function __construct(
        private readonly CompanyMembershipService $membershipService,
        private readonly OrgAuthorization $orgAuthorization,
    ) {
    }

    private function authChecker(): void
    {
        $this->orgAuthorization->assertOwnerAccount();
    }

    private function getCompanyManagers()
    {
        return CompanyMembership::query()
            ->where('company_id', $this->getCompany()->id)
            ->where('role', CompanyMembership::ROLE_MANAGER)
            ->with(['user', 'modules'])
            ->latest('id');
    }

    public function index(): View
    {
        $this->authChecker();

        return view($this->view_path.'index', [
            'setup' => [
                'title' => __('Organization managers'),
                'action_link' => route($this->webroute_path.'create'),
                'action_name' => __('Add manager'),
                'items' => $this->getCompanyManagers()->paginate(config('settings.paginate')),
                'item_names' => 'managers',
                'webroute_path' => $this->webroute_path,
                'parameter_name' => $this->parameter_name,
            ],
            'templatesLink' => route($this->webroute_path.'templates.index'),
        ]);
    }

    public function create(): View
    {
        $this->authChecker();
        $company = $this->getCompany();

        return view($this->view_path.'form', [
            'setup' => [
                'title' => __('New manager'),
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => __('Back'),
                'action' => route($this->webroute_path.'store'),
                'isupdate' => false,
            ],
            'modules' => $this->orgAuthorization->grantableModulesForCompany($company),
            'templates' => $this->membershipService->templatesForCompany($company),
            'selectedModules' => [],
            'membership' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authChecker();
        $company = $this->getCompany();

        if (! $this->membershipService->canAddManager($company)) {
            return redirect()->route($this->webroute_path.'index')
                ->withStatus($this->membershipService->managerLimitExceededMessage($company));
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
            'modules' => 'nullable|array',
            'modules.*' => 'string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'in:view,manage',
        ]);

        $moduleGrants = $this->buildModuleGrants($request);

        if ($request->filled('template_id')) {
            $template = CompanyRoleTemplate::query()->findOrFail($request->integer('template_id'));
            $moduleGrants = $template->moduleGrants();
        }

        $existing = User::query()->where('email', $request->email)->first();

        if ($existing !== null && $existing->hasRole('owner')) {
            return redirect()->back()->withStatus(__('This email belongs to an account owner and cannot be added as a manager.'));
        }

        $this->membershipService->createManager(
            $company,
            auth()->user(),
            $request->name,
            $request->email,
            $request->password,
            $moduleGrants,
        );

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Manager has been added.'));
    }

    public function edit(CompanyMembership $membership): View
    {
        $this->authChecker();
        $this->assertMembershipBelongsToCompany($membership);
        $company = $this->getCompany();

        return view($this->view_path.'form', [
            'setup' => [
                'title' => __('Edit manager'),
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => __('Back'),
                'action' => route($this->webroute_path.'update', $membership),
                'isupdate' => true,
            ],
            'modules' => $this->orgAuthorization->grantableModulesForCompany($company),
            'templates' => $this->membershipService->templatesForCompany($company),
            'selectedModules' => $membership->modules()->pluck('module_alias')->all(),
            'selectedPermissions' => $membership->modules()->pluck('permission', 'module_alias')->all(),
            'membership' => $membership->load('user'),
        ]);
    }

    public function update(Request $request, CompanyMembership $membership): RedirectResponse
    {
        $this->authChecker();
        $this->assertMembershipBelongsToCompany($membership);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'nullable|string|min:6',
            'modules' => 'nullable|array',
            'modules.*' => 'string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'in:view,manage',
        ]);

        $moduleGrants = $this->buildModuleGrants($request);

        if ($request->filled('template_id')) {
            $template = CompanyRoleTemplate::query()->findOrFail($request->integer('template_id'));
            $moduleGrants = $template->moduleGrants();
        }

        $this->membershipService->updateManager(
            $membership,
            auth()->user(),
            $request->name,
            $request->email,
            $request->password,
            $moduleGrants,
        );

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Manager has been updated.'));
    }

    public function destroy(CompanyMembership $membership): RedirectResponse
    {
        $this->authChecker();
        $this->assertMembershipBelongsToCompany($membership);

        $this->membershipService->deleteManager($membership, auth()->user());

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Manager has been removed.'));
    }

    public function loginas(CompanyMembership $membership): RedirectResponse
    {
        $this->authChecker();
        $this->assertMembershipBelongsToCompany($membership);

        if (config('settings.is_demo', false)) {
            return redirect()->back()->withStatus('Not allowed in demo');
        }

        app(ImpersonationService::class)->start($membership->user);

        return redirect(route('home'));
    }

    public function templatesIndex(): View
    {
        $this->authChecker();
        $company = $this->getCompany();

        return view($this->view_path.'templates.index', [
            'templates' => $this->membershipService->templatesForCompany($company),
            'modules' => $this->orgAuthorization->grantableModulesForCompany($company),
            'backLink' => route($this->webroute_path.'index'),
        ]);
    }

    public function templatesStore(Request $request): RedirectResponse
    {
        $this->authChecker();
        $company = $this->getCompany();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'modules' => 'nullable|array',
            'permissions' => 'nullable|array',
        ]);

        $this->membershipService->createCompanyTemplate(
            $company,
            auth()->user(),
            $request->name,
            $request->description,
            $this->buildModuleGrants($request),
        );

        return redirect()->route($this->webroute_path.'templates.index')->withStatus(__('Role template created.'));
    }

    public function templatesDestroy(CompanyRoleTemplate $template): RedirectResponse
    {
        $this->authChecker();

        if ($template->company_id !== null && (int) $template->company_id !== (int) $this->getCompany()->id) {
            abort(403);
        }

        $this->membershipService->deleteTemplate($template, auth()->user());

        return redirect()->route($this->webroute_path.'templates.index')->withStatus(__('Role template deleted.'));
    }

    /**
     * @return array<int, array{module_alias: string, permission: string}>
     */
    private function buildModuleGrants(Request $request): array
    {
        $modules = $request->input('modules', []);
        $permissions = $request->input('permissions', []);
        $grants = [];

        foreach ($modules as $alias) {
            $grants[] = [
                'module_alias' => $alias,
                'permission' => $permissions[$alias] ?? 'manage',
            ];
        }

        return $grants;
    }

    private function assertMembershipBelongsToCompany(CompanyMembership $membership): void
    {
        if ((int) $membership->company_id !== (int) $this->getCompany()->id || ! $membership->isManager()) {
            abort(403, __('Unauthorized action.'));
        }
    }
}
