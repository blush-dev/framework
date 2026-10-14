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

use Blush\Data\InvalidData;
use Blush\Theme\ThemeException;
use Blush\View\PageMarkup;
use Blush\View\Placement;
use Blush\View\ViewException;

/**
 * Adds the assets a page asked for to its markup (D-570, D-578): for
 * each handle, in the order `AssetRegistry::ordered()` gives (what it
 * requires first), its stylesheets to the head and its scripts to the
 * head or, when they say `footer`, the foot, at their URLs. A file with
 * no URL (its plugin is off, or it's missing) is skipped. Tags are keyed
 * by URL, so a file two assets share prints once. Each asset's first and
 * last script are recorded, for the data and inline code tied to it
 * (D-580).
 *
 * A script that names a catalog (D-831) gets it printed before the
 * asset's first script, in the page's language, and the head maps
 * `blush/messages`, core's module that reads it, in its import map.
 */
final readonly class Assets
{
	/**
	 * The module scripts import to read their catalogs (D-831).
	 */
	public const string MESSAGES = 'blush/messages';

	/**
	 * Its file in core's built site files.
	 */
	public const string MESSAGES_PATH = 'js/messages.js';

	public function __construct(
		public AssetRegistry $registry,
		private AssetUrls $urls
	) {}

	/**
	 * Adds the files of the handles to a page's markup.
	 *
	 * @param  list<string> $handles
	 * @throws ThemeException When a theme's build manifest is invalid.
	 * @throws InvalidData When a catalog can't be parsed.
	 * @throws ViewException When a catalog can't be JSON.
	 */
	public function apply(PageMarkup $markup, array $handles): void
	{
		foreach ($this->registry->ordered($handles) as $asset) {
			foreach ($asset->styles as $style) {
				$url = $this->urls->url($style->path, $style->from);

				if ($url !== null) {
					$markup->head->style($url, $style->attributes);
				}
			}

			$keys = [];

			foreach ($asset->scripts as $script) {
				$url = $this->urls->url($script->path, $script->from);

				if ($url !== null) {
					$script->footer
						? $markup->foot->script($url, $script->attributes)
						: $markup->head->script($url, $script->attributes);

					$keys[] = "script:{$url}";

					if ($script->catalog !== null) {
						$this->messages($markup, $script, $asset->handle);
					}
				}
			}

			if ($keys !== []) {
				$markup->bind($asset->handle, $keys[0], $keys[array_key_last($keys)]);
			}
		}
	}

	/**
	 * Adds the catalog a script reads and maps `blush/messages`.
	 *
	 * @throws InvalidData
	 * @throws ViewException
	 */
	private function messages(PageMarkup $markup, Script $script, string $handle): void
	{
		$markup->addMessages($script->footer ? Placement::Foot : Placement::Head, $script->domain(), (string) $script->catalog, $handle);

		$module = $this->urls->url(self::MESSAGES_PATH, AssetUrls::CORE);

		if ($module !== null) {
			$markup->import(self::MESSAGES, $module);
		}
	}
}
