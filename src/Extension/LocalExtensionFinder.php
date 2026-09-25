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
use Blush\Data\InvalidData;
use Blush\Data\SymfonyYamlParser;

/**
 * Finds local extensions: folders in `user/extensions/` holding an
 * `extension.json` (or `extension.yaml`/`.yml`) manifest:
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
 * Blush autoloads the `psr-4` map itself (see `LocalAutoloader`). When a
 * folder has manifests in several formats, JSON wins (D-032). YAML is read
 * with the framework's parser directly, since extensions are discovered
 * before the container exists.
 */
final readonly class LocalExtensionFinder implements ExtensionFinder
{
	/**
	 * The manifest file name, without its extension.
	 */
	public const string MANIFEST = 'extension';

	/**
	 * The manifest formats, in precedence order.
	 *
	 * @var list<string>
	 */
	public const array FORMATS = ['json', 'yaml', 'yml'];

	public function __construct(private string $extensionsPath)
	{
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(): array
	{
		$files = [];

		foreach (glob($this->extensionsPath . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
			$file = array_find(
				array_map(static fn (string $format): string => "{$directory}/" . self::MANIFEST . ".{$format}", self::FORMATS),
				static fn (string $file): bool => is_file($file)
			);

			if ($file !== null) {
				$files[] = $file;
			}
		}

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
		$contents = (string) file_get_contents($file);

		try {
			$data = str_ends_with($file, '.json')
				? json_decode($contents, true, 512, JSON_THROW_ON_ERROR)
				: new SymfonyYamlParser()->parse($contents);
		} catch (JsonException | InvalidData $e) {
			throw new ExtensionException(sprintf('Invalid extension manifest "%s": %s', $file, $e->getMessage()), previous: $e);
		}

		if (! is_array($data) || array_is_list($data)) {
			throw new ExtensionException(sprintf('Extension manifest "%s" must be a map of keys to values.', $file));
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
