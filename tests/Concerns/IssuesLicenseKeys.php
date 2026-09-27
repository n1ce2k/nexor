<?php

namespace Tests\Concerns;

use Nexor\Cms\Enums\License;
use Nexor\Cms\Support\License\LicenseKey;
use Nexor\Cms\Support\Licensing;

/**
 * Своя пара ключей на время теста: настоящий приватный ключ издателя лежит вне
 * репозитория, поэтому тесты подписывают ключи сами.
 */
trait IssuesLicenseKeys
{
    protected string $privateKey;

    protected string $publicKey;

    /**
     * Сайт начинает доверять ключам этого теста.
     */
    protected function useIssuer(): void
    {
        [$this->privateKey, $this->publicKey] = $this->issuerPair();

        config(['nexor.license_public_key' => $this->publicKey]);
        Licensing::flush();
    }

    /**
     * Новая пара ключей.
     *
     * @return array{0: string, 1: string}
     */
    protected function issuerPair(): array
    {
        $config = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];

        // openssl под Windows не находит свой конфиг сам.
        if (is_file('C:/php/extras/ssl/openssl.cnf')) {
            $config['config'] = 'C:/php/extras/ssl/openssl.cnf';
        }

        $pair = openssl_pkey_new($config);
        openssl_pkey_export($pair, $private, null, $config);

        return [(string) $private, openssl_pkey_get_details($pair)['key']];
    }

    /**
     * Выпускает ключ и вписывает его сайту.
     */
    protected function useKey(License $edition = License::Pro, ?int $expiresAt = null, ?string $host = null): LicenseKey
    {
        $key = LicenseKey::issue($edition, $expiresAt, $this->privateKey, host: $host);

        config(['nexor.license_key' => $key->key]);
        Licensing::flush();

        return $key;
    }
}
