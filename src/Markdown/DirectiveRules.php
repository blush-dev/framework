<?php

/**
 * Directive rules interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

/**
 * What a `DirectiveRenderer` that knows its directives says of them, for
 * the parser: the forms each is written in (D-530), and what a container
 * holds when it's only some things (D-529). `Component\ComponentDirectives`
 * answers from each component's registration.
 */
interface DirectiveRules
{
	/**
	 * Returns the forms a directive is registered for, or `null` when it's
	 * unknown, so any form goes. Written in another form, it renders as
	 * an unknown directive does; a `:::` line for one that isn't a
	 * container is a line of its own, never opening one, so it can't take
	 * a closing fence meant for a container around it.
	 *
	 * @return non-empty-list<DirectiveKind>|null
	 */
	public function forms(string $directive): ?array;

	/**
	 * Returns what a container directive holds: `image` for Markdown
	 * images and the full names of directives, or an empty list when it
	 * holds anything.
	 *
	 * @return list<string>
	 */
	public function holds(string $container): array;

	/**
	 * Returns a directive's full name, as `holds()` lists it, or the name
	 * as given when it has none.
	 */
	public function fullName(string $directive): string;
}
