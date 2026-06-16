<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Services\EventRegistrationService;
use Modules\Wpbox\Events\Chatlistchange;
use Modules\Wpbox\Traits\Contacts;

class EventRegistrationsController extends Controller
{
    use Contacts;

    private string $webroute_path = 'reminders.event-registrations.';

    private string $view_path = 'reminders::event-registrations.';

    public function __construct(
        private readonly EventRegistrationService $registrationService
    ) {
    }

    public function index(Request $request)
    {
        $this->ownerAndStaffOnly();

        $items = EventRegistration::query()
            ->with(['event', 'occurrence', 'contact'])
            ->orderByDesc('registered_at');

        if ($request->filled('event_id')) {
            $items->where('event_id', $request->event_id);
        }

        $items = $items->paginate(config('settings.paginate'));

        return view($this->view_path.'index', [
            'setup' => [
                'title' => __('Event registrations'),
                'subtitle' => __('Attendees registered for published events.'),
                'action_link' => route('reminders.events.index'),
                'action_name' => __('Manage events'),
                'items' => $items,
                'item_names' => __('registrations'),
                'webroute_path' => $this->webroute_path,
                'parameter_name' => 'eventRegistration',
                'custom_table' => true,
            ],
        ]);
    }

    public function show(EventRegistration $eventRegistration)
    {
        $this->ownerAndStaffOnly();

        $eventRegistration->load(['event', 'occurrence', 'contact']);

        return view($this->view_path.'show', [
            'setup' => [
                'title' => __('Registration').' #'.$eventRegistration->id,
                'subtitle' => $eventRegistration->event?->title,
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => __('Back to list'),
                'iscontent' => true,
            ],
            'registration' => $eventRegistration,
            'reminderMessages' => $eventRegistration->reminderMessages(),
        ]);
    }

    public function openChat(EventRegistration $eventRegistration)
    {
        $this->ownerAndStaffOnly();

        $companyId = $this->activeCompanyId();
        if (! $companyId || (int) $eventRegistration->company_id !== (int) $companyId) {
            abort(404);
        }

        $contact = $this->findBookingContact($eventRegistration->contact_id, $companyId);

        $this->promoteContactToInbox($contact);

        event(new Chatlistchange($contact->id, $contact->company_id));

        return $this->redirectToContactChat($contact);
    }

    public function cancel(EventRegistration $eventRegistration)
    {
        $this->ownerAndStaffOnly();

        $this->registrationService->cancel($eventRegistration);

        return redirect()->route($this->webroute_path.'show', ['eventRegistration' => $eventRegistration->id])
            ->withStatus(__('Registration cancelled.'));
    }
}
