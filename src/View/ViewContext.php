<?php

/**
 * View context.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

/**
 * The state of one page render, shared by every template it takes: the
 * `Head`, the sections templates define for their layouts, the data every
 * template sees (such as `$site`), the `<body>` classes, and the layout
 * an entry asks for in front matter (D-027).
 *
 * A context lives for one render; renderers build a new one per page.
 */
final class ViewContext
{
	/**
	 * Sections by name.
	 *
	 * @var array<string, string>
	 */
	private array $sections = [];

	/**
	 * `<body>` classes, as a set.
	 *
	 * @var array<string, true>
	 */
	private array $classes = [];

	/**
	 * @param array<string, mixed> $shared Data every template sees.
	 * @param ?string              $layout A layout that replaces the one the page's template asks for.
	 */
	public function __construct(
		public readonly Head $head = new Head(),
		public readonly array $shared = [],
		public readonly ?string $layout = null
	) {}

	/**
	 * Sets a section's content.
	 */
	public function setSection(string $name, string $content): void
	{
		$this->sections[$name] = $content;
	}

	/**
	 * Returns a section's content, or `null` when it isn't set.
	 */
	public function section(string $name): ?string
	{
		return $this->sections[$name] ?? null;
	}

	/**
	 * Adds `<body>` classes. Values that aren't valid class names are
	 * skipped.
	 */
	public function addClass(string ...$classes): void
	{
		foreach ($classes as $class) {
			foreach (preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $name) {
				if (preg_match('/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/', $name) === 1) {
					$this->classes[$name] = true;
				}
			}
		}
	}

	/**
	 * Returns the `<body>` classes.
	 *
	 * @return list<string>
	 */
	public function classes(): array
	{
		return array_keys($this->classes);
	}
}
