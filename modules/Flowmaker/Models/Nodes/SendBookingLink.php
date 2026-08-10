<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use App\Services\Flowmaker\FlowRunLogger;
use Modules\Flowmaker\Models\Contact;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\BookingManageTokenService;
use Modules\Reminders\Services\EventCatalogService;

class SendBookingLink extends Node
{
    public function process($message, $data): array
    {
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        if (! $contact) {
            return ['success' => false];
        }

        $company = Company::find($contact->company_id);

        if (! $company) {
            return ['success' => false];
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $linkType = (string) ($settings['link_type'] ?? 'appointments');
        $url = $this->resolveUrl($company, $contact, $linkType, $settings);

        if ($url === '') {
            $contact->sendMessage(__('Booking page is not available right now.'), false, false, 'TEXT', null, null, null, true);
            $this->routeToHandle($contact, 'error', $message, $data);

            return ['success' => false];
        }

        $contact->setContactState($this->flow_id, 'booking_share_url', $url);
        $contact->setContactState($this->flow_id, 'booking_link', $url);

        $body = (string) ($settings['message'] ?? __('Book online: {{booking_link}}'));
        $body = str_replace('{{booking_link}}', $url, $contact->changeVariables($body, $this->flow_id));

        $header = trim((string) ($settings['header'] ?? ''));
        $footer = trim((string) ($settings['footer'] ?? ''));

        if ($header !== '') {
            $contact->sendMessage($contact->changeVariables($header, $this->flow_id), false, false, 'TEXT', null, null, null, true);
        }

        $contact->sendMessage($body, false, false, 'TEXT', null, null, null, true);

        if ($footer !== '') {
            $contact->sendMessage($contact->changeVariables($footer, $this->flow_id), false, false, 'TEXT', null, null, null, true);
        }

        FlowRunLogger::log($this->flow_id, $contact->id, 'booking_link_sent', $this->id, $linkType);

        $this->routeToHandle($contact, 'success', $message, $data);

        return ['success' => true];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveUrl(Company $company, Contact $contact, string $linkType, array $settings): string
    {
        $subdomain = (string) $company->subdomain;
        $tokens = app(BookingManageTokenService::class);

        return match ($linkType) {
            'events' => app(EventCatalogService::class)->eventsEnabled($company)
                ? route('reminders.booking.events', ['subdomain' => $subdomain])
                : '',
            'service' => $this->serviceUrl($company, $settings),
            'manage' => $this->manageAppointmentUrl($company, $contact, $tokens),
            'manage_events' => $this->manageEventUrl($company, $contact, $tokens),
            default => route('reminders.booking.catalog', ['subdomain' => $subdomain]),
        };
    }

    private function manageAppointmentUrl(Company $company, Contact $contact, BookingManageTokenService $tokens): string
    {
        $upcoming = Reservation::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('contact_id', $contact->id)
            ->whereNull('cancelled_at')
            ->where('status', 1)
            ->where('start_date', '>', now())
            ->orderBy('start_date')
            ->first();

        if ($upcoming) {
            return $tokens->makeReservationUrl($company, $upcoming);
        }

        return $tokens->landingUrl($company, 'appointments');
    }

    private function manageEventUrl(Company $company, Contact $contact, BookingManageTokenService $tokens): string
    {
        if (! app(EventCatalogService::class)->eventsEnabled($company)) {
            return '';
        }

        $upcoming = EventRegistration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('contact_id', $contact->id)
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->whereNull('cancelled_at')
            ->whereHas('occurrence', fn ($q) => $q->where('starts_at', '>', now()))
            ->orderByDesc('id')
            ->first();

        if ($upcoming) {
            return $tokens->makeRegistrationUrl($company, $upcoming);
        }

        return $tokens->landingUrl($company, 'events');
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function serviceUrl(Company $company, array $settings): string
    {
        $sourceId = (int) ($settings['source_id'] ?? 0);
        $sourceName = trim((string) ($settings['source_name'] ?? ''));

        $source = null;

        if ($sourceId > 0) {
            $source = Source::queryForCompany($company->id)
                ->where('is_bookable', true)
                ->find($sourceId);
        } elseif ($sourceName !== '') {
            $source = Source::queryForCompany($company->id)
                ->where('is_bookable', true)
                ->where('name', $sourceName)
                ->first();
        }

        if (! $source) {
            return route('reminders.booking.catalog', ['subdomain' => $company->subdomain]);
        }

        return route('reminders.booking.widget', [
            'subdomain' => $company->subdomain,
            'source' => $source->name,
        ]);
    }

    private function routeToHandle(Contact $contact, string $handle, $message, $data): void
    {
        $next = $this->getNextNodeId($handle);

        if ($next) {
            $next->process($message, $data);
        }
    }

    protected function getNextNodeId($handleId = null)
    {
        foreach ($this->outgoingEdges as $edge) {
            if ($handleId === null || $edge->getSourceHandle() === (string) $handleId) {
                return $edge->getTarget();
            }
        }

        return null;
    }
}
