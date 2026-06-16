<?php

namespace Modules\Wpbox\Http\Controllers;

use Akaunting\Module\Facade as Module;
use App\Http\Controllers\Controller;
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
use Modules\Wpbox\Traits\Whatsapp;

class ChatController extends Controller
{
    use Whatsapp;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if ($this->getCompany()->getConfig('whatsapp_webhook_verified', 'no') != 'yes' || $this->getCompany()->getConfig('whatsapp_settings_done', 'no') != 'yes') {
            return redirect(route('whatsapp.setup'));
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

        //Sort the sidebar modules, so that "Contact" is first ,"AI Message Style" is second, and the rest in alphabetical order
        usort($sidebarModules, function ($a, $b) {
            if ($a['name'] === 'Contact') {
                return -1;
            }
            if ($b['name'] === 'Contact') {
                return 1;
            }

            return strcmp($a['name'], $b['name']);
        });

        return view('wpbox::chat.master', [
            'company' => $this->getCompany(),
            'templates' => $templates->toArray(),
            'replies' => $replies->toArray(),
            'users' => $users->toArray(),
            'languages' => $languages,
            'fetcherModules' => $fetcherModules,
            'sidebarModules' => $sidebarModules,
            'initialContactId' => $this->resolveInitialChatContactId(request()),
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
            ->when($agentAssignedOnly, fn ($query) => $query->where('user_id', $userId))
            ->when($lastmessagetime !== 'none' && $lastmessagetime !== '', function ($query) use ($lastmessagetime) {
                $query->where('last_reply_at', '>', $lastmessagetime);
            });

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

        if (request()->has('filter') && request()->filter == 'resolved') {
            $chatList->where('resolved_chat', 1);
        } elseif (request()->input('filter') !== 'all') {
            $chatList->where('resolved_chat', 0);
        }

        $totalForPage = (clone $chatList)->count();
        $numberOfPages = max(1, (int) ceil($totalForPage / $pageSize));

        $contacts = $chatList
            ->select([
                'id', 'name', 'phone', 'avatar', 'last_message', 'last_reply_at',
                'is_last_message_by_contact', 'resolved_chat', 'user_id', 'country_id',
                'last_client_reply_at', 'language', 'enabled_ai_bot',
            ])
            ->with('country:id,name,iso2')
            ->orderByDesc('last_reply_at')
            ->skip(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        return response()->json([
            'data' => $contacts,
            'numberOfPages' => $numberOfPages,
            'page' => (int) $page,
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
                'call_brief_payload', 'sender_name', 'error', 'status', 'created_at',
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
            $messageSend = $contact->sendMessage(strip_tags($request->message), false);

            return response()->json([
                'message' => $messageSend,
                'messagetime' => $messageSend->created_at->format('Y-m-d H:i:s'),
                'status' => true,
                'errMsg' => '',
            ]);
        }

    }

    public function sendImageMessageToContact(Request $request, Contact $contact)
    {
        $this->ensureContactBelongsToActiveCompany($contact);

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
            'messagetime' => $messageSend->created_at->format('Y-m-d H:i:s'),
            'status' => true,
            'errMsg' => '',
        ]);
    }

    public function sendDocumentMessageToContact(Request $request, Contact $contact)
    {
        $this->ensureContactBelongsToActiveCompany($contact);

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
            'messagetime' => $messageSend->created_at->format('Y-m-d H:i:s'),
            'status' => true,
            'errMsg' => '',
        ]);
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
