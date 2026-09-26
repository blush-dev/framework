<?php

/**
 * Body cache.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Entry;

use Closure;
use Blush\Markdown\MarkdownException;

/**
 * Keeps rendered bodies and summaries between requests. The key the body
 * gives names its source (a content hash, or a hash of the Markdown); an
 * implementation adds whatever else the rendering depends on. The
 * framework's is `Cache\RenderedBodies`, keyed by the content version and
 * the theme too (D-112).
 */
interface BodyCache
{
	/**
	 * Returns the cached HTML for a key, or renders and keeps it.
	 *
	 * @param  Closure(): string $render
	 * @throws MarkdownException From `$render`.
	 */
	public function remember(string $key, Closure $render): string;
}
