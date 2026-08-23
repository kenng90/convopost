<?php

namespace App\Services\Agents;

use App\Models\Company;
use App\Services\Platform\ManagedAiService;
use App\Services\Platform\OpenRouterService;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

class ActionAgentService
{
    public function __construct(
        private readonly AgentToolRegistry $tools,
        private readonly OpenRouterService $openRouter,
        private readonly ManagedAiService $managedAi,
    ) {
    }

    /**
     * @return array{reply: string, tools_used: array<int, string>, handoff: bool, source: string}
     */
    public function handleInbound(Company $company, Contact $contact, string $message): array
    {
        $result = $this->reason($company, $contact, $message, 'chat');

        app(AgentMemoryService::class)->remember(
            $company,
            $contact,
            $message,
            (string) ($result['reply'] ?? ''),
            $result['tools_used'] ?? [],
            ['handoff' => $result['handoff'] ?? false],
        );

        if ($result['handoff']) {
            return $result;
        }

        if ($result['reply'] !== '') {
            $contact->sendMessage($result['reply'], false, false, 'TEXT', null, null, 'send_bot_auto_reply', true);
        }

        return $result;
    }

    /**
     * @return array{reply: string, tools_used: array<int, string>, handoff: bool, source: string}
     */
    public function replyForVoice(Company $company, Contact $contact, string $speech, string $priorTranscript = ''): array
    {
        $prompt = trim($priorTranscript."\nCaller: ".$speech);

        return $this->reason($company, $contact, $prompt, 'voice');
    }

    /**
     * @return array{reply: string, tools_used: array<int, string>, handoff: bool, source: string}
     */
    public function reason(Company $company, Contact $contact, string $message, string $channel = 'chat'): array
    {
        $fallback = $this->ruleBased($company, $contact, $message);
        $keyInfo = $this->managedAi->resolveOpenRouterKey($company);

        if (! filled($keyInfo['key']) || ! $this->managedAi->canPerformAction($company, 'ai_llm_reply')) {
            return $fallback;
        }

        try {
            $llm = $this->callModel($company, $contact, $message, $channel, (string) $keyInfo['key']);
            if ($keyInfo['should_meter']) {
                $this->managedAi->consume($company, $this->managedAi->actionCost('ai_llm_reply'), 'ai_llm_reply', [
                    'model' => $llm['model'] ?? null,
                    'channel' => $channel,
                ]);
            }

            return $llm;
        } catch (\Throwable $e) {
            Log::warning('action_agent.llm_failed', ['error' => $e->getMessage()]);

            return $fallback;
        }
    }

    /**
     * @return array{reply: string, tools_used: array<int, string>, handoff: bool, source: string}
     */
    public function ruleBased(Company $company, Contact $contact, string $message): array
    {
        $lower = mb_strtolower($message);
        $toolsUsed = [];

        if (str_contains($lower, 'human') || str_contains($lower, 'agent') || str_contains($lower, 'talk to')) {
            $handoff = $this->tools->execute($company, $contact, 'handoff_to_human', [
                'reason' => 'Customer asked for a human',
            ]);

            return [
                'reply' => __('I am connecting you with a teammate now.'),
                'tools_used' => ['handoff_to_human'],
                'handoff' => (bool) ($handoff['ok'] ?? false),
                'source' => 'rules',
            ];
        }

        if (preg_match('/\b(price|buy|order|catalog|product|shop)\b/i', $message)) {
            $search = $this->tools->searchCatalog($company, $message);
            $toolsUsed[] = 'search_catalog';
            $titles = collect($search['items'] ?? [])->pluck('title')->filter()->take(3)->implode(', ');
            $reply = $titles
                ? __('Here is what I found: :items. Would you like to order one?', ['items' => $titles])
                : __('I can help you browse our catalog. Tell me what you are looking for.');

            return ['reply' => $reply, 'tools_used' => $toolsUsed, 'handoff' => false, 'source' => 'rules'];
        }

        if (preg_match('/\b(book|booking|appointment|reserve)\b/i', $message)) {
            $listed = $this->tools->execute($company, $contact, 'list_services');
            $toolsUsed[] = 'list_services';
            $names = collect($listed['services'] ?? [])->pluck('name')->filter()->take(3)->implode(', ');
            $reply = $names
                ? __('I can book: :services. Which one and when?', ['services' => $names])
                : __('I can help you book. Tell me the service and time you want.');

            return ['reply' => $reply, 'tools_used' => $toolsUsed, 'handoff' => false, 'source' => 'rules'];
        }

        if (preg_match('/\b(pay|charge|stk|mpesa|invoice)\b/i', $message)) {
            preg_match('/(\d+(?:\.\d+)?)/', $message, $amountMatch);
            $pending = app(AgentMemoryService::class)->pendingCharge($company, $contact);
            $amount = (float) ($amountMatch[1] ?? ($pending['amount'] ?? 0));
            $confirmed = (bool) preg_match('/\b(yes|confirm|proceed|ok)\b/i', $message);
            $pay = $this->tools->execute($company, $contact, 'send_payment', [
                'amount' => $amount,
                'description' => $message,
                'confirmed' => $confirmed && $amount > 0,
            ]);
            $toolsUsed[] = 'send_payment';
            $reply = ! empty($pay['needs_confirmation'])
                ? __('I can charge :amount. Reply YES to confirm.', ['amount' => $amount])
                : (($pay['ok'] ?? false)
                    ? __('Payment request is ready for :amount.', ['amount' => $pay['amount'] ?? $amount])
                    : ($pay['error'] ?? __('I could not start the payment. A teammate can help.')));

            app(AgentMemoryService::class)->remember($company, $contact, $message, $reply, $toolsUsed, [
                'pending_charge' => ! empty($pay['needs_confirmation']) ? ['amount' => $amount] : null,
            ]);

            return ['reply' => $reply, 'tools_used' => $toolsUsed, 'handoff' => false, 'source' => 'rules'];
        }

        $kb = $this->tools->searchKnowledge($company, $message);
        if (! empty($kb['articles'])) {
            $toolsUsed[] = 'search_knowledge';
            $first = $kb['articles'][0];

            return [
                'reply' => $first['excerpt'] ?: $first['title'],
                'tools_used' => $toolsUsed,
                'handoff' => false,
                'source' => 'rules',
            ];
        }

        $name = $contact->name ?: __('there');

        return [
            'reply' => __('Hi :name, I can help with bookings, orders, and questions. How can I help?', ['name' => $name]),
            'tools_used' => $toolsUsed,
            'handoff' => false,
            'source' => 'rules',
        ];
    }

    /**
     * @return array{reply: string, tools_used: array<int, string>, handoff: bool, source: string, model?: string}
     */
    private function callModel(Company $company, Contact $contact, string $message, string $channel, string $apiKey): array
    {
        $history = $this->recentHistory($contact);
        $memory = app(AgentMemoryService::class)->recentTurns($company, $contact);
        $system = 'You are the Convocon business agent for '.$company->name.'. '
            .'Use tools to search knowledge, catalog, bookings, invoices, and journeys. '
            .'Never collect payment until the customer confirms. Never exceed the company charge limit. '
            .'Channel: '.$channel.'. Keep replies short. Call handoff_to_human when the customer asks for a person or you cannot complete the job.';

        $messages = [
            ['role' => 'system', 'content' => $system],
            ...$memory,
            ...$history,
            ['role' => 'user', 'content' => $message],
        ];

        $model = config('managed-ai.flow_generate_model', 'openai/gpt-4o-mini');
        $toolsUsed = [];
        $handoff = false;
        $reply = '';

        for ($i = 0; $i < 4; $i++) {
            $response = $this->openRouter->chatCompletion(
                $apiKey,
                $messages,
                $model,
                0.3,
                800,
                false,
                $this->tools->openRouterTools()
            );

            $choice = $response['message'] ?? [];
            $toolCalls = $choice['tool_calls'] ?? [];
            $content = trim((string) ($choice['content'] ?? $response['content'] ?? ''));

            if ($toolCalls === []) {
                $reply = $content;

                break;
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $content !== '' ? $content : null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $call) {
                $name = $call['function']['name'] ?? '';
                $arguments = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];
                $result = $this->tools->execute($company, $contact, $name, is_array($arguments) ? $arguments : []);
                $toolsUsed[] = $name;
                if ($name === 'handoff_to_human' && ($result['ok'] ?? false)) {
                    $handoff = true;
                }
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'] ?? $name,
                    'content' => json_encode($result),
                ];
            }

            if ($handoff) {
                $reply = $content !== '' ? $content : __('I am connecting you with a teammate now.');

                break;
            }
        }

        return [
            'reply' => $reply !== '' ? $reply : __('Let me look into that for you.'),
            'tools_used' => array_values(array_unique($toolsUsed)),
            'handoff' => $handoff,
            'source' => 'llm',
            'model' => $model,
        ];
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function recentHistory(Contact $contact): array
    {
        if (! class_exists(Message::class)) {
            return [];
        }

        return Message::withoutGlobalScopes()
            ->where('contact_id', $contact->id)
            ->where('status', '>', 0)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->reverse()
            ->map(fn (Message $message) => [
                'role' => $message->is_message_by_contact ? 'user' : 'assistant',
                'content' => (string) $message->value,
            ])
            ->filter(fn ($row) => trim($row['content']) !== '')
            ->values()
            ->all();
    }
}
