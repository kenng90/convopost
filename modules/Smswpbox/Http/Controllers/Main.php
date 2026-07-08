<?php

namespace Modules\Smswpbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Telephony\Sms\SmsSender;
use Illuminate\Http\Request;

class Main extends Controller
{
    public function __construct(
        private readonly SmsSender $smsSender,
    ) {
    }

    public function send(Request $request)
    {
        $message = $request->input('message');
        $phone = $request->input('phone');

        $company = $this->getCompany();
        $result = $this->smsSender->send($company, (string) $phone, (string) $message);

        if ($result->success) {
            return response()->json([
                'success' => true,
                'message' => $result->message,
                'transaction_id' => $result->providerMessageId,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result->message,
        ]);
    }

    public function getTemplates()
    {
        $company = $this->getCompany();

        $templates = array_filter(array_map(function ($i) use ($company) {
            return $company->getConfig("SMS_TEMPLATE_$i", '');
        }, range(1, 5)));

        $templates = array_map(function ($template) {
            return [
                'value' => $template,
                'name' => implode(' ', array_slice(explode(' ', str_replace(["\n", "\r"], ' ', $template)), 0, 4)),
            ];
        }, $templates);

        return response()->json($templates);
    }
}
