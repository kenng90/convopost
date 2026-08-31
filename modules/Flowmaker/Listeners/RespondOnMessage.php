<?php

namespace Modules\Flowmaker\Listeners;

use App\Models\Company;
use App\Services\Flowmaker\FlowChannelCompatibility;
use App\Services\Flowmaker\FlowDispatchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Jobs\ProcessFlowMessage;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;

class RespondOnMessage
{
    public function handleMessageByContact($event)
    {
        try {
            $message = $event->message;
            $contact = $message->contact;

            if (! $contact) {
                Log::warning('flowmaker.respond.skip_missing_contact', [
                    'message_id' => $message->id ?? null,
                ]);

                return;
            }

            $channel = $message->channel ?? 'whatsapp';
            if (! in_array($channel, FlowChannelCompatibility::SUPPORTED_FLOW_CHANNELS, true)) {
                Log::debug('flowmaker.respond.skip_channel', [
                    'channel' => $channel,
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                ]);

                return;
            }

            if (! $contact->enabled_ai_bot) {
                Log::info('flowmaker.respond.skip_bot_disabled', [
                    'channel' => $channel,
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                ]);

                return;
            }

            if ($message->bot_has_replied) {
                Log::debug('flowmaker.respond.skip_already_replied', [
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                ]);

                return;
            }

            $company_id = $contact->company_id;
            $company = Company::findOrFail($company_id);

            $flows = Flow::withoutGlobalScopes()
                ->where('company_id', $company_id)
                ->where('is_active', true)
                ->whereNotNull('flow_data')
                ->where('flow_data', '!=', '')
                ->where('flow_data', '!=', '{}')
                ->get();

            $flowmakerContact = Contact::withoutGlobalScopes()->find($contact->id) ?: $contact;
            $messageBody = (string) ($message->value ?? $message->body ?? '');

            $flowsForChat = app(FlowDispatchService::class)->selectFlowsForMessage(
                $company,
                $flows,
                $flowmakerContact,
                $messageBody,
                $channel
            );

            if ($flowsForChat->isEmpty()) {
                Log::info('flowmaker.respond.no_matching_flows', [
                    'channel' => $channel,
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                    'active_flows' => $flows->pluck('id')->all(),
                    'body_preview' => mb_substr($messageBody, 0, 80),
                ]);

                if ($company->getConfig('action_agent_enabled', 'yes') === 'yes') {
                    app(\App\Services\Agents\ActionAgentService::class)
                        ->handleInbound($company, $contact, $messageBody);
                    app(\App\Services\Workspace\ConversationSlaService::class)->start($company, $contact);
                }

                return;
            }

            foreach ($flowsForChat as $flow) {
                ProcessFlowMessage::dispatch($flow->id, $message->id)->onQueue('flows');
                Log::info('flowmaker.respond.dispatched', [
                    'flow_id' => $flow->id,
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                    'channel' => $channel,
                ]);
            }
        } catch (\Throwable $th) {
            Log::error('flowmaker.respond.failed', [
                'error' => $th->getMessage(),
                'message_id' => $event->message->id ?? null,
            ]);
        }
    }

    public function filterFlowsForChat(Company $company, $flows): Collection
    {
        return app(FlowDispatchService::class)->filterFlowsForChat($company, $flows);
    }

    public function subscribe($events)
    {
        $events->listen(
            'Modules\Wpbox\Events\ContactReplies',
            [RespondOnMessage::class, 'handleMessageByContact']
        );
    }
}
