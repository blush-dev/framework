<?php

/**
 * Component name.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use Override;
use Stringable;

/**
 * A component's name, `{namespace}/{name}` (D-171, D-532): a theme's or
 * plugin's namespace (D-378), or `app` for the site's own. There are no
 * core components, so a component is always written with its namespace.
 *
 * A component's template is `components/{namespace}-{name}`.
 */
final readonly class ComponentName implements Stringable
{
	/**
	 * The site's own components' namespace.
	 */
	public const string SITE = 'app';

	/**
	 * The syntax of a name, as a regex fragment without delimiters or
	 * anchors.
	 */
	public const string SYNTAX = '[A-Za-z][A-Za-z0-9_-]*/[A-Za-z][A-Za-z0-9_-]*';

	public function __construct(
		public string $namespace,
		public string $name
	) {}

	/**
	 * Returns the name a string refers to, or `null` when it isn't a
	 * valid full name.
	 */
	public static function parse(string $value): ?self
	{
		if (preg_match('#^' . self::SYNTAX . '$#', $value) !== 1) {
			return null;
		}

		[$namespace, $name] = explode('/', $value, 2);

		return new self($namespace, $name);
	}

	/**
	 * Returns the name a template file is for (the file name without its
	 * extension), given the namespaces it could belong to, or `null` when
	 * the file isn't named for one: `{namespace}-{name}`, with the longest
	 * namespace that fits.
	 *
	 * @param list<string> $namespaces
	 */
	public static function fromFileName(string $file, array $namespaces): ?self
	{
		usort($namespaces, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

		foreach ($namespaces as $namespace) {
			if (str_starts_with($file, "{$namespace}-")) {
				$name = self::parse($namespace . '/' . substr($file, strlen($namespace) + 1));

				if ($name !== null) {
					return $name;
				}
			}
		}

		return null;
	}

	/**
	 * Returns the view name of its template.
	 */
	public function view(): string
	{
		return "components/{$this->namespace}-{$this->name}";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function __toString(): string
	{
		return "{$this->namespace}/{$this->name}";
	}
}
