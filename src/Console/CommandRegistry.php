<?php

/**
 * Command registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Blush\Console\Input\Signature;

/**
 * Maps command names (and aliases) to command classes. Commands have no
 * interface to check against, so this doesn't extend `Support\Registry`;
 * instead, each class is validated by reading its signature, which also
 * supplies its name. Classes aren't built until a command runs.
 *
 * Extensions and sites add commands by tagging their classes with `TAG` in
 * a provider's `TAGS` constant:
 *
 *     protected const array TAGS = [CommandRegistry::TAG => [Deploy::class]];
 *
 * @implements IteratorAggregate<string, class-string>
 */
final class CommandRegistry implements IteratorAggregate, Countable
{
	/**
	 * The container tag for command classes.
	 */
	public const string TAG = 'blush.commands';

	/**
	 * Signatures keyed by command name.
	 *
	 * @var array<string, Signature>
	 */
	private array $signatures = [];

	/**
	 * Command names keyed by alias.
	 *
	 * @var array<string, string>
	 */
	private array $aliases = [];

	/**
	 * Registers a command class under the name in its `#[Command]`
	 * attribute, replacing any command already registered under it.
	 *
	 * @throws InvalidCommand When the class isn't a valid command.
	 */
	public function register(string $class): void
	{
		$signature = Signature::fromClass($class);

		$this->signatures[$signature->name] = $signature;

		foreach ($signature->aliases as $alias) {
			$this->aliases[$alias] = $signature->name;
		}
	}

	/**
	 * Registers a command class only when its name is free, so built-ins
	 * never replace an extension's command.
	 *
	 * @throws InvalidCommand When the class isn't a valid command.
	 */
	public function registerIf(string $class): void
	{
		if (! $this->has(Signature::fromClass($class)->name)) {
			$this->register($class);
		}
	}

	/**
	 * Removes a command by name.
	 */
	public function unregister(string $name): void
	{
		unset($this->signatures[$name]);

		$this->aliases = array_filter($this->aliases, static fn (string $target): bool => $target !== $name);
	}

	/**
	 * Whether a command answers to the name or alias.
	 */
	public function has(string $name): bool
	{
		return $this->signature($name) !== null;
	}

	/**
	 * Returns the class for a command name or alias.
	 *
	 * @return ?class-string
	 */
	public function get(string $name): ?string
	{
		return $this->signature($name)?->class;
	}

	/**
	 * Returns the signature for a command name or alias. A name wins over
	 * an alias.
	 */
	public function signature(string $name): ?Signature
	{
		return $this->signatures[$name] ?? $this->signatures[$this->aliases[$name] ?? ''] ?? null;
	}

	/**
	 * Returns every signature, sorted by name.
	 *
	 * @return array<string, Signature>
	 */
	public function signatures(): array
	{
		$signatures = $this->signatures;
		ksort($signatures);

		return $signatures;
	}

	/**
	 * Returns every name and alias, for suggestions.
	 *
	 * @return list<string>
	 */
	public function names(): array
	{
		return [...array_keys($this->signatures), ...array_keys($this->aliases)];
	}

	/**
	 * Returns every command class keyed by name.
	 *
	 * @return array<string, class-string>
	 */
	public function all(): array
	{
		return array_map(static fn (Signature $signature): string => $signature->class, $this->signatures);
	}

	/**
	 * @inheritDoc
	 *
	 * @return ArrayIterator<string, class-string>
	 */
	#[Override]
	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->all());
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(): int
	{
		return count($this->signatures);
	}
}
