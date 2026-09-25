<?php

/**
 * Theme cache.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Blush\Support\PhpArrayFile;

/**
 * The compiled list of installed themes (`storage/cache/themes.php`),
 * read everywhere but development, like the extension cache (D-058).
 */
final readonly class ThemeCache
{
	public function __construct(private PhpArrayFile $file)
	{}

	/**
	 * Returns the cached themes, or `null` when there's no usable cache.
	 */
	public function read(): ?Themes
	{
		$data = $this->file->read();

		if (! is_array($data['themes'] ?? null) || ! is_array($data['invalid'] ?? null)) {
			return null;
		}

		$themes = [];

		try {
			foreach ($data['themes'] as $slug => $theme) {
				if (! is_array($theme) || ! is_string($theme['path'] ?? null) || ! is_array($theme['data'] ?? null)) {
					return null;
				}

				$themes[(string) $slug] = ThemeManifest::fromArray(
					(string) $slug,
					$theme['path'],
					$theme['data'],
					ThemeSource::tryFrom(is_string($theme['source'] ?? null) ? $theme['source'] : '') ?? ThemeSource::Local
				);
			}
		} catch (ThemeException) {
			return null;
		}

		/** @var array<string, string> $invalid */
		$invalid = array_filter($data['invalid'], is_string(...));

		return new Themes($themes, $invalid);
	}

	/**
	 * Writes the themes.
	 */
	public function write(Themes $themes): void
	{
		$this->file->write([
			'themes'  => array_map(static fn (ThemeManifest $theme): array => $theme->toArray(), $themes->all()),
			'invalid' => $themes->invalid()
		]);
	}
}
