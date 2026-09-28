<?php

/**
 * Directive.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

/**
 * A generic directive found in Markdown (D-026), handed to the
 * `DirectiveRenderer`: its name, kind, attributes, label (plain text),
 * content (HTML: a container's rendered blocks, or the escaped label of a
 * leaf or inline directive), and the base folder under `user/content` of
 * the entry it's in, for resolving bundle media (D-179). A table of
 * contents directive also gets the document's outline (D-183): each
 * heading's level, plain text, and link target, in order.
 */
final readonly class Directive
{
	/**
	 * @param array<string, string>                              $attributes
	 * @param list<array{level: int, text: string, id: string}> $outline
	 */
	public function __construct(
		public string $name,
		public DirectiveKind $kind,
		public array $attributes = [],
		public string $label = '',
		public string $content = '',
		public string $base = '',
		public array $outline = []
	) {}
}
