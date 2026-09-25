<?php

/**
 * Local extension finder.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use JsonException;
use Override;

/**
 * Finds local extensions: folders in `user/extensions/` holding an
 * `extension.json` manifest:
 *
 *     {
 *         "name": "acme/gallery",
 *         "version": "1.0.0",
 *         "description": "Photo galleries.",
 *         "provider": "Acme\\Gallery\\GalleryServiceProvider",
 *         "autoload": { "psr-4": { "Acme\\Gallery\\": "src/" } },
 *         "requires": { "blush": "^2.0" }
 *     }
 *
 * Blush autoloads the `psr-4` map itself (see `LocalAutoloader`). YAML
 * manifests (D-032) arrive with the data loader.
 */
final readonly class LocalExtensionFinder implements ExtensionFinder
{
	/**
	 * The manifest file name.
	 */
	public const string MANIFEST = 'extension.json';

	public function __construct(private string $extensionsPath)
	{
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(): array
	{
		$files = glob($this->extensionsPath . '/*/' . self::MANIFEST) ?: [];

		sort($files);

		return array_map($this->manifest(...), $files);
	}

	/**
	 * Builds a manifest from a manifest file.
	 *
	 * @throws ExtensionException
	 */
	private function manifest(string $file): ExtensionManifest
	{
		try {
			$data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new ExtensionException(sprintf('Invalid extension manifest "%s": %s', $file, $e->getMessage()), previous: $e);
		}

		if (! is_array($data)) {
			throw new ExtensionException(sprintf('Extension manifest "%s" must be a JSON object.', $file));
		}

		$autoload = is_array($data['autoload'] ?? null) ? $data['autoload'] : [];

		try {
			return ExtensionManifest::fromArray([
				...$data,
				'source'   => ExtensionSource::Local,
				'path'     => dirname($file),
				'autoload' => $autoload['psr-4'] ?? []
			]);
		} catch (ExtensionException $e) {
			throw new ExtensionException(sprintf('%s (%s)', $e->getMessage(), $file), previous: $e);
		}
	}
}
