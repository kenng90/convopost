<?php

namespace Modules\Wpbox\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Modules\Contacts\Models\Contact as ContactModel;
use Modules\Contacts\Models\Group;
use Modules\Wpbox\Support\BotRulesCache;
use Modules\Wpbox\Traits\Whatsapp;

class Campaign extends Model
{
    use Whatsapp;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PAUSED_INSUFFICIENT_CREDITS = 'paused_insufficient_credits';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_EMAIL = 'email';

    public const TIMEZONE_MODE_CONTACT = 'contact';

    public const TIMEZONE_MODE_BUSINESS = 'business';

    protected $table = 'wa_campaings';

    public $guarded = [];

    protected $casts = [
        'recurrence_rule' => 'array',
        'launched_at' => 'datetime',
        'completed_at' => 'datetime',
        'recurrence_next_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function segment()
    {
        return $this->belongsTo(CampaignSegment::class, 'segment_id');
    }

    public function scopeBroadcastsOnly($query)
    {
        return $query
            ->whereNull('contact_id')
            ->where('is_bot', false)
            ->where('is_api', false)
            ->where('is_reminder', false);
    }

    public function isBroadcast(): bool
    {
        return ! $this->is_bot && ! $this->is_api && ! $this->is_reminder && $this->contact_id === null;
    }

    public function cloneAsDraft(?string $name = null): self
    {
        $clone = $this->replicate([
            'sended_to', 'delivered_to', 'read_by', 'used', 'launched_at', 'completed_at',
        ]);

        $clone->name = $name ?? ($this->name.' (copy)');
        $clone->status = self::STATUS_DRAFT;
        $clone->cloned_from_id = $this->id;
        $clone->is_active = true;
        $clone->send_to = 0;
        $clone->sended_to = 0;
        $clone->delivered_to = 0;
        $clone->read_by = 0;
        $clone->save();

        return $clone;
    }

    public function cancelPendingMessages(): int
    {
        return $this->messages()
            ->where('status', Message::STATUS_PENDING)
            ->update(['status' => Message::STATUS_CANCELLED]);
    }

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $company_id = session('company_id', null);
            if ($company_id) {
                $model->company_id = $company_id;
            }
        });

        static::saved(function ($model) {
            if ($model->company_id) {
                BotRulesCache::forget((int) $model->company_id);
            }
        });

        static::deleted(function ($model) {
            if ($model->company_id) {
                BotRulesCache::forget((int) $model->company_id);
            }
        });
    }

    public function shouldWeUseIt($receivedMessage, ContactModel $contact) //Brij Mohan Negi Update
    {
        $receivedMessage = ' '.strtolower($receivedMessage);
        $message = '';
        $sendThisCampaign = false;

        // Store the value of $this->trigger in a new variable
        $triggerValues = $this->trigger;

        // Convert $triggerValues into an array if it contains commas
        if (strpos($triggerValues, ',') !== false) {
            $triggerValues = explode(',', $triggerValues);
        }

        if (is_array($triggerValues)) {
            foreach ($triggerValues as $trigger) {
                if ($this->bot_type == 2) {
                    // Exact match
                    $trigger = ' '.strtolower($trigger); //Brij Mohan Negi Update
                    if ($receivedMessage == $trigger) {
                        $sendThisCampaign = true;
                        break; // exit the loop once a match is found
                    }
                } elseif ($this->bot_type == 3) {
                    // Contains
                    if (stripos($receivedMessage, $trigger) !== false) {
                        $sendThisCampaign = true;
                        break; // exit the loop once a match is found
                    }
                }
            }
        } else {
            //Doesn't contain commas
            if ($this->bot_type == 2) {
                // Exact match

                $triggerValues = ' '.strtolower($triggerValues); //Brij Mohan Negi Update
                if ($receivedMessage == $triggerValues) {
                    $sendThisCampaign = true;
                }
            } elseif ($this->bot_type == 3) {
                // Contains
                if (stripos($receivedMessage, $triggerValues) !== false) {
                    $sendThisCampaign = true;
                }
            }
        }

        //Change message
        if ($sendThisCampaign) {
            $this->increment('used', 1);
            $this->update();

            $message = $this->makeMessages(null, $contact);
            $contact->sendMessage(
                $contact->getCompany()->getConfig('delay_response', __('Give me a moment, I will have the answer shortly')),
                false,
                false,
                'TEXT',
                null,
                null,
                null,
                true,
            );
            $this->sendCampaignMessageToWhatsApp($message);

            return true;
        } else {
            return false;
        }
    }

    public function makeMessages($request, ?ContactModel $contact = null)
    {
        if ($this->segment_id != null && $contact == null) {
            $company = $this->company ?? Company::find($this->company_id);
            $resolver = app(\App\Services\Campaign\CampaignAudienceResolver::class);
            $audience = $resolver->resolve($company, ['segment_id' => $this->segment_id]);
            $contacts = $audience['contacts'];
        } elseif ($this->group_id == null && $this->contact_id == null && $contact == null) {
            $contacts = Contact::where('subscribed', 1)->get();
        } elseif ($this->group_id != null) {
            $contacts = Group::findOrFail($this->group_id)
                ->contacts()
                ->where('subscribed', 1)
                ->get();
        } elseif ($this->contact_id != null) {
            $contacts = Contact::where('id', $this->contact_id)->get();
        } else {
            $contacts = collect([$contact]);
        }

        $queued = $this->queueMessagesForContacts($request, $contacts);

        if ($contact != null) {
            return Message::where('contact_id', $contact->id)->where('campaign_id', $this->id)->orderBy('id', 'desc')->first();
        }
    }

    /**
     * Build and persist campaign messages for a collection of contacts.
     */
    public function queueMessagesForContacts($request, iterable $contacts, ?callable $variablesResolver = null): int
    {
        $messages = [];
        $demoLimit = config('settings.is_demo', false) ? 5 : null;

        foreach ($contacts as $contact) {
            if ($demoLimit !== null && count($messages) >= $demoLimit) {
                break;
            }

            $variablesValues = $variablesResolver ? $variablesResolver($contact) : null;
            $messageData = $this->buildMessageDataForContact($contact, $request, $variablesValues);

            if ($messageData !== null) {
                $messages[] = $messageData;
            }
        }

        $this->insertCampaignMessages($messages);

        $this->send_to = count($messages);
        $this->total_contacts = count($messages);
        $this->save();

        return count($messages);
    }

    public function insertCampaignMessages(array $messages): void
    {
        if (count($messages) === 0) {
            return;
        }

        foreach (array_chunk($messages, 500) as $chunk) {
            Message::insert($chunk);
        }
    }

    /**
     * Build a single outbound campaign message row (same structure as group broadcasts).
     */
    public function buildMessageDataForContact(ContactModel $contact, $request = null, ?array $variablesValuesOverride = null): ?array
    {
        $channel = $this->channel ?? self::CHANNEL_WHATSAPP;

        if ($channel === self::CHANNEL_SMS) {
            return $this->buildSmsMessageDataForContact($contact, $request, $variablesValuesOverride);
        }

        if ($channel === self::CHANNEL_EMAIL) {
            return $this->buildEmailMessageDataForContact($contact, $request, $variablesValuesOverride);
        }

        $template = Template::withoutGlobalScope(\App\Scopes\CompanyScope::class)->where('id', $this->template_id)->first();

        if (! $template) {
            return null;
        }

        $variablesValues = $variablesValuesOverride ?? json_decode($this->variables, true) ?? [];
        $variables_match = json_decode($this->variables_match, true) ?? [];

        if ($variablesValuesOverride !== null) {
            $variables_match = $this->inferFileBroadcastVariablesMatch($variablesValues, $variables_match);
        }

        $tzBasedDelivery = false;
        $systemRelatedDateTimeOfSend = null;

        if ($request != null && ! $request->has('send_now') && $request->has('send_time') && $request->send_time != null) {
            $company = $this->company;

            config(['app.timezone' => $company->getConfig('time_zone', config('app.timezone'))]);

            $companyRelatedDateTimeOfSend = Carbon::parse($request->send_time);
            $systemRelatedDateTimeOfSend = $companyRelatedDateTimeOfSend->copy()->tz(config('app.timezone'));
            $tzBasedDelivery = true;
        }

        $components = json_decode($template->components, true);

        $content = '';
        $header_text = '';
        $header_image = '';
        $header_document = '';
        $header_video = '';
        $header_audio = '';
        $footer = '';
        $buttons = [];

        $sendTime = Carbon::now();

        if ($tzBasedDelivery && ($this->timezone_mode ?? self::TIMEZONE_MODE_CONTACT) === self::TIMEZONE_MODE_CONTACT) {
            try {
                $sendTime = Carbon::parse($systemRelatedDateTimeOfSend->format('Y-m-d H:i:s'), $contact->country->timezone)->copy()->tz(config('app.timezone'))->format('Y-m-d H:i:s');
            } catch (\Throwable $th) {
            }
        } elseif ($tzBasedDelivery) {
            $sendTime = $systemRelatedDateTimeOfSend;
        }

        $APIComponents = [];

        foreach ($components as $keyComponent => $component) {
            $lowKey = strtolower($component['type']);

            if ($component['type'] == 'HEADER' && isset($component['format']) && $component['format'] == 'TEXT') {
                $header_text = $component['text'];
                $component['parameters'] = [];

                if (isset($variables_match[$lowKey])) {
                    $this->setParameter($variables_match[$lowKey], $variablesValues[$lowKey] ?? [], $component, $header_text, $contact);
                    unset($component['text']);
                    unset($component['format']);
                    unset($component['example']);
                    array_push($APIComponents, $component);
                }
            } elseif ($component['type'] == 'BODY') {
                $content = $component['text'];
                $component['parameters'] = [];

                if (isset($variables_match[$lowKey])) {
                    $this->setParameter($variables_match[$lowKey], $variablesValues[$lowKey] ?? [], $component, $content, $contact);
                    unset($component['text']);
                    unset($component['format']);
                    unset($component['example']);
                    array_push($APIComponents, $component);
                } elseif (! preg_match('/{{(\d+)}}/', $component['text'] ?? '')) {
                    unset($component['text']);
                    unset($component['format']);
                    unset($component['example']);
                    array_push($APIComponents, $component);
                }
            } elseif (($component['type'] == 'HEADER' && $component['format'] == 'DOCUMENT')) {
                $component['parameters'] = [[
                    'type' => 'document',
                    'document' => [
                        'link' => $this->media_link,
                    ],
                ]];
                $header_document = $this->media_link;
                unset($component['format']);
                unset($component['example']);
                array_push($APIComponents, $component);
            } elseif (($component['type'] == 'HEADER' && $component['format'] == 'IMAGE')) {
                $component['parameters'] = [[
                    'type' => 'image',
                    'image' => [
                        'link' => $this->media_link,
                    ],
                ]];
                $header_image = $this->media_link;
                unset($component['format']);
                unset($component['example']);
                array_push($APIComponents, $component);
            } elseif (($component['type'] == 'HEADER' && $component['format'] == 'VIDEO')) {
                $component['parameters'] = [[
                    'type' => 'video',
                    'video' => [
                        'link' => $this->media_link,
                    ],
                ]];
                $header_video = $this->media_link;
                unset($component['format']);
                unset($component['example']);
                array_push($APIComponents, $component);
            } elseif (($component['type'] == 'HEADER' && $component['format'] == 'AUDIO')) {
                $component['parameters'] = [[
                    'type' => 'audio',
                    'audio' => [
                        'link' => $this->media_link,
                    ],
                ]];
                $header_audio = $this->media_link;
                unset($component['format']);
                unset($component['example']);
                array_push($APIComponents, $component);
            } elseif ($component['type'] == 'FOOTER') {
                $footer = $component['text'];
            } elseif ($component['type'] == 'BUTTONS') {
                $keyButton = 0;

                foreach ($component['buttons'] as $keyButtonFromLoop => $valueButton) {
                    if (isset($variables_match[$lowKey][$keyButton]) && (($valueButton['type'] == 'URL' && stripos($valueButton['url'], '{{') !== false) || ($valueButton['type'] == 'COPY_CODE'))) {
                        $buttonName = '';
                        $button = [
                            'type' => 'button',
                            'sub_type' => strtolower($valueButton['type']),
                            'index' => $keyButtonFromLoop.'',
                            'parameters' => [],
                        ];
                        $paramType = 'text';

                        if ($valueButton['type'] == 'COPY_CODE') {
                            $paramType = 'coupon_code';
                        }

                        $this->setParameter($variables_match[$lowKey][$keyButton], $variablesValues[$lowKey][$keyButton] ?? [], $button, $buttonName, $contact, $paramType);

                        array_push($APIComponents, $button);
                        array_push($buttons, $valueButton);
                        $keyButton++;
                    } elseif ($valueButton['type'] == 'FLOW') {
                        $button = [
                            'type' => 'button',
                            'sub_type' => strtolower($valueButton['type']),
                            'index' => $keyButtonFromLoop.'',
                            'parameters' => [],
                        ];
                        $keyButton++;
                        array_push($APIComponents, $button);
                        array_push($buttons, $valueButton);
                    } else {
                        array_push($buttons, $valueButton);
                    }
                }
            }
        }

        $components = $APIComponents;

        $companyId = $contact->company_id ?? session('company_id');

        $dataToSend = [
            'contact_id' => $contact->id,
            'company_id' => $companyId,
            'value' => $content,
            'header_image' => $header_image,
            'header_video' => $header_video,
            'header_audio' => $header_audio,
            'header_document' => $header_document,
            'footer_text' => $footer,
            'buttons' => json_encode($buttons),
            'header_text' => $header_text,
            'is_message_by_contact' => false,
            'is_campign_messages' => true,
            'status' => 0,
            'created_at' => now(),
            'scchuduled_at' => $sendTime,
            'components' => json_encode($components),
            'campaign_id' => $this->id,
        ];

        if (config('settings.is_demo', false)) {
            $dataToSend['value'] = '[THIS IS DEMO] '.$dataToSend['value'];
        }

        return $dataToSend;
    }

    public function buildSmsMessageDataForContact(ContactModel $contact, $request = null, ?array $variablesValuesOverride = null): ?array
    {
        $variablesValues = $variablesValuesOverride ?? json_decode($this->variables, true) ?? [];
        $body = $variablesValues['sms_body'] ?? '';

        if ($body === '') {
            return null;
        }

        $body = $this->applyContactMergeTags($body, $contact);
        $sendTime = $this->resolveSendTimeForContact($contact, $request);

        return [
            'contact_id' => $contact->id,
            'company_id' => $contact->company_id ?? $this->company_id,
            'value' => $body,
            'header_image' => '',
            'header_video' => '',
            'header_audio' => '',
            'header_document' => '',
            'footer_text' => '',
            'buttons' => '[]',
            'header_text' => '',
            'is_message_by_contact' => false,
            'is_campign_messages' => true,
            'status' => 0,
            'created_at' => now(),
            'scchuduled_at' => $sendTime,
            'components' => '[]',
            'campaign_id' => $this->id,
        ];
    }

    public function buildEmailMessageDataForContact(ContactModel $contact, $request = null, ?array $variablesValuesOverride = null): ?array
    {
        if (empty($contact->email)) {
            return null;
        }

        $variablesValues = $variablesValuesOverride ?? json_decode($this->variables, true) ?? [];
        $subject = $variablesValues['email_subject'] ?? $this->name;
        $body = $variablesValues['email_body'] ?? '';

        if ($body === '') {
            return null;
        }

        $subject = $this->applyContactMergeTags($subject, $contact);
        $body = $this->applyContactMergeTags($body, $contact);
        $sendTime = $this->resolveSendTimeForContact($contact, $request);

        return [
            'contact_id' => $contact->id,
            'company_id' => $contact->company_id ?? $this->company_id,
            'value' => $body,
            'header_image' => '',
            'header_video' => '',
            'header_audio' => '',
            'header_document' => '',
            'footer_text' => '',
            'buttons' => '[]',
            'header_text' => $subject,
            'is_message_by_contact' => false,
            'is_campign_messages' => true,
            'status' => 0,
            'created_at' => now(),
            'scchuduled_at' => $sendTime,
            'components' => '[]',
            'campaign_id' => $this->id,
        ];
    }

    private function applyContactMergeTags(string $text, ContactModel $contact): string
    {
        return str_replace(
            ['{{name}}', '{{phone}}', '{{email}}', '{{contact.name}}', '{{contact.phone}}', '{{contact.email}}'],
            [$contact->name ?? '', $contact->phone ?? '', $contact->email ?? '', $contact->name ?? '', $contact->phone ?? '', $contact->email ?? ''],
            $text
        );
    }

    private function resolveSendTimeForContact(ContactModel $contact, $request = null): mixed
    {
        $sendTime = Carbon::now();

        if ($request === null || $request->has('send_now') || ! $request->has('send_time') || $request->send_time === null) {
            return $sendTime;
        }

        $company = $this->company ?? Company::find($this->company_id);
        config(['app.timezone' => $company?->getConfig('time_zone', config('app.timezone'))]);

        $systemRelatedDateTimeOfSend = Carbon::parse($request->send_time)->tz(config('app.timezone'));

        if (($this->timezone_mode ?? self::TIMEZONE_MODE_CONTACT) === self::TIMEZONE_MODE_CONTACT) {
            try {
                return Carbon::parse($systemRelatedDateTimeOfSend->format('Y-m-d H:i:s'), $contact->country->timezone)
                    ->copy()->tz(config('app.timezone'))->format('Y-m-d H:i:s');
            } catch (\Throwable $th) {
            }
        }

        return $systemRelatedDateTimeOfSend;
    }

    /**
     * File broadcasts supply per-row values via file_column_map but often omit parammatch.
     * Treat mapped variables as static (-2) so setParameter applies the row values.
     */
    private function inferFileBroadcastVariablesMatch(array $variablesValues, array $variablesMatch): array
    {
        foreach (['body', 'header'] as $section) {
            if (! isset($variablesValues[$section]) || ! is_array($variablesValues[$section])) {
                continue;
            }

            foreach ($variablesValues[$section] as $variableId => $value) {
                if (! isset($variablesMatch[$section][$variableId])) {
                    $variablesMatch[$section][$variableId] = '-2';
                }
            }
        }

        if (isset($variablesValues['buttons']) && is_array($variablesValues['buttons'])) {
            foreach ($variablesValues['buttons'] as $keyButton => $buttonVars) {
                if (! is_array($buttonVars)) {
                    continue;
                }

                foreach ($buttonVars as $variableId => $value) {
                    if (! isset($variablesMatch['buttons'][$keyButton][$variableId])) {
                        $variablesMatch['buttons'][$keyButton][$variableId] = '-2';
                    }
                }
            }
        }

        return $variablesMatch;
    }

    private function setParameter($variables, $values, &$component, &$content, $contact, $type = 'text')
    {
        foreach ($variables as $keyVM => $vm) {
            $data = ['type' => $type];
            if ($vm == '-2') {
                //Use static value
                $data[$type] = $values[$keyVM];
                array_push($component['parameters'], $data);
                $content = str_replace('{{'.$keyVM.'}}', $values[$keyVM], $content);

            } elseif ($vm == '-3') {
                //Contact extra value in runtime
                try {
                    $extraValueNeeded = $values[$keyVM]; // ex "order.id"
                    $extraValues = $contact->extra_value; //ex ["order"=>["id"=>1,"status"=>"pending"]]
                    $valueNeeded = null;

                    if (isset($extraValues)) {
                        $keys = explode('.', $extraValueNeeded);
                        $valueNeeded = $extraValues;

                        foreach ($keys as $key) {
                            if (isset($valueNeeded[$key])) {
                                $valueNeeded = $valueNeeded[$key];
                            } else {
                                $valueNeeded = $values[$keyVM];
                                break;
                            }
                        }
                    }

                    $data[$type] = $valueNeeded;
                    array_push($component['parameters'], $data);
                    $content = str_replace('{{'.$keyVM.'}}', $valueNeeded, $content);

                } catch (\Throwable $th) {
                    //Use static value
                    $data[$type] = $values[$keyVM];
                    array_push($component['parameters'], $data);
                    $content = str_replace('{{'.$keyVM.'}}', $values[$keyVM].'---', $content);
                }

            } elseif ($vm == '-1') {
                //Contact name
                $data[$type] = $contact->name;
                array_push($component['parameters'], $data);
                $content = str_replace('{{'.$keyVM.'}}', $contact->name, $content);
            } elseif ($vm == '0') {
                //Contact phone
                $data[$type] = $contact->phone;
                array_push($component['parameters'], $data);
                $content = str_replace('{{'.$keyVM.'}}', $contact->phone, $content);
            } else {
                //Use defined contact field
                if ($contact->fields->where('id', $vm)->first()) {
                    $val = $contact->fields->where('id', $vm)->first()->pivot->value;
                    $data[$type] = $val;
                    array_push($component['parameters'], $data);
                    $content = str_replace('{{'.$keyVM.'}}', $val, $content);
                } else {
                    $data[$type] = '';
                    array_push($component['parameters'], $data);
                    $content = str_replace('{{'.$keyVM.'}}', '', $content);
                }
            }
        }
    }
}
