<?php

namespace Modules\Journies\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Contacts\Models\Contact;
use Modules\Journies\Events\ContactMovedToStage;
use Modules\Journies\Models\Journey;
use Modules\Wpbox\Models\Contact as ModelsContact;

class Main extends Controller
{
    /**
     * Provide class.
     */
    private $provider = Journey::class;

    /**
     * Web RoutePath for the name of the routes.
     */
    private $webroute_path = 'journies.';

    /**
     * View path.
     */
    private $view_path = 'journies::';

    /**
     * Parameter name.
     */
    private $parameter_name = 'journey';

    /**
     * Title of this crud.
     */
    private $title = 'journey';

    /**
     * Title of this crud in plural.
     */
    private $titlePlural = 'journies';

    private function getFields($class='col-md-4')
    {
        $fields=[];
        
        //Add name field
        $fields[0]=['class'=>$class, 'ftype'=>'input', 'name'=>'Name', 'id'=>'name', 'placeholder'=>'Enter name', 'required'=>true];
        $fields[1]=['class'=>$class, 'ftype'=>'textarea', 'name'=>'Description', 'id'=>'description', 'placeholder'=>'Enter description'];


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
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        $this->authChecker();

        $items = $this->provider::orderBy('id', 'desc');
        $items = $items->paginate(config('settings.paginate'));

        $setup=[
            'usefilter'=>null,
            'title'=>__('Journies'),
            'action_link2'=>route($this->webroute_path.'create'),
            'action_name2'=>__('Create journey'),
            'items'=>$items,
            'item_names'=>$this->titlePlural,
            'webroute_path'=>$this->webroute_path,
            'fields'=>$this->getFields('col-md-3'),
            'filterFields'=>$this->getFilterFields(),
            'custom_table'=>true,
            'parameter_name'=>$this->parameter_name,
            'parameters'=>count($_GET) != 0,
            'hidePaging'=>false,
        ];
        return view($this->view_path.'index', ['setup' => $setup]);
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        $this->authChecker();
        
        $fields = $this->getFields('col-md-6');
       

        return view('general.form', ['setup' => [
            'title'=>__('crud.new_item', ['item'=>__('Journey')]),
            'action_link'=>route($this->webroute_path.'index'),
            'action_name'=>__('crud.back'),
            'iscontent'=>true,
            'action'=>route($this->webroute_path.'store')
        ],
        'fields'=>$fields ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $this->authChecker();
        
        //Create new journey
        $journey = $this->provider::create([
            'name' => $request->name,
            'description' => $request->description
        ]);
        $journey->save();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_added', ['item'=>__($this->title)]));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit($id)
    {
        $this->authChecker();
        $journey = $this->provider::findOrFail($id);

        $fields = $this->getFields('col-md-6');
        $fields[0]['value'] = $journey->name;
        $fields[1]['value'] = $journey->description;

        

        $parameter = [];
        $parameter[$this->parameter_name] = $journey->id;

        return view('general.form', ['setup' => [
            'title'=>__('crud.edit_item_name', ['item'=>__($this->title), 'name'=>$journey->name]),
            'action_link'=>route($this->webroute_path.'index'),
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
        $item->description = $request->description;
        $item->update();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_updated', ['item'=>__($this->title)]));
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

    public function kanban(Journey $journey)
    {
        $stages = $journey->stages; 
        $contacts = Contact::all();

      

        //Remove the contacts that are already in the journey
        /*$existingContacts = $journey->contacts->pluck('id');
        dd($existingContacts);
        if($existingContacts->count()>0){
            $contacts = $contacts->whereNotIn('id', $existingContacts);
        }*/

        //Load stages and stages contacts
        $stages = $stages->load('contacts');

        
        if($stages->count()==0){
            return redirect()->route('stages.create', $journey)->withStatus(__('Add the first stage to start the journey'));
        }
        return view('journies::kanban', ['journey' => $journey, 'contacts' => $contacts]);
    }

    public function addContact(Journey $journey, Request $request)
    {
       //Get the first stage
       $stage = $journey->stages->first();
       $stage->contacts()->attach($request->contact_id, ['stage_id' => $stage->id]);

       //Send campaign
       event(new ContactMovedToStage(Contact::findOrFail($request->contact_id), $stage));

        return redirect()->route('journies.kanban', $journey)->withStatus(__('Contact added to journey'));
    }


    public function getJournies(ModelsContact $contact){
       //Get all the journeys
       $journeys = Journey::with(['stages' => function($query) {
           $query->select('id', 'journey_id', 'name');
       }])->select('id', 'name')->get()->map(function($journey) use ($contact) {
           $journey->stages->transform(function($stage) use ($contact) {
               $stage->contact_in = $stage->contacts()->where('contacts.id', $contact->id)->exists();
               return $stage;
           });
           return $journey;
       });

       
       return response()->json($journeys);
    }

   
}
