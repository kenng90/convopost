<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Department;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\SourceArchiveService;
use Modules\Reminders\Services\SourceReminderSyncService;
use Modules\Reminders\Support\BookingPaymentConfig;
use Modules\Reminders\Support\WorkingHours;
use Modules\Wpbox\Models\Campaign;

class SourcesController extends Controller
{
    private $provider = Source::class;

    private $webroute_path = 'reminders.sources.';

    private $view_path = 'reminders::sources.';

    private $parameter_name = 'source';

    private $title = 'source';

    private $titlePlural = 'sources';

    public function __construct(
        private readonly SourceReminderSyncService $sourceReminderSync,
        private readonly SourceArchiveService $sourceArchive,
    ) {
    }

    private function getFields($class = 'col-md-4', ?Source $source = null)
    {
        $fields = [];

        $fields[] = [
            'class' => 'col-md-12',
            'ftype' => 'info',
            'id' => 'service_details_intro',
            'name' => __('Service details'),
            'text' => __('Name and booking settings for this bookable service.'),
        ];

        $fields[] = ['class' => $class, 'ftype' => 'input', 'name' => __('Service name'), 'id' => 'name', 'placeholder' => 'Consultation', 'required' => true, 'value' => $source?->name];
        $fields[] = ['class' => $class, 'ftype' => 'bool', 'name' => __('Bookable online'), 'id' => 'is_bookable', 'required' => false, 'value' => $source?->is_bookable ?? true];
        $fields[] = ['class' => $class, 'ftype' => 'bool', 'name' => __('Require payment'), 'id' => 'payment_required', 'required' => false, 'value' => $source?->payment_required ?? false, 'additionalInfo' => __('When enabled, customers must pay via M-Pesa before the appointment is confirmed.')];
        $fields[] = ['class' => $class, 'ftype' => 'input', 'type' => 'number', 'name' => __('Total amount'), 'id' => 'payment_amount', 'placeholder' => '1500', 'required' => false, 'value' => $source?->payment_amount, 'additionalInfo' => __('Full service price in the selected currency.')];
        $fields[] = ['class' => $class, 'ftype' => 'input', 'type' => 'number', 'name' => __('Upfront payment (%)'), 'id' => 'payment_upfront_percent', 'placeholder' => '100', 'required' => false, 'value' => $source?->payment_upfront_percent ?? 100, 'additionalInfo' => __('Percentage of the total collected via M-Pesa to confirm the booking. Use 100 for full payment.')];
        $fields[] = ['class' => $class, 'ftype' => 'input', 'name' => __('Payment currency'), 'id' => 'payment_currency', 'placeholder' => 'KES', 'required' => false, 'value' => $source?->payment_currency ?? 'KES'];

        $fields[] = [
            'class' => $class,
            'ftype' => 'select',
            'name' => __('Department'),
            'id' => 'department_id',
            'required' => false,
            'value' => $source?->department_id,
            'data' => ['' => __('All departments')] + Department::query()->orderBy('name')->pluck('name', 'id')->toArray(),
            'additionalInfo' => __('Optional. Limits which team members can be assigned to this service.'),
        ];

        $fields[] = [
            'class' => 'col-md-12',
            'ftype' => 'info',
            'id' => 'booking_rules_intro',
            'name' => __('Booking rules'),
            'text' => __('Duration, availability window, and team assignment for this service.'),
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'input',
            'type' => 'number',
            'name' => __('Default duration (minutes)'),
            'id' => 'default_duration_minutes',
            'placeholder' => '30',
            'required' => true,
            'value' => $source?->default_duration_minutes ?? 30,
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'input',
            'name' => __('Duration options (comma-separated minutes)'),
            'id' => 'duration_options',
            'placeholder' => '30,60,90',
            'required' => false,
            'value' => $source ? implode(',', $source->durationOptions()) : '30,60',
            'additionalInfo' => __('Choices shown to clients when booking.'),
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'input',
            'type' => 'number',
            'name' => __('Buffer between slots (minutes)'),
            'id' => 'buffer_minutes',
            'placeholder' => '0',
            'required' => true,
            'value' => $source?->buffer_minutes ?? 0,
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'input',
            'name' => __('Timezone'),
            'id' => 'timezone',
            'placeholder' => 'UTC',
            'required' => true,
            'value' => $source?->timezone ?? config('app.timezone', 'UTC'),
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'input',
            'type' => 'number',
            'name' => __('Minimum notice (hours)'),
            'id' => 'min_notice_hours',
            'placeholder' => '1',
            'required' => true,
            'value' => $source?->min_notice_hours ?? 1,
            'additionalInfo' => __('How soon before start time clients may book.'),
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'input',
            'type' => 'number',
            'name' => __('Maximum advance booking (days)'),
            'id' => 'max_advance_days',
            'placeholder' => '60',
            'required' => true,
            'value' => $source?->max_advance_days ?? 60,
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'select',
            'name' => __('Team member assignment'),
            'id' => 'staff_assignment_mode',
            'required' => true,
            'value' => $source?->staff_assignment_mode ?? Source::ASSIGNMENT_CUSTOMER_CHOICE,
            'data' => Source::staffAssignmentModeOptions(),
            'additionalInfo' => __('How a team member is chosen when a client books. Round-robin and least-busy hide staff names from clients.'),
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'select',
            'name' => __('Assigned team'),
            'id' => 'appointment_staff_ids[]',
            'placeholder' => __('Select team members'),
            'required' => false,
            'multiple' => true,
            'multipleselected' => $source
                ? $source->staffAssignments()->where('is_active', true)->pluck('appointment_staff_id')->map(fn ($id) => (string) $id)->all()
                : [],
            'data' => $this->appointmentStaffOptions($source?->department_id),
            'additionalInfo' => __('Who can receive bookings and calendar notifications for this service.'),
        ];

        $campaignOptions = ['' => __('— None —')] + $this->reminderCampaignOptions($source);

        $fields[] = [
            'class' => $class,
            'ftype' => 'input',
            'separator' => __('Location'),
            'name' => __('Location'),
            'id' => 'location',
            'placeholder' => __('Main clinic, Room 2'),
            'required' => false,
            'value' => $source?->location,
            'additionalInfo' => __('Shown in confirmation and reminder templates when you map the Location variable.'),
        ];

        $fields[] = [
            'class' => 'col-md-12',
            'ftype' => 'info',
            'id' => 'client_notifications_intro',
            'name' => __('Client notifications'),
            'text' => __('Use reminder-type WhatsApp templates. Map variables to Date, Time, and Location (and optional service/staff fields). Confirmation sends immediately on booking; before/after reminders are scheduled automatically.'),
            'button' => [
                'link' => route('reminders.reminders.index'),
                'text' => __('View synced rules'),
            ],
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'select',
            'separator' => __('Confirmation'),
            'name' => __('Confirmation template'),
            'id' => 'confirmation_campaign_id',
            'required' => false,
            'value' => $source?->confirmation_campaign_id,
            'data' => $campaignOptions,
            'additionalInfo' => __('Sent immediately when a booking is created. Map template variables to Date, Time, Location.'),
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'select',
            'separator' => __('Before appointment'),
            'name' => __('WhatsApp template'),
            'id' => 'reminder_before_campaign_id',
            'required' => false,
            'value' => $source?->reminder_before_campaign_id,
            'data' => $campaignOptions,
            'additionalInfo' => __('Reminder-type WhatsApp template from Campaigns.'),
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'input',
            'type' => 'number',
            'name' => __('Send how long before start?'),
            'id' => 'reminder_before_value',
            'placeholder' => '24',
            'required' => false,
            'value' => $source?->reminder_before_value,
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'select',
            'name' => __('Time unit'),
            'id' => 'reminder_before_unit',
            'required' => false,
            'value' => $source?->reminder_before_unit ?? 'hours',
            'data' => ['minutes' => __('Minutes'), 'hours' => __('Hours'), 'days' => __('Days')],
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'select',
            'separator' => __('After appointment'),
            'name' => __('WhatsApp template'),
            'id' => 'reminder_after_campaign_id',
            'required' => false,
            'value' => $source?->reminder_after_campaign_id,
            'data' => $campaignOptions,
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'input',
            'type' => 'number',
            'name' => __('Send how long after end?'),
            'id' => 'reminder_after_value',
            'placeholder' => '1',
            'required' => false,
            'value' => $source?->reminder_after_value,
        ];

        $fields[] = [
            'class' => $class,
            'ftype' => 'select',
            'name' => __('Time unit'),
            'id' => 'reminder_after_unit',
            'required' => false,
            'value' => $source?->reminder_after_unit ?? 'hours',
            'data' => ['minutes' => __('Minutes'), 'hours' => __('Hours'), 'days' => __('Days')],
        ];

        return $fields;
    }

    private function getFilterFields()
    {
        return [[
            'class' => 'col-md-3',
            'ftype' => 'input',
            'name' => __('Service name'),
            'id' => 'name',
            'placeholder' => __('Consultation'),
            'required' => true,
        ]];
    }

    private function authChecker()
    {
        $this->ownerAndStaffOnly();
    }

    public function index()
    {
        $this->authChecker();

        $items = $this->provider::orderBy('sort_order')->orderBy('id', 'desc');
        if (isset($_GET['name']) && strlen($_GET['name']) > 1) {
            $items = $items->where('name', 'like', '%'.$_GET['name'].'%');
        }
        $items = $items->paginate(config('settings.paginate'));

        return view($this->view_path.'index', ['setup' => [
            'usefilter' => true,
            'title' => __('Services'),
            'subtitle' => __('Bookable appointment types for your public booking page.'),
            'action_link' => route($this->webroute_path.'create'),
            'action_name' => __('Add service'),
            'items' => $items,
            'item_names' => $this->titlePlural,
            'webroute_path' => $this->webroute_path,
            'fields' => $this->getFields(),
            'filterFields' => $this->getFilterFields(),
            'custom_table' => true,
            'parameter_name' => $this->parameter_name,
            'parameters' => count($_GET) != 0,
            'getting_started_type' => 'services',
            'breadcrumbs' => [
                [__('Services'), '#'],
            ],
        ]]);
    }

    public function create()
    {
        $this->authChecker();

        return view('general.form', ['setup' => [
            'title' => __('New service'),
            'action_link' => route($this->webroute_path.'index'),
            'action_name' => __('Back'),
            'iscontent' => true,
            'inrow' => true,
            'action' => route($this->webroute_path.'store'),
            'breadcrumbs' => [
                [__('Services'), route('reminders.sources.index')],
            ],
        ],
            'fields' => $this->getFields(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authChecker();

        $source = $this->provider::create($this->sourceAttributes($request));
        $this->syncStaff($source, $request->input('appointment_staff_ids', []));
        $this->sourceReminderSync->sync($source->fresh());

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Service created.'));
    }

    public function edit(Source $source)
    {
        $this->authChecker();

        $fields = $this->getFields('col-md-4', $source);

        $parameter = [];
        $parameter[$this->parameter_name] = $source->id;

        return view($this->view_path.'edit', ['setup' => [
            'title' => __('Edit service').': '.$source->name,
            'action_link' => route($this->webroute_path.'index'),
            'action_name' => __('Back'),
            'iscontent' => true,
            'inrow' => true,
            'isupdate' => true,
            'action' => route($this->webroute_path.'update', $parameter),
        ],
            'fields' => $fields,
            'source' => $source,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);
        $item->update($this->sourceAttributes($request, $item));
        $this->syncStaff($item, $request->input('appointment_staff_ids', []));
        $this->sourceReminderSync->sync($item->fresh());

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Service updated.'));
    }

    public function destroy($id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);

        $hasReservations = $item->reservations()->exists();
        $this->sourceArchive->archive($item);

        $message = $hasReservations
            ? __('Service archived. Existing appointments are kept for your records.')
            : __('Service removed.');

        return redirect()->route($this->webroute_path.'index')->withStatus($message);
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceAttributes(Request $request, ?Source $existing = null): array
    {
        $durationOptions = collect(explode(',', (string) $request->input('duration_options', '')))
            ->map(fn ($value) => (int) trim($value))
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->all();

        $mode = $request->input('staff_assignment_mode', Source::ASSIGNMENT_CUSTOMER_CHOICE);
        if (! array_key_exists($mode, Source::staffAssignmentModeOptions())) {
            $mode = Source::ASSIGNMENT_CUSTOMER_CHOICE;
        }

        return [
            'name' => $request->name,
            'is_bookable' => $request->boolean('is_bookable'),
            'payment_required' => $request->boolean('payment_required'),
            'payment_amount' => $request->filled('payment_amount') ? $request->input('payment_amount') : null,
            'payment_upfront_percent' => $request->boolean('payment_required')
                ? BookingPaymentConfig::normalizeUpfrontPercent($request->input('payment_upfront_percent'))
                : null,
            'payment_currency' => strtoupper((string) $request->input('payment_currency', 'KES')),
            'department_id' => $request->input('department_id') ?: null,
            'default_duration_minutes' => (int) $request->input('default_duration_minutes', 30),
            'duration_options' => $durationOptions ?: [(int) $request->input('default_duration_minutes', 30)],
            'buffer_minutes' => (int) $request->input('buffer_minutes', 0),
            'timezone' => $request->input('timezone', config('app.timezone', 'UTC')),
            'min_notice_hours' => (int) $request->input('min_notice_hours', 1),
            'max_advance_days' => (int) $request->input('max_advance_days', 60),
            'staff_assignment_mode' => $mode,
            'working_hours' => $existing?->working_hours ?: WorkingHours::default(),
            'location' => $request->input('location') ?: null,
            'confirmation_campaign_id' => $request->input('confirmation_campaign_id') ?: null,
            'reminder_before_campaign_id' => $request->input('reminder_before_campaign_id') ?: null,
            'reminder_after_campaign_id' => $request->input('reminder_after_campaign_id') ?: null,
            'reminder_before_value' => $request->input('reminder_before_value') ?: null,
            'reminder_before_unit' => $request->input('reminder_before_unit') ?: null,
            'reminder_after_value' => $request->input('reminder_after_value') ?: null,
            'reminder_after_unit' => $request->input('reminder_after_unit') ?: null,
        ];
    }

    /**
     * @param  array<int|string>|null  $staffIds
     */
    private function syncStaff(Source $source, ?array $staffIds): void
    {
        $staffIds = collect($staffIds ?: [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $existing = SourceStaff::query()
            ->where('source_id', $source->id)
            ->get()
            ->keyBy('appointment_staff_id');

        SourceStaff::query()
            ->where('source_id', $source->id)
            ->whereNotIn('appointment_staff_id', $staffIds->all())
            ->delete();

        foreach ($staffIds as $staffId) {
            if ($existing->has($staffId)) {
                $existing[$staffId]->update(['is_active' => true]);

                continue;
            }

            SourceStaff::create([
                'source_id' => $source->id,
                'appointment_staff_id' => $staffId,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function appointmentStaffOptions(?int $departmentId = null): array
    {
        $query = AppointmentStaff::query()->where('is_active', true)->orderBy('name');

        if ($departmentId) {
            $query->where(function ($builder) use ($departmentId) {
                $builder->where('department_id', $departmentId)
                    ->orWhereHas('departments', fn ($relation) => $relation->where('rem_departments.id', $departmentId));
            });
        }

        return $query->pluck('name', 'id')->toArray();
    }

    /**
     * @return array<int, string>
     */
    private function reminderCampaignOptions(?Source $source = null): array
    {
        $company = $this->getCompany();
        if (! $company) {
            return [];
        }

        $selectedCampaignIds = array_filter([
            $source?->confirmation_campaign_id,
            $source?->reminder_before_campaign_id,
            $source?->reminder_after_campaign_id,
        ]);

        return Campaign::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($selectedCampaignIds) {
                $query->where('is_reminder', true);
                if ($selectedCampaignIds !== []) {
                    $query->orWhereIn('id', $selectedCampaignIds);
                }
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
