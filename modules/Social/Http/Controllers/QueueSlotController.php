<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Social\Http\Requests\StoreSocialQueueSlotRequest;
use Modules\Social\Models\SocialQueueSlot;
use Modules\Social\Services\SocialQueueSlotService;

class QueueSlotController extends Controller
{
    public function __construct(private readonly SocialQueueSlotService $queueSlots)
    {
    }

    public function index(Request $request): View
    {
        $company = $request->user()->currentCompany();

        $slots = SocialQueueSlot::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->orderBy('weekday')
            ->orderBy('time')
            ->get();

        $nextAt = $company ? $this->queueSlots->nextAvailableAt($company) : null;

        return view('social::queue.index', [
            'slots' => $slots,
            'nextAt' => $nextAt,
            'weekdays' => $this->weekdays(),
        ]);
    }

    public function store(StoreSocialQueueSlotRequest $request): RedirectResponse
    {
        $company = $request->user()->currentCompany();

        if (! $company) {
            return redirect()
                ->route('social.queue.index')
                ->withError(__('Select a company before managing queue slots.'));
        }

        SocialQueueSlot::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'weekday' => (int) $request->validated('weekday'),
                'time' => $request->validated('time'),
            ],
            [
                'timezone' => $request->validated('timezone') ?: config('app.timezone', 'UTC'),
                'is_active' => true,
            ]
        );

        return redirect()
            ->route('social.queue.index')
            ->with('success', __('Queue slot saved.'));
    }

    public function destroy(Request $request, SocialQueueSlot $slot): RedirectResponse
    {
        $company = $request->user()->currentCompany();

        if (! $company || (int) $slot->company_id !== (int) $company->id) {
            abort(404);
        }

        $slot->delete();

        return redirect()
            ->route('social.queue.index')
            ->with('success', __('Queue slot deleted.'));
    }

    public function seedRecommended(Request $request): RedirectResponse
    {
        $company = $request->user()->currentCompany();

        if (! $company) {
            return redirect()
                ->route('social.queue.index')
                ->withError(__('Select a company before seeding queue slots.'));
        }

        $created = $this->queueSlots->seedRecommended($company);

        return redirect()
            ->route('social.queue.index')
            ->with('success', __('Added :count recommended queue slot(s).', ['count' => count($created)]));
    }

    /**
     * @return array<int, string>
     */
    protected function weekdays(): array
    {
        return [
            0 => __('Sunday'),
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
        ];
    }
}
