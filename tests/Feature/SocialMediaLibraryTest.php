<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Social\Models\SocialMediaAsset;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostVersion;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Storage::fake('public');
        config(['social.media.disk' => 'public']);
    }

    public function test_owner_can_upload_media_to_library(): void
    {
        [$owner, $company] = $this->makeOwnerCompany();

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.media.store'), [
                'file' => UploadedFile::fake()->create('hero.jpg', 120, 'image/jpeg'),
            ]);

        $response->assertRedirect(route('social.media.index'));
        $response->assertSessionHas('status');

        $asset = SocialMediaAsset::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $this->assertNotNull($asset);
        $this->assertSame('image/jpeg', $asset->mime);
        Storage::disk('public')->assertExists($asset->path);
    }

    public function test_unused_media_can_be_deleted(): void
    {
        [$owner, $company] = $this->makeOwnerCompany();

        $asset = SocialMediaAsset::factory()->image()->create([
            'company_id' => $company->id,
            'disk' => 'public',
            'path' => 'social/media/'.$company->id.'/unused.jpg',
        ]);
        Storage::disk('public')->put($asset->path, 'fake-image');

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->delete(route('social.media.destroy', $asset));

        $response->assertRedirect(route('social.media.index'));
        $response->assertSessionHas('status');

        $this->assertSoftDeleted('social_media_assets', ['id' => $asset->id]);
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_media_attached_to_post_cannot_be_deleted(): void
    {
        [$owner, $company] = $this->makeOwnerCompany();

        $asset = SocialMediaAsset::factory()->image()->create([
            'company_id' => $company->id,
            'disk' => 'public',
            'path' => 'social/media/'.$company->id.'/used.jpg',
        ]);
        Storage::disk('public')->put($asset->path, 'fake-image');

        $post = SocialPost::factory()->create(['company_id' => $company->id]);
        SocialPostVersion::factory()->create([
            'social_post_id' => $post->id,
            'provider' => 'default',
            'media_ids' => [$asset->id],
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->delete(route('social.media.destroy', $asset));

        $response->assertRedirect(route('social.media.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('social_media_assets', [
            'id' => $asset->id,
            'deleted_at' => null,
        ]);
        Storage::disk('public')->assertExists($asset->path);
    }

    /**
     * @return array{0: User, 1: Company}
     */
    protected function makeOwnerCompany(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        return [$owner, $company];
    }
}
