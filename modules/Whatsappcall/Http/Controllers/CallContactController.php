<?php

namespace Modules\Whatsappcall\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Whatsappcall\Models\Call as CallModel;
use Modules\Wpbox\Models\Contact;

class CallContactController extends Controller
{
    public function lastVoiceCall(Contact $contact)
    {
        $company = $this->getCompany();
        if ((int) $contact->company_id !== (int) $company->id) {
            abort(403);
        }

        $call = CallModel::where('company_id', $company->id)
            ->where('contact_id', $contact->id)
            ->whereIn('handled_by_type', ['ai', 'human'])
            ->orderByDesc('id')
            ->first();

        if (! $call) {
            return response()->json(['ok' => true, 'call' => null]);
        }

        $fieldsCaptured = 0;
        $fieldsTotal = 0;
        $structured = $call->structured ?? [];
        if (! empty($structured['fields']) && is_array($structured['fields'])) {
            $fieldsTotal = count($structured['fields']);
            $fieldsCaptured = collect($structured['fields'])
                ->filter(fn ($f) => in_array($f['status'] ?? '', ['confirmed', 'corrected'], true) && ! empty($f['value']))
                ->count();
        }

        return response()->json([
            'ok' => true,
            'call' => [
                'id' => $call->id,
                'handled_by_type' => $call->handled_by_type,
                'duration_seconds' => $call->duration_seconds,
                'intent' => $call->intent,
                'summary' => $call->summary,
                'structured' => $structured,
                'handoff_requested' => $call->handoff_requested,
                'handoff_reason' => $call->handoff_reason,
                'created_at' => $call->created_at,
                'fields_captured' => $fieldsCaptured,
                'fields_total' => $fieldsTotal,
                'brief_message_id' => $call->brief_message_id,
            ],
        ]);
    }
}
