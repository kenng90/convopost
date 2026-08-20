<?php

namespace Modules\Journies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Security\ApiTokenAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyStage;
use Modules\Journies\Services\JourneyContactService;
use Modules\Wpbox\Models\Contact;

class ApiController extends Controller
{
    public function __construct(private JourneyContactService $journeyContacts)
    {
    }

    private function authenticate(Request $request, \Closure $next, array $rules = ['token' => 'required'])
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $auth = app(ApiTokenAuthenticator::class)->authenticate($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        return $next($request);
    }

    public function moveContact(Request $request): JsonResponse
    {
        return $this->authenticate($request, function (Request $request) {
            $request->validate([
                'phone' => 'required_without:contact_id|string',
                'contact_id' => 'required_without:phone|integer',
                'stage_id' => 'required|integer|exists:journey_stages,id',
                'fire_campaign' => 'sometimes|boolean',
            ]);

            $company = $this->getCompany();
            if (! $company) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
            }

            $contact = $request->filled('contact_id')
                ? Contact::query()->where('company_id', $company->id)->findOrFail($request->contact_id)
                : Contact::query()->where('company_id', $company->id)->where('phone', $request->phone)->firstOrFail();

            $stage = JourneyStage::with('journey')->findOrFail($request->stage_id);

            if ((int) $contact->company_id !== (int) $company->id
                || (int) $stage->journey->company_id !== (int) $company->id) {
                return response()->json(['status' => 'error', 'message' => 'Contact and stage belong to different companies'], 422);
            }

            $result = $this->journeyContacts->moveContactToStage(
                $contact,
                $stage,
                'api',
                Auth::id(),
                $request->boolean('fire_campaign', true),
            );

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'data' => $result,
            ], $result['success'] ? 200 : 422);
        }, [
            'token' => 'required',
            'stage_id' => 'required|integer',
        ]);
    }

    public function contactStatus(Request $request): JsonResponse
    {
        return $this->authenticate($request, function (Request $request) {
            $request->validate([
                'phone' => 'required_without:contact_id|string',
                'contact_id' => 'required_without:phone|integer',
                'journey_id' => 'required|integer|exists:journeys,id',
            ]);

            $company = $this->getCompany();
            if (! $company) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
            }

            $contact = $request->filled('contact_id')
                ? Contact::query()->where('company_id', $company->id)->findOrFail($request->contact_id)
                : Contact::query()->where('company_id', $company->id)->where('phone', $request->phone)->firstOrFail();

            $journey = Journey::query()->where('company_id', $company->id)->findOrFail($request->journey_id);
            $currentStage = $this->journeyContacts->currentStageForContact($contact, $journey);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'in_journey' => $currentStage !== null,
                    'stage_id' => $currentStage?->id,
                    'stage_name' => $currentStage?->name,
                    'journey_id' => $journey->id,
                    'journey_name' => $journey->name,
                ],
            ]);
        }, [
            'token' => 'required',
            'journey_id' => 'required|integer',
        ]);
    }
}
