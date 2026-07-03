<?php

namespace Modules\Whatsappcall\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ListCatalog;
use Illuminate\Http\Request;
use Modules\Contacts\Models\Field;
use Modules\Flowmaker\Models\Flow;
use Modules\Whatsappcall\Services\CompanyVoiceOpenAiKeyResolver;

class SetupController extends Controller
{
    use \Modules\Whatsappcall\Traits\WhatsappCall;

    public function index()
    {
        $company = $this->getCompany();
        $settings = [
            'enabled' => (bool) $company->getConfig('whatsapp_calling_enabled', false),
            'inbound_allowed' => (bool) $company->getConfig('whatsapp_calling_inbound_allowed', true),
            'timezone_id' => $company->getConfig('whatsapp_calling_timezone_id', 'UTC'),
            'hours_status' => $company->getConfig('whatsapp_calling_hours_status', 'DISABLED'),
            'weekly_operating_hours' => json_decode($company->getConfig('whatsapp_calling_weekly_operating_hours', '[]'), true) ?: [],
            'holiday_schedule' => json_decode($company->getConfig('whatsapp_calling_holiday_schedule', '[]'), true) ?: [],
            'call_icon_visibility' => $company->getConfig('whatsapp_call_icon_visibility', 'DEFAULT'),
            'callback_permission_status' => $company->getConfig('whatsapp_calling_callback_permission_status', 'DISABLED'),
            'call_handling' => $company->getConfig('whatsapp_call_handling', 'live'),
            'use_builtin_worker' => (bool) $company->getConfig('whatsapp_use_builtin_worker', false),
            'ai_worker_url' => $company->getConfig('whatsapp_ai_worker_url', config('whatsappcallworker.default_url', 'http://127.0.0.1:8787')),
            'ai_greeting' => $company->getConfig('whatsapp_ai_greeting', ''),
            'ai_handoff_phrases' => json_decode($company->getConfig('whatsapp_ai_handoff_phrases', '[]'), true) ?: [],
            'ai_required_fields' => json_decode($company->getConfig('whatsapp_ai_required_fields', '[]'), true) ?: [],
            'ai_flow_id' => (int) $company->getConfig('whatsapp_ai_flow_id', 0) ?: null,
            'ai_catalog_ids' => json_decode($company->getConfig('whatsapp_ai_catalog_ids', '[]'), true) ?: [],
            'ai_enable_vector_search' => filter_var($company->getConfig('whatsapp_ai_enable_vector_search', true), FILTER_VALIDATE_BOOLEAN),
            'ai_send_invoice_after_call' => filter_var($company->getConfig('whatsapp_ai_send_invoice_after_call', true), FILTER_VALIDATE_BOOLEAN),
            'ai_booking_enabled' => filter_var($company->getConfig('whatsapp_ai_booking_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'ai_booking_appointments' => filter_var($company->getConfig('whatsapp_ai_booking_appointments', true), FILTER_VALIDATE_BOOLEAN),
            'ai_booking_events' => filter_var($company->getConfig('whatsapp_ai_booking_events', true), FILTER_VALIDATE_BOOLEAN),
            'ai_booking_send_payment_link' => filter_var($company->getConfig('whatsapp_ai_booking_send_payment_link', true), FILTER_VALIDATE_BOOLEAN),
            'ai_booking_hold_minutes' => (int) $company->getConfig('whatsapp_ai_booking_hold_minutes', 15),
            'ai_openai_api_key_set' => app(CompanyVoiceOpenAiKeyResolver::class)->isConfigured($company),
            'ai_voice_ready' => app(CompanyVoiceOpenAiKeyResolver::class)->isConfigured($company),
        ];

        $flows = class_exists(Flow::class)
            ? Flow::where('company_id', $company->id)->orderBy('name')->get(['id', 'name'])
            : collect();

        $catalogs = ListCatalog::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);
        $customFields = Field::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);

        return view('whatsappcall::setup.index', compact('settings', 'flows', 'catalogs', 'customFields'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'sometimes|boolean',
            'inbound_allowed' => 'sometimes|boolean',
            'hours_status' => 'required|in:ENABLED,DISABLED',
            'timezone_id' => 'required|string',
            'call_icon_visibility' => 'nullable|string',
            'callback_permission_status' => 'required|in:ENABLED,DISABLED',
            'call_handling' => 'required|in:live,ai,ai_after_hours',
            'use_builtin_worker' => 'sometimes|boolean',
            'ai_worker_url' => 'nullable|url|max:500',
            'ai_worker_secret' => 'nullable|string|max:255',
            'ai_greeting' => 'nullable|string|max:2000',
            'ai_handoff_phrases' => 'nullable|string',
            'ai_required_fields' => 'nullable|array',
            'ai_flow_id' => 'nullable|integer',
            'ai_catalog_ids' => 'nullable|array',
            'ai_catalog_ids.*' => 'integer',
            'ai_enable_vector_search' => 'sometimes|boolean',
            'ai_send_invoice_after_call' => 'sometimes|boolean',
            'ai_booking_enabled' => 'sometimes|boolean',
            'ai_booking_appointments' => 'sometimes|boolean',
            'ai_booking_events' => 'sometimes|boolean',
            'ai_booking_send_payment_link' => 'sometimes|boolean',
            'ai_booking_hold_minutes' => 'nullable|integer|min:5|max:60',
            'ai_openai_api_key' => 'nullable|string|max:500',
        ]);

        $company = $this->getCompany();

        $existingOpenAiKey = trim((string) $company->getConfig('whatsapp_ai_openai_api_key', ''));
        $incomingOpenAiKey = trim((string) ($validated['ai_openai_api_key'] ?? ''));
        $willHaveOpenAiKey = $incomingOpenAiKey !== '' || $existingOpenAiKey !== '';

        if (in_array($validated['call_handling'], ['ai', 'ai_after_hours'], true) && ! $willHaveOpenAiKey) {
            return redirect()->back()
                ->withInput()
                ->with('error', __('AI voice agents require your own OpenAI API key. Add one under OpenAI API key (voice Realtime) before enabling AI call handling.'));
        }

        $company->setConfig('whatsapp_calling_enabled', $validated['enabled'] ?? false);
        $company->setConfig('whatsapp_calling_inbound_allowed', $validated['inbound_allowed'] ?? true);
        $company->setConfig('whatsapp_calling_hours_status', $validated['hours_status']);
        $company->setConfig('whatsapp_calling_timezone_id', $validated['timezone_id']);
        $company->setConfig('whatsapp_call_icon_visibility', $validated['call_icon_visibility'] ?? 'DEFAULT');
        $company->setConfig('whatsapp_calling_callback_permission_status', $validated['callback_permission_status']);

        $company->setConfig('whatsapp_call_handling', $validated['call_handling']);
        $company->setConfig('whatsapp_use_builtin_worker', $request->boolean('use_builtin_worker'));
        $workerUrl = $validated['ai_worker_url'] ?? '';
        if ($request->boolean('use_builtin_worker') && $workerUrl === '') {
            $workerUrl = config('whatsappcallworker.default_url', 'http://127.0.0.1:8787');
        }
        $company->setConfig('whatsapp_ai_worker_url', $workerUrl);

        if (! empty($validated['ai_worker_secret'])) {
            $company->setConfig('whatsapp_ai_worker_secret', $validated['ai_worker_secret']);
        }

        $company->setConfig('whatsapp_ai_greeting', $validated['ai_greeting'] ?? '');
        $phrases = array_values(array_filter(array_map('trim', explode("\n", $validated['ai_handoff_phrases'] ?? ''))));
        $company->setConfig('whatsapp_ai_handoff_phrases', json_encode($phrases));
        $company->setConfig('whatsapp_ai_required_fields', json_encode(array_values($validated['ai_required_fields'] ?? [])));
        $company->setConfig('whatsapp_ai_flow_id', $validated['ai_flow_id'] ?: '');
        $company->setConfig('whatsapp_ai_catalog_ids', json_encode(array_values($validated['ai_catalog_ids'] ?? [])));
        $company->setConfig('whatsapp_ai_enable_vector_search', $request->boolean('ai_enable_vector_search', true));
        $company->setConfig('whatsapp_ai_send_invoice_after_call', $request->boolean('ai_send_invoice_after_call', true));
        $company->setConfig('whatsapp_ai_booking_enabled', $request->boolean('ai_booking_enabled', false));
        $company->setConfig('whatsapp_ai_booking_appointments', $request->boolean('ai_booking_appointments', true));
        $company->setConfig('whatsapp_ai_booking_events', $request->boolean('ai_booking_events', true));
        $company->setConfig('whatsapp_ai_booking_send_payment_link', $request->boolean('ai_booking_send_payment_link', true));
        $company->setConfig('whatsapp_ai_booking_hold_minutes', (int) ($validated['ai_booking_hold_minutes'] ?? 15));

        if (! empty($validated['ai_openai_api_key'])) {
            $company->setConfig('whatsapp_ai_openai_api_key', trim($validated['ai_openai_api_key']));
        }

        $days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
        $weekly = [];
        foreach ($days as $day) {
            $enabled = (bool) $request->input("weekly.$day.enabled", false);
            $open = $request->input("weekly.$day.open_time");
            $close = $request->input("weekly.$day.close_time");
            if ($enabled && $open && $close) {
                $weekly[] = [
                    'day_of_week' => $day,
                    'open_time' => str_replace(':', '', $open),
                    'close_time' => str_replace(':', '', $close),
                ];
            }
        }
        $company->setConfig('whatsapp_calling_weekly_operating_hours', json_encode($weekly));

        $holidaysInput = $request->input('holidays', []);
        $holidays = [];
        if (is_array($holidaysInput)) {
            foreach ($holidaysInput as $h) {
                if (! empty($h['date'])) {
                    $holidays[] = [
                        'date' => $h['date'],
                        'start_time' => isset($h['start_time']) ? str_replace(':', '', $h['start_time']) : '0000',
                        'end_time' => isset($h['end_time']) ? str_replace(':', '', $h['end_time']) : '2359',
                    ];
                }
            }
        }
        $company->setConfig('whatsapp_calling_holiday_schedule', json_encode($holidays));

        $result = $this->syncCallSettingsWithMeta($company);

        if (is_array($result) && isset($result['ok']) && ! $result['ok']) {
            $body = $result['body'] ?? null;
            $details = is_array($body) ? json_encode($body) : ($result['error_message'] ?? (string) $body);
            $msg = __('Failed to update WhatsApp Calling settings: ').$details;

            return redirect()->back()->with('error', $msg);
        }

        $okMsg = __('WhatsApp Calling settings saved.');
        if (is_array($result)) {
            $okMsg .= ' (HTTP '.($result['status'] ?? '200').')';
            if (isset($result['body'])) {
                $okMsg .= ' - '.(is_array($result['body']) ? json_encode($result['body']) : (string) $result['body']);
            }
        }

        return redirect()->back()->with('status', $okMsg);
    }
}
