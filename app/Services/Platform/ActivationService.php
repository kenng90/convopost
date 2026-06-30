<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\User;
use Modules\Flowmaker\Models\Flow;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

class ActivationService
{
    public const STEP_WHATSAPP = 'whatsapp';

    public const STEP_TEST_MESSAGE = 'test_message';

    public const STEP_CONTACTS = 'contacts';

    public const STEP_FLOW = 'flow';

    /**
     * @return array<string, array{key: string, title: string, description: string, completed: bool, action_route: ?string, action_label: ?string}>
     */
    public function steps(Company $company): array
    {
        $whatsappDone = $this->isWhatsappConnected($company);
        $testDone = $this->isTestMessageSent($company);
        $contactsDone = $this->hasContacts($company);
        $flowDone = $this->hasActiveFlow($company);

        return [
            self::STEP_WHATSAPP => [
                'key' => self::STEP_WHATSAPP,
                'title' => __('Connect WhatsApp'),
                'description' => __('Link your WhatsApp Business number via Embedded Signup or manual Cloud API setup.'),
                'completed' => $whatsappDone,
                'action_route' => 'whatsapp.setup',
                'action_label' => __('Open setup'),
            ],
            self::STEP_TEST_MESSAGE => [
                'key' => self::STEP_TEST_MESSAGE,
                'title' => __('Send a test message'),
                'description' => __('Confirm outbound messaging works by sending a test reply from the inbox.'),
                'completed' => $testDone,
                'action_route' => $whatsappDone ? 'chat.index' : null,
                'action_label' => __('Open inbox'),
            ],
            self::STEP_CONTACTS => [
                'key' => self::STEP_CONTACTS,
                'title' => __('Add contacts'),
                'description' => __('Import or create at least one contact for campaigns and automations.'),
                'completed' => $contactsDone,
                'action_route' => 'contacts.index',
                'action_label' => __('Manage contacts'),
            ],
            self::STEP_FLOW => [
                'key' => self::STEP_FLOW,
                'title' => __('Launch your first flow'),
                'description' => __('Install a pre-built flow template or create automation from the library.'),
                'completed' => $flowDone,
                'action_route' => 'flows.index',
                'action_label' => __('Browse templates'),
            ],
        ];
    }

    public function progressPercent(Company $company): int
    {
        $steps = $this->steps($company);
        $completed = collect($steps)->where('completed', true)->count();

        return (int) round(($completed / max(count($steps), 1)) * 100);
    }

    public function isComplete(Company $company): bool
    {
        if ($company->getConfig('activation_skipped', 'no') === 'yes') {
            return true;
        }

        if ($company->getConfig('activation_completed', 'no') === 'yes') {
            return true;
        }

        return collect($this->steps($company))->every(fn (array $step) => $step['completed']);
    }

    public function markComplete(Company $company): void
    {
        $company->setConfig('activation_completed', 'yes');
    }

    public function skip(Company $company): void
    {
        $company->setConfig('activation_skipped', 'yes');
    }

    public function markTestMessageSent(Company $company): void
    {
        $company->setConfig('activation_test_message_sent', 'yes');
    }

    public function refreshAutoDetectedSteps(Company $company): void
    {
        if ($this->isTestMessageSent($company)) {
            $company->setConfig('activation_test_message_sent', 'yes');
        }

        if ($this->hasContacts($company)) {
            $company->setConfig('activation_contacts_imported', 'yes');
        }

        if ($this->hasActiveFlow($company)) {
            $company->setConfig('activation_flow_installed', 'yes');
        }

        if ($this->isComplete($company)) {
            $this->markComplete($company);
        }
    }

    public function isWhatsappConnected(Company $company): bool
    {
        return $company->getConfig('whatsapp_webhook_verified', 'no') === 'yes'
            && $company->getConfig('whatsapp_settings_done', 'no') === 'yes';
    }

    public function isTestMessageSent(Company $company): bool
    {
        if ($company->getConfig('activation_test_message_sent', 'no') === 'yes') {
            return true;
        }

        return Message::where('company_id', $company->id)
            ->where('is_message_by_contact', false)
            ->exists();
    }

    public function hasContacts(Company $company): bool
    {
        if ($company->getConfig('activation_contacts_imported', 'no') === 'yes') {
            return true;
        }

        return Contact::where('company_id', $company->id)->exists();
    }

    public function hasActiveFlow(Company $company): bool
    {
        if ($company->getConfig('activation_flow_installed', 'no') === 'yes') {
            return true;
        }

        return Flow::where('company_id', $company->id)
            ->whereNotNull('flow_data')
            ->where('flow_data', '!=', '')
            ->where('flow_data', '!=', '{}')
            ->exists();
    }

    public function shouldRedirectToActivation(Company $company): bool
    {
        return $this->isWhatsappConnected($company) && ! $this->isComplete($company);
    }

    public function shouldRedirectUserToActivation(User $user, Company $company): bool
    {
        return $user->hasRole('owner') && $this->shouldRedirectToActivation($company);
    }
}
