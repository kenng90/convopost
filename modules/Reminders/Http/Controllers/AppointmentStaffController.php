<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Department;

class AppointmentStaffController extends Controller
{
    private string $webroute_path = 'reminders.appointment-staff.';

    private string $view_path = 'reminders::appointment-staff.';

    private function fields(?AppointmentStaff $member = null): array
    {
        return [
            ['class' => 'col-md-6', 'ftype' => 'input', 'name' => 'Name', 'id' => 'name', 'placeholder' => 'Jane Doe', 'required' => true, 'value' => $member?->name],
            ['class' => 'col-md-6', 'ftype' => 'input', 'name' => 'Email', 'id' => 'email', 'placeholder' => 'jane@example.com', 'required' => true, 'value' => $member?->email],
            ['class' => 'col-md-6', 'ftype' => 'input', 'name' => 'WhatsApp phone', 'id' => 'whatsapp_phone', 'placeholder' => '+254712345678', 'required' => false, 'value' => $member?->whatsapp_phone, 'additionalInfo' => 'E.164 format, e.g. +254712345678'],
            [
                'class' => 'col-md-6',
                'ftype' => 'select',
                'name' => 'Department',
                'id' => 'department_id',
                'required' => false,
                'value' => $member?->department_id,
                'data' => Department::query()->orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            [
                'class' => 'col-md-6',
                'ftype' => 'select',
                'name' => 'Linked platform user (optional)',
                'id' => 'user_id',
                'required' => false,
                'value' => $member?->user_id,
                'additionalInfo' => __('Optional. Used for Google Calendar sync on appointments and WhatsApp alerts when assigned as an event host.'),
                'data' => $this->linkableUserOptions(),
            ],
            ['class' => 'col-md-6', 'ftype' => 'bool', 'name' => 'Active', 'id' => 'is_active', 'required' => false, 'value' => $member?->is_active ?? true],
        ];
    }

    public function index()
    {
        $this->ownerAndStaffOnly();

        $items = AppointmentStaff::query()
            ->with('department')
            ->orderBy('name')
            ->paginate(config('settings.paginate'));

        return view($this->view_path.'index', [
            'setup' => [
                'title' => __('Team'),
                'subtitle' => __('Shared by appointments and events. Assign members to services or choose a host on event forms.'),
                'action_link' => route($this->webroute_path.'create'),
                'action_name' => __('Add team member'),
                'items' => $items,
                'item_names' => __('team members'),
                'webroute_path' => $this->webroute_path,
                'parameter_name' => 'appointmentStaff',
                'custom_table' => true,
                'getting_started_type' => 'team',
            ],
        ]);
    }

    public function create()
    {
        $this->ownerAndStaffOnly();

        return view('general.form', [
            'setup' => [
                'title' => __('New team member'),
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => __('Back'),
                'iscontent' => true,
                'action' => route($this->webroute_path.'store'),
            ],
            'fields' => $this->fields(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ownerAndStaffOnly();

        AppointmentStaff::create($this->attributes($request));

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Team member added.'));
    }

    public function edit(AppointmentStaff $appointmentStaff)
    {
        $this->ownerAndStaffOnly();

        return view($this->view_path.'edit', [
            'setup' => [
                'title' => __('Edit team member'),
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => __('Back'),
                'iscontent' => true,
                'isupdate' => true,
                'action' => route($this->webroute_path.'update', ['appointmentStaff' => $appointmentStaff->id]),
            ],
            'fields' => $this->fields($appointmentStaff),
            'member' => $appointmentStaff,
            'calendarConnected' => $appointmentStaff->hasConnectedCalendar(),
        ]);
    }

    public function update(Request $request, AppointmentStaff $appointmentStaff)
    {
        $this->ownerAndStaffOnly();

        $appointmentStaff->update($this->attributes($request));

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Team member updated.'));
    }

    public function destroy(AppointmentStaff $appointmentStaff)
    {
        $this->ownerAndStaffOnly();

        $appointmentStaff->delete();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Team member removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'whatsapp_phone' => $request->input('whatsapp_phone'),
            'department_id' => $request->input('department_id') ?: null,
            'user_id' => $request->input('user_id') ?: null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function linkableUserOptions(): array
    {
        $company = $this->getCompany();
        if (! $company) {
            return [];
        }

        return User::query()
            ->where(function ($query) use ($company) {
                $query->where('company_id', $company->id)
                    ->orWhere('id', $company->user_id);
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
