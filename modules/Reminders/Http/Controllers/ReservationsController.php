<?php

namespace Modules\Reminders\Http\Controllers;

use Modules\Reminders\Models\Reservation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Contacts\Models\Contact;
use Modules\Wpbox\Traits\Contacts;
use Modules\Reminders\Models\Source;
use Modules\Wpbox\Http\Controllers\APIController;
use Modules\Wpbox\Models\Message;

class ReservationsController extends Controller
{
    use Contacts;

    /**
     * Provide class.
     */
    private $provider = Reservation::class;

    /**
     * Web RoutePath for the name of the routes.
     */
    private $webroute_path = 'reminders.reservations.';

    /**
     * View path.
     */
    private $view_path = 'reminders::reservations.';

    /**
     * Parameter name.
     */
    private $parameter_name = 'reservation';

    /**
     * Title of this crud.
     */
    private $title = 'reservation';

    /**
     * Title of this crud in plural.
     */
    private $titlePlural = 'reservations';

    private function getFields($class='col-md-4')
    {
        $fields=[];

         
        //Add contact
        $fields[]=[
            'class'=>$class,
            'ftype'=>'select',
            'name'=>'Contact',
            'id'=>'contact_id',
            'placeholder'=>'Select contact',
            'required'=>true,
            'label'=>__('Contact'),
            'type'=>'select',
            'additionalInfo'=>"<a class='mt-2' href='".route('contacts.create')."'>".__('Add new contact')."</a>",
            'data'=>Contact::all()->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'text' => $contact->name . ' ' . $contact->phone
                ];
            })->pluck('text', 'id')->toArray(),
        ];

        //Add source
        $fields[]=[
            'class'=>$class,
            'ftype'=>'select',
            'name'=>'Source',
            'id'=>'source_id',
            'placeholder'=>'Select source',
            'required'=>true,
            'label'=>__('Source'),
            'additionalInfo'=>"<a class='mt-2' href='".route('reminders.sources.create')."'>".__('Add new source')."</a>",
            'data'=>Source::pluck('name', 'id')->toArray(),
        ];

        //Add date and time input
        $fields[]=[
            'class'=>$class,
            'ftype'=>'input',
            'type'=>'datetime-local',
            'name'=>'Date and time - start',
            'id'=>'start_date',
            'placeholder'=>'Select date and time',
            'required'=>true,
            'label'=>__('Date and time'),
            'step'=>'60'
        ];

        //Add date and time input
        $fields[]=[
            'class'=>$class,
            'ftype'=>'input',
            'type'=>'datetime-local',
            'name'=>'Date and time - end',
            'id'=>'end_date',
            'placeholder'=>'Select date and time',
            'required'=>true,
            'label'=>__('Date and time'),
            'step'=>'60'
        ];

        //Add external reference input
        $fields[]=[
            'class'=>$class,
            'ftype'=>'input',
            'name'=>'External reference',
            'id'=>'external_id',
            'placeholder'=>'External reference',
            'required'=>false,
            'label'=>__('External reference'),
        ];


        //Return fields
        return $fields;
    }


    private function getFilterFields(){
        $fields=$this->getFields('col-md-3');
        $fields[0]['required']=true;

        //Unset the dates
        $fields[2]['type']='date';
        $fields[2]['required']=false;
        $fields[3]['type']='date';
        $fields[3]['required']=false;
        unset($fields[2]['step']);
        unset($fields[3]['step']);

 

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

        $items=$this->provider::orderBy('id', 'desc');
        if(isset($_GET['external_id'])&&strlen($_GET['external_id'])>1){
            $items=$items->where('external_id',  'like', '%'.$_GET['external_id'].'%');
        }

        if (isset($_GET['source_id']) && !empty($_GET['source_id'])) {
            $items = $items->where('source_id', $_GET['source_id']);
        }

        if (isset($_GET['contact_id']) && !empty($_GET['contact_id'])) {
            $items = $items->where('contact_id', $_GET['contact_id']);
        }

        if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
            $items = $items->whereDate('start_date', '>=', $_GET['start_date']);
        }

        if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
            $items = $items->whereDate('end_date', '<=', $_GET['end_date']);
        }


       

        
        $items=$items->paginate(config('settings.paginate'));



        return view($this->view_path.'index', ['setup' => [
            'usefilter'=>true,
            'title'=>__('crud.item_managment', ['item'=>__($this->titlePlural)]),
            'action_link'=>route($this->webroute_path.'create'),
            'action_name'=>__('crud.add_new_item', ['item'=>__($this->title)]),
            'items'=>$items,
            'item_names'=>$this->titlePlural,
            'webroute_path'=>$this->webroute_path,
            'fields'=>$this->getFields(),
            'filterFields'=>$this->getFilterFields(),
            'custom_table'=>true,
            'parameter_name'=>$this->parameter_name,
            'parameters'=>count($_GET) != 0,
            'breadcrumbs' => [
                //[__('Reminders'), route('reminders.index')],
                [__('crud.item_managment', ['item'=>__($this->titlePlural)]), '#'],
            ],
        ]]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authChecker();


        return view('general.form', ['setup' => [
            'title'=>__('crud.new_item', ['item'=>__($this->title)]),
            'action_link'=>route($this->webroute_path.'index'),
            'action_name'=>__('crud.back'),
            'iscontent'=>true,
            'action'=>route($this->webroute_path.'store'),
            'breadcrumbs' => [
                [__('Reservations'), route('reminders.reservations.index')]
            ],
        ],
        'fields'=>$this->getFields() ]);
    }

    //Make reservation
    public function makeReservation(Request $request)
    {
        return $this->authenticate($request,function($request){
           


            //Create reservation
            $reservation=\Modules\Reminders\Models\Reservation::create([
                'contact_id'=>$request->contact_id,
                'source_id'=>$request->source_id,
                'start_date'=>$request->start_date,
                'end_date'=>$request->end_date,
                'external_id'=>$request->external_id
            ]);
            
        },
        );
    }

    //Create API function for creating a new reservation
    public function apiStore(Request $request)
    {

        
        APIController::authenticateStatic($request, function($request){
             //Company
             $company=$this->getCompany();

             //Get or create contact
             $contact=$this->getOrMakeContact($request->phone,$company,$request->name);
 
             //Get or create source
             //Find source by name
             $source=Source::where('name',$request->source)->first();
             if(!$source){
                $source=Source::create(['name'=>$request->source,'company_id'=>$company->id]);
             }

             //Create reservation
             $reservation=Reservation::create([
                'contact_id'=>$contact->id,
                'source_id'=>$source->id,
                'start_date'=>$request->start_date,
                'end_date'=>$request->end_date,
                'status'=>1,
                'external_id'=>$request->external_id
             ]);

             //Return reservation
             return response()->json($reservation);
             
             
        },
        [
            'token' => 'required',
            'phone' => 'required',
            'name' => 'required',
            'source' => 'required',
            'start_date' => 'required',
            'end_date' => 'required'
        ]);
    }
    

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authChecker();
        
        //Create new reminder
        $reservation = $this->provider::create([
            'contact_id'=>$request->contact_id,
            'source_id'=>$request->source_id,
            'start_date'=>$request->start_date,
            'end_date'=>$request->end_date,
            'external_id'=>$request->external_id,
        ]);
        $reservation->save();
     

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_added', ['item'=>__($this->title)]));
    }

    

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Contact  $reminders
     * @return \Illuminate\Http\Response
     */
    public function edit(reservation $reservation)
    {
        $this->authChecker();

        $fields = $this->getFields();

        //Set the values
        $fields[0]['value'] = $reservation->contact->id;
        $fields[1]['value'] = $reservation->source->id;
        $fields[2]['value'] = $reservation->start_date;
        $fields[3]['value'] = $reservation->end_date;
        $fields[4]['value'] = $reservation->external_id;

        $parameter = [];
        $parameter[$this->parameter_name] = $reservation->id;

        return view($this->view_path.'edit', ['setup' => [
            'title'=>__('crud.edit_item_name', ['item'=>__($this->title), 'name'=>$reservation->name]),
            'action_link'=>route($this->webroute_path.'index'),
            'action_name'=>__('crud.back'),
            'iscontent'=>true,
            'isupdate'=>true,
            'action'=>route($this->webroute_path.'update', $parameter),
        ],
        'fields'=>$fields, ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Contact  $reminders
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);

        $item->contact_id = $request->contact_id;
        $item->source_id = $request->source_id;
        $item->start_date = $request->start_date;
        $item->end_date = $request->end_date;
        $item->external_id = $request->external_id;

        $item->update();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_updated', ['item'=>__($this->title)]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Contact  $reminders
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);

        //Delete the messages
        try {
            Message::where('extra', $item->id)->where('status', 0)->delete();
        } catch (\Throwable $th) {
            //throw $th;
        }
        


        $item->delete();
        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_removed', ['item'=>__($this->title)]));
    }
    
}
