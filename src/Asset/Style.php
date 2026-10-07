<?php

/**
 * Stylesheet.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

/**
 * One of an asset's stylesheets (D-569): a file and where it's from,
 * which `AssetUrls` turns into a versioned URL.
 *
 * `$from` is `blush` for core's own files, a plugin's or theme's
 * `vendor/name` for a file in its folder, or `''` for a URL as given
 * (`/css/print.css`, `https://cdn.example.com/a.css`).
 */
final readonly class Style
{
	/**
	 * @param array<string, string|bool> $attributes More attributes for its `<link>`, such as `media`.
	 */
	public function __construct(
		public string $path,
		public string $from = '',
		public array $attributes = []
	) {}
}
