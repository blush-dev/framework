<?php

/**
 * Extension abandoned tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Extension\ComposerJson;
use Blush\Extension\ExtensionAbandoned;
use Blush\Extension\ExtensionException;
use Blush\Icon\IconPack;
use Blush\Plugin\PluginManifest;
use Blush\Tests\TemporaryDirectory;
use Blush\Theme\ThemeManifest;

#[CoversClass(ExtensionAbandoned::class)]
final class ExtensionAbandonedTest extends TestCase
{
	use TemporaryDirectory;

	public function testReadsComposersShape(): void
	{
		$this->assertTrue(ExtensionAbandoned::fromManifest(true));
		$this->assertFalse(ExtensionAbandoned::fromManifest(false));
		$this->assertSame('acme/next', ExtensionAbandoned::fromManifest(' acme/next '));
		$this->assertFalse(ExtensionAbandoned::fromManifest(''), 'An empty string isn\'t abandoned, as Composer reads it.');

		foreach ([1, 'Not A Name', ['acme/next'], null] as $value) {
			try {
				ExtensionAbandoned::fromManifest($value);
				$this->fail(sprintf('%s should break the manifest.', var_export($value, true)));
			} catch (ExtensionException $error) {
				$this->assertStringContainsString('"abandoned" must be', $error->getMessage());
			}
		}
	}

	public function testReadsComposerJsonLeniently(): void
	{
		$this->assertSame('acme/next', ExtensionAbandoned::lenient('acme/next'));
		$this->assertTrue(ExtensionAbandoned::lenient('see the readme'), 'A string that isn\'t a name is still abandoned.');
		$this->assertFalse(ExtensionAbandoned::lenient(''));
		$this->assertFalse(ExtensionAbandoned::lenient(0));
	}

	public function testWarns(): void
	{
		$this->assertNull(ExtensionAbandoned::warning(false));
		$this->assertSame('It\'s abandoned, and no longer maintained.', ExtensionAbandoned::warning(true));
		$this->assertSame('It\'s abandoned; use "acme/next" instead.', ExtensionAbandoned::warning('acme/next'));
	}

	public function testEveryKindReadsItAndComposerJsonFillsIt(): void
	{
		$this->writeTemporaryFile('acme/composer.json', '{"abandoned": "acme/next"}');

		$folder = $this->temporaryDirectory() . '/acme';
		$data   = ComposerJson::fill(['name' => 'acme/old'], $folder);

		$this->assertSame('acme/next', $data['abandoned'] ?? null);
		$this->assertFalse(ComposerJson::fill(['name' => 'acme/old', 'abandoned' => false], $folder)['abandoned'], 'The manifest\'s own wins.');

		$plugin = PluginManifest::fromArray([...$data, 'source' => 'local', 'path' => $folder]);

		$this->assertSame('acme/next', $plugin->abandoned);
		$this->assertEquals($plugin, PluginManifest::fromArray($plugin->toArray()), 'It round-trips through a cached manifest.');

		$pack = IconPack::fromArray($folder, $data);

		$this->assertSame('acme/next', $pack->abandoned);
		$this->assertEquals($pack, IconPack::fromArray($pack->path, $pack->toArray()['data']));
		$this->assertTrue(ThemeManifest::fromArray($folder, ['name' => 'acme/old', 'abandoned' => true])->abandoned);
		$this->assertFalse(ThemeManifest::fromArray($folder, ['name' => 'acme/old'])->abandoned);

		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('Plugin manifest for "acme/old": "abandoned" must be');

		PluginManifest::fromArray(['name' => 'acme/old', 'abandoned' => 'yes', 'source' => 'local', 'path' => $folder]);
	}
}
