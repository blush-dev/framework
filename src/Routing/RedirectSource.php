<?php

/**
 * Redirect source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

/**
 * Supplies redirects to the route table: `config/routes.php`, then the
 * `redirects` table (D-678; `redirect_from` front matter went, D-680). An
 * extension adds redirects by tagging its source with `RedirectSource::TAG`.
 * Earlier sources win when two redirect the same path.
 */
interface RedirectSource
{
	/**
	 * The container tag for redirect sources.
	 */
	public const string TAG = 'routing.redirects';

	/**
	 * Returns the redirects.
	 *
	 * @return iterable<Redirect>
	 */
	public function redirects(): iterable;
}
