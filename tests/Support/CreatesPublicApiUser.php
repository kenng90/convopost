<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use Spatie\Permission\Models\Role;

trait CreatesPublicApiUser
{
    protected User $apiUser;

    protected Company $apiCompany;

    protected Plans $apiPlan;

    protected string $apiToken;

    /**
     * @param  array<int, string>|null  $capabilities
     */
    protected function createPublicApiOwner(?array $capabilities = null): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $this->apiPlan = Plans::create([
            'name' => $capabilities === null ? 'Agency' : 'Starter',
            'limit_items' => 0,
            'limit_orders' => 0,
            'limit_views' => 0,
            'price' => $capabilities === null ? 299 : 29,
            'period' => 1,
            'description' => $capabilities === null ? 'Agency' : 'Starter',
            'features' => $capabilities === null ? 'Agency' : 'Starter',
        ]);

        if ($capabilities !== null) {
            $this->apiPlan->setConfig('capabilities', json_encode($capabilities));
        }

        $this->apiUser = User::factory()->create(['plan_id' => $this->apiPlan->id]);
        $this->apiUser->assignRole('owner');
        $this->apiCompany = Company::factory()->create(['user_id' => $this->apiUser->id]);
        $this->apiUser->update(['company_id' => $this->apiCompany->id]);
        $this->apiToken = $this->apiUser->createToken('public-api-test')->plainTextToken;

        config(['settings.enable_credits' => false]);
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    protected function publicApiHeaders(array $extra = []): array
    {
        return array_merge([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/json',
            'X-Company-Id' => (string) $this->apiCompany->id,
        ], $extra);
    }
}
