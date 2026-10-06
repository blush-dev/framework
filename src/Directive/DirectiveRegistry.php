<?php

/**
 * Directive registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

use Closure;
use Countable;
use InvalidArgumentException;
use Override;
use ReflectionClass;
use Blush\Support\RegistrationException;

/**
 * The registered directives, by full name (D-171, D-172). A plugin or the
 * site registers a directive's class in its provider's `boot()`; every
 * directive has a class, which declares its `KIND` (D-534). Themes can't
 * (D-532): a directive is what content says, and content outlives themes,
 * so a name in a theme's namespace is refused. Themes give directives a
 * look instead.
 *
 * ```php
 * $directives->register('acme/tabs', Tabs::class);
 * ```
 *
 * Every directive is registered (D-532): one that isn't is unknown, and
 * renders as plain content. Registering makes it known to the admin's
 * inserter and to `directive:list` with its text. Third-party names need
 * their namespace, and the `blush` namespace holds only the core
 * directives, which a provider may replace (`register('callout', …)` is
 * `blush/callout`). `register()` overwrites; the core directives are
 * seeded with `registerIf()`.
 *
 * Unlike the other registries (D-019), this one holds definitions (a
 * name and its class), which read what the class declares.
 */
final class DirectiveRegistry implements Countable
{
	/**
	 * The registered directives, by full name.
	 *
	 * @var array<string, DirectiveDefinition>
	 */
	private array $definitions = [];

	/**
	 * @param ?Closure(string): bool $isTheme Whether a namespace is an installed theme's.
	 */
	public function __construct(private readonly ?Closure $isTheme = null)
	{}

	/**
	 * Registers a directive's class, replacing any registered under its
	 * name. The class declares everything else (D-534): its `KIND`, which
	 * is required, `CONTENT`, `HOLDS`, `VARIANTS`, and its props.
	 *
	 * @param  class-string<Directive> $class
	 * @throws RegistrationException When the name, class, kind, or a variant isn't valid.
	 */
	public function register(string $name, string $class): void
	{
		$parsed = $this->name($name);

		if (! class_exists($class) || ! is_subclass_of($class, Directive::class)) {
			throw RegistrationException::notSubclassOf($class, Directive::class);
		}

		if (! new ReflectionClass($class)->isInstantiable()) {
			throw RegistrationException::notInstantiable($class);
		}

		try {
			$definition = new DirectiveDefinition($parsed, $class);
			$definition->variants();
		} catch (InvalidArgumentException $error) {
			throw new RegistrationException(sprintf('The "%s" directive: %s', $parsed, $error->getMessage()), 0, $error);
		}

		// Only what wraps blocks is a container, and it's always one.
		if (($definition->kind() === DirectiveKind::Container) !== ($definition->content() === DirectiveContent::Blocks)) {
			throw new RegistrationException(sprintf(
				'The "%s" directive: only a directive that wraps blocks is a container, and one that does is always a container.',
				$parsed
			));
		}

		$this->definitions[(string) $parsed] = $definition;
	}

	/**
	 * Registers a directive only when nothing is registered under its
	 * name yet, so the core directives never replace a provider's.
	 *
	 * @param  class-string<Directive> $class
	 * @throws RegistrationException
	 */
	public function registerIf(string $name, string $class): void
	{
		if (! $this->isRegistered($name)) {
			$this->register($name, $class);
		}
	}

	/**
	 * Removes a directive, if it's registered.
	 */
	public function unregister(string $name): void
	{
		$parsed = DirectiveName::parse($name);

		if ($parsed !== null) {
			unset($this->definitions[(string) $parsed]);
		}
	}

	/**
	 * Returns whether a directive is registered under a name.
	 */
	public function isRegistered(string $name): bool
	{
		return $this->get($name) !== null;
	}

	/**
	 * Returns the directive registered under a name (full, or a core
	 * directive's short name), or `null`.
	 */
	public function get(string $name): ?DirectiveDefinition
	{
		$parsed = DirectiveName::parse($name);

		return $parsed === null ? null : $this->definitions[(string) $parsed] ?? null;
	}

	/**
	 * Returns every registered directive, by full name.
	 *
	 * @return array<string, DirectiveDefinition>
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
			static fn (DirectiveDefinition $definition): string => $definition->name->namespace,
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
	private function name(string $name): DirectiveName
	{
		$parsed = DirectiveName::parse($name);

		if ($parsed === null) {
			throw new RegistrationException(str_contains($name, '/') || preg_match('#^' . DirectiveName::SYNTAX . '$#', $name) !== 1
				? sprintf('"%s" is not a valid directive name.', $name)
				: sprintf('"%s" needs its namespace, such as "vendor/%s"; only core directives have short names.', $name, $name));
		}

		if ($parsed->isCore() && DirectiveType::tryFrom($parsed->name) === null) {
			throw new RegistrationException(sprintf('"%s" is in the "%s" namespace, which only core directives use.', $name, DirectiveName::CORE));
		}

		if (! $parsed->isCore() && $this->isTheme !== null && ($this->isTheme)($parsed->namespace)) {
			throw new RegistrationException(sprintf(
				'"%s" is in a theme\'s namespace, and themes can\'t register directives (D-532): register it from a plugin or the site (app/%s), or make it a component the theme\'s templates use.',
				$name,
				$parsed->name
			));
		}

		return $parsed;
	}
}
