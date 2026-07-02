<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsEnvBoolUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $originalEnvContents;

    protected function setUp(): void
    {
        parent::setUp();

        config(['settings.is_demo' => false]);

        $this->originalEnvContents = file_get_contents(app()->environmentFilePath());
    }

    protected function tearDown(): void
    {
        file_put_contents(app()->environmentFilePath(), $this->originalEnvContents);

        parent::tearDown();
    }

    public function test_unchecked_bool_env_field_defaults_to_zero_when_missing_from_request(): void
    {
        $this->prepareEnvWithEnablePricing('1');

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->put(route('admin.settings.update', 1), $this->settingsPayload([
            'APP_NAME' => 'Test Site',
        ]));

        $response->assertRedirect(route('admin.settings.index'));
        $this->assertEnvValue('ENABLE_PRICING', '0');
    }

    public function test_unchecked_bool_env_field_submitted_as_zero_from_hidden_input(): void
    {
        $this->prepareEnvWithEnablePricing('1');

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->put(route('admin.settings.update', 1), $this->settingsPayload([
            'APP_NAME' => 'Test Site',
            'ENABLE_PRICING' => '0',
        ]));

        $response->assertRedirect(route('admin.settings.index'));
        $this->assertEnvValue('ENABLE_PRICING', '0');
    }

    public function test_checked_bool_env_field_submitted_as_one(): void
    {
        $this->prepareEnvWithEnablePricing('0');

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->put(route('admin.settings.update', 1), $this->settingsPayload([
            'APP_NAME' => 'Test Site',
            'ENABLE_PRICING' => '1',
        ]));

        $response->assertRedirect(route('admin.settings.index'));
        $this->assertEnvValue('ENABLE_PRICING', '1');
    }

    private function createAdmin(): User
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * @param  array<string, string>  $env
     * @return array<string, mixed>
     */
    private function settingsPayload(array $env): array
    {
        return [
            'env' => $env,
            'jsfront' => '',
            'jsback' => '',
            'cssfront' => '',
            'cssback' => '',
            'jsfrontmenu' => '',
            'cssfrontmenu' => '',
        ];
    }

    private function prepareEnvWithEnablePricing(string $value): void
    {
        $envPath = app()->environmentFilePath();
        $contents = file_get_contents($envPath);

        if (preg_match('/^ENABLE_PRICING=.*$/m', $contents)) {
            $contents = preg_replace('/^ENABLE_PRICING=.*$/m', "ENABLE_PRICING={$value}", $contents);
        } else {
            $contents .= "\nENABLE_PRICING={$value}\n";
        }

        file_put_contents($envPath, $contents);
    }

    private function assertEnvValue(string $key, string $expectedValue): void
    {
        $contents = file_get_contents(app()->environmentFilePath());

        $this->assertMatchesRegularExpression(
            '/^'.preg_quote($key, '/').'='.preg_quote($expectedValue, '/').'$/m',
            $contents
        );
    }
}
