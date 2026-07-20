<?php

namespace Modules\Wpbox\Traits;

use Akaunting\Module\Facade as Module;
use App\Models\Company;
use App\Models\User;
use App\Services\Billing\CreditBillingResolver;
use App\Services\Billing\CreditCharger;
use App\Services\WhatsApp\InteractiveListLimits;
use App\Services\WhatsApp\WebhookCompanyResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Wpbox\Jobs\ReceiveUpdate;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Models\Template;

trait Whatsapp
{
    use Contacts;

    public static $facebookAPI = 'https://graph.facebook.com/v19.0/';

    private function getToken(?Company $company = null)
    {
        if ($company == null) {
            $company = $this->getCompany();
        }

        return $company->getConfig('whatsapp_permanent_access_token', '');
    }

    private function getPhoneID(?Company $company = null)
    {
        if ($company == null) {
            $company = $this->getCompany();
        }

        return $company->getConfig('whatsapp_phone_number_id', '');
    }

    private function getAccountID(?Company $company = null)
    {
        if ($company == null) {
            $company = $this->getCompany();
        }

        return $company->getConfig('whatsapp_business_account_id', '');
    }

    private function sendCampaignMessageToWhatsApp(Message $message)
    {

        Log::info('Sending campaign message to WhatsApp');
        Log::info($message);

        //We need data per company
        $company = null;
        try {
            $company = $message->campaign->company;
            $message->contact->phone;
        } catch (\Throwable $th) {
            Log::error($th);
            $message->error = 'The company or contact is not found';
            $message->status = 1;
            $message->update();
        }

        Log::info('Company found');
        Log::info($company);

        if ($company) {
            $charger = app(CreditCharger::class);
            $billingResolver = app(CreditBillingResolver::class);
            $template = $message->campaign?->template;
            $creditAction = $billingResolver->resolveCampaignTemplateAction($template);

            if (! $charger->canCharge($company, $creditAction)) {
                $message->error = $charger->insufficientCreditsMessage($creditAction);
                $message->status = 1;
                $message->update();

                return;
            }

            $url = self::$facebookAPI.$this->getPhoneID($company).'/messages';
            $accessToken = $this->getToken($company);
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ])->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $message->contact->phone, // Add recipient information
                    'type' => 'template',
                    'template' => [
                        'name' => $message->campaign->template->name,
                        'language' => [
                            'code' => $message->campaign->template->language,
                        ],
                        'components' => json_decode($message->components),
                    ],
                ]);
                Log::info($response->body());

                $statusCode = $response->status();
                $content = json_decode($response->body(), true);
                //dd($content);
                $message->created_at = now();
                if (isset($content['messages'])) {
                    $message->fb_message_id = $content['messages'][0]['id'];
                    $charger->charge($company, $creditAction, $company->id);
                } else {
                    $message->error = isset($content['error']) ? $content['error']['message'] : 'Unknown error';
                    //dd($content);
                }
                $message->status = 1;
                $message->update();
                // Handle the response as needed based on $statusCode and $content
            } catch (\Exception $e) {
                Log::error($e);
                // Handle the exception
            }
        }

    }

    public function receiveMessage(Request $request, $token)
    {
        Log::info('Receive WhatsApp webhook');
        Log::info('Receive WhatsApp webhook', ['request' => $request->all()]);

        // If this webhook is a WhatsApp Calling event, delegate to whatsappcall module when available
        try {
            $entry = $request->entry[0] ?? null;
            $change = $entry['changes'][0] ?? [];
            $field = $change['field'] ?? '';
            $hasCalls = isset($change['value']['calls']);
            if ($field === 'calls' || $hasCalls) {
                // Check if whatsappcall module exists and is active
                $hasWhatsappCall = false;
                foreach (Module::all() as $m) {
                    try {
                        if (($m->get('alias') === 'whatsappcall') && (int) $m->get('active') === 1) {
                            $hasWhatsappCall = true;
                            break;
                        }
                    } catch (\Throwable $th) {
                    }
                }

                if ($hasWhatsappCall) {
                    try {
                        return app(\Modules\Whatsappcall\Http\Controllers\CallController::class)
                            ->receiveCallingWebhook($request, $token);
                    } catch (\Throwable $th) {
                        Log::error($th);

                        return response()->json(['ok' => false]);
                    }
                }

                // If module not present, acknowledge to avoid retries
                return response()->json(['ok' => true]);
            }
        } catch (\Throwable $th) {
            // continue with regular flow
        }

        if (config('wpbox.campaign_sending_type', 'normal') == 'normal') {
            //Continue with the regular flow
        } else {
            //Check if the request is update from WhatsApp
            try {
                $value = $request->entry[0]['changes'][0]['value'];
                if (isset($value['statuses'])) {
                    //this is an update of message status, and we do have laravel queue running
                    //We need to  put this in the queue
                    dispatch(new ReceiveUpdate($value));

                    return response()->json(['send' => true, 'message' => 'Update received and queued']);

                } else {
                    //This is a new message or other type of data
                    //Continue with the regular flow
                }
            } catch (\Throwable $th) {
                //Continue with the regular flow
            }
        }
        $token = PersonalAccessToken::findToken($token);

        if ($token) {

            // Token is valid
            // Proceed with the request handling

            //Find the user
            $user = User::findOrFail($token->tokenable_id);
            Auth::login($user);

            //if the user is admin
            if ($user->hasRole('admin') || true) {
                $company = app(WebhookCompanyResolver::class)->resolveFromWebhookRequest($request);

                if (! $company) {
                    $wabaid = $request->entry[0]['id'] ?? 'unknown';

                    return response()->json(['send' => false, 'error' => 'Company not found for WhatsApp webhook (WABAID: '.$wabaid.')']);
                }

                Auth::login($company->user);
            } else {
                //Company, -- not used anymore
                $company = $this->getCompany();
            }

            $this->setWebhookCompanyContext($company);

            //Resend the Request to webhook
            try {
                $whatsapp_data_send_webhook = $company->getConfig('whatsapp_data_send_webhook', '');
                if (strlen($whatsapp_data_send_webhook) > 5) {
                    //Send the data to a webhook
                    Http::post($whatsapp_data_send_webhook, $request->all());
                }
            } catch (\Throwable $th) {
                //throw $th;
            }

            //Get the message object
            try {
                $value = $request->entry[0]['changes'][0]['value'];

                if (isset($value['statuses'])) {

                    //Status change -- Message update
                    $newStatus = $value['statuses'][0]['status'];
                    $messageFBID = $value['statuses'][0]['id'];
                    $message = Message::withoutGlobalScopes()
                        ->where('fb_message_id', $messageFBID)
                        ->where('company_id', $company->id)
                        ->first();
                    if ($message) {
                        $message_previous_status = $message->status;
                        if ($newStatus == 'sent' && $message->status != 3) {
                            $message->status = 2;
                        } elseif ($newStatus == 'delivered' && $message->status != 4) {
                            $message->status = 3;
                        } elseif ($newStatus == 'read') {
                            $message->status = 4;
                        } elseif ($newStatus == 'failed') {
                            $message->status = 5;
                            $message->error = $value['statuses'][0]['errors'][0]['message'];
                        }
                        $message->update();
                        if ($message->campaign_id != null && $message_previous_status != $message->status) {
                            $campaign = Campaign::where('id', $message->campaign_id)->first();
                            if ($campaign) {
                                if ($newStatus == 'sent') {
                                    $campaign->increment('sended_to', 1);
                                } elseif ($newStatus == 'delivered') {
                                    $campaign->increment('delivered_to', 1);
                                } elseif ($newStatus == 'read') {
                                    $campaign->increment('read_by', 1);
                                }
                                $campaign->update();
                            }

                        }
                    }
                } else {
                    //Message receive
                    $phone = $value['messages'][0]['from'];

                    //Check if this phone is in the blacklist
                    $blacklist = $company->getConfig('black_listed_phone_numbers', '');
                    if (strlen($blacklist) > 5) {
                        $blacklist = explode(',', $blacklist);

                        if (in_array($phone, $blacklist)) {
                            return response()->json(['send' => false, 'error' => 'Blacklisted']);
                        }
                    }

                    $type = $value['messages'][0]['type'];
                    $name = $value['contacts'][0]['profile']['name'];
                    $messageID = $value['messages'][0]['id'];

                    //Find the contact scoped to the webhook organisation
                    $contact = $this->findContactByPhone($company, $phone);

                    if (! $contact) {
                        //Create new contact
                        $contact = Contact::create([
                            'name' => $name,
                            'phone' => $phone,
                            'avatar' => '',
                            'company_id' => $company->id,
                            'has_chat' => true,
                            'enabled_ai_bot' => 1,
                            'subscribed' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                            'last_support_reply_at' => null,
                            'last_reply_at' => now(),
                            'last_message' => '',
                            'is_last_message_by_contact' => true,
                        ]);
                    }

                    if ($type == 'image') {
                        //We need to download and store the image
                        $urlLink = $this->downloadAndStoreMedia($value['messages'][0]['image']['id'], '.jpg');
                        $message = $contact->sendMessage($urlLink, true, false, 'IMAGE', $messageID);
                    } elseif ($type == 'audio') {
                        // We need to download and store the audio
                        $urlLink = $this->downloadAndStoreMedia($value['messages'][0]['audio']['id'], '.mp3');
                        $message = $contact->sendMessage($urlLink, true, false, 'AUDIO', $messageID);
                    } elseif ($type == 'video') {
                        //We need to download and store the video
                        $urlLink = $this->downloadAndStoreMedia($value['messages'][0]['video']['id'], '.mp4');
                        $message = $contact->sendMessage($urlLink, true, false, 'VIDEO', $messageID);
                    } elseif ($type == 'document') {
                        //We need to download and store the video
                        $urlLink = $this->downloadAndStoreMedia($value['messages'][0]['document']['id'], '.pdf');
                        $message = $contact->sendMessage($urlLink, true, false, 'DOCUMENT', $messageID);
                    } elseif ($type == 'text') {

                        $message = $value['messages'][0]['text']['body'];

                        //Store the message
                        $message = $contact->sendMessage($message, true, false, 'TEXT', $messageID);

                    } elseif ($type == 'interactive') {
                        if ($value['messages'][0]['interactive']['type'] == 'button_reply') {
                            $messageContent = $value['messages'][0]['interactive']['button_reply']['title'];

                            try {
                                //Store the message
                                $message = $contact->sendMessage($messageContent, true, false, 'TEXT', $messageID, $value['messages'][0]['interactive']['button_reply']['id']);
                            } catch (\Throwable $th) {
                                $message = null;
                            }

                            try {
                                //Send the button reply ID to the bot
                                $messageButtonID = $value['messages'][0]['interactive']['button_reply']['id'];
                                $contact->botReply($messageButtonID, $message);
                            } catch (\Throwable $th) {
                                //throw $th;
                            }

                        } elseif ($value['messages'][0]['interactive']['type'] == 'list_reply') {
                            $messageContent = $value['messages'][0]['interactive']['list_reply']['title'];

                            try {
                                //Store the message
                                $message = $contact->sendMessage($messageContent, true, false, 'TEXT', $messageID, $value['messages'][0]['interactive']['list_reply']['id']);
                            } catch (\Throwable $th) {
                                $message = null;
                            }

                            try {
                                //Send the list reply ID to the bot
                                $messageListID = $value['messages'][0]['interactive']['list_reply']['id'];
                                $contact->botReply($messageListID, $message);
                            } catch (\Throwable $th) {
                                //throw $th;
                            }
                        } elseif ($value['messages'][0]['interactive']['type'] == 'nfm_reply') {
                            // WhatsApp Flow completion — user submitted the form
                            try {
                                $nfmReply = $value['messages'][0]['interactive']['nfm_reply'] ?? [];
                                $responseJson = json_decode($nfmReply['response_json'] ?? '{}', true) ?? [];
                                $flowToken = $responseJson['flow_token'] ?? null;

                                \Illuminate\Support\Facades\Log::info('WhatsApp Flow nfm_reply received', [
                                    'contact_id' => $contact->id,
                                    'flow_token' => $flowToken,
                                    'response' => $responseJson,
                                ]);

                                // Strip internal keys — store only user-submitted field data
                                $internalKeys = ['flow_token', 'version', 'action', 'screen', 'name'];
                                $formData = array_filter(
                                    $responseJson,
                                    fn ($k) => ! in_array($k, $internalKeys, true),
                                    ARRAY_FILTER_USE_KEY
                                );

                                // Find and mark the response record as completed
                                if ($flowToken && str_starts_with($flowToken, 'flow_')) {
                                    $parts = explode('_', $flowToken);
                                    $responseId = $parts[1] ?? null;

                                    if ($responseId) {
                                        $flowResponse = \App\Models\WhatsappFlowResponse::with('whatsappFlow')->find($responseId);
                                        if ($flowResponse && (! empty($formData) || $flowResponse->status === 'pending')) {
                                            $submissionService = app(\App\Services\WhatsappFlowSubmissionService::class);
                                            $contactModel = \Modules\Flowmaker\Models\Contact::find($contact->id);
                                            $automationFlowId = $flowResponse->flow_id;

                                            $submissionService->handleCompleted(
                                                $flowResponse,
                                                ! empty($formData) ? $formData : $responseJson,
                                                $contactModel,
                                                $automationFlowId
                                            );

                                            \Illuminate\Support\Facades\Log::info('WhatsApp Flow response marked completed via nfm_reply', [
                                                'response_id' => $responseId,
                                                'field_count' => count($formData),
                                                'fields' => array_keys($formData),
                                            ]);
                                        }
                                    }
                                }

                                // Store a synthetic message so the chat history shows the submission
                                $messageContent = __('WhatsApp Flow completed');
                                $message = $contact->sendMessage($messageContent, true, false, 'TEXT', $messageID);

                                // Trigger Flowmaker to resume automation from the waiting flow node
                                if ($message) {
                                    // $message->extra = json_encode($formData ?: $responseJson);

                                    // Always store the complete raw payload. The flow_token is needed by the
                                    // WhatsAppFlow node to locate the DB response record. Storing only $formData
                                    // loses the token when fields are present, and falls back to just {flow_token}
                                    // when $formData is empty — making field data unreachable in the node.
                                    $message->extra = json_encode($responseJson);
                                    $message->save();

                                    // Use the company owner, not the contact's assigned user.
                                    // Contacts often have user_id = null (unassigned), which
                                    // would prevent the event from firing.
                                    $company = \App\Models\Company::find($contact->company_id);
                                    $companyUser = $company ? \App\Models\User::find($company->user_id) : null;

                                    // Fallback to contact's assigned user if company owner not found
                                    if (! $companyUser) {
                                        $companyUser = \App\Models\User::find($contact->user_id);
                                    }

                                    if ($companyUser) {
                                        \Illuminate\Support\Facades\Log::info('WhatsApp Flow nfm_reply: dispatching ContactReplies', [
                                            'contact_id' => $contact->id,
                                            'company_user_id' => $companyUser->id,
                                            'has_form_data' => ! empty($formData),
                                        ]);
                                        event(new \Modules\Wpbox\Events\ContactReplies($companyUser, $message, $contact));
                                    } else {
                                        \Illuminate\Support\Facades\Log::error('WhatsApp Flow nfm_reply: could not find company user — ContactReplies not dispatched', [
                                            'contact_id' => $contact->id,
                                            'company_id' => $contact->company_id,
                                        ]);
                                    }
                                }

                            } catch (\Throwable $th) {
                                \Illuminate\Support\Facades\Log::error('WhatsApp Flow nfm_reply handling error', [
                                    'error' => $th->getMessage(),
                                    'contact_id' => $contact->id ?? null,
                                ]);
                            }
                        }
                    } elseif ($type == 'contacts' || $type == 'contact') {
                        $message = $contact->sendMessage(__('Contact message is sent. But the message format is unsupported'), true, false, 'TEXT', $messageID);
                    } elseif ($type == 'location') {

                        //return response()->json(['send' => "Location"]);
                        $message = 'https://www.google.com/maps?q='.$value['messages'][0]['location']['latitude'].','.$value['messages'][0]['location']['longitude'];
                        //Store the message
                        $message = $contact->sendMessage($message, true, false, 'LOCATION', $messageID);
                    } elseif ($type == 'button') {
                        $message = $value['messages'][0]['button']['text'];
                        //Store the message
                        $message = $contact->sendMessage($message, true, false, 'TEXT', $messageID);
                    }

                }

                return response()->json(['send' => true]);

            } catch (\Throwable $th) {
                return response()->json(['send' => false, 'error' => $th, 'data' => $request->all()]);
            }

        } else {
            return response()->json(['send' => false]);
        }
    }

    public function downloadAndStoreMedia($mediaID, $ext = '.jpg')
    {
        $url = self::$facebookAPI.$mediaID;
        $accessToken = $this->getToken();
        $company = $this->getCompany();
        try {

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ])->get($url);

            $statusCode = $response->status();
            $content = json_decode($response->body(), true);

            $responseImage = $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
            ])->get($content['url']);

            $fileContents = $responseImage->getBody()->getContents();

            // Define the local path where you want to save the downloaded file
            if (config('settings.use_s3_as_storage', false)) {
                //S3 - store per company
                $fileName = 'uploads/media/received/'.$company->id.'/'.$content['id'].$ext;
                $path = Storage::disk('s3')->put($fileName, $fileContents, 'public');
                /** @var \Illuminate\Filesystem\FilesystemAdapter $s3 */
                $s3 = Storage::disk('s3');

                return $s3->url($fileName);
            } else {
                //Regular
                $localPath = public_path('uploads/media/'.$content['id'].$ext);
                file_put_contents($localPath, $fileContents);
                $url = config('app.url').'/uploads/media/'.$content['id'].$ext;

                return preg_replace('#(https?:\/\/[^\/]+)\/\/#', '$1/', $url);
            }

        } catch (\Exception $e) {
            dd($e);
            // Handle the exception
        }
    }

    public function verifyWebhook(Request $request, $tokenViaURL)
    {
        // Parse params from the webhook verification request
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $token = PersonalAccessToken::findToken($token);
        if ($token) {
            // Token is valid
            // Proceed with the request handling
            // Check if a token and mode were sent
            if ($mode && $token) {
                // Check the mode and token sent are correct
                if ($mode === 'subscribe') {
                    $user = User::findOrFail($token->tokenable_id);
                    Auth::login($user);

                    try {
                        //Company
                        $company = $this->getCompany();
                        $company->setConfig('whatsapp_webhook_verified', 'yes');

                    } catch (\Throwable $th) {
                    }

                    // Respond with 200 OK and challenge token from the request
                    return response($challenge, 200);
                } else {
                    // Respond with '403 Forbidden' if verify tokens do not match
                    return response()->json([], 403);
                }
            }
        } else {
            return response()->json([], 403);
        }

    }

    /**
     * Upload a file to facebook and return the handle using the upload API
     */
    public function uploadDocumentToFacebook($file)
    {
        //Upload a file to facebook and return the handle using the upload API
        $company = $this->getCompany();
        $facebook_app_id = $company->getConfig('facebook_app_id', '');
        if (strlen($facebook_app_id) < 5) {
            throw new \Exception('Facebook App ID is not set. Please set it in the App Settings.');
        }
        $url = self::$facebookAPI.$facebook_app_id.'/media';
        $mediaURL = self::$facebookAPI.$facebook_app_id.'/uploads';
        $accessToken = $this->getToken();

        //Get an upload sessions id
        try {
            // First get an upload session ID
            $uploadSessionResponse = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ])->post($mediaURL, [
                'file_length' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'file_name' => $file->getClientOriginalName(),
            ]);

            $uploadSession = json_decode($uploadSessionResponse->body(), true);

            if (! isset($uploadSession['id'])) {
                throw new \Exception('Failed to get upload session ID');
            }

            $uploadURL = self::$facebookAPI.$uploadSession['id'];

            // Now upload the actual file using the session ID
            $response = Http::withHeaders([
                'Authorization' => 'OAuth '.$accessToken,
                'Content-Type' => 'application/json',
                'file_offset' => '0',
            ])->withBody(
                file_get_contents($file->getRealPath()),
                $file->getMimeType()
            )->post($uploadURL);

            $result = json_decode($response->body(), true);

            if (isset($result['h'])) {
                return $result['h']; // Return the handle
            }

            throw new \Exception('Failed to upload document');
        } catch (\Exception $e) {
            // Handle any errors
            Log::error('Facebook document upload failed: '.$e->getMessage());

            return null;
        }
    }

    public function submitWhatsAppTemplate($templateData)
    {
        $company = $this->getCompany();
        $url = self::$facebookAPI.$this->getAccountID().'/message_templates';
        $accessToken = $this->getToken();
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $templateData);

            $statusCode = $response->status();
            $content = json_decode($response->body(), true);

            return ['status' => $statusCode, 'content' => $content];
        } catch (\Exception $e) {
            // Handle the exception
            return ['status' => 500, 'content' => $e->getMessage()];
        }
    }

    public function deleteWhatsAppTemplate($templateName)
    {
        $company = $this->getCompany();
        $url = self::$facebookAPI.$this->getAccountID().'/message_templates?name='.$templateName;
        $accessToken = $this->getToken();
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ])->delete($url);

            $statusCode = $response->status();
            $content = json_decode($response->body(), true);

            return ['status' => $statusCode, 'content' => $content];
        } catch (\Exception $e) {
            // Handle the exception
            return ['status' => 500, 'content' => $e->getMessage()];
        }
    }

    public function loadTemplatesFromWhatsApp($after = '')
    {
        $url = self::$facebookAPI.$this->getAccountID().'/message_templates';
        $queryParams = [
            'fields' => 'name,category,language,quality_score,components,status',
            'limit' => 100,
        ];
        if ($after != '') {
            $queryParams['after'] = $after;
        }
        $headers = [
            'Authorization' => 'Bearer '.$this->getToken(),
        ];

        $response = Http::withHeaders($headers)->get($url, $queryParams);

        // Handle the response here
        if ($response->successful()) {
            $responseData = $response->json();

            //On success - remove all previous templates, if on initial call
            if ($after == '') {
                $existingTemplateIds = Template::pluck('id')->toArray();

                foreach ($existingTemplateIds as $templateId) {
                    if (! in_array($templateId, array_column($responseData['data'], 'id'))) {
                        $template = Template::find($templateId);
                        if ($template && $template->isReferenced()) {
                            $template->deleted_at = now();
                            $template->save();
                        } else {
                            $template?->forceDelete();
                        }
                    }
                }
            }

            $companyID = $this->getCompany()->id;
            foreach ($responseData['data'] as $key => $template) {
                //Insert new messages
                try {
                    //Find the template, and set not to be deleted

                    $data = [
                        'name' => $template['name'],
                        'category' => $template['category'],
                        'language' => $template['language'],
                        'status' => $template['status'],
                        'id' => $template['id'],
                        'company_id' => $companyID,
                        'components' => json_encode($template['components']),
                        'deleted_at' => null,
                    ];
                    $template = Template::upsert($data, 'id', ['components', 'status', 'deleted_at']);

                } catch (\Throwable $th) {
                    //throw $th;
                    //dd($th);
                }
            }

            //Check if we have more templates
            if ($responseData['paging'] && isset($responseData['paging']['next']) && isset($responseData['paging']['cursors']['after'])) {
                return $this->loadTemplatesFromWhatsApp($responseData['paging']['cursors']['after']);
            } else {

                //
                return true;
            }

            // Process $responseData as needed
        } else {
            // Handle error response
            return false;
        }
    }

    public function sendMessageToWhatsApp(Message $message, $contact)
    {
        $url = self::$facebookAPI.$this->getPhoneID().'/messages';
        $accessToken = $this->getToken();
        if (strlen($accessToken > 5)) {
            try {

                $dataToSend = [
                    'messaging_product' => 'whatsapp',
                    'to' => $contact->phone, // Add recipient information
                ];

                if (strlen($message->buttons) > 5) {
                    //Interactive message
                    $dataToSend['type'] = 'interactive';

                    $dataToSend['interactive']['body'] = [
                        'text' => InteractiveListLimits::truncate($message->value, InteractiveListLimits::BODY),
                    ];

                    //Header if available
                    if (strlen($message->header_text) > 0) {
                        $dataToSend['interactive']['header'] = [
                            'type' => 'text',
                            'text' => InteractiveListLimits::truncate($message->header_text, InteractiveListLimits::HEADER),
                        ];
                    }

                    //Footer if available
                    if (strlen($message->footer_text) > 0) {
                        $dataToSend['interactive']['footer'] = [
                            'text' => InteractiveListLimits::truncate($message->footer_text, InteractiveListLimits::FOOTER),
                        ];
                    }

                    //->is_cta is runtime property
                    if ($message->is_cta) {
                        unset($message->is_cta); //We don't need this, since will cause error
                        $dataToSend['interactive']['type'] = 'cta_url';
                        $dataToSend['interactive']['action'] = array_values(json_decode($message->buttons, true))[0];

                    } else {
                        //Reply buttons
                        $buttons = array_values(json_decode($message->buttons, true));

                        if (count($buttons) > 0) {
                            if (isset($buttons[0]) && isset($buttons[0]['type']) && $buttons[0]['type'] == 'reply') {
                                $dataToSend['interactive']['type'] = 'button';
                                $dataToSend['interactive']['action']['buttons'] = $buttons;
                            } else {
                                $dataToSend['interactive']['type'] = 'list';

                                $dataToSend['interactive']['action'] = InteractiveListLimits::constrainListAction(
                                    json_decode($message->buttons, true) ?? []
                                );
                            }
                        }

                    }
                } elseif (strlen($message->value) > 0) {
                    //Text message
                    $dataToSend['type'] = 'text';

                    if (config('settings.is_demo', false)) {
                        //Demo
                        $dataToSend['text'] = [
                            'body' => '[THIS IS DEMO] '.$message->value,
                            'preview_url' => true,
                        ];
                    } else {
                        //Production
                        $dataToSend['text'] = [
                            'body' => $message->value,
                            'preview_url' => true,
                        ];
                    }

                } elseif (strlen($message->header_image) > 0) {
                    $dataToSend['type'] = 'image';
                    $dataToSend['image'] = [
                        'link' => $message->header_image,
                    ];
                } elseif (strlen($message->header_video) > 0) {
                    if (substr($message->header_video, -4) === '.mp3') {
                        $dataToSend['type'] = 'audio';
                        $dataToSend['audio'] = [
                            'link' => $message->header_video,
                        ];
                    } else {
                        $dataToSend['type'] = 'video';
                        $dataToSend['video'] = [
                            'link' => $message->header_video,
                        ];
                    }

                } elseif (strlen($message->header_audop) > 0) {
                    $dataToSend['type'] = 'audio';
                    $dataToSend['audio'] = [
                        'link' => $message->header_audio,
                    ];

                } elseif (strlen($message->header_document) > 0) {
                    $path = parse_url($message->header_document, PHP_URL_PATH);
                    $filename = pathinfo($path, PATHINFO_FILENAME);
                    $dataToSend['type'] = 'document';
                    $dataToSend['document'] = [
                        'link' => $message->header_document,
                        'filename' => $filename,
                    ];
                }

                Log::info('================= Sending message to WhatsApp ==================');
                Log::info($dataToSend);

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ])->post($url, $dataToSend);

                $statusCode = $response->status();
                $content = json_decode($response->body(), true);
                //If error

                if (isset($content['error'])) {
                    $errorMessage = $content['error']['message'] ?? 'Unknown error';
                    $message->error = $errorMessage;
                    $message->update();
                    Log::error('WhatsApp API rejected message', [
                        'message_id' => $message->id,
                        'status' => $statusCode,
                        'error' => $content['error'],
                    ]);
                } else {
                    $message->fb_message_id = $content['messages'][0]['id'];
                    $message->update();
                }

                // Handle the response as needed based on $statusCode and $content
            } catch (\Exception $e) {
                // Handle the exception
                //if debug mode is on
                if (config('app.debug', false)) {
                    dd($e);
                }
            }
        }

    }
}
