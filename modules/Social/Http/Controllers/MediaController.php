<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Social\Http\Requests\StoreSocialMediaAssetRequest;
use Modules\Social\Services\SocialMediaUploadService;

class MediaController extends Controller
{
    public function __construct(private readonly SocialMediaUploadService $uploads)
    {
    }

    public function store(StoreSocialMediaAssetRequest $request): RedirectResponse
    {
        $company = $request->user()->currentCompany();

        if (! $company) {
            return redirect()
                ->route('social.home')
                ->withError(__('Select a company before uploading media.'));
        }

        $this->uploads->store($company, $request->file('file'), $request->user());

        return redirect()
            ->route('social.home')
            ->withStatus(__('Media uploaded successfully.'));
    }
}
