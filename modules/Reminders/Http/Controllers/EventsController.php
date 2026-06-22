<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Department;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventOccurrence;
use Modules\Reminders\Services\EventReminderSyncService;
use Modules\Wpbox\Models\Campaign;

class EventsController extends Controller
{
    private string $webroute_path = 'reminders.events.';

    private string $view_path = 'reminders::events.';

    public function __construct(
        private readonly EventReminderSyncService $eventReminderSync
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fields(?Event $event = null, string $class = 'col-md-4'): array
    {
        $campaigns = Campaign::query()->orderBy('name')->pluck('name', 'id')->toArray();

        return [
            ['class' => 'col-md-12', 'ftype' => 'info', 'id' => 'event_intro', 'name' => __('Event details'), 'text' => __('Fixed-date events with capacity-based registration.')],
            ['class' => $class, 'ftype' => 'input', 'name' => __('Title'), 'id' => 'title', 'placeholder' => 'Product launch webinar', 'required' => true, 'value' => $event?->title],
            ['class' => $class, 'ftype' => 'textarea', 'name' => __('Description'), 'id' => 'description', 'required' => false, 'value' => $event?->description],
            ['class' => $class, 'ftype' => 'input', 'name' => __('Location'), 'id' => 'location', 'placeholder' => 'Main hall', 'required' => false, 'value' => $event?->location],
            ['class' => $class, 'ftype' => 'input', 'name' => __('Virtual URL'), 'id' => 'virtual_url', 'placeholder' => 'https://zoom.us/...', 'required' => false, 'value' => $event?->virtual_url],
            ['class' => $class, 'ftype' => 'input', 'name' => __('Timezone'), 'id' => 'timezone', 'placeholder' => 'UTC', 'required' => true, 'value' => $event?->timezone ?? config('app.timezone', 'UTC')],
            ['class' => $class, 'ftype' => 'bool', 'name' => __('Published'), 'id' => 'is_published', 'required' => false, 'value' => $event?->is_published ?? false],
            ['class' => $class, 'ftype' => 'select', 'name' => __('Department'), 'id' => 'department_id', 'required' => false, 'value' => $event?->department_id, 'data' => ['' => __('None')] + Department::query()->orderBy('name')->pluck('name', 'id')->toArray(), 'additionalInfo' => __('Optional label for your records. Does not filter the public events page or affect registration.')],
            ['class' => $class, 'ftype' => 'select', 'name' => __('Host'), 'id' => 'appointment_staff_id', 'required' => false, 'value' => $event?->appointment_staff_id, 'data' => ['' => __('None')] + AppointmentStaff::query()->orderBy('name')->pluck('name', 'id')->toArray(), 'additionalInfo' => __('Optional. Choose someone from Bookings → Team to receive WhatsApp alerts when guests register or cancel.')],
            ['class' => 'col-md-12', 'ftype' => 'info', 'id' => 'event_calendar_note', 'name' => __('Google Calendar'), 'text' => __('Events do not sync to Google Calendar. Calendar integration applies to one-to-one appointments only.')],
            ['class' => 'col-md-12', 'ftype' => 'info', 'id' => 'event_notifications_intro', 'name' => __('Client notifications'), 'text' => __('Confirmation and reminder messages for registrants.')],
            ['class' => $class, 'ftype' => 'select', 'name' => __('Confirmation campaign'), 'id' => 'confirmation_campaign_id', 'required' => false, 'value' => $event?->confirmation_campaign_id, 'data' => ['' => __('None')] + $campaigns],
            ['class' => $class, 'ftype' => 'select', 'name' => __('Reminder before (campaign)'), 'id' => 'reminder_before_campaign_id', 'required' => false, 'value' => $event?->reminder_before_campaign_id, 'data' => ['' => __('None')] + $campaigns],
            ['class' => $class, 'ftype' => 'input', 'type' => 'number', 'name' => __('Reminder before (value)'), 'id' => 'reminder_before_value', 'placeholder' => '24', 'required' => false, 'value' => $event?->reminder_before_value],
            ['class' => $class, 'ftype' => 'select', 'name' => __('Reminder before (unit)'), 'id' => 'reminder_before_unit', 'required' => false, 'value' => $event?->reminder_before_unit, 'data' => ['minutes' => 'Minutes', 'hours' => 'Hours', 'days' => 'Days']],
            ['class' => $class, 'ftype' => 'select', 'name' => __('Reminder after (campaign)'), 'id' => 'reminder_after_campaign_id', 'required' => false, 'value' => $event?->reminder_after_campaign_id, 'data' => ['' => __('None')] + $campaigns],
            ['class' => $class, 'ftype' => 'input', 'type' => 'number', 'name' => __('Reminder after (value)'), 'id' => 'reminder_after_value', 'placeholder' => '1', 'required' => false, 'value' => $event?->reminder_after_value],
            ['class' => $class, 'ftype' => 'select', 'name' => __('Reminder after (unit)'), 'id' => 'reminder_after_unit', 'required' => false, 'value' => $event?->reminder_after_unit, 'data' => ['minutes' => 'Minutes', 'hours' => 'Hours', 'days' => 'Days']],
        ];
    }

    public function index()
    {
        $this->ownerAndStaffOnly();

        $items = Event::query()->withCount('occurrences')->orderBy('sort_order')->orderBy('title')->paginate(config('settings.paginate'));

        return view($this->view_path.'index', ['setup' => [
            'title' => __('Events'),
            'subtitle' => __('Fixed-date events with seat capacity. Team and departments are managed under Bookings → Shared setup.'),
            'action_link' => route($this->webroute_path.'create'),
            'action_name' => __('Add event'),
            'items' => $items,
            'item_names' => __('events'),
            'webroute_path' => $this->webroute_path,
            'parameter_name' => 'event',
            'custom_table' => true,
            'getting_started_type' => 'events',
        ]]);
    }

    public function create()
    {
        $this->ownerAndStaffOnly();

        return view('general.form', [
            'setup' => [
                'title' => __('New event'),
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => __('Back'),
                'iscontent' => true,
                'inrow' => true,
                'action' => route($this->webroute_path.'store'),
            ],
            'fields' => $this->fields(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ownerAndStaffOnly();

        $event = Event::create($this->attributes($request));
        $this->eventReminderSync->sync($event->fresh());

        return redirect()->route($this->webroute_path.'edit', ['event' => $event->id])->withStatus(__('Event created. Add occurrences below.'));
    }

    public function edit(Event $event)
    {
        $this->ownerAndStaffOnly();

        $event->load(['occurrences' => fn ($query) => $query->orderBy('starts_at')]);

        return view($this->view_path.'edit', [
            'setup' => [
                'title' => __('Edit event').': '.$event->title,
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => __('Back'),
                'iscontent' => true,
                'inrow' => true,
                'isupdate' => true,
                'action' => route($this->webroute_path.'update', ['event' => $event->id]),
            ],
            'fields' => $this->fields($event),
            'event' => $event,
        ]);
    }

    public function update(Request $request, Event $event)
    {
        $this->ownerAndStaffOnly();

        $event->update($this->attributes($request));
        $this->eventReminderSync->sync($event->fresh());

        return redirect()->route($this->webroute_path.'edit', ['event' => $event->id])->withStatus(__('Event updated.'));
    }

    public function destroy(Event $event)
    {
        $this->ownerAndStaffOnly();

        $event->delete();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Event removed.'));
    }

    public function storeOccurrence(Request $request, Event $event)
    {
        $this->ownerAndStaffOnly();

        $data = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'capacity' => 'required|integer|min:1|max:100000',
            'registration_opens_at' => 'nullable|date',
            'registration_closes_at' => 'nullable|date',
            'status' => 'required|in:draft,published,cancelled,completed',
        ]);

        EventOccurrence::create([
            'company_id' => $event->company_id,
            'event_id' => $event->id,
            ...$data,
        ]);

        return redirect()->route($this->webroute_path.'edit', ['event' => $event->id])->withStatus(__('Occurrence added.'));
    }

    public function destroyOccurrence(Event $event, EventOccurrence $occurrence)
    {
        $this->ownerAndStaffOnly();

        abort_unless((int) $occurrence->event_id === (int) $event->id, 404);

        $occurrence->delete();

        return redirect()->route($this->webroute_path.'edit', ['event' => $event->id])->withStatus(__('Occurrence removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Request $request): array
    {
        return [
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'location' => $request->input('location'),
            'virtual_url' => $request->input('virtual_url'),
            'timezone' => $request->input('timezone', 'UTC'),
            'is_published' => $request->boolean('is_published'),
            'department_id' => $request->input('department_id') ?: null,
            'appointment_staff_id' => $request->input('appointment_staff_id') ?: null,
            'confirmation_campaign_id' => $request->input('confirmation_campaign_id') ?: null,
            'reminder_before_campaign_id' => $request->input('reminder_before_campaign_id') ?: null,
            'reminder_before_value' => $request->input('reminder_before_value') ?: null,
            'reminder_before_unit' => $request->input('reminder_before_unit') ?: null,
            'reminder_after_campaign_id' => $request->input('reminder_after_campaign_id') ?: null,
            'reminder_after_value' => $request->input('reminder_after_value') ?: null,
            'reminder_after_unit' => $request->input('reminder_after_unit') ?: null,
        ];
    }
}
