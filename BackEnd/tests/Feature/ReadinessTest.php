<?php

namespace Tests\Feature;

use App\Services\Readiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        file_put_contents('/tmp/afra-ready-private.pem', $private);
        file_put_contents('/tmp/afra-ready-public.pem', openssl_pkey_get_details($key)['key']);
        config(['afra.private_key' => '/tmp/afra-ready-private.pem', 'afra.public_key' => '/tmp/afra-ready-public.pem', 'afra.previous_public_keys' => []]);
    }

    public function test_dependency_probe_is_not_a_release_acceptance_claim(): void
    {
        $this->getJson('/api/ready')->assertOk()->assertJsonPath('data.ready_for_requests', true)->assertJsonPath('data.release_acceptance', 'incomplete');
        $production = app(Readiness::class)->inspect(true);
        $this->assertFalse($production['ready_for_requests']);
        $this->assertFalse($production['checks']['production_environment']);
        $this->assertFalse($production['checks']['shared_cache_driver']);
        $this->artisan('afra:check', ['--production' => true])->assertFailed();
    }

    public function test_missing_signing_key_and_pending_migration_fail_without_disclosing_paths(): void
    {
        config(['afra.private_key' => '/tmp/afra-missing-ready-key.pem']);
        $this->getJson('/api/ready')->assertStatus(503)->assertJsonMissingPath('data.checks');
        $state = app(Readiness::class)->inspect();
        $this->assertFalse($state['checks']['signing_keys']);
        DB::table('migrations')->where('migration', '2026_11_20_000005_version_requirement_coverage')->delete();
        $this->assertFalse(app(Readiness::class)->inspect()['checks']['schema_current']);
    }
}
