<?php

namespace Modules\Journies\Listeners;

use App\Models\Company;
use Modules\Contacts\Models\Contact;
use Modules\Journies\Services\JourneyContactService;
use Modules\Journies\Services\JourneySettings;

class EnrollContactOnCreate
{
    public function handle(Contact $contact): void
    {
        $company = Company::find($contact->company_id);

        if (! $company || ! JourneySettings::for($company)->isEnabled()) {
            return;
        }

        app(JourneyContactService::class)->enrollNewContactIfConfigured($contact);
    }
}
