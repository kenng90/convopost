<?php

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Platform\Customer360Service;
use Illuminate\Http\JsonResponse;
use Modules\Wpbox\Models\Contact;

class Customer360Controller extends Controller
{
    public function __construct(private Customer360Service $customer360)
    {
    }

    public function show(Contact $contact): JsonResponse
    {
        $this->ownerAndStaffOnly();

        if ((int) $contact->company_id !== (int) $this->getCompany()->id) {
            abort(403);
        }

        return response()->json(
            $this->customer360->forContact($this->getCompany(), $contact)
        );
    }
}
