<?php

/**
 * Block registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block;

use Closure;
use Countable;
use InvalidArgumentException;
use Override;
use ReflectionClass;
use Blush\Support\RegistrationException;

/**
 * The registered blocks, by full name (D-171, D-172). A plugin or the
 * site registers a block's class in its provider's `boot()`; every
 * block has a class, which declares its `KIND` (D-534). Themes can't
 * (D-532): a block is what content says, and content outlives themes,
 * so a name in a theme's namespace is refused. Themes give blocks a
 * look instead.
 *
 * ```php
 * $blocks->register('acme/tabs', Tabs::class);
 * ```
 *
 * Every block is registered (D-532): one that isn't is unknown, and
 * renders as plain content. Registering makes it known to the admin's
 * inserter and to `block:list` with its text. Third-party names need
 * their namespace, and the `core` namespace holds only the core
 * blocks, which a provider may replace (`register('callout', …)` is
 * `core/callout`). `register()` overwrites; the core blocks are
 * seeded with `registerIf()`.
 *
 * Unlike the other registries (D-019), this one holds definitions (a
 * name and its class), which read what the class declares.
 */
final class BlockRegistry implements Countable
{
	/**
	 * The registered blocks, by full name.
	 *
	 * @var array<string, BlockDefinition>
	 */
	private array $definitions = [];

	/**
	 * @param ?Closure(string): bool $isTheme Whether a namespace is an installed theme's.
	 */
	public function __construct(private readonly ?Closure $isTheme = null)
	{}

	/**
	 * Registers a block's class, replacing any registered under its
	 * name. The class declares everything else (D-534): its `KIND`, which
	 * is required, `CONTENT`, `HOLDS`, `VARIANTS`, and its props.
	 *
	 * @param  class-string<Block> $class
	 * @throws RegistrationException When the name, class, kind, or a variant isn't valid.
	 */
	public function register(string $name, string $class): void
	{
		$parsed = $this->name($name);

		if (! class_exists($class) || ! is_subclass_of($class, Block::class)) {
			throw RegistrationException::notSubclassOf($class, Block::class);
		}

		if (! new ReflectionClass($class)->isInstantiable()) {
			throw RegistrationException::notInstantiable($class);
		}

		try {
			$definition = new BlockDefinition($parsed, $class);
			$definition->variants();
		} catch (InvalidArgumentException $error) {
			throw new RegistrationException(sprintf('The "%s" block: %s', $parsed, $error->getMessage()), 0, $error);
		}

		// Only what wraps blocks is a container, and it's always one.
		if (($definition->kind() === BlockKind::Container) !== ($definition->content() === BlockContent::Blocks)) {
			throw new RegistrationException(sprintf(
				'The "%s" block: only a block that wraps blocks is a container, and one that does is always a container.',
				$parsed
			));
		}

		$this->definitions[(string) $parsed] = $definition;
	}

	/**
	 * Registers a block only when nothing is registered under its
	 * name yet, so the core blocks never replace a provider's.
	 *
	 * @param  class-string<Block> $class
	 * @throws RegistrationException
	 */
	public function registerIf(string $name, string $class): void
	{
		if (! $this->isRegistered($name)) {
			$this->register($name, $class);
		}
	}

	/**
	 * Removes a block, if it's registered.
	 */
	public function unregister(string $name): void
	{
		$parsed = BlockName::parse($name);

		if ($parsed !== null) {
			unset($this->definitions[(string) $parsed]);
		}
	}

	/**
	 * Returns whether a block is registered under a name.
	 */
	public function isRegistered(string $name): bool
	{
		return $this->get($name) !== null;
	}

	/**
	 * Returns the block registered under a name (full, or a core
	 * block's short name), or `null`.
	 */
	public function get(string $name): ?BlockDefinition
	{
		$parsed = BlockName::parse($name);

		return $parsed === null ? null : $this->definitions[(string) $parsed] ?? null;
	}

	/**
	 * Returns every registered block, by full name.
	 *
	 * @return array<string, BlockDefinition>
	 */
	public function all(): array
	{
		return $this->definitions;
	}

	/**
	 * Returns the registered namespaces.
	 *
	 * @return list<string>
	 */
	public function namespaces(): array
	{
		return array_values(array_unique(array_map(
			static fn (BlockDefinition $definition): string => $definition->name->namespace,
			$this->definitions
		)));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(): int
	{
		return count($this->definitions);
	}

	/**
	 * Returns the name a registration is for.
	 *
	 * @throws RegistrationException When it isn't a name a provider may register.
	 */
	private function name(string $name): BlockName
	{
		$parsed = BlockName::parse($name);

		if ($parsed === null) {
			throw new RegistrationException(str_contains($name, '/') || preg_match('#^' . BlockName::SYNTAX . '$#', $name) !== 1
				? sprintf('"%s" is not a valid block name.', $name)
				: sprintf('"%s" needs its namespace, such as "vendor/%s"; only core blocks have short names.', $name, $name));
		}

		if ($parsed->isCore() && BlockType::tryFrom($parsed->name) === null) {
			throw new RegistrationException(sprintf('"%s" is in the "%s" namespace, which only core blocks use.', $name, BlockName::CORE));
		}

		if (! $parsed->isCore() && $this->isTheme !== null && ($this->isTheme)($parsed->namespace)) {
			throw new RegistrationException(sprintf(
				'"%s" is in a theme\'s namespace, and themes can\'t register blocks (D-532): register it from a plugin, or make it a component the theme\'s templates use.',
				$name
			));
		}

		return $parsed;
	}
}
