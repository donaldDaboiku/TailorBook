<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\ShopReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShopReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shop_review_appears_on_the_site_only_after_admin_approval(): void
    {
        $owner = User::factory()->create(['email' => 'ada@example.com']);
        Business::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Ada Atelier',
            'state' => 'Lagos',
        ]);
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($owner);

        $this->getJson('/api/public/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $saved = $this->putJson('/api/review', [
            'rating' => 5,
            'body' => 'My measurements stay in one place.',
        ])->assertOk()
            ->assertJsonPath('data.approved', false)
            ->assertJsonPath('data.rating', 5);

        $this->getJson('/api/public/reviews')->assertJsonCount(0, 'data');

        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/reviews/'.$saved->json('data.id'), [
            'approved' => true,
        ])->assertOk()
            ->assertJsonPath('data.approved', true)
            ->assertJsonPath('data.shop_name', 'Ada Atelier');

        $this->getJson('/api/public/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.shop_name', 'Ada Atelier')
            ->assertJsonPath('data.0.state', 'Lagos')
            ->assertJsonPath('data.0.body', 'My measurements stay in one place.')
            ->assertJsonMissingPath('data.0.owner_email');

        Sanctum::actingAs($owner);

        $this->putJson('/api/review', [
            'rating' => 4,
            'body' => 'Updated after a busy week.',
        ])->assertOk()
            ->assertJsonPath('data.approved', false);

        $this->getJson('/api/public/reviews')->assertJsonCount(0, 'data');
        $this->putJson('/api/admin/reviews/'.$saved->json('data.id'), [
            'approved' => true,
        ])->assertForbidden();

        $this->assertSame(1, ShopReview::query()->count());
    }
}
