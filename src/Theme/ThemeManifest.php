<?php

/**
 * Theme manifest.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

/**
 * A theme, as its `theme.json` (or `.yaml`, D-032) describes it: a name,
 * a version, an optional parent, and the stylesheets and scripts every
 * page loads. Only `name` is required; `styles` defaults to
 * `["style.css"]`, so the smallest theme is a manifest and a stylesheet
 * (D-021).
 *
 * Keys this version doesn't read yet (settings, image sizes, menus,
 * regions, the provider) are kept in `$data`.
 */
final readonly class ThemeManifest
{
	/**
	 * @param string               $path    The theme's absolute folder.
	 * @param list<string>         $styles  Stylesheet paths, relative to the theme (resolved through the chain).
	 * @param list<string>         $scripts Script paths, relative to the theme.
	 * @param array<string, mixed> $data    The whole manifest.
	 */
	public function __construct(
		public string $slug,
		public string $path,
		public string $name,
		public string $version = '',
		public ?string $parent = null,
		public string $description = '',
		public array $styles = ['style.css'],
		public array $scripts = [],
		public array $data = []
	) {}

	/**
	 * Builds a manifest from parsed data.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ThemeException When a value has the wrong type.
	 */
	public static function fromArray(string $slug, string $path, array $data): self
	{
		$name = $data['name'] ?? null;

		if (! is_string($name) || trim($name) === '') {
			throw new ThemeException(sprintf('The "%s" theme\'s manifest needs a "name".', $slug));
		}

		$parent = $data['parent'] ?? null;

		if ($parent !== null && (! is_string($parent) || ! Themes::isValidSlug($parent))) {
			throw new ThemeException(sprintf('The "%s" theme\'s "parent" must be a theme slug.', $slug));
		}

		/** @var array<string, mixed> $data */
		return new self(
			slug: $slug,
			path: $path,
			name: trim($name),
			version: self::string($slug, $data, 'version'),
			parent: $parent,
			description: self::string($slug, $data, 'description'),
			styles: self::paths($slug, $data, 'styles', ['style.css']),
			scripts: self::paths($slug, $data, 'scripts', []),
			data: $data
		);
	}

	/**
	 * Returns the theme's views folder.
	 */
	public function viewsPath(): string
	{
		return "{$this->path}/views";
	}

	/**
	 * Returns the theme's message catalog folder.
	 */
	public function langPath(): string
	{
		return "{$this->path}/lang";
	}

	/**
	 * Reads an optional string.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ThemeException
	 */
	private static function string(string $slug, array $data, string $key): string
	{
		$value = $data[$key] ?? '';

		return is_string($value) ? $value : throw new ThemeException(sprintf('The "%s" theme\'s "%s" must be a string.', $slug, $key));
	}

	/**
	 * Reads an optional list of relative asset paths.
	 *
	 * @param  array<array-key, mixed> $data
	 * @param  list<string>            $default
	 * @return list<string>
	 * @throws ThemeException
	 */
	private static function paths(string $slug, array $data, string $key, array $default): array
	{
		$value = $data[$key] ?? $default;

		if (! is_array($value) || ! array_is_list($value)) {
			throw new ThemeException(sprintf('The "%s" theme\'s "%s" must be a list of paths.', $slug, $key));
		}

		$paths = [];

		foreach ($value as $path) {
			if (! is_string($path) || ! ThemeChain::isValidAssetPath($path)) {
				throw new ThemeException(sprintf('The "%s" theme\'s "%s" must be a list of paths inside the theme.', $slug, $key));
			}

			$paths[] = $path;
		}

		return $paths;
	}
}
