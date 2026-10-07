<?php

/**
 * Assets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

use Blush\Theme\ThemeException;
use Blush\View\Head;

/**
 * Prints the assets a page asked for into its head (D-570): for each
 * handle, in the order `AssetRegistry::ordered()` gives (what it
 * requires first), its stylesheets and scripts at their URLs. A file
 * with no URL (its plugin is off, or it's missing) is skipped. The head
 * keys each by URL, so a file two assets share prints once.
 */
final readonly class Assets
{
	public function __construct(
		public AssetRegistry $registry,
		private AssetUrls $urls
	) {}

	/**
	 * Adds the files of the handles to a head.
	 *
	 * @param  list<string> $handles
	 * @throws ThemeException When a theme's build manifest is invalid.
	 */
	public function apply(Head $head, array $handles): void
	{
		foreach ($this->registry->ordered($handles) as $asset) {
			foreach ($asset->styles as $style) {
				$url = $this->urls->url($style->path, $style->from);

				if ($url !== null) {
					$head->style($url, $style->attributes);
				}
			}

			foreach ($asset->scripts as $script) {
				$url = $this->urls->url($script->path, $script->from);

				if ($url !== null) {
					$head->script($url, $script->attributes, $script->footer);
				}
			}
		}
	}
}
