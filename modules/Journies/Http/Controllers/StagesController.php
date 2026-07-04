<?php

namespace Modules\Journies\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyGroupRule;
use Modules\Journies\Models\JourneyStage;
use Modules\Journies\Services\JourneyContactService;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;

class StagesController extends Controller
{
    private $provider = JourneyStage::class;

    private $webroute_path = 'stages.';

    private $parameter_name = 'stage';

    private $title = 'stage';

    public function __construct(private JourneyContactService $journeyContacts)
    {
    }

    private function getFields($class = 'col-md-4', ?Journey $journey = null)
    {
        $campaigns = Campaign::where('is_api', true)->get()->pluck('name', 'id')->toArray();
        $campaigns = ['' => __('No automation')] + $campaigns;

        return [
            ['class' => $class, 'ftype' => 'input', 'name' => 'Name', 'id' => 'name', 'placeholder' => __('Enter name'), 'required' => true],
            ['class' => $class, 'ftype' => 'select', 'name' => 'Campaign', 'id' => 'campaign', 'placeholder' => __('Select campaign (optional)'), 'required' => false, 'data' => $campaigns],
            ['class' => $class, 'ftype' => 'input', 'name' => 'Campaign delay (minutes)', 'id' => 'campaign_delay_minutes', 'placeholder' => '0', 'required' => false],
            ['class' => $class, 'ftype' => 'info', 'name' => 'Info', 'id' => 'info', 'text' => __('When a contact enters this stage, the selected API campaign can be triggered automatically. Leave campaign empty for manual-only stages.'), 'button' => ['text' => __('Create API Campaign'), 'link' => route('wpbox.api.create')]],
        ];
    }

    private function authChecker(): void
    {
        $this->ownerAndStaffOnly();
    }

    public function create(Journey $journey)
    {
        $this->authChecker();

        return view('general.form', [
            'setup' => [
                'title' => __('crud.new_item', ['item' => __('Stage')]),
                'action_link' => route('journies.kanban', $journey),
                'action_name' => __('crud.back'),
                'iscontent' => true,
                'action' => route($this->webroute_path.'store', ['journey' => $journey]),
            ],
            'fields' => $this->getFields('col-md-6', $journey),
        ]);
    }

    public function store(Request $request, Journey $journey)
    {
        $this->authChecker();

        $request->validate([
            'name' => 'required|string|max:255',
            'campaign' => 'nullable|integer',
            'campaign_delay_minutes' => 'nullable|integer|min:0|max:10080',
        ]);

        $nextOrder = (int) $journey->stages()->max('order') + 1;

        $this->provider::create([
            'name' => $request->name,
            'journey_id' => $journey->id,
            'campaign_id' => $request->campaign ?: null,
            'campaign_delay_minutes' => (int) ($request->campaign_delay_minutes ?? 0),
            'order' => $nextOrder,
        ]);

        return redirect()->route('journies.kanban', $journey)
            ->withStatus(__('crud.item_has_been_added', ['item' => __($this->title)]));
    }

    public function edit($id)
    {
        $this->authChecker();
        $stage = $this->provider::findOrFail($id);

        $fields = $this->getFields('col-md-6', $stage->journey);
        $fields[0]['value'] = $stage->name;
        $fields[1]['value'] = $stage->campaign_id ?? '';
        $fields[2]['value'] = $stage->campaign_delay_minutes ?? 0;

        return view('general.form', [
            'setup' => [
                'title' => __('crud.edit_item_name', ['item' => __($this->title), 'name' => $stage->name]),
                'action_link' => route('journies.kanban', $stage->journey),
                'action_name' => __('crud.back'),
                'iscontent' => true,
                'isupdate' => true,
                'action' => route($this->webroute_path.'update', ['stage' => $stage->id]),
            ],
            'fields' => $fields,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authChecker();

        $request->validate([
            'name' => 'required|string|max:255',
            'campaign' => 'nullable|integer',
            'campaign_delay_minutes' => 'nullable|integer|min:0|max:10080',
        ]);

        $item = $this->provider::findOrFail($id);
        $item->update([
            'name' => $request->name,
            'campaign_id' => $request->campaign ?: null,
            'campaign_delay_minutes' => (int) ($request->campaign_delay_minutes ?? 0),
        ]);

        return redirect()->route('journies.kanban', $item->journey)
            ->withStatus(__('crud.item_has_been_updated', ['item' => __($this->title)]));
    }

    public function destroy($id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);
        $journey = $item->journey;
        $item->delete();

        return redirect()->route('journies.kanban', $journey)
            ->withStatus(__('crud.item_has_been_removed', ['item' => __($this->title)]));
    }

    public function reorder(Request $request, Journey $journey): JsonResponse
    {
        $this->authChecker();

        $request->validate([
            'stage_ids' => 'required|array',
            'stage_ids.*' => 'integer',
        ]);

        foreach ($request->stage_ids as $order => $stageId) {
            JourneyStage::query()
                ->where('journey_id', $journey->id)
                ->where('id', $stageId)
                ->update(['order' => $order]);
        }

        return response()->json(['success' => true]);
    }

    public function moveContact($stageId, $contactId): JsonResponse
    {
        $this->authChecker();

        $stage = $this->provider::findOrFail($stageId);
        $contact = Contact::findOrFail($contactId);

        $result = $this->journeyContacts->moveContactToStage(
            $contact,
            $stage,
            'manual_kanban',
            auth()->id(),
            true,
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function moveContactFromSideapp(Request $request): JsonResponse
    {
        $this->authChecker();

        $company = auth()->user()?->currentCompany();
        if (! $company || ! $company->hasPlanPlugin('journies')) {
            return response()->json(['success' => false, 'message' => __('Journeys are not available on your plan.')], 403);
        }

        $request->validate([
            'stage_id' => 'required|integer|exists:journey_stages,id',
            'contact_id' => 'required|integer|exists:contacts,id',
            'fire_campaign' => 'sometimes|boolean',
        ]);

        $contact = Contact::findOrFail($request->contact_id);
        $stage = $this->provider::findOrFail($request->stage_id);

        $result = $this->journeyContacts->moveContactToStage(
            $contact,
            $stage,
            'manual_sidebar',
            auth()->id(),
            $request->boolean('fire_campaign', true),
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function groupRules(Journey $journey)
    {
        $this->authChecker();

        $journey->load(['stages', 'groupRules.group:id,name']);

        $groups = \Modules\Contacts\Models\Group::query()->orderBy('name')->get(['id', 'name']);

        return view('journies::group-rules', compact('journey', 'groups'));
    }

    public function storeGroupRule(Request $request, Journey $journey)
    {
        $this->authChecker();

        $request->validate([
            'group_id' => 'required|integer',
            'stage_id' => 'required|integer|exists:journey_stages,id',
        ]);

        JourneyGroupRule::updateOrCreate(
            [
                'company_id' => $journey->company_id,
                'group_id' => $request->group_id,
                'journey_id' => $journey->id,
            ],
            ['stage_id' => $request->stage_id]
        );

        return redirect()->route('journies.group-rules', $journey)
            ->withStatus(__('Group rule saved. Contacts added to this group will move to the selected stage.'));
    }

    public function destroyGroupRule(Journey $journey, JourneyGroupRule $rule)
    {
        $this->authChecker();

        if ((int) $rule->journey_id !== (int) $journey->id) {
            abort(404);
        }

        $rule->delete();

        return redirect()->route('journies.group-rules', $journey)
            ->withStatus(__('Group rule removed.'));
    }
}
