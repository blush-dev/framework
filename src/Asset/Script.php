<?php

/**
 * Script.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

use Blush\Translation\Catalog;
use Blush\Translation\Translator;

/**
 * One of an asset's scripts (D-569): a file, where it's from (as for a
 * `Style`), its attributes, and whether it prints in the footer.
 *
 * Its attributes control how it loads, as `Head::script()` takes them:
 * scripts are deferred unless they say otherwise (`['defer' => false]`,
 * `['async' => true]`, or a `type`, such as `module`). In the footer,
 * it's added to the page's `Foot` instead of its head, and prints where
 * the layout prints `$template->foot()` (D-577, D-578).
 *
 * A script that shows text names the catalog it reads (D-831), one of
 * its domain's named catalogs (`lang/{catalog}/{locale}.json`):
 *
 *     new Script('js/player.js', AssetUrls::CORE, ['type' => 'module'], catalog: 'site');
 *
 * Its domain is its `from`'s (`core` for core's files, else the plugin's
 * or theme's `vendor/name`), and the page prints the catalog before it,
 * in the page's language, for `blush/messages` to read. A script from a
 * URL has no domain, so it can't name one.
 */
final readonly class Script
{
	/**
	 * @param  array<string, string|bool> $attributes
	 * @throws AssetException When it names a catalog it can't have.
	 */
	public function __construct(
		public string $path,
		public string $from = '',
		public array $attributes = [],
		public bool $footer = false,
		public ?string $catalog = null
	) {
		if ($catalog !== null && ! Catalog::isName($catalog)) {
			throw new AssetException(sprintf('"%s" isn\'t a catalog\'s name; use lowercase letters, digits, hyphens, and underscores, such as "site".', $catalog));
		}

		if ($catalog !== null && $from === '') {
			throw new AssetException(sprintf('The script at "%s" names the "%s" catalog, but a script from a URL has no domain to read it from.', $path, $catalog));
		}
	}

	/**
	 * Returns the translation domain its catalog is in (D-831): `core`
	 * for core's files, else its extension's `vendor/name`.
	 */
	public function domain(): string
	{
		return $this->from === AssetUrls::CORE ? Translator::CORE : $this->from;
	}
}
