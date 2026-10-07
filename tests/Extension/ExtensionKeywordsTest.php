<?php

/**
 * Extension keywords tests.
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
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKeywords;
use Blush\Icon\IconPack;
use Blush\Plugin\PluginManifest;
use Blush\Tests\TemporaryDirectory;
use Blush\Theme\ThemeManifest;

#[CoversClass(ExtensionKeywords::class)]
final class ExtensionKeywordsTest extends TestCase
{
	use TemporaryDirectory;

	public function testReadsComposersShape(): void
	{
		$this->assertSame([], ExtensionKeywords::fromManifest(null));
		$this->assertSame([], ExtensionKeywords::fromManifest([]));
		$this->assertSame(['blog', 'serif'], ExtensionKeywords::fromManifest([' blog ', '', 'serif', 'blog']), 'Trimmed, without empty ones or repeats.');

		foreach (['blog', ['blog' => 'serif'], ['blog', 1]] as $value) {
			try {
				ExtensionKeywords::fromManifest($value);
				$this->fail(sprintf('%s should break the manifest.', var_export($value, true)));
			} catch (ExtensionException $error) {
				$this->assertStringContainsString('"keywords" must', $error->getMessage());
			}
		}
	}

	public function testReadsComposerJsonLeniently(): void
	{
		$this->assertSame(['blog', 'serif'], ExtensionKeywords::lenient(['blog', 1, 'serif']), 'Only strings are kept.');
		$this->assertSame([], ExtensionKeywords::lenient('blog'));
		$this->assertSame([], ExtensionKeywords::lenient(['blog' => 'serif']));
	}

	public function testEveryKindReadsItAndComposerJsonFillsIt(): void
	{
		$this->writeTemporaryFile('acme/composer.json', '{"keywords": ["gallery", 2, "images"]}');

		$folder = $this->temporaryDirectory() . '/acme';
		$data   = ComposerJson::fill(['name' => 'acme/hello'], $folder);

		$this->assertSame(['gallery', 'images'], $data['keywords'] ?? null);
		$this->assertSame([], ComposerJson::fill(['name' => 'acme/hello', 'keywords' => []], $folder)['keywords'], 'The manifest\'s own wins.');

		$plugin = PluginManifest::fromArray([...$data, 'source' => 'local', 'path' => $folder]);

		$this->assertSame(['gallery', 'images'], $plugin->keywords);
		$this->assertEquals($plugin, PluginManifest::fromArray($plugin->toArray()), 'It round-trips through a cached manifest.');

		$pack = IconPack::fromArray($folder, $data);

		$this->assertSame(['gallery', 'images'], $pack->keywords);
		$this->assertEquals($pack, IconPack::fromArray($pack->path, $pack->toArray()['data']));
		$this->assertSame(['docs'], ThemeManifest::fromArray($folder, ['name' => 'acme/nova', 'keywords' => ['docs']])->keywords);
		$this->assertSame([], ThemeManifest::fromArray($folder, ['name' => 'acme/nova'])->keywords);

		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('Plugin manifest for "acme/hello": "keywords" must be a list of strings.');

		PluginManifest::fromArray(['name' => 'acme/hello', 'keywords' => 'gallery', 'source' => 'local', 'path' => $folder]);
	}
}
