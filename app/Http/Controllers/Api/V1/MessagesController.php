<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Api\PublicApiResponse;
use App\Services\Api\PublicMessageService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Wpbox\Models\Message;

class MessagesController extends Controller
{
    public function __construct(private readonly PublicMessageService $messages)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => 'nullable|in:whatsapp,sms,instagram,messenger',
            'to' => 'required_without_all:phone,contact_id|string',
            'phone' => 'required_without_all:to,contact_id|string',
            'contact_id' => 'required_without_all:to,phone|integer',
            'body' => 'required_without_all:message,template_name,action,media_url,image',
            'message' => 'required_without_all:body,template_name,action,media_url,image',
            'template_name' => 'nullable|string',
            'template_language' => 'required_with:template_name|string',
            'action' => 'nullable',
        ]);

        return $this->messages->send($request, $request->attributes->get('public_api_company'));
    }

    public function show(Request $request, int $message): JsonResponse
    {
        $company = $request->attributes->get('public_api_company');
        $model = Message::query()
            ->where('company_id', $company->id)
            ->find($message);

        if (! $model) {
            throw new HttpResponseException(PublicApiResponse::error('not_found', 'Message not found', 404));
        }

        return PublicApiResponse::success($this->messages->present($model));
    }
}
