<?php

namespace Modules\Journies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
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

        if (Auth::check()) {
            return $next($request);
        }

        $token = PersonalAccessToken::findToken($request->token);

        if (! $token) {
            return response()->json(['status' => 'error', 'message' => 'Invalid token'], 401);
        }

        $user = User::findOrFail($token->tokenable_id);
        Auth::login($user);

        if ($user->company_id) {
            session(['company_id' => $user->company_id]);
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

            $contact = $request->filled('contact_id')
                ? Contact::findOrFail($request->contact_id)
                : Contact::where('phone', $request->phone)->firstOrFail();

            $stage = JourneyStage::findOrFail($request->stage_id);

            if ($contact->company_id !== $stage->journey->company_id) {
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

            $contact = $request->filled('contact_id')
                ? Contact::findOrFail($request->contact_id)
                : Contact::where('phone', $request->phone)->firstOrFail();

            $journey = Journey::findOrFail($request->journey_id);
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
