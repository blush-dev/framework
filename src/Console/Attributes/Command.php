<?php

/**
 * Command attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Attributes;

use Attribute;

/**
 * Marks an invokable class as a console command (D-065). The class's
 * constructor takes services, and its `__invoke()` parameters declare the
 * command's input with `#[Argument]` and `#[Option]`.
 *
 *     #[Command('cache:clear', 'Clear the compiled caches.')]
 *     final readonly class CacheClear
 *     {
 *         public function __invoke(Output $output, #[Option] bool $config = false): ExitCode
 *     }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Command
{
	/**
	 * @param list<string> $aliases Other names the command answers to.
	 * @param bool         $hidden  Whether to leave the command out of `list`.
	 */
	public function __construct(
		public string $name,
		public string $description = '',
		public array $aliases = [],
		public bool $hidden = false
	) {}
}
