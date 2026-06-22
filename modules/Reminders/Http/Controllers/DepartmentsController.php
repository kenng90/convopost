<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Reminders\Models\Department;
use Modules\Reminders\Services\BookingClosureService;
use Modules\Reminders\Support\WorkingHours;

class DepartmentsController extends Controller
{
    private string $webroute_path = 'reminders.departments.';

    private string $view_path = 'reminders::departments.';

    public function __construct(
        private readonly BookingClosureService $closureService
    ) {
    }

    private function fields(?Department $department = null): array
    {
        return [
            ['class' => 'col-md-12', 'ftype' => 'info', 'id' => 'department_intro', 'name' => __('About departments'), 'text' => __('Working hours and closure dates apply to appointment services in this department. Events use their own session dates — department on an event is an optional label only.')],
            ['class' => 'col-md-6', 'ftype' => 'input', 'name' => 'Name', 'id' => 'name', 'placeholder' => 'Reception', 'required' => true, 'value' => $department?->name],
            ['class' => 'col-md-6', 'ftype' => 'textarea', 'name' => 'Description', 'id' => 'description', 'required' => false, 'value' => $department?->description],
            ['class' => 'col-md-6', 'ftype' => 'bool', 'name' => 'Active', 'id' => 'is_active', 'required' => false, 'value' => $department?->is_active ?? true],
            [
                'class' => 'col-md-6',
                'ftype' => 'input',
                'name' => __('Timezone'),
                'id' => 'timezone',
                'placeholder' => 'UTC',
                'required' => false,
                'value' => $department?->timezone ?? config('app.timezone', 'UTC'),
                'additionalInfo' => __('Optional. Used for department working hours when no service timezone applies.'),
            ],
            [
                'class' => 'col-md-12',
                'ftype' => 'working_hours',
                'name' => __('Department working hours'),
                'id' => 'working_hours',
                'value' => $department?->working_hours,
                'additionalInfo' => __('Base schedule for services in this department. Team and service hours can override these.'),
            ],
        ];
    }

    public function index()
    {
        $this->ownerAndStaffOnly();

        $items = Department::query()->orderBy('name')->paginate(config('settings.paginate'));

        return view($this->view_path.'index', ['setup' => [
            'title' => __('Departments'),
            'subtitle' => __('Shared by appointments and events. Working hours here affect appointment availability only.'),
            'action_link' => route($this->webroute_path.'create'),
            'action_name' => __('Add department'),
            'items' => $items,
            'item_names' => __('departments'),
            'webroute_path' => $this->webroute_path,
            'parameter_name' => 'department',
            'custom_table' => true,
            'getting_started_type' => 'departments',
        ]]);
    }

    public function create()
    {
        $this->ownerAndStaffOnly();

        return view('general.form', [
            'setup' => [
                'title' => __('New department'),
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

        Department::create($this->attributes($request));

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Department created.'));
    }

    public function edit(Department $department)
    {
        $this->ownerAndStaffOnly();

        $department->load('closures');

        return view($this->view_path.'edit', [
            'setup' => [
                'title' => __('Edit department'),
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => __('Back'),
                'iscontent' => true,
                'isupdate' => true,
                'action' => route($this->webroute_path.'update', ['department' => $department->id]),
            ],
            'fields' => $this->fields($department),
            'closures' => $department->closures()->orderBy('starts_on')->get(),
        ]);
    }

    public function update(Request $request, Department $department)
    {
        $this->ownerAndStaffOnly();

        $department->update($this->attributes($request, $department));
        $this->closureService->syncForDepartment($department, $request->input('closures', []));

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Department updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Request $request, ?Department $existing = null): array
    {
        $attributes = [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active'),
            'timezone' => $request->input('timezone') ?: null,
        ];

        if ($request->has('working_hours')) {
            $attributes['working_hours'] = WorkingHours::fromRequest($request->input('working_hours', []));
        } elseif (! $existing) {
            $attributes['working_hours'] = WorkingHours::default();
        }

        return $attributes;
    }

    public function destroy(Department $department)
    {
        $this->ownerAndStaffOnly();

        $department->delete();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Department removed.'));
    }
}
