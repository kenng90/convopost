<?php

namespace Modules\Wpbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Campaign\ApiCampaignService;
use App\Services\Campaign\CampaignDispatchService;
use App\Services\Campaign\CampaignTemplateVariablesParser;
use App\Services\Security\ApiTokenAuthenticator;
use App\Services\Security\SafeRemoteUrl;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Contacts\Models\Field;
use Modules\Contacts\Models\Group;
use Modules\Wpbox\Events\AgentReplies;
use Modules\Wpbox\Jobs\SendMessage;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Models\Reply;
use Modules\Wpbox\Models\Template;
use Modules\Wpbox\Traits\Contacts;
use Modules\Wpbox\Traits\InboxModes;
use Modules\Wpbox\Traits\Whatsapp;

class APIController extends Controller
{
    use Contacts;
    use InboxModes;
    use Whatsapp;

    public function sendListMessageToPhoneNumber(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            //Company
            $company = $this->getCompany();

            //Make or get the contact
            $contact = $this->getOrMakeContact($request->phone, $company, $request->phone);

            Log::info($contact);

            $createData = [
                'contact_id' => $contact->id,
                'company_id' => $contact->company_id,
                'value' => $request->message,
                'header_text' => (string) ($request->input('header') ?? ''),
                'footer_text' => (string) ($request->input('footer') ?? ''),
                'buttons' => json_encode($request->action),
                'is_message_by_contact' => false,
                'is_campign_messages' => false,
                'status' => 1,
                'fb_message_id' => null,
            ];

            $messageToBeSend = Message::create($createData);

            $messageToBeSend->save();

            Log::info($messageToBeSend);

            $contact->last_support_reply_at = now();
            $contact->is_last_message_by_contact = false;
            $contact->sendMessageToWhatsApp($messageToBeSend, $contact);

            //Find the user of the company
            $companyUser = $company->user;
            event(new AgentReplies($companyUser, $messageToBeSend, $contact));

            $contact->last_message = $contact->trimString($request->message, 40);
            $contact->update();

            return $messageToBeSend;
        }, [
            'token' => 'required',
            'phone' => 'required',
            'message' => 'required',
            'action' => 'required',
        ]);
    }

    //Send message to phone number
    public function sendMessageToPhoneNumber(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            //Company
            $company = $this->getCompany();

            // Prefer contact_id so Instagram/Messenger (empty phone) work from mobile.
            $contact = $this->resolveContactFromRequest($request, $company);
            if (! $contact) {
                return response()->json(['status' => 'error', 'message' => 'Contact not found'], 404);
            }

            //If request has buttons
            if ($request->has('buttons') || $request->has('header') || $request->has('footer')) {

                $header_text = '';
                if ($request->has('header')) {
                    $header_text = $request->header;
                }
                $footer_text = '';
                if ($request->has('footer')) {
                    $footer_text = $request->footer;
                }

                // Make Replay object
                $replay = new Reply([
                    'trigger' => 'none',
                    'type' => 1,
                    'text' => $request->message,
                    'company_id' => $company->id,
                    'header' => $header_text,
                    'footer' => $footer_text,
                ]);

                //In the $reply we have button1, button1_id. Assign them from $buttons
                if ($request->has('buttons')) {
                    foreach ($request->buttons as $key => $button) {
                        if ($key < 3) {
                            $replay['button'.($key + 1)] = $button['title'];
                            $replay['button'.($key + 1).'_id'] = $button['id'];
                        }
                    }
                }

                $message = $contact->sendReply($replay);

            } elseif ($request->hasFile('image')) {
                $request->validate([
                    'image' => 'required|file|max:16384|mimes:jpeg,jpg,png,gif,webp,mp4,3gp,aac,amr,mp3,ogg,opus,pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
                ]);
                //Image message
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

                $message = $contact->sendMessage($imageUrl, false, false, $messageType);

            } elseif ($request->filled('media_url')) {
                //Media URL message - fetch and store the media
                $imageUrl = '';

                try {
                    $mediaContent = app(SafeRemoteUrl::class)->fetch((string) $request->media_url);

                    // Get file extension from URL or use default
                    $urlInfo = pathinfo(parse_url($request->media_url, PHP_URL_PATH));
                    $extension = isset($urlInfo['extension']) ? $urlInfo['extension'] : 'jpg';

                    // Generate unique filename
                    $filename = 'media_'.time().'_'.uniqid().'.'.$extension;

                    if (config('settings.use_s3_as_storage', false)) {
                        //S3 - store per company
                        $path = 'uploads/media/send/'.$contact->company_id.'/'.$filename;
                        Storage::disk('s3')->put($path, $mediaContent);
                        $imageUrl = Storage::disk('s3')->url($path);
                    } else {
                        //Regular storage
                        $path = $filename;
                        Storage::disk('public_media_upload')->put($path, $mediaContent);
                        $imageUrl = Storage::disk('public_media_upload')->url($path);
                    }

                    // Get file info to determine type
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $fileType = finfo_buffer($finfo, $mediaContent);
                    finfo_close($finfo);

                    if (str_contains($fileType, 'image')) {
                        // It's an image
                        $messageType = 'IMAGE';
                    } elseif (str_contains($fileType, 'video')) {
                        // It's a video
                        $messageType = 'VIDEO';
                    } elseif (str_contains($fileType, 'audio')) {
                        // It's audio
                        $messageType = 'AUDIO';
                    } else {
                        // Handle other types as document
                        $messageType = 'DOCUMENT';
                    }

                    $message = $contact->sendMessage($imageUrl, false, false, $messageType);

                } catch (\InvalidArgumentException) {
                    return response()->json(['status' => 'error', 'message' => 'Media URL is not allowed']);
                } catch (\Exception) {
                    return response()->json(['status' => 'error', 'message' => 'Failed to process media URL']);
                }

            } else {
                $extra = $this->outboundCommentReplyExtra($contact, $request->input('reply_mode'));
                $message = $contact->sendMessage($request->message, false, false, 'TEXT', null, $extra);
            }

            return response()->json(['status' => 'success', 'message_id' => $message->id, 'message_wamid' => $message->fb_message_id]);

        }, [
            'token' => 'required',
            'contact_id' => 'required_without:phone',
            'phone' => 'required_without:contact_id',
            //'message' => 'required',
        ]);
    }

    //Send Template     message to phone number
    public function sendTemplateMessageToPhoneNumber(Request $request)
    {

        return $this->authenticate($request, function ($request) {
            //Company
            $company = $this->getCompany();

            //Make or get the contact
            $contact = $this->getOrMakeContact($request->phone, $company, $request->phone);

            //Find the template based on the provided id (with 5 day cache)
            $templateCacheKey = "template_{$company->id}_{$request->template_name}_{$request->template_language}";
            $template = Cache::remember($templateCacheKey, 60 * 60 * 24 * 5, function () use ($company, $request) {
                return Template::where('company_id', $company->id)->where('name', $request->template_name)->where('language', $request->template_language)->first();
            });

            if (! $template) {
                return response()->json(['status' => 'error', 'message' => 'Invalid template']);
            }

            $campaign = Campaign::create([
                'company_id' => $company->id,
                'name' => 'api_message_'.now(),
                'timestamp_for_delivery' => null,
                'variables' => '',
                'variables_match' => '',
                'template_id' => $template->id,
                'group_id' => null,
                'contact_id' => $contact->id,
                'total_contacts' => Contact::where('company_id', $company->id)->count(),
            ]);

            $bodyText = 'API Message';
            $header_text = '';
            $header_image = '';
            $header_video = '';
            $header_audio = '';
            $header_document = '';
            try {
                foreach (json_decode($template->components, true) as $component) {
                    if ($component['type'] == 'BODY') {
                        $bodyText = $component['text'];
                        foreach ($request->components as $key => $receivedComponent) {
                            if ($receivedComponent['type'] == 'body') {
                                foreach ($receivedComponent['parameters'] as $keyp => $parameter) {
                                    $bodyText = str_replace('{{'.($keyp + 1).'}}', $parameter['text'], $bodyText);
                                }
                            }
                        }
                    }
                    if ($component['type'] == 'HEADER' && $component['format'] == 'TEXT') {
                        $header_text = $component['text'];
                        foreach ($request->components as $key => $receivedComponent) {
                            if ($receivedComponent['type'] == 'header') {
                                foreach ($receivedComponent['parameters'] as $keyp => $parameter) {
                                    $bodyText = str_replace('{{'.($keyp + 1).'}}', $parameter['text'], $bodyText);
                                }
                            }
                        }
                    }

                    // Handle header media types
                    if ($component['type'] == 'HEADER') {
                        foreach ($request->components as $key => $receivedComponent) {
                            if ($receivedComponent['type'] == 'header') {
                                foreach ($receivedComponent['parameters'] as $keyp => $parameter) {
                                    if (isset($parameter['type'])) {
                                        switch ($parameter['type']) {
                                            case 'image':
                                                $header_image = $parameter['image']['link'];
                                                break;
                                            case 'video':
                                                $header_video = $parameter['video']['link'];
                                                break;
                                            case 'document':
                                                $header_document = $parameter['document']['link'];
                                                break;
                                            case 'audio':
                                                $header_audio = $parameter['audio']['link'];
                                                break;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    //Video header

                }
            } catch (\Throwable $th) {
                //throw $th;
            }

            $dataForMessage = [
                'contact_id' => $contact->id,
                'company_id' => $contact->company_id,
                'value' => $bodyText,
                'header_image' => $header_image,
                'header_video' => $header_video,
                'header_audio' => $header_audio,
                'header_document' => $header_document,
                'footer_text' => '',
                'buttons' => '',
                'header_text' => $header_text,
                'is_message_by_contact' => false,
                'is_campign_messages' => true,
                'status' => 0,
                'created_at' => now(),
                'scchuduled_at' => Carbon::now(),
                'components' => json_encode($request->components),
                'campaign_id' => $campaign->id,
            ];

            //Create a message on the contact
            $message = Message::create($dataForMessage);

            //Add logging before dispatch
            Log::info('Attempting to dispatch SendMessage job', [
                'message_id' => $message->id,
                'contact_id' => $contact->id,
                'company_id' => $company->id,
            ]);

            try {
                //Instead of sending it here,  queuing it to be sent
                dispatch(new SendMessage($message));

                Log::info('Successfully dispatched SendMessage job', [
                    'message_id' => $message->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to dispatch SendMessage job', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }

            return response()->json(['status' => 'success', 'message_id' => $message->id, 'message_wamid' => $message->fb_message_id]);

        },
            [
                'token' => 'required',
                'phone' => 'required',
                'template_name' => 'required',
                'template_language' => 'required',
                'components' => 'array',
            ]);

    }

    //Get ot make contact  //Last Update by Brij 24Jun
    public function makeContact($name, $phone, $company)
    {
        $contact = Contact::where('company_id', $company->id)->where('phone', $phone)->first();
        if (! $contact) {
            $contact = Contact::create([
                'name' => $name ?? $phone,
                'phone' => $phone,
                'company_id' => $company->id,
            ]);
        }

        return $contact;
    }

    //Get templates
    public function getTemplates(Request $request)
    {

        return $this->authenticate($request, function ($request) {
            //Company
            $company = $this->getCompany();
            //Find the template based on the provided id
            $templates = Template::where('company_id', $company->id)->get();

            return response()->json(['status' => 'success', 'templates' => $templates]);
        });
    }

    //Send Campaign via API
    public function sendCampaignMessageToPhoneNumber(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $request->validate([
                'phone' => 'required',
                'campaign_id' => 'required_without:campaing_id',
                'campaing_id' => 'nullable',
            ]);

            $campaignId = $request->input('campaign_id', $request->input('campaing_id'));

            if (! $campaignId) {
                return response()->json(['status' => 'error', 'message' => 'campaign_id is required'], 422);
            }

            $company = $this->getCompany();
            $campaign = Campaign::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->find($campaignId);

            if (! $campaign) {
                return response()->json(['status' => 'error', 'message' => 'API campaign not found'], 404);
            }

            $apiCampaigns = app(ApiCampaignService::class);

            try {
                $apiCampaigns->assertSendable($campaign);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getStatusCode());
            }

            $data = $request->input('data', []);
            if (! is_array($data)) {
                $data = [];
            }

            $missing = $apiCampaigns->missingApiVariables($campaign, $data);
            if ($missing !== []) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Missing required API variable paths in data.',
                    'missing' => $missing,
                ], 422);
            }

            $contact = $this->getOrMakeContact($request->phone, $company, $request->input('name', $request->phone));
            $contact->extra_value = $data;

            $message = $campaign->makeMessages(null, $contact);

            if (! $message) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Could not build campaign message for this contact.',
                ], 422);
            }

            // Messages are queued (status pending) and sent by the campaign dispatcher.
            // Optional immediate send when the queue/scheduler is not relied upon:
            if ($request->boolean('send_now')) {
                app(CampaignDispatchService::class)->sendSynchronously($message);
                $message->refresh();
            }

            return response()->json([
                'status' => 'success',
                'message_id' => $message->id,
                'message_wamid' => $message->fb_message_id,
                'queued' => (int) $message->status === Message::STATUS_PENDING,
                'note' => (int) $message->status === Message::STATUS_PENDING
                    ? 'Message queued. Ensure the scheduler runs: php artisan schedule:run'
                    : 'Message sent.',
            ]);
        }, [
            'token' => 'required',
            'phone' => 'required',
        ]);
    }

    //Get groups
    public function getGroups(Request $request)
    {

        return $this->authenticate($request, function ($request) {
            //Company
            $company = $this->getCompany();
            if ($request->has('showContacts') && $request->showContacts == 'yes') {
                $groups = Group::where('company_id', $company->id)->with('contacts')->get();
            } else {
                $groups = Group::where('company_id', $company->id)->get();
            }

            return response()->json(['status' => 'success', 'groups' => $groups]);

        });
    }

    public function getCampaigns(Request $request)
    {

        return $this->authenticate($request, function ($request) {
            //Company
            $company = $this->getCompany();

            if ($request->has('type')) {
                if ($request->type == 'bot') {
                    $items = Campaign::where('company_id', $company->id)->where('is_bot', true)->get();
                } elseif ($request->type == 'api') {
                    $items = Campaign::where('company_id', $company->id)->where('is_api', true)->get();
                } elseif ($request->type == 'regular') {
                    $items = Campaign::where('company_id', $company->id)->where('is_api', false)->where('is_bot', false)->get();
                }
            } else {
                $items = Campaign::where('company_id', $company->id)->get();
            }

            return response()->json(['status' => 'success', 'items' => $items]);

        });
    }

    public function getContacts(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            [$contacts, $meta] = app(\App\Http\Controllers\Api\V1\ContactsController::class)
                ->paginateContacts($request, $company);

            return response()->json([
                'status' => 'success',
                'contacts' => $contacts,
                'meta' => $meta,
            ]);
        });
    }

    public function getConversations(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            //Company
            $company = $this->getCompany();
            session(['company_id' => $company->id]);
            $inboxMode = $this->resolveInboxMode($request->input('inbox_mode'));

            $baseQuery = Contact::where('has_chat', 1)
                ->where('company_id', $company->id);

            $countBase = clone $baseQuery;
            $this->applyInboxModeFilter($baseQuery, $inboxMode);

            $chatList = $baseQuery
                ->with([
                    'channelIdentities' => function ($query) {
                        $query->withoutGlobalScopes()->select('id', 'contact_id', 'channel', 'display_name');
                    },
                    'conversations' => function ($query) {
                        $query->withoutGlobalScopes()
                            ->select(['id', 'contact_id', 'channel', 'metadata', 'last_client_reply_at'])
                            ->latest('id');
                    },
                ])
                ->orderBy('last_reply_at', 'DESC')
                ->limit(150)
                ->get()
                ->map(fn (Contact $contact) => $this->presentInboxContact($contact));

            return response()->json([
                'data' => $chatList,
                'company_id' => $company->id,
                'inboxMode' => $inboxMode,
                'messageChatsCount' => $this->countInboxMode($countBase, 'messages'),
                'commentChatsCount' => $this->countInboxMode($countBase, 'comments'),
                'commentUnreadCount' => $this->countInboxMode($countBase, 'comments', unreadOnly: true),
                'status' => true,
                'errMsg' => '',
            ]);

        });
    }

    public function getMessages(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $contact = Contact::where('id', $request->contact_id)
                ->where('company_id', $company->id)
                ->first();

            if (! $contact) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Contact not found',
                ], 404);
            }

            // Opening a chat clears the unread flag (parity with web chatmessages)
            try {
                $contact->is_last_message_by_contact = 0;
                $contact->update();
            } catch (\Exception $e) {
                // ignore
            }

            $limit = min((int) $request->input('limit', 50), 100);
            $beforeId = $request->input('before_id');

            $messages = Message::where('contact_id', $contact->id)
                ->where('company_id', $company->id)
                ->where('status', '>', 0)
                ->when($beforeId, fn ($query) => $query->where('id', '<', $beforeId))
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get();

            $contact->load([
                'channelIdentities' => function ($query) {
                    $query->withoutGlobalScopes()->select('id', 'contact_id', 'channel', 'display_name');
                },
                'conversations' => function ($query) {
                    $query->withoutGlobalScopes()
                        ->select(['id', 'contact_id', 'channel', 'metadata', 'last_client_reply_at'])
                        ->latest('id');
                },
            ]);
            $this->presentInboxContact($contact);

            return response()->json([
                'data' => $messages,
                'has_more' => $messages->count() === $limit,
                'comment_reply' => $contact->comment_reply,
                'channel' => $contact->channel,
                'status' => true,
                'errMsg' => '',
            ]);

        }, [
            'token' => 'required',
            'contact_id' => 'required',
        ]);
    }

    public function updateContact(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            if (! $company) {
                return response()->json(['status' => 'error', 'message' => 'Contact not found'], 404);
            }

            $contact = Contact::query()
                ->where('company_id', $company->id)
                ->findOrFail($request->id);

            $contact->update($request->only([
                'name',
                'email',
                'phone',
            ]));

            return response()->json(['status' => 'success', 'contact' => $contact]);
        }, [
            'id' => 'required',
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
        ]);
    }

    //Send Template     message to phone number
    public function contactApiMake(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            //Company
            $company = $this->getCompany();

            $contact = $this->makeContact($request->name, $request->phone, $company);

            //Update the contact
            $contact->update(['name' => $request->name]);

            //If there is a email
            if ($request->has('email')) {
                $contact->update(['email' => $request->email]);
            }

            //If request has groups
            if ($request->has('groups')) {
                // Attaching groups to the contact
                $contact->groups()->sync([]);
                // Groups are passed as string with comma
                $groups = explode(',', $request->groups);

                // Remove empty values from the array
                $groups = array_filter($groups);

                //Convert each group name into a group id
                $groupIds = [];
                foreach ($groups as $groupName) {
                    $groupId = Group::where('name', $groupName)->where('company_id', $company->id)->first();
                    if ($groupId) {
                        $groupIds[] = $groupId->id;
                    } else {
                        //Create a new group
                        $groupId = Group::create([
                            'name' => $groupName,
                            'company_id' => $company->id,
                        ]);
                        $groupIds[] = $groupId->id;
                    }
                }

                $contact->groups()->attach($groupIds);
            }

            //If request has custom fields
            if ($request->has('custom')) {
                $contact->fields()->sync([]);
                foreach ($request->custom as $key => $value) {
                    if ($value) {
                        //Find the custom field id
                        $fieldId = Field::where('name', $key)->where('company_id', $company->id)->first();
                        if ($fieldId) {
                            $contact->fields()->attach($fieldId->id, ['value' => $value]);
                        } else {
                            //Create a new custom field
                            $field = Field::create([
                                'name' => $key,
                                'company_id' => $company->id,
                            ]);
                            $contact->fields()->attach($field->id, ['value' => $value]);
                        }
                    }
                }

            }
            $contact->update();
            $contact->load('groups', 'fields');

            return response()->json([
                'status' => 'success',
                'contact' => $contact,
            ]);

        }, [
            'token' => 'required',
            'phone' => 'required',
        ]);
    }

    public function getCustomFields(Request $request)
    {

    }

    public function getSingleContact(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            //Company
            $company = $this->getCompany();

            if ($request->has('contact_id')) {
                $contact = Contact::where('id', $request->contact_id)
                    ->where('company_id', $company->id)
                    ->firstOrFail();
            } elseif ($request->has('phone')) {
                $contact = Contact::where('phone', $request->phone)
                    ->where('company_id', $company->id)
                    ->firstOrFail();
            }

            return response()->json(['status' => 'success', 'contact' => $contact]);
        }, [
            'token' => 'required',
            'contact_id' => 'required_without:phone',
            'phone' => 'required_without:contact_id',
        ]);
    }

    /**
     * Resolve an existing contact for mobile/API sends.
     * contact_id is required for Instagram/Messenger (contacts often have empty phone).
     */
    private function resolveContactFromRequest(Request $request, $company): ?Contact
    {
        if ($request->filled('contact_id')) {
            return Contact::where('id', $request->contact_id)
                ->where('company_id', $company->id)
                ->first();
        }

        if ($request->filled('phone')) {
            return $this->getOrMakeContact($request->phone, $company, $request->phone);
        }

        return null;
    }

    private function authenticate(Request $request, Closure $next, $rules = ['token' => 'required'])
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        /*if (config('settings.is_demo')) {
            return response()->json([
                'status' => 'error',
                'errors' => "API is disabled in demo"
            ], 400);
        }*/

        $auth = app(ApiTokenAuthenticator::class)->authenticate($request);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        return $next($request);
    }

    public function info()
    {
        $token = PersonalAccessToken::where('tokenable_id', auth()->user()->id)->where('tokenable_type', 'App\Models\User')->first();
        $company = $this->getCompany();

        if (! $token || $company->getConfig('whatsapp_webhook_verified', 'no') != 'yes' || $company->getConfig('whatsapp_settings_done', 'no') != 'yes') {
            return redirect(route('whatsapp.setup'));
        }

        //Get old config
        $planText = $company->getConfig('plain_token', '');

        return view('wpbox::api.info', ['token' => $planText, 'company' => $company]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->ownerAndStaffOnly();

        $items = Campaign::query()
            ->with('template')
            ->where('is_api', true)
            ->orderByDesc('id')
            ->get();

        $setup = [
            'usefilter' => null,
            'title' => __('API Campaigns'),
            'action_link' => route('wpbox.api.create'),
            'action_name' => __('New API Campaign'),
            'action_link2' => route('api.info'),
            'action_name2' => __('API Info'),
            'action_link3' => route('campaigns.integrations'),
            'action_name3' => __('Integrations hub'),
            'items' => $items,
            'item_names' => __('API Campaigns'),
            'webroute_path' => 'campaigns.',
            'fields' => [],
            'filterFields' => [],
            'custom_table' => true,
            'parameter_name' => 'campaigns',
            'parameters' => count($_GET) != 0,
            'hidePaging' => true,
        ];

        return view('wpbox::api.index', [
            'setup' => $setup,
            'sendEndpoint' => rtrim(config('app.url'), '/').'/api/wpbox/sendcampaigns',
        ]);
    }

    public function create(Request $request)
    {
        $this->ownerAndStaffOnly();

        return $this->renderApiCampaignForm($request);
    }

    public function edit(Request $request, Campaign $campaign)
    {
        $this->ownerAndStaffOnly();
        abort_unless($campaign->is_api, 404);

        return $this->renderApiCampaignForm($request, $campaign);
    }

    public function store(Request $request, ApiCampaignService $apiCampaigns)
    {
        $this->ownerAndStaffOnly();

        $campaign = $apiCampaigns->create(
            $this->getCompany(),
            $apiCampaigns->payloadFromRequest($request)
        );

        return redirect()
            ->route('campaigns.show', $campaign)
            ->withStatus(__('API campaign created. Use campaign ID :id to trigger it.', ['id' => $campaign->id]));
    }

    public function update(Request $request, Campaign $campaign, ApiCampaignService $apiCampaigns)
    {
        $this->ownerAndStaffOnly();
        abort_unless($campaign->is_api, 404);

        $apiCampaigns->update($campaign, $apiCampaigns->payloadFromRequest($request));

        return redirect()
            ->route('campaigns.show', $campaign)
            ->withStatus(__('API campaign updated.'));
    }

    public function toggle(Campaign $campaign, ApiCampaignService $apiCampaigns)
    {
        $this->ownerAndStaffOnly();
        abort_unless($campaign->is_api, 404);

        $campaign = $apiCampaigns->toggleActive($campaign);
        $message = $campaign->is_active
            ? __('API campaign activated.')
            : __('API campaign deactivated.');

        return redirect()->route('wpbox.api.index')->withStatus($message);
    }

    public function clone(Campaign $campaign, ApiCampaignService $apiCampaigns)
    {
        $this->ownerAndStaffOnly();
        abort_unless($campaign->is_api, 404);

        $clone = $apiCampaigns->clone($campaign);

        return redirect()
            ->route('wpbox.api.edit', $clone)
            ->withStatus(__('API campaign cloned. Review and save.'));
    }

    /**
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    private function renderApiCampaignForm(Request $request, ?Campaign $campaign = null)
    {
        $templates = Template::where('status', 'APPROVED')
            ->get()
            ->mapWithKeys(fn (Template $template) => [$template->id => $template->name.' - '.$template->language])
            ->all();

        if ($templates === []) {
            try {
                $this->loadTemplatesFromWhatsApp();
                $templates = Template::where('status', 'APPROVED')
                    ->get()
                    ->mapWithKeys(fn (Template $template) => [$template->id => $template->name.' - '.$template->language])
                    ->all();
            } catch (\Throwable $th) {
            }
        }

        if ($templates === []) {
            return redirect()->route('templates.index')
                ->withStatus(__('Please add a template first. Or wait some to be approved'));
        }

        $templateId = $request->input('template_id', $campaign?->template_id);
        $selectedTemplate = $templateId
            ? Template::withoutGlobalScope(\App\Scopes\CompanyScope::class)->find($templateId)
            : null;

        $variables = $selectedTemplate
            ? app(CampaignTemplateVariablesParser::class)->parse($selectedTemplate)
            : null;

        $contactFields = [
            -3 => __('Use API defined value'),
            -2 => __('Use manually defined value'),
            -1 => __('Contact name'),
            0 => __('Contact phone'),
        ];
        foreach (Field::pluck('name', 'id') as $key => $value) {
            $contactFields[$key] = $value;
        }

        $paramvalues = $request->input('paramvalues', json_decode($campaign?->variables ?? '[]', true) ?? []);
        $parammatch = $request->input('parammatch', json_decode($campaign?->variables_match ?? '[]', true) ?? []);

        return view('wpbox::api.create', [
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
            'selectedTemplateComponents' => $selectedTemplate ? json_decode($selectedTemplate->components, true) : null,
            'variables' => $variables,
            'contactFields' => $contactFields,
            'campaign' => $campaign,
            'paramvalues' => $paramvalues,
            'parammatch' => $parammatch,
            'isBot' => false,
            'isAPI' => true,
            'isReminder' => false,
            'selectedContacts' => 0,
            'formAction' => $campaign
                ? route('wpbox.api.update', $campaign)
                : route('wpbox.api.store'),
            'formMethod' => $campaign ? 'PUT' : 'POST',
        ]);
    }

    public function updateAIBot(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            // Company
            $company = $this->getCompany();

            // Validate request
            $validator = Validator::make($request->all(), [
                'id' => 'required',
                'enabled_ai_bot' => 'required|in:0,1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Find the contact
            $contact = Contact::where('id', $request->id)
                ->where('company_id', $company->id)
                ->first();

            if (! $contact) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Contact not found',
                ], 404);
            }

            // Update the AI bot status
            $contact->update([
                'enabled_ai_bot' => $request->enabled_ai_bot,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'AI Bot status updated successfully',
            ]);
        }, [
            'token' => 'required',
        ]);
    }

    public function getContactGroupsAndCustomFields(Contact $contact)
    {

        // Get the contact's groups with their details
        $groups = $contact->groups()->get();
        $customFields = $contact->fields->toArray();
        foreach ($customFields as $key => $fieldWithPivot) {
            $customFields[$key]['value'] = $fieldWithPivot['pivot']['value'];
        }

        return response()->json([
            'groups' => $groups,
            'customFields' => $customFields,
            'latestFormSubmission' => $this->latestFormSubmissionForContact($contact),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function latestFormSubmissionForContact(Contact $contact): ?array
    {
        $response = \App\Models\WhatsappFlowResponse::query()
            ->with('whatsappFlow:id,name,meta_flow_id')
            ->where('contact_id', $contact->id)
            ->where('company_id', $contact->company_id)
            ->latest('completed_at')
            ->latest('id')
            ->first();

        if (! $response) {
            return null;
        }

        $service = app(\App\Services\WhatsappFlowResponseService::class);
        $answers = $service->enrichResponsesFlat($response->responses ?? [], $response->whatsappFlow);

        return [
            'id' => $response->id,
            'status' => $response->status,
            'form_name' => $response->whatsappFlow?->name,
            'form_id' => $response->whatsapp_flow_id,
            'flow_id' => $response->flow_id,
            'completed_at' => optional($response->completed_at)?->toIso8601String(),
            'answers' => array_slice($answers, 0, 12),
            'responses_url' => route('whatsapp-flows.responses', ['flow' => $response->whatsapp_flow_id]),
            'automation_url' => $response->flow_id
                ? route('flowmaker.edit', $response->flow_id)
                : null,
        ];
    }

    public function getNotes(Contact $contact)
    {
        // Get all notes for the contact
        $notes = $contact->notes()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $notes,
        ]);
    }

    public function me(Request $request)
    {
        //return response()->json(['status'=>'success']);
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $user = auth()->user();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $user,
                    'company_id' => $company?->id,
                    'company_name' => $company?->name,
                ],
                'user' => $user,
                'company_id' => $company?->id,
            ]);
        });
    }

    /**
     * Mobile agent inbox helpers (token auth via CheckAPIPlan).
     */
    public function markChatRead(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $contact = Contact::where('id', $request->contact_id)
                ->where('company_id', $company->id)
                ->first();

            if (! $contact) {
                return response()->json(['status' => 'error', 'message' => 'Contact not found'], 404);
            }

            $contact->is_last_message_by_contact = 0;
            $contact->save();

            return response()->json(['status' => true, 'data' => $contact]);
        }, [
            'token' => 'required',
            'contact_id' => 'required',
        ]);
    }

    public function resolveChat(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $contact = Contact::where('id', $request->contact_id)
                ->where('company_id', $company->id)
                ->first();

            if (! $contact) {
                return response()->json(['status' => 'error', 'message' => 'Contact not found'], 404);
            }

            $contact->resolved_chat = 1;
            $contact->save();
            event(new \Modules\Wpbox\Events\Chatlistchange($contact->id, $contact->company_id));

            return response()->json(['status' => true, 'data' => $contact, 'message' => 'Chat resolved']);
        }, [
            'token' => 'required',
            'contact_id' => 'required',
        ]);
    }

    public function reopenChatApi(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $contact = Contact::where('id', $request->contact_id)
                ->where('company_id', $company->id)
                ->first();

            if (! $contact) {
                return response()->json(['status' => 'error', 'message' => 'Contact not found'], 404);
            }

            $contact->resolved_chat = 0;
            $contact->save();
            event(new \Modules\Wpbox\Events\Chatlistchange($contact->id, $contact->company_id));

            return response()->json(['status' => true, 'data' => $contact, 'message' => 'Chat reopened']);
        }, [
            'token' => 'required',
            'contact_id' => 'required',
        ]);
    }

    public function assignChat(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $contact = Contact::where('id', $request->contact_id)
                ->where('company_id', $company->id)
                ->first();

            if (! $contact) {
                return response()->json(['status' => 'error', 'message' => 'Contact not found'], 404);
            }

            $ownerId = optional($company->user)->id;
            $agent = User::where('id', $request->user_id)
                ->where(function ($q) use ($company, $ownerId) {
                    $q->where('company_id', $company->id);
                    if ($ownerId) {
                        $q->orWhere('id', $ownerId);
                    }
                })
                ->first();

            if (! $agent) {
                return response()->json(['status' => 'error', 'message' => 'Agent not found'], 404);
            }

            $contact->user_id = $agent->id;
            $contact->save();
            event(new \Modules\Wpbox\Events\Chatlistchange($contact->id, $contact->company_id));

            return response()->json(['status' => true, 'data' => $contact, 'message' => 'Assigned']);
        }, [
            'token' => 'required',
            'contact_id' => 'required',
            'user_id' => 'required',
        ]);
    }

    public function sendNote(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $contact = Contact::where('id', $request->contact_id)
                ->where('company_id', $company->id)
                ->first();

            if (! $contact) {
                return response()->json(['status' => 'error', 'message' => 'Contact not found'], 404);
            }

            $note = $contact->addNote($request->note);

            return response()->json(['status' => true, 'data' => $note]);
        }, [
            'token' => 'required',
            'contact_id' => 'required',
            'note' => 'required|string',
        ]);
    }

    public function getAgents(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $ownerId = optional($company->user)->id;
            $agents = User::query()
                ->where(function ($q) use ($company, $ownerId) {
                    $q->where('company_id', $company->id);
                    if ($ownerId) {
                        $q->orWhere('id', $ownerId);
                    }
                })
                ->get(['id', 'name', 'email']);

            return response()->json(['status' => true, 'data' => $agents]);
        });
    }

    public function getQuickReplies(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $replies = Reply::where('company_id', $company->id)
                ->where('type', 1)
                ->whereNull('flow_id')
                ->orderBy('name')
                ->get(['id', 'name', 'text', 'trigger', 'type']);

            return response()->json(['status' => true, 'data' => $replies]);
        });
    }

    public function getNotesApi(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $contact = Contact::where('id', $request->contact_id)
                ->where('company_id', $company->id)
                ->first();

            if (! $contact) {
                return response()->json(['status' => 'error', 'message' => 'Contact not found'], 404);
            }

            $notes = $contact->notes()->orderBy('created_at', 'desc')->get();

            return response()->json(['status' => true, 'data' => $notes]);
        }, [
            'token' => 'required',
            'contact_id' => 'required',
        ]);
    }

    public function suggestCopilot(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $contact = Contact::where('id', $request->contact_id)
                ->where('company_id', $company->id)
                ->first();

            if (! $contact) {
                return response()->json(['status' => 'error', 'message' => 'Contact not found'], 404);
            }

            $draft = $request->input('draft');
            $copilot = app(\App\Services\Platform\AgentCopilotService::class);

            return response()->json([
                'status' => true,
                'data' => $copilot->suggest($company, $contact, $draft),
            ]);
        }, [
            'token' => 'required',
            'contact_id' => 'required',
        ]);
    }
}
