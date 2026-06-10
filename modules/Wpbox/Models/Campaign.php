<?php

namespace Modules\Wpbox\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Modules\Contacts\Models\Contact as ContactModel;
use Modules\Contacts\Models\Group;
use Modules\Wpbox\Traits\Whatsapp;

class Campaign extends Model
{
    use Whatsapp;

    protected $table = 'wa_campaings';

    public $guarded = [];

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

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $company_id = session('company_id', null);
            if ($company_id) {
                $model->company_id = $company_id;
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
            $contact->sendMessage($contact->getCompany()->getConfig('delay_response', __('Give me a moment, I will have the answer shortly')), false);
            $this->sendCampaignMessageToWhatsApp($message);

            return true;
        } else {
            return false;
        }
    }

    public function makeMessages($request, ?ContactModel $contact = null)
    {
        if ($this->group_id == null && $this->contact_id == null && $contact == null) {
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

        if ($tzBasedDelivery) {
            try {
                $sendTime = Carbon::parse($systemRelatedDateTimeOfSend->format('Y-m-d H:i:s'), $contact->country->timezone)->copy()->tz(config('app.timezone'))->format('Y-m-d H:i:s');
            } catch (\Throwable $th) {
            }
        }

        $APIComponents = [];

        foreach ($components as $keyComponent => $component) {
            $lowKey = strtolower($component['type']);

            if ($component['type'] == 'HEADER' && isset($component['format']) && $component['format'] == 'TEXT') {
                $header_text = $component['text'];
                $component['parameters'] = [];

                if (isset($variables_match[$lowKey])) {
                    $this->setParameter($variables_match[$lowKey], $variablesValues[$lowKey], $component, $header_text, $contact);
                    unset($component['text']);
                    unset($component['format']);
                    unset($component['example']);
                    array_push($APIComponents, $component);
                }
            } elseif ($component['type'] == 'BODY') {
                $content = $component['text'];
                $component['parameters'] = [];

                if (isset($variables_match[$lowKey])) {
                    $this->setParameter($variables_match[$lowKey], $variablesValues[$lowKey], $component, $content, $contact);
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

                        $this->setParameter($variables_match[$lowKey][$keyButton], $variablesValues[$lowKey][$keyButton], $button, $buttonName, $contact, $paramType);

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
