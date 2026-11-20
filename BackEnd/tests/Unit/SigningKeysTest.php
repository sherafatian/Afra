<?php

namespace Tests\Unit;

use App\Services\SigningKeys;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SigningKeysTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/afra-keys-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->directory) as $name) {
            $path = $this->directory.'/'.$name;
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($this->directory);
    }

    public function test_bundled_configuration_generates_matching_signing_keys(): void
    {
        (new SigningKeys)->generate($this->directory, dirname(__DIR__, 2).'/resources/openssl.cnf');
        $private = file_get_contents($this->directory.'/private.pem');
        $public = file_get_contents($this->directory.'/public.pem');
        $this->assertTrue(openssl_sign('Afra signing probe', $signature, $private, OPENSSL_ALGO_SHA256));
        $this->assertSame(1, openssl_verify('Afra signing probe', $signature, $public, OPENSSL_ALGO_SHA256));
        $this->assertSame(3072, openssl_pkey_get_details(openssl_pkey_get_public($public))['bits']);
    }

    public function test_existing_public_key_is_preserved_and_no_private_key_is_created(): void
    {
        file_put_contents($this->directory.'/public.pem', 'existing-public-key');
        try {
            (new SigningKeys)->generate($this->directory, dirname(__DIR__, 2).'/resources/openssl.cnf');
            $this->fail('Expected overwrite refusal.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('refusing to overwrite', $error->getMessage());
        }
        $this->assertSame('existing-public-key', file_get_contents($this->directory.'/public.pem'));
        $this->assertFileDoesNotExist($this->directory.'/private.pem');
    }

    public function test_missing_configuration_fails_without_partial_keys(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('configuration is not readable');
        (new SigningKeys)->generate($this->directory, $this->directory.'/missing.cnf');
    }
}
