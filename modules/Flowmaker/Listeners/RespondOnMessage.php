<?php

namespace Modules\Flowmaker\Listeners;

use App\Models\Company;
use App\Services\Flowmaker\FlowDispatchService;
use Illuminate\Support\Collection;
use Modules\Flowmaker\Jobs\ProcessFlowMessage;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;

class RespondOnMessage
{
    public function handleMessageByContact($event)
    {
        try {
            $contact = $event->message->contact;
            $message = $event->message;
            if ($contact->enabled_ai_bot && ! $message->bot_has_replied) {
                $company_id = $contact->company_id;
                $company = Company::findOrFail($company_id);

                $flows = Flow::query()
                    ->where('company_id', $company_id)
                    ->where('is_active', true)
                    ->whereNotNull('flow_data')
                    ->where('flow_data', '!=', '')
                    ->where('flow_data', '!=', '{}')
                    ->get();

                $flowmakerContact = Contact::find($contact->id) ?: $contact;
                $messageBody = (string) ($message->value ?? $message->body ?? '');

                $flowsForChat = app(FlowDispatchService::class)->selectFlowsForMessage(
                    $company,
                    $flows,
                    $flowmakerContact,
                    $messageBody
                );

                foreach ($flowsForChat as $flow) {
                    ProcessFlowMessage::dispatch($flow->id, $message->id)->onQueue('flows');
                }
            }
        } catch (\Throwable $th) {
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
