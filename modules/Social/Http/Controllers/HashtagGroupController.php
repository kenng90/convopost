<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Social\Http\Requests\StoreSocialHashtagGroupRequest;
use Modules\Social\Models\SocialHashtagGroup;

class HashtagGroupController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->currentCompany();

        $groups = SocialHashtagGroup::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->orderBy('name')
            ->paginate(20);

        return view('social::hashtags.index', [
            'groups' => $groups,
        ]);
    }

    public function store(StoreSocialHashtagGroupRequest $request): RedirectResponse
    {
        $company = $request->user()->currentCompany();

        if (! $company) {
            return redirect()
                ->route('social.hashtags.index')
                ->withError(__('Select a company before creating hashtag groups.'));
        }

        SocialHashtagGroup::query()->create([
            'company_id' => $company->id,
            'name' => $request->validated('name'),
            'tags' => $this->parseTags($request->validated('tags')),
        ]);

        return redirect()
            ->route('social.hashtags.index')
            ->with('success', __('Hashtag group created.'));
    }

    public function destroy(Request $request, SocialHashtagGroup $hashtag): RedirectResponse
    {
        $company = $request->user()->currentCompany();

        if (! $company || (int) $hashtag->company_id !== (int) $company->id) {
            abort(404);
        }

        $hashtag->delete();

        return redirect()
            ->route('social.hashtags.index')
            ->with('success', __('Hashtag group deleted.'));
    }

    /**
     * @return list<string>
     */
    protected function parseTags(string $raw): array
    {
        return collect(preg_split('/[\s,]+/', $raw) ?: [])
            ->map(fn ($tag) => ltrim(trim((string) $tag), '#'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
