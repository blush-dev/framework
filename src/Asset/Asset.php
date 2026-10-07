<?php

/**
 * Asset.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

use Blush\Theme\ThemeChain;

/**
 * A named set of stylesheets and scripts that load together (D-569),
 * such as `blush/player`, the site's audio and video players. Core,
 * plugins, and themes register assets by handle, a `vendor/name`;
 * pages, templates, directives, and components then ask for them by
 * handle, and the head prints each one once, after what it `requires`.
 *
 *     new Asset('acme/gallery',
 *         styles: [new Style('css/gallery.css', from: 'acme/gallery')],
 *         scripts: [new Script('js/gallery.js', from: 'acme/gallery', attributes: ['type' => 'module'])],
 *         requires: ['blush/player']
 *     );
 *
 * An asset with no files is a blank: registering one over another's
 * handle (a theme drawing the player itself) loads nothing for it.
 */
final readonly class Asset
{
	/**
	 * The handle's form: `vendor/name`, as extensions are named.
	 */
	public const string HANDLE = '[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*';

	/**
	 * @param list<Style>  $styles   Its stylesheets, in order.
	 * @param list<Script> $scripts  Its scripts, in order.
	 * @param list<string> $requires The handles it loads after.
	 * @throws AssetException When the handle isn't a `vendor/name`.
	 */
	public function __construct(
		public string $handle,
		public array $styles = [],
		public array $scripts = [],
		public array $requires = []
	) {
		if (! self::isHandle($handle)) {
			throw new AssetException(sprintf('"%s" isn\'t an asset handle; a handle is a "vendor/name", such as "acme/gallery".', $handle));
		}
	}

	/**
	 * Builds an asset from a manifest's description of it, such as a
	 * theme's `assets` in `theme.json` (D-574): its `styles` and
	 * `scripts`, each a path in the folder of what it's `from`, a full
	 * URL (`https://`, or `//`), or an object with that `path` and its
	 * `attributes` (and a script's `footer`), and the handles it
	 * `requires`.
	 *
	 *     "acme/nova-lightbox": {
	 *         "styles": ["css/lightbox.css"],
	 *         "scripts": [{"path": "js/lightbox.js", "footer": true, "attributes": {"type": "module"}}],
	 *         "requires": ["blush/player"]
	 *     }
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws AssetException When a value has the wrong shape.
	 */
	public static function fromArray(string $handle, array $data, string $from = ''): self
	{
		$unknown = array_diff(array_keys($data), ['styles', 'scripts', 'requires']);

		if ($unknown !== []) {
			throw new AssetException(sprintf('The "%s" asset has "%s", which isn\'t "styles", "scripts", or "requires".', $handle, implode('", "', $unknown)));
		}

		$requires = $data['requires'] ?? [];

		if (! is_array($requires) || ! array_is_list($requires) || ! array_all($requires, static fn (mixed $required): bool => is_string($required) && self::isHandle($required))) {
			throw new AssetException(sprintf('The "%s" asset\'s "requires" must be a list of handles (vendor/name).', $handle));
		}

		/** @var list<string> $requires */
		return new self(
			$handle,
			array_map(static function (array $file) use ($from): Style {
				/** @var array<string, string|bool> $attributes */
				$attributes = $file['attributes'];

				return new Style($file['path'], $file['url'] ? '' : $from, $attributes);
			}, self::files($handle, $data, 'styles')),
			array_map(static function (array $file) use ($from): Script {
				/** @var array<string, string|bool> $attributes */
				$attributes = $file['attributes'];

				return new Script($file['path'], $file['url'] ? '' : $from, $attributes, $file['footer']);
			}, self::files($handle, $data, 'scripts')),
			$requires
		);
	}

	/**
	 * Reads a list of files: paths, URLs, or objects.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return list<array{path: string, url: bool, attributes: array<array-key, mixed>, footer: bool}>
	 * @throws AssetException
	 */
	private static function files(string $handle, array $data, string $key): array
	{
		$value = $data[$key] ?? [];
		$error = new AssetException(sprintf(
			'The "%s" asset\'s "%s" must be a list of paths, full URLs, or objects with a "path" and its "attributes"%s.',
			$handle,
			$key,
			$key === 'scripts' ? ' (and "footer")' : ''
		));

		if (! is_array($value) || ! array_is_list($value)) {
			throw $error;
		}

		$files = [];

		foreach ($value as $file) {
			$file       = is_string($file) ? ['path' => $file] : $file;
			$path       = is_array($file) ? ($file['path'] ?? null) : null;
			$attributes = is_array($file) ? ($file['attributes'] ?? []) : null;
			$footer     = is_array($file) ? ($file['footer'] ?? false) : null;
			$allowed    = $key === 'scripts' ? ['path', 'attributes', 'footer'] : ['path', 'attributes'];

			if (
				! is_array($file)
				|| array_diff(array_keys($file), $allowed) !== []
				|| ! is_string($path)
				|| ! is_array($attributes)
				|| ($attributes !== [] && array_is_list($attributes))
				|| ! array_all($attributes, static fn (mixed $attribute): bool => is_string($attribute) || is_bool($attribute))
				|| ! is_bool($footer)
			) {
				throw $error;
			}

			$url = preg_match('#^(https?:)?//[^/]#i', $path) === 1;

			if (! $url && ! ThemeChain::isValidAssetPath($path)) {
				throw $error;
			}

			$files[] = ['path' => $path, 'url' => $url, 'attributes' => $attributes, 'footer' => $footer];
		}

		return $files;
	}

	/**
	 * Returns whether a string is a valid handle.
	 */
	public static function isHandle(string $handle): bool
	{
		return preg_match('#^' . self::HANDLE . '$#', $handle) === 1;
	}
}
