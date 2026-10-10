<?php

/**
 * Secret tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Secret;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Env\Env;
use Blush\Secret\InvalidSecret;
use Blush\Secret\Secret;
use Blush\Secret\SecretCipher;
use Blush\Secret\SecretDefinition;
use Blush\Secret\SecretKeys;
use Blush\Secret\SecretUnreadable;

#[CoversClass(Secret::class)]
#[CoversClass(SecretDefinition::class)]
#[CoversClass(SecretKeys::class)]
#[CoversClass(SecretCipher::class)]
final class SecretTest extends TestCase
{
	private const string VALUE = 'sk-live-0123456789';

	public function testASecretShowsItsValueOnlyWhenAsked(): void
	{
		$secret = new Secret(self::VALUE);

		$this->assertSame(self::VALUE, $secret->reveal());
		$this->assertTrue($secret->equals(self::VALUE));
		$this->assertFalse($secret->equals('something else'));
		$this->assertSame('[secret]', (string) $secret);
		$this->assertStringNotContainsString(self::VALUE, print_r($secret, true));
		$this->assertStringNotContainsString(self::VALUE, var_export($secret, true));
		$this->assertStringNotContainsString(self::VALUE, (string) json_encode($secret));

		ob_start();
		var_dump($secret);
		$this->assertStringNotContainsString(self::VALUE, (string) ob_get_clean());

		$this->expectException(LogicException::class);
		serialize($secret);
	}

	public function testADefinitionNamesItsSecretAndVariable(): void
	{
		$plugin = new SecretDefinition('acme/photo.gallery', 'api_token', 'API token', required: true);

		$this->assertSame('acme/photo.gallery.api_token', $plugin->name());
		$this->assertSame('acme__photo.gallery.api_token', $plugin->recordKey());
		$this->assertSame('ACME_PHOTO_GALLERY_API_TOKEN', $plugin->env);
		$this->assertSame('API token', $plugin->title());

		$core = new SecretDefinition('core', 'anthropic_key', env: 'ANTHROPIC_API_KEY');

		$this->assertSame('core.anthropic_key', $core->name());
		$this->assertSame('ANTHROPIC_API_KEY', $core->env);
		$this->assertSame('core.anthropic_key', $core->title(), 'Without a label, its name.');
	}

	public function testADefinitionRefusesWhatCantBeASecret(): void
	{
		foreach ([['blush', 'key', null], ['Acme/Tabs', 'key', null], ['acme/tabs', 'Key', null], ['acme/tabs', 'a.b', null], ['acme/tabs', 'key', 'lower'], ['acme/tabs', 'key', 'SECRETS_KEY'], ['acme/tabs', 'key', 'APP_SECRET']] as [$owner, $key, $env]) {
			try {
				new SecretDefinition($owner, $key, env: $env);
				$this->fail(sprintf('%s.%s (%s) should be refused.', $owner, $key, $env ?? 'derived'));
			} catch (InvalidSecret) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testKeysComeFromTheEnvironment(): void
	{
		$current  = SecretKeys::generate();
		$previous = SecretKeys::generate();
		$keys     = new SecretKeys(new Env(['SECRETS_KEY' => $current, 'SECRETS_KEY_PREVIOUS' => $previous]));

		$this->assertTrue($keys->canEncrypt());
		$this->assertSame(SecretKeys::id((string) base64_decode($current, true)), $keys->currentId());
		$this->assertNotNull($keys->find(SecretKeys::id((string) base64_decode($previous, true))));
		$this->assertSame([], $keys->problems());
		$this->assertStringNotContainsString($current, print_r($keys, true));

		$none = new SecretKeys(new Env());

		$this->assertFalse($none->canEncrypt());
		$this->assertSame([], $none->problems(), 'No key is nothing wrong, only nothing stored.');

		$bad = new SecretKeys(new Env(['SECRETS_KEY' => 'not a key']));

		$this->assertFalse($bad->canEncrypt());
		$this->assertCount(1, $bad->problems());

		$this->expectException(InvalidSecret::class);
		$none->current();
	}

	public function testTheCipherBindsAValueToItsNameAndKey(): void
	{
		$keys   = new SecretKeys(new Env(['SECRETS_KEY' => SecretKeys::generate()]));
		$cipher = new SecretCipher($keys);
		$stored = $cipher->encrypt('acme/tabs.token', self::VALUE);

		$this->assertStringStartsWith('v1:' . $keys->currentId() . ':', $stored);
		$this->assertStringNotContainsString(self::VALUE, $stored);
		$this->assertNotSame($stored, $cipher->encrypt('acme/tabs.token', self::VALUE), 'A new nonce each time.');
		$this->assertSame(self::VALUE, $cipher->decrypt('acme/tabs.token', $stored)->reveal());
		$this->assertSame($keys->currentId(), SecretCipher::keyId($stored));

		$body     = explode(':', $stored, 3)[2];
		$tampered = substr($stored, 0, -2) . ($stored[-2] === 'A' ? 'B' : 'A') . $stored[-1];
		$unknown  = new SecretCipher(new SecretKeys(new Env(['SECRETS_KEY' => SecretKeys::generate()])));

		$cases = [
			'moved'    => fn () => $cipher->decrypt('acme/other.token', $stored),
			'tampered' => fn () => $cipher->decrypt('acme/tabs.token', $tampered),
			'rekeyed'  => fn () => $cipher->decrypt('acme/tabs.token', "v1:00000000:{$body}"),
			'unknown'  => fn () => $unknown->decrypt('acme/tabs.token', $stored),
			'garbled'  => fn () => $cipher->decrypt('acme/tabs.token', 'plain text')
		];

		foreach ($cases as $case => $decrypt) {
			try {
				$decrypt();
				$this->fail("A {$case} value should be unreadable.");
			} catch (SecretUnreadable $e) {
				$this->assertStringNotContainsString(self::VALUE, $e->getMessage());
			}
		}
	}
}
