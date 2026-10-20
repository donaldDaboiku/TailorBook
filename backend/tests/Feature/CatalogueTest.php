<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CatalogueItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shop_can_keep_styles_and_another_shop_cannot_remove_them(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $business = Business::factory()->create(['user_id' => $user->id, 'currency' => 'NGN']);
        Sanctum::actingAs($user);

        $created = $this->post('/api/catalogue', [
            'name' => 'Senator',
            'price' => '45000',
            'image' => UploadedFile::fake()->image('senator.jpg', 40, 40),
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Senator')
            ->assertJsonPath('data.price', '45000.00')
            ->assertJsonPath('data.price_label', '₦45,000.00');

        $this->assertStringStartsWith('data:image/', (string) $created->json('data.image_url'));

        $this->getJson('/api/catalogue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Senator');

        $other = User::factory()->create();
        Business::factory()->create(['user_id' => $other->id]);
        Sanctum::actingAs($other);

        $this->deleteJson('/api/catalogue/'.$created->json('data.id'))->assertNotFound();

        Sanctum::actingAs($user);
        $this->deleteJson('/api/catalogue/'.$created->json('data.id'))
            ->assertOk();

        $this->assertSame(0, CatalogueItem::query()->where('business_id', $business->id)->count());
    }
}
