<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Social\Http\Requests\StoreSocialTemplateRequest;
use Modules\Social\Http\Requests\UpdateSocialTemplateRequest;
use Modules\Social\Models\SocialTemplate;

class TemplateController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->currentCompany();

        $templates = SocialTemplate::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->orderBy('name')
            ->paginate(20);

        return view('social::templates.index', [
            'templates' => $templates,
        ]);
    }

    public function store(StoreSocialTemplateRequest $request): RedirectResponse
    {
        $company = $request->user()->currentCompany();

        if (! $company) {
            return redirect()
                ->route('social.templates.index')
                ->withError(__('Select a company before creating templates.'));
        }

        SocialTemplate::query()->create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'content' => $request->validated('content'),
            'category' => $request->validated('category'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('social.templates.index')
            ->with('success', __('Template created.'));
    }

    public function update(UpdateSocialTemplateRequest $request, SocialTemplate $template): RedirectResponse
    {
        $this->ensureCompanyTemplate($request, $template);

        $template->update([
            'name' => $request->validated('name'),
            'content' => $request->validated('content'),
            'category' => $request->validated('category'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('social.templates.index')
            ->with('success', __('Template updated.'));
    }

    public function destroy(Request $request, SocialTemplate $template): RedirectResponse
    {
        $this->ensureCompanyTemplate($request, $template);
        $template->delete();

        return redirect()
            ->route('social.templates.index')
            ->with('success', __('Template deleted.'));
    }

    protected function ensureCompanyTemplate(Request $request, SocialTemplate $template): void
    {
        $company = $request->user()->currentCompany();

        if (! $company || (int) $template->company_id !== (int) $company->id) {
            abort(404);
        }
    }
}
