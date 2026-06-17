<?php

namespace Modules\Wpbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Platform\AgentCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Wpbox\Models\Contact;

class CopilotController extends Controller
{
    public function __construct(private AgentCopilotService $copilot)
    {
    }

    public function suggest(Request $request, Contact $contact): JsonResponse
    {
        $this->ownerAndStaffOnly();

        if ((int) $contact->company_id !== (int) $this->getCompany()->id) {
            abort(403);
        }

        $draft = $request->input('draft');

        return response()->json(
            $this->copilot->suggest($this->getCompany(), $contact, $draft)
        );
    }

    public function templates(): JsonResponse
    {
        $this->ownerAndStaffOnly();

        return response()->json([
            'templates' => $this->copilot->approvedTemplates($this->getCompany()),
        ]);
    }
}
