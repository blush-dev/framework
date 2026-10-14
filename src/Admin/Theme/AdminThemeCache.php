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

namespace Blush\Admin\Theme;

use Blush\Extension\ExtensionException;
use Blush\Support\PhpArrayFile;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The compiled list of installed admin themes, with their tokens
 * (`storage/cache/admin-themes.php`), read everywhere but development,
 * like the icon pack cache. A broken theme's reason written with `t()` is
 * kept as its message and read back as one (D-836), so it's shown in the
 * language of whoever reads it, not whoever compiled the cache.
 */
final readonly class AdminThemeCache
{
	/**
	 * @param Messages $messages What reads the reasons back: one without a
	 *                           translator during boot, since they're
	 *                           translated where they're shown.
	 */
	public function __construct(
		private PhpArrayFile $file,
		private Messages $messages = new Messages()
	) {}

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

		$invalid = [];

		foreach ($data['invalid'] as $where => $reason) {
			$reason = is_string($reason) ? $reason : $this->messages->restore($reason);

			if ($reason !== null) {
				$invalid[(string) $where] = $reason;
			}
		}

		return new AdminThemes($themes, $invalid);
	}

	/**
	 * Writes the themes.
	 */
	public function write(AdminThemes $themes): void
	{
		$this->file->write([
			'themes'  => array_map(static fn (AdminTheme $theme): array => $theme->toArray(), $themes->all()),
			'invalid' => array_map(static fn (string|Text $reason): string|array => $reason instanceof Text ? $reason->toArray() : $reason, $themes->invalid())
		]);
	}
}
