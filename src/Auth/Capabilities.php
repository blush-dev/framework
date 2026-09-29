<?php

/**
 * Capability registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * Every capability the site knows, with its label: the built-ins
 * (`Capability`) plus any an extension registers from its provider's
 * `boot()`. The administrator role's `*` grants all of them, and the admin
 * lists them by label.
 */
final class Capabilities
{
	/**
	 * What a capability name may be: dotted lowercase words.
	 */
	public const string NAME = '/^[a-z][a-z0-9_-]*(\.[a-z0-9_-]+)*$/';

	/**
	 * Labels by capability name.
	 *
	 * @var array<string, string>
	 */
	private array $labels = [];

	/**
	 * Builds the registry with the built-in capabilities.
	 */
	public static function withBuiltIns(): self
	{
		$capabilities = new self();

		foreach (Capability::cases() as $capability) {
			$capabilities->register($capability->value, $capability->label());
		}

		return $capabilities;
	}

	/**
	 * Registers a capability, or relabels one.
	 *
	 * @throws AuthException For an invalid name.
	 */
	public function register(string $name, string $label): void
	{
		if (preg_match(self::NAME, $name) !== 1) {
			throw new AuthException(sprintf('"%s" can\'t be a capability; use dotted lowercase words such as "shop.orders.edit".', $name));
		}

		$this->labels[$name] = $label;
	}

	/**
	 * Whether a capability is registered.
	 */
	public function has(string $name): bool
	{
		return isset($this->labels[$name]);
	}

	/**
	 * Returns every capability's label, by name.
	 *
	 * @return array<string, string>
	 */
	public function all(): array
	{
		return $this->labels;
	}
}
