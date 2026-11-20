<?php

namespace App\Services;

use RuntimeException;

class SigningKeys
{
    public function generate(string $directory, string $configuration): void
    {
        if (! is_file($configuration) || ! is_readable($configuration)) {
            throw new RuntimeException('OpenSSL configuration is not readable. Check --config or OPENSSL_CONF.');
        }
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create signing-key directory. Check filesystem permissions.');
        }
        $lock = @fopen($directory.'/.generation.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot lock signing-key directory. Check filesystem permissions.');
        }
        $created = [];
        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Another process is generating keys. Try again later.');
            }
            foreach (['private.pem', 'public.pem'] as $name) {
                if (file_exists($directory.'/'.$name) || is_link($directory.'/'.$name)) {
                    throw new RuntimeException('Keys already exist (possibly a partial pair); refusing to overwrite. Preserve existing keys.');
                }
            }
            while (openssl_error_string() !== false) {
                // Discard unrelated OpenSSL errors before this operation.
            }
            $options = ['config' => $configuration, 'private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
            $key = @openssl_pkey_new($options);
            if ($key === false || ! @openssl_pkey_export($key, $private, null, $options)) {
                throw new RuntimeException($this->failure());
            }
            $details = openssl_pkey_get_details($key);
            if (! $details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 3072) {
                throw new RuntimeException('OpenSSL did not return a valid RSA3072 public key.');
            }
            $previousMask = umask(0077);
            try {
                foreach (['private.pem' => $private, 'public.pem' => $details['key']] as $name => $contents) {
                    $path = $directory.'/'.$name;
                    $stream = @fopen($path, 'x');
                    if ($stream === false) {
                        throw new RuntimeException('Cannot create signing-key file without overwriting. Check filesystem permissions.');
                    }
                    $created[] = $path;
                    try {
                        if (fwrite($stream, $contents) !== strlen($contents) || ! fflush($stream)) {
                            throw new RuntimeException('Could not write complete signing-key file.');
                        }
                    } finally {
                        fclose($stream);
                    }
                    if (! @chmod($path, 0600)) {
                        throw new RuntimeException('Cannot restrict signing-key file permissions.');
                    }
                }
            } finally {
                umask($previousMask);
            }
        } catch (\Throwable $error) {
            foreach ($created as $path) {
                @unlink($path);
            }
            throw $error;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function failure(): string
    {
        $errors = [];
        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return 'RSA key generation failed. Verify OpenSSL support and configuration. '.implode('; ', $errors);
    }
}
