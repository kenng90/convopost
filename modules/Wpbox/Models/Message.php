<?php

namespace Modules\Wpbox\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Message extends Model
{
    public const STATUS_PENDING = 0;

    public const STATUS_SENT = 1;

    public const STATUS_SENT_ALT = 2;

    public const STATUS_DELIVERED = 3;

    public const STATUS_READ = 4;

    public const STATUS_FAILED = 5;

    public const STATUS_CANCELLED = 6;

    protected $table = 'messages';

    public $guarded = [];

    protected $casts = [
        'is_call_brief' => 'boolean',
        'call_brief_payload' => 'array',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id', 'id', 'wa_campaings');
    }

    public function doTranslation($is_message_by_contact)
    {

        $company = Company::where('id', $this->company_id)->first();
        if ($is_message_by_contact) {
            //Translate the message to the company language
            $language = $company->getConfig('translate_incoming_messages', 'Original');
        } else {
            //Translate the message to the contact language
            $language = $this->contact->language;
        }

        if ($language == 'none' || $language == 'Original') {
            //Do nothing
        } elseif ($company->getConfig('translation_enabled', false)) {
            //Translate the message

            $dataTosend = [
                'model' => config('wpbox.openai_model', 'gpt-4'),
                'messages' => [
                    ['role' => 'user', 'content' => 'Translate the following message to '.$language.': '.$this->value],

                ],
                'temperature' => 0.8,
                'stream' => false,
                'max_tokens' => intval(config('wpbox.openai_max_tokens')),
            ];

            $open_ai_key = config('wpbox.openai_api_key');
            if (config('settings.is_demo', false)) {
                $open_ai_key = config('wpbox.openai_api_key_demo');
            }

            if (strlen($open_ai_key) < 5) {
                //No API key
            } else {
                $openAIResponse = Http::timeout(400)->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$open_ai_key,
                ])->post('https://api.openai.com/v1/chat/completions', $dataTosend);

                if (! $openAIResponse->ok()) {
                    //If admin, show the error
                    $this->original_message = 'Error -> '.$openAIResponse->getBody()->getContents();
                } else {
                    $this->original_message = $this->value;
                    $this->value = $openAIResponse->json()['choices'][0]['message']['content'];
                    $this->save();
                }
            }

        }

    }

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $company_id = session('company_id', null);
            if ($company_id && ! $model->company_id) {
                $model->company_id = $company_id;
            }
        });

        static::created(function (Message $message) {
            if ($message->is_note) {
                return;
            }

            $type = $message->is_message_by_contact ? 'message.received' : 'message.sent';
            app(\App\Services\Api\PublicWebhookDispatcher::class)->dispatch($message->company_id, $type, [
                'id' => $message->id,
                'contact_id' => $message->contact_id,
                'body' => $message->value,
                'status' => $message->status,
                'wamid' => $message->fb_message_id,
            ]);
        });

        static::updated(function (Message $message) {
            if (! $message->wasChanged('status') || $message->is_note) {
                return;
            }

            $map = [
                self::STATUS_SENT => 'message.sent',
                self::STATUS_DELIVERED => 'message.delivered',
                self::STATUS_READ => 'message.read',
                self::STATUS_FAILED => 'message.failed',
            ];

            $type = $map[(int) $message->status] ?? null;

            if ($type) {
                app(\App\Services\Api\PublicWebhookDispatcher::class)->dispatch($message->company_id, $type, [
                    'id' => $message->id,
                    'contact_id' => $message->contact_id,
                    'status' => $message->status,
                    'error' => $message->error,
                    'wamid' => $message->fb_message_id,
                ]);
            }
        });
    }
}
