<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Reminders\Models\Department;

class DepartmentsController extends Controller
{
    private string $webroute_path = 'reminders.departments.';

    private string $view_path = 'reminders::departments.';

    private function fields(?Department $department = null): array
    {
        return [
            ['class' => 'col-md-6', 'ftype' => 'input', 'name' => 'Name', 'id' => 'name', 'placeholder' => 'Reception', 'required' => true, 'value' => $department?->name],
            ['class' => 'col-md-6', 'ftype' => 'textarea', 'name' => 'Description', 'id' => 'description', 'required' => false, 'value' => $department?->description],
            ['class' => 'col-md-6', 'ftype' => 'bool', 'name' => 'Active', 'id' => 'is_active', 'required' => false, 'value' => $department?->is_active ?? true],
        ];
    }

    public function index()
    {
        $this->ownerAndStaffOnly();

        $items = Department::query()->orderBy('name')->paginate(config('settings.paginate'));

        return view($this->view_path.'index', ['setup' => [
            'title' => __('Departments'),
            'action_link' => route($this->webroute_path.'create'),
            'action_name' => __('Add department'),
            'items' => $items,
            'item_names' => __('departments'),
            'webroute_path' => $this->webroute_path,
            'parameter_name' => 'department',
            'custom_table' => true,
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
        ]);
    }

    public function update(Request $request, Department $department)
    {
        $this->ownerAndStaffOnly();

        $department->update($this->attributes($request));

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Department updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    public function destroy(Department $department)
    {
        $this->ownerAndStaffOnly();

        $department->delete();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Department removed.'));
    }
}
