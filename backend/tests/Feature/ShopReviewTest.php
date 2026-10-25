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
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Pending')
            ->assertJsonPath('data.rating', 5);

        $this->getJson('/api/public/reviews')->assertJsonCount(0, 'data');

        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/reviews/'.$saved->json('data.id'), [
            'action' => 'approve',
        ])->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.shop_name', 'Ada Atelier');

        $this->getJson('/api/public/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.shop_name', 'Ada Atelier')
            ->assertJsonPath('data.0.state', 'Lagos')
            ->assertJsonPath('data.0.body', 'My measurements stay in one place.')
            ->assertJsonMissingPath('data.0.owner_email')
            ->assertJsonMissingPath('data.0.rejection_reason');

        Sanctum::actingAs($owner);

        $this->putJson('/api/review', [
            'rating' => 4,
            'body' => 'Updated after a busy week.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/public/reviews')->assertJsonCount(0, 'data');
        $this->putJson('/api/admin/reviews/'.$saved->json('data.id'), [
            'action' => 'approve',
        ])->assertForbidden();

        $this->assertSame(1, ShopReview::query()->count());
    }

    public function test_rejected_and_hidden_reviews_stay_off_the_public_site(): void
    {
        $owner = User::factory()->create();
        Business::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Ada Atelier',
        ]);
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($owner);
        $this->putJson('/api/review', [
            'rating' => 5,
            'body' => '   ',
        ])->assertStatus(422);

        $saved = $this->putJson('/api/review', [
            'rating' => 5,
            'body' => 'Jobs are easy to find.',
        ])->assertOk();

        $this->putJson('/api/review', [
            'rating' => 4,
            'body' => 'Still one review for this shop.',
        ])->assertOk();
        $this->assertSame(1, ShopReview::query()->count());

        Sanctum::actingAs($admin);
        $id = $saved->json('data.id');

        $this->putJson('/api/admin/reviews/'.$id, [
            'action' => 'reject',
            'reason' => 'Too short to post.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.status_label', 'Not approved')
            ->assertJsonPath('data.rejection_reason', 'Too short to post.');

        $this->getJson('/api/public/reviews')->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/reviews?status=rejected&q=ada')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.shop_name', 'Ada Atelier');

        $this->putJson('/api/admin/reviews/'.$id, [
            'action' => 'approve',
        ])->assertOk();
        $this->getJson('/api/public/reviews')->assertJsonCount(1, 'data');

        $this->putJson('/api/admin/reviews/'.$id, [
            'action' => 'hide',
        ])->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/public/reviews')->assertJsonCount(0, 'data');

        $review = ShopReview::query()->findOrFail($id);
        $this->assertNotNull($review->moderated_at);
        $this->assertSame($admin->id, $review->moderated_by);
    }
}
