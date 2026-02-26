<?php

namespace Modules\Reminders\Http\Controllers;

use Modules\Reminders\Models\Remineder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RemindersController extends Controller
{
    /**
     * Provide class.
     */
    private $provider = Remineder::class;

    /**
     * Web RoutePath for the name of the routes.
     */
    private $webroute_path = 'reminders.reminders.';

    /**
     * View path.
     */
    private $view_path = 'reminders::reminders.';

    /**
     * Parameter name.
     */
    private $parameter_name = 'reminder';

    /**
     * Title of this crud.
     */
    private $title = 'reminder';

    /**
     * Title of this crud in plural.
     */
    private $titlePlural = 'reminders';

    private function getFields($class='col-md-4')
    {
        $fields=[];
        
        //Add name field
        $fields[0]=['class'=>$class, 'ftype'=>'input', 'name'=>'Name', 'id'=>'name', 'placeholder'=>'Enter name', 'required'=>true];

        //Return fields
        return $fields;
    }


    private function getFilterFields(){
        $fields=$this->getFields('col-md-3');
        $fields[0]['required']=true;
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
     * Display a listing of the rereminder.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authChecker();

        $items=$this->provider::orderBy('id', 'desc');
        if(isset($_GET['name'])&&strlen($_GET['name'])>1){
            $items=$items->where('name',  'like', '%'.$_GET['name'].'%');
        }
        $items=$items->paginate(config('settings.paginate'));

        return view($this->view_path.'index', ['setup' => [
            'usefilter'=>true,
            'title'=>__('crud.item_managment', ['item'=>__($this->titlePlural)]),
            'action_link'=>route('campaigns.create').'?type=reminder',
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
                [__('crud.item_managment', ['item'=>__($this->titlePlural)]), '#'],
            ],
        ]]);
    }

    /**
     * Show the form for creating a new rereminder.
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
                [__('Reminders'), route('reminders.reminders.index')]
            ],
        ],
        'fields'=>$this->getFields() ]);
    }

    /**
     * Store a newly created rereminder in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authChecker();
        
        //Create new contact
        $contact = $this->provider::create([
            'name' => $request->name,
        ]);
        $contact->save();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_added', ['item'=>__($this->title)]));
    }

    

    /**
     * Show the form for editing the specified rereminder.
     *
     * @param  \App\Contact  $reminders
     * @return \Illuminate\Http\Response
     */
    public function edit(reminder $reminder)
    {
        $this->authChecker();

        $fields = $this->getFields();
        $fields[0]['value'] = $reminder->name;

        $parameter = [];
        $parameter[$this->parameter_name] = $reminder->id;

        return view($this->view_path.'edit', ['setup' => [
            'title'=>__('crud.edit_item_name', ['item'=>__($this->title), 'name'=>$reminder->name]),
            'action_link'=>route($this->webroute_path.'index'),
            'action_name'=>__('crud.back'),
            'iscontent'=>true,
            'isupdate'=>true,
            'action'=>route($this->webroute_path.'update', $parameter),
        ],
        'fields'=>$fields, ]);
    }

    /**
     * Update the specified rereminder in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Contact  $reminders
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);
        $item->name = $request->name;
        $item->update();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_updated', ['item'=>__($this->title)]));
    }

    /**
     * Remove the specified rereminder from storage.
     *
     * @param  \App\Contact  $reminders
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);
        $item->delete();
        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_removed', ['item'=>__($this->title)]));
    }
    
}
