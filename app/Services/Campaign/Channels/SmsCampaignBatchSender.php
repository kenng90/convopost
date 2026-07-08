<?php

namespace App\Services\Campaign\Channels;

use App\Models\Company;
use App\Services\Billing\CreditCharger;
use App\Services\Telephony\Sms\HostPinnacleBulkSmsSender;
use App\Services\Telephony\Sms\SmsConfig;
use Illuminate\Support\Collection;
use Modules\Wpbox\Models\Message;

class SmsCampaignBatchSender
{
    public function __construct(
        private readonly CampaignChannelRegistry $channels,
        private readonly HostPinnacleBulkSmsSender $bulkSender,
        private readonly CreditCharger $charger,
    ) {
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    public function dispatch(Collection $messages): int
    {
        $sent = 0;
        $remaining = collect();

        foreach ($messages->groupBy('company_id') as $companyId => $companyMessages) {
            /** @var Message $first */
            $first = $companyMessages->first();
            $first->loadMissing(['campaign.company', 'contact']);
            $company = $first->campaign?->company;

            if (! $company instanceof Company || ! $this->channels->usesHostPinnacleBulk($company)) {
                $remaining = $remaining->merge($companyMessages);

                continue;
            }

            foreach ($companyMessages->groupBy(fn (Message $message) => md5((string) $message->value)) as $group) {
                if ($group->count() >= $this->channels->bulkMinBatch()) {
                    $sent += $this->sendBulkGroup($company, $group);
                } else {
                    $remaining = $remaining->merge($group);
                }
            }
        }

        foreach ($remaining as $message) {
            $message->loadMissing(['campaign.company', 'contact']);
            $company = $message->campaign?->company;
            if (! $company) {
                continue;
            }

            if ($this->channels->sendSms($message, $company)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    private function sendBulkGroup(Company $company, Collection $messages): int
    {
        $rows = [];
        $validMessages = [];

        foreach ($messages as $message) {
            $message->loadMissing('contact');
            $phone = $message->contact->phone ?? null;
            if (empty($phone)) {
                $message->error = 'Contact has no phone number';
                $message->status = Message::STATUS_FAILED;
                $message->save();

                continue;
            }

            $rows[] = [
                'phone' => $phone,
                'message' => (string) $message->value,
            ];
            $validMessages[] = $message;
        }

        if ($rows === []) {
            return 0;
        }

        $creditAction = 'send_sms_message';
        $requiredCredits = count($validMessages);
        if (! $this->charger->canCharge($company, $creditAction, $requiredCredits)) {
            $error = $this->charger->insufficientCreditsMessage($creditAction, $requiredCredits);
            foreach ($validMessages as $message) {
                $message->error = $error;
                $message->status = Message::STATUS_FAILED;
                $message->save();
            }

            return 0;
        }

        $result = $this->bulkSender->send(SmsConfig::forCompany($company), $rows);
        if (! $result->success) {
            foreach ($validMessages as $message) {
                $message->error = $result->message;
                $message->status = Message::STATUS_FAILED;
                $message->save();
            }

            return 0;
        }

        return $this->channels->markBulkSmsSent($validMessages, $company, $result->providerMessageId);
    }
}
