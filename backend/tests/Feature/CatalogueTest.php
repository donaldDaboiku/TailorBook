<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\CatalogueItem;
use App\Models\User;
use App\Services\PaystackBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

        $created->assertJsonPath('data.has_image', true);
        $this->assertStringNotContainsString('base64', (string) $created->getContent());

        $image = $this->get('/api/catalogue/'.$created->json('data.id').'/image')->assertOk();
        $this->assertStringStartsWith('image/', (string) $image->headers->get('content-type'));

        $list = $this->getJson('/api/catalogue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Senator')
            ->assertJsonPath('data.0.has_image', true);
        $this->assertStringNotContainsString('base64', (string) $list->getContent());

        $other = User::factory()->create();
        Business::factory()->create(['user_id' => $other->id]);
        Sanctum::actingAs($other);

        $this->get('/api/catalogue/'.$created->json('data.id').'/image')->assertNotFound();
        $this->deleteJson('/api/catalogue/'.$created->json('data.id'))->assertNotFound();

        Sanctum::actingAs($user);
        $this->deleteJson('/api/catalogue/'.$created->json('data.id'))
            ->assertOk();

        $this->assertSame(0, CatalogueItem::query()->where('business_id', $business->id)->count());
    }

    public function test_a_shop_cannot_store_a_41st_style(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        for ($i = 0; $i < 40; $i++) {
            CatalogueItem::query()->create([
                'business_id' => $business->id,
                'name' => 'Style '.$i,
                'price' => '1000',
            ]);
        }

        $this->post('/api/catalogue', [
            'name' => 'One more',
            'price' => '1000',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'This shop is full at 40 styles. 10 more cost ₦1,000.00.')
            ->assertJsonPath('catalogue_full', true);

        $this->assertSame(40, CatalogueItem::query()->where('business_id', $business->id)->count());
    }

    public function test_paid_slots_allow_styles_past_the_free_forty(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'catalogue_extra' => 10,
        ]);
        Sanctum::actingAs($user);

        for ($i = 0; $i < 40; $i++) {
            CatalogueItem::query()->create([
                'business_id' => $business->id,
                'name' => 'Style '.$i,
                'price' => '1000',
            ]);
        }

        $this->post('/api/catalogue', [
            'name' => 'Paid style',
            'price' => '1000',
        ])->assertCreated();

        $this->getJson('/api/catalogue')
            ->assertOk()
            ->assertJsonCount(41, 'data')
            ->assertJsonPath('allowance.limit', 50)
            ->assertJsonPath('allowance.used', 41);
    }

    public function test_a_full_shop_pays_one_thousand_naira_for_ten_styles(): void
    {
        config([
            'services.paystack.secret_key' => 'sk_test_dummy',
            'services.paystack.callback_url' => 'http://localhost:5173/?billing=1',
        ]);

        $until = now()->addDays(20)->toDateString();
        $user = User::factory()->create([
            'subscription_status' => SubscriptionStatus::Subscribed,
            'subscribed_until' => $until,
        ]);
        $business = Business::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        for ($i = 0; $i < 40; $i++) {
            CatalogueItem::query()->create([
                'business_id' => $business->id,
                'name' => 'Style '.$i,
                'price' => '1000',
            ]);
        }

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/styles',
                ],
            ]),
        ]);

        $checkout = $this->postJson('/api/catalogue/extra')
            ->assertOk()
            ->assertJsonPath('data.authorization_url', 'https://checkout.paystack.com/styles');

        $reference = $checkout->json('data.reference');
        $this->assertDatabaseHas('subscription_payments', [
            'reference' => $reference,
            'amount' => 100000,
            'purpose' => 'catalogue',
            'status' => 'pending',
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 100000,
                    'currency' => 'NGN',
                    'reference' => $reference,
                ],
            ]),
        ]);

        $this->postJson('/api/billing/verify', ['reference' => $reference])
            ->assertOk()
            ->assertJsonPath('data.subscription_status', 'subscribed');

        $fresh = $user->fresh();
        $this->assertSame($until, $fresh->subscribed_until->toDateString());
        $this->assertSame(10, $fresh->business->catalogue_extra);

        app(PaystackBilling::class)->markRefunded($reference);

        $refunded = $user->fresh();
        $this->assertSame(SubscriptionStatus::Subscribed, $refunded->subscription_status);
        $this->assertSame($until, $refunded->subscribed_until->toDateString());
        $this->assertSame(0, $refunded->business->catalogue_extra);
    }

    public function test_a_shop_with_room_cannot_buy_styles_yet(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_dummy']);

        $user = User::factory()->create();
        Business::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/catalogue/extra')
            ->assertStatus(422)
            ->assertJsonPath('message', 'This shop can still add styles before paying.');

        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_a_rejected_style_does_not_keep_its_photo(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Business::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        CatalogueItem::creating(function (): void {
            throw new \RuntimeException('style was not saved');
        });

        $this->post('/api/catalogue', [
            'name' => 'Senator',
            'price' => '45000',
            'image' => UploadedFile::fake()->image('senator.jpg', 40, 40),
        ])->assertStatus(500);

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, CatalogueItem::query()->count());
    }

    public function test_a_style_can_keep_front_back_and_side_photos(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Business::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $created = $this->post('/api/catalogue', [
            'name' => 'Agbada',
            'price' => '80000',
            'image' => UploadedFile::fake()->image('front.jpg', 40, 40),
            'image_back' => UploadedFile::fake()->image('back.jpg', 40, 40),
            'image_side' => UploadedFile::fake()->image('side.jpg', 40, 40),
        ])->assertCreated()
            ->assertJsonPath('data.has_image', true)
            ->assertJsonPath('data.has_back', true)
            ->assertJsonPath('data.has_side', true);

        $id = $created->json('data.id');
        $this->assertCount(3, Storage::disk('local')->allFiles());
        $this->get('/api/catalogue/'.$id.'/image')->assertOk();
        $this->get('/api/catalogue/'.$id.'/image?view=back')->assertOk();
        $this->get('/api/catalogue/'.$id.'/image?view=side')->assertOk();
        $this->get('/api/catalogue/'.$id.'/image?view=top')->assertNotFound();

        $this->deleteJson('/api/catalogue/'.$id)->assertOk();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
