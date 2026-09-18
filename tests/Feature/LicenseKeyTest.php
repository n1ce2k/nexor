<?php

namespace Tests\Feature;

use Nexor\Cms\Enums\License;
use Nexor\Cms\Support\License\Base62;
use Nexor\Cms\Support\License\LicenseKey;
use Nexor\Cms\Support\Licensing;
use Nexor\Cms\Support\Nexor;
use Tests\TestCase;

/**
 * Лицензионный ключ: подпись, редакция и срок.
 *
 * Ключ проверяется на месте публичным ключом издателя — сервер не нужен, а
 * подделать ключ, не зная приватного, нельзя.
 */
class LicenseKeyTest extends TestCase
{
    protected string $privateKey;

    protected string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Своя пара на время теста: настоящий приватный ключ лежит вне репозитория.
        $config = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];

        if (is_file('C:/php/extras/ssl/openssl.cnf')) {
            $config['config'] = 'C:/php/extras/ssl/openssl.cnf';
        }

        $pair = openssl_pkey_new($config);
        openssl_pkey_export($pair, $private, null, $config);

        $this->privateKey = (string) $private;
        $this->publicKey = openssl_pkey_get_details($pair)['key'];

        config(['nexor.license_public_key' => $this->publicKey]);
        Licensing::flush();
    }

    protected function useKey(License $edition = License::Pro, ?int $expiresAt = null): LicenseKey
    {
        $key = LicenseKey::issue($edition, $expiresAt, $this->privateKey);

        config(['nexor.license_key' => $key->key]);
        Licensing::flush();

        return $key;
    }

    public function test_a_key_looks_like_one_continuous_word(): void
    {
        $key = LicenseKey::issue(License::Pro, null, $this->privateKey);

        $this->assertMatchesRegularExpression('/^nxr-[A-Za-z0-9]+$/', $key->key);
        $this->assertGreaterThan(80, strlen($key->key));
    }

    public function test_a_key_carries_its_edition_and_term(): void
    {
        $expires = time() + 86400;
        $issued = LicenseKey::issue(License::Standart, $expires, $this->privateKey);

        $parsed = LicenseKey::parse($issued->key, $this->publicKey);

        $this->assertSame(License::Standart, $parsed->edition);
        $this->assertSame($expires, $parsed->expiresAt);
        $this->assertSame($issued->serial, $parsed->serial);
        $this->assertFalse($parsed->isExpired());
    }

    public function test_a_tampered_key_is_refused(): void
    {
        $key = LicenseKey::issue(License::Pro, null, $this->privateKey)->key;

        $swapped = substr($key, 0, -1).(str_ends_with($key, 'a') ? 'b' : 'a');

        $this->assertNull(LicenseKey::parse($swapped, $this->publicKey));
        $this->assertNull(LicenseKey::parse('nxr-', $this->publicKey));
        $this->assertNull(LicenseKey::parse('nxr-нелатиница', $this->publicKey));
        $this->assertNull(LicenseKey::parse('просто строка', $this->publicKey));
        $this->assertNull(LicenseKey::parse(null, $this->publicKey));
    }

    public function test_a_key_from_another_issuer_is_refused(): void
    {
        $config = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];

        if (is_file('C:/php/extras/ssl/openssl.cnf')) {
            $config['config'] = 'C:/php/extras/ssl/openssl.cnf';
        }

        $other = openssl_pkey_new($config);
        openssl_pkey_export($other, $otherPrivate, null, $config);

        $key = LicenseKey::issue(License::Pro, null, $otherPrivate)->key;

        $this->assertNull(LicenseKey::parse($key, $this->publicKey));
    }

    public function test_the_edition_of_the_site_comes_from_the_key(): void
    {
        $this->useKey(License::Standart);

        $this->assertSame(License::Standart, Nexor::license());
        $this->assertSame(Licensing::OK, Licensing::status());
        $this->assertTrue(Nexor::feature('catalog.offers'));
    }

    public function test_an_expired_key_drops_the_site_to_the_fallback(): void
    {
        $this->useKey(License::Pro, time() - 60);
        config(['nexor.license' => 'lite']);

        $this->assertSame(Licensing::EXPIRED, Licensing::status());
        $this->assertSame(License::Lite, Nexor::license());
        $this->assertFalse(Nexor::feature('catalog.offers'));
    }

    public function test_a_broken_key_is_reported_separately_from_a_missing_one(): void
    {
        config(['nexor.license_key' => 'nxr-brokenkey']);
        Licensing::flush();
        $this->assertSame(Licensing::INVALID, Licensing::status());

        config(['nexor.license_key' => null]);
        Licensing::flush();
        $this->assertSame(Licensing::NONE, Licensing::status());
    }

    public function test_base62_survives_any_bytes(): void
    {
        foreach ([random_bytes(74), "\x00\x00\x01\x02", "\xff\xff\xff", 'обычный текст'] as $binary) {
            $encoded = Base62::encode($binary);

            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]*$/', $encoded);
            $this->assertSame($binary, Base62::decode($encoded));
        }

        $this->assertNull(Base62::decode('не-латиница'));
    }

    public function test_the_key_number_is_shown_the_same_way_everywhere(): void
    {
        $key = LicenseKey::issue(License::Pro, null, $this->privateKey, 0x0A1B2C3D);

        $this->assertSame('0A1B2C3D', $key->number());
        $this->assertSame('0A1B2C3D', LicenseKey::parse($key->key, $this->publicKey)->number());
    }
}
