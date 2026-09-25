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
 * 1. A Vite-style manifest (`dist/.vite/manifest.json` or
 *    `dist/manifest.json`) that lists the path as an entry gives its
 *    built, hashed file: `src/main.js` → `/themes/nova/dist/assets/main-4f2a.js`.
 * 2. Otherwise a file at the path gives its URL, versioned by mtime:
 *    `/themes/nova/style.css?v=1700000000`.
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
		'dist/.vite/manifest.json' => 'dist',
		'dist/manifest.json'       => 'dist'
	];

	/**
	 * Manifests read so far, by slug: the entries and their base folder.
	 *
	 * @var array<string, array{array<string, mixed>, string}>
	 */
	private array $manifests = [];

	public function __construct(public readonly ThemeChain $chain)
	{}

	/**
	 * Returns an asset's URL, or `null` when no theme has it.
	 *
	 * @throws ThemeException When a manifest is invalid.
	 */
	public function url(string $path): ?string
	{
		foreach ($this->chain as $theme) {
			[$entry, $base] = $this->entry($theme, $path);

			if (is_string($entry['file'] ?? null) && ThemeChain::isServable("{$base}/{$entry['file']}")) {
				return sprintf('%s/%s/%s/%s', ThemeChain::ASSET_URL, $theme->slug, $base, $entry['file']);
			}

			if (ThemeChain::isServable($path) && is_file("{$theme->path}/{$path}")) {
				return sprintf('%s/%s/%s?v=%d', ThemeChain::ASSET_URL, $theme->slug, $path, (int) filemtime("{$theme->path}/{$path}"));
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
					static fn (string $file): string => sprintf('%s/%s/%s/%s', ThemeChain::ASSET_URL, $theme->slug, $base, $file),
					array_filter($files, static fn (mixed $file): bool => is_string($file) && ThemeChain::isServable("{$base}/{$file}"))
				));
			}
		}

		return [];
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
		[$entries, $base] = $this->manifests[$theme->slug] ??= self::read($theme);
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
				throw new ThemeException(sprintf('The "%s" theme\'s %s is invalid: %s', $theme->slug, $file, $error->getMessage()), 0, $error);
			}

			/** @var array<string, mixed> $entries */
			$entries = is_array($data) ? $data : [];

			return [$entries, $base];
		}

		return [[], 'dist'];
	}
}
