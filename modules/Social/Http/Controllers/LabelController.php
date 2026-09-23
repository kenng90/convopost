<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Social\Http\Requests\StoreSocialLabelRequest;
use Modules\Social\Models\SocialLabel;

class LabelController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->currentCompany();

        $labels = SocialLabel::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->orderBy('name')
            ->paginate(30);

        return view('social::labels.index', [
            'labels' => $labels,
        ]);
    }

    public function store(StoreSocialLabelRequest $request): RedirectResponse
    {
        $company = $request->user()->currentCompany();

        if (! $company) {
            return redirect()
                ->route('social.labels.index')
                ->withError(__('Select a company before creating labels.'));
        }

        SocialLabel::query()->create([
            'company_id' => $company->id,
            'name' => $request->validated('name'),
            'color' => $request->validated('color') ?: '#0E8A7A',
        ]);

        return redirect()
            ->route('social.labels.index')
            ->with('success', __('Label created.'));
    }

    public function destroy(Request $request, SocialLabel $label): RedirectResponse
    {
        $company = $request->user()->currentCompany();

        if (! $company || (int) $label->company_id !== (int) $company->id) {
            abort(404);
        }

        $label->delete();

        return redirect()
            ->route('social.labels.index')
            ->with('success', __('Label deleted.'));
    }
}
