<?php

namespace Modules\Agents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ImpersonationService;
use App\Services\OrgAuthorization;
use App\Services\PlanResourceLimit;
use App\Services\PlanSeatBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Main extends Controller
{
    /**
     * Provide class.
     */
    private $provider = User::class;

    /**
     * Web RoutePath for the name of the routes.
     */
    private $webroute_path = 'agent.';

    /**
     * View path.
     */
    private $view_path = 'agents::';

    /**
     * Parameter name.
     */
    private $parameter_name = 'agent';

    /**
     * Title of this crud.
     */
    private $title = 'agent';

    /**
     * Title of this crud in plural.
     */
    private $titlePlural = 'agent';

    /**
     * View access — list and edit forms.
     */
    private function authCheckerView(): void
    {
        $user = auth()->user();

        if ($user->hasRole('owner')) {
            return;
        }

        app(OrgAuthorization::class)->assertModuleAccess('agents', 'view');
    }

    /**
     * Manage access — create, update, delete, login as.
     */
    private function authCheckerManage(): void
    {
        $user = auth()->user();

        if ($user->hasRole('owner')) {
            return;
        }

        app(OrgAuthorization::class)->assertModuleAccess('agents', 'manage');
    }

    private function assertAgentInCompany(User $agent): void
    {
        if ((int) $agent->company_id !== (int) $this->getCompany()->id) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function getFields()
    {
        return [
            ['class' => 'col-md-4', 'ftype' => 'input', 'name' => 'Name', 'id' => 'name', 'placeholder' => 'First and Last name', 'required' => true],
            ['class' => 'col-md-4', 'ftype' => 'input', 'name' => 'Email', 'id' => 'email', 'placeholder' => 'Enter email', 'required' => true],
            ['class' => 'col-md-4', 'ftype' => 'input', 'type' => 'password', 'name' => 'Password', 'id' => 'password', 'placeholder' => 'Enter password', 'required' => true],
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authCheckerView();
        $fields = $this->getFields();
        unset($fields[2]);
        $orgAuth = app(OrgAuthorization::class);

        return view($this->view_path.'index', ['setup' => [
            'title' => __('crud.item_managment', ['item' => __($this->titlePlural)]),
            'action_link' => $orgAuth->canManageAgents() ? route($this->webroute_path.'create') : null,
            'action_name' => __('crud.add_new_item', ['item' => __($this->title)]),
            'items' => $this->getCompany()->staff()->paginate(config('settings.paginate')),
            'item_names' => $this->titlePlural,
            'webroute_path' => $this->webroute_path,
            'fields' => $fields,
            'parameter_name' => $this->parameter_name,
            'canManageAgents' => $orgAuth->canManageAgents(),
        ]]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authCheckerManage();

        return view('general.form', ['setup' => [
            'inrow' => true,
            'title' => __('crud.new_item', ['item' => __($this->title)]),
            'action_link' => route($this->webroute_path.'index'),
            'action_name' => __('crud.back'),
            'iscontent' => true,
            'action' => route($this->webroute_path.'store'),
        ],
            'fields' => $this->getFields(), ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authCheckerManage();

        $company = $this->getCompany();
        $resourceLimit = app(PlanResourceLimit::class);

        if (! $resourceLimit->canAddAgent($company)) {
            return redirect()->route($this->webroute_path.'index')->withStatus($resourceLimit->agentLimitExceededMessage($company));
        }

        $email_exist = $this->provider::where('email', $request->email)->first();

        if (! $email_exist) {
            $item = $this->provider::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'password' => Hash::make($request->password),
                'api_token' => Str::random(80),
                'company_id' => $this->getCompany()->id,
            ]);
            $item->save();

            $item->assignRole('staff');

            app(\App\Services\CompanyMembershipService::class)->ensureAgentMembership(
                $item,
                $company,
                auth()->user()
            );

            $billingSynced = app(PlanSeatBillingService::class)->syncForCompanyOwner($company);
            $status = __('crud.item_has_been_added', ['item' => __($this->title)]);

            if (! $billingSynced && app(PlanSeatBillingService::class)->shouldSync($company->user)) {
                $status .= ' '.__('Billing could not be updated automatically — check your subscription.');
            }

            return redirect()->route($this->webroute_path.'index')->withStatus($status);
        } else {

            return redirect()->route($this->webroute_path.'index')->withStatus(__('Error: This email address is already registered. Please use a different email address.', ['item' => __($this->title)]));
        }
    }

    public function loginas($id)
    {
        $this->authCheckerManage();
        if (config('settings.is_demo', false)) {
            return redirect()->back()->withStatus('Not allowed in demo');
        }

        $agent = User::findOrFail($id);
        $this->assertAgentInCompany($agent);

        app(ImpersonationService::class)->start($agent);

        return redirect(route('home'));

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->authCheckerManage();

        $item = $this->provider::findOrFail($id);
        if ((int) $this->getCompany()->id !== (int) $item->company_id) {
            abort(403, 'Unauthorized action.');
        }

        $fields = $this->getFields();
        $fields[0]['value'] = $item->name;
        $fields[1]['value'] = $item->email;

        $parameter = [];
        $parameter[$this->parameter_name] = $id;

        return view('general.form', ['setup' => [
            'inrow' => true,
            'title' => __('crud.edit_item_name', ['item' => __($this->title), 'name' => $item->name]),
            'action_link' => route($this->webroute_path.'index'),
            'action_name' => __('crud.back'),
            'iscontent' => true,
            'isupdate' => true,
            'action' => route($this->webroute_path.'update', $parameter),
        ],
            'fields' => $fields, ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->authCheckerManage();
        $item = $this->provider::findOrFail($id);
        $this->assertAgentInCompany($item);
        $item->name = $request->name;
        $item->email = $request->email;
        if ($request->password && strlen($request->password) > 2) {
            $item->password = Hash::make($request->password);
        }
        $item->update();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_updated', ['item' => __($this->title)]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->authCheckerManage();
        $item = $this->provider::findOrFail($id);
        $this->assertAgentInCompany($item);
        $company = $item->company;
        $item->delete();

        if ($company) {
            app(PlanSeatBillingService::class)->syncForCompanyOwner($company);
        }

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_removed', ['item' => __($this->title)]));
    }
}
