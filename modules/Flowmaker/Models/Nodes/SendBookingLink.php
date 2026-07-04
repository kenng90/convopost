<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use App\Services\Flowmaker\FlowRunLogger;
use Modules\Flowmaker\Models\Contact;
use Modules\Reminders\Models\Source;
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
        $url = $this->resolveUrl($company, $linkType, $settings);

        if ($url === '') {
            $contact->sendMessage(__('Booking page is not available right now.'), false, false, 'TEXT');
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
            $contact->sendMessage($contact->changeVariables($header, $this->flow_id), false, false, 'TEXT');
        }

        $contact->sendMessage($body, false, false, 'TEXT');

        if ($footer !== '') {
            $contact->sendMessage($contact->changeVariables($footer, $this->flow_id), false, false, 'TEXT');
        }

        FlowRunLogger::log($this->flow_id, $contact->id, 'booking_link_sent', $this->id, $linkType);

        $this->routeToHandle($contact, 'success', $message, $data);

        return ['success' => true];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveUrl(Company $company, string $linkType, array $settings): string
    {
        $subdomain = (string) $company->subdomain;

        return match ($linkType) {
            'events' => app(EventCatalogService::class)->eventsEnabled($company)
                ? route('reminders.booking.events', ['subdomain' => $subdomain])
                : '',
            'service' => $this->serviceUrl($company, $settings),
            default => route('reminders.booking.catalog', ['subdomain' => $subdomain]),
        };
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
            'source' => $source->slug ?: $source->id,
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
