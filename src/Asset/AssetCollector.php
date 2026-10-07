<?php

/**
 * Asset collector.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

use Closure;

/**
 * Collects the asset handles what's rendering asks for (D-572), so they
 * reach the page that prints them, wherever they were asked for.
 *
 * A page opens a scope for its render (`Views::render()`), and so does
 * anything whose HTML is cached, such as a fragment: a directive or
 * component adds its handles to the innermost scope, and a scope that
 * closes passes its handles out to the one around it. A cached fragment
 * keeps its handles beside its HTML and adds them again whenever it's
 * printed, so the page gets them though nothing rendered.
 *
 * An entry's body renders in an isolated scope (`isolate()`), whose
 * handles don't pass out: the body keeps them and adds them only when
 * its HTML is asked for (`Body::html()`), so a listing that reads a body
 * only for its excerpt doesn't load what its directives need. Outside
 * any scope (a feed printing a body), handles go nowhere.
 *
 * It holds state only while something renders.
 */
final class AssetCollector
{
	/**
	 * The open scopes, innermost last, each a set of handles.
	 *
	 * @var list<array<string, true>>
	 */
	private array $scopes = [];

	/**
	 * Runs a render in its own scope and returns its result with the
	 * handles asked for during it, which also go to the scope around it.
	 *
	 * @template T
	 * @param  Closure(): T $render
	 * @return array{T, list<string>}
	 */
	public function collect(Closure $render): array
	{
		[$result, $handles] = $this->isolate($render);

		$this->add(...$handles);

		return [$result, $handles];
	}

	/**
	 * Runs a render in its own scope and returns its result with the
	 * handles asked for during it, which stay out of the scope around
	 * it: for the caller to add when it's used.
	 *
	 * @template T
	 * @param  Closure(): T $render
	 * @return array{T, list<string>}
	 */
	public function isolate(Closure $render): array
	{
		$this->scopes[] = [];

		try {
			$result = $render();
		} finally {
			$handles = array_keys(array_pop($this->scopes) ?? []);
		}

		return [$result, $handles];
	}

	/**
	 * Asks for assets in the current scope, if there is one.
	 */
	public function add(string ...$handles): void
	{
		$last = array_key_last($this->scopes);

		if ($last === null) {
			return;
		}

		foreach ($handles as $handle) {
			$this->scopes[$last][$handle] = true;
		}
	}
}
