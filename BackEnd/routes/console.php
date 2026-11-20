<?php

use App\Models\User;
use App\Services\SigningKeys;
use Illuminate\Support\Facades\Artisan;

Artisan::command('afra:admin {email} {name}', function () {
    $password = $this->secret('Initial password (minimum 12 characters)');
    if (! is_string($password) || strlen($password) < 12) {
        $this->error('Password must contain at least 12 characters.');

        return 1;
    }
    if (User::where('email', strtolower($this->argument('email')))->exists()) {
        $this->error('Account already exists.');

        return 1;
    }
    $u = new User(['name' => $this->argument('name'), 'email' => strtolower($this->argument('email')), 'password' => $password]);
    $u->is_system_admin = true;
    $u->must_change_password = true;
    $u->save();
    $this->info('Administrator created. Password change is required.');
});
Artisan::command('afra:keys {--config= : OpenSSL configuration file (optional)}', function () {
    $configuration = $this->option('config') ?: (getenv('OPENSSL_CONF') ?: resource_path('openssl.cnf'));
    try {
        app(SigningKeys::class)->generate(storage_path('keys'), $configuration);
    } catch (RuntimeException $error) {
        $this->error($error->getMessage());

        return 1;
    }
    $this->info('RSA3072 signing keys generated. Keep private.pem private; restrict its Windows NTFS permissions to your account and authorized application identities.');

    return 0;
});
