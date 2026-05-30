<?php

namespace Modules\Voicecall\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ListCatalog;
use App\Services\Telephony\TelephonyConfig;
use Illuminate\Http\Request;
use Modules\Flowmaker\Models\Flow;
use Modules\Voicecall\Models\VoicePhoneNumber;

class SetupController extends Controller
{
    public function index()
    {
        $company = $this->getCompany();
        $telephony = TelephonyConfig::forCompany($company);
        $numbers = VoicePhoneNumber::where('company_id', $company->id)->orderBy('phone_number')->get();
        $flows = Flow::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);
        $catalogs = ListCatalog::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);
        $credentialsOk = $telephony->voiceReady();
        $webhookBase = $telephony->isTelnyx()
            ? url('/webhook/voicecall/telnyx')
            : url('/webhook/voicecall/twilio');

        return view('voicecall::setup.index', compact(
            'numbers',
            'flows',
            'catalogs',
            'credentialsOk',
            'telephony',
            'webhookBase',
            'company'
        ));
    }

    public function store(Request $request)
    {
        return redirect()->route('voicecall.settings')->with('status', __('Use the form below to add phone numbers.'));
    }

    public function storeNumber(Request $request)
    {
        $company = $this->getCompany();

        $telephony = TelephonyConfig::forCompany($company);
        if (! $telephony->voiceReady()) {
            return redirect()->back()->with('error', $telephony->isTelnyx()
                ? __('Configure Telnyx API key and Connection ID in App Settings → Telephony first.')
                : __('Configure Twilio Account SID and Auth Token in App Settings → Telephony first.'));
        }

        $validated = $request->validate([
            'phone_number' => 'required|string|max:32',
            'friendly_name' => 'nullable|string|max:120',
            'voice_flow_id' => 'nullable|integer',
            'catalog_ids' => 'nullable|array',
            'catalog_ids.*' => 'integer',
            'ai_greeting' => 'nullable|string|max:2000',
            'handoff_phrases' => 'nullable|string',
            'required_field_keys' => 'nullable|array',
        ]);

        $phrases = array_values(array_filter(array_map('trim', explode("\n", $validated['handoff_phrases'] ?? ''))));

        VoicePhoneNumber::create([
            'company_id' => $company->id,
            'provider' => $telephony->provider,
            'phone_number' => $this->normalizePhone($validated['phone_number']),
            'friendly_name' => $validated['friendly_name'] ?? null,
            'voice_flow_id' => $validated['voice_flow_id'] ?: null,
            'catalog_ids' => array_values($validated['catalog_ids'] ?? []),
            'ai_greeting' => $validated['ai_greeting'] ?? '',
            'handoff_phrases' => $phrases,
            'required_field_keys' => array_values($validated['required_field_keys'] ?? ['name', 'phone']),
            'is_active' => true,
        ]);

        $hint = $telephony->isTelnyx()
            ? __('Voice number added. Set your Telnyx Voice API Application webhook to the URL shown below.')
            : __('Voice number added. Point Twilio voice webhook to the URL shown below.');

        return redirect()->route('voicecall.settings')->with('status', $hint);
    }

    public function destroyNumber(VoicePhoneNumber $number)
    {
        $company = $this->getCompany();
        if ((int) $number->company_id !== (int) $company->id) {
            abort(403);
        }
        $number->delete();

        return redirect()->route('voicecall.settings')->with('status', __('Number removed.'));
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);

        return str_starts_with($phone, '+') ? $phone : '+'.$phone;
    }
}
