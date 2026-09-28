<?php

/**
 * Icon registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Icon;

/**
 * Folders of icons for namespaces that aren't a theme or the site: an
 * extension adds its own in a provider's `boot()` (D-187):
 *
 *     $this->container->make(IconRegistry::class)->add('acme', __DIR__ . '/../icons');
 *
 * Each `{name}.svg` in the folder is `{namespace}/{name}`.
 */
final class IconRegistry
{
	/**
	 * Folders, by namespace, in the order they were added.
	 *
	 * @var array<string, list<string>>
	 */
	private array $folders = [];

	/**
	 * Adds a folder of icons to a namespace.
	 */
	public function add(string $namespace, string $folder): void
	{
		$this->folders[$namespace][] = rtrim($folder, '/');
	}

	/**
	 * Returns a namespace's folders.
	 *
	 * @return list<string>
	 */
	public function folders(string $namespace): array
	{
		return $this->folders[$namespace] ?? [];
	}

	/**
	 * Returns every namespace's folders.
	 *
	 * @return array<string, list<string>>
	 */
	public function all(): array
	{
		return $this->folders;
	}
}
