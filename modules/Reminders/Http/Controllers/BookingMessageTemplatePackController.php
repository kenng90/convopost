<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Reminders\Services\BookingMessageTemplatePackService;

class BookingMessageTemplatePackController extends Controller
{
    public function install(BookingMessageTemplatePackService $pack): RedirectResponse
    {
        $this->ownerAndStaffOnly();

        $company = $this->getCompany();
        if (! $company) {
            abort(403);
        }

        $result = $pack->installForCompany($company);

        if ($result['status'] === 'missing_credentials') {
            return redirect()
                ->route('whatsapp.setup')
                ->withStatus($result['message']);
        }

        $redirect = redirect()->route('reminders.overview.index');

        if (! $result['success']) {
            return $redirect->withStatus($result['message']);
        }

        return $redirect->withStatus($result['message']);
    }
}
