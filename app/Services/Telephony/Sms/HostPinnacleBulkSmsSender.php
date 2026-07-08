<?php

namespace App\Services\Telephony\Sms;

use App\Services\HostPinnacle\HostPinnacleClient;
use App\Services\HostPinnacle\HostPinnacleCredentials;
use App\Services\HostPinnacle\HostPinnacleCreditSync;
use App\Services\Telephony\PhoneNormalizer;
use App\Support\ConvoConnectBrand;
use Illuminate\Support\Facades\File;

class HostPinnacleBulkSmsSender
{
    public function __construct(
        protected HostPinnacleClient $client,
        protected HostPinnacleCreditSync $creditSync,
        protected PhoneNormalizer $normalizer,
    ) {
    }

    /**
     * @param  array<int, array{phone: string, message: string}>  $rows
     */
    public function send(SmsConfig $config, array $rows): SmsBulkSendResult
    {
        $credentials = HostPinnacleCredentials::forCompany($config->company);
        if ($credentials === null) {
            return SmsBulkSendResult::fail(ConvoConnectBrand::name().' SMS is not provisioned for this organization.');
        }

        if ($rows === []) {
            return SmsBulkSendResult::fail('No SMS recipients provided.');
        }

        if (! $this->creditSync->hasMinimumBalance($config->company)) {
            return SmsBulkSendResult::fail(ConvoConnectBrand::name().' SMS balance is too low. Add messaging credits to continue.');
        }

        $filePath = $this->buildUploadFile($rows);
        try {
            $response = $this->client->sendBulkUpload($credentials, $filePath);
        } finally {
            if (is_file($filePath)) {
                File::delete($filePath);
            }
        }

        if (! ($response['ok'] ?? false)) {
            return SmsBulkSendResult::fail(ConvoConnectBrand::sanitizeError($response['error'] ?? ConvoConnectBrand::name().' bulk SMS send failed.'));
        }

        $transactionId = data_get($response, 'data.transactionId');

        return SmsBulkSendResult::ok(
            count($rows),
            is_string($transactionId) ? $transactionId : null,
        );
    }

    /**
     * @param  array<int, array{phone: string, message: string}>  $rows
     */
    private function buildUploadFile(array $rows): string
    {
        $directory = storage_path('app/hostpinnacle/bulk');
        File::ensureDirectoryExists($directory);

        $filePath = $directory.'/bulk_'.uniqid('', true).'.csv';
        $handle = fopen($filePath, 'w');
        fputcsv($handle, ['mobile', 'msg']);

        foreach ($rows as $row) {
            $mobile = $this->normalizer->forHostPinnacle($row['phone'] ?? '');
            if ($mobile === null) {
                continue;
            }

            fputcsv($handle, [$mobile, $row['message'] ?? '']);
        }

        fclose($handle);

        return $filePath;
    }
}
