<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Api\PublicApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contacts\Models\Field;
use Modules\Contacts\Models\Group;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Traits\Contacts;

class ContactsController extends Controller
{
    use Contacts;

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request);
        [$contacts, $meta] = $this->paginateContacts($request, $company);

        return PublicApiResponse::success($contacts, 200, $meta);
    }

    public function show(Request $request, int $contact): JsonResponse
    {
        $model = $this->findContact($request, $contact);

        return PublicApiResponse::success($this->present($model));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|max:50',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'groups' => 'nullable',
            'custom' => 'nullable|array',
        ]);

        $company = $this->company($request);
        $contact = $this->getOrMakeContact($request->phone, $company, $request->input('name', $request->phone));
        $contact->update(array_filter([
            'name' => $request->input('name', $contact->name),
            'email' => $request->input('email', $contact->email),
        ], fn ($value) => $value !== null));

        $this->syncGroupsAndFields($request, $contact, $company);
        $contact->load('groups', 'fields');

        return PublicApiResponse::success($this->present($contact), 201);
    }

    public function update(Request $request, int $contact): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
        ]);

        $model = $this->findContact($request, $contact);
        $model->update($request->only(['name', 'email', 'phone']));

        return PublicApiResponse::success($this->present($model->fresh()));
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    public function paginateContacts(Request $request, Company $company): array
    {
        $limit = min(max((int) $request->input('limit', config('public-api.pagination.default_limit', 50)), 1), (int) config('public-api.pagination.contacts_hard_cap', 100));
        $query = Contact::query()->where('company_id', $company->id)->orderByDesc('id');

        if ($request->filled('cursor')) {
            $query->where('id', '<', (int) $request->input('cursor'));
        }

        if ($request->filled('phone')) {
            $query->where('phone', $request->input('phone'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $presented = $rows->map(fn (Contact $contact) => $this->present($contact))->values()->all();

        return [$presented, [
            'limit' => $limit,
            'next_cursor' => $hasMore ? (string) $rows->last()?->id : null,
            'has_more' => $hasMore,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'has_chat' => (bool) $contact->has_chat,
            'resolved' => (bool) $contact->resolved_chat,
            'assigned_user_id' => $contact->user_id,
            'created_at' => optional($contact->created_at)?->toIso8601String(),
        ];
    }

    private function findContact(Request $request, int $id): Contact
    {
        $contact = Contact::query()
            ->where('company_id', $this->company($request)->id)
            ->find($id);

        if (! $contact) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                PublicApiResponse::error('not_found', 'Contact not found', 404)
            );
        }

        return $contact;
    }

    private function company(Request $request): Company
    {
        return $request->attributes->get('public_api_company');
    }

    private function syncGroupsAndFields(Request $request, Contact $contact, Company $company): void
    {
        if ($request->filled('groups')) {
            $groups = is_array($request->groups) ? $request->groups : explode(',', (string) $request->groups);
            $groupIds = [];
            foreach (array_filter($groups) as $groupName) {
                $group = Group::firstOrCreate(['name' => trim((string) $groupName), 'company_id' => $company->id]);
                $groupIds[] = $group->id;
            }
            $contact->groups()->sync($groupIds);
        }

        if ($request->has('custom') && is_array($request->custom)) {
            $contact->fields()->sync([]);
            foreach ($request->custom as $key => $value) {
                if (! $value) {
                    continue;
                }
                $field = Field::firstOrCreate(['name' => $key, 'company_id' => $company->id]);
                $contact->fields()->attach($field->id, ['value' => $value]);
            }
        }
    }
}
