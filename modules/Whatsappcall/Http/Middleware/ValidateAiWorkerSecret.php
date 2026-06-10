<?php

namespace Modules\Whatsappcall\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Modules\Whatsappcall\Models\Call as CallModel;
use Symfony\Component\HttpFoundation\Response;

class ValidateAiWorkerSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = $request->header('X-Worker-Secret', '');
        $globalSecret = config('whatsappcall.ai_worker_secret', '');

        if ($globalSecret !== '' && hash_equals($globalSecret, (string) $provided)) {
            return $next($request);
        }

        $call = $request->route('call');
        if ($call instanceof CallModel) {
            $company = Company::find($call->company_id);
            $companySecret = $company?->getConfig('whatsapp_ai_worker_secret', '') ?? '';
            if ($companySecret !== '' && hash_equals($companySecret, (string) $provided)) {
                return $next($request);
            }
        }

        $companyId = $request->input('company_id');
        if ($companyId) {
            $company = Company::find($companyId);
            $companySecret = $company?->getConfig('whatsapp_ai_worker_secret', '') ?? '';
            if ($companySecret !== '' && hash_equals($companySecret, (string) $provided)) {
                return $next($request);
            }
        }

        return response()->json(['ok' => false, 'error' => 'Unauthorized worker'], 401);
    }
}
