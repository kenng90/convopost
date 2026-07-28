<?php

namespace App\Services\Campaign\Channels;

use App\Models\Company;
use App\Services\Billing\CreditCharger;
use App\Services\Telephony\Sms\SmsConfig;
use App\Services\Telephony\Sms\SmsSender;
use Illuminate\Support\Facades\Mail;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Message;

class CampaignChannelRegistry
{
    public function __construct(
        private readonly CreditCharger $charger,
        private readonly SmsSender $smsSender,
    ) {
    }

    public function send(string $channel, Message $message, Company $company): bool
    {
        return match ($channel) {
            Campaign::CHANNEL_SMS => $this->sendSms($message, $company),
            Campaign::CHANNEL_EMAIL => $this->sendEmail($message, $company),
            default => false,
        };
    }

    public function sendSms(Message $message, Company $company): bool
    {
        $creditAction = 'send_sms_message';

        if (! $this->charger->canCharge($company, $creditAction)) {
            $message->error = $this->charger->insufficientCreditsMessage($creditAction);
            $message->status = Message::STATUS_FAILED;
            $message->save();

            return false;
        }

        $message->loadMissing('contact');
        $phone = $message->contact->phone ?? null;
        if (empty($phone)) {
            $message->error = 'Contact has no phone number';
            $message->status = Message::STATUS_FAILED;
            $message->save();

            return false;
        }

        $result = $this->smsSender->send($company, $phone, (string) $message->value);
        if (! $result->success) {
            $message->error = $result->message;
            $message->status = Message::STATUS_FAILED;
            $message->save();

            return false;
        }

        $this->charger->charge($company, $creditAction, $company->id);
        $message->provider_message_id = $result->providerMessageId;
        $message->status = Message::STATUS_SENT;
        $message->save();

        return true;
    }

    public function markBulkSmsSent(array $messages, Company $company, ?string $providerMessageId = null): int
    {
        $creditAction = 'send_sms_message';
        $sent = 0;
        $sentIds = [];

        foreach ($messages as $message) {
            if (! $message instanceof Message) {
                continue;
            }

            if (! $this->charger->canCharge($company, $creditAction)) {
                $message->error = $this->charger->insufficientCreditsMessage($creditAction);
                $message->status = Message::STATUS_FAILED;
                $message->save();

                continue;
            }

            $sentIds[] = $message->id;
            $sent++;
        }

        if ($sentIds === []) {
            return 0;
        }

        $this->charger->charge($company, $creditAction, $company->id, $sent);

        Message::withoutGlobalScopes()
            ->whereIn('id', $sentIds)
            ->update([
                'provider_message_id' => $providerMessageId,
                'status' => Message::STATUS_SENT,
                'error' => '',
            ]);

        $campaignIds = collect($messages)
            ->filter(fn ($message) => $message instanceof Message && in_array($message->id, $sentIds, true))
            ->groupBy(fn (Message $message) => (int) $message->campaign_id);

        foreach ($campaignIds as $campaignId => $group) {
            Campaign::withoutGlobalScopes()
                ->whereKey($campaignId)
                ->increment('sended_to', $group->count());
        }

        return $sent;
    }

    public function usesHostPinnacleBulk(Company $company): bool
    {
        $config = SmsConfig::forCompany($company);

        return $config->isHostPinnacle() && $config->smsReady();
    }

    public function bulkMinBatch(): int
    {
        return max(2, (int) config('hostpinnacle.bulk_min_batch', 2));
    }

    private function sendEmail(Message $message, Company $company): bool
    {
        $creditAction = 'send_email_message';
        $email = $message->contact->email ?? null;

        if (empty($email)) {
            $message->error = 'Contact has no email address';
            $message->status = Message::STATUS_FAILED;
            $message->save();

            return false;
        }

        if (! $this->charger->canCharge($company, $creditAction)) {
            $message->error = $this->charger->insufficientCreditsMessage($creditAction);
            $message->status = Message::STATUS_FAILED;
            $message->save();

            return false;
        }

        try {
            $subject = $message->header_text ?: ($message->campaign->name ?? 'Campaign message');
            Mail::raw($message->value, function ($mail) use ($email, $subject) {
                $mail->to($email)->subject($subject);
            });

            $this->charger->charge($company, $creditAction, $company->id);
            $message->status = Message::STATUS_SENT;
            $message->save();

            return true;
        } catch (\Throwable $e) {
            $message->error = $e->getMessage();
            $message->status = Message::STATUS_FAILED;
            $message->save();

            return false;
        }
    }
}
