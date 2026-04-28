<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Ensures OpenSSL can find its config file on Windows (XAMPP, Wamp, Laragon).
 * On Linux/Mac, OpenSSL is configured globally and this is a no-op.
 *
 * Use this trait in any class that calls openssl_pkey_new(), openssl_pkey_get_private(),
 * openssl_private_decrypt(), or openssl_encrypt()/openssl_decrypt().
 */
trait EnsuresOpenSsl
{
    /**
     * Set OPENSSL_CONF environment variable so OpenSSL functions work on Windows.
     * Must be called before any openssl_* function that requires the config.
     * Safe to call multiple times — skips if already set.
     */
    protected function ensureOpenSslConf(): void
    {
        $path = $this->findOpenSslConfPath();

        if ($path) {
            putenv('OPENSSL_CONF=' . $path);
        }
    }

    /**
     * Find the openssl.cnf path and return it for use in openssl_pkey_new() config array.
     * Returns null if not found (Linux/Mac typically don't need it explicitly).
     */
    protected function findOpenSslConf(): ?string
    {
        return $this->findOpenSslConfPath();
    }

    /**
     * Normalize PEM line endings to \n.
     * Windows can produce \r\n line endings in PEM exports which causes
     * OpenSSL 3.x "DECODER routines::unsupported" when the key is read back.
     */
    protected function normalizePem(string $pem): string
    {
        // Handle literal \n that can come from JSON storage
        if (str_contains($pem, '\\n') && ! str_contains($pem, "\n")) {
            $pem = str_replace('\\n', "\n", $pem);
        }

        return trim(str_replace(["\r\n", "\r"], "\n", $pem)) . "\n";
    }

    /**
     * Load a private key PEM, transparently converting PKCS#1 to PKCS#8 if needed.
     *
     * OpenSSL 3.x (default provider) cannot load PKCS#1 keys ("BEGIN RSA PRIVATE KEY")
     * without the legacy provider. This method converts PKCS#1 to PKCS#8 in pure PHP
     * using raw DER manipulation so no external tools or extra providers are required.
     *
     * @throws \Exception if the key cannot be loaded after conversion
     */
    protected function loadPrivateKey(string $pem): \OpenSSLAsymmetricKey
    {
        $normalized = $this->normalizePem($pem);

        // First try loading as-is (works for PKCS#8 on OpenSSL 3.x)
        $key = openssl_pkey_get_private($normalized);
        if ($key !== false) {
            return $key;
        }

        // If the key is PKCS#1, convert it to PKCS#8 and retry
        if (str_contains($normalized, 'BEGIN RSA PRIVATE KEY')) {
            Log::info('Private key is PKCS#1 — converting to PKCS#8 for OpenSSL 3.x compatibility');
            $pkcs8Pem = $this->convertPkcs1ToPkcs8($normalized);
            $key = openssl_pkey_get_private($pkcs8Pem);
            if ($key !== false) {
                return $key;
            }
        }

        $error = openssl_error_string();
        throw new \Exception(
            'Invalid RSA private key: ' . $error .
            ' — Click "Setup Keys" in the flow builder to regenerate a compatible key pair.'
        );
    }

    /**
     * Decrypt data encrypted with RSA-OAEP-SHA256.
     *
     * Meta's WhatsApp Flows client encrypts the AES key using:
     *   crypto.privateDecrypt({ key, padding: RSA_PKCS1_OAEP_PADDING, oaepHash: "sha256" })
     *
     * PHP's openssl_private_decrypt() with OPENSSL_PKCS1_OAEP_PADDING uses SHA-1 by default,
     * causing an "oaep decoding error" even with the correct key.
     *
     * This method implements RFC 3447 OAEP-SHA256 unpadding in pure PHP:
     *   1. Raw RSA decrypt (no padding) → EM
     *   2. OAEP-SHA256 unmask using MGF1-SHA256
     *   3. Return the message M
     *
     * @throws \RuntimeException on any decryption or padding failure
     */
    protected function rsaOaepSha256Decrypt(string $ciphertext, string $privateKeyPem): string
    {
        $privateKey = $this->loadPrivateKey($privateKeyPem);

        // Step 1: Raw RSA decryption — no padding removal, gives us EM
        $ok = openssl_private_decrypt($ciphertext, $em, $privateKey, OPENSSL_NO_PADDING);

        if (! $ok || $em === null) {
            throw new \RuntimeException('RSA raw decrypt failed: ' . openssl_error_string());
        }

        // Step 2: OAEP-SHA256 unmask (RFC 3447 §7.1.2)
        $hLen = 32;             // SHA-256 output: 32 bytes
        $emLen = strlen($em);   // should equal key size in bytes (e.g. 256 for 2048-bit)

        if ($emLen < 2 * $hLen + 2) {
            throw new \RuntimeException('OAEP unpadding failed: ciphertext too short');
        }

        // EM = 0x00 || maskedSeed || maskedDB
        if (ord($em[0]) !== 0x00) {
            throw new \RuntimeException('OAEP unpadding failed: leading byte is not 0x00');
        }

        $maskedSeed = substr($em, 1, $hLen);
        $maskedDB   = substr($em, 1 + $hLen);

        // seedMask = MGF1-SHA256(maskedDB, hLen)
        $seedMask = $this->mgf1Sha256($maskedDB, $hLen);

        // seed = maskedSeed XOR seedMask
        $seed = $maskedSeed ^ $seedMask;

        // dbMask = MGF1-SHA256(seed, emLen - hLen - 1)
        $dbMask = $this->mgf1Sha256($seed, $emLen - $hLen - 1);

        // DB = maskedDB XOR dbMask
        $db = $maskedDB ^ $dbMask;

        // DB = lHash (32 bytes) || PS (zero or more 0x00) || 0x01 || M
        // lHash = SHA-256('') (empty label)
        $lHash = hash('sha256', '', true);

        if (! hash_equals($lHash, substr($db, 0, $hLen))) {
            throw new \RuntimeException('OAEP unpadding failed: lHash mismatch — wrong private key or corrupted data');
        }

        // Find the 0x01 separator after lHash and PS
        $rest   = substr($db, $hLen);
        $sepPos = strpos($rest, "\x01");

        if ($sepPos === false) {
            throw new \RuntimeException('OAEP unpadding failed: missing 0x01 separator');
        }

        // Validate PS is all zeros
        for ($i = 0; $i < $sepPos; $i++) {
            if ($rest[$i] !== "\x00") {
                throw new \RuntimeException('OAEP unpadding failed: non-zero byte in padding string');
            }
        }

        // Message M follows the 0x01 separator
        return substr($rest, $sepPos + 1);
    }

    /**
     * MGF1 mask generation function using SHA-256.
     * Used internally by rsaOaepSha256Decrypt() to implement RFC 3447 §B.2.1.
     */
    private function mgf1Sha256(string $seed, int $length): string
    {
        $output  = '';
        $counter = 0;
        while (strlen($output) < $length) {
            $output .= hash('sha256', $seed . pack('N', $counter++), true);
        }
        return substr($output, 0, $length);
    }

    /**
     * Convert a PKCS#1 RSA private key PEM to PKCS#8 PEM using pure PHP DER manipulation.
     * This avoids the need for OpenSSL's legacy provider on OpenSSL 3.x.
     */
    protected function convertPkcs1ToPkcs8(string $pkcs1Pem): string
    {
        // Strip PEM headers and decode to raw DER
        $der = base64_decode(
            preg_replace('/-----[^-]+-----|[\r\n\s]/', '', $pkcs1Pem)
        );

        // RSA OID: 1.2.840.113549.1.1.1
        $rsaOid = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01";

        // AlgorithmIdentifier: SEQUENCE { OID rsaEncryption, NULL }
        $algorithmIdentifier = "\x30" . $this->derLen(strlen($rsaOid) + 2)
            . $rsaOid . "\x05\x00";

        // PrivateKey: OCTET STRING wrapping the PKCS#1 DER
        $privateKeyOctetString = "\x04" . $this->derLen(strlen($der)) . $der;

        // version INTEGER = 0
        $version = "\x02\x01\x00";

        // PrivateKeyInfo: SEQUENCE { version, algorithmIdentifier, privateKey }
        $pkcs8Content = $version . $algorithmIdentifier . $privateKeyOctetString;
        $pkcs8Der = "\x30" . $this->derLen(strlen($pkcs8Content)) . $pkcs8Content;

        return "-----BEGIN PRIVATE KEY-----\n"
            . chunk_split(base64_encode($pkcs8Der), 64, "\n")
            . "-----END PRIVATE KEY-----\n";
    }

    /** Encode a DER length value (supports multi-byte lengths). */
    private function derLen(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $bytes = '';
        $tmp   = $length;
        while ($tmp > 0) {
            $bytes = chr($tmp & 0xFF) . $bytes;
            $tmp >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function findOpenSslConfPath(): ?string
    {
        // If already set in the environment and the file exists, use it
        $envConf = getenv('OPENSSL_CONF');
        if (! empty($envConf) && file_exists($envConf)) {
            return $envConf;
        }

        $candidates = [
            // XAMPP Windows
            'C:/xampp/php/extras/openssl/openssl.cnf',
            'C:/xampp/apache/conf/openssl.cnf',
            // WAMP
            'C:/wamp64/bin/apache/apache2.4.54/conf/openssl.cnf',
            'C:/wamp64/bin/apache/apache2.4.58/conf/openssl.cnf',
            // Laragon
            'C:/laragon/etc/ssl/openssl.cnf',
            // Linux
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
            // macOS Homebrew
            '/usr/local/ssl/openssl.cnf',
            '/usr/local/etc/openssl/openssl.cnf',
            '/opt/homebrew/etc/openssl@3/openssl.cnf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                Log::debug('OpenSSL config located', ['path' => $path]);

                return $path;
            }
        }

        Log::debug('openssl.cnf not found in common paths — relying on system OpenSSL config.');

        return null;
    }
}
