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

use NoDiscard;

/**
 * A named set of capabilities (D-216). `*` grants every capability,
 * including ones extensions add later. The description says in a line
 * what the role is for, as the admin shows it (D-312).
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
		public array $capabilities = [],
		public string $description = ''
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
	 * Whether the role grants a capability: it has `*`, the capability,
	 * or one with a `*` word where the capability has any word
	 * (`content.*.edit` grants `content.post.edit`, D-359).
	 */
	public function allows(string $capability): bool
	{
		return in_array(self::ALL, $this->capabilities, true)
			|| in_array($capability, $this->capabilities, true)
			|| array_any($this->capabilities, static fn (string $granted): bool => self::matches($granted, $capability));
	}

	/**
	 * Whether a capability with `*` words matches another.
	 */
	private static function matches(string $pattern, string $capability): bool
	{
		if (! str_contains($pattern, '*')) {
			return false;
		}

		$words = explode('.', $capability);
		$parts = explode('.', $pattern);

		return count($words) === count($parts)
			&& array_all($parts, static fn (string $part, int $index): bool => $part === '*' || $part === $words[$index]);
	}

	/**
	 * Returns a copy with other capabilities.
	 *
	 * @param  list<string> $capabilities
	 * @throws AuthException For an invalid capability.
	 */
	#[NoDiscard]
	public function withCapabilities(array $capabilities): self
	{
		return new self($this->name, $this->label, array_values(array_unique($capabilities)), $this->description);
	}

	/**
	 * Builds a role from an array (`name`, `label`, `capabilities`, and
	 * optionally `description`).
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
		return new self(
			$data['name'],
			is_string($data['label'] ?? null) ? $data['label'] : ucfirst($data['name']),
			$capabilities,
			is_string($data['description'] ?? null) ? $data['description'] : ''
		);
	}

	/**
	 * Returns the role as an array, without an empty description.
	 *
	 * @return array{name: string, label: string, capabilities: list<string>, description?: string}
	 */
	public function toArray(): array
	{
		return [
			'name'         => $this->name,
			'label'        => $this->label,
			'capabilities' => $this->capabilities,
			...($this->description === '' ? [] : ['description' => $this->description])
		];
	}
}
