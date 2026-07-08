<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HostPinnacleWebhookController extends Controller
{
    public function deliveryReport(Request $request)
    {
        Log::info('HostPinnacle DLR received', [
            'payload' => $request->all(),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
