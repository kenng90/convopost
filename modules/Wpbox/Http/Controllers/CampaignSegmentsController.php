<?php

namespace Modules\Wpbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Campaign\CampaignAudienceResolver;
use Illuminate\Http\Request;
use Modules\Wpbox\Models\CampaignSegment;

class CampaignSegmentsController extends Controller
{
    public function index(CampaignAudienceResolver $audienceResolver)
    {
        $this->ownerAndStaffOnly();

        $segments = CampaignSegment::orderByDesc('id')->get()->map(function (CampaignSegment $segment) use ($audienceResolver) {
            $audience = $audienceResolver->resolve($this->getCompany(), ['segment_id' => $segment->id]);

            return [
                'model' => $segment,
                'subscribed_count' => $audience['subscribed_count'],
            ];
        });

        return view('wpbox::campaigns.segments.index', [
            'segments' => $segments,
        ]);
    }

    public function store(Request $request)
    {
        $this->ownerAndStaffOnly();

        $request->validate([
            'name' => 'required|string|max:120',
            'filters' => 'nullable|array',
        ]);

        CampaignSegment::create([
            'name' => $request->name,
            'filters' => $request->input('filters', []),
        ]);

        return redirect()->route('campaigns.segments.index')->withStatus(__('Segment created.'));
    }

    public function destroy(CampaignSegment $segment)
    {
        $this->ownerAndStaffOnly();
        $segment->delete();

        return redirect()->route('campaigns.segments.index')->withStatus(__('Segment deleted.'));
    }
}
