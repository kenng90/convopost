<?php

namespace App\Services\Campaign;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Modules\Contacts\Models\Field;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\CampaignSegment;
use Modules\Wpbox\Models\Contact;

class CampaignAudienceResolver
{
    /**
     * @param  array<string, mixed>  $options  group_id, segment_id, contact_id, phones, channel, messaging_channel
     * @return array{total_count: int, subscribed_count: int, excluded_count: int, contacts: \Illuminate\Support\Collection}
     */
    public function resolve(Company $company, array $options = []): array
    {
        $query = $this->baseQuery($company, $options);

        $total = (clone $query)->count();
        $subscribedQuery = (clone $query)->where('subscribed', 1);
        $this->applyDeliverabilityConstraints($subscribedQuery, $options);
        $subscribed = (clone $subscribedQuery)->count();

        return [
            'total_count' => $total,
            'subscribed_count' => $subscribed,
            'excluded_count' => max(0, $total - $subscribed),
            'contacts' => (clone $subscribedQuery)
                ->limit(max(1, (int) config('wpbox.campaign_audience_preview_limit', 500)))
                ->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function subscribedQuery(Company $company, array $options = []): Builder
    {
        $query = $this->baseQuery($company, $options)->where('subscribed', 1);
        $this->applyDeliverabilityConstraints($query, $options);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function subscribedCount(Company $company, array $options = []): int
    {
        return $this->subscribedQuery($company, $options)->count();
    }

    /**
     * Restrict to contacts that can actually receive the campaign channel.
     *
     * @param  array<string, mixed>  $options
     */
    public function applyDeliverabilityConstraints(Builder $query, array $options = []): void
    {
        $channel = (string) ($options['channel'] ?? '');

        if ($channel === '') {
            return;
        }

        if ($channel === Campaign::CHANNEL_EMAIL) {
            $query->whereNotNull('contacts.email')->where('contacts.email', '!=', '');

            return;
        }

        if (in_array($channel, [Campaign::CHANNEL_WHATSAPP, Campaign::CHANNEL_SMS], true)) {
            $query->whereNotNull('contacts.phone')
                ->where('contacts.phone', '!=', '');
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function baseQuery(Company $company, array $options): Builder
    {
        $query = Contact::query()->where('contacts.company_id', $company->id);

        if (! empty($options['contact_id'])) {
            $query->where('contacts.id', $options['contact_id']);
        } elseif (! empty($options['segment_id'])) {
            $segment = CampaignSegment::where('company_id', $company->id)
                ->find($options['segment_id']);

            if ($segment) {
                $this->applySegmentFilters($query, $segment->filters ?? []);
            }
        } elseif (isset($options['group_id']) && $options['group_id'] !== null && (string) $options['group_id'] !== '0') {
            $groupId = (int) $options['group_id'];

            $query->whereIn('contacts.id', function ($subquery) use ($groupId) {
                $subquery->select('contact_id')
                    ->from('groups_contacts')
                    ->where('group_id', $groupId);
            });
        } elseif (! empty($options['phones']) && is_array($options['phones'])) {
            $query->whereIn('phone', $options['phones']);
        }

        if (! empty($options['messaging_channel'])) {
            $this->applyMessagingChannelFilter($query, (string) $options['messaging_channel']);
        }

        return $query;
    }

    public function applyMessagingChannelFilter(Builder $query, string $channel): void
    {
        if ($channel === 'whatsapp') {
            $query->where(function (Builder $q) {
                $q->whereHas('channelIdentities', function (Builder $identity) {
                    $identity->where('channel', MessagingChannelType::Whatsapp->value);
                })->orWhere(function (Builder $legacy) {
                    $legacy->whereDoesntHave('channelIdentities')
                        ->whereNotNull('phone')
                        ->where('phone', '!=', '');
                });
            });

            return;
        }

        if (in_array($channel, [
            MessagingChannelType::Instagram->value,
            MessagingChannelType::Messenger->value,
            MessagingChannelType::Tiktok->value,
        ], true)) {
            $query->whereHas('channelIdentities', function (Builder $identity) use ($channel) {
                $identity->where('channel', $channel);
            });
        }
    }

    /**
     * @param  array<int, array{field: string, operator: string, value: string}>  $filters
     */
    public function applySegmentFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? '';

            if ($field === null || $field === '') {
                continue;
            }

            if ($field === 'subscribed') {
                $query->where('subscribed', $value === '1' || $value === 'yes' ? 1 : 0);

                continue;
            }

            if ($field === 'has_channel') {
                if ($value === '' || $value === null) {
                    continue;
                }
                $this->applyMessagingChannelFilter($query, (string) $value);

                continue;
            }

            if ($field === 'phone_present') {
                if ($value === '' || $value === null) {
                    continue;
                }
                if ($value === '1' || $value === 'yes') {
                    $query->whereNotNull('phone')->where('phone', '!=', '');
                } else {
                    $query->where(function (Builder $q) {
                        $q->whereNull('phone')->orWhere('phone', '');
                    });
                }

                continue;
            }

            if (str_starts_with((string) $field, 'field_')) {
                $fieldId = (int) str_replace('field_', '', $field);
                $fieldModel = Field::find($fieldId);

                if (! $fieldModel) {
                    continue;
                }

                $query->whereHas('fields', function ($q) use ($fieldModel, $operator, $value) {
                    $q->where('fields.id', $fieldModel->id);
                    $this->applyOperator($q, 'contact_fields.value', $operator, $value);
                });

                continue;
            }

            $this->applyOperator($query, $field, $operator, $value);
        }
    }

    private function applyOperator(Builder $query, string $column, string $operator, mixed $value): void
    {
        match ($operator) {
            'equals' => $query->where($column, '=', $value),
            'not_equals' => $query->where($column, '!=', $value),
            'contains' => $query->where($column, 'like', '%'.$value.'%'),
            'starts_with' => $query->where($column, 'like', $value.'%'),
            'greater_than' => $query->where($column, '>', $value),
            'less_than' => $query->where($column, '<', $value),
            default => $query->where($column, '=', $value),
        };
    }
}
