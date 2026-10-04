<?php

/**
 * Extension suggest tests.
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
use Blush\Extension\ExtensionSuggest;
use Blush\Icon\IconPack;
use Blush\Plugin\PluginManifest;
use Blush\Tests\TemporaryDirectory;
use Blush\Theme\ThemeManifest;

#[CoversClass(ExtensionSuggest::class)]
final class ExtensionSuggestTest extends TestCase
{
	use TemporaryDirectory;

	public function testReadsComposersShape(): void
	{
		$this->assertSame([], ExtensionSuggest::fromManifest(null));
		$this->assertSame([], ExtensionSuggest::fromManifest([]));
		$this->assertSame(
			['acme/blocks' => 'For more blocks.', 'ext-intl' => ''],
			ExtensionSuggest::fromManifest([' acme/blocks ' => ' For more blocks. ', 'ext-intl' => ''])
		);

		foreach (['acme/blocks', ['acme/blocks'], ['acme/blocks' => true], ['' => 'Why.']] as $value) {
			try {
				ExtensionSuggest::fromManifest($value);
				$this->fail(sprintf('%s should break the manifest.', var_export($value, true)));
			} catch (ExtensionException $error) {
				$this->assertStringContainsString('"suggest" must', $error->getMessage());
			}
		}
	}

	public function testReadsComposerJsonLeniently(): void
	{
		$this->assertSame(['acme/blocks' => 'Why.'], ExtensionSuggest::lenient(['acme/blocks' => 'Why.', 'acme/odd' => 1, '' => 'Why.']), 'Only names mapped to strings are kept.');
		$this->assertSame([], ExtensionSuggest::lenient('acme/blocks'));
		$this->assertSame([], ExtensionSuggest::lenient(['acme/blocks']));
	}

	public function testEveryKindReadsItAndComposerJsonFillsIt(): void
	{
		$this->writeTemporaryFile('acme/composer.json', '{"suggest": {"acme/blocks": "For more blocks.", "acme/odd": 1}}');

		$folder = $this->temporaryDirectory() . '/acme';
		$data   = ComposerJson::fill(['name' => 'acme/hello'], $folder);

		$this->assertSame(['acme/blocks' => 'For more blocks.'], $data['suggest'] ?? null);
		$this->assertSame([], ComposerJson::fill(['name' => 'acme/hello', 'suggest' => []], $folder)['suggest'], 'The manifest\'s own wins.');

		$plugin = PluginManifest::fromArray([...$data, 'source' => 'local', 'path' => $folder]);

		$this->assertSame(['acme/blocks' => 'For more blocks.'], $plugin->suggest);
		$this->assertEquals($plugin, PluginManifest::fromArray($plugin->toArray()), 'It round-trips through a cached manifest.');

		$pack = IconPack::fromArray($folder, $data);

		$this->assertSame(['acme/blocks' => 'For more blocks.'], $pack->suggest);
		$this->assertEquals($pack, IconPack::fromArray($pack->path, $pack->toArray()['data']));
		$this->assertSame(['ext-intl' => 'For dates.'], ThemeManifest::fromArray($folder, ['name' => 'acme/nova', 'suggest' => ['ext-intl' => 'For dates.']])->suggest);
		$this->assertSame([], ThemeManifest::fromArray($folder, ['name' => 'acme/nova'])->suggest);

		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('Plugin manifest for "acme/hello": "suggest" must be an object.');

		PluginManifest::fromArray(['name' => 'acme/hello', 'suggest' => 'acme/blocks', 'source' => 'local', 'path' => $folder]);
	}
}
