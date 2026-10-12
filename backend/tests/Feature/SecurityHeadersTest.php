<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class SecurityHeadersTest extends TestCase
{
    public function test_api_responses_include_security_headers(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_database_backup_writes_a_sql_dump_when_pg_dump_is_installed(): void
    {
        $probe = new Process(['pg_dump', '--version']);
        $probe->run();

        if (! $probe->isSuccessful()) {
            $this->markTestSkipped('pg_dump is not installed.');
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $this->markTestSkipped('PostgreSQL is not running.');
        }

        $directory = storage_path('app/private/backups');
        $before = collect(File::exists($directory) ? File::files($directory) : [])
            ->map(fn ($file) => $file->getPathname())
            ->all();

        $this->artisan('backup:database')->assertSuccessful();

        $created = collect(File::files($directory))
            ->map(fn ($file) => $file->getPathname())
            ->diff($before)
            ->values();

        $this->assertNotEmpty($created);
        $this->assertGreaterThan(0, filesize($created->first()));
        File::delete($created->all());
    }
}
