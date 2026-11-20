<?php

use App\Models\User;
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
Artisan::command('afra:keys', function () {
    $dir = storage_path('keys');
    if (is_file($dir.'/private.pem')) {
        $this->error('Keys already exist; refusing to overwrite.');

        return 1;
    }
    if (! is_dir($dir)) {
        mkdir($dir, 0700, true);
    } $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $private);
    file_put_contents($dir.'/private.pem', $private);
    chmod($dir.'/private.pem', 0600);
    file_put_contents($dir.'/public.pem', openssl_pkey_get_details($key)['key']);
    $this->info('RSA keys generated. Share securely across application nodes.');
});
