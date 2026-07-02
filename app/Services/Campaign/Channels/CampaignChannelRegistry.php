<?php

namespace App\Services\Campaign\Channels;

use App\Models\Company;
use App\Services\Billing\CreditBillingResolver;
use App\Services\Billing\CreditCharger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Message;

class CampaignChannelRegistry
{
    public function __construct(
        private readonly CreditCharger $charger,
        private readonly CreditBillingResolver $billingResolver,
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

    private function sendSms(Message $message, Company $company): bool
    {
        $creditAction = 'send_sms_message';

        if (! $this->charger->canCharge($company, $creditAction)) {
            $message->error = $this->charger->insufficientCreditsMessage($creditAction);
            $message->status = Message::STATUS_FAILED;
            $message->save();

            return false;
        }

        $twilioAccountSid = $company->getConfig('TWILIO_ACCOUNT_SID', '');
        $twilioAuthToken = $company->getConfig('TWILIO_AUTH_TOKEN', '');
        $twilioFromNumber = $company->getConfig('TWILIO_FROM_NUMBER', '');

        if (empty($twilioAccountSid) || empty($twilioAuthToken) || empty($twilioFromNumber)) {
            $message->error = 'Twilio settings are missing';
            $message->status = Message::STATUS_FAILED;
            $message->save();

            return false;
        }

        $response = Http::withBasicAuth($twilioAccountSid, $twilioAuthToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$twilioAccountSid}/Messages.json", [
                'To' => $message->contact->phone,
                'From' => $twilioFromNumber,
                'Body' => $message->value,
            ]);

        $body = json_decode($response->body(), true);

        if (($body['status'] ?? '') === 'queued') {
            $this->charger->charge($company, $creditAction, $company->id);
            $message->status = Message::STATUS_SENT;
            $message->save();

            return true;
        }

        $message->error = $body['message'] ?? 'SMS send failed';
        $message->status = Message::STATUS_FAILED;
        $message->save();

        return false;
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
