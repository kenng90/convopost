<?php

namespace Modules\Embeddedlogin\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Main extends Controller
{
    public $graph_url = 'https://graph.facebook.com/v22.0';

    public function start($code)
    {
        Log::info('Start');
        Log::info('Step 1: getFacebookAccessToken v22.0');
        //Step 1 - Exchange the code for the access tokens
        $accessTokenResult = $this->getFacebookAccessToken($code);
        try {
            $accessToken = $accessTokenResult['access_token'];
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid code - Error in the Facebook App.', 'info' => $accessTokenResult], 200);
        }

        //Step 2 - Debug the token, get the shared WABID
        $wabidResult = $this->getWabid($accessToken);
        try {
            Log::info($wabidResult);
            $userid = $wabidResult['data']['user_id'];
            $wabid = $this->resolveWabidFromDebugToken($wabidResult);

            //If no WABID found, return error
            if ($wabid === null) {
                Log::warning('Embedded signup: no WABA ID in debug_token granular_scopes', [
                    'scopes' => collect($wabidResult['data']['granular_scopes'] ?? [])->pluck('scope')->all(),
                    'app_id' => config('services.facebook.app_id'),
                    'config_id' => config('embeddedlogin.config_id'),
                ]);

                return response()->json([
                    'error' => 'No WABID found. Please check the app permissions',
                    'hint' => 'Ensure the Meta app Embedded Signup configuration grants whatsapp_business_management and whatsapp_business_messaging, and that convoconnect.tech is in Allowed domains.',
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error getting share WABAID'], 200);
        }

        //Step 3 - Get the phone number
        $phoneResult = $this->getPhoneNumber($accessToken, $wabid);
        try {
            $phone = $phoneResult['data'][0]['display_phone_number'];
            $phoneID = $phoneResult['data'][0]['id'];
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error getting phone number'], 200);
        }

        //Step 4 - Register phone number
        $phoneRegisterResult = $this->registerPhoneNumber($accessToken, $wabid, $phoneID);
        try {
            $phoneRegisterResult = $phoneRegisterResult['success'];
            if ($phoneRegisterResult) {
                //OK, continue with the registration
            } else {
                return response()->json(['error' => 'Error registering phone number'], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error registering phone number'], 200);
        }

        //Step 5 - Subscribe to the webhook
        $subscribedResult = $this->subscribeWebhook($accessToken, $wabid);
        try {
            $subscribedResult = $subscribedResult['success'];
            if ($subscribedResult) {
                //OK, continue with the registration
            } else {
                return response()->json(['error' => 'Error subscribing to the webhook'], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error subscribing to the webhook'], 200);
        }

        //Step 6 - Assign the data to the current user's company in new function
        $this->updateCompany($userid, $phone, $phoneID, $wabid, $accessToken);

        //Step 7 - Inform the user that the process was successful
        return response()->json(['status' => 'success', 'success' => 'Whatsapp Business Account successfully linked'], 200);

    }

    /**
     *Get the Facebook Access Token  from the Code returned from the embedded login
     */
    public function getFacebookAccessToken($code)
    {
        //Token
        //App id and App secret get from settings
        $appId = config('services.facebook.app_id');
        $appSecret = config('services.facebook.app_secret');

        //Log the appId and appSecret, code and url
        Log::info('--------------------------------');
        Log::info('appId: '.$appId);
        Log::info('appSecret: '.$appSecret);
        Log::info('code: '.$code);
        Log::info('url: '.$this->graph_url.'/oauth/access_token');
        Log::info('--------------------------------');

        $url = $this->graph_url.'/oauth/access_token';
        $params = [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];

        $response = Http::get($url, $params);
        Log::info($response->json());

        if ($response->successful()) {

            return $response->json();

        } else {
            // Handle error
            return null;
        }
    }

    private function resolveWabidFromDebugToken(?array $wabidResult): ?string
    {
        $granularScopes = $wabidResult['data']['granular_scopes'] ?? [];
        $preferredScopes = ['whatsapp_business_management', 'whatsapp_business_messaging'];

        foreach ($preferredScopes as $preferredScope) {
            foreach ($granularScopes as $scope) {
                if (
                    ($scope['scope'] ?? '') === $preferredScope
                    && ! empty($scope['target_ids'])
                ) {
                    return $scope['target_ids'][0];
                }
            }
        }

        return null;
    }

    public function getWabid($accessToken)
    {
        $url = $this->graph_url.'/debug_token';
        $appId = config('services.facebook.app_id');
        $appSecret = config('services.facebook.app_secret');

        $params = [
            'input_token' => $accessToken,
        ];

        //Log all the params
        try {
            Log::info('--------------------------------');
            Log::info('Get Wabid code 2');
            Log::info('appId: '.$appId);
            Log::info('appSecret: '.$appSecret);
            Log::info('params: '.json_encode($params));
            Log::info('url: '.$url);
            Log::info('--------------------------------');
        } catch (\Exception $e) {

        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$appId.'|'.$appSecret,
        ])->get($url, $params);

        Log::info('Get Wabid');
        Log::info($url);
        Log::info($params);
        try {
            Log::info($response->json());
            Log::info($response->body());
        } catch (\Exception $e) {
            Log::info($e);
        }

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    private function getPhoneNumber($accessToken, $wabid)
    {
        $url = $this->graph_url."/$wabid".'/phone_numbers';
        $params = [
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
        ])->get($url, $params);

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    private function registerPhoneNumber($accessToken, $wabid, $phoneID)
    {
        $url = $this->graph_url.'/'.$phoneID.'/register';
        $params = [
            'messaging_product' => 'whatsapp',
            'pin' => '212834',
        ];

        //return ['url'=>$url, 'params'=>$params];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
        ])->post($url, $params);

        Log::info('Register Phone Number');
        Log::info($url);
        Log::info($params);
        Log::info($response->json());

        if ($response->successful()) {
            //return ['url'=>$url, 'params'=>$params, 'response'=>$response->json()];
            return $response->json();
        }

        return null;
    }

    private function subscribeWebhook($accessToken, $wabid)
    {
        $url = $this->graph_url."/$wabid".'/subscribed_apps';
        $params = [];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
        ])->post($url, $params);

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    private function updateCompany($userid, $phone, $phoneID, $wabid, $accessToken)
    {
        //Get the current user
        $user = Auth::user();
        $company = $user->getCurrentCompany();

        $company->setConfig('whatsapp_permanent_access_token', $accessToken);
        $company->setConfig('whatsapp_business_account_id', $wabid);
        $company->setConfig('whatsapp_phone_number_id', $phoneID);
        $company->setConfig('whatsapp_webhook_verified', 'yes');
        $company->setConfig('whatsapp_settings_done', 'yes');

    }
}
