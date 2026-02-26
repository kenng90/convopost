<?php

namespace Modules\Journies\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Journies\Events\ContactMovedToStage;
use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyStage;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;

class StagesController extends Controller
{
    /**
     * Provide class.
     */
    private $provider = JourneyStage::class;

    /**
     * Web RoutePath for the name of the routes.
     */
    private $webroute_path = 'stages.';

    /**
     * View path.
     */
    private $view_path = 'journies::stages.';

    /**
     * Parameter name.
     */
    private $parameter_name = 'stage';

    /**
     * Title of this crud.
     */
    private $title = 'stage';

    /**
     * Title of this crud in plural.
     */
    private $titlePlural = 'stages';

    private function getFields($class='col-md-4')
    {
        $fields=[];
        
        //Add name field
        $fields[0]=['class'=>$class, 'ftype'=>'input', 'name'=>'Name', 'id'=>'name', 'placeholder'=>'Enter name', 'required'=>true];
       
        //Campaign
        $fields[1]=['class'=>$class, 'ftype'=>'select', 'name'=>'Campaign', 'id'=>'campaign', 'placeholder'=>'Select campaign', 'required'=>true, 
        'data'=>Campaign::where('is_api', true)->get()->pluck('name', 'id')->toArray()];


        //Add info field
        $fields[2]=['class'=>$class, 'ftype'=>'info', 'name'=>'Info', 'id'=>'info', 'text'=>'When a contact enters this stage, the selected API campaign will be triggered. Create new API campaigns using the button below.','button'=>['text'=>'Create API Campaign', 'link'=>route('wpbox.api.index', ['type' => 'api'])]];


        //Return fields
        return $fields;
    }

    private function getFilterFields(){
        $fields=$this->getFields('col-md-3');
        return $fields;
    }

    /**
     * Auth checker function for the crud.
     */
    private function authChecker()
    {
        $this->ownerAndStaffOnly();
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create(Journey $journey)
    {
       $this->authChecker();
        
        $fields = $this->getFields('col-md-6');

       
        return view('general.form', ['setup' => [
            'title'=>__('crud.new_item', ['item'=>__('Stage')]),
            'action_link'=>route('journies.kanban', $journey),
            'action_name'=>__('crud.back'),
            'iscontent'=>true,
            'action'=>route($this->webroute_path.'store', ['journey'=>$journey])
        ],
        'fields'=>$fields ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request, Journey $journey)
    {
        $this->authChecker();

        //Make sure thtat campaign_id is present
        if(!$request->campaign){
            return redirect()->route($this->webroute_path.'create',['journey'=>$journey])->withStatus('Campaign is required');
        }
        
        //Create new stage
        $stage = $this->provider::create([
            'name' => $request->name,
            'journey_id' => $journey->id,
            'campaign_id' => $request->campaign,
        ]);
        $stage->save();

        return redirect()->route('journies.kanban', $journey)->withStatus(__('crud.item_has_been_added', ['item'=>__($this->title)]));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit($id)
    {
        $this->authChecker();
        $stage = $this->provider::findOrFail($id);

        $fields = $this->getFields('col-md-6');
        $fields[0]['value'] = $stage->name;
        $fields[1]['value'] = $stage->campaign_id;

        $parameter = [];
        $parameter[$this->parameter_name] = $stage->id;

        return view('general.form', ['setup' => [
            'title'=>__('crud.edit_item_name', ['item'=>__($this->title), 'name'=>$stage->name]),
            'action_link'=>route('journies.kanban', $stage->journey),
            'action_name'=>__('crud.back'),
            'iscontent'=>true,
            'isupdate'=>true,
            'action'=>route($this->webroute_path.'update', $parameter),
        ],
        'fields'=>$fields ]);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);
        $item->name = $request->name;
        $item->campaign_id = $request->campaign;
        $item->update();

        return redirect()->route('journies.kanban', $item->journey)->withStatus(__('crud.item_has_been_updated', ['item'=>__($this->title)]));
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);
        $item->delete();
        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_removed', ['item'=>__($this->title)]));
    }

    public function moveContact($stageId, $contactId)
    {
        $putInThisStage = $this->provider::findOrFail($stageId);
        $contact = Contact::findOrFail($contactId);

        //Find the stage journey
        $journey = $putInThisStage->journey;

        //Loop through the stages and  remove the contact from the stage
        foreach($journey->stages as $stage){
            $stage->contacts()->detach($contact);
        }

        //Add the contact to the new stage
        $putInThisStage->contacts()->attach($contact);

        //Fire the ContactMovedToStage event
        event(new ContactMovedToStage($contact, $putInThisStage));

        //

        return response()->json(['success' => true]);
    }

    public function moveContactFromSideapp(Request $request)
    {
        $contact = Contact::findOrFail($request->contact_id);
        $stage = $this->provider::findOrFail($request->stage_id);
        return $this->moveContact($stage->id, $contact->id);
    }
}
