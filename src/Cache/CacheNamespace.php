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
	 * Anything else a site, theme, or extension computes.
	 */
	case Fragments = 'fragments';

	/**
	 * Signed webhook requests already seen, so none can be replayed, and
	 * failed-signature counters (`WebhookThrottle`). Never cleared with the
	 * others, so clearing caches doesn't reset either.
	 */
	case Webhooks = 'webhooks';

	/**
	 * oEmbed answers (D-184), which expire on their own. Never cleared with
	 * the others, so publishing doesn't ask every provider again;
	 * `cache:clear --embeds` empties it (D-448).
	 */
	case Embeds = 'embeds';

	/**
	 * Failed sign-in counters (D-219), which expire on their own. Never
	 * cleared with the others, so clearing caches doesn't reset a lockout.
	 */
	case Logins = 'logins';

	/**
	 * Returns whether the namespace holds derived values that clearing
	 * the caches removes.
	 */
	public function isDerived(): bool
	{
		return ! in_array($this, [self::Webhooks, self::Embeds, self::Logins], true);
	}
}
