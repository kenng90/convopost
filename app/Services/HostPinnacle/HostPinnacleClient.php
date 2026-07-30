<?php

namespace App\Services\HostPinnacle;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class HostPinnacleClient
{
    public function __construct(
        private readonly string $baseUrl = '',
    ) {
    }

    public static function make(): self
    {
        return new self((string) config('hostpinnacle.base_url'));
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function postMultipart(string $path, array $fields, ?HostPinnacleCredentials $credentials = null, ?string $filePath = null): array
    {
        $multipart = [];

        foreach ($fields as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $multipart[] = [
                'name' => (string) $name,
                'contents' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
            ];
        }

        if ($filePath !== null && is_file($filePath)) {
            $multipart[] = [
                'name' => 'file',
                'contents' => fopen($filePath, 'r'),
                'filename' => basename($filePath),
            ];
        }

        $request = Http::timeout(60)->asMultipart();

        if ($credentials !== null && $credentials->apiKey !== '') {
            $request = $request->withHeaders(['apikey' => $credentials->apiKey]);
        }

        $response = $request->post($this->endpoint($path), $multipart);

        return $this->parseResponse($response);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function postReseller(string $path, array $fields): array
    {
        $credentials = HostPinnacleCredentials::reseller();
        if ($credentials === null) {
            return ['ok' => false, 'error' => 'HostPinnacle reseller credentials are not configured.'];
        }

        $fields['userid'] = $credentials->userId;
        if ($credentials->password !== '') {
            $fields['password'] = $credentials->password;
        }
        $fields['output'] = $fields['output'] ?? 'json';

        return $this->postMultipart($path, $fields, $credentials);
    }

    /**
     * HostPinnacle's reset-password docs use application/x-www-form-urlencoded.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function postResellerForm(string $path, array $fields): array
    {
        $credentials = HostPinnacleCredentials::reseller();
        if ($credentials === null) {
            return ['ok' => false, 'error' => 'HostPinnacle reseller credentials are not configured.'];
        }

        $fields['userid'] = $credentials->userId;
        if ($credentials->password !== '') {
            $fields['password'] = $credentials->password;
        }
        $fields['output'] = $fields['output'] ?? 'json';

        $payload = [];
        foreach ($fields as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $payload[(string) $name] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        $request = Http::timeout(60)->asForm();

        if ($credentials->apiKey !== '') {
            $request = $request->withHeaders(['apikey' => $credentials->apiKey]);
        }

        $response = $request->post($this->endpoint($path), $payload);

        return $this->parseResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function readAccountStatus(HostPinnacleCredentials $credentials): array
    {
        return $this->postMultipart('/SMSApi/account/readstatus', [
            'userid' => $credentials->userId,
            'password' => $credentials->password !== '' ? $credentials->password : null,
            'output' => 'json',
        ], $credentials);
    }

    /**
     * @return array<string, mixed>
     */
    public function readSenderIds(HostPinnacleCredentials $credentials): array
    {
        return $this->postMultipart('/SMSApi/senderid/read', [
            'userid' => $credentials->userId,
            'password' => $credentials->password !== '' ? $credentials->password : null,
            'output' => 'json',
        ], $credentials);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendQuick(HostPinnacleCredentials $credentials, string $mobile, string $message): array
    {
        return $this->postMultipart('/SMSApi/send', [
            'userid' => $credentials->userId,
            'password' => $credentials->password !== '' ? $credentials->password : null,
            'sendMethod' => 'quick',
            'mobile' => $mobile,
            'senderid' => $credentials->senderId,
            'msg' => $message,
            'msgType' => $this->messageType($message),
            'duplicatecheck' => true,
            'output' => 'json',
        ], $credentials);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendBulkUpload(HostPinnacleCredentials $credentials, string $filePath): array
    {
        return $this->postMultipart('/SMSApi/send', [
            'userid' => $credentials->userId,
            'password' => $credentials->password !== '' ? $credentials->password : null,
            'sendMethod' => 'bulkupload',
            'senderid' => $credentials->senderId,
            'msgType' => 'text',
            'duplicatecheck' => true,
            'output' => 'json',
        ], $credentials, $filePath);
    }

    /**
     * @return array<string, mixed>
     */
    public function createSenderId(HostPinnacleCredentials $credentials, string $senderId): array
    {
        return $this->postMultipart('/SMSApi/senderid/create', [
            'userid' => $credentials->userId,
            'password' => $credentials->password !== '' ? $credentials->password : null,
            'senderid' => $senderId,
            'output' => 'json',
        ], $credentials);
    }

    /**
     * @return array<string, mixed>
     */
    public function createApiKey(string $userId, string $password): array
    {
        return $this->postMultipart('/SMSApi/apikey/create', [
            'userid' => $userId,
            'password' => $password,
            'output' => 'json',
        ], new HostPinnacleCredentials($userId, '', '', $password));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createSubUser(array $payload): array
    {
        return $this->postReseller('/SMSApi/reseller/createuser', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function generateSubUserPassword(string $userLoginName): array
    {
        return $this->postReseller('/SMSApi/reseller/generateuserpassword', [
            'userloginname' => $userLoginName,
            'output' => 'json',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function resetSubUserPassword(string $userLoginName, string $newPassword): array
    {
        // Docs list camelCase `newPassword`; the live gateway validates lowercase
        // `newpassword` vs `confirmpassword`. Sending only one casing fails either
        // with code 265 or by emailing a reset link instead of setting the password.
        return $this->postResellerForm('/SMSApi/reseller/resetuserpassword', [
            'userloginname' => $userLoginName,
            'newPassword' => $newPassword,
            'newpassword' => $newPassword,
            'confirmPassword' => $newPassword,
            'confirmpassword' => $newPassword,
            'output' => 'json',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function readApiKey(HostPinnacleCredentials $credentials): array
    {
        return $this->postMultipart('/SMSApi/apikey/read', [
            'userid' => $credentials->userId,
            'password' => $credentials->password !== '' ? $credentials->password : null,
            'output' => 'json',
        ], $credentials);
    }

    public function extractCreatedApiKey(array $response): ?string
    {
        foreach ([
            data_get($response, 'data.response.apiKey'),
            data_get($response, 'data.response.apikey'),
            data_get($response, 'data.apiKey'),
            data_get($response, 'raw.response.apiKey'),
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    public function extractReadApiKey(array $response): ?string
    {
        $candidate = data_get($response, 'data.response.apikeyList.apikey')
            ?? data_get($response, 'data.response.apikeyList.0.apikey');

        return is_string($candidate) && $candidate !== '' ? $candidate : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function addSubUserCredit(string $userLoginName, float $credits, string $comment = ''): array
    {
        return $this->postReseller('/SMSApi/reseller/addcredit', [
            'userloginname' => $userLoginName,
            'credits' => (string) $credits,
            'comment' => $comment !== '' ? $comment : 'ConvoCon credit sync',
            'product' => 'SMS',
            'transactiontype' => 'purchase',
            'output' => 'json',
        ]);
    }

    private function messageType(string $message): string
    {
        return preg_match('/[^\x00-\x7F]/', $message) ? 'unicode' : 'text';
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->baseUrl !== '' ? $this->baseUrl : (string) config('hostpinnacle.base_url'), '/').$path;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(Response $response): array
    {
        if ($response->failed()) {
            return [
                'ok' => false,
                'error' => 'HostPinnacle HTTP error: '.$response->body(),
                'raw' => $response->json(),
            ];
        }

        $data = $response->json();
        if (! is_array($data)) {
            $body = trim($response->body());
            $normalized = strtolower($body);

            if ($body !== '' && str_contains($normalized, 'password changed successfully')) {
                return ['ok' => true, 'data' => ['response' => ['status' => 'success', 'msg' => $body]]];
            }

            return [
                'ok' => false,
                'error' => $body !== '' ? $body : 'Invalid HostPinnacle response',
                'raw' => null,
            ];
        }

        $nestedStatus = data_get($data, 'response.status');
        $topStatus = $data['status'] ?? null;

        if ($topStatus === 'success' || $nestedStatus === 'success') {
            return ['ok' => true, 'data' => $data];
        }

        $reason = $data['reason']
            ?? data_get($data, 'response.msg')
            ?? data_get($data, 'response.reason')
            ?? 'HostPinnacle request failed';

        return [
            'ok' => false,
            'error' => (string) $reason,
            'raw' => $data,
        ];
    }
}
