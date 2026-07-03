<?php

namespace Modules\Whatsappcall\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\VoiceBooking\VoiceBookingToolService;
use Illuminate\Http\Request;
use Modules\Whatsappcall\Models\Call as CallModel;

class VoiceBookingToolController extends Controller
{
    public function invoke(Request $request, CallModel $call, VoiceBookingToolService $toolService)
    {
        $call = $this->resolveWorkerCall($call);

        $validated = $request->validate([
            'tool' => 'required|string|max:80',
            'arguments' => 'nullable|array',
            'tool_call_id' => 'nullable|string|max:120',
        ]);

        $result = $toolService->invoke(
            $call,
            $validated['tool'],
            $validated['arguments'] ?? [],
            $validated['tool_call_id'] ?? null
        );

        return response()->json($result);
    }

    private function resolveWorkerCall(CallModel $call): CallModel
    {
        if ($call->exists && $call->getKey()) {
            return $call->fresh() ?? $call;
        }

        $routeKey = request()->route('call');
        if ($routeKey instanceof CallModel && $routeKey->exists) {
            return $routeKey->fresh() ?? $routeKey;
        }

        $id = is_numeric($routeKey) ? (int) $routeKey : (is_numeric($call->getKey()) ? (int) $call->getKey() : null);

        if (! $id) {
            abort(404, 'Call not found');
        }

        return CallModel::findOrFail($id);
    }
}
