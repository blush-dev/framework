<?php

/**
 * Entry template registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

/**
 * The templates themes declare for single entries (D-783), by name. A
 * theme's provider registers its own in `boot()`; only the active
 * chain's providers run, and `Views::entryTemplates()` offers only the
 * ones with a view in the chain.
 */
final class EntryTemplateRegistry
{
	/**
	 * Templates by name.
	 *
	 * @var array<string, EntryTemplate>
	 */
	private array $templates = [];

	/**
	 * Registers a template, replacing any of the same name.
	 */
	public function register(EntryTemplate $template): void
	{
		$this->templates[$template->name] = $template;
	}

	/**
	 * Removes a template, if it's there.
	 */
	public function remove(string $name): void
	{
		unset($this->templates[$name]);
	}

	/**
	 * Returns whether a name is registered.
	 */
	public function has(string $name): bool
	{
		return isset($this->templates[$name]);
	}

	/**
	 * Returns a template by name, or `null`.
	 */
	public function get(string $name): ?EntryTemplate
	{
		return $this->templates[$name] ?? null;
	}

	/**
	 * Returns every template, by name.
	 *
	 * @return array<string, EntryTemplate>
	 */
	public function all(): array
	{
		return $this->templates;
	}

	/**
	 * Returns the templates offered on a type, in the order they were
	 * registered.
	 *
	 * @return list<EntryTemplate>
	 */
	public function forType(string $type): array
	{
		return array_values(array_filter($this->templates, static fn (EntryTemplate $template): bool => $template->isFor($type)));
	}
}
