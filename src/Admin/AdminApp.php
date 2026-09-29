<?php

/**
 * Admin front end.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;

/**
 * The built admin front end (D-221, D-222): the framework's bundled Vue
 * app in `public/admin`, or the folder `AdminConfig::$app` names. Either
 * is a Vite build: an entry script and its styles, listed in
 * `.vite/manifest.json`. File names carry no hashes; `url()` versions
 * each with `?v=` and a CRC32 of its contents, as theme assets are
 * (D-194, D-224). Every file in the folder but the manifest's is
 * servable, at `{path}/assets/{file}`.
 */
final readonly class AdminApp
{
	/**
	 * The bundled build, relative to this file.
	 */
	public const string BUNDLED = __DIR__ . '/../../public/admin';

	/**
	 * The file types served, with their media types.
	 */
	public const array TYPES = [
		'js'    => 'text/javascript',
		'css'   => 'text/css',
		'svg'   => 'image/svg+xml',
		'png'   => 'image/png',
		'webp'  => 'image/webp',
		'jpg'   => 'image/jpeg',
		'woff2' => 'font/woff2'
	];

	public function __construct(private AdminConfig $config)
	{}

	/**
	 * Returns a build file's URL, versioned with a hash of its contents
	 * (none when the file doesn't exist).
	 */
	public function url(string $file): string
	{
		$url  = "{$this->config->path}/assets/{$file}";
		$path = $this->asset($file);

		return $path === null ? $url : $url . '?v=' . hash_file('crc32b', $path);
	}

	/**
	 * Returns the build's folder.
	 */
	public function directory(): string
	{
		return rtrim($this->config->app ?? (realpath(self::BUNDLED) ?: self::BUNDLED), '/');
	}

	/**
	 * Returns the entry's script and styles, relative to the folder, or
	 * `null` when there's no usable build.
	 *
	 * @return ?array{script: string, styles: list<string>}
	 */
	public function entry(): ?array
	{
		$contents = @file_get_contents($this->directory() . '/.vite/manifest.json');

		try {
			$manifest = $contents === false ? null : json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return null;
		}

		foreach (is_array($manifest) ? $manifest : [] as $chunk) {
			if (is_array($chunk) && ($chunk['isEntry'] ?? false) === true && is_string($chunk['file'] ?? null)) {
				$styles = is_array($chunk['css'] ?? null) ? array_values(array_filter($chunk['css'], is_string(...))) : [];

				return ['script' => $chunk['file'], 'styles' => $styles];
			}
		}

		return null;
	}

	/**
	 * Returns the absolute path of a servable build file, or `null`. Only
	 * the listed file types are served, and nothing under a dot folder
	 * (such as `.vite/`).
	 */
	public function asset(string $path): ?string
	{
		if (preg_match('#^[A-Za-z0-9_-][A-Za-z0-9_.-]*(/[A-Za-z0-9_-][A-Za-z0-9_.-]*)*$#', $path) !== 1 || str_contains($path, '..')) {
			return null;
		}

		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		$file      = $this->directory() . "/{$path}";

		return isset(self::TYPES[$extension]) && is_file($file) ? $file : null;
	}
}
