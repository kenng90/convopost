<?php

namespace Tests\Unit;

use App\Services\Messaging\MetaInstagramCredentialResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MetaInstagramCredentialResolverTest extends TestCase
{
    public function test_rejects_instagram_login_tokens(): void
    {
        $this->expectException(ValidationException::class);

        app(MetaInstagramCredentialResolver::class)->resolve(
            '452795984579637',
            '17841413486594880',
            'IGAAxxxxxxxx',
        );
    }

    public function test_rejects_token_missing_instagram_manage_messages(): void
    {
        Http::fake([
            '*/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'type' => 'PAGE',
                    'scopes' => ['pages_messaging', 'pages_show_list'],
                ],
            ]),
        ]);

        try {
            app(MetaInstagramCredentialResolver::class)->resolve(
                '452795984579637',
                '17841413486594880',
                'EAAxxxxxxxx',
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'instagram_manage_messages',
                $e->errors()['page_access_token'][0] ?? '',
            );
        }
    }

    public function test_rejects_page_without_linked_instagram_account(): void
    {
        Http::fake([
            '*/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'type' => 'PAGE',
                    'scopes' => ['pages_messaging', 'instagram_manage_messages'],
                ],
            ]),
            '*/452795984579637*' => Http::response([
                'id' => '452795984579637',
                'name' => 'ConvoConnect',
                'access_token' => 'EAA-page-token',
            ]),
        ]);

        try {
            app(MetaInstagramCredentialResolver::class)->resolve(
                '452795984579637',
                '17841413486594880',
                'EAAxxxxxxxx',
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'not linked',
                strtolower($e->errors()['page_id'][0] ?? ''),
            );
        }
    }

    public function test_exchanges_user_token_and_validates_linked_ig(): void
    {
        Http::fake([
            '*/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'type' => 'USER',
                    'scopes' => ['pages_messaging', 'instagram_manage_messages'],
                ],
            ]),
            '*/452795984579637*' => Http::response([
                'id' => '452795984579637',
                'name' => 'ConvoConnect',
                'access_token' => 'EAA-page-token',
                'instagram_business_account' => [
                    'id' => '17841413486594880',
                    'username' => 'convoconnect',
                ],
            ]),
        ]);

        $resolved = app(MetaInstagramCredentialResolver::class)->resolve(
            '452795984579637',
            '17841413486594880',
            'EAAuser',
        );

        $this->assertSame('452795984579637', $resolved['page_id']);
        $this->assertSame('17841413486594880', $resolved['instagram_account_id']);
        $this->assertSame('EAA-page-token', $resolved['page_access_token']);
        $this->assertSame('ConvoConnect', $resolved['page_name']);
        $this->assertSame('convoconnect', $resolved['ig_username']);
    }

    public function test_falls_back_to_me_accounts_when_page_node_requires_extra_permission(): void
    {
        Http::fake([
            '*/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'type' => 'USER',
                    'scopes' => ['pages_messaging', 'instagram_manage_messages', 'pages_show_list'],
                ],
            ]),
            '*/452795984579637*' => Http::response([
                'error' => [
                    'message' => "(#100) Object does not exist, cannot be loaded due to missing permission or reviewable feature, or does not support this operation. This endpoint requires the 'pages_read_engagement' permission",
                    'type' => 'OAuthException',
                    'code' => 100,
                ],
            ], 400),
            '*/me?*' => Http::response([
                'error' => [
                    'message' => '(#100) Tried accessing nonexisting field (accounts)',
                    'code' => 100,
                ],
            ], 400),
            '*/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => '452795984579637',
                        'name' => 'ConvoConnect',
                        'access_token' => 'EAA-page-token',
                        'instagram_business_account' => [
                            'id' => '17841413486594880',
                            'username' => 'convoconnect',
                        ],
                    ],
                ],
            ]),
        ]);

        $resolved = app(MetaInstagramCredentialResolver::class)->resolve(
            '452795984579637',
            '17841413486594880',
            'EAAuser',
        );

        $this->assertSame('452795984579637', $resolved['page_id']);
        $this->assertSame('EAA-page-token', $resolved['page_access_token']);
        $this->assertSame('convoconnect', $resolved['ig_username']);
    }

    public function test_rejects_mismatched_instagram_account_id(): void
    {
        Http::fake([
            '*/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'scopes' => ['pages_messaging', 'instagram_manage_messages'],
                ],
            ]),
            '*/452795984579637*' => Http::response([
                'id' => '452795984579637',
                'name' => 'ConvoConnect',
                'access_token' => 'EAA-page-token',
                'instagram_business_account' => [
                    'id' => '17841413486594880',
                    'username' => 'convoconnect',
                ],
            ]),
        ]);

        try {
            app(MetaInstagramCredentialResolver::class)->resolve(
                '452795984579637',
                '99999999999999999',
                'EAAxxxxxxxx',
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                '17841413486594880',
                $e->errors()['instagram_account_id'][0] ?? '',
            );
        }
    }
}
