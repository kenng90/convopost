<?php

namespace Modules\Flowmaker\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Flowmaker\FlowTemplateService;
use App\Services\PlanEntitlementResolver;
use App\Services\PlanUsageLimit;
use App\Services\Platform\ManagedAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Flowmaker\Models\Flow;
use Modules\Wpbox\Models\Reply;

class FlowsController extends Controller
{
    public function __construct(
        private FlowTemplateService $templates,
    ) {
    }

    /**
     * Provide class.
     */
    private $provider = Flow::class;

    /**
     * Web RoutePath for the name of the routes.
     */
    private $webroute_path = 'flows.';

    /**
     * View path.
     */
    private $view_path = 'flowmaker::flows.';

    /**
     * Parameter name.
     */
    private $parameter_name = 'flow';

    /**
     * Title of this crud.
     */
    private $title = 'flow';

    /**
     * Title of this crud in plural.
     */
    private $titlePlural = 'flows';

    private function getFields($class = 'col-md-4')
    {
        $fields = [];

        //Add name field
        $fields[0] = ['class' => $class, 'ftype' => 'input', 'name' => 'Name', 'id' => 'name', 'placeholder' => 'Enter name', 'required' => true];

        //Return fields
        return $fields;
    }

    private function getFilterFields()
    {
        $fields = $this->getFields('col-md-3', 'qr');

        return $fields;
    }

    /**
     * Auth checker functin for the crud.
     */
    private function authChecker()
    {
        $this->ownerAndStaffOnly();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authChecker();

        $items = $this->provider::orderBy('id', 'desc');
        $items = $items->paginate(config('settings.paginate'));

        //Regular, bot ant template based bot
        $setup = [
            'usefilter' => null,
            'title' => __('Flows'),
            'action_link2' => route($this->webroute_path.'create'),
            'action_name2' => __('Create flow'),
            'items' => $items,
            'item_names' => $this->titlePlural,
            'webroute_path' => $this->webroute_path,
            'fields' => $this->getFields('col-md-3'),
            'filterFields' => $this->getFilterFields(),
            'custom_table' => true,
            'parameter_name' => $this->parameter_name,
            'parameters' => count($_GET) != 0,
            'hidePaging' => false,
        ];

        $company = auth()->user()?->currentCompany();
        $aiStatus = $company
            ? app(ManagedAiService::class)->status($company)
            : ['enabled' => false, 'monthly_allowance' => 0, 'remaining' => 0, 'can_generate' => false, 'generate_cost' => 0];

        $user = auth()->user();
        $plan = $user ? app(PlanUsageLimit::class)->resolvePlanForUser($user) : null;
        $hasAiFlowAssistant = $plan && app(PlanEntitlementResolver::class)->hasCapability($plan, 'ai_flow_assistant');

        return view($this->view_path.'index', [
            'setup' => $setup,
            'templates' => $this->templates->all(),
            'aiStatus' => $aiStatus,
            'hasAiFlowAssistant' => $hasAiFlowAssistant,
        ]);
    }

    public function createFromTemplate(string $template)
    {
        $this->authChecker();

        $flow = $this->templates->install($template);

        if (! $flow) {
            return redirect()->route($this->webroute_path.'index')
                ->withError(__('Template not found.'));
        }

        return redirect()->route('flowmaker.edit', $flow)
            ->withStatus(__('Template installed. Review and publish your flow.'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authChecker();

        $fields = $this->getFields('col-md-6');

        return view('general.form', ['setup' => [
            'title' => __('crud.new_item', ['item' => __('Flow')]),
            'action_link' => route($this->webroute_path.'index', ['type' => 'qr']),
            'action_name' => __('crud.back'),
            'iscontent' => true,
            'action' => route($this->webroute_path.'store'),
        ],
            'fields' => $fields]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authChecker();

        //Create new field
        $field = $this->provider::create([
            'name' => $request->name,
        ]);
        $field->save();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_added', ['item' => __($this->title)]));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Contact  $contacts
     * @return \Illuminate\Http\Response
     */
    public function edit(Flow $flow)
    {
        $this->authChecker();

        $fields = $this->getFields('col-md-6');
        $fields[0]['value'] = $flow->name;

        $parameter = [];
        $parameter[$this->parameter_name] = $flow->id;

        return view($this->view_path.'edit', ['setup' => [
            'title' => __('crud.edit_item_name', ['item' => __($this->title), 'name' => $flow->name]),
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
     * @param  \App\Contact  $contacts
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);
        $item->name = $request->name;
        $item->update();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_updated', ['item' => __($this->title)]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Contact  $contacts
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);

        //Delete all replies to this item
        //Set all next_reply_id to null
        Reply::where('flow_id', $item->id)->orderBy('id', 'desc')->get()->each(function ($reply) {
            $reply->next_reply_id = null;
            $reply->update();
        });
        Reply::where('flow_id', $item->id)->delete();
        $item->delete();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_removed', ['item' => __($this->title)]));
    }

    /**
     * Export flow data as JSON file.
     *
     * @return \Illuminate\Http\Response
     */
    public function export(Flow $flow)
    {
        $this->authChecker();

        $flowData = $flow->flow_data ?? '{}';
        $fileName = 'flow_'.$flow->id.'_'.Str::slug($flow->name).'_'.date('Y-m-d_H-i-s').'.json';

        return response($flowData)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');
    }

    /**
     * Show the import form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showImport(Flow $flow)
    {
        $this->authChecker();

        return view($this->view_path.'import', [
            'flow' => $flow,
            'setup' => [
                'title' => __('Import Flow Data for :name', ['name' => $flow->name]),
                'action_link' => url('/flows'),
                'action_name' => __('crud.back'),
                'action' => url('/flows/'.$flow->id.'/import'),
                'iscontent' => true,
            ],
        ]);
    }

    /**
     * Import flow data from JSON file.
     *
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request, Flow $flow)
    {
        $this->authChecker();

        $request->validate([
            'flow_file' => 'required|file|mimes:json|max:2048',
        ]);

        try {
            $file = $request->file('flow_file');
            $content = file_get_contents($file->path());

            // Validate if it's valid JSON
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return redirect()->back()->withErrors(['flow_file' => 'Invalid JSON file format.']);
            }

            // Update the flow's flow_data
            $flow->flow_data = $content;
            $flow->save();

            return redirect('/flows')
                ->withStatus(__('Flow data has been imported successfully for :name', ['name' => $flow->name]));

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['flow_file' => 'Error importing file: '.$e->getMessage()]);
        }
    }
}
