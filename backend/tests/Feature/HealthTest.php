<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_returns_the_configured_app_name(): void
    {
        config(['app.name' => 'Sample Shop']);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->getJson('/api/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'app' => 'Sample Shop',
            ])
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
