<?php

/**
 * Cache namespaces.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

/**
 * The framework's cache namespaces. Each is its own store, so clearing
 * one never touches another. Extensions may use any other name.
 *
 * Every namespace but `webhooks` holds values derived from the site,
 * which `Caches::clear()` (and so `cache:clear` and publishing) removes.
 */
enum CacheNamespace: string
{
	/**
	 * Whole responses, for `PageCache`.
	 */
	case Pages = 'pages';

	/**
	 * Rendered entry bodies and summaries.
	 */
	case Bodies = 'bodies';

	/**
	 * Compiled design-token CSS, per theme chain.
	 */
	case Tokens = 'tokens';

	/**
	 * Anything else a site, theme, or extension computes.
	 */
	case Fragments = 'fragments';

	/**
	 * Signed webhook requests already seen, so none can be replayed. Never
	 * cleared with the others.
	 */
	case Webhooks = 'webhooks';

	/**
	 * Returns whether the namespace holds derived values that clearing
	 * the caches removes.
	 */
	public function isDerived(): bool
	{
		return $this !== self::Webhooks;
	}
}
