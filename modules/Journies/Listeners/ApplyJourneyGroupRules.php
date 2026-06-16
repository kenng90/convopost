<?php

namespace Modules\Journies\Listeners;

use App\Models\Company;
use Modules\Journies\Services\JourneyContactService;
use Modules\Journies\Services\JourneySettings;
use Modules\Wpbox\Models\Contact;

class ApplyJourneyGroupRules
{
    public function handle(Contact $contact, int $groupId): void
    {
        $company = Company::find($contact->company_id);

        if (! $company || ! JourneySettings::for($company)->isEnabled()) {
            return;
        }

        app(JourneyContactService::class)->applyGroupRules($contact, $groupId);
    }
}
