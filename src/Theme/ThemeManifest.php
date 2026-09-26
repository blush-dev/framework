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
 * A theme with PHP names a `provider` (a service provider, registered
 * before the site's, D-054) and, for a local theme, the `autoload.psr-4`
 * map Blush registers for it. Its `settings` are field definitions
 * (D-022); keys this version doesn't read (image sizes, menus, regions)
 * are kept in `$data`.
 */
final readonly class ThemeManifest
{
	/**
	 * @param string               $path    The theme's absolute folder.
	 * @param list<string>         $styles  Stylesheet paths, relative to the theme (resolved through the chain).
	 * @param list<string>         $scripts Script paths, relative to the theme.
	 * @param array<string, mixed>  $data     The whole manifest.
	 * @param ?string               $provider A service provider class.
	 * @param array<string, string> $autoload PSR-4 prefixes and their folders, relative to the theme.
	 * @param bool                  $inheritTokens Whether its parents' and the default theme's tokens apply (D-148).
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
		public array $data = [],
		public ThemeSource $source = ThemeSource::Local,
		public ?string $provider = null,
		public array $autoload = [],
		public bool $inheritTokens = true
	) {}

	/**
	 * Builds a manifest from parsed data.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ThemeException When a value has the wrong type.
	 */
	public static function fromArray(string $slug, string $path, array $data, ThemeSource $source = ThemeSource::Local): self
	{
		$name = $data['name'] ?? null;

		if (! is_string($name) || trim($name) === '') {
			throw new ThemeException(sprintf('The "%s" theme\'s manifest needs a "name".', $slug));
		}

		$parent = $data['parent'] ?? null;

		if ($parent !== null && (! is_string($parent) || ! Themes::isValidSlug($parent))) {
			throw new ThemeException(sprintf('The "%s" theme\'s "parent" must be a theme slug.', $slug));
		}

		$provider = $data['provider'] ?? null;

		if ($provider !== null && (! is_string($provider) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $provider) !== 1)) {
			throw new ThemeException(sprintf('The "%s" theme\'s "provider" must be a class name.', $slug));
		}

		$inheritTokens = $data['inheritTokens'] ?? true;

		if (! is_bool($inheritTokens)) {
			throw new ThemeException(sprintf('The "%s" theme\'s "inheritTokens" must be true or false.', $slug));
		}

		$settings = $data['settings'] ?? [];

		if (! is_array($settings) || ($settings !== [] && array_is_list($settings)) || ! array_all($settings, static fn (mixed $item): bool => is_array($item))) {
			throw new ThemeException(sprintf('The "%s" theme\'s "settings" must map names to field definitions.', $slug));
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
			data: $data,
			source: $source,
			provider: $provider,
			autoload: self::autoload($slug, $data),
			inheritTokens: $inheritTokens
		);
	}

	/**
	 * Returns the manifest's setting definitions, by name.
	 *
	 * @return array<string, array<array-key, mixed>>
	 */
	public function settings(): array
	{
		/** @var array<string, array<array-key, mixed>> Checked by `fromArray()`. */
		return $this->data['settings'] ?? [];
	}

	/**
	 * Returns what the theme cache stores.
	 *
	 * @return array{path: string, source: string, data: array<string, mixed>}
	 */
	public function toArray(): array
	{
		return ['path' => $this->path, 'source' => $this->source->value, 'data' => $this->data];
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
	 * Reads the `autoload.psr-4` map.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<string, string>
	 * @throws ThemeException
	 */
	private static function autoload(string $slug, array $data): array
	{
		$autoload = $data['autoload'] ?? [];
		$map      = is_array($autoload) ? ($autoload['psr-4'] ?? []) : null;

		if (! is_array($map)) {
			throw new ThemeException(sprintf('The "%s" theme\'s "autoload" must be {"psr-4": {"Prefix\\\\": "src/"}}.', $slug));
		}

		$psr4 = [];

		foreach ($map as $prefix => $directory) {
			if (! is_string($prefix) || ! is_string($directory) || ! ThemeChain::isValidAssetPath(trim($directory, '/'))) {
				throw new ThemeException(sprintf('The "%s" theme\'s "autoload.psr-4" must map namespace prefixes to folders inside the theme.', $slug));
			}

			$psr4[rtrim($prefix, '\\') . '\\'] = trim($directory, '/');
		}

		return $psr4;
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
