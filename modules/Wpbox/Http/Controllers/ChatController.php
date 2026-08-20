<?php

namespace Modules\Wpbox\Http\Controllers;

use Akaunting\Module\Facade as Module;
use App\Enums\MessagingChannelType;
use App\Http\Controllers\Controller;
use App\Models\Messaging\ChannelConnection;
use App\Models\User;
use App\Services\PlanEntitlementResolver;
use App\Services\Platform\ActivationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Wpbox\Events\Chatlistchange;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Models\Reply;
use Modules\Wpbox\Models\Template;
use Modules\Wpbox\Traits\InboxModes;
use Modules\Wpbox\Traits\Whatsapp;

class ChatController extends Controller
{
    use InboxModes;
    use Whatsapp;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $user = auth()->user();
        $company = $this->getCompany();
        $whatsappReady = $company->getConfig('whatsapp_webhook_verified', 'no') == 'yes'
            && $company->getConfig('whatsapp_settings_done', 'no') == 'yes';

        $hasMessagingChannel = $whatsappReady || ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', 'connected')
            ->whereIn('channel', [
                MessagingChannelType::Instagram->value,
                MessagingChannelType::Messenger->value,
            ])
            ->exists();

        if (! $hasMessagingChannel) {
            if ($user->hasRole('owner')) {
                return redirect(route('whatsapp.setup'));
            }

            return redirect()->route('dashboard')->withError(__('WhatsApp is not configured for this workspace yet. Please contact the account owner.'));
        }

        $activation = app(ActivationService::class);
        if ($activation->shouldRedirectUserToActivation($user, $company)) {
            return redirect()->route('activation.index');
        }

        $templates = Template::where('status', 'APPROVED')->select('name', 'id', 'language')->get();
        $replies = Reply::where('type', 1)->where('flow_id', null)->get();

        $languages = explode(',', __('No translation').','.config('wpbox.available_languages', 'English,Spanish,German,Italian,Portuguese,Dutch,French,Japanese,Chinese'));

        //Find the users of the company
        $users = $this->getCompany()->users()->pluck('name', 'id');

        // Link fetcher data is lazy-loaded when the user opens a fetcher modal (refreshLinkData).
        $fetcherModules = [];
        $sidebarModules = [];
        foreach (Module::all() as $key => $module) {
            if ($module->get('isLinkFetcher')) {
                try {
                    $fetcherModules[$module->get('alias')] = [
                        'name' => $this->getCompany()->getConfig($module->get('alias').'_button_name', __('No name')),
                        'data' => [],
                    ];
                } catch (\Exception $e) {
                    //Do nothing
                }
            }
            if ($module->get('hasSidebar')) {
                try {
                    $moduleAlias = $module->get('alias');

                    if (! $module->get('alwayson') && ! $this->getCompany()->hasPlanPlugin($moduleAlias)) {
                        continue;
                    }

                    foreach ($module->get('sidebarData') as $sidebarApp) {
                        $sidebarModules[] = [
                            'alias' => $sidebarApp['app'],
                            'name' => $sidebarApp['name'],
                            'brandColor' => $sidebarApp['brandColor'] ?? '#96588A',
                            'icon' => $sidebarApp['icon'],
                            'view' => $sidebarApp['view'],
                            'script' => $sidebarApp['script'],
                        ];
                    }
                } catch (\Exception $e) {
                    //Do nothing
                    //dd($e);
                }
            }
        }

        usort($sidebarModules, function ($a, $b) {
            $priority = ['Contact' => 0, 'Customer 360' => 1];
            $pa = $priority[$a['name']] ?? 99;
            $pb = $priority[$b['name']] ?? 99;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp($a['name'], $b['name']);
        });

        $entitlements = app(PlanEntitlementResolver::class);

        return view('wpbox::chat.master', [
            'company' => $this->getCompany(),
            'templates' => $templates->toArray(),
            'replies' => $replies->toArray(),
            'users' => $users->toArray(),
            'languages' => $languages,
            'fetcherModules' => $fetcherModules,
            'sidebarModules' => $sidebarModules,
            'initialContactId' => $this->resolveInitialChatContactId(request()),
            'enabledChannels' => $this->resolveEnabledChannels($company, $user, $entitlements),
            'channelFilter' => request()->input('channel', 'all'),
        ]);
    }

    private function resolveInitialChatContactId(Request $request): ?int
    {
        if (! $request->filled('contact')) {
            return null;
        }

        $contact = Contact::query()
            ->where('company_id', $this->getCompany()->id)
            ->where('id', $request->integer('contact'))
            ->first();

        return $contact?->id;
    }

    /**
     * API
     */
    public function chatlist($lastmessagetime, $page = 1, $search_query = '')
    {
        $pageSize = config('wpbox.chat_page_size', 6);
        $companyId = $this->getCompany()->id;
        $userId = Auth::id();
        $agentAssignedOnly = Auth::user()->hasRole('staff')
            && $this->getCompany()->getConfig('agent_assigned_only', 'false') != 'false';

        $baseQuery = Contact::query()
            ->where('company_id', $companyId)
            ->where('has_chat', 1)
            ->when($agentAssignedOnly, function ($query) use ($userId) {
                $query->where(function ($assignedQuery) use ($userId) {
                    $assignedQuery->where('user_id', $userId)
                        ->orWhere(function ($unassignedQuery) {
                            $unassignedQuery->whereNull('user_id')
                                ->where('is_last_message_by_contact', 1);
                        });
                });
            })
            ->when(request()->filled('channel') && request()->input('channel') !== 'all', function ($query) {
                $channel = request()->input('channel');

                // Legacy WhatsApp contacts often have no channel_identities row yet.
                if ($channel === MessagingChannelType::Whatsapp->value) {
                    $query->where(function ($channelQuery) use ($channel) {
                        $channelQuery
                            ->whereDoesntHave('channelIdentities')
                            ->orWhereHas('channelIdentities', function ($identityQuery) use ($channel) {
                                $identityQuery->withoutGlobalScopes()->where('channel', $channel);
                            });
                    });

                    return;
                }

                $query->whereHas('channelIdentities', function ($identityQuery) use ($channel) {
                    $identityQuery->withoutGlobalScopes()->where('channel', $channel);
                });
            });

        $inboxMode = $this->resolveInboxMode(request()->input('inbox_mode'));
        $countBase = clone $baseQuery;

        $baseQuery->when($lastmessagetime !== 'none' && $lastmessagetime !== '', function ($query) use ($lastmessagetime) {
            $query->where('last_reply_at', '>', Carbon::parse($lastmessagetime));
        });
        $this->applyInboxModeFilter($baseQuery, $inboxMode);

        $stats = (clone $baseQuery)->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN user_id = ? THEN 1 ELSE 0 END) as mine,
            SUM(CASE WHEN is_last_message_by_contact = 1 THEN 1 ELSE 0 END) as unread,
            SUM(CASE WHEN resolved_chat = 1 THEN 1 ELSE 0 END) as resolved
        ', [$userId])->first();

        $chatList = clone $baseQuery;

        if ($search_query != '' && strlen($search_query) > 3) {
            $chatList->where(function ($query) use ($search_query) {
                $query->where('name', 'like', '%'.$search_query.'%')
                    ->orWhere('phone', 'like', '%'.$search_query.'%')
                    ->orWhere('last_message', 'like', '%'.$search_query.'%');
            });
        }

        $this->applyInboxTabFilter($chatList, request()->input('filter', 'open'), $userId);

        $totalForPage = (clone $chatList)->count();
        $numberOfPages = max(1, (int) ceil($totalForPage / $pageSize));

        $contacts = $chatList
            ->select([
                'id', 'name', 'phone', 'avatar', 'last_message', 'last_reply_at',
                'is_last_message_by_contact', 'resolved_chat', 'user_id', 'country_id',
                'last_client_reply_at', 'language', 'enabled_ai_bot',
            ])
            ->with([
                'country:id,name,iso2',
                'channelIdentities:id,contact_id,channel,display_name',
                'conversations' => function ($query) {
                    $query->withoutGlobalScopes()
                        ->select(['id', 'contact_id', 'channel', 'metadata', 'last_client_reply_at'])
                        ->latest('id');
                },
            ])
            ->orderByDesc('last_reply_at')
            ->skip(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->map(fn (Contact $contact) => $this->presentInboxContact($contact));

        return response()->json([
            'data' => $contacts,
            'numberOfPages' => $numberOfPages,
            'page' => (int) $page,
            'inboxMode' => $inboxMode,
            'messageChatsCount' => $this->countInboxMode($countBase, 'messages'),
            'commentChatsCount' => $this->countInboxMode($countBase, 'comments'),
            'commentUnreadCount' => $this->countInboxMode($countBase, 'comments', unreadOnly: true),
            'totalChats' => (int) ($stats->total ?? 0),
            'myChatsCount' => (int) ($stats->mine ?? 0),
            'unreadChatsCount' => (int) ($stats->unread ?? 0),
            'newMessagesCount' => (int) ($stats->unread ?? 0),
            'resolvedChatsCount' => (int) ($stats->resolved ?? 0),
            'status' => true,
            'errMsg' => '',
        ]);
    }

    public function setLanguage(Request $request, Contact $contact)
    {
        $this->ensureContactBelongsToActiveCompany($contact);

        // Validate the request...
        $validatedData = $request->validate([
            'language' => 'required|string',
        ]);

        // Assign the contact to the user
        $contact->language = $validatedData['language'];

        if (__('No translation') == $validatedData['language']) {
            $contact->language = 'none';
        }
        $contact->save();

        return response()->json([
            'status' => true,
            'message' => 'Language set successfully',
        ]);
    }

    public function assignContact(Request $request, Contact $contact)
    {
        $this->ensureContactBelongsToActiveCompany($contact);

        // Validate the request...
        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Assign the contact to the user
        $contact->user_id = $validatedData['user_id'];
        $contact->save();

        event(new Chatlistchange($contact->id, $contact->company_id));

        return response()->json([
            'status' => true,
            'message' => 'Contact assigned successfully',
        ]);
    }

    public function chatmessages($contact)
    {
        $contactUser = Contact::withoutGlobalScopes()->find($contact);

        if (! $contactUser || ! $this->contactBelongsToActiveCompany($contactUser)) {
            abort(403);
        }

        try {
            $contactUser->is_last_message_by_contact = 0;
            $contactUser->update();
        } catch (\Exception $e) {
            //Do nothing
        }

        $limit = min((int) request()->input('limit', 50), 100);
        $beforeId = request()->input('before_id');

        $messages = Message::withoutGlobalScopes()
            ->where('contact_id', $contactUser->id)
            ->where('company_id', $this->getCompany()->id)
            ->where('status', '>', 0)
            ->when($beforeId, fn ($query) => $query->where('id', '<', $beforeId))
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get([
                'id', 'contact_id', 'company_id', 'value', 'original_message',
                'header_text', 'header_image', 'header_document', 'header_video',
                'header_audio', 'header_location', 'footer_text', 'buttons', 'components',
                'is_message_by_contact', 'is_campign_messages', 'is_note', 'is_call_brief',
                'call_brief_payload', 'sender_name', 'error', 'status', 'created_at', 'extra',
            ]);

        return response()->json([
            'data' => $messages,
            'has_more' => $messages->count() === $limit,
            'status' => true,
            'errMsg' => '',
        ]);
    }

    public function sendNoteToContact(Request $request, Contact $contact)
    {
        $this->ensureContactBelongsToActiveCompany($contact);

        /**
         * Contact id
         * Message
         */
        $validator = Validator::make($request->all(), [
            'note' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errorsText = $validator->errors()->all();
            // Convert the array of error messages to a single string
            $errorsString = implode("\n", $errorsText);

            return response()->json([
                'status' => false,
                'errMsg' => $errorsString,
            ]);
        } else {
            // OK, we can send the note
            $note = $request->input('note');
            $contact->addNote($note);

            return response()->json([
                'status' => true,
                'message' => 'Note added successfully',
            ]);
        }
    }

    public function sendMessageToContact(Request $request, Contact $contact)
    {
        $this->ensureContactBelongsToActiveCompany($contact);

        /**
         * Contact id
         * Message
         */

        // Create a validator instance
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:500',
            'reply_mode' => 'nullable|in:public,private,direct',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            $errorsText = $validator->errors()->all();
            // Convert the array of error messages to a single string
            $errorsString = implode("\n", $errorsText);

            return response()->json([
                'status' => false,
                'errMsg' => $errorsString,
            ]);
        } elseif (strip_tags($request->message) != $request->message) {
            return response()->json([
                'status' => false,
                'errMsg' => __('Only text is allowed!'),
            ]);
        } else {
            //OK, we can send the message
            $extra = $this->outboundCommentReplyExtra($contact, $request->input('reply_mode'));
            $messageSend = $contact->sendMessage(strip_tags($request->message), false, false, 'TEXT', null, $extra);

            return response()->json([
                'message' => $messageSend,
                'messagetime' => $messageSend->created_at->toIso8601String(),
                'status' => true,
                'errMsg' => '',
            ]);
        }

    }

    public function sendImageMessageToContact(Request $request, Contact $contact)
    {
        $this->ensureContactBelongsToActiveCompany($contact);

        $request->validate([
            'image' => 'required|file|max:16384|mimes:jpeg,jpg,png,gif,webp,mp4,3gp,aac,amr,mp3,ogg,opus',
        ]);

        /**
         * Contact id
         * Message
         */
        $imageUrl = '';
        if (config('settings.use_s3_as_storage', false)) {
            //S3 - store per company
            $path = $request->image->storePublicly('uploads/media/send/'.$contact->company_id, 's3');
            $imageUrl = Storage::disk('s3')->url($path);
        } else {
            //Regular
            $path = $request->image->store(null, 'public_media_upload');
            $imageUrl = Storage::disk('public_media_upload')->url($path);
        }

        $fileType = $request->file('image')->getMimeType();
        if (str_contains($fileType, 'image')) {
            // It's an image
            $messageType = 'IMAGE';
        } elseif (str_contains($fileType, 'video')) {
            // It's a video
            $messageType = 'VIDEO';
        } elseif (str_contains($fileType, 'audio')) {
            // It's audio
            $messageType = 'VIDEO';
        } else {
            // Handle other types or show an error message
            $messageType = 'IMAGE';
        }

        $messageSend = $contact->sendMessage($imageUrl, false, false, $messageType);

        return response()->json([
            'message' => $messageSend,
            'messagetime' => $messageSend->created_at->toIso8601String(),
            'status' => true,
            'errMsg' => '',
        ]);
    }

    public function sendDocumentMessageToContact(Request $request, Contact $contact)
    {
        $this->ensureContactBelongsToActiveCompany($contact);

        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,jpeg,jpg,png,gif,webp',
        ]);

        /**
         * Contact id
         * Message
         */
        $fileURL = '';
        if (config('settings.use_s3_as_storage', false)) {
            //S3 - store per company
            $path = $request->file->storePublicly('uploads/media/send/'.$contact->company_id, 's3');
            $fileURL = Storage::disk('s3')->url($path);
        } else {
            //Regular
            $path = $request->file->store(null, 'public_media_upload');
            $fileURL = Storage::disk('public_media_upload')->url($path);
        }

        $messageSend = $contact->sendMessage($fileURL, false, false, 'DOCUMENT');

        return response()->json([
            'message' => $messageSend,
            'messagetime' => $messageSend->created_at->toIso8601String(),
            'status' => true,
            'errMsg' => '',
        ]);
    }

    protected function applyInboxTabFilter($query, ?string $filter, int $userId): void
    {
        $filter = $filter ?? 'open';

        if (in_array($filter, ['resolved', 'closed'], true)) {
            $query->where('resolved_chat', 1);

            return;
        }

        if ($filter === 'mine') {
            $query->where('user_id', $userId)->where('resolved_chat', 0);

            return;
        }

        if ($filter === 'new') {
            $query->where('is_last_message_by_contact', 1)->where('resolved_chat', 0);

            return;
        }

        $query->where('resolved_chat', 0);
    }

    public function updateChatStatus(Request $request, Contact $contact)
    {
        $this->ensureContactBelongsToActiveCompany($contact);

        // Update the resolved_chat status
        $contact->resolved_chat = 1;
        $contact->save();

        event(new Chatlistchange($contact->id, $contact->company_id));

        return response()->json([
            'status' => true,
            'message' => 'Chat status updated successfully',
        ]);
    }

    public function reopenChat(Request $request, Contact $contact)
    {
        $this->ensureContactBelongsToActiveCompany($contact);

        // Update the resolved_chat status to reopen
        $contact->resolved_chat = 0;
        $contact->save();

        event(new Chatlistchange($contact->id, $contact->company_id));

        return response()->json([
            'status' => true,
            'message' => 'Chat reopened successfully',
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function resolveEnabledChannels($company, User $user, PlanEntitlementResolver $entitlements): array
    {
        $channels = [
            ['value' => 'all', 'label' => __('All channels')],
            ['value' => MessagingChannelType::Whatsapp->value, 'label' => __('WhatsApp')],
        ];

        if ($entitlements->userHasCapability($user, 'inbox_instagram')) {
            $hasInstagram = ChannelConnection::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('channel', MessagingChannelType::Instagram->value)
                ->where('status', 'connected')
                ->exists();

            if ($hasInstagram) {
                $channels[] = ['value' => MessagingChannelType::Instagram->value, 'label' => __('Instagram')];
            }
        }

        if ($entitlements->userHasCapability($user, 'inbox_messenger')) {
            $hasMessenger = ChannelConnection::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('channel', MessagingChannelType::Messenger->value)
                ->where('status', 'connected')
                ->exists();

            if ($hasMessenger) {
                $channels[] = ['value' => MessagingChannelType::Messenger->value, 'label' => __('Messenger')];
            }
        }

        return $channels;
    }

    protected function contactBelongsToActiveCompany(Contact $contact): bool
    {
        $company = $this->getCompany();

        return $company !== null && (int) $contact->company_id === (int) $company->id;
    }

    protected function ensureContactBelongsToActiveCompany(Contact $contact): void
    {
        if (! $this->contactBelongsToActiveCompany($contact)) {
            abort(403);
        }
    }
}
