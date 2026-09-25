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
 * `DirectiveRenderer`: its name, kind, attributes, label (plain text), and
 * content (HTML: a container's rendered blocks, or the escaped label of a
 * leaf or inline directive).
 */
final readonly class Directive
{
	/**
	 * @param array<string, string> $attributes
	 */
	public function __construct(
		public string $name,
		public DirectiveKind $kind,
		public array $attributes = [],
		public string $label = '',
		public string $content = ''
	) {}
}
