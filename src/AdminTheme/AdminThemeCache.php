<?php

/**
 * Admin theme cache.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\AdminTheme;

use Blush\Extension\ExtensionException;
use Blush\Support\PhpArrayFile;

/**
 * The compiled list of installed admin themes, with their tokens
 * (`storage/cache/admin-themes.php`), read everywhere but development,
 * like the icon pack cache.
 */
final readonly class AdminThemeCache
{
	public function __construct(private PhpArrayFile $file)
	{}

	/**
	 * Returns the cached themes, or `null` when there's no usable cache.
	 */
	public function read(): ?AdminThemes
	{
		$data = $this->file->read();

		if (! is_array($data['themes'] ?? null) || ! is_array($data['invalid'] ?? null)) {
			return null;
		}

		$themes = [];

		try {
			foreach ($data['themes'] as $name => $theme) {
				if (! is_array($theme) || ! is_string($theme['path'] ?? null) || ! is_array($theme['data'] ?? null) || ! is_array($theme['tokens'] ?? null)) {
					return null;
				}

				$themes[(string) $name] = AdminTheme::fromArray(
					$theme['path'],
					$theme['data'],
					AdminThemeSource::tryFrom(is_string($theme['source'] ?? null) ? $theme['source'] : '') ?? AdminThemeSource::Local,
					$theme['tokens']
				);
			}
		} catch (ExtensionException) {
			return null;
		}

		/** @var array<string, string> $invalid */
		$invalid = array_filter($data['invalid'], is_string(...));

		return new AdminThemes($themes, $invalid);
	}

	/**
	 * Writes the themes.
	 */
	public function write(AdminThemes $themes): void
	{
		$this->file->write([
			'themes'  => array_map(static fn (AdminTheme $theme): array => $theme->toArray(), $themes->all()),
			'invalid' => $themes->invalid()
		]);
	}
}
