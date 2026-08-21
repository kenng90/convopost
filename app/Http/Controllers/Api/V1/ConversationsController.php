<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Api\PublicApiResponse;
use App\Services\Api\PublicMessageService;
use App\Services\Api\PublicWebhookDispatcher;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Wpbox\Events\Chatlistchange;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

class ConversationsController extends Controller
{
    public function __construct(
        private readonly PublicMessageService $messages,
        private readonly PublicWebhookDispatcher $webhooks,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('public_api_company');
        $limit = min(max((int) $request->input('limit', 50), 1), 100);
        $cursor = $request->input('cursor');

        $query = Contact::query()
            ->where('company_id', $company->id)
            ->where('has_chat', 1)
            ->orderByDesc('last_reply_at')
            ->orderByDesc('id');

        if ($cursor) {
            $query->where('id', '<', (int) $cursor);
        }

        if ($request->filled('status')) {
            if ($request->input('status') === 'resolved') {
                $query->where('resolved_chat', 1);
            } elseif ($request->input('status') === 'open') {
                $query->where(function ($builder) {
                    $builder->where('resolved_chat', 0)->orWhereNull('resolved_chat');
                });
            }
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return PublicApiResponse::success(
            $rows->map(fn (Contact $contact) => $this->present($contact))->values()->all(),
            200,
            [
                'limit' => $limit,
                'next_cursor' => $hasMore ? (string) $rows->last()?->id : null,
                'has_more' => $hasMore,
            ]
        );
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        $contact = $this->findContact($request, $conversation);
        $limit = min(max((int) $request->input('limit', 50), 1), 100);
        $beforeId = $request->input('before_id');

        $messages = Message::query()
            ->where('contact_id', $contact->id)
            ->where('company_id', $contact->company_id)
            ->where('status', '>', 0)
            ->when($beforeId, fn ($query) => $query->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return PublicApiResponse::success([
            'conversation' => $this->present($contact),
            'messages' => $messages->map(fn (Message $message) => $this->messages->present($message))->values()->all(),
            'has_more' => $messages->count() === $limit,
        ]);
    }

    public function reply(Request $request, int $conversation): JsonResponse
    {
        $contact = $this->findContact($request, $conversation);
        $request->merge([
            'contact_id' => $contact->id,
            'phone' => $contact->phone,
            'channel' => $request->input('channel', 'whatsapp'),
        ]);

        return $this->messages->send($request, $request->attributes->get('public_api_company'));
    }

    public function assign(Request $request, int $conversation): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer']);
        $contact = $this->findContact($request, $conversation);
        $company = $request->attributes->get('public_api_company');
        $ownerId = optional($company->user)->id;

        $agent = User::query()
            ->where('id', $request->user_id)
            ->where(function ($query) use ($company, $ownerId) {
                $query->where('company_id', $company->id);
                if ($ownerId) {
                    $query->orWhere('id', $ownerId);
                }
            })
            ->first();

        if (! $agent) {
            throw new HttpResponseException(PublicApiResponse::error('not_found', 'Agent not found', 404));
        }

        $contact->user_id = $agent->id;
        $contact->save();
        event(new Chatlistchange($contact->id, $contact->company_id));
        $this->webhooks->dispatch($company->id, 'conversation.assigned', $this->present($contact));

        return PublicApiResponse::success($this->present($contact->fresh()));
    }

    public function resolve(Request $request, int $conversation): JsonResponse
    {
        $contact = $this->findContact($request, $conversation);
        $contact->resolved_chat = 1;
        $contact->save();
        event(new Chatlistchange($contact->id, $contact->company_id));
        $this->webhooks->dispatch($contact->company_id, 'conversation.resolved', $this->present($contact));

        return PublicApiResponse::success($this->present($contact));
    }

    public function reopen(Request $request, int $conversation): JsonResponse
    {
        $contact = $this->findContact($request, $conversation);
        $contact->resolved_chat = 0;
        $contact->save();
        event(new Chatlistchange($contact->id, $contact->company_id));
        $this->webhooks->dispatch($contact->company_id, 'conversation.opened', $this->present($contact));

        return PublicApiResponse::success($this->present($contact));
    }

    public function storeNote(Request $request, int $conversation): JsonResponse
    {
        $request->validate(['note' => 'required|string']);
        $contact = $this->findContact($request, $conversation);
        $note = $contact->addNote($request->note);

        return PublicApiResponse::success($this->messages->present($note), 201);
    }

    public function notes(Request $request, int $conversation): JsonResponse
    {
        $contact = $this->findContact($request, $conversation);
        $notes = $contact->notes()->orderByDesc('created_at')->get();

        return PublicApiResponse::success(
            $notes->map(fn (Message $note) => $this->messages->present($note))->values()->all()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'contact_id' => $contact->id,
            'name' => $contact->name,
            'phone' => $contact->phone,
            'status' => $contact->resolved_chat ? 'resolved' : 'open',
            'assigned_user_id' => $contact->user_id,
            'last_message' => $contact->last_message,
            'last_reply_at' => optional($contact->last_reply_at)?->toIso8601String(),
        ];
    }

    private function findContact(Request $request, int $id): Contact
    {
        $contact = Contact::query()
            ->where('company_id', $request->attributes->get('public_api_company')->id)
            ->find($id);

        if (! $contact) {
            throw new HttpResponseException(PublicApiResponse::error('not_found', 'Conversation not found', 404));
        }

        return $contact;
    }
}
