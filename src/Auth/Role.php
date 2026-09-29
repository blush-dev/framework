<?php

/**
 * Role.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * A named set of capabilities (D-216). `*` grants every capability,
 * including ones extensions add later.
 */
final readonly class Role
{
	/**
	 * The capability that grants all others.
	 */
	public const string ALL = '*';

	/**
	 * @param list<string> $capabilities
	 * @throws AuthException For an invalid name or capability.
	 */
	public function __construct(
		public string $name,
		public string $label,
		public array $capabilities = []
	) {
		if (preg_match('/^[a-z][a-z0-9_-]*$/', $name) !== 1) {
			throw new AuthException(sprintf('"%s" can\'t be a role name; use lowercase letters, digits, "_", and "-".', $name));
		}

		foreach ($capabilities as $capability) {
			if ($capability !== self::ALL && preg_match(Capabilities::NAME, $capability) !== 1) {
				throw new AuthException(sprintf('The "%s" role\'s capability "%s" isn\'t a capability name.', $name, $capability));
			}
		}
	}

	/**
	 * Whether the role grants a capability.
	 */
	public function allows(string $capability): bool
	{
		return in_array(self::ALL, $this->capabilities, true) || in_array($capability, $this->capabilities, true);
	}

	/**
	 * Builds a role from an array (`name`, `label`, `capabilities`).
	 *
	 * @param  array<mixed> $data
	 * @throws AuthException
	 */
	public static function fromArray(array $data): self
	{
		$capabilities = $data['capabilities'] ?? [];

		if (! is_string($data['name'] ?? null) || ! is_array($capabilities) || ! array_is_list($capabilities) || ! array_all($capabilities, static fn (mixed $item): bool => is_string($item))) {
			throw new AuthException('A role needs a "name" and a list of "capabilities".');
		}

		/** @var list<string> $capabilities */
		return new self($data['name'], is_string($data['label'] ?? null) ? $data['label'] : ucfirst($data['name']), $capabilities);
	}

	/**
	 * Returns the role as an array.
	 *
	 * @return array{name: string, label: string, capabilities: list<string>}
	 */
	public function toArray(): array
	{
		return ['name' => $this->name, 'label' => $this->label, 'capabilities' => $this->capabilities];
	}
}
