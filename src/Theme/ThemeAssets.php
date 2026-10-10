<?php

/**
 * Theme assets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use JsonException;

/**
 * Resolves asset URLs for a theme chain (D-031). For each theme, nearest
 * first:
 *
 * 1. A Vite-style manifest (`public/.vite/manifest.json`, D-155, or
 *    `dist/.vite/manifest.json`, or either without `.vite/`) that lists
 *    the path gives its built file:
 *    `resources/js/app.js` → `/themes/acme/nova/public/js/app.js?v=4f2a9c1b`.
 * 2. Otherwise a file at the path gives its URL:
 *    `/themes/acme/nova/style.css?v=77aa03de`.
 *
 * Either way, `?v=` is a hash of the file's contents (D-194), so a URL
 * changes only when its file does.
 *
 * Build tooling is the theme's choice; a theme without a build step never
 * has a manifest.
 */
final class ThemeAssets
{
	/**
	 * Manifest locations, relative to the theme, and the folder their
	 * files are relative to.
	 *
	 * @var array<string, string>
	 */
	public const array MANIFESTS = [
		'public/.vite/manifest.json' => 'public',
		'public/manifest.json'       => 'public',
		'dist/.vite/manifest.json'   => 'dist',
		'dist/manifest.json'         => 'dist'
	];

	/**
	 * Manifests read so far, by theme name: the entries and their base folder.
	 *
	 * @var array<string, array{array<string, mixed>, string}>
	 */
	private array $manifests = [];

	/**
	 * File versions computed so far, by absolute path.
	 *
	 * @var array<string, string>
	 */
	private array $versions = [];

	public function __construct(public readonly ThemeChain $chain)
	{}

	/**
	 * Returns an asset's URL, or `null` when no theme has it. Without
	 * `$version`, the URL has no `?v=`, as a file's stylesheet asks for
	 * it (D-698).
	 *
	 * @throws ThemeException When a manifest is invalid.
	 */
	public function url(string $path, bool $version = true): ?string
	{
		foreach ($this->chain as $theme) {
			[$entry, $base] = $this->entry($theme, $path);

			if (is_string($entry['file'] ?? null) && ThemeChain::isServable("{$base}/{$entry['file']}")) {
				return $this->versioned($theme, "{$base}/{$entry['file']}", $version);
			}

			if (ThemeChain::isServable($path) && is_file("{$theme->path}/{$path}")) {
				return $this->versioned($theme, $path, $version);
			}
		}

		return null;
	}

	/**
	 * Returns whether a path resolves to a build manifest entry.
	 *
	 * @throws ThemeException
	 */
	public function isBuilt(string $path): bool
	{
		foreach ($this->chain as $theme) {
			if ($this->entry($theme, $path)[0] !== null) {
				return true;
			}

			if (is_file("{$theme->path}/{$path}")) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Returns the stylesheets a manifest entry imports (Vite's `css`
	 * list), as URLs.
	 *
	 * @return list<string>
	 * @throws ThemeException
	 */
	public function css(string $path): array
	{
		foreach ($this->chain as $theme) {
			[$entry, $base] = $this->entry($theme, $path);

			if ($entry !== null) {
				$files = is_array($entry['css'] ?? null) ? $entry['css'] : [];

				return array_values(array_map(
					fn (string $file): string => $this->versioned($theme, "{$base}/{$file}"),
					array_filter($files, static fn (mixed $file): bool => is_string($file) && ThemeChain::isServable("{$base}/{$file}"))
				));
			}
		}

		return [];
	}

	/**
	 * Returns a theme file's URL with its version, a CRC32 of its
	 * contents (eight hex characters). A missing file, or one asked for
	 * without a version, has none.
	 */
	private function versioned(ThemeManifest $theme, string $path, bool $version = true): string
	{
		$url  = sprintf('%s/%s/%s', ThemeChain::ASSET_URL, $theme->name, $path);
		$file = "{$theme->path}/{$path}";

		if (! $version || ! is_file($file)) {
			return $url;
		}

		$this->versions[$file] ??= (string) hash_file('crc32b', $file);

		return "{$url}?v={$this->versions[$file]}";
	}

	/**
	 * Returns a theme's manifest entry for a path, and the folder its
	 * files are relative to.
	 *
	 * @return array{?array<array-key, mixed>, string}
	 * @throws ThemeException
	 */
	private function entry(ThemeManifest $theme, string $path): array
	{
		[$entries, $base] = $this->manifests[$theme->name] ??= self::read($theme);
		$entry            = $entries[$path] ?? null;

		return [is_array($entry) ? $entry : null, $base];
	}

	/**
	 * Reads a theme's manifest, if it has one.
	 *
	 * @return array{array<string, mixed>, string}
	 * @throws ThemeException
	 */
	private static function read(ThemeManifest $theme): array
	{
		foreach (self::MANIFESTS as $file => $base) {
			if (! is_file("{$theme->path}/{$file}")) {
				continue;
			}

			try {
				$data = json_decode((string) file_get_contents("{$theme->path}/{$file}"), true, 512, JSON_THROW_ON_ERROR);
			} catch (JsonException $error) {
				throw new ThemeException(sprintf('The "%s" theme\'s %s is invalid: %s', $theme->name, $file, $error->getMessage()), 0, $error);
			}

			/** @var array<string, mixed> $entries */
			$entries = is_array($data) ? $data : [];

			return [$entries, $base];
		}

		return [[], 'dist'];
	}
}
