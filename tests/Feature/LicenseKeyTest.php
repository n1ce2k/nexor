<?php

namespace Tests\Feature;

use Nexor\Cms\Enums\License;
use Nexor\Cms\Support\License\Base62;
use Nexor\Cms\Support\License\Host;
use Nexor\Cms\Support\License\LicenseKey;
use Nexor\Cms\Support\License\Signature;
use Nexor\Cms\Support\Licensing;
use Nexor\Cms\Support\Nexor;
use Tests\Concerns\IssuesLicenseKeys;
use Tests\TestCase;

/**
 * Лицензионный ключ: подпись, редакция и срок.
 *
 * Ключ проверяется на месте публичным ключом издателя — сервер не нужен, а
 * подделать ключ, не зная приватного, нельзя.
 */
class LicenseKeyTest extends TestCase
{
    use IssuesLicenseKeys;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIssuer();
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
        [$otherPrivate] = $this->issuerPair();

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

    // ---------------------------------------------------------------- домен

    public function test_a_key_carries_the_domain_it_was_issued_for(): void
    {
        $key = LicenseKey::issue(License::Pro, null, $this->privateKey, host: 'https://WWW.Site.ru/catalog');

        // В ключ домен попадает уже приведённым к одному виду.
        $this->assertSame('site.ru', $key->host);
        $this->assertSame('site.ru', LicenseKey::parse($key->key, $this->publicKey)->host);

        $this->assertTrue($key->matches('site.ru'));
        $this->assertTrue($key->matches('www.site.ru:8080'));
        $this->assertFalse($key->matches('vasya.ru'));
        $this->assertFalse($key->matches('sub.site.ru'));
    }

    public function test_a_key_without_a_domain_fits_any_site(): void
    {
        $key = LicenseKey::issue(License::Pro, null, $this->privateKey);

        $this->assertNull($key->host);
        $this->assertTrue($key->matches('vasya.ru'));
        $this->assertTrue($key->matches(null));
    }

    public function test_keys_of_the_first_version_keep_working(): void
    {
        // Такие ключи уже выданы: в них нет домена, и ломать их нельзя.
        $payload = pack('CCNN', 1, 3, 0, 777);
        $key = LicenseKey::PREFIX.Base62::encode($payload.Signature::sign($payload, $this->privateKey));

        $parsed = LicenseKey::parse($key, $this->publicKey);

        $this->assertSame(License::Pro, $parsed->edition);
        $this->assertSame(777, $parsed->serial);
        $this->assertNull($parsed->host);
        $this->assertTrue($parsed->matches('vasya.ru'));
    }

    public function test_a_domain_is_read_the_same_however_it_is_written(): void
    {
        foreach (['https://WWW.Site.ru/catalog?x=1', 'site.ru:8080', 'www.site.ru.', 'SITE.RU'] as $value) {
            $this->assertSame('site.ru', Host::normalise($value));
        }

        $this->assertSame('', Host::normalise(null));
        $this->assertSame('::1', Host::normalise('[::1]:8080'));

        // Рабочие адреса лицензией не проверяются.
        $this->assertTrue(Host::isLocal('localhost'));
        $this->assertTrue(Host::isLocal('nexor.test'));
        $this->assertTrue(Host::isLocal('192.168.1.10'));
        $this->assertFalse(Host::isLocal('site.ru'));
        $this->assertFalse(Host::isLocal('95.181.12.4'));
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
