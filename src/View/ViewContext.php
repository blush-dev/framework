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
 * template sees (such as `$site`), the `<body>` classes, the layout
 * an entry asks for in front matter (D-027), and the page's URL path and
 * locale, which menus read to mark the current item and pick their text
 * (D-199, D-202). A fragment rendered outside a page (a component in
 * Markdown) has no path.
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
	 * @param string               $path   The page's URL path, or `''` outside a page.
	 * @param string               $locale The page's locale (its entry's, else the site's), or `''` for the site's.
	 */
	public function __construct(
		public readonly Head $head = new Head(),
		public private(set) array $shared = [],
		public readonly ?string $layout = null,
		public readonly string $path = '',
		public readonly string $locale = ''
	) {}

	/**
	 * Adds data every template sees: the page's own data, so partials,
	 * layouts, and components can use it without it being passed along
	 * (D-146). A template's own data wins over it.
	 *
	 * @param array<string, mixed> $data
	 */
	public function share(array $data): void
	{
		$this->shared = [...$this->shared, ...$data];
	}

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
