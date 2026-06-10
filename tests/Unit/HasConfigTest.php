<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Config;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HasConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_set_multiple_config_replaces_duplicate_rows_and_reads_latest_value(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        Config::create([
            'key' => 'EMAIL_TEMPLATE_1_BODY',
            'value' => 'Old seeded template body',
            'model_type' => 'App\Models\Company',
            'model_id' => $company->id,
        ]);

        Config::create([
            'key' => 'EMAIL_TEMPLATE_1_BODY',
            'value' => 'Even older duplicate body',
            'model_type' => 'App\Models\Company',
            'model_id' => $company->id,
        ]);

        $company->setMultipleConfig([
            'EMAIL_TEMPLATE_1_BODY' => 'Updated template body from settings form',
        ]);

        $this->assertSame(
            'Updated template body from settings form',
            $company->getConfig('EMAIL_TEMPLATE_1_BODY')
        );

        $this->assertSame(
            'Updated template body from settings form',
            $company->getAllConfigs()['EMAIL_TEMPLATE_1_BODY']
        );

        $this->assertSame(
            1,
            Config::query()
                ->where('key', 'EMAIL_TEMPLATE_1_BODY')
                ->where('model_type', 'App\Models\Company')
                ->where('model_id', $company->id)
                ->count()
        );
    }
}
