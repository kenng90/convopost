<?php

namespace App\Services\Campaign;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Modules\Contacts\Models\Field;
use Modules\Contacts\Models\Group;
use Modules\Wpbox\Models\CampaignSegment;
use Modules\Wpbox\Models\Contact;

class CampaignAudienceResolver
{
    /**
     * @param  array<string, mixed>  $options  group_id, segment_id, contact_id, phones (array)
     * @return array{total_count: int, subscribed_count: int, excluded_count: int, contacts: \Illuminate\Support\Collection}
     */
    public function resolve(Company $company, array $options = []): array
    {
        $query = Contact::query()->where('company_id', $company->id);

        if (! empty($options['contact_id'])) {
            $query->where('id', $options['contact_id']);
        } elseif (! empty($options['segment_id'])) {
            $segment = CampaignSegment::where('company_id', $company->id)
                ->find($options['segment_id']);

            if ($segment) {
                $this->applySegmentFilters($query, $segment->filters ?? []);
            }
        } elseif (isset($options['group_id']) && $options['group_id'] !== null && (string) $options['group_id'] !== '0') {
            $group = Group::where('company_id', $company->id)->find($options['group_id']);

            if ($group) {
                $contactIds = $group->contacts()->pluck('contacts.id');
                $query->whereIn('id', $contactIds);
            }
        } elseif (! empty($options['phones']) && is_array($options['phones'])) {
            $query->whereIn('phone', $options['phones']);
        }

        $total = (clone $query)->count();
        $subscribed = (clone $query)->where('subscribed', 1)->count();

        return [
            'total_count' => $total,
            'subscribed_count' => $subscribed,
            'excluded_count' => max(0, $total - $subscribed),
            'contacts' => (clone $query)->where('subscribed', 1)->get(),
        ];
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
