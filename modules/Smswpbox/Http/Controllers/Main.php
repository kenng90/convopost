<?php

namespace Modules\Smswpbox\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Support\Facades\Http;

class Main extends Controller
{
   
    public function send(Request $request)
    {
        //Get the message and phone number from the request
        $message = $request->input('message');
        $phone = $request->input('phone');

        //Get the company
        $company = $this->getCompany();

        //Get the Twilio settings from the the company settings
        $twilioAccountSid = $company->getConfig('TWILIO_ACCOUNT_SID', '');
        $twilioAuthToken = $company->getConfig('TWILIO_AUTH_TOKEN', '');
        $twilioFromNumber = $company->getConfig('TWILIO_FROM_NUMBER', '');

        //if some of the settings are missing, return an error
        if (empty($twilioAccountSid) || empty($twilioAuthToken) || empty($twilioFromNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Twilio settings are missing, set them in the App Settings in the SMS Twilio tab'
            ]);
        }

        //Sen the SMS using Twilio
        $response = Http::withBasicAuth($twilioAccountSid, $twilioAuthToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$twilioAccountSid}/Messages.json", [
                'To' => $phone,
                'From' => $twilioFromNumber,
                'Body' => $message
            ]);

        //Parse, check and return the response
        $response = json_decode($response->body());
        if ($response->status == 'queued') {
            return response()->json([
                'success' => true,
                'message' => 'SMS sent successfully'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'SMS failed to send, error: ' . ($response->error_message ?? $response->message ?? 'Unknown error')
            ]);
        }
    }


    public function getTemplates()
    {
        //Get the company
        $company = $this->getCompany();

        //Get the templates from the company settings, make sure to return them as an array. Only return the ones that are not empty
        $templates = array_filter(array_map(function($i) use ($company) {
            return $company->getConfig("SMS_TEMPLATE_$i", '');
        }, range(1, 5)));

        //For each of the template, the the first 10 words, and make an associative array with the key as the template name and the value as the first 10 words
        $templates = array_map(function($template) {
            return [
                'value' => $template,
                'name' => implode(' ', array_slice(explode(' ', str_replace(["\n", "\r"], ' ', $template)), 0, 4))
            ];
        }, $templates);

        return response()->json($templates);
    }
}
