<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SlipScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_shop_can_scan_a_slip_photo_and_match_an_existing_customer(): void
    {
        config([
            'services.slip_ocr.driver' => 'openai',
            'services.slip_ocr.api_key' => 'test-key',
            'services.slip_ocr.base_url' => 'https://ocr.test/v1',
            'services.slip_ocr.model' => 'gpt-4o-mini',
        ]);

        Http::fake([
            'https://ocr.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'name' => 'Ada Okafor',
                            'phone' => '08031234567',
                            'gender' => 'female',
                            'style' => 'Ceremony dress',
                            'note' => 'Saturday fitting',
                            'template_slug' => 'female',
                            'unit' => 'in',
                            'measurements' => [
                                'bust' => 36,
                                'waist' => 28,
                                'unknown_field' => 12,
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $user = User::factory()->create();
        $business = Business::factory()->create(['user_id' => $user->id]);
        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'name' => 'Ada',
            'phone' => '2348031234567',
        ]);

        Sanctum::actingAs($user);

        $this->post('/api/scan/slip', [
            'image' => UploadedFile::fake()->image('slip.jpg', 800, 600),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ada Okafor')
            ->assertJsonPath('data.phone', '08031234567')
            ->assertJsonPath('data.style', 'Ceremony dress')
            ->assertJsonPath('data.template_slug', 'female')
            ->assertJsonPath('data.measurements.bust', 36)
            ->assertJsonPath('data.measurements.waist', 28)
            ->assertJsonMissingPath('data.measurements.unknown_field')
            ->assertJsonPath('data.matched_customer.id', $customer->id);
    }

    public function test_scan_fails_clearly_when_ocr_is_not_configured(): void
    {
        config([
            'services.slip_ocr.driver' => 'openai',
            'services.slip_ocr.api_key' => null,
        ]);

        $user = User::factory()->create();
        Business::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->post('/api/scan/slip', [
            'image' => UploadedFile::fake()->image('slip.jpg'),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Slip scanning is not configured. Set SLIP_OCR_API_KEY (or OPENAI_API_KEY) on the server.',
            );
    }

    public function test_fake_driver_returns_sample_slip_data(): void
    {
        config(['services.slip_ocr.driver' => 'fake']);

        $user = User::factory()->create();
        Business::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->post('/api/scan/slip', [
            'image' => UploadedFile::fake()->image('slip.jpg'),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ada Okafor')
            ->assertJsonPath('data.phone', '08031234567');
    }
}
