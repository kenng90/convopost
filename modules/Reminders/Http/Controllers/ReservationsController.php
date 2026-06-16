<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Wpbox\Events\Chatlistchange;
use Modules\Wpbox\Http\Controllers\APIController;
use Modules\Wpbox\Models\Contact as WpboxContact;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Traits\Contacts;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    private function getFields($class = 'col-md-4')
    {
        $fields = [];

        //Add contact
        $fields[] = [
            'class' => $class,
            'ftype' => 'select',
            'name' => 'Contact',
            'id' => 'contact_id',
            'placeholder' => 'Select contact',
            'required' => true,
            'label' => __('Contact'),
            'type' => 'select',
            'additionalInfo' => "<a class='mt-2' href='".route('contacts.create')."'>".__('Add new contact').'</a>',
            'data' => WpboxContact::query()->get()->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'text' => $contact->name.' '.$contact->phone,
                ];
            })->pluck('text', 'id')->toArray(),
        ];

        //Add source
        $fields[] = [
            'class' => $class,
            'ftype' => 'select',
            'name' => 'Source',
            'id' => 'source_id',
            'placeholder' => 'Select source',
            'required' => true,
            'label' => __('Source'),
            'additionalInfo' => "<a class='mt-2' href='".route('reminders.sources.create')."'>".__('Add new source').'</a>',
            'data' => Source::pluck('name', 'id')->toArray(),
        ];

        //Add date and time input
        $fields[] = [
            'class' => $class,
            'ftype' => 'input',
            'type' => 'datetime-local',
            'name' => 'Date and time - start',
            'id' => 'start_date',
            'placeholder' => 'Select date and time',
            'required' => true,
            'label' => __('Date and time'),
            'step' => '60',
        ];

        //Add date and time input
        $fields[] = [
            'class' => $class,
            'ftype' => 'input',
            'type' => 'datetime-local',
            'name' => 'Date and time - end',
            'id' => 'end_date',
            'placeholder' => 'Select date and time',
            'required' => true,
            'label' => __('Date and time'),
            'step' => '60',
        ];

        //Add external reference input
        $fields[] = [
            'class' => $class,
            'ftype' => 'input',
            'name' => 'External reference',
            'id' => 'external_id',
            'placeholder' => 'External reference',
            'required' => false,
            'label' => __('External reference'),
        ];

        //Return fields
        return $fields;
    }

    private function getFilterFields(): array
    {
        return [
            [
                'class' => 'col-md-3',
                'ftype' => 'select',
                'name' => __('Status'),
                'id' => 'display_status',
                'placeholder' => __('All statuses'),
                'required' => false,
                'data' => [
                    '' => __('All statuses'),
                    'upcoming' => __('Upcoming'),
                    'in_progress' => __('In progress'),
                    'completed' => __('Completed'),
                    'cancelled' => __('Cancelled'),
                    'confirmed' => __('Confirmed'),
                ],
            ],
            [
                'class' => 'col-md-3',
                'ftype' => 'select',
                'name' => __('Service'),
                'id' => 'source_id',
                'placeholder' => __('All services'),
                'required' => false,
                'data' => ['' => __('All services')] + Source::query()->orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            [
                'class' => 'col-md-3',
                'ftype' => 'select',
                'name' => __('Team member'),
                'id' => 'appointment_staff_id',
                'placeholder' => __('All team members'),
                'required' => false,
                'data' => ['' => __('All team members')] + AppointmentStaff::query()->orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            [
                'class' => 'col-md-3',
                'ftype' => 'input',
                'type' => 'date',
                'name' => __('Start date from'),
                'id' => 'start_date',
                'placeholder' => __('Start date'),
                'required' => false,
            ],
            [
                'class' => 'col-md-3',
                'ftype' => 'input',
                'type' => 'date',
                'name' => __('End date to'),
                'id' => 'end_date',
                'placeholder' => __('End date'),
                'required' => false,
            ],
            [
                'class' => 'col-md-3',
                'ftype' => 'input',
                'name' => __('Reference'),
                'id' => 'external_id',
                'placeholder' => __('Reference'),
                'required' => false,
            ],
        ];
    }

    private function filteredQuery(Request $request): Builder
    {
        $items = $this->provider::query()
            ->with(['contact', 'source', 'appointmentStaffMember'])
            ->orderByDesc('start_date');

        if ($request->filled('external_id') && strlen((string) $request->external_id) > 1) {
            $items->where('external_id', 'like', '%'.$request->external_id.'%');
        }

        if ($request->filled('source_id')) {
            $items->where('source_id', $request->source_id);
        }

        if ($request->filled('appointment_staff_id')) {
            $items->where('appointment_staff_id', $request->appointment_staff_id);
        }

        if ($request->filled('start_date')) {
            $items->whereDate('start_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $items->whereDate('start_date', '<=', $request->end_date);
        }

        if ($request->filled('display_status')) {
            $items->filterByDisplayStatus($request->display_status);
        }

        return $items;
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
    public function index(Request $request)
    {
        $this->authChecker();

        $items = $this->filteredQuery($request)->paginate(config('settings.paginate'));

        $exportQuery = array_filter($request->only([
            'display_status',
            'source_id',
            'appointment_staff_id',
            'start_date',
            'end_date',
            'external_id',
        ]));

        return view($this->view_path.'index', ['setup' => [
            'usefilter' => true,
            'title' => __('Appointments'),
            'subtitle' => __('One-to-one bookings via web, WhatsApp, API, or manually.'),
            'action_link' => route($this->webroute_path.'create'),
            'action_name' => __('crud.add_new_item', ['item' => __($this->title)]),
            'action_link2' => route($this->webroute_path.'export', $exportQuery),
            'action_name2' => __('Export CSV'),
            'items' => $items,
            'item_names' => $this->titlePlural,
            'webroute_path' => $this->webroute_path,
            'fields' => $this->getFields(),
            'filterFields' => $this->getFilterFields(),
            'custom_table' => true,
            'parameter_name' => $this->parameter_name,
            'parameters' => count($request->query()) !== 0,
            'breadcrumbs' => [
                [__('Appointments'), '#'],
            ],
        ]]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authChecker();

        $reservations = $this->filteredQuery($request)->get();
        $filename = 'appointments-'.now()->format('Y-m-d-His').'.csv';

        return response()->stream(function () use ($reservations) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Status',
                'Client',
                'Phone',
                'Service',
                'Team member',
                'Start',
                'End',
                'Duration (minutes)',
                'Reference',
                'Booked at',
            ]);

            foreach ($reservations as $reservation) {
                fputcsv($file, [
                    $reservation->displayStatusLabel(),
                    $reservation->contact?->name,
                    $reservation->contact?->phone,
                    $reservation->source?->name,
                    $reservation->appointmentStaffMember?->name,
                    $reservation->start_date?->format('Y-m-d H:i'),
                    $reservation->end_date?->format('Y-m-d H:i'),
                    $reservation->duration_minutes,
                    $reservation->external_id,
                    $reservation->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function show(Reservation $reservation)
    {
        $this->authChecker();

        $reservation->load([
            'contact',
            'source.department',
            'appointmentStaffMember.department',
            'staff',
        ]);

        return view($this->view_path.'show', [
            'setup' => [
                'title' => __('Reservation').' #'.($reservation->external_id ?: $reservation->id),
                'subtitle' => $reservation->source?->name,
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => __('Back to list'),
                'action_link2' => route($this->webroute_path.'edit', ['reservation' => $reservation->id]),
                'action_name2' => __('Edit'),
                'iscontent' => true,
                'breadcrumbs' => [
                    [__('Appointments'), route('reminders.reservations.index')],
                    ['#'.($reservation->external_id ?: $reservation->id), '#'],
                ],
            ],
            'reservation' => $reservation,
            'reminderMessages' => $reservation->reminderMessages(),
        ]);
    }

    public function openChat(Reservation $reservation)
    {
        $this->authChecker();

        $companyId = $this->activeCompanyId();
        if (! $companyId || (int) $reservation->company_id !== (int) $companyId) {
            abort(404);
        }

        $contact = $this->findBookingContact($reservation->contact_id, $companyId);

        $this->promoteContactToInbox($contact);

        event(new Chatlistchange($contact->id, $contact->company_id));

        return $this->redirectToContactChat($contact);
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
            'title' => __('crud.new_item', ['item' => __($this->title)]),
            'action_link' => route($this->webroute_path.'index'),
            'action_name' => __('crud.back'),
            'iscontent' => true,
            'action' => route($this->webroute_path.'store'),
            'breadcrumbs' => [
                [__('Appointments'), route('reminders.reservations.index')],
            ],
        ],
            'fields' => $this->getFields()]);
    }

    //Make reservation
    public function makeReservation(Request $request)
    {
        return $this->authenticate($request, function ($request) {

            //Create reservation
            $reservation = \Modules\Reminders\Models\Reservation::create([
                'contact_id' => $request->contact_id,
                'source_id' => $request->source_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'external_id' => $request->external_id,
            ]);

        },
        );
    }

    //Create API function for creating a new reservation
    public function apiStore(Request $request)
    {

        APIController::authenticateStatic($request, function ($request) {
            //Company
            $company = $this->getCompany();

            //Get or create contact
            $contact = $this->getOrMakeBookingContact($request->phone, $company, $request->name);

            //Get or create source
            //Find source by name
            $source = Source::where('name', $request->source)->first();
            if (! $source) {
                $source = Source::create(['name' => $request->source, 'company_id' => $company->id]);
            }

            //Create reservation
            $reservation = Reservation::create([
                'contact_id' => $contact->id,
                'source_id' => $source->id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => 1,
                'external_id' => $request->external_id,
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
                'end_date' => 'required',
            ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authChecker();

        //Create new reminder
        $reservation = $this->provider::create([
            'contact_id' => $request->contact_id,
            'source_id' => $request->source_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'external_id' => $request->external_id,
        ]);
        $reservation->save();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_added', ['item' => __($this->title)]));
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
            'title' => __('crud.edit_item_name', ['item' => __($this->title), 'name' => $reservation->name]),
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

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_updated', ['item' => __($this->title)]));
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

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_removed', ['item' => __($this->title)]));
    }
}
