<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Social\Http\Requests\StoreSocialMediaAssetRequest;
use Modules\Social\Models\SocialMediaAsset;
use Modules\Social\Services\SocialMediaUploadService;

class MediaController extends Controller
{
    public function __construct(private readonly SocialMediaUploadService $uploads)
    {
    }

    public function index(Request $request): View
    {
        $company = $request->user()->currentCompany();

        $assets = SocialMediaAsset::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->orderByDesc('id')
            ->paginate(24);

        return view('social::media.index', [
            'assets' => $assets,
            'maxKilobytes' => (int) config('social.media.max_kilobytes', 51200),
            'allowedMimes' => (array) config('social.media.allowed_mimes', []),
        ]);
    }

    public function store(StoreSocialMediaAssetRequest $request): RedirectResponse
    {
        $company = $request->user()->currentCompany();

        if (! $company) {
            return redirect()
                ->route('social.media.index')
                ->withError(__('Select a company before uploading media.'));
        }

        $this->uploads->store($company, $request->file('file'), $request->user());

        return redirect()
            ->route('social.media.index')
            ->withStatus(__('Media uploaded successfully.'));
    }
}
