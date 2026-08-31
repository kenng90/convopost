<?php

namespace App\Services\Flowmaker;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Services\Billing\CreditBillingResolver;
use App\Services\Billing\CreditCharger;
use App\Services\Messaging\DTO\MessageContent;
use App\Services\Messaging\InboundMessageProcessor;
use App\Services\Messaging\OutboundMessageService;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Events\AgentReplies;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Models\Reply;

class FlowOutboundService
{
    public const META_QUICK_REPLY_LIMIT = 13;

    public const META_QUICK_REPLY_TITLE_LIMIT = 20;

    public function __construct(
        private readonly OutboundMessageService $outbound,
        private readonly CreditCharger $charger,
        private readonly CreditBillingResolver $billingResolver,
    ) {
    }

    public function sendText(Contact $contact, string $text): ?Message
    {
        return $contact->sendMessage($text, false, false, 'TEXT', null, null, null, true);
    }

    /**
     * @param  array<int, array{id: string, title: string, description?: string}>  $choices
     */
    public function sendChoices(
        Contact $contact,
        string $body,
        array $choices,
        ?string $header = null,
        ?string $footer = null,
        ?string $listButton = null,
    ): ?Message {
        $choices = array_values(array_filter($choices, fn ($choice) => trim((string) ($choice['title'] ?? '')) !== ''));

        if ($choices === []) {
            return $this->sendText($contact, $body);
        }

        $channel = $contact->messagingChannel();

        if ($channel === MessagingChannelType::Whatsapp) {
            return $this->sendWhatsappChoices($contact, $body, $choices, $header, $footer, $listButton);
        }

        if ($channel === MessagingChannelType::Tiktok) {
            return $this->sendNumberedChoiceList($contact, $body, $choices, $header, $footer);
        }

        return $this->sendMetaChoices($contact, $body, $choices, $header, $footer);
    }

    /**
     * @param  array<int, array{id: string, title: string, description?: string}>  $choices
     */
    private function sendWhatsappChoices(
        Contact $contact,
        string $body,
        array $choices,
        ?string $header,
        ?string $footer,
        ?string $listButton,
    ): ?Message {
        $useList = count($choices) > 3 || ($listButton !== null && $listButton !== '');

        if (! $useList) {
            $reply = new Reply([
                'trigger' => 'none',
                'type' => 1,
                'text' => $body,
                'company_id' => $contact->company_id,
                'header' => $header ?? '',
                'footer' => $footer ?? '',
            ]);

            foreach (array_slice($choices, 0, 3) as $index => $choice) {
                $reply['button'.($index + 1)] = $choice['title'];
                $reply['button'.($index + 1).'_id'] = $choice['id'];
            }

            return $contact->sendReply($reply);
        }

        $sections = [[
            'title' => 'Options',
            'rows' => array_map(fn ($choice) => [
                'id' => $choice['id'],
                'title' => mb_substr($choice['title'], 0, 24),
                'description' => mb_substr((string) ($choice['description'] ?? ''), 0, 72),
            ], $choices),
        ]];

        $createData = [
            'contact_id' => $contact->id,
            'company_id' => $contact->company_id,
            'value' => $body,
            'header_text' => (string) ($header ?? ''),
            'footer_text' => (string) ($footer ?? ''),
            'buttons' => json_encode([
                'button' => $listButton ?: __('Choose an option'),
                'sections' => $sections,
            ]),
            'is_message_by_contact' => false,
            'is_campign_messages' => false,
            'status' => 1,
            'fb_message_id' => null,
            'channel' => MessagingChannelType::Whatsapp->value,
        ];

        $message = Message::create($createData);
        app(InboundMessageProcessor::class)->attachConversationToMessage($message, $contact);

        $company = Company::find($contact->company_id);
        $creditAction = $this->billingResolver->resolveInboxOutboundAction($contact, true, null);

        if ($company && ! $this->charger->canCharge($company, $creditAction)) {
            $message->status = 2;
            $message->error = $this->charger->insufficientCreditsMessage($creditAction);
            $message->save();

            return $message;
        }

        $contact->last_support_reply_at = now();
        $contact->is_last_message_by_contact = false;
        $contact->sendMessageToWhatsApp($message, $contact);

        if ($company) {
            $this->charger->charge($company, $creditAction, $contact->company_id);
            $companyUser = $company->user;
            if ($companyUser) {
                event(new AgentReplies($companyUser, $message, $contact));
            }
        }

        $contact->last_message = $contact->trimString($body, 40);
        $contact->update();

        return $message;
    }

    /**
     * @param  array<int, array{id: string, title: string, description?: string}>  $choices
     */
    private function sendMetaChoices(
        Contact $contact,
        string $body,
        array $choices,
        ?string $header,
        ?string $footer,
    ): ?Message {
        $textParts = array_filter([$header, $body, $footer], fn ($part) => is_string($part) && trim($part) !== '');
        $text = implode("\n\n", $textParts);

        if (count($choices) <= self::META_QUICK_REPLY_LIMIT) {
            $quickReplies = [];
            foreach ($choices as $choice) {
                $quickReplies[] = [
                    'content_type' => 'text',
                    'title' => mb_substr($choice['title'], 0, self::META_QUICK_REPLY_TITLE_LIMIT),
                    'payload' => $choice['id'],
                ];
            }

            return $this->sendMetaMessage($contact, MessageContent::textWithQuickReplies($text, $quickReplies));
        }

        return $this->sendNumberedChoiceList($contact, $body, $choices, $header, $footer);
    }

    /**
     * @param  array<int, array{id: string, title: string, description?: string}>  $choices
     */
    private function sendNumberedChoiceList(
        Contact $contact,
        string $body,
        array $choices,
        ?string $header,
        ?string $footer,
    ): ?Message {
        $textParts = array_filter([$header, $body, $footer], fn ($part) => is_string($part) && trim($part) !== '');
        $text = implode("\n\n", $textParts);

        $lines = [$text, ''];
        foreach ($choices as $index => $choice) {
            $n = $index + 1;
            $line = "{$n}. {$choice['title']}";
            if (! empty($choice['description'])) {
                $line .= ' — '.$choice['description'];
            }
            $lines[] = $line;
        }
        $lines[] = '';
        $lines[] = __('Reply with the number of your choice.');

        return $this->sendMetaMessage($contact, MessageContent::text(implode("\n", $lines)));
    }

    private function sendMetaMessage(Contact $contact, MessageContent $content): ?Message
    {
        $company = Company::find($contact->company_id);
        $creditAction = $this->billingResolver->resolveInboxOutboundAction($contact, true, null);

        if ($company && ! $this->charger->canCharge($company, $creditAction)) {
            $blocked = Message::create([
                'contact_id' => $contact->id,
                'company_id' => $contact->company_id,
                'value' => $content->body,
                'is_message_by_contact' => false,
                'is_campign_messages' => false,
                'status' => 2,
                'buttons' => '[]',
                'components' => '',
                'channel' => $contact->messagingChannel()->value,
                'error' => $this->charger->insufficientCreditsMessage($creditAction),
            ]);
            app(InboundMessageProcessor::class)->attachConversationToMessage($blocked, $contact);

            return $blocked;
        }

        $message = Message::create([
            'contact_id' => $contact->id,
            'company_id' => $contact->company_id,
            'value' => $content->body,
            'is_message_by_contact' => false,
            'is_campign_messages' => false,
            'status' => 1,
            'buttons' => json_encode($content->quickReplies ?? []),
            'components' => '',
            'channel' => $contact->messagingChannel()->value,
        ]);
        app(InboundMessageProcessor::class)->attachConversationToMessage($message, $contact);

        $result = $this->outbound->send($contact, $message, $content);

        if ($result->success && $company) {
            $this->charger->charge($company, $creditAction, $contact->company_id);
            $companyUser = $company->user;
            if ($companyUser) {
                event(new AgentReplies($companyUser, $message, $contact));
            }
        } elseif (! $result->success) {
            Log::warning('flowmaker.outbound.meta_choice_failed', [
                'contact_id' => $contact->id,
                'error' => $result->error,
            ]);
        }

        $contact->last_support_reply_at = now();
        $contact->is_last_message_by_contact = false;
        $contact->last_message = $contact->trimString($content->body, 40);
        $contact->update();

        return $message->fresh();
    }
}
