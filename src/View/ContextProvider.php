<?php

/**
 * Context provider interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

/**
 * Supplies data to views, so templates don't run queries (see
 * `theming.md`). A provider is attached to view names or patterns in a
 * theme or site provider:
 *
 *     $container->make(ContextProviders::class)->add('partials/header', PrimaryMenu::class);
 *
 * Its values are defaults: data a template is given explicitly wins.
 */
interface ContextProvider
{
	/**
	 * Returns data for a view, given the data it already has.
	 *
	 * @param  array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function provide(string $view, array $data): array;
}
